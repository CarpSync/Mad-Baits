<?php
/**
 * CLI checks for range launch decisions. Run: php theme/tests/range-launch-clock-test.php
 *
 * @package MadBaits
 */

define('ABSPATH', __DIR__);

require dirname(__DIR__) . '/inc/range-launch-clock.php';

$tz  = 'Europe/London';
$now_before_stp = mad_baits_parse_launch_datetime('2026-10-22 23:59:59', $tz);
$now_at_stp     = mad_baits_parse_launch_datetime('2026-10-23 00:00:00', $tz);
$now_before_swan = mad_baits_parse_launch_datetime('2026-12-31 23:59:59', $tz);
$now_swan_year   = mad_baits_parse_launch_datetime('2027-01-01 00:00:00', $tz);

$failures = array();

$expect = static function ($label, $actual, $expected) use (&$failures) {
	if ($actual !== $expected) {
		$failures[] = $label . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true);
	}
};

$expect(
	'STP bulk stays hidden the minute before launch',
	mad_baits_storefront_item_is_hidden(
		array(
			'public_from' => mad_baits_stp_bulk_launch_default(),
			'now'         => $now_before_stp,
			'timezone'    => $tz,
		)
	),
	true
);

$expect(
	'STP bulk is public at 23 October 2026 00:00 Europe/London',
	mad_baits_storefront_item_is_hidden(
		array(
			'public_from' => mad_baits_stp_bulk_launch_default(),
			'now'         => $now_at_stp,
			'timezone'    => $tz,
		)
	),
	false
);

$expect(
	'STP 1kg product with no schedule is public',
	mad_baits_storefront_item_is_hidden(
		array(
			'now'      => $now_before_stp,
			'timezone' => $tz,
		)
	),
	false
);

$expect(
	'BBB stays hidden',
	mad_baits_storefront_item_is_hidden(
		array(
			'retired'  => true,
			'now'      => $now_at_stp,
			'timezone' => $tz,
		)
	),
	true
);

$expect(
	'Swan stays hidden before 2027 even if marked live',
	mad_baits_storefront_item_is_hidden(
		array(
			'range_mode'     => 'live',
			'range_earliest' => mad_baits_swan_mussel_earliest_default(),
			'now'            => $now_before_swan,
			'timezone'       => $tz,
		)
	),
	true
);

$expect(
	'Swan stays hidden in 2027 until the owner marks it live',
	mad_baits_storefront_item_is_hidden(
		array(
			'range_mode'     => 'hidden',
			'range_earliest' => mad_baits_swan_mussel_earliest_default(),
			'now'            => $now_swan_year,
			'timezone'       => $tz,
		)
	),
	true
);

$expect(
	'Swan can go live on or after 1 January 2027 when the owner marks it live',
	mad_baits_storefront_item_is_hidden(
		array(
			'range_mode'     => 'live',
			'range_earliest' => mad_baits_swan_mussel_earliest_default(),
			'now'            => $now_swan_year,
			'timezone'       => $tz,
		)
	),
	false
);

$expect(
	'Swan bulk-style product does not inherit the STP date',
	mad_baits_storefront_item_is_hidden(
		array(
			'range_mode'     => 'hidden',
			'range_earliest' => mad_baits_swan_mussel_earliest_default(),
			'public_from'    => '',
			'now'            => $now_at_stp,
			'timezone'       => $tz,
		)
	),
	true
);

$expect(
	'Swan stays a draft at 31 December 2026 23:59:59 even if marked live',
	mad_baits_swan_storefront_post_status('live', $now_before_swan, $tz),
	'draft'
);

$expect(
	'Swan stays a draft at 1 January 2027 00:00:00 while the mode is hidden',
	mad_baits_swan_storefront_post_status('hidden', $now_swan_year, $tz),
	'draft'
);

$expect(
	'Swan publishes at 1 January 2027 00:00:00 only when the mode is live',
	mad_baits_swan_storefront_post_status('live', $now_swan_year, $tz),
	'publish'
);

$expect(
	'Swan stays a draft on the STP launch day even if marked live',
	mad_baits_swan_storefront_post_status('live', $now_at_stp, $tz),
	'draft'
);

$ready_variation = static function ($price, $managed = false, $qty = null) {
	return array(
		'price'        => $price,
		'manage_stock' => $managed,
		'stock_status' => 'instock',
		'stock_qty'    => $qty,
		'backorders'   => 'no',
		'virtual'      => false,
		'downloadable' => false,
		'tax_status'   => 'parent',
	);
};

