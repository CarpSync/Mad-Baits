<?php
/**
 * Storefront visibility for retired, scheduled and owner-gated bait ranges.
 *
 * BBB products are hidden, not deleted, so historical orders keep their product records.
 * STP bulk deals use a launch datetime. Swan Mussel stays hidden until the owner
 * marks the range live, and not before 2027.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

require_once get_theme_file_path('/inc/range-launch-clock.php');

const MAD_BAITS_RANGE_HIDDEN_META       = '_mad_baits_storefront_hidden';
const MAD_BAITS_RANGE_PUBLIC_FROM_META  = '_mad_baits_public_from';
const MAD_BAITS_RANGE_SLUG_META         = '_mad_baits_range_slug';
const MAD_BAITS_DEAL_RANGE_META         = '_mad_baits_deal_range';
const MAD_BAITS_SWAN_MODE_OPTION        = 'mad_baits_swan_mussel_mode';
const MAD_BAITS_STP_LAUNCH_OPTION       = 'mad_baits_stp_bulk_public_from';
const MAD_BAITS_SPECIALS_HIDE_OOS       = 'mad_baits_compulsive_specials_hide_oos';
const MAD_BAITS_COMPULSIVE_SPECIAL_TAG  = 'compulsive-special';

/**
 * Site timezone used to read launch datetimes.
 *
 * @return DateTimeZone
 */
function mad_baits_launch_timezone() {
	if (function_exists('wp_timezone')) {
		return wp_timezone();
	}

	return new DateTimeZone('UTC');
}

/**
 * Current unix timestamp in WordPress time when available.
 *
 * @return int
 */
function mad_baits_launch_now() {
	return time();
}

/**
 * STP bulk-deal launch datetime saved by the owner, or the 23 October 2026 default.
 *
 * @return string
 */
function mad_baits_get_stp_bulk_public_from() {
	$stored = get_option(MAD_BAITS_STP_LAUNCH_OPTION, '');
	$stored = is_string($stored) ? trim($stored) : '';
	if ('' === $stored) {
		return mad_baits_stp_bulk_launch_default();
	}

	return $stored;
}

/**
 * Swan Mussel owner switch. Anything other than "live" stays hidden.
 *
 * @return string hidden|live
 */
function mad_baits_get_swan_mussel_mode() {
	$mode = get_option(MAD_BAITS_SWAN_MODE_OPTION, 'hidden');
	$mode = is_string($mode) ? strtolower(trim($mode)) : 'hidden';

	return 'live' === $mode ? 'live' : 'hidden';
}

/**
 * Whether sold-out Compulsive specials should leave the specials section.
 *
 * @return bool
 */
function mad_baits_compulsive_specials_hide_out_of_stock() {
	$stored = get_option(MAD_BAITS_SPECIALS_HIDE_OOS, 'yes');

	return 'no' !== (string) $stored;
}

/**
 * Range slugs that must never appear on the public storefront.
 *
 * @return string[]
 */
function mad_baits_get_retired_range_slugs() {
	return array('bbb');
}

/**
 * @param string $range_slug Range slug.
 * @return bool
 */
function mad_baits_range_is_retired($range_slug) {
	return in_array(sanitize_title((string) $range_slug), mad_baits_get_retired_range_slugs(), true);
}

/**
 * Whether a whole range may appear in navigation, grids and search.
 *
 * @param string $range_slug Range slug.
 * @return bool
 */
function mad_baits_range_is_storefront_visible($range_slug) {
	$range_slug = sanitize_title((string) $range_slug);
	if ('' === $range_slug || mad_baits_range_is_retired($range_slug)) {
		return false;
	}

	if ('swan-mussel' === $range_slug) {
		return ! mad_baits_storefront_item_is_hidden(
			array(
				'range_mode'     => mad_baits_get_swan_mussel_mode(),
				'range_earliest' => mad_baits_swan_mussel_earliest_default(),
				'now'            => mad_baits_launch_now(),
				'timezone'       => mad_baits_launch_timezone(),
			)
		);
	}

	return true;
}

/**
 * Product IDs that belong to a retired range. Used to hide BBB without deleting it.
 *
 * @param string $range_slug Range slug.
 * @return int[]
 */
