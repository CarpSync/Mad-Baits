<?php
/**
 * Bundle Manager unit checks. No WordPress or WooCommerce required.
 *
 *   php plugins/mad-baits-bundle-builder/tests/test-bundle-manager.php
 *
 * @package MadBaitsBundleBuilder
 */

if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__);
}

if (! function_exists('__')) {
	function __($text, $domain = null) {
		unset($domain);
		return $text;
	}
}
if (! function_exists('_n')) {
	function _n($single, $plural, $number, $domain = null) {
		unset($domain);
		return 1 === (int) $number ? $single : $plural;
	}
}
if (! function_exists('sanitize_text_field')) {
	function sanitize_text_field($text) {
		return trim(wp_strip_all_tags((string) $text));
	}
}
if (! function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($text) {
		return sanitize_text_field($text);
	}
}
if (! function_exists('sanitize_key')) {
	function sanitize_key($key) {
		$key = strtolower((string) $key);
		return preg_replace('/[^a-z0-9_\-]/', '', $key);
	}
}
if (! function_exists('sanitize_title')) {
	function sanitize_title($title) {
		$title = strtolower(trim((string) $title));
		$title = preg_replace('/[^a-z0-9]+/', '-', $title);
		return trim((string) $title, '-');
	}
}
if (! function_exists('absint')) {
	function absint($value) {
		return abs((int) $value);
	}
}
if (! function_exists('wp_json_encode')) {
	function wp_json_encode($data) {
		return json_encode($data);
	}
}
if (! function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags($text) {
		return trim(strip_tags((string) $text));
	}
}

require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-config.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-pricing.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-eligibility.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-compiler.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-validator.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-legacy.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-service.php';
require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-admin.php';

$failures = 0;

$assert = static function ($condition, $label) use (&$failures) {
	if (! $condition) {
		fwrite(STDERR, "FAIL: {$label}\n");
		++$failures;
		return;
	}
	fwrite(STDOUT, "PASS: {$label}\n");
};

$catalogue = array(
	array('id' => 1, 'parent_id' => 10, 'name' => 'Strawberry 15mm', 'range_slug' => 'strawberry', 'size_slug' => '15mm', 'attributes' => array('pa_size' => '15mm', 'pa_flavour' => 'strawberry'), 'category_slugs' => array('boilies'), 'in_stock' => true, 'purchasable' => true, 'price' => 8.50),
	array('id' => 2, 'parent_id' => 10, 'name' => 'Strawberry 18mm', 'range_slug' => 'strawberry', 'size_slug' => '18mm', 'attributes' => array('pa_size' => '18mm', 'pa_flavour' => 'strawberry'), 'category_slugs' => array('boilies'), 'in_stock' => true, 'purchasable' => true, 'price' => 8.50),
	array('id' => 3, 'parent_id' => 11, 'name' => 'The Nutz 15mm', 'range_slug' => 'the-nutz', 'size_slug' => '15mm', 'attributes' => array('pa_size' => '15mm', 'pa_flavour' => 'the-nutz'), 'category_slugs' => array('boilies'), 'in_stock' => true, 'purchasable' => true, 'price' => 9.00),
	array('id' => 4, 'parent_id' => 12, 'name' => 'Monster Crab 18mm', 'range_slug' => 'monster-crab', 'size_slug' => '18mm', 'attributes' => array('pa_size' => '18mm', 'pa_flavour' => 'monster-crab'), 'category_slugs' => array('boilies'), 'in_stock' => true, 'purchasable' => true, 'price' => 9.00),
	array('id' => 5, 'parent_id' => 11, 'name' => 'The Nutz 15mm tub', 'range_slug' => 'the-nutz', 'size_slug' => '15mm', 'attributes' => array('pa_size' => '15mm', 'pa_flavour' => 'the-nutz'), 'category_slugs' => array('boilies'), 'in_stock' => false, 'purchasable' => false, 'price' => 9.00),
	array('id' => 6, 'parent_id' => 13, 'name' => 'Squid 15mm', 'range_slug' => 'squid', 'size_slug' => '15mm', 'attributes' => array('pa_size' => '15mm'), 'category_slugs' => array('boilies'), 'in_stock' => true, 'purchasable' => true, 'price' => 7.00),
);

