<?php
/**
 * PWA / mobile app version and cache-busting helpers.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/** @var string WordPress option key for the active app version string. */
const MAD_BAITS_APP_VERSION_OPTION = 'mad_baits_app_version';

/** @var string WordPress option key for last admin refresh timestamp. */
const MAD_BAITS_APP_VERSION_REFRESH_OPTION = 'mad_baits_app_version_refreshed_at';

/**
 * Default app version when none is stored yet.
 *
 * @return string
 */
function mad_baits_get_default_app_version() {
	return gmdate('Y.m.d') . '.001';
}

/**
 * Read the stored app version (creates default on first use).
 *
 * @return string
 */
function mad_baits_get_app_version() {
	$stored = get_option(MAD_BAITS_APP_VERSION_OPTION, '');
	$stored = is_string($stored) ? trim($stored) : '';

	if ('' === $stored) {
		$stored = mad_baits_get_default_app_version();
		update_option(MAD_BAITS_APP_VERSION_OPTION, $stored, false);
	}

	return $stored;
}

/**
 * Timestamp of the last admin “force refresh” request.
 *
 * @return int Unix timestamp or 0.
 */
function mad_baits_get_app_version_refreshed_at() {
	return (int) get_option(MAD_BAITS_APP_VERSION_REFRESH_OPTION, 0);
}

/**
 * Bump the app version (admin force refresh).
 *
 * @return string New version string.
 */
function mad_baits_bump_app_version() {
	$current = mad_baits_get_app_version();
	$next    = mad_baits_increment_app_version($current);

	update_option(MAD_BAITS_APP_VERSION_OPTION, $next, false);
	update_option(MAD_BAITS_APP_VERSION_REFRESH_OPTION, time(), false);

	mad_baits_clear_pwa_transients();

	/**
	 * Fires after staff request a mobile/PWA cache refresh.
	 *
	 * @param string $next     New version.
	 * @param string $previous Previous version.
	 */
	do_action('mad_baits_app_version_bumped', $next, $current);

	return $next;
}

/**
 * Increment dotted version suffix or append a new dated segment.
 *
 * @param string $version Current version.
 * @return string
 */
function mad_baits_increment_app_version($version) {
	$version = trim((string) $version);
	$today   = gmdate('Y.m.d');

	if (preg_match('/^(\d{4}\.\d{2}\.\d{2})\.(\d{3})$/', $version, $matches)) {
		$date_part = $matches[1];
		$seq       = (int) $matches[2];
		if ($date_part === $today) {
			return $date_part . '.' . str_pad((string) ($seq + 1), 3, '0', STR_PAD_LEFT);
		}
	}

	if (preg_match('/^(\d{4}\.\d{2}\.\d{2})\.(\d+)$/', $version, $matches)) {
		$date_part = $matches[1];
		$seq       = (int) $matches[2];
		if ($date_part === $today) {
			return $date_part . '.' . str_pad((string) ($seq + 1), 3, '0', STR_PAD_LEFT);
		}
	}

	return $today . '.001';
}

/**
 * Clear theme transients that may cache app-facing output.
 *
 * @return void
 */
function mad_baits_clear_pwa_transients() {
	$keys = array(
		'mad_baits_news_ticker_items_v2',
	);

	foreach ($keys as $key) {
		delete_transient($key);
	}

	if (function_exists('mad_baits_clear_news_ticker_cache')) {
		mad_baits_clear_news_ticker_cache();
	}
}

/**
 * Asset version for wp_enqueue_* (controlled by admin app version).
 *
 * @param string $file_path Optional absolute path (reserved for future use).
 * @return string
 */
function mad_baits_get_asset_version($file_path = '') {
	$app_version = mad_baits_get_app_version();

	if ('' !== $file_path && is_string($file_path) && file_exists($file_path)) {
		return $app_version . '.' . (string) filemtime($file_path);
	}

	return $app_version;
}

/**
 * Build ID exposed to the frontend (app version + deploy hints).
 *
 * @return string
 */
function mad_baits_get_pwa_build_id() {
	$parts = array( 'app-' . mad_baits_get_app_version() );

	$main_js = get_theme_file_path('assets/js/main.js');
	if (file_exists($main_js)) {
		$parts[] = 'main-' . (string) filemtime($main_js);
	}

	if (defined('MBBB_VERSION')) {
		$parts[] = 'mbbb-' . MBBB_VERSION;
	}

	return implode('-', $parts);
}

