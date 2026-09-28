<?php
/**
 * Event data helpers.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Normalize event data from post/meta.
 */
class Mad_Events_Data {
	/**
	 * Meta keys map.
	 *
	 * @return array<string, string>
	 */
	public static function meta_keys() {
		return array(
			'start_date'          => '_mad_event_start_date',
			'start_time'          => '_mad_event_start_time',
			'end_date'            => '_mad_event_end_date',
			'end_time'            => '_mad_event_end_time',
			'location_name'       => '_mad_event_location_name',
			'address'             => '_mad_event_address',
			'map_link'            => '_mad_event_map_link',
			'short_description'   => '_mad_event_short_description',
			'full_description'    => '_mad_event_full_description',
			'gallery_ids'         => '_mad_event_gallery_ids',
			'featured'            => '_mad_event_featured',
			'cta_label'           => '_mad_event_cta_label',
			'cta_url'             => '_mad_event_cta_url',
			'rsvp_enabled'        => '_mad_event_rsvp_enabled',
			'max_attendees'       => '_mad_event_max_attendees',
			'featured_products'   => '_mad_event_featured_products',
			'push_message'        => '_mad_event_push_message',
			'status'              => '_mad_event_status',
			'highlights'          => '_mad_event_highlights',
		);
	}

	/**
	 * Get normalized event array.
	 *
	 * @param int $post_id Event post ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_event($post_id) {
		$post_id = absint($post_id);
		$post    = get_post($post_id);

		if (! $post instanceof WP_Post || 'mad_event' !== $post->post_type || 'publish' !== $post->post_status) {
			return null;
		}

		$keys = self::meta_keys();
		$meta = array();
		foreach ($keys as $field => $meta_key) {
			$meta[ $field ] = get_post_meta($post_id, $meta_key, true);
		}

		$start_date = self::sanitize_date((string) $meta['start_date']);
		$end_date   = self::sanitize_date((string) $meta['end_date']);
		$status     = sanitize_key((string) $meta['status']);
		if (! in_array($status, array('upcoming', 'past', 'cancelled'), true)) {
			$status = self::derive_status($start_date, $end_date);
		}

		$highlights = self::parse_highlights((string) $meta['highlights']);
		$product_ids = self::parse_product_ids((string) $meta['featured_products']);
		$gallery_ids = self::parse_gallery_ids((string) $meta['gallery_ids']);

		$hero_image = get_the_post_thumbnail_url($post_id, 'large');
		if (! is_string($hero_image) || '' === $hero_image) {
			$hero_image = '';
		}

		$start_dt = self::build_datetime($start_date, (string) $meta['start_time'], '09:00');
		$end_dt   = self::build_datetime($end_date ?: $start_date, (string) $meta['end_time'], '17:00');

		return array(
			'id'                => $post_id,
			'title'             => get_the_title($post_id),
			'slug'              => $post->post_name,
			'permalink'         => get_permalink($post_id),
			'excerpt'           => has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags($post->post_content), 28),
			'content'           => apply_filters('the_content', $post->post_content),
			'start_date'        => $start_date,
			'start_time'        => self::sanitize_time((string) $meta['start_time'], '09:00'),
			'end_date'          => $end_date,
			'end_time'          => self::sanitize_time((string) $meta['end_time'], '17:00'),
			'start_datetime'    => $start_dt,
			'end_datetime'      => $end_dt,
			'location_name'     => sanitize_text_field((string) $meta['location_name']),
			'address'           => sanitize_textarea_field((string) $meta['address']),
			'map_link'          => esc_url_raw((string) $meta['map_link']),
			'directions_url'    => self::get_directions_url((string) $meta['map_link'], (string) $meta['address']),
			'short_description' => wp_kses_post((string) $meta['short_description']),
			'full_description'  => wp_kses_post((string) $meta['full_description']),
			'hero_image'        => $hero_image,
			'gallery_ids'       => $gallery_ids,
			'featured'          => '1' === (string) $meta['featured'],
			'cta_label'         => sanitize_text_field((string) $meta['cta_label']),
			'cta_url'           => esc_url_raw((string) $meta['cta_url']),
			'rsvp_enabled'      => '1' === (string) $meta['rsvp_enabled'],
			'max_attendees'     => max(0, (int) $meta['max_attendees']),
			'featured_products' => $product_ids,
			'push_message'      => sanitize_textarea_field((string) $meta['push_message']),
			'status'            => $status,
			'highlights'        => $highlights,
			'date_label'        => self::format_date_label($start_date),
			'date_badge'        => self::format_date_badge($start_date),
			'time_label'        => self::format_time_label(self::sanitize_time((string) $meta['start_time'], '09:00'), self::sanitize_time((string) $meta['end_time'], '17:00')),
			'countdown_label'   => self::get_countdown_label($start_dt, $status),
			'rsvp_count'        => Mad_Events_RSVP::count_for_event($post_id),
		);
	}

	/**
	 * Query upcoming events.
	 *
	 * @param array<string, mixed> $args Query overrides.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_upcoming_events($args = array()) {
		$query_args = wp_parse_args(
			$args,
			array(
				'post_type'      => 'mad_event',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'orderby'        => 'meta_value',
				'meta_key'       => '_mad_event_start_date',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_mad_event_status',
						'value'   => 'upcoming',
						'compare' => '=',
					),
					array(
						'key'     => '_mad_event_status',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$posts = get_posts($query_args);
		$events = array();

		foreach ($posts as $post) {
			if (! $post instanceof WP_Post) {
				continue;
			}
			$event = self::get_event((int) $post->ID);
			if (! $event || 'cancelled' === $event['status'] || 'past' === $event['status']) {
				continue;
			}
			if (! empty($event['start_datetime']) && $event['start_datetime'] < time()) {
				continue;
			}
			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Featured or next upcoming event.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_featured_event() {
		$featured = get_posts(
			array(
				'post_type'      => 'mad_event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_mad_event_featured',
						'value' => '1',
					),
				),
			)
		);

		if (! empty($featured[0]) && $featured[0] instanceof WP_Post) {
			$event = self::get_event((int) $featured[0]->ID);
			if ($event && 'upcoming' === self::effective_status($event)) {
				return $event;
			}
		}

		$upcoming = self::get_upcoming_events(array('posts_per_page' => 1));
		return ! empty($upcoming[0]) ? $upcoming[0] : null;
	}

	/**
	 * Effective status for display.
	 *
	 * @param array<string, mixed> $event Event data.
	 * @return string
	 */
	public static function effective_status($event) {
		$status = isset($event['status']) ? (string) $event['status'] : 'upcoming';
		if ('cancelled' === $status) {
			return 'cancelled';
		}
		if (! empty($event['start_datetime']) && (int) $event['start_datetime'] < time()) {
			return 'past';
		}
		return 'upcoming';
	}

