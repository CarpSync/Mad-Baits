<?php
/**
 * Admin helpers to create and configure bundle products quickly.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Bundle setup utilities for WooCommerce admin.
 */
final class MBBB_Admin_Setup {

	/**
	 * Bundles & Deals category slugs used on the storefront.
	 *
	 * @return string[]
	 */
	public static function bundle_category_slugs() {
		return array('bundles-deals', 'bundle-deals');
	}

	/**
	 * Apply a preset (and optional defaults) to a product.
	 *
	 * @param int    $product_id       Product ID.
	 * @param string $preset_id        Preset key.
	 * @param bool   $enable_builder   Enable bundle builder meta.
	 * @param bool   $apply_defaults   Apply recommended product settings.
	 * @return bool
	 */
	public static function apply_preset($product_id, $preset_id, $enable_builder = true, $apply_defaults = true) {
		$product_id = absint($product_id);
		$preset_id  = sanitize_key((string) $preset_id);
		if ($product_id < 1 || '' === $preset_id) {
			return false;
		}

		$slots = MBBB_Presets::get_preset_slots($preset_id);
		if (empty($slots)) {
			return false;
		}

		$plugin = MBBB_Plugin::instance();
		// Enrich slots with explicit variation attribute links when possible (back-compat).
		$slots = $plugin->migrate_slots_attribute_links($slots, $product_id);

		$enabled  = $enable_builder ? 'yes' : ($plugin->is_enabled($product_id) ? 'yes' : 'no');
		$settings = $apply_defaults ? MBBB_Plugin::default_settings() : $plugin->get_settings($product_id);
		$plugin->save_product_bundle_meta($product_id, $enabled, $settings, $slots);

		self::ensure_bundle_category($product_id);
		self::log(
			sprintf(
				/* translators: 1: product ID 2: preset id */
				__('Applied preset "%2$s" to product #%1$d.', 'mad-baits-bundle-builder'),
				$product_id,
				$preset_id
			)
		);

		return true;
	}

	/**
	 * Copy bundle builder config from one product to another.
	 *
	 * @param int $source_id Source product ID.
	 * @param int $target_id Target product ID.
	 * @return bool
	 */
	public static function copy_bundle_config($source_id, $target_id) {
		$source_id = absint($source_id);
		$target_id = absint($target_id);
		if ($source_id < 1 || $target_id < 1 || $source_id === $target_id) {
			return false;
		}

		$plugin = MBBB_Plugin::instance();
		$slots  = $plugin->get_slots($source_id);
		if (empty($slots)) {
			return false;
		}

		$slots    = $plugin->migrate_slots_attribute_links($slots, $target_id);
		$settings = $plugin->get_settings($source_id);
		$enabled  = $plugin->is_enabled($source_id) ? 'yes' : 'no';
		$plugin->save_product_bundle_meta($target_id, $enabled, $settings, $slots);

		self::ensure_bundle_category($target_id);
		self::log(
			sprintf(
				/* translators: 1: target product ID 2: source product ID */
				__('Copied bundle setup from product #%2$d to #%1$d.', 'mad-baits-bundle-builder'),
				$target_id,
				$source_id
			)
		);

		return true;
	}

	/**
	 * Create a new simple bundle product from a preset.
	 *
	 * @param string               $title     Product title.
	 * @param string               $preset_id Preset key.
	 * @param array<string, mixed> $args      Optional price, status, sku.
	 * @return int Product ID or 0 on failure.
	 */
	public static function create_bundle_product($title, $preset_id, $args = array()) {
		if (! class_exists('WC_Product_Simple')) {
			return 0;
		}

		$title = sanitize_text_field((string) $title);
		if ('' === $title) {
			return 0;
		}

		$product = new WC_Product_Simple();
		$product->set_name($title);
		$product->set_status(isset($args['status']) ? sanitize_key((string) $args['status']) : 'draft');
		$product->set_catalog_visibility('visible');
		$product->set_sold_individually(false);

		if (! empty($args['sku'])) {
			$product->set_sku(sanitize_text_field((string) $args['sku']));
		}

		if (isset($args['regular_price']) && '' !== (string) $args['regular_price']) {
			$product->set_regular_price(wc_format_decimal((string) $args['regular_price']));
		}

		$product_id = (int) $product->save();
		if ($product_id < 1) {
			return 0;
		}

		if (! self::apply_preset($product_id, $preset_id, true, true)) {
			wp_delete_post($product_id, true);
			return 0;
		}

		self::log(
			sprintf(
				/* translators: 1: product ID 2: product title */
				__('Created bundle product #%1$d (%2$s).', 'mad-baits-bundle-builder'),
				$product_id,
				$title
			)
		);

		return $product_id;
	}

