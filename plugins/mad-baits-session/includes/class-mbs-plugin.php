<?php
/**
 * Session plugin bootstrap.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Core plugin.
 */
final class MBS_Plugin {

	const OPTION_SETTINGS = 'mbs_settings';

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function init() {
		MBS_CPT::init();
		MBS_Access::init();
		MBS_REST::init();
		MBS_Admin::init();
		MBS_WooCommerce::init();
		MBS_Weather::init();
		MBS_Insights::init();
		MBS_Reminders::init();

		add_action('init', array($this, 'maybe_upgrade_settings'), 15);
		add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'), 30);
		add_filter('mad_baits_session_app_is_coming_soon', array($this, 'filter_fishing_log_coming_soon'), 20);
		add_filter('mad_baits_should_hide_bottom_app_nav', array($this, 'maybe_hide_nav_on_session_flow'), 20);
	}

	/**
	 * Disable theme coming-soon when Session app is enabled.
	 *
	 * @param bool $coming_soon Current value.
	 * @return bool
	 */
	public function filter_fishing_log_coming_soon($coming_soon) {
		if (! self::is_enabled()) {
			return $coming_soon;
		}
		return false;
	}

	/**
	 * @return void
	 */
	public static function activate() {
		MBS_CPT::register_post_types();
		flush_rewrite_rules();
		if (! get_option(self::OPTION_SETTINGS)) {
			update_option(self::OPTION_SETTINGS, MBS_Admin::default_settings());
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$defaults = MBS_Admin::default_settings();
		$stored   = get_option(self::OPTION_SETTINGS, array());
		if (! is_array($stored)) {
			$stored = array();
		}
		return array_merge($defaults, $stored);
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_setting($key, $default = null) {
		$settings = self::get_settings();
		return array_key_exists($key, $settings) ? $settings[ $key ] : $default;
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === self::get_setting('enabled', 'yes');
	}

	/**
	 * @return bool
	 */
	public static function is_mobile_only_enforced() {
		return 'yes' === self::get_setting('mobile_only', 'yes');
	}

	/**
	 * @return string
	 */
	public static function get_session_page_url() {
		if (function_exists('mad_baits_get_page_url')) {
			return mad_baits_get_page_url('session', 'session');
		}
		return home_url('/session/');
	}

	/**
	 * @return bool
	 */
	public static function is_session_app_request() {
		if (is_admin()) {
			return false;
		}
		if (function_exists('mad_baits_is_session_app_request')) {
			return mad_baits_is_session_app_request();
		}
		$path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
		if (! is_string($path)) {
			return false;
		}
		$path = trim($path, '/');
		return 'session' === $path || preg_match('#(^|/)session(/|$)#', $path);
	}

	/**
	 * @return bool
	 */
	public static function is_mobile_viewport_context() {
		if (function_exists('wp_is_mobile') && wp_is_mobile()) {
			return true;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
		return (bool) preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i', $ua);
	}

	/**
	 * Merge pending into legacy Session order-status settings.
	 *
	 * @return void
	 */
	public function maybe_upgrade_settings() {
		$stored = get_option(self::OPTION_SETTINGS, array());
		if (! is_array($stored)) {
			return;
		}
		$statuses = isset($stored['order_statuses']) ? (string) $stored['order_statuses'] : '';
		if ('processing,completed' === $statuses) {
			$stored['order_statuses'] = 'pending,processing,completed';
			update_option(self::OPTION_SETTINGS, $stored, false);
		}
	}

	/**
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if (! self::is_session_app_request()) {
			return;
		}

		$css = MBS_PATH . 'assets/css/mbs-session-app.css';
		$js  = MBS_PATH . 'assets/js/mbs-session-app.js';
		if (file_exists($css)) {
			wp_enqueue_style(
				'mad-baits-session-app',
				MBS_URL . 'assets/css/mbs-session-app.css',
				array(),
				(string) filemtime($css)
			);
		}
		if (file_exists($js)) {
			wp_enqueue_script(
				'mad-baits-session-app',
				MBS_URL . 'assets/js/mbs-session-app.js',
				array(),
				(string) filemtime($js),
				true
			);
			wp_localize_script('mad-baits-session-app', 'mbsSessionConfig', MBS_REST::get_client_config());
		}

		add_filter('body_class', static function ($classes) {
			$classes[] = 'mbs-session-app-page';
			$classes[] = 'is-mobile-app-shell';
			if (MBS_Plugin::is_mobile_viewport_context()) {
				$classes[] = 'mbs-session-mobile-context';
			}
			return $classes;
		});
	}

	/**
	 * Hide bottom nav on deep session flows (modals handled in CSS).
	 *
	 * @param bool $hide Current hide state.
	 * @return bool
	 */
	public function maybe_hide_nav_on_session_flow($hide) {
		return $hide;
	}
}

require_once MBS_PATH . 'includes/class-mbs-cpt.php';
require_once MBS_PATH . 'includes/class-mbs-access.php';
require_once MBS_PATH . 'includes/class-mbs-meta.php';
require_once MBS_PATH . 'includes/class-mbs-rest.php';
require_once MBS_PATH . 'includes/class-mbs-admin.php';
require_once MBS_PATH . 'includes/class-mbs-woocommerce.php';
require_once MBS_PATH . 'includes/class-mbs-weather.php';
require_once MBS_PATH . 'includes/class-mbs-insights.php';
require_once MBS_PATH . 'includes/class-mbs-reminders.php';
require_once MBS_PATH . 'includes/class-mbs-catch-reports.php';
require_once MBS_PATH . 'includes/class-mbs-companion.php';
require_once MBS_PATH . 'includes/mbs-bait-product-map.php';
