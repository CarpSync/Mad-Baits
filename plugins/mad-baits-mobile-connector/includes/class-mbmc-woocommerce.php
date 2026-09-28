<?php
/**
 * WooCommerce integration — order notification hooks and future auth/orders.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_WooCommerce
 */
class MBMC_WooCommerce {

	/**
	 * Register integration hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register_order_hooks' ), 20 );
		add_action( 'plugins_loaded', array( 'MBMC_Loyalty_Order', 'init' ), 25 );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Register WooCommerce order status hooks when available.
	 *
	 * @return void
	 */
	public static function register_order_hooks() {
		if ( ! self::is_available() ) {
			return;
		}

		$status_map = array(
			'pending'    => 'order_received',
			'processing' => 'order_processing',
			'completed'  => 'order_completed',
			'cancelled'  => 'order_cancelled',
			'refunded'   => 'order_refunded',
			'failed'     => 'order_failed',
		);

		foreach ( $status_map as $status => $notification_type ) {
			add_action(
				'woocommerce_order_status_' . $status,
				static function ( $order_id, $order = null ) use ( $notification_type ) {
					MBMC_Notification_Service::dispatch_order_notification( $order_id, $notification_type, $order );
				},
				10,
				2
			);
		}

		$dispatched_hooks = array(
			'woocommerce_order_status_dispatched',
			'woocommerce_order_status_wc-dispatched',
		);

		foreach ( $dispatched_hooks as $hook ) {
			add_action(
				$hook,
				array( __CLASS__, 'handle_dispatched_status' ),
				10,
				2
			);
		}

		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_handle_tracking_meta_update' ), 10, 4 );
	}

	/**
	 * Handle custom dispatched order statuses.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order    Order object.
	 * @return void
	 */
	public static function handle_dispatched_status( $order_id, $order = null ) {
		MBMC_Notification_Service::dispatch_order_notification( $order_id, 'order_dispatched', $order );
	}

	/**
	 * Send tracking notification when supported tracking meta is added.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function maybe_handle_tracking_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );

		if ( ! self::is_available() || ! mbmc_is_order_notifications_enabled() ) {
			return;
		}

		if ( ! mbmc_is_order_tracking_notifications_enabled() ) {
			return;
		}

		if ( 'shop_order' !== get_post_type( $object_id ) ) {
			return;
		}

		if ( ! in_array( $meta_key, mbmc_get_tracking_meta_keys(), true ) ) {
			return;
		}

		if ( empty( $meta_value ) ) {
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $object_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $order->get_meta( '_mbmc_tracking_push_sent' ) ) {
			return;
		}

		$tracking = self::get_order_tracking_data( $order );

		if ( empty( $tracking['trackingNumber'] ) ) {
			return;
		}

		MBMC_Notification_Service::dispatch_order_notification( $object_id, 'tracking_added', $order );
	}

	/**
	 * Detect tracking data from common WooCommerce / plugin meta keys.
	 *
	 * @param WC_Order $order Order object.
	 * @return array{trackingNumber:?string,trackingUrl:?string,courier:?string}
	 */
	public static function get_order_tracking_data( WC_Order $order ) {
		$result = array(
			'trackingNumber' => null,
			'trackingUrl'    => null,
			'courier'        => null,
		);

		$number_keys = array(
			'_tracking_number',
			'tracking_number',
			'_shipping_tracking_number',
			'_wc_shipment_tracking_number',
		);

		foreach ( $number_keys as $key ) {
			$value = trim( (string) $order->get_meta( $key ) );

			if ( '' !== $value ) {
				$result['trackingNumber'] = sanitize_text_field( $value );
				break;
			}
		}

		$url_keys = array(
			'_tracking_url',
			'tracking_url',
			'_wc_shipment_tracking_items',
		);

		foreach ( $url_keys as $key ) {
			$value = $order->get_meta( $key );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$result['trackingUrl'] = esc_url_raw( $value );
					break;
				}
			}

