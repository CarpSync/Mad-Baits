<?php
/**
 * Bait range / flavour resolution for products.
 *
 * @package MadBaitsProductOrganiser
 */

defined('ABSPATH') || exit;

class MBPO_Ranges {
	/**
	 * Canonical range catalog slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function get_range_catalog() {
		if (function_exists('mad_baits_catalogue_get_ranges')) {
			$out = array();
			foreach (mad_baits_catalogue_get_ranges() as $def) {
				$slug = isset($def['slug']) ? sanitize_title((string) $def['slug']) : '';
				$label = isset($def['label']) ? (string) $def['label'] : '';
				if ('' !== $slug) {
					$out[ $slug ] = '' !== $label ? $label : strtoupper($slug);
				}
			}
			if (! empty($out)) {
				return $out;
			}
		}

		if (function_exists('mad_baits_get_range_tag_catalog')) {
			return mad_baits_get_range_tag_catalog();
		}

		return array(
			'asbo'              => 'ASBO',
			'pandemic'          => 'Pandemic',
			'p-fish-2'          => 'P-Fish',
			'nutz-plus'         => 'Nutz Plus',
			'nutz-banana'       => 'Nutz Banana',
			'wicked-white'      => 'Wicked White',
			'calamino'          => 'Calamino',
			'compulsive-angler' => 'Compulsive Angler',
			'stp'               => 'STP',
			'swan-mussel'       => 'Swan Mussel',
		);
	}

	/**
	 * Search aliases per canonical range slug.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_range_aliases() {
		$aliases = array(
			'asbo'              => array('asbo'),
			'pandemic'          => array('pandemic'),
			'p-fish-2'          => array('p-fish-2', 'p-fish', 'pfish', 'p-fish'),
			'nutz-plus'         => array('nutz-plus', 'nutzplus', 'nutz-plus-2'),
			'nutz-banana'       => array('nutz-banana', 'nutzbanana', 'banana'),
			'wicked-white'      => array('wicked-white', 'wicked-whites', 'wickedwhite'),
			'calamino'          => array('calamino'),
			'compulsive-angler' => array('compulsive-angler', 'compulsive'),
			'stp'               => array('stp'),
			'swan-mussel'       => array('swan-mussel', 'swan mussel'),
		);

		$catalog = self::get_range_catalog();
		foreach ($catalog as $slug => $label) {
			if (! isset($aliases[ $slug ])) {
				$aliases[ $slug ] = array($slug);
			}
			$label_slug = sanitize_title($label);
			if ('' !== $label_slug) {
				$aliases[ $slug ][] = $label_slug;
			}
			$aliases[ $slug ] = array_values(array_unique(array_filter(array_map('sanitize_title', $aliases[ $slug ]))));
		}

		return $aliases;
	}

	/**
	 * @param string $range_slug Range slug.
	 * @return string
	 */
	public static function normalize_range_slug($range_slug) {
		$slug = sanitize_title((string) $range_slug);
		if ('p-fish' === $slug || 'pfish' === $slug) {
			return 'p-fish-2';
		}
		if (function_exists('mad_baits_resolve_range_tag_slug')) {
			return mad_baits_resolve_range_tag_slug($slug);
		}
		return $slug;
	}

	/**
	 * Ranges that have at least one published product.
	 *
	 * @return array<string, array{label: string, count: int}>
	 */
	public static function get_ranges_with_products() {
		$out = array();
		foreach (self::get_range_catalog() as $slug => $label) {
			$ids = self::get_product_ids_for_range($slug, false);
			if (! empty($ids)) {
				$out[ $slug ] = array(
					'label' => (string) $label,
					'count' => count($ids),
				);
			}
		}
		return $out;
	}

	/**
	 * Debug counts for all catalog ranges.
	 *
	 * @return array<string, int>
	 */
	public static function get_range_product_counts() {
		$counts = array();
		foreach (self::get_range_catalog() as $slug => $label) {
			$counts[ $slug ] = count(self::get_product_ids_for_range($slug, false));
		}
		return $counts;
	}

