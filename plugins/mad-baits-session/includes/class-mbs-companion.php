<?php
/**
 * Session companion: timeline, bait totals, weather tips, bite windows.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Live session companion data (no WooCommerce side effects).
 */
class MBS_Companion {

	/** @var array<string, string> */
	const ACTIVITY_LABELS = array(
		'liner'        => 'Liner',
		'lost_fish'    => 'Lost fish',
		'fish_showing' => 'Fish showing',
		'recast'       => 'Recast',
		'baited_spot'  => 'Baited spot',
		'rig_changed'  => 'Rig changed',
		'moved_swim'   => 'Moved swim',
		'note'         => 'Note',
	);

	/**
	 * Build companion payload for active/ended session views.
	 *
	 * @param array<string, mixed> $data Session data.
	 * @param int                  $session_id Session post ID.
	 * @return array<string, mixed>
	 */
	public static function build_for_session(array $data, $session_id = 0) {
		$weather = self::latest_weather($data);
		return array(
			'bait_totals'    => self::compute_bait_totals($data),
			'timeline'       => self::build_timeline($data, $session_id),
			'weather_tips'   => self::weather_tips($weather, $data),
			'bite_windows'   => self::bite_windows($weather),
			'session_counts' => self::session_counts($data),
			'disclaimer'     => __('Helpful suggestions from your session data — not guaranteed bite predictions.', 'mad-baits-session'),
		);
	}

	/**
	 * @param array<string, mixed> $data Session data.
	 * @return array<string, mixed>|null
	 */
	public static function latest_weather(array $data) {
		$snaps = isset($data['weather_snapshots']) && is_array($data['weather_snapshots']) ? $data['weather_snapshots'] : array();
		if (empty($snaps)) {
			return null;
		}
		$last = end($snaps);
		return is_array($last) ? $last : null;
	}

	/**
	 * @param array<string, mixed> $data Session data.
	 * @return array<string, mixed>
	 */
	public static function compute_bait_totals(array $data) {
		$rows   = isset($data['bait_used']) && is_array($data['bait_used']) ? $data['bait_used'] : array();
		$lines  = array();
		$grams  = 0;

		foreach ($rows as $row) {
			if (! is_array($row)) {
				continue;
			}
			$name = trim((string) ($row['name'] ?? $row['range'] ?? ''));
			if ('' === $name) {
				$name = __('Bait', 'mad-baits-session');
			}
			$amount = trim((string) ($row['amount_used'] ?? $row['amount_taken'] ?? ''));
			$grams += self::parse_amount_to_grams($amount);
			$lines[] = array(
				'name'     => $name,
				'hookbait' => trim((string) ($row['hookbait'] ?? '')),
				'amount'   => $amount,
				'type'     => trim((string) ($row['bait_type'] ?? '')),
			);
		}

		return array(
			'lines'        => $lines,
			'entry_count'  => count($lines),
			'estimated_g'  => $grams > 0 ? $grams : null,
			'estimated_label' => $grams > 0 ? self::format_grams($grams) : null,
		);
	}

	/**
	 * @param string $amount Amount string.
	 * @return int Grams estimate.
	 */
	private static function parse_amount_to_grams($amount) {
		$amount = strtolower(trim((string) $amount));
		if ('' === $amount) {
			return 0;
		}
		if (preg_match('/(\d+(?:\.\d+)?)\s*kg/', $amount, $m)) {
			return (int) round((float) $m[1] * 1000);
		}
		if (preg_match('/(\d+(?:\.\d+)?)\s*g/', $amount, $m)) {
			return (int) round((float) $m[1]);
		}
		if (false !== strpos($amount, 'handful')) {
			return 150;
		}
		if (false !== strpos($amount, 'spod')) {
			return 2000;
		}
		if (false !== strpos($amount, 'solid bag')) {
			return 1000;
		}
		return 0;
	}

	/**
	 * @param int $grams Grams.
	 * @return string
	 */
	private static function format_grams($grams) {
		if ($grams >= 1000) {
			return round($grams / 1000, 1) . 'kg';
		}
		return $grams . 'g';
	}

