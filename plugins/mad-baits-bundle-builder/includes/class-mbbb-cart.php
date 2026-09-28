<?php
/**
 * Cart, checkout, and order meta.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Cart integration.
 */
final class MBBB_Cart {

	/**
	 * @return void
	 */
	public function init() {
		add_filter('woocommerce_add_to_cart_handler', array($this, 'cart_handler'), 20, 2);
		add_filter('woocommerce_add_to_cart_validation', array($this, 'normalize_bundle_post_data'), 1, 3);
		add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
		add_filter('woocommerce_add_to_cart_validation', array($this, 'force_internal_bundle_add_validation_pass'), 99999, 3);
		add_filter('woocommerce_product_get_type', array($this, 'filter_product_type_for_cart'), 10, 2);
		add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
		add_filter('woocommerce_add_cart_item', array($this, 'sanitize_bundle_cart_item_variation_meta'), 20, 1);
		add_filter('woocommerce_get_cart_item_from_session', array($this, 'sanitize_bundle_cart_item_from_session'), 20, 2);
		add_action('wp_ajax_mbbb_add_to_cart', array($this, 'ajax_add_to_cart'));
		add_action('wp_ajax_nopriv_mbbb_add_to_cart', array($this, 'ajax_add_to_cart'));
		add_action('template_redirect', array($this, 'prune_bundle_required_field_notices'), 2);
		add_action('wp', array($this, 'prune_bundle_required_field_notices'), 2);
		add_filter('woocommerce_get_item_data', array($this, 'filter_cart_item_data_display'), 9999, 2);
		add_filter('woocommerce_cart_item_is_purchasable', array($this, 'bundle_cart_item_is_purchasable'), 10, 3);
		add_action('woocommerce_checkout_create_order_line_item', array($this, 'order_line_item_meta'), 10, 4);
		add_action('woocommerce_checkout_create_order_line_item', array($this, 'finalize_order_line_item_meta'), 25, 4);
		add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'filter_order_item_formatted_meta'), 10, 2);
		add_filter('woocommerce_order_item_display_meta_key', array($this, 'format_meta_key'), 10, 3);
		add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hidden_order_itemmeta'));
	}

	/**
	 * Remove generic third-party "required fields" notices in bundle add contexts.
	 *
	 * @return void
	 */
	public function prune_bundle_required_field_notices() {
		if (! function_exists('WC') || ! WC()->session || ! $this->is_bundle_request_context()) {
			return;
		}

		$notices = WC()->session->get('wc_notices', array());
		if (! is_array($notices) || empty($notices['error']) || ! is_array($notices['error'])) {
			return;
		}

		$filtered = array();
		foreach ($notices['error'] as $notice) {
			$text = '';
			if (is_array($notice) && isset($notice['notice'])) {
				$text = wp_strip_all_tags((string) $notice['notice']);
			} else {
				$text = wp_strip_all_tags((string) $notice);
			}

			$text_lc = strtolower(trim($text));
			$is_generic_required = (false !== strpos($text_lc, 'required field'));
			if (! $is_generic_required) {
				$filtered[] = $notice;
			}
		}

		$notices['error'] = $filtered;
		WC()->session->set('wc_notices', $notices);
	}

	/**
	 * Whether current request is operating on a bundle builder add flow.
	 *
	 * @return bool
	 */
	private function is_bundle_request_context() {
		if (! empty($GLOBALS['mbbb_adding_to_cart'])) {
			return true;
		}

		if (wp_doing_ajax()) {
			$action = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : '';
			return 'mbbb_add_to_cart' === $action;
		}

		$product_id = isset($_REQUEST['add-to-cart']) ? absint(wp_unslash((string) $_REQUEST['add-to-cart'])) : 0;
		if ($product_id > 0 && MBBB_Plugin::instance()->is_enabled($product_id)) {
			return true;
		}

		if (function_exists('is_product') && is_product()) {
			$product_id = absint(get_queried_object_id());
			if ($product_id > 0 && MBBB_Plugin::instance()->is_enabled($product_id)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $hidden Hidden meta keys.
	 * @return array
	 */
	public function hidden_order_itemmeta($hidden) {
		$hidden[] = '_mbbb_bundle_summary';
		return $hidden;
	}

	/**
	 * Use simple add-to-cart handler so parent bundle product is added without variation ID.
	 *
	 * @param string     $handler Handler.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function cart_handler($handler, $product) {
		if ($product instanceof WC_Product && MBBB_Plugin::instance()->is_enabled($product->get_id())) {
			return 'simple';
		}
		return $handler;
	}

	/**
	 * Strip legacy variation POST data so WooCommerce does not validate hidden attributes.
	 *
	 * @param bool $passed Passed.
	 * @param int  $product_id Product ID.
	 * @param int  $qty Qty.
	 * @return bool
	 */
	public function normalize_bundle_post_data($passed, $product_id, $qty) {
		unset($qty);
		if (! MBBB_Plugin::instance()->is_enabled($product_id)) {
			return $passed;
		}

		// During internal bundle add we may intentionally provide a resolved variation context.
		// Do not wipe it here.
		if (! empty($GLOBALS['mbbb_adding_to_cart']) && absint($GLOBALS['mbbb_adding_to_cart']) === absint($product_id)) {
			return $passed;
		}

		if (isset($_POST['variation_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST['variation_id'] = 0;
		}
		if (isset($_REQUEST['variation_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_REQUEST['variation_id'] = 0;
		}

		foreach (array_keys($_POST) as $key) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (0 === strpos((string) $key, 'attribute_')) {
				unset($_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
		foreach (array_keys($_REQUEST) as $key) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (0 === strpos((string) $key, 'attribute_')) {
				unset($_REQUEST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		return $passed;
	}

	/**
	 * Treat bundle variable products as simple during cart add (parent + line-item meta).
	 *
	 * @param string     $type Product type.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter_product_type_for_cart($type, $product) {
		if (empty($GLOBALS['mbbb_adding_to_cart']) || ! $product instanceof WC_Product) {
			return $type;
		}

		if ((int) $GLOBALS['mbbb_adding_to_cart'] === (int) $product->get_id() && MBBB_Plugin::instance()->is_enabled($product->get_id())) {
			return 'simple';
		}

		return $type;
	}

	/**
	 * Add a bundle parent product to the cart (bypasses variable-product variation ID requirement).
	 *
	 * @param int                  $product_id Product ID.
	 * @param int                  $quantity Quantity.
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 * @return string|false
	 */
	public static function add_bundle_product_to_cart($product_id, $quantity, $cart_item_data = array()) {
		$product_id = absint($product_id);
		$quantity   = max(1, absint($quantity));
		$posted     = self::get_posted_choices_from_request();
		if (empty($posted) && ! empty($cart_item_data[ MBBB_Plugin::CART_META_KEY ]['raw'])) {
			$posted = (array) $cart_item_data[ MBBB_Plugin::CART_META_KEY ]['raw'];
		}

		// Variable bundle products still need a variation ID for WooCommerce cart APIs.
		// Match from customer slot choices when possible; order meta cleanup keeps admin display accurate.
		$variation_id   = 0;
		$variation_data = array();
		$variation_ctx  = self::resolve_bundle_variation_context($product_id, $posted);
		if (! empty($variation_ctx['variation_id'])) {
			$variation_id   = absint($variation_ctx['variation_id']);
			$variation_data = is_array($variation_ctx['variation']) ? $variation_ctx['variation'] : array();
		}

		$GLOBALS['mbbb_adding_to_cart'] = $product_id;
		$prev_post_add_to_cart          = isset($_POST['add-to-cart']) ? $_POST['add-to-cart'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prev_request_add_to_cart       = isset($_REQUEST['add-to-cart']) ? $_REQUEST['add-to-cart'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prev_post_qty                  = isset($_POST['quantity']) ? $_POST['quantity'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prev_request_qty               = isset($_REQUEST['quantity']) ? $_REQUEST['quantity'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prev_post_variation_id         = isset($_POST['variation_id']) ? $_POST['variation_id'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prev_request_variation_id      = isset($_REQUEST['variation_id']) ? $_REQUEST['variation_id'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$prev_post_attributes           = array();
		$prev_request_attributes        = array();

		// Some Woo extensions require canonical add-to-cart request vars during validation.
		$_POST['add-to-cart']    = $product_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_REQUEST['add-to-cart'] = $product_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['quantity']       = $quantity; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_REQUEST['quantity']    = $quantity; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST['variation_id']   = $variation_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_REQUEST['variation_id'] = $variation_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ($variation_data as $attr_key => $attr_value) {
			$attr_key = (string) $attr_key;
			if ('' === $attr_key) {
				continue;
			}
			$prev_post_attributes[ $attr_key ] = isset($_POST[ $attr_key ]) ? $_POST[ $attr_key ] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$prev_request_attributes[ $attr_key ] = isset($_REQUEST[ $attr_key ]) ? $_REQUEST[ $attr_key ] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST[ $attr_key ] = $attr_value; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_REQUEST[ $attr_key ] = $attr_value; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}

		try {
			return WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data, $cart_item_data);
		} finally {
			unset($GLOBALS['mbbb_adding_to_cart']);
			if (null === $prev_post_add_to_cart) {
				unset($_POST['add-to-cart']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_POST['add-to-cart'] = $prev_post_add_to_cart; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if (null === $prev_request_add_to_cart) {
				unset($_REQUEST['add-to-cart']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_REQUEST['add-to-cart'] = $prev_request_add_to_cart; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if (null === $prev_post_qty) {
				unset($_POST['quantity']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_POST['quantity'] = $prev_post_qty; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if (null === $prev_request_qty) {
				unset($_REQUEST['quantity']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_REQUEST['quantity'] = $prev_request_qty; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if (null === $prev_post_variation_id) {
				unset($_POST['variation_id']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_POST['variation_id'] = $prev_post_variation_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if (null === $prev_request_variation_id) {
				unset($_REQUEST['variation_id']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_REQUEST['variation_id'] = $prev_request_variation_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			foreach ($variation_data as $attr_key => $attr_value) {
				unset($attr_value);
				$attr_key = (string) $attr_key;
				$prev_post_attr = array_key_exists($attr_key, $prev_post_attributes) ? $prev_post_attributes[ $attr_key ] : null;
				$prev_request_attr = array_key_exists($attr_key, $prev_request_attributes) ? $prev_request_attributes[ $attr_key ] : null;
				if (null === $prev_post_attr) {
					unset($_POST[ $attr_key ]); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				} else {
					$_POST[ $attr_key ] = $prev_post_attr; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}
				if (null === $prev_request_attr) {
					unset($_REQUEST[ $attr_key ]); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				} else {
					$_REQUEST[ $attr_key ] = $prev_request_attr; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}
			}
		}
	}

	/**
	 * Resolve a valid variation context for bundle products that are variable in Woo.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     Customer slot choices.
	 * @return array{variation_id:int,variation:array<string,string>}
	 */
	private static function resolve_bundle_variation_context($product_id, $posted = array()) {
		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product_Variable) {
			return array(
				'variation_id' => 0,
				'variation'    => array(),
			);
		}

		$variation_id = 0;
		$variation    = array();
		$variation_matrix = (array) $product->get_variation_attributes();
		$desired_variation = self::build_desired_variation_map($product, $variation_matrix);

		if (! empty($posted)) {
			$choice_attributes = self::map_choices_to_variation_attributes($product_id, $posted);
			if (! empty($choice_attributes)) {
				$store = WC_Data_Store::load('product');
				if ($store && method_exists($store, 'find_matching_product_variation')) {
					$match_id = (int) $store->find_matching_product_variation($product, $choice_attributes);
					if ($match_id > 0) {
						$match = wc_get_product($match_id);
						if ($match instanceof WC_Product_Variation) {
							return array(
								'variation_id' => $match_id,
								'variation'    => array_map('strval', (array) $match->get_variation_attributes()),
							);
						}
					}
				}
			}
		}

		$default_attributes = array_filter((array) $product->get_default_attributes(), static function ($value) {
			return '' !== (string) $value;
		});
		if (empty($default_attributes) && ! empty($desired_variation)) {
			$default_attributes = self::strip_attribute_prefix_keys($desired_variation);
		}
		if (! empty($default_attributes)) {
			$store = WC_Data_Store::load('product');
			if ($store && method_exists($store, 'find_matching_product_variation')) {
				$match_id = (int) $store->find_matching_product_variation($product, $default_attributes);
				if ($match_id > 0) {
					$match = wc_get_product($match_id);
					if ($match instanceof WC_Product_Variation) {
						$variation_id = $match_id;
						$variation    = array_map('strval', (array) $match->get_variation_attributes());
					}
				}
			}
		}

		if ($variation_id < 1) {
			foreach ((array) $product->get_children() as $child_id) {
				$child = wc_get_product((int) $child_id);
				if (! $child instanceof WC_Product_Variation) {
					continue;
				}
				$variation_id = (int) $child->get_id();
				$variation    = array_map('strval', (array) $child->get_variation_attributes());
				break;
			}
		}

		if (! empty($desired_variation)) {
			foreach ($desired_variation as $attr_key => $attr_value) {
				if (! isset($variation[ $attr_key ]) || '' === (string) $variation[ $attr_key ]) {
					$variation[ $attr_key ] = (string) $attr_value;
				}
			}
		}

		return array(
			'variation_id' => $variation_id,
			'variation'    => $variation,
		);
	}

	/**
	 * Map bundle slot choices to WooCommerce variation attribute slugs.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     slot_key => value.
	 * @return array<string, string> pa_* => slug (no attribute_ prefix).
	 */
	private static function map_choices_to_variation_attributes($product_id, $posted) {
		return MBBB_Plugin::instance()->map_posted_choices_to_variation_attributes($product_id, $posted);
	}

	/**
	 * Build a robust variation attribute map from defaults or first available values.
	 *
	 * @param WC_Product_Variable          $product Product.
	 * @param array<string, array<int,string>> $variation_matrix Variation attributes.
	 * @return array<string, string>
	 */
	private static function build_desired_variation_map($product, $variation_matrix) {
		$map = array();
		$defaults = (array) $product->get_default_attributes();

		foreach ($variation_matrix as $attr_key => $values) {
			$attr_key = self::ensure_attribute_prefix((string) $attr_key);
			if ('' === $attr_key) {
				continue;
			}

			$short_key = preg_replace('/^attribute_/', '', $attr_key);
			$default_value = isset($defaults[ $short_key ]) ? (string) $defaults[ $short_key ] : '';

			$chosen = '';
			if ('' !== $default_value) {
				$chosen = $default_value;
			} else {
				foreach ((array) $values as $candidate) {
					$candidate = (string) $candidate;
					if ('' !== $candidate) {
						$chosen = $candidate;
						break;
					}
				}
			}

			if ('' !== $chosen) {
				$map[ $attr_key ] = $chosen;
			}
		}

		return $map;
	}

	/**
	 * Convert attribute_pa_x keys to pa_x keys for variation matching lookup.
	 *
	 * @param array<string, string> $attrs Attributes.
	 * @return array<string, string>
	 */
	private static function strip_attribute_prefix_keys($attrs) {
		$out = array();
		foreach ((array) $attrs as $key => $value) {
			$clean_key = preg_replace('/^attribute_/', '', (string) $key);
			if (! is_string($clean_key) || '' === $clean_key) {
				continue;
			}
			$out[ $clean_key ] = (string) $value;
		}
		return $out;
	}

	/**
	 * Ensure variation attribute key has "attribute_" prefix.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function ensure_attribute_prefix($key) {
		$key = trim((string) $key);
		if ('' === $key) {
			return '';
		}
		if (0 === strpos($key, 'attribute_')) {
			return $key;
		}
		if (function_exists('wc_variation_attribute_name')) {
			return (string) wc_variation_attribute_name($key);
		}
		return 'attribute_' . sanitize_title($key);
	}

	/**
	 * @param bool $passed Passed.
	 * @param int  $product_id Product ID.
	 * @param int  $qty Qty.
	 * @return bool
	 */
	public function validate_add_to_cart($passed, $product_id, $qty) {
		unset($qty);
		$plugin = MBBB_Plugin::instance();
		if (! $plugin->is_enabled($product_id)) {
			return $passed;
		}

		// AJAX add uses dedicated handler; avoid duplicate WC notices.
		if (wp_doing_ajax()) {
			return $passed;
		}

		$is_add_request = isset($_POST['add-to-cart']) && (int) $_POST['add-to-cart'] === (int) $product_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (! $is_add_request) {
			return $passed;
		}

		if (empty($_POST['mbbb_choices']) || ! is_array($_POST['mbbb_choices'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		$posted = array_map(
			static function ($v) {
				return is_array($v) ? array_map('sanitize_text_field', wp_unslash($v)) : sanitize_text_field(wp_unslash((string) $v));
			},
			wp_unslash($_POST['mbbb_choices']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$result = $plugin->validate_choices($product_id, $posted);
		if (is_wp_error($result)) {
			return false;
		}

		return $passed;
	}

	/**
	 * Ensure third-party required-field validators do not block the internal bundle AJAX add.
	 *
	 * We run our own slot validation before calling WC()->cart->add_to_cart(). During that
	 * internal add, some Woo extensions still inspect unrelated hidden fields and can return
	 * false with generic "required fields" notices. This high-priority filter guarantees the
	 * controlled internal bundle add is not vetoed after custom validation already passed.
	 *
	 * @param bool $passed Passed.
	 * @param int  $product_id Product ID.
	 * @param int  $qty Qty.
	 * @return bool
	 */
	public function force_internal_bundle_add_validation_pass($passed, $product_id, $qty) {
		unset($qty);
		if (empty($GLOBALS['mbbb_adding_to_cart'])) {
			return $passed;
		}

		$target_id = absint($GLOBALS['mbbb_adding_to_cart']);
		if ($target_id < 1 || $target_id !== absint($product_id)) {
			return $passed;
		}

		if (! MBBB_Plugin::instance()->is_enabled($product_id)) {
			return $passed;
		}

		return true;
	}

	/**
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @return array
	 */
	public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
		unset($variation_id);
		$plugin = MBBB_Plugin::instance();
		if (! $plugin->is_enabled($product_id)) {
			return $cart_item_data;
		}

		$posted = self::get_posted_choices_from_request();
		if (empty($posted)) {
			return $cart_item_data;
		}

		return array_merge($cart_item_data, self::build_cart_item_data($product_id, $posted));
	}

	/**
	 * AJAX add to cart for smooth mobile/PWA flow.
	 *
	 * @return void
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer('mbbb_add_to_cart', 'nonce');
		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$quantity   = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;
		$plugin     = MBBB_Plugin::instance();

		if ($product_id < 1 || ! $plugin->is_enabled($product_id)) {
			wp_send_json_error(array('message' => __('Invalid bundle product.', 'mad-baits-bundle-builder')), 400);
		}

		$posted = self::get_posted_choices_from_request();
		if (empty($posted)) {
			wp_send_json_error(array('message' => __('Please complete all bundle choices.', 'mad-baits-bundle-builder')), 400);
		}

		$valid = $plugin->validate_choices($product_id, $posted);
		if (is_wp_error($valid)) {
			wp_send_json_error(array('message' => $valid->get_error_message()), 400);
		}

		$product = wc_get_product($product_id);
		if (! $product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
			wp_send_json_error(array('message' => __('This bundle is not available right now.', 'mad-baits-bundle-builder')), 400);
		}

		$cart_item_data = self::build_cart_item_data($product_id, $posted);
		$cart_key       = self::add_bundle_product_to_cart($product_id, $quantity, $cart_item_data);

		if (! $cart_key) {
			$notice_message = '';
			if (function_exists('wc_get_notices')) {
				$notices = wc_get_notices('error');
				if (! empty($notices) && isset($notices[0]['notice'])) {
					$notice_message = wp_strip_all_tags((string) $notices[0]['notice']);
				}
				if (function_exists('wc_clear_notices')) {
					wc_clear_notices();
				}
			}
			wp_send_json_error(
				array(
					'message' => $notice_message ? $notice_message : __('Could not add bundle to basket. Please try again.', 'mad-baits-bundle-builder'),
				),
				500
			);
		}

		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}

		$display = isset($cart_item_data[ MBBB_Plugin::CART_META_KEY ]['display'])
			? $cart_item_data[ MBBB_Plugin::CART_META_KEY ]['display']
			: array();

		wp_send_json_success(
			array(
				'message'      => __('Bundle added to your basket.', 'mad-baits-bundle-builder'),
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
				'choices'      => $display,
				'cart_count'   => WC()->cart->get_cart_contents_count(),
			)
		);
	}

	/**
	 * @return array<string, string|array>
	 */
	public static function get_posted_choices_from_request() {
		if (empty($_POST['mbbb_choices'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array();
		}

		$raw    = wp_unslash($_POST['mbbb_choices']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted = array();

		if (! is_array($raw)) {
			return array();
		}

		foreach ($raw as $key => $value) {
			$raw_key = trim((string) $key);
			if ('' === $raw_key) {
				continue;
			}

			if (is_array($value)) {
				$clean_value = array_map('sanitize_text_field', $value);
			} else {
				$clean_value = sanitize_text_field((string) $value);
			}

			// Preserve configured slot keys exactly; destructive key sanitizing causes false "required" errors.
			$posted[ $raw_key ] = $clean_value;

			// Backward-compatible aliases for legacy key formats.
			$sanitized_key = sanitize_key($raw_key);
			if ('' !== $sanitized_key && ! isset($posted[ $sanitized_key ])) {
				$posted[ $sanitized_key ] = $clean_value;
			}
			$title_key = sanitize_title($raw_key);
			if ('' !== $title_key && ! isset($posted[ $title_key ])) {
				$posted[ $title_key ] = $clean_value;
			}
		}

		return $posted;
	}

	/**
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     Choices.
	 * @return array<string, mixed>
	 */
	public static function build_cart_item_data($product_id, $posted) {
		$plugin   = MBBB_Plugin::instance();
		$canonical = $plugin->canonicalize_posted_choices($product_id, $posted);
		if (! empty($canonical)) {
			$posted = $canonical;
		}
		$rows    = $plugin->format_choices_rows($product_id, $posted);
		$display = $plugin->format_choices_for_display($product_id, $posted);
		foreach (MBBB_Deal_Builder::get_included_product_display_rows($product_id) as $included_row) {
			$label = (string) ($included_row['label'] ?? '');
			$value = (string) ($included_row['value'] ?? '');
			if ('' === $label || '' === $value) {
				continue;
			}
			$display[ $label ] = $value;
			$rows[]            = array(
				'key'   => 'included-' . sanitize_title($value),
				'label' => $label,
				'value' => $value,
			);
		}

		return array(
			MBBB_Plugin::CART_META_KEY => array(
				'raw'          => $posted,
				'display'      => $display,
				'display_list' => $rows,
			),
			'unique_key'               => md5(wp_json_encode($posted) . microtime(true)),
		);
	}

	/**
	 * Bundle lines use a placeholder variation ID for Woo APIs; validate against the parent bundle.
	 *
	 * @param bool               $purchasable Whether purchasable.
	 * @param array<string,mixed> $cart_item   Cart item.
	 * @param string             $cart_item_key Cart key.
	 * @return bool
	 */
	public function bundle_cart_item_is_purchasable($purchasable, $cart_item, $cart_item_key) {
		unset($cart_item_key);

		if (empty($cart_item[ MBBB_Plugin::CART_META_KEY ]['raw']) && empty($cart_item['mbbb_choices']['raw'])) {
			return $purchasable;
		}

		$product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
		if ($product_id < 1) {
			return $purchasable;
		}

		$parent = wc_get_product($product_id);
		if (! $parent instanceof WC_Product) {
			return $purchasable;
		}

		return $parent->is_purchasable() && $parent->is_in_stock();
	}

	/**
	 * Cart/mini-cart: show only bundle builder choices (never duplicate WC variation rows).
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function filter_cart_item_data_display($item_data, $cart_item) {
		$display = self::resolve_bundle_display_map($cart_item);
		if (empty($display)) {
			return $item_data;
		}

		$out = array();
		foreach ($display as $label => $value) {
			$out[] = array(
				'key'   => $label,
				'value' => $value,
			);
		}

		return $out;
	}

	/**
	 * Remove legacy variation context from bundle cart items after add.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 * @return array<string, mixed>
	 */
	public function sanitize_bundle_cart_item_variation_meta($cart_item) {
		if (! is_array($cart_item) || empty($cart_item[ MBBB_Plugin::CART_META_KEY ])) {
			return $cart_item;
		}

		$cart_item = self::repair_bundle_cart_meta($cart_item);
		if (empty($cart_item[ MBBB_Plugin::CART_META_KEY ]['display']) && empty($cart_item[ MBBB_Plugin::CART_META_KEY ]['display_list'])) {
			return $cart_item;
		}

		$cart_item['variation'] = array();

		return $cart_item;
	}

	/**
	 * Ensure session-restored bundle cart items keep variation metadata stripped.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 * @param array<string, mixed> $values Session values.
	 * @return array<string, mixed>
	 */
	public function sanitize_bundle_cart_item_from_session($cart_item, $values) {
		if (is_array($values) && ! empty($values[ MBBB_Plugin::CART_META_KEY ])) {
			$cart_item[ MBBB_Plugin::CART_META_KEY ] = $values[ MBBB_Plugin::CART_META_KEY ];
		}

		return $this->sanitize_bundle_cart_item_variation_meta($cart_item);
	}

	/**
	 * @param array<string, string> $display Display map.
	 * @return string
	 */
	private function format_display_list($display) {
		if (! is_array($display)) {
			return '';
		}
		$lines = array();
		foreach ($display as $label => $value) {
			$lines[] = esc_html($label) . ': ' . esc_html($value);
		}
		return implode("\n", $lines);
	}

	/**
	 * @param WC_Order_Item_Product $item Item.
	 * @param string                $cart_item_key Key.
	 * @param array                 $values Values.
	 * @param WC_Order              $order Order.
	 * @return void
	 */
	public function order_line_item_meta($item, $cart_item_key, $values, $order) {
		unset($cart_item_key, $order);
		$display = self::resolve_bundle_display_map($values);
		if (empty($display)) {
			return;
		}

		foreach ($display as $label => $value) {
			$item->add_meta_data($label, $value, true);
		}
		$item->add_meta_data(
			'_mbbb_bundle_summary',
			wp_json_encode($display),
			true
		);
	}

	/**
	 * Remove legacy WooCommerce variation attribute meta that duplicates bundle builder choices.
	 *
	 * Variable bundle products still carry WC attributes; checkout was saving both the
	 * auto-resolved variation combination and the customer's builder selections.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @param string                $cart_item_key Key.
	 * @param array<string, mixed>  $values Cart values.
	 * @param WC_Order              $order Order.
	 * @return void
	 */
	public function finalize_order_line_item_meta($item, $cart_item_key, $values, $order) {
		unset($cart_item_key, $order);

		if (! $item instanceof WC_Order_Item_Product) {
			return;
		}

		$display = self::resolve_bundle_display_map($values);
		$product_id = isset($values['product_id']) ? absint($values['product_id']) : $item->get_product_id();
		if (empty($display) || $product_id < 1) {
			return;
		}

		$allowed_entries = self::build_allowed_summary_entries($display);
		$attribute_labels = self::get_variation_attribute_labels_for_product($product_id);

		foreach ($item->get_meta_data() as $meta) {
			if (! is_object($meta) || ! isset($meta->key)) {
				continue;
			}

			$key   = (string) $meta->key;
			$value = isset($meta->value) ? (string) $meta->value : '';
			if ('_mbbb_bundle_summary' === $key || '_' === substr($key, 0, 1)) {
				continue;
			}

			if (self::summary_entry_matches_meta($allowed_entries, $key, $value)) {
				continue;
			}

			$norm = self::normalize_meta_label_key($key);
			if (in_array($norm, $attribute_labels, true)) {
				$item->delete_meta_data($key);
			}
		}
	}

	/**
	 * Hide duplicate variation attribute rows in admin/emails when bundle summary exists.
	 *
	 * @param array<int, WC_Meta_Data> $formatted_meta Meta.
	 * @param WC_Order_Item_Product    $item Item.
	 * @return array<int, WC_Meta_Data>
	 */
	public function filter_order_item_formatted_meta($formatted_meta, $item) {
		if (! $item instanceof WC_Order_Item_Product) {
			return $formatted_meta;
		}

		$summary_raw = $item->get_meta('_mbbb_bundle_summary', true);
		if (! is_string($summary_raw) || '' === $summary_raw) {
			return $formatted_meta;
		}

		$summary = json_decode($summary_raw, true);
		if (! is_array($summary) || empty($summary)) {
			return $formatted_meta;
		}

		$allowed_entries = self::build_allowed_summary_entries($summary);
		$product_id      = $item->get_product_id();
		$attr_labels     = $product_id > 0 ? self::get_variation_attribute_labels_for_product($product_id) : array();

		$filtered = array();
		foreach ($formatted_meta as $meta) {
			if (! is_object($meta) || ! isset($meta->key)) {
				$filtered[] = $meta;
				continue;
			}

			$key = (string) $meta->key;
			$val = isset($meta->value) ? (string) $meta->value : '';

			if ('_mbbb_bundle_summary' === $key) {
				continue;
			}

			if (self::summary_entry_matches_meta($allowed_entries, $key, $val)) {
				$filtered[] = $meta;
				continue;
			}

			$norm = self::normalize_meta_label_key($key);
			if (in_array($norm, $attr_labels, true)) {
				continue;
			}

			$filtered[] = $meta;
		}

		return $filtered;
	}

	/**
	 * Canonical display map from cart line (deduped by slot label).
	 *
	 * @param array<string, mixed> $values Cart line values.
	 * @return array<string, string>
	 */
	private static function get_canonical_bundle_display(array $values) {
		return self::resolve_bundle_display_map($values);
	}

	/**
	 * Resolve bundle display map, rebuilding from raw slot choices when needed.
	 *
	 * @param array<string, mixed> $values Cart/order line values.
	 * @return array<string, string>
	 */
	private static function resolve_bundle_display_map(array $values) {
		$meta = self::get_bundle_meta($values);
		if (empty($meta)) {
			return array();
		}

		$display = self::display_map_from_meta($meta);
		$product_id = isset($values['product_id']) ? absint($values['product_id']) : 0;
		$raw = isset($meta['raw']) && is_array($meta['raw']) ? $meta['raw'] : array();

		if ($product_id > 0 && ! empty($raw)) {
			$expected = count(MBBB_Plugin::instance()->get_resolved_slots($product_id));
			if ($expected > count($display)) {
				$display = MBBB_Plugin::instance()->format_choices_for_display($product_id, $raw);
			}
		}

		return $display;
	}

	/**
	 * @param array<string, mixed> $values Cart line values.
	 * @return array<string, mixed>
	 */
	private static function get_bundle_meta(array $values) {
		if (isset($values[ MBBB_Plugin::CART_META_KEY ]) && is_array($values[ MBBB_Plugin::CART_META_KEY ])) {
			return $values[ MBBB_Plugin::CART_META_KEY ];
		}
		if (isset($values['mbbb_choices']) && is_array($values['mbbb_choices'])) {
			return $values['mbbb_choices'];
		}
		return array();
	}

	/**
	 * @param array<string, mixed> $meta Bundle cart meta.
	 * @return array<string, string>
	 */
	private static function display_map_from_meta(array $meta) {
		if (! empty($meta['display_list']) && is_array($meta['display_list'])) {
			$out = array();
			foreach ($meta['display_list'] as $row) {
				if (! is_array($row)) {
					continue;
				}
				$label = trim((string) ($row['label'] ?? ''));
				$value = trim((string) ($row['value'] ?? ''));
				$key   = trim((string) ($row['key'] ?? ''));
				if ('' === $label || '' === $value) {
					continue;
				}
				if (array_key_exists($label, $out)) {
					$label = '' !== $key ? $label . ' (' . $key . ')' : $label . ' 2';
				}
				$out[ $label ] = $value;
			}
			if (! empty($out)) {
				return $out;
			}
		}

		$raw = array();
		if (isset($meta['display']) && is_array($meta['display'])) {
			$raw = $meta['display'];
		}

		$out = array();
		foreach ($raw as $label => $value) {
			$label = trim((string) $label);
			$value = trim((string) $value);
			if ('' === $label || '' === $value) {
				continue;
			}
			$out[ $label ] = $value;
		}

		return $out;
	}

	/**
	 * Repair incomplete bundle cart meta after session restore or legacy rows.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 * @return array<string, mixed>
	 */
	private static function repair_bundle_cart_meta(array $cart_item) {
		$meta = self::get_bundle_meta($cart_item);
		if (empty($meta)) {
			return $cart_item;
		}

		$product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
		$raw        = isset($meta['raw']) && is_array($meta['raw']) ? $meta['raw'] : array();
		if ($product_id < 1 || empty($raw)) {
			return $cart_item;
		}

		$plugin   = MBBB_Plugin::instance();
		$rows     = $plugin->format_choices_rows($product_id, $raw);
		$display  = $plugin->format_choices_for_display($product_id, $raw);
		$expected = count($plugin->get_resolved_slots($product_id));

		if ($expected > 0 && count($rows) >= $expected) {
			$meta['display_list'] = $rows;
			$meta['display']      = $display;
			$cart_item[ MBBB_Plugin::CART_META_KEY ] = $meta;
		}

		return $cart_item;
	}

	/**
	 * @param array<string, string> $summary Summary map.
	 * @return array<int, array{label: string, norm: string, value: string}>
	 */
	private static function build_allowed_summary_entries(array $summary) {
		$entries = array();
		foreach ($summary as $label => $value) {
			$label = trim((string) $label);
			$value = trim((string) $value);
			if ('' === $label || '' === $value) {
				continue;
			}
			$entries[] = array(
				'label' => $label,
				'norm'  => self::normalize_meta_label_key($label),
				'value' => $value,
			);
		}
		return $entries;
	}

	/**
	 * @param array<int, array{label: string, norm: string, value: string}> $entries Summary entries.
	 * @param string                                                         $meta_key Meta key.
	 * @param string                                                         $meta_value Meta value.
	 * @return bool
	 */
	private static function summary_entry_matches_meta(array $entries, $meta_key, $meta_value) {
		$meta_key   = trim((string) $meta_key);
		$meta_value = trim((string) $meta_value);
		$meta_norm  = self::normalize_meta_label_key($meta_key);

		foreach ($entries as $entry) {
			if ($entry['value'] !== $meta_value) {
				continue;
			}
			if ($entry['label'] === $meta_key || $entry['norm'] === $meta_norm) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalized label key for comparing bundle meta vs variation attributes.
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function normalize_meta_label_key($label) {
		$label = strtolower(wp_strip_all_tags((string) $label));
		$label = preg_replace('/[^a-z0-9]+/', '', $label);
		return is_string($label) ? $label : '';
	}

	/**
	 * Variation attribute labels for a bundle product (used to strip duplicate order meta).
	 *
	 * @param int $product_id Product ID.
	 * @return string[] Normalized labels.
	 */
	private static function get_variation_attribute_labels_for_product($product_id) {
		$product = wc_get_product($product_id);
		if (! $product) {
			return array();
		}

		$labels = array();
		foreach ($product->get_attributes() as $attribute) {
			if (! $attribute instanceof WC_Product_Attribute) {
				continue;
			}
			$labels[] = self::normalize_meta_label_key(wc_attribute_label($attribute->get_name()));
		}

		return array_values(array_filter(array_unique($labels)));
	}

	/**
	 * @param string               $display_key Key.
	 * @param stdClass|array       $meta Meta.
	 * @param WC_Order_Item_Product $item Item.
	 * @return string
	 */
	public function format_meta_key($display_key, $meta, $item) {
		unset($item);
		if (is_object($meta) && isset($meta->key) && '_mbbb_bundle_summary' === $meta->key) {
			return '';
		}
		return $display_key;
	}

}
