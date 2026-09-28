<?php
/**
 * Range page sections from WooCommerce product categories only.
 *
 * @package MadBaitsProductOrganiser
 */

defined('ABSPATH') || exit;

class MBPO_Sections {
	/**
	 * Map a product_cat slug to a range section key.
	 *
	 * @param string $cat_slug Category slug.
	 * @return string
	 */
	public static function map_category_slug_to_section($cat_slug) {
		$cat_slug = sanitize_title((string) $cat_slug);
		if ('' === $cat_slug) {
			return '';
		}

		$hardened = array('hardened-hookbaits', 'hardened-hookbait', 'skinz-hardened');
		if (in_array($cat_slug, $hardened, true)) {
			return 'hardened-hookbaits';
		}

		if (! function_exists('mad_baits_get_range_product_type_map')) {
			return '';
		}

		$type_map = mad_baits_get_range_product_type_map();
		foreach ($type_map as $section_key => $conf) {
			$cat_slugs = isset($conf['cat_slugs']) ? (array) $conf['cat_slugs'] : array();
			if (in_array($cat_slug, array_map('sanitize_title', $cat_slugs), true)) {
				return sanitize_key((string) $section_key);
			}
		}

		return '';
	}

	/**
	 * Resolve section key from assigned product categories (not tags).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function resolve_section_key($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return '';
		}

		$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		if (is_wp_error($cat_slugs) || ! is_array($cat_slugs) || empty($cat_slugs)) {
			return '';
		}

		$cat_slugs = array_values(array_unique(array_map('sanitize_title', array_map('strval', $cat_slugs))));
		$matched   = array();

		foreach ($cat_slugs as $cat_slug) {
			$section = self::map_category_slug_to_section($cat_slug);
			if ('' !== $section) {
				$matched[] = $section;
			}
		}

		$matched = array_values(array_unique($matched));
		if (empty($matched)) {
			return '';
		}

		if (function_exists('mad_baits_get_range_section_order_keys')) {
			foreach (mad_baits_get_range_section_order_keys() as $section_key) {
				if (in_array($section_key, $matched, true)) {
					return $section_key;
				}
			}
		}

		return $matched[0];
	}

	/**
	 * Human label for admin from primary product category.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_section_label($product_id) {
		$section_key = self::resolve_section_key($product_id);
		if ('' === $section_key) {
			return '';
		}

		if (function_exists('mad_baits_get_range_product_sections_config')) {
			$sections = mad_baits_get_range_product_sections_config();
			if (isset($sections[ $section_key ]['label'])) {
				return (string) $sections[ $section_key ]['label'];
			}
		}

		return ucwords(str_replace('-', ' ', $section_key));
	}

	/**
	 * Debug reason for admin.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_section_reason($product_id) {
		$product_id = absint($product_id);
		$section    = self::resolve_section_key($product_id);
		if ('' === $section) {
			return __('No matching product category for a range section.', 'mad-baits-product-organiser');
		}

		$cat_names = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
		$cat_names = is_array($cat_names) && ! is_wp_error($cat_names) ? implode(', ', array_map('strval', $cat_names)) : '';

		return sprintf(
			/* translators: 1: section key, 2: category list */
			__('Category: %2$s → section "%1$s"', 'mad-baits-product-organiser'),
			$section,
			$cat_names
		);
	}
}
