<?php
/**
 * Checkout PayPal cancellation recovery helpers.
 *
 * Some off-site PayPal flows can leave shoppers with an empty cart when they
 * cancel and return. We capture a lightweight cart snapshot at order creation
 * and restore it only on explicit cancel-style return requests.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Disable PayPal cart simulation — it temporarily removes/restores cart lines and
 * breaks bundle-builder meta, stock checks, and other custom cart integrations.
 *
 * @link https://github.com/woocommerce/woocommerce-paypal-payments/wiki/Actions-and-Filters#modify-cart-simulation-behavior
 * @return bool
 */
function mad_baits_ppcp_simulate_cart_enabled() {
	return (bool) apply_filters('mad_baits_ppcp_simulate_cart_enabled', false);
}
add_filter('woocommerce_paypal_payments_simulate_cart_enabled', 'mad_baits_ppcp_simulate_cart_enabled');

/**
 * Payment method IDs used by WooCommerce PayPal Payments.
 *
 * @return string[]
 */
function mad_baits_ppcp_payment_method_ids() {
	return array(
		'ppcp-gateway',
		'paypal',
		'ppcp-credit-card-gateway',
		'ppcp-googlepay',
		'ppcp-applepay',
	);
}

/**
 * Whether the order was created for a PayPal Payments checkout attempt.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function mad_baits_is_ppcp_order($order) {
	if (! $order instanceof WC_Order) {
		return false;
	}

	$method = (string) $order->get_payment_method();
	if (in_array($method, mad_baits_ppcp_payment_method_ids(), true)) {
		return true;
	}

	return 0 === strpos($method, 'ppcp-');
}

/**
 * Build a cart snapshot payload safe for order meta storage.
 *
 * @param array<string, mixed> $cart_item Raw WC cart item.
 * @return array<string, mixed>
 */
function mad_baits_build_checkout_snapshot_item(array $cart_item) {
	$product_id   = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
	$variation_id = isset($cart_item['variation_id']) ? absint($cart_item['variation_id']) : 0;
	$quantity     = isset($cart_item['quantity']) ? max(1, (int) $cart_item['quantity']) : 1;
	$variation    = isset($cart_item['variation']) && is_array($cart_item['variation']) ? $cart_item['variation'] : array();

	$cart_item_data = $cart_item;
	unset(
		$cart_item_data['product_id'],
		$cart_item_data['variation_id'],
		$cart_item_data['variation'],
		$cart_item_data['quantity'],
		$cart_item_data['data'],
		$cart_item_data['data_hash'],
		$cart_item_data['key'],
		$cart_item_data['line_tax_data'],
		$cart_item_data['line_subtotal'],
		$cart_item_data['line_subtotal_tax'],
		$cart_item_data['line_total'],
		$cart_item_data['line_tax']
	);

	return array(
		'product_id'     => $product_id,
		'variation_id'   => $variation_id,
		'variation'      => $variation,
		'quantity'       => $quantity,
		'cart_item_data' => $cart_item_data,
	);
}

/**
 * Capture current cart at order creation.
 *
 * @param WC_Order               $order Order being created.
 * @param array<string, mixed>   $data  Posted checkout data.
 * @return void
 */
function mad_baits_capture_checkout_cart_snapshot($order, $data) {
	unset($data);

	if (! $order instanceof WC_Order || ! function_exists('WC') || ! WC()->cart) {
		return;
	}

	$cart = WC()->cart->get_cart();
	if (! is_array($cart) || empty($cart)) {
		return;
	}

	$snapshot = array();
	foreach ($cart as $cart_item) {
		if (! is_array($cart_item)) {
			continue;
		}

		$item = mad_baits_build_checkout_snapshot_item($cart_item);
		if (empty($item['product_id']) || empty($item['quantity'])) {
			continue;
		}
		$snapshot[] = $item;
	}

	if (empty($snapshot)) {
		return;
	}

	$order->update_meta_data('_mad_baits_cart_snapshot', $snapshot);
}
add_action('woocommerce_checkout_create_order', 'mad_baits_capture_checkout_cart_snapshot', 20, 2);

