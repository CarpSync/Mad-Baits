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

if (! empty($failures)) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

echo "range launch checks passed\n";
exit(0);