$owner = MBBB_Bundle_Config::sanitize(array(
	'name'          => '10kg Boilie Bundle',
	'bundle_type'   => 'mix_and_match',
	'quantity_mode' => 'exact',
	'quantity'      => 10,
	'unit'          => 'bags',
	'ranges'        => array('Strawberry', 'The Nutz', 'Monster Crab'),
	'sizes'         => array('15mm', '18mm'),
	'pricing'       => array('mode' => 'fixed', 'fixed_price' => '74.99'),
	'status'        => 'active',
	'display'       => array('helper_text' => 'Choose any 10 bags from the ranges below.', 'button_text' => 'Build Your Bundle', 'badge' => 'Save £15'),
));

$matched = MBBB_Bundle_Eligibility::matching($catalogue, $owner);
$live    = MBBB_Bundle_Eligibility::purchasable($catalogue, $owner);
$assert(5 === count($matched), 'ranges and sizes match five variations, including the out-of-stock tub');
$assert(4 === count($live), 'out-of-stock variation is excluded');
$assert(false === MBBB_Bundle_Eligibility::matches($catalogue[5], $owner), 'unticked Squid range is not eligible');
$simple = MBBB_Bundle_Config::sanitize($owner);
$simple['categories'] = array();
$simple['product_ids'] = array();
$simple['variation_ids'] = array();
$simple['attributes'] = array();
$assert(count($live) === count(MBBB_Bundle_Eligibility::purchasable($catalogue, $simple)), 'simple range and size selection keeps the same eligibility result');
$narrowed = MBBB_Bundle_Config::sanitize($owner);
$narrowed['categories'] = array('hookbaits');
$assert(0 === count(MBBB_Bundle_Eligibility::purchasable($catalogue, $narrowed)), 'advanced category filter still narrows a simple range and size selection');

$options = MBBB_Bundle_Eligibility::to_slot_options($live);
$slots   = MBBB_Bundle_Compiler::compile($owner, $options);
$assert(10 === count($slots), 'new 10-item bundle creates 10 choices');
$assert(! empty($slots[0]['required']) && ! empty($slots[9]['required']), 'exact quantity marks every choice required');
$assert(4 === count($slots[0]['manual_options']), 'each choice only lists purchasable products');
$assert('74.99' === (string) MBBB_Bundle_Pricing::calculate($owner)['total'], 'fixed-price bundle calculates £74.99');

$twenty = $owner;
$twenty['quantity'] = 20;
$twenty['min_quantity'] = 20;
$twenty['max_quantity'] = 20;
$twenty_slots = MBBB_Bundle_Compiler::compile(MBBB_Bundle_Config::sanitize($twenty), $options);
$assert(20 === count($twenty_slots), 'new 20-item bundle creates 20 choices');

$range = MBBB_Bundle_Config::sanitize(array(
	'name'          => 'Flexible bundle',
	'quantity_mode' => 'minimum',
	'min_quantity'  => 10,
	'max_quantity'  => 20,
	'multiple_of'   => 10,
	'ranges'        => array('strawberry'),
	'pricing'       => array('mode' => 'fixed', 'fixed_price' => '10'),
));
$range_slots = MBBB_Bundle_Compiler::compile($range, $options);
$assert(20 === count($range_slots), 'maximum quantity creates the optional extra choices');
$assert(! empty($range_slots[9]['required']) && empty($range_slots[10]['required']), 'minimum quantity stays required and the rest are optional');
$assert(! empty(MBBB_Bundle_Validator::selection_errors($owner, 9)), 'exact quantity rejects 9 choices');
$assert(empty(MBBB_Bundle_Validator::selection_errors($owner, 10)), 'exact quantity accepts 10 choices');
$assert(! empty(MBBB_Bundle_Validator::selection_errors($range, 15)), 'multiples of 10 are enforced');

$percent = MBBB_Bundle_Config::sanitize(array(
	'pricing' => array('mode' => 'percent', 'percent' => 10),
));
$amount = MBBB_Bundle_Config::sanitize(array(
	'pricing' => array('mode' => 'amount', 'amount' => '10'),
));
$assert(90.0 === MBBB_Bundle_Pricing::calculate($percent, array(50, 50))['total'], '10% discount calculates correctly');
$assert(70.0 === MBBB_Bundle_Pricing::calculate($amount, array(40, 40))['total'], '£10 discount calculates correctly');

$invalid = MBBB_Bundle_Config::sanitize(array(
	'name'     => '',
	'status'   => 'active',
	'quantity' => 0,
	'ranges'   => array(),
	'pricing'  => array('mode' => 'fixed', 'fixed_price' => ''),
	'start_date' => '2026-12-01',
	'end_date'   => '2026-01-01',
));
$invalid_plan = MBBB_Bundle_Service::plan_save(null, $invalid, true, 'activate', array(
	'eligible_total' => 0,
	'eligible_purchasable' => 0,
	'will_compile_priced_options' => false,
));
$invalid_text = implode(' ', $invalid_plan['errors']);
$assert(false !== strpos($invalid_text, 'Give this bundle a name'), 'activation requires a name');
$assert(false !== strpos($invalid_text, 'start date needs to be before the end date'), 'start date after end date is rejected');
$assert(false === $invalid_plan['compile'], 'invalid bundle is not activated');

