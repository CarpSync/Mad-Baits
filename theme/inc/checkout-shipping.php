<?php
/**
 * Checkout shipping UX — prefer / force free shipping when a bundle qualifies.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Whether the cart contains a Mad Baits bundle builder product.
 *
 * @return bool
 */
function mad_baits_cart_has_bundle_builder_item() {
	if (! function_exists('WC') || ! WC()->cart || WC()->cart->is_empty()) {
		return false;
	}

	$meta_key = defined('MBBB_Plugin::CART_META_KEY') ? MBBB_Plugin::CART_META_KEY : 'mbbb_choices';

	foreach (WC()->cart->get_cart() as $cart_item) {
		if (! empty($cart_item[ $meta_key ]) || ! empty($cart_item['mbbb_choices'])) {
			return true;
		}

		$product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
		if ($product_id > 0 && class_exists('MBBB_Plugin')) {
			if (MBBB_Plugin::instance()->is_enabled($product_id)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * When true, any bundle in the cart makes the whole order ship free (UK checkout).
 *
 * @return bool
 */
function mad_baits_bundle_forces_cart_free_shipping() {
	if (! mad_baits_cart_has_bundle_builder_item()) {
		return false;
	}

	/**
	 * Bundle in basket unlocks free shipping for the entire order (not only the bundle line).
	 *
	 * @param bool $force Force free shipping.
	 */
	return (bool) apply_filters('mad_baits_bundle_forces_cart_free_shipping', true);
}

/**
 * Extract free_shipping rates from a rates array.
 *
 * @param array<string, WC_Shipping_Rate> $rates Rates.
 * @return array<string, WC_Shipping_Rate>
 */
function mad_baits_collect_free_shipping_rates(array $rates) {
	$free_rates = array();

	foreach ($rates as $rate_id => $rate) {
		$method_id = '';

		if (is_object($rate) && isset($rate->method_id)) {
			$method_id = (string) $rate->method_id;
		} elseif (is_string($rate_id) && str_starts_with($rate_id, 'free_shipping')) {
			$method_id = 'free_shipping';
		}

		if ('free_shipping' === $method_id) {
			$free_rates[ $rate_id ] = $rate;
		}
	}

	return $free_rates;
}

/**
 * Inject a zero-cost shipping rate when none exists (bundle override fallback).
 *
 * @return WC_Shipping_Rate
 */
function mad_baits_create_bundle_free_shipping_rate() {
	return new WC_Shipping_Rate(
		'mad_baits_bundle_free_shipping',
		0,
		0,
		'',
		__('Free shipping', 'mad-baits'),
		''
	);
}

/**
 * Keep only free shipping rates, injecting one if Woo did not offer free_shipping.
 *
 * @param array<string, WC_Shipping_Rate> $rates Rates.
 * @return array<string, WC_Shipping_Rate>
 */
function mad_baits_apply_free_shipping_only_rates(array $rates) {
	$free_rates = mad_baits_collect_free_shipping_rates($rates);

	if (empty($free_rates)) {
		$free_rates['mad_baits_bundle_free_shipping'] = mad_baits_create_bundle_free_shipping_rate();
	}

	return $free_rates;
}

/**
 * When free shipping is available (or bundle forces it), hide paid flat rate options.
 *
 * @param array<string, WC_Shipping_Rate> $rates Package rates.
 * @param array<string, mixed>            $package Package data.
 * @return array<string, WC_Shipping_Rate>
 */
function mad_baits_filter_package_rates_for_free_shipping($rates, $package) {
	unset($package);

	if (empty($rates) || ! is_array($rates)) {
		return $rates;
	}

	if (mad_baits_bundle_forces_cart_free_shipping()) {
		return mad_baits_apply_free_shipping_only_rates($rates);
	}

	$free_rates = mad_baits_collect_free_shipping_rates($rates);
	if (! empty($free_rates)) {
		return $free_rates;
	}

	return $rates;
}
add_filter('woocommerce_package_rates', 'mad_baits_filter_package_rates_for_free_shipping', 100, 2);

/**
 * Make WooCommerce free shipping method available when a bundle is in the cart.
 *
 * Fixes mixed carts where the bundle alone qualified but adding other products
 * disabled the free_shipping method (e.g. minimum order amount).
 *
 * @param bool                               $is_available Available.
 * @param array<int, array<string, mixed>>   $package Package.
 * @param WC_Shipping_Free_Shipping          $method Method instance.
 * @return bool
 */
function mad_baits_free_shipping_available_for_bundle_cart($is_available, $package, $method) {
	unset($package, $method);

	if (mad_baits_bundle_forces_cart_free_shipping()) {
		return true;
	}

	return $is_available;
}
add_filter('woocommerce_shipping_free_shipping_is_available', 'mad_baits_free_shipping_available_for_bundle_cart', 20, 3);

/**
 * Auto-select free shipping as the chosen method when present.
 *
 * @param string                           $chosen_method Chosen method id.
 * @param array<string, WC_Shipping_Rate>  $rates Available rates.
 * @param int|string                       $package_key Package index.
 * @return string
 */
function mad_baits_choose_free_shipping_method($chosen_method, $rates, $package_key) {
	unset($package_key);

	if (empty($rates) || ! is_array($rates)) {
		return $chosen_method;
	}

	if (mad_baits_bundle_forces_cart_free_shipping()) {
		$free_rates = mad_baits_collect_free_shipping_rates($rates);
		if (! empty($free_rates)) {
			return (string) key($free_rates);
		}
		return 'mad_baits_bundle_free_shipping';
	}

	foreach ($rates as $rate_id => $rate) {
		$method_id = is_object($rate) && isset($rate->method_id) ? (string) $rate->method_id : '';
		if ('free_shipping' === $method_id || str_starts_with((string) $rate_id, 'free_shipping')) {
			return (string) $rate_id;
		}
		if ('mad_baits_bundle_free_shipping' === (string) $rate_id) {
			return (string) $rate_id;
		}
	}

	return $chosen_method;
}
add_filter('woocommerce_shipping_chosen_method', 'mad_baits_choose_free_shipping_method', 100, 3);

/**
 * Clear stale paid shipping selection when a bundle is added so totals recalculate with free shipping.
 *
 * @return void
 */
function mad_baits_reset_chosen_shipping_when_bundle_present() {
	if (! mad_baits_bundle_forces_cart_free_shipping()) {
		return;
	}

	if (! function_exists('WC') || ! WC()->session) {
		return;
	}

	WC()->session->set('chosen_shipping_methods', array());
}
add_action('woocommerce_add_to_cart', 'mad_baits_reset_chosen_shipping_when_bundle_present', 25);
add_action('woocommerce_cart_item_removed', 'mad_baits_reset_chosen_shipping_when_bundle_present', 25);
add_action('woocommerce_cart_item_restored', 'mad_baits_reset_chosen_shipping_when_bundle_present', 25);

/**
 * Enqueue checkout shipping fallback script.
 *
 * @return void
 */
function mad_baits_enqueue_checkout_shipping_script() {
	if (! function_exists('is_checkout') || ! is_checkout()) {
		return;
	}

	$path = get_theme_file_path('assets/js/mad-checkout-shipping.js');
	if (! file_exists($path)) {
		return;
	}

	wp_enqueue_script(
		'mad-baits-checkout-shipping',
		get_theme_file_uri('assets/js/mad-checkout-shipping.js'),
		array('jquery', 'mad-baits-main'),
		(string) filemtime($path),
		true
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_checkout_shipping_script', 35);
