<?php
/**
 * Plugin bootstrap.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

final class MBTA_Plugin {
	/** @var self|null */
	private static $instance = null;

	/**
	 * Singleton.
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load modules.
	 */
	public function init() {
		require_once MBTA_PATH . 'includes/class-mbta-roles.php';
		require_once MBTA_PATH . 'includes/class-mbta-db.php';
		require_once MBTA_PATH . 'includes/class-mbta-invites.php';
		require_once MBTA_PATH . 'includes/class-mbta-invite-mail.php';
		require_once MBTA_PATH . 'includes/class-mbta-registration.php';
		require_once MBTA_PATH . 'includes/class-mbta-products.php';
		require_once MBTA_PATH . 'includes/class-mbta-portal.php';
		require_once MBTA_PATH . 'includes/class-mbta-team-tester-range.php';
		require_once MBTA_PATH . 'includes/class-mbta-push.php';
		require_once MBTA_PATH . 'includes/class-mbta-discount-bridge.php';
		require_once MBTA_PATH . 'includes/class-mbta-admin.php';

		MBTA_Roles::init();
		MBTA_DB::init();
		MBTA_Invites::init();
		MBTA_Registration::init();
		MBTA_Products::init();
		MBTA_Portal::init();
		MBTA_Team_Tester_Range::init();
		MBTA_Push::init();
		MBTA_Discount_Bridge::init();
		MBTA_Admin::init();
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		require_once MBTA_PATH . 'includes/class-mbta-roles.php';
		require_once MBTA_PATH . 'includes/class-mbta-db.php';
		require_once MBTA_PATH . 'includes/class-mbta-registration.php';
		require_once MBTA_PATH . 'includes/class-mbta-portal.php';
		require_once MBTA_PATH . 'includes/class-mbta-team-tester-range.php';

		MBTA_Roles::register_roles();
		MBTA_DB::create_tables();
		MBTA_Registration::register_rewrite_rules();
		MBTA_Portal::ensure_pages();
		MBTA_Team_Tester_Range::ensure_page();
		flush_rewrite_rules();
		update_option('mbta_flush_rewrite', '1');
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
