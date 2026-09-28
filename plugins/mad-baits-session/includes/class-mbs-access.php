<?php
/**
 * Session access control.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Customer verification and ownership.
 */
class MBS_Access {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter('woocommerce_login_redirect', array(__CLASS__, 'filter_login_redirect'), 20, 2);
		add_filter('woocommerce_registration_redirect', array(__CLASS__, 'filter_login_redirect'), 20, 1);
		add_filter('login_redirect', array(__CLASS__, 'filter_wp_login_redirect'), 20, 3);
		add_action('woocommerce_login_form_end', array(__CLASS__, 'print_login_redirect_field'));
		add_action('woocommerce_register_form_end', array(__CLASS__, 'print_login_redirect_field'));
		add_action('template_redirect', array(__CLASS__, 'remember_session_return_url'), 5);
		add_action('woocommerce_order_status_changed', array(__CLASS__, 'clear_order_cache_for_order'), 10, 4);
		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'clear_cache_for_checkout_order'), 10, 3);
		add_action('wp_login', array(__CLASS__, 'on_user_login'), 10, 2);
	}

	/**
	 * Fresh order check after login (e.g. admin just assigned a demo order).
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User.
	 * @return void
	 */
	public static function on_user_login($user_login, $user) {
		unset($user_login);
		if ($user instanceof WP_User) {
			self::clear_order_cache_for_user((int) $user->ID);
		}
	}

	/**
	 * WooCommerce order statuses that unlock Session (pending = placed; processing = confirmed/paid).
	 *
	 * @return array<string>
	 */
	public static function get_valid_order_statuses() {
		$raw = (string) MBS_Plugin::get_setting('order_statuses', 'pending,processing,completed');
		$statuses = array();
		foreach (explode(',', $raw) as $slug) {
			$slug = sanitize_key(trim($slug));
			if ('' !== $slug) {
				$statuses[] = $slug;
			}
		}
		if (empty($statuses)) {
			$statuses = array('pending', 'processing', 'completed');
		}
		if ('yes' === MBS_Plugin::get_setting('allow_on_hold', 'no') && ! in_array('on-hold', $statuses, true)) {
			$statuses[] = 'on-hold';
		}
		return apply_filters('mbs_valid_order_statuses', array_values(array_unique($statuses)));
	}

	/**
	 * Clear access cache as soon as checkout creates/links an order (pending counts).
	 *
	 * @param int       $order_id   Order ID.
	 * @param array     $posted_data Posted checkout data.
	 * @param WC_Order  $order      Order object.
	 * @return void
	 */
	public static function clear_cache_for_checkout_order($order_id, $posted_data, $order) {
		unset($posted_data);
		if ($order instanceof WC_Order) {
			self::clear_order_cache_for_order((int) $order_id, '', (string) $order->get_status(), $order);
			return;
		}
		$fetched = wc_get_order((int) $order_id);
		if ($fetched instanceof WC_Order) {
			self::clear_order_cache_for_order((int) $order_id, '', (string) $fetched->get_status(), $fetched);
		}
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_valid_order($user_id = 0) {
		if (! class_exists('WooCommerce')) {
			return false;
		}
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ($user_id < 1) {
			return false;
		}

		$cache_key = 'mbs_valid_order_' . $user_id;
		$cached    = get_transient($cache_key);
		if (false !== $cached) {
			return (bool) $cached;
		}

		$statuses = self::get_valid_order_statuses();
		$orders   = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => $statuses,
				'limit'       => 1,
				'return'      => 'ids',
			)
		);

		$has = ! empty($orders);

		if (! $has) {
			$user = get_userdata($user_id);
			$email = $user && is_string($user->user_email) ? sanitize_email($user->user_email) : '';
			if ('' !== $email) {
				$orders = wc_get_orders(
					array(
						'billing_email' => $email,
						'status'        => $statuses,
						'limit'         => 1,
						'return'        => 'ids',
					)
				);
				$has = ! empty($orders);
				if ($has) {
					self::link_unassigned_orders_to_customer($user_id, $email);
				}
			}
		}

		set_transient($cache_key, $has ? 1 : 0, HOUR_IN_SECONDS);
		return $has;
	}

	/**
	 * Attach guest/manual admin orders to the customer when billing email matches.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   Billing email.
	 * @return void
	 */
	public static function link_unassigned_orders_to_customer($user_id, $email) {
		if (! function_exists('wc_get_orders')) {
			return;
		}
		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => self::get_valid_order_statuses(),
				'limit'         => 20,
				'return'        => 'objects',
			)
		);
		foreach ($orders as $order) {
			if (! $order instanceof WC_Order) {
				continue;
			}
			if ((int) $order->get_customer_id() === (int) $user_id) {
				continue;
			}
			if ((int) $order->get_customer_id() > 0) {
				continue;
			}
			$order->set_customer_id((int) $user_id);
			$order->save();
		}
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function clear_order_cache_for_user($user_id) {
		delete_transient('mbs_valid_order_' . (int) $user_id);
	}

	/**
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public static function clear_order_cache_for_order($order_id, $from, $to, $order) {
		unset($from, $to);
		if ($order instanceof WC_Order) {
			$customer_id = (int) $order->get_customer_id();
			if ($customer_id > 0) {
				self::clear_order_cache_for_user($customer_id);
				return;
			}
			$email = sanitize_email((string) $order->get_billing_email());
			if ('' !== $email) {
				$user = get_user_by('email', $email);
				if ($user instanceof WP_User) {
					self::clear_order_cache_for_user((int) $user->ID);
				}
			}
		}
	}

	/**
	 * Session app URL for post-login return.
	 *
	 * @return string
	 */
	public static function get_return_url() {
		return class_exists('MBS_Plugin') ? MBS_Plugin::get_session_page_url() : home_url('/session/');
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_safe_return_url($url) {
		$url = esc_url_raw($url);
		if ('' === $url) {
			return false;
		}
		$home = wp_parse_url(home_url('/'));
		$target = wp_parse_url($url);
		if (! is_array($home) || ! is_array($target)) {
			return false;
		}
		$home_host = isset($home['host']) ? strtolower((string) $home['host']) : '';
		$target_host = isset($target['host']) ? strtolower((string) $target['host']) : '';
		return '' === $target_host || $home_host === $target_host;
	}

	/**
	 * @return string
	 */
	public static function get_pending_return_url() {
		if (! empty($_COOKIE['mbs_return_url'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$url = esc_url_raw(wp_unslash((string) $_COOKIE['mbs_return_url']));
			if (self::is_safe_return_url($url)) {
				return $url;
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (! empty($_REQUEST['redirect'])) {
			$url = esc_url_raw(wp_unslash((string) $_REQUEST['redirect']));
			if (self::is_safe_return_url($url)) {
				return $url;
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (! empty($_REQUEST['redirect_to'])) {
			$url = esc_url_raw(wp_unslash((string) $_REQUEST['redirect_to']));
			if (self::is_safe_return_url($url)) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * WooCommerce / WP login URL that returns to Session after auth.
	 *
	 * @param string $context login|register.
	 * @return string
	 */
	public static function get_auth_url($context = 'login') {
		$return = self::get_return_url();
		if (function_exists('wc_get_page_permalink')) {
			$account = wc_get_page_permalink('myaccount');
			if (is_string($account) && '' !== $account) {
				$args = array('redirect' => $return);
				if ('register' === $context) {
					$args['action'] = 'register';
				}
				return add_query_arg($args, $account);
			}
		}
		return wp_login_url($return);
	}

	/**
	 * Store return URL when guest opens Session.
	 *
	 * @return void
	 */
	public static function remember_session_return_url() {
		if (is_user_logged_in() || ! class_exists('MBS_Plugin') || ! MBS_Plugin::is_session_app_request()) {
			return;
		}
		$return = self::get_return_url();
		if (! headers_sent()) {
			setcookie('mbs_return_url', $return, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
		}
	}

	/**
	 * @param string  $redirect Default redirect.
	 * @param WP_User $user     User.
	 * @return string
	 */
	public static function filter_login_redirect($redirect, $user = null) {
		unset($user);
		$pending = self::get_pending_return_url();
		if ('' !== $pending) {
			self::clear_return_cookie();
			return $pending;
		}
		return $redirect;
	}

	/**
	 * @param string           $redirect_to           Redirect.
	 * @param string           $requested_redirect_to Requested.
	 * @param WP_User|WP_Error $user                  User.
	 * @return string
	 */
	public static function filter_wp_login_redirect($redirect_to, $requested_redirect_to, $user) {
		unset($user);
		if (is_string($requested_redirect_to) && self::is_safe_return_url($requested_redirect_to)) {
			self::clear_return_cookie();
			return $requested_redirect_to;
		}
		$pending = self::get_pending_return_url();
		if ('' !== $pending) {
			self::clear_return_cookie();
			return $pending;
		}
		return $redirect_to;
	}

	/**
	 * @return void
	 */
	public static function clear_return_cookie() {
		if (! headers_sent()) {
			setcookie('mbs_return_url', '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
		}
	}

	/**
	 * @return void
	 */
	public static function print_login_redirect_field() {
		$redirect = self::get_pending_return_url();
		if ('' === $redirect) {
			$redirect = self::get_return_url();
		}
		echo '<input type="hidden" name="redirect" value="' . esc_attr($redirect) . '" />';
	}

	/**
	 * @return string guest|logged_no_order|customer|admin
	 */
	public static function get_access_state() {
		if (! is_user_logged_in()) {
			return 'guest';
		}
		if (current_user_can('manage_options')) {
			return 'admin';
		}
		if (self::user_has_valid_order() || self::user_has_team_session_bypass()) {
			return 'customer';
		}
		return 'logged_no_order';
	}

	/**
	 * @return bool
	 */
	public static function can_use_session_app() {
		if (! MBS_Plugin::is_enabled()) {
			return false;
		}
		if (! is_user_logged_in()) {
			return false;
		}
		if (current_user_can('manage_options')) {
			return true;
		}
		return self::user_has_valid_order() || self::user_has_team_session_bypass();
	}

	/**
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_owns_post($post_id, $user_id = 0) {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ($user_id < 1) {
			return false;
		}
		if (user_can($user_id, 'manage_options')) {
			return true;
		}
		$post = get_post($post_id);
		if (! $post) {
			return false;
		}
		return (int) $post->post_author === (int) $user_id;
	}

	/**
	 * @return bool
	 */
	/**
	 * Team / tester accounts can use Session for field testing without a personal order.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_team_session_bypass($user_id = 0) {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ($user_id < 1) {
			return false;
		}
		if (! is_user_logged_in()) {
			return false;
		}
		$roles = array('mad_team', 'mad_rnt', 'mad_ambassador', 'mad_tester');
		$user  = get_userdata($user_id);
		if (! $user) {
			return false;
		}
		foreach ($roles as $role) {
			if (in_array($role, (array) $user->roles, true)) {
				return (bool) apply_filters('mbs_user_has_team_session_bypass', true, $user);
			}
		}
		return (bool) apply_filters('mbs_user_has_team_session_bypass', false, $user);
	}

	/**
	 * @return bool
	 */
	public static function user_can_submit_team_reports() {
		if ('yes' !== MBS_Plugin::get_setting('team_reports', 'yes')) {
			return false;
		}
		if (! is_user_logged_in()) {
			return false;
		}
		if (current_user_can('manage_options')) {
			return true;
		}
		$roles = array('mad_team', 'mad_rnt', 'mad_ambassador', 'mad_tester', 'team', 'ambassador', 'field_tester');
		$user  = wp_get_current_user();
		foreach ($roles as $role) {
			if (in_array($role, (array) $user->roles, true)) {
				return true;
			}
		}
		return (bool) apply_filters('mbs_user_can_submit_team_reports', false, $user);
	}

	/**
	 * REST permission: logged in + valid customer (or admin).
	 *
	 * @return bool|WP_Error
	 */
	public static function rest_customer_permission() {
		if (! is_user_logged_in()) {
			return new WP_Error('mbs_unauthorized', __('You must be logged in.', 'mad-baits-session'), array('status' => 401));
		}
		if (! self::can_use_session_app()) {
			return new WP_Error(
				'mbs_forbidden',
				__('Session is available after you place a Mad Baits order on this account (pending or confirmed).', 'mad-baits-session'),
				array('status' => 403)
			);
		}
		return true;
	}
}