function mad_baits_get_product_ids_for_retired_range($range_slug) {
	$range_slug = sanitize_title((string) $range_slug);
	if ('' === $range_slug || ! function_exists('wc_get_products')) {
		return array();
	}

	$tax_query = array('relation' => 'OR');
	$taxonomies = array(
		'product_tag' => array($range_slug),
		'product_cat' => array($range_slug, 'boilies-' . $range_slug),
		'pa_range'    => array($range_slug),
	);

	foreach ($taxonomies as $taxonomy => $terms) {
		if (! taxonomy_exists($taxonomy)) {
			continue;
		}
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => $terms,
		);
	}

	if (count($tax_query) < 2) {
		return array();
	}

	$ids = wc_get_products(
		array(
			'status'   => array('publish', 'private', 'draft', 'pending'),
			'limit'    => -1,
			'return'   => 'ids',
			'tax_query'=> $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		)
	);

	return array_values(array_unique(array_map('absint', (array) $ids)));
}

/**
 * Whether one product must stay off the public storefront.
 *
 * @param int $product_id Product or variation ID.
 * @return bool
 */
function mad_baits_product_is_storefront_hidden($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return false;
	}

	$parent_id = (int) wp_get_post_parent_id($product_id);
	if ($parent_id > 0 && mad_baits_product_is_storefront_hidden($parent_id)) {
		return true;
	}

	$range_slug = sanitize_title((string) get_post_meta($product_id, MAD_BAITS_RANGE_SLUG_META, true));
	$deal_range = sanitize_title((string) get_post_meta($product_id, MAD_BAITS_DEAL_RANGE_META, true));
	$public_from = trim((string) get_post_meta($product_id, MAD_BAITS_RANGE_PUBLIC_FROM_META, true));

	if ('' === $range_slug) {
		$range_slug = $deal_range;
	}

	$range_mode     = '';
	$range_earliest = '';
	if ('swan-mussel' === $range_slug) {
		$range_mode     = mad_baits_get_swan_mussel_mode();
		$range_earliest = mad_baits_swan_mussel_earliest_default();
	} elseif (mad_baits_range_is_retired($range_slug)) {
		return true;
	}

	$hidden = mad_baits_storefront_item_is_hidden(
		array(
			'retired'        => mad_baits_product_uses_retired_range($product_id),
			'range_mode'     => $range_mode,
			'range_earliest' => $range_earliest,
			'public_from'    => $public_from,
			'now'            => mad_baits_launch_now(),
			'timezone'       => mad_baits_launch_timezone(),
		)
	);

	if ($hidden) {
		return true;
	}

	if ('' === $public_from && '' === $range_mode && '1' === (string) get_post_meta($product_id, MAD_BAITS_RANGE_HIDDEN_META, true)) {
		return true;
	}

	return false;
}

/**
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_product_uses_retired_range($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return false;
	}

	$range_slug = sanitize_title((string) get_post_meta($product_id, MAD_BAITS_RANGE_SLUG_META, true));
	if (mad_baits_range_is_retired($range_slug)) {
		return true;
	}

	foreach (mad_baits_get_retired_range_slugs() as $retired) {
		$tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
		if (is_array($tags) && in_array($retired, array_map('sanitize_title', $tags), true)) {
			return true;
		}
		if (taxonomy_exists('pa_range')) {
			$attrs = wp_get_post_terms($product_id, 'pa_range', array('fields' => 'slugs'));
			if (is_array($attrs) && in_array($retired, array_map('sanitize_title', $attrs), true)) {
				return true;
			}
		}
		$cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		if (is_array($cats)) {
			$cats = array_map('sanitize_title', $cats);
			if (in_array($retired, $cats, true) || in_array('boilies-' . $retired, $cats, true)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Cached IDs currently hidden from customers.
 *
 * @param bool $refresh Force a fresh lookup.
 * @return int[]
 */
