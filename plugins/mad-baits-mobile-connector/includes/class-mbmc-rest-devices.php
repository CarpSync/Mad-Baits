<?php
/**
 * REST API: device registration and notification preferences.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Devices
 */
class MBMC_REST_Devices {

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
		$permission = 'mbmc_app_token_permission_callback';

		register_rest_route(
			self::REST_NAMESPACE,
			'/device/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_device' ),
				'permission_callback' => $permission,
				'args'                => $this->get_register_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/push/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_device' ),
				'permission_callback' => $permission,
				'args'                => $this->get_register_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/device/unregister',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unregister_device' ),
				'permission_callback' => $permission,
				'args'                => $this->get_device_id_args(),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/device/preferences',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_preferences' ),
				'permission_callback' => $permission,
				'args'                => $this->get_preferences_args(),
			)
		);
	}

	/**
	 * POST /device/register
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_device( WP_REST_Request $request ) {
		$device_id  = mbmc_sanitize_device_id( $request->get_param( 'deviceId' ) );
		$push_token = mbmc_sanitize_push_token( $request->get_param( 'pushToken' ) );
		$platform   = mbmc_sanitize_platform( $request->get_param( 'platform' ) );

		if ( '' === $device_id ) {
			return new WP_Error(
				'mbmc_invalid_device',
				__( 'deviceId is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $push_token ) {
			return new WP_Error(
				'mbmc_invalid_token',
				__( 'pushToken is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $platform ) {
			return new WP_Error(
				'mbmc_invalid_platform',
				__( 'platform must be android or ios.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$preferences = mbmc_sanitize_notification_preferences( $request->get_param( 'preferences' ) );
		$auth        = mbmc_get_authenticated_user_from_request( $request );
		$user_id     = $auth ? (int) $auth['user_id'] : null;
		$customer_id = $auth ? (int) $auth['customer_id'] : null;

		if ( $user_id && ! $customer_id ) {
			$customer_id = $user_id;
		}

		$result = MBMC_DB::upsert_device(
			array(
				'device_id'                => $device_id,
				'push_token'                 => $push_token,
				'platform'                 => $platform,
				'app_version'              => mbmc_sanitize_app_version( (string) $request->get_param( 'appVersion' ) ),
				'notification_preferences'   => mbmc_encode_preferences( $preferences ),
				'user_id'                  => $user_id,
				'customer_id'              => $customer_id,
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'mbmc_register_failed',
				__( 'Could not register device.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		$row = MBMC_DB::get_device_by_device_id( $device_id );

		return rest_ensure_response(
			array(
				'ok'          => true,
				'deviceId'    => $device_id,
				'platform'    => $platform,
				'registered'  => true,
				'createdAt'   => $row ? $row->created_at : null,
				'updatedAt'   => $row ? $row->updated_at : null,
				'lastSeenAt'  => $row ? $row->last_seen_at : null,
				'authLinked'  => (bool) $user_id,
				'customerId'  => $customer_id ? (string) $customer_id : null,
				'userId'      => $user_id ? (string) $user_id : null,
				'source'      => 'wordpress',
				'generatedAt' => wp_date( 'c' ),
			)
		);
	}

	/**
	 * POST /device/unregister
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function unregister_device( WP_REST_Request $request ) {
		$device_id  = mbmc_sanitize_device_id( $request->get_param( 'deviceId' ) );
		$push_token = mbmc_sanitize_push_token( (string) $request->get_param( 'pushToken' ) );

		if ( '' === $device_id ) {
			return new WP_Error(
				'mbmc_invalid_device',
				__( 'deviceId is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$row = MBMC_DB::get_device_by_device_id( $device_id );

		if ( ! $row ) {
			return rest_ensure_response(
				array(
					'ok'          => true,
					'deviceId'    => $device_id,
					'removed'     => false,
					'message'     => __( 'Device was not registered.', 'mad-baits-mobile-connector' ),
					'source'      => 'wordpress',
					'generatedAt' => wp_date( 'c' ),
				)
			);
		}

		if ( '' !== $push_token && ! mbmc_verify_device_token( $row, $push_token ) ) {
			return new WP_Error(
				'mbmc_token_mismatch',
				__( 'pushToken does not match this device.', 'mad-baits-mobile-connector' ),
				array( 'status' => 403 )
			);
		}

		$removed = MBMC_DB::delete_device( $device_id );

		return rest_ensure_response(
			array(
				'ok'          => true,
				'deviceId'    => $device_id,
				'removed'     => $removed,
				'source'      => 'wordpress',
				'generatedAt' => wp_date( 'c' ),
			)
		);
	}

	/**
	 * POST /device/preferences
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_preferences( WP_REST_Request $request ) {
		$device_id  = mbmc_sanitize_device_id( $request->get_param( 'deviceId' ) );
		$push_token = mbmc_sanitize_push_token( (string) $request->get_param( 'pushToken' ) );
		$preferences = mbmc_sanitize_notification_preferences( $request->get_param( 'preferences' ) );

		if ( '' === $device_id ) {
			return new WP_Error(
				'mbmc_invalid_device',
				__( 'deviceId is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $preferences ) ) {
			return new WP_Error(
				'mbmc_invalid_preferences',
				__( 'preferences object is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$row = MBMC_DB::get_device_by_device_id( $device_id );

		if ( ! $row ) {
			return new WP_Error(
				'mbmc_device_not_found',
				__( 'Device not registered. Call /device/register first.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		if ( '' !== $push_token && ! mbmc_verify_device_token( $row, $push_token ) ) {
			return new WP_Error(
				'mbmc_token_mismatch',
				__( 'pushToken does not match this device.', 'mad-baits-mobile-connector' ),
				array( 'status' => 403 )
			);
		}

		$encoded = mbmc_encode_preferences( $preferences );
		$updated = MBMC_DB::update_preferences( $device_id, $encoded );

		if ( ! $updated ) {
			return new WP_Error(
				'mbmc_preferences_failed',
				__( 'Could not update preferences.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		$row = MBMC_DB::get_device_by_device_id( $device_id );

		return rest_ensure_response(
			array(
				'ok'           => true,
				'deviceId'     => $device_id,
				'preferences'  => $preferences,
				'updatedAt'    => $row ? $row->updated_at : null,
				'source'       => 'wordpress',
				'generatedAt'  => wp_date( 'c' ),
			)
		);
	}

	/**
	 * Register endpoint args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_register_args() {
		return array_merge(
			$this->get_device_id_args(),
			array(
				'pushToken'  => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'mbmc_sanitize_push_token',
				),
				'platform'   => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'mbmc_sanitize_platform',
				),
				'appVersion' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'mbmc_sanitize_app_version',
				),
				'preferences' => array(
					'required' => false,
					'type'     => 'object',
				),
				'customerId' => array(
					'required'          => false,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			)
		);
	}

	/**
	 * Shared deviceId arg.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_device_id_args() {
		return array(
			'deviceId' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_device_id',
			),
			'pushToken' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_push_token',
			),
		);
	}

	/**
	 * Preferences endpoint args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_preferences_args() {
		return array_merge(
			$this->get_device_id_args(),
			array(
				'preferences' => array(
					'required' => true,
					'type'     => 'object',
				),
			)
		);
	}
}
