<?php
/**
 * Lightweight favourites system (user meta + guest local fallback).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Meta key used for customer favourites.
 */
function mad_baits_favourites_meta_key() {
	return '_mad_baits_favourite_product_ids';
}

/**
 * Get current user favourites.
 *
 * @param int $user_id User ID.
 * @return array<int>
 */
function mad_baits_get_user_favourites($user_id = 0) {
	$user_id = $user_id ? absint($user_id) : get_current_user_id();
	if ($user_id < 1) {
		return array();
	}

	$raw = get_user_meta($user_id, mad_baits_favourites_meta_key(), true);
	if (! is_array($raw)) {
		return array();
	}

	$ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
	return $ids;
}

/**
 * Save favourites list for user.
 *
 * @param int        $user_id User ID.
 * @param array<int> $ids Product IDs.
 * @return void
 */
function mad_baits_save_user_favourites($user_id, $ids) {
	$user_id = absint($user_id);
	if ($user_id < 1) {
		return;
	}

	$ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
	update_user_meta($user_id, mad_baits_favourites_meta_key(), $ids);
}

/**
 * Toggle favourite product for current user.
 */
function mad_baits_ajax_favourites_toggle() {
	check_ajax_referer('mad_baits_favourites', 'nonce');

	if (! is_user_logged_in()) {
		wp_send_json_success(
			array(
				'logged_in' => false,
				'message'   => __('Saved locally on this device.', 'mad-baits'),
			)
		);
	}

	$product_id = isset($_POST['product_id']) ? absint(wp_unslash((string) $_POST['product_id'])) : 0;
	if ($product_id < 1 || 'product' !== get_post_type($product_id)) {
		wp_send_json_error(array('message' => __('Invalid product.', 'mad-baits')));
	}

	$user_id    = get_current_user_id();
	$favourites = mad_baits_get_user_favourites($user_id);
	$is_saved   = in_array($product_id, $favourites, true);

	if ($is_saved) {
		$favourites = array_values(array_diff($favourites, array($product_id)));
		$is_saved   = false;
	} else {
		$favourites[] = $product_id;
		$is_saved     = true;
	}

	mad_baits_save_user_favourites($user_id, $favourites);

	wp_send_json_success(
		array(
			'logged_in'    => true,
			'product_id'   => $product_id,
			'is_favourite' => $is_saved,
			'count'        => count($favourites),
			'ids'          => $favourites,
		)
	);
}
add_action('wp_ajax_mad_baits_favourites_toggle', 'mad_baits_ajax_favourites_toggle');
add_action('wp_ajax_nopriv_mad_baits_favourites_toggle', 'mad_baits_ajax_favourites_toggle');

/**
 * Return current favourites list for JS sync.
 */
function mad_baits_ajax_favourites_get() {
	check_ajax_referer('mad_baits_favourites', 'nonce');

	if (! is_user_logged_in()) {
		wp_send_json_success(
			array(
				'logged_in' => false,
				'ids'       => array(),
			)
		);
	}

	$ids = mad_baits_get_user_favourites(get_current_user_id());
	wp_send_json_success(
		array(
			'logged_in' => true,
			'ids'       => $ids,
		)
	);
}
add_action('wp_ajax_mad_baits_favourites_get', 'mad_baits_ajax_favourites_get');
add_action('wp_ajax_nopriv_mad_baits_favourites_get', 'mad_baits_ajax_favourites_get');

/**
 * Add all favourite products to basket.
 */
function mad_baits_ajax_favourites_add_all_to_cart() {
	check_ajax_referer('mad_baits_favourites', 'nonce');

	if (! is_user_logged_in()) {
		wp_send_json_error(array('message' => __('Please sign in to use account favourites.', 'mad-baits')));
	}

	if (! function_exists('WC') || ! WC()->cart) {
		wp_send_json_error(array('message' => __('Cart is unavailable right now.', 'mad-baits')));
	}

	$favourites = mad_baits_get_user_favourites(get_current_user_id());
	if (empty($favourites)) {
		wp_send_json_error(array('message' => __('No favourites saved yet.', 'mad-baits')));
	}

	$added_count = 0;
	foreach ($favourites as $product_id) {
		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
			continue;
		}

		// Variable products need a chosen variation — skip to avoid session error notices.
		if ($product->is_type('variable')) {
			continue;
		}

		$added = WC()->cart->add_to_cart($product_id, 1);
		if ($added) {
			$added_count++;
		}
	}

	if ($added_count < 1) {
		wp_send_json_error(array('message' => __('No favourite products could be added right now.', 'mad-baits')));
	}

	wp_send_json_success(
		array(
			'added_count' => $added_count,
			'cart_url'    => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
			'message'     => sprintf(
				/* translators: %d number of products */
				_n('%d favourite added to basket.', '%d favourites added to basket.', $added_count, 'mad-baits'),
				$added_count
			),
		)
	);
}
add_action('wp_ajax_mad_baits_favourites_add_all_to_cart', 'mad_baits_ajax_favourites_add_all_to_cart');
add_action('wp_ajax_nopriv_mad_baits_favourites_add_all_to_cart', 'mad_baits_ajax_favourites_add_all_to_cart');

