<?php
/**
 * Product supplier taxonomy and admin operational filtering.
 *
 * Admin-only filtering: storefront queries are unchanged unless explicitly filtered.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Taxonomy slug for product suppliers.
 */
function mad_baits_supplier_taxonomy() {
	return 'product_supplier';
}

/**
 * Registered suppliers (extend via filter).
 *
 * @return array<string, array{label: string, is_core: bool, badge_bg: string, badge_color: string}>
 */
function mad_baits_supplier_get_registry() {
	$suppliers = array(
		'mad-baits'      => array(
			'label'       => __('Mad Baits', 'mad-baits'),
			'is_core'     => true,
			'badge_bg'    => '#f5c518',
			'badge_color' => '#1d1d1b',
		),
		'atomic-tackle'  => array(
			'label'       => __('Atomic Tackle', 'mad-baits'),
			'is_core'     => false,
			'badge_bg'    => '#dcdcde',
			'badge_color' => '#50575e',
		),
	);

	/**
	 * @param array<string, array{label: string, is_core: bool, badge_bg: string, badge_color: string}> $suppliers
	 */
	return (array) apply_filters('mad_baits_supplier_registry', $suppliers);
}

/**
 * Core (Mad Baits) supplier slug.
 *
 * @return string
 */
function mad_baits_supplier_get_core_slug() {
	return 'mad-baits';
}

/**
 * Register supplier taxonomy and default terms.
 *
 * @return void
 */
function mad_baits_supplier_register_taxonomy() {
	if (! class_exists('WooCommerce', false)) {
		return;
	}

	$labels = array(
		'name'          => __('Suppliers', 'mad-baits'),
		'singular_name' => __('Supplier', 'mad-baits'),
		'search_items'  => __('Search suppliers', 'mad-baits'),
		'all_items'     => __('All suppliers', 'mad-baits'),
		'edit_item'     => __('Edit supplier', 'mad-baits'),
		'update_item'   => __('Update supplier', 'mad-baits'),
		'add_new_item'  => __('Add new supplier', 'mad-baits'),
		'new_item_name' => __('New supplier name', 'mad-baits'),
		'menu_name'     => __('Suppliers', 'mad-baits'),
	);

	register_taxonomy(
		mad_baits_supplier_taxonomy(),
		'product',
		array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'hierarchical'      => false,
			'rewrite'           => false,
			'query_var'         => false,
			'meta_box_cb'       => false,
		)
	);

	mad_baits_supplier_ensure_default_terms();
}
add_action('init', 'mad_baits_supplier_register_taxonomy', 12);

/**
 * Create registry terms when missing.
 *
 * @return void
 */
function mad_baits_supplier_ensure_default_terms() {
	if (! taxonomy_exists(mad_baits_supplier_taxonomy())) {
		return;
	}

	foreach (mad_baits_supplier_get_registry() as $slug => $data) {
		$slug = sanitize_title((string) $slug);
		if ('' === $slug) {
			continue;
		}
		$term = get_term_by('slug', $slug, mad_baits_supplier_taxonomy());
		if ($term instanceof WP_Term) {
			continue;
		}
		wp_insert_term((string) ($data['label'] ?? $slug), mad_baits_supplier_taxonomy(), array('slug' => $slug));
	}
}

/**
 * Product category slugs that indicate external supplier catalogue (e.g. Atomic).
 *
 * @return string[]
 */
function mad_baits_supplier_get_external_category_slugs() {
	$slugs = array(
		'atomic-tackle',
		'tackle',
	);

	/**
	 * @param string[] $slugs Category slugs treated as external supplier products.
	 */
	$slugs = (array) apply_filters('mad_baits_supplier_external_category_slugs', $slugs);

	return array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));
}

/**
 * Term IDs for external-supplier product categories (cached).
 *
 * @return int[]
 */
function mad_baits_supplier_get_atomic_category_term_ids() {
	static $cached = null;

	if (null !== $cached) {
		return $cached;
	}

	$cached = array();
	if (! taxonomy_exists('product_cat')) {
		return $cached;
	}

	foreach (mad_baits_supplier_get_external_category_slugs() as $slug) {
		$term = get_term_by('slug', $slug, 'product_cat');
		if (! $term instanceof WP_Term) {
			continue;
		}
		$cached[] = (int) $term->term_id;
		$children = get_term_children((int) $term->term_id, 'product_cat');
		if (is_array($children)) {
			foreach ($children as $child_id) {
				$cached[] = (int) $child_id;
			}
		}
	}

	$cached = array_values(array_unique(array_filter($cached)));

	return $cached;
}

