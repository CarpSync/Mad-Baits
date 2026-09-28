<?php
/**
 * Seed default events.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Seed Mad Baits Open Day.
 */
class Mad_Events_Seed {
	const OPTION_KEY = 'mad_baits_events_seeded_v1';

	const SYNC_OPTION_KEY = 'mad_baits_open_day_enriched_v1';

	/**
	 * Default featured product IDs for Open Day (bundle/session deals).
	 *
	 * @return array<int, int>
	 */
	public static function get_open_day_featured_product_ids() {
		return array_map('absint', (array) apply_filters('mad_baits_open_day_featured_product_ids', array(830, 548, 339, 574, 63)));
	}

	/**
	 * Factory location defaults (matches contact page collection address).
	 *
	 * @return array<string, string>
	 */
	public static function get_open_day_location_defaults() {
		$address_lines = array(
			'Unit A, The Woodlands, Chawston',
			'BEDFORDSHIRE MK44 3BH',
			'United Kingdom',
		);

		if (function_exists('mad_baits_get_contact_details')) {
			$contact = mad_baits_get_contact_details();
			if (! empty($contact['collection_address']) && is_array($contact['collection_address'])) {
				$address_lines = array_values(
					array_filter(
						array_map(
							static function ($line) {
								return is_string($line) ? trim($line) : '';
							},
							$contact['collection_address']
						)
					)
				);
			}
		}

		$address = implode(', ', $address_lines);
		$map     = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);

		return (array) apply_filters(
			'mad_baits_open_day_location_defaults',
			array(
				'location_name' => __('Mad Baits Factory', 'mad-baits'),
				'address'       => $address,
				'map_link'      => $map,
			)
		);
	}

	/**
	 * Theme image used as Open Day hero if none is set.
	 *
	 * @return string Absolute file path.
	 */
	public static function get_open_day_hero_source_path() {
		$candidates = array(
			'assets/img/mad-baits-open-day-hero.jpg',
			'assets/img/ecac2f50a97548228dc72bbf424d39bes1900x490.png',
			'assets/img/64c887023957c9001d76c77e.jpg',
			'assets/img/app-mockup-bundle-deals.png',
		);

		foreach ($candidates as $relative) {
			$path = get_theme_file_path($relative);
			if (is_string($path) && file_exists($path)) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('admin_init', array(__CLASS__, 'maybe_seed'));
		add_action('after_switch_theme', array(__CLASS__, 'maybe_seed'));
		add_action('init', array(__CLASS__, 'maybe_enrich_open_day'), 30);
	}

	/**
	 * Seed once if missing.
	 *
	 * @return void
	 */
	public static function maybe_seed() {
		if (get_option(self::OPTION_KEY)) {
			$existing = get_page_by_path('mad-baits-open-day', OBJECT, 'mad_event');
			if ($existing instanceof WP_Post) {
				return;
			}
		}

		$existing = get_page_by_path('mad-baits-open-day', OBJECT, 'mad_event');
		if ($existing instanceof WP_Post) {
			update_option(self::OPTION_KEY, 1);
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'mad_event',
				'post_status'  => 'publish',
				'post_title'   => __('Mad Baits Open Day', 'mad-baits'),
				'post_name'    => 'mad-baits-open-day',
				'post_excerpt' => __('Factory open day with BBQ, free food, bait deals and a chance to meet the Mad Baits team.', 'mad-baits'),
				'post_content' => __('Join us at the Mad Baits factory for a proper open day. We will have the BBQ going, free food, product chat, bait deals and the chance to meet the team behind the baits.', 'mad-baits'),
			),
			true
		);

		if (is_wp_error($post_id) || ! $post_id) {
			return;
		}

		$keys = Mad_Events_Data::meta_keys();
		update_post_meta($post_id, $keys['start_date'], '2026-08-01');
		update_post_meta($post_id, $keys['start_time'], '11:00');
		update_post_meta($post_id, $keys['end_time'], '17:00');
		update_post_meta($post_id, $keys['short_description'], __('Factory BBQ, free food, bait deals, meet the team and an open day at the Mad Baits factory.', 'mad-baits'));
		update_post_meta($post_id, $keys['full_description'], __('Join us at the Mad Baits factory on August 1st for a proper Mad Baits open day. Expect a BBQ, free food, bait deals, product chat, factory insight and a chance to meet the team.', 'mad-baits'));
		update_post_meta($post_id, $keys['highlights'], "BBQ\nFree Food\nBait Deals\nMeet The Team\nFactory Open Day");
		self::apply_open_day_defaults((int) $post_id);

		update_option(self::OPTION_KEY, 1);
	}

	/**
	 * Enrich existing Open Day post (hero, address, products) once per site.
	 *
	 * @return void
	 */
	public static function maybe_enrich_open_day() {
		if (get_option(self::SYNC_OPTION_KEY)) {
			return;
		}

		$post = get_page_by_path('mad-baits-open-day', OBJECT, 'mad_event');
		if (! $post instanceof WP_Post) {
			return;
		}

		self::apply_open_day_defaults((int) $post->ID);
		update_option(self::SYNC_OPTION_KEY, 1);
	}

	/**
	 * Apply location, products, and hero image to Open Day event.
	 *
	 * @param int $post_id Event post ID.
	 * @return void
	 */
	public static function apply_open_day_defaults($post_id) {
		$post_id = absint($post_id);
		if (! $post_id || 'mad_event' !== get_post_type($post_id)) {
			return;
		}

		$keys     = Mad_Events_Data::meta_keys();
		$location = self::get_open_day_location_defaults();

		update_post_meta($post_id, $keys['location_name'], (string) $location['location_name']);
		update_post_meta($post_id, $keys['address'], (string) $location['address']);
		update_post_meta($post_id, $keys['map_link'], esc_url_raw((string) $location['map_link']));
		update_post_meta($post_id, $keys['featured_products'], implode(',', self::get_open_day_featured_product_ids()));
		update_post_meta($post_id, $keys['featured'], '1');
		update_post_meta($post_id, $keys['rsvp_enabled'], '1');
		update_post_meta($post_id, $keys['status'], 'upcoming');
		update_post_meta($post_id, $keys['push_message'], __('August 1st — free BBQ, bait deals and factory open day. Tap for details.', 'mad-baits'));
		update_post_meta($post_id, $keys['cta_label'], __('View Event', 'mad-baits'));

		if (! has_post_thumbnail($post_id)) {
			self::assign_hero_from_theme_image($post_id);
		}
	}

	/**
	 * Import theme hero file into media library and set as featured image.
	 *
	 * @param int $post_id Event post ID.
	 * @return void
	 */
	public static function assign_hero_from_theme_image($post_id) {
		$source = self::get_open_day_hero_source_path();
		if ('' === $source || ! file_exists($source)) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$filename = basename($source);
		$upload   = wp_upload_bits($filename, null, (string) file_get_contents($source));
		if (! empty($upload['error'])) {
			return;
		}

		$attachment = array(
			'post_mime_type' => wp_check_filetype($filename)['type'] ?? 'image/jpeg',
			'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
		if (is_wp_error($attachment_id) || ! $attachment_id) {
			return;
		}

		$metadata = wp_generate_attachment_metadata((int) $attachment_id, $upload['file']);
		if (! is_wp_error($metadata) && ! empty($metadata)) {
			wp_update_attachment_metadata((int) $attachment_id, $metadata);
		}

		set_post_thumbnail($post_id, (int) $attachment_id);
	}
}
