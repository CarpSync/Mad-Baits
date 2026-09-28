<?php
/**
 * REST API for Session app.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * REST routes.
 */
class MBS_REST {

	/**
	 * @return void
	 */
	public static function init() {
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));
	}

	/**
	 * Logged-in user fields for catch report forms.
	 *
	 * @return array<string, string>
	 */
	public static function get_client_user() {
		$user = wp_get_current_user();
		if (! $user || ! $user->exists()) {
			return array();
		}
		return array(
			'displayName' => $user->display_name ? $user->display_name : $user->user_login,
			'email'       => $user->user_email,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_client_config() {
		$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
		$bundles_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
		$login_url    = MBS_Access::get_auth_url('login');
		$register_url = MBS_Access::get_auth_url('register');

		return array(
			'restUrl'       => esc_url_raw(rest_url(MBS_REST_NAMESPACE)),
			'nonce'         => wp_create_nonce('wp_rest'),
			'access'        => MBS_Access::get_access_state(),
			'canUse'        => MBS_Access::can_use_session_app(),
			'mobileOnly'    => MBS_Plugin::is_mobile_only_enforced(),
			'isMobile'      => MBS_Plugin::is_mobile_viewport_context(),
			'sessionUrl'    => MBS_Plugin::get_session_page_url(),
			'shopUrl'       => is_string($shop_url) ? $shop_url : home_url('/shop/'),
			'bundlesUrl'    => is_string($bundles_url) ? $bundles_url : home_url('/shop/'),
			'baitMapReady'  => function_exists('mbs_get_bait_product_map'),
			'quickAmounts'  => array('250g', '500g', '1kg', 'handful', 'spod mix', 'solid bag'),
			'activityTypes' => array_keys(MBS_Companion::ACTIVITY_LABELS),
			'loginUrl'      => $login_url,
			'registerUrl'   => $register_url,
			'cartUrl'       => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
			'teamReports'   => MBS_Access::user_can_submit_team_reports(),
			'settings'      => array(
				'catchPhotos'    => 'yes' === MBS_Plugin::get_setting('catch_photos', 'yes'),
				'maxPhotos'      => (int) MBS_Plugin::get_setting('max_photos_per_catch', 3),
				'publicConsent'  => 'yes' === MBS_Plugin::get_setting('public_catch_consent', 'yes'),
				'pdfExport'      => 'yes' === MBS_Plugin::get_setting('pdf_export', 'yes'),
				'offlineDraft'   => 'yes' === MBS_Plugin::get_setting('offline_draft', 'yes'),
				'haptics'        => 'yes' === MBS_Plugin::get_setting('haptics', 'yes'),
				'weatherEnabled'   => 'yes' === MBS_Plugin::get_setting('weather_enabled', 'no'),
				'weatherApiReady'    => MBS_Weather::is_api_configured(),
				'weatherManual'      => true,
			),
			'user'          => self::get_client_user(),
			'i18n'          => array(
				'loading'       => __('Loading Session…', 'mad-baits-session'),
				'save'          => __('Save', 'mad-baits-session'),
				'savingCatch'   => __('Saving catch…', 'mad-baits-session'),
				'saving'        => __('Saving…', 'mad-baits-session'),
				'submittingReport' => __('Submitting catch report…', 'mad-baits-session'),
				'sessionSaved'  => __('Session saved', 'mad-baits-session'),
				'catchLogged'   => __('Catch logged', 'mad-baits-session'),
				'reportSubmitted' => __('Catch report submitted', 'mad-baits-session'),
				'reportThanks'  => __('Thanks — the Mad Baits media team has received your report.', 'mad-baits-session'),
			),
		);
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		$ns = MBS_REST_NAMESPACE;

		register_rest_route($ns, '/session/access', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'get_access'),
			'permission_callback' => '__return_true',
		));

		register_rest_route($ns, '/session/dashboard', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'get_dashboard'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/sessions', array(
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'list_sessions'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'create_session'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
		));

		register_rest_route($ns, '/sessions/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'get_session'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array(__CLASS__, 'update_session'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array(__CLASS__, 'delete_session'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
		));

		register_rest_route($ns, '/sessions/(?P<id>\d+)/(?P<action>[a-z_]+)', array(
			'methods'             => array('POST', 'PATCH'),
			'callback'            => array(__CLASS__, 'session_action'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/venues', array(
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'list_venues'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'create_venue'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
		));

		register_rest_route($ns, '/venues/(?P<id>\d+)', array(
			array(
				'methods'             => 'PATCH',
				'callback'            => array(__CLASS__, 'update_venue'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array(__CLASS__, 'delete_venue'),
				'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
			),
		));

		register_rest_route($ns, '/order-products', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'get_order_products'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/weather', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'get_weather'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/insights/(?P<type>[a-z_]+)', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'get_insights'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/reorder', array(
			'methods'             => 'POST',
			'callback'            => array(__CLASS__, 'reorder_setup'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/sessions/(?P<id>\d+)/repeat', array(
			'methods'             => 'POST',
			'callback'            => array(__CLASS__, 'repeat_session'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/team-reports', array(
			array(
				'methods'             => 'GET',
				'callback'            => array(__CLASS__, 'list_team_reports'),
				'permission_callback' => array(__CLASS__, 'team_report_permission'),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'create_team_report'),
				'permission_callback' => array(__CLASS__, 'team_report_permission'),
			),
		));

		register_rest_route($ns, '/reminders', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'get_reminders'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));

		register_rest_route($ns, '/upload/catch-photo', array(
			'methods'             => 'POST',
			'callback'            => array(__CLASS__, 'upload_catch_photo'),
			'permission_callback' => array('MBS_Access', 'rest_customer_permission'),
		));
	}

	/**
	 * @return bool|WP_Error
	 */
	public static function team_report_permission() {
		$base = MBS_Access::rest_customer_permission();
		if (true !== $base) {
			return $base;
		}
		if (! MBS_Access::user_can_submit_team_reports()) {
			return new WP_Error('mbs_forbidden', __('Team reports are not available for your account.', 'mad-baits-session'), array('status' => 403));
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_access(WP_REST_Request $request) {
		return new WP_REST_Response(
			array(
				'state'      => MBS_Access::get_access_state(),
				'can_use'    => MBS_Access::can_use_session_app(),
				'mobile_only'=> MBS_Plugin::is_mobile_only_enforced(),
				'is_mobile'  => MBS_Plugin::is_mobile_viewport_context(),
				'enabled'    => MBS_Plugin::is_enabled(),
			)
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_dashboard() {
		$user_id = get_current_user_id();
		return new WP_REST_Response(
			array(
				'stats'     => MBS_Insights::get_dashboard_stats($user_id),
				'reminders' => MBS_Reminders::get_in_app_reminders($user_id),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_sessions(WP_REST_Request $request) {
		$user_id = get_current_user_id();
		$posts   = MBS_Insights::get_user_sessions($user_id);
		$items   = array();

		foreach ($posts as $post) {
			$data  = MBS_Meta::get_session_data($post->ID);
			$stats = MBS_Meta::compute_session_stats($data);
			$items[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'venue_name'  => (string) ($data['venue_name'] ?? ''),
				'start_at'    => (string) ($data['start_at'] ?? $post->post_date),
				'end_at'      => (string) ($data['end_at'] ?? ''),
				'status'      => (string) ($data['status'] ?? ''),
				'catch_count' => (int) $stats['catch_count'],
				'best_fish_lb'=> (float) $stats['best_fish_lb'],
			);
		}

		return new WP_REST_Response(array('sessions' => $items));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_session(WP_REST_Request $request) {
		$params = $request->get_json_params();
		if (! is_array($params)) {
			$params = array();
		}

		$title = sanitize_text_field((string) ($params['title'] ?? __('Fishing Session', 'mad-baits-session')));
		$data  = isset($params['data']) && is_array($params['data']) ? self::sanitize_session_data($params['data']) : array();
		if (empty($data['status'])) {
			$data['status'] = 'active';
		}
		if (empty($data['start_at'])) {
			$data['start_at'] = current_time('c');
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => MBS_CPT::SESSION,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);

		if (is_wp_error($post_id)) {
			return $post_id;
		}

		MBS_Meta::update_session_data($post_id, $data);
		return new WP_REST_Response(MBS_Meta::session_to_api($post_id), 201);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_session(WP_REST_Request $request) {
		$id = (int) $request['id'];
		if (! MBS_Access::user_owns_post($id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}
		return new WP_REST_Response(MBS_Meta::session_to_api($id));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_session(WP_REST_Request $request) {
		$id = (int) $request['id'];
		if (! MBS_Access::user_owns_post($id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}

		$params = $request->get_json_params();
		if (! is_array($params)) {
			$params = array();
		}

		if (! empty($params['title'])) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => sanitize_text_field((string) $params['title']),
				)
			);
		}

		if (isset($params['data']) && is_array($params['data'])) {
			MBS_Meta::patch_session_data($id, self::sanitize_session_data($params['data']));
		}

		return new WP_REST_Response(MBS_Meta::session_to_api($id));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_session(WP_REST_Request $request) {
		$id = (int) $request['id'];
		if (! MBS_Access::user_owns_post($id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}
		wp_trash_post($id);
		return new WP_REST_Response(array('deleted' => true));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function session_action(WP_REST_Request $request) {
		$id     = (int) $request['id'];
		$action = sanitize_key((string) $request['action']);

		if (! MBS_Access::user_owns_post($id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}

		$params = $request->get_json_params();
		if (! is_array($params)) {
			$params = array();
		}

		$list_map = array(
			'catch'       => 'catches',
			'baiting'     => 'baiting_log',
			'rig'         => 'rigs',
			'spot'        => 'spots',
			'bait'        => 'bait_used',
			'weather'     => 'weather_snapshots',
			'activity'    => 'activity_log',
			'photo'       => 'photos',
		);

		if ('catch_report' === $action) {
			if (! class_exists('MBS_Catch_Reports')) {
				return new WP_Error('mbs_catch_report_unavailable', __('Catch report system is unavailable.', 'mad-baits-session'), array('status' => 500));
			}
			$result = MBS_Catch_Reports::submit_from_session($id, $params);
			if (is_wp_error($result)) {
				return $result;
			}
			return new WP_REST_Response($result);
		}

		if (isset($list_map[ $action ])) {
			$key   = $list_map[ $action ];
			$entry = self::sanitize_list_entry($action, $params);
			if ('weather' === $action) {
				$entry = MBS_Weather::sanitize_snapshot($entry);
				$entry['captured_at'] = current_time('c');
			}
			$result = MBS_Meta::append_list_entry($id, $key, $entry);
			if (is_wp_error($result)) {
				return $result;
			}
			if ('end' === ($params['finalize'] ?? '')) {
				MBS_Meta::patch_session_data(
					$id,
					array(
						'status'  => 'ended',
						'end_at'  => current_time('c'),
						'outcome' => isset($params['outcome']) && is_array($params['outcome']) ? $params['outcome'] : array(),
						'lessons' => sanitize_textarea_field((string) ($params['lessons'] ?? '')),
					)
				);
			}
			return new WP_REST_Response(array('entry' => $result, 'session' => MBS_Meta::session_to_api($id)));
		}

		if ('end' === $action) {
			MBS_Meta::patch_session_data(
				$id,
				array(
					'status'  => 'ended',
					'end_at'  => current_time('c'),
					'outcome' => isset($params['outcome']) && is_array($params['outcome']) ? self::sanitize_outcome($params['outcome']) : array(),
					'lessons' => sanitize_textarea_field((string) ($params['lessons'] ?? '')),
				)
			);
			$snap = array();
			if (! empty($params['weather']) && is_array($params['weather'])) {
				$snap = $params['weather'];
				$snap['captured_at'] = current_time('c');
				MBS_Meta::append_list_entry($id, 'weather_snapshots', $snap);
			}
			return new WP_REST_Response(MBS_Meta::session_to_api($id));
		}

		if ('conditions' === $action) {
			MBS_Meta::patch_session_data($id, array('conditions' => self::sanitize_conditions($params)));
			return new WP_REST_Response(MBS_Meta::session_to_api($id));
		}

		if ('note' === $action) {
			$note = sanitize_textarea_field((string) ($params['note'] ?? ''));
			$data = MBS_Meta::get_session_data($id);
			$data['notes'] = trim((string) ($data['notes'] ?? '') . "\n" . $note);
			MBS_Meta::update_session_data($id, $data);
			return new WP_REST_Response(MBS_Meta::session_to_api($id));
		}

		return new WP_Error('mbs_invalid_action', __('Invalid action.', 'mad-baits-session'), array('status' => 400));
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function list_venues() {
		$posts = get_posts(
			array(
				'post_type'      => MBS_CPT::VENUE,
				'post_status'    => 'publish',
				'author'         => get_current_user_id(),
				'posts_per_page' => 100,
			)
		);
		$items = array();
		foreach ($posts as $post) {
			$items[] = MBS_Meta::venue_to_api($post->ID);
		}
		return new WP_REST_Response(array('venues' => $items));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_venue(WP_REST_Request $request) {
		$params = $request->get_json_params();
		if (! is_array($params)) {
			$params = array();
		}
		$title = sanitize_text_field((string) ($params['title'] ?? __('Saved Venue', 'mad-baits-session')));
		$post_id = wp_insert_post(
			array(
				'post_type'   => MBS_CPT::VENUE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);
		if (is_wp_error($post_id)) {
			return $post_id;
		}
		$data = isset($params['data']) && is_array($params['data']) ? $params['data'] : array();
		MBS_Meta::update_venue_data($post_id, self::sanitize_venue_data($data));
		return new WP_REST_Response(MBS_Meta::venue_to_api($post_id), 201);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_venue(WP_REST_Request $request) {
		$id = (int) $request['id'];
		if (! MBS_Access::user_owns_post($id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}
		$params = $request->get_json_params();
		if (is_array($params) && isset($params['data'])) {
			MBS_Meta::update_venue_data($id, self::sanitize_venue_data($params['data']));
		}
		return new WP_REST_Response(MBS_Meta::venue_to_api($id));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_venue(WP_REST_Request $request) {
		$id = (int) $request['id'];
		if (! MBS_Access::user_owns_post($id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}
		wp_trash_post($id);
		return new WP_REST_Response(array('deleted' => true));
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_order_products() {
		return new WP_REST_Response(
			array('products' => MBS_WooCommerce::get_customer_order_products())
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_weather(WP_REST_Request $request) {
		$lat = (float) $request->get_param('lat');
		$lng = (float) $request->get_param('lng');
		$postcode = sanitize_text_field((string) $request->get_param('postcode'));

		if ((0.0 === $lat && 0.0 === $lng) && '' !== $postcode) {
			$geo = MBS_Weather::geocode_postcode($postcode);
			if (is_wp_error($geo)) {
				return $geo;
			}
			$lat = (float) $geo['lat'];
			$lng = (float) $geo['lng'];
		}

		$result = MBS_Weather::fetch_current($lat, $lng);
		if (is_wp_error($result)) {
			return $result;
		}
		return new WP_REST_Response($result);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_insights(WP_REST_Request $request) {
		$type    = sanitize_key((string) $request['type']);
		$user_id = get_current_user_id();

		switch ($type) {
			case 'bait':
				$data = MBS_Insights::get_bait_performance($user_id);
				break;
			case 'conditions':
				$data = MBS_Insights::get_conditions_insights($user_id);
				break;
			case 'pbs':
				$data = array('pbs' => MBS_Insights::get_pb_tracker($user_id));
				break;
			case 'gallery':
				$data = array(
					'items' => MBS_Insights::get_catch_gallery(
						$user_id,
						array(
							'pb_only' => '1' === (string) $request->get_param('pb_only'),
							'venue'   => sanitize_text_field((string) $request->get_param('venue')),
						)
					),
				);
				break;
			default:
				$data = array('message' => __('Unknown insight type.', 'mad-baits-session'));
		}

		return new WP_REST_Response($data);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reorder_setup(WP_REST_Request $request) {
		$params = $request->get_json_params();
		if (! is_array($params)) {
			$params = array();
		}

		$map = array();
		if (! empty($params['session_id'])) {
			$sid = (int) $params['session_id'];
			if (! MBS_Access::user_owns_post($sid)) {
				return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
			}
			$data = MBS_Meta::get_session_data($sid);
			$map  = MBS_WooCommerce::collect_reorder_map_from_session($data);
		} elseif (! empty($params['products']) && is_array($params['products'])) {
			foreach ($params['products'] as $row) {
				if (! is_array($row)) {
					continue;
				}
				$pid = absint($row['product_id'] ?? 0);
				$qty = max(1, absint($row['qty'] ?? 1));
				if ($pid > 0) {
					$map[ $pid ] = $qty;
				}
			}
		}

		return new WP_REST_Response(MBS_WooCommerce::reorder_products($map));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function repeat_session(WP_REST_Request $request) {
		$source_id = (int) $request['id'];
		if (! MBS_Access::user_owns_post($source_id)) {
			return new WP_Error('mbs_forbidden', __('Access denied.', 'mad-baits-session'), array('status' => 403));
		}

		$source = MBS_Meta::get_session_data($source_id);
		$draft  = $source;
		$draft['status']   = 'draft';
		$draft['start_at'] = '';
		$draft['end_at']   = '';
		$draft['catches']  = array();
		$draft['baiting_log'] = array();
		$draft['weather_snapshots'] = array();

		$title = sprintf(
			/* translators: %s: venue name */
			__('Repeat — %s', 'mad-baits-session'),
			(string) ($source['venue_name'] ?? get_the_title($source_id))
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => MBS_CPT::SESSION,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field($title),
				'post_author' => get_current_user_id(),
			),
			true
		);

		if (is_wp_error($post_id)) {
			return $post_id;
		}

		MBS_Meta::update_session_data($post_id, $draft);
		return new WP_REST_Response(MBS_Meta::session_to_api($post_id), 201);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function list_team_reports() {
		$posts = get_posts(
			array(
				'post_type'      => MBS_CPT::REPORT,
				'post_status'    => 'publish',
				'author'         => get_current_user_id(),
				'posts_per_page' => 50,
			)
		);
		$items = array();
		foreach ($posts as $post) {
			$items[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'data'  => get_post_meta($post->ID, MBS_Meta::META_REPORT, true),
			);
		}
		return new WP_REST_Response(array('reports' => $items));
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_team_report(WP_REST_Request $request) {
		$params = $request->get_json_params();
		if (! is_array($params)) {
			$params = array();
		}
		$title = sanitize_text_field((string) ($params['title'] ?? __('Team Report', 'mad-baits-session')));
		$post_id = wp_insert_post(
			array(
				'post_type'   => MBS_CPT::REPORT,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);
		if (is_wp_error($post_id)) {
			return $post_id;
		}
		update_post_meta($post_id, MBS_Meta::META_REPORT, $params);
		return new WP_REST_Response(array('id' => $post_id), 201);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function get_reminders() {
		return new WP_REST_Response(
			array('reminders' => MBS_Reminders::get_in_app_reminders(get_current_user_id()))
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_catch_photo(WP_REST_Request $request) {
		if ('yes' !== MBS_Plugin::get_setting('catch_photos', 'yes')) {
			return new WP_Error('mbs_disabled', __('Catch photos are disabled.', 'mad-baits-session'), array('status' => 400));
		}

		$files = $request->get_file_params();
		if (empty($files['photo'])) {
			return new WP_Error('mbs_no_file', __('No photo uploaded.', 'mad-baits-session'), array('status' => 400));
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload('photo', 0);
		if (is_wp_error($attachment_id)) {
			return $attachment_id;
		}

		update_post_meta($attachment_id, '_mbs_owner', get_current_user_id());

		return new WP_REST_Response(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_image_url($attachment_id, 'large'),
			)
		);
	}

	/**
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private static function sanitize_session_data(array $data) {
		$clean = MBS_Meta::default_session_data();
		$text_fields = array('title', 'venue_name', 'venue_location', 'postcode', 'lake_swim', 'start_at', 'end_at', 'planned_end_at', 'status', 'notes', 'lessons');
		foreach ($text_fields as $key) {
			if (isset($data[ $key ])) {
				$clean[ $key ] = 'notes' === $key || 'lessons' === $key
					? sanitize_textarea_field((string) $data[ $key ])
					: sanitize_text_field((string) $data[ $key ]);
			}
		}
		if (isset($data['conditions']) && is_array($data['conditions'])) {
			$clean['conditions'] = self::sanitize_conditions($data['conditions']);
		}
		foreach (array('bait_used', 'baiting_log', 'catches', 'rigs', 'spots', 'swim_notes', 'weather_snapshots', 'photos', 'tactics') as $list) {
			if (isset($data[ $list ]) && is_array($data[ $list ])) {
				$clean[ $list ] = $data[ $list ];
			}
		}
		if (isset($data['outcome']) && is_array($data['outcome'])) {
			$clean['outcome'] = self::sanitize_outcome($data['outcome']);
		}
		if (isset($data['venue_id'])) {
			$clean['venue_id'] = absint($data['venue_id']);
		}
		if (isset($data['location_lat'])) {
			$clean['location_lat'] = is_numeric($data['location_lat']) ? (float) $data['location_lat'] : null;
		}
		if (isset($data['location_lng'])) {
			$clean['location_lng'] = is_numeric($data['location_lng']) ? (float) $data['location_lng'] : null;
		}
		return $clean;
	}

	/**
	 * @param array<string, mixed> $data Conditions.
	 * @return array<string, mixed>
	 */
	private static function sanitize_conditions(array $data) {
		return array(
			'water_clarity'    => sanitize_text_field((string) ($data['water_clarity'] ?? '')),
			'water_temp'       => sanitize_text_field((string) ($data['water_temp'] ?? '')),
			'weed_level'       => sanitize_text_field((string) ($data['weed_level'] ?? '')),
			'fishing_pressure' => sanitize_text_field((string) ($data['fishing_pressure'] ?? '')),
			'baiting_level'    => sanitize_text_field((string) ($data['baiting_level'] ?? '')),
			'lake_activity'    => sanitize_text_field((string) ($data['lake_activity'] ?? '')),
			'notes'            => sanitize_textarea_field((string) ($data['notes'] ?? '')),
		);
	}

	/**
	 * @param array<string, mixed> $data Outcome.
	 * @return array<string, mixed>
	 */
	private static function sanitize_outcome(array $data) {
		return array(
			'result'           => sanitize_text_field((string) ($data['result'] ?? '')),
			'what_worked'      => sanitize_textarea_field((string) ($data['what_worked'] ?? '')),
			'what_change'      => sanitize_textarea_field((string) ($data['what_change'] ?? '')),
			'same_bait_again'  => sanitize_text_field((string) ($data['same_bait_again'] ?? '')),
			'bait_confidence'  => min(5, max(1, absint($data['bait_confidence'] ?? 3))),
			'notes'            => sanitize_textarea_field((string) ($data['notes'] ?? '')),
		);
	}

	/**
	 * @param string               $action Action.
	 * @param array<string, mixed> $params Params.
	 * @return array<string, mixed>
	 */
	private static function sanitize_list_entry($action, array $params) {
		switch ($action) {
			case 'photo':
				$photo_ids = array();
				if (isset($params['photo_ids']) && is_array($params['photo_ids'])) {
					$photo_ids = array_values(array_filter(array_map('absint', $params['photo_ids'])));
				} elseif (! empty($params['attachment_id'])) {
					$photo_ids = array(absint($params['attachment_id']));
				}
				$main = absint($params['main_photo_id'] ?? ($photo_ids[0] ?? 0));
				return array(
					'attachment_id'       => $main,
					'photo_ids'           => $photo_ids,
					'main_photo_id'       => $main,
					'catch_id'            => sanitize_text_field((string) ($params['catch_id'] ?? '')),
					'weight'              => sanitize_text_field((string) ($params['weight'] ?? $params['fish_weight'] ?? '')),
					'bait_used'           => sanitize_text_field((string) ($params['bait_used'] ?? '')),
					'swim'                => sanitize_text_field((string) ($params['swim'] ?? '')),
					'venue'               => sanitize_text_field((string) ($params['venue'] ?? '')),
					'captured_at'         => sanitize_text_field((string) ($params['captured_at'] ?? current_time('c'))),
					'use_in_catch_report' => ! empty($params['use_in_catch_report']),
				);
			case 'catch':
				$photo_ids = array();
				if (isset($params['photo_ids']) && is_array($params['photo_ids'])) {
					$photo_ids = array_values(array_filter(array_map('absint', $params['photo_ids'])));
				}
				$main_photo = absint($params['main_photo_id'] ?? $params['photo_id'] ?? ($photo_ids[0] ?? 0));
				if ($main_photo > 0 && ! in_array($main_photo, $photo_ids, true)) {
					array_unshift($photo_ids, $main_photo);
				}
				return array(
					'catch_time'     => sanitize_text_field((string) ($params['catch_time'] ?? current_time('c'))),
					'species'        => sanitize_text_field((string) ($params['species'] ?? 'Carp')),
					'weight_lb'      => (float) ($params['weight_lb'] ?? 0),
					'weight_oz'      => (float) ($params['weight_oz'] ?? 0),
					'photo_id'       => $main_photo,
					'photo_ids'      => $photo_ids,
					'main_photo_id'  => $main_photo,
					'use_in_catch_report' => ! empty($params['use_in_catch_report']),
					'bait_used'      => sanitize_text_field((string) ($params['bait_used'] ?? '')),
					'hookbait'       => sanitize_text_field((string) ($params['hookbait'] ?? '')),
					'rig'            => sanitize_text_field((string) ($params['rig'] ?? '')),
					'swim'           => sanitize_text_field((string) ($params['swim'] ?? '')),
					'conditions_note'=> sanitize_textarea_field((string) ($params['conditions_note'] ?? '')),
					'private_note'   => sanitize_textarea_field((string) ($params['private_note'] ?? '')),
					'is_pb'          => ! empty($params['is_pb']),
					'public_consent'         => ! empty($params['public_consent']),
					'consent_at'             => ! empty($params['public_consent']) ? current_time('c') : '',
					'catch_report_id'        => absint($params['catch_report_id'] ?? 0),
					'media_report_submitted' => ! empty($params['media_report_submitted']),
					'media_report_at'        => sanitize_text_field((string) ($params['media_report_at'] ?? '')),
				);
			case 'baiting':
				return array(
					'time'   => sanitize_text_field((string) ($params['time'] ?? current_time('H:i'))),
					'bait'   => sanitize_text_field((string) ($params['bait'] ?? '')),
					'amount' => sanitize_text_field((string) ($params['amount'] ?? '')),
					'method' => sanitize_text_field((string) ($params['method'] ?? '')),
					'notes'  => sanitize_textarea_field((string) ($params['notes'] ?? '')),
				);
			case 'rig':
				return array(
					'name'      => sanitize_text_field((string) ($params['name'] ?? '')),
					'hook_size' => sanitize_text_field((string) ($params['hook_size'] ?? '')),
					'hooklink'  => sanitize_text_field((string) ($params['hooklink'] ?? '')),
					'lead'      => sanitize_text_field((string) ($params['lead'] ?? '')),
					'hookbait'  => sanitize_text_field((string) ($params['hookbait'] ?? '')),
					'result'    => sanitize_text_field((string) ($params['result'] ?? '')),
					'notes'     => sanitize_textarea_field((string) ($params['notes'] ?? '')),
				);
			case 'spot':
				return array(
					'name'  => sanitize_text_field((string) ($params['name'] ?? '')),
					'type'  => sanitize_text_field((string) ($params['type'] ?? '')),
					'lat'   => isset($params['lat']) ? (float) $params['lat'] : null,
					'lng'   => isset($params['lng']) ? (float) $params['lng'] : null,
					'notes' => sanitize_textarea_field((string) ($params['notes'] ?? '')),
				);
			case 'activity':
				return MBS_Companion::sanitize_activity($params);
			case 'bait':
				return array(
					'product_id'      => absint($params['product_id'] ?? 0),
					'name'            => sanitize_text_field((string) ($params['name'] ?? '')),
					'range'           => sanitize_text_field((string) ($params['range'] ?? '')),
					'hookbait'        => sanitize_text_field((string) ($params['hookbait'] ?? '')),
					'bait_type'       => sanitize_text_field((string) ($params['bait_type'] ?? $params['type'] ?? '')),
					'size'            => sanitize_text_field((string) ($params['size'] ?? '')),
					'amount_taken'    => sanitize_text_field((string) ($params['amount_taken'] ?? '')),
					'amount_used'     => sanitize_text_field((string) ($params['amount_used'] ?? $params['amount'] ?? '')),
					'amount_remaining'=> sanitize_text_field((string) ($params['amount_remaining'] ?? '')),
					'time_added'      => sanitize_text_field((string) ($params['time_added'] ?? current_time('H:i'))),
					'confidence'      => min(5, max(1, absint($params['confidence'] ?? 3))),
					'notes'           => sanitize_textarea_field((string) ($params['notes'] ?? '')),
					'manual'          => empty($params['product_id']),
				);
			default:
				return $params;
		}
	}

	/**
	 * @param array<string, mixed> $data Venue data.
	 * @return array<string, mixed>
	 */
	private static function sanitize_venue_data(array $data) {
		return array(
			'location'        => sanitize_text_field((string) ($data['location'] ?? '')),
			'favourite_swims' => sanitize_textarea_field((string) ($data['favourite_swims'] ?? '')),
			'notes'           => sanitize_textarea_field((string) ($data['notes'] ?? '')),
			'water_clarity'   => sanitize_text_field((string) ($data['water_clarity'] ?? '')),
			'rules'           => sanitize_textarea_field((string) ($data['rules'] ?? '')),
		);
	}
}