/**
 * Whether current request looks like a PayPal cancellation return.
 *
 * @return bool
 */
function mad_baits_is_paypal_cancel_return_request() {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$params = is_array($_GET) ? wp_unslash($_GET) : array();
	if (! is_array($params)) {
		return false;
	}

	$cancel_flags = array(
		'cancel_order',
		'cancelled',
		'paypal_cancel',
		'ppcp_cancel',
		'payment_cancelled',
	);

	foreach ($cancel_flags as $flag) {
		if (array_key_exists($flag, $params)) {
			return true;
		}
	}

	$cancel_values = array('true', '1', 'yes');
	if (isset($params['cancel_order']) && in_array(strtolower((string) $params['cancel_order']), $cancel_values, true)) {
		return true;
	}

	return false;
}

/**
 * Whether we should cancel a stale pending PayPal order and release held stock.
 *
 * Only runs on explicit cancel/abandon return URLs so active checkout is not affected.
 *
 * @return bool
 */
function mad_baits_should_release_ppcp_pending_order() {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return false;
	}

	if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
		return false;
	}

	return mad_baits_is_paypal_cancel_return_request() || mad_baits_is_paypal_failure_return_request();
}

/**
 * Cancel abandoned PayPal pending orders so stock is not left reserved.
 *
 * @return void
 */
function mad_baits_release_ppcp_pending_order_stock() {
	if (! mad_baits_should_release_ppcp_pending_order()) {
		return;
	}

	$order_id = mad_baits_get_checkout_return_order_id();
	if ($order_id < 1 && function_exists('WC') && WC()->session) {
		$order_id = absint(WC()->session->get('order_awaiting_payment'));
	}

	if ($order_id < 1) {
		return;
	}

	$session_key = 'mad_baits_ppcp_pending_released_' . $order_id;
	if (function_exists('WC') && WC()->session && '1' === (string) WC()->session->get($session_key)) {
		return;
	}

	$order = wc_get_order($order_id);
	if (! $order instanceof WC_Order || ! mad_baits_is_ppcp_order($order) || ! $order->needs_payment()) {
		return;
	}

	if (! in_array($order->get_status(), array('pending', 'failed', 'checkout-draft'), true)) {
		return;
	}

	$order->update_status(
		'cancelled',
		__('PayPal checkout was not completed. Order cancelled to release stock.', 'mad-baits')
	);

	if (function_exists('WC') && WC()->session) {
		WC()->session->set($session_key, '1');
		if ((int) WC()->session->get('order_awaiting_payment') === $order_id) {
			WC()->session->set('order_awaiting_payment', null);
		}
	}
}
add_action('template_redirect', 'mad_baits_release_ppcp_pending_order_stock', 1);
add_action('woocommerce_check_cart_items', 'mad_baits_release_ppcp_pending_order_stock', 1);

/**
 * Resolve order ID from return URL/query.
 *
 * @return int
 */
