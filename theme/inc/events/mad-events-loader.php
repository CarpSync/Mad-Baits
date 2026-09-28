<?php
/**
 * Mad Baits Events bootstrap.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

require_once get_theme_file_path('/inc/events/class-mad-events-cpt.php');
require_once get_theme_file_path('/inc/events/class-mad-events-data.php');
require_once get_theme_file_path('/inc/events/class-mad-events-meta.php');
require_once get_theme_file_path('/inc/events/class-mad-events-render.php');
require_once get_theme_file_path('/inc/events/class-mad-events-rsvp.php');
require_once get_theme_file_path('/inc/events/class-mad-events-calendar.php');
require_once get_theme_file_path('/inc/events/class-mad-events-push.php');
require_once get_theme_file_path('/inc/events/class-mad-events-seed.php');

/**
 * Initialize events modules.
 *
 * @return void
 */
function mad_baits_events_bootstrap() {
	Mad_Events_CPT::init();
	Mad_Events_Meta::init();
	Mad_Events_RSVP::init();
	Mad_Events_Calendar::init();
	Mad_Events_Push::init();
	Mad_Events_Seed::init();
	Mad_Events_Render::init();
}
add_action('init', 'mad_baits_events_bootstrap', 5);

/**
 * Flush rewrite rules once after events module deploy.
 *
 * @return void
 */
function mad_baits_events_maybe_flush_rewrites() {
	if (get_option('mad_baits_events_rewrite_flush_v1')) {
		return;
	}
	Mad_Events_CPT::register_post_types();
	flush_rewrite_rules(false);
	update_option('mad_baits_events_rewrite_flush_v1', 1);
}
add_action('init', 'mad_baits_events_maybe_flush_rewrites', 99);
add_filter('request', 'mad_baits_events_parse_request', 1);
add_action('template_redirect', 'mad_baits_events_template_fallback', 0);
add_filter('template_include', 'mad_baits_events_template_include', 99);

/**
 * Map /events/ URLs to mad_event queries (overrides conflicting WP pages).
 *
 * @param array<string, mixed> $query_vars Query vars.
 * @return array<string, mixed>
 */
function mad_baits_events_parse_request($query_vars) {
	if (is_admin()) {
		return $query_vars;
	}

	$path = mad_baits_events_get_request_path();
	if (! str_starts_with($path, 'events')) {
		return $query_vars;
	}

	$parts = explode('/', $path);
	$slug  = isset($parts[1]) ? sanitize_title($parts[1]) : '';

	unset($query_vars['pagename'], $query_vars['page'], $query_vars['name'], $query_vars['attachment']);

	if ('' !== $slug) {
		$query_vars['post_type'] = 'mad_event';
		$query_vars['name']      = $slug;
		$query_vars['mad_event'] = $slug;
	} else {
		$query_vars['post_type'] = 'mad_event';
	}

	return $query_vars;
}

/**
 * Force theme templates for mad_event routes.
 *
 * @param string $template Template path.
 * @return string
 */
function mad_baits_events_template_include($template) {
	if (is_post_type_archive('mad_event')) {
		$archive = get_theme_file_path('archive-mad_event.php');
		if (file_exists($archive)) {
			return $archive;
		}
	}
	if (is_singular('mad_event')) {
		$single = get_theme_file_path('single-mad_event.php');
		if (file_exists($single)) {
			return $single;
		}
	}
	return $template;
}

/**
 * Parse request path segment after home path.
 *
 * @return string
 */
function mad_baits_events_get_request_path() {
	$uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
	$path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
	$home = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
	if ('' !== $home && str_starts_with($path, $home)) {
		$path = trim(substr($path, strlen($home)), '/');
	}
	return strtolower($path);
}

/**
 * Serve events templates when permalinks are not flushed yet.
 *
 * @return void
 */
