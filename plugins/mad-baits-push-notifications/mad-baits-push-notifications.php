<?php
/**
 * Plugin Name: Mad Baits Push Notifications
 * Description: Web Push notifications for Mad Baits PWA with admin send portal.
 * Version: 1.0.18
 * Author: Mad Baits
 * Text Domain: mad-baits-push
 */

if (! defined('ABSPATH')) {
	exit;
}

define('MAD_BAITS_PUSH_VERSION', '1.0.18');
define('MAD_BAITS_PUSH_PATH', plugin_dir_path(__FILE__));
define('MAD_BAITS_PUSH_URL', plugin_dir_url(__FILE__));

$mad_baits_push_autoload = MAD_BAITS_PUSH_PATH . 'vendor/autoload.php';
if (file_exists($mad_baits_push_autoload)) {
	require_once $mad_baits_push_autoload;
}

require_once MAD_BAITS_PUSH_PATH . 'includes/class-mad-push-db.php';
require_once MAD_BAITS_PUSH_PATH . 'includes/class-mad-push-sender.php';
require_once MAD_BAITS_PUSH_PATH . 'includes/class-mad-push-rest.php';
require_once MAD_BAITS_PUSH_PATH . 'includes/class-mad-push-admin.php';

/**
 * Bootstrap plugin.
 *
 * @return void
 */
function mad_baits_push_bootstrap() {
	Mad_Baits_Push_DB::init();
	Mad_Baits_Push_REST::init();
	Mad_Baits_Push_Admin::init();

	add_action('wp_enqueue_scripts', 'mad_baits_push_enqueue_frontend_assets');
}
add_action('plugins_loaded', 'mad_baits_push_bootstrap');

/**
 * Future WooCommerce push hooks scaffold.
 * Keep disabled until notification logic is explicitly approved for order events.
 */
if (! defined('MAD_BAITS_PUSH_ENABLE_WOO_HOOKS')) {
	define('MAD_BAITS_PUSH_ENABLE_WOO_HOOKS', false);
}
// if (MAD_BAITS_PUSH_ENABLE_WOO_HOOKS) {
// 	add_action('woocommerce_order_status_completed', 'mad_baits_push_on_order_completed', 10, 1);
// 	add_action('woocommerce_order_status_processing', 'mad_baits_push_on_order_dispatched', 10, 1);
// 	add_action('woocommerce_product_set_stock_status', 'mad_baits_push_on_back_in_stock', 10, 3);
// }

/**
 * Activate plugin and create DB schema.
 *
 * @return void
 */
function mad_baits_push_activate() {
	Mad_Baits_Push_DB::create_tables();
}
register_activation_hook(__FILE__, 'mad_baits_push_activate');

/**
 * Resolve cache-busting asset version for plugin files.
 *
 * @param string $relative_path Relative plugin path.
 * @return string
 */
function mad_baits_push_asset_version($relative_path) {
	$relative_path = ltrim((string) $relative_path, '/');
	$full_path     = MAD_BAITS_PUSH_PATH . $relative_path;

	if (file_exists($full_path)) {
		return (string) filemtime($full_path);
	}

	return MAD_BAITS_PUSH_VERSION;
}

/**
 * Enqueue frontend prompt assets.
 *
 * @return void
 */
function mad_baits_push_enqueue_frontend_assets() {
	if (is_admin()) {
		return;
	}

	wp_enqueue_style(
		'mad-baits-push',
		MAD_BAITS_PUSH_URL . 'assets/css/mad-push.css',
		array(),
		mad_baits_push_asset_version('assets/css/mad-push.css')
	);

	wp_enqueue_script(
		'mad-baits-push',
		MAD_BAITS_PUSH_URL . 'assets/js/mad-push.js',
		array(),
		mad_baits_push_asset_version('assets/js/mad-push.js'),
		true
	);

	$public_key = Mad_Baits_Push_Admin::get_public_vapid_key();
	$service_worker_url = home_url('/?mad_baits_pwa=service-worker');

	wp_localize_script(
		'mad-baits-push',
		'madBaitsPushConfig',
		array(
			'restRoot'         => esc_url_raw(rest_url('mad-baits/v1/push/')),
			'restNonce'        => wp_create_nonce('wp_rest'),
			'publicVapidKey'   => $public_key,
			'serviceWorkerUrl' => esc_url_raw($service_worker_url),
			'shopUrl'          => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
			'isLoggedIn'       => is_user_logged_in(),
			'dismissKey'       => 'madBaitsPushPromptDismissedAt',
		)
	);
}