function mad_baits_get_checkout_return_order_id() {
	$order_id = 0;

	if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
		global $wp;
		if ($wp instanceof WP && ! empty($wp->query_vars['order-pay'])) {
			$order_id = absint($wp->query_vars['order-pay']);
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$params = is_array($_GET) ? wp_unslash($_GET) : array();
	if (! is_array($params)) {
		return $order_id;
	}

	if ($order_id < 1 && ! empty($params['order_id'])) {
		$order_id = absint($params['order_id']);
	}
	if ($order_id < 1 && ! empty($params['order'])) {
		$order_id = absint($params['order']);
	}
	if ($order_id < 1 && ! empty($params['key']) && function_exists('wc_get_order_id_by_order_key')) {
		$order_id = absint(wc_get_order_id_by_order_key((string) $params['key']));
	}

	return $order_id;
}

/**
 * Customer-friendly copy for common PayPal / card decline errors.
 *
 * @param string $message Raw gateway or plugin message.
 * @return string
 */
function mad_baits_ppcp_friendly_error_message($message) {
	$message = wp_strip_all_tags((string) $message);
	if ('' === $message) {
		return __(
			'We could not complete your payment. Please try again with a different card or payment method.',
			'mad-baits'
		);
	}

	$needles = array(
		'instrument_declined',
		'instrument presented',
		'declined by the processor',
		'declined by the bank',
		'card was declined',
		'payment was declined',
	);

	$haystack = strtolower($message);
	foreach ($needles as $needle) {
		if (false !== strpos($haystack, $needle)) {
			return __(
				'Your bank or card provider declined this payment. Please try a different card, use Apple Pay, or contact your bank and try again.',
				'mad-baits'
			);
		}
	}

	if (false !== strpos($haystack, 'unprocessable_entity') || false !== strpos($haystack, 'failed to process the payment')) {
		return __(
			'We could not complete your PayPal payment. Please try again with a different payment method.',
			'mad-baits'
		);
	}

	return $message;
}

/**
 * Notice shown after the basket is restored following a declined/failed PayPal attempt.
 *
 * @param string $context declined|failed|cancel.
 * @return string
 */
function mad_baits_ppcp_cart_restored_notice($context = 'failed') {
	switch ($context) {
		case 'declined':
			return __(
				'Your basket has been restored. Your bank declined the payment — please try a different card or payment method.',
				'mad-baits'
			);
		case 'cancel':
			return __('Your basket was restored after the cancelled PayPal payment. Please try again.', 'mad-baits');
		default:
			return __(
				'Your basket has been restored after the failed payment. Please try again with a different payment method.',
				'mad-baits'
			);
	}
}

/**
 * Restore a customer's basket from an order snapshot.
 *
 * @param WC_Order $order   Order with snapshot meta.
 * @param string   $context declined|failed|cancel.
 * @return bool
 */
function mad_baits_ppcp_restore_cart_from_order($order, $context = 'failed') {
	if (! $order instanceof WC_Order || ! mad_baits_is_ppcp_order($order)) {
		return false;
	}

	if (! $order->has_status(array('pending', 'failed', 'cancelled'))) {
		return false;
	}

	if (! function_exists('WC') || ! WC()->cart || ! WC()->session) {
		return false;
	}

	if (! WC()->cart->is_empty()) {
		return false;
	}

	$order_id    = $order->get_id();
	$session_key = 'mad_baits_paypal_cancel_restored_' . $order_id;
	if ('1' === (string) WC()->session->get($session_key)) {
		return false;
	}

	$snapshot = $order->get_meta('_mad_baits_cart_snapshot', true);
	if (! is_array($snapshot) || empty($snapshot)) {
		return false;
	}

	$restored_any = false;
	foreach ($snapshot as $item) {
		if (! is_array($item)) {
			continue;
		}

		$product_id     = isset($item['product_id']) ? absint($item['product_id']) : 0;
		$variation_id   = isset($item['variation_id']) ? absint($item['variation_id']) : 0;
		$quantity       = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;
		$variation      = isset($item['variation']) && is_array($item['variation']) ? $item['variation'] : array();
		$cart_item_data = isset($item['cart_item_data']) && is_array($item['cart_item_data']) ? $item['cart_item_data'] : array();

		if ($product_id < 1) {
			continue;
		}

		$added_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
		if (! empty($added_key)) {
			$restored_any = true;
		}
	}

	if (! $restored_any) {
		return false;
	}

	WC()->session->set($session_key, '1');
	WC()->cart->calculate_totals();
	wc_add_notice(mad_baits_ppcp_cart_restored_notice($context), 'notice');

	return true;
}

/**
 * Replace technical PayPal error notices with customer-friendly messages on checkout.
 *
 * @return void
 */
function mad_baits_ppcp_rewrite_checkout_error_notices() {
	if (! function_exists('is_checkout') || ! is_checkout() || ! function_exists('wc_get_notices')) {
		return;
	}

	$errors = wc_get_notices('error');
	if (empty($errors) || ! is_array($errors)) {
		return;
	}

	$changed = false;
	$rewritten = array();

	foreach ($errors as $error) {
		$raw = '';
		if (is_array($error) && isset($error['notice'])) {
			$raw = (string) $error['notice'];
		} else {
			$raw = (string) $error;
		}

		$friendly = mad_baits_ppcp_friendly_error_message($raw);
		if ('' === trim(wp_strip_all_tags($friendly))) {
			continue;
		}
		if ($friendly !== $raw) {
			$changed = true;
		}
		$rewritten[] = array(
			'notice' => $friendly,
			'data'   => is_array($error) && isset($error['data']) ? $error['data'] : array(),
		);
	}

	if (! $changed) {
		return;
	}

	wc_clear_notices('error');
	foreach ($rewritten as $error) {
		wc_add_notice($error['notice'], 'error', $error['data']);
	}
}

/**
 * Keep shoppers on checkout with a clear message when PayPal marks an order failed.
 *
 * @param array<string, mixed> $result   Payment result from PPCP.
 * @param int                    $order_id Order ID.
 * @param mixed                  $gateway  Gateway instance.
 * @return array<string, mixed>
 */
function mad_baits_ppcp_process_payment_result($result, $order_id, $gateway) {
	unset($gateway);

	if (! is_array($result)) {
		return $result;
	}

	$order = wc_get_order(absint($order_id));
	if (! $order instanceof WC_Order) {
		return $result;
	}

	if ($order->has_status('failed')) {
		mad_baits_ppcp_restore_cart_from_order($order, 'declined');
	}

	$raw_message = '';
	if (! empty($result['errorMessage'])) {
		$raw_message = (string) $result['errorMessage'];
	} elseif (! empty($result['message'])) {
		$raw_message = (string) $result['message'];
	}

	if ('success' === ($result['result'] ?? '') && $order->has_status('failed')) {
		return array(
			'result'       => 'failure',
			'redirect'     => wc_get_checkout_url(),
			'errorMessage' => mad_baits_ppcp_friendly_error_message($raw_message),
		);
	}

	if ('failure' === ($result['result'] ?? '') && '' !== $raw_message) {
		$result['errorMessage'] = mad_baits_ppcp_friendly_error_message($raw_message);
		if (empty($result['redirect'])) {
			$result['redirect'] = wc_get_checkout_url();
		}
	}

	return $result;
}
add_filter('woocommerce_paypal_payments_process_payment_result', 'mad_baits_ppcp_process_payment_result', 20, 3);

/**
 * Restore basket when a PayPal order fails during checkout (e.g. INSTRUMENT_DECLINED).
 *
 * @param int $order_id Order ID.
 * @return void
 */
function mad_baits_ppcp_on_order_failed($order_id) {
	$order = wc_get_order(absint($order_id));
	if (! $order instanceof WC_Order) {
		return;
	}

	mad_baits_ppcp_restore_cart_from_order($order, 'declined');
}
add_action('woocommerce_order_status_failed', 'mad_baits_ppcp_on_order_failed', 20);

/**
 * Checkout bootstrap: rewrite PayPal errors and restore basket for failed awaiting orders.
 *
 * @return void
 */
function mad_baits_ppcp_checkout_failure_bootstrap() {
	if (! function_exists('is_checkout') || ! is_checkout() || ! function_exists('WC') || ! WC()->session) {
		return;
	}

	mad_baits_ppcp_rewrite_checkout_error_notices();

	$order_id = absint(WC()->session->get('order_awaiting_payment'));
	if ($order_id < 1) {
		return;
	}

	$order = wc_get_order($order_id);
	if (! $order instanceof WC_Order || ! $order->has_status('failed')) {
		return;
	}

	mad_baits_ppcp_restore_cart_from_order($order, 'declined');
}
add_action('template_redirect', 'mad_baits_ppcp_checkout_failure_bootstrap', 6);


/**
 * Restore cart from snapshot after cancelled/failed PayPal return.
 *
 * @param bool $force When true, skip cancel/failure URL checks.
 * @return void
 */
function mad_baits_restore_cart_after_paypal_abandon($force = false) {
	if (! $force && ! mad_baits_is_paypal_cancel_return_request() && ! mad_baits_is_paypal_failure_return_request()) {
		return;
	}

	$order_id = mad_baits_get_checkout_return_order_id();
	if ($order_id < 1 && function_exists('WC') && WC()->session) {
		$order_id = absint(WC()->session->get('order_awaiting_payment'));
	}
	if ($order_id < 1) {
		return;
	}

	$order = wc_get_order($order_id);
	if (! $order instanceof WC_Order) {
		return;
	}

	$context = mad_baits_is_paypal_cancel_return_request() ? 'cancel' : 'declined';
	mad_baits_ppcp_restore_cart_from_order($order, $context);
}

/**
 * Restore cart when the shopper returns from a cancelled PayPal flow.
 *
 * @return void
 */
function mad_baits_restore_cart_after_paypal_cancel() {
	mad_baits_restore_cart_after_paypal_abandon(false);
}
add_action('template_redirect', 'mad_baits_restore_cart_after_paypal_cancel', 5);

/**
 * Remove PayPal SDK debug mode on production checkout (debug breaks some flows).
 *
 * @param string $url SDK URL.
 * @return string
 */
function mad_baits_ppcp_strip_debug_from_sdk_url($url) {
	$url = remove_query_arg('debug', (string) $url);
	return remove_query_arg('debug', $url);
}
add_filter('woocommerce_paypal_payments_sdk_url', 'mad_baits_ppcp_strip_debug_from_sdk_url', 20);
add_filter('woocommerce_paypal_payments_sdk_script_url', 'mad_baits_ppcp_strip_debug_from_sdk_url', 20);

/**
 * Strip debug flag from PPCP script component config when exposed to the browser.
 *
 * @param array<string, mixed> $data PPCP script data.
 * @return array<string, mixed>
 */
function mad_baits_ppcp_strip_debug_from_script_data($data) {
	if (! is_array($data)) {
		return $data;
	}

	if (isset($data['url']) && is_string($data['url'])) {
		$data['url'] = mad_baits_ppcp_strip_debug_from_sdk_url($data['url']);
	}

	if (isset($data['url_params']) && is_array($data['url_params'])) {
		unset($data['url_params']['debug']);
	}

	return $data;
}
add_filter('woocommerce_paypal_payments_js_sdk_script_data', 'mad_baits_ppcp_strip_debug_from_script_data', 20);

/**
 * Whether the current request indicates a failed/abandoned PayPal return.
 *
 * @return bool
 */
function mad_baits_is_paypal_failure_return_request() {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$params = is_array($_GET) ? wp_unslash($_GET) : array();
	if (! is_array($params)) {
		return false;
	}

	$failure_keys = array(
		'payment_status',
		'PAYMENTINFO_0_PAYMENTSTATUS',
	);

	foreach ($failure_keys as $key) {
		if (! isset($params[ $key ])) {
			continue;
		}
		$value = strtolower((string) $params[ $key ]);
		if (in_array($value, array('failed', 'denied', 'expired', 'voided'), true)) {
			return true;
		}
	}

	if (isset($params['errorcode']) || isset($params['error_code'])) {
		return true;
	}

	if (isset($params['error']) && (isset($params['order_id']) || isset($params['order']) || isset($params['key']))) {
		return true;
	}

	return false;
}

/**
 * Release stock and restore cart on failed PayPal returns as well as explicit cancels.
 *
 * @return void
 */
function mad_baits_handle_paypal_failure_return() {
	if (! mad_baits_is_paypal_failure_return_request()) {
		return;
	}

	mad_baits_release_ppcp_pending_order_stock();
	mad_baits_restore_cart_after_paypal_abandon(true);
}
add_action('template_redirect', 'mad_baits_handle_paypal_failure_return', 4);