$ready_snapshot = array(
	'virtual'        => false,
	'downloadable'   => false,
	'needs_shipping' => true,
	'tax_status'     => 'taxable',
	'tax_class'      => '',
	'variations'     => array(
		'15mm' => $ready_variation('24.00'),
		'18mm' => $ready_variation('24.00', true, 6),
	),
);

$blank_snapshot = $ready_snapshot;
$blank_snapshot['variations']['15mm']['price'] = '';
$blank_snapshot['variations']['18mm']['price'] = '';

$expect(
	'Complete STP deal stays private at 22 October 2026 23:59:59 Europe/London',
	mad_baits_stp_bulk_is_public($now_before_stp, $tz, $ready_snapshot),
	false
);

$expect(
	'Blank-price STP deal stays private at 23 October 2026 00:00:00 Europe/London',
	mad_baits_stp_bulk_is_public($now_at_stp, $tz, $blank_snapshot),
	false
);

$expect(
	'Complete STP deal becomes public at 23 October 2026 00:00:00 Europe/London',
	mad_baits_stp_bulk_is_public($now_at_stp, $tz, $ready_snapshot),
	true
);

$one_size = $ready_snapshot;
$one_size['variations']['18mm']['price'] = '0';
$expect('STP deal with a zero 18mm price stays private', mad_baits_stp_bulk_snapshot_is_purchasable($one_size), false);

$virtual = $ready_snapshot;
$virtual['virtual'] = true;
$virtual['needs_shipping'] = false;
$expect('Virtual STP deal stays private', mad_baits_stp_bulk_snapshot_is_purchasable($virtual), false);

$untaxed = $ready_snapshot;
$untaxed['tax_status'] = 'none';
$expect('Untaxed STP deal stays private', mad_baits_stp_bulk_snapshot_is_purchasable($untaxed), false);

$out = $ready_snapshot;
$out['variations']['15mm']['stock_status'] = 'outofstock';
$expect('Out-of-stock STP deal stays private', mad_baits_stp_bulk_snapshot_is_purchasable($out), false);

$untracked = $ready_snapshot;
$untracked['variations']['18mm']['manage_stock'] = true;
$untracked['variations']['18mm']['stock_qty'] = 0;
$expect('Tracked STP variation with no quantity stays private', mad_baits_stp_bulk_snapshot_is_purchasable($untracked), false);

$expect(
	'Special tag alone is not a Compulsive special',
	mad_baits_compulsive_special_qualifies(array('compulsive-special'), array('boilies'), array(), true, true),
	false
);

$expect(
	'STP product tagged as a Compulsive special is rejected',
	mad_baits_compulsive_special_qualifies(array('compulsive-special', 'stp'), array('boilies-stp'), array('stp'), true, true),
	false
);

$expect(
	'In-stock Compulsive product with the special tag qualifies',
	mad_baits_compulsive_special_qualifies(array('compulsive-special', 'compulsive-angler'), array('boilies'), array('compulsive-angler'), true, true),
	true
);

$expect(
	'Sold-out Compulsive special is hidden while the stock rule is on',
	mad_baits_compulsive_special_qualifies(array('compulsive-special', 'compulsive-angler'), array(), array(), false, true),
	false
);

$expect(
	'Sold-out Compulsive special can remain when the stock rule is off',
	mad_baits_compulsive_special_qualifies(array('compulsive-special'), array('compulsive-anglers'), array(), false, false),
	true
);

$new_shelf = $blank_snapshot;
$new_shelf['has_image'] = false;
$expect(
	'A newly provisioned STP 1kg product stays draft',
	mad_baits_stp_shelf_life_post_status(false),
	'draft'
);
$expect(
	'A newly provisioned STP 1kg product is not public with blank prices',
	mad_baits_stp_shelf_life_is_public($new_shelf, false),
	false
);
$blank_with_image = $blank_snapshot;
$blank_with_image['has_image'] = true;
$expect(
	'Blank STP 1kg prices stay private even if someone marks it published and adds an image',
	mad_baits_stp_shelf_life_is_public($blank_with_image, true),
	false
);

$complete_shelf = $ready_snapshot;
$complete_shelf['has_image'] = true;
$shelf_before_bulk = mad_baits_stp_shelf_life_is_public($complete_shelf, true);
$bulk_before = mad_baits_stp_bulk_is_public($now_before_stp, $tz, $ready_snapshot);
$shelf_on_bulk_day = mad_baits_stp_shelf_life_is_public($complete_shelf, true);
$bulk_on_day = mad_baits_stp_bulk_is_public($now_at_stp, $tz, $ready_snapshot);

