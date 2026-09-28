<?php
/**
 * REST API routes for push lifecycle.
 *
 * @package MadBaitsPush
 */

if (! defined('ABSPATH')) {
	exit;
}

class Mad_Baits_Push_REST {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'mad-baits/v1',
			'/push/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'subscribe'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'mad-baits/v1',
			'/push/unsubscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'unsubscribe'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'mad-baits/v1',
			'/push/status',
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'status'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'mad-baits/v1',
			'/push/send',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'send'),
				'permission_callback' => function () {
					return current_user_can('manage_options');
				},
			)
		);
	}

	/**
	 * Subscribe endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function subscribe($request) {
		if (is_user_logged_in() && ! self::is_valid_rest_nonce($request)) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Invalid REST nonce.', 'mad-baits-push')), 403);
		}

		$endpoint   = esc_url_raw((string) $request->get_param('endpoint'));
		$keys       = $request->get_param('keys');
		$permission = sanitize_key((string) $request->get_param('permission'));
		$is_pwa     = rest_sanitize_boolean($request->get_param('is_pwa'));
		$label      = sanitize_text_field((string) $request->get_param('device_label'));

		if ('' === $endpoint || ! is_array($keys) || ! self::is_valid_endpoint($endpoint)) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Invalid subscription payload.', 'mad-baits-push')), 400);
		}

		$public_key = isset($keys['p256dh']) ? sanitize_text_field((string) $keys['p256dh']) : '';
		$auth_token = isset($keys['auth']) ? sanitize_text_field((string) $keys['auth']) : '';
		if ('' === $public_key || '' === $auth_token) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Subscription keys are missing.', 'mad-baits-push')), 400);
		}

		$user_agent = sanitize_text_field((string) $request->get_param('user_agent'));
		if ('' === $user_agent && isset($_SERVER['HTTP_USER_AGENT'])) {
			$user_agent = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']));
		}
		$user_id    = is_user_logged_in() ? get_current_user_id() : null;

		$id = Mad_Baits_Push_DB::upsert_subscriber(
			array(
				'user_id'           => $user_id,
				'endpoint'          => $endpoint,
				'public_key'        => $public_key,
				'auth_token'        => $auth_token,
				'user_agent'        => $user_agent,
				'device_label'      => $label,
				'is_pwa'            => (bool) $is_pwa,
				'permission_status' => in_array($permission, array('granted', 'denied', 'default'), true) ? $permission : 'default',
				'active'            => true,
			)
		);

		if (! $id) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Could not save subscription.', 'mad-baits-push')), 500);
		}

		return new WP_REST_Response(array('success' => true, 'id' => $id), 200);
	}

	/**
	 * Unsubscribe endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function unsubscribe($request) {
		if (is_user_logged_in() && ! self::is_valid_rest_nonce($request)) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Invalid REST nonce.', 'mad-baits-push')), 403);
		}

		$endpoint = esc_url_raw((string) $request->get_param('endpoint'));
		if ('' === $endpoint || ! self::is_valid_endpoint($endpoint)) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Endpoint is required.', 'mad-baits-push')), 400);
		}

		Mad_Baits_Push_DB::unsubscribe($endpoint);
		return new WP_REST_Response(array('success' => true), 200);
	}

	/**
	 * Status endpoint.
	 *
	 * @return WP_REST_Response
	 */
	public static function status() {
		$public_key = Mad_Baits_Push_Admin::get_public_vapid_key();
		return new WP_REST_Response(
			array(
				'success'      => true,
				'push_enabled' => '' !== $public_key,
				'public_key'   => $public_key,
				'library'      => Mad_Baits_Push_Sender::has_webpush_library(),
			),
			200
		);
	}

	/**
	 * Send endpoint (admin only).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function send($request) {
		$title         = sanitize_text_field((string) $request->get_param('title'));
		$message       = sanitize_textarea_field((string) $request->get_param('message'));
		$url           = esc_url_raw((string) $request->get_param('url'));
		$image         = esc_url_raw((string) $request->get_param('image'));
		$audience      = sanitize_key((string) $request->get_param('audience'));
		$force         = rest_sanitize_boolean($request->get_param('force'));
		$test_endpoint = esc_url_raw((string) $request->get_param('test_endpoint'));

		if ('' === $title || '' === $message) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Title and message are required.', 'mad-baits-push')), 400);
		}
		if ('' === $url) {
			$url = home_url('/shop/');
		}

		if (! in_array($audience, array('all', 'pwa', 'logged_in', 'test'), true)) {
			$audience = 'all';
		}
		if ('test' === $audience && ! self::is_valid_endpoint($test_endpoint)) {
			return new WP_REST_Response(array('success' => false, 'message' => __('Test endpoint missing or invalid.', 'mad-baits-push')), 400);
		}

		$last_send = (int) get_transient('mad_baits_push_last_send_ts');
		if (! $force && $last_send > 0 && (time() - $last_send) < 30) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __('Rate limited: wait 30 seconds or confirm forced send.', 'mad-baits-push'),
				),
				429
			);
		}

		set_transient('mad_baits_push_last_send_ts', time(), 60);

		$result = Mad_Baits_Push_Sender::send(
			array(
				'title' => $title,
				'body'  => $message,
				'url'   => $url,
				'image' => $image,
				'tag'   => 'mad-baits-' . gmdate('YmdHis'),
			),
			'test' === $audience ? 'all' : $audience,
			'test' === $audience ? $test_endpoint : null
		);

		Mad_Baits_Push_DB::insert_log(
			array(
				'title'            => $title,
				'message'          => $message,
				'target_url'       => $url,
				'image_url'        => $image,
				'audience'         => $audience,
				'attempted'        => isset($result['attempted']) ? (int) $result['attempted'] : 0,
				'sent'             => isset($result['sent']) ? (int) $result['sent'] : 0,
				'failed'           => isset($result['failed']) ? (int) $result['failed'] : 0,
				'failures_summary' => isset($result['errors']) ? wp_json_encode((array) $result['errors']) : '',
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'result'  => $result,
			),
			200
		);
	}

	/**
	 * Validate REST nonce from request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function is_valid_rest_nonce($request) {
		$nonce = (string) $request->get_header('X-WP-Nonce');
		return '' !== $nonce && wp_verify_nonce($nonce, 'wp_rest');
	}

	/**
	 * Validate endpoint format.
	 *
	 * @param string $endpoint Endpoint.
	 * @return bool
	 */
	private static function is_valid_endpoint($endpoint) {
		$parts = wp_parse_url($endpoint);
		if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return false;
		}

		if (! in_array(strtolower((string) $parts['scheme']), array('https'), true)) {
			return false;
		}

		return strlen($endpoint) < 1900;
	}
}
