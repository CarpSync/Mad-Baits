<?php
/**
 * Shared helpers for Mad Baits Mobile Connector.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Usernames allowed to see Mobile App wp-admin screens.
 *
 * @return string[]
 */
function mbmc_get_allowed_admin_usernames() {
	$defaults = array( 'reece' );

	/**
	 * Filter which WordPress user logins may access Mobile App admin screens.
	 *
	 * @param string[] $usernames Lowercase login names.
	 */
	return apply_filters( 'mbmc_allowed_admin_usernames', $defaults );
}

/**
 * Whether the current (or given) user may access Mobile App wp-admin screens.
 *
 * @param int|null $user_id Optional user ID.
 * @return bool
 */
function mbmc_user_can_manage_mobile_app( $user_id = null ) {
	$user = null;

	if ( null !== $user_id ) {
		$user = get_userdata( (int) $user_id );
	} else {
		$user = wp_get_current_user();
	}

	if ( ! $user || ! $user->exists() ) {
		return false;
	}

	if ( ! user_can( $user, 'manage_options' ) ) {
		return false;
	}

	$login   = strtolower( $user->user_login );
	$allowed = array_map( 'strtolower', mbmc_get_allowed_admin_usernames() );

	return in_array( $login, $allowed, true );
}

/**
 * Event type definitions (slug => admin label).
 *
 * @return array<string, string>
 */
function mbmc_get_event_types() {
	return array(
		'bbq'             => __( 'BBQ', 'mad-baits-mobile-connector' ),
		'open_day'        => __( 'Open Day', 'mad-baits-mobile-connector' ),
		'product_launch'  => __( 'Product Launch', 'mad-baits-mobile-connector' ),
		'social'          => __( 'Social', 'mad-baits-mobile-connector' ),
		'team_event'      => __( 'Team Event', 'mad-baits-mobile-connector' ),
		'fishing_event'   => __( 'Fishing Event', 'mad-baits-mobile-connector' ),
		'app_event'       => __( 'App Event', 'mad-baits-mobile-connector' ),
	);
}

/**
 * Map stored slug to API-facing event type label.
 *
 * @param string $slug Event type slug.
 * @return string
 */
function mbmc_format_event_type_for_api( $slug ) {
	$types = mbmc_get_event_types();
	$slug  = sanitize_key( $slug );

	if ( isset( $types[ $slug ] ) ) {
		return $types[ $slug ];
	}

	return __( 'App Event', 'mad-baits-mobile-connector' );
}

/**
 * Sanitize ISO-like datetime for storage.
 *
 * @param string $value Raw datetime string.
 * @return string Empty string or sanitized value.
 */
function mbmc_sanitize_datetime( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( '' === $value ) {
		return '';
	}

	// Accept HTML datetime-local (Y-m-d\TH:i) or full ISO strings.
	$timestamp = strtotime( $value );

	if ( false === $timestamp ) {
		return '';
	}

	return gmdate( 'c', $timestamp );
}

/**
 * Format stored datetime for API (ISO 8601 with timezone).
 *
 * @param string $stored Stored datetime.
 * @return string|null
 */
function mbmc_format_datetime_for_api( $stored ) {
	$stored = is_string( $stored ) ? trim( $stored ) : '';

	if ( '' === $stored ) {
		return null;
	}

	$timestamp = strtotime( $stored );

	if ( false === $timestamp ) {
		return null;
	}

	return wp_date( 'c', $timestamp );
}

/**
 * Sanitize URL for output.
 *
 * @param string $url Raw URL.
 * @return string
 */
function mbmc_sanitize_url_field( $url ) {
	return esc_url_raw( trim( (string) $url ) );
}

/**
 * Upload a base64-encoded image to the WordPress uploads directory.
 *
 * @param string $base64 Base64 payload without data-uri prefix.
 * @param string $mime_type MIME type.
 * @param string $filename_prefix Filename prefix.
 * @return string|false Public URL on success.
 */