function mad_baits_events_template_fallback() {
	if (is_admin() || wp_doing_ajax()) {
		return;
	}

	$path = mad_baits_events_get_request_path();
	if (! str_starts_with($path, 'events')) {
		return;
	}

	if (is_post_type_archive('mad_event') || is_singular('mad_event')) {
		return;
	}

	$parts = explode('/', $path);
	$slug  = isset($parts[1]) ? sanitize_title($parts[1]) : '';

	if ('' !== $slug) {
		$post = get_page_by_path($slug, OBJECT, 'mad_event');
		if ($post instanceof WP_Post) {
			global $wp_query, $post;
			$post = $post; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			$wp_query->posts             = array($post);
			$wp_query->post              = $post;
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = (int) $post->ID;
			$wp_query->post_count        = 1;
			$wp_query->is_singular       = true;
			$wp_query->is_single         = true;
			$wp_query->is_page           = false;
			$wp_query->is_home           = false;
			$wp_query->is_front_page    = false;
			$wp_query->is_404            = false;
			setup_postdata($post);
			status_header(200);
			include get_theme_file_path('single-mad_event.php');
			exit;
		}
		return;
	}

	if ('events' === $path) {
		global $wp_query;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'mad_event',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
			)
		);
		$wp_query->is_archive            = true;
		$wp_query->is_post_type_archive  = true;
		$wp_query->is_404                = false;
		status_header(200);
		include get_theme_file_path('archive-mad_event.php');
		exit;
	}
}

/**
 * Events archive URL.
 *
 * @return string
 */
function mad_baits_get_events_url() {
	$link = get_post_type_archive_link('mad_event');
	return is_string($link) && '' !== $link ? $link : home_url('/events/');
}

/**
 * Render compact upcoming event teaser.
 *
 * @param array<string, mixed> $args Args.
 * @return void
 */
function mad_baits_render_event_teaser($args = array()) {
	if (class_exists('Mad_Events_Render')) {
		Mad_Events_Render::render_teaser($args);
	}
}

/**
 * Enqueue events assets.
 *
 * @return void
 */
function mad_baits_events_enqueue_assets() {
	$load = is_post_type_archive('mad_event') || is_singular('mad_event') || is_front_page();
	if (! $load) {
		return;
	}

	$css_path = get_theme_file_path('assets/css/mad-events.css');
	$js_path  = get_theme_file_path('assets/js/mad-events.js');

	if (file_exists($css_path)) {
		wp_enqueue_style(
			'mad-baits-events',
			get_theme_file_uri('assets/css/mad-events.css'),
			array('mad-baits-main'),
			(string) filemtime($css_path)
		);
	}

	if (file_exists($js_path)) {
		wp_enqueue_script(
			'mad-baits-events',
			get_theme_file_uri('assets/js/mad-events.js'),
			array('mad-baits-main'),
			(string) filemtime($js_path),
			true
		);

		wp_localize_script(
			'mad-baits-events',
			'madBaitsEvents',
			array(
				'ajaxUrl'       => admin_url('admin-ajax.php'),
				'rsvpNonce'     => wp_create_nonce('mad_baits_event_rsvp'),
				'rsvpAction'    => 'mad_baits_event_rsvp',
				'i18nSubmitting'=> __('Sending your RSVP...', 'mad-baits'),
				'i18nSuccess'   => __('Thanks for registering your interest. We will send event updates closer to the day.', 'mad-baits'),
				'i18nError'     => __('Could not submit RSVP. Please check the form and try again.', 'mad-baits'),
			)
		);
	}
}
add_action('wp_enqueue_scripts', 'mad_baits_events_enqueue_assets', 30);

/**
 * Flush rewrite rules after theme switch.
 *
 * @return void
 */
function mad_baits_events_flush_rewrites() {
	Mad_Events_CPT::register_post_types();
	flush_rewrite_rules();
}
add_action('after_switch_theme', 'mad_baits_events_flush_rewrites');
