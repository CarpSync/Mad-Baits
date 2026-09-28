<?php
/**
 * Mobile app UX enhancements — haptics hooks, offline polish, app-exclusive slots.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Whether current request should skip playful UX (checkout stability).
 *
 * @return bool
 */
function mad_baits_is_ux_enhancement_safe_context() {
	if (is_admin()) {
		return false;
	}

	if (function_exists('mad_baits_is_wc_checkout_flow_request') && mad_baits_is_wc_checkout_flow_request()) {
		return false;
	}

	return true;
}

/**
 * Enqueue mobile UX enhancement assets.
 *
 * @return void
 */
function mad_baits_enqueue_app_mobile_ux_assets() {
	if (function_exists('mad_baits_is_wc_checkout_flow_request') && mad_baits_is_wc_checkout_flow_request()) {
		return;
	}

	$css_path = get_theme_file_path('assets/css/scoped/app-mobile-ux.css');
	$js_path  = get_theme_file_path('assets/js/app-mobile-ux.js');

	if (file_exists($css_path)) {
		wp_enqueue_style(
			'mad-baits-app-mobile-ux',
			get_theme_file_uri('assets/css/scoped/app-mobile-ux.css'),
			array('mad-baits-mobile-app'),
			function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($css_path) : (string) filemtime($css_path)
		);
	}

	if (file_exists($js_path)) {
		wp_enqueue_script(
			'mad-baits-app-mobile-ux',
			get_theme_file_uri('assets/js/app-mobile-ux.js'),
			array(),
			function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($js_path) : (string) filemtime($js_path),
			true
		);

		$premium_deps = array( 'mad-baits-app-mobile-ux' );
		if ( function_exists( 'mad_baits_is_capacitor_native_request' ) && mad_baits_is_capacitor_native_request() ) {
			$premium_deps[] = 'mad-baits-capacitor-shell';
		}

		$premium_js = get_theme_file_path('assets/js/app-native-premium.js');
		if (file_exists($premium_js)) {
			wp_enqueue_script(
				'mad-baits-app-native-premium',
				get_theme_file_uri('assets/js/app-native-premium.js'),
				$premium_deps,
				function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($premium_js) : (string) filemtime($premium_js),
				true
			);
		}

		$placeholders_js = get_theme_file_path('assets/js/app-shell-placeholders.js');
		if (file_exists($placeholders_js)) {
			wp_enqueue_script(
				'mad-baits-app-shell-placeholders',
				get_theme_file_uri('assets/js/app-shell-placeholders.js'),
				array('mad-baits-app-native-premium'),
				function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($placeholders_js) : (string) filemtime($placeholders_js),
				true
			);
		}

		wp_localize_script(
			'mad-baits-app-mobile-ux',
			'madBaitsMobileUxConfig',
			array(
				'logoUrl'     => get_theme_file_uri('assets/img/CUP-LOGOv2-1.svg'),
				'homeUrl'     => home_url('/'),
				'shopUrl'     => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
				'isStandalone'=> false,
				'enableHaptics' => true,
				'enablePullRefresh' => true,
				'i18nOffline' => __('You\'re off the grid', 'mad-baits'),
				'i18nOfflineDetail' => __('Some features may be unavailable while signal is weak.', 'mad-baits'),
				'i18nReconnect' => __('Back online', 'mad-baits'),
				'i18nRetry'   => __('Try again', 'mad-baits'),
				'i18nAddedToBucket' => __('Added to your basket', 'mad-baits'),
				'i18nAddedShort' => __('✓ Added', 'mad-baits'),
				'i18nPullRefresh' => __('Refreshing latest bait drops…', 'mad-baits'),
				'i18nCatchSubmitting' => __('Submitting…', 'mad-baits'),
				'i18nCatchSuccess' => __('Catch submitted!', 'mad-baits'),
				'i18nCatchTakePhoto' => __('Take Photo', 'mad-baits'),
				'i18nCatchChooseLibrary' => __('Choose from Library', 'mad-baits'),
				'i18nCatchUploading' => __('Adding photo…', 'mad-baits'),
				'i18nCatchCardSoonTitle' => __('Catch card — coming soon', 'mad-baits'),
				'i18nCatchCardSoonCopy' => __('Share a branded Mad Baits catch card with photo, weight, venue and bait. For now your catch is submitted for review.', 'mad-baits'),
				'i18nQuickViewSoonTitle' => __('Quick view', 'mad-baits'),
				'i18nQuickViewSoonCopy' => __('Product quick view is coming soon. Tap through to the full product page for now.', 'mad-baits'),
				'i18nQuickViewFullProduct' => __('View full product', 'mad-baits'),
				'i18nQuickViewClose' => __('Close', 'mad-baits'),
			)
		);
	}
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_app_mobile_ux_assets', 45);

/**
 * Body class for UX module.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function mad_baits_app_mobile_ux_body_class($classes) {
	if (mad_baits_is_ux_enhancement_safe_context()) {
		$classes[] = 'mad-app-ux-enhanced';
	}

	return $classes;
}
add_filter('body_class', 'mad_baits_app_mobile_ux_body_class', 24);

/**
 * Render reusable APP EXCLUSIVE badge (content via filter only).
 *
 * @param array<string, mixed> $args Optional context.
 * @return void
 */
function mad_baits_render_app_exclusive_badge($args = array()) {
	$label = apply_filters('mad_baits_app_exclusive_badge_label', '', $args);
	$label = is_string($label) ? trim($label) : '';

	if ('' === $label) {
		return;
	}
	?>
	<span class="mad-app-exclusive-badge"<?php echo ! empty($args['context']) ? ' data-app-exclusive-context="' . esc_attr((string) $args['context']) . '"' : ''; ?>>
		<?php echo esc_html($label); ?>
	</span>
	<?php
}

/**
 * App-exclusive promo banner slot (no default offer copy).
 *
 * @param array<string, mixed> $args Optional overrides merged into filter data.
 * @return void
 */
function mad_baits_render_app_exclusive_banner($args = array()) {
	$data = apply_filters('mad_baits_app_exclusive_banner', null, $args);
	if (! is_array($data) || empty($data['headline'])) {
		return;
	}

	$headline = (string) $data['headline'];
	$copy     = isset($data['copy']) ? (string) $data['copy'] : '';
	$cta      = isset($data['cta_label']) ? (string) $data['cta_label'] : '';
	$url      = isset($data['cta_url']) ? (string) $data['cta_url'] : '';
	$badge    = isset($data['badge']) ? (string) $data['badge'] : __('APP EXCLUSIVE', 'mad-baits');
	?>
	<aside class="mad-app-exclusive-banner" role="complementary" aria-label="<?php esc_attr_e('App exclusive offer', 'mad-baits'); ?>">
		<div class="mad-app-exclusive-banner__inner">
			<span class="mad-app-exclusive-badge mad-app-exclusive-badge--banner"><?php echo esc_html($badge); ?></span>
			<div class="mad-app-exclusive-banner__copy">
				<strong><?php echo esc_html($headline); ?></strong>
				<?php if ('' !== $copy) : ?>
					<p><?php echo esc_html($copy); ?></p>
				<?php endif; ?>
			</div>
			<?php if ('' !== $cta && '' !== $url) : ?>
				<a class="mad-app-exclusive-banner__cta mad-button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($cta); ?></a>
			<?php endif; ?>
		</div>
	</aside>
	<?php
}

/**
 * Home card data for optional dismissible app-exclusive slot.
 *
 * @return array<string, mixed>|null
 */
function mad_baits_get_app_exclusive_home_card_data() {
	$data = apply_filters('mad_baits_app_exclusive_home_card', null);
	return is_array($data) && ! empty($data['headline']) ? $data : null;
}

/**
 * Render dismissible app-exclusive home card when content is provided.
 *
 * @return void
 */
function mad_baits_render_app_exclusive_home_card() {
	$data = mad_baits_get_app_exclusive_home_card_data();
	if (! $data) {
		return;
	}

	$dismiss_key = 'madBaitsAppExclusiveHomeDismiss';
	$headline    = (string) $data['headline'];
	$copy        = isset($data['copy']) ? (string) $data['copy'] : '';
	$cta         = isset($data['cta_label']) ? (string) $data['cta_label'] : '';
	$url         = isset($data['cta_url']) ? (string) $data['cta_url'] : '';
	?>
	<article class="mad-app-exclusive-card" data-app-exclusive-home-card data-dismiss-key="<?php echo esc_attr($dismiss_key); ?>" hidden>
		<button type="button" class="mad-app-exclusive-card__dismiss" data-app-exclusive-dismiss aria-label="<?php esc_attr_e('Dismiss', 'mad-baits'); ?>">&times;</button>
		<span class="mad-app-exclusive-badge"><?php esc_html_e('APP EXCLUSIVE', 'mad-baits'); ?></span>
		<h3 class="mad-app-exclusive-card__title"><?php echo esc_html($headline); ?></h3>
		<?php if ('' !== $copy) : ?>
			<p class="mad-app-exclusive-card__copy"><?php echo esc_html($copy); ?></p>
		<?php endif; ?>
		<?php if ('' !== $cta && '' !== $url) : ?>
			<a class="mad-app-exclusive-card__cta mad-button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($cta); ?></a>
		<?php endif; ?>
	</article>
	<?php
}
add_action('mad_baits_app_home_before_quick_grid', 'mad_baits_render_app_exclusive_home_card', 8);
add_action('woocommerce_before_shop_loop', 'mad_baits_render_app_exclusive_banner', 4);