$missing_range = MBBB_Bundle_Config::sanitize(array(
	'name' => '10kg Boilie Bundle',
	'quantity' => 10,
	'ranges' => array(),
	'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
));
$missing_plan = MBBB_Bundle_Service::plan_save(null, $missing_range, true, 'activate', array(
	'eligible_purchasable' => 0,
	'eligible_total' => 0,
));
$assert(false !== strpos(implode(' ', $missing_plan['errors']), 'Choose at least one bait range'), 'activation requires a bait range');

$ready = MBBB_Bundle_Service::plan_save(null, $owner, true, 'activate', array(
	'eligible_total' => 4,
	'eligible_purchasable' => 24,
	'will_compile_priced_options' => true,
));
$assert(true === $ready['compile'] && empty($ready['errors']), 'a complete 10-bag bundle can be activated');
$assert('bundle-manager' === $ready['config']['managed_by'], 'new bundles are marked as manager-owned');

$legacy = MBBB_Bundle_Legacy::project(array(
	'name' => '10KG Boilie Deal',
	'post_status' => 'publish',
	'enabled' => true,
	'regular_price' => '89.99',
	'slots' => array(
		array('label' => '5KG Split 1', 'key' => '5kg-split-1'),
		array('label' => '5KG Split 2', 'key' => '5kg-split-2'),
		array('label' => 'Hookbait 1', 'key' => 'hookbait-1'),
		array('label' => 'Hookbait 2', 'key' => 'hookbait-2'),
	),
	'deal_meta' => array('deal_type' => 'mix_and_match', 'boilie_ranges' => array('asbo', 'pandemic')),
));
$assert(! empty($legacy['preserve_slots']), 'existing bundle keeps its original choices');
$assert(2 === (int) $legacy['quantity'], 'existing 10kg deal reports its boilie choices');
$assert('89.99' === (string) $legacy['pricing']['fixed_price'], 'existing bundle price is read from the product');
$assert(false !== strpos(MBBB_Bundle_Legacy::quantity_label(array(
	array('label' => '5KG Split 1'),
	array('label' => '5KG Split 2'),
	array('label' => '5KG Split 3'),
	array('label' => '5KG Split 4'),
	array('label' => 'Hookbait 1'),
)), '4 boilie'), 'existing 20kg deal is described from its current choices');

$renamed = $legacy;
$renamed['name'] = '10KG Boilie Deal updated';
$renamed['pricing']['fixed_price'] = '79.99';
$edit = MBBB_Bundle_Service::plan_save($legacy, $renamed, false, 'save', array('legacy_slots_priced' => false));
$assert(false === $edit['compile'] && empty($edit['errors']), 'existing bundle can be edited without rebuilding choices');
$assert('79.99' === (string) $edit['config']['pricing']['fixed_price'], 'edited price is kept');
$assert(! empty($edit['config']['preserve_slots']), 'edit does not discard the original choices');

$changed = $legacy;
$changed['ranges'] = array('strawberry');
$blocked = MBBB_Bundle_Service::plan_save($legacy, $changed, false, 'save', array());
$assert(! empty($blocked['errors']) && false === $blocked['compile'], 'changing ranges on an existing bundle needs confirmation');
$allowed = MBBB_Bundle_Service::plan_save($legacy, $changed, true, 'save', array(
	'eligible_purchasable' => 3,
	'will_compile_priced_options' => true,
	'legacy_slots_priced' => false,
));
$assert(true === $allowed['compile'], 'confirmed updates replace customer choices');

$copy = MBBB_Bundle_Config::duplicate_config($owner, '10kg Boilie Bundle');
$assert('10kg Boilie Bundle Copy' === $copy['name'], 'duplicate name uses Copy');
$assert('draft' === $copy['status'], 'duplicate starts as a draft');
$assert($copy['ranges'] === $owner['ranges'], 'duplicate keeps the eligible ranges');