	/**
	 * @param string $date Date Y-m-d.
	 * @return string
	 */
	public static function sanitize_date($date) {
		$date = trim($date);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $date;
		}
		return '';
	}

	/**
	 * @param string $time Time H:i.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function sanitize_time($time, $fallback = '09:00') {
		$time = trim($time);
		if (preg_match('/^\d{2}:\d{2}$/', $time)) {
			return $time;
		}
		return $fallback;
	}

	/**
	 * @param string $date Date.
	 * @param string $time Time.
	 * @param string $fallback_time Fallback.
	 * @return int
	 */
	public static function build_datetime($date, $time, $fallback_time = '09:00') {
		if ('' === $date) {
			return 0;
		}
		$time = self::sanitize_time($time, $fallback_time);
		$ts   = strtotime($date . ' ' . $time);
		return $ts ? (int) $ts : 0;
	}

	/**
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return string
	 */
	public static function derive_status($start_date, $end_date) {
		$compare = $end_date ?: $start_date;
		if ('' === $compare) {
			return 'upcoming';
		}
		$ts = strtotime($compare . ' 23:59:59');
		return ($ts && $ts < time()) ? 'past' : 'upcoming';
	}

	/**
	 * @param string $raw Highlights text.
	 * @return array<int, string>
	 */
	public static function parse_highlights($raw) {
		$lines = preg_split('/\r\n|\r|\n/', $raw) ?: array();
		$out   = array();
		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ('' !== $line) {
				$out[] = sanitize_text_field($line);
			}
		}
		return $out;
	}

	/**
	 * @param string $raw Product IDs csv.
	 * @return array<int, int>
	 */
	public static function parse_product_ids($raw) {
		$parts = preg_split('/[,\s]+/', $raw) ?: array();
		$ids   = array();
		foreach ($parts as $part) {
			$id = absint($part);
			if ($id && 'product' === get_post_type($id)) {
				$ids[] = $id;
			}
		}
		return array_values(array_unique($ids));
	}

	/**
	 * @param string $raw Gallery IDs csv.
	 * @return array<int, int>
	 */
	public static function parse_gallery_ids($raw) {
		$parts = preg_split('/[,\s]+/', $raw) ?: array();
		$ids   = array();
		foreach ($parts as $part) {
			$id = absint($part);
			if ($id && wp_attachment_is_image($id)) {
				$ids[] = $id;
			}
		}
		return array_values(array_unique($ids));
	}

	/**
	 * @param string $map_link Map URL.
	 * @param string $address Address.
	 * @return string
	 */
	public static function get_directions_url($map_link, $address) {
		if ('' !== $map_link && filter_var($map_link, FILTER_VALIDATE_URL)) {
			return esc_url_raw($map_link);
		}
		if ('' !== trim($address)) {
			return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
		}
		return '';
	}

	/**
	 * @param string $date Date.
	 * @return string
	 */
	public static function format_date_label($date) {
		if ('' === $date) {
			return '';
		}
		$ts = strtotime($date);
		return $ts ? wp_date('j F Y', $ts) : $date;
	}

	/**
	 * @param string $date Date.
	 * @return string
	 */
	public static function format_date_badge($date) {
		if ('' === $date) {
			return '';
		}
		$ts = strtotime($date);
		if (! $ts) {
			return '';
		}
		return '<span class="mad-event-card__day">' . esc_html(wp_date('j', $ts)) . '</span><span class="mad-event-card__month">' . esc_html(wp_date('M', $ts)) . '</span>';
	}

	/**
	 * @param string $start Start time.
	 * @param string $end End time.
	 * @return string
	 */
	public static function format_time_label($start, $end) {
		if ('' === $start) {
			return '';
		}
		$start_label = wp_date(get_option('time_format'), strtotime('1970-01-01 ' . $start));
		if ('' === $end || $start === $end) {
			return $start_label;
		}
		$end_label = wp_date(get_option('time_format'), strtotime('1970-01-01 ' . $end));
		return $start_label . ' – ' . $end_label;
	}

	/**
	 * @param int    $start_ts Start timestamp.
	 * @param string $status Status.
	 * @return string
	 */
	public static function get_countdown_label($start_ts, $status) {
		if ('cancelled' === $status) {
			return __('Cancelled', 'mad-baits');
		}
		if ('past' === $status || ! $start_ts) {
			return '';
		}
		$diff = (int) ceil(($start_ts - time()) / DAY_IN_SECONDS);
		if ($diff > 1) {
			return sprintf(
				/* translators: %d: number of days */
				__('Starts in %d days', 'mad-baits'),
				$diff
			);
		}
		if (1 === $diff) {
			return __('Starts tomorrow', 'mad-baits');
		}
		if (0 === $diff) {
			return __('Starts today', 'mad-baits');
		}
		return '';
	}
}
