<?php
/**
 * Single product page layout and unified session recommendations.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

require_once get_theme_file_path('/inc/single-product-pdp-layout.php');

/**
 * Configure PDP hooks: one "Complete Your Session" block, no duplicate carousels.
 *
 * @return void
 */
function mad_baits_configure_single_product_pdp() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	remove_action('woocommerce_before_single_product', 'woocommerce_output_all_notices', 10);

	remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
	remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
	remove_action('woocommerce_after_single_product_summary', 'mad_baits_render_pair_it_with_section', 17);
	remove_action('woocommerce_after_single_product_summary', 'mad_baits_render_perfect_match_section', 18);

	add_action('woocommerce_after_single_product_summary', 'mad_baits_render_complete_your_session_section', 20);
	add_action('woocommerce_after_single_product_summary', 'mad_baits_render_complete_the_range_links', 22);
}
add_action('wp', 'mad_baits_configure_single_product_pdp', 20);

/**
 * Add PDP layout class to the product wrapper.
 *
 * @param array      $classes CSS classes.
 * @param WC_Product $product Product object.
 * @return array
 */
function mad_baits_single_product_post_class($classes, $product) {
	unset($product);
	$classes[] = 'mad-pdp-product';

	return array_values(array_unique($classes));
}
add_filter('woocommerce_post_class', 'mad_baits_single_product_post_class', 10, 2);

/**
 * Collect posted variation attributes from the add-to-cart form.
 *
 * @return array<string, string>
 */
function mad_baits_get_posted_variation_attributes() {
	$attributes = array();

	foreach ($_POST as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (0 !== strpos((string) $key, 'attribute_')) {
			continue;
		}
		$attributes[ wp_unslash((string) $key) ] = wc_clean(wp_unslash((string) $value));
	}

	return $attributes;
}

/**
 * Resolve variation_id server-side when attributes are chosen but hidden field lags.
 *
 * @param bool $passed       Validation result.
 * @param int  $product_id   Product ID.
 * @param int  $quantity     Quantity.
 * @param int  $variation_id Variation ID.
 * @param array<string, string>|null $variations Attribute map.
 * @return bool
 */
function mad_baits_resolve_variable_add_to_cart_validation($passed, $product_id, $quantity, $variation_id = 0, $variations = null) {
	unset($quantity);

	if (! $passed) {
		return false;
	}

	$product = wc_get_product($product_id);
	if (! $product || ! $product->is_type('variable')) {
		return $passed;
	}

	$variation_id = absint($variation_id);
	if ($variation_id > 0) {
		return $passed;
	}

	$attributes = is_array($variations) ? $variations : mad_baits_get_posted_variation_attributes();
	if (empty($attributes)) {
		return $passed;
	}

	$missing = false;
	foreach ($attributes as $value) {
		if ('' === (string) $value) {
			$missing = true;
			break;
		}
	}
	if ($missing) {
		return $passed;
	}

	$match_id = (int) (new WC_Product_Data_Store_CPT())->find_matching_product_variation($product, $attributes);
	if ($match_id > 0) {
		$_POST['variation_id']    = $match_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_REQUEST['variation_id'] = $match_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $passed;
	}

	return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'mad_baits_resolve_variable_add_to_cart_validation', 5, 5);

/**
 * Remove stale variation errors once an item is successfully added.
 *
 * @param string $cart_item_key Cart item key.
 * @return void
 */
function mad_baits_clear_variation_errors_after_add_to_cart($cart_item_key) {
	if ('' === (string) $cart_item_key) {
		return;
	}
	wc_clear_notices('error');
}
add_action('woocommerce_add_to_cart', 'mad_baits_clear_variation_errors_after_add_to_cart', 20);

/**
 * Whether a WooCommerce notice is the standard variable-product options error.
 *
 * @param mixed $notice Notice payload.
 * @return bool
 */
function mad_baits_is_choose_product_options_notice($notice) {
	$text = '';
	if (is_array($notice) && isset($notice['notice'])) {
		$text = wp_strip_all_tags((string) $notice['notice']);
	} else {
		$text = wp_strip_all_tags((string) $notice);
	}

	if ('' === $text) {
		return false;
	}

	return false !== stripos($text, 'choose product options');
}

/**
 * Whether "choose product options" notices should remain visible on this request.
 *
 * @return bool
 */
function mad_baits_should_keep_choose_product_options_notices() {
	return (function_exists('is_cart') && is_cart())
		|| (function_exists('is_checkout') && is_checkout());
}

/**
 * Redirect variable parent ?add-to-cart links to the product page (prevents WC error notices).
 *
 * @return void
 */
