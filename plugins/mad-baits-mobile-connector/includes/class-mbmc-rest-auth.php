<?php
/**
 * REST API: JWT authentication.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Auth
 */
class MBMC_REST_Auth {

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
			'/auth/login',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Customer email address or WordPress username.',
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/logout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'refreshToken' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'refreshToken' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/password-reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'password_reset' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/register',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => array(
					'email'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password'    => array(
						'required' => true,
						'type'     => 'string',
					),
					'displayName' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'deviceId'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'mbmc_sanitize_device_id',
					),
				),
			)
		);
	}

	/**
	 * Build login/register auth payload.
	 *
	 * @param WP_User $user      Authenticated user.
	 * @param string  $device_id Optional device ID.
	 * @return array<string, mixed>
	 */
	public static function build_auth_response_for_user( WP_User $user, $device_id = '' ) {
		return ( new self() )->build_auth_response( $user, $device_id );
	}

	/**
	 * Build login/register auth payload.
	 *
	 * @param WP_User     $user        Authenticated user.
	 * @param string      $device_id   Optional device ID.
	 * @return array<string, mixed>
	 */
	private function build_auth_response( WP_User $user, $device_id = '' ) {
		$customer_id  = (int) $user->ID;
		$access_token = MBMC_JWT::create_access_token( $user->ID, $customer_id );
		$refresh      = MBMC_JWT::create_refresh_token( $user->ID, $device_id );
		$device_id    = mbmc_sanitize_device_id( (string) $device_id );

		if ( '' !== $device_id ) {
			MBMC_DB::link_device_to_user( $device_id, $user->ID, $customer_id );
		}

		return array(
			'ok'           => true,
			'accessToken'  => $access_token,
			'refreshToken' => $refresh['token'],
			'expiresAt'    => gmdate( 'c', time() + MBMC_JWT::ACCESS_TTL ),
			'tokenType'    => 'Bearer',
			'customerId'   => (string) $customer_id,
			'user'         => mbmc_format_auth_user( $user, $customer_id ),
			'source'       => 'wordpress',
			'generatedAt'  => wp_date( 'c' ),
		);
	}

	/**
	 * POST /auth/login
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function login( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_auth_login', 20, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many login attempts. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$login_identifier = trim( (string) $request->get_param( 'email' ) );
		$password         = (string) $request->get_param( 'password' );

		if ( '' === $login_identifier || '' === $password ) {
			return mbmc_invalid_login_error();
		}

		$user = MBMC_WooCommerce::authenticate_customer( $login_identifier, $password );

		if ( is_wp_error( $user ) ) {
			if ( 'mbmc_account_not_permitted' === $user->get_error_code() ) {
				return $user;
			}

			return mbmc_invalid_login_error();
		}

		if ( ! $user instanceof WP_User ) {
			return mbmc_invalid_login_error();
		}

		$customer_id  = (int) $user->ID;
		$device_id    = mbmc_sanitize_device_id( (string) $request->get_param( 'deviceId' ) );

		return rest_ensure_response(
			$this->build_auth_response( $user, $device_id )
		);
	}

	/**
	 * POST /auth/logout
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function logout( WP_REST_Request $request ) {
		MBMC_JWT::revoke_from_request( $request );

		return rest_ensure_response(
			array(
				'ok'          => true,
				'loggedOut'   => true,
				'source'      => 'wordpress',
				'generatedAt' => wp_date( 'c' ),
			)
		);
	}

	/**
	 * POST /auth/refresh
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refresh( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_auth_refresh', 60, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many refresh attempts. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$result = MBMC_JWT::refresh_session( sanitize_text_field( (string) $request->get_param( 'refreshToken' ) ) );

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
	 * POST /auth/password-reset — placeholder for future WordPress integration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error
	 */
	public function password_reset( WP_REST_Request $request ) {
		unset( $request );

		return new WP_Error(
			'mbmc_not_implemented',
			__(
				'Password reset via the mobile app is not yet available. Use madbaits.com to reset your password until this endpoint is implemented with WordPress core password reset.',
				'mad-baits-mobile-connector'
			),
			array( 'status' => 501 )
		);
	}

	/**
	 * POST /auth/register — create WooCommerce customer and return session tokens.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_auth_register', 10, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many registration attempts. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$email        = sanitize_email( (string) $request->get_param( 'email' ) );
		$password     = (string) $request->get_param( 'password' );
		$display_name = sanitize_text_field( (string) $request->get_param( 'displayName' ) );
		$device_id    = mbmc_sanitize_device_id( (string) $request->get_param( 'deviceId' ) );

		$user = MBMC_WooCommerce::register_customer( $email, $password, $display_name );

		if ( is_wp_error( $user ) ) {
			$status = (int) ( $user->get_error_data()['status'] ?? 400 );
			return new WP_Error(
				$user->get_error_code(),
				$user->get_error_message(),
				array( 'status' => $status > 0 ? $status : 400 )
			);
		}

		return rest_ensure_response(
			$this->build_auth_response( $user, $device_id )
		);
	}
}