$disabled = $owner;
$disabled['status'] = 'disabled';
$assert(false === MBBB_Bundle_Config::is_purchasable($disabled, '2026-10-07'), 'disabled bundle cannot be purchased');
$scheduled = $owner;
$scheduled['status'] = 'active';
$scheduled['start_date'] = '2026-12-01';
$assert('scheduled' === MBBB_Bundle_Config::effective_status($scheduled, '2026-10-07'), 'future start date is scheduled');
$assert(false === MBBB_Bundle_Config::is_purchasable($scheduled, '2026-10-07'), 'scheduled bundle cannot be purchased yet');
$assert(true === MBBB_Bundle_Config::is_purchasable($owner, '2026-10-07'), 'active bundle can be purchased');

$fixed = MBBB_Bundle_Config::sanitize(array(
	'name' => 'Session bucket',
	'bundle_type' => 'fixed',
	'fixed_items' => array(
		array('variation_id' => 1, 'quantity' => 1, 'label' => 'Strawberry 15mm'),
		array('variation_id' => 3, 'quantity' => 2, 'label' => 'The Nutz 15mm'),
	),
	'pricing' => array('mode' => 'fixed', 'fixed_price' => '40'),
));
$fixed_slots = MBBB_Bundle_Compiler::compile($fixed, $options);
$assert(3 === count($fixed_slots), 'fixed bundle repeats a product when quantity is more than one');
$assert(! empty($fixed_slots[0]['auto_select']), 'fixed bundle choices can be preselected');

