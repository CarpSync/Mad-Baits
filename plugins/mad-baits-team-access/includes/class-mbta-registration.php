<?php
/**
 * Invite registration and rewrite rules.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Registration {
	/**
	 * @return void
	 */
	public static function init() {
		add_action('init', array(__CLASS__, 'register_rewrite_rules'));
		add_filter('query_vars', array(__CLASS__, 'query_vars'));
		add_filter('template_include', array(__CLASS__, 'template_include'));
		add_action('template_redirect', array(__CLASS__, 'handle_post'));
		add_shortcode('mbta_team_invite', array(__CLASS__, 'shortcode_invite'));
	}

	/**
	 * @return void
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule('^team-invite/?$', 'index.php?mbta_team_invite=1', 'top');
	}

	/**
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars($vars) {
		$vars[] = 'mbta_team_invite';
		return $vars;
	}

	/**
	 * @param string $template Template.
	 * @return string
	 */
	public static function template_include($template) {
		if (! get_query_var('mbta_team_invite')) {
			return $template;
		}
		$custom = MBTA_PATH . 'templates/invite-register.php';
		return file_exists($custom) ? $custom : $template;
	}

	/**
	 * Handle registration POST.
	 */
	public static function handle_post() {
		if (! get_query_var('mbta_team_invite') && ! is_page(MBTA_Portal::get_invite_page_id())) {
			return;
		}
		if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
			return;
		}
		if (! isset($_POST['mbta_register_submit'])) {
			return;
		}
		check_admin_referer('mbta_register', 'mbta_register_nonce');

		$code = isset($_POST['invite_code']) ? MBTA_Invites::normalize_code(wp_unslash((string) $_POST['invite_code'])) : '';
		if ('' === $code && isset($_GET['code'])) {
			$code = MBTA_Invites::normalize_code(wp_unslash((string) $_GET['code']));
		}

		$invite = MBTA_Invites::get_by_code($code);
		$error  = $invite ? MBTA_Invites::validate_invite($invite) : __('Invalid invite code.', 'mad-baits-team-access');
		if ('' !== $error) {
			self::redirect_with_error($error, $code);
		}

		$first   = sanitize_text_field(wp_unslash((string) ($_POST['first_name'] ?? '')));
		$last    = sanitize_text_field(wp_unslash((string) ($_POST['last_name'] ?? '')));
		$email   = sanitize_email(wp_unslash((string) ($_POST['email'] ?? '')));
		$pass    = (string) ($_POST['password'] ?? '');
		$phone   = sanitize_text_field(wp_unslash((string) ($_POST['phone'] ?? '')));
		$insta   = sanitize_text_field(wp_unslash((string) ($_POST['instagram'] ?? '')));
		$role    = sanitize_key((string) ($invite['role'] ?? ''));

		if ('' === $first || '' === $last || ! is_email($email) || strlen($pass) < 8) {
			self::redirect_with_error(__('Please complete all required fields (password min 8 characters).', 'mad-baits-team-access'), $code);
		}
		if (email_exists($email)) {
			self::redirect_with_error(__('That email is already registered.', 'mad-baits-team-access'), $code);
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim($first . ' ' . $last),
				'role'         => $role,
			)
		);

		if (is_wp_error($user_id)) {
			self::redirect_with_error($user_id->get_error_message(), $code);
		}

		if ('' !== $phone) {
			update_user_meta($user_id, 'billing_phone', $phone);
			update_user_meta($user_id, 'mbta_phone', $phone);
		}
		if ('' !== $insta) {
			update_user_meta($user_id, 'mbta_instagram', $insta);
		}
		update_user_meta($user_id, 'mbta_invite_code', $code);

		MBTA_Invites::increment_usage((int) $invite['id']);
		do_action('mbta_user_registered', $user_id, $role);

		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true);

		wp_safe_redirect(MBTA_Portal::get_portal_url());
		exit;
	}

	/**
	 * @param string $message Error.
	 * @param string $code    Code.
	 */
	private static function redirect_with_error($message, $code) {
		$url = add_query_arg(
			array(
				'code'  => rawurlencode($code),
				'error' => rawurlencode($message),
			),
			home_url('/team-invite/')
		);
		wp_safe_redirect($url);
		exit;
	}

	/**
	 * @return string
	 */
	public static function shortcode_invite() {
		ob_start();
		include MBTA_PATH . 'templates/partials/invite-form.php';
		return (string) ob_get_clean();
	}
}
