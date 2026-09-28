<?php
/**
 * Required checkout acknowledgement for neighbour / safe-place deliveries.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Order meta key for delivery safe-place acknowledgement.
 */
const MAD_BAITS_DELIVERY_SAFE_PLACE_ACK_META = '_mad_delivery_safe_place_ack';

/**
 * POST / field id for classic checkout.
 *
 * @return string
 */
function mad_baits_get_delivery_safe_place_ack_field_name() {
	return 'mad_delivery_safe_place_ack';
}

/**
 * Blocks checkout additional field id.
 *
 * @return string
 */
function mad_baits_get_delivery_safe_place_ack_blocks_field_id() {
	return 'mad-baits/delivery-safe-place-ack';
}

/**
 * Full legal copy for delivery safe-place acknowledgement.
 *
 * @return string
 */
function mad_baits_get_delivery_safe_place_acknowledgement_text() {
	return __(
		'I understand that if my parcel is left with a neighbour or in a safe place (as requested or permitted by the carrier), I accept full responsibility for the delivery. Mad Baits cannot be held responsible for loss, theft, or damage in these circumstances, and I confirm that no refund or replacement will be issued.',
		'mad-baits'
	);
}

/**
 * Short checkbox label (blocks + classic) — full legal text is shown beside/near the field.
 *
 * @return string
 */
function mad_baits_get_delivery_safe_place_ack_checkbox_label() {
	return __(
		'I accept delivery responsibility for neighbour or safe-place deliveries (required).',
		'mad-baits'
	);
}

/**
 * Customer-facing validation message (blocks + classic).
 *
 * @return string
 */
function mad_baits_get_delivery_safe_place_ack_error_message() {
	return __(
		'Please confirm delivery responsibility for neighbour or safe-place deliveries before placing your order.',
		'mad-baits'
	);
}

/**
 * Whether the current request is WooCommerce Blocks checkout / Store API checkout.
 *
 * @return bool
 */