/**
 * Detect supplier slug from categories / name when term not set.
 *
 * @param int $product_id Product ID.
 * @return string Supplier slug.
 */
function mad_baits_supplier_detect_slug_for_product($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return mad_baits_supplier_get_core_slug();
	}

	$atomic_ids = mad_baits_supplier_get_atomic_category_term_ids();
	if (! empty($atomic_ids)) {
		$cat_ids = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
		if (is_array($cat_ids) && array_intersect($atomic_ids, array_map('intval', $cat_ids))) {
			return 'atomic-tackle';
		}
	}

	$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
	if (is_array($cat_slugs)) {
		foreach ($cat_slugs as $slug) {
			$slug = sanitize_title((string) $slug);
			if ('' !== $slug && false !== strpos($slug, 'atomic-tackle')) {
				return 'atomic-tackle';
			}
			if ('tackle' === $slug || ('' !== $slug && substr($slug, -7) === '-tackle')) {
				if (false !== strpos($slug, 'atomic')) {
					return 'atomic-tackle';
				}
			}
		}
	}

	$name = strtolower((string) get_the_title($product_id));
	if ('' !== $name && false !== strpos($name, 'atomic tackle')) {
		return 'atomic-tackle';
	}

	return mad_baits_supplier_get_core_slug();
}

/**
 * Assigned supplier slug for a product (term or detection).
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_supplier_get_product_slug($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! taxonomy_exists(mad_baits_supplier_taxonomy())) {
		return mad_baits_supplier_get_core_slug();
	}

	$terms = wp_get_post_terms($product_id, mad_baits_supplier_taxonomy(), array('fields' => 'slugs'));
	if (is_array($terms) && ! empty($terms) && ! is_wp_error($terms)) {
		return sanitize_title((string) $terms[0]);
	}

	return mad_baits_supplier_detect_slug_for_product($product_id);
}

/**
 * Whether product belongs to a core (Mad Baits) supplier.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_supplier_product_is_core($product_id) {
	$slug     = mad_baits_supplier_get_product_slug($product_id);
	$registry = mad_baits_supplier_get_registry();

	return isset($registry[ $slug ]['is_core']) && (bool) $registry[ $slug ]['is_core'];
}

/**
 * Whether product is an external / non-core supplier.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_supplier_product_is_external($product_id) {
	return ! mad_baits_supplier_product_is_core($product_id);
}

/**
 * Assign supplier term to a product (replaces existing supplier terms).
 *
 * @param int    $product_id Product ID.
 * @param string $slug       Supplier slug.
 * @return bool
 */
function mad_baits_supplier_assign_product($product_id, $slug) {
	$product_id = absint($product_id);
	$slug       = sanitize_title((string) $slug);
	if ($product_id < 1 || '' === $slug || ! taxonomy_exists(mad_baits_supplier_taxonomy())) {
		return false;
	}

	mad_baits_supplier_ensure_default_terms();

	$term = get_term_by('slug', $slug, mad_baits_supplier_taxonomy());
	if (! $term instanceof WP_Term) {
		$registry = mad_baits_supplier_get_registry();
		$label    = isset($registry[ $slug ]['label']) ? (string) $registry[ $slug ]['label'] : $slug;
		$result   = wp_insert_term($label, mad_baits_supplier_taxonomy(), array('slug' => $slug));
		if (is_wp_error($result)) {
			$term = get_term_by('slug', $slug, mad_baits_supplier_taxonomy());
		} else {
			$term = get_term((int) $result['term_id'], mad_baits_supplier_taxonomy());
		}
	}

	if (! $term instanceof WP_Term) {
		return false;
	}

	$set = wp_set_object_terms($product_id, array((int) $term->term_id), mad_baits_supplier_taxonomy(), false);

	return ! is_wp_error($set);
}

/**
 * Bulk-assign suppliers from detection rules.
 *
 * @param bool $dry_run When true, only return counts.
 * @return array{mad_baits: int, atomic: int, skipped: int, total: int}
 */
