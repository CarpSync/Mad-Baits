<?php
/**
 * Push notification orchestration and logging.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Notification_Service
 */
class MBMC_Notification_Service {

	/**
	 * Send a single test/direct notification via REST or admin panel.
	 *
	 * @param array<string, mixed> $args Request args.
	 * @return array<string, mixed>
	 */
	public static function send_test_notification( array $args ) {
		return self::send_push_to_device(
			array(
				'type'       => $args['type'] ?? '',
				'title'      => $args['title'] ?? '',
				'message'    => $args['message'] ?? '',
				'data'       => $args['data'] ?? array(),
				'device_id'  => $args['deviceId'] ?? '',
				'push_token' => $args['pushToken'] ?? '',
			),
			array(
				'enforce_test_type_prefix' => true,
			)
		);
	}

	/**
	 * Dispatch order push notifications to all matching customer devices.
	 *
	 * @param int                   $order_id          WooCommerce order ID.
	 * @param string                $notification_type Notification type slug.
	 * @param WC_Order|null         $order             Optional order object.
	 * @return array<string, mixed> Summary result.
	 */
	public static function dispatch_order_notification( $order_id, $notification_type, $order = null ) {
		$notification_type = mbmc_sanitize_notification_type( $notification_type );
		$order_id          = absint( $order_id );

		if ( ! $order_id || '' === $notification_type ) {
			return self::build_batch_summary( 0, 0, 0 );
		}

		if ( ! mbmc_is_order_notifications_enabled() ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'order_notifications_disabled',
				__( 'Order notifications are disabled in plugin settings.', 'mad-baits-mobile-connector' )
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		if ( 'order_dispatched' === $notification_type && ! mbmc_is_order_dispatched_notifications_enabled() ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'dispatched_notifications_disabled',
				__( 'Dispatched order notifications are disabled.', 'mad-baits-mobile-connector' )
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		if ( 'tracking_added' === $notification_type && ! mbmc_is_order_tracking_notifications_enabled() ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'tracking_notifications_disabled',
				__( 'Tracking notifications are disabled.', 'mad-baits-mobile-connector' )
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		if ( mbmc_is_order_require_push_enabled() && ! mbmc_is_push_sending_enabled() ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'push_sending_disabled',
				__( 'Push sending is disabled. Order notifications require push sending to be enabled.', 'mad-baits-mobile-connector' )
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return self::build_batch_summary( 0, 0, 0 );
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'order_not_found',
				__( 'Order not found.', 'mad-baits-mobile-connector' )
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		$customer_id = (int) $order->get_customer_id();

		if ( $customer_id <= 0 ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'guest_order_no_devices',
				__( 'Guest orders cannot receive push notifications until a customer account is linked.', 'mad-baits-mobile-connector' ),
				$order
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		$devices = MBMC_DB::get_devices_by_customer_id( $customer_id, mbmc_get_push_max_batch() );

		if ( empty( $devices ) ) {
			self::log_order_level_skip(
				$order_id,
				$notification_type,
				'no_registered_devices',
				__( 'No registered devices found for this customer.', 'mad-baits-mobile-connector' ),
				$order,
				$customer_id
			);

			return self::build_batch_summary( 0, 0, 1 );
		}

		if ( mbmc_is_push_test_mode_enabled() ) {
			foreach ( $devices as $device ) {
				self::write_log(
					array(
						'device_id'         => $device->device_id,
						'push_token'        => $device->push_token,
						'notification_type' => $notification_type,
						'title'             => self::get_order_notification_title(),
						'message'           => mbmc_get_order_notification_message( $notification_type, $order->get_order_number() ),
						'payload'           => wp_json_encode( self::build_order_push_data( $order, $notification_type, $customer_id ) ),
						'provider'          => mbmc_get_push_provider(),
						'status'            => 'skipped',
						'provider_response' => wp_json_encode(
							array(
								'reason'      => 'test_mode_blocks_order_notifications',
								'orderId'     => $order_id,
								'customerId'  => $customer_id,
							)
						),
					)
				);
			}

			return self::build_batch_summary( 0, 0, count( $devices ) );
		}

		$title   = self::get_order_notification_title();
		$message = mbmc_get_order_notification_message( $notification_type, $order->get_order_number() );
		$data    = self::build_order_push_data( $order, $notification_type, $customer_id );

		$sent    = 0;
		$failed  = 0;
		$skipped = 0;

		foreach ( $devices as $device ) {
			if ( ! mbmc_device_allows_order_notification( $device, $notification_type ) ) {
				self::write_log(
					array(
						'device_id'         => $device->device_id,
						'push_token'        => $device->push_token,
						'notification_type' => $notification_type,
						'title'             => $title,
						'message'           => $message,
						'payload'           => wp_json_encode( $data ),
						'provider'          => mbmc_get_push_provider(),
						'status'            => 'skipped',
						'provider_response' => wp_json_encode(
							array(
								'reason'     => 'preference_disabled',
								'orderId'    => $order_id,
								'customerId' => $customer_id,
							)
						),
					)
				);
				++$skipped;
				continue;
			}

			$result = self::send_push_to_device(
				array(
					'type'       => $notification_type,
					'title'      => $title,
					'message'    => $message,
					'data'       => $data,
					'device_id'  => $device->device_id,
					'push_token' => $device->push_token,
				),
				array(
					'enforce_test_type_prefix' => false,
				)
			);

			if ( ! empty( $result['success'] ) ) {
				++$sent;
			} elseif ( 'skipped' === ( $result['status'] ?? '' ) ) {
				++$skipped;
			} else {
				++$failed;
			}
		}

		if ( 'tracking_added' === $notification_type && $sent > 0 ) {
			$order->update_meta_data( '_mbmc_tracking_push_sent', '1' );
			$order->save();
		}

		return self::build_batch_summary( $sent, $failed, $skipped );
	}

	/**
	 * Core single-device push send used by tests and order notifications.
	 *
	 * @param array<string, mixed> $args    Notification args.
	 * @param array<string, bool>  $options Behaviour flags.
	 * @return array<string, mixed>
	 */
	public static function send_push_to_device( array $args, array $options = array() ) {
		$enforce_test_prefix = ! empty( $options['enforce_test_type_prefix'] );

		$notification_type = mbmc_sanitize_notification_type( $args['type'] ?? '' );
		$title             = mbmc_sanitize_short_text( $args['title'] ?? '', 255 );
		$message           = mbmc_sanitize_long_text( $args['message'] ?? '', 500 );
		$data              = mbmc_sanitize_push_data( $args['data'] ?? array() );
		$device_id         = mbmc_sanitize_device_id( $args['device_id'] ?? ( $args['deviceId'] ?? '' ) );
		$push_token        = mbmc_sanitize_push_token( $args['push_token'] ?? ( $args['pushToken'] ?? '' ) );
		$provider_id       = mbmc_get_push_provider();

		if ( '' === $notification_type ) {
			return self::build_response(
				false,
				'skipped',
				__( 'type is required.', 'mad-baits-mobile-connector' ),
				null,
				$provider_id
			);
		}

		if ( '' === $title || '' === $message ) {
			return self::build_response(
				false,
				'skipped',
				__( 'title and message are required.', 'mad-baits-mobile-connector' ),
				null,
				$provider_id
			);
		}

		if ( ! mbmc_is_push_sending_enabled() ) {
			$log_id = self::write_log(
				array(
					'device_id'         => $device_id ?: null,
					'push_token'        => $push_token ?: null,
					'notification_type' => $notification_type,
					'title'             => $title,
					'message'           => $message,
					'payload'           => wp_json_encode( $data ),
					'provider'          => $provider_id,
					'status'            => 'skipped',
					'provider_response' => wp_json_encode(
						array(
							'reason' => 'push_sending_disabled',
						)
					),
				)
			);

			return self::build_response(
				false,
				'skipped',
				__( 'Push sending is disabled. Enable it under Mobile App → Settings.', 'mad-baits-mobile-connector' ),
				$log_id,
				$provider_id
			);
		}

		if ( $enforce_test_prefix && mbmc_is_push_test_mode_enabled() && 0 !== strpos( $notification_type, 'test_' ) ) {
			$log_id = self::write_log(
				array(
					'device_id'         => $device_id ?: null,
					'push_token'        => $push_token ?: null,
					'notification_type' => $notification_type,
					'title'             => $title,
					'message'           => $message,
					'payload'           => wp_json_encode( $data ),
					'provider'          => $provider_id,
					'status'            => 'skipped',
					'provider_response' => wp_json_encode(
						array(
							'reason' => 'test_mode_type_restriction',
						)
					),
				)
			);

			return self::build_response(
				false,
				'skipped',
				__( 'Test mode is enabled. notification type must start with test_.', 'mad-baits-mobile-connector' ),
				$log_id,
				$provider_id
			);
		}

		$resolved = self::resolve_push_token( $device_id, $push_token );

		if ( is_wp_error( $resolved ) ) {
			$log_id = self::write_log(
				array(
					'device_id'         => $device_id ?: null,
					'push_token'        => $push_token ?: null,
					'notification_type' => $notification_type,
					'title'             => $title,
					'message'           => $message,
					'payload'           => wp_json_encode( $data ),
					'provider'          => $provider_id,
					'status'            => 'failed',
					'provider_response' => wp_json_encode(
						array(
							'error' => $resolved->get_error_code(),
						)
					),
				)
			);

			return self::build_response(
				false,
				'failed',
				$resolved->get_error_message(),
				$log_id,
				$provider_id
			);
		}

		$push_token = $resolved['token'];
		$device_id  = $resolved['device_id'];

		if ( 'firebase_future' === $provider_id ) {
			$log_id = self::write_log(
				array(
					'device_id'         => $device_id,
					'push_token'        => $push_token,
					'notification_type' => $notification_type,
					'title'             => $title,
					'message'           => $message,
					'payload'           => wp_json_encode( $data ),
					'provider'          => $provider_id,
					'status'            => 'skipped',
					'provider_response' => wp_json_encode(
						array(
							'reason' => 'provider_not_implemented',
						)
					),
				)
			);

			return self::build_response(
				false,
				'skipped',
				__( 'Firebase push provider is not implemented yet. Use expo.', 'mad-baits-mobile-connector' ),
				$log_id,
				$provider_id
			);
		}

		$provider = MBMC_Push_Provider::create( $provider_id );

		if ( ! $provider ) {
			return self::build_response(
				false,
				'failed',
				__( 'Push provider is not available.', 'mad-baits-mobile-connector' ),
				null,
				$provider_id
			);
		}

		$result = $provider->send( $push_token, $title, $message, $data );

		$log_id = self::write_log(
			array(
				'device_id'         => $device_id,
				'push_token'        => $push_token,
				'notification_type' => $notification_type,
				'title'             => $title,
				'message'           => $message,
				'payload'           => wp_json_encode( $data ),
				'provider'          => $provider->get_id(),
				'status'            => $result['status'],
				'provider_response' => $result['provider_response'],
			)
		);

		$response = self::build_response(
			$result['success'],
			$result['status'],
			$result['success']
				? __( 'Notification sent.', 'mad-baits-mobile-connector' )
				: ( $result['error_message'] ?: __( 'Notification failed.', 'mad-baits-mobile-connector' ) ),
			$log_id,
			$provider->get_id()
		);

		if ( ! empty( $result['ticket_id'] ) ) {
			$response['ticketId'] = $result['ticket_id'];
		}

		return $response;
	}

	/**
	 * Build order push payload data.
	 *
	 * @param WC_Order $order             Order.
	 * @param string   $notification_type Type slug.
	 * @param int      $customer_id       Customer ID.
	 * @return array<string, scalar|null>
	 */
	public static function build_order_push_data( WC_Order $order, $notification_type, $customer_id ) {
		$order_id = $order->get_id();

		$data = array(
			'type'         => $notification_type,
			'orderId'      => (string) $order_id,
			'orderNumber'  => (string) $order->get_order_number(),
			'status'       => (string) $order->get_status(),
			'customerId'   => (string) $customer_id,
			'deepLink'     => 'madbaits://orders/' . $order_id,
		);

		$tracking = MBMC_WooCommerce::get_order_tracking_data( $order );

		if ( ! empty( $tracking['trackingNumber'] ) ) {
			$data['trackingNumber'] = $tracking['trackingNumber'];
		}

		if ( ! empty( $tracking['trackingUrl'] ) ) {
			$data['trackingUrl'] = $tracking['trackingUrl'];
		}

		if ( ! empty( $tracking['courier'] ) ) {
			$data['courier'] = $tracking['courier'];
		}

		return mbmc_sanitize_push_data( $data );
	}

	/**
	 * Default push title for order notifications.
	 *
	 * @return string
	 */
	public static function get_order_notification_title() {
		return __( 'Mad Baits order update', 'mad-baits-mobile-connector' );
	}

	/**
	 * Log an order-level skip without a specific device.
	 *
	 * @param int           $order_id          Order ID.
	 * @param string        $notification_type Type slug.
	 * @param string        $reason            Reason code.
	 * @param string        $message           Human message.
	 * @param WC_Order|null $order             Optional order.
	 * @param int|null      $customer_id       Optional customer ID.
	 * @return void
	 */
	private static function log_order_level_skip( $order_id, $notification_type, $reason, $message, $order = null, $customer_id = null ) {
		$order_number = $order instanceof WC_Order ? $order->get_order_number() : (string) $order_id;
		$payload      = array(
			'orderId'     => (string) $order_id,
			'orderNumber' => (string) $order_number,
		);

		if ( null !== $customer_id ) {
			$payload['customerId'] = (string) $customer_id;
		}

		self::write_log(
			array(
				'device_id'         => null,
				'push_token'        => null,
				'notification_type' => $notification_type,
				'title'             => self::get_order_notification_title(),
				'message'           => mbmc_get_order_notification_message( $notification_type, $order_number ),
				'payload'           => wp_json_encode( $payload ),
				'provider'          => mbmc_get_push_provider(),
				'status'            => 'skipped',
				'provider_response' => wp_json_encode(
					array(
						'reason'  => $reason,
						'message' => $message,
					)
				),
			)
		);
	}

	/**
	 * Batch summary for order dispatch.
	 *
	 * @param int $sent    Sent count.
	 * @param int $failed  Failed count.
	 * @param int $skipped Skipped count.
	 * @return array<string, mixed>
	 */
	private static function build_batch_summary( $sent, $failed, $skipped ) {
		return array(
			'sent'    => $sent,
			'failed'  => $failed,
			'skipped' => $skipped,
			'total'   => $sent + $failed + $skipped,
		);
	}

	/**
	 * Resolve push token from device ID and/or direct token.
	 *
	 * @param string $device_id  Device id.
	 * @param string $push_token Direct token.
	 * @return array{token:string,device_id:?string}|WP_Error
	 */
	private static function resolve_push_token( $device_id, $push_token ) {
		if ( '' !== $push_token ) {
			return array(
				'token'     => $push_token,
				'device_id' => '' !== $device_id ? $device_id : null,
			);
		}

		if ( '' === $device_id ) {
			return new WP_Error(
				'mbmc_missing_target',
				__( 'deviceId or pushToken is required.', 'mad-baits-mobile-connector' )
			);
		}

		$row = MBMC_DB::get_device_by_device_id( $device_id );

		if ( ! $row || empty( $row->push_token ) ) {
			return new WP_Error(
				'mbmc_device_not_found',
				__( 'Device not found or has no push token registered.', 'mad-baits-mobile-connector' )
			);
		}

		return array(
			'token'     => (string) $row->push_token,
			'device_id' => $device_id,
		);
	}

	/**
	 * Persist notification log row.
	 *
	 * @param array<string, mixed> $row Log data.
	 * @return int|null
	 */
	private static function write_log( array $row ) {
		$id = MBMC_DB::insert_notification_log( $row );

		return false !== $id ? (int) $id : null;
	}

	/**
	 * Build REST/admin response envelope.
	 *
	 * @param bool        $success       Whether send succeeded.
	 * @param string      $status        Log status.
	 * @param string      $message       Human message.
	 * @param int|null    $log_id        Log row id.
	 * @param string|null $provider      Provider id.
	 * @return array<string, mixed>
	 */
	private static function build_response( $success, $status, $message, $log_id, $provider ) {
		return array(
			'ok'          => (bool) $success,
			'success'     => (bool) $success,
			'status'      => $status,
			'message'     => $message,
			'logId'       => null !== $log_id ? (string) $log_id : null,
			'provider'    => $provider,
			'source'      => 'wordpress',
			'generatedAt' => wp_date( 'c' ),
		);
	}

	/**
	 * Send loyalty / achievement push notifications to a user.
	 *
	 * @param int    $user_id User id.
	 * @param string $type    Notification type slug.
	 * @param string $title   Title.
	 * @param string $message Body.
	 * @param array  $data    Extra payload.
	 * @return array<string, mixed>
	 */
	public static function dispatch_loyalty_notification( $user_id, $type, $title, $message, $data = array() ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! mbmc_is_push_sending_enabled() ) {
			return self::build_batch_summary( 0, 0, 1 );
		}

		$devices = MBMC_DB::get_devices_by_customer_id( $user_id, mbmc_get_push_max_batch() );
		if ( empty( $devices ) ) {
			return self::build_batch_summary( 0, 0, 1 );
		}

		$payload = array_merge(
			array(
				'type'   => sanitize_key( (string) $type ),
				'userId' => (string) $user_id,
			),
			is_array( $data ) ? $data : array()
		);

		$sent = 0;
		foreach ( $devices as $device ) {
			$result = self::send_push_to_device(
				array(
					'type'       => sanitize_key( (string) $type ),
					'title'      => $title,
					'message'    => $message,
					'data'       => $payload,
					'device_id'  => $device->device_id,
					'push_token' => $device->push_token,
				)
			);
			if ( ! empty( $result['success'] ) ) {
				++$sent;
			}
		}

		return self::build_batch_summary( $sent, count( $devices ), 0 );
	}
}