function mad_baits_is_blocks_checkout_context() {
	if (defined('REST_REQUEST') && REST_REQUEST) {
		$route = isset($GLOBALS['wp']->query_vars['rest_route']) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ('' === $route && ! empty($_SERVER['REQUEST_URI'])) {
			$route = (string) wp_parse_url((string) wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
		}
		if (false !== strpos($route, '/wc/store/') || false !== strpos($route, 'wc/store/v1/checkout')) {
			return true;
		}
	}

	if (function_exists('has_block') && has_block('woocommerce/checkout')) {
		return true;
	}

	return false;
}

/**
 * Register delivery acknowledgement checkbox for WooCommerce Blocks checkout.
 *
 * Not marked required at registration — a hard-required contact checkbox breaks
 * Apple Pay / Google Pay / Link / PayPal Express. We validate for standard
 * place-order checkouts in Store API instead.
 *
 * @return void
 */
function mad_baits_register_delivery_safe_place_blocks_checkout_field() {
	if (! function_exists('woocommerce_register_additional_checkout_field')) {
		return;
	}

	woocommerce_register_additional_checkout_field(
		array(
			'id'            => mad_baits_get_delivery_safe_place_ack_blocks_field_id(),
			'label'         => mad_baits_get_delivery_safe_place_ack_checkbox_label(),
			'optionalLabel' => mad_baits_get_delivery_safe_place_ack_checkbox_label(),
			'location'      => 'contact',
			'type'          => 'checkbox',
			'required'      => false,
			'attributes'    => array(
				'title' => mad_baits_get_delivery_safe_place_acknowledgement_text(),
			),
		)
	);
}
add_action('woocommerce_init', 'mad_baits_register_delivery_safe_place_blocks_checkout_field');

/**
 * Whether a Store API checkout request is an express / wallet payment.
 *
 * Kept intentionally narrow so standard card / PayPal place-order still requires
 * the delivery acknowledgement checkbox.
 *
 * @param mixed $request Request object or array.
 * @return bool
 */
function mad_baits_is_express_checkout_request($request) {
	$payment_data = array();

	if (is_object($request) && method_exists($request, 'get_param')) {
		$payment_data = (array) $request->get_param('payment_data');
	} elseif (is_array($request)) {
		$payment_data = isset($request['payment_data']) ? (array) $request['payment_data'] : array();
	}

	$wallet_type_keys = array(
		'paymentRequestType',
		'payment_request_type',
		'express_payment_type',
		'expressCheckoutType',
		'wc-stripe-express-checkout-type',
	);
	$wallet_types = array(
		'apple_pay',
		'apple-pay',
		'google_pay',
		'google-pay',
		'payment_request',
		'express',
		'link',
	);

	foreach ($payment_data as $item) {
		if (! is_array($item)) {
			continue;
		}
		$key   = isset($item['key']) ? (string) $item['key'] : '';
		$value = isset($item['value']) ? strtolower(trim((string) $item['value'])) : '';

		if (in_array($key, $wallet_type_keys, true) && in_array($value, $wallet_types, true)) {
			return true;
		}

		// Stripe Express Checkout Element flag.
		if ('wc-stripe-express-checkout-element' === $key && in_array($value, array('1', 'true', 'yes'), true)) {
			return true;
		}
		if ('is_express' === $key && in_array($value, array('1', 'true', 'yes'), true)) {
			return true;
		}
	}

	return false;
}

/**
 * Additional fields payload from a Store API request.
 *
 * @param mixed $request Request.
 * @return array
 */
function mad_baits_store_api_request_additional_fields($request) {
	if (is_object($request) && method_exists($request, 'get_param')) {
		return (array) $request->get_param('additional_fields');
	}
	if (is_array($request) && isset($request['additional_fields'])) {
		return (array) $request['additional_fields'];
	}
	return array();
}

/**
 * Whether the Store API payload includes our delivery-ack field key.
 *
 * Express / wallet checkouts usually omit it entirely. Standard Blocks checkout
 * includes it (true/false). Only enforce when the key is present.
 *
 * @param mixed $request Request.
 * @return bool
 */
function mad_baits_store_api_request_mentions_delivery_ack($request) {
	$field_id   = mad_baits_get_delivery_safe_place_ack_blocks_field_id();
	$additional = mad_baits_store_api_request_additional_fields($request);

	return array_key_exists($field_id, $additional)
		|| (isset($additional['contact']) && is_array($additional['contact']) && array_key_exists($field_id, $additional['contact']));
}

/**
 * Read blocks additional-field acknowledgement from a Store API request.
 *
 * @param mixed $request Request.
 * @return bool
 */
function mad_baits_store_api_request_has_delivery_ack($request) {
	$field_id   = mad_baits_get_delivery_safe_place_ack_blocks_field_id();
	$additional = mad_baits_store_api_request_additional_fields($request);

	if (array_key_exists($field_id, $additional)) {
		$value = $additional[ $field_id ];
		return ! empty($value) && ! in_array((string) $value, array('0', 'false', 'no'), true);
	}

	if (isset($additional['contact']) && is_array($additional['contact']) && array_key_exists($field_id, $additional['contact'])) {
		$value = $additional['contact'][ $field_id ];
		return ! empty($value) && ! in_array((string) $value, array('0', 'false', 'no'), true);
	}

	return false;
}

/**
 * Validate delivery ack on Blocks Store API for standard place-order only.
 *
 * Never block Apple Pay / Google Pay / Link / PayPal Express. Those payloads
 * typically omit our checkbox field; requiring it breaks wallet checkout.
 *
 * @param WC_Order $order   Order.
 * @param mixed    $request Request.
 * @return void
 */
function mad_baits_validate_delivery_ack_store_api($order, $request) {
	unset($order);

	if (mad_baits_is_express_checkout_request($request)) {
		return;
	}

	// Wallet / incomplete payloads: do not hard-fail when the field was never sent.
	if (! mad_baits_store_api_request_mentions_delivery_ack($request)) {
		return;
	}

	if (mad_baits_store_api_request_has_delivery_ack($request)) {
		return;
	}

	$message = mad_baits_get_delivery_safe_place_ack_error_message();

	if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'mad_baits_delivery_ack_required',
			$message,
			400
		);
	}

	throw new Exception($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
}
add_action('woocommerce_store_api_checkout_update_order_from_request', 'mad_baits_validate_delivery_ack_store_api', 10, 2);

/**
 * Keep our contact checkbox out of Stripe Express Checkout Element params.
 *
 * Stripe surfaces custom_checkout_fields to wallet flows; even optional checkboxes
 * can prevent Apple Pay / Google Pay from completing.
 *
 * @param array $params Express checkout params.
 * @return array
 */
function mad_baits_strip_delivery_ack_from_stripe_express_params($params) {
	if (! is_array($params)) {
		return $params;
	}

	$field_id = mad_baits_get_delivery_safe_place_ack_blocks_field_id();

	if (isset($params['custom_checkout_fields']) && is_array($params['custom_checkout_fields'])) {
		unset($params['custom_checkout_fields'][ $field_id ]);
	}

	return $params;
}
add_filter('wc_stripe_express_checkout_params', 'mad_baits_strip_delivery_ack_from_stripe_express_params', 20);
add_filter('wc_stripe_payment_request_params', 'mad_baits_strip_delivery_ack_from_stripe_express_params', 20);