function mad_baits_supplier_sync_all_products($dry_run = false) {
	$stats = array(
		'mad_baits' => 0,
		'atomic'    => 0,
		'skipped'   => 0,
		'total'     => 0,
	);

	if (! function_exists('wc_get_products')) {
		return $stats;
	}

	$ids = wc_get_products(
		array(
			'status' => array('publish', 'draft', 'private', 'pending'),
			'limit'  => -1,
			'return' => 'ids',
			'type'   => array('simple', 'variable', 'grouped', 'external'),
		)
	);

	foreach ((array) $ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			++$stats['skipped'];
			continue;
		}

		$slug = mad_baits_supplier_detect_slug_for_product($product_id);
		++$stats['total'];

		if ($dry_run) {
			if ('atomic-tackle' === $slug) {
				++$stats['atomic'];
			} else {
				++$stats['mad_baits'];
			}
			continue;
		}

		if (mad_baits_supplier_assign_product($product_id, $slug)) {
			if ('atomic-tackle' === $slug) {
				++$stats['atomic'];
			} else {
				++$stats['mad_baits'];
			}
		} else {
			++$stats['skipped'];
		}
	}

	return $stats;
}

/**
 * Valid admin filter modes.
 *
 * @return string[]
 */
function mad_baits_supplier_get_filter_modes() {
	return array('core', 'external', 'all');
}

/**
 * Current admin supplier filter mode.
 *
 * core = Mad Baits only, external = non-core suppliers, all = no filter, or specific supplier slug.
 *
 * @return string
 */