$admin = file_get_contents(dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-admin.php');
$cart  = file_get_contents(dirname(__DIR__) . '/includes/class-mbbb-cart.php');
$assert(false !== strpos($admin, 'current_user_can'), 'admin screens check capability');
$assert(false !== strpos($admin, 'check_admin_referer'), 'admin actions check nonces');
$assert(false !== strpos($admin, 'check_ajax_referer'), 'eligibility requests check nonces');
$assert('manage_woocommerce' === MBBB_Bundle_Admin::capability(), 'only shop managers and administrators can manage bundles');
$assert(false !== strpos($cart, 'MBBB_Bundle_Runtime::validate_posted_choices'), 'existing cart validation still runs the bundle rules');

$first = MBBB_Bundle_Config::structure_signature($owner);
$again = MBBB_Bundle_Config::structure_signature($owner);
$assert($first === $again, 'reading an existing bundle does not change its signature');

$percent_config = MBBB_Bundle_Config::sanitize(array(
	'name' => 'Percent bundle',
	'status' => 'active',
	'quantity' => 10,
	'ranges' => array('strawberry'),
	'pricing' => array('mode' => 'percent', 'percent' => 10, 'fixed_price' => ''),
));
$components = array(8.5, 8.5, 8.5, 8.5, 8.5, 8.5, 8.5, 8.5, 8.5, 8.5);
$unit = MBBB_Bundle_Pricing::safe_unit_price($percent_config, $components, 10);
$unit_again = MBBB_Bundle_Pricing::safe_unit_price($percent_config, $components, 10);
$assert(abs($unit - 76.5) < 0.001, '10% discount unit price is £76.50');
$assert(abs($unit_again - $unit) < 0.001, 'recalculating the same choices does not stack the discount');
$stacked = MBBB_Bundle_Pricing::calculate($percent_config, array($unit));
$assert($stacked['total'] < $unit, 'feeding the discounted price back would stack, so the cart must not do that');
$assert(abs(MBBB_Bundle_Pricing::line_total($unit, 2) - 153.0) < 0.001, 'quantity 2 charges two bundle unit prices');
$assert(abs(MBBB_Bundle_Pricing::line_total($unit, 1) - $unit) < 0.001, 'quantity 1 matches the unit price used by the mini-cart and checkout');
$coupon_line = round(MBBB_Bundle_Pricing::line_total($unit, 2) * 0.9, 2);
$assert(abs($coupon_line - 137.7) < 0.001 && abs($unit_again - 76.5) < 0.001, 'a later coupon does not change the bundle unit price');

$amount_config = MBBB_Bundle_Config::sanitize(array(
	'name' => 'Amount bundle',
	'status' => 'active',
	'quantity' => 10,
	'ranges' => array('strawberry'),
	'pricing' => array('mode' => 'amount', 'amount' => '10'),
));
$amount_unit = MBBB_Bundle_Pricing::safe_unit_price($amount_config, $components, 10);
$assert(abs($amount_unit - 75.0) < 0.001, '£10 discount unit price is £75.00');
$assert(abs(MBBB_Bundle_Pricing::line_total($amount_unit, 2) - 150.0) < 0.001, 'quantity 2 takes £10 off each bundle');
$assert(null === MBBB_Bundle_Pricing::safe_unit_price($amount_config, array(8.5, null), 2), 'amount discount fails safe when a choice price is missing');
$assert(null === MBBB_Bundle_Pricing::safe_unit_price($percent_config, array(8.5), 2), 'percentage discount fails safe when a choice is missing');
$assert(null === MBBB_Bundle_Pricing::safe_unit_price($owner, $components, 10), 'fixed-price bundles do not get a cart discount price');

$sale_product = new class {
	public function get_sale_price($context = 'view') {
		unset($context);
		return '7.00';
	}
	public function get_regular_price($context = 'view') {
		unset($context);
		return '8.50';
	}
	public function get_price($context = 'view') {
		unset($context);
		return '1.00';
	}
};
$regular_product = new class {
	public function get_sale_price($context = 'view') {
		unset($context);
		return '';
	}
	public function get_regular_price($context = 'view') {
		unset($context);
		return '8.50';
	}
	public function get_price($context = 'view') {
		unset($context);
		return '1.00';
	}
};
$assert(abs(MBBB_Bundle_Pricing::component_base_price($sale_product) - 7.0) < 0.001, 'component price uses the catalogue sale price');
$assert(abs(MBBB_Bundle_Pricing::component_base_price($regular_product) - 8.5) < 0.001, 'component price ignores a mutated cart price');
$assert(null === MBBB_Bundle_Pricing::component_base_price(null), 'missing component price cannot be resolved');

$few_stock = MBBB_Bundle_Service::plan_save(null, $owner, true, 'activate', array(
	'eligible_total' => 2,
	'eligible_purchasable' => 2,
	'will_compile_priced_options' => true,
));
$assert(empty($few_stock['errors']), 'fewer distinct products than the quantity can still be activated');
$none_stock = MBBB_Bundle_Service::plan_save(null, $owner, true, 'activate', array(
	'eligible_total' => 2,
	'eligible_purchasable' => 0,
	'will_compile_priced_options' => true,
));
$assert(false !== strpos(implode(' ', $none_stock['errors']), 'None of the selected products'), 'activation stops when nothing can be bought');

$managed_percent = $percent_config;
$managed_percent['managed_by'] = 'bundle-manager';
$managed_percent['status'] = 'disabled';
$reenable_blocked = MBBB_Bundle_Service::plan_save($managed_percent, $managed_percent, false, 'activate', array(
	'eligible_total' => 4,
	'eligible_purchasable' => 4,
	'will_compile_priced_options' => false,
	'legacy_slots_priced' => false,
));
$assert(! empty($reenable_blocked['errors']), 're-enabling a discount bundle fails when choice prices are unknown');
$reenable_ok = MBBB_Bundle_Service::plan_save($managed_percent, $managed_percent, false, 'activate', array(
	'eligible_total' => 4,
	'eligible_purchasable' => 4,
	'will_compile_priced_options' => false,
	'legacy_slots_priced' => true,
));
$assert(empty($reenable_ok['errors']) && 'active' === $reenable_ok['config']['status'], 're-enabling a priced discount bundle succeeds');

$today = '2026-10-07';
$draft_state = MBBB_Bundle_Config::publication_state(array('status' => 'draft'), $today);
$active_state = MBBB_Bundle_Config::publication_state(array('status' => 'active'), $today);
$disabled_state = MBBB_Bundle_Config::publication_state(array('status' => 'disabled', 'start_date' => '2026-01-01'), $today);
$scheduled_state = MBBB_Bundle_Config::publication_state(array('status' => 'active', 'start_date' => '2026-12-01'), $today);
$ended_config = array('status' => 'active', 'end_date' => '2026-10-01');
$ended_state = MBBB_Bundle_Config::publication_state($ended_config, $today);
$assert('draft' === $draft_state['post_status'] && 'hidden' === $draft_state['catalog_visibility'] && false === $draft_state['purchasable'], 'draft bundle is hidden and not purchasable');
$assert('publish' === $active_state['post_status'] && 'visible' === $active_state['catalog_visibility'] && true === $active_state['purchasable'], 'active bundle is visible and purchasable');
$assert('draft' === $disabled_state['post_status'] && false === $disabled_state['purchasable'], 'disabled bundle is unpublished');
$assert('publish' === $scheduled_state['post_status'] && 'hidden' === $scheduled_state['catalog_visibility'] && false === $scheduled_state['purchasable'], 'scheduled bundle stays hidden until the start date');
$assert('Ended' === MBBB_Bundle_Config::status_label($ended_state['effective'], $ended_config, $today) && false === $ended_state['purchasable'] && 'hidden' === $ended_state['catalog_visibility'], 'ended bundle is hidden and not purchasable');
$reenabled = MBBB_Bundle_Config::publication_state(array('status' => 'active'), $today);
$assert(true === $reenabled['purchasable'] && 'visible' === $reenabled['catalog_visibility'], 're-enabling publishes the bundle for sale');

if (! class_exists('WC_Product')) {
	class WC_Product {
		public $id;
		public $stock = true;
		public $price = 10;
		public $sets = 0;
		public function __construct($id = 0) {
			$this->id = (int) $id;
		}
		public function get_id() {
			return $this->id;
		}
		public function is_purchasable() {
			return (bool) $this->stock;
		}
		public function is_in_stock() {
			return (bool) $this->stock;
		}
		public function get_name() {
			return 'Bait';
		}
		public function set_price($price) {
			$this->sets++;
			$this->price = $price;
			if (1 === $this->sets && 59 === $this->id && isset($GLOBALS['mbbb_test_cart'])) {
				MBBB_Bundle_Runtime::apply_cart_prices($GLOBALS['mbbb_test_cart']);
			}
		}
	}
}
if (! class_exists('WP_Error')) {
	class WP_Error {
		public $message;
		public function __construct($code = '', $message = '') {
			unset($code);
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if (! function_exists('is_wp_error')) {
	function is_wp_error($thing) {
		return $thing instanceof WP_Error;
	}
}
if (! function_exists('current_time')) {
	function current_time($type, $gmt = 0) {
		unset($gmt);
		return 'Y-m-d' === $type ? '2026-10-07' : '2026-10-07 00:00:00';
	}
}
if (! function_exists('get_post_meta')) {
	function get_post_meta($post_id, $key = '', $single = false) {
		unset($single);
		$post_id = (int) $post_id;
		if (! isset($GLOBALS['mbbb_test_meta'][ $post_id ][ $key ])) {
			return '';
		}
		return $GLOBALS['mbbb_test_meta'][ $post_id ][ $key ];
	}
}
if (! function_exists('wc_get_product')) {
	function wc_get_product($id) {
		$product = new WC_Product((int) $id);
		$product->stock = 5 !== (int) $id;
		return $product;
	}
}
if (! class_exists('MBBB_Fake_Cart')) {
	class MBBB_Fake_Cart {
		public $items = array();
		public function get_cart() {
			return $this->items;
		}
	}
}

require dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-runtime.php';

$GLOBALS['mbbb_test_meta'] = array(
	55 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Discount',
			'status' => 'active',
			'pricing' => array('mode' => 'percent', 'percent' => 10),
		)),
	),
	56 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Scheduled',
			'status' => 'active',
			'start_date' => '2026-12-01',
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
		)),
	),
	57 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Legacy',
			'status' => 'active',
			'preserve_slots' => true,
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '89.99'),
		)),
	),
	58 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Managed',
			'status' => 'active',
			'managed_by' => 'bundle-manager',
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
			'stock' => array('hide_unavailable' => true, 'prevent_oos' => true),
		)),
	),
	59 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Fixed manager',
			'status' => 'active',
			'managed_by' => 'bundle-manager',
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
		)),
	),
	61 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Ended',
			'status' => 'active',
			'end_date' => '2026-10-01',
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
		)),
	),
	62 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Draft',
			'status' => 'draft',
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
		)),
	),
	63 => array(
		MBBB_Bundle_Config::META_KEY => MBBB_Bundle_Config::sanitize(array(
			'name' => 'Disabled',
			'status' => 'disabled',
			'pricing' => array('mode' => 'fixed', 'fixed_price' => '74.99'),
		)),
	),
);