/**
 * Record express checkout delivery-terms acceptance on the order.
 *
 * Express wallets cannot collect our checkbox; customers see the express notice
 * and continue. Persist that the order used express under those terms.
 *
 * @param WC_Order $order   Order.
 * @param mixed    $request Request.
 * @return void
 */
function mad_baits_record_express_delivery_ack($order, $request) {
	if (! $order instanceof WC_Order || ! mad_baits_is_express_checkout_request($request)) {
		return;
	}

	if (mad_baits_order_has_delivery_safe_place_acknowledgement($order)) {
		return;
	}

	$order->update_meta_data(MAD_BAITS_DELIVERY_SAFE_PLACE_ACK_META, 'yes');
	$order->update_meta_data('_mad_delivery_safe_place_ack_at', current_time('mysql'));
	$order->update_meta_data('_mad_delivery_safe_place_ack_via', 'express');
	$order->add_order_note(
		__(
			'Express checkout: customer proceeded with Apple Pay / Google Pay / Link / PayPal Express. Neighbour / safe-place delivery responsibility terms apply.',
			'mad-baits'
		)
	);
}
add_action('woocommerce_store_api_checkout_update_order_from_request', 'mad_baits_record_express_delivery_ack', 20, 2);

/**
 * Persist blocks checkout acknowledgement on the order.
 *
 * @param string                  $key Field key.
 * @param mixed                   $value Field value.
 * @param string                  $group Field group.
 * @param WC_Order|WC_Customer|mixed $wc_object Target object.
 * @return void
 */
function mad_baits_store_delivery_safe_place_blocks_field_value($key, $value, $group, $wc_object) {
	unset($group);

	if (mad_baits_get_delivery_safe_place_ack_blocks_field_id() !== (string) $key) {
		return;
	}

	if (! $wc_object instanceof WC_Order) {
		return;
	}

	if (empty($value) || in_array((string) $value, array('0', 'false', 'no'), true)) {
		return;
	}

	$wc_object->update_meta_data(MAD_BAITS_DELIVERY_SAFE_PLACE_ACK_META, 'yes');
	$wc_object->update_meta_data('_mad_delivery_safe_place_ack_at', current_time('mysql'));
}
add_action('woocommerce_set_additional_field_value', 'mad_baits_store_delivery_safe_place_blocks_field_value', 10, 4);

/**
 * Render required acknowledgement on classic checkout.
 *
 * @return void
 */
function mad_baits_render_delivery_safe_place_acknowledgement() {
	if (mad_baits_is_blocks_checkout_context()) {
		return;
	}

	$field_name = mad_baits_get_delivery_safe_place_ack_field_name();
	$field_id   = 'mad_delivery_safe_place_ack';
	?>
	<div class="mad-checkout-delivery-ack woocommerce-form-row woocommerce-form-row--wide form-row validate-required" id="<?php echo esc_attr($field_id); ?>_field">
		<p class="mad-checkout-delivery-ack__title"><?php esc_html_e('Delivery responsibility', 'mad-baits'); ?></p>
		<p class="mad-checkout-delivery-ack__lead">
			<?php esc_html_e('If your courier leaves your parcel with a neighbour or in a safe place, please confirm the following before placing your order.', 'mad-baits'); ?>
		</p>
		<div class="mad-checkout-delivery-ack__legal">
			<p><?php echo esc_html(mad_baits_get_delivery_safe_place_acknowledgement_text()); ?></p>
		</div>
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="<?php echo esc_attr($field_id); ?>">
			<input
				type="checkbox"
				class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
				name="<?php echo esc_attr($field_name); ?>"
				id="<?php echo esc_attr($field_id); ?>"
				value="1"
				aria-required="true"
			/>
			<span class="mad-checkout-delivery-ack__label woocommerce-terms-and-conditions-checkbox-text">
				<?php echo esc_html(mad_baits_get_delivery_safe_place_ack_checkbox_label()); ?>
			</span>
		</label>
	</div>
	<?php
}
add_action('woocommerce_review_order_before_submit', 'mad_baits_render_delivery_safe_place_acknowledgement', 10);

/**
 * Validate classic checkout acknowledgement.
 *
 * @return void
 */
