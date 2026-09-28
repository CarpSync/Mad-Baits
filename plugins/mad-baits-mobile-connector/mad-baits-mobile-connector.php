<?php
/**
 * Plugin Name:       Mad Baits Mobile Connector
 * Plugin URI:        https://madbaits.com
 * Description:       Secure REST API endpoints for the Mad Baits V2 mobile app — events, devices, feedback, team reports, push sending, JWT auth, and account reads.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mad Baits
 * Author URI:        https://madbaits.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mad-baits-mobile-connector
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

define( 'MBMC_VERSION', '2.0.0' );
define( 'MBMC_PLUGIN_FILE', __FILE__ );
define( 'MBMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MBMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MBMC_PLUGIN_DIR . 'includes/helpers.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-db.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-push-provider.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-expo-push-provider.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-notification-service.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-jwt.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-woocommerce.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-native-checkout.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-loyalty-config.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-loyalty-eligibility.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-loyalty.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-loyalty-order.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-achievements.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-admin-loyalty.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-cpt-events.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-events.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-devices.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-feedback.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-team.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-diary.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-achievements.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-fisheries.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-catches.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-auth.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-account.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-notifications.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-ai.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-rest-loyalty.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-fisheries-seed.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-admin.php';
require_once MBMC_PLUGIN_DIR . 'includes/class-mbmc-plugin.php';

/**
 * Bootstrap plugin.
 *
 * @return MBMC_Plugin
 */
function mbmc_plugin() {
	return MBMC_Plugin::instance();
}

add_action( 'plugins_loaded', 'mbmc_plugin' );
add_action( 'plugins_loaded', array( 'MBMC_DB', 'maybe_upgrade' ), 5 );

register_activation_hook(
	__FILE__,
	static function () {
		MBMC_DB::install();
		$events = new MBMC_CPT_Events();
		$events->register_post_type();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