function mad_baits_get_hidden_storefront_product_ids($refresh = false) {
	static $building = false;

	if ($building) {
		return array();
	}

	$cache_key = 'mad_baits_hidden_storefront_ids';
	if (! $refresh) {
		$cached = get_transient($cache_key);
		if (is_array($cached)) {
			return array_map('absint', $cached);
		}
	}

	$building = true;
	$ids = array();
	foreach (mad_baits_get_retired_range_slugs() as $retired) {
		$ids = array_merge($ids, mad_baits_get_product_ids_for_retired_range($retired));
	}

	$meta_ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => array('publish', 'private', 'draft', 'pending'),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'   => MAD_BAITS_RANGE_HIDDEN_META,
					'value' => '1',
				),
				array(
					'key'     => MAD_BAITS_RANGE_PUBLIC_FROM_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => MAD_BAITS_RANGE_SLUG_META,
					'value'   => 'swan-mussel',
				),
				array(
					'key'   => MAD_BAITS_DEAL_RANGE_META,
					'value' => 'swan-mussel',
				),
			),
		)
	);

	$ids = array_values(array_unique(array_merge($ids, array_map('absint', (array) $meta_ids))));
	$ids = array_values(
		array_filter(
			$ids,
			static function ($product_id) {
				return mad_baits_product_is_storefront_hidden((int) $product_id);
			}
		)
	);

	set_transient($cache_key, $ids, 10 * MINUTE_IN_SECONDS);
	$building = false;

	return $ids;
}

/**
 * Drop hidden IDs from a product list.
 *
 * @param int[] $product_ids Product IDs.
 * @return int[]
 */
function mad_baits_filter_public_product_ids($product_ids) {
	$hidden = array_fill_keys(mad_baits_get_hidden_storefront_product_ids(), true);
	$public = array();
	foreach ((array) $product_ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || isset($hidden[ $product_id ])) {
			continue;
		}
		$public[] = $product_id;
	}

	return $public;
}

/**
 * Keep catalogue visibility and the hidden flag aligned with launch rules.
 *
 * Does not delete products, stock, prices or order history.
 *
 * @return void
 */
function mad_baits_sync_storefront_visibility() {
	if (! function_exists('wc_get_product')) {
		return;
	}

	$touched = array();

	foreach (mad_baits_get_retired_range_slugs() as $retired) {
		foreach (mad_baits_get_product_ids_for_retired_range($retired) as $product_id) {
			mad_baits_apply_product_visibility($product_id, true);
			update_post_meta($product_id, MAD_BAITS_RANGE_SLUG_META, $retired);
			$touched[] = $product_id;
		}
	}

	$scheduled = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => array('publish', 'private', 'draft', 'pending'),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => MAD_BAITS_RANGE_PUBLIC_FROM_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => MAD_BAITS_RANGE_SLUG_META,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => MAD_BAITS_DEAL_RANGE_META,
					'compare' => 'EXISTS',
				),
			),
		)
	);

	$next_launch = 0;
	$now         = mad_baits_launch_now();
	$timezone    = mad_baits_launch_timezone();

	foreach ((array) $scheduled as $product_id) {
		$product_id = absint($product_id);
		$hidden     = mad_baits_product_is_storefront_hidden($product_id);
		mad_baits_apply_product_visibility($product_id, $hidden);
		$touched[] = $product_id;

		$public_from = trim((string) get_post_meta($product_id, MAD_BAITS_RANGE_PUBLIC_FROM_META, true));
		$launch_ts   = mad_baits_parse_launch_datetime($public_from, $timezone);
		if ($launch_ts > $now && (0 === $next_launch || $launch_ts < $next_launch)) {
			$next_launch = $launch_ts;
		}
	}

	if (mad_baits_range_is_storefront_visible('swan-mussel')) {
		$swan_earliest = mad_baits_parse_launch_datetime(mad_baits_swan_mussel_earliest_default(), $timezone);
		if ($swan_earliest > $now && (0 === $next_launch || $swan_earliest < $next_launch)) {
			$next_launch = $swan_earliest;
		}
	} else {
		$swan_earliest = mad_baits_parse_launch_datetime(mad_baits_swan_mussel_earliest_default(), $timezone);
		if ($swan_earliest > $now && (0 === $next_launch || $swan_earliest < $next_launch)) {
			$next_launch = $swan_earliest;
		}
	}

	update_option('mad_baits_next_launch_ts', $next_launch, false);
	delete_transient('mad_baits_hidden_storefront_ids');
	mad_baits_get_hidden_storefront_product_ids(true);

	unset($touched);
}

/**
 * Set WooCommerce catalogue visibility without deleting the product.
 *
 * @param int  $product_id Product ID.
 * @param bool $hidden     Whether customers should be blocked.
 * @return void
 */