function mad_baits_validate_delivery_safe_place_acknowledgement() {
	if (mad_baits_is_blocks_checkout_context()) {
		return;
	}

	$field_name = mad_baits_get_delivery_safe_place_ack_field_name();

	if (empty($_POST[ $field_name ])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wc_add_notice(mad_baits_get_delivery_safe_place_ack_error_message(), 'error');
	}
}
add_action('woocommerce_checkout_process', 'mad_baits_validate_delivery_safe_place_acknowledgement');

/**
 * Persist acknowledgement on the order (classic checkout).
 *
 * @param int $order_id Order ID.
 * @return void
 */
function mad_baits_save_delivery_safe_place_acknowledgement($order_id) {
	$field_name = mad_baits_get_delivery_safe_place_ack_field_name();

	if (empty($_POST[ $field_name ])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	update_post_meta($order_id, MAD_BAITS_DELIVERY_SAFE_PLACE_ACK_META, 'yes');
	update_post_meta($order_id, '_mad_delivery_safe_place_ack_at', current_time('mysql'));
}
add_action('woocommerce_checkout_update_order_meta', 'mad_baits_save_delivery_safe_place_acknowledgement');

/**
 * Enqueue checkout JS to gate PayPal until acknowledgement is checked.
 *
 * Checkout only — never load on cart. Cart express PayPal must stay clickable;
 * address/name are collected inside PayPal, not on our cart page.
 *
 * @return void
 */
function mad_baits_enqueue_delivery_safe_place_ack_script() {
	if (! function_exists('is_checkout') || ! is_checkout()) {
		return;
	}

	if (function_exists('is_order_received_page') && is_order_received_page()) {
		return;
	}

	$path = get_theme_file_path('assets/js/mad-checkout-delivery-ack.js');
	if (! file_exists($path)) {
		return;
	}

	wp_enqueue_script(
		'mad-baits-checkout-delivery-ack',
		get_theme_file_uri('assets/js/mad-checkout-delivery-ack.js'),
		array(),
		(string) filemtime($path),
		true
	);

	wp_localize_script(
		'mad-baits-checkout-delivery-ack',
		'madBaitsDeliveryAck',
		array(
			'fieldId'        => mad_baits_get_delivery_safe_place_ack_blocks_field_id(),
			'classicFieldId' => 'mad_delivery_safe_place_ack',
			'errorMessage'   => mad_baits_get_delivery_safe_place_ack_error_message(),
			'expressNotice'  => __(
				'By using Apple Pay, Google Pay, Link or PayPal Express you accept delivery responsibility for neighbour or safe-place deliveries.',
				'mad-baits'
			),
			'legalHtml'      => wp_kses_post(
				'<p class="mad-checkout-delivery-ack__lead">' . esc_html(
					__(
						'If your courier leaves your parcel with a neighbour or in a safe place, please confirm the following before paying.',
						'mad-baits'
					)
				) . '</p><p>' . esc_html(mad_baits_get_delivery_safe_place_acknowledgement_text()) . '</p>'
			),
		)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_delivery_safe_place_ack_script', 36);

/**
 * Whether the order has the delivery safe-place acknowledgement recorded.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function mad_baits_order_has_delivery_safe_place_acknowledgement($order) {
	if (! $order instanceof WC_Order) {
		return false;
	}

	if ('yes' === (string) $order->get_meta(MAD_BAITS_DELIVERY_SAFE_PLACE_ACK_META)) {
		return true;
	}

	$blocks_value = $order->get_meta(mad_baits_get_delivery_safe_place_ack_blocks_field_id());

	return ! empty($blocks_value) && ! in_array((string) $blocks_value, array('0', 'false', 'no'), true);
}

/**
 * Show acknowledgement status in admin order screen.
 *
 * @param WC_Order $order Order.
 * @return void
 */
function mad_baits_admin_order_delivery_safe_place_acknowledgement($order) {
	if (! mad_baits_order_has_delivery_safe_place_acknowledgement($order)) {
		return;
	}

	$confirmed_at = (string) $order->get_meta('_mad_delivery_safe_place_ack_at');
	?>
	<p class="form-field form-field-wide mad-admin-delivery-ack">
		<strong><?php esc_html_e('Delivery responsibility confirmed', 'mad-baits'); ?></strong><br />
		<?php echo esc_html(mad_baits_get_delivery_safe_place_acknowledgement_text()); ?>
		<?php if ('' !== $confirmed_at) : ?>
			<br /><em><?php echo esc_html(sprintf(__('Confirmed: %s', 'mad-baits'), $confirmed_at)); ?></em>
		<?php endif; ?>
	</p>
	<?php
}
add_action('woocommerce_admin_order_data_after_billing_address', 'mad_baits_admin_order_delivery_safe_place_acknowledgement');