function mbmc_upload_base64_image( $base64, $mime_type = 'image/jpeg', $filename_prefix = 'mbmc-catch' ) {
	$payload = trim( (string) $base64 );
	if ( '' === $payload ) {
		return false;
	}

	if ( false !== strpos( $payload, 'base64,' ) ) {
		$parts   = explode( 'base64,', $payload, 2 );
		$payload = isset( $parts[1] ) ? $parts[1] : '';
	}

	$payload = preg_replace( '/\s+/', '', $payload );
	$binary  = base64_decode( $payload, true );
	if ( false === $binary || strlen( $binary ) < 32 ) {
		return false;
	}

	$allowed = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);
	$mime_type = strtolower( trim( (string) $mime_type ) );
	$extension = isset( $allowed[ $mime_type ] ) ? $allowed[ $mime_type ] : 'jpg';

	if ( strlen( $binary ) > 8 * 1024 * 1024 ) {
		return false;
	}

	$filename = sanitize_file_name( $filename_prefix . '-' . wp_generate_uuid4() . '.' . $extension );
	$upload   = wp_upload_bits( $filename, null, $binary );

	if ( ! empty( $upload['error'] ) || empty( $upload['url'] ) ) {
		return false;
	}

	return esc_url_raw( $upload['url'] );
}

/**
 * Get event meta keys used by the plugin.
 *
 * @return string[]
 */
function mbmc_get_event_meta_keys() {
	return array(
		'event_start_date',
		'event_end_date',
		'event_location',
		'event_type',
		'event_image_url',
		'event_cta_label',
		'event_cta_url',
		'event_is_featured',
	);
}

/**
 * Allowed mobile device platforms.
 *
 * @return string[]
 */
function mbmc_get_allowed_platforms() {
	return array( 'android', 'ios' );
}

/**
 * Notification preference keys accepted from the mobile app.
 *
 * @return string[]
 */
function mbmc_get_notification_preference_keys() {
	return array(
		'orderReceived',
		'orderProcessing',
		'orderDispatched',
		'orderCompleted',
		'orderCancelled',
		'orderRefunded',
		'orderFailed',
		'trackingAdded',
		'orderDispatchedTracking',
		'orderCompletedCancelled',
		'biteWindowAlerts',
		'weatherChanges',
		'sessionReminders',
		'catchReminders',
		'appOnlyOffers',
		'productLaunches',
		'restockAlerts',
		'betaUpdates',
		'appUpdates',
		'accountAlerts',
	);
}

/**
 * Sanitize notification preferences object from JSON.
 *
 * @param mixed $preferences Raw preferences.
 * @return array<string, bool>
 */
function mbmc_sanitize_notification_preferences( $preferences ) {
	$allowed = mbmc_get_notification_preference_keys();
	$clean   = array();

	if ( ! is_array( $preferences ) ) {
		return $clean;
	}

	foreach ( $allowed as $key ) {
		if ( array_key_exists( $key, $preferences ) ) {
			$clean[ $key ] = (bool) $preferences[ $key ];
		}
	}

	return $clean;
}

/**
 * Encode preferences for DB storage.
 *
 * @param array<string, bool> $preferences Sanitized preferences.
 * @return string|null
 */
function mbmc_encode_preferences( array $preferences ) {
	if ( empty( $preferences ) ) {
		return null;
	}

	return wp_json_encode( $preferences );
}

/**
 * Sanitize device_id from client.
 *
 * @param string $device_id Raw device id.
 * @return string
 */
function mbmc_sanitize_device_id( $device_id ) {
	$device_id = sanitize_text_field( (string) $device_id );

	return substr( $device_id, 0, 64 );
}

/**
 * Sanitize push token.
 *
 * @param string $token Raw token.
 * @return string
 */
function mbmc_sanitize_push_token( $token ) {
	$token = sanitize_text_field( (string) $token );

	return substr( $token, 0, 512 );
}

/**
 * Sanitize platform slug.
 *
 * @param string $platform Raw platform.
 * @return string Empty if invalid.
 */
function mbmc_sanitize_platform( $platform ) {
	$platform = sanitize_key( (string) $platform );

	if ( in_array( $platform, mbmc_get_allowed_platforms(), true ) ) {
		return $platform;
	}

	return '';
}

/**
 * Sanitize app version string.
 *
 * @param string $version Raw version.
 * @return string|null
 */