function mad_baits_apply_product_visibility($product_id, $hidden) {
	$product_id = absint($product_id);
	$product    = wc_get_product($product_id);
	if (! $product) {
		return;
	}

	$target = $hidden ? 'hidden' : 'visible';
	$flag   = $hidden ? '1' : '0';

	if ((string) get_post_meta($product_id, MAD_BAITS_RANGE_HIDDEN_META, true) !== $flag) {
		update_post_meta($product_id, MAD_BAITS_RANGE_HIDDEN_META, $flag);
	}

	if ($product->get_catalog_visibility() !== $target && in_array($product->get_status(), array('publish', 'private'), true)) {
		$product->set_catalog_visibility($target);
		$product->save();
	}

	$range_slug = sanitize_title((string) get_post_meta($product_id, MAD_BAITS_RANGE_SLUG_META, true));
	if ('swan-mussel' === $range_slug && ! $hidden && 'publish' !== $product->get_status()) {
		$product->set_status('publish');
		$product->set_catalog_visibility('visible');
		$product->save();
	}
}

/**
 * Run the visibility sync when settings change or a launch time is reached.
 *
 * @return void
 */
function mad_baits_maybe_sync_storefront_visibility() {
	if (! function_exists('wc_get_product')) {
		return;
	}

	$signature = md5(
		wp_json_encode(
			array(
				mad_baits_get_stp_bulk_public_from(),
				mad_baits_get_swan_mussel_mode(),
				mad_baits_swan_mussel_earliest_default(),
			)
		)
	);
	$stored_sig = (string) get_option('mad_baits_visibility_sig', '');
	$next       = (int) get_option('mad_baits_next_launch_ts', 0);
	$now        = mad_baits_launch_now();

	if ($stored_sig === $signature && ($next < 1 || $now < $next)) {
		return;
	}

	mad_baits_sync_storefront_visibility();
	update_option('mad_baits_visibility_sig', $signature, false);
}
add_action('init', 'mad_baits_maybe_sync_storefront_visibility', 30);

/**
 * @param bool       $visible Whether WooCommerce considers the product visible.
 * @param int        $product_id Product ID.
 * @return bool
 */
function mad_baits_filter_product_is_visible($visible, $product_id) {
	if (is_admin() && ! wp_doing_ajax()) {
		return $visible;
	}
	if (mad_baits_product_is_storefront_hidden((int) $product_id)) {
		return false;
	}

	return $visible;
}
add_filter('woocommerce_product_is_visible', 'mad_baits_filter_product_is_visible', 10, 2);

/**
 * @param bool       $purchasable Purchasable flag.
 * @param WC_Product $product     Product.
 * @return bool
 */
function mad_baits_filter_product_is_purchasable($purchasable, $product) {
	if ($product instanceof WC_Product && mad_baits_product_is_storefront_hidden($product->get_id())) {
		return false;
	}

	return $purchasable;
}
add_filter('woocommerce_is_purchasable', 'mad_baits_filter_product_is_purchasable', 10, 2);

/**
 * @param int[] $related Related product IDs.
 * @return int[]
 */
function mad_baits_filter_related_product_ids($related) {
	return mad_baits_filter_public_product_ids((array) $related);
}
add_filter('woocommerce_related_products', 'mad_baits_filter_related_product_ids', 20);

/**
 * Keep hidden products out of shop, search and custom product loops.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function mad_baits_exclude_hidden_products_from_queries($query) {
	static $running = false;

	if ($running || ! $query instanceof WP_Query || is_admin()) {
		return;
	}

	$post_type = $query->get('post_type');
	$types     = array_filter((array) $post_type);
	$is_product_query = in_array('product', $types, true) || in_array('product_variation', $types, true);
	$is_search        = $query->is_search();
	if (! $is_product_query && ! $is_search) {
		return;
	}

	$running = true;
	$hidden  = mad_baits_get_hidden_storefront_product_ids();
	$running = false;
	if (empty($hidden)) {
		return;
	}

	$existing = $query->get('post__not_in');
	$existing = is_array($existing) ? array_map('absint', $existing) : array();
	$query->set('post__not_in', array_values(array_unique(array_merge($existing, $hidden))));
}
add_action('pre_get_posts', 'mad_baits_exclude_hidden_products_from_queries', 25);

/**
 * Send retired BBB links and direct hidden product URLs away from the storefront.
 *
 * @return void
 */