$legacy_product = new WC_Product(99);
$legacy_html = '<span class="price">£89.99</span>';
$legacy_options = array(
	array('value' => 'keep', 'product_id' => 5, 'label' => 'Existing choice'),
);
$assert(true === MBBB_Bundle_Runtime::filter_purchasable(true, $legacy_product), 'legacy product with no owner meta stays purchasable');
$assert(false === MBBB_Bundle_Runtime::filter_purchasable(false, $legacy_product), 'legacy product keeps an existing not-purchasable result');
$assert(true === MBBB_Bundle_Runtime::filter_visible(true, 99), 'legacy product stays visible in the catalogue');
$assert(false === MBBB_Bundle_Runtime::filter_visible(false, 99), 'legacy product keeps an existing hidden result');
$assert($legacy_html === MBBB_Bundle_Runtime::filter_price_html($legacy_html, $legacy_product), 'legacy product price HTML is unchanged');
$assert($legacy_options === MBBB_Bundle_Runtime::filter_slot_options($legacy_options, array('key' => 'split'), 99), 'legacy slot options are not filtered');
$assert(true === MBBB_Bundle_Runtime::validate_posted_choices(99, array('5kg-split-1' => 'asbo')), 'legacy add to cart skips owner rules');
$assert(null === MBBB_Bundle_Runtime::direct_purchase_error(99), 'legacy direct add to cart is not blocked by owner rules');
$legacy_cart_product = new WC_Product(99);
$legacy_cart = new MBBB_Fake_Cart();
$legacy_cart->items = array(array('data' => $legacy_cart_product, 'product_id' => 99));
MBBB_Bundle_Runtime::apply_cart_prices($legacy_cart);
$assert(0 === $legacy_cart_product->sets, 'legacy cart price is not rewritten');