function mad_baits_redirect_variable_add_to_cart_without_variation() {
	if (is_admin() || wp_doing_ajax()) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if (! isset($_REQUEST['add-to-cart'])) {
		return;
	}

	$product_id = absint(wp_unslash((string) $_REQUEST['add-to-cart']));
	if ($product_id < 1 || ! function_exists('wc_get_product')) {
		return;
	}

	$product = wc_get_product($product_id);
	if (! $product instanceof WC_Product || ! $product->is_type('variable')) {
		return;
	}

	$variation_id = isset($_REQUEST['variation_id']) ? absint(wp_unslash((string) $_REQUEST['variation_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ($variation_id > 0) {
		return;
	}

	wp_safe_redirect($product->get_permalink());
	exit;
}
add_action('wp_loaded', 'mad_baits_redirect_variable_add_to_cart_without_variation', 19);

/**
 * Drop stale "choose product options" errors from the WooCommerce session.
 *
 * Those notices are created when a variable parent product is added without a
 * variation. WooCommerce keeps them in session, so they reappear on every page.
 *
 * @return void
 */
function mad_baits_prune_choose_product_options_notices() {
	if (! function_exists('WC') || ! WC()->session) {
		return;
	}

	if (mad_baits_should_keep_choose_product_options_notices()) {
		return;
	}

	$notices = WC()->session->get('wc_notices', array());
	if (! is_array($notices) || empty($notices['error'])) {
		return;
	}

	$errors = array();
	foreach ((array) $notices['error'] as $notice) {
		if (mad_baits_is_choose_product_options_notice($notice)) {
			continue;
		}
		$errors[] = $notice;
	}

	if (count($errors) === count((array) $notices['error'])) {
		return;
	}

	if (empty($errors)) {
		unset($notices['error']);
	} else {
		$notices['error'] = $errors;
	}

	WC()->session->set('wc_notices', $notices);
}
add_action('template_redirect', 'mad_baits_prune_choose_product_options_notices', 3);
add_action('wp', 'mad_baits_prune_choose_product_options_notices', 99);
add_action('woocommerce_before_single_product', 'mad_baits_prune_choose_product_options_notices', 2);

/**
 * Deduplicate identical WooCommerce notices before they render.
 *
 * @return void
 */
function mad_baits_dedupe_woocommerce_notices() {
	if (! function_exists('WC') || ! WC()->session) {
		return;
	}

	$notices = WC()->session->get('wc_notices', array());
	if (! is_array($notices) || empty($notices)) {
		return;
	}

	foreach ($notices as $type => $items) {
		if (! is_array($items)) {
			continue;
		}

		$seen    = array();
		$unique  = array();
		foreach ($items as $notice) {
			$text = '';
			if (is_array($notice) && isset($notice['notice'])) {
				$text = wp_strip_all_tags((string) $notice['notice']);
			} else {
				$text = wp_strip_all_tags((string) $notice);
			}
			$hash = md5(strtolower(trim($text)));
			if ('' === $text || isset($seen[ $hash ])) {
				continue;
			}
			$seen[ $hash ] = true;
			$unique[]      = $notice;
		}
		$notices[ $type ] = $unique;
	}

	if (! empty($notices['success']) && ! empty($notices['error'])) {
		unset($notices['error']);
	}

	WC()->session->set('wc_notices', $notices);
}
add_action('template_redirect', 'mad_baits_dedupe_woocommerce_notices', 4);
add_action('woocommerce_before_single_product', 'mad_baits_dedupe_woocommerce_notices', 4);

/**
 * Product IDs for the unified Complete Your Session module.
 *
 * @param int $product_id Product ID.
 * @param int $limit      Max products.
 * @return int[]
 */
function mad_baits_get_complete_session_product_ids($product_id, $limit = 4) {
	$product_id = absint($product_id);
	$limit      = max(1, absint($limit));
	$ids        = array();

	if (function_exists('mad_baits_get_pair_it_with_product_ids')) {
		$ids = mad_baits_get_pair_it_with_product_ids($product_id, $limit);
	}

	if (count($ids) < $limit && function_exists('wc_get_related_products')) {
		$related = wc_get_related_products($product_id, $limit * 3);
		foreach ((array) $related as $related_id) {
			$related_id = absint($related_id);
			if ($related_id < 1 || $related_id === $product_id) {
				continue;
			}
			if (! in_array($related_id, $ids, true)) {
				$ids[] = $related_id;
			}
			if (count($ids) >= $limit) {
				break;
			}
		}
	}

	return array_slice(array_values(array_unique(array_map('absint', $ids))), 0, $limit);
}

/**
 * Render unified Complete Your Session recommendations.
 *
 * @return void
 */
function mad_baits_render_complete_your_session_section() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	global $product;
	if (! is_object($product) || ! is_a($product, 'WC_Product')) {
		return;
	}

	$product_id  = (int) $product->get_id();
	$product_ids = mad_baits_get_complete_session_product_ids($product_id, 4);
	if (empty($product_ids)) {
		return;
	}

	$enable_variations = function_exists('mad_baits_enable_compact_card_variations')
		? mad_baits_enable_compact_card_variations()
		: false;
	?>
	<section class="mad-session-upsell mad-pdp-module" aria-labelledby="mad-complete-session-heading">
		<div class="container mad-pdp-module__inner">
			<header class="mad-session-upsell__intro mad-pdp-module__header">
				<p class="section__kicker"><?php esc_html_e('Session Matching', 'mad-baits'); ?></p>
				<h2 id="mad-complete-session-heading"><?php esc_html_e('Complete Your Session', 'mad-baits'); ?></h2>
				<p class="mad-pdp-module__lede"><?php esc_html_e('Pair this product with matching hookbaits, liquids and session-ready essentials.', 'mad-baits'); ?></p>
			</header>

			<div class="mad-session-upsell__products">
				<ul class="products mad-session-upsell__grid columns-<?php echo esc_attr((string) min(4, count($product_ids))); ?>">
					<?php foreach ($product_ids as $session_product_id) : ?>
						<?php
						if (! function_exists('mad_baits_render_product_card') || ! wc_get_product($session_product_id)) {
							continue;
						}
						?>
						<li class="product type-product">
							<?php mad_baits_render_product_card((int) $session_product_id, (bool) $enable_variations, 'complete-session'); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			</div>
	</section>
	<?php
}

/**
 * Whether a product belongs to a bait category that should show range cross-links.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_product_should_show_range_links($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return false;
	}

	$slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
	if (! is_array($slugs) || is_wp_error($slugs)) {
		return false;
	}

	$targets = array(
		'boilies',
		'boilie',
		'hookbaits',
		'hookbait',
		'liquids',
		'liquid',
		'pellets',
		'pellet',
	);

	return (bool) array_intersect($targets, array_map('sanitize_title', $slugs));
}

/**
 * Contextual “Complete the range” internal links on bait PDPs.
 *
 * @return void
 */
function mad_baits_render_complete_the_range_links() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	global $product;
	if (! $product instanceof WC_Product) {
		return;
	}

	$product_id = (int) $product->get_id();
	if (! mad_baits_product_should_show_range_links($product_id)) {
		return;
	}

	$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	$links    = array();

	if (function_exists('mad_baits_get_product_cat_link')) {
		$links[] = array(__('Boilies', 'mad-baits'), mad_baits_get_product_cat_link(array('boilies', 'boilie'), $shop_url));
		$links[] = array(__('Hookbaits', 'mad-baits'), mad_baits_get_product_cat_link(array('hookbaits', 'hookbait'), $shop_url));
		$links[] = array(__('Liquids', 'mad-baits'), mad_baits_get_product_cat_link(array('liquids', 'liquid'), $shop_url));
		$links[] = array(__('Pellets', 'mad-baits'), mad_baits_get_product_cat_link(array('pellets', 'pellet'), $shop_url));
	}

	if (function_exists('mad_baits_get_product_signature_range_tags') && function_exists('mad_baits_get_range_tag_shop_url')) {
		$range_slugs = mad_baits_get_product_signature_range_tags($product_id);
		if (! empty($range_slugs[0])) {
			$range_slug = (string) $range_slugs[0];
			$label      = function_exists('mad_baits_get_range_display_label')
				? mad_baits_get_range_display_label($range_slug, $range_slug)
				: $range_slug;
			$links[] = array(
				sprintf(
					/* translators: %s: bait range name */
					__('%s range', 'mad-baits'),
					$label
				),
				mad_baits_get_range_tag_shop_url($range_slug),
			);
		}
	}

	if (function_exists('mad_baits_get_bundle_deals_url')) {
		$links[] = array(__('Bundles & Deals', 'mad-baits'), mad_baits_get_bundle_deals_url());
	}
	if (function_exists('mad_baits_get_ai_bait_finder_url')) {
		$links[] = array(__('AI Bait Finder', 'mad-baits'), mad_baits_get_ai_bait_finder_url());
	}

	$links = array_values(
		array_filter(
			$links,
			static function ($link) {
				return is_array($link) && ! empty($link[0]) && ! empty($link[1]);
			}
		)
	);

	if (empty($links)) {
		return;
	}
	?>
	<section class="mad-seo-internal-links mad-pdp-range-links mad-pdp-module" aria-labelledby="mad-complete-range-heading">
		<div class="container mad-pdp-module__inner">
			<header class="mad-pdp-module__header">
				<h2 id="mad-complete-range-heading" class="mad-seo-internal-links__title"><?php esc_html_e('Complete the range', 'mad-baits'); ?></h2>
				<p class="mad-pdp-module__lede"><?php esc_html_e('Match this bait with hookbaits, liquids, pellets, bundles or the AI Bait Finder.', 'mad-baits'); ?></p>
			</header>
			<nav class="mad-shop-quick-filters mad-seo-internal-links__nav" aria-label="<?php esc_attr_e('Related bait categories', 'mad-baits'); ?>">
				<?php foreach ($links as $link) : ?>
					<a class="mad-shop-quick-filters__link" href="<?php echo esc_url((string) $link[1]); ?>"><?php echo esc_html((string) $link[0]); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</section>
	<?php
}
