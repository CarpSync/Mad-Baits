<?php
/**
 * Meta Pixel / Conversions API prep — centralised event hooks.
 *
 * Set pixel ID via wp-config: define( 'MAD_BAITS_META_PIXEL_ID', '123456789' );
 * Or option: update_option( 'mad_baits_meta_pixel_id', '123456789' );
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Resolve Meta Pixel ID.
 *
 * @return string
 */
function mad_baits_get_meta_pixel_id() {
	if (defined('MAD_BAITS_META_PIXEL_ID') && MAD_BAITS_META_PIXEL_ID) {
		return sanitize_text_field((string) MAD_BAITS_META_PIXEL_ID);
	}

	return sanitize_text_field((string) get_option('mad_baits_meta_pixel_id', ''));
}

/**
 * Whether tracking is enabled.
 *
 * @return bool
 */
function mad_baits_meta_tracking_enabled() {
	$pixel_id = mad_baits_get_meta_pixel_id();
	if ('' === $pixel_id) {
		return false;
	}

	return (bool) apply_filters('mad_baits_meta_tracking_enabled', true);
}

/**
 * Print Meta Pixel base code in head.
 *
 * @return void
 */
function mad_baits_print_meta_pixel_base() {
	if (! mad_baits_meta_tracking_enabled() || is_admin()) {
		return;
	}

	$pixel_id = mad_baits_get_meta_pixel_id();
	?>
	<!-- Meta Pixel (Mad Baits) -->
	<script>
	window.madBaitsMetaPixelId = <?php echo wp_json_encode($pixel_id); ?>;
	!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
	n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
	t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
	'https://connect.facebook.net/en_US/fbevents.js');
	fbq('init', window.madBaitsMetaPixelId);
	fbq('track', 'PageView');
	</script>
	<noscript><img height="1" width="1" style="display:none" alt=""
		src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1" /></noscript>
	<?php
}
add_action('wp_head', 'mad_baits_print_meta_pixel_base', 25);

/**
 * Build tracking payload for current product page.
 *
 * @return array<string, mixed>|null
 */
function mad_baits_meta_get_view_content_payload() {
	if (! function_exists('is_product') || ! is_product()) {
		return null;
	}

	global $product;
	if (! $product instanceof WC_Product) {
		return null;
	}

	return array(
		'event'      => 'ViewContent',
		'content_ids'=> array((string) $product->get_id()),
		'content_name'=> $product->get_name(),
		'content_type'=> 'product',
		'value'      => (float) $product->get_price(),
		'currency'   => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'GBP',
	);
}

/**
 * Build purchase payload on thank-you page.
 *
 * @param int $order_id Order ID.
 * @return array<string, mixed>|null
 */
function mad_baits_meta_get_purchase_payload($order_id) {
	$order_id = absint($order_id);
	if ($order_id < 1 || ! function_exists('wc_get_order')) {
		return null;
	}

	$order = wc_get_order($order_id);
	if (! $order) {
		return null;
	}

	$content_ids = array();
	foreach ($order->get_items() as $item) {
		$product_id = $item->get_product_id();
		if ($product_id) {
			$content_ids[] = (string) $product_id;
		}
	}

	return array(
		'event'      => 'Purchase',
		'content_ids'=> $content_ids,
		'value'      => (float) $order->get_total(),
		'currency'   => $order->get_currency(),
		'order_id'   => (string) $order_id,
	);
}

/**
 * Pass queued events to frontend (single fire via JS).
 *
 * @return void
 */
function mad_baits_meta_tracking_localize() {
	if (! mad_baits_meta_tracking_enabled()) {
		return;
	}

	$events = array();

	$view_content = mad_baits_meta_get_view_content_payload();
	if ($view_content) {
		$events[] = $view_content;
	}

	if (function_exists('is_order_received_page') && is_order_received_page()) {
		$order_id = absint(get_query_var('order-received'));
		if ($order_id < 1 && isset($_GET['order-received'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_id = absint(wp_unslash((string) $_GET['order-received']));
		}
		$purchase = mad_baits_meta_get_purchase_payload($order_id);
		if ($purchase) {
			$events[] = $purchase;
		}
	}

	if (function_exists('is_checkout') && is_checkout() && ! is_order_received_page()) {
		$events[] = array(
			'event'    => 'InitiateCheckout',
			'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'GBP',
			'value'    => (function_exists('WC') && WC()->cart) ? (float) WC()->cart->get_total('edit') : 0,
		);
	}

	wp_add_inline_script(
		'mad-baits-social',
		'window.madBaitsMetaEvents = ' . wp_json_encode($events) . ';',
		'before'
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_meta_tracking_localize', 50);

/**
 * AddToCart server-side hint for simple products (JS handles AJAX).
 *
 * @param string $cart_item_key Cart item key.
 * @param int    $product_id Product ID.
 * @return void
 */
function mad_baits_meta_note_add_to_cart($cart_item_key, $product_id) {
	if (! mad_baits_meta_tracking_enabled() || ! function_exists('wc_get_product')) {
		return;
	}

	$product = wc_get_product($product_id);
	if (! $product) {
		return;
	}

	WC()->session->set(
		'mad_baits_meta_last_add_to_cart',
		array(
			'event'       => 'AddToCart',
			'content_ids' => array((string) $product->get_id()),
			'content_name'=> $product->get_name(),
			'value'       => (float) $product->get_price(),
			'currency'    => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'GBP',
		)
	);
}
add_action('woocommerce_add_to_cart', 'mad_baits_meta_note_add_to_cart', 20, 2);

/**
 * Expose last add-to-cart event to JS once.
 *
 * @return void
 */
function mad_baits_meta_flush_add_to_cart_event() {
	if (! mad_baits_meta_tracking_enabled() || ! function_exists('WC') || ! WC()->session) {
		return;
	}

	$payload = WC()->session->get('mad_baits_meta_last_add_to_cart');
	if (! is_array($payload) || empty($payload)) {
		return;
	}

	WC()->session->set('mad_baits_meta_last_add_to_cart', null);

	wp_add_inline_script(
		'mad-baits-social',
		'window.madBaitsMetaAddToCart = ' . wp_json_encode($payload) . ';',
		'before'
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_meta_flush_add_to_cart_event', 51);