			if ( is_array( $value ) && ! empty( $value ) ) {
				$first = reset( $value );

				if ( is_array( $first ) ) {
					if ( ! empty( $first['tracking_number'] ) && empty( $result['trackingNumber'] ) ) {
						$result['trackingNumber'] = sanitize_text_field( (string) $first['tracking_number'] );
					}

					if ( ! empty( $first['tracking_link'] ) ) {
						$result['trackingUrl'] = esc_url_raw( (string) $first['tracking_link'] );
					} elseif ( ! empty( $first['custom_tracking_link'] ) ) {
						$result['trackingUrl'] = esc_url_raw( (string) $first['custom_tracking_link'] );
					}

					if ( ! empty( $first['tracking_provider'] ) || ! empty( $first['custom_tracking_provider'] ) ) {
						$result['courier'] = sanitize_text_field(
							(string) ( $first['tracking_provider'] ?? $first['custom_tracking_provider'] )
						);
					}

					break;
				}
			}
		}

		$courier_keys = array(
			'_tracking_provider',
			'tracking_provider',
			'_shipping_carrier',
		);

		if ( empty( $result['courier'] ) ) {
			foreach ( $courier_keys as $key ) {
				$value = trim( (string) $order->get_meta( $key ) );

				if ( '' !== $value ) {
					$result['courier'] = sanitize_text_field( $value );
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Authenticate a WooCommerce customer by email and password.
	 *
	 * @param string $email    Customer email.
	 * @param string $password Customer password.
	 * @return WP_User|WP_Error
	 */
	public static function authenticate_customer( $identifier, $password ) {
		$identifier = trim( (string) $identifier );
		$password   = (string) $password;

		if ( '' === $identifier || '' === $password ) {
			return new WP_Error( 'mbmc_invalid_login', 'Invalid credentials' );
		}

		$user = mbmc_resolve_user_by_login_identifier( $identifier );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'mbmc_invalid_login', 'Invalid credentials' );
		}

		$authenticated = wp_authenticate( $user->user_login, $password );

		if ( is_wp_error( $authenticated ) ) {
			return new WP_Error( 'mbmc_invalid_login', 'Invalid credentials' );
		}

		if ( ! $authenticated instanceof WP_User ) {
			return new WP_Error( 'mbmc_invalid_login', 'Invalid credentials' );
		}

		$denial = mbmc_get_customer_auth_denial( $authenticated );
		if ( $denial instanceof WP_Error ) {
			return $denial;
		}

		return $authenticated;
	}

	/**
	 * Register a new WooCommerce customer for mobile app sign-up.
	 *
	 * @param string $email        Customer email.
	 * @param string $password     Customer password.
	 * @param string $display_name Preferred display name.
	 * @return WP_User|WP_Error
	 */
	public static function register_customer( $email, $password, $display_name = '' ) {
		if ( ! self::is_available() ) {
			return new WP_Error(
				'mbmc_wc_unavailable',
				__( 'WooCommerce is required for account registration.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		if ( ! function_exists( 'wc_create_new_customer' ) ) {
			return new WP_Error(
				'mbmc_wc_unavailable',
				__( 'Customer registration is unavailable on this store.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$email = sanitize_email( (string) $email );
		$password = (string) $password;
		$display_name = sanitize_text_field( (string) $display_name );

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'mbmc_invalid_email',
				__( 'Enter a valid email address.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $password ) < 8 ) {
			return new WP_Error(
				'mbmc_invalid_password',
				__( 'Password must be at least 8 characters.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( email_exists( $email ) ) {
			return new WP_Error(
				'mbmc_email_exists',
				__( 'An account with this email already exists. Sign in instead.', 'mad-baits-mobile-connector' ),
				array( 'status' => 409 )
			);
		}

		if ( '' === $display_name ) {
			$display_name = sanitize_text_field( (string) strstr( $email, '@', true ) );
		}

		if ( '' === $display_name ) {
			$display_name = 'Angler';
		}

		$customer_id = wc_create_new_customer(
			$email,
			'',
			$password,
			array(
				'display_name' => $display_name,
				'first_name'   => $display_name,
			)
		);

		if ( is_wp_error( $customer_id ) ) {
			$code = $customer_id->get_error_code();

			if ( in_array( $code, array( 'registration-error-email-exists', 'registration-error-username-exists' ), true ) ) {
				return new WP_Error(
					'mbmc_email_exists',
					__( 'An account with this email already exists. Sign in instead.', 'mad-baits-mobile-connector' ),
					array( 'status' => 409 )
				);
			}

			if ( 'registration-error-invalid-email' === $code ) {
				return new WP_Error(
					'mbmc_invalid_email',
					__( 'Enter a valid email address.', 'mad-baits-mobile-connector' ),
					array( 'status' => 400 )
				);
			}

			return $customer_id;
		}

		$user = get_user_by( 'id', (int) $customer_id );

		if ( ! $user instanceof WP_User || ! mbmc_user_is_allowed_customer( $user ) ) {
			return new WP_Error(
				'mbmc_registration_failed',
				__( 'Could not create your account. Please try again.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		return $user;
	}

	/**
	 * Build customer account profile for GET /account.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_customer_account( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_invalid_user',
				__( 'Account not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || ! mbmc_user_is_allowed_customer( $user ) ) {
			return new WP_Error(
				'mbmc_invalid_user',
				__( 'Account not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		$customer_id = $user_id;
		$total_orders = 0;

		if ( self::is_available() && function_exists( 'wc_get_customer_order_count' ) ) {
			$total_orders = (int) wc_get_customer_order_count( $customer_id );
		}

		$billing  = self::format_address_summary_from_user( $user, 'billing' );
		$shipping = self::format_address_summary_from_user( $user, 'shipping' );

		return array_merge(
			array(
				'userId'       => (string) $user_id,
				'customerId'   => (string) $customer_id,
				'email'        => sanitize_email( $user->user_email ),
				'firstName'    => sanitize_text_field( (string) get_user_meta( $user_id, 'first_name', true ) ) ?: null,
				'lastName'     => sanitize_text_field( (string) get_user_meta( $user_id, 'last_name', true ) ) ?: null,
				'displayName'  => sanitize_text_field( $user->display_name ),
				'billing'      => $billing,
				'shipping'     => $shipping,
				'totalOrders'  => $total_orders,
				'dateCreated'  => gmdate( 'c', strtotime( $user->user_registered ) ),
			),
			mbmc_get_user_team_profile( $user )
		);
	}

	/**
	 * Build paginated order list for GET /orders.
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @param int $page        Page number.
	 * @param int $per_page    Items per page.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_customer_orders( $customer_id, $page = 1, $per_page = 20 ) {
		$customer_id = absint( $customer_id );
		$page        = max( 1, (int) $page );
		$per_page    = max( 1, min( 50, (int) $per_page ) );

		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'mbmc_invalid_customer',
				__( 'Customer not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		if ( ! self::is_available() || ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'orders'  => array(),
				'page'    => $page,
				'perPage' => $per_page,
				'total'   => 0,
			);
		}

		$query = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => $per_page,
				'page'        => $page,
				'paginate'    => true,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
			)
		);

		$orders = array();

		if ( is_object( $query ) && ! empty( $query->orders ) ) {
			foreach ( $query->orders as $order ) {
				if ( $order instanceof WC_Order && (int) $order->get_customer_id() === $customer_id ) {
					$orders[] = self::map_order_summary( $order );
				}
			}
		}

		return array(
			'orders'  => $orders,
			'page'    => $page,
			'perPage' => $per_page,
			'total'   => is_object( $query ) ? (int) $query->total : count( $orders ),
		);
	}

	/**
	 * Fetch a single order for the authenticated customer.
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @param int $order_id    Order ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_customer_order( $customer_id, $order_id ) {
		$customer_id = absint( $customer_id );
		$order_id    = absint( $order_id );

		if ( $customer_id <= 0 || $order_id <= 0 ) {
			return new WP_Error(
				'mbmc_order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		if ( ! self::is_available() || ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error(
				'mbmc_order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== $customer_id ) {
			return new WP_Error(
				'mbmc_order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		return self::map_order_detail( $order );
	}

	/**
	 * Create a pending WooCommerce order from mobile cart line items.
	 *
	 * @param int   $customer_id WooCommerce customer ID.
	 * @param array $line_items  Cart line items from app.
	 * @return WC_Order|WP_Error
	 */
	public static function create_order_from_line_items( $customer_id, $line_items ) {
		$customer_id = absint( $customer_id );

		if ( ! self::is_available() || ! function_exists( 'wc_create_order' ) || ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		if ( ! is_array( $line_items ) || empty( $line_items ) ) {
			return new WP_Error(
				'mbmc_invalid_line_items',
				__( 'At least one line item is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$order = wc_create_order(
			array(
				'customer_id' => $customer_id,
				'created_via' => 'madbaits_mobile',
			)
		);

		if ( is_wp_error( $order ) || ! $order instanceof WC_Order ) {
			return is_wp_error( $order )
				? $order
				: new WP_Error(
					'mbmc_checkout_order_create_failed',
					__( 'Could not start checkout.', 'mad-baits-mobile-connector' ),
					array( 'status' => 500 )
				);
		}

		$added_count = 0;

		foreach ( $line_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$product_id   = isset( $raw_item['productId'] ) ? absint( $raw_item['productId'] ) : 0;
			$variation_id = isset( $raw_item['variationId'] ) ? absint( $raw_item['variationId'] ) : 0;
			$quantity     = isset( $raw_item['quantity'] ) ? max( 1, absint( $raw_item['quantity'] ) ) : 1;

			$target_product_id = $variation_id > 0 ? $variation_id : $product_id;
			if ( $target_product_id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $target_product_id );
			if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
				continue;
			}

			try {
				$order->add_product( $product, $quantity );
				$added_count++;
			} catch ( Exception $exception ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'MBMC checkout add_product failed: ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		if ( $added_count <= 0 ) {
			$order->delete( true );
			return new WP_Error(
				'mbmc_checkout_items_invalid',
				__( 'None of the selected items are available for checkout.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( $customer_id > 0 ) {
			$user = get_user_by( 'id', $customer_id );
			if ( $user instanceof WP_User ) {
				$billing_email = sanitize_email( $user->user_email );
				if ( '' !== $billing_email ) {
					$order->set_billing_email( $billing_email );
				}
			}
		}

		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Create a checkout payment session from mobile cart line items.
	 *
	 * @param int   $customer_id WooCommerce customer ID.
	 * @param array $line_items  Cart line items from app.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_checkout_session( $customer_id, $line_items ) {
		$order = self::create_order_from_line_items( $customer_id, $line_items );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return array(
			'orderId'      => (string) $order->get_id(),
			'orderNumber'  => (string) $order->get_order_number(),
			'checkoutUrl'  => esc_url_raw( $order->get_checkout_payment_url() ),
			'checkoutType' => $customer_id > 0 ? 'order_pay' : 'guest_order_pay',
			'isGuest'      => $customer_id <= 0,
		);
	}

	/**
	 * Create a customer account from a completed guest order and link the order.
	 *
	 * @param int    $order_id  WooCommerce order ID.
	 * @param string $order_key Order key from order-received URL.
	 * @param string $password  Customer password for the new (or existing) account.
	 * @return WP_User|WP_Error
	 */
	public static function complete_guest_checkout( $order_id, $order_key, $password ) {
		$order_id  = absint( $order_id );
		$order_key = sanitize_text_field( (string) $order_key );
		$password  = (string) $password;

		if ( $order_id <= 0 || '' === $order_key ) {
			return new WP_Error(
				'mbmc_invalid_order',
				__( 'Invalid order details.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $password ) < 8 ) {
			return new WP_Error(
				'mbmc_invalid_password',
				__( 'Password must be at least 8 characters.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( ! self::is_available() || ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error(
				'mbmc_order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		if ( ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
			return new WP_Error(
				'mbmc_order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		$allowed_statuses = array( 'processing', 'completed', 'on-hold' );
		if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
			return new WP_Error(
				'mbmc_order_not_complete',
				__( 'This order is not ready for account setup yet.', 'mad-baits-mobile-connector' ),
				array( 'status' => 409 )
			);
		}

		$email = sanitize_email( (string) $order->get_billing_email() );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'mbmc_invalid_email',
				__( 'No billing email was found on this order.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$existing_customer_id = (int) $order->get_customer_id();
		$user                 = null;

		if ( email_exists( $email ) ) {
			$user = self::authenticate_customer( $email, $password );
			if ( is_wp_error( $user ) ) {
				return new WP_Error(
					'mbmc_email_exists',
					__( 'An account with this email already exists. Sign in with your existing password to link this order.', 'mad-baits-mobile-connector' ),
					array( 'status' => 409 )
				);
			}
		} else {
			$display_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$user         = self::register_customer( $email, $password, $display_name );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'mbmc_registration_failed',
				__( 'Could not create your account. Please try again.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		if ( $existing_customer_id <= 0 || $existing_customer_id !== (int) $user->ID ) {
			$order->set_customer_id( (int) $user->ID );
			$order->save();
		}

		return $user;
	}

	/**
	 * Map WooCommerce order to mobile-safe detail payload.
	 *
	 * @param WC_Order $order Order object.
	 * @return array<string, mixed>
	 */
	public static function map_order_detail( WC_Order $order ) {
		$tracking = self::get_order_tracking_data( $order );
		$summary  = self::map_order_summary( $order );

		return array_merge(
			$summary,
			array(
				'billing'  => self::format_address_summary_from_order( $order, 'billing' ),
				'shipping' => self::format_address_summary_from_order( $order, 'shipping' ),
				'tracking' => ! empty( $tracking['trackingNumber'] ) || ! empty( $tracking['trackingUrl'] ) || ! empty( $tracking['courier'] )
					? $tracking
					: null,
				'timeline' => self::build_order_timeline( $order ),
			)
		);
	}

	/**
	 * Build order progress timeline for mobile UI.
	 *
	 * @param WC_Order $order Order object.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_order_timeline( WC_Order $order ) {
		$created     = $order->get_date_created();
		$created_at  = $created ? gmdate( 'c', $created->getTimestamp() ) : null;
		$status      = sanitize_key( $order->get_status() );
		$dispatched  = self::is_order_dispatched( $order );
		$processing  = self::order_has_reached_processing( $order );
		$completed   = 'completed' === $status;

		return array(
			array(
				'stage'     => 'received',
				'label'     => __( 'Order received', 'mad-baits-mobile-connector' ),
				'completed' => true,
				'date'      => $created_at,
			),
			array(
				'stage'     => 'processing',
				'label'     => __( 'Processing', 'mad-baits-mobile-connector' ),
				'completed' => $processing,
				'date'      => $processing ? self::get_order_status_date( $order, 'processing' ) : null,
			),
			array(
				'stage'     => 'dispatched',
				'label'     => __( 'Dispatched', 'mad-baits-mobile-connector' ),
				'completed' => $dispatched,
				'date'      => $dispatched ? self::get_dispatched_date( $order ) : null,
			),
			array(
				'stage'     => 'completed',
				'label'     => __( 'Completed', 'mad-baits-mobile-connector' ),
				'completed' => $completed,
				'date'      => $completed ? self::get_order_status_date( $order, 'completed' ) : null,
			),
		);
	}

	/**
	 * Whether order has reached processing stage or beyond.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	private static function order_has_reached_processing( WC_Order $order ) {
		$status = sanitize_key( $order->get_status() );

		if ( in_array( $status, array( 'processing', 'completed', 'dispatched', 'wc-dispatched' ), true ) ) {
			return true;
		}

		return null !== self::get_order_status_date( $order, 'processing' );
	}

	/**
	 * Whether order is dispatched by status or tracking meta.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	private static function is_order_dispatched( WC_Order $order ) {
		$status = sanitize_key( $order->get_status() );

		if ( in_array( $status, array( 'dispatched', 'wc-dispatched' ), true ) ) {
			return true;
		}

		$tracking = self::get_order_tracking_data( $order );

		return ! empty( $tracking['trackingNumber'] ) || ! empty( $tracking['trackingUrl'] );
	}

	/**
	 * Best-effort date for a WooCommerce order status from notes.
	 *
	 * @param WC_Order $order  Order object.
	 * @param string   $status Target status slug.
	 * @return string|null ISO8601 date.
	 */
	private static function get_order_status_date( WC_Order $order, $status ) {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'orderby'  => 'date_created',
				'order'    => 'ASC',
			)
		);

		foreach ( $notes as $note ) {
			if ( ! is_object( $note ) || empty( $note->content ) ) {
				continue;
			}

			$content = strtolower( (string) $note->content );

			if ( false !== strpos( $content, 'status changed' ) && false !== strpos( $content, $status ) ) {
				if ( ! empty( $note->date_created ) && method_exists( $note->date_created, 'getTimestamp' ) ) {
					return gmdate( 'c', $note->date_created->getTimestamp() );
				}
			}
		}

		if ( sanitize_key( $order->get_status() ) === $status ) {
			$modified = $order->get_date_modified();

			if ( $modified ) {
				return gmdate( 'c', $modified->getTimestamp() );
			}
		}

		return null;
	}

	/**
	 * Best-effort dispatched date from status notes or modified date.
	 *
	 * @param WC_Order $order Order object.
	 * @return string|null ISO8601 date.
	 */
	private static function get_dispatched_date( WC_Order $order ) {
		$status_date = self::get_order_status_date( $order, 'dispatched' );

		if ( $status_date ) {
			return $status_date;
		}

		$status_date = self::get_order_status_date( $order, 'wc-dispatched' );

		if ( $status_date ) {
			return $status_date;
		}

		$tracking = self::get_order_tracking_data( $order );

		if ( ! empty( $tracking['trackingNumber'] ) || ! empty( $tracking['trackingUrl'] ) ) {
			$modified = $order->get_date_modified();

			if ( $modified ) {
				return gmdate( 'c', $modified->getTimestamp() );
			}
		}

		return null;
	}

	/**
	 * Map WooCommerce order to mobile-safe summary.
	 *
	 * @param WC_Order $order Order object.
	 * @return array<string, mixed>
	 */
	public static function map_order_summary( WC_Order $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$items[] = array(
				'name'     => sanitize_text_field( $item->get_name() ),
				'quantity' => (int) $item->get_quantity(),
				'total'    => wc_format_decimal( $item->get_total(), 2 ),
			);
		}

		$tracking = self::get_order_tracking_data( $order );
		$payload  = array(
			'id'              => (string) $order->get_id(),
			'orderNumber'     => (string) $order->get_order_number(),
			'status'          => sanitize_key( $order->get_status() ),
			'dateCreated'     => gmdate( 'c', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ),
			'total'           => wc_format_decimal( $order->get_total(), 2 ),
			'currency'        => sanitize_text_field( $order->get_currency() ),
			'items'           => $items,
			'billingAddress'  => self::format_address_summary_from_order( $order, 'billing' ),
			'shippingAddress' => self::format_address_summary_from_order( $order, 'shipping' ),
		);

		if ( ! empty( $tracking['trackingNumber'] ) || ! empty( $tracking['trackingUrl'] ) || ! empty( $tracking['courier'] ) ) {
			$payload['tracking'] = array(
				'trackingNumber' => $tracking['trackingNumber'],
				'trackingUrl'    => $tracking['trackingUrl'],
				'courier'        => $tracking['courier'],
			);
		}

		return $payload;
	}

	/**
	 * Format billing/shipping summary from user meta.
	 *
	 * @param WP_User $user Address owner.
	 * @param string  $type billing|shipping.
	 * @return array<string, string|null>
	 */
	private static function format_address_summary_from_user( WP_User $user, $type ) {
		$prefix = 'billing' === $type ? 'billing_' : 'shipping_';

		return array(
			'firstName' => sanitize_text_field( (string) get_user_meta( $user->ID, $prefix . 'first_name', true ) ) ?: null,
			'lastName'  => sanitize_text_field( (string) get_user_meta( $user->ID, $prefix . 'last_name', true ) ) ?: null,
			'city'      => sanitize_text_field( (string) get_user_meta( $user->ID, $prefix . 'city', true ) ) ?: null,
			'postcode'  => sanitize_text_field( (string) get_user_meta( $user->ID, $prefix . 'postcode', true ) ) ?: null,
			'country'   => sanitize_text_field( (string) get_user_meta( $user->ID, $prefix . 'country', true ) ) ?: null,
		);
	}

	/**
	 * Format billing/shipping summary from order.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $type  billing|shipping.
	 * @return array<string, string|null>
	 */
	private static function format_address_summary_from_order( WC_Order $order, $type ) {
		if ( 'billing' === $type ) {
			return array(
				'firstName' => sanitize_text_field( $order->get_billing_first_name() ) ?: null,
				'lastName'  => sanitize_text_field( $order->get_billing_last_name() ) ?: null,
				'city'      => sanitize_text_field( $order->get_billing_city() ) ?: null,
				'postcode'  => sanitize_text_field( $order->get_billing_postcode() ) ?: null,
				'country'   => sanitize_text_field( $order->get_billing_country() ) ?: null,
			);
		}

		return array(
			'firstName' => sanitize_text_field( $order->get_shipping_first_name() ) ?: null,
			'lastName'  => sanitize_text_field( $order->get_shipping_last_name() ) ?: null,
			'city'      => sanitize_text_field( $order->get_shipping_city() ) ?: null,
			'postcode'  => sanitize_text_field( $order->get_shipping_postcode() ) ?: null,
			'country'   => sanitize_text_field( $order->get_shipping_country() ) ?: null,
		);
	}
}
