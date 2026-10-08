<?php
/**
 * Plugin Name: Mad Baits Bundle Builder
 * Description: Premium mobile-first bundle builder for Mad Baits WooCommerce bundle products.
 * Version: 1.12.1
 * Author: Mad Baits
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: mad-baits-bundle-builder
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

define('MBBB_VERSION', '1.12.1');
define('MBBB_PLUGIN_FILE', __FILE__);
define('MBBB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MBBB_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Bootstrap plugin.
 */
function mbbb_init() {
	if (! class_exists('WooCommerce', false)) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__('Mad Baits Bundle Builder requires WooCommerce.', 'mad-baits-bundle-builder') . '</p></div>';
			}
		);
		return;
	}

	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-plugin.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-presets.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-cart.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-frontend.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-admin.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-migrator.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-admin-setup.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-deal-builder.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-pool-options.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-deals-admin.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-config.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-pricing.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-eligibility.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-compiler.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-validator.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-legacy.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-repository.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-service.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-runtime.php';
	require_once MBBB_PLUGIN_DIR . 'includes/bundle-manager/class-mbbb-bundle-admin.php';

	if (false === get_option(MBBB_Plugin::OPTION_SETTINGS, false)) {
		MBBB_Plugin::instance()->save_global_settings(MBBB_Plugin::default_global_settings());
	}

	(new MBBB_Cart())->init();
	(new MBBB_Frontend())->init();
	MBBB_Bundle_Runtime::init();

	if (is_admin()) {
		(new MBBB_Admin())->init();
		MBBB_Pool_Options::init();
		(new MBBB_Deals_Admin())->init();
		(new MBBB_Bundle_Admin())->init();
	}
}
add_action('plugins_loaded', 'mbbb_init', 20);

/**
 * Activation: seed global pools.
 */
function mbbb_activate() {
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-plugin.php';
	require_once MBBB_PLUGIN_DIR . 'includes/class-mbbb-presets.php';
	MBBB_Presets::install_default_pools();

	if (false === get_option(MBBB_Plugin::OPTION_SETTINGS, false)) {
		MBBB_Plugin::instance()->save_global_settings(MBBB_Plugin::default_global_settings());
	}
}
register_activation_hook(__FILE__, 'mbbb_activate');