function mbmc_sanitize_app_version( $version ) {
	$version = sanitize_text_field( (string) $version );

	if ( '' === $version ) {
		return null;
	}

	return substr( $version, 0, 32 );
}

/**
 * Best-effort client IP for rate limiting.
 *
 * @return string
 */
function mbmc_get_client_ip() {
	$ip = '';

	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$ip    = trim( $parts[0] );
	} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	return substr( $ip, 0, 45 );
}

/**
 * Basic transient rate limiter per IP + action.
 *
 * @param string $action Action key.
 * @param int    $limit  Max requests.
 * @param int    $window Window seconds.
 * @return bool True if allowed.
 */
function mbmc_rate_limit_check( $action, $limit = 120, $window = HOUR_IN_SECONDS ) {
	$ip  = mbmc_get_client_ip();
	$key = 'mbmc_rl_' . md5( $action . '|' . $ip );
	$count = (int) get_transient( $key );

	if ( $count >= $limit ) {
		return false;
	}

	set_transient( $key, $count + 1, $window );

	return true;
}

/**
 * Option key for the shared mobile app token.
 */
function mbmc_get_app_token_option_key() {
	return 'mbmc_app_token';
}

/**
 * Whether the Mad Baits App Token is configured.
 *
 * @return bool
 */
function mbmc_is_app_token_configured() {
	return '' !== mbmc_get_app_token();
}

/**
 * Get configured app token (empty when unset).
 *
 * @return string
 */
function mbmc_get_app_token() {
	return (string) get_option( mbmc_get_app_token_option_key(), '' );
}

/**
 * Verify X-Madbaits-App-Token header against configured option.
 *
 * @param WP_REST_Request $request Request.
 * @return bool
 */
function mbmc_verify_app_token( WP_REST_Request $request ) {
	$expected = mbmc_get_app_token();

	if ( '' === $expected ) {
		return false;
	}

	$provided = (string) $request->get_header( 'X-Madbaits-App-Token' );

	return hash_equals( $expected, $provided );
}

/**
 * JWT auth — returns WP user ID when Bearer access token is valid.
 *
 * @param WP_REST_Request $request Request.
 * @return int|null WordPress user ID or null.
 */
function mbmc_get_user_id_from_jwt( WP_REST_Request $request ) {
	$auth = MBMC_JWT::get_authenticated_user_from_request( $request );

	return $auth ? (int) $auth['user_id'] : null;
}

/**
 * Get authenticated user payload from JWT request.
 *
 * @param WP_REST_Request $request Request.
 * @return array{user_id:int,customer_id:int,user:WP_User}|null
 */
function mbmc_get_authenticated_user_from_request( WP_REST_Request $request ) {
	return MBMC_JWT::get_authenticated_user_from_request( $request );
}

/**
 * Whether user may authenticate via the mobile app.
 *
 * @param WP_User $user WordPress user.
 * @return bool
 */
function mbmc_user_is_allowed_customer( WP_User $user ) {
	if ( ! $user->exists() ) {
		return false;
	}

	return null === mbmc_get_customer_auth_denial( $user );
}

/**
 * Return an auth denial error for users who must not sign in via the mobile app.
 *
 * @param WP_User $user WordPress user.
 * @return WP_Error|null
 */
function mbmc_get_customer_auth_denial( WP_User $user ) {
	$blocked_roles = apply_filters(
		'mbmc_blocked_auth_roles',
		array( 'administrator', 'shop_manager', 'editor', 'author' )
	);

	foreach ( $blocked_roles as $role ) {
		if ( in_array( $role, (array) $user->roles, true ) ) {
			return new WP_Error(
				'mbmc_account_not_permitted',
				__(
					'Shop staff and admin WordPress accounts cannot sign in here. Use your customer email and password from My Account on madbaits.com, or create a free customer account in the app.',
					'mad-baits-mobile-connector'
				),
				array( 'status' => 403 )
			);
		}
	}

	if ( ! user_can( $user, 'read' ) ) {
		return new WP_Error(
			'mbmc_invalid_login',
			__( 'Invalid email or password.', 'mad-baits-mobile-connector' ),
			array( 'status' => 401 )
		);
	}

	return null;
}

/**
 * Resolve a WooCommerce customer by email (case-insensitive) or username.
 *
 * @param string $identifier Email address or WordPress username.
 * @return WP_User|null
 */
