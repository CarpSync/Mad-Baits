<?php
/**
 * Shop archive layout helpers.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Whether the current request is a WooCommerce product listing archive.
 *
 * @return bool
 */
function mad_baits_is_woocommerce_product_listing_page() {
	if (! function_exists('is_woocommerce') || ! is_woocommerce()) {
		return false;
	}

	if (function_exists('is_shop') && is_shop()) {
		return true;
	}

	if (function_exists('is_product_category') && is_product_category()) {
		return true;
	}

	if (function_exists('is_product_tag') && is_product_tag()) {
		return true;
	}

	return is_post_type_archive('product');
}

/**
 * Remove WooCommerce sidebar on product archives (no reserved column).
 */
function mad_baits_remove_woo_archive_sidebar() {
	if (! function_exists('is_woocommerce') || ! is_woocommerce()) {
		return;
	}
	if (! (is_shop() || is_product_taxonomy() || is_post_type_archive('product'))) {
		return;
	}
	remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
}
add_action('wp', 'mad_baits_remove_woo_archive_sidebar');

/**
 * Enqueue archive grid layout fixes.
 */
function mad_baits_enqueue_shop_archive_grid_styles() {
	if (! function_exists('is_woocommerce') || ! is_woocommerce()) {
		return;
	}
	if (! (is_shop() || is_product_taxonomy() || is_post_type_archive('product'))) {
		return;
	}

	$path = get_theme_file_path('assets/css/scoped/shop-archive-grid.css');
	if (! file_exists($path)) {
		return;
	}

	$deps = array('mad-baits-main');
	if (wp_style_is('mad-baits-product-card-images', 'registered') || wp_style_is('mad-baits-product-card-images', 'enqueued')) {
		$deps[] = 'mad-baits-product-card-images';
	}

	wp_enqueue_style(
		'mad-baits-shop-archive-grid',
		get_theme_file_uri('assets/css/scoped/shop-archive-grid.css'),
		$deps,
		(string) filemtime($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_shop_archive_grid_styles', 56);

/**
 * Accessories category slugs on the storefront.
 *
 * @return string[]
 */
function mad_baits_get_accessories_category_slugs() {
	return array('accessories', 'accessories-2', 'accesories');
}

/**
 * Terminal tackle category slugs to keep out of Accessories archives.
 *
 * @return string[]
 */
function mad_baits_get_terminal_tackle_category_slugs() {
	return array('tackle', 'terminal', 'terminal-tackle', 'terminal-tackle-category', 'rigs', 'hooks', 'hook', 'line', 'leads');
}

/**
 * Resolve product_cat term IDs for slug list (includes child terms).
 *
 * @param string[] $slugs Category slugs.
 * @return int[]
 */
function mad_baits_get_product_cat_term_ids_by_slugs(array $slugs) {
	if (! taxonomy_exists('product_cat')) {
		return array();
	}

	$term_ids = array();
	foreach ($slugs as $slug) {
		$slug = sanitize_title((string) $slug);
		if ('' === $slug) {
			continue;
		}

		$term = get_term_by('slug', $slug, 'product_cat');
		if (! $term instanceof WP_Term) {
			continue;
		}

		$term_ids[] = (int) $term->term_id;

		$children = get_term_children((int) $term->term_id, 'product_cat');
		if (! is_wp_error($children) && is_array($children)) {
			foreach ($children as $child_id) {
				$term_ids[] = (int) $child_id;
			}
		}
	}

	return array_values(array_unique(array_filter(array_map('absint', $term_ids))));
}

/**
 * Whether the current request is an Accessories product category archive.
 *
 * @return bool
 */
function mad_baits_is_accessories_product_category_archive() {
	if (! function_exists('is_product_category') || ! is_product_category()) {
		return false;
	}

	$term = get_queried_object();
	if (! $term instanceof WP_Term || 'product_cat' !== $term->taxonomy) {
		return false;
	}

	return in_array(sanitize_title((string) $term->slug), mad_baits_get_accessories_category_slugs(), true);
}

/**
 * Exclude terminal tackle categories from Accessories archive product queries.
 *
 * @param array $tax_query Tax query clauses.
 * @return array
 */
function mad_baits_accessories_archive_tax_query_exclude_terminal(array $tax_query) {
	if (! mad_baits_is_accessories_product_category_archive()) {
		return $tax_query;
	}

	$exclude_ids = mad_baits_get_product_cat_term_ids_by_slugs(mad_baits_get_terminal_tackle_category_slugs());
	if (empty($exclude_ids)) {
		return $tax_query;
	}

	if (! is_array($tax_query)) {
		$tax_query = array();
	}

	$tax_query[] = array(
		'taxonomy'         => 'product_cat',
		'field'            => 'term_id',
		'terms'            => $exclude_ids,
		'operator'         => 'NOT IN',
		'include_children' => true,
	);

	return $tax_query;
}
add_filter('woocommerce_product_query_tax_query', 'mad_baits_accessories_archive_tax_query_exclude_terminal', 30);

/**
 * Main query fallback for Accessories archives (non-Woo product loops).
 *
 * @param WP_Query $query Query.
 * @return void
 */
function mad_baits_accessories_archive_pre_get_posts_exclude_terminal($query) {
	if (is_admin() || ! ($query instanceof WP_Query) || ! $query->is_main_query()) {
		return;
	}
	if (! function_exists('is_product_category') || ! is_product_category()) {
		return;
	}
	if (! mad_baits_is_accessories_product_category_archive()) {
		return;
	}

	$tax_query = $query->get('tax_query');
	$tax_query = mad_baits_accessories_archive_tax_query_exclude_terminal(is_array($tax_query) ? $tax_query : array());
	$query->set('tax_query', $tax_query);
}
add_action('pre_get_posts', 'mad_baits_accessories_archive_pre_get_posts_exclude_terminal', 25);