function mad_baits_supplier_get_admin_filter_mode() {
	if (! is_admin()) {
		return 'all';
	}

	if (isset($_GET['mad_supplier_filter'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = sanitize_key(wp_unslash((string) $_GET['mad_supplier_filter']));
		if ('' !== $raw) {
			if (in_array($raw, mad_baits_supplier_get_filter_modes(), true)) {
				return $raw;
			}
			if (isset(mad_baits_supplier_get_registry()[ $raw ])) {
				return $raw;
			}
		}
	}

	if (! empty($_GET['mad_include_external'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'all';
	}

	$user_id = get_current_user_id();
	if ($user_id > 0) {
		$saved = get_user_meta($user_id, 'mad_baits_admin_supplier_filter', true);
		if (is_string($saved) && '' !== $saved) {
			if (in_array($saved, mad_baits_supplier_get_filter_modes(), true) || isset(mad_baits_supplier_get_registry()[ $saved ])) {
				return $saved;
			}
		}
	}

	return 'core';
}

/**
 * Persist filter preference for the current user.
 *
 * @param string $mode Filter mode.
 * @return void
 */
function mad_baits_supplier_save_admin_filter_mode($mode) {
	$user_id = get_current_user_id();
	if ($user_id < 1) {
		return;
	}
	update_user_meta($user_id, 'mad_baits_admin_supplier_filter', sanitize_key((string) $mode));
}

/**
 * Whether a product ID passes the active admin supplier filter.
 *
 * @param int         $product_id Product ID.
 * @param string|null $mode       Optional override mode.
 * @return bool
 */
function mad_baits_supplier_product_passes_admin_filter($product_id, $mode = null) {
	$mode = null !== $mode ? sanitize_key((string) $mode) : mad_baits_supplier_get_admin_filter_mode();

	if ('all' === $mode) {
		return true;
	}

	$slug = mad_baits_supplier_get_product_slug($product_id);

	if ('core' === $mode) {
		return mad_baits_supplier_product_is_core($product_id);
	}

	if ('external' === $mode) {
		return mad_baits_supplier_product_is_external($product_id);
	}

	return $slug === $mode;
}

/**
 * Filter a list of product IDs for admin tools.
 *
 * @param int[]         $product_ids Product IDs.
 * @param string|null   $mode        Filter mode override.
 * @return int[]
 */
function mad_baits_supplier_filter_product_ids(array $product_ids, $mode = null) {
	$mode = null !== $mode ? sanitize_key((string) $mode) : mad_baits_supplier_get_admin_filter_mode();

	if ('all' === $mode) {
		return array_values(array_unique(array_map('absint', $product_ids)));
	}

	$filtered = array();
	foreach ($product_ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			continue;
		}
		if (mad_baits_supplier_product_passes_admin_filter($product_id, $mode)) {
			$filtered[] = $product_id;
		}
	}

	return array_values(array_unique($filtered));
}

/**
 * Merge supplier tax_query into wc_get_products() args (admin).
 *
 * @param array<string, mixed> $args WC product query args.
 * @param string|null          $mode Filter mode.
 * @return array<string, mixed>
 */
function mad_baits_supplier_merge_wc_product_args(array $args, $mode = null) {
	if (! is_admin()) {
		return $args;
	}

	$mode = null !== $mode ? sanitize_key((string) $mode) : mad_baits_supplier_get_admin_filter_mode();
	if ('all' === $mode) {
		return $args;
	}

	$tax_query = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : array();
	if (! isset($tax_query['relation'])) {
		$tax_query['relation'] = 'AND';
	}

	$atomic_cat_ids = mad_baits_supplier_get_atomic_category_term_ids();

	if ('core' === $mode && ! empty($atomic_cat_ids)) {
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $atomic_cat_ids,
			'operator' => 'NOT IN',
		);
	} elseif ('external' === $mode && ! empty($atomic_cat_ids)) {
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $atomic_cat_ids,
			'operator' => 'IN',
		);
	} elseif (isset(mad_baits_supplier_get_registry()[ $mode ]) && taxonomy_exists(mad_baits_supplier_taxonomy())) {
		$tax_query[] = array(
			'taxonomy' => mad_baits_supplier_taxonomy(),
			'field'    => 'slug',
			'terms'    => array($mode),
			'operator' => 'IN',
		);
	}

	if (count($tax_query) > 1) {
		$args['tax_query'] = $tax_query;
	}

	return $args;
}

/**
 * Merge supplier filter into WP_Query product list args (admin).
 *
 * @param array<string, mixed> $args Query args.
 * @param string|null          $mode Filter mode.
 * @return array<string, mixed>
 */
function mad_baits_supplier_merge_wp_query_args(array $args, $mode = null) {
	return mad_baits_supplier_merge_wc_product_args($args, $mode);
}

/**
 * wc_get_products() for admin operational tools (IDs only).
 *
 * @param array<string, mixed> $args   Query args.
 * @param string|null          $mode   Filter mode override.
 * @return int[]
 */
function mad_baits_supplier_wc_get_product_ids(array $args = array(), $mode = null) {
	if (! function_exists('wc_get_products')) {
		return array();
	}

	$defaults = array(
		'return' => 'ids',
	);
	$args     = mad_baits_supplier_merge_wc_product_args(array_merge($defaults, $args), $mode);
	$ids      = wc_get_products($args);

	return mad_baits_supplier_filter_product_ids((array) $ids, $mode);
}

/**
 * Supplier label for display.
 *
 * @param string $slug Supplier slug.
 * @return string
 */
function mad_baits_supplier_get_label($slug) {
	$slug     = sanitize_title((string) $slug);
	$registry = mad_baits_supplier_get_registry();

	return isset($registry[ $slug ]['label']) ? (string) $registry[ $slug ]['label'] : $slug;
}

/**
 * Render supplier badge HTML.
 *
 * @param int|string $product_or_slug Product ID or supplier slug.
 * @return string
 */
function mad_baits_supplier_render_badge($product_or_slug) {
	$slug = is_numeric($product_or_slug)
		? mad_baits_supplier_get_product_slug((int) $product_or_slug)
		: sanitize_title((string) $product_or_slug);

	$registry = mad_baits_supplier_get_registry();
	$data     = isset($registry[ $slug ]) ? $registry[ $slug ] : array(
		'label'       => mad_baits_supplier_get_label($slug),
		'badge_bg'    => '#dcdcde',
		'badge_color' => '#50575e',
	);

	$label = (string) ($data['label'] ?? $slug);
	$bg    = (string) ($data['badge_bg'] ?? '#dcdcde');
	$color = (string) ($data['badge_color'] ?? '#50575e');

	return sprintf(
		'<span class="mad-supplier-badge mad-supplier-badge--%s" style="background:%s;color:%s">%s</span>',
		esc_attr($slug),
		esc_attr($bg),
		esc_attr($color),
		esc_html($label)
	);
}

/**
 * Mad Baits operational admin screen IDs (toolbar + default filter).
 *
 * @return string[]
 */
function mad_baits_supplier_get_operational_screen_ids() {
	$screens = array(
		'toplevel_page_woocommerce',
		'woocommerce_page_mad-baits-catalogue-setup',
		'woocommerce_page_mad-baits-product-organiser',
		'woocommerce_page_mad-baits-range-product-order',
		'woocommerce_page_mbbb-tools',
		'edit-product',
	);

	return (array) apply_filters('mad_baits_supplier_operational_screen_ids', $screens);
}

/**
 * Whether current admin screen should use supplier operational filtering.
 *
 * @return bool
 */
function mad_baits_supplier_is_operational_admin_screen() {
	if (! is_admin()) {
		return false;
	}

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (! $screen) {
		return false;
	}

	$allowed = mad_baits_supplier_get_operational_screen_ids();
	if (in_array($screen->id, $allowed, true)) {
		return true;
	}

	if (isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_key(wp_unslash((string) $_GET['page']));
		if (in_array($page, array('mad-baits-catalogue-setup', 'mad-baits-product-organiser', 'mad-baits-range-product-order', 'mbbb-tools'), true)) {
			return true;
		}
	}

	return false;
}