$expect('Completed STP 1kg can be public before 23 October 2026', $shelf_before_bulk, true);
$expect('STP 1kg does not use the 23 October launch instant', $shelf_before_bulk === $shelf_on_bulk_day, true);
$expect('Publishing STP 1kg does not open the bulk deals before 23 October 2026', $bulk_before, false);
$expect('Publishing STP 1kg does not change the bulk result on launch day', $bulk_on_day, true);
$expect(
	'A complete STP 1kg product still waits for an explicit publish',
	mad_baits_stp_shelf_life_is_public($complete_shelf, false),
	false
);

$no_image = $complete_shelf;
$no_image['has_image'] = false;
$expect(
	'STP 1kg without a product image stays private after publish',
	mad_baits_stp_shelf_life_is_public($no_image, true),
	false
);
$expect(
	'STP bulk deals stay private at 22 October 2026 23:59:59 even when 1kg is complete',
	mad_baits_stp_bulk_is_public($now_before_stp, $tz, $ready_snapshot),
	false
);

$expect('STP 1kg 15mm is £12.95', mad_baits_stp_confirmed_variation_price('MB-STP-SL', '15mm'), '12.95');
$expect('STP 1kg 18mm is £12.95', mad_baits_stp_confirmed_variation_price('MB-STP-SL', '18mm'), '12.95');
$expect('STP 10kg 15mm is £119.90', mad_baits_stp_confirmed_variation_price('MB-STP-10KG', '15mm'), '119.90');
$expect('STP 10kg 18mm is £119.90', mad_baits_stp_confirmed_variation_price('MB-STP-10KG', '18mm'), '119.90');
$expect('STP 20kg 15mm is £239.80', mad_baits_stp_confirmed_variation_price('MB-STP-20KG', '15mm'), '239.80');
$expect('STP 20kg 18mm is £239.80', mad_baits_stp_confirmed_variation_price('MB-STP-20KG', '18mm'), '239.80');
$expect('Swan has no confirmed STP price', mad_baits_stp_confirmed_variation_price('MB-SWAN-SL', '15mm'), '');
$expect('A saved non-zero price is not replaced', mad_baits_should_apply_confirmed_price('10.00', '12.95'), false);
$expect('A blank price receives the confirmed price', mad_baits_should_apply_confirmed_price('', '12.95'), true);

$rate_pence = mad_baits_money_to_pence('59.95');
$expect('10kg is two times £59.95', mad_baits_money_to_pence('119.90'), $rate_pence * 2);
$expect('20kg is four times £59.95', mad_baits_money_to_pence('239.80'), $rate_pence * 4);

$priced_10kg = $ready_snapshot;
$priced_10kg['variations']['15mm']['price'] = mad_baits_stp_confirmed_variation_price('MB-STP-10KG', '15mm');
$priced_10kg['variations']['18mm']['price'] = mad_baits_stp_confirmed_variation_price('MB-STP-10KG', '18mm');
$priced_20kg = $ready_snapshot;
$priced_20kg['variations']['15mm']['price'] = mad_baits_stp_confirmed_variation_price('MB-STP-20KG', '15mm');
$priced_20kg['variations']['18mm']['price'] = mad_baits_stp_confirmed_variation_price('MB-STP-20KG', '18mm');
$priced_1kg = $ready_snapshot;
$priced_1kg['has_image'] = false;
$priced_1kg['variations']['15mm']['price'] = mad_baits_stp_confirmed_variation_price('MB-STP-SL', '15mm');
$priced_1kg['variations']['18mm']['price'] = mad_baits_stp_confirmed_variation_price('MB-STP-SL', '18mm');

$expect(
	'Priced STP 10kg stays hidden at 22 October 2026 23:59:59 Europe/London',
	mad_baits_stp_bulk_is_public($now_before_stp, $tz, $priced_10kg),
	false
);
$expect(
	'Priced STP 20kg stays hidden at 22 October 2026 23:59:59 Europe/London',
	mad_baits_stp_bulk_is_public($now_before_stp, $tz, $priced_20kg),
	false
);
$expect(
	'Priced STP 10kg can open at 23 October 2026 00:00:00 Europe/London',
	mad_baits_stp_bulk_is_public($now_at_stp, $tz, $priced_10kg),
	true
);
$expect(
	'Priced STP 20kg can open at 23 October 2026 00:00:00 Europe/London',
	mad_baits_stp_bulk_is_public($now_at_stp, $tz, $priced_20kg),
	true
);
$expect(
	'Priced STP 1kg stays draft until it is published',
	mad_baits_stp_shelf_life_is_public($priced_1kg, false),
	false
);
$expect(
	'Priced STP 1kg still needs an image before it is publish-ready',
	mad_baits_stp_shelf_life_is_public(array_merge($priced_1kg, array('has_image' => false)), true),
	false
);

if (! empty($failures)) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

echo "range launch checks passed\n";
exit(0);