	/**
	 * Duplicate an existing product title/price with bundle config copied from a template.
	 *
	 * @param int                  $template_id Template product ID.
	 * @param string               $title       New title.
	 * @param array<string, mixed> $args        Optional price and status.
	 * @return int New product ID or 0.
	 */
	public static function duplicate_from_template($template_id, $title, $args = array()) {
		$template_id = absint($template_id);
		$title       = sanitize_text_field((string) $title);
		if ($template_id < 1 || '' === $title) {
			return 0;
		}

		$template = wc_get_product($template_id);
		if (! $template) {
			return 0;
		}

		$create_args = array(
			'status' => isset($args['status']) ? sanitize_key((string) $args['status']) : 'draft',
		);

		if (isset($args['regular_price']) && '' !== (string) $args['regular_price']) {
			$create_args['regular_price'] = (string) $args['regular_price'];
		} else {
			$create_args['regular_price'] = (string) $template->get_regular_price();
		}

		$preset = MBBB_Presets::detect_preset_from_title($template->get_name());
		if ('' === $preset) {
			$preset = MBBB_Presets::detect_preset_from_title($title);
		}

		$product_id = self::create_bundle_product($title, $preset ?: '30kg-boilie-deal', $create_args);
		if ($product_id < 1) {
			return 0;
		}

		self::copy_bundle_config($template_id, $product_id);

		return $product_id;
	}

	/**
	 * Assign Bundles & Deals category when it exists.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function ensure_bundle_category($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return;
		}

		$term_ids = array();
		foreach (self::bundle_category_slugs() as $slug) {
			$term = get_term_by('slug', $slug, 'product_cat');
			if ($term && ! is_wp_error($term)) {
				$term_ids[] = (int) $term->term_id;
				break;
			}
		}

		if (empty($term_ids)) {
			return;
		}

		$existing = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
		$existing = is_array($existing) && ! is_wp_error($existing) ? array_map('absint', $existing) : array();
		wp_set_object_terms($product_id, array_values(array_unique(array_merge($existing, $term_ids))), 'product_cat');
	}

	/**
	 * Products suitable as bundle templates (enabled builder or deals category with slots).
	 *
	 * @return array<int, string> id => label
	 */
	public static function get_template_product_options() {
		$plugin = MBBB_Plugin::instance();
		$out    = array();

		$query_args = array(
			'status' => array('publish', 'draft', 'private'),
			'limit'  => 100,
			'orderby' => 'title',
			'order'   => 'ASC',
		);

		if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$ids                  = wc_get_products($query_args);
		}

		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1) {
				continue;
			}
			if (! $plugin->is_enabled($product_id) && empty($plugin->get_slots($product_id))) {
				continue;
			}
			$title = get_the_title($product_id);
			if ('' === $title) {
				continue;
			}
			$slot_count = count($plugin->get_slots($product_id));
			$out[ $product_id ] = $title . ($slot_count ? ' (' . $slot_count . ' slots)' : '');
		}

		asort($out, SORT_NATURAL | SORT_FLAG_CASE);
		return $out;
	}

	/**
	 * Bundle products for admin tables (enabled builder first).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_bundle_product_rows() {
		$plugin = MBBB_Plugin::instance();
		$rows   = array();

		$query_args = array(
			'status' => array('publish', 'draft', 'private'),
			'limit'  => 100,
			'orderby' => 'modified',
			'order'   => 'DESC',
		);

		if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$ids                  = wc_get_products($query_args);
		}

		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1) {
				continue;
			}

			$enabled = $plugin->is_enabled($product_id);
			$slots   = $plugin->get_slots($product_id);
			if (! $enabled && empty($slots)) {
				continue;
			}

			$preset = MBBB_Presets::detect_preset_from_title(get_the_title($product_id));
			$rows[] = array(
				'id'           => $product_id,
				'title'        => get_the_title($product_id),
				'edit_url'     => get_edit_post_link($product_id, 'raw'),
				'view_url'     => get_permalink($product_id),
				'enabled'      => $enabled,
				'slot_count'   => count($slots),
				'preset_id'    => $preset,
				'preset_label' => $preset ? (MBBB_Presets::get_presets()[ $preset ]['label'] ?? $preset) : '',
				'status'       => get_post_status($product_id),
			);
		}

		return $rows;
	}

	/**
	 * Deal-category products without bundle setup yet.
	 *
	 * @return array<int, string>
	 */
	public static function get_unconfigured_deal_products() {
		$plugin = MBBB_Plugin::instance();
		$out    = array();

		$term_ids = array();
		foreach (self::bundle_category_slugs() as $slug) {
			$term = get_term_by('slug', $slug, 'product_cat');
			if ($term && ! is_wp_error($term)) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		if (empty($term_ids)) {
			return $out;
		}

		$query_args = array(
			'status'   => array('publish', 'draft'),
			'limit'    => 100,
			'category' => $term_ids,
			'orderby'  => 'title',
			'order'    => 'ASC',
		);

		if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$ids                  = wc_get_products($query_args);
		}

		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1 || $plugin->is_enabled($product_id) || ! empty($plugin->get_slots($product_id))) {
				continue;
			}
			$out[ $product_id ] = get_the_title($product_id);
		}

		return $out;
	}

	/**
	 * @param string $message Log message.
	 * @return void
	 */
	public static function log($message) {
		$logs   = get_option(MBBB_Plugin::OPTION_LOGS, array());
		$logs   = is_array($logs) ? $logs : array();
		$logs[] = array(
			'time'    => current_time('mysql'),
			'message' => (string) $message,
		);
		update_option(MBBB_Plugin::OPTION_LOGS, array_slice($logs, -100), false);
	}
}