	/**
	 * @param string $range_slug     Range slug.
	 * @param bool   $apply_saved_order Apply saved drag order.
	 * @return int[]
	 */
	public static function get_product_ids_for_range($range_slug, $apply_saved_order = true) {
		if (! function_exists('wc_get_products')) {
			return array();
		}

		$range_slug = self::normalize_range_slug($range_slug);
		if ('' === $range_slug) {
			return array();
		}

		$aliases = self::get_range_aliases();
		$needles = isset($aliases[ $range_slug ]) ? $aliases[ $range_slug ] : array($range_slug);
		$label   = self::get_range_catalog()[ $range_slug ] ?? '';

		$query_args = array(
			'status' => 'publish',
			'limit'  => -1,
			'type'   => array('simple', 'variable', 'grouped', 'external'),
		);

		if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$all_ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$all_ids              = wc_get_products($query_args);
		}

		$matched = array();
		foreach ((array) $all_ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1) {
				continue;
			}
			if (self::product_matches_range($product_id, $range_slug, $needles, $label)) {
				$matched[] = $product_id;
			}
		}

		$matched = array_values(array_unique($matched));

		if (! $apply_saved_order) {
			return $matched;
		}

		return MBPO_Order::sort_ids_by_saved_order($range_slug, $matched);
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $range_slug Canonical range slug.
	 * @param string[] $aliases  Slug aliases.
	 * @param string $label    Human label.
	 * @return bool
	 */
	public static function product_matches_range($product_id, $range_slug, array $aliases, $label = '') {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}

		$aliases = array_values(array_unique(array_filter(array_map('sanitize_title', $aliases))));
		if (empty($aliases)) {
			$aliases = array(sanitize_title($range_slug));
		}

		// 1. pa_flavour
		if (self::attribute_matches($product_id, 'pa_flavour', $aliases, $label)) {
			return true;
		}

		// 2. pa_range
		if (self::attribute_matches($product_id, 'pa_range', $aliases, $label)) {
			return true;
		}

		// 3. Range product tags only (never product-type tags).
		$allowed_range_tags = class_exists('MBPO_Product_Tags') ? MBPO_Product_Tags::get_range_tag_slugs() : array();
		$product_tags       = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
		if (is_array($product_tags)) {
			foreach ($product_tags as $tag_slug) {
				$tag_slug   = sanitize_title((string) $tag_slug);
				$normalized = self::normalize_range_slug($tag_slug);
				if (! empty($allowed_range_tags) && ! in_array($tag_slug, $allowed_range_tags, true) && ! in_array($normalized, $allowed_range_tags, true)) {
					continue;
				}
				if (in_array($tag_slug, $aliases, true) || in_array($normalized, $aliases, true) || $normalized === self::normalize_range_slug($range_slug)) {
					return true;
				}
			}
		}

		// 4. Product name fallback.
		$name = strtolower((string) get_the_title($product_id));
		if ('' !== $name) {
			foreach ($aliases as $alias) {
				$alias = str_replace('-', ' ', $alias);
				if ('' !== $alias && false !== strpos($name, $alias)) {
					return true;
				}
			}
			if ('' !== $label && false !== stripos($name, $label)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int      $product_id Product ID.
	 * @param string   $taxonomy   Attribute taxonomy.
	 * @param string[] $aliases    Range aliases.
	 * @param string   $label      Range label.
	 * @return bool
	 */
	private static function attribute_matches($product_id, $taxonomy, array $aliases, $label = '') {
		if (! taxonomy_exists($taxonomy)) {
			return false;
		}

		$terms = wp_get_post_terms($product_id, $taxonomy);
		if (is_wp_error($terms) || empty($terms)) {
			return false;
		}

		foreach ($terms as $term) {
			if (! $term instanceof WP_Term) {
				continue;
			}
			$slug = sanitize_title((string) $term->slug);
			$name = sanitize_title((string) $term->name);

			foreach ($aliases as $alias) {
				if ($slug === $alias || $name === $alias) {
					return true;
				}
				if (false !== stripos((string) $term->name, str_replace('-', ' ', $alias))) {
					return true;
				}
			}

			if ('' !== $label && false !== stripos((string) $term->name, $label)) {
				return true;
			}
		}

		return false;
	}
}
