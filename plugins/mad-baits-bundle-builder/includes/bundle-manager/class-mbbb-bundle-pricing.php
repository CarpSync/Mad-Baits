<?php
/**
 * Bundle price summaries and calculations.
 *
 * Fixed prices stay on the WooCommerce product. Percentage and fixed
 * discounts are applied from the chosen products at cart time.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Pricing helper.
 */
final class MBBB_Bundle_Pricing {

	/**
	 * @param array<string, mixed> $config Owner config.
	 * @return string
	 */
	public static function summary(array $config) {
		$pricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : array();
		$mode    = (string) ($pricing['mode'] ?? 'fixed');

		if ('percent' === $mode) {
			return sprintf(
				/* translators: %d: percent */
				__('Customer receives: %d%% discount', 'mad-baits-bundle-builder'),
				(int) ($pricing['percent'] ?? 0)
			);
		}

		if ('amount' === $mode) {
			return sprintf(
				/* translators: %s: money amount */
				__('Customer receives: %s off', 'mad-baits-bundle-builder'),
				self::format_money($pricing['amount'] ?? 0)
			);
		}

		return sprintf(
			/* translators: %s: bundle price */
			__('Customer pays: %s', 'mad-baits-bundle-builder'),
			self::format_money($pricing['fixed_price'] ?? 0)
		);
	}

	/**
	 * @param array<string, mixed> $config            Owner config.
	 * @param array<int, float>    $component_prices  Chosen product prices.
	 * @return array{base: float, total: float, mode: string}
	 */
	public static function calculate(array $config, array $component_prices = array()) {
		$pricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : array();
		$mode    = (string) ($pricing['mode'] ?? 'fixed');
		$base    = 0.0;

		foreach ($component_prices as $price) {
			$base += (float) $price;
		}
		$base = round($base, 2);

		if ('percent' === $mode) {
			$percent = max(0, min(100, (float) ($pricing['percent'] ?? 0)));
			$total   = round($base * (1 - ($percent / 100)), 2);
		} elseif ('amount' === $mode) {
			$total = round(max(0, $base - (float) ($pricing['amount'] ?? 0)), 2);
		} else {
			$total = round((float) ($pricing['fixed_price'] ?? 0), 2);
			$base  = $total;
		}

		return array(
			'base'  => $base,
			'total' => $total,
			'mode'  => $mode,
		);
	}

	/**
	 * Unit price for one bundle.
	 *
	 * Returns null when the cart price must be left alone: fixed-price bundles
	 * use the WooCommerce parent price, and a discount is skipped when any
	 * chosen product price cannot be resolved. The same component prices always
	 * produce the same unit price, so repeating cart calculation cannot stack
	 * the discount. Cart quantity is applied by WooCommerce after this price.
	 *
	 * @param array<string, mixed> $config            Owner config.
	 * @param array<int, mixed>    $component_prices  One price per chosen product, in choice order.
	 * @param int                  $selected_count    Choices the customer actually made.
	 * @return float|null
	 */
	public static function safe_unit_price(array $config, array $component_prices, $selected_count) {
		$pricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : array();
		$mode    = (string) ($pricing['mode'] ?? 'fixed');
		if ('percent' !== $mode && 'amount' !== $mode) {
			return null;
		}

		$selected_count = (int) $selected_count;
		if ($selected_count < 1 || count($component_prices) !== $selected_count) {
			return null;
		}

		$clean = array();
		foreach ($component_prices as $price) {
			if (null === $price || ! is_numeric($price)) {
				return null;
			}
			$clean[] = round((float) $price, 2);
		}

		return self::calculate($config, $clean)['total'];
	}

	/**
	 * Line total for several of the same bundle. The discount stays inside the unit price.
	 *
	 * @param float $unit_price Unit price.
	 * @param int   $quantity   Cart quantity.
	 * @return float
	 */
	public static function line_total($unit_price, $quantity) {
		$quantity = max(1, (int) $quantity);
		return round(((float) $unit_price) * $quantity, 2);
	}

	/**
	 * Stable catalogue price for a chosen product.
	 *
	 * Uses the stored regular price so cart price filters, coupons, and a
	 * previous set_price() cannot become the base of another discount.
	 *
	 * @param object $product Product.
	 * @return float|null
	 */
	public static function component_base_price($product) {
		if (! is_object($product) || ! method_exists($product, 'get_regular_price')) {
			return null;
		}

		$sale = method_exists($product, 'get_sale_price') ? $product->get_sale_price('edit') : '';
		if (is_numeric($sale) && '' !== (string) $sale) {
			return round((float) $sale, 2);
		}

		$regular = $product->get_regular_price('edit');
		if (is_numeric($regular) && '' !== (string) $regular) {
			return round((float) $regular, 2);
		}

		return null;
	}

	/**
	 * @param mixed $amount Amount.
	 * @return string
	 */
	public static function format_money($amount) {
		if ('' === (string) $amount) {
			$amount = 0;
		}
		if (function_exists('wc_price')) {
			return html_entity_decode(wp_strip_all_tags(wc_price((float) $amount)), ENT_QUOTES, 'UTF-8');
		}

		return '£' . number_format((float) $amount, 2, '.', '');
	}

	/**
	 * Whether every slot choice points at a priced WooCommerce product.
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return bool
	 */
	public static function slots_have_priced_choices(array $slots) {
		if (empty($slots)) {
			return false;
		}
		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				return false;
			}
			$options = isset($slot['manual_options']) && is_array($slot['manual_options']) ? $slot['manual_options'] : array();
			if (empty($options)) {
				return false;
			}
			foreach ($options as $option) {
				if (! is_array($option) || absint($option['product_id'] ?? 0) < 1) {
					return false;
				}
			}
		}
		return true;
	}
}
