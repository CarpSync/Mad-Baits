<?php
/**
 * Bait name / range → WooCommerce product mapping (configure per deployment).
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Map session bait labels to shop targets.
 *
 * Keys are lowercase substrings matched against bait name/range.
 * Values: product_id (int), category_slug (string), or shop_search (string).
 *
 * @return array<string, array<string, mixed>>
 */
function mbs_get_bait_product_map() {
	$map = array(
		// Example placeholders — replace with real product IDs from WooCommerce admin.
		'washed out' => array(
			'product_id' => 0,
			'shop_search' => 'washed out',
			'category_slug' => '',
		),
		'squid' => array(
			'product_id' => 0,
			'shop_search' => 'squid',
			'category_slug' => 'boilies',
		),
	);

	/**
	 * Filter bait → product mapping for Session App shop/reorder links.
	 *
	 * @param array<string, array<string, mixed>> $map Mapping.
	 */
	return apply_filters('mbs_bait_product_map', $map);
}

/**
 * Resolve shop URL for a bait label.
 *
 * @param string $bait_label Bait name or range.
 * @return string URL (shop home or search).
 */
function mbs_resolve_bait_shop_url($bait_label) {
	$bait_label = strtolower(trim((string) $bait_label));
	$shop       = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	if ('' === $bait_label) {
		return is_string($shop) ? $shop : home_url('/shop/');
	}

	foreach (mbs_get_bait_product_map() as $needle => $target) {
		if (false === strpos($bait_label, strtolower((string) $needle))) {
			continue;
		}
		$product_id = absint($target['product_id'] ?? 0);
		if ($product_id > 0) {
			$url = get_permalink($product_id);
			if (is_string($url) && '' !== $url) {
				return $url;
			}
		}
		$slug = sanitize_title((string) ($target['category_slug'] ?? ''));
		if ('' !== $slug) {
			return add_query_arg(array(), trailingslashit($shop) . 'product-category/' . $slug);
		}
		$search = trim((string) ($target['shop_search'] ?? $needle));
		if ('' !== $search) {
			return add_query_arg('s', $search, $shop);
		}
	}

	return add_query_arg('s', $bait_label, $shop);
}