$scheduled_product = new WC_Product(56);
$assert(false === MBBB_Bundle_Runtime::filter_purchasable(true, $scheduled_product), 'scheduled bundle is not purchasable before the start date');
$assert(false === MBBB_Bundle_Runtime::filter_visible(true, 56), 'scheduled bundle is hidden from the catalogue');
$assert(is_wp_error(MBBB_Bundle_Runtime::direct_purchase_error(56)), 'scheduled bundle cannot be added directly');
$assert(is_wp_error(MBBB_Bundle_Runtime::validate_posted_choices(56, array('choice-1' => '1'))), 'scheduled bundle fails add-to-cart validation');
$ended_product = new WC_Product(61);
$assert(false === MBBB_Bundle_Runtime::filter_purchasable(true, $ended_product) && false === MBBB_Bundle_Runtime::filter_visible(true, 61), 'ended bundle is hidden and cannot be purchased');
$assert(false === MBBB_Bundle_Runtime::filter_purchasable(true, new WC_Product(62)), 'draft bundle is not purchasable');
$assert(false === MBBB_Bundle_Runtime::filter_visible(true, 62), 'draft bundle is hidden');
$assert(false === MBBB_Bundle_Runtime::filter_purchasable(true, new WC_Product(63)), 'disabled bundle is not purchasable');
$assert(false === MBBB_Bundle_Runtime::filter_visible(true, 63), 'disabled bundle is hidden');
$assert(true === MBBB_Bundle_Runtime::filter_purchasable(true, new WC_Product(59)), 'active manager bundle can be purchased');
$assert(true === MBBB_Bundle_Runtime::filter_visible(true, 59), 'active manager bundle stays in the catalogue');

$preserved = MBBB_Bundle_Runtime::filter_slot_options($legacy_options, array(), 57);
$assert($preserved === $legacy_options, 'a legacy bundle keeps its choices after a price-only edit');
$managed_options = MBBB_Bundle_Runtime::filter_slot_options(array(
	array('value' => '1', 'product_id' => 1, 'label' => 'In stock'),
	array('value' => '5', 'product_id' => 5, 'label' => 'Out of stock'),
), array(), 58);
$assert(1 === count($managed_options) && '1' === (string) $managed_options[0]['value'], 'out-of-stock choices are removed from manager bundles');

$fixed_cart_product = new WC_Product(59);
$fixed_cart = new MBBB_Fake_Cart();
$fixed_cart->items = array(array('data' => $fixed_cart_product, 'product_id' => 59));
$GLOBALS['mbbb_test_cart'] = $fixed_cart;
MBBB_Bundle_Runtime::apply_cart_prices($fixed_cart);
$assert(1 === $fixed_cart_product->sets && abs((float) $fixed_cart_product->price - 74.99) < 0.001, 'a nested cart calculation does not apply the fixed price twice');
MBBB_Bundle_Runtime::apply_cart_prices($fixed_cart);
$assert(2 === $fixed_cart_product->sets && abs((float) $fixed_cart_product->price - 74.99) < 0.001, 'running cart totals again keeps the £74.99 parent price');

$discount_cart_product = new WC_Product(55);
$discount_cart = new MBBB_Fake_Cart();
$discount_cart->items = array(array('data' => $discount_cart_product, 'product_id' => 55, 'mbbb_choices' => array('raw' => array('choice-1' => '1'))));
MBBB_Bundle_Runtime::apply_cart_prices($discount_cart);
$assert(0 === $discount_cart_product->sets && 10 === (int) $discount_cart_product->price, 'unresolved discount prices are not written onto the cart');

