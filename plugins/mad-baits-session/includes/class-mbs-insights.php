<?php
/**
 * Bait & venue performance insights.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Insights from user logs only.
 */
class MBS_Insights {

	/**
	 * @return void
	 */
	public static function init() {
		// REST callbacks.
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<int, WP_Post>
	 */
	public static function get_user_sessions($user_id) {
		return get_posts(
			array(
				'post_type'      => MBS_CPT::SESSION,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function get_dashboard_stats($user_id) {
		$sessions = self::get_user_sessions($user_id);
		$total_sessions = count($sessions);
		$total_catches  = 0;
		$best_fish_lb   = 0.0;
		$pb_count       = 0;
		$bait_usage     = array();
		$venue_usage    = array();
		$last_session   = null;

		foreach ($sessions as $i => $post) {
			$data  = MBS_Meta::get_session_data($post->ID);
			$stats = MBS_Meta::compute_session_stats($data);
			$total_catches += (int) $stats['catch_count'];
			$best_fish_lb   = max($best_fish_lb, (float) $stats['best_fish_lb']);
			$pb_count       += (int) $stats['pb_count'];

			if (0 === $i) {
				$last_session = array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'venue_name' => (string) ($data['venue_name'] ?? ''),
					'start_at'   => (string) ($data['start_at'] ?? $post->post_date),
					'status'     => (string) ($data['status'] ?? ''),
				);
			}

			$venue = trim((string) ($data['venue_name'] ?? ''));
			if ('' !== $venue) {
				$venue_usage[ $venue ] = isset($venue_usage[ $venue ]) ? $venue_usage[ $venue ] + 1 : 1;
			}

			$bait_rows = isset($data['bait_used']) && is_array($data['bait_used']) ? $data['bait_used'] : array();
			foreach ($bait_rows as $bait) {
				if (! is_array($bait)) {
					continue;
				}
				$key = trim((string) ($bait['range'] ?? $bait['name'] ?? ''));
				if ('' === $key) {
					continue;
				}
				if (! isset($bait_usage[ $key ])) {
					$bait_usage[ $key ] = array('uses' => 0, 'catches' => 0);
				}
				++$bait_usage[ $key ]['uses'];
			}

			$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
			foreach ($catches as $catch) {
				if (! is_array($catch)) {
					continue;
				}
				$bait_key = trim((string) ($catch['bait_used'] ?? ''));
				if ('' !== $bait_key && isset($bait_usage[ $bait_key ])) {
					++$bait_usage[ $bait_key ]['catches'];
				}
			}
		}

		$most_used_bait = self::pick_top_key($bait_usage, 'uses');
		$most_success   = self::pick_top_key($bait_usage, 'catches');
		$fav_venue      = self::pick_top_key_simple($venue_usage);

		$active = null;
		foreach ($sessions as $post) {
			$data = MBS_Meta::get_session_data($post->ID);
			if ('active' === ($data['status'] ?? '')) {
				$active = array(
					'id'         => $post->ID,
					'title'      => $post->post_title,
					'venue_name' => (string) ($data['venue_name'] ?? ''),
					'start_at'   => (string) ($data['start_at'] ?? ''),
				);
				break;
			}
		}

		return array(
			'total_sessions'      => $total_sessions,
			'total_catches'       => $total_catches,
			'best_fish_lb'        => $best_fish_lb,
			'pb_count'            => $pb_count,
			'most_used_bait'      => $most_used_bait,
			'most_successful_bait'=> $most_success,
			'favourite_venue'     => $fav_venue,
			'last_session'        => $last_session,
			'active_session'      => $active,
		);
	}

	/**
	 * @param array<string, array<string, int>> $map Map.
	 * @param string                             $field Field.
	 * @return string|null
	 */
	private static function pick_top_key(array $map, $field) {
		$top = null;
		$max = -1;
		foreach ($map as $key => $row) {
			$val = isset($row[ $field ]) ? (int) $row[ $field ] : 0;
			if ($val > $max) {
				$max = $val;
				$top = $key;
			}
		}
		return $top;
	}

	/**
	 * @param array<string, int> $map Map.
	 * @return string|null
	 */
	private static function pick_top_key_simple(array $map) {
		$top = null;
		$max = -1;
		foreach ($map as $key => $val) {
			if ($val > $max) {
				$max = $val;
				$top = $key;
			}
		}
		return $top;
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function get_bait_performance($user_id) {
		$sessions = self::get_user_sessions($user_id);
		$by_range = array();
		$by_venue = array();

		foreach ($sessions as $post) {
			$data  = MBS_Meta::get_session_data($post->ID);
			$venue = trim((string) ($data['venue_name'] ?? ''));
			$catches = isset($data['catches']) && is_array($data['catches']) ? count($data['catches']) : 0;
			$bait_rows = isset($data['bait_used']) && is_array($data['bait_used']) ? $data['bait_used'] : array();

			foreach ($bait_rows as $bait) {
				if (! is_array($bait)) {
					continue;
				}
				$range = trim((string) ($bait['range'] ?? $bait['name'] ?? 'Unknown'));
				if (! isset($by_range[ $range ])) {
					$by_range[ $range ] = array('sessions' => 0, 'catches' => 0);
				}
				++$by_range[ $range ]['sessions'];
				$by_range[ $range ]['catches'] += $catches > 0 ? 1 : 0;
			}

			if ('' !== $venue) {
				if (! isset($by_venue[ $venue ])) {
					$by_venue[ $venue ] = array('sessions' => 0, 'catches' => 0, 'baits' => array());
				}
				++$by_venue[ $venue ]['sessions'];
				$by_venue[ $venue ]['catches'] += $catches;
				foreach ($bait_rows as $bait) {
					if (! is_array($bait)) {
						continue;
					}
					$range = trim((string) ($bait['range'] ?? $bait['name'] ?? ''));
					if ('' === $range) {
						continue;
					}
					$by_venue[ $venue ]['baits'][ $range ] = isset($by_venue[ $venue ]['baits'][ $range ])
						? $by_venue[ $venue ]['baits'][ $range ] + 1
						: 1;
				}
			}
		}

		$session_count = count($sessions);
		$disclaimer    = $session_count < 3
			? __('Not enough data yet — log more sessions to unlock stronger trends.', 'mad-baits-session')
			: ($session_count < 8
				? __('Early trend based on your logs.', 'mad-baits-session')
				: __('Based on your logs.', 'mad-baits-session'));

		return array(
			'disclaimer' => $disclaimer,
			'by_range'   => $by_range,
			'by_venue'   => $by_venue,
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function get_conditions_insights($user_id) {
		$sessions = self::get_user_sessions($user_id);
		$with_catches = array();

		foreach ($sessions as $post) {
			$data = MBS_Meta::get_session_data($post->ID);
			$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
			if (empty($catches)) {
				continue;
			}
			$with_catches[] = $data;
		}

		if (count($with_catches) < 3) {
			return array(
				'ready'   => false,
				'message' => __('Log more sessions to unlock conditions insights.', 'mad-baits-session'),
			);
		}

		$pressure = array();
		$wind     = array();
		$clarity  = array();

		foreach ($with_catches as $data) {
			$snap = ! empty($data['weather_snapshots']) && is_array($data['weather_snapshots'])
				? end($data['weather_snapshots'])
				: array();
			if (isset($snap['pressure'])) {
				$pressure[] = (float) $snap['pressure'];
			}
			if (isset($snap['wind_direction'])) {
				$wind[ (string) $snap['wind_direction'] ] = isset($wind[ (string) $snap['wind_direction'] ])
					? $wind[ (string) $snap['wind_direction'] ] + 1
					: 1;
			}
			$cond = isset($data['conditions']) && is_array($data['conditions']) ? $data['conditions'] : array();
			if (! empty($cond['water_clarity'])) {
				$clarity[ (string) $cond['water_clarity'] ] = isset($clarity[ (string) $cond['water_clarity'] ])
					? $clarity[ (string) $cond['water_clarity'] ] + 1
					: 1;
			}
		}

		return array(
			'ready'          => true,
			'disclaimer'     => __('Based on your logs — not a guarantee of future results.', 'mad-baits-session'),
			'pressure_avg'   => ! empty($pressure) ? round(array_sum($pressure) / count($pressure), 1) : null,
			'top_wind'       => self::pick_top_key_simple($wind),
			'top_clarity'    => self::pick_top_key_simple($clarity),
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_pb_tracker($user_id) {
		$sessions = self::get_user_sessions($user_id);
		$pbs      = array();

		foreach ($sessions as $post) {
			$data = MBS_Meta::get_session_data($post->ID);
			$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
			foreach ($catches as $catch) {
				if (! is_array($catch) || empty($catch['is_pb'])) {
					continue;
				}
				$pbs[] = array_merge(
					$catch,
					array(
						'session_id' => $post->ID,
						'venue_name' => (string) ($data['venue_name'] ?? ''),
					)
				);
			}
		}

		return $pbs;
	}

	/**
	 * @param int   $user_id User ID.
	 * @param array<string, string> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_catch_gallery($user_id, array $filters = array()) {
		$sessions = self::get_user_sessions($user_id);
		$items    = array();

		foreach ($sessions as $post) {
			$data = MBS_Meta::get_session_data($post->ID);
			$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
			foreach ($catches as $catch) {
				if (! is_array($catch) || empty($catch['photo_id'])) {
					continue;
				}
				if (! empty($filters['pb_only']) && empty($catch['is_pb'])) {
					continue;
				}
				if (! empty($filters['venue']) && (string) ($data['venue_name'] ?? '') !== (string) $filters['venue']) {
					continue;
				}
				$url = wp_get_attachment_image_url((int) $catch['photo_id'], 'large');
				if (! $url) {
					continue;
				}
				$items[] = array(
					'catch_id'   => (string) ($catch['id'] ?? ''),
					'session_id' => $post->ID,
					'photo_url'  => $url,
					'species'    => (string) ($catch['species'] ?? ''),
					'weight_lb'  => (float) ($catch['weight_lb'] ?? 0),
					'weight_oz'  => (float) ($catch['weight_oz'] ?? 0),
					'venue_name' => (string) ($data['venue_name'] ?? ''),
					'bait_used'  => (string) ($catch['bait_used'] ?? ''),
					'is_pb'      => ! empty($catch['is_pb']),
					'caught_at'  => (string) ($catch['catch_time'] ?? ''),
					'public_consent' => ! empty($catch['public_consent']),
				);
			}
		}

		return $items;
	}
}