/**
 * Service worker cache name for the active app version.
 *
 * @return string
 */
function mad_baits_get_pwa_cache_name() {
	return 'mad-baits-app-cache-' . mad_baits_get_app_version();
}

/**
 * Whether the current front-end request should block update prompts / auto refresh.
 *
 * @return bool
 */
function mad_baits_is_unsafe_app_refresh_context() {
	if (is_admin()) {
		return true;
	}

	if (function_exists('is_checkout') && is_checkout()) {
		return true;
	}

	if (function_exists('is_cart') && is_cart()) {
		return true;
	}

	if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay'))) {
		return true;
	}

	if (function_exists('mad_baits_is_woocommerce_product_listing_page') && mad_baits_is_woocommerce_product_listing_page()) {
		return true;
	}

	return false;
}

/**
 * Inject app version placeholder when serving the service worker script.
 *
 * @param string $contents Raw service worker file contents.
 * @return string
 */
function mad_baits_prepare_service_worker_contents($contents) {
	$version = mad_baits_get_app_version();
	$version = preg_replace('/[^a-zA-Z0-9._-]/', '', $version);
	if ('' === $version) {
		$version = mad_baits_get_default_app_version();
	}

	return str_replace('__MAD_BAITS_APP_VERSION__', $version, $contents);
}

/**
 * Inject site-relative manifest paths with the current site URL (staging/production safe).
 *
 * @param string $contents Raw manifest.json contents.
 * @return string
 */
function mad_baits_prepare_manifest_contents($contents) {
	$data = json_decode((string) $contents, true);
	if (! is_array($data)) {
		return $contents;
	}

	$data['start_url'] = home_url('/');
	$data['id']        = home_url('/');
	$data['scope']     = '/';

	if (! empty($data['icons']) && is_array($data['icons'])) {
		foreach ($data['icons'] as $index => $icon) {
			if (! is_array($icon) || empty($icon['src'])) {
				continue;
			}
			$src = (string) $icon['src'];
			if (0 === strpos($src, '/')) {
				$data['icons'][ $index ]['src'] = home_url($src);
			}
		}
	}

	if (! empty($data['shortcuts']) && is_array($data['shortcuts'])) {
		foreach ($data['shortcuts'] as $index => $shortcut) {
			if (! is_array($shortcut) || empty($shortcut['url'])) {
				continue;
			}
			$url = (string) $shortcut['url'];
			if (0 === strpos($url, '/')) {
				$data['shortcuts'][ $index ]['url'] = home_url($url);
			}
		}
	}

	$encoded = wp_json_encode($data, JSON_UNESCAPED_SLASHES);
	return is_string($encoded) && '' !== $encoded ? $encoded : $contents;
}

/**
 * Use admin app version for Mad Bundle Builder assets when available.
 *
 * @param string $version Plugin default version.
 * @return string
 */
function mad_baits_filter_mbbb_asset_version($version) {
	return mad_baits_get_app_version();
}
add_filter('mbbb_asset_version', 'mad_baits_filter_mbbb_asset_version');

/**
 * Small app version label for footer / PWA chrome (support + cache debugging).
 *
 * @param string $class Extra class names.
 * @return void
 */
function mad_baits_render_app_version_label($class = '') {
	if (! mad_baits_should_show_app_version_label()) {
		return;
	}

	$version = mad_baits_get_app_version();
	if ('' === $version) {
		return;
	}

	$classes = trim('mad-app-version-label ' . (string) $class);
	printf(
		'<p class="%1$s" aria-hidden="true">%2$s</p>',
		esc_attr($classes),
		esc_html($version)
	);
}

/**
 * Whether the PWA build/version label should render for the current viewer.
 *
 * @return bool
 */
function mad_baits_should_show_app_version_label() {
	if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
		return true;
	}

	/**
	 * Allow staff-only version labels without enabling WP_DEBUG.
	 *
	 * @param bool $show Default false for shoppers.
	 */
	return (bool) apply_filters('mad_baits_show_app_version_label', false);
}
