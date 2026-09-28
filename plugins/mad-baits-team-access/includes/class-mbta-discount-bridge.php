<?php
/**
 * Discount Rules Pro targeting hooks (no pricing logic here).
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Discount_Bridge {
	/**
	 * @return void
	 */
	public static function init() {
		add_filter('woocommerce_post_class', array(__CLASS__, 'product_post_class'), 10, 2);
		add_filter('body_class', array(__CLASS__, 'discount_body_class'), 20);
	}

	/**
	 * @param string[]   $classes Classes.
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	public static function product_post_class($classes, $product) {
		if (! $product instanceof WC_Product) {
			return $classes;
		}
		$groups = MBTA_Roles::get_product_access_groups($product->get_id());
		foreach ($groups as $group) {
			$classes[] = 'mbta-access-' . sanitize_html_class($group);
		}
		if (is_user_logged_in()) {
			$role = MBTA_Roles::get_primary_role();
			if ('' !== $role) {
				$classes[] = 'mbta-viewer-' . sanitize_html_class($role);
				$classes[] = 'mad-baits-discount-viewer-' . sanitize_html_class($role);
			}
		}
		return $classes;
	}

	/**
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function discount_body_class($classes) {
		if (! is_user_logged_in()) {
			return $classes;
		}
		$role = MBTA_Roles::get_primary_role();
		if ('' === $role) {
			return $classes;
		}
		$classes[] = 'mad-baits-role-' . sanitize_html_class($role);
		return $classes;
	}
}
