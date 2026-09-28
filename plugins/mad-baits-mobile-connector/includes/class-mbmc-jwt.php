<?php
/**
 * JWT issue, validation, and refresh token storage.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_JWT
 */
class MBMC_JWT {

	/**
	 * Access token lifetime in seconds.
	 */
	const ACCESS_TTL = 900;

	/**
	 * Refresh token lifetime in seconds.
	 */
	const REFRESH_TTL = 2592000;

	/**
	 * User meta key for hashed refresh tokens.
	 */
	const REFRESH_META_KEY = 'mbmc_refresh_tokens';

	/**
	 * Get signing secret derived from WordPress salts.
	 *
	 * @return string
	 */
	public static function get_secret() {
		$app_token = (string) get_option( mbmc_get_app_token_option_key(), '' );

		return hash( 'sha256', wp_salt( 'auth' ) . '|mbmc_jwt|' . $app_token );
	}

	/**
	 * Create a signed access token.
	 *
	 * @param int $user_id     WordPress user ID.
	 * @param int $customer_id WooCommerce customer ID.
	 * @return string
	 */
	public static function create_access_token( $user_id, $customer_id ) {
		$now = time();

		return self::encode(
			array(
				'sub'  => (int) $user_id,
				'cid'  => (int) $customer_id,
				'type' => 'access',
				'iat'  => $now,
				'exp'  => $now + self::ACCESS_TTL,
				'jti'  => wp_generate_uuid4(),
			)
		);
	}

	/**
	 * Create a signed refresh token and persist its hash.
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param string $device_id Optional device id from login.
	 * @return array{token:string,expiresAt:string,jti:string}
	 */
	public static function create_refresh_token( $user_id, $device_id = '' ) {
		$now  = time();
		$jti  = wp_generate_uuid4();
		$exp  = $now + self::REFRESH_TTL;
		$token = self::encode(
			array(
				'sub'  => (int) $user_id,
				'type' => 'refresh',
				'iat'  => $now,
				'exp'  => $exp,
				'jti'  => $jti,
			)
		);

		self::store_refresh_token( (int) $user_id, $jti, $token, $exp, $device_id );

		return array(
			'token'     => $token,
			'expiresAt' => gmdate( 'c', $exp ),
			'jti'       => $jti,
		);
	}

	/**
	 * Validate Bearer access token from request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{user_id:int,customer_id:int,user:WP_User}|null
	 */
	public static function get_authenticated_user_from_request( WP_REST_Request $request ) {
		$auth = (string) $request->get_header( 'Authorization' );

		if ( '' === $auth || 0 !== stripos( $auth, 'Bearer ' ) ) {
			return null;
		}

		$token   = trim( substr( $auth, 7 ) );
		$payload = self::decode( $token );

		if ( ! $payload || 'access' !== ( $payload['type'] ?? '' ) ) {
			return null;
		}

		$user_id = absint( $payload['sub'] ?? 0 );

		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || ! mbmc_user_is_allowed_customer( $user ) ) {
			return null;
		}

		$customer_id = absint( $payload['cid'] ?? $user_id );

