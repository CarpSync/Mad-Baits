<?php
/**
 * Plugin Name: Mad Baits Session
 * Description: Private mobile fishing logbook — sessions, catches, weather, bait tracking and insights for Mad Baits customers.
 * Version: 1.1.0
 * Author: Mad Baits
 * Text Domain: mad-baits-session
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('MBS_VERSION', '1.1.0');
define('MBS_PATH', plugin_dir_path(__FILE__));
define('MBS_URL', plugin_dir_url(__FILE__));
define('MBS_REST_NAMESPACE', 'mad-baits/v1');

require_once MBS_PATH . 'includes/class-mbs-plugin.php';

/**
 * Bootstrap plugin.
 */
function mbs_bootstrap() {
	MBS_Plugin::instance()->init();
}
add_action('plugins_loaded', 'mbs_bootstrap', 25);

register_activation_hook(__FILE__, array('MBS_Plugin', 'activate'));
