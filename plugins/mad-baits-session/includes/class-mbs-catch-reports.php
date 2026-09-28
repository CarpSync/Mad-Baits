<?php
/**
 * Bridge Session App catches to WordPress Catch Reports + media email.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Session catch report sync.
 */
class MBS_Catch_Reports {

	/**
	 * @return void
	 */
	public static function init() {
		// Loaded via plugin bootstrap.
	}

	/**
	 * Check if session catch already has a linked report.
	 *
	 * @param int    $session_id Session post ID.
	 * @param string $catch_entry_id Catch entry ID.
	 * @return int Report post ID or 0.
	 */
	public static function get_linked_report_id($session_id, $catch_entry_id) {
		$data = MBS_Meta::get_session_data($session_id);
		$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
		foreach ($catches as $catch) {
			if (! is_array($catch) || (string) ($catch['id'] ?? '') !== (string) $catch_entry_id) {
				continue;
			}
			return absint($catch['catch_report_id'] ?? 0);
		}
		return 0;
	}

	/**
	 * Build weather summary string from session data.
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return string
	 */
	public static function build_weather_summary(array $session_data) {
		$snaps = isset($session_data['weather_snapshots']) && is_array($session_data['weather_snapshots'])
			? $session_data['weather_snapshots']
			: array();
		if (empty($snaps)) {
			return '';
		}
		$last = end($snaps);
		if (! is_array($last)) {
			return '';
		}
		$parts = array_filter(
			array(
				isset($last['summary']) ? (string) $last['summary'] : '',
				isset($last['temp_c']) ? (string) $last['temp_c'] . '°C' : '',
				isset($last['wind']) ? 'Wind ' . (string) $last['wind'] : '',
			)
		);
		return implode(' · ', $parts);
	}

	/**
	 * Submit catch report from Session App.
	 *
	 * @param int                  $session_id Session ID.
	 * @param array<string, mixed> $params     Request params.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function submit_from_session($session_id, array $params) {
		if (! function_exists('mad_baits_submit_catch_report')) {
			return new WP_Error('mbs_catch_report_unavailable', __('Catch report system is unavailable.', 'mad-baits-session'), array('status' => 500));
		}

		$session_data = MBS_Meta::get_session_data($session_id);
		$catch_entry_id = sanitize_text_field((string) ($params['session_catch_id'] ?? ''));
		if ('' !== $catch_entry_id) {
			$existing = self::get_linked_report_id($session_id, $catch_entry_id);
			if ($existing > 0) {
				return new WP_Error('mbs_report_exists', __('This catch has already been reported.', 'mad-baits-session'), array('status' => 409));
			}
		}

		$user = wp_get_current_user();
		$lb   = isset($params['weight_lb']) ? (float) $params['weight_lb'] : 0;
		$oz   = isset($params['weight_oz']) ? (float) $params['weight_oz'] : 0;

		if ('' === sanitize_text_field((string) ($params['session_length'] ?? ''))) {
			$params['session_length'] = self::compute_session_length($session_data);
		}

		$payload = array(
			'angler_name'       => sanitize_text_field((string) ($params['angler_name'] ?? ($user->exists() ? $user->display_name : ''))),
			'angler_email'      => sanitize_email((string) ($params['angler_email'] ?? ($user->exists() ? $user->user_email : ''))),
			'angler_phone'      => sanitize_text_field((string) ($params['angler_phone'] ?? '')),
			'fish_weight'       => sanitize_text_field((string) ($params['fish_weight'] ?? '')),
			'weight_lb'         => $lb,
			'weight_oz'         => $oz,
			'bait_used'         => sanitize_text_field((string) ($params['bait_used'] ?? '')),
			'hookbait'          => sanitize_text_field((string) ($params['hookbait'] ?? '')),
			'rig'               => sanitize_text_field((string) ($params['rig'] ?? '')),
			'venue'             => sanitize_text_field((string) ($params['venue'] ?? $session_data['venue_name'] ?? '')),
			'swim'              => sanitize_text_field((string) ($params['swim'] ?? $session_data['lake_swim'] ?? '')),
			'date_caught'       => sanitize_text_field((string) ($params['date_caught'] ?? $params['catch_time'] ?? current_time('c'))),
			'session_length'    => sanitize_text_field((string) ($params['session_length'] ?? '')),
			'weather_summary'   => sanitize_textarea_field((string) ($params['weather_summary'] ?? self::build_weather_summary($session_data))),
			'story'             => sanitize_textarea_field((string) ($params['story'] ?? $params['private_note'] ?? '')),
			'marketing_consent' => ! empty($params['marketing_consent']) || ! empty($params['public_consent']),
			'species'           => sanitize_text_field((string) ($params['species'] ?? 'Carp')),
			'session_id'        => $session_id,
			'session_catch_id'  => $catch_entry_id,
			'source'            => 'session_app',
			'photo_ids'         => array(),
		);

		$photo_id = absint($params['photo_id'] ?? 0);
		if ($photo_id > 0) {
			$owner = (int) get_post_meta($photo_id, '_mbs_owner', true);
			if ($owner === get_current_user_id() || current_user_can('manage_options')) {
				$payload['photo_ids'] = array($photo_id);
			}
		}

		$result = mad_baits_submit_catch_report($payload);
		if (is_wp_error($result)) {
			return $result;
		}

		$report_id = (int) ($result['catch_report_id'] ?? 0);
		if ('' !== $catch_entry_id && $report_id > 0) {
			MBS_Meta::update_list_entry(
				$session_id,
				'catches',
				$catch_entry_id,
				array(
					'catch_report_id'        => $report_id,
					'media_report_submitted' => true,
					'media_report_at'        => current_time('c'),
				)
			);
		} elseif ($report_id > 0 && empty($catch_entry_id)) {
			$entry = array(
				'catch_time'             => sanitize_text_field((string) ($params['catch_time'] ?? current_time('c'))),
				'species'                => $payload['species'],
				'weight_lb'              => $lb,
				'weight_oz'              => $oz,
				'bait_used'              => $payload['bait_used'],
				'hookbait'               => $payload['hookbait'],
				'rig'                    => $payload['rig'],
				'swim'                   => $payload['swim'],
				'photo_id'               => $photo_id,
				'catch_report_id'        => $report_id,
				'media_report_submitted' => true,
				'media_report_at'        => current_time('c'),
			);
			MBS_Meta::append_list_entry($session_id, 'catches', $entry);
		}

		return array_merge(
			$result,
			array(
				'session' => MBS_Meta::session_to_api($session_id),
			)
		);
	}

	/**
	 * Human-readable session duration from session start.
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return string
	 */
	public static function compute_session_length(array $session_data) {
		$start = isset($session_data['start_at']) ? (string) $session_data['start_at'] : '';
		if ('' === $start) {
			return '';
		}
		$start_ts = strtotime($start);
		if (! $start_ts) {
			return '';
		}
		$mins = max(0, (int) floor((time() - $start_ts) / 60));
		$hours = (int) floor($mins / 60);
		$remain = $mins % 60;
		if ($hours > 0) {
			return sprintf('%dh %dm', $hours, $remain);
		}
		return sprintf('%dm', $remain);
	}
}