		return array(
			'user_id'     => $user_id,
			'customer_id' => $customer_id,
			'user'        => $user,
		);
	}

	/**
	 * Refresh session using a valid refresh token.
	 *
	 * @param string $refresh_token Raw refresh token.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function refresh_session( $refresh_token ) {
		$payload = self::decode( $refresh_token );

		if ( ! $payload || 'refresh' !== ( $payload['type'] ?? '' ) ) {
			return new WP_Error(
				'mbmc_invalid_refresh',
				__( 'Invalid or expired refresh token.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$user_id = absint( $payload['sub'] ?? 0 );
		$jti     = sanitize_text_field( (string) ( $payload['jti'] ?? '' ) );

		if ( $user_id <= 0 || '' === $jti || ! self::verify_stored_refresh_token( $user_id, $jti, $refresh_token ) ) {
			return new WP_Error(
				'mbmc_invalid_refresh',
				__( 'Invalid or expired refresh token.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user instanceof WP_User || ! mbmc_user_is_allowed_customer( $user ) ) {
			return new WP_Error(
				'mbmc_invalid_refresh',
				__( 'Invalid or expired refresh token.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		self::revoke_refresh_token( $user_id, $jti );

		$customer_id   = (int) $user_id;
		$access_token  = self::create_access_token( $user_id, $customer_id );
		$refresh       = self::create_refresh_token( $user_id );
		$expires_at    = gmdate( 'c', time() + self::ACCESS_TTL );

		return array(
			'accessToken'  => $access_token,
			'refreshToken' => $refresh['token'],
			'expiresAt'    => $expires_at,
			'tokenType'    => 'Bearer',
			'customerId'   => (string) $customer_id,
			'user'         => mbmc_format_auth_user( $user, $customer_id ),
		);
	}

	/**
	 * Revoke refresh token from logout request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public static function revoke_from_request( WP_REST_Request $request ) {
		$refresh_token = sanitize_text_field( (string) $request->get_param( 'refreshToken' ) );

		if ( '' !== $refresh_token ) {
			$payload = self::decode( $refresh_token );

			if ( $payload && 'refresh' === ( $payload['type'] ?? '' ) ) {
				self::revoke_refresh_token(
					absint( $payload['sub'] ?? 0 ),
					sanitize_text_field( (string) ( $payload['jti'] ?? '' ) )
				);
			}

			return;
		}

		$auth = self::get_authenticated_user_from_request( $request );

		if ( $auth ) {
			self::revoke_all_refresh_tokens( (int) $auth['user_id'] );
		}
	}

	/**
	 * Encode JWT payload.
	 *
	 * @param array<string, mixed> $payload Claims.
	 * @return string
	 */
	public static function encode( array $payload ) {
		$header  = self::base64url_encode( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
		$body    = self::base64url_encode( wp_json_encode( $payload ) );
		$sig     = self::base64url_encode( hash_hmac( 'sha256', $header . '.' . $body, self::get_secret(), true ) );

		return $header . '.' . $body . '.' . $sig;
	}

	/**
	 * Decode and verify JWT.
	 *
	 * @param string $token Raw token.
	 * @return array<string, mixed>|null
	 */
	public static function decode( $token ) {
		$token = trim( (string) $token );
		$parts = explode( '.', $token );

		if ( 3 !== count( $parts ) ) {
			return null;
		}

		list( $header64, $payload64, $sig64 ) = $parts;
		$expected = self::base64url_encode(
			hash_hmac( 'sha256', $header64 . '.' . $payload64, self::get_secret(), true )
		);

		if ( ! hash_equals( $expected, $sig64 ) ) {
			return null;
		}

		$payload = json_decode( self::base64url_decode( $payload64 ), true );

		if ( ! is_array( $payload ) ) {
			return null;
		}

		if ( ! empty( $payload['exp'] ) && time() >= (int) $payload['exp'] ) {
			return null;
		}

		return $payload;
	}

	/**
	 * Store refresh token hash in user meta.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $jti         Token ID.
	 * @param string $token       Raw token.
	 * @param int    $expires     Expiry timestamp.
	 * @param string $device_id   Device id.
	 * @return void
	 */
	private static function store_refresh_token( $user_id, $jti, $token, $expires, $device_id = '' ) {
		$tokens = self::get_refresh_tokens( $user_id );
		$tokens = self::prune_refresh_tokens( $tokens );

		$tokens[ $jti ] = array(
			'hash'      => self::hash_refresh_token( $token ),
			'expires'   => (int) $expires,
			'device_id' => mbmc_sanitize_device_id( $device_id ) ?: null,
			'created'   => time(),
		);

		update_user_meta( $user_id, self::REFRESH_META_KEY, $tokens );
	}

	/**
	 * Verify refresh token against stored hash.
	 *
	 * @param int    $user_id User ID.
	 * @param string $jti     Token ID.
	 * @param string $token   Raw token.
	 * @return bool
	 */
	private static function verify_stored_refresh_token( $user_id, $jti, $token ) {
		$tokens = self::get_refresh_tokens( $user_id );

		if ( empty( $tokens[ $jti ] ) ) {
			return false;
		}

		$row = $tokens[ $jti ];

		if ( time() >= (int) ( $row['expires'] ?? 0 ) ) {
			self::revoke_refresh_token( $user_id, $jti );

			return false;
		}

		return hash_equals( (string) $row['hash'], self::hash_refresh_token( $token ) );
	}

	/**
	 * Revoke one refresh token.
	 *
	 * @param int    $user_id User ID.
	 * @param string $jti     Token ID.
	 * @return void
	 */
	public static function revoke_refresh_token( $user_id, $jti ) {
		$user_id = absint( $user_id );
		$jti     = sanitize_text_field( (string) $jti );

		if ( $user_id <= 0 || '' === $jti ) {
			return;
		}

		$tokens = self::get_refresh_tokens( $user_id );

		if ( isset( $tokens[ $jti ] ) ) {
			unset( $tokens[ $jti ] );
			update_user_meta( $user_id, self::REFRESH_META_KEY, $tokens );
		}
	}

	/**
	 * Revoke all refresh tokens for a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function revoke_all_refresh_tokens( $user_id ) {
		delete_user_meta( absint( $user_id ), self::REFRESH_META_KEY );
	}

	/**
	 * Get stored refresh tokens for user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_refresh_tokens( $user_id ) {
		$tokens = get_user_meta( absint( $user_id ), self::REFRESH_META_KEY, true );

		return is_array( $tokens ) ? $tokens : array();
	}

	/**
	 * Remove expired refresh token rows.
	 *
	 * @param array<string, array<string, mixed>> $tokens Token map.
	 * @return array<string, array<string, mixed>>
	 */
	private static function prune_refresh_tokens( array $tokens ) {
		$now = time();

		foreach ( $tokens as $jti => $row ) {
			if ( $now >= (int) ( $row['expires'] ?? 0 ) ) {
				unset( $tokens[ $jti ] );
			}
		}

		return $tokens;
	}

	/**
	 * Hash refresh token for storage.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	private static function hash_refresh_token( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) . '|mbmc_refresh' );
	}

	/**
	 * Base64 URL encode.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64 URL decode.
	 *
	 * @param string $value Encoded value.
	 * @return string
	 */
	private static function base64url_decode( $value ) {
		$remainder = strlen( $value ) % 4;

		if ( $remainder ) {
			$value .= str_repeat( '=', 4 - $remainder );
		}

		return base64_decode( strtr( $value, '-_', '+/' ) );
	}
}
