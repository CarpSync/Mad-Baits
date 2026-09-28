<?php
/**
 * Return URL assertions for MBMC native checkout.
 *
 * Run on the WordPress server (requires WooCommerce):
 *   wp eval-file wp-content/plugins/mad-baits-mobile-connector/tests/test-return-url.php
 *
 * @package MadBaitsMobileConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

if ( ! class_exists( 'MBMC_Native_Checkout' ) || ! function_exists( 'wc_create_order' ) ) {
	fwrite( STDERR, "MBMC_Native_Checkout or WooCommerce unavailable.\n" );
	exit( 1 );
}

$failures = 0;

$assert_contains = static function ( $needle, $haystack, $label ) use ( &$failures ) {
	if ( false === strpos( (string) $haystack, (string) $needle ) ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		++$failures;
		return;
	}
	fwrite( STDOUT, "PASS: {$label}\n" );
};

$base = MBMC_Native_Checkout::get_checkout_return_url_base();
$assert_contains( 'order-received', $base, 'base URL contains order-received' );
$assert_contains( 'utm_nooverride=1', $base, 'base URL contains utm_nooverride=1' );

$order = wc_create_order();
$order->save();
$return = MBMC_Native_Checkout::get_payment_return_url( $order );

$assert_contains( 'order-received', $return, 'order return URL contains order-received' );
$assert_contains( 'key=' . $order->get_order_key(), $return, 'order return URL contains order key' );
$assert_contains( 'utm_nooverride=1', $return, 'order return URL contains utm_nooverride=1' );
$assert_contains( (string) $order->get_id(), $return, 'order return URL contains order id' );

$order->delete( true );

if ( $failures > 0 ) {
	fwrite( STDERR, "{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

fwrite( STDOUT, "All return URL assertions passed.\n" );
exit( 0 );