function mbmc_resolve_user_by_login_identifier( $identifier ) {
	$identifier = trim( (string) $identifier );

	if ( '' === $identifier ) {
		return null;
	}

	if ( is_email( $identifier ) ) {
		$email = sanitize_email( $identifier );
		if ( '' !== $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->users} WHERE LOWER(user_email) = LOWER(%s) LIMIT 1",
				$identifier
			)
		);

		if ( $user_id ) {
			$user = get_user_by( 'id', (int) $user_id );
			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		return null;
	}

	$login = sanitize_user( $identifier, true );
	if ( '' === $login ) {
		return null;
	}

	$user = get_user_by( 'login', $login );

	return $user instanceof WP_User ? $user : null;
}

/**
 * Format WP user for auth API responses.
 *
 * @param WP_User $user        WordPress user.
 * @param int     $customer_id Customer ID.
 * @return array<string, string|null|bool>
 */
function mbmc_format_auth_user( WP_User $user, $customer_id ) {
	return array_merge(
		array(
			'id'          => (string) $user->ID,
			'email'       => sanitize_email( $user->user_email ),
			'firstName'   => sanitize_text_field( (string) get_user_meta( $user->ID, 'first_name', true ) ) ?: null,
			'lastName'    => sanitize_text_field( (string) get_user_meta( $user->ID, 'last_name', true ) ) ?: null,
			'displayName' => sanitize_text_field( $user->display_name ),
			'customerId'  => (string) absint( $customer_id ),
		),
		mbmc_get_user_team_profile( $user )
	);
}

/**
 * Map a WordPress Team Access role slug to the mobile app teamRole slug.
 *
 * @param string $wp_role WordPress role slug.
 * @return string
 */
function mbmc_map_wp_team_role_to_app( $wp_role ) {
	$wp_role = sanitize_key( (string) $wp_role );

	$map = array(
		'mad_team'             => 'mad_team',
		'mad_rnt'              => 'rnt_team',
		'mad_ambassador'       => 'ambassador',
		'mad_tester'           => 'bait_tester',
		'mad_media'            => 'media_team',
		'mad_baits_team'       => 'mad_team',
		'team'                 => 'mad_team',
		'mad_baits_rnt'        => 'rnt_team',
		'rnt'                  => 'rnt_team',
		'mad_baits_ambassador' => 'ambassador',
		'ambassador'           => 'ambassador',
		'mad_baits_tester'     => 'bait_tester',
		'tester'               => 'bait_tester',
		'mad_baits_media'      => 'media_team',
		'mad_baits_media_team' => 'media_team',
		'media_team'           => 'media_team',
		'media'                => 'media_team',
	);

	return isset( $map[ $wp_role ] ) ? (string) $map[ $wp_role ] : 'customer';
}

/**
 * Resolve the primary Mad Baits Team Access role for a user.
 *
 * @param WP_User $user WordPress user.
 * @return string Canonical mad_* role slug or empty string.
 */
function mbmc_get_wp_primary_team_role( WP_User $user ) {
	if ( class_exists( 'MBTA_Roles' ) ) {
		return sanitize_key( (string) MBTA_Roles::get_primary_role( $user ) );
	}

	$priority = array( 'mad_team', 'mad_rnt', 'mad_ambassador', 'mad_tester', 'mad_media' );
	$user_roles = array_map( 'sanitize_key', (array) $user->roles );

	foreach ( $priority as $role ) {
		if ( in_array( $role, $user_roles, true ) ) {
			return $role;
		}
	}

	foreach ( $user_roles as $role ) {
		$mapped = mbmc_map_wp_team_role_to_app( $role );
		if ( 'customer' !== $mapped ) {
			foreach ( $priority as $canonical ) {
				if ( mbmc_map_wp_team_role_to_app( $canonical ) === $mapped ) {
					return $canonical;
				}
			}
		}
	}

	return '';
}

/**
 * Human-readable labels for app team roles.
 *
 * @return array<string, string>
 */