	/**
	 * Unified chronological timeline.
	 *
	 * @param array<string, mixed> $data Session data.
	 * @param int                  $session_id Session ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_timeline(array $data, $session_id = 0) {
		$events = array();

		if (! empty($data['start_at'])) {
			$events[] = self::event('session_started', $data['start_at'], __('Session started', 'mad-baits-session'), $data['venue_name'] ?? '');
		}

		$lists = array(
			'bait_used'    => array('type' => 'bait_added', 'title' => __('Bait added', 'mad-baits-session'), 'detail' => 'name'),
			'baiting_log'  => array('type' => 'baiting', 'title' => __('Baiting', 'mad-baits-session'), 'detail' => 'bait'),
			'catches'      => array('type' => 'catch', 'title' => __('Catch logged', 'mad-baits-session'), 'detail' => 'species'),
			'rigs'         => array('type' => 'rig', 'title' => __('Rig logged', 'mad-baits-session'), 'detail' => 'name'),
			'activity_log' => array('type' => 'activity', 'title' => '', 'detail' => 'notes'),
		);

		foreach ($lists as $key => $cfg) {
			$items = isset($data[ $key ]) && is_array($data[ $key ]) ? $data[ $key ] : array();
			foreach ($items as $item) {
				if (! is_array($item)) {
					continue;
				}
				$time = (string) ($item['catch_time'] ?? $item['time'] ?? $item['created_at'] ?? $item['captured_at'] ?? '');
				if ('' === $time) {
					$time = (string) ($data['start_at'] ?? '');
				}
				$detail_key = $cfg['detail'];
				$detail     = trim((string) ($item[ $detail_key ] ?? ''));
				if ('catch' === $cfg['type']) {
					$lb = isset($item['weight_lb']) ? (float) $item['weight_lb'] : 0;
					$oz = isset($item['weight_oz']) ? (float) $item['weight_oz'] : 0;
					if ($lb > 0 || $oz > 0) {
						$detail = trim($lb . 'lb' . ($oz > 0 ? ' ' . $oz . 'oz' : '') . ($detail ? ' · ' . $detail : ''));
					}
				}
				if ('activity' === $cfg['type']) {
					$atype = sanitize_key((string) ($item['type'] ?? ''));
					$cfg['title'] = isset(self::ACTIVITY_LABELS[ $atype ]) ? self::ACTIVITY_LABELS[ $atype ] : ucfirst($atype);
				}
				if ('bait_added' === $cfg['type'] && '' !== $detail) {
					$amt = trim((string) ($item['amount_used'] ?? ''));
					if ($amt) {
						$detail .= ' · ' . $amt;
					}
				}
				$events[] = self::event($cfg['type'], $time, $cfg['title'], $detail, $item['id'] ?? '');
			}
		}

		$photos = isset($data['photos']) && is_array($data['photos']) ? $data['photos'] : array();
		foreach ($photos as $photo) {
			if (! is_array($photo)) {
				continue;
			}
			$events[] = self::event(
				'photo',
				(string) ($photo['captured_at'] ?? $photo['created_at'] ?? ''),
				__('Photo added', 'mad-baits-session'),
				trim((string) ($photo['bait_used'] ?? $photo['weight'] ?? ''))
			);
		}

		$weather = isset($data['weather_snapshots']) && is_array($data['weather_snapshots']) ? $data['weather_snapshots'] : array();
		foreach ($weather as $snap) {
			if (! is_array($snap)) {
				continue;
			}
			$summary = trim((string) ($snap['description'] ?? ''));
			if (isset($snap['temperature']) && is_numeric($snap['temperature'])) {
				$summary = trim($summary . ' · ' . $snap['temperature'] . '°C');
			}
			$events[] = self::event(
				'weather',
				(string) ($snap['captured_at'] ?? ''),
				__('Weather update', 'mad-baits-session'),
				$summary
			);
		}

		if (! empty($data['end_at'])) {
			$events[] = self::event('session_ended', (string) $data['end_at'], __('Session ended', 'mad-baits-session'), '');
		}

		usort(
			$events,
			function ($a, $b) {
				return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
			}
		);

		return array_slice($events, 0, 40);
	}

	/**
	 * @param string $type Type.
	 * @param string $time ISO time.
	 * @param string $title Title.
	 * @param string $detail Detail.
	 * @param string $entry_id Entry ID.
	 * @return array<string, mixed>
	 */
	private static function event($type, $time, $title, $detail, $entry_id = '') {
		return array(
			'type'     => $type,
			'time'     => $time,
			'title'    => $title,
			'detail'   => $detail,
			'entry_id' => (string) $entry_id,
		);
	}

