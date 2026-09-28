<?php
/**
 * Session meta helpers.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Data structure helpers.
 */
class MBS_Meta {

	const META_DATA   = '_mbs_data';
	const META_VENUE  = '_mbs_venue_data';
	const META_REPORT = '_mbs_report_data';

	/**
	 * Default session payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_session_data() {
		return array(
			'title'                => '',
			'venue_name'           => '',
			'venue_location'       => '',
			'postcode'             => '',
			'lake_swim'            => '',
			'start_at'             => '',
			'end_at'               => '',
			'planned_end_at'       => '',
			'status'               => 'draft',
			'weather_snapshots'    => array(),
			'conditions'           => array(),
			'bait_used'            => array(),
			'baiting_log'          => array(),
			'catches'              => array(),
			'rigs'                 => array(),
			'spots'                => array(),
			'swim_notes'           => array(),
			'notes'                => '',
			'photos'               => array(),
			'activity_log'         => array(),
			'outcome'              => array(),
			'lessons'              => '',
			'reorder_product_ids'  => array(),
			'tactics'              => array(),
			'venue_id'             => 0,
			'location_lat'         => null,
			'location_lng'         => null,
		);
	}

	/**
	 * @param int $post_id Session post ID.
	 * @return array<string, mixed>
	 */
	public static function get_session_data($post_id) {
		$raw = get_post_meta($post_id, self::META_DATA, true);
		if (! is_array($raw)) {
			$raw = array();
		}
		return array_merge(self::default_session_data(), $raw);
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Data.
	 * @return void
	 */
	public static function update_session_data($post_id, array $data) {
		$merged = array_merge(self::default_session_data(), $data);
		update_post_meta($post_id, self::META_DATA, $merged);
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $patch   Partial update.
	 * @return array<string, mixed>
	 */
	public static function patch_session_data($post_id, array $patch) {
		$current = self::get_session_data($post_id);
		$merged  = array_merge($current, $patch);
		self::update_session_data($post_id, $merged);
		return $merged;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function session_to_api($post_id) {
		$post = get_post($post_id);
		if (! $post || MBS_CPT::SESSION !== $post->post_type) {
			return array();
		}
		$data = self::get_session_data($post_id);
		$payload = array(
			'id'         => (int) $post_id,
			'title'      => (string) $post->post_title,
			'created_at' => (string) $post->post_date_gmt,
			'updated_at' => (string) $post->post_modified_gmt,
			'data'       => $data,
			'stats'      => self::compute_session_stats($data),
		);
		if (class_exists('MBS_Companion')) {
			$payload['companion'] = MBS_Companion::build_for_session($data, $post_id);
			if ('ended' === ($data['status'] ?? '') || ! empty($data['end_at'])) {
				$payload['end_summary'] = MBS_Companion::end_summary($data);
			}
		}
		return $payload;
	}

	/**
	 * @param array<string, mixed> $data Session data.
	 * @return array<string, mixed>
	 */
	public static function compute_session_stats(array $data) {
		$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
		$best_lb = 0.0;
		$pb_count = 0;
		foreach ($catches as $catch) {
			if (! is_array($catch)) {
				continue;
			}
			$lb = isset($catch['weight_lb']) ? (float) $catch['weight_lb'] : 0;
			$oz = isset($catch['weight_oz']) ? (float) $catch['weight_oz'] : 0;
			$total_lb = $lb + ($oz / 16);
			if ($total_lb > $best_lb) {
				$best_lb = $total_lb;
			}
			if (! empty($catch['is_pb'])) {
				++$pb_count;
			}
		}
		return array(
			'catch_count' => count($catches),
			'best_fish_lb' => round($best_lb, 2),
			'pb_count'    => $pb_count,
		);
	}

	/**
	 * @param string $prefix Prefix for ID.
	 * @return string
	 */
	public static function generate_entry_id($prefix = 'entry') {
		return sanitize_key($prefix . '_' . wp_generate_uuid4());
	}

	/**
	 * @param int    $post_id Session ID.
	 * @param string $list_key List key in data.
	 * @param array<string, mixed> $entry Entry.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function append_list_entry($post_id, $list_key, array $entry) {
		$data = self::get_session_data($post_id);
		if (! isset($data[ $list_key ]) || ! is_array($data[ $list_key ])) {
			$data[ $list_key ] = array();
		}
		if (empty($entry['id'])) {
			$entry['id'] = self::generate_entry_id($list_key);
		}
		$entry['created_at'] = current_time('c');
		$data[ $list_key ][] = $entry;
		self::update_session_data($post_id, $data);
		return $entry;
	}

	/**
	 * @param int    $post_id Session ID.
	 * @param string $list_key List key.
	 * @param string $entry_id Entry ID.
	 * @param array<string, mixed> $patch Patch.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_list_entry($post_id, $list_key, $entry_id, array $patch) {
		$data = self::get_session_data($post_id);
		if (! isset($data[ $list_key ]) || ! is_array($data[ $list_key ])) {
			return new WP_Error('mbs_not_found', __('Entry not found.', 'mad-baits-session'), array('status' => 404));
		}
		foreach ($data[ $list_key ] as $i => $entry) {
			if (! is_array($entry) || (string) ($entry['id'] ?? '') !== (string) $entry_id) {
				continue;
			}
			$data[ $list_key ][ $i ] = array_merge($entry, $patch);
			self::update_session_data($post_id, $data);
			return $data[ $list_key ][ $i ];
		}
		return new WP_Error('mbs_not_found', __('Entry not found.', 'mad-baits-session'), array('status' => 404));
	}

	/**
	 * @param int $post_id Venue post ID.
	 * @return array<string, mixed>
	 */
	public static function get_venue_data($post_id) {
		$raw = get_post_meta($post_id, self::META_VENUE, true);
		return is_array($raw) ? $raw : array();
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $data    Data.
	 * @return void
	 */
	public static function update_venue_data($post_id, array $data) {
		update_post_meta($post_id, self::META_VENUE, $data);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function venue_to_api($post_id) {
		$post = get_post($post_id);
		if (! $post || MBS_CPT::VENUE !== $post->post_type) {
			return array();
		}
		return array(
			'id'    => (int) $post_id,
			'title' => (string) $post->post_title,
			'data'  => self::get_venue_data($post_id),
		);
	}
}