function mbmc_get_app_team_role_labels() {
	return array(
		'customer'    => __( 'Customer', 'mad-baits-mobile-connector' ),
		'mad_team'    => __( 'Mad Baits Team', 'mad-baits-mobile-connector' ),
		'rnt_team'    => __( 'Mad Baits RNT', 'mad-baits-mobile-connector' ),
		'ambassador'  => __( 'Mad Baits Ambassador', 'mad-baits-mobile-connector' ),
		'bait_tester' => __( 'Mad Baits Tester', 'mad-baits-mobile-connector' ),
		'media_team'  => __( 'Mad Baits Media Team', 'mad-baits-mobile-connector' ),
	);
}

/**
 * Resolve Mad Baits Team profile for mobile auth/account responses.
 *
 * @param WP_User $user WordPress user.
 * @return array<string, string|bool|null>
 */
function mbmc_get_user_team_profile( WP_User $user ) {
	$wp_role  = mbmc_get_wp_primary_team_role( $user );
	$app_role = '' === $wp_role ? 'customer' : mbmc_map_wp_team_role_to_app( $wp_role );
	$labels   = mbmc_get_app_team_role_labels();

	return array(
		'teamRole'     => mbmc_sanitize_team_role( $app_role ),
		'teamStatus'   => 'customer' === $app_role ? 'invited' : 'active',
		'teamLabel'    => isset( $labels[ $app_role ] ) ? (string) $labels[ $app_role ] : null,
		'wpTeamRole'   => '' !== $wp_role ? $wp_role : null,
		'isTeamMember' => 'customer' !== $app_role,
	);
}

/**
 * Permission callback requiring app token + valid JWT access token.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function mbmc_jwt_auth_permission_callback( WP_REST_Request $request ) {
	$app_check = mbmc_app_token_permission_callback( $request );

	if ( is_wp_error( $app_check ) ) {
		return $app_check;
	}

	if ( ! mbmc_get_authenticated_user_from_request( $request ) ) {
		return new WP_Error(
			'mbmc_unauthorized',
			__( 'Invalid or missing Bearer access token.', 'mad-baits-mobile-connector' ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Generic invalid login error.
 *
 * @return WP_Error
 */
function mbmc_invalid_login_error() {
	return new WP_Error(
		'mbmc_invalid_login',
		__( 'Invalid email or password.', 'mad-baits-mobile-connector' ),
		array( 'status' => 401 )
	);
}

/**
 * Permission callback for protected mobile app routes (all except GET /events).
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function mbmc_app_token_permission_callback( WP_REST_Request $request ) {
	if ( ! mbmc_is_app_token_configured() ) {
		return new WP_Error(
			'mbmc_token_not_configured',
			__( 'Mad Baits App Token is not configured. Set it under Mobile App → Settings in wp-admin. Only GET /events is available until then.', 'mad-baits-mobile-connector' ),
			array( 'status' => 503 )
		);
	}

	if ( ! mbmc_rate_limit_check( 'mbmc_api', 120, HOUR_IN_SECONDS ) ) {
		return new WP_Error(
			'mbmc_rate_limited',
			__( 'Too many requests. Please try again later.', 'mad-baits-mobile-connector' ),
			array( 'status' => 429 )
		);
	}

	if ( ! mbmc_verify_app_token( $request ) ) {
		return new WP_Error(
			'mbmc_unauthorized',
			__( 'Invalid or missing X-Madbaits-App-Token header.', 'mad-baits-mobile-connector' ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Allowed feedback types from the mobile app.
 *
 * @return string[]
 */
function mbmc_get_feedback_types() {
	return array( 'app', 'product', 'bug', 'feature', 'team', 'other' );
}

/**
 * Allowed feedback priority values.
 *
 * @return string[]
 */
function mbmc_get_feedback_priorities() {
	return array( 'low', 'normal', 'high' );
}

/**
 * Sanitize feedback type slug.
 *
 * @param string $type Raw type.
 * @return string
 */
function mbmc_sanitize_feedback_type( $type ) {
	$type = sanitize_key( (string) $type );

	if ( in_array( $type, mbmc_get_feedback_types(), true ) ) {
		return $type;
	}

	return 'app';
}

/**
 * Sanitize feedback priority.
 *
 * @param string $priority Raw priority.
 * @return string
 */
