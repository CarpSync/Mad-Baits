<?php
/**
 * Storefront behaviour for bundles created in the Bundle Manager.
 *
 * Existing bundles without owner settings are left untouched. These hooks do
 * not read or write WooCommerce orders, so HPOS order storage is unchanged.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Cart, stock, and schedule runtime.
 */
final class MBBB_Bundle_Runtime {

	/**
	 * Stops a nested cart calculation from applying prices twice.
	 *
	 * @var bool
	 */
	private static $applying_prices = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter('woocommerce_is_purchasable', array(__CLASS__, 'filter_purchasable'), 20, 2);
		add_filter('woocommerce_product_is_visible', array(__CLASS__, 'filter_visible'), 20, 2);
		add_filter('mbbb_slot_options', array(__CLASS__, 'filter_slot_options'), 20, 3);
		add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'apply_cart_prices'), 30);
		add_action('woocommerce_check_cart_items', array(__CLASS__, 'guard_cart_items'), 20);
		add_filter('woocommerce_get_price_html', array(__CLASS__, 'filter_price_html'), 20, 2);
	}

	/**
	 * @param bool       $purchasable Current flag.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public static function filter_purchasable($purchasable, $product) {
		if (! $product instanceof WC_Product) {
			return $purchasable;
		}
		$config = self::config_for($product->get_id());
		if (null === $config) {
			return $purchasable;
		}
		if (! MBBB_Bundle_Config::is_purchasable($config, self::today())) {
			return false;
		}
		if (self::blocks_for_stock($config, $product->get_id())) {
			return false;
		}
		return $purchasable;
	}

	/**
	 * @param bool $visible    Current flag.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function filter_visible($visible, $product_id) {
		$config = self::config_for(absint($product_id));
		if (null === $config) {
			return $visible;
		}
		if ('active' !== MBBB_Bundle_Config::effective_status($config, self::today())) {
			return false;
		}
		return $visible;
	}

	/**
	 * Hide out-of-stock choices for manager bundles.
	 *
	 * @param array<int, array<string, mixed>> $options    Options.
	 * @param array<string, mixed>             $slot       Slot.
	 * @param int                              $product_id Bundle ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_slot_options($options, $slot, $product_id) {
		unset($slot);
		$config = self::config_for(absint($product_id));
		if (null === $config || ! empty($config['preserve_slots']) || ! is_array($options)) {
			return $options;
		}
		$stock = isset($config['stock']) && is_array($config['stock']) ? $config['stock'] : array();
		if (empty($stock['hide_unavailable']) && empty($stock['prevent_oos'])) {
			return $options;
		}

		$filtered = array();
		foreach ($options as $option) {
			if (! is_array($option)) {
				continue;
			}
			$option_id = absint($option['product_id'] ?? 0);
			if ($option_id < 1 || ! function_exists('wc_get_product')) {
				$filtered[] = $option;
				continue;
			}
			$product = wc_get_product($option_id);
			if ($product instanceof WC_Product && $product->is_purchasable() && $product->is_in_stock()) {
				$filtered[] = $option;
			}
		}
		return $filtered;
	}

	/**
	 * @param WC_Cart $cart Cart.
	 * @return void
	 */
	public static function apply_cart_prices($cart) {
		if (self::$applying_prices || ! is_object($cart) || ! method_exists($cart, 'get_cart')) {
			return;
		}
		if (function_exists('is_admin') && is_admin() && ! (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
			return;
		}

		self::$applying_prices = true;
		try {
			foreach ($cart->get_cart() as $item) {
				if (empty($item['data']) || ! $item['data'] instanceof WC_Product) {
					continue;
				}
				$product_id = absint($item['product_id'] ?? $item['data']->get_id());
				$config     = self::config_for($product_id);
				if (null === $config) {
					continue;
				}
				$mode = (string) ($config['pricing']['mode'] ?? 'fixed');
				if ('fixed' === $mode) {
					if (! empty($config['preserve_slots'])) {
						continue;
					}
					$fixed = $config['pricing']['fixed_price'] ?? '';
					if (! is_numeric($fixed) || (float) $fixed <= 0) {
						continue;
					}
					$item['data']->set_price(round((float) $fixed, 2));
					continue;
				}
				$posted = array();
				if (class_exists('MBBB_Plugin') && ! empty($item[ MBBB_Plugin::CART_META_KEY ]['raw']) && is_array($item[ MBBB_Plugin::CART_META_KEY ]['raw'])) {
					$posted = $item[ MBBB_Plugin::CART_META_KEY ]['raw'];
				}
				$unit = self::quote_for_choices($product_id, $posted, $config);
				if (null === $unit) {
					continue;
				}
				$item['data']->set_price($unit);
			}
		} finally {
			self::$applying_prices = false;
		}
	}

	/**
	 * Block checkout when a discount bundle in the cart cannot be priced,
	 * or when a saved bundle is no longer on sale.
	 *
	 * @return void
	 */
	public static function guard_cart_items() {
		if (! function_exists('WC') || ! WC()->cart || ! method_exists(WC()->cart, 'get_cart')) {
			return;
		}
		$seen = array();
		foreach (WC()->cart->get_cart() as $item) {
			$product_id = absint($item['product_id'] ?? 0);
			if ($product_id < 1 || isset($seen[ $product_id ])) {
				continue;
			}
			$config = self::config_for($product_id);
			if (null === $config) {
				continue;
			}
			$seen[ $product_id ] = true;
			if (! MBBB_Bundle_Config::is_purchasable($config, self::today())) {
				wc_add_notice(__('A bundle in your basket is not available right now. Remove it before checkout.', 'mad-baits-bundle-builder'), 'error');
				continue;
			}
			$mode = (string) ($config['pricing']['mode'] ?? 'fixed');
			if (! in_array($mode, array('percent', 'amount'), true)) {
				continue;
			}
			$posted = array();
			if (class_exists('MBBB_Plugin') && ! empty($item[ MBBB_Plugin::CART_META_KEY ]['raw']) && is_array($item[ MBBB_Plugin::CART_META_KEY ]['raw'])) {
				$posted = $item[ MBBB_Plugin::CART_META_KEY ]['raw'];
			}
			if (null === self::quote_for_choices($product_id, $posted, $config)) {
				wc_add_notice(__('A bundle in your basket does not have a price yet. Remove it and choose the products again.', 'mad-baits-bundle-builder'), 'error');
			}
		}
	}

	/**
	 * Apply a discount unit price without reading the cart line's current price.
	 *
	 * @param object               $product           Cart product.
	 * @param array<string, mixed> $config            Owner config.
	 * @param array<int, mixed>    $component_prices  Chosen product prices.
	 * @param int                  $selected_count    Choice count.
	 * @return float|null
	 */
	public static function apply_resolved_price($product, array $config, array $component_prices, $selected_count) {
		if (! is_object($product) || ! method_exists($product, 'set_price')) {
			return null;
		}
		$unit = MBBB_Bundle_Pricing::safe_unit_price($config, $component_prices, $selected_count);
		if (null === $unit) {
			return null;
		}
		$product->set_price($unit);
		return $unit;
	}

	/**
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function filter_price_html($html, $product) {
		if (! $product instanceof WC_Product) {
			return $html;
		}
		$config = self::config_for($product->get_id());
		if (null === $config) {
			return $html;
		}
		$mode = (string) ($config['pricing']['mode'] ?? 'fixed');
		if ('fixed' === $mode) {
			return $html;
		}
		return esc_html(MBBB_Bundle_Pricing::summary($config));
	}

	/**
	 * Why a direct add-to-cart must be refused, or null when this product has no owner rules.
	 *
	 * @param int $product_id Product ID.
	 * @return WP_Error|null
	 */
	public static function direct_purchase_error($product_id) {
		$config = self::config_for(absint($product_id));
		if (null === $config) {
			return null;
		}
		if (! MBBB_Bundle_Config::is_purchasable($config, self::today())) {
			return new WP_Error('mb_bundle_unavailable', __('This bundle is not available right now.', 'mad-baits-bundle-builder'));
		}
		if (self::blocks_for_stock($config, absint($product_id))) {
			return new WP_Error('mb_bundle_stock', __('None of the selected products can be bought right now.', 'mad-baits-bundle-builder'));
		}
		return null;
	}

	/**
	 * Extra server-side checks for manager bundles.
	 *
	 * @param int                  $product_id Bundle ID.
	 * @param array<string, mixed> $posted     Slot choices.
	 * @return true|WP_Error
	 */
	public static function validate_posted_choices($product_id, array $posted) {
		$config = self::config_for(absint($product_id));
		if (null === $config) {
			return true;
		}
		$blocked = self::direct_purchase_error($product_id);
		if (is_wp_error($blocked)) {
			return $blocked;
		}

		$stock_error = self::stock_error($config, $product_id, $posted);
		if (is_wp_error($stock_error)) {
			return $stock_error;
		}

		$count  = 0;
		foreach ($posted as $value) {
			if (is_array($value)) {
				$value = array_filter($value);
				if (! empty($value)) {
					++$count;
				}
				continue;
			}
			if ('' !== trim((string) $value)) {
				++$count;
			}
		}
		$errors = MBBB_Bundle_Validator::selection_errors($config, $count);
		if (! empty($errors)) {
			return new WP_Error('mb_bundle_quantity', $errors[0]);
		}

		$mode = (string) ($config['pricing']['mode'] ?? 'fixed');
		if (in_array($mode, array('percent', 'amount'), true) && null === self::quote_for_choices($product_id, $posted, $config)) {
			return new WP_Error('mb_bundle_price', __('This bundle price could not be calculated from the products you chose. Please choose again.', 'mad-baits-bundle-builder'));
		}
		return true;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	public static function config_for($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return null;
		}
		$stored = get_post_meta($product_id, MBBB_Bundle_Config::META_KEY, true);
		if (! is_array($stored)) {
			return null;
		}
		return MBBB_Bundle_Config::sanitize($stored);
	}

	/**
	 * @param int                  $product_id Bundle ID.
	 * @param array<string, mixed> $posted     Choices.
	 * @return float[]
	 */
	public static function prices_for_choices($product_id, array $posted) {
		$quote = self::choice_quote(absint($product_id), $posted);
		return $quote['prices'];
	}

	/**
	 * Discount unit price, or null when it cannot be resolved safely.
	 *
	 * @param int                       $product_id Bundle ID.
	 * @param array<string, mixed>      $posted     Choices.
	 * @param array<string, mixed>|null $config     Owner config.
	 * @return float|null
	 */
	private static function quote_for_choices($product_id, array $posted, $config = null) {
		if (! is_array($config)) {
			$config = self::config_for($product_id);
		}
		if (null === $config) {
			return null;
		}
		$quote = self::choice_quote($product_id, $posted);
		return MBBB_Bundle_Pricing::safe_unit_price($config, $quote['prices'], $quote['selected']);
	}

	/**
	 * @param int                  $product_id Bundle ID.
	 * @param array<string, mixed> $posted     Choices.
	 * @return array{prices: array<int, float|null>, selected: int}
	 */
	private static function choice_quote($product_id, array $posted) {
		$result = array(
			'prices'   => array(),
			'selected' => 0,
		);
		if (! class_exists('MBBB_Plugin') || ! function_exists('wc_get_product')) {
			return $result;
		}
		$plugin = MBBB_Plugin::instance();
		foreach ($plugin->get_slots($product_id) as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$key   = (string) ($slot['key'] ?? '');
			$value = $plugin->get_posted_slot_value($posted, $key, '');
			if (is_array($value)) {
				$value = reset($value);
			}
			$value = (string) $value;
			if ('' === $value) {
				continue;
			}
			++$result['selected'];
			$resolved = null;
			foreach ($plugin->resolve_slot_options($slot, $product_id) as $option) {
				if ((string) ($option['value'] ?? '') !== $value) {
					continue;
				}
				$option_product = wc_get_product(absint($option['product_id'] ?? 0));
				$resolved       = MBBB_Bundle_Pricing::component_base_price($option_product);
				break;
			}
			$result['prices'][] = $resolved;
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $config     Config.
	 * @param int                  $product_id Bundle ID.
	 * @return bool
	 */
	private static function blocks_for_stock(array $config, $product_id) {
		if (! empty($config['preserve_slots']) || empty($config['stock']['prevent_oos'])) {
			return false;
		}
		if (! class_exists('MBBB_Plugin')) {
			return false;
		}
		$needed = MBBB_Bundle_Compiler::required_count($config);
		if ($needed < 1) {
			return false;
		}
		$plugin = MBBB_Plugin::instance();
		$slots  = $plugin->get_resolved_slots($product_id);
		if (empty($slots)) {
			return false;
		}
		$options = $plugin->resolve_slot_options($slots[0], $product_id);
		return count($options) < 1;
	}

	/**
	 * @param array<string, mixed> $config     Config.
	 * @param int                  $product_id Bundle ID.
	 * @param array<string, mixed> $posted     Choices.
	 * @return true|WP_Error
	 */
	private static function stock_error(array $config, $product_id, array $posted) {
		if (! empty($config['preserve_slots']) || empty($config['stock']['prevent_oos']) || ! class_exists('MBBB_Plugin') || ! function_exists('wc_get_product')) {
			return true;
		}
		$plugin = MBBB_Plugin::instance();
		foreach ($plugin->get_slots($product_id) as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$key   = (string) ($slot['key'] ?? '');
			$value = $plugin->get_posted_slot_value($posted, $key, '');
			if (is_array($value)) {
				$value = reset($value);
			}
			$value = (string) $value;
			if ('' === $value) {
				continue;
			}
			$options = isset($slot['manual_options']) && is_array($slot['manual_options']) ? $slot['manual_options'] : array();
			foreach ($options as $option) {
				if (! is_array($option) || (string) ($option['value'] ?? '') !== $value) {
					continue;
				}
				$option_product = wc_get_product(absint($option['product_id'] ?? 0));
				if ($option_product instanceof WC_Product && (! $option_product->is_purchasable() || ! $option_product->is_in_stock())) {
					return new WP_Error(
						'mb_bundle_stock',
						sprintf(
							/* translators: %s: product name */
							__('%s is out of stock. Please choose another.', 'mad-baits-bundle-builder'),
							$option_product->get_name()
						)
					);
				}
			}
		}
		return true;
	}

	/**
	 * @return string
	 */
	private static function today() {
		return function_exists('current_time') ? (string) current_time('Y-m-d') : gmdate('Y-m-d');
	}
}