$probe = new WC_Product(0);
$resolved = MBBB_Bundle_Runtime::apply_resolved_price($probe, $percent_config, $components, 10);
$resolved_again = MBBB_Bundle_Runtime::apply_resolved_price($probe, $percent_config, $components, 10);
$assert(abs((float) $resolved - 76.5) < 0.001 && abs((float) $resolved_again - 76.5) < 0.001, 'resolved discount price stays the same when totals are recalculated');
$assert(null === MBBB_Bundle_Runtime::apply_resolved_price($probe, $percent_config, array(8.5, null), 2), 'unresolved choices do not change the cart price');

$runtime_source = file_get_contents(dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-runtime.php');
$cart_source = file_get_contents(dirname(__DIR__) . '/includes/class-mbbb-cart.php');
$assert(false !== strpos($runtime_source, 'config_for'), 'runtime hooks read owner meta before changing a product');
$assert(false !== strpos($cart_source, 'direct_purchase_error'), 'direct add to cart checks owner availability');
$assert(false === strpos($runtime_source, 'wc_get_orders'), 'bundle runtime does not query orders');

$assert(true === MBBB_Bundle_Admin::is_boilie_diameter('15mm', '15mm'), '15mm is a boilie size');
$assert(true === MBBB_Bundle_Admin::is_boilie_diameter('18mm', '18mm'), '18mm is a boilie size');
$assert(false === MBBB_Bundle_Admin::is_boilie_diameter('1kg', '1kg'), 'kilogram weights are not simple boilie sizes');
$assert(false === MBBB_Bundle_Admin::is_boilie_diameter('500ml', '500ml'), 'millilitre volumes are not simple boilie sizes');
$assert(false === MBBB_Bundle_Admin::is_boilie_diameter('xl', 'XL'), 'clothing sizes are not simple boilie sizes');
$assert(false === MBBB_Bundle_Admin::is_boilie_diameter('15mm-skinz', '15mm Skinz'), 'hookbait size labels stay out of the simple size list');
$size_split = MBBB_Bundle_Admin::split_sizes(array(
	'sizes' => array(
		'15mm' => '15mm',
		'18mm' => '18mm',
		'12mm' => '12mm',
		'1kg' => '1kg',
		'500ml' => '500ml',
		'xl' => 'XL',
		'15mm-skinz' => '15mm Skinz',
	),
	'variations' => array(
		array('size_slug' => '15mm', 'size_label' => '15mm', 'range_slug' => 'absorb'),
		array('size_slug' => '18mm', 'size_label' => '18mm', 'range_slug' => 'absorb'),
		array('size_slug' => '18mm', 'size_label' => '18mm', 'range_slug' => 'calamari'),
		array('size_slug' => '12mm', 'size_label' => '12mm', 'range_slug' => 'bsb'),
		array('size_slug' => '1kg', 'size_label' => '1kg', 'range_slug' => 'absorb'),
		array('size_slug' => '15mm-skinz', 'size_label' => '15mm Skinz', 'range_slug' => 'absorb'),
		array('size_slug' => 'xl', 'size_label' => 'XL', 'range_slug' => 'shirts'),
	),
));
$assert(array('12mm', '15mm', '18mm') === array_keys($size_split['sizes']), 'simple sizes are only boilie diameters found on a range');
$assert(false !== strpos($size_split['range_attrs']['15mm'], 'absorb'), '15mm stays tied to the boilie range that sells it');
$assert(false !== strpos($size_split['range_attrs']['18mm'], 'calamari'), '18mm stays tied to every selected boilie range');
$assert(isset($size_split['other']['1kg'], $size_split['other']['500ml'], $size_split['other']['xl'], $size_split['other']['15mm-skinz']), 'weights, volumes, clothing, and hookbait sizes stay in Advanced Options');
$assert(false === isset($size_split['sizes']['1kg']), 'a kilogram weight is not offered as a simple boilie size');
$editor_source = file_get_contents(dirname(__DIR__) . '/includes/bundle-manager/class-mbbb-bundle-admin.php');
$script_source = file_get_contents(dirname(__DIR__) . '/assets/js/mb-bundle-manager.js');
$assert(false !== strpos($editor_source, '! $is_new && ! $legacy && self::advanced_is_in_use'), 'a new bundle does not auto-open Advanced Options');
$assert(false !== strpos($script_source, 'closeAdvanced()'), 'presets close Advanced Options');

if ($failures > 0) {
	fwrite(STDERR, "{$failures} failed.\n");
	exit(1);
}

fwrite(STDOUT, "All bundle manager checks passed.\n");
exit(0);
