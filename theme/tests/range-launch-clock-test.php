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

if (! empty($failures)) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

echo "range launch checks passed\n";
exit(0);