function mbmc_sanitize_feedback_priority( $priority ) {
	$priority = sanitize_key( (string) $priority );

	if ( in_array( $priority, mbmc_get_feedback_priorities(), true ) ) {
		return $priority;
	}

	return 'normal';
}

/**
 * Sanitize short text field.
 *
 * @param string $value Raw value.
 * @param int    $max   Max length.
 * @return string
 */
function mbmc_sanitize_short_text( $value, $max = 255 ) {
	$max_length = is_numeric( $max ) ? (int) $max : 255;
	if ( $max_length < 1 ) {
		$max_length = 255;
	}

	return substr( sanitize_text_field( (string) $value ), 0, $max_length );
}

/**
 * Sanitize long text / message field.
 *
 * @param string $value Raw value.
 * @param int    $max   Max length.
 * @return string
 */
function mbmc_sanitize_long_text( $value, $max = 5000 ) {
	$max_length = is_numeric( $max ) ? (int) $max : 5000;
	if ( $max_length < 1 ) {
		$max_length = 5000;
	}

	return substr( sanitize_textarea_field( (string) $value ), 0, $max_length );
}

/**
 * Encode device info object for storage.
 *
 * @param mixed $device_info Raw device info.
 * @return string|null
 */
function mbmc_encode_device_info( $device_info ) {
	if ( ! is_array( $device_info ) ) {
		return null;
	}

	$clean = array();

	foreach ( $device_info as $key => $value ) {
		$key = sanitize_key( (string) $key );

		if ( '' === $key ) {
			continue;
		}

		if ( is_bool( $value ) ) {
			$clean[ $key ] = $value;
		} elseif ( is_numeric( $value ) ) {
			$clean[ $key ] = $value;
		} else {
			$clean[ $key ] = sanitize_text_field( (string) $value );
		}
	}

	return empty( $clean ) ? null : wp_json_encode( $clean );
}

/**
 * Allowed team roles from the mobile app.
 *
 * @return string[]
 */
function mbmc_get_team_roles() {
	return array(
		'customer',
		'mad_team',
		'rnt_team',
		'ambassador',
		'bait_tester',
		'media_team',
	);
}

/**
 * Sanitize team role slug.
 *
 * @param string $role Raw role.
 * @return string
 */
function mbmc_sanitize_team_role( $role ) {
	$role = sanitize_key( (string) $role );

	if ( in_array( $role, mbmc_get_team_roles(), true ) ) {
		return $role;
	}

	return 'customer';
}

/**
 * Standard not-implemented REST response envelope.
 *
 * @param string $feature Feature label.
 * @return WP_REST_Response
 */
function mbmc_not_implemented_response( $feature ) {
	return rest_ensure_response(
		array(
			'ok'          => false,
			'implemented' => false,
			'code'        => 'not_implemented',
			'message'     => sprintf(
				/* translators: %s: feature name */
				__( '%s is not implemented yet. Structure is reserved for future WooCommerce integration.', 'mad-baits-mobile-connector' ),
				$feature
			),
			'source'      => 'wordpress',
			'generatedAt' => wp_date( 'c' ),
		)
	);
}

/**
 * Verify device ownership using device_id and optional push_token.
 *
 * @param object $row         DB row.
 * @param string $push_token  Optional token to verify.
 * @return bool
 */
function mbmc_verify_device_token( $row, $push_token = '' ) {
	if ( ! $row ) {
		return false;
	}

	if ( '' === $push_token ) {
		return true;
	}

	return hash_equals( (string) $row->push_token, $push_token );
}

/**
 * Push settings option keys.
 *
 * @return string[]
 */
function mbmc_get_push_option_keys() {
	return array(
		'push_sending_enabled'   => 'mbmc_push_sending_enabled',
		'push_provider'          => 'mbmc_push_provider',
		'push_test_mode_enabled' => 'mbmc_push_test_mode_enabled',
		'push_max_batch'         => 'mbmc_push_max_batch',
	);
}

/**
 * Whether outbound push sending is enabled.
 *
 * @return bool
 */
function mbmc_is_push_sending_enabled() {
	return (bool) get_option( 'mbmc_push_sending_enabled', false );
}

/**
 * Whether push test mode restrictions apply.
 *
 * @return bool
 */
