<?php
/**
 * REST API: authenticated account and orders.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Account
 */
class MBMC_REST_Account {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'madbaits/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/account',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_account' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_orders' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_order' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_checkout_session' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'lineItems' => array(
						'required' => true,
						'type'     => 'array',
						'minItems' => 1,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'productId'   => array(
									'type'              => 'integer',
									'minimum'           => 1,
									'sanitize_callback' => 'absint',
								),
								'variationId' => array(
									'type'              => 'integer',
									'minimum'           => 0,
									'sanitize_callback' => 'absint',
								),
								'quantity'    => array(
									'type'              => 'integer',
									'minimum'           => 1,
									'sanitize_callback' => 'absint',
								),
							),
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout/native/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_native_checkout_config' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout/native/shipping-quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'quote_native_checkout_shipping' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout/native/prepare',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'prepare_native_checkout' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout/native/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm_native_checkout' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout/complete-guest',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_guest_checkout' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'orderId'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderKey' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
					'deviceId' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'mbmc_sanitize_device_id',
					),
				),
			)
		);
	}

	/**
	 * GET /account
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_account( WP_REST_Request $request ) {
		$auth = mbmc_get_authenticated_user_from_request( $request );

		if ( ! $auth ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Invalid or missing Bearer access token.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$account = MBMC_WooCommerce::get_customer_account( (int) $auth['user_id'] );

		if ( is_wp_error( $account ) ) {
			return $account;
		}

		return rest_ensure_response(
			array_merge(
				$account,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * GET /orders
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_orders( WP_REST_Request $request ) {
		$auth = mbmc_get_authenticated_user_from_request( $request );

		if ( ! $auth ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Invalid or missing Bearer access token.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 50, (int) $request->get_param( 'per_page' ) ) );

		$result = MBMC_WooCommerce::get_customer_orders( (int) $auth['customer_id'], $page, $per_page );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array_merge(
				$result,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * GET /orders/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_order( WP_REST_Request $request ) {
		$auth = mbmc_get_authenticated_user_from_request( $request );

		if ( ! $auth ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Invalid or missing Bearer access token.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$order_id = absint( $request->get_param( 'id' ) );
		$order    = MBMC_WooCommerce::get_customer_order( (int) $auth['customer_id'], $order_id );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return rest_ensure_response(
			array_merge(
				$order,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * GET /checkout/native/config
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_native_checkout_config( WP_REST_Request $request ) {
		unset( $request );
		return rest_ensure_response(
			array_merge(
				MBMC_Native_Checkout::get_config(),
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * POST /checkout/native/shipping-quote
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function quote_native_checkout_shipping( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_native_checkout_shipping_quote', 60, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many shipping quote requests. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$params     = $this->get_checkout_json_params( $request );
		$line_items = isset( $params['lineItems'] ) ? $params['lineItems'] : null;
		$billing    = isset( $params['billing'] ) ? $params['billing'] : null;
		$shipping   = isset( $params['shipping'] ) ? $params['shipping'] : array();

		if ( ! is_array( $line_items ) || empty( $line_items ) || ! is_array( $billing ) ) {
			return new WP_Error(
				'mbmc_invalid_checkout_payload',
				__( 'Cart items and billing address are required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$result = MBMC_Native_Checkout::quote_shipping(
			$line_items,
			$billing,
			is_array( $shipping ) ? $shipping : array()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array_merge(
				$result,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * POST /checkout/native/prepare
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function prepare_native_checkout( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_native_checkout_prepare', 30, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many checkout attempts. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$auth        = mbmc_get_authenticated_user_from_request( $request );
		$customer_id = $auth ? (int) $auth['customer_id'] : 0;
		$params      = $this->get_checkout_json_params( $request );

		$line_items = isset( $params['lineItems'] ) ? $params['lineItems'] : null;
		$billing    = isset( $params['billing'] ) ? $params['billing'] : null;
		$shipping   = isset( $params['shipping'] ) ? $params['shipping'] : array();
		$loyalty_points = isset( $params['loyaltyPointsToApply'] ) ? absint( $params['loyaltyPointsToApply'] ) : 0;
		$user_id    = $auth ? (int) $auth['user_id'] : 0;
		$shipping_method_id = isset( $params['shippingMethodId'] ) ? sanitize_text_field( (string) $params['shippingMethodId'] ) : '';

		if ( ! is_array( $line_items ) || empty( $line_items ) || ! is_array( $billing ) ) {
			return new WP_Error(
				'mbmc_invalid_checkout_payload',
				__( 'Cart items and billing address are required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $shipping_method_id ) {
			return new WP_Error(
				'mbmc_shipping_method_required',
				__( 'Please select a shipping option before paying.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$result = MBMC_Native_Checkout::prepare(
			$customer_id,
			$line_items,
			$billing,
			is_array( $shipping ) ? $shipping : array(),
			$loyalty_points,
			$user_id,
			$shipping_method_id
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array_merge(
				$result,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * POST /checkout/native/confirm
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_native_checkout( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_native_checkout_confirm', 30, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many checkout attempts. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$params = $this->get_checkout_json_params( $request );
		$order_id = isset( $params['orderId'] ) ? absint( $params['orderId'] ) : 0;
		$order_key = isset( $params['orderKey'] ) ? sanitize_text_field( (string) $params['orderKey'] ) : '';
		$payment_intent_id = isset( $params['paymentIntentId'] ) ? sanitize_text_field( (string) $params['paymentIntentId'] ) : '';
		$payment_method_id = isset( $params['paymentMethodId'] ) ? sanitize_text_field( (string) $params['paymentMethodId'] ) : '';

		$result = MBMC_Native_Checkout::confirm( $order_id, $order_key, $payment_intent_id, $payment_method_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array_merge(
				$result,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * Merge JSON body with REST params for checkout routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function get_checkout_json_params( WP_REST_Request $request ) {
		$params = $request->get_params();
		$json   = $request->get_json_params();

		if ( is_array( $json ) ) {
			return array_merge( $params, $json );
		}

		return is_array( $params ) ? $params : array();
	}

	/**
	 * POST /checkout/session
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_checkout_session( WP_REST_Request $request ) {
		$auth        = mbmc_get_authenticated_user_from_request( $request );
		$customer_id = $auth ? (int) $auth['customer_id'] : 0;
		$line_items  = $request->get_param( 'lineItems' );

		if ( ! is_array( $line_items ) || empty( $line_items ) ) {
			$json_params = $request->get_json_params();
			if ( is_array( $json_params ) && ! empty( $json_params['lineItems'] ) ) {
				$line_items = $json_params['lineItems'];
			}
		}

		$result = MBMC_WooCommerce::create_checkout_session( $customer_id, $line_items );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array_merge(
				$result,
				array(
					'ok'          => true,
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			)
		);
	}

	/**
	 * POST /checkout/complete-guest
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete_guest_checkout( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_checkout_complete_guest', 10, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many attempts. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$order_id  = absint( $request->get_param( 'orderId' ) );
		$order_key = sanitize_text_field( (string) $request->get_param( 'orderKey' ) );
		$password  = (string) $request->get_param( 'password' );
		$device_id = mbmc_sanitize_device_id( (string) $request->get_param( 'deviceId' ) );

		$user = MBMC_WooCommerce::complete_guest_checkout( $order_id, $order_key, $password );

		if ( is_wp_error( $user ) ) {
			$status = (int) ( $user->get_error_data()['status'] ?? 400 );
			return new WP_Error(
				$user->get_error_code(),
				$user->get_error_message(),
				array( 'status' => $status > 0 ? $status : 400 )
			);
		}

		return rest_ensure_response(
			MBMC_REST_Auth::build_auth_response_for_user( $user, $device_id )
		);
	}
}