	/**
	 * @param array<string, mixed>|null $weather Latest weather.
	 * @param array<string, mixed>      $data Session data.
	 * @return array<int, string>
	 */
	public static function weather_tips($weather, array $data) {
		$tips = array();
		if (! is_array($weather)) {
			$tips[] = __('Log bank weather to unlock condition-based tips for this session.', 'mad-baits-session');
			return $tips;
		}

		$temp = isset($weather['temperature']) ? (float) $weather['temperature'] : null;
		$wind = isset($weather['wind_speed']) ? (float) $weather['wind_speed'] : null;
		$pressure = isset($weather['pressure']) ? (float) $weather['pressure'] : null;
		$clouds = isset($weather['cloud_cover']) ? (int) $weather['cloud_cover'] : null;
		$wind_dir = (string) ($weather['wind_direction'] ?? '');

		if (null !== $temp && $temp < 8) {
			$tips[] = __('Cold conditions: consider a lower feed approach with a high-attract hookbait.', 'mad-baits-session');
		} elseif (null !== $temp && $temp >= 16) {
			$tips[] = __('Warmer water: fish may be more active — a steady feed line can help hold them.', 'mad-baits-session');
		}

		if (null !== $wind && $wind >= 6) {
			$tips[] = __('Strong wind: worth checking windward margins and sheltered bays.', 'mad-baits-session');
			if ('' !== $wind_dir) {
				$tips[] = sprintf(
					/* translators: %s: compass wind direction */
					__('Wind from %s — consider how it pushes food and scent down the lake.', 'mad-baits-session'),
					$wind_dir
				);
			}
		}

		if (null !== $pressure && $pressure < 1005) {
			$tips[] = __('Pressure dropping: worth watching for a possible feeding spell.', 'mad-baits-session');
		} elseif (null !== $pressure && $pressure >= 1020) {
			$tips[] = __('High pressure / bright conditions: zigs, shade and low-light periods may outperform midday.', 'mad-baits-session');
		}

		if (null !== $clouds && $clouds < 25) {
			$tips[] = __('Clear skies: fish may be cautious in bright sun — edge times can be key.', 'mad-baits-session');
		}

		$clarity = (string) ($data['conditions']['water_clarity'] ?? '');
		if ('' !== $clarity && false !== stripos($clarity, 'coloured')) {
			$tips[] = __('Coloured water: stronger-smelling hookbaits and shorter rigs often work well.', 'mad-baits-session');
		}

		if (empty($tips)) {
			$tips[] = __('Conditions look mixed — keep notes on what produces bites today.', 'mad-baits-session');
		}

		return array_slice($tips, 0, 5);
	}

	/**
	 * @param array<string, mixed>|null $weather Weather snapshot.
	 * @return array<int, array<string, string>>
	 */
	public static function bite_windows($weather) {
		$windows = array();
		$sunrise = isset($weather['sunrise']) ? (int) $weather['sunrise'] : 0;
		$sunset  = isset($weather['sunset']) ? (int) $weather['sunset'] : 0;

		if ($sunrise > 0) {
			$windows[] = array(
				'label' => __('Dawn window', 'mad-baits-session'),
				'time'  => wp_date(get_option('time_format'), $sunrise),
				'hint'  => __('Worth watching — suggested dawn feeding spell.', 'mad-baits-session'),
			);
			$dawn_end = $sunrise + 2 * HOUR_IN_SECONDS;
			$windows[] = array(
				'label' => __('Early morning', 'mad-baits-session'),
				'time'  => wp_date(get_option('time_format'), $sunrise) . ' – ' . wp_date(get_option('time_format'), $dawn_end),
				'hint'  => __('Likely feeding spell after first light.', 'mad-baits-session'),
			);
		} else {
			$windows[] = array(
				'label' => __('Dawn window', 'mad-baits-session'),
				'time'  => __('~first light', 'mad-baits-session'),
				'hint'  => __('Worth watching — refresh live weather for exact sunrise.', 'mad-baits-session'),
			);
		}

		if ($sunset > 0) {
			$windows[] = array(
				'label' => __('Evening window', 'mad-baits-session'),
				'time'  => wp_date(get_option('time_format'), $sunset - HOUR_IN_SECONDS) . ' – ' . wp_date(get_option('time_format'), $sunset + HOUR_IN_SECONDS),
				'hint'  => __('Suggested window as light fades.', 'mad-baits-session'),
			);
			$windows[] = array(
				'label' => __('Overnight', 'mad-baits-session'),
				'time'  => wp_date(get_option('time_format'), $sunset) . '+',
				'hint'  => __('Many venues pick up after dark — worth watching.', 'mad-baits-session'),
			);
		} else {
			$windows[] = array(
				'label' => __('Evening window', 'mad-baits-session'),
				'time'  => __('~dusk', 'mad-baits-session'),
				'hint'  => __('Suggested window — enable live weather for sunset times.', 'mad-baits-session'),
			);
		}

		return $windows;
	}