function mbmc_is_push_test_mode_enabled() {
	return (bool) get_option( 'mbmc_push_test_mode_enabled', true );
}

/**
 * Configured push provider slug.
 *
 * @return string
 */
function mbmc_get_push_provider() {
	$provider = sanitize_key( (string) get_option( 'mbmc_push_provider', 'expo' ) );

	if ( in_array( $provider, array( 'expo', 'firebase_future' ), true ) ) {
		return $provider;
	}

	return 'expo';
}

/**
 * Max notifications per batch (future mass send guard).
 *
 * @return int
 */
function mbmc_get_push_max_batch() {
	$value = (int) get_option( 'mbmc_push_max_batch', 10 );

	return max( 1, min( 100, $value ) );
}

/**
 * Sanitize notification type slug.
 *
 * @param string $type Raw type.
 * @return string
 */
function mbmc_sanitize_notification_type( $type ) {
	return substr( sanitize_key( (string) $type ), 0, 64 );
}

/**
 * Sanitize push data payload object.
 *
 * @param mixed $data Raw data.
 * @return array<string, scalar|null>
 */
function mbmc_sanitize_push_data( $data ) {
	$clean = array();

	if ( ! is_array( $data ) ) {
		return $clean;
	}

	foreach ( $data as $key => $value ) {
		$key = sanitize_key( (string) $key );

		if ( '' === $key ) {
			continue;
		}

		if ( is_bool( $value ) ) {
			$clean[ $key ] = $value;
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$clean[ $key ] = $value;
		} elseif ( null === $value ) {
			$clean[ $key ] = null;
		} else {
			$clean[ $key ] = sanitize_text_field( (string) $value );
		}
	}

	return $clean;
}

/**
 * Sanitize checkbox option from settings form.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function mbmc_sanitize_checkbox_option( $value ) {
	return rest_sanitize_boolean( $value );
}

/**
 * Sanitize push provider option.
 *
 * @param string $value Raw provider.
 * @return string
 */
function mbmc_sanitize_push_provider_option( $value ) {
	$provider = sanitize_key( (string) $value );

	return in_array( $provider, array( 'expo', 'firebase_future' ), true ) ? $provider : 'expo';
}

/**
 * Sanitize max batch option.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function mbmc_sanitize_push_max_batch_option( $value ) {
	return max( 1, min( 100, absint( $value ) ) );
}

/**
 * Supported WooCommerce order notification type slugs.
 *
 * @return string[]
 */
function mbmc_get_order_notification_types() {
	return array(
		'order_received',
		'order_processing',
		'order_dispatched',
		'order_completed',
		'order_cancelled',
		'order_refunded',
		'order_failed',
		'tracking_added',
	);
}

/**
 * Human-readable order notification message.
 *
 * @param string $notification_type Type slug.
 * @param string $order_number      Order number for substitution.
 * @return string
 */
function mbmc_get_order_notification_message( $notification_type, $order_number ) {
	$order_number = sanitize_text_field( (string) $order_number );

	$messages = array(
		'order_received'   => __( 'Your Mad Baits order #%s has been received.', 'mad-baits-mobile-connector' ),
		'order_processing' => __( 'Your Mad Baits order #%s is now being prepared.', 'mad-baits-mobile-connector' ),
		'order_dispatched' => __( 'Your Mad Baits order #%s has been dispatched.', 'mad-baits-mobile-connector' ),
		'order_completed'  => __( 'Your Mad Baits order #%s has been completed.', 'mad-baits-mobile-connector' ),
		'order_cancelled'  => __( 'Your Mad Baits order #%s has been cancelled.', 'mad-baits-mobile-connector' ),
		'order_refunded'   => __( 'Your Mad Baits order #%s has been refunded.', 'mad-baits-mobile-connector' ),
		'order_failed'     => __( 'There was a problem with your Mad Baits order #%s.', 'mad-baits-mobile-connector' ),
		'tracking_added'   => __( 'Tracking has been added for your Mad Baits order #%s.', 'mad-baits-mobile-connector' ),
	);

	$notification_type = mbmc_sanitize_notification_type( $notification_type );

	if ( ! isset( $messages[ $notification_type ] ) ) {
		return sprintf(
			/* translators: %s: order number */
			__( 'Update for your Mad Baits order #%s.', 'mad-baits-mobile-connector' ),
			$order_number
		);
	}

	return sprintf( $messages[ $notification_type ], $order_number );
}

