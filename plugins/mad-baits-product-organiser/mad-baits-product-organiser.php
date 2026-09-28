<?php
/**
 * Plugin Name: Mad Baits Product Organiser
 * Description: Range-based product discovery, exact type badges, and per-range drag-and-drop ordering for Mad Baits.
 * Version: 1.0.4
 * Author: Mad Baits
 * Text Domain: mad-baits-product-organiser
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

define('MBPO_VERSION', '1.0.4');
define('MBPO_PATH', plugin_dir_path(__FILE__));
define('MBPO_URL', plugin_dir_url(__FILE__));

require_once MBPO_PATH . 'includes/class-mbpo-ranges.php';
require_once MBPO_PATH . 'includes/class-mbpo-product-types.php';
require_once MBPO_PATH . 'includes/class-mbpo-sections.php';
require_once MBPO_PATH . 'includes/class-mbpo-product-tags.php';
require_once MBPO_PATH . 'includes/class-mbpo-order.php';
require_once MBPO_PATH . 'includes/class-mbpo-admin.php';

/**
 * Bootstrap plugin.
 */
function mbpo_bootstrap() {
	if (! class_exists('WooCommerce')) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__('Mad Baits Product Organiser requires WooCommerce.', 'mad-baits-product-organiser') . '</p></div>';
			}
		);
		return;
	}

	MBPO_Admin::init();
	MBPO_Order::init();
}
add_action('plugins_loaded', 'mbpo_bootstrap', 15);

register_activation_hook(__FILE__, array('MBPO_Order', 'activate'));

/**
 * Public API: product IDs for a range (ordered).
 *
 * @param string $range_slug Range slug.
 * @return int[]
 */
function mbpo_get_range_product_ids($range_slug) {
	return MBPO_Ranges::get_product_ids_for_range($range_slug);
}

/**
 * Public API: resolve product type section key for frontend grouping.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mbpo_resolve_product_section_key($product_id) {
	return MBPO_Sections::resolve_section_key($product_id);
}

/**
 * Public API: admin display label for product type.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mbpo_get_product_type_label($product_id) {
	return MBPO_Product_Types::get_display_label($product_id);
}

/**
 * Public API: human-readable classification reason (admin debug).
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mbpo_get_classification_reason($product_id) {
	return MBPO_Product_Types::get_classification_reason($product_id);
}