function mad_baits_redirect_hidden_range_requests() {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return;
	}

	$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	$compulsive = function_exists('mad_baits_get_compulsive_angler_url') ? mad_baits_get_compulsive_angler_url() : $shop_url;

	if (function_exists('is_product') && is_product()) {
		$product_id = (int) get_queried_object_id();
		if ($product_id > 0 && mad_baits_product_is_storefront_hidden($product_id) && ! current_user_can('manage_woocommerce')) {
			$target = mad_baits_product_uses_retired_range($product_id) ? $compulsive : $shop_url;
			wp_safe_redirect($target, 302);
			exit;
		}
	}

	$range_slug = '';
	if (function_exists('is_product_tag') && is_product_tag()) {
		$term = get_queried_object();
		$range_slug = $term instanceof WP_Term ? (string) $term->slug : '';
	} elseif (function_exists('is_product_category') && is_product_category()) {
		$term = get_queried_object();
		$range_slug = $term instanceof WP_Term ? (string) $term->slug : '';
	}

	$range_slug = sanitize_title($range_slug);
	if ('boilies-bbb' === $range_slug) {
		$range_slug = 'bbb';
	}
	if ('boilies-swan-mussel' === $range_slug) {
		$range_slug = 'swan-mussel';
	}

	if (mad_baits_range_is_retired($range_slug)) {
		wp_safe_redirect($compulsive, 301);
		exit;
	}

	if ('swan-mussel' === $range_slug && ! mad_baits_range_is_storefront_visible('swan-mussel') && ! current_user_can('manage_woocommerce')) {
		wp_safe_redirect($shop_url, 302);
		exit;
	}

	if (function_exists('is_shop') && is_shop()) {
		$requested = '';
		if (isset($_GET['product_tag'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_title(wp_unslash((string) $_GET['product_tag']));
		} elseif (isset($_GET['filter_range'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_title(wp_unslash((string) $_GET['filter_range']));
		}
		if (mad_baits_range_is_retired($requested)) {
			wp_safe_redirect($compulsive, 301);
			exit;
		}
		if ('swan-mussel' === $requested && ! mad_baits_range_is_storefront_visible('swan-mussel') && ! current_user_can('manage_woocommerce')) {
			wp_safe_redirect($shop_url, 302);
			exit;
		}
	}
}
add_action('template_redirect', 'mad_baits_redirect_hidden_range_requests', 5);

/**
 * Remove BBB from menus that were saved in Appearance → Menus.
 *
 * @param WP_Post[] $items Menu items.
 * @return WP_Post[]
 */
function mad_baits_strip_retired_ranges_from_nav($items) {
	if (! is_array($items)) {
		return $items;
	}

	$kept = array();
	foreach ($items as $item) {
		if (! $item instanceof WP_Post) {
			$kept[] = $item;
			continue;
		}
		$title = sanitize_title((string) $item->title);
		$url   = isset($item->url) ? (string) $item->url : '';
		$is_bbb = ('bbb' === $title)
			|| (bool) preg_match('#/(product-tag|product-category)/(bbb|boilies-bbb)/?([?#].*)?$#', $url)
			|| false !== strpos($url, 'product_tag=bbb')
			|| false !== strpos($url, 'filter_range=bbb');
		$is_swan = ('swan-mussel' === $title || 'swan' === $title)
			|| (bool) preg_match('#/(product-tag|product-category)/(swan-mussel|boilies-swan-mussel)/?#', $url)
			|| false !== strpos($url, 'product_tag=swan-mussel')
			|| false !== strpos($url, 'filter_range=swan-mussel');
		if ($is_bbb) {
			continue;
		}
		if ($is_swan && ! mad_baits_range_is_storefront_visible('swan-mussel')) {
			continue;
		}
		$kept[] = $item;
	}

	return $kept;
}
add_filter('wp_nav_menu_objects', 'mad_baits_strip_retired_ranges_from_nav', 20);

/**
 * Signature range slugs for public hubs.
 *
 * @return string[]
 */
function mad_baits_get_storefront_range_hub_slugs() {
	$slugs = array(
		'asbo',
		'nutz-plus',
		'nutz-banana',
		'pandemic',
		'p-fish-2',
		'p-fish',
		'wicked-white',
		'wicked-whites',
		'calamino',
		'compulsive-angler',
		'compulsive',
		'stp',
	);

	if (mad_baits_range_is_storefront_visible('swan-mussel')) {
		$slugs[] = 'swan-mussel';
	}

	return $slugs;
}

/**
 * Remove BBB choices from saved bundle pools and add STP shelf-life choices.
 *
 * Does not delete WooCommerce products or orders.
 *
 * @return void
 */
function mad_baits_sync_bundle_pool_ranges() {
	if (! defined('MBBB_Plugin::OPTION_POOLS')) {
		return;
	}

	$pools = get_option(MBBB_Plugin::OPTION_POOLS, array());
	if (! is_array($pools) || empty($pools)) {
		return;
	}

	$changed = false;
	foreach ($pools as $pool_id => $pool) {
		if (! is_array($pool) || empty($pool['options']) || ! is_array($pool['options'])) {
			continue;
		}
		$kept = array();
		foreach ($pool['options'] as $option) {
			if (! is_array($option)) {
				$kept[] = $option;
				continue;
			}
			$label = strtolower(trim((string) ($option['label'] ?? '')));
			$value = sanitize_title((string) ($option['value'] ?? ''));
			$range = sanitize_title((string) ($option['range_slug'] ?? ''));
			if ('bbb' === $value || 'bbb' === $range || 'bbb' === $label) {
				$changed = true;
				continue;
			}
			$kept[] = $option;
		}
		$pools[ $pool_id ]['options'] = $kept;
	}

	if (isset($pools['standard-boilie-choices']['options']) && is_array($pools['standard-boilie-choices']['options'])) {
		$existing_labels = array();
		foreach ($pools['standard-boilie-choices']['options'] as $option) {
			if (is_array($option)) {
				$existing_labels[] = strtolower((string) ($option['label'] ?? ''));
			}
		}
		foreach (array('STP 15mm Shelf Life', 'STP 18mm Shelf Life') as $label) {
			if (in_array(strtolower($label), $existing_labels, true)) {
				continue;
			}
			$pools['standard-boilie-choices']['options'][] = array(
				'label'      => $label,
				'value'      => sanitize_title($label),
				'product_id' => 0,
				'image'      => '',
				'active'     => true,
				'range_slug' => 'stp',
			);
			$changed = true;
		}
	}

	if ($changed) {
		update_option(MBBB_Plugin::OPTION_POOLS, $pools, false);
	}
}
add_action('init', 'mad_baits_sync_bundle_pool_ranges', 40);

/**
 * WooCommerce screen for launch dates and Compulsive specials.
 *
 * @return void
 */
function mad_baits_register_range_launch_admin_page() {
	add_submenu_page(
		'woocommerce',
		__('Range launches', 'mad-baits'),
		__('Range launches', 'mad-baits'),
		'manage_woocommerce',
		'mad-baits-range-launches',
		'mad_baits_render_range_launch_admin_page'
	);
}
add_action('admin_menu', 'mad_baits_register_range_launch_admin_page', 60);

/**
 * @return void
 */
function mad_baits_render_range_launch_admin_page() {
	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	$notice = '';
	if (isset($_POST['mad_baits_save_range_launches']) && check_admin_referer('mad_baits_save_range_launches')) {
		$stp_from = isset($_POST['stp_bulk_public_from']) ? sanitize_text_field(wp_unslash((string) $_POST['stp_bulk_public_from'])) : '';
		$stp_from = str_replace('T', ' ', $stp_from);
		if (strlen($stp_from) === 16) {
			$stp_from .= ':00';
		}
		if (mad_baits_parse_launch_datetime($stp_from, mad_baits_launch_timezone()) < 1) {
			$stp_from = mad_baits_stp_bulk_launch_default();
		}
		update_option(MAD_BAITS_STP_LAUNCH_OPTION, $stp_from, false);

		$swan_mode = isset($_POST['swan_mussel_mode']) ? sanitize_key(wp_unslash((string) $_POST['swan_mussel_mode'])) : 'hidden';
		if ('live' === $swan_mode) {
			$allowed = ! mad_baits_storefront_item_is_hidden(
				array(
					'range_mode'     => 'live',
					'range_earliest' => mad_baits_swan_mussel_earliest_default(),
					'now'            => mad_baits_launch_now(),
					'timezone'       => mad_baits_launch_timezone(),
				)
			);
			$swan_mode = $allowed ? 'live' : 'hidden';
			if (! $allowed) {
				$notice = __('Swan Mussel stays hidden until 2027. The live switch was not applied.', 'mad-baits');
			}
		} else {
			$swan_mode = 'hidden';
		}
		update_option(MAD_BAITS_SWAN_MODE_OPTION, $swan_mode, false);
		update_option(MAD_BAITS_SPECIALS_HIDE_OOS, empty($_POST['hide_sold_out_specials']) ? 'no' : 'yes', false);

		if (function_exists('mad_baits_provision_range_products')) {
			mad_baits_provision_range_products();
		}
		delete_option('mad_baits_visibility_sig');
		mad_baits_sync_storefront_visibility();
		if ('' === $notice) {
			$notice = __('Range launch settings saved.', 'mad-baits');
		}
	}

	$stp_value = mad_baits_get_stp_bulk_public_from();
	$stp_local = substr(str_replace(' ', 'T', $stp_value), 0, 16);
	$swan_mode = mad_baits_get_swan_mussel_mode();
	$swan_can_go_live = ! mad_baits_storefront_item_is_hidden(
		array(
			'range_mode'     => 'live',
			'range_earliest' => mad_baits_swan_mussel_earliest_default(),
			'now'            => mad_baits_launch_now(),
			'timezone'       => mad_baits_launch_timezone(),
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Range launches', 'mad-baits'); ?></h1>
		<?php if ('' !== $notice) : ?>
			<div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e('BBB stays in WooCommerce for previous orders and is hidden from the shop. These settings control STP bulk deals, Swan Mussel and Compulsive specials without a code change.', 'mad-baits'); ?></p>
		<form method="post">
			<?php wp_nonce_field('mad_baits_save_range_launches'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="stp_bulk_public_from"><?php esc_html_e('STP 10kg and 20kg deals', 'mad-baits'); ?></label></th>
					<td>
						<input type="datetime-local" id="stp_bulk_public_from" name="stp_bulk_public_from" value="<?php echo esc_attr($stp_local); ?>" />
						<p class="description"><?php esc_html_e('Uses the site timezone. The deals stay off the shop, search, related products and bundle sections until this moment. Default is 23 October 2026 at 00:00. This does not apply to Swan Mussel.', 'mad-baits'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Swan Mussel', 'mad-baits'); ?></th>
					<td>
						<label><input type="radio" name="swan_mussel_mode" value="hidden" <?php checked('hidden', $swan_mode); ?> /> <?php esc_html_e('Hidden', 'mad-baits'); ?></label><br />
						<label><input type="radio" name="swan_mussel_mode" value="live" <?php checked('live', $swan_mode); ?> <?php disabled(! $swan_can_go_live); ?> /> <?php esc_html_e('Live on the storefront', 'mad-baits'); ?></label>
						<p class="description"><?php esc_html_e('The range and its 15mm / 18mm shelf-life boilies are prepared now. They stay hidden until you choose Live, and Live is blocked before 1 January 2027.', 'mad-baits'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Compulsive specials', 'mad-baits'); ?></th>
					<td>
						<label><input type="checkbox" name="hide_sold_out_specials" value="1" <?php checked(mad_baits_compulsive_specials_hide_out_of_stock()); ?> /> <?php esc_html_e('Hide sold-out Compulsive specials', 'mad-baits'); ?></label>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: product tag name */
									__('Tag a Compulsive product with “%s” to add it to the Compulsive Specials section. Remove the tag, or mark it out of stock, to take a limited run off the section.', 'mad-baits'),
									'Compulsive Special'
								)
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(__('Save launch settings', 'mad-baits'), 'primary', 'mad_baits_save_range_launches'); ?>
		</form>
	</div>
	<?php
}

/**
 * Launch datetime field on the product editor.
 *
 * @return void
 */
function mad_baits_register_product_launch_meta_box() {
	add_meta_box(
		'mad-baits-product-launch',
		__('Storefront launch', 'mad-baits'),
		'mad_baits_render_product_launch_meta_box',
		'product',
		'side',
		'default'
	);
}
add_action('add_meta_boxes', 'mad_baits_register_product_launch_meta_box');

/**
 * @param WP_Post $post Product post.
 * @return void
 */
function mad_baits_render_product_launch_meta_box($post) {
	wp_nonce_field('mad_baits_product_launch', 'mad_baits_product_launch_nonce');
	$public_from = trim((string) get_post_meta($post->ID, MAD_BAITS_RANGE_PUBLIC_FROM_META, true));
	$local       = '' !== $public_from ? substr(str_replace(' ', 'T', $public_from), 0, 16) : '';
	$range       = sanitize_title((string) get_post_meta($post->ID, MAD_BAITS_RANGE_SLUG_META, true));
	$deal_range  = sanitize_title((string) get_post_meta($post->ID, MAD_BAITS_DEAL_RANGE_META, true));
	?>
	<p>
		<label for="mad_baits_public_from"><strong><?php esc_html_e('Public from', 'mad-baits'); ?></strong></label><br />
		<input type="datetime-local" id="mad_baits_public_from" name="mad_baits_public_from" value="<?php echo esc_attr($local); ?>" />
	</p>
	<p class="description"><?php esc_html_e('Leave empty for a normal product. A future date keeps this product out of the shop until then. Site timezone applies.', 'mad-baits'); ?></p>
	<p>
		<label for="mad_baits_deal_range"><strong><?php esc_html_e('Bulk deal range', 'mad-baits'); ?></strong></label><br />
		<select id="mad_baits_deal_range" name="mad_baits_deal_range">
			<option value=""><?php esc_html_e('Not a range bulk deal', 'mad-baits'); ?></option>
			<option value="stp" <?php selected('stp', $deal_range); ?>><?php esc_html_e('STP only', 'mad-baits'); ?></option>
			<option value="swan-mussel" <?php selected('swan-mussel', $deal_range); ?>><?php esc_html_e('Swan Mussel only', 'mad-baits'); ?></option>
		</select>
	</p>
	<?php if ('' !== $range) : ?>
		<p class="description"><?php echo esc_html(sprintf(/* translators: %s: range slug */ __('Range key: %s', 'mad-baits'), $range)); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * @param int $post_id Product ID.
 * @return void
 */
function mad_baits_save_product_launch_meta_box($post_id) {
	if (! isset($_POST['mad_baits_product_launch_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mad_baits_product_launch_nonce'])), 'mad_baits_product_launch')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$public_from = isset($_POST['mad_baits_public_from']) ? sanitize_text_field(wp_unslash((string) $_POST['mad_baits_public_from'])) : '';
	$public_from = str_replace('T', ' ', $public_from);
	if (strlen($public_from) === 16) {
		$public_from .= ':00';
	}
	if ('' === $public_from || mad_baits_parse_launch_datetime($public_from, mad_baits_launch_timezone()) < 1) {
		delete_post_meta($post_id, MAD_BAITS_RANGE_PUBLIC_FROM_META);
	} else {
		update_post_meta($post_id, MAD_BAITS_RANGE_PUBLIC_FROM_META, $public_from);
	}

	$deal_range = isset($_POST['mad_baits_deal_range']) ? sanitize_title(wp_unslash((string) $_POST['mad_baits_deal_range'])) : '';
	if (in_array($deal_range, array('stp', 'swan-mussel'), true)) {
		update_post_meta($post_id, MAD_BAITS_DEAL_RANGE_META, $deal_range);
	} else {
		delete_post_meta($post_id, MAD_BAITS_DEAL_RANGE_META);
	}

	$option_launch = function_exists('mad_baits_get_stp_bulk_public_from') ? mad_baits_get_stp_bulk_public_from() : '';
	$saved_launch  = trim((string) get_post_meta($post_id, MAD_BAITS_RANGE_PUBLIC_FROM_META, true));
	if ('stp' === $deal_range && '' !== $saved_launch && $saved_launch === $option_launch) {
		update_post_meta($post_id, '_mad_baits_launch_follows_option', 'yes');
	} else {
		update_post_meta($post_id, '_mad_baits_launch_follows_option', 'no');
	}

	delete_option('mad_baits_visibility_sig');
}
add_action('save_post_product', 'mad_baits_save_product_launch_meta_box');