/**
 * Decode stored device notification preferences.
 *
 * @param object $device Device row.
 * @return array<string, bool>
 */
function mbmc_decode_device_preferences( $device ) {
	if ( ! $device || empty( $device->notification_preferences ) ) {
		return array();
	}

	$prefs = json_decode( (string) $device->notification_preferences, true );

	return is_array( $prefs ) ? $prefs : array();
}

/**
 * Whether a device allows a given order notification type.
 *
 * Defaults to enabled unless a mapped preference is explicitly false.
 *
 * @param object $device            Device row.
 * @param string $notification_type Order notification type.
 * @return bool
 */
function mbmc_device_allows_order_notification( $device, $notification_type ) {
	$prefs = mbmc_decode_device_preferences( $device );

	switch ( $notification_type ) {
		case 'order_received':
			return mbmc_preference_default_true( $prefs, 'orderReceived' );
		case 'order_processing':
			return mbmc_preference_default_true( $prefs, 'orderProcessing' );
		case 'order_dispatched':
			if ( array_key_exists( 'orderDispatched', $prefs ) ) {
				return (bool) $prefs['orderDispatched'];
			}
			return mbmc_preference_default_true( $prefs, 'orderDispatchedTracking' );
		case 'order_completed':
			if ( array_key_exists( 'orderCompleted', $prefs ) ) {
				return (bool) $prefs['orderCompleted'];
			}
			return mbmc_preference_default_true( $prefs, 'orderCompletedCancelled' );
		case 'order_cancelled':
			if ( array_key_exists( 'orderCancelled', $prefs ) ) {
				return (bool) $prefs['orderCancelled'];
			}
			return mbmc_preference_default_true( $prefs, 'orderCompletedCancelled' );
		case 'order_refunded':
			return mbmc_preference_default_true( $prefs, 'orderRefunded' );
		case 'order_failed':
			return mbmc_preference_default_true( $prefs, 'orderFailed' );
		case 'tracking_added':
			if ( array_key_exists( 'trackingAdded', $prefs ) ) {
				return (bool) $prefs['trackingAdded'];
			}
			return mbmc_preference_default_true( $prefs, 'orderDispatchedTracking' );
		default:
			return true;
	}
}

/**
 * Preference helper — enabled unless explicitly false.
 *
 * @param array<string, bool> $prefs Preference map.
 * @param string              $key   Preference key.
 * @return bool
 */
function mbmc_preference_default_true( array $prefs, $key ) {
	if ( ! array_key_exists( $key, $prefs ) ) {
		return true;
	}

	return (bool) $prefs[ $key ];
}

/**
 * Whether WooCommerce order push notifications are enabled.
 *
 * @return bool
 */
function mbmc_is_order_notifications_enabled() {
	return (bool) get_option( 'mbmc_order_notifications_enabled', false );
}

/**
 * Whether dispatched status notifications are enabled.
 *
 * @return bool
 */
function mbmc_is_order_dispatched_notifications_enabled() {
	return (bool) get_option( 'mbmc_order_dispatched_notifications_enabled', false );
}

/**
 * Whether tracking-added notifications are enabled.
 *
 * @return bool
 */
function mbmc_is_order_tracking_notifications_enabled() {
	return (bool) get_option( 'mbmc_order_tracking_notifications_enabled', false );
}

/**
 * Whether order notifications require push sending to be enabled.
 *
 * @return bool
 */
function mbmc_is_order_require_push_enabled() {
	return (bool) get_option( 'mbmc_order_require_push_enabled', true );
}

/**
 * Common order tracking meta keys used by popular plugins.
 *
 * @return string[]
 */
function mbmc_get_tracking_meta_keys() {
	return array(
		'_tracking_number',
		'tracking_number',
		'_shipping_tracking_number',
		'_wc_shipment_tracking_number',
		'_tracking_url',
		'tracking_url',
		'_wc_shipment_tracking_items',
		'_tracking_provider',
		'tracking_provider',
		'_shipping_carrier',
	);
}

