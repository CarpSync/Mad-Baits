<?php
/**
 * Capacitor native shell detection and iOS WebView assets.
 *
 * PayPal/WooCommerce SDK console warnings in WKWebView are often normal before
 * redirect; payment URLs should open via Capacitor Browser (capacitor-shell.js).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Detect Capacitor native WebView requests.
 *
 * @return bool
 */
function mad_baits_is_capacitor_native_request() {
	if (is_admin()) {
		return false;
	}

	$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
	if ('' === $ua) {
		return false;
	}

	return false !== stripos($ua, 'Capacitor');
}

/**
 * Detect Capacitor iOS shell.
 *
 * @return bool
 */
function mad_baits_is_capacitor_ios_request() {
	if (! mad_baits_is_capacitor_native_request()) {
		return false;
	}

	$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
	return false !== stripos($ua, 'iPhone')
		|| false !== stripos($ua, 'iPad')
		|| false !== stripos($ua, 'iOS');
}

/**
 * Enqueue Capacitor shell helpers for native app WebViews.
 *
 * @return void
 */
function mad_baits_enqueue_capacitor_shell_assets() {
	if (! mad_baits_is_capacitor_native_request()) {
		return;
	}

	$js_path  = get_theme_file_path('assets/js/capacitor-shell.js');
	$css_path = get_theme_file_path('assets/css/scoped/ios-shell.css');

	if (file_exists($js_path)) {
		wp_enqueue_script(
			'mad-baits-capacitor-shell',
			get_theme_file_uri('assets/js/capacitor-shell.js'),
			array(),
			function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($js_path) : (string) filemtime($js_path),
			true
		);
	}

	$camera_js = get_theme_file_path('assets/js/capacitor-camera.js');
	if (file_exists($camera_js)) {
		wp_enqueue_script(
			'mad-baits-capacitor-camera',
			get_theme_file_uri('assets/js/capacitor-camera.js'),
			array('mad-baits-capacitor-shell'),
			function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($camera_js) : (string) filemtime($camera_js),
			true
		);
	}

	if (mad_baits_is_capacitor_ios_request() && file_exists($css_path)) {
		wp_enqueue_style(
			'mad-baits-ios-shell',
			get_theme_file_uri('assets/css/scoped/ios-shell.css'),
			array('mad-baits-mobile-app'),
			function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($css_path) : (string) filemtime($css_path)
		);
	}
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_capacitor_shell_assets', 44);

/**
 * Body classes for Capacitor shell.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function mad_baits_capacitor_shell_body_class($classes) {
	if (mad_baits_is_capacitor_native_request()) {
		$classes[] = 'is-capacitor-app';
	}
	if (mad_baits_is_capacitor_ios_request()) {
		$classes[] = 'is-capacitor-ios';
		$classes[] = 'is-mobile-app-shell';
	}

	return $classes;
}
add_filter('body_class', 'mad_baits_capacitor_shell_body_class', 23);
