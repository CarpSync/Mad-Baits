<?php
/**
 * Session fishing logbook app — /session/ route & shell.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Whether current request is the Session app page.
 *
 * @return bool
 */
function mad_baits_is_session_app_request() {
	if (is_admin()) {
		return false;
	}
	if (is_page('session')) {
		return true;
	}
	$path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
	if (! is_string($path)) {
		return false;
	}
	$path = trim($path, '/');
	return 'session' === $path;
}

/**
 * @return string
 */
function mad_baits_get_session_app_url() {
	if (class_exists('MBS_Plugin')) {
		return MBS_Plugin::get_session_page_url();
	}
	return mad_baits_get_page_url('session', 'session');
}

/**
 * Virtual session page when no WP page exists.
 *
 * @return WP_Post
 */
function mad_baits_get_virtual_session_post() {
	$virtual                    = new stdClass();
	$virtual->ID                = 0;
	$virtual->post_author       = 1;
	$virtual->post_date         = current_time('mysql');
	$virtual->post_date_gmt     = current_time('mysql', 1);
	$virtual->post_content      = '';
	$virtual->post_title        = __('Session', 'mad-baits');
	$virtual->post_excerpt      = '';
	$virtual->post_status       = 'publish';
	$virtual->post_comment_status = 'closed';
	$virtual->post_ping_status  = 'closed';
	$virtual->post_password     = '';
	$virtual->post_name         = 'session';
	$virtual->to_ping           = '';
	$virtual->pinged            = '';
	$virtual->post_modified     = current_time('mysql');
	$virtual->post_modified_gmt = current_time('mysql', 1);
	$virtual->post_content_filtered = '';
	$virtual->post_parent       = 0;
	$virtual->guid              = home_url('/session/');
	$virtual->menu_order        = 0;
	$virtual->post_type         = 'page';
	$virtual->post_mime_type    = '';
	$virtual->comment_count     = 0;
	$virtual->filter            = 'raw';

	return new WP_Post($virtual);
}

/**
 * Serve virtual /session/ page.
 *
 * @return void
 */
function mad_baits_serve_virtual_session_page() {
	if (! mad_baits_is_session_app_request()) {
		return;
	}
	if (get_page_by_path('session')) {
		return;
	}

	global $wp_query, $post;
	$virtual_post               = mad_baits_get_virtual_session_post();
	$post                       = $virtual_post;
	$wp_query->posts            = array($virtual_post);
	$wp_query->post             = $virtual_post;
	$wp_query->queried_object   = $virtual_post;
	$wp_query->queried_object_id = 0;
	$wp_query->is_page          = true;
	$wp_query->is_singular      = true;
	$wp_query->is_home          = false;
	$wp_query->is_404           = false;
	$wp_query->post_count       = 1;

	status_header(200);
}
add_action('template_redirect', 'mad_baits_serve_virtual_session_page', 0);

/**
 * Force session app template.
 *
 * @param string $template Template path.
 * @return string
 */
function mad_baits_session_app_template($template) {
	if (! mad_baits_is_session_app_request()) {
		return $template;
	}

	$custom = get_theme_file_path('template-session-app.php');
	if (file_exists($custom)) {
		return $custom;
	}
	return $template;
}
add_filter('template_include', 'mad_baits_session_app_template', 99);

/**
 * Body classes for session app page.
 *
 * @param array<int, string> $classes Classes.
 * @return array<int, string>
 */
function mad_baits_session_app_body_class($classes) {
	if (! mad_baits_is_session_app_request()) {
		return $classes;
	}
	$classes[] = 'mad-session-route';
	$classes[] = 'is-mobile-app-shell';
	if (class_exists('MBS_Plugin') && MBS_Plugin::is_mobile_viewport_context()) {
		$classes[] = 'mbs-session-mobile-context';
	}
	return $classes;
}
add_filter('body_class', 'mad_baits_session_app_body_class');

/**
 * Mobile back bar on session route.
 *
 * @param bool $show Show.
 * @return bool
 */
function mad_baits_session_app_show_back_bar($show) {
	if (mad_baits_is_session_app_request()) {
		return true;
	}
	return $show;
}
add_filter('mad_baits_show_mobile_back_bar', 'mad_baits_session_app_show_back_bar');

/**
 * @param string $title Title.
 * @return string
 */
function mad_baits_session_app_back_bar_title($title) {
	if (mad_baits_is_session_app_request()) {
		return __('Session', 'mad-baits');
	}
	return $title;
}
add_filter('mad_baits_mobile_back_bar_title', 'mad_baits_session_app_back_bar_title');

/**
 * Hide site footer chrome on session app (full-screen).
 *
 * @param bool $hide Hide footer.
 * @return bool
 */
function mad_baits_session_app_hide_footer($hide) {
	if (mad_baits_is_session_app_request()) {
		return true;
	}
	return $hide;
}
add_filter('mad_baits_hide_site_footer', 'mad_baits_session_app_hide_footer', 20);
