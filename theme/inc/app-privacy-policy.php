<?php
/**
 * App privacy policy direct route.
 *
 * Provides a public URL for app-store compliance without requiring
 * the page to be added to WordPress navigation menus.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register pretty URL route for app privacy policy.
 *
 * URL: /app-privacy-policy/
 *
 * @return void
 */
function mad_baits_register_app_privacy_policy_route() {
	add_rewrite_rule('^app-privacy-policy/?$', 'index.php?mad_app_privacy_policy=1', 'top');
}
add_action('init', 'mad_baits_register_app_privacy_policy_route');

/**
 * Register custom query var.
 *
 * @param array<int, string> $vars Existing query vars.
 * @return array<int, string>
 */
function mad_baits_register_app_privacy_policy_query_var($vars) {
	$vars[] = 'mad_app_privacy_policy';
	return $vars;
}
add_filter('query_vars', 'mad_baits_register_app_privacy_policy_query_var');

/**
 * Fallback router for environments where rewrite rules are not flushed yet.
 *
 * @param WP $wp WordPress environment.
 * @return void
 */
function mad_baits_app_privacy_policy_parse_request($wp) {
	if (! isset($wp->request)) {
		return;
	}

	$request_path = trim((string) $wp->request, '/');
	if ('app-privacy-policy' !== $request_path) {
		return;
	}

	$wp->query_vars['mad_app_privacy_policy'] = '1';
}
add_action('parse_request', 'mad_baits_app_privacy_policy_parse_request');

/**
 * Route custom URL to dedicated privacy template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function mad_baits_use_app_privacy_policy_template($template) {
	if ('1' !== (string) get_query_var('mad_app_privacy_policy')) {
		return $template;
	}

	$custom_template = get_theme_file_path('/template-app-privacy-policy.php');
	if (file_exists($custom_template)) {
		status_header(200);
		return $custom_template;
	}

	return $template;
}
add_filter('template_include', 'mad_baits_use_app_privacy_policy_template');

/**
 * Add body class for scoped styling/hooks.
 *
 * @param array<int, string> $classes Existing classes.
 * @return array<int, string>
 */
function mad_baits_app_privacy_policy_body_class($classes) {
	if ('1' === (string) get_query_var('mad_app_privacy_policy')) {
		$classes[] = 'mad-app-privacy-policy-page';
	}

	return $classes;
}
add_filter('body_class', 'mad_baits_app_privacy_policy_body_class');

/**
 * Flush rewrite rules when theme is activated.
 *
 * @return void
 */
function mad_baits_flush_app_privacy_policy_rewrite() {
	mad_baits_register_app_privacy_policy_route();
	flush_rewrite_rules();
}
add_action('after_switch_theme', 'mad_baits_flush_app_privacy_policy_rewrite');