/**
 * Account dashboard favourites panel.
 *
 * @return void
 */
function mad_baits_render_account_favourites_panel() {
	if (! function_exists('is_account_page') || ! is_account_page()) {
		return;
	}
	?>
	<section class="mad-account-app-panel" data-account-favourites-panel>
		<header>
			<h3><?php esc_html_e('Favourite Baits', 'mad-baits'); ?></h3>
			<p><?php esc_html_e('Your saved bait shortlist. Add all in one tap when building a quick session order.', 'mad-baits'); ?></p>
		</header>
		<div class="mad-account-app-panel__list" data-favourites-list></div>
		<button class="mad-button mad-button--small" type="button" data-favourites-add-all><?php esc_html_e('Add All Favourites To Basket', 'mad-baits'); ?></button>
	</section>
	<?php
}
add_action('woocommerce_account_dashboard', 'mad_baits_render_account_favourites_panel', 9);

/**
 * Render compact product preview card used in app panels.
 *
 * @param WC_Product $product Product object.
 * @return string
 */
function mad_baits_render_app_product_preview_html($product) {
	if (! $product instanceof WC_Product) {
		return '';
	}

	$product_id = (int) $product->get_id();
	$url        = get_permalink($product_id);
	$name       = $product->get_name();
	$price_html = $product->get_price_html();
	$image_id   = (int) $product->get_image_id();
	$image_size = function_exists('mad_baits_get_product_card_image_size') ? mad_baits_get_product_card_image_size() : 'woocommerce_thumbnail';
	$image_url  = $image_id > 0 ? (string) wp_get_attachment_image_url($image_id, $image_size) : '';
	if ('' === $image_url) {
		$image_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src($image_size) : '';
	}

	ob_start();
	?>
	<a class="mad-app-product-preview" href="<?php echo esc_url(is_string($url) ? $url : home_url('/shop/')); ?>">
		<span class="mad-app-product-preview__media" style="<?php echo esc_attr("--mad-preview-image: url('" . esc_url_raw($image_url) . "');"); ?>"></span>
		<span class="mad-app-product-preview__copy">
			<strong><?php echo esc_html((string) $name); ?></strong>
			<?php if (is_string($price_html) && '' !== $price_html) : ?>
				<span><?php echo wp_kses_post($price_html); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php
	return (string) ob_get_clean();
}

/**
 * Return compact product previews for requested IDs.
 *
 * @return void
 */
function mad_baits_ajax_get_product_previews() {
	check_ajax_referer('mad_baits_favourites', 'nonce');

	if (! function_exists('wc_get_product')) {
		wp_send_json_success(array('items' => array()));
	}

	$raw_ids = isset($_POST['product_ids']) ? (array) wp_unslash($_POST['product_ids']) : array();
	$ids     = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));
	$ids     = array_slice($ids, 0, 12);

	if (empty($ids)) {
		wp_send_json_success(array('items' => array()));
	}

	$items = array();
	foreach ($ids as $id) {
		$product = wc_get_product($id);
		if (! $product instanceof WC_Product) {
			continue;
		}
		$items[] = array(
			'id'   => $id,
			'html' => mad_baits_render_app_product_preview_html($product),
		);
	}

	wp_send_json_success(array('items' => $items));
}
add_action('wp_ajax_mad_baits_get_product_previews', 'mad_baits_ajax_get_product_previews');
add_action('wp_ajax_nopriv_mad_baits_get_product_previews', 'mad_baits_ajax_get_product_previews');
