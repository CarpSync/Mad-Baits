<?php
/**
 * Native in-app checkout — Stripe PaymentIntent via WooCommerce Stripe gateway keys.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Native_Checkout
 */
class MBMC_Native_Checkout {

	/**
	 * Whether native checkout can run (WooCommerce + Stripe keys).
	 *
	 * @return bool
	 */
	public static function is_available() {
		return MBMC_WooCommerce::is_available() && null !== self::get_stripe_keys();
	}

	/**
	 * Config payload for GET /checkout/native/config.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_config() {
		$keys     = self::get_stripe_keys();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'GBP';

		return array(
			'nativeCheckoutEnabled' => null !== $keys,
			'paymentProvider'       => null !== $keys ? 'stripe' : null,
			'stripePublishableKey'  => $keys ? $keys['publishable'] : null,
			'currency'              => $currency,
			'defaultCountry'        => 'GB',
			'merchantDisplayName'   => 'Mad Baits',
			'supportedPaymentMethods' => array( 'card', 'apple_pay', 'klarna', 'paypal' ),
		);
	}

	/**
	 * Quote available WooCommerce shipping rates for cart + address.
	 *
	 * @param array $line_items Cart line items.
	 * @param array $billing    Billing address payload.
	 * @param array $shipping   Shipping address payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function quote_shipping( $line_items, $billing, $shipping = array() ) {
		if ( ! MBMC_WooCommerce::is_available() ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$rates = self::calculate_shipping_rates( $line_items, $billing, $shipping );
		if ( is_wp_error( $rates ) ) {
			return $rates;
		}

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'GBP';

		return array(
			'rates'    => $rates,
			'currency' => $currency,
		);
	}

	/**
	 * Prepare order + Stripe PaymentIntent for native checkout.
	 *
	 * @param int    $customer_id        WooCommerce customer ID (0 = guest).
	 * @param array  $line_items         Cart line items.
	 * @param array  $billing            Billing address payload.
	 * @param array  $shipping           Shipping address payload (optional; copies billing when empty).
	 * @param int    $loyalty_points     Points to apply at checkout (0 = none).
	 * @param int    $user_id            Authenticated user id for points redemption.
	 * @param string $shipping_method_id Selected WooCommerce shipping rate id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function prepare( $customer_id, $line_items, $billing, $shipping = array(), $loyalty_points = 0, $user_id = 0, $shipping_method_id = '' ) {
		$keys = self::get_stripe_keys();
		if ( null === $keys ) {
			return new WP_Error(
				'mbmc_native_checkout_unavailable',
				__( 'Native checkout is not configured. Enable WooCommerce Stripe on the store.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$order = MBMC_WooCommerce::create_order_from_line_items( $customer_id, $line_items );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$billing_error = self::apply_address_to_order( $order, 'billing', $billing );
		if ( is_wp_error( $billing_error ) ) {
			$order->delete( true );
			return $billing_error;
		}

		$ship_payload = ! empty( $shipping ) ? $shipping : $billing;
		$shipping_error = self::apply_address_to_order( $order, 'shipping', $ship_payload );
		if ( is_wp_error( $shipping_error ) ) {
			$order->delete( true );
			return $shipping_error;
		}

		$shipping_method_id = sanitize_text_field( (string) $shipping_method_id );
		if ( '' !== $shipping_method_id ) {
			$apply_rate = self::apply_shipping_rate_to_order( $order, $line_items, $billing, $ship_payload, $shipping_method_id );
			if ( is_wp_error( $apply_rate ) ) {
				$order->delete( true );
				return $apply_rate;
			}
		}

		$order->set_payment_method( 'stripe' );
		$order->set_payment_method_title( __( 'Card (App)', 'mad-baits-mobile-connector' ) );

		$loyalty_points = absint( $loyalty_points );
		$user_id        = absint( $user_id );
		if ( $loyalty_points > 0 && $user_id > 0 ) {
			$points_error = MBMC_Loyalty_Order::apply_checkout_points_discount( $order, $user_id, $loyalty_points );
			if ( is_wp_error( $points_error ) ) {
				$order->delete( true );
				return $points_error;
			}
		}

		$order->calculate_totals();
		$order->save();

		$amount_cents = self::order_total_cents( $order );
		if ( $amount_cents < 50 ) {
			$order->delete( true );
			return new WP_Error(
				'mbmc_checkout_amount_invalid',
				__( 'Order total is too low to pay by card.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$intent = self::stripe_create_payment_intent(
			$keys['secret'],
			array(
				'amount'                              => $amount_cents,
				'currency'                            => strtolower( $order->get_currency() ),
				'description'                         => sprintf(
					/* translators: %s: order number */
					__( 'Mad Baits order %s', 'mad-baits-mobile-connector' ),
					$order->get_order_number()
				),
				'receipt_email'                       => sanitize_email( (string) $order->get_billing_email() ),
				'metadata[order_id]'                  => (string) $order->get_id(),
				'metadata[order_key]'                 => (string) $order->get_order_key(),
				'metadata[source]'                    => 'madbaits_mobile',
				'automatic_payment_methods[enabled]'  => 'true',
				'automatic_payment_methods[allow_redirects]' => 'always',
			)
		);

		if ( is_wp_error( $intent ) ) {
			$order->delete( true );
			return $intent;
		}

		$client_secret = isset( $intent['client_secret'] ) ? (string) $intent['client_secret'] : '';
		$intent_id     = isset( $intent['id'] ) ? (string) $intent['id'] : '';

		if ( '' === $client_secret || '' === $intent_id ) {
			$order->delete( true );
			return new WP_Error(
				'mbmc_stripe_intent_failed',
				__( 'Could not start card payment.', 'mad-baits-mobile-connector' ),
				array( 'status' => 502 )
			);
		}

		$order->update_meta_data( '_mbmc_stripe_intent_id', $intent_id );
		$order->save();

		return array(
			'orderId'               => (string) $order->get_id(),
			'orderNumber'           => (string) $order->get_order_number(),
			'orderKey'              => (string) $order->get_order_key(),
			'isGuest'               => $customer_id <= 0,
			'paymentIntentId'       => $intent_id,
			'paymentIntentClientSecret' => $client_secret,
			'stripePublishableKey'  => $keys['publishable'],
			'currency'              => $order->get_currency(),
			'totals'                => self::map_order_totals( $order ),
		);
	}

	/**
	 * Confirm Stripe payment and mark WooCommerce order paid.
	 *
	 * @param int    $order_id          Order ID.
	 * @param string $order_key         Order key.
	 * @param string $payment_intent_id Stripe PaymentIntent ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function confirm( $order_id, $order_key, $payment_intent_id ) {
		$order_id          = absint( $order_id );
		$order_key         = sanitize_text_field( (string) $order_key );
		$payment_intent_id = sanitize_text_field( (string) $payment_intent_id );

		if ( $order_id <= 0 || '' === $order_key || '' === $payment_intent_id ) {
			return new WP_Error(
				'mbmc_invalid_order',
				__( 'Invalid checkout details.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$keys = self::get_stripe_keys();
		if ( null === $keys ) {
			return new WP_Error(
				'mbmc_native_checkout_unavailable',
				__( 'Native checkout is not configured.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
			return new WP_Error(
				'mbmc_order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		$stored_intent = (string) $order->get_meta( '_mbmc_stripe_intent_id' );
		if ( '' !== $stored_intent && ! hash_equals( $stored_intent, $payment_intent_id ) ) {
			return new WP_Error(
				'mbmc_payment_mismatch',
				__( 'Payment does not match this order.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$intent = self::stripe_request( $keys['secret'], 'GET', 'payment_intents/' . rawurlencode( $payment_intent_id ) );
		if ( is_wp_error( $intent ) ) {
			return $intent;
		}

		$status = isset( $intent['status'] ) ? (string) $intent['status'] : '';
		if ( 'succeeded' !== $status ) {
			return new WP_Error(
				'mbmc_payment_not_complete',
				__( 'Payment has not completed yet. Please try again.', 'mad-baits-mobile-connector' ),
				array( 'status' => 402 )
			);
		}

		$meta_order_id = isset( $intent['metadata']['order_id'] ) ? absint( $intent['metadata']['order_id'] ) : 0;
		if ( $meta_order_id > 0 && $meta_order_id !== $order_id ) {
			return new WP_Error(
				'mbmc_payment_mismatch',
				__( 'Payment does not match this order.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $order->is_paid() ) {
			$order->payment_complete( $payment_intent_id );
			$order->add_order_note(
				sprintf(
					/* translators: %s: Stripe PaymentIntent id */
					__( 'Mobile app checkout paid via Stripe (%s).', 'mad-baits-mobile-connector' ),
					$payment_intent_id
				)
			);
			$order->save();
		}

		if ( class_exists( 'MBMC_Loyalty_Order' ) ) {
			MBMC_Loyalty_Order::finalize_checkout_points_redemption( $order );
		}

		return array(
			'orderId'     => (string) $order->get_id(),
			'orderNumber' => (string) $order->get_order_number(),
			'orderKey'    => (string) $order->get_order_key(),
			'status'      => $order->get_status(),
			'isGuest'     => (int) $order->get_customer_id() <= 0,
		);
	}

	/**
	 * Read WooCommerce Stripe gateway keys (test/live).
	 *
	 * @return array{secret: string, publishable: string}|null
	 */
	public static function get_stripe_keys() {
		$settings = get_option( 'woocommerce_stripe_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['enabled'] ) || 'yes' !== $settings['enabled'] ) {
			return null;
		}

		$testmode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];

		if ( $testmode ) {
			$secret      = isset( $settings['test_secret_key'] ) ? trim( (string) $settings['test_secret_key'] ) : '';
			$publishable = isset( $settings['test_publishable_key'] ) ? trim( (string) $settings['test_publishable_key'] ) : '';
		} else {
			$secret      = isset( $settings['secret_key'] ) ? trim( (string) $settings['secret_key'] ) : '';
			$publishable = isset( $settings['publishable_key'] ) ? trim( (string) $settings['publishable_key'] ) : '';
		}

		if ( '' === $secret || '' === $publishable ) {
			return null;
		}

		return array(
			'secret'      => $secret,
			'publishable' => $publishable,
		);
	}

	/**
	 * Apply billing/shipping fields to an order.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $type    billing|shipping.
	 * @param array    $payload Address fields from app.
	 * @return true|WP_Error
	 */
	private static function apply_address_to_order( WC_Order $order, $type, $payload ) {
		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'mbmc_invalid_address',
				__( 'Address details are required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$email = isset( $payload['email'] ) ? sanitize_email( (string) $payload['email'] ) : '';
		if ( 'billing' === $type && ( '' === $email || ! is_email( $email ) ) ) {
			return new WP_Error(
				'mbmc_invalid_email',
				__( 'A valid billing email is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$first = isset( $payload['firstName'] ) ? sanitize_text_field( (string) $payload['firstName'] ) : '';
		$last  = isset( $payload['lastName'] ) ? sanitize_text_field( (string) $payload['lastName'] ) : '';
		$line1 = isset( $payload['address1'] ) ? sanitize_text_field( (string) $payload['address1'] ) : '';
		$line2 = isset( $payload['address2'] ) ? sanitize_text_field( (string) $payload['address2'] ) : '';
		$city  = isset( $payload['city'] ) ? sanitize_text_field( (string) $payload['city'] ) : '';
		$state = isset( $payload['state'] ) ? sanitize_text_field( (string) $payload['state'] ) : '';
		$post  = isset( $payload['postcode'] ) ? sanitize_text_field( (string) $payload['postcode'] ) : '';
		$country = isset( $payload['country'] ) ? strtoupper( sanitize_text_field( (string) $payload['country'] ) ) : 'GB';
		$phone = isset( $payload['phone'] ) ? sanitize_text_field( (string) $payload['phone'] ) : '';

		if ( '' === $first || '' === $last || '' === $line1 || '' === $city || '' === $post ) {
			return new WP_Error(
				'mbmc_invalid_address',
				__( 'Please complete all required address fields.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( 'billing' === $type ) {
			$order->set_billing_first_name( $first );
			$order->set_billing_last_name( $last );
			$order->set_billing_address_1( $line1 );
			$order->set_billing_address_2( $line2 );
			$order->set_billing_city( $city );
			$order->set_billing_state( $state );
			$order->set_billing_postcode( $post );
			$order->set_billing_country( $country );
			$order->set_billing_email( $email );
			$order->set_billing_phone( $phone );
			return true;
		}

		$order->set_shipping_first_name( $first );
		$order->set_shipping_last_name( $last );
		$order->set_shipping_address_1( $line1 );
		$order->set_shipping_address_2( $line2 );
		$order->set_shipping_city( $city );
		$order->set_shipping_state( $state );
		$order->set_shipping_postcode( $post );
		$order->set_shipping_country( $country );
		$order->set_shipping_phone( $phone );

		return true;
	}

	/**
	 * Map order totals for mobile UI.
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, string>
	 */
	private static function map_order_totals( WC_Order $order ) {
		$loyalty_discount = (float) $order->get_meta( '_mbmc_loyalty_discount_gbp' );
		$loyalty_points   = (int) $order->get_meta( '_mbmc_loyalty_points_applied' );

		return array(
			'subtotal'        => wc_format_decimal( $order->get_subtotal(), 2 ),
			'shipping'        => wc_format_decimal( $order->get_shipping_total(), 2 ),
			'tax'             => wc_format_decimal( $order->get_total_tax(), 2 ),
			'loyaltyDiscount' => $loyalty_discount > 0 ? wc_format_decimal( $loyalty_discount, 2 ) : '0.00',
			'loyaltyPoints'   => $loyalty_points > 0 ? (string) $loyalty_points : '0',
			'total'           => wc_format_decimal( $order->get_total(), 2 ),
		);
	}

	/**
	 * Convert order total to Stripe smallest currency unit.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	private static function order_total_cents( WC_Order $order ) {
		$total = (float) $order->get_total();
		return (int) round( $total * 100 );
	}

	/**
	 * Create a Stripe PaymentIntent.
	 *
	 * @param string $secret_key Secret key.
	 * @param array  $body       Intent params.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function stripe_create_payment_intent( $secret_key, $body ) {
		return self::stripe_request( $secret_key, 'POST', 'payment_intents', $body );
	}

	/**
	 * Low-level Stripe API request (no SDK required).
	 *
	 * @param string $secret_key Secret key.
	 * @param string $method     HTTP method.
	 * @param string $path       API path without leading v1/.
	 * @param array  $body       POST body.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function stripe_request( $secret_key, $method, $path, $body = array() ) {
		$url = 'https://api.stripe.com/v1/' . ltrim( $path, '/' );

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret_key,
			),
		);

		if ( ! empty( $body ) && 'GET' !== strtoupper( $method ) ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mbmc_stripe_request_failed',
				__( 'Payment service is temporarily unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$message = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Payment service returned an error.', 'mad-baits-mobile-connector' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'MBMC Stripe API error: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new WP_Error(
				'mbmc_stripe_api_error',
				$message,
				array( 'status' => $code >= 400 && $code < 600 ? $code : 502 )
			);
		}

		return $data;
	}

	/**
	 * Ensure WooCommerce session/cart exist for shipping calculations in REST context.
	 *
	 * @return true|WP_Error
	 */
	private static function bootstrap_wc_session_for_shipping() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		if ( null === WC()->session ) {
			WC()->initialize_session();
		}

		if ( null === WC()->session ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout session could not be started.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		if ( null === WC()->customer ) {
			WC()->customer = new WC_Customer( 0, true );
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		if ( ! WC()->cart ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout cart could not be started.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		return true;
	}

	/**
	 * Merge partial billing/shipping payloads for quote-only requests.
	 *
	 * @param array $billing  Billing payload.
	 * @param array $shipping Shipping payload.
	 * @return array<string, string>
	 */
	private static function normalize_address_for_quote( $billing, $shipping ) {
		$billing  = is_array( $billing ) ? $billing : array();
		$shipping = is_array( $shipping ) ? $shipping : array();
		$merged   = array_merge( $billing, array_filter( $shipping ) );

		if ( empty( $merged['email'] ) || ! is_email( (string) $merged['email'] ) ) {
			$merged['email'] = 'shipping-quote@madbaits.com';
		}

		if ( empty( $merged['country'] ) ) {
			$merged['country'] = 'GB';
		}

		return $merged;
	}

	/**
	 * Calculate WooCommerce shipping rates for mobile cart + destination.
	 *
	 * @param array $line_items Cart line items.
	 * @param array $billing    Billing address payload.
	 * @param array $shipping   Shipping address payload.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private static function calculate_shipping_rates( $line_items, $billing, $shipping = array() ) {
		if ( ! MBMC_WooCommerce::is_available() ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$ship_payload = ! empty( $shipping ) ? $shipping : $billing;
		if ( ! is_array( $ship_payload ) ) {
			return new WP_Error(
				'mbmc_invalid_address',
				__( 'Shipping address is required to calculate delivery options.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$post = isset( $ship_payload['postcode'] ) ? sanitize_text_field( (string) $ship_payload['postcode'] ) : '';
		$city = isset( $ship_payload['city'] ) ? sanitize_text_field( (string) $ship_payload['city'] ) : '';

		if ( '' === $post || '' === $city ) {
			return new WP_Error(
				'mbmc_invalid_address',
				__( 'Enter your city and postcode to see shipping options.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$boot = self::bootstrap_wc_session_for_shipping();
		if ( is_wp_error( $boot ) ) {
			return $boot;
		}

		$destination = self::normalize_address_for_quote( $billing, $ship_payload );
		if ( empty( $destination['firstName'] ) ) {
			$destination['firstName'] = 'Guest';
		}
		if ( empty( $destination['lastName'] ) ) {
			$destination['lastName'] = 'User';
		}
		if ( empty( $destination['address1'] ) ) {
			$destination['address1'] = 'Address pending';
		}

		$order = MBMC_WooCommerce::create_order_from_line_items( 0, $line_items );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$billing_error = self::apply_address_to_order( $order, 'billing', $destination );
		if ( is_wp_error( $billing_error ) ) {
			$order->delete( true );
			return $billing_error;
		}

		$shipping_error = self::apply_address_to_order( $order, 'shipping', $destination );
		if ( is_wp_error( $shipping_error ) ) {
			$order->delete( true );
			return $shipping_error;
		}

		$order->calculate_totals();

		$package_contents = array();
		foreach ( $order->get_items( 'line_item' ) as $order_item ) {
			if ( ! $order_item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $order_item->get_product();
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$package_contents[] = array(
				'data'         => $product,
				'quantity'     => $order_item->get_quantity(),
				'line_total'   => $order_item->get_total(),
				'line_tax'     => $order_item->get_total_tax(),
				'product_id'   => $order_item->get_product_id(),
				'variation_id' => $order_item->get_variation_id(),
			);
		}

		if ( empty( $package_contents ) ) {
			$order->delete( true );
			return new WP_Error(
				'mbmc_invalid_line_items',
				__( 'At least one valid cart item is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$packages = array(
			array(
				'contents'        => $package_contents,
				'contents_cost'   => $order->get_subtotal(),
				'applied_coupons' => array(),
				'user'            => array(
					'ID' => 0,
				),
				'destination'     => array(
					'country'   => $order->get_shipping_country(),
					'state'     => $order->get_shipping_state(),
					'postcode'  => $order->get_shipping_postcode(),
					'city'      => $order->get_shipping_city(),
					'address'   => $order->get_shipping_address_1(),
					'address_1' => $order->get_shipping_address_1(),
					'address_2' => $order->get_shipping_address_2(),
				),
				'cart_subtotal'   => $order->get_subtotal(),
			),
		);

		$packages = WC()->shipping()->calculate_shipping( $packages );

		$rates = array();
		foreach ( $packages as $package_index => $package ) {
			if ( empty( $package['rates'] ) || ! is_array( $package['rates'] ) ) {
				continue;
			}

			foreach ( $package['rates'] as $rate_id => $rate ) {
				if ( ! $rate instanceof WC_Shipping_Rate ) {
					continue;
				}

				$rates[] = array(
					'id'           => (string) $rate_id,
					'packageIndex' => (int) $package_index,
					'label'        => (string) $rate->get_label(),
					'cost'         => wc_format_decimal( $rate->get_cost(), 2 ),
					'tax'          => wc_format_decimal( array_sum( $rate->get_taxes() ), 2 ),
				);
			}
		}

		$order->delete( true );

		if ( empty( $rates ) ) {
			return new WP_Error(
				'mbmc_no_shipping_rates',
				__( 'No shipping options are available for this address.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		return $rates;
	}

	/**
	 * Apply a selected shipping rate to an order.
	 *
	 * @param WC_Order $order              Order.
	 * @param array    $line_items         Cart line items.
	 * @param array    $billing            Billing address payload.
	 * @param array    $shipping           Shipping address payload.
	 * @param string   $shipping_method_id Selected rate id.
	 * @return true|WP_Error
	 */
	private static function apply_shipping_rate_to_order( WC_Order $order, $line_items, $billing, $shipping, $shipping_method_id ) {
		$rates = self::calculate_shipping_rates( $line_items, $billing, $shipping );
		if ( is_wp_error( $rates ) ) {
			return $rates;
		}

		$matched = null;
		foreach ( $rates as $rate ) {
			if ( isset( $rate['id'] ) && hash_equals( (string) $rate['id'], $shipping_method_id ) ) {
				$matched = $rate;
				break;
			}
		}

		if ( null === $matched ) {
			return new WP_Error(
				'mbmc_invalid_shipping_method',
				__( 'Selected shipping option is no longer available. Please choose again.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$method_parts = explode( ':', $shipping_method_id );
		$method_id    = isset( $method_parts[0] ) ? sanitize_text_field( $method_parts[0] ) : '';
		$instance_id  = isset( $method_parts[1] ) ? absint( $method_parts[1] ) : 0;

		$item = new WC_Order_Item_Shipping();
		$item->set_method_title( (string) $matched['label'] );
		$item->set_method_id( $method_id );
		if ( $instance_id > 0 ) {
			$item->set_instance_id( $instance_id );
		}
		$item->set_total( (string) $matched['cost'] );
		$order->add_item( $item );

		return true;
	}
}
