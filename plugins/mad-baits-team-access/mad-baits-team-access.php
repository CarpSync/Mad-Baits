<?php
/**
 * Plugin Name: Mad Baits Team Access
 * Description: Invite-only team, RNT, ambassador and tester access with private products, portal and role push.
 * Version: 1.1.3
 * Author: Mad Baits
 * Text Domain: mad-baits-team-access
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('MBTA_VERSION', '1.1.3');
define('MBTA_PATH', plugin_dir_path(__FILE__));
define('MBTA_URL', plugin_dir_url(__FILE__));

require_once MBTA_PATH . 'includes/class-mbta-plugin.php';

/**
 * Bootstrap plugin.
 */
function mbta_bootstrap() {
	MBTA_Plugin::instance()->init();
}
add_action('plugins_loaded', 'mbta_bootstrap', 20);

register_activation_hook(__FILE__, array('MBTA_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('MBTA_Plugin', 'deactivate'));
