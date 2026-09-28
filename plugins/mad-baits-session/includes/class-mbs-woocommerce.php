<?php
/**
 * WooCommerce integration — order products & reorder.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Woo helpers.
 */
class MBS_WooCommerce {

	/**
	 * @return void
	 */
	public static function init() {
		// REST handlers call these methods directly.
	}

	/**
	 * Products from customer's previous orders.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_customer_order_products($user_id = 0, $limit = 80) {
		if (! class_exists('WooCommerce')) {
			return array();
		}
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ($user_id < 1) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => MBS_Access::get_valid_order_statuses(),
				'limit'       => 30,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$seen    = array();
		$payload = array();

		foreach ($orders as $order) {
			if (! $order instanceof WC_Order) {
				continue;
			}
			foreach ($order->get_items() as $item) {
				if (! $item instanceof WC_Order_Item_Product) {
					continue;
				}
				$product_id = (int) $item->get_product_id();
				if ($product_id < 1 || isset($seen[ $product_id ])) {
					continue;
				}
				$product = wc_get_product($product_id);
				if (! $product || ! $product->is_purchasable()) {
					continue;
				}
				$seen[ $product_id ] = true;
				$payload[]           = array(
					'product_id'   => $product_id,
					'name'         => (string) $product->get_name(),
					'image'        => (string) wp_get_attachment_image_url((int) $product->get_image_id(), 'thumbnail'),
					'permalink'    => (string) get_permalink($product_id),
					'order_id'     => (int) $order->get_id(),
					'order_item_id'=> (int) $item->get_id(),
					'in_stock'     => $product->is_in_stock(),
					'price_html'   => (string) $product->get_price_html(),
				);
				if (count($payload) >= $limit) {
					break 2;
				}
			}
		}

		return $payload;
	}

	/**
	 * Add session bait products to cart.
	 *
	 * @param array<int, int> $product_qty Map product_id => qty.
	 * @return array<string, mixed>
	 */
	public static function reorder_products(array $product_qty) {
		if (! class_exists('WooCommerce') || ! WC()->cart) {
			return array(
				'added'       => array(),
				'unavailable' => array_keys($product_qty),
				'manual'      => array(),
			);
		}

		$added       = array();
		$unavailable = array();

		foreach ($product_qty as $product_id => $qty) {
			$product_id = absint($product_id);
			$qty        = max(1, absint($qty));
			if ($product_id < 1) {
				continue;
			}
			$product = wc_get_product($product_id);
			if (! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || $product->is_type('variable')) {
				$unavailable[] = $product_id;
				continue;
			}
			$key = WC()->cart->add_to_cart($product_id, $qty);
			if ($key) {
				$added[] = array(
					'product_id' => $product_id,
					'qty'        => $qty,
					'cart_key'   => $key,
				);
			} else {
				$unavailable[] = $product_id;
			}
		}

		return array(
			'added'       => $added,
			'unavailable' => $unavailable,
			'cart_url'    => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
		);
	}

	/**
	 * Collect purchasable product IDs from session bait_used.
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return array<int, int>
	 */
	public static function collect_reorder_map_from_session(array $session_data) {
		$map = array();
		$bait = isset($session_data['bait_used']) && is_array($session_data['bait_used']) ? $session_data['bait_used'] : array();
		foreach ($bait as $row) {
			if (! is_array($row)) {
				continue;
			}
			$pid = isset($row['product_id']) ? absint($row['product_id']) : 0;
			if ($pid < 1) {
				continue;
			}
			$qty = isset($row['amount_taken']) ? max(1, absint($row['amount_taken'])) : 1;
			if (! isset($map[ $pid ])) {
				$map[ $pid ] = $qty;
			} else {
				$map[ $pid ] += $qty;
			}
		}
		return $map;
	}
}
