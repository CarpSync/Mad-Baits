<?php
/**
 * Per-range product order storage.
 *
 * @package MadBaitsProductOrganiser
 */

defined('ABSPATH') || exit;

class MBPO_Order {
	const OPTION_ORDERS = 'mbpo_range_product_orders';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter('mad_baits_range_tag_product_ids', array(__CLASS__, 'filter_range_product_ids'), 10, 2);
	}

	/**
	 * @return void
	 */
	public static function activate() {
		if (! get_option(self::OPTION_ORDERS)) {
			add_option(self::OPTION_ORDERS, array(), '', false);
		}
	}

	/**
	 * @return array<string, int[]>
	 */
	public static function get_all_orders() {
		$stored = get_option(self::OPTION_ORDERS, array());
		return is_array($stored) ? $stored : array();
	}

	/**
	 * @param string $range_slug Range slug.
	 * @return int[]
	 */
	public static function get_saved_order($range_slug) {
		$range_slug = MBPO_Ranges::normalize_range_slug($range_slug);
		$all      = self::get_all_orders();
		$ids      = isset($all[ $range_slug ]) ? (array) $all[ $range_slug ] : array();
		return array_values(array_unique(array_filter(array_map('absint', $ids))));
	}

	/**
	 * @param string $range_slug  Range slug.
	 * @param int[]  $product_ids Product IDs in order.
	 * @return int Number of products saved in order.
	 */
	public static function save_order($range_slug, array $product_ids) {
		$range_slug  = MBPO_Ranges::normalize_range_slug($range_slug);
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));

		if ('' === $range_slug) {
			return 0;
		}

		$allowed = array_flip(MBPO_Ranges::get_product_ids_for_range($range_slug, false));
		$clean   = array();
		foreach ($product_ids as $product_id) {
			if (isset($allowed[ $product_id ])) {
				$clean[] = $product_id;
			}
		}

		$all                 = self::get_all_orders();
		$all[ $range_slug ] = $clean;

		update_option(self::OPTION_ORDERS, $all, false);

		return count($clean);
	}

	/**
	 * @param string $range_slug Range slug.
	 * @param int[]  $product_ids Unordered IDs.
	 * @return int[]
	 */
	public static function sort_ids_by_saved_order($range_slug, array $product_ids) {
		$saved = self::get_saved_order($range_slug);
		if (empty($saved)) {
			usort(
				$product_ids,
				static function ($a, $b) {
					$order_a = (int) get_post_field('menu_order', $a);
					$order_b = (int) get_post_field('menu_order', $b);
					if ($order_a === $order_b) {
						return strcasecmp((string) get_the_title($a), (string) get_the_title($b));
					}
					return $order_a <=> $order_b;
				}
			);
			return array_values($product_ids);
		}

		$product_ids = array_values(array_unique(array_map('absint', $product_ids)));
		$flipped     = array_flip($product_ids);
		$ordered     = array();

		foreach ($saved as $product_id) {
			if (isset($flipped[ $product_id ])) {
				$ordered[] = $product_id;
				unset($flipped[ $product_id ]);
			}
		}

		$remaining = array_keys($flipped);
		usort(
			$remaining,
			static function ($a, $b) {
				return strcasecmp((string) get_the_title($a), (string) get_the_title($b));
			}
		);

		return array_merge($ordered, $remaining);
	}

	/**
	 * Filter hook for theme range product IDs.
	 *
	 * @param int[]  $ids        Product IDs.
	 * @param string $range_slug Range slug.
	 * @return int[]
	 */
	public static function filter_range_product_ids($ids, $range_slug) {
		unset($ids);
		return MBPO_Ranges::get_product_ids_for_range($range_slug, true);
	}
}