	/**
	 * @param array<string, mixed> $data Session data.
	 * @return array<string, int>
	 */
	public static function session_counts(array $data) {
		$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
		$photos  = 0;
		foreach ($catches as $c) {
			if (is_array($c) && ! empty($c['photo_id'])) {
				++$photos;
			}
			if (is_array($c) && ! empty($c['photo_ids']) && is_array($c['photo_ids'])) {
				$photos += count($c['photo_ids']);
			}
		}
		$session_photos = isset($data['photos']) && is_array($data['photos']) ? count($data['photos']) : 0;
		$notes = trim((string) ($data['notes'] ?? ''));
		$note_lines = '' !== $notes ? substr_count($notes, "\n") + 1 : 0;

		return array(
			'catches'      => count($catches),
			'photos'       => max($photos, $session_photos),
			'bait_entries' => isset($data['bait_used']) && is_array($data['bait_used']) ? count($data['bait_used']) : 0,
			'notes'        => $note_lines,
			'activities'   => isset($data['activity_log']) && is_array($data['activity_log']) ? count($data['activity_log']) : 0,
		);
	}

	/**
	 * End-of-session summary for UI.
	 *
	 * @param array<string, mixed> $data Session data.
	 * @return array<string, mixed>
	 */
	public static function end_summary(array $data) {
		$stats = MBS_Meta::compute_session_stats($data);
		$bait  = self::compute_bait_totals($data);
		$best_bait = '';
		$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
		$bait_hits = array();
		foreach ($catches as $catch) {
			if (! is_array($catch)) {
				continue;
			}
			$key = trim((string) ($catch['bait_used'] ?? ''));
			if ('' !== $key) {
				$bait_hits[ $key ] = isset($bait_hits[ $key ] ) ? $bait_hits[ $key ] + 1 : 1;
			}
		}
		$max = 0;
		foreach ($bait_hits as $k => $v) {
			if ($v > $max) {
				$max = $v;
				$best_bait = $k;
			}
		}

		$duration = '';
		if (! empty($data['start_at'])) {
			$start = strtotime((string) $data['start_at']);
			$end   = ! empty($data['end_at']) ? strtotime((string) $data['end_at']) : time();
			if ($start && $end > $start) {
				$mins = (int) floor(($end - $start) / 60);
				$duration = sprintf('%dh %dm', (int) floor($mins / 60), $mins % 60);
			}
		}

		$weather = self::latest_weather($data);
		$weather_label = '';
		if (is_array($weather)) {
			$parts = array_filter(
				array(
					$weather['description'] ?? '',
					isset($weather['temperature']) ? $weather['temperature'] . '°C' : '',
					isset($weather['wind_direction'], $weather['wind_speed']) ? $weather['wind_direction'] . ' ' . $weather['wind_speed'] . 'm/s' : '',
				)
			);
			$weather_label = implode(' · ', $parts);
		}

		return array(
			'duration'       => $duration,
			'venue'          => (string) ($data['venue_name'] ?? ''),
			'swim'           => (string) ($data['lake_swim'] ?? ''),
			'catch_count'    => (int) $stats['catch_count'],
			'best_fish_lb'   => (float) $stats['best_fish_lb'],
			'bait_total'     => $bait['estimated_label'],
			'best_bait'      => $best_bait,
			'notes_count'    => self::session_counts($data)['notes'],
			'photos_count'   => self::session_counts($data)['photos'],
			'weather'        => $weather_label,
		);
	}

	/**
	 * @param array<string, mixed> $params Raw params.
	 * @return array<string, mixed>
	 */
	public static function sanitize_activity(array $params) {
		$type = sanitize_key((string) ($params['type'] ?? 'note'));
		if (! isset(self::ACTIVITY_LABELS[ $type ])) {
			$type = 'note';
		}
		return array(
			'type'  => $type,
			'time'  => sanitize_text_field((string) ($params['time'] ?? current_time('c'))),
			'notes' => sanitize_textarea_field((string) ($params['notes'] ?? '')),
		);
	}
}
