<?php
/**
 * Theme bootstrap for Mad Baits.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once get_theme_file_path('/inc/user-roles.php');
require_once get_theme_file_path('/inc/session-builder.php');
require_once get_theme_file_path('/inc/bait-comparison.php');
require_once get_theme_file_path('/inc/fresh-rolling.php');
require_once get_theme_file_path('/inc/product-matching.php');
require_once get_theme_file_path('/inc/product-card-images.php');
require_once get_theme_file_path('/inc/range-shop.php');
require_once get_theme_file_path('/inc/shop-archive-layout.php');
require_once get_theme_file_path('/inc/pwa-version.php');
require_once get_theme_file_path('/inc/pwa-admin.php');
require_once get_theme_file_path('/inc/pwa-app.php');
require_once get_theme_file_path('/inc/home-app-promo.php');
require_once get_theme_file_path('/inc/home-news-ticker.php');
require_once get_theme_file_path('/inc/mobile-app-ux.php');
require_once get_theme_file_path('/inc/app-enhancements.php');
require_once get_theme_file_path('/inc/ai-settings.php');
require_once get_theme_file_path('/inc/favourites.php');
require_once get_theme_file_path('/inc/catch-report-media.php');
require_once get_theme_file_path('/inc/catch-submissions.php');
require_once get_theme_file_path('/inc/session-journal.php');
require_once get_theme_file_path('/inc/session-app.php');
require_once get_theme_file_path('/inc/events/mad-events-loader.php');
require_once get_theme_file_path('/inc/checkout-shipping.php');
require_once get_theme_file_path('/inc/checkout-paypal-recovery.php');
require_once get_theme_file_path('/inc/checkout-delivery-acknowledgement.php');
require_once get_theme_file_path('/inc/social-integration.php');
require_once get_theme_file_path('/inc/meta-tracking.php');
require_once get_theme_file_path('/inc/login-branding.php');
require_once get_theme_file_path('/inc/admin-dashboard.php');
require_once get_theme_file_path('/inc/seo.php');
require_once get_theme_file_path('/inc/app-privacy-policy.php');

/**
 * Load WooCommerce theme integrations.
 *
 * Theme functions.php runs after plugins_loaded, so do not hook plugins_loaded here.
 *
 * @return void
 */
function mad_baits_load_woocommerce_integrations() {
	if (! class_exists('WooCommerce', false)) {
		return;
	}

	require_once get_theme_file_path('/inc/woocommerce-email-branding.php');
	require_once get_theme_file_path('/inc/product-supplier.php');
	require_once get_theme_file_path('/inc/product-supplier-admin.php');
	require_once get_theme_file_path('/inc/woocommerce-catalogue-setup.php');
	require_once get_theme_file_path('/inc/single-product-pdp.php');
	require_once get_theme_file_path('/inc/checkout-payment-ui.php');
}
add_action('after_setup_theme', 'mad_baits_load_woocommerce_integrations', 15);

function mad_baits_theme_setup() {
	load_theme_textdomain('mad-baits', get_template_directory() . '/languages');

	add_theme_support('automatic-feed-links');
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('custom-logo', array(
		'height'      => 60,
		'width'       => 220,
		'flex-height' => true,
		'flex-width'  => true,
	));
	add_theme_support('woocommerce');
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
	add_theme_support('html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	));

	register_nav_menus(array(
		'primary' => __('Primary Menu', 'mad-baits'),
		'mobile'  => __('Mobile Menu', 'mad-baits'),
		'footer'  => __('Footer Menu', 'mad-baits'),
	));
}
add_action('after_setup_theme', 'mad_baits_theme_setup');

function mad_baits_enqueue_assets() {
	$theme_version = wp_get_theme()->get('Version');
	$style_path    = get_theme_file_path('style.css');
	$main_css_path = get_theme_file_path('assets/css/main.css');
	$main_js_path  = get_theme_file_path('assets/js/main.js');
	$environment   = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
	$is_dev        = defined('WP_DEBUG') && WP_DEBUG;
	$script_dependencies = array('jquery');
	$pwa_install_platform_js = get_theme_file_path('assets/js/pwa-install-platform.js');
	$asset_version    = function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version() : $theme_version;
	$style_version    = function_exists('mad_baits_get_asset_version')
		? mad_baits_get_asset_version($style_path)
		: $asset_version;
	$main_css_version = function_exists('mad_baits_get_asset_version')
		? mad_baits_get_asset_version($main_css_path)
		: $asset_version;
	$main_js_version  = function_exists('mad_baits_get_asset_version')
		? mad_baits_get_asset_version($main_js_path)
		: $asset_version;

	if (class_exists('WooCommerce')) {
		$script_dependencies[] = 'wc-cart-fragments';
	}

	wp_enqueue_style(
		'mad-baits-style',
		get_stylesheet_uri(),
		array(),
		$style_version
	);

	wp_enqueue_style(
		'mad-baits-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array('mad-baits-style'),
		$main_css_version
	);

	$conditional_scoped_styles = array(
		array(
			'handle' => 'mad-baits-home',
			'path'   => 'assets/css/scoped/home.css',
			'load'   => static function () {
				return function_exists('is_front_page') && is_front_page();
			},
		),
		array(
			'handle' => 'mad-baits-product',
			'path'   => 'assets/css/scoped/product.css',
			'load'   => static function () {
				if (function_exists('is_product') && is_product()) {
					return true;
				}
				if (function_exists('is_front_page') && is_front_page()) {
					return true;
				}
				return function_exists('mad_baits_is_woocommerce_product_listing_page')
					&& mad_baits_is_woocommerce_product_listing_page();
			},
		),
		array(
			'handle' => 'mad-baits-checkout',
			'path'   => 'assets/css/scoped/checkout.css',
			'load'   => static function () {
				return (function_exists('is_checkout') && is_checkout())
					|| (function_exists('is_cart') && is_cart());
			},
		),
		array(
			'handle' => 'mad-baits-account',
			'path'   => 'assets/css/scoped/account.css',
			'load'   => static function () {
				return function_exists('is_account_page') && is_account_page();
			},
		),
	);

	$always_scoped_styles = array(
		'mad-baits-overrides'        => 'assets/css/scoped/overrides.css',
		'mad-baits-app-enhancements' => 'assets/css/scoped/app-enhancements.css',
		'mad-baits-mobile-nav'       => 'assets/css/scoped/mobile-nav.css',
		'mad-baits-nav-dropdowns'    => 'assets/css/scoped/nav-dropdowns.css',
	);

	$previous_style_handle = 'mad-baits-main';
	foreach ($conditional_scoped_styles as $style_conf) {
		$should_load = isset($style_conf['load']) && is_callable($style_conf['load']) ? (bool) call_user_func($style_conf['load']) : false;
		if (! $should_load) {
			continue;
		}

		$relative_path = (string) ($style_conf['path'] ?? '');
		$scoped_path   = get_theme_file_path($relative_path);
		if ('' === $relative_path || ! file_exists($scoped_path)) {
			continue;
		}

		wp_enqueue_style(
			(string) $style_conf['handle'],
			get_template_directory_uri() . '/' . ltrim($relative_path, '/'),
			array($previous_style_handle),
			mad_baits_get_asset_version($scoped_path)
		);

		$previous_style_handle = (string) $style_conf['handle'];
	}

	foreach ($always_scoped_styles as $handle => $relative_path) {
		$scoped_path = get_theme_file_path($relative_path);
		if (! file_exists($scoped_path)) {
			continue;
		}

		wp_enqueue_style(
			$handle,
			get_template_directory_uri() . '/' . ltrim($relative_path, '/'),
			array($previous_style_handle),
			mad_baits_get_asset_version($scoped_path)
		);

		$previous_style_handle = $handle;
	}

	if (file_exists($pwa_install_platform_js)) {
		wp_enqueue_script(
			'mad-baits-pwa-install-platform',
			get_theme_file_uri('assets/js/pwa-install-platform.js'),
			array(),
			mad_baits_get_asset_version($pwa_install_platform_js),
			true
		);
		$script_dependencies[] = 'mad-baits-pwa-install-platform';
	}

	wp_enqueue_script(
		'mad-baits-main',
		get_template_directory_uri() . '/assets/js/main.js',
		$script_dependencies,
		$main_js_version,
		true
	);

	$app_enhancements_js = get_theme_file_path('assets/js/app-enhancements.js');
	if (file_exists($app_enhancements_js)) {
		wp_enqueue_script(
			'mad-baits-app-enhancements',
			get_theme_file_uri('assets/js/app-enhancements.js'),
			array('mad-baits-main'),
			mad_baits_get_asset_version($app_enhancements_js),
			true
		);
	}

	$pwa_update_js = get_theme_file_path('assets/js/pwa-app-update.js');
	if (file_exists($pwa_update_js)) {
		wp_enqueue_script(
			'mad-baits-pwa-app-update',
			get_theme_file_uri('assets/js/pwa-app-update.js'),
			array('mad-baits-main'),
			mad_baits_get_asset_version($pwa_update_js),
			true
		);
	}

	$pwa_update_css = get_theme_file_path('assets/css/scoped/pwa-app-update.css');
	if (file_exists($pwa_update_css)) {
		wp_enqueue_style(
			'mad-baits-pwa-app-update',
			get_theme_file_uri('assets/css/scoped/pwa-app-update.css'),
			array('mad-baits-overrides'),
			mad_baits_get_asset_version($pwa_update_css)
		);
	}

	wp_localize_script(
		'mad-baits-main',
		'madBaitsConfig',
		array(
			'ajaxUrl'          => admin_url('admin-ajax.php'),
			'addVariationNonce'=> wp_create_nonce('mad_baits_card_add_to_cart'),
			'aiFinderNonce'           => wp_create_nonce('mad_baits_ai_bait_finder'),
			'aiFinderAction'          => 'mad_baits_ai_bait_finder',
			'aiAddSessionAction'      => 'mad_baits_ai_add_session_to_cart',
			'i18nAiSessionAdding'     => __('Adding recommended session to basket...', 'mad-baits'),
			'i18nAiSessionAdded'      => __('Recommended session added to basket.', 'mad-baits'),
			'i18nAiSessionAddFailed'  => __('Could not add recommended products. Try individual add buttons or open product pages for variable items.', 'mad-baits'),
			'i18nAdded'        => __('Added', 'mad-baits'),
			'i18nChooseOptions'=> __('Choose options first', 'mad-baits'),
			'i18nAddFailed'    => __('Could not add to basket. Try again.', 'mad-baits'),
			'i18nInvalidCombo' => __('That option combination is unavailable.', 'mad-baits'),
			'i18nVariationUnavailable' => __('This variation is currently out of stock.', 'mad-baits'),
			'i18nReadyToAdd'   => __('Ready to add', 'mad-baits'),
			'i18nVariationSelected' => __('Variation selected', 'mad-baits'),
			'i18nBuildingPlan' => __('Building your session plan...', 'mad-baits'),
			'i18nViewBasket'   => __('View Basket', 'mad-baits'),
			'i18nCheckout'     => __('Checkout', 'mad-baits'),
			'cartUrl'          => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
			'checkoutUrl'      => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'),
			'pwaServiceWorkerUrl' => mad_baits_get_pwa_endpoint_url('service-worker'),
			'pwaManifestUrl'   => mad_baits_get_pwa_endpoint_url('manifest'),
			'pwaOfflineUrl'    => mad_baits_get_pwa_endpoint_url('offline'),
			'pwaBuildId'       => mad_baits_get_pwa_build_id(),
			'appVersion'       => mad_baits_get_app_version(),
			'appVersionStorageKey' => 'madBaitsAppVersion',
			'appUpdatePendingKey'  => 'madBaitsAppUpdatePending',
			'appUpdateUnsafe'  => function_exists('mad_baits_is_unsafe_app_refresh_context') ? mad_baits_is_unsafe_app_refresh_context() : false,
			'i18nAppUpdateTitle'   => __('App update required', 'mad-baits'),
			'i18nAppUpdateTitleBrowser' => __('Update available', 'mad-baits'),
			'i18nAppUpdateEyebrow' => __('Mad Baits app', 'mad-baits'),
			'i18nAppUpdateLeadStandalone' => __('A new version of the Mad Baits app is ready. Please fully close the app, then open it again.', 'mad-baits'),
			'i18nAppUpdateLeadBrowser' => __('A new version of Mad Baits is ready. Refresh to load the latest shop and bundle builder.', 'mad-baits'),
			'i18nAppUpdateSteps'   => "1. " . __('Close the Mad Baits app completely.', 'mad-baits') . "\n2. " . __('Open it again from your home screen or app list.', 'mad-baits') . "\n3. " . __('If anything still looks old, tap Refresh below.', 'mad-baits'),
			'i18nAppUpdateStepsIos' => "1. " . __('Swipe the Mad Baits app fully off the screen (App Switcher).', 'mad-baits') . "\n2. " . __('Open Mad Baits again from your Home Screen.', 'mad-baits') . "\n3. " . __('If anything still looks old, tap Refresh below.', 'mad-baits'),
			'i18nAppUpdateStepsBrowser' => __('Tap Refresh now. If the page still looks old, close the tab and open Mad Baits again.', 'mad-baits'),
			'i18nAppUpdateVersion' => __('Updating from %1$s to %2$s', 'mad-baits'),
			'i18nAppUpdateVersionSingle' => __('New version: %s', 'mad-baits'),
			'i18nAppUpdateRefresh' => __('Refresh now', 'mad-baits'),
			'i18nAppUpdateLater'   => __('Remind me later', 'mad-baits'),
			'pwaInstallDismissKey' => 'madBaitsPwaInstallDismissed',
			'i18nPwaInstallIos' => __('On iPhone/iPad: tap Share, then Add to Home Screen.', 'mad-baits'),
			'i18nPwaInstallAndroidChrome' => __('On Android: open madbaits.com in Google Chrome, tap the menu, then Install app or Add to Home screen.', 'mad-baits'),
			'i18nPwaInstallSamsungWarning' => __('Samsung Internet can block installs on newer Android phones. Open this page in Google Chrome to install safely.', 'mad-baits'),
			'i18nPwaInstallInAppWarning' => __('In-app browsers cannot install the app. Open madbaits.com in Chrome or Safari first.', 'mad-baits'),
			'i18nPwaInstallGeneric' => __('Open your browser menu and tap Install app or Add to Home screen.', 'mad-baits'),
			'i18nPwaInstallHomeHintAndroid' => __('Android: install with Google Chrome (menu, then Install app). iPhone: Share, then Add to Home Screen.', 'mad-baits'),
			'i18nPwaInstallHomeHintIos' => __('Free to install. On iPhone: Share, then Add to Home Screen.', 'mad-baits'),
			'i18nPwaInstallOpenChrome' => __('Open in Chrome', 'mad-baits'),
			'pwaThemeColor'    => '#fff202',
			'favouritesNonce'  => wp_create_nonce('mad_baits_favourites'),
			'favouritesToggleAction' => 'mad_baits_favourites_toggle',
			'favouritesGetAction' => 'mad_baits_favourites_get',
			'favouritesAddAllAction' => 'mad_baits_favourites_add_all_to_cart',
			'favouritesPreviewAction' => 'mad_baits_get_product_previews',
			'isLoggedIn'       => is_user_logged_in(),
			'currentUserId'    => get_current_user_id(),
			'isProduct'        => function_exists('is_product') ? is_product() : false,
			'currentProductId' => function_exists('is_product') && is_product() ? (int) get_the_ID() : 0,
			'siteUrl'          => home_url('/'),
			'isMobileBottomNavHidden' => function_exists('mad_baits_should_hide_bottom_app_nav') ? mad_baits_should_hide_bottom_app_nav() : false,
			'isMobileTopBackBar' => function_exists('mad_baits_should_show_mobile_back_bar') ? mad_baits_should_show_mobile_back_bar() : false,
			'shopUrl'               => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
			'bundleDealsUrl'        => function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/'),
			'buildSessionUrl'       => function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('whats-in-the-water', 'whats-in-the-water') : home_url('/whats-in-the-water/'),
			'sessionAppUrl'         => function_exists('mad_baits_get_session_app_url') ? mad_baits_get_session_app_url() : home_url('/session/'),
			'aiFinderUrl'           => home_url('/ai-bait-finder/'),
			'myAccountUrl'          => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'),
			'freeShippingThreshold'   => function_exists('mad_baits_get_free_shipping_threshold') ? mad_baits_get_free_shipping_threshold() : 100,
			'cartCount'               => (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0,
			'i18nAddedToBasket'       => __('Added to basket', 'mad-baits'),
			'isDev'            => $is_dev || in_array($environment, array('local', 'development'), true),
		)
	);

	$app_enhancements = function_exists('mad_baits_get_app_enhancement_settings')
		? mad_baits_get_app_enhancement_settings()
		: array();

	wp_localize_script(
		'mad-baits-main',
		'madBaitsEnhancements',
		array(
			'settings' => $app_enhancements,
			'ajaxUrl'  => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('mad_baits_water_recommender'),
			'shopUrl'  => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
			'bundleDealsUrl' => function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/'),
		)
	);

	if (function_exists('is_page') && is_page('whats-in-the-water')) {
		$finder_css = get_theme_file_path('assets/css/scoped/whats-in-the-water.css');
		if (file_exists($finder_css)) {
			wp_enqueue_style(
				'mad-baits-water-finder',
				get_theme_file_uri('assets/css/scoped/whats-in-the-water.css'),
				array('mad-baits-main'),
				mad_baits_get_asset_version($finder_css)
			);
		}

		$finder_js = get_theme_file_path('assets/js/whats-in-the-water.js');
		if (file_exists($finder_js)) {
			wp_enqueue_script(
				'mad-baits-water-finder',
				get_theme_file_uri('assets/js/whats-in-the-water.js'),
				array('mad-baits-main'),
				mad_baits_get_asset_version($finder_js),
				true
			);
			wp_localize_script(
				'mad-baits-water-finder',
				'madBaitsWaterFinder',
				array(
					'ajaxUrl'       => admin_url('admin-ajax.php'),
					'nonce'         => wp_create_nonce('mad_baits_water_finder_page'),
					'shopUrl'       => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
					'enableHaptics' => ! empty($app_enhancements['enable_haptics']),
				)
			);
		}
	}
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_assets');

/**
 * Whether checkout/cart payment & shipping fix assets should load.
 *
 * @return bool
 */
function mad_baits_should_enqueue_checkout_flow_assets() {
	if (function_exists('mad_baits_is_wc_checkout_flow_request')) {
		return mad_baits_is_wc_checkout_flow_request();
	}

	return (function_exists('is_checkout') && is_checkout())
		|| (function_exists('is_cart') && is_cart());
}

/**
 * Checkout blocks fix CSS — late enqueue so WooCommerce block/gateway styles register first.
 *
 * @return void
 */
function mad_baits_enqueue_checkout_flow_assets() {
	if (! mad_baits_should_enqueue_checkout_flow_assets()) {
		return;
	}

	$checkout_blocks_fix_path = get_theme_file_path('assets/css/scoped/checkout-blocks-fix.css');
	if (! file_exists($checkout_blocks_fix_path)) {
		return;
	}

	if (wp_style_is('mad-baits-checkout-blocks-fix', 'enqueued')) {
		return;
	}

	$checkout_fix_deps = array('mad-baits-overrides');
	$late_style_handles = array(
		'wc-blocks-style',
		'wc-blocks-checkout-style',
		'wc-blocks-vendors-style',
		'wc-stripe-blocks-checkout-style',
		'wc-stripe-upe-checkout',
		'wc-stripe-upe-styles',
		'woocommerce-gateway-stripe',
		'ppcp-checkout',
		'ppcp-button',
		'ppcp-applepay',
		'ppcp-googlepay',
	);
	foreach ($late_style_handles as $style_handle) {
		if (wp_style_is($style_handle, 'registered') || wp_style_is($style_handle, 'enqueued')) {
			$checkout_fix_deps[] = $style_handle;
		}
	}

	wp_enqueue_style(
		'mad-baits-checkout-blocks-fix',
		get_theme_file_uri('assets/css/scoped/checkout-blocks-fix.css'),
		array_values(array_unique($checkout_fix_deps)),
		mad_baits_get_asset_version($checkout_blocks_fix_path)
	);

	if (! wp_script_is('mad-baits-main', 'enqueued')) {
		return;
	}

	wp_add_inline_script(
		'mad-baits-main',
		"(function () {\n"
		. "\tfunction madBaitsTunePayPalMessages() {\n"
		. "\t\tdocument.querySelectorAll('pp-message,[data-pp-message]').forEach(function (node) {\n"
		. "\t\t\tnode.setAttribute('data-pp-style-color', 'gold');\n"
		. "\t\t\tnode.setAttribute('data-pp-style-logo-type', 'primary');\n"
		. "\t\t});\n"
		. "\t\tdocument.querySelectorAll('.ppcp-messages,[class*=\"paylater\"],[class*=\"pay-later\"],[class*=\"pay-in\"]').forEach(function (node) {\n"
		. "\t\t\tnode.style.setProperty('color', '#fff202', 'important');\n"
		. "\t\t\tnode.style.setProperty('-webkit-text-fill-color', '#fff202', 'important');\n"
		. "\t\t});\n"
		. "\t}\n"
		. "\tif (document.readyState === 'loading') {\n"
		. "\t\tdocument.addEventListener('DOMContentLoaded', madBaitsTunePayPalMessages);\n"
		. "\t} else {\n"
		. "\t\tmadBaitsTunePayPalMessages();\n"
		. "\t}\n"
		. "\tif (typeof MutationObserver !== 'undefined' && document.body) {\n"
		. "\t\tnew MutationObserver(madBaitsTunePayPalMessages).observe(document.body, { childList: true, subtree: true });\n"
		. "\t}\n"
		. "\twindow.setTimeout(madBaitsTunePayPalMessages, 800);\n"
		. "\twindow.setTimeout(madBaitsTunePayPalMessages, 2200);\n"
		. "})();",
		'after'
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_checkout_flow_assets', 100);

/**
 * Product card image rules load last (after mobile-app.css).
 *
 * @return void
 */
function mad_baits_enqueue_product_card_image_styles() {
	$path = get_theme_file_path('assets/css/scoped/product-card-images.css');
	if (! file_exists($path)) {
		return;
	}

	$deps = array('mad-baits-main', 'mad-baits-overrides');
	if (wp_style_is('mad-baits-mobile-app', 'registered') || wp_style_is('mad-baits-mobile-app', 'enqueued')) {
		$deps[] = 'mad-baits-mobile-app';
	}
	if (wp_style_is('mad-baits-app-enhancements', 'registered') || wp_style_is('mad-baits-app-enhancements', 'enqueued')) {
		$deps[] = 'mad-baits-app-enhancements';
	}

	wp_enqueue_style(
		'mad-baits-product-card-images',
		get_theme_file_uri('assets/css/scoped/product-card-images.css'),
		$deps,
		mad_baits_get_asset_version($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_product_card_image_styles', 55);

/**
 * Single product layout fixes (loads after product-card-images).
 *
 * @return void
 */
function mad_baits_enqueue_product_pdp_layout_fixes() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	if (function_exists('mad_baits_is_standard_product_pdp') && ! mad_baits_is_standard_product_pdp()) {
		return;
	}

	$path = get_theme_file_path('assets/css/scoped/product-pdp-layout-fixes.css');
	if (! file_exists($path)) {
		return;
	}

	$deps = array('mad-baits-product-card-images');
	if (! wp_style_is('mad-baits-product-card-images', 'registered') && ! wp_style_is('mad-baits-product-card-images', 'enqueued')) {
		$deps = array('mad-baits-product');
	}

	wp_enqueue_style(
		'mad-baits-product-pdp-layout-fixes',
		get_theme_file_uri('assets/css/scoped/product-pdp-layout-fixes.css'),
		$deps,
		mad_baits_get_asset_version($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_product_pdp_layout_fixes', 60);

/**
 * Bundle builder PDP layout isolation — standard polish CSS must not load.
 *
 * @return void
 */
function mad_baits_enqueue_bundle_product_pdp_layout() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	if (! function_exists('mad_baits_is_bundle_builder_product') || ! mad_baits_is_bundle_builder_product()) {
		return;
	}

	$path = get_theme_file_path('assets/css/scoped/product-bundle-pdp-layout.css');
	if (! file_exists($path)) {
		return;
	}

	$deps = array('mad-baits-product');
	if (wp_style_is('mad-baits-product-card-images', 'registered') || wp_style_is('mad-baits-product-card-images', 'enqueued')) {
		$deps[] = 'mad-baits-product-card-images';
	}

	wp_enqueue_style(
		'mad-baits-product-bundle-pdp-layout',
		get_theme_file_uri('assets/css/scoped/product-bundle-pdp-layout.css'),
		$deps,
		mad_baits_get_asset_version($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_bundle_product_pdp_layout', 62);

/**
 * Standard (non–bundle-builder) single product polish — loads last on PDP.
 *
 * @return void
 */
function mad_baits_enqueue_standard_product_pdp_styles() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	if (! function_exists('mad_baits_is_standard_product_pdp') || ! mad_baits_is_standard_product_pdp()) {
		return;
	}

	$path = get_theme_file_path('assets/css/scoped/product-standard-pdp.css');
	if (! file_exists($path)) {
		return;
	}

	$deps = array('mad-baits-product-pdp-layout-fixes');
	if (! wp_style_is('mad-baits-product-pdp-layout-fixes', 'registered') && ! wp_style_is('mad-baits-product-pdp-layout-fixes', 'enqueued')) {
		$deps = array('mad-baits-product-card-images');
	}

	wp_enqueue_style(
		'mad-baits-product-standard-pdp',
		get_theme_file_uri('assets/css/scoped/product-standard-pdp.css'),
		$deps,
		mad_baits_get_asset_version($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_standard_product_pdp_styles', 65);

/**
 * Final visual consistency layer (loads after scoped/page CSS).
 *
 * @return void
 */
function mad_baits_enqueue_visual_consistency_styles() {
	$path = get_theme_file_path('assets/css/scoped/visual-consistency.css');
	if (! file_exists($path)) {
		return;
	}

	wp_enqueue_style(
		'mad-baits-visual-consistency',
		get_theme_file_uri('assets/css/scoped/visual-consistency.css'),
		array('mad-baits-main'),
		mad_baits_get_asset_version($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_visual_consistency_styles', 120);

/**
 * Redirect legacy/dead category paths to canonical category or range-filter URLs.
 *
 * @return void
 */
function mad_baits_redirect_legacy_taxonomy_urls() {
	if (is_admin() || ! function_exists('is_404') || ! is_404()) {
		return;
	}

	$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
	$request_path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
	if ('' === $request_path) {
		return;
	}

	$legacy_redirects = array(
		'product-category/bundles'      => function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/'),
		'product-category/bundle-deals' => function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/'),
		'product-category/bundle-offers'=> function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/'),
		'product-category/deals'        => function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/'),
		'product-category/accesories'   => home_url('/product-category/accessories/'),
		'product-category/asbo'         => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('asbo', home_url('/shop/')) : home_url('/shop/?product_tag=asbo'),
		'product-category/pandemic'     => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('pandemic', home_url('/shop/')) : home_url('/shop/?product_tag=pandemic'),
		'product-category/p-fish'       => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('p-fish-2', home_url('/shop/')) : home_url('/shop/?product_tag=p-fish-2'),
		'product-category/p-fish-2'     => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('p-fish-2', home_url('/shop/')) : home_url('/shop/?product_tag=p-fish-2'),
		'product-category/nutz-plus'    => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('nutz-plus', home_url('/shop/')) : home_url('/shop/?product_tag=nutz-plus'),
		'product-category/nutz-banana'  => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('nutz-banana', home_url('/shop/')) : home_url('/shop/?product_tag=nutz-banana'),
		'product-category/wicked-white' => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('wicked-white', home_url('/shop/')) : home_url('/shop/?product_tag=wicked-white'),
		'product-category/bbb'          => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('bbb', home_url('/shop/')) : home_url('/shop/?product_tag=bbb'),
		'product-category/calamino'          => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('calamino', home_url('/shop/')) : home_url('/product-tag/calamino/'),
		'product-category/compulsive'        => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('compulsive-angler', home_url('/shop/')) : home_url('/shop/?product_tag=compulsive-angler'),
		'product-category/compulsive-angler' => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('compulsive-angler', home_url('/shop/')) : home_url('/shop/?product_tag=compulsive-angler'),
	);
	if (! isset($legacy_redirects[ $request_path ])) {
		return;
	}

	$target = (string) $legacy_redirects[ $request_path ];
	$target = is_string($target) && '' !== $target ? $target : home_url('/product-category/bundles-deals/');
	$target_path = trim((string) wp_parse_url($target, PHP_URL_PATH), '/');
	if ('' === $target_path || $target_path === $request_path) {
		return;
	}

	wp_safe_redirect($target, 301);
	exit;
}
add_action('template_redirect', 'mad_baits_redirect_legacy_taxonomy_urls', 1);

/**
 * Render mobile-first quick category chips on shop/category archives.
 *
 * @return void
 */
function mad_baits_render_mobile_shop_chips() {
	if (! function_exists('is_shop') || ! function_exists('is_product_category')) {
		return;
	}

	if (! (is_shop() || is_product_category() || (function_exists('is_product_tag') && is_product_tag()))) {
		return;
	}

	$shop_url = mad_baits_get_shop_url();
	$items    = array(
		'boilies' => array(
			'label' => __('Boilies', 'mad-baits'),
			'url'   => function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('boilies', 'boilie'), $shop_url) : $shop_url,
		),
		'hookbaits' => array(
			'label' => __('Hookbaits', 'mad-baits'),
			'url'   => function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('hookbaits', 'hookbait', 'hook-baits'), $shop_url) : $shop_url,
		),
		'pellets' => array(
			'label' => __('Pellets', 'mad-baits'),
			'url'   => function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('pellets', 'pellet'), $shop_url) : $shop_url,
		),
		'liquids' => array(
			'label' => __('Liquids', 'mad-baits'),
			'url'   => function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('liquids', 'liquid-foods', 'other-liquid-foods'), $shop_url) : $shop_url,
		),
		'bundles-deals' => array(
			'label' => __('Deals', 'mad-baits'),
			'url'   => function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url,
		),
	);

	$current_term = is_tax('product_cat') ? get_queried_object() : null;
	$current_slug = $current_term instanceof WP_Term ? sanitize_title((string) $current_term->slug) : '';
	?>
	<div class="mad-mobile-shop-chips" aria-label="<?php esc_attr_e('Shop quick categories', 'mad-baits'); ?>">
		<?php foreach ($items as $item_slug => $item) : ?>
			<?php
			$url      = isset($item['url']) ? (string) $item['url'] : $shop_url;
			$is_active = ('' !== $current_slug && false !== strpos($current_slug, (string) $item_slug))
				|| (is_shop() && 'boilies' === $item_slug);
			?>
			<a class="mad-mobile-shop-chips__item<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($url); ?>">
				<?php echo esc_html((string) $item['label']); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}
add_action('woocommerce_before_shop_loop', 'mad_baits_render_mobile_shop_chips', 3);

/**
 * Add app-shell context classes.
 *
 * @param array<string> $classes Existing classes.
 * @return array<string>
 */
function mad_baits_add_app_shell_body_classes($classes) {
	$classes[] = 'mad-app-shell-ready';
	if (wp_is_mobile()) {
		$classes[] = 'mad-app-mobile';
		$classes[] = 'is-mobile-app-shell';
	}
	if (function_exists('mad_baits_should_hide_bottom_app_nav') && mad_baits_should_hide_bottom_app_nav()) {
		$classes[] = 'mad-app-nav-hidden-context';
	}
	if (function_exists('mad_baits_should_show_mobile_back_bar') && mad_baits_should_show_mobile_back_bar()) {
		$classes[] = 'mad-app-has-top-back';
	}
	return $classes;
}
add_filter('body_class', 'mad_baits_add_app_shell_body_classes', 30);

/**
 * Ensure checkout-flow WooCommerce body classes always suppress bottom app nav CSS.
 *
 * @param array<string> $classes Existing classes.
 * @return array<string>
 */
function mad_baits_add_nav_hidden_class_for_wc_checkout_flow($classes) {
	if (! is_array($classes) || ! function_exists('mad_baits_get_bottom_app_nav_hidden_wc_body_classes')) {
		return $classes;
	}

	$wc_hide = mad_baits_get_bottom_app_nav_hidden_wc_body_classes();
	if (array_intersect($wc_hide, $classes)) {
		$classes[] = 'mad-app-nav-hidden-context';
	}

	return $classes;
}
add_filter('body_class', 'mad_baits_add_nav_hidden_class_for_wc_checkout_flow', 99);

/**
 * Build root-level URL for PWA virtual assets.
 *
 * @param string $asset Asset key.
 * @return string
 */
function mad_baits_get_pwa_endpoint_url($asset) {
	return home_url('/?mad_baits_pwa=' . rawurlencode((string) $asset));
}

/**
 * Resolve a PWA icon URI, falling back to the cup logo.
 *
 * @param string $relative_path Relative path under the theme.
 * @return string
 */
function mad_baits_get_pwa_icon_uri($relative_path) {
	$relative_path = ltrim((string) $relative_path, '/');
	$fallback      = 'assets/img/CUP-LOGOv2-1.svg';

	if ('' !== $relative_path && file_exists(get_theme_file_path($relative_path))) {
		return get_theme_file_uri($relative_path);
	}

	return get_theme_file_uri($fallback);
}

/**
 * Add installable PWA meta tags for browsers and iOS.
 *
 * @return void
 */
function mad_baits_print_pwa_head_tags() {
	if (is_admin()) {
		return;
	}

	$manifest_url = esc_url(mad_baits_get_pwa_endpoint_url('manifest'));
	$theme_color  = '#fff202';
	$apple_icon_152 = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/apple-touch-icon-152x152.png'));
	$apple_icon_167 = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/apple-touch-icon-167x167.png'));
	$apple_icon_180 = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/apple-touch-icon-180x180.png'));
	$apple_icon_512 = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/app-icon-512.png'));
	$favicon_32     = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/app-favicon-32.png'));
	$favicon_16     = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/app-favicon-16.png'));
	$favicon_ico    = esc_url(mad_baits_get_pwa_icon_uri('assets/img/icons/app-favicon.ico'));
	$mask_icon    = esc_url(get_theme_file_uri('assets/img/CUP-LOGOv2-1.svg'));

	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $favicon_32 . '">' . "\n";
	echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $favicon_16 . '">' . "\n";
	echo '<link rel="shortcut icon" href="' . $favicon_ico . '">' . "\n";
	echo '<link rel="manifest" href="' . $manifest_url . '">' . "\n";
	echo '<meta name="theme-color" content="' . esc_attr($theme_color) . '">' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="Mad Baits">' . "\n";
	echo '<meta name="application-name" content="Mad Baits">' . "\n";
	echo '<meta name="msapplication-TileColor" content="' . esc_attr($theme_color) . '">' . "\n";
	echo '<meta name="msapplication-TileImage" content="' . $apple_icon_512 . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . $apple_icon_180 . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="152x152" href="' . $apple_icon_152 . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="167x167" href="' . $apple_icon_167 . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $apple_icon_180 . '">' . "\n";
	echo '<link rel="mask-icon" href="' . $mask_icon . '" color="' . esc_attr($theme_color) . '">' . "\n";
	// Detect installed app before paint so homepage install promo can stay hidden.
	echo '<script>try{var m=window.matchMedia("(display-mode: standalone)"),s=(m&&m.matches)||window.navigator.standalone===true;if(s){document.documentElement.classList.add("is-standalone-app");}}catch(e){}</script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

	$app_version = mad_baits_get_app_version();
	echo '<script>(function(){var V=' . wp_json_encode($app_version) . ';var KEY="madBaitsAppVersion";try{if(V){var p=localStorage.getItem(KEY);if(!p){localStorage.setItem(KEY,V);}}}catch(e){}})();</script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
}
add_action('wp_head', 'mad_baits_print_pwa_head_tags', 2);

/**
 * Serve virtual PWA assets from root URL for reliable browser installability.
 *
 * @return void
 */
function mad_baits_serve_pwa_assets() {
	if (is_admin()) {
		return;
	}

	$asset = isset($_GET['mad_baits_pwa']) ? sanitize_key(wp_unslash((string) $_GET['mad_baits_pwa'])) : '';
	if ('' === $asset) {
		return;
	}

	$map = array(
		'service-worker' => array(
			'file'        => 'service-worker.js',
			'contentType' => 'application/javascript; charset=UTF-8',
			'is_worker'   => true,
		),
		'manifest' => array(
			'file'        => 'manifest.json',
			'contentType' => 'application/manifest+json; charset=UTF-8',
			'is_worker'   => false,
		),
		'offline' => array(
			'file'        => 'offline.html',
			'contentType' => 'text/html; charset=UTF-8',
			'is_worker'   => false,
		),
	);

	if (! isset($map[$asset])) {
		status_header(404);
		exit;
	}

	$file_path = get_theme_file_path($map[$asset]['file']);
	if (! file_exists($file_path)) {
		status_header(404);
		exit;
	}

	nocache_headers();
	header('Content-Type: ' . $map[$asset]['contentType']);
	if (! empty($map[$asset]['is_worker'])) {
		header('Service-Worker-Allowed: /');
	}

	$contents = file_get_contents($file_path);
	if (false === $contents) {
		status_header(500);
		exit;
	}

	if (! empty($map[$asset]['is_worker'])) {
		$contents = mad_baits_prepare_service_worker_contents($contents);
	} elseif ('manifest' === $asset && function_exists('mad_baits_prepare_manifest_contents')) {
		$contents = mad_baits_prepare_manifest_contents($contents);
	}

	echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static worker file.
	exit;
}
add_action('template_redirect', 'mad_baits_serve_pwa_assets', 0);

/**
 * Resolve a product category link with graceful fallback.
 *
 * @param string|array $slugs Category slug or slugs.
 * @param string       $fallback_url URL used when term is unavailable.
 * @return string
 */
function mad_baits_get_product_cat_link($slugs, $fallback_url) {
	if (! taxonomy_exists('product_cat')) {
		return $fallback_url;
	}

	$resolved_slugs = array_values(array_filter(array_map('sanitize_title', (array) $slugs)));

	foreach ((array) $slugs as $slug) {
		$term = get_term_by('slug', sanitize_title((string) $slug), 'product_cat');
		if (! $term || is_wp_error($term)) {
			continue;
		}

		$link = get_term_link($term);
		if (! is_wp_error($link) && is_string($link) && '' !== $link) {
			return $link;
		}
	}

	// Temporary storefront hardening while duplicate/misspelled accessories terms are cleaned in WP admin.
	if (! empty(array_intersect($resolved_slugs, array('accessories', 'accessories-2', 'accesories')))) {
		return home_url('/product-category/accessories/');
	}

	return $fallback_url;
}

/**
 * Resolve shop archive URL.
 *
 * @return string
 */
function mad_baits_get_shop_url() {
	$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');

	return (is_string($shop_url) && '' !== $shop_url) ? $shop_url : home_url('/shop/');
}

/**
 * Product IDs for a category-slug landing page.
 *
 * @param string[] $category_slugs Candidate product_cat slugs.
 * @param int      $limit          Max products.
 * @return int[]
 */
function mad_baits_get_landing_product_ids_by_category_slugs(array $category_slugs, $limit = 24) {
	$category_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $category_slugs))));
	if (empty($category_slugs)) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max(1, (int) $limit),
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category_slugs,
				),
			),
		)
	);

	$product_ids = isset($query->posts) && is_array($query->posts) ? $query->posts : array();
	wp_reset_postdata();

	return array_values(array_filter(array_map('absint', $product_ids)));
}

/**
 * Whether a product belongs on the Rock Salt landing/category surfaces.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_is_rock_salt_product($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return false;
	}

	$title = strtolower((string) get_the_title($product_id));
	$slug  = strtolower((string) get_post_field('post_name', $product_id));

	$needles = array('rock salt', 'rock-salt', 'himalayan rock', 'himalayan-rock');
	foreach ($needles as $needle) {
		if ('' !== $needle && (false !== strpos($title, $needle) || false !== strpos($slug, $needle))) {
			return true;
		}
	}

	if (taxonomy_exists('product_cat')) {
		$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		if (! is_wp_error($cat_slugs) && is_array($cat_slugs)) {
			$rock_slugs = array('rock-salt', 'rocksalt', 'pink-himalayan-rock-salt', 'himalayan-rock-salt');
			if (! empty(array_intersect(array_map('sanitize_title', $cat_slugs), $rock_slugs))) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Product IDs for the Rock Salt landing page.
 *
 * Uses category assignment first, then name/slug matching for products like
 * Pink Himalayan rock salt that may live in another category in Woo admin.
 *
 * @param int $limit Max products.
 * @return int[]
 */
function mad_baits_get_rock_salt_product_ids($limit = 24) {
	$limit = max(1, (int) $limit);
	$ids   = mad_baits_get_landing_product_ids_by_category_slugs(
		array('rock-salt', 'rocksalt', 'pink-himalayan-rock-salt', 'himalayan-rock-salt'),
		$limit
	);

	$known_slugs = array(
		'pink-himalayan-rock-salt-coarse-3kg-or-5kg-bucket-refill-5kg',
		'pink-himalayan-rock-salt',
	);
	foreach ($known_slugs as $product_slug) {
		$post = get_page_by_path((string) $product_slug, OBJECT, 'product');
		if ($post instanceof WP_Post) {
			$ids[] = (int) $post->ID;
		}
	}

	if (count($ids) < $limit && function_exists('wc_get_products')) {
		$search_ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => $limit,
				'return' => 'ids',
				's'      => 'rock salt',
			)
		);
		$ids = array_merge($ids, (array) $search_ids);
	}

	$ids = array_values(
		array_unique(
			array_filter(
				array_map('absint', $ids),
				static function ($product_id) {
					return mad_baits_is_rock_salt_product($product_id);
				}
			)
		)
	);

	return array_slice($ids, 0, $limit);
}

/**
 * Final customer-facing shop categories.
 *
 * @return array<string, array{label:string, slugs:string[], public:bool, nav_primary:bool}>
 */
function mad_baits_get_final_shop_category_definitions() {
	return array(
		'boilies' => array('label' => __('Boilies', 'mad-baits'), 'slugs' => array('boilies', 'boilie'), 'public' => true, 'nav_primary' => true),
		'hookbaits' => array('label' => __('Hookbaits', 'mad-baits'), 'slugs' => array('hookbaits', 'hookbait', 'hook-baits'), 'public' => true, 'nav_primary' => true),
		'pellets' => array('label' => __('Pellets', 'mad-baits'), 'slugs' => array('pellets', 'pellet'), 'public' => true, 'nav_primary' => true),
		'liquids' => array('label' => __('Liquids', 'mad-baits'), 'slugs' => array('liquids', 'liquid-foods', 'other-liquid-foods', 'liquid'), 'public' => true, 'nav_primary' => true),
		'groundbait-bag-mix' => array('label' => __('Groundbait & Bag Mix', 'mad-baits'), 'slugs' => array('groundbait-bag-mix', 'groundbait', 'bag-mix'), 'public' => true, 'nav_primary' => true),
		'paste' => array('label' => __('Paste', 'mad-baits'), 'slugs' => array('paste'), 'public' => true, 'nav_primary' => false),
		'sprays' => array('label' => __('Sprays', 'mad-baits'), 'slugs' => array('sprays', 'spray'), 'public' => true, 'nav_primary' => false),
		'bundles-deals' => array('label' => __('Bundles & Deals', 'mad-baits'), 'slugs' => array('bundles-deals', 'bundle-deals', 'bundles', 'deals', 'offers', 'bundle-offers'), 'public' => true, 'nav_primary' => true),
		'tackle' => array('label' => __('Tackle', 'mad-baits'), 'slugs' => array('tackle', 'terminal', 'terminal-tackle'), 'public' => true, 'nav_primary' => true),
		'accessories' => array('label' => __('Accessories', 'mad-baits'), 'slugs' => array('accessories', 'accessories-2', 'accesories'), 'public' => true, 'nav_primary' => true),
		'clothing' => array('label' => __('Clothing', 'mad-baits'), 'slugs' => array('clothing', 'merchandise', 'merch'), 'public' => true, 'nav_primary' => true),
		'rock-salt' => array('label' => __('Rock Salt', 'mad-baits'), 'slugs' => array('rock-salt', 'rocksalt'), 'public' => true, 'nav_primary' => false),
		'extras' => array('label' => __('Extras', 'mad-baits'), 'slugs' => array('extras'), 'public' => true, 'nav_primary' => false),
		'team-testing' => array('label' => __('Team & Testing', 'mad-baits'), 'slugs' => array('team-testing'), 'public' => false, 'nav_primary' => false),
	);
}

/**
 * Final range chips/menu entries.
 *
 * @return array<string, array{label:string, slug:string}>
 */
function mad_baits_get_final_shop_range_definitions() {
	return array(
		'asbo' => array('label' => 'ASBO', 'slug' => 'asbo'),
		'pandemic' => array('label' => 'Pandemic', 'slug' => 'pandemic'),
		'p-fish' => array('label' => 'P-Fish', 'slug' => 'p-fish-2'),
		'nutz-plus' => array('label' => 'Nutz Plus', 'slug' => 'nutz-plus'),
		'nutz-banana' => array('label' => 'Nutz Banana', 'slug' => 'nutz-banana'),
		'wicked-white' => array('label' => 'Wicked Whites', 'slug' => 'wicked-white'),
		'bbb' => array('label' => 'BBB', 'slug' => 'bbb'),
		'calamino' => array('label' => 'Calamino', 'slug' => 'calamino'),
		'compulsive-angler' => array('label' => 'Compulsive Angler', 'slug' => 'compulsive-angler'),
	);
}

/**
 * Range list used by the homepage Choose Your Edge image controls.
 *
 * @return array<string, string>
 */
function mad_baits_get_choose_edge_ranges() {
	return array(
		'asbo'              => 'ASBO',
		'bbb'               => 'BBB',
		'nutz-plus'         => 'Nutz Plus',
		'nutz-banana'       => 'Nutz Banana',
		'pandemic'          => 'Pandemic',
		'p-fish'            => 'P-Fish',
		'wicked-white'      => 'Wicked Whites',
		'compulsive-angler' => 'Compulsive Angler',
	);
}

/**
 * Resolve Customize setting key for a range card image.
 *
 * @param string $range_slug Range slug.
 * @return string
 */
function mad_baits_get_choose_edge_image_setting_key($range_slug) {
	$range_slug = sanitize_title((string) $range_slug);
	if ('p-fish-2' === $range_slug) {
		$range_slug = 'p-fish';
	}

	return 'mad_baits_choose_edge_image_' . str_replace('-', '_', $range_slug);
}

/**
 * Get manually selected Choose Your Edge image URL for a range.
 *
 * @param string $range_slug Range slug.
 * @param string $size Image size.
 * @return string
 */
function mad_baits_get_choose_edge_custom_image_url($range_slug, $size = 'large') {
	$setting_key = mad_baits_get_choose_edge_image_setting_key($range_slug);
	$attachment_id = absint((int) get_theme_mod($setting_key, 0));
	if ($attachment_id < 1) {
		return '';
	}

	$image_url = wp_get_attachment_image_url($attachment_id, $size);
	return is_string($image_url) ? $image_url : '';
}

/**
 * Register homepage Choose Your Edge image controls in Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function mad_baits_register_choose_edge_customizer($wp_customize) {
	if (! ($wp_customize instanceof WP_Customize_Manager)) {
		return;
	}

	$wp_customize->add_section(
		'mad_baits_choose_edge_images',
		array(
			'title'       => __('Homepage: Choose Your Edge Images', 'mad-baits'),
			'description' => __('Set a manual card image for each bait range. These override automatic tagged-product images.', 'mad-baits'),
			'priority'    => 132,
		)
	);

	foreach (mad_baits_get_choose_edge_ranges() as $range_slug => $range_label) {
		$setting_key = mad_baits_get_choose_edge_image_setting_key($range_slug);

		$wp_customize->add_setting(
			$setting_key,
			array(
				'default'           => 0,
				'type'              => 'theme_mod',
				'transport'         => 'refresh',
				'sanitize_callback' => 'absint',
			)
		);

		if (class_exists('WP_Customize_Media_Control')) {
			$wp_customize->add_control(
				new WP_Customize_Media_Control(
					$wp_customize,
					$setting_key,
					array(
						'label'       => sprintf(__('Choose image: %s', 'mad-baits'), $range_label),
						'section'     => 'mad_baits_choose_edge_images',
						'settings'    => $setting_key,
						'mime_type'   => 'image',
					)
				)
			);
		}
	}
}
add_action('customize_register', 'mad_baits_register_choose_edge_customizer');

/**
 * Build a shop URL filtered by range.
 *
 * @param string $range_slug Canonical range slug.
 * @param string $fallback_url Optional fallback URL.
 * @return string
 */
function mad_baits_get_range_filter_url($range_slug, $fallback_url = '') {
	$range_slug = sanitize_title((string) $range_slug);
	$shop_url   = mad_baits_get_shop_url();

	if ('' === $range_slug) {
		return '' !== $fallback_url ? (string) $fallback_url : $shop_url;
	}

	if (function_exists('mad_baits_get_range_tag_shop_url')) {
		return mad_baits_get_range_tag_shop_url($range_slug);
	}

	$range_slug = function_exists('mad_baits_resolve_range_tag_slug')
		? mad_baits_resolve_range_tag_slug($range_slug)
		: $range_slug;

	$term = get_term_by('slug', $range_slug, 'product_tag');
	if ($term instanceof WP_Term && ! is_wp_error($term)) {
		$link = get_term_link($term);
		if (! is_wp_error($link) && is_string($link) && '' !== $link) {
			return $link;
		}
	}

	$target = add_query_arg(
		array(
			'product_tag' => $range_slug,
		),
		$shop_url
	);

	return (is_string($target) && '' !== $target)
		? $target
		: ('' !== $fallback_url ? (string) $fallback_url : $shop_url);
}

/**
 * Product category slugs hidden from the homepage (cards, grids, badges).
 *
 * @return string[]
 */
function mad_baits_get_hidden_homepage_product_cat_slugs() {
	$slugs = array(
		'atomic-tackle',
		'discount',
		'discount-ex',
		'team-testing',
	);

	/**
	 * Filter slugs excluded from homepage category surfaces and product strips.
	 *
	 * @param string[] $slugs Category slugs.
	 */
	return array_values(array_unique(array_filter(array_map('sanitize_title', (array) apply_filters('mad_baits_hidden_homepage_product_cat_slugs', $slugs)))));
}

/**
 * Whether a product category should be hidden on the homepage.
 *
 * @param WP_Term|string $term_or_slug Term object or category slug.
 * @return bool
 */
function mad_baits_is_hidden_homepage_product_cat($term_or_slug) {
	$slug = '';
	$name = '';

	if ($term_or_slug instanceof WP_Term) {
		$slug = sanitize_title((string) $term_or_slug->slug);
		$name = strtolower(trim((string) $term_or_slug->name));
	} else {
		$slug = sanitize_title((string) $term_or_slug);
	}

	$blocked_slugs = mad_baits_get_hidden_homepage_product_cat_slugs();
	if ('' !== $slug && in_array($slug, $blocked_slugs, true)) {
		return true;
	}

	if ('' !== $slug && (false !== strpos($slug, 'atomic-tackle') || false !== strpos($slug, 'clothing'))) {
		return true;
	}

	if ('tackle' === $slug || ('' !== $slug && substr($slug, -7) === '-tackle')) {
		return true;
	}

	$blocked_names = array('tackle', 'atomic tackle', 'terminal tackle', 'clothing');
	foreach ($blocked_names as $blocked_name) {
		if ($name === $blocked_name) {
			return true;
		}
	}

	if ('' !== $name && (false !== strpos($name, 'atomic tackle') || false !== strpos($name, 'clothing'))) {
		return true;
	}

	return false;
}

/**
 * Term IDs for categories excluded from homepage product queries.
 *
 * @return int[]
 */
function mad_baits_get_hidden_homepage_product_cat_term_ids() {
	static $cached = null;

	if (null !== $cached) {
		return $cached;
	}

	$cached = array();

	if (! taxonomy_exists('product_cat')) {
		return $cached;
	}

	foreach (mad_baits_get_hidden_homepage_product_cat_slugs() as $slug) {
		$term = get_term_by('slug', $slug, 'product_cat');
		if ($term && ! is_wp_error($term)) {
			$cached[] = (int) $term->term_id;
		}
	}

	$all_terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'all',
		)
	);
	if (! is_wp_error($all_terms)) {
		foreach ($all_terms as $term) {
			if ($term instanceof WP_Term && mad_baits_is_hidden_homepage_product_cat($term)) {
				$cached[] = (int) $term->term_id;
			}
		}
	}

	$cached = array_values(array_unique(array_filter($cached)));

	return $cached;
}

/**
 * wc_get_products() args excluding tackle/clothing categories on the homepage.
 *
 * @param array<string, mixed> $args Query args.
 * @return array<string, mixed>
 */
function mad_baits_filter_homepage_wc_product_query_args($args) {
	if (! is_front_page()) {
		return $args;
	}

	$exclude_tax = mad_baits_get_homepage_product_exclude_tax_query();
	if (empty($exclude_tax)) {
		return $args;
	}

	$tax_query = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : array();
	if (empty($tax_query)) {
		$args['tax_query'] = $exclude_tax;
	} else {
		$args['tax_query'] = array(
			'relation' => 'AND',
			$tax_query,
			$exclude_tax[0],
		);
	}

	return $args;
}

/**
 * Whether a product should appear in homepage product strips.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_should_show_product_on_homepage($product_id) {
	if (! is_front_page() || $product_id < 1) {
		return true;
	}

	$terms = wp_get_post_terms($product_id, 'product_cat');
	if (empty($terms) || is_wp_error($terms)) {
		return true;
	}

	foreach ($terms as $term) {
		if ($term instanceof WP_Term && mad_baits_is_hidden_homepage_product_cat($term)) {
			return false;
		}
	}

	return true;
}

/**
 * Tax query fragment excluding tackle/accessory categories from homepage loops.
 *
 * @return array<int, array<string, mixed>>
 */
function mad_baits_get_homepage_product_exclude_tax_query() {
	$term_ids = mad_baits_get_hidden_homepage_product_cat_term_ids();

	if (empty($term_ids)) {
		return array();
	}

	return array(
		array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => $term_ids,
			'operator'         => 'NOT IN',
			'include_children' => true,
		),
	);
}

/**
 * First visible product category name for cards (skips homepage-hidden terms).
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_get_product_card_category_label($product_id) {
	$product_id = absint($product_id);

	if ($product_id < 1 || ! taxonomy_exists('product_cat')) {
		return '';
	}

	$terms = wc_get_product_terms($product_id, 'product_cat');
	if (empty($terms) || is_wp_error($terms)) {
		return '';
	}

	foreach ($terms as $term) {
		if (! $term instanceof WP_Term) {
			continue;
		}

		if (is_front_page() && mad_baits_is_hidden_homepage_product_cat($term)) {
			continue;
		}

		return (string) $term->name;
	}

	return '';
}

/**
 * Product categories for the homepage "Choose Your Edge" section only (whitelist).
 *
 * Resolves each row by slug aliases, skips missing terms, hides empty categories
 * (count 0). Bundle Deals falls back to the bundle-deals page URL when no matching
 * non-empty category exists. Compulsive Anglers falls back to the Compulsive landing page.
 *
 * @return array<int, array{term: ?WP_Term, link: string, name: string, count: ?int, slug_key: string, meta_text: string}>
 */
function mad_baits_get_home_choose_your_edge_items() {
	if (! taxonomy_exists('product_cat')) {
		return array();
	}

	$rows = array(
		array(
			'range_slug' => 'asbo',
			'label'      => __('ASBO', 'mad-baits'),
		),
		array(
			'range_slug' => 'bbb',
			'label'      => __('BBB', 'mad-baits'),
		),
		array(
			'range_slug' => 'nutz-plus',
			'label'      => __('Nutz Plus', 'mad-baits'),
		),
		array(
			'range_slug' => 'nutz-banana',
			'label'      => __('Nutz Banana', 'mad-baits'),
		),
		array(
			'range_slug' => 'pandemic',
			'label'      => __('Pandemic', 'mad-baits'),
		),
		array(
			'range_slug' => 'p-fish',
			'label'      => __('P-Fish', 'mad-baits'),
		),
		array(
			'range_slug' => 'wicked-white',
			'label'      => __('Wicked White', 'mad-baits'),
		),
		array(
			'range_slug' => 'compulsive-angler',
			'label'      => __('Compulsive Angler', 'mad-baits'),
		),
	);

	$out = array();

	foreach ($rows as $row) {
		$label              = isset($row['label']) ? (string) $row['label'] : '';
		$slugs              = isset($row['slugs']) && is_array($row['slugs']) ? $row['slugs'] : array();
		$range_slug         = isset($row['range_slug']) ? sanitize_title((string) $row['range_slug']) : '';
		$bundle_page        = ! empty($row['bundle_page']);
		$compulsive_landing = ! empty($row['compulsive_landing']);

		if ('' !== $range_slug) {
			$out[] = array(
				'term'      => null,
				'link'      => mad_baits_get_range_filter_url($range_slug, mad_baits_get_shop_url()),
				'name'      => $label,
				'count'     => null,
				'slug_key'  => $range_slug,
				'meta_text' => __('Shop by range', 'mad-baits'),
			);
			continue;
		}

		$term = null;
		foreach ($slugs as $slug) {
			$slug = sanitize_title((string) $slug);
			if ('' === $slug) {
				continue;
			}
			$candidate = get_term_by('slug', $slug, 'product_cat');
			if ($candidate && ! is_wp_error($candidate)) {
				$term = $candidate;
				break;
			}
		}

		if ($term && ! is_wp_error($term)) {
			if (mad_baits_is_hidden_homepage_product_cat($term)) {
				$term = null;
			}
		}

		if ($term && ! is_wp_error($term)) {
			if ((int) $term->count < 1) {
				if ($bundle_page && function_exists('mad_baits_get_bundle_deals_url')) {
					$fallback = mad_baits_get_bundle_deals_url();
					if (is_string($fallback) && '' !== $fallback) {
						$out[] = array(
							'term'      => null,
							'link'      => $fallback,
							'name'      => $label,
							'count'     => null,
							'slug_key'  => 'bundle-deals',
							'meta_text' => __('Deals & bundles', 'mad-baits'),
						);
					}
				} elseif ($compulsive_landing && function_exists('mad_baits_get_compulsive_angler_url')) {
					$fallback = mad_baits_get_compulsive_angler_url();
					if (is_string($fallback) && '' !== $fallback) {
						$out[] = array(
							'term'      => null,
							'link'      => $fallback,
							'name'      => $label,
							'count'     => null,
							'slug_key'  => 'compulsive-anglers',
							'meta_text' => __('Campaign range', 'mad-baits'),
						);
					}
				}
				continue;
			}

			$link = get_term_link($term);
			if (is_wp_error($link) || ! is_string($link) || '' === $link) {
				continue;
			}

			$out[] = array(
				'term'      => $term,
				'link'      => $link,
				'name'      => $label,
				'count'     => (int) $term->count,
				'slug_key'  => sanitize_title((string) $term->slug),
				'meta_text' => '',
			);
			continue;
		}

		if ($bundle_page && function_exists('mad_baits_get_bundle_deals_url')) {
			$fallback = mad_baits_get_bundle_deals_url();
			if (is_string($fallback) && '' !== $fallback) {
				$out[] = array(
					'term'      => null,
					'link'      => $fallback,
					'name'      => $label,
					'count'     => null,
					'slug_key'  => 'bundle-deals',
					'meta_text' => __('Deals & bundles', 'mad-baits'),
				);
			}
		} elseif ($compulsive_landing && function_exists('mad_baits_get_compulsive_angler_url')) {
			$fallback = mad_baits_get_compulsive_angler_url();
			if (is_string($fallback) && '' !== $fallback) {
				$out[] = array(
					'term'      => null,
					'link'      => $fallback,
					'name'      => $label,
					'count'     => null,
					'slug_key'  => 'compulsive-anglers',
					'meta_text' => __('Campaign range', 'mad-baits'),
				);
			}
		}
	}

	return $out;
}

/**
 * Resolve first existing theme image URI from candidate list.
 *
 * @param array|string $candidates            Candidate filenames inside /assets/img.
 * @param bool         $prefer_scene_variants Whether to prefer optimized *-scene variants first.
 * @return string
 */
function mad_baits_get_theme_image_uri($candidates, $prefer_scene_variants = true) {
	$resolve_preferred_variant = static function ($filename) {
		$filename = trim((string) $filename);
		if ('' === $filename) {
			return '';
		}

		$pathinfo = pathinfo($filename);
		$name = isset($pathinfo['filename']) ? (string) $pathinfo['filename'] : '';
		$ext  = isset($pathinfo['extension']) ? strtolower((string) $pathinfo['extension']) : '';

		if ('' === $name || '' === $ext) {
			return '';
		}

		$preferred = array(
			$name . '-scene.webp',
			$name . '-scene.jpg',
			$name . '-scene.jpeg',
		);

		foreach ($preferred as $variant) {
			$variant_path = get_theme_file_path('/assets/img/' . $variant);
			if ($variant_path && file_exists($variant_path)) {
				return $variant;
			}
		}

		return '';
	};

	foreach ((array) $candidates as $filename) {
		$filename = trim((string) $filename);
		if ('' === $filename) {
			continue;
		}

		if (! empty($prefer_scene_variants)) {
			$preferred_variant = $resolve_preferred_variant($filename);
			if ('' !== $preferred_variant) {
				$preferred_uri = get_theme_file_uri('/assets/img/' . ltrim($preferred_variant, '/'));
				if (is_string($preferred_uri) && '' !== $preferred_uri) {
					return $preferred_uri;
				}
			}
		}

		$relative_path = '/assets/img/' . ltrim($filename, '/');
		$absolute_path = get_theme_file_path($relative_path);
		if ($absolute_path && file_exists($absolute_path)) {
			return get_theme_file_uri($relative_path);
		}
	}

	return '';
}

/**
 * Return stable fallback image candidates that are currently in /assets/img.
 *
 * @param string $context Optional context key.
 * @return string[]
 */
function mad_baits_get_existing_theme_image_candidates($context = 'general') {
	$context = sanitize_key((string) $context);

	$context_map = array(
		'hero' => array(
			'jerry-rigging-bivvy.png',
			'jerry-sunset-cast.png',
			'jerry-wading-rod-setup.png',
			'HAMMONDAPRIL_22_031.jpg',
			'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
			'IMG_0820-scaled.jpeg',
			'IMG_6209.jpeg',
			'IMG_6277.jpeg',
		),
		'campaign' => array(
			'hookbaits-hero.png',
			'compulsive-triple-hookbaits.png',
			'compulsive-orange-popups-night.png',
			'compulsive-pink-popups-hand.png',
			'compulsive-yellow-popups-bank.png',
			'p-fish-glug-waterline.png',
			'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
			'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
			'02_02_22_Jerry_Hammond_058-e1676481405465.jpg',
			'IMG_6270-1.jpeg',
			'IMG_6271.jpeg',
			'IMG_6273.jpeg',
			'IMG_6276.jpeg',
			'IMG_6277.jpeg',
			'session-pack.png',
		),
		'footer' => array(
			'jerry-sunset-cast.png',
			'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
			'HAMMONDAPRIL_22_031.jpg',
			'IMG_0820-scaled.jpeg',
			'IMG_6277.jpeg',
		),
	);

	$base_candidates = array(
		'jerry-rigging-bivvy.png',
		'jerry-sunset-cast.png',
		'jerry-wading-rod-setup.png',
		'compulsive-orange-popups-night.png',
		'compulsive-pink-popups-hand.png',
		'compulsive-yellow-popups-bank.png',
		'p-fish-glug-waterline.png',
		'HAMMONDAPRIL_22_031.jpg',
		'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
		'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
		'IMG_0820-scaled.jpeg',
		'IMG_6209.jpeg',
		'IMG_6270-1.jpeg',
		'IMG_6271.jpeg',
		'IMG_6273.jpeg',
		'IMG_6276.jpeg',
		'IMG_6277.jpeg',
		'CUP-LOGOv2-1.svg',
	);

	$candidates = array_merge(
		isset($context_map[ $context ]) ? $context_map[ $context ] : array(),
		$base_candidates
	);

	$resolved = array();
	foreach (array_values(array_unique($candidates)) as $filename) {
		$relative_path = '/assets/img/' . ltrim((string) $filename, '/');
		$absolute_path = get_theme_file_path($relative_path);
		if ($absolute_path && file_exists($absolute_path)) {
			$resolved[] = $filename;
		}
	}

	if (! empty($resolved)) {
		return $resolved;
	}

	return array(
		'CUP-LOGOv2-1.svg',
	);
}

/**
 * Return prioritized moody/atmospheric background candidates.
 *
 * @param string $context Optional context key.
 * @return string[]
 */
function mad_baits_get_moody_background_candidates($context = 'general') {
	$base_candidates = mad_baits_get_existing_theme_image_candidates();

	$context_map = array(
		'hero' => array(
			'jerry-rigging-bivvy.png',
			'jerry-sunset-cast.png',
			'jerry-wading-rod-setup.png',
			'HAMMONDAPRIL_22_031.jpg',
			'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
			'IMG_0820-scaled.jpeg',
			'IMG_6209.jpeg',
		),
		'campaign' => array(
			'compulsive-orange-popups-night.png',
			'compulsive-pink-popups-hand.png',
			'compulsive-yellow-popups-bank.png',
			'p-fish-glug-waterline.png',
			'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
			'IMG_6270-1.jpeg',
			'IMG_6271.jpeg',
			'IMG_6273.jpeg',
			'IMG_6276.jpeg',
			'IMG_6277.jpeg',
		),
		'footer' => array(
			'jerry-sunset-cast.png',
			'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
			'HAMMONDAPRIL_22_031.jpg',
			'IMG_0820-scaled.jpeg',
			'IMG_6277.jpeg',
		),
	);

	$context = sanitize_key((string) $context);
	$context_candidates = isset($context_map[ $context ]) ? (array) $context_map[ $context ] : array();

	return array_values(array_unique(array_merge($context_candidates, mad_baits_get_existing_theme_image_candidates($context), $base_candidates)));
}

/**
 * Return real image filenames found in /assets/img.
 *
 * @return string[]
 */
function mad_baits_get_theme_asset_inventory() {
	static $inventory = null;

	if (null !== $inventory) {
		return $inventory;
	}

	$inventory = array();
	$img_dir   = trailingslashit(get_template_directory()) . 'assets/img/';
	if (! is_dir($img_dir) || ! is_readable($img_dir)) {
		return $inventory;
	}

	$files = scandir($img_dir);
	if (! is_array($files)) {
		return $inventory;
	}

	foreach ($files as $file) {
		$file = (string) $file;
		if ('' === $file || '.' === $file || '..' === $file) {
			continue;
		}

		$path = $img_dir . $file;
		if (! is_file($path)) {
			continue;
		}

		$ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
		if (! in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'avif', 'svg'), true)) {
			continue;
		}

		$inventory[] = $file;
	}

	sort($inventory, SORT_NATURAL | SORT_FLAG_CASE);
	return $inventory;
}

/**
 * Build inline CSS variable declaration string.
 *
 * @param array $vars CSS variable map.
 * @return string
 */
function mad_baits_build_css_var_string($vars) {
	$declarations = array();

	foreach ((array) $vars as $var_name => $value) {
		$var_name = trim((string) $var_name);
		$value    = trim((string) $value);
		if ('' === $var_name || '' === $value) {
			continue;
		}

		$declarations[] = $var_name . ': ' . $value;
	}

	return implode('; ', $declarations);
}

/**
 * Resolve a context image from mapped product-category slugs.
 *
 * @param array $slugs Product category slugs.
 * @return string
 */
function mad_baits_resolve_product_cat_hero_image($slugs) {
	$category_slugs = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ($slug) {
						return sanitize_title((string) $slug);
					},
					(array) $slugs
				)
			)
		)
	);

	if (empty($category_slugs) || ! taxonomy_exists('product_cat')) {
		return '';
	}

	foreach ($category_slugs as $slug) {
		$term = get_term_by('slug', $slug, 'product_cat');
		if (! $term instanceof WP_Term) {
			continue;
		}

		$thumb_id = absint((int) get_term_meta((int) $term->term_id, 'thumbnail_id', true));
		if (! $thumb_id) {
			continue;
		}

		$term_image = wp_get_attachment_image_url($thumb_id, 'full');
		if (is_string($term_image) && '' !== $term_image) {
			return $term_image;
		}
	}

	if (! function_exists('wc_get_products')) {
		return '';
	}

	$product_ids = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 8,
			'return'   => 'ids',
			'orderby'  => 'date',
			'order'    => 'DESC',
			'category' => $category_slugs,
		)
	);

	foreach ((array) $product_ids as $product_id) {
		$image_uri = get_the_post_thumbnail_url((int) $product_id, 'full');
		if (is_string($image_uri) && '' !== $image_uri) {
			return $image_uri;
		}
	}

	return '';
}

/**
 * Resolve a context image from semantic product keywords.
 *
 * @param array $terms Semantic keywords.
 * @return string
 */
function mad_baits_resolve_semantic_product_hero_image($terms) {
	if (! function_exists('mad_baits_get_semantic_product_ids')) {
		return '';
	}

	$needles = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ($term) {
						return sanitize_text_field((string) $term);
					},
					(array) $terms
				)
			)
		)
	);

	if (empty($needles)) {
		return '';
	}

	$product_ids = (array) mad_baits_get_semantic_product_ids($needles, 8);
	foreach ($product_ids as $product_id) {
		$image_uri = get_the_post_thumbnail_url((int) $product_id, 'full');
		if (is_string($image_uri) && '' !== $image_uri) {
			return $image_uri;
		}
	}

	return '';
}

/**
 * Resolve best available hero image URI for page/category context.
 *
 * @param array $args Hero resolver arguments.
 * @return string
 */
function mad_baits_resolve_context_hero_image($args = array()) {
	$args = wp_parse_args(
		(array) $args,
		array(
			'post_id'             => 0,
			'term_id'             => 0,
			'term_taxonomy'       => 'product_cat',
			'candidate_filenames' => array(),
			'product_cat_slugs'   => array(),
			'semantic_terms'      => array(),
			'allow_latest_product'=> true,
			'prefer_theme_candidates' => true,
		)
	);

	$post_id = absint($args['post_id']);
	if ($post_id) {
		$post_thumb = get_the_post_thumbnail_url($post_id, 'full');
		if (is_string($post_thumb) && '' !== $post_thumb) {
			return $post_thumb;
		}
	}

	$term_id = absint($args['term_id']);
	if ($term_id && taxonomy_exists((string) $args['term_taxonomy'])) {
		$term_thumb_id = absint((int) get_term_meta($term_id, 'thumbnail_id', true));
		if ($term_thumb_id) {
			$term_thumb = wp_get_attachment_image_url($term_thumb_id, 'full');
			if (is_string($term_thumb) && '' !== $term_thumb) {
				return $term_thumb;
			}
		}
	}

	$candidates         = array_values(array_filter(array_map('strval', (array) $args['candidate_filenames'])));
	$prefer_theme_first = ! empty($args['prefer_theme_candidates']) && ! empty($candidates);

	if ($prefer_theme_first) {
		$theme_uri = mad_baits_get_theme_image_uri($candidates, true);
		if (is_string($theme_uri) && '' !== $theme_uri) {
			return $theme_uri;
		}
	}

	$category_image = mad_baits_resolve_product_cat_hero_image((array) $args['product_cat_slugs']);
	if ('' !== $category_image) {
		return $category_image;
	}

	$semantic_image = mad_baits_resolve_semantic_product_hero_image((array) $args['semantic_terms']);
	if ('' !== $semantic_image) {
		return $semantic_image;
	}

	$inventory       = mad_baits_get_theme_asset_inventory();
	$mapped_candidates = array();

	foreach ($candidates as $candidate) {
		if (in_array($candidate, $inventory, true)) {
			$mapped_candidates[] = $candidate;
		}
	}

	if (! empty($mapped_candidates)) {
		$mapped_uri = mad_baits_get_theme_image_uri($mapped_candidates, false);
		if (is_string($mapped_uri) && '' !== $mapped_uri) {
			return $mapped_uri;
		}
	}

	if (! empty($inventory)) {
		$inventory_uri = mad_baits_get_theme_image_uri($inventory, false);
		if (is_string($inventory_uri) && '' !== $inventory_uri) {
			return $inventory_uri;
		}
	}

	if (! empty($args['allow_latest_product']) && function_exists('wc_get_products')) {
		$product_ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 4,
				'return' => 'ids',
			)
		);

		foreach ((array) $product_ids as $product_id) {
			$product_image = get_the_post_thumbnail_url((int) $product_id, 'full');
			if (is_string($product_image) && '' !== $product_image) {
				return $product_image;
			}
		}
	}

	return '';
}

/**
 * Get page-level hero data with mapped copy and visual preset.
 *
 * @param array $args Hero options.
 * @return array
 */
function mad_baits_get_page_hero_data($args = array()) {
	$queried = get_queried_object();
	$post_id = isset($args['post_id']) ? absint($args['post_id']) : (is_singular('page') ? (int) get_queried_object_id() : 0);
	$slug    = isset($args['slug']) ? sanitize_title((string) $args['slug']) : (($queried instanceof WP_Post) ? sanitize_title((string) $queried->post_name) : '');
	$title   = isset($args['title']) ? (string) $args['title'] : (($queried instanceof WP_Post) ? (string) get_the_title($queried) : __('Mad Baits', 'mad-baits'));

	$page_map = array(
		'about' => array(
			'label'      => __('About Mad Baits', 'mad-baits'),
			'intro'      => __('The quality-first philosophy behind our bait systems, rolling process and campaign results.', 'mad-baits'),
			'candidates' => array('p-fish-glug-waterline.png', 'HAMMONDAPRIL_22_031.jpg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('about', 'brand', 'campaign', 'angler'),
			'position'   => '50% 28%',
			'accent'     => '78% 10%',
		),
		'contact' => array(
			'label'      => __('Contact', 'mad-baits'),
			'intro'      => __('Speak to the team for product support, order help and practical session guidance.', 'mad-baits'),
			'candidates' => array('jerry-sunset-cast.png', 'jerry-wading-rod-setup.png', 'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg', 'IMG_6277.jpeg'),
			'semantic_terms' => array('catch', 'angler', 'session', 'boilie'),
			'position'   => '50% 34%',
			'accent'     => '82% 12%',
		),
		'faq' => array(
			'label'      => __('Support', 'mad-baits'),
			'intro'      => __('Straight answers on delivery, returns, product use and order support.', 'mad-baits'),
			'candidates' => array('IMG_6273.jpeg', 'IMG_6271.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('packing', 'delivery', 'returns'),
			'position'   => '42% 34%',
			'accent'     => '86% 18%',
		),
		'delivery-information' => array(
			'label'      => __('Delivery', 'mad-baits'),
			'intro'      => __('Dispatch timelines, courier expectations and practical shipping notes for every order.', 'mad-baits'),
			'candidates' => array('IMG_6273.jpeg', 'IMG_6209.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('delivery', 'packing', 'bundle'),
			'position'   => '52% 36%',
			'accent'     => '80% 16%',
		),
		'delivery' => array(
			'label'      => __('Delivery', 'mad-baits'),
			'intro'      => __('Dispatch timelines, courier expectations and practical shipping notes for every order.', 'mad-baits'),
			'candidates' => array('IMG_6273.jpeg', 'IMG_6209.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('delivery', 'packing', 'bundle'),
			'position'   => '52% 36%',
			'accent'     => '80% 16%',
		),
		'returns-refunds' => array(
			'label'      => __('Returns', 'mad-baits'),
			'intro'      => __('Clear guidance for damaged parcels, return steps and refund handling.', 'mad-baits'),
			'candidates' => array('IMG_6271.jpeg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('returns', 'packing', 'delivery'),
			'position'   => '54% 30%',
			'accent'     => '84% 14%',
		),
		'returns' => array(
			'label'      => __('Returns', 'mad-baits'),
			'intro'      => __('Clear guidance for damaged parcels, return steps and refund handling.', 'mad-baits'),
			'candidates' => array('IMG_6271.jpeg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('returns', 'packing', 'delivery'),
			'position'   => '54% 30%',
			'accent'     => '84% 14%',
		),
		'privacy-policy' => array(
			'label'      => __('Legal', 'mad-baits'),
			'intro'      => __('How we protect your data and respect your privacy when using Mad Baits.', 'mad-baits'),
			'candidates' => array('CUP-LOGOv2-1.svg'),
			'position'   => '50% 50%',
			'accent'     => '22% 18%',
		),
		'privacy' => array(
			'label'      => __('Legal', 'mad-baits'),
			'intro'      => __('How we protect your data and respect your privacy when using Mad Baits.', 'mad-baits'),
			'candidates' => array('CUP-LOGOv2-1.svg'),
			'position'   => '50% 50%',
			'accent'     => '22% 18%',
		),
		'terms-conditions' => array(
			'label'      => __('Legal', 'mad-baits'),
			'intro'      => __('Important order, payment and service terms for using this website.', 'mad-baits'),
			'candidates' => array('CUP-LOGOv2-1.svg'),
			'position'   => '58% 50%',
			'accent'     => '84% 12%',
		),
		'terms' => array(
			'label'      => __('Legal', 'mad-baits'),
			'intro'      => __('Important order, payment and service terms for using this website.', 'mad-baits'),
			'candidates' => array('CUP-LOGOv2-1.svg'),
			'position'   => '58% 50%',
			'accent'     => '84% 12%',
		),
		'cookie-policy' => array(
			'label'      => __('Legal', 'mad-baits'),
			'intro'      => __('Cookie usage details and controls for improving your browsing experience.', 'mad-baits'),
			'candidates' => array('CUP-LOGOv2-1.svg'),
			'position'   => '45% 50%',
			'accent'     => '72% 18%',
		),
		'cookies' => array(
			'label'      => __('Legal', 'mad-baits'),
			'intro'      => __('Cookie usage details and controls for improving your browsing experience.', 'mad-baits'),
			'candidates' => array('CUP-LOGOv2-1.svg'),
			'position'   => '45% 50%',
			'accent'     => '72% 18%',
		),
		'ai-bait-finder' => array(
			'label'      => __('Mad Baits Intelligence', 'mad-baits'),
			'intro'      => __('Tell us your conditions and get practical recommendations based on real Mad Baits stock.', 'mad-baits'),
			'candidates' => array('IMG_6209.jpeg', 'IMG_6270-1.jpeg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('session', 'bundle', 'hookbait', 'boilie'),
			'position'   => '60% 34%',
			'accent'     => '86% 10%',
		),
		'build-my-session' => array(
			'label'      => __('Session Planner', 'mad-baits'),
			'intro'      => __('Build a focused approach around venue conditions, confidence baits and practical add-ons.', 'mad-baits'),
			'candidates' => array('IMG_6209.jpeg', 'IMG_6276.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('bundle-deals', 'bundles', 'boilies', 'hookbaits'),
			'semantic_terms' => array('session', 'bundle', 'boilie'),
			'position'   => '52% 34%',
			'accent'     => '78% 16%',
		),
		'bundle-deals' => array(
			'label'      => __('Premium Session Value', 'mad-baits'),
			'intro'      => __('Stock up with campaign-ready combinations and better-value session packs.', 'mad-baits'),
			'candidates' => array('jerry-rigging-bivvy.png', 'IMG_6277.jpeg', 'IMG_6273.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('bundle-deals', 'bundles'),
			'semantic_terms' => array('bundle', 'deal', 'session pack'),
			'position'   => '58% 34%',
			'accent'     => '82% 12%',
		),
		'boilie-range' => array(
			'label'      => __('Boilie Range', 'mad-baits'),
			'intro'      => __('Premium food bait systems and campaign boilies designed for repeat confidence on pressured waters.', 'mad-baits'),
			'candidates' => array('compulsive-yellow-popups-bank.png', 'compulsive-pink-popups-hand.png', 'boilie-range-hero.jpg', 'boilie-range.jpg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('boilies', 'boilie'),
			'semantic_terms' => array('boilie', 'food bait', 'campaign'),
			'position'   => '56% 34%',
			'accent'     => '84% 12%',
		),
		'hookbaits' => array(
			'label'      => __('Hookbaits', 'mad-baits'),
			'intro'      => __('Pop-ups, wafters and boosted options tuned for clean presentation and quick response.', 'mad-baits'),
			'candidates' => array('hookbaits-hero.png', 'compulsive-triple-hookbaits.png', 'compulsive-pink-popups-hand.png', 'compulsive-yellow-popups-bank.png', 'compulsive-orange-popups-night.png', 'bait-spikez-product.webp', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('hookbaits', 'hookbait', 'pop-ups', 'wafters'),
			'semantic_terms' => array('hookbait', 'pop-up', 'wafter'),
			'position'   => '49% 31%',
			'accent'     => '20% 14%',
		),
		'terminal-tackle' => array(
			'label'      => __('Terminal Tackle', 'mad-baits'),
			'intro'      => __('Reliable rig, hook, line and terminal components built for consistent mechanics session after session.', 'mad-baits'),
			'candidates' => array('terminal-tackle-hero.png', 'ecac2f50a97548228dc72bbf424d39bes1900x490.png', 'jerry-rigging-bivvy.png', 'jerry-wading-rod-setup.png', 'terminal-tackle-hero.jpg', 'terminal-tackle.jpg', 'IMG_6273.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('terminal', 'terminal-tackle', 'tackle'),
			'semantic_terms' => array('terminal', 'rig', 'hook', 'line'),
			'position'   => '56% 39%',
			'accent'     => '84% 16%',
		),
		'compulsive-angler' => array(
			'label'      => __('Compulsive Angler', 'mad-baits'),
			'intro'      => __('A focused campaign selection for anglers who fish hard and demand premium bait consistency.', 'mad-baits'),
			'candidates' => array('compulsive-orange-popups-night.png', 'compulsive-pink-popups-hand.png', 'compulsive-yellow-popups-bank.png', 'compulsive-angler-hero.jpg', 'compulsive-angler.jpg', 'IMG_6209.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('compulsive-anglers', 'compulsive', 'boilies'),
			'semantic_terms' => array('compulsive', 'campaign', 'big fish'),
			'position'   => '54% 34%',
			'accent'     => '82% 11%',
		),
		'clothing' => array(
			'label'      => __('Clothing', 'mad-baits'),
			'intro'      => __('Session-ready apparel and branded layers for comfort on and off the bank.', 'mad-baits'),
			'candidates' => array('clothing-hero.jpg', 'clothing.jpg', 'IMG_0820-scaled.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs' => array('clothing', 'merchandise', 'merch'),
			'semantic_terms' => array('clothing', 'hoodie', 'shirt', 'cap'),
			'position'   => '44% 34%',
			'accent'     => '72% 12%',
		),
		'catch-reports' => array(
			'label'      => __('Catch Reports', 'mad-baits'),
			'intro'      => __('Real catches, real venues and proven bait combinations from the Mad Baits community.', 'mad-baits'),
			'candidates' => array('jerry-wading-rod-setup.png', 'jerry-sunset-cast.png', 'catch-reports-hero.jpg', 'catch-reports.jpg', 'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms' => array('catch', 'angler', 'session', 'boilie'),
			'position'   => '50% 30%',
			'accent'     => '83% 11%',
		),
	);

	$preset = isset($page_map[ $slug ]) ? $page_map[ $slug ] : array(
		'label'      => __('Mad Baits', 'mad-baits'),
		'intro'      => __('Premium bait systems and angling support, built for confident sessions.', 'mad-baits'),
		'candidates' => array('CUP-LOGOv2-1.svg'),
		'position'   => '50% 38%',
		'accent'     => '82% 12%',
	);

	$image_uri = mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => $post_id,
			'candidate_filenames' => isset($preset['candidates']) ? (array) $preset['candidates'] : array(),
			'product_cat_slugs'   => isset($preset['product_cat_slugs']) ? (array) $preset['product_cat_slugs'] : array(),
			'semantic_terms'      => isset($preset['semantic_terms']) ? (array) $preset['semantic_terms'] : array(),
		)
	);

	$css_vars = array(
		'--mb-page-hero-position' => isset($preset['position']) ? (string) $preset['position'] : '50% 38%',
		'--mb-page-hero-accent'   => isset($preset['accent']) ? (string) $preset['accent'] : '82% 12%',
	);

	if ('' !== $image_uri) {
		$css_vars['--mb-page-hero-image'] = "url('" . esc_url_raw($image_uri) . "')";
	}

	return array(
		'slug'      => $slug,
		'title'     => $title,
		'label'     => isset($preset['label']) ? (string) $preset['label'] : __('Mad Baits', 'mad-baits'),
		'intro'     => isset($preset['intro']) ? (string) $preset['intro'] : '',
		'image_uri' => $image_uri,
		'style'     => mad_baits_build_css_var_string($css_vars),
	);
}

/**
 * Render reusable premium page hero section.
 *
 * @param array $args Hero arguments.
 */
function mad_baits_render_page_hero($args = array()) {
	$hero = mad_baits_get_page_hero_data((array) $args);
	$style_attr = '';
	$home_label = _x('Home', 'breadcrumb', 'mad-baits');
	$page_title = isset($hero['title']) ? (string) $hero['title'] : __('Mad Baits', 'mad-baits');
	if (! empty($hero['style']) && is_string($hero['style'])) {
		$style_attr = ' style="' . esc_attr($hero['style']) . '"';
	}
	?>
	<section class="mb-page-hero"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="container container--narrow mb-page-hero__inner">
			<nav class="mb-breadcrumbs mb-hero-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'mad-baits'); ?>">
				<a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($home_label); ?></a>
				<span class="mb-breadcrumbs__sep" aria-hidden="true">/</span>
				<span class="mb-breadcrumbs__current"><?php echo esc_html($page_title); ?></span>
			</nav>
			<p class="mb-page-hero__label"><?php echo esc_html((string) $hero['label']); ?></p>
			<h1 class="mb-page-hero__title"><?php echo esc_html((string) $hero['title']); ?></h1>
			<?php if (! empty($hero['intro']) && is_string($hero['intro'])) : ?>
				<p class="mb-page-hero__intro"><?php echo esc_html((string) $hero['intro']); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Keep Woo breadcrumb semantics but allow custom placement.
 */
function mad_baits_reposition_woo_breadcrumbs() {
	if (! function_exists('is_woocommerce')) {
		return;
	}

	remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
}
add_action('init', 'mad_baits_reposition_woo_breadcrumbs', 30);

/**
 * Render premium Woo breadcrumb trail.
 *
 * @param string $class Optional class hook.
 */
function mad_baits_render_woo_breadcrumbs($class = 'mb-hero-breadcrumbs') {
	if (! function_exists('woocommerce_breadcrumb')) {
		return;
	}

	$breadcrumb_class = sanitize_html_class((string) $class);
	$wrap_before = '<nav class="' . esc_attr(trim('mb-breadcrumbs ' . $breadcrumb_class)) . '" aria-label="' . esc_attr__('Breadcrumb', 'mad-baits') . '">';

	woocommerce_breadcrumb(
		array(
			'delimiter'   => '<span class="mb-breadcrumbs__sep" aria-hidden="true">/</span>',
			'wrap_before' => $wrap_before,
			'wrap_after'  => '</nav>',
			'before'      => '<span class="mb-breadcrumbs__crumb">',
			'after'       => '</span>',
			'home'        => _x('Home', 'breadcrumb', 'mad-baits'),
		)
	);
}

/**
 * Get structured fallback items for desktop/mobile primary navigation.
 *
 * @return array
 */
function mad_baits_get_primary_nav_items() {
	$shop_url   = mad_baits_get_shop_url();

	$deals_url = function_exists('mad_baits_get_bundle_deals_url')
		? mad_baits_get_bundle_deals_url()
		: mad_baits_get_product_cat_link(array('bundles-deals', 'bundle-deals', 'bundles'), $shop_url);

	$range_children = array(
		array('label' => 'BBB', 'url' => mad_baits_get_range_filter_url('bbb', $shop_url)),
		array('label' => 'ASBO', 'url' => mad_baits_get_range_filter_url('asbo', $shop_url)),
		array('label' => 'Wicked Whites', 'url' => mad_baits_get_range_filter_url('wicked-white', $shop_url)),
		array('label' => 'P-Fish', 'url' => mad_baits_get_range_filter_url('p-fish-2', $shop_url)),
		array('label' => 'Pandemic', 'url' => mad_baits_get_range_filter_url('pandemic', $shop_url)),
		array('label' => 'Nutz Plus', 'url' => mad_baits_get_range_filter_url('nutz-plus', $shop_url)),
		array('label' => 'Nutz Banana', 'url' => mad_baits_get_range_filter_url('nutz-banana', $shop_url)),
		array('label' => 'Compulsive Angler', 'url' => mad_baits_get_range_filter_url('compulsive-angler', $shop_url)),
	);

	$pellets_url = mad_baits_get_product_cat_link(array('pellets', 'pellet'), $shop_url);
	$groundbait_bag_mix_url = mad_baits_get_product_cat_link(array('groundbait-bag-mix', 'groundbait', 'bag-mix'), $shop_url);
	$liquids_url = mad_baits_get_product_cat_link(array('liquids', 'liquid-foods', 'other-liquid-foods', 'liquid'), $shop_url);
	$sprays_url  = mad_baits_get_product_cat_link(array('sprays', 'spray'), $shop_url);
	$paste_url   = mad_baits_get_product_cat_link(array('paste'), $shop_url);
	$tackle_url  = mad_baits_get_product_cat_link(array('tackle', 'terminal', 'terminal-tackle'), $shop_url);
	$accessories_url = mad_baits_get_product_cat_link(array('accessories', 'accessories-2', 'accesories'), $shop_url);
	$clothing_url    = mad_baits_get_product_cat_link(array('clothing', 'merchandise', 'merch'), $shop_url);
	$rock_salt_url   = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('rock-salt', 'rock-salt') : home_url('/rock-salt/');

	return array(
		array('label' => __('Home', 'mad-baits'), 'url' => home_url('/')),
		array('label' => __('Bundles & Deals', 'mad-baits'), 'url' => $deals_url),
		array(
			'label'              => __('Bait Ranges', 'mad-baits'),
			'url'                => $shop_url,
			'children'           => $range_children,
			'featured_dropdown'  => true,
		),
		array(
			'label'    => __('Pellets & Bag Mix', 'mad-baits'),
			'url'      => $pellets_url,
			'children' => array(
				array('label' => __('Pellets', 'mad-baits'), 'url' => $pellets_url),
				array('label' => __('Groundbait & Bag Mix', 'mad-baits'), 'url' => $groundbait_bag_mix_url),
			),
		),
		array(
			'label'    => __('Liquids & Additives', 'mad-baits'),
			'url'      => $liquids_url,
			'children' => array(
				array('label' => __('Liquids', 'mad-baits'), 'url' => $liquids_url),
				array('label' => __('Sprays', 'mad-baits'), 'url' => $sprays_url),
				array('label' => __('Paste', 'mad-baits'), 'url' => $paste_url),
				array('label' => __('Rock Salt', 'mad-baits'), 'url' => $rock_salt_url),
			),
		),
		array(
			'label'    => __('Tackle & Essentials', 'mad-baits'),
			'url'      => $tackle_url,
			'children' => array(
				array('label' => __('Terminal Tackle', 'mad-baits'), 'url' => $tackle_url),
				array('label' => __('Accessories', 'mad-baits'), 'url' => $accessories_url),
				array('label' => __('Clothing', 'mad-baits'), 'url' => $clothing_url),
			),
		),
	);
}

/**
 * Build primary/mobile nav markup from structured menu items.
 *
 * @param array  $items      Menu rows from mad_baits_get_primary_nav_items().
 * @param string $menu_class Root <ul> class.
 * @return string
 */
function mad_baits_build_nav_menu_html(array $items, $menu_class) {
	$html = '<ul class="' . esc_attr((string) $menu_class) . '">';

	foreach ($items as $item) {
		$children = isset($item['children']) && is_array($item['children']) ? $item['children'] : array();
		$classes  = empty($children) ? array() : array('menu-item-has-children');
		if (! empty($item['featured_dropdown'])) {
			$classes[] = 'menu-item-featured-dropdown';
		}
		if (! empty($item['classes']) && is_array($item['classes'])) {
			$classes = array_merge($classes, array_map('sanitize_html_class', $item['classes']));
		}

		$html .= '<li class="' . esc_attr(implode(' ', $classes)) . '">';
		$link_label = esc_html((string) $item['label']);
		if (! empty($item['badge'])) {
			$link_label .= ' <span class="mbta-nav-item__badge">' . esc_html((string) $item['badge']) . '</span>';
		}
		$html .= '<a href="' . esc_url((string) $item['url']) . '">' . $link_label . '</a>';

		if (! empty($children)) {
			$html .= '<ul class="sub-menu">';
			foreach ($children as $child) {
				$html .= '<li><a href="' . esc_url((string) $child['url']) . '">' . esc_html((string) $child['label']) . '</a></li>';
			}
			$html .= '</ul>';
		}

		$html .= '</li>';
	}

	$html .= '</ul>';

	return $html;
}

/**
 * Whether header nav should use theme-enforced structure (not WP admin menu tree).
 *
 * @return bool
 */
function mad_baits_use_enforced_primary_nav() {
	return (bool) apply_filters('mad_baits_use_enforced_primary_nav', true);
}

/**
 * Output enforced desktop primary navigation.
 *
 * @return void
 */
function mad_baits_render_enforced_primary_nav() {
	echo mad_baits_build_nav_menu_html(mad_baits_get_primary_nav_items(), 'primary-nav__menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Output enforced mobile navigation.
 *
 * @return void
 */
function mad_baits_render_enforced_mobile_nav() {
	echo mad_baits_build_nav_menu_html(mad_baits_get_primary_nav_items(), 'mobile-nav__menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Render a safe fallback desktop primary menu.
 *
 * @param array $args Menu arguments.
 * @return string
 */
function mad_baits_primary_menu_fallback($args) {
	unset($args);

	return mad_baits_build_nav_menu_html(mad_baits_get_primary_nav_items(), 'primary-nav__menu');
}

/**
 * Render a safe fallback mobile menu.
 *
 * @param array $args Menu arguments.
 * @return string
 */
function mad_baits_mobile_menu_fallback($args) {
	unset($args);

	return mad_baits_build_nav_menu_html(mad_baits_get_primary_nav_items(), 'mobile-nav__menu');
}

/**
 * Resolve a page URL by path with fallback.
 *
 * @param string $path Relative page path.
 * @param string $fallback_path Fallback relative path.
 * @return string
 */
function mad_baits_get_page_url($path, $fallback_path = '/') {
	$page = get_page_by_path(sanitize_title((string) $path));
	if ($page instanceof WP_Post) {
		$permalink = get_permalink($page);
		if (is_string($permalink) && '' !== $permalink) {
			return $permalink;
		}
	}

	return home_url('/' . trim((string) $fallback_path, '/') . '/');
}

/**
 * Resolve AI bait finder page URL, prioritising assigned template pages.
 *
 * @return string
 */
function mad_baits_get_ai_bait_finder_url() {
	$template_page = get_pages(
		array(
			'post_status' => 'publish',
			'number'      => 1,
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'template-ai-bait-finder.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if (! empty($template_page) && isset($template_page[0]) && $template_page[0] instanceof WP_Post) {
		$template_link = get_permalink($template_page[0]);
		if (is_string($template_link) && '' !== $template_link) {
			return $template_link;
		}
	}

	return mad_baits_get_page_url('ai-bait-finder', 'ai-bait-finder');
}

/**
 * Resolve bundle deals page URL, prioritising assigned template pages.
 *
 * @return string
 */
function mad_baits_get_bundle_deals_url() {
	$shop_url = mad_baits_get_shop_url();
	$bundle_cat_url = mad_baits_get_product_cat_link(
		array('bundles-deals', 'bundle-deals', 'bundles', 'deals', 'offers', 'bundle-offers'),
		$shop_url
	);
	if (is_string($bundle_cat_url) && '' !== $bundle_cat_url && $bundle_cat_url !== $shop_url) {
		return $bundle_cat_url;
	}

	$template_page = get_pages(
		array(
			'post_status' => 'publish',
			'number'      => 1,
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'template-bundle-deals.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if (! empty($template_page) && isset($template_page[0]) && $template_page[0] instanceof WP_Post) {
		$template_link = get_permalink($template_page[0]);
		if (is_string($template_link) && '' !== $template_link) {
			return $template_link;
		}
	}

	$bundle_page = mad_baits_get_page_url('bundles-deals', 'bundles-deals');
	if (is_string($bundle_page) && '' !== $bundle_page) {
		return $bundle_page;
	}

	$bundle_page = mad_baits_get_page_url('bundle-deals', 'bundle-deals');
	if (is_string($bundle_page) && '' !== $bundle_page) {
		return $bundle_page;
	}

	return $shop_url;
}

/**
 * Resolve a published page URL by assigned template file.
 *
 * @param string $template_file Page template filename.
 * @param string $fallback_path Relative fallback path.
 * @return string
 */
function mad_baits_get_template_page_url($template_file, $fallback_path = '/') {
	$template_file = trim((string) $template_file);
	if ('' !== $template_file) {
		$template_page = get_pages(
			array(
				'post_status' => 'publish',
				'number'      => 1,
				'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $template_file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if (! empty($template_page) && isset($template_page[0]) && $template_page[0] instanceof WP_Post) {
			$template_link = get_permalink($template_page[0]);
			if (is_string($template_link) && '' !== $template_link) {
				return $template_link;
			}
		}
	}

	return mad_baits_get_page_url((string) $fallback_path, (string) $fallback_path);
}

/**
 * Resolve Boilie Range page URL.
 *
 * @return string
 */
function mad_baits_get_boilie_range_url() {
	return mad_baits_get_template_page_url('template-boilie-range.php', 'boilie-range');
}

/**
 * Resolve Hookbaits page URL.
 *
 * @return string
 */
function mad_baits_get_hookbaits_page_url() {
	return mad_baits_get_template_page_url('template-hookbaits.php', 'hookbaits');
}

/**
 * Resolve Compulsive Angler page URL.
 *
 * @return string
 */
function mad_baits_get_compulsive_angler_url() {
	return mad_baits_get_template_page_url('template-compulsive-angler.php', 'compulsive-angler');
}

/**
 * Resolve Terminal Tackle page URL.
 *
 * @return string
 */
function mad_baits_get_terminal_tackle_url() {
	return mad_baits_get_template_page_url('template-terminal-tackle.php', 'terminal-tackle');
}

/**
 * Resolve Clothing page URL.
 *
 * @return string
 */
function mad_baits_get_clothing_page_url() {
	return mad_baits_get_template_page_url('template-clothing.php', 'clothing');
}

/**
 * Return branded bait-range content profiles.
 *
 * @return array<string, array<string, mixed>>
 */
function mad_baits_get_range_brand_profiles() {
	return array(
		'asbo' => array(
			'aliases'    => array('asbo'),
			'theme'      => 'asbo',
			'label'      => __('Signature Fishmeal System', 'mad-baits'),
			'headline'   => __('ASBO', 'mad-baits'),
			'intro'      => __('A high-quality digestible fishmeal bait engineered for anglers wanting serious long-term confidence on pressured waters.', 'mad-baits'),
			'story'      => array(
				__('ASBO combines premium krill fishmeal, CPSP90 and Norse LT94 fishmeals with our in-house spice package of chilli, garlic, fenugreek and asafoetida for a rich aggressive food profile.', 'mad-baits'),
				__('Fermented shrimp concentrate in liquid and paste form, liver powders and liquids, CSL, Minamino, CLO birdfood and our amino blend complete a devastating food signal carp respond to repeatedly.', 'mad-baits'),
			),
			'pull_quote' => __('"A rich, spicy fishmeal profile built to keep fish searching your area."', 'mad-baits'),
			'hero'       => array(
				'position'       => '58% 36%',
				'accent'         => '86% 14%',
				'hero_slugs'     => array('asbo'),
				'semantic_terms' => array('asbo', 'spice', 'fishmeal', 'campaign'),
				'candidates'     => array('IMG_6273-scene.jpg', 'IMG_6273.jpeg', 'HAMMONDAPRIL_22_031-scene.jpg', 'HAMMONDAPRIL_22_031.jpg'),
			),
			'ingredients' => array(
				__('Krill fishmeal', 'mad-baits'),
				__('CPSP90 and Norse LT94 fishmeals', 'mad-baits'),
				__('In-house chilli-garlic-fenugreek-asafoetida spice package', 'mad-baits'),
				__('Fermented shrimp concentrate, liver package, CSL, Minamino and CLO', 'mad-baits'),
			),
			'best_for' => array(
				__('Long-term campaign baiting', 'mad-baits'),
				__('Pressured waters needing stronger food pull', 'mad-baits'),
				__('Session anglers who want a bold fishmeal edge', 'mad-baits'),
			),
			'pair_with' => __('Match with high-attract hookbaits and liquid food to keep the profile tight from feed to rig.', 'mad-baits'),
			'availability' => __('Freezer and shelf-life options vary by listing.', 'mad-baits'),
		),
		'nutz-plus' => array(
			'aliases'    => array('nutz-plus', 'nutzplus'),
			'theme'      => 'nutz-plus',
			'label'      => __('Signature Nut Range', 'mad-baits'),
			'headline'   => __('Nutz Plus', 'mad-baits'),
			'intro'      => __('An all-season classic built around digestibility, creamy attraction and long-term confidence.', 'mad-baits'),
			'story'      => array(
				__('Nutz Plus blends premium milk proteins with crushed tiger nuts, peanuts and Brazil nuts to produce a rich smooth food profile carp actively return to.', 'mad-baits'),
				__('Coconut, coconut milk, hazelnut oil, hemp oil and GLM add extra depth, while later Wicked White integration gives the range its distinctive creamy backnote and extra pulling power.', 'mad-baits'),
			),
			'pull_quote' => __('"If you need one year-round confidence bait, Nutz Plus remains a proven front-runner."', 'mad-baits'),
			'hero'       => array(
				'position'       => '52% 36%',
				'accent'         => '80% 12%',
				'hero_slugs'     => array('nutz-plus', 'nutzplus'),
				'semantic_terms' => array('nutz plus', 'nut', 'creamy', 'winter'),
				'candidates'     => array('IMG_0820-scaled-scene.jpg', 'IMG_0820-scaled.jpeg', 'IMG_6277-scene.jpg', 'IMG_6277.jpeg'),
			),
			'ingredients' => array(
				__('Milk proteins and nut meals', 'mad-baits'),
				__('Crushed tiger nuts, peanuts and Brazil nuts', 'mad-baits'),
				__('Coconut package, hazelnut and hemp oils', 'mad-baits'),
				__('GLM and refined creamy attractor notes', 'mad-baits'),
			),
			'best_for' => array(
				__('All-season campaign work', 'mad-baits'),
				__('Cold-water confidence sessions', 'mad-baits'),
				__('Anglers preferring a smoother food profile', 'mad-baits'),
			),
			'pair_with' => __('Pair with matching creamy hookbaits for a low-risk confidence-led presentation.', 'mad-baits'),
			'availability' => __('Freezer and shelf-life options vary by listing.', 'mad-baits'),
		),
		'nutz-banana' => array(
			'aliases'    => array('nutz-banana', 'nutz-bananas'),
			'theme'      => 'nutz-banana',
			'label'      => __('Classic Remastered', 'mad-baits'),
			'headline'   => __('Nutz Banana', 'mad-baits'),
			'intro'      => __('One of our old-school favourites rebuilt for modern campaign angling.', 'mad-baits'),
			'story'      => array(
				__('Using the proven Nutz Plus base mix and liquid package, Nutz Banana layers in a rich banana oil and powder system for a smooth instantly recognisable food signal.', 'mad-baits'),
				__('Finished in iconic yellow, this range blends nostalgia with dependable modern bait performance.', 'mad-baits'),
			),
			'pull_quote' => __('"Classic banana confidence, now tuned for today\'s pressured waters."', 'mad-baits'),
			'hero'       => array(
				'position'       => '54% 34%',
				'accent'         => '82% 14%',
				'hero_slugs'     => array('nutz-banana', 'nutz-bananas'),
				'semantic_terms' => array('banana', 'nutz banana', 'classic', 'boilie'),
				'candidates'     => array('IMG_6271-scene.jpg', 'IMG_6271.jpeg', 'session-pack-scene.jpg', 'session-pack.png'),
			),
			'ingredients' => array(
				__('Nutz Plus base mix and liquids', 'mad-baits'),
				__('Banana oil and banana powder blend', 'mad-baits'),
				__('Balanced sweet creamy food profile', 'mad-baits'),
			),
			'best_for' => array(
				__('Nostalgic flavour confidence fishing', 'mad-baits'),
				__('Short sessions needing instant recognition', 'mad-baits'),
				__('Anglers who prefer classic sweet notes', 'mad-baits'),
			),
			'pair_with' => __('Pair with bright yellow hookbait tones for a tight visual and flavour identity.', 'mad-baits'),
			'availability' => __('Available in shelf-life only.', 'mad-baits'),
		),
		'wicked-whites' => array(
			'aliases'    => array('wicked-whites', 'wicked-white', 'wicked-whites-range'),
			'theme'      => 'wicked-whites',
			'label'      => __('Cream Attraction System', 'mad-baits'),
			'headline'   => __('Wicked White', 'mad-baits'),
			'intro'      => __('Bright in appearance, rich in attraction and devastating all year round.', 'mad-baits'),
			'story'      => array(
				__('Wicked White combines premium milk proteins, birdfoods and original Sluis CLO with GLM, betaine, maple extract and buttercream notes for a deep creamy signal.', 'mad-baits'),
				__('It performs confidently alone or alongside core food bait systems, with a long track record across consultants, team anglers and customers.', 'mad-baits'),
			),
			'pull_quote' => __('"A genuine all-season edge when pressured fish need a cleaner creamy trigger."', 'mad-baits'),
			'hero'       => array(
				'position'       => '50% 32%',
				'accent'         => '84% 12%',
				'hero_slugs'     => array('wicked-whites', 'wicked-white', 'wicked-whites-range'),
				'semantic_terms' => array('wicked whites', 'cream', 'all season'),
				'candidates'     => array('IMG_6276-scene.jpg', 'IMG_6276.jpeg', 'IMG_6271-scene.jpg', 'IMG_6271.jpeg'),
			),
			'ingredients' => array(
				__('Milk proteins and premium birdfoods', 'mad-baits'),
				__('Original Sluis CLO', 'mad-baits'),
				__('GLM extract, betaine and maple notes', 'mad-baits'),
				__('Buttercream and refined creamy attractors', 'mad-baits'),
			),
			'best_for' => array(
				__('All-season campaign use', 'mad-baits'),
				__('Waters where creamy profiles stand out', 'mad-baits'),
				__('Pairing with darker food-bait systems', 'mad-baits'),
			),
			'pair_with' => __('Ideal for pairing with food-bait ranges when you want a cleaner hook-level signal.', 'mad-baits'),
			'availability' => __('Freezer and shelf-life options vary by listing.', 'mad-baits'),
		),
		'p-fish' => array(
			'aliases'    => array('p-fish-2', 'p-fish', 'pfish', 'p-fish-range'),
			'theme'      => 'p-fish',
			'label'      => __('Technical Blend Range', 'mad-baits'),
			'headline'   => __('P-Fish', 'mad-baits'),
			'intro'      => __('A unique all-round concept balancing fishmeal, nuts, milk proteins, CLO and digestibility in one modern food bait.', 'mad-baits'),
			'story'      => array(
				__('P-Fish is deliberately built around equal balance: 20 percent fishmeal, 20 percent nut content, 20 percent milk proteins, 20 percent CLO birdfoods and 20 percent binders, attractors and appetite stimulators.', 'mad-baits'),
				__('GLM powder, betaine, liver powder and yeast combine with a liquid package of liver liquid, fish hydro, calanus and tiger nut extract for real versatility.', 'mad-baits'),
			),
			'pull_quote' => __('"Designed to perform equally well on instant hits and long campaign timelines."', 'mad-baits'),
			'hero'       => array(
				'position'       => '56% 34%',
				'accent'         => '84% 14%',
				'hero_slugs'     => array('p-fish-2', 'p-fish', 'pfish', 'p-fish-range'),
				'semantic_terms' => array('p-fish-2', 'p-fish', 'fishmeal', 'technical', 'blend'),
				'candidates'     => array('IMG_6209-scene.jpg', 'IMG_6209.jpeg', 'IMG_6270-1-scene.jpg', 'IMG_6270-1.jpeg'),
			),
			'ingredients' => array(
				__('20% fishmeal / 20% nut content / 20% milk proteins', 'mad-baits'),
				__('20% CLO birdfoods and 20% binders-attractors package', 'mad-baits'),
				__('GLM, betaine, liver powder and yeast', 'mad-baits'),
				__('Liver liquid, fish hydro, calanus and tiger nut extract', 'mad-baits'),
			),
			'best_for' => array(
				__('Versatile all-round angling', 'mad-baits'),
				__('Switching between instant and campaign styles', 'mad-baits'),
				__('Anglers who want one balanced food system', 'mad-baits'),
			),
			'pair_with' => __('Pair with confidence hookbaits to keep the same balanced food identity on the rig.', 'mad-baits'),
			'availability' => __('Freezer and shelf-life options vary by listing.', 'mad-baits'),
		),
		'pandemic' => array(
			'aliases'    => array('pandemic'),
			'theme'      => 'pandemic',
			'label'      => __('Krill-Spice Aggression', 'mad-baits'),
			'headline'   => __('Pandemic', 'mad-baits'),
			'intro'      => __('A powerful krill-based food bait with a serious spicy twist for anglers wanting bold confidence-led fishmeal pull.', 'mad-baits'),
			'story'      => array(
				__('Pandemic combines premium krill meal with two additional fishmeals, liver powder and selected birdfoods before introducing our in-house spice package.', 'mad-baits'),
				__('Chilli, garlic, fenugreek, paprika and additional undisclosed elements produce a rich aggressive food signal carp and barbel respond to hard.', 'mad-baits'),
			),
			'pull_quote' => __('"Built for anglers who want a stronger response profile with genuine long-term pull."', 'mad-baits'),
			'hero'       => array(
				'position'       => '58% 36%',
				'accent'         => '84% 16%',
				'hero_slugs'     => array('pandemic'),
				'semantic_terms' => array('pandemic', 'krill', 'spice', 'fishmeal'),
				'candidates'     => array('IMG_6270-1-scene.jpg', 'IMG_6270-1.jpeg', 'IMG_6273-scene.jpg', 'IMG_6273.jpeg'),
			),
			'ingredients' => array(
				__('Premium krill meal with additional fishmeals', 'mad-baits'),
				__('Liver powder and selected birdfoods', 'mad-baits'),
				__('Chilli, garlic, fenugreek and paprika spice package', 'mad-baits'),
			),
			'best_for' => array(
				__('Bold fishmeal campaigning', 'mad-baits'),
				__('Waters where stronger signals excel', 'mad-baits'),
				__('Carp and barbel targeting sessions', 'mad-baits'),
			),
			'pair_with' => __('Pair with spice-led hookbait options to keep the entire setup aggressive and coherent.', 'mad-baits'),
			'availability' => __('Freezer and shelf-life options vary by listing.', 'mad-baits'),
		),
		'bbb' => array(
			'aliases'    => array('bbb'),
			'theme'      => 'bbb',
			'label'      => __('Barnes Black Barrels', 'mad-baits'),
			'headline'   => __('BBB', 'mad-baits'),
			'intro'      => __('Originally developed with Julian Barnes, BBB quickly built a reputation for exceptional barbel and carp performance.', 'mad-baits'),
			'story'      => array(
				__('BBB uses a dark fishmeal base with fish hydro liquid, liver liquid and powder, squid hydro, shrimp liquid and blue cheese powder for a deep savoury profile.', 'mad-baits'),
				__('A supporting spice blend and garlic oil finish the mix, delivering a meaty scent profile and iconic jet-black barrel presentation.', 'mad-baits'),
			),
			'pull_quote' => __('"A gritty dark profile that keeps producing when subtle confidence matters most."', 'mad-baits'),
			'hero'       => array(
				'position'       => '54% 38%',
				'accent'         => '78% 12%',
				'hero_slugs'     => array('bbb'),
				'semantic_terms' => array('bbb', 'barbel', 'dark', 'campaign'),
				'candidates'     => array('Jerry_Hammond_March_22_Englefield_Lagoon_014-scene.jpg', 'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg', 'IMG_2905-scene.jpg', 'IMG_2905.jpeg'),
			),
			'ingredients' => array(
				__('Dark fishmeal base with fish hydro and liver package', 'mad-baits'),
				__('Squid hydro, shrimp liquid and blue cheese powder', 'mad-baits'),
				__('Spice blend and garlic oil finish', 'mad-baits'),
			),
			'best_for' => array(
				__('Serious barbel and carp campaigns', 'mad-baits'),
				__('Low-light and pressured feeding windows', 'mad-baits'),
				__('Anglers preferring savoury dark-food profiles', 'mad-baits'),
			),
			'pair_with' => __('Pair with robust savoury hookbaits to preserve BBB profile integrity at hook level.', 'mad-baits'),
			'availability' => __('Available in Midi Barrel 15x18mm and Large Barrel 18x22mm, freezer and shelf-life.', 'mad-baits'),
		),
		'compulsive' => array(
			'aliases'    => array('compulsive', 'compulsive-anglers', 'compulsive-angler'),
			'theme'      => 'compulsive',
			'label'      => __('Elite Hookbait Collection', 'mad-baits'),
			'headline'   => __('Compulsive', 'mad-baits'),
			'intro'      => __('Developed from the ground up with Jerry Hammond, Mark and Nick — three years of relentless testing until every bait earned their complete confidence.', 'mad-baits'),
			'story'      => array(
				__('Jerry has had a hand in every flavour and colour, working closely alongside Mark and Nick to develop each bait from the ground up. After three years of relentless testing, tweaking and refining, the final range was created — products all three were completely confident in putting their names behind.', 'mad-baits'),
				__('Over the years, these baits have gone on to achieve near-legendary status, accounting for some of the biggest and most sought-after carp in the country.', 'mad-baits'),
			),
			'pull_quote' => __('"Built for anglers who demand total confidence in every hookbait they cast."', 'mad-baits'),
			'hero'       => array(
				'position'       => '52% 34%',
				'accent'         => '84% 11%',
				'hero_slugs'     => array('compulsive', 'compulsive-anglers', 'compulsive-angler'),
				'semantic_terms' => array('compulsive', 'elite', 'hookbait', 'campaign'),
				'candidates'     => array('IMG_6209-scene.jpg', 'IMG_6209.jpeg', 'Jerry_Hammond_March_22_Englefield_Lagoon_016-scene.jpg', 'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg'),
			),
			'ingredients' => array(
				__('Long-term tested hookbait flavour systems', 'mad-baits'),
				__('Purpose-built colour and attraction identities', 'mad-baits'),
				__('Confidence-led presentation for big-fish campaigns', 'mad-baits'),
			),
			'best_for' => array(
				__('Big-fish targeting and campaign angling', 'mad-baits'),
				__('Hook-level confidence in pressured situations', 'mad-baits'),
				__('Anglers wanting premium hookbait identity', 'mad-baits'),
			),
			'pair_with' => __('Pair with matching food-bait profiles to keep your rig signal aligned with feed introduction.', 'mad-baits'),
			'availability' => __('Availability and sizes vary by current Compulsive listing.', 'mad-baits'),
		),
	);
}

/**
 * Resolve a branded range profile from a product category slug.
 *
 * @param string $slug Product category slug.
 * @return array<string, mixed>
 */
function mad_baits_get_range_brand_profile($slug) {
	$slug = sanitize_title((string) $slug);
	if ('' === $slug) {
		return array();
	}

	foreach (mad_baits_get_range_brand_profiles() as $profile) {
		$aliases = isset($profile['aliases']) ? (array) $profile['aliases'] : array();
		$aliases = array_map('sanitize_title', $aliases);
		if (in_array($slug, $aliases, true)) {
			return is_array($profile) ? $profile : array();
		}
	}

	return array();
}

/**
 * Render premium branded range section for category pages.
 *
 * @param array $profile Range profile.
 * @param array $args Render arguments.
 */
function mad_baits_render_range_brand_section($profile, $args = array()) {
	if (! is_array($profile) || empty($profile)) {
		return;
	}

	$args = wp_parse_args(
		(array) $args,
		array(
			'layout'        => 'full',
			'term'          => null,
			'shop_url'      => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
			'term_link'     => '',
			'boilie_range_url' => function_exists('mad_baits_get_boilie_range_url') ? mad_baits_get_boilie_range_url() : '',
			'hookbaits_url' => function_exists('mad_baits_get_hookbaits_page_url') ? mad_baits_get_hookbaits_page_url() : home_url('/hookbaits/'),
			'ai_url'        => function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/'),
			'build_url'     => function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('build-my-session', 'build-my-session') : home_url('/build-my-session/'),
			'compact'       => false,
		)
	);

	$compact = ! empty($args['compact']);
	$layout  = isset($args['layout']) ? sanitize_key((string) $args['layout']) : 'full';

	$headline = isset($profile['headline']) ? (string) $profile['headline'] : __('Range', 'mad-baits');
	$label    = isset($profile['label']) ? (string) $profile['label'] : __('Mad Baits Signature', 'mad-baits');
	$intro    = isset($profile['intro']) ? (string) $profile['intro'] : '';
	$theme    = isset($profile['theme']) ? sanitize_html_class((string) $profile['theme']) : 'range';
	$story      = isset($profile['story']) && is_array($profile['story']) ? $profile['story'] : array();
	$hero       = isset($profile['hero']) && is_array($profile['hero']) ? $profile['hero'] : array();
	$ingredients = isset($profile['ingredients']) && is_array($profile['ingredients']) ? $profile['ingredients'] : array();
	$best_for    = isset($profile['best_for']) && is_array($profile['best_for']) ? $profile['best_for'] : array();
	$pull_quote  = isset($profile['pull_quote']) ? (string) $profile['pull_quote'] : '';
	$pair_with   = isset($profile['pair_with']) ? (string) $profile['pair_with'] : '';
	$availability = isset($profile['availability']) ? (string) $profile['availability'] : '';
	$term_link = is_string($args['term_link']) && '' !== $args['term_link'] ? (string) $args['term_link'] : (string) $args['shop_url'];
	$boilie_range_url = isset($args['boilie_range_url']) && is_string($args['boilie_range_url']) && '' !== $args['boilie_range_url']
		? (string) $args['boilie_range_url']
		: $term_link;
	$whats_in_water_url = function_exists('mad_baits_get_page_url')
		? mad_baits_get_page_url('whats-in-the-water', 'whats-in-the-water')
		: home_url('/whats-in-the-water/');
	$term      = ($args['term'] instanceof WP_Term) ? $args['term'] : null;

	$scene_image = function_exists('mad_baits_resolve_context_hero_image')
		? mad_baits_resolve_context_hero_image(
			array(
				'term_id'             => $term instanceof WP_Term ? (int) $term->term_id : 0,
				'term_taxonomy'       => 'product_cat',
				'candidate_filenames' => isset($hero['candidates']) ? (array) $hero['candidates'] : array(),
				'product_cat_slugs'   => isset($hero['hero_slugs']) ? (array) $hero['hero_slugs'] : array(),
				'semantic_terms'      => isset($hero['semantic_terms']) ? (array) $hero['semantic_terms'] : array(),
			)
		)
		: '';
	$style_attr = '';
	if (is_string($scene_image) && '' !== $scene_image) {
		$style_attr = ' style="' . esc_attr("--mb-range-scene-image: url('" . esc_url_raw($scene_image) . "');") . '"';
	}
	?>
	<section class="section section--contrast mb-range-brand mb-range-brand--<?php echo esc_attr($theme); ?><?php echo $compact ? ' mb-range-brand--compact' : ''; ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="container">
			<div class="mb-range-brand__intro">
				<p class="section__kicker"><?php echo esc_html($label); ?></p>
				<h2><?php echo esc_html($headline); ?></h2>
				<?php if ('' !== $intro) : ?>
					<p><?php echo esc_html($intro); ?></p>
				<?php endif; ?>
			</div>

			<div class="mb-range-brand__story-grid">
				<div class="mb-range-brand__story">
					<?php foreach ($story as $story_line) : ?>
						<?php if (is_string($story_line) && '' !== $story_line) : ?>
							<p><?php echo esc_html($story_line); ?></p>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<aside class="mb-range-brand__quote-card">
					<p class="mb-range-brand__quote-label"><?php esc_html_e('Why anglers use it', 'mad-baits'); ?></p>
					<?php if ('' !== $pull_quote) : ?>
						<blockquote><?php echo esc_html($pull_quote); ?></blockquote>
					<?php endif; ?>
					<?php if ('' !== $availability) : ?>
						<p class="mb-range-brand__availability"><?php echo esc_html($availability); ?></p>
					<?php endif; ?>
				</aside>
			</div>

			<?php if ('story' === $layout) : ?>
				<?php if ('compulsive' === $theme) : ?>
					<div class="mb-range-brand__actions">
						<a class="mad-button" href="<?php echo esc_url((string) $args['shop_url']); ?>"><?php esc_html_e('Shop Compulsive', 'mad-baits'); ?></a>
						<a class="mad-button mad-button--ghost" href="<?php echo esc_url((string) $args['hookbaits_url']); ?>"><?php esc_html_e('Browse Hookbaits', 'mad-baits'); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return;
	endif;
	?>

			<?php
			$overview_sections = array(
				array(
					'key'     => 'ingredients',
					'title'   => __('Key Ingredients', 'mad-baits'),
					'type'    => 'list',
					'content' => array_values(array_filter(array_map('strval', $ingredients))),
				),
				array(
					'key'     => 'built-for',
					'title'   => __('Built For', 'mad-baits'),
					'type'    => 'text',
					'content' => __('Serious carp anglers who demand food-value consistency and clean hookbait pairing.', 'mad-baits'),
				),
				array(
					'key'     => 'best-in',
					'title'   => __('Best In', 'mad-baits'),
					'type'    => 'list',
					'content' => array_values(array_filter(array_map('strval', $best_for))),
				),
				array(
					'key'     => 'session-confidence',
					'title'   => __('Session Confidence', 'mad-baits'),
					'type'    => 'text',
					'content' => __('Built to hold confidence from first introduction through repeat campaign feeding windows.', 'mad-baits'),
				),
				array(
					'key'     => 'session-style',
					'title'   => __('Session Style', 'mad-baits'),
					'type'    => 'text',
					'content' => __('Use as a complete baiting system with matching hookbaits and attraction support.', 'mad-baits'),
				),
				array(
					'key'     => 'available-sizes',
					'title'   => __('Available Sizes', 'mad-baits'),
					'type'    => 'text',
					'content' => __('Choose practical options to suit short-session prep, campaign feeding and stock-up plans.', 'mad-baits'),
				),
				array(
					'key'     => 'availability',
					'title'   => __('Freezer / Shelf Life', 'mad-baits'),
					'type'    => 'text',
					'content' => '' !== $availability ? $availability : __('Range availability varies by current listing.', 'mad-baits'),
				),
				array(
					'key'     => 'pair-with',
					'title'   => __('Pair With', 'mad-baits'),
					'type'    => 'text',
					'content' => '' !== $pair_with ? $pair_with : __('Pair with confidence hookbaits and matching liquids.', 'mad-baits'),
				),
			);
			$overview_sections = array_values(
				array_filter(
					$overview_sections,
					static function ($section) {
						$type = isset($section['type']) ? (string) $section['type'] : 'text';
						if ('list' === $type) {
							return ! empty($section['content']) && is_array($section['content']);
						}

						$content = isset($section['content']) ? trim((string) $section['content']) : '';

						return '' !== $content;
					}
				)
			);
			?>

			<section class="mb-range-brand__overview" data-range-overview>
				<header class="mb-range-brand__overview-head">
					<h3 class="mb-range-brand__overview-title"><?php esc_html_e('Range Overview', 'mad-baits'); ?></h3>
				</header>
				<div class="mb-range-brand__overview-list">
					<?php foreach ($overview_sections as $overview_index => $overview_section) : ?>
						<?php
						$section_key   = isset($overview_section['key']) ? sanitize_key((string) $overview_section['key']) : 'section';
						$section_title = isset($overview_section['title']) ? (string) $overview_section['title'] : '';
						$section_type  = isset($overview_section['type']) ? (string) $overview_section['type'] : 'text';
						$panel_id      = 'mb-range-overview-' . sanitize_html_class($theme) . '-' . absint($overview_index);
						$is_open       = $overview_index < 2;
						?>
						<article class="mb-range-brand__overview-item<?php echo $is_open ? ' is-open' : ''; ?>" data-range-overview-item>
							<button
								type="button"
								class="mb-range-brand__overview-trigger"
								aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
								aria-controls="<?php echo esc_attr($panel_id); ?>"
								data-range-overview-trigger
							>
								<span class="mb-range-brand__overview-trigger-label"><?php echo esc_html($section_title); ?></span>
								<span class="mb-range-brand__overview-trigger-icon" aria-hidden="true"></span>
							</button>
							<div
								id="<?php echo esc_attr($panel_id); ?>"
								class="mb-range-brand__overview-panel"
								<?php echo $is_open ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								data-range-overview-panel
							>
								<div class="mb-range-brand__overview-panel-inner">
									<?php if ('list' === $section_type) : ?>
										<ul>
											<?php foreach ((array) $overview_section['content'] as $line) : ?>
												<?php if ('' !== trim((string) $line)) : ?>
													<li><?php echo esc_html((string) $line); ?></li>
												<?php endif; ?>
											<?php endforeach; ?>
										</ul>
									<?php else : ?>
										<p><?php echo esc_html((string) $overview_section['content']); ?></p>
									<?php endif; ?>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>

			<div class="mb-range-brand__modules mb-range-brand__modules--ingredients">
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Key Ingredients', 'mad-baits'); ?></h3>
					<ul>
						<?php foreach ($ingredients as $ingredient) : ?>
							<?php if (is_string($ingredient) && '' !== $ingredient) : ?>
								<li><?php echo esc_html($ingredient); ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</article>
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Session Confidence', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Built to hold confidence from first introduction through repeat campaign feeding windows.', 'mad-baits'); ?></p>
				</article>
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Built For', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Serious carp anglers who demand food-value consistency and clean hookbait pairing.', 'mad-baits'); ?></p>
				</article>
			</div>

			<div class="mb-range-brand__modules mb-range-brand__modules--usage">
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Best In', 'mad-baits'); ?></h3>
					<ul>
						<?php foreach ($best_for as $use_case) : ?>
							<?php if (is_string($use_case) && '' !== $use_case) : ?>
								<li><?php echo esc_html($use_case); ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</article>
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Session Style', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Use as a complete baiting system with matching hookbaits and attraction support.', 'mad-baits'); ?></p>
				</article>
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Available Sizes', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Choose practical options to suit short-session prep, campaign feeding and stock-up plans.', 'mad-baits'); ?></p>
				</article>
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Freezer / Shelf Life', 'mad-baits'); ?></h3>
					<p><?php echo esc_html('' !== $availability ? $availability : __('Range availability varies by current listing.', 'mad-baits')); ?></p>
				</article>
				<article class="mb-range-brand__module">
					<h3><?php esc_html_e('Pair With', 'mad-baits'); ?></h3>
					<p><?php echo esc_html('' !== $pair_with ? $pair_with : __('Pair with confidence hookbaits and matching liquids.', 'mad-baits')); ?></p>
				</article>
			</div>

			<div class="mb-range-brand__actions">
				<a class="mad-button" href="<?php echo esc_url($boilie_range_url); ?>"><?php echo esc_html(sprintf(__('Shop %s', 'mad-baits'), $headline)); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url((string) $whats_in_water_url); ?>"><?php esc_html_e("What's In The Water?", 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost mb-range-brand__action--extra" href="<?php echo esc_url((string) $args['build_url']); ?>"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost mb-range-brand__action--extra" href="<?php echo esc_url((string) $args['hookbaits_url']); ?>"><?php esc_html_e('Pair With Hookbaits', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost mb-range-brand__action--extra" href="<?php echo esc_url((string) $args['ai_url']); ?>"><?php esc_html_e('Ask AI Bait Finder', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Normalize semantic keyword list.
 *
 * @param array $needles Source keyword list.
 * @return string[]
 */
function mad_baits_normalize_semantic_needles($needles) {
	$normalized = array();
	foreach ((array) $needles as $needle) {
		$slug = sanitize_title((string) $needle);
		if ('' !== $slug) {
			$normalized[] = $slug;
		}
	}

	return array_values(array_unique($normalized));
}

/**
 * Resolve matching product term IDs by semantic keyword hints.
 *
 * @param string $taxonomy Taxonomy key.
 * @param array  $needles Keyword hints.
 * @param bool   $hide_empty Hide empty terms.
 * @return int[]
 */
function mad_baits_get_matching_product_term_ids($taxonomy, $needles, $hide_empty = true) {
	if (! taxonomy_exists((string) $taxonomy)) {
		return array();
	}

	$needles = mad_baits_normalize_semantic_needles((array) $needles);
	if (empty($needles)) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => (string) $taxonomy,
			'hide_empty' => (bool) $hide_empty,
		)
	);

	if (is_wp_error($terms) || ! is_array($terms)) {
		return array();
	}

	$matched_ids = array();
	foreach ($terms as $term) {
		if (! $term instanceof WP_Term) {
			continue;
		}

		$slug = sanitize_title((string) $term->slug);
		$name = sanitize_title((string) $term->name);
		foreach ($needles as $needle) {
			if (false !== strpos($slug, $needle) || false !== strpos($name, $needle)) {
				$matched_ids[] = (int) $term->term_id;
				break;
			}
		}
	}

	return array_values(array_unique(array_filter(array_map('absint', $matched_ids))));
}

/**
 * Resolve Woo product IDs from semantic keyword hints.
 *
 * @param array $needles Keyword hints.
 * @param int   $limit Number of products to return.
 * @return int[]
 */
function mad_baits_get_semantic_product_ids($needles, $limit = 12) {
	$limit = max(1, absint($limit));
	if (! class_exists('WooCommerce')) {
		return array();
	}

	$needles = mad_baits_normalize_semantic_needles((array) $needles);
	if (empty($needles)) {
		return array();
	}

	$term_tax_query = array('relation' => 'OR');
	foreach (array('product_cat', 'product_tag') as $taxonomy) {
		$term_ids = mad_baits_get_matching_product_term_ids($taxonomy, $needles, true);
		if (! empty($term_ids)) {
			$term_tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			);
		}
	}

	$ids = array();
	if (count($term_tax_query) > 1) {
		$taxonomy_query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'tax_query'              => $term_tax_query,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = array_values(array_filter(array_map('absint', (array) $taxonomy_query->posts)));
		wp_reset_postdata();
	}

	if (count($ids) >= $limit) {
		return array_slice(array_values(array_unique($ids)), 0, $limit);
	}

	$remaining = $limit - count($ids);
	foreach ($needles as $needle) {
		if ($remaining <= 0) {
			break;
		}

		$search_query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $remaining,
				's'                      => str_replace('-', ' ', $needle),
				'post__not_in'           => $ids,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$search_ids = array_values(array_filter(array_map('absint', (array) $search_query->posts)));
		wp_reset_postdata();

		if (! empty($search_ids)) {
			$ids       = array_values(array_unique(array_merge($ids, $search_ids)));
			$remaining = $limit - count($ids);
		}
	}

	return array_slice(array_values(array_unique($ids)), 0, $limit);
}

/**
 * Resolve Mad Baits TV page URL, prioritising assigned template pages.
 *
 * @return string
 */
function mad_baits_get_mad_baits_tv_url() {
	$template_page = get_pages(
		array(
			'post_status' => 'publish',
			'number'      => 1,
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'template-mad-baits-tv.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if (! empty($template_page) && isset($template_page[0]) && $template_page[0] instanceof WP_Post) {
		$template_link = get_permalink($template_page[0]);
		if (is_string($template_link) && '' !== $template_link) {
			return $template_link;
		}
	}

	return mad_baits_get_page_url('mad-baits-tv', 'mad-baits-tv');
}

/**
 * Get allowed Mad Baits TV categories.
 *
 * @return array<string, string>
 */
function mad_baits_get_mad_baits_tv_categories() {
	return array(
		'product-videos'   => __('Product Videos', 'mad-baits'),
		'catch-reports'    => __('Catch Reports', 'mad-baits'),
		'session-tips'     => __('Session Tips', 'mad-baits'),
		'behind-the-bait'  => __('Behind The Bait', 'mad-baits'),
	);
}

/**
 * Mad Baits TV meta key map.
 *
 * @return array<string, string>
 */
function mad_baits_get_mad_baits_tv_meta_keys() {
	return array(
		'youtube_url'      => '_mad_tv_youtube_url',
		'category'         => '_mad_tv_category',
		'featured'         => '_mad_tv_featured',
		'thumbnail_url'    => '_mad_tv_thumbnail_url',
		'card_description' => '_mad_tv_card_description',
	);
}

/**
 * Parse a YouTube URL/embed string to a video ID.
 *
 * @param string $url Source URL.
 * @return string
 */
function mad_baits_get_youtube_video_id($url) {
	$url = trim((string) $url);
	if ('' === $url) {
		return '';
	}

	$parts = wp_parse_url($url);
	if (! is_array($parts)) {
		return '';
	}

	$host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
	$path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';

	if (false !== strpos($host, 'youtu.be')) {
		$segments = array_values(array_filter(explode('/', $path)));
		return isset($segments[0]) ? sanitize_text_field((string) $segments[0]) : '';
	}

	if (false !== strpos($host, 'youtube.com') || false !== strpos($host, 'youtube-nocookie.com')) {
		if (isset($parts['query'])) {
			$query_args = array();
			parse_str((string) $parts['query'], $query_args);
			if (! empty($query_args['v']) && is_string($query_args['v'])) {
				return sanitize_text_field((string) $query_args['v']);
			}
		}

		$segments = array_values(array_filter(explode('/', $path)));
		if (count($segments) >= 2 && in_array($segments[0], array('embed', 'shorts', 'live'), true)) {
			return sanitize_text_field((string) $segments[1]);
		}
	}

	return '';
}

/**
 * Get fallback Mad Baits TV videos.
 *
 * This array is intentionally simple so editors can safely update titles,
 * descriptions, thumbnail URLs and YouTube links without code changes.
 *
 * @return array<int, array<string, mixed>>
 */
function mad_baits_get_mad_baits_tv_videos() {
	$videos = array(
		array(
			'title'       => 'Compulsive Boilie Breakdown',
			'description' => 'A deep look at how Compulsive performs on pressured waters and where to deploy it.',
			'category'    => 'product-videos',
			'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			'thumbnail'   => '',
			'featured'    => true,
		),
		array(
			'title'       => '48-Hour Catch Report: Spring Pit',
			'description' => 'Real campaign footage, baiting decisions and final fish results from a short UK session.',
			'category'    => 'catch-reports',
			'youtube_url' => 'https://www.youtube.com/watch?v=M7lc1UVf-VE',
			'thumbnail'   => '',
		),
		array(
			'title'       => 'Margin Spot Prep Session Tips',
			'description' => 'How to prep a high-confidence margin area and keep fish feeding over multiple trips.',
			'category'    => 'session-tips',
			'youtube_url' => 'https://youtu.be/ysz5S6PUM-U',
			'thumbnail'   => '',
		),
		array(
			'title'       => 'Inside The Bait Room',
			'description' => 'Behind the scenes with ingredient selection, rolling consistency and quality checks.',
			'category'    => 'behind-the-bait',
			'youtube_url' => 'https://www.youtube.com/embed/aqz-KE-bpKQ',
			'thumbnail'   => '',
		),
		array(
			'title'       => 'Nutz Plus Product Walkthrough',
			'description' => 'When to run Nutz Plus, ideal pairings, and session timing for stronger response windows.',
			'category'    => 'product-videos',
			'youtube_url' => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
			'thumbnail'   => '',
		),
		array(
			'title'       => 'Overnighter Catch Report Highlights',
			'description' => 'A practical breakdown of location, bait spread and weather-trigger adjustments.',
			'category'    => 'catch-reports',
			'youtube_url' => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
			'thumbnail'   => '',
		),
	);

	/**
	 * Allow optional extension via child theme/plugins later.
	 */
	return apply_filters('mad_baits_tv_videos', $videos);
}

/**
 * Build Mad Baits TV videos from CPT entries.
 *
 * @return array<int, array<string, mixed>>
 */
function mad_baits_get_mad_baits_tv_cpt_videos() {
	$meta_keys = mad_baits_get_mad_baits_tv_meta_keys();
	$query     = new WP_Query(
		array(
			'post_type'              => 'mad_baits_video',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		)
	);

	if (! $query->have_posts()) {
		wp_reset_postdata();
		return array();
	}

	$categories        = mad_baits_get_mad_baits_tv_categories();
	$fallback_category = (string) array_key_first($categories);
	$videos            = array();

	foreach ($query->posts as $post) {
		if (! $post instanceof WP_Post) {
			continue;
		}

		$post_id     = (int) $post->ID;
		$youtube_url = get_post_meta($post_id, $meta_keys['youtube_url'], true);
		$category    = get_post_meta($post_id, $meta_keys['category'], true);
		$is_featured = get_post_meta($post_id, $meta_keys['featured'], true);

		$thumbnail = get_post_meta($post_id, $meta_keys['thumbnail_url'], true);
		$thumbnail = is_string($thumbnail) ? esc_url_raw($thumbnail) : '';
		if ('' === $thumbnail) {
			$featured_image = get_the_post_thumbnail_url($post_id, 'large');
			$thumbnail      = is_string($featured_image) ? esc_url_raw($featured_image) : '';
		}

		$description_override = get_post_meta($post_id, $meta_keys['card_description'], true);
		$description_override = is_string($description_override) ? sanitize_text_field($description_override) : '';

		$excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : '';
		$excerpt = is_string($excerpt) ? sanitize_text_field($excerpt) : '';

		$content = get_post_field('post_content', $post_id);
		$content = is_string($content) ? wp_trim_words(wp_strip_all_tags($content), 28, '...') : '';
		$content = sanitize_text_field($content);

		$description = '' !== $description_override ? $description_override : $excerpt;
		if ('' === $description) {
			$description = $content;
		}

		$category = is_string($category) ? sanitize_key($category) : $fallback_category;
		if (! isset($categories[ $category ])) {
			$category = $fallback_category;
		}

		$videos[] = array(
			'title'       => sanitize_text_field(get_the_title($post_id)),
			'description' => $description,
			'category'    => $category,
			'youtube_url' => is_string($youtube_url) ? esc_url_raw($youtube_url) : '',
			'thumbnail'   => $thumbnail,
			'featured'    => ! empty($is_featured),
		);
	}

	wp_reset_postdata();

	return $videos;
}

/**
 * Build a safe, normalized Mad Baits TV video set.
 *
 * @return array<int, array<string, string>>
 */
function mad_baits_get_mad_baits_tv_video_rows() {
	$categories = mad_baits_get_mad_baits_tv_categories();
	$fallback_category = (string) array_key_first($categories);
	$rows = array();
	$cpt_videos = mad_baits_get_mad_baits_tv_cpt_videos();
	$videos     = ! empty($cpt_videos) ? $cpt_videos : mad_baits_get_mad_baits_tv_videos();

	foreach ($videos as $index => $video) {
		if (! is_array($video)) {
			continue;
		}

		$youtube_url = isset($video['youtube_url']) ? (string) $video['youtube_url'] : '';
		$video_id    = mad_baits_get_youtube_video_id($youtube_url);
		if ('' === $video_id) {
			continue;
		}

		$category = isset($video['category']) ? sanitize_key((string) $video['category']) : $fallback_category;
		if (! isset($categories[ $category ])) {
			$category = $fallback_category;
		}

		$title = isset($video['title']) ? sanitize_text_field((string) $video['title']) : '';
		if ('' === $title) {
			$title = sprintf(__('Mad Baits TV Video %d', 'mad-baits'), (int) $index + 1);
		}

		$description = isset($video['description']) ? sanitize_text_field((string) $video['description']) : '';
		$thumbnail = isset($video['thumbnail']) ? esc_url_raw((string) $video['thumbnail']) : '';
		if ('' === $thumbnail) {
			$thumbnail = 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/hqdefault.jpg';
		}

		$rows[] = array(
			'id'            => sanitize_title($title . '-' . ((string) $index + 1)),
			'title'         => $title,
			'description'   => $description,
			'category'      => $category,
			'category_name' => (string) $categories[ $category ],
			'video_id'      => $video_id,
			'embed_url'     => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video_id) . '?rel=0&modestbranding=1',
			'watch_url'     => 'https://www.youtube.com/watch?v=' . rawurlencode($video_id),
			'thumbnail'     => $thumbnail,
			'is_featured'   => ! empty($video['featured']),
		);
	}

	if (! empty($cpt_videos) && empty($rows)) {
		foreach (mad_baits_get_mad_baits_tv_videos() as $index => $video) {
			if (! is_array($video)) {
				continue;
			}

			$youtube_url = isset($video['youtube_url']) ? (string) $video['youtube_url'] : '';
			$video_id    = mad_baits_get_youtube_video_id($youtube_url);
			if ('' === $video_id) {
				continue;
			}

			$category = isset($video['category']) ? sanitize_key((string) $video['category']) : $fallback_category;
			if (! isset($categories[ $category ])) {
				$category = $fallback_category;
			}

			$title = isset($video['title']) ? sanitize_text_field((string) $video['title']) : '';
			if ('' === $title) {
				$title = sprintf(__('Mad Baits TV Video %d', 'mad-baits'), (int) $index + 1);
			}

			$description = isset($video['description']) ? sanitize_text_field((string) $video['description']) : '';
			$thumbnail   = isset($video['thumbnail']) ? esc_url_raw((string) $video['thumbnail']) : '';
			if ('' === $thumbnail) {
				$thumbnail = 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/hqdefault.jpg';
			}

			$rows[] = array(
				'id'            => sanitize_title($title . '-' . ((string) $index + 1)),
				'title'         => $title,
				'description'   => $description,
				'category'      => $category,
				'category_name' => (string) $categories[ $category ],
				'video_id'      => $video_id,
				'embed_url'     => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video_id) . '?rel=0&modestbranding=1',
				'watch_url'     => 'https://www.youtube.com/watch?v=' . rawurlencode($video_id),
				'thumbnail'     => $thumbnail,
				'is_featured'   => ! empty($video['featured']),
			);
		}
	}

	return $rows;
}

/**
 * Split Mad Baits TV rows into featured + grid videos.
 *
 * @param int $grid_limit Optional max non-featured videos.
 * @return array{featured: array<string, string>|null, grid: array<int, array<string, string>>, categories: array<string, string>}
 */
function mad_baits_get_mad_baits_tv_payload($grid_limit = 0) {
	$rows       = mad_baits_get_mad_baits_tv_video_rows();
	$categories = mad_baits_get_mad_baits_tv_categories();
	$featured   = null;
	$grid       = array();

	foreach ($rows as $row) {
		if (null === $featured && ! empty($row['is_featured'])) {
			$featured = $row;
			continue;
		}
		$grid[] = $row;
	}

	if (null === $featured && ! empty($grid)) {
		$featured = array_shift($grid);
	}

	$grid_limit = absint($grid_limit);
	if ($grid_limit > 0) {
		$grid = array_slice($grid, 0, $grid_limit);
	}

	return array(
		'featured'   => $featured,
		'grid'       => $grid,
		'categories' => $categories,
	);
}

/**
 * Render reusable Mad Baits TV component.
 *
 * @param array $args Render arguments.
 */
function mad_baits_render_mad_baits_tv_component($args = array()) {
	$args = wp_parse_args(
		(array) $args,
		array(
			'section_class' => 'section section--contrast mad-baits-tv',
			'show_header'   => true,
			'show_cta'      => false,
			'show_filters'  => false,
			'grid_limit'    => 0,
			'headline'      => __('Mad Baits TV', 'mad-baits'),
			'kicker'        => __('Mad Baits TV', 'mad-baits'),
			'cta_url'       => mad_baits_get_mad_baits_tv_url(),
		)
	);

	$payload  = mad_baits_get_mad_baits_tv_payload((int) $args['grid_limit']);
	$featured = isset($payload['featured']) && is_array($payload['featured']) ? $payload['featured'] : null;
	$grid     = isset($payload['grid']) && is_array($payload['grid']) ? $payload['grid'] : array();

	if (null === $featured) {
		return;
	}
	?>
	<section class="<?php echo esc_attr((string) $args['section_class']); ?>">
		<div class="container">
			<?php if (! empty($args['show_header'])) : ?>
				<div class="section__heading section__heading--with-actions">
					<div>
						<p class="section__kicker"><?php echo esc_html((string) $args['kicker']); ?></p>
						<h2><?php echo esc_html((string) $args['headline']); ?></h2>
					</div>
					<?php if (! empty($args['show_cta'])) : ?>
						<a class="mad-button mad-button--small" href="<?php echo esc_url((string) $args['cta_url']); ?>">
							<?php esc_html_e('Watch Mad Baits TV', 'mad-baits'); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<article class="mbtv-featured-card">
				<div class="mbtv-featured-card__media">
					<iframe
						src="<?php echo esc_url((string) $featured['embed_url']); ?>"
						title="<?php echo esc_attr((string) $featured['title']); ?>"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						allowfullscreen
						loading="lazy"></iframe>
				</div>
				<div class="mbtv-featured-card__content">
					<span class="mbtv-chip"><?php echo esc_html((string) $featured['category_name']); ?></span>
					<h3><?php echo esc_html((string) $featured['title']); ?></h3>
					<?php if ('' !== $featured['description']) : ?>
						<p><?php echo esc_html((string) $featured['description']); ?></p>
					<?php endif; ?>
					<a class="mad-button mad-button--small" href="<?php echo esc_url((string) $featured['watch_url']); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Watch Now', 'mad-baits'); ?>
					</a>
				</div>
			</article>

			<?php if (! empty($args['show_filters'])) : ?>
				<div class="mbtv-filters" data-tv-filter-wrap>
					<button type="button" class="mbtv-filter is-active" data-tv-filter="all" aria-pressed="true"><?php esc_html_e('All', 'mad-baits'); ?></button>
					<?php foreach ((array) $payload['categories'] as $category_key => $category_name) : ?>
						<button type="button" class="mbtv-filter" data-tv-filter="<?php echo esc_attr((string) $category_key); ?>" aria-pressed="false">
							<?php echo esc_html((string) $category_name); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (! empty($grid)) : ?>
				<div class="mbtv-grid">
					<?php foreach ($grid as $video) : ?>
						<article class="mbtv-card" data-tv-card data-tv-category="<?php echo esc_attr((string) $video['category']); ?>">
							<a class="mbtv-card__media" href="<?php echo esc_url((string) $video['watch_url']); ?>" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url((string) $video['thumbnail']); ?>" alt="<?php echo esc_attr((string) $video['title']); ?>" loading="lazy" decoding="async" />
								<span class="mbtv-card__play" aria-hidden="true"></span>
							</a>
							<div class="mbtv-card__content">
								<span class="mbtv-chip"><?php echo esc_html((string) $video['category_name']); ?></span>
								<h3><?php echo esc_html((string) $video['title']); ?></h3>
								<?php if ('' !== $video['description']) : ?>
									<p><?php echo esc_html((string) $video['description']); ?></p>
								<?php endif; ?>
								<a class="mad-button mad-button--small" href="<?php echo esc_url((string) $video['watch_url']); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e('Watch Video', 'mad-baits'); ?>
								</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Footer menu fallback with trust-policy links.
 *
 * @param array $args Menu args.
 * @return string
 */
function mad_baits_footer_menu_fallback($args) {
	unset($args);

	$items = array(
		array('label' => __('AI Bait Finder', 'mad-baits'), 'url' => mad_baits_get_ai_bait_finder_url()),
		array('label' => __('Delivery Information', 'mad-baits'), 'url' => mad_baits_get_page_url('delivery-information', 'delivery-information')),
		array('label' => __('Returns & Refunds', 'mad-baits'), 'url' => mad_baits_get_page_url('returns-refunds', 'returns-refunds')),
		array('label' => __('FAQ', 'mad-baits'), 'url' => mad_baits_get_page_url('faq', 'faq')),
		array('label' => __('Contact', 'mad-baits'), 'url' => mad_baits_get_page_url('contact', 'contact')),
		array('label' => __('Privacy Policy', 'mad-baits'), 'url' => mad_baits_get_page_url('privacy-policy', 'privacy-policy')),
		array('label' => __('Terms & Conditions', 'mad-baits'), 'url' => mad_baits_get_page_url('terms-conditions', 'terms-conditions')),
		array('label' => __('Cookie Policy', 'mad-baits'), 'url' => mad_baits_get_page_url('cookie-policy', 'cookie-policy')),
	);

	$html = '<ul class="site-footer__menu">';
	foreach ($items as $item) {
		$html .= '<li><a href="' . esc_url((string) $item['url']) . '">' . esc_html((string) $item['label']) . '</a></li>';
	}
	$html .= '</ul>';

	return $html;
}

/**
 * Add custom dropdown class to selected top-level items.
 *
 * @param array    $classes Menu item classes.
 * @param WP_Post  $item    Menu item object.
 * @param stdClass $args    Nav menu args.
 * @return array
 */
function mad_baits_nav_menu_classes($classes, $item, $args) {
	if (empty($args->theme_location) || 'primary' !== $args->theme_location) {
		return $classes;
	}

	$item_title_slug = sanitize_title((string) $item->title);
	$item_url        = isset($item->url) ? sanitize_text_field((string) $item->url) : '';

	if (
		in_array('menu-item-has-children', $classes, true)
		&& (
			false !== strpos($item_title_slug, 'bait-ranges')
			|| false !== strpos($item_title_slug, 'shop-by-range')
			|| false !== strpos($item_url, '/product-tag/')
		)
	) {
		$classes[] = 'menu-item-featured-dropdown';
	}

	return array_values(array_unique($classes));
}
add_filter('nav_menu_css_class', 'mad_baits_nav_menu_classes', 10, 3);

/**
 * Normalize key navigation labels to premium concise wording.
 *
 * @param string   $title Menu label.
 * @param WP_Post  $item  Menu item.
 * @param stdClass $args  Menu args.
 * @param int      $depth Depth.
 * @return string
 */
function mad_baits_nav_menu_item_title($title, $item, $args, $depth) {
	unset($item, $depth);

	if (empty($args->theme_location) || ! in_array($args->theme_location, array('primary', 'mobile'), true)) {
		return $title;
	}

	$normalized = sanitize_title((string) $title);
	if ('merchandise' === $normalized || 'merch' === $normalized) {
		return __('Clothing', 'mad-baits');
	}

	return $title;
}
add_filter('nav_menu_item_title', 'mad_baits_nav_menu_item_title', 10, 4);

/**
 * Keep Boilies signature-range submenu ordered alphabetically.
 *
 * @param array    $items Menu item objects.
 * @param stdClass $args  Menu args.
 * @return array
 */
function mad_baits_sort_signature_range_dropdown($items, $args) {
	if (empty($args->theme_location) || 'primary' !== $args->theme_location || empty($items) || ! is_array($items)) {
		return $items;
	}

	$range_parent_ids = array();
	foreach ($items as $item) {
		if (! $item instanceof WP_Post) {
			continue;
		}
		if ((int) $item->menu_item_parent !== 0) {
			continue;
		}
		$title_slug = sanitize_title((string) $item->title);
		$url_value  = isset($item->url) ? (string) $item->url : '';
		if (
			false !== strpos($title_slug, 'bait-ranges')
			|| false !== strpos($title_slug, 'shop-by-range')
			|| false !== strpos($url_value, '/product-tag/')
		) {
			$range_parent_ids[] = (int) $item->ID;
		}
	}

	if (empty($range_parent_ids)) {
		return $items;
	}

	$children_map = array();
	foreach ($items as $item) {
		if (! $item instanceof WP_Post) {
			continue;
		}
		$parent_id = (int) $item->menu_item_parent;
		if (! in_array($parent_id, $range_parent_ids, true)) {
			continue;
		}
		if (! isset($children_map[ $parent_id ])) {
			$children_map[ $parent_id ] = array();
		}
		$children_map[ $parent_id ][] = $item;
	}

	foreach ($children_map as $parent_id => $children) {
		usort($children, static function ($a, $b) {
			$desired_order = array(
				'bbb',
				'asbo',
				'wicked-white',
				'p-fish',
				'p-fish-2',
				'pandemic',
				'nutz-plus',
				'nutz-banana',
				'compulsive-angler',
			);
			$order_index = array_fill_keys($desired_order, 999);
			foreach ($desired_order as $idx => $slug) {
				$order_index[ $slug ] = $idx;
			}

			$get_order = static function ($item) use ($order_index) {
				$title_slug = sanitize_title(isset($item->title) ? (string) $item->title : '');
				$url_value  = isset($item->url) ? (string) $item->url : '';
				$url_slug   = sanitize_title(trim((string) basename(parse_url($url_value, PHP_URL_PATH) ?: ''), '/'));
				if (isset($order_index[ $url_slug ])) {
					return (int) $order_index[ $url_slug ];
				}
				if (isset($order_index[ $title_slug ])) {
					return (int) $order_index[ $title_slug ];
				}
				return 999;
			};

			$a_order = $get_order($a);
			$b_order = $get_order($b);
			if ($a_order !== $b_order) {
				return $a_order <=> $b_order;
			}

			$a_title = isset($a->title) ? wp_strip_all_tags((string) $a->title) : '';
			$b_title = isset($b->title) ? wp_strip_all_tags((string) $b->title) : '';
			return strcasecmp($a_title, $b_title);
		});
		$children_map[ $parent_id ] = $children;
	}

	$sorted            = array();
	$appended_child_id = array();
	foreach ($items as $item) {
		if (! $item instanceof WP_Post) {
			$sorted[] = $item;
			continue;
		}

		$current_id = (int) $item->ID;
		if (isset($appended_child_id[ $current_id ])) {
			continue;
		}

		$parent_id = (int) $item->menu_item_parent;
		if ($parent_id > 0 && in_array($parent_id, $range_parent_ids, true)) {
			continue;
		}

		$sorted[] = $item;

		if (isset($children_map[ $current_id ]) && ! empty($children_map[ $current_id ])) {
			foreach ($children_map[ $current_id ] as $child) {
				$sorted[] = $child;
				$appended_child_id[ (int) $child->ID ] = true;
			}
		}
	}

	return $sorted;
}
add_filter('wp_nav_menu_objects', 'mad_baits_sort_signature_range_dropdown', 20, 2);

function mad_baits_body_classes($classes) {
	$classes[] = 'mad-baits-theme';

	if (function_exists('is_woocommerce') && is_woocommerce()) {
		$classes[] = 'mad-baits-woocommerce';
	}

	return $classes;
}
add_filter('body_class', 'mad_baits_body_classes');

/**
 * Update cart count in header with Woo fragments.
 *
 * @param array $fragments Existing fragments.
 * @return array
 */
function mad_baits_cart_count_fragment($fragments) {
	if (! function_exists('WC') || ! WC()->cart) {
		return $fragments;
	}

	ob_start();
	?>
	<span class="site-header__cart-count"><?php echo esc_html((string) WC()->cart->get_cart_contents_count()); ?></span>
	<?php
	$fragments['.site-header__cart-count'] = ob_get_clean();

	ob_start();
	?>
	<span class="mad-mini-cart-drawer__count"><?php echo esc_html((string) WC()->cart->get_cart_contents_count()); ?></span>
	<?php
	$fragments['.mad-mini-cart-drawer__count'] = ob_get_clean();

	$fragments['.mad-mini-cart-drawer__content']        = mad_baits_get_mini_cart_drawer_items_html();
	$fragments['.mad-mini-cart-drawer__subtotal-value'] = mad_baits_get_mini_cart_drawer_subtotal_html();

	return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'mad_baits_cart_count_fragment');

/**
 * Get mini cart drawer line-items HTML.
 *
 * @return string
 */
function mad_baits_get_mini_cart_drawer_items_html() {
	if (! function_exists('WC') || ! WC()->cart || ! function_exists('woocommerce_mini_cart')) {
		return '<p class="mad-mini-cart-drawer__empty">' . esc_html__('Your basket is currently unavailable.', 'mad-baits') . '</p>';
	}

	ob_start();
	?>
	<div class="mad-mini-cart-drawer__content">
		<?php woocommerce_mini_cart(); ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Get mini cart drawer subtotal fragment HTML.
 *
 * @return string
 */
function mad_baits_get_mini_cart_drawer_subtotal_html() {
	if (! function_exists('WC') || ! WC()->cart) {
		return '<span class="mad-mini-cart-drawer__subtotal-value">' . esc_html__('Unavailable', 'mad-baits') . '</span>';
	}

	return '<span class="mad-mini-cart-drawer__subtotal-value">' . wp_kses_post(WC()->cart->get_cart_subtotal()) . '</span>';
}

/**
 * Render the single global mini-cart drawer (Your Basket).
 * Only one instance should exist in the page footer.
 */
function mad_baits_render_mini_cart_drawer() {
	if (is_admin() || ! class_exists('WooCommerce')) {
		return;
	}

	if (function_exists('mad_baits_is_wc_checkout_flow_request') && mad_baits_is_wc_checkout_flow_request()) {
		return;
	}

	$cart_url     = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
	$checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
	?>
	<div class="mad-mini-cart-drawer-shell" data-mini-cart-drawer hidden aria-hidden="true">
		<div class="mad-mini-cart-drawer__backdrop" data-mini-cart-close tabindex="-1" aria-hidden="true"></div>
		<aside class="mad-mini-cart-drawer" role="dialog" aria-modal="true" aria-labelledby="mad-mini-cart-title" tabindex="-1">
			<div class="mad-mini-cart-drawer__handle" aria-hidden="true"></div>
			<header class="mad-mini-cart-drawer__header">
				<h2 id="mad-mini-cart-title"><?php esc_html_e('Your Basket', 'mad-baits'); ?></h2>
				<div class="mad-mini-cart-drawer__header-meta">
					<span><?php esc_html_e('Items', 'mad-baits'); ?>:</span>
					<span class="mad-mini-cart-drawer__count"><?php echo esc_html((string) (function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0)); ?></span>
				</div>
				<button type="button" class="mad-mini-cart-drawer__close" data-mini-cart-close aria-label="<?php esc_attr_e('Close basket', 'mad-baits'); ?>">
					&times;
				</button>
			</header>

			<?php echo mad_baits_get_mini_cart_drawer_items_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<div class="mad-mini-cart-drawer__footer">
				<p class="mad-mini-cart-drawer__subtotal">
					<span><?php esc_html_e('Subtotal', 'mad-baits'); ?></span>
					<?php echo mad_baits_get_mini_cart_drawer_subtotal_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
				<div class="mad-mini-cart-drawer__actions">
					<a class="mad-button" href="<?php echo esc_url($checkout_url); ?>"><?php esc_html_e('Checkout', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($cart_url); ?>"><?php esc_html_e('View Basket', 'mad-baits'); ?></a>
					<button type="button" class="mad-mini-cart-drawer__continue" data-mini-cart-close><?php esc_html_e('Continue shopping', 'mad-baits'); ?></button>
				</div>
			</div>
		</aside>
	</div>
	<?php
}
add_action('wp_footer', 'mad_baits_render_mini_cart_drawer', 40);

/**
 * Ajax add-to-cart handler for compact variable forms on homepage cards.
 */
function mad_baits_ajax_add_variation_to_cart() {
	check_ajax_referer('mad_baits_card_add_to_cart', 'nonce');

	if (! function_exists('WC') || ! WC()->cart) {
		wp_send_json_error(array('message' => __('Cart is unavailable right now.', 'mad-baits')));
	}

	$product_id   = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
	$variation_id = isset($_POST['variation_id']) ? absint(wp_unslash($_POST['variation_id'])) : 0;
	$quantity     = isset($_POST['quantity']) ? max(1, absint(wp_unslash($_POST['quantity']))) : 1;
	$raw_attrs    = isset($_POST['attributes']) ? (array) wp_unslash($_POST['attributes']) : array();
	$attributes   = array();

	foreach ($raw_attrs as $key => $value) {
		$key = sanitize_text_field((string) $key);
		if (0 !== strpos($key, 'attribute_')) {
			continue;
		}
		$attributes[ $key ] = sanitize_text_field((string) $value);
	}

	$product   = wc_get_product($product_id);
	$variation = wc_get_product($variation_id);

	if (! $product || ! $variation || ! $product->is_type('variable') || (int) $variation->get_parent_id() !== (int) $product_id) {
		wp_send_json_error(array('message' => __('Please choose valid options.', 'mad-baits')));
	}

	if (! $variation->is_purchasable() || ! $variation->is_in_stock()) {
		wp_send_json_error(array('message' => __('This variation is unavailable.', 'mad-baits')));
	}

	$added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $attributes);
	if (! $added) {
		wp_send_json_error(array('message' => __('Could not add this product right now.', 'mad-baits')));
	}

	wp_send_json_success(
		array(
			'message'    => __('Added to basket', 'mad-baits'),
			'cart_count' => (int) WC()->cart->get_cart_contents_count(),
			'cart_url'   => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
		)
	);
}
add_action('wp_ajax_mad_baits_add_variation_to_cart', 'mad_baits_ajax_add_variation_to_cart');
add_action('wp_ajax_nopriv_mad_baits_add_variation_to_cart', 'mad_baits_ajax_add_variation_to_cart');

/**
 * Standardize WooCommerce product counts across key loops.
 *
 * @param int $columns Existing loop columns.
 * @return int
 */
function mad_baits_loop_shop_columns($columns) {
	unset($columns);
	return 4;
}
add_filter('loop_shop_columns', 'mad_baits_loop_shop_columns');

/**
 * Standardize archive product count across all product listing pages.
 *
 * @return int
 */
function mad_baits_loop_shop_per_page() {
	if (function_exists('is_product_category') && is_product_category()) {
		$term = get_queried_object();
		if ($term instanceof WP_Term && 'hookbaits' === $term->slug) {
			return 12;
		}
	}

	return 24;
}
add_filter('loop_shop_per_page', 'mad_baits_loop_shop_per_page', 20);

/**
 * Keep related products tidy and premium.
 *
 * @param array $args Existing args.
 * @return array
 */
function mad_baits_related_products_args($args) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
}
add_filter('woocommerce_output_related_products_args', 'mad_baits_related_products_args');

/**
 * Use a premium heading for related products.
 *
 * @return string
 */
function mad_baits_related_products_heading() {
	return __('Complete Your Session', 'mad-baits');
}
add_filter('woocommerce_product_related_products_heading', 'mad_baits_related_products_heading', 99);

/**
 * Use a custom heading for upsell products.
 *
 * @return string
 */
function mad_baits_upsell_products_heading() {
	return __('Anglers Also Pair This With', 'mad-baits');
}
add_filter('woocommerce_product_upsells_products_heading', 'mad_baits_upsell_products_heading', 99);

/**
 * Keep upsells in a 4-column layout.
 *
 * @param array $args Existing args.
 * @return array
 */
function mad_baits_upsell_display_args($args) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
}
add_filter('woocommerce_upsell_display_args', 'mad_baits_upsell_display_args');

/**
 * Set cross-sell product amount.
 *
 * @return int
 */
function mad_baits_cross_sells_total() {
	return 4;
}
add_filter('woocommerce_cross_sells_total', 'mad_baits_cross_sells_total');

/**
 * Set cross-sell columns.
 *
 * @return int
 */
function mad_baits_cross_sells_columns() {
	return 4;
}
add_filter('woocommerce_cross_sells_columns', 'mad_baits_cross_sells_columns');

/**
 * Render conversion trust strip on cart and checkout.
 */
function mad_baits_render_woo_trust_strip() {
	$is_checkout = function_exists('is_checkout') && is_checkout();
	$points      = $is_checkout
		? array(
			__('Secure checkout', 'mad-baits'),
			__('Fast dispatch', 'mad-baits'),
			__('Fresh bait', 'mad-baits'),
			__('Support available', 'mad-baits'),
		)
		: array(
			__('Fast dispatch', 'mad-baits'),
			__('Secure checkout', 'mad-baits'),
			__('Fresh bait', 'mad-baits'),
			__('UK delivery', 'mad-baits'),
		);
	?>
	<section class="mad-woo-trust-strip" aria-label="<?php esc_attr_e('Shopping reassurance', 'mad-baits'); ?>">
		<ul class="mad-woo-trust-strip__list">
			<?php foreach ($points as $point) : ?>
				<li><?php echo esc_html($point); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
}
add_action('woocommerce_before_cart', 'mad_baits_render_woo_trust_strip', 5);
add_action('woocommerce_before_checkout_form', 'mad_baits_render_woo_trust_strip', 6);

/**
 * Add trust points underneath cart totals CTA.
 */
function mad_baits_render_cart_totals_trust_points() {
	if (! function_exists('is_cart') || ! is_cart()) {
		return;
	}
	?>
	<ul class="mad-cart-totals-trust" aria-label="<?php esc_attr_e('Cart reassurance points', 'mad-baits'); ?>">
		<li><?php esc_html_e('Fast dispatch', 'mad-baits'); ?></li>
		<li><?php esc_html_e('Secure checkout', 'mad-baits'); ?></li>
		<li><?php esc_html_e('Fresh bait', 'mad-baits'); ?></li>
		<li><?php esc_html_e('UK delivery', 'mad-baits'); ?></li>
	</ul>
	<?php
}
add_action('woocommerce_proceed_to_checkout', 'mad_baits_render_cart_totals_trust_points', 25);

/**
 * Improve empty cart conversion path with guided CTAs.
 */
function mad_baits_render_empty_cart_cta() {
	if (! function_exists('wc_get_page_permalink')) {
		return;
	}

	$shop_url    = wc_get_page_permalink('shop');
	$bundle_url  = function_exists('mad_baits_get_bundle_deals_url')
		? mad_baits_get_bundle_deals_url()
		: home_url('/product-category/bundles-deals/');
	$boilies_url = get_term_link('boilies', 'product_cat');
	if (is_wp_error($boilies_url)) {
		$boilies_url = home_url('/product-category/boilies/');
	}
	$ai_url = function_exists('mad_baits_get_ai_bait_finder_url')
		? mad_baits_get_ai_bait_finder_url()
		: home_url('/ai-bait-finder/');
	?>
	<div class="mad-empty-cart-panel">
		<p class="mad-empty-cart-panel__eyebrow"><?php esc_html_e('Mad Baits', 'mad-baits'); ?></p>
		<h2><?php esc_html_e('Your basket is empty', 'mad-baits'); ?></h2>
		<p><?php esc_html_e('Load up with proven bait systems and session-ready bundles before your next trip.', 'mad-baits'); ?></p>
		<div class="mad-empty-cart-panel__actions">
			<a class="mad-button" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Start with a bundle', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--ghost" href="<?php echo esc_url($boilies_url); ?>"><?php esc_html_e('Shop boilies', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--ghost" href="<?php echo esc_url($ai_url); ?>"><?php esc_html_e('Use AI Bait Finder', 'mad-baits'); ?></a>
			<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse all products', 'mad-baits'); ?></a>
		</div>
	</div>
	<?php
}
add_action('woocommerce_cart_is_empty', 'mad_baits_render_empty_cart_cta', 20);

/**
 * Hide default WooCommerce empty-cart copy — theme panel replaces it.
 *
 * @return void
 */
function mad_baits_remove_default_empty_cart_message() {
	if (! function_exists('is_cart') || ! is_cart()) {
		return;
	}
	remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);
}
add_action('template_redirect', 'mad_baits_remove_default_empty_cart_message', 5);

/**
 * Display-time copy fixes for known product description typos.
 *
 * @param string $content Post or excerpt content.
 * @return string
 */
function mad_baits_polish_product_copy($content) {
	if (! is_string($content) || '' === $content) {
		return $content;
	}
	if (! function_exists('is_product') || ! is_product()) {
		return $content;
	}

	$replacements = array(
		'Nutz Plus18mm'  => 'Nutz Plus 18mm',
		'Range: Abso,'   => 'Range: ASBO,',
		'Abso,P fish'    => 'ASBO, P-Fish',
		'Wicked whites'  => 'Wicked Whites',
	);

	return str_replace(array_keys($replacements), array_values($replacements), $content);
}
add_filter('the_content', 'mad_baits_polish_product_copy', 25);
add_filter('woocommerce_short_description', 'mad_baits_polish_product_copy', 25);

/**
 * Whether a product ID is currently in the WooCommerce cart.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_product_is_in_cart($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('WC') || ! WC()->cart) {
		return false;
	}

	foreach ((array) WC()->cart->get_cart() as $cart_item) {
		if (empty($cart_item['product_id'])) {
			continue;
		}
		if ((int) $cart_item['product_id'] === $product_id) {
			return true;
		}
	}

	return false;
}

/**
 * When checkout is hit with an empty basket, Woo redirects to cart —
 * add a clear notice so the redirect feels intentional.
 *
 * @return void
 */
function mad_baits_empty_checkout_notice() {
	if (! function_exists('is_cart') || ! is_cart() || ! function_exists('WC') || ! WC()->cart) {
		return;
	}
	if (! WC()->cart->is_empty()) {
		return;
	}
	if (empty($_GET['empty-cart']) && empty($_GET['removed_item'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Soft notice only when arriving from checkout redirect patterns Woo may set.
		$ref = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
		if ($ref && false === stripos($ref, 'checkout')) {
			return;
		}
	}
	wc_add_notice(
		__('Your basket is empty. Pick a bundle or shop boilies to get started.', 'mad-baits'),
		'notice'
	);
}
add_action('template_redirect', 'mad_baits_empty_checkout_notice', 5);

/**
 * Add reassurance copy above place order.
 */
function mad_baits_checkout_reassurance_copy() {
	?>
	<div class="mad-checkout-reassurance" aria-live="polite">
		<strong><?php esc_html_e('Payment protected by secure checkout.', 'mad-baits'); ?></strong>
		<span><?php esc_html_e('You will receive order confirmation and dispatch updates by email.', 'mad-baits'); ?></span>
	</div>
	<?php
}
add_action('woocommerce_review_order_before_submit', 'mad_baits_checkout_reassurance_copy', 15);

/**
 * Add premium post-purchase next steps.
 *
 * @param int $order_id Order ID.
 */
function mad_baits_thankyou_next_steps($order_id) {
	if (! $order_id || ! function_exists('wc_get_order')) {
		return;
	}

	$order = wc_get_order($order_id);
	if (! $order) {
		return;
	}

	$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$bundle_url = function_exists('mad_baits_get_bundle_deals_url')
		? mad_baits_get_bundle_deals_url()
		: home_url('/product-category/bundles-deals/');
	?>
	<section class="mad-thankyou-next-steps" aria-label="<?php esc_attr_e('Order next steps', 'mad-baits'); ?>">
		<p class="mad-thankyou-next-steps__eyebrow"><?php esc_html_e('What Happens Next', 'mad-baits'); ?></p>
		<ul class="mad-thankyou-next-steps__list">
			<li><?php esc_html_e('Order confirmation email', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Dispatch updates', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Contact support if needed', 'mad-baits'); ?></li>
		</ul>
		<div class="mad-thankyou-next-steps__actions">
			<a class="mad-button mad-button--small" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Back To Shop', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Browse Bundles', 'mad-baits'); ?></a>
		</div>
	</section>
	<?php
}
add_action('woocommerce_thankyou', 'mad_baits_thankyou_next_steps', 25);

/**
 * Add premium confirmation card at start of thank-you page.
 *
 * @param int $order_id Order ID.
 */
function mad_baits_thankyou_confirmation_card($order_id) {
	if (! $order_id || ! function_exists('wc_get_order')) {
		return;
	}

	$order = wc_get_order($order_id);
	if (! $order || $order->has_status('failed')) {
		return;
	}

	$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$bundle_url = function_exists('mad_baits_get_bundle_deals_url')
		? mad_baits_get_bundle_deals_url()
		: home_url('/product-category/bundles-deals/');
	?>
	<section class="mad-thankyou-confirmation-card" aria-label="<?php esc_attr_e('Order confirmation highlights', 'mad-baits'); ?>">
		<p class="mad-thankyou-confirmation-card__eyebrow"><?php esc_html_e('Order Confirmed', 'mad-baits'); ?></p>
		<h2><?php esc_html_e('Your bait is queued and being prepared fresh.', 'mad-baits'); ?></h2>
		<ul class="mad-thankyou-confirmation-card__next-steps">
			<li><?php esc_html_e('Confirmation lands in your inbox shortly.', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Dispatch updates are sent as soon as your order moves.', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Need help? Our support team is available.', 'mad-baits'); ?></li>
		</ul>
		<div class="mad-thankyou-confirmation-card__actions">
			<a class="mad-button mad-button--small" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Back To Shop', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Bundle Deals', 'mad-baits'); ?></a>
		</div>
	</section>
	<?php
}
add_action('woocommerce_before_thankyou', 'mad_baits_thankyou_confirmation_card', 8);

/**
 * Global toggle for compact variation controls on product cards.
 *
 * Override via:
 * add_filter('mad_baits_enable_compact_card_variations', '__return_false');
 *
 * @return bool
 */
function mad_baits_enable_compact_card_variations() {
	return (bool) apply_filters('mad_baits_enable_compact_card_variations', true);
}

/**
 * Disable inline variation forms on WooCommerce listing archives.
 *
 * Variable products on archive cards link to the product page instead.
 *
 * @param bool $enabled Whether compact card variations are enabled.
 * @return bool
 */
function mad_baits_disable_compact_variations_on_product_listings($enabled) {
	if (! $enabled) {
		return false;
	}

	if (function_exists('mad_baits_is_woocommerce_product_listing_page') && mad_baits_is_woocommerce_product_listing_page()) {
		return false;
	}

	return $enabled;
}
add_filter('mad_baits_enable_compact_card_variations', 'mad_baits_disable_compact_variations_on_product_listings', 15);

/**
 * Build safe HTML attributes string for action links.
 *
 * Uses WooCommerce helper when available, with a lightweight fallback
 * to avoid fatals on older WooCommerce versions.
 *
 * @param array $attributes Attribute key/value pairs.
 * @return string
 */
function mad_baits_implode_html_attributes($attributes) {
	if (function_exists('wc_implode_html_attributes')) {
		return wc_implode_html_attributes($attributes);
	}

	$compiled = array();
	foreach ((array) $attributes as $name => $value) {
		$compiled[] = sprintf('%s="%s"', esc_attr((string) $name), esc_attr((string) $value));
	}

	return implode(' ', $compiled);
}

function mad_baits_render_product_card($product_id = 0, $enable_compact_variations = false, $card_context = '', $grid_type = '') {
	if ($product_id instanceof WC_Product) {
		$product_id = (int) $product_id->get_id();
	}

	$product_id = $product_id ? absint($product_id) : get_the_ID();

	if (! $product_id || 'product' !== get_post_type($product_id)) {
		return;
	}

	if (function_exists('mad_baits_should_show_product_on_homepage') && ! mad_baits_should_show_product_on_homepage($product_id)) {
		return;
	}

	if (! function_exists('wc_get_product')) {
		return;
	}

	$product = wc_get_product($product_id);

	if (! $product) {
		return;
	}

	$image_html      = '';
	$thumbnail_id    = $product->get_image_id();
	$product_name    = $product->get_name();
	$product_url     = $product->get_permalink();
	$add_to_cart_url = $product->add_to_cart_url();
	$product_type    = $product->get_type();

	if (function_exists('mad_baits_get_product_card_image_html')) {
		$image_html = mad_baits_get_product_card_image_html(
			$product_id,
			array(
				'alt' => $product_name,
			)
		);
	} elseif ($thumbnail_id) {
		$image_html = wp_get_attachment_image(
			$thumbnail_id,
			'woocommerce_thumbnail',
			false,
			array(
				'class'    => 'mad-product-card__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'sizes'    => '(max-width: 480px) 220px, (max-width: 768px) 280px, (max-width: 1200px) 320px, 400px',
				'alt'      => $product_name,
			)
		);
	}

	$button_classes = array('button', 'mad-button', 'mad-button--small');
	$button_attrs   = array(
		'aria-label' => $product->add_to_cart_description(),
		'rel'        => 'nofollow',
	);

	if ($product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock() && $product->is_type('simple')) {
		$button_classes[] = 'add_to_cart_button';
		$button_classes[] = 'ajax_add_to_cart';
		$button_attrs['data-quantity']    = 1;
		$button_attrs['data-product_id']  = $product->get_id();
		$button_attrs['data-product_sku'] = $product->get_sku();
	}

	$is_on_sale     = (bool) $product->is_on_sale();
	$category_label = function_exists('mad_baits_get_product_card_category_label')
		? mad_baits_get_product_card_category_label($product_id)
		: '';
	if ('' === $category_label) {
		$categories = wc_get_product_terms($product_id, 'product_cat', array('fields' => 'names'));
		if (! empty($categories) && is_array($categories)) {
			$category_label = (string) $categories[0];
		}
	}

	$category_slugs = wc_get_product_terms($product_id, 'product_cat', array('fields' => 'slugs'));
	if (! is_array($category_slugs)) {
		$category_slugs = array();
	}

	$deal_category_hints = array(
		'bundles-deals',
		'bundle-deals',
		'bundles',
		'bundle',
		'deal',
		'deals',
		'offer',
		'offers',
		'save-more',
		'multi-buy',
		'multipack',
		'value-pack',
	);
	$has_bundle_deal_marker = false;
	foreach ($category_slugs as $slug) {
		if (in_array(sanitize_title((string) $slug), $deal_category_hints, true)) {
			$has_bundle_deal_marker = true;
			break;
		}
	}

	$discount_badges = array();
	if ($has_bundle_deal_marker) {
		$discount_badges[] = array(
			'class' => 'mad-product-card__discount-badge--bundle',
			'label' => __('Bundle Deal', 'mad-baits'),
		);
	}
	if ($is_on_sale) {
		$discount_badges[] = array(
			'class' => 'mad-product-card__discount-badge--save',
			'label' => __('Save More', 'mad-baits'),
		);
	}

	$range_badge = '';
	if (taxonomy_exists('pa_range')) {
		$range_terms = wc_get_product_terms($product_id, 'pa_range', array('fields' => 'names'));
		if (is_array($range_terms) && ! empty($range_terms[0])) {
			$range_badge = (string) $range_terms[0];
		}
	}
	if ('' === $range_badge && function_exists('mad_baits_get_final_shop_range_definitions')) {
		$product_tag_slugs = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
		$product_tag_slugs = is_array($product_tag_slugs) ? array_map('sanitize_title', $product_tag_slugs) : array();
		foreach (mad_baits_get_final_shop_range_definitions() as $range_def) {
			$candidate_slug = sanitize_title((string) ($range_def['slug'] ?? ''));
			if ('' !== $candidate_slug && in_array($candidate_slug, $product_tag_slugs, true)) {
				$range_badge = (string) ($range_def['label'] ?? '');
				break;
			}
		}
	}

	$format_badge = '';
	if (taxonomy_exists('pa_bait-format')) {
		$format_terms = wc_get_product_terms($product_id, 'pa_bait-format', array('fields' => 'names'));
		if (is_array($format_terms) && ! empty($format_terms[0])) {
			$format_badge = (string) $format_terms[0];
		}
	}

	$card_classes = array('mad-product-card', 'mad-product-card--unified');
	$card_context = sanitize_key((string) $card_context);
	if ('' !== $card_context) {
		$card_classes[] = 'mad-product-card--context-' . $card_context;
	}
	if ($is_on_sale) {
		$card_classes[] = 'mad-product-card--discounted';
	}
	$publish_ts = (int) get_post_time('U', true, $product_id);
	$is_new_product = $publish_ts > 0 ? ((time() - $publish_ts) < (DAY_IN_SECONDS * 21)) : false;
	$is_team_pick   = has_term(array('team-pick', 'team-picks', 'staff-pick'), 'product_tag', $product_id);
	$total_sales    = (int) get_post_meta($product_id, 'total_sales', true);
	$is_best_seller = $total_sales >= 150;

	$premium_badges = array();
	if ('' !== $range_badge) {
		$premium_badges[] = $range_badge;
	}
	if ('' !== $format_badge) {
		$premium_badges[] = $format_badge;
	}
	if ($is_best_seller) {
		$premium_badges[] = __('Best seller', 'mad-baits');
	}
	if ($is_new_product) {
		$premium_badges[] = __('New', 'mad-baits');
	}
	if ($is_team_pick) {
		$premium_badges[] = __('Team pick', 'mad-baits');
	}
	if (in_array($card_context, array('perfect-match', 'perfect-match-inline'), true)) {
		$premium_badges[] = __('Perfect match', 'mad-baits');
	}

	if ($has_bundle_deal_marker) {
		$card_classes[] = 'mad-product-card--bundle-deal';
	}

	$discount_message = '';
	if ($has_bundle_deal_marker && $is_on_sale) {
		$discount_message = __('Bundle deal and promotional pricing available.', 'mad-baits');
	} elseif ($has_bundle_deal_marker) {
		$discount_message = __('Bundle pricing available on this product.', 'mad-baits');
	} elseif ($is_on_sale) {
		$discount_message = __('Limited-time savings live now.', 'mad-baits');
	}

	$show_variation_form = false;
	$variation_payload   = array();
	$variation_attrs     = array();
	$variation_count     = 0;
	$has_purchasable_variations = false;
	$is_bundle_builder_product = function_exists('mad_baits_is_bundle_builder_product')
		? (bool) mad_baits_is_bundle_builder_product($product_id)
		: false;

	if ($enable_compact_variations && $product->is_type('variable')) {
		$variation_attrs = (array) $product->get_variation_attributes();
		$variation_count = count($variation_attrs);

		if ($variation_count > 0 && $variation_count <= 3) {
			$available_variations = (array) $product->get_available_variations();
			foreach ($available_variations as $variation_data) {
				if (empty($variation_data['variation_id'])) {
					continue;
				}

				$raw_attributes = isset($variation_data['attributes']) ? (array) $variation_data['attributes'] : array();
				$normalized_attributes = array();
				foreach ($raw_attributes as $attribute_name => $attribute_value) {
					$base_name = str_replace('attribute_', '', (string) $attribute_name);
					$canonical_attribute = wc_variation_attribute_name($base_name);
					$normalized_attributes[ $canonical_attribute ] = wc_clean((string) $attribute_value);
				}

				$is_purchasable = ! empty($variation_data['is_purchasable']);
				$is_in_stock    = ! empty($variation_data['is_in_stock']);

				$variation_payload[] = array(
					'variation_id' => absint($variation_data['variation_id']),
					'attributes'   => $normalized_attributes,
					'is_purchasable' => $is_purchasable,
					'is_in_stock'  => $is_in_stock,
					'price_html'   => isset($variation_data['price_html']) ? wp_kses_post((string) $variation_data['price_html']) : '',
				);

				if ($is_purchasable && $is_in_stock) {
					$has_purchasable_variations = true;
				}
			}

			$show_variation_form = $has_purchasable_variations && ! empty($variation_payload);
		}
	}

	if ($show_variation_form) {
		$card_classes[] = 'mad-product-card--with-attrs';
	} else {
		$card_classes[] = 'mad-product-card--no-attrs';
	}

	$show_bundle_helper = ! $show_variation_form && ($has_bundle_deal_marker || $is_bundle_builder_product);
	$show_simple_helper = ! $show_variation_form && ! $show_bundle_helper && 'simple' === $product_type;
	$helper_points      = array();

	if ($show_bundle_helper) {
		$term_slugs = array_merge(
			$category_slugs,
			(array) wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'))
		);
		$term_slugs = array_map('sanitize_title', array_filter(array_map('strval', $term_slugs)));
		$keyword_haystack = strtolower($product_name . ' ' . implode(' ', $term_slugs));

		$append_helper_point = static function ($label) use (&$helper_points) {
			$label = trim((string) $label);
			if ('' === $label || in_array($label, $helper_points, true) || count($helper_points) >= 4) {
				return;
			}
			$helper_points[] = $label;
		};

		if ($is_bundle_builder_product) {
			$append_helper_point(__('Bundle Builder', 'mad-baits'));
		}
		if ($has_bundle_deal_marker) {
			$append_helper_point(__('Session Deal', 'mad-baits'));
		}
		if (false !== strpos($keyword_haystack, 'boilie')) {
			$append_helper_point(__('Boilies', 'mad-baits'));
		}
		if (false !== strpos($keyword_haystack, 'hookbait')) {
			$append_helper_point(__('Hookbaits', 'mad-baits'));
		}
		if (false !== strpos($keyword_haystack, 'pellet')) {
			$append_helper_point(__('Pellets', 'mad-baits'));
		}
		if (false !== strpos($keyword_haystack, 'liquid') || false !== strpos($keyword_haystack, 'dip') || false !== strpos($keyword_haystack, 'oil')) {
			$append_helper_point(__('Liquids', 'mad-baits'));
		}

		if (count($helper_points) < 4) {
			foreach (
				array(
					__('Session-ready deal', 'mad-baits'),
					__('Built to save money', 'mad-baits'),
					__('Fast checkout', 'mad-baits'),
					__('Perfect for your next trip', 'mad-baits'),
				) as $fallback_point
			) {
				$append_helper_point($fallback_point);
			}
		}
	}

	$variation_payload_json = wp_json_encode($variation_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	if (! is_string($variation_payload_json)) {
		$variation_payload_json = '[]';
	}

	$grid_type_attr = sanitize_key((string) $grid_type);
	if ('' === $grid_type_attr && function_exists('mad_baits_get_product_grid_type_data_attr')) {
		$grid_type_attr = mad_baits_get_product_grid_type_data_attr($product_id);
	}
	?>
	<article <?php post_class($card_classes, $product_id); ?> data-mad-product-id="<?php echo esc_attr((string) $product_id); ?>"<?php echo '' !== $grid_type_attr ? ' data-product-type="' . esc_attr($grid_type_attr) . '"' : ''; ?>>
		<div class="mad-product-card__media">
			<a class="mad-product-card__image-link" href="<?php echo esc_url($product_url); ?>">
				<?php if (! empty($discount_badges)) : ?>
					<span class="mad-product-card__discount-badges">
						<?php foreach ($discount_badges as $badge) : ?>
							<span class="mad-product-card__sale-badge mad-product-card__discount-badge <?php echo esc_attr((string) $badge['class']); ?>">
								<?php echo esc_html((string) $badge['label']); ?>
							</span>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
				<?php if ($category_label) : ?>
					<span class="mad-product-card__category-badge"><?php echo esc_html($category_label); ?></span>
				<?php endif; ?>
				<?php if (! empty($premium_badges)) : ?>
					<span class="mad-product-card__premium-badges">
						<?php foreach ($premium_badges as $premium_badge_label) : ?>
							<span class="mad-product-card__premium-badge"><?php echo esc_html((string) $premium_badge_label); ?></span>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
				<?php if ($image_html) : ?>
					<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<img
						class="mad-product-card__image mad-product-card__image--placeholder"
						src="<?php echo esc_url(get_theme_file_uri('/assets/img/CUP-LOGOv2-1.svg')); ?>"
						loading="lazy"
						alt="<?php esc_attr_e('Mad Baits logo', 'mad-baits'); ?>"
					/>
				<?php endif; ?>
			</a>
			<button
				type="button"
				class="mad-product-card__favourite"
				data-favourite-toggle
				data-product-id="<?php echo esc_attr((string) $product_id); ?>"
				aria-pressed="false"
				aria-label="<?php esc_attr_e('Save to favourites', 'mad-baits'); ?>"
			>
				<span class="mad-product-card__favourite-icon" aria-hidden="true">❤</span>
			</button>
		</div>
		<div class="mad-product-card__content">
			<span class="mad-product-card__eyebrow"><?php echo esc_html__('Mad Baits', 'mad-baits'); ?></span>
			<h3 class="mad-product-card__title">
				<a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($product_name); ?></a>
			</h3>
			<div class="mad-product-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
			<?php if ('' !== $discount_message) : ?>
				<p class="mad-product-card__discount-message"><?php echo esc_html($discount_message); ?></p>
			<?php endif; ?>
			<?php if ($show_bundle_helper) : ?>
				<div class="mad-product-card__helper mad-product-card__helper--bundle" aria-label="<?php esc_attr_e("What's included", 'mad-baits'); ?>">
					<p class="mad-product-card__helper-title"><?php esc_html_e("What's included", 'mad-baits'); ?></p>
					<ul class="mad-product-card__helper-list">
						<?php foreach ($helper_points as $helper_point) : ?>
							<li><?php echo esc_html((string) $helper_point); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php elseif ($show_simple_helper) : ?>
				<div class="mad-product-card__helper mad-product-card__helper--simple">
					<p class="mad-product-card__helper-title"><?php esc_html_e('Ready to add', 'mad-baits'); ?></p>
					<p class="mad-product-card__helper-copy"><?php esc_html_e('No options needed - add straight to basket.', 'mad-baits'); ?></p>
				</div>
			<?php endif; ?>
			<div class="mad-product-card__actions">
				<?php if ($is_bundle_builder_product) : ?>
					<a href="<?php echo esc_url($product_url); ?>" class="mad-button mad-button--small"><?php esc_html_e('Build Bundle', 'mad-baits'); ?></a>
					<?php if (function_exists('mad_baits_product_is_in_cart') && mad_baits_product_is_in_cart($product_id)) : ?>
						<a href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); ?>" class="mad-button mad-button--small mad-button--ghost"><?php esc_html_e('View Basket', 'mad-baits'); ?></a>
					<?php endif; ?>
				<?php elseif ($show_variation_form) : ?>
					<form class="mad-product-card__variation-form" data-variation-form data-product-id="<?php echo esc_attr((string) $product_id); ?>" data-variations="<?php echo esc_attr($variation_payload_json); ?>">
						<?php foreach ($variation_attrs as $attr_name => $options) : ?>
							<?php
							$base_name = str_replace('attribute_', '', (string) $attr_name);
							$taxonomy = $base_name;
							$field_name = wc_variation_attribute_name($base_name);
							$label    = wc_attribute_label($taxonomy);
							?>
							<label class="mad-product-card__variation-field" for="<?php echo esc_attr('attr-' . $product_id . '-' . sanitize_title((string) $attr_name)); ?>">
								<span class="mad-product-card__variation-label"><?php echo esc_html($label); ?></span>
								<select id="<?php echo esc_attr('attr-' . $product_id . '-' . sanitize_title((string) $attr_name)); ?>" class="mad-product-card__variation-select" name="<?php echo esc_attr((string) $field_name); ?>">
									<option value=""><?php echo esc_html(sprintf(__('Select %s', 'mad-baits'), $label)); ?></option>
									<?php foreach ((array) $options as $option_value) : ?>
										<?php
										$option_label = (string) $option_value;
										if (taxonomy_exists($taxonomy)) {
											$term = get_term_by('slug', (string) $option_value, $taxonomy);
											if ($term && ! is_wp_error($term)) {
												$option_label = (string) $term->name;
											}
										}
										?>
										<option value="<?php echo esc_attr((string) $option_value); ?>"><?php echo esc_html($option_label); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endforeach; ?>
						<div class="mad-product-card__variation-row">
							<span class="mad-product-card__variation-price" data-variation-price><?php echo wp_kses_post($product->get_price_html()); ?></span>
							<button type="submit" class="mad-button mad-button--small" data-variation-submit disabled><?php esc_html_e('Add To Basket', 'mad-baits'); ?></button>
						</div>
						<p class="mad-product-card__inline-message" data-inline-message aria-live="polite"></p>
					</form>
				<?php elseif ('variable' === $product_type) : ?>
					<?php if (! $has_purchasable_variations && $enable_compact_variations) : ?>
						<a href="<?php echo esc_url($product_url); ?>" class="mad-button mad-button--small"><?php esc_html_e('View Product', 'mad-baits'); ?></a>
					<?php else : ?>
						<a href="<?php echo esc_url($product_url); ?>" class="mad-button mad-button--small"><?php esc_html_e('Choose Options', 'mad-baits'); ?></a>
					<?php endif; ?>
				<?php elseif ($enable_compact_variations && ! $product->is_purchasable()) : ?>
					<a href="<?php echo esc_url($product_url); ?>" class="mad-button mad-button--small"><?php esc_html_e('View Product', 'mad-baits'); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url($add_to_cart_url); ?>" class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" <?php echo mad_baits_implode_html_attributes($button_attrs); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php echo esc_html($product->add_to_cart_text()); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php /* View Basket only after a successful add — Checkout lives in mini-cart / cart page. */ ?>
			<div class="mad-product-card__post-add" data-card-feedback hidden>
				<span class="mad-product-card__post-add-label" data-card-feedback-text aria-live="polite"></span>
				<div class="mad-product-card__post-add-links">
					<a class="text-link" href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); ?>" data-card-view-basket><?php esc_html_e('View Basket', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Resolve OpenAI API key from constant or option.
 *
 * @return string
 */
function mad_baits_get_openai_api_key() {
	if (defined('MAD_BAITS_OPENAI_API_KEY') && MAD_BAITS_OPENAI_API_KEY) {
		return (string) MAD_BAITS_OPENAI_API_KEY;
	}

	if (function_exists('getenv')) {
		$env_key = getenv('MAD_BAITS_OPENAI_API_KEY');
		if (is_string($env_key) && '' !== trim($env_key)) {
			return trim($env_key);
		}
	}

	$stored = get_option('mad_baits_openai_api_key', '');
	return is_string($stored) ? trim($stored) : '';
}

/**
 * Resolve OpenAI model from constant/option/default.
 *
 * @return string
 */
function mad_baits_get_openai_model() {
	if (defined('MAD_BAITS_OPENAI_MODEL') && MAD_BAITS_OPENAI_MODEL) {
		return (string) apply_filters('mad_baits_openai_model', (string) MAD_BAITS_OPENAI_MODEL);
	}

	$stored = get_option('mad_baits_openai_model', '');
	if (is_string($stored) && '' !== trim($stored)) {
		return (string) apply_filters('mad_baits_openai_model', trim($stored));
	}

	return (string) apply_filters('mad_baits_openai_model', 'gpt-4.1-mini');
}

/**
 * Build compact WooCommerce product catalog for grounding.
 *
 * @param int $limit Product cap.
 * @return array
 */
function mad_baits_get_ai_catalog($limit = 120) {
	if (! class_exists('WooCommerce')) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max(1, absint($limit)),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if (! $query->have_posts()) {
		return array();
	}

	$catalog = array();
	while ($query->have_posts()) {
		$query->the_post();
		$product = wc_get_product(get_the_ID());
		if (! $product) {
			continue;
		}

		$terms = get_the_terms($product->get_id(), 'product_cat');
		$cats  = array();
		if (is_array($terms)) {
			foreach ($terms as $term) {
				if (! $term instanceof WP_Term) {
					continue;
				}
				$cats[] = array(
					'name' => (string) $term->name,
					'slug' => (string) $term->slug,
				);
			}
		}

		$catalog[] = array(
			'id'          => (int) $product->get_id(),
			'name'        => (string) $product->get_name(),
			'slug'        => (string) $product->get_slug(),
			'url'         => (string) $product->get_permalink(),
			'type'        => (string) $product->get_type(),
			'sku'         => (string) $product->get_sku(),
			'categories'  => $cats,
		);
	}
	wp_reset_postdata();

	return $catalog;
}

/**
 * Convert catalog into compact prompt context.
 *
 * @param array $catalog Grounding catalog.
 * @return string
 */
function mad_baits_format_catalog_for_prompt($catalog) {
	$lines = array();
	foreach ($catalog as $item) {
		$cats = array();
		foreach ((array) $item['categories'] as $cat) {
			if (! empty($cat['name'])) {
				$cats[] = (string) $cat['name'] . ' [' . (string) $cat['slug'] . ']';
			}
		}
		$lines[] = sprintf(
			'- %s | type:%s | slug:%s | categories:%s',
			(string) $item['name'],
			(string) $item['type'],
			(string) $item['slug'],
			$cats ? implode(', ', $cats) : 'none'
		);
	}

	return implode("\n", $lines);
}

/**
 * Extract plain text response from Responses API payload.
 *
 * @param array $payload API response.
 * @return string
 */
function mad_baits_extract_openai_text($payload) {
	if (! empty($payload['output_text']) && is_string($payload['output_text'])) {
		return trim($payload['output_text']);
	}

	if (empty($payload['output']) || ! is_array($payload['output'])) {
		return '';
	}

	$chunks = array();
	foreach ($payload['output'] as $item) {
		if (empty($item['content']) || ! is_array($item['content'])) {
			continue;
		}
		foreach ($item['content'] as $content) {
			if (! empty($content['text']) && is_string($content['text'])) {
				$chunks[] = $content['text'];
			}
		}
	}

	return trim(implode("\n", $chunks));
}

/**
 * Parse AI JSON safely with simple recovery.
 *
 * @param string $text LLM raw output text.
 * @return array
 */
function mad_baits_parse_ai_json($text) {
	$decoded = json_decode((string) $text, true);
	if (is_array($decoded)) {
		return $decoded;
	}

	$first = strpos((string) $text, '{');
	$last  = strrpos((string) $text, '}');
	if (false === $first || false === $last || $last <= $first) {
		return array();
	}

	$slice   = substr((string) $text, $first, $last - $first + 1);
	$decoded = json_decode((string) $slice, true);

	return is_array($decoded) ? $decoded : array();
}

/**
 * Build graceful fallback recommendation.
 *
 * @param array $input Sanitized quiz input.
 * @return array
 */
function mad_baits_build_fallback_recommendation($input) {
	$approach = isset($input['approach']) ? (string) $input['approach'] : 'bundle';
	$season   = isset($input['season']) ? (string) $input['season'] : '';

	$boilie = 'Compulsive';
	if (false !== stripos($season, 'winter')) {
		$boilie = 'Nutz Plus';
	}

	$hookbait = 'Matching pop-up';
	if ('hookbaits' === $approach) {
		$hookbait = 'Bright pop-up + washed wafter options';
	}

	return array(
		'boilie_range'      => $boilie,
		'hookbait'          => $hookbait,
		'addon'             => 'Matching liquid food / bait soak',
		'baiting_plan'      => 'Start with a light spread, monitor activity for 1-2 hours, then top up little-and-often around showing fish.',
		'why_suits_lake'    => 'This keeps attraction high while controlling bait volume, ideal for pressured venues and changing sessions.',
		'suggested_products'=> array('Session Pack', 'Compulsive', 'Nutz Plus'),
		'suggested_category_slugs' => array('bundles', 'boilies', 'hookbaits'),
	);
}

/**
 * Product image URL for compact recommendation cards.
 *
 * @param WC_Product $product Product object.
 * @param string     $size    Image size.
 * @return string
 */
function mad_baits_get_product_image_url($product, $size = 'woocommerce_thumbnail') {
	if (! $product instanceof WC_Product) {
		return function_exists('wc_placeholder_img_src') ? (string) wc_placeholder_img_src($size) : '';
	}

	$image_id = (int) $product->get_image_id();
	if ($image_id > 0) {
		$url = wp_get_attachment_image_url($image_id, $size);
		if (is_string($url) && '' !== $url) {
			return $url;
		}
	}

	return function_exists('wc_placeholder_img_src') ? (string) wc_placeholder_img_src($size) : '';
}

/**
 * Format a Woo product row for AI finder responses.
 *
 * @param WC_Product $product Product object.
 * @param array      $item    Optional catalog row.
 * @return array
 */
function mad_baits_format_ai_recommended_product($product, $item = array()) {
	$product_id = (int) $product->get_id();
	$url        = (string) $product->get_permalink();

	if (! empty($item['url']) && is_string($item['url'])) {
		$url = (string) $item['url'];
	}

	return array(
		'id'              => $product_id,
		'name'            => (string) $product->get_name(),
		'url'             => $url,
		'type'            => (string) $product->get_type(),
		'image_url'       => mad_baits_get_product_image_url($product),
		'price_html'      => wp_kses_post((string) $product->get_price_html()),
		'add_to_cart_url' => (string) $product->add_to_cart_url(),
		'add_to_cart_text'=> (string) $product->add_to_cart_text(),
		'sku'             => (string) $product->get_sku(),
		'can_ajax'        => (bool) ($product->supports('ajax_add_to_cart') && $product->is_type('simple')),
	);
}

/**
 * Add a product (simple or first available variation) to the cart.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_add_product_to_cart_for_ai($product_id) {
	if (! function_exists('WC') || ! WC()->cart) {
		return false;
	}

	$product = wc_get_product($product_id);
	if (! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
		return false;
	}

	if ($product->is_type('simple')) {
		return (bool) WC()->cart->add_to_cart($product_id, 1);
	}

	if (! $product->is_type('variable')) {
		return false;
	}

	$variation_ids = $product->get_children();
	foreach ($variation_ids as $variation_id) {
		$variation = wc_get_product((int) $variation_id);
		if (! $variation instanceof WC_Product || ! $variation->is_purchasable() || ! $variation->is_in_stock()) {
			continue;
		}

		if (WC()->cart->add_to_cart($product_id, 1, (int) $variation_id)) {
			return true;
		}
	}

	return false;
}

/**
 * Ajax: add AI recommended products to basket.
 *
 * @return void
 */
function mad_baits_ajax_ai_add_session_to_cart() {
	check_ajax_referer('mad_baits_ai_bait_finder', 'nonce');

	if (! function_exists('WC') || ! WC()->cart) {
		wp_send_json_error(array('message' => __('Cart is unavailable right now.', 'mad-baits')));
	}

	$raw_ids = isset($_POST['product_ids']) ? wp_unslash($_POST['product_ids']) : array();
	$ids     = array();

	if (is_array($raw_ids)) {
		$ids = array_map('absint', $raw_ids);
	} else {
		$ids = array_map('absint', explode(',', (string) $raw_ids));
	}

	$ids = array_values(array_filter(array_unique($ids)));
	if (empty($ids)) {
		wp_send_json_error(array('message' => __('No recommended products to add.', 'mad-baits')));
	}

	$added_count = 0;
	foreach ($ids as $product_id) {
		if (mad_baits_add_product_to_cart_for_ai($product_id)) {
			$added_count++;
		}
	}

	if ($added_count < 1) {
		wp_send_json_error(
			array(
				'message' => __('Could not add recommended products. Variable items may need options chosen on the product page.', 'mad-baits'),
			)
		);
	}

	wp_send_json_success(
		array(
			'added_count' => $added_count,
			'cart_count'  => (int) WC()->cart->get_cart_contents_count(),
			'cart_url'    => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
			'message'     => sprintf(
				/* translators: %d: number of products added */
				_n('%d recommended product added to basket.', '%d recommended products added to basket.', $added_count, 'mad-baits'),
				$added_count
			),
		)
	);
}
add_action('wp_ajax_mad_baits_ai_add_session_to_cart', 'mad_baits_ajax_ai_add_session_to_cart');
add_action('wp_ajax_nopriv_mad_baits_ai_add_session_to_cart', 'mad_baits_ajax_ai_add_session_to_cart');

/**
 * Match suggested products/categories to real Woo products.
 *
 * @param array $catalog Grounding products.
 * @param array $ai_data Parsed AI response.
 * @param int   $limit Max return rows.
 * @return array
 */
function mad_baits_match_recommended_products($catalog, $ai_data, $limit = 4) {
	$matches = array();
	$seen    = array();

	$suggested_names = isset($ai_data['suggested_products']) && is_array($ai_data['suggested_products']) ? $ai_data['suggested_products'] : array();
	foreach ($suggested_names as $name) {
		$name = strtolower(trim((string) $name));
		if ('' === $name) {
			continue;
		}

		foreach ($catalog as $item) {
			$product_name = strtolower((string) $item['name']);
			if (false === strpos($product_name, $name) && false === strpos($name, $product_name)) {
				continue;
			}
			if (isset($seen[ $item['id'] ])) {
				continue;
			}

			$product = wc_get_product((int) $item['id']);
			if (! $product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
				continue;
			}

			$seen[ $item['id'] ] = true;
			$matches[] = mad_baits_format_ai_recommended_product($product, $item);
			if (count($matches) >= $limit) {
				return $matches;
			}
			break;
		}
	}

	$slugs = isset($ai_data['suggested_category_slugs']) && is_array($ai_data['suggested_category_slugs']) ? $ai_data['suggested_category_slugs'] : array();
	foreach ($slugs as $slug) {
		$slug = sanitize_title((string) $slug);
		if ('' === $slug) {
			continue;
		}
		foreach ($catalog as $item) {
			if (isset($seen[ $item['id'] ])) {
				continue;
			}
			$has_slug = false;
			foreach ((array) $item['categories'] as $cat) {
				if (! empty($cat['slug']) && sanitize_title((string) $cat['slug']) === $slug) {
					$has_slug = true;
					break;
				}
			}
			if (! $has_slug) {
				continue;
			}

			$product = wc_get_product((int) $item['id']);
			if (! $product || ! $product->is_purchasable() || ! $product->is_in_stock()) {
				continue;
			}

			$seen[ $item['id'] ] = true;
			$matches[] = mad_baits_format_ai_recommended_product($product, $item);
			if (count($matches) >= $limit) {
				return $matches;
			}
		}
	}

	return $matches;
}

/**
 * Build category level recommendations from slugs.
 *
 * @param array $slugs Category slug list.
 * @return array
 */
function mad_baits_build_category_links($slugs) {
	$links = array();
	foreach ((array) $slugs as $slug) {
		$slug = sanitize_title((string) $slug);
		if ('' === $slug || ! taxonomy_exists('product_cat')) {
			continue;
		}
		$term = get_term_by('slug', $slug, 'product_cat');
		if (! $term || is_wp_error($term)) {
			continue;
		}
		$url = get_term_link($term);
		if (is_wp_error($url) || ! is_string($url)) {
			continue;
		}
		$links[] = array(
			'name' => (string) $term->name,
			'url'  => $url,
		);
	}

	return $links;
}

/**
 * Ajax endpoint for AI bait finder.
 */
function mad_baits_ajax_ai_bait_finder() {
	check_ajax_referer('mad_baits_ai_bait_finder', 'nonce');

	$fields = array(
		'lake_type',
		'stock_level',
		'water_clarity',
		'season',
		'bottom_type',
		'weed_level',
		'session_length',
		'target_size',
		'approach',
		'notes',
	);

	$input = array();
	foreach ($fields as $field) {
		$raw = isset($_POST[ $field ]) ? wp_unslash($_POST[ $field ]) : '';
		$input[ $field ] = 'notes' === $field ? sanitize_textarea_field((string) $raw) : sanitize_text_field((string) $raw);
	}

	$required = array('lake_type', 'stock_level', 'water_clarity', 'season', 'bottom_type', 'weed_level', 'session_length', 'target_size', 'approach');
	foreach ($required as $req) {
		if ('' === $input[ $req ]) {
			wp_send_json_error(array('message' => __('Please complete all required fields.', 'mad-baits')));
		}
	}

	$catalog = mad_baits_get_ai_catalog(120);
	if (empty($catalog)) {
		wp_send_json_error(array('message' => __('No products available to recommend yet.', 'mad-baits')));
	}

	$api_key = mad_baits_get_openai_api_key();
	$model   = mad_baits_get_openai_model();
	$ai_data = array();
	$notice  = '';

	if ('' === $api_key) {
		$ai_data = mad_baits_build_fallback_recommendation($input);
		$notice  = __('AI key not configured. Showing intelligent fallback recommendations.', 'mad-baits');
	} else {
		$catalog_context = mad_baits_format_catalog_for_prompt($catalog);
		$prompt = "You are Mad Baits AI Bait Finder for carp anglers.\n"
			. "Only recommend products/categories from the provided WooCommerce catalog.\n"
			. "Never invent products.\n"
			. "Keep advice practical, safe and legal. Avoid harmful/environmentally unsafe advice.\n"
			. "Return JSON only with this exact shape:\n"
			. "{\n"
			. "  \"boilie_range\": \"string\",\n"
			. "  \"hookbait\": \"string\",\n"
			. "  \"addon\": \"string\",\n"
			. "  \"baiting_plan\": \"string\",\n"
			. "  \"why_suits_lake\": \"string\",\n"
			. "  \"suggested_products\": [\"Product Name\", \"...\"],\n"
			. "  \"suggested_category_slugs\": [\"slug\", \"...\"]\n"
			. "}\n\n"
			. "User session:\n"
			. wp_json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
			. "\n\nCatalog:\n"
			. $catalog_context;

		$request_body = array(
			'model' => $model,
			'input' => array(
				array(
					'role'    => 'system',
					'content' => array(
						array(
							'type' => 'input_text',
							'text' => 'You are an expert UK carp bait advisor for Mad Baits.',
						),
					),
				),
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'input_text',
							'text' => $prompt,
						),
					),
				),
			),
			'temperature'       => 0.4,
			'max_output_tokens' => 650,
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode($request_body),
			)
		);

		if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
			$ai_data = mad_baits_build_fallback_recommendation($input);
			$notice  = __('AI service is currently unavailable. Showing fallback recommendations.', 'mad-baits');
		} else {
			$body      = json_decode((string) wp_remote_retrieve_body($response), true);
			$text      = mad_baits_extract_openai_text(is_array($body) ? $body : array());
			$parsed    = mad_baits_parse_ai_json($text);
			$ai_data   = is_array($parsed) ? $parsed : array();

			if (empty($ai_data)) {
				$ai_data = mad_baits_build_fallback_recommendation($input);
				$notice  = __('AI response could not be parsed. Showing fallback recommendations.', 'mad-baits');
			}
		}
	}

	$defaults = mad_baits_build_fallback_recommendation($input);
	$ai_data  = wp_parse_args($ai_data, $defaults);

	$products      = mad_baits_match_recommended_products($catalog, $ai_data, 4);
	$category_links= mad_baits_build_category_links(isset($ai_data['suggested_category_slugs']) ? (array) $ai_data['suggested_category_slugs'] : array());

	wp_send_json_success(
		array(
			'notice'          => $notice,
			'recommendations' => array(
				'boilie_range'   => sanitize_text_field((string) $ai_data['boilie_range']),
				'hookbait'       => sanitize_text_field((string) $ai_data['hookbait']),
				'addon'          => sanitize_text_field((string) $ai_data['addon']),
				'pellets'        => sanitize_text_field((string) (isset($ai_data['pellets']) ? $ai_data['pellets'] : $ai_data['addon'])),
				'suggested_bundle' => sanitize_text_field((string) (isset($ai_data['suggested_bundle']) ? $ai_data['suggested_bundle'] : __('Mad Baits Session Bundle', 'mad-baits'))),
				'baiting_plan'   => sanitize_textarea_field((string) $ai_data['baiting_plan']),
				'why_suits_lake' => sanitize_textarea_field((string) $ai_data['why_suits_lake']),
			),
			'products'        => $products,
			'categories'      => $category_links,
			'disclaimer'      => __('Recommendations are a guide only. Adjust based on venue rules, fish activity and conditions.', 'mad-baits'),
		)
	);
}
add_action('wp_ajax_mad_baits_ai_bait_finder', 'mad_baits_ajax_ai_bait_finder');
add_action('wp_ajax_nopriv_mad_baits_ai_bait_finder', 'mad_baits_ajax_ai_bait_finder');

/**
 * Render AI bait finder form via shortcode.
 *
 * @return string
 */
function mad_baits_ai_bait_finder_shortcode() {
	ob_start();
	?>
	<section class="mad-ai-bait-finder" data-ai-bait-finder>
		<div class="mad-ai-bait-finder__shell">
			<div class="mad-ai-bait-finder__intro">
				<p class="section__kicker"><?php esc_html_e('AI Bait Finder', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Build Your Session Plan', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Tell us about your venue and approach, and get a practical Mad Baits recommendation plan.', 'mad-baits'); ?></p>
			</div>

			<form class="mad-ai-bait-finder__form" data-ai-bait-finder-form novalidate>
				<div class="mad-ai-bait-finder__step-progress" data-ai-progress>
					<span class="mad-ai-bait-finder__step-progress-label" data-ai-progress-label><?php esc_html_e('Step 1 of 6', 'mad-baits'); ?></span>
					<span class="mad-ai-bait-finder__step-progress-bar"><span data-ai-progress-bar></span></span>
				</div>
				<div class="mad-ai-bait-finder__answers" data-ai-answers hidden aria-live="polite">
					<strong><?php esc_html_e('Your answers', 'mad-baits'); ?></strong>
					<ul data-ai-answers-list></ul>
				</div>
				<div class="mad-ai-bait-finder__grid">
					<label data-ai-step="1"><span><?php esc_html_e('Session Length', 'mad-baits'); ?></span>
						<select name="session_length" required>
							<option value=""><?php esc_html_e('Select length', 'mad-baits'); ?></option>
							<option value="quick_overnight"><?php esc_html_e('Quick Overnight', 'mad-baits'); ?></option>
							<option value="24hr"><?php esc_html_e('24hr Session', 'mad-baits'); ?></option>
							<option value="48hr"><?php esc_html_e('48hr Session', 'mad-baits'); ?></option>
							<option value="weekend"><?php esc_html_e('Weekend Campaign', 'mad-baits'); ?></option>
							<option value="long_campaign"><?php esc_html_e('Long Campaign', 'mad-baits'); ?></option>
						</select>
					</label>
					<label data-ai-step="1"><span><?php esc_html_e('Water Type', 'mad-baits'); ?></span><input type="text" name="lake_type" required placeholder="<?php esc_attr_e('Syndicate, day ticket, big pit...', 'mad-baits'); ?>"></label>

					<label data-ai-step="2"><span><?php esc_html_e('Season', 'mad-baits'); ?></span><input type="text" name="season" required placeholder="<?php esc_attr_e('Spring, Summer, Autumn, Winter', 'mad-baits'); ?>"></label>
					<label data-ai-step="2"><span><?php esc_html_e('Pressure Level', 'mad-baits'); ?></span>
						<select name="stock_level" required>
							<option value=""><?php esc_html_e('Select pressure', 'mad-baits'); ?></option>
							<option value="low_pressure"><?php esc_html_e('Low Pressure', 'mad-baits'); ?></option>
							<option value="mixed_pressure"><?php esc_html_e('Mixed Pressure', 'mad-baits'); ?></option>
							<option value="high_pressure"><?php esc_html_e('High Pressure', 'mad-baits'); ?></option>
						</select>
					</label>

					<label data-ai-step="3"><span><?php esc_html_e('Water Clarity', 'mad-baits'); ?></span><input type="text" name="water_clarity" required placeholder="<?php esc_attr_e('Clear, coloured, heavy algae...', 'mad-baits'); ?>"></label>
					<label data-ai-step="3"><span><?php esc_html_e('Bottom Type', 'mad-baits'); ?></span><input type="text" name="bottom_type" required placeholder="<?php esc_attr_e('Silt, clay, gravel, chod...', 'mad-baits'); ?>"></label>

					<label data-ai-step="4"><span><?php esc_html_e('Target Fish', 'mad-baits'); ?></span><input type="text" name="target_size" required placeholder="<?php esc_attr_e('Size, strain, old originals...', 'mad-baits'); ?>"></label>
					<label data-ai-step="4"><span><?php esc_html_e('Weed Level', 'mad-baits'); ?></span><input type="text" name="weed_level" required placeholder="<?php esc_attr_e('Low, moderate, heavy', 'mad-baits'); ?>"></label>

					<label data-ai-step="5"><span><?php esc_html_e('Approach', 'mad-baits'); ?></span>
						<select name="approach" required>
							<option value=""><?php esc_html_e('Select approach', 'mad-baits'); ?></option>
							<option value="boilies"><?php esc_html_e('Boilies', 'mad-baits'); ?></option>
							<option value="hookbaits"><?php esc_html_e('Hookbaits', 'mad-baits'); ?></option>
							<option value="particles"><?php esc_html_e('Particles', 'mad-baits'); ?></option>
							<option value="bundle"><?php esc_html_e('Bundle', 'mad-baits'); ?></option>
						</select>
					</label>
					<label data-ai-step="5"><span><?php esc_html_e('Confidence Style', 'mad-baits'); ?></span>
						<select name="confidence_style">
							<option value="instant"><?php esc_html_e('Instant Hits', 'mad-baits'); ?></option>
							<option value="long_campaign"><?php esc_html_e('Long Campaign', 'mad-baits'); ?></option>
						</select>
					</label>

					<label class="mad-ai-bait-finder__notes" data-ai-step="6"><span><?php esc_html_e('Extra Notes', 'mad-baits'); ?></span><textarea name="notes" rows="3"></textarea></label>
				</div>
				<div class="mad-ai-bait-finder__actions">
					<button class="mad-button mad-button--ghost mad-button--small" type="button" data-ai-prev hidden><?php esc_html_e('Back', 'mad-baits'); ?></button>
					<button class="mad-button mad-button--small" type="button" data-ai-next><?php esc_html_e('Next', 'mad-baits'); ?></button>
					<button class="mad-button" type="submit"><?php esc_html_e('Build My Session Plan', 'mad-baits'); ?></button>
					<p class="mad-ai-bait-finder__status" data-ai-status aria-live="polite"></p>
				</div>
			</form>

			<div class="mad-ai-bait-finder__result" data-ai-result hidden>
				<p class="mad-ai-bait-finder__notice" data-ai-notice></p>
				<div class="mad-ai-bait-finder__recommendations">
					<div><strong><?php esc_html_e('Boilie / Range:', 'mad-baits'); ?></strong> <span data-ai-boilie></span></div>
					<div><strong><?php esc_html_e('Hookbait:', 'mad-baits'); ?></strong> <span data-ai-hookbait></span></div>
					<div><strong><?php esc_html_e('Add-on / Liquid:', 'mad-baits'); ?></strong> <span data-ai-addon></span></div>
					<div><strong><?php esc_html_e('Pellets:', 'mad-baits'); ?></strong> <span data-ai-pellets></span></div>
					<div><strong><?php esc_html_e('Suggested Bundle:', 'mad-baits'); ?></strong> <span data-ai-bundle></span></div>
					<div><strong><?php esc_html_e('Baiting Plan:', 'mad-baits'); ?></strong> <span data-ai-plan></span></div>
					<div><strong><?php esc_html_e('Why This Suits The Lake:', 'mad-baits'); ?></strong> <span data-ai-why></span></div>
				</div>
				<div class="mad-ai-bait-finder__products" data-ai-products></div>
				<div class="mad-ai-bait-finder__result-actions" data-ai-result-actions hidden>
					<button class="mad-button mad-button--small" type="button" data-ai-add-session><?php esc_html_e('Add Recommended Session To Basket', 'mad-baits'); ?></button>
				</div>
				<p class="mad-ai-bait-finder__fallback" data-ai-fallback hidden><?php esc_html_e('No exact product match found. Browse boilies and bundles, or adjust your answers and try again.', 'mad-baits'); ?></p>
				<div class="mad-ai-bait-finder__categories" data-ai-categories></div>
				<p class="mad-ai-bait-finder__disclaimer" data-ai-disclaimer></p>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode('mad_baits_ai_bait_finder', 'mad_baits_ai_bait_finder_shortcode');
// Pages -> Add New -> Template: AI Bait Finder
add_shortcode('mad_baits_ai_finder', 'mad_baits_ai_bait_finder_shortcode');

/**
 * Render Mad Baits TV grid via shortcode.
 *
 * @return string
 */
function mad_baits_tv_shortcode() {
	ob_start();
	mad_baits_render_mad_baits_tv_component(
		array(
			'section_class' => 'section section--contrast mad-baits-tv mad-baits-tv--shortcode',
			'show_header'   => true,
			'show_cta'      => false,
			'show_filters'  => true,
			'grid_limit'    => 0,
			'headline'      => __('Mad Baits TV', 'mad-baits'),
			'kicker'        => __('Mad Baits TV', 'mad-baits'),
		)
	);
	return (string) ob_get_clean();
}
add_shortcode('mad_baits_tv', 'mad_baits_tv_shortcode');

/**
 * Register Mad Baits TV custom post type.
 */
function mad_baits_register_video_cpt() {
	$labels = array(
		'name'               => __('Mad Baits TV Videos', 'mad-baits'),
		'singular_name'      => __('Mad Baits TV Video', 'mad-baits'),
		'add_new'            => __('Add Video', 'mad-baits'),
		'add_new_item'       => __('Add New Mad Baits TV Video', 'mad-baits'),
		'edit_item'          => __('Edit Mad Baits TV Video', 'mad-baits'),
		'new_item'           => __('New Mad Baits TV Video', 'mad-baits'),
		'view_item'          => __('View Mad Baits TV Video', 'mad-baits'),
		'search_items'       => __('Search Mad Baits TV Videos', 'mad-baits'),
		'not_found'          => __('No Mad Baits TV videos found', 'mad-baits'),
		'not_found_in_trash' => __('No Mad Baits TV videos found in bin', 'mad-baits'),
		'menu_name'          => __('Mad Baits TV Videos', 'mad-baits'),
	);

	register_post_type(
		'mad_baits_video',
		array(
			'labels'             => $labels,
			'public'             => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-video-alt3',
			'has_archive'        => false,
			'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
			'publicly_queryable' => true,
			'show_in_nav_menus'  => true,
		)
	);
}
add_action('init', 'mad_baits_register_video_cpt');

/**
 * Add Mad Baits TV video meta box.
 */
function mad_baits_add_video_meta_box() {
	add_meta_box(
		'mad_baits_tv_video_meta',
		__('Video Details', 'mad-baits'),
		'mad_baits_render_video_meta_box',
		'mad_baits_video',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes', 'mad_baits_add_video_meta_box');

/**
 * Render Mad Baits TV video meta box fields.
 *
 * @param WP_Post $post Post object.
 */
function mad_baits_render_video_meta_box($post) {
	if (! $post instanceof WP_Post) {
		return;
	}

	$meta_keys          = mad_baits_get_mad_baits_tv_meta_keys();
	$youtube_url        = get_post_meta($post->ID, $meta_keys['youtube_url'], true);
	$category           = get_post_meta($post->ID, $meta_keys['category'], true);
	$featured           = get_post_meta($post->ID, $meta_keys['featured'], true);
	$thumbnail_url      = get_post_meta($post->ID, $meta_keys['thumbnail_url'], true);
	$card_description   = get_post_meta($post->ID, $meta_keys['card_description'], true);
	$categories         = mad_baits_get_mad_baits_tv_categories();
	$fallback_category  = (string) array_key_first($categories);
	$current_category   = is_string($category) ? sanitize_key($category) : $fallback_category;
	$current_category   = isset($categories[ $current_category ]) ? $current_category : $fallback_category;
	$featured_is_active = ! empty($featured);

	wp_nonce_field('mad_baits_save_video_meta', 'mad_baits_video_nonce');
	?>
	<p>
		<label for="mad-tv-youtube-url"><strong><?php esc_html_e('YouTube URL', 'mad-baits'); ?></strong></label><br />
		<input id="mad-tv-youtube-url" type="url" class="widefat" name="mad_tv_youtube_url" value="<?php echo esc_attr((string) $youtube_url); ?>" placeholder="<?php esc_attr_e('https://www.youtube.com/watch?v=...', 'mad-baits'); ?>" />
	</p>
	<p>
		<label for="mad-tv-category"><strong><?php esc_html_e('Category', 'mad-baits'); ?></strong></label><br />
		<select id="mad-tv-category" class="widefat" name="mad_tv_category">
			<?php foreach ($categories as $category_key => $category_label) : ?>
				<option value="<?php echo esc_attr((string) $category_key); ?>" <?php selected($current_category, $category_key); ?>>
					<?php echo esc_html((string) $category_label); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="mad-tv-thumbnail-url"><strong><?php esc_html_e('Custom thumbnail URL (optional)', 'mad-baits'); ?></strong></label><br />
		<input id="mad-tv-thumbnail-url" type="url" class="widefat" name="mad_tv_thumbnail_url" value="<?php echo esc_attr((string) $thumbnail_url); ?>" placeholder="<?php esc_attr_e('Leave blank to use Featured Image or YouTube thumbnail', 'mad-baits'); ?>" />
	</p>
	<p>
		<label for="mad-tv-card-description"><strong><?php esc_html_e('Card description override (optional)', 'mad-baits'); ?></strong></label><br />
		<textarea id="mad-tv-card-description" class="widefat" name="mad_tv_card_description" rows="3" placeholder="<?php esc_attr_e('Leave blank to use Excerpt, then post content excerpt.', 'mad-baits'); ?>"><?php echo esc_textarea((string) $card_description); ?></textarea>
	</p>
	<p>
		<label for="mad-tv-featured">
			<input id="mad-tv-featured" type="checkbox" name="mad_tv_featured" value="1" <?php checked($featured_is_active); ?> />
			<?php esc_html_e('Feature this video', 'mad-baits'); ?>
		</label>
	</p>
	<?php
}

/**
 * Save Mad Baits TV video meta fields.
 *
 * @param int $post_id Post ID.
 */
function mad_baits_save_video_meta($post_id) {
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	if ('mad_baits_video' !== get_post_type($post_id)) {
		return;
	}

	if (! isset($_POST['mad_baits_video_nonce'])) {
		return;
	}

	$nonce = sanitize_text_field(wp_unslash($_POST['mad_baits_video_nonce']));
	if (! wp_verify_nonce($nonce, 'mad_baits_save_video_meta')) {
		return;
	}

	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$meta_keys  = mad_baits_get_mad_baits_tv_meta_keys();
	$categories = mad_baits_get_mad_baits_tv_categories();
	$category   = isset($_POST['mad_tv_category']) ? sanitize_key(wp_unslash($_POST['mad_tv_category'])) : '';
	if (! isset($categories[ $category ])) {
		$category = (string) array_key_first($categories);
	}

	$fields = array(
		'youtube_url'      => isset($_POST['mad_tv_youtube_url']) ? esc_url_raw(wp_unslash($_POST['mad_tv_youtube_url'])) : '',
		'category'         => $category,
		'featured'         => isset($_POST['mad_tv_featured']) ? '1' : '',
		'thumbnail_url'    => isset($_POST['mad_tv_thumbnail_url']) ? esc_url_raw(wp_unslash($_POST['mad_tv_thumbnail_url'])) : '',
		'card_description' => isset($_POST['mad_tv_card_description']) ? sanitize_text_field(wp_unslash($_POST['mad_tv_card_description'])) : '',
	);

	foreach ($fields as $field_key => $value) {
		if ('' === $value) {
			delete_post_meta($post_id, $meta_keys[ $field_key ]);
			continue;
		}
		update_post_meta($post_id, $meta_keys[ $field_key ], $value);
	}
}
add_action('save_post_mad_baits_video', 'mad_baits_save_video_meta');

/**
 * Catch report meta key map.
 *
 * @return array<string, string>
 */
function mad_baits_get_catch_report_meta_keys() {
	return array(
		'fish_weight'  => '_mad_catch_fish_weight',
		'venue'        => '_mad_catch_venue',
		'bait_used'    => '_mad_catch_bait_used',
		'product_link' => '_mad_catch_product_link',
		'product_ids'  => '_mad_catch_product_ids',
	);
}

/**
 * Register Catch Reports custom post type.
 */
function mad_baits_register_catch_report_cpt() {
	$labels = array(
		'name'               => __('Catch Reports', 'mad-baits'),
		'singular_name'      => __('Catch Report', 'mad-baits'),
		'add_new'            => __('Add Catch Report', 'mad-baits'),
		'add_new_item'       => __('Add New Catch Report', 'mad-baits'),
		'edit_item'          => __('Edit Catch Report', 'mad-baits'),
		'new_item'           => __('New Catch Report', 'mad-baits'),
		'view_item'          => __('View Catch Report', 'mad-baits'),
		'search_items'       => __('Search Catch Reports', 'mad-baits'),
		'not_found'          => __('No catch reports found', 'mad-baits'),
		'not_found_in_trash' => __('No catch reports found in bin', 'mad-baits'),
		'menu_name'          => __('Catch Reports', 'mad-baits'),
	);

	register_post_type(
		'catch_report',
		array(
			'labels'             => $labels,
			'public'             => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-format-gallery',
			'has_archive'        => false,
			'rewrite'            => array('slug' => 'catch-reports'),
			'supports'           => array('title', 'editor', 'thumbnail', 'excerpt'),
			'publicly_queryable' => true,
			'show_in_nav_menus'  => true,
		)
	);
}
add_action('init', 'mad_baits_register_catch_report_cpt');

/**
 * Add catch report meta box.
 */
function mad_baits_add_catch_report_meta_box() {
	add_meta_box(
		'mad_baits_catch_report_meta',
		__('Catch Details', 'mad-baits'),
		'mad_baits_render_catch_report_meta_box',
		'catch_report',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes', 'mad_baits_add_catch_report_meta_box');

/**
 * Render catch report meta box fields.
 *
 * @param WP_Post $post Post object.
 */
function mad_baits_render_catch_report_meta_box($post) {
	if (! $post instanceof WP_Post) {
		return;
	}

	$meta_keys    = mad_baits_get_catch_report_meta_keys();
	$fish_weight  = get_post_meta($post->ID, $meta_keys['fish_weight'], true);
	$venue        = get_post_meta($post->ID, $meta_keys['venue'], true);
	$bait_used    = get_post_meta($post->ID, $meta_keys['bait_used'], true);
	$product_link = get_post_meta($post->ID, $meta_keys['product_link'], true);
	$product_ids  = mad_baits_get_catch_report_product_ids($post->ID);
	$product_query_args = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => 120,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'fields'                 => 'ids',
	);

	if (function_exists('mad_baits_supplier_merge_wp_query_args')) {
		$product_query_args = mad_baits_supplier_merge_wp_query_args($product_query_args);
	}

	$product_posts = get_posts($product_query_args);

	if (function_exists('mad_baits_supplier_filter_product_ids')) {
		$product_posts = mad_baits_supplier_filter_product_ids((array) $product_posts);
	}

	wp_nonce_field('mad_baits_save_catch_report_meta', 'mad_baits_catch_report_nonce');
	?>
	<p>
		<label for="mad-catch-fish-weight"><strong><?php esc_html_e('Fish weight', 'mad-baits'); ?></strong></label><br />
		<input id="mad-catch-fish-weight" type="text" class="widefat" name="mad_catch_fish_weight" value="<?php echo esc_attr((string) $fish_weight); ?>" placeholder="<?php esc_attr_e('e.g. 32lb 8oz', 'mad-baits'); ?>" />
	</p>
	<p>
		<label for="mad-catch-venue"><strong><?php esc_html_e('Venue / lake type', 'mad-baits'); ?></strong></label><br />
		<input id="mad-catch-venue" type="text" class="widefat" name="mad_catch_venue" value="<?php echo esc_attr((string) $venue); ?>" placeholder="<?php esc_attr_e('e.g. Day-ticket gravel pit', 'mad-baits'); ?>" />
	</p>
	<p>
		<label for="mad-catch-bait-used"><strong><?php esc_html_e('Bait used', 'mad-baits'); ?></strong></label><br />
		<input id="mad-catch-bait-used" type="text" class="widefat" name="mad_catch_bait_used" value="<?php echo esc_attr((string) $bait_used); ?>" placeholder="<?php esc_attr_e('e.g. Nutz Plus Wafters', 'mad-baits'); ?>" />
	</p>
	<p>
		<label for="mad-catch-product-link"><strong><?php esc_html_e('Product link', 'mad-baits'); ?></strong></label><br />
		<input id="mad-catch-product-link" type="url" class="widefat" name="mad_catch_product_link" value="<?php echo esc_attr((string) $product_link); ?>" placeholder="<?php esc_attr_e('https://example.com/product/...', 'mad-baits'); ?>" />
	</p>
	<p>
		<label for="mad-catch-product-ids"><strong><?php esc_html_e('Products used (WooCommerce)', 'mad-baits'); ?></strong></label><br />
		<select id="mad-catch-product-ids" class="widefat" name="mad_catch_product_ids[]" multiple size="8">
			<?php foreach ((array) $product_posts as $product_post_id) : ?>
				<?php
				$product_post_id = absint($product_post_id);
				if (! $product_post_id) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr((string) $product_post_id); ?>"<?php selected(in_array($product_post_id, $product_ids, true), true); ?>>
					<?php echo esc_html((string) get_the_title($product_post_id)); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<small><?php esc_html_e('Hold Cmd/Ctrl to select multiple products.', 'mad-baits'); ?></small>
	</p>
	<?php
}

/**
 * Save catch report meta fields.
 *
 * @param int $post_id Post ID.
 */
function mad_baits_save_catch_report_meta($post_id) {
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}

	if (! isset($_POST['mad_baits_catch_report_nonce'])) {
		return;
	}

	$nonce = sanitize_text_field(wp_unslash($_POST['mad_baits_catch_report_nonce']));
	if (! wp_verify_nonce($nonce, 'mad_baits_save_catch_report_meta')) {
		return;
	}

	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	if ('catch_report' !== get_post_type($post_id)) {
		return;
	}

	$meta_keys = mad_baits_get_catch_report_meta_keys();
	$fields    = array(
		'fish_weight'  => isset($_POST['mad_catch_fish_weight']) ? sanitize_text_field(wp_unslash($_POST['mad_catch_fish_weight'])) : '',
		'venue'        => isset($_POST['mad_catch_venue']) ? sanitize_text_field(wp_unslash($_POST['mad_catch_venue'])) : '',
		'bait_used'    => isset($_POST['mad_catch_bait_used']) ? sanitize_text_field(wp_unslash($_POST['mad_catch_bait_used'])) : '',
		'product_link' => isset($_POST['mad_catch_product_link']) ? esc_url_raw(wp_unslash($_POST['mad_catch_product_link'])) : '',
	);

	foreach ($fields as $field_key => $value) {
		if ('' === $value) {
			delete_post_meta($post_id, $meta_keys[ $field_key ]);
			continue;
		}
		update_post_meta($post_id, $meta_keys[ $field_key ], $value);
	}

	$product_ids_input = isset($_POST['mad_catch_product_ids']) && is_array($_POST['mad_catch_product_ids'])
		? array_map('absint', wp_unslash($_POST['mad_catch_product_ids']))
		: array();
	$product_ids_input = array_values(array_filter(array_unique($product_ids_input)));

	if (empty($product_ids_input)) {
		delete_post_meta($post_id, $meta_keys['product_ids']);
	} else {
		update_post_meta($post_id, $meta_keys['product_ids'], $product_ids_input);
	}
}
add_action('save_post_catch_report', 'mad_baits_save_catch_report_meta');

/**
 * Resolve catch reports page URL.
 *
 * @return string
 */
function mad_baits_get_catch_reports_url() {
	$template_page = get_pages(
		array(
			'post_status' => 'publish',
			'number'      => 1,
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'template-catch-reports.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if (! empty($template_page) && isset($template_page[0]) && $template_page[0] instanceof WP_Post) {
		$template_link = get_permalink($template_page[0]);
		if (is_string($template_link) && '' !== $template_link) {
			return $template_link;
		}
	}

	return mad_baits_get_page_url('catch-reports', 'catch-reports');
}

/**
 * Get catch report meta by logical key.
 *
 * @param int    $post_id Catch report post ID.
 * @param string $field_key Meta field key alias.
 * @return string
 */
function mad_baits_get_catch_report_meta($post_id, $field_key) {
	$meta_keys = mad_baits_get_catch_report_meta_keys();
	if (! isset($meta_keys[ $field_key ])) {
		return '';
	}

	$value = get_post_meta(absint($post_id), $meta_keys[ $field_key ], true);
	return is_string($value) ? trim($value) : '';
}

/**
 * Normalize stored catch report product ID meta values.
 *
 * @param mixed $raw Raw post meta value.
 * @return int[]
 */
function mad_baits_parse_catch_report_product_ids_meta($raw) {
	if (is_array($raw)) {
		return array_values(array_filter(array_unique(array_map('absint', $raw))));
	}

	if (is_numeric($raw)) {
		$single_id = absint($raw);
		return $single_id > 0 ? array($single_id) : array();
	}

	if (! is_string($raw)) {
		return array();
	}

	$raw = trim($raw);
	if ('' === $raw) {
		return array();
	}

	$maybe_unserialized = maybe_unserialize($raw);
	if (is_array($maybe_unserialized)) {
		return array_values(array_filter(array_unique(array_map('absint', $maybe_unserialized))));
	}

	if (is_numeric($raw)) {
		$single_id = absint($raw);
		return $single_id > 0 ? array($single_id) : array();
	}

	return array_values(
		array_filter(
			array_unique(
				array_map(
					'absint',
					array_map('trim', explode(',', $raw))
				)
			)
		)
	);
}

/**
 * Find a published product ID by exact post title.
 *
 * @param string $title Product title.
 * @return int
 */
function mad_baits_get_product_id_by_exact_title($title) {
	$title = trim((string) $title);
	if ('' === $title) {
		return 0;
	}

	global $wpdb;

	$product_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_title = %s LIMIT 1",
			$title
		)
	);

	return $product_id > 0 ? $product_id : 0;
}

/**
 * Resolve linked WooCommerce product IDs for a catch report.
 *
 * @param int $post_id Catch report post ID.
 * @return int[]
 */
function mad_baits_get_catch_report_product_ids($post_id) {
	$post_id     = absint($post_id);
	$meta_keys   = mad_baits_get_catch_report_meta_keys();
	$product_ids = array();
	$meta_keys_to_check = array('_mad_catch_products_used');

	if (! empty($meta_keys['product_ids'])) {
		$meta_keys_to_check[] = (string) $meta_keys['product_ids'];
	}

	foreach (array_values(array_unique($meta_keys_to_check)) as $meta_key) {
		$raw_ids = get_post_meta($post_id, $meta_key, true);
		$parsed  = mad_baits_parse_catch_report_product_ids_meta($raw_ids);
		if (! empty($parsed)) {
			$product_ids = array_merge($product_ids, $parsed);
		}
	}

	$product_ids = array_values(array_filter(array_unique(array_map('absint', $product_ids))));
	if (! empty($product_ids)) {
		return $product_ids;
	}

	// Fallback: legacy single product URL field.
	$product_link = mad_baits_get_catch_report_meta($post_id, 'product_link');
	if ('' !== $product_link) {
		$product_id = absint(url_to_postid($product_link));
		if ($product_id > 0 && 'product' === get_post_type($product_id)) {
			return array($product_id);
		}
	}

	// Fallback: exact title match from bait text (comma-separated supported).
	$bait_used = mad_baits_get_catch_report_meta($post_id, 'bait_used');
	if ('' === $bait_used) {
		return array();
	}

	$bait_names = preg_split('/\s*,\s*/', $bait_used);
	if (! is_array($bait_names)) {
		$bait_names = array($bait_used);
	}

	foreach ($bait_names as $bait_name) {
		$bait_name = trim((string) $bait_name);
		if ('' === $bait_name) {
			continue;
		}

		$matched_id = mad_baits_get_product_id_by_exact_title($bait_name);
		if ($matched_id > 0) {
			$product_ids[] = $matched_id;
		}
	}

	return array_values(array_filter(array_unique(array_map('absint', $product_ids))));
}

/**
 * Resolve published WooCommerce products used on a catch report.
 *
 * @param int $post_id Catch report post ID.
 * @return WC_Product[]
 */
function mad_baits_get_catch_report_products_used($post_id) {
	if (! function_exists('wc_get_product')) {
		return array();
	}

	$products = array();
	foreach (mad_baits_get_catch_report_product_ids($post_id) as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || 'publish' !== get_post_status($product_id)) {
			continue;
		}

		$product = wc_get_product($product_id);
		if ($product instanceof WC_Product) {
			$products[] = $product;
		}
	}

	return $products;
}

/**
 * Render a catch report product card with excerpt and dual CTAs.
 *
 * @param int $product_id Product ID.
 * @return void
 */
function mad_baits_render_catch_report_product_card($product_id = 0) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('wc_get_product')) {
		return;
	}

	$product = wc_get_product($product_id);
	if (! $product instanceof WC_Product || 'publish' !== get_post_status($product_id)) {
		return;
	}

	$product_url     = $product->get_permalink();
	$product_name    = $product->get_name();
	$thumbnail_id    = $product->get_image_id();
	$short_description = wp_strip_all_tags((string) $product->get_short_description());
	if ('' === $short_description) {
		$short_description = wp_strip_all_tags((string) $product->get_description());
	}

	$button_classes = array('button', 'mad-button', 'mad-button--small');
	$button_attrs   = array(
		'aria-label' => $product->add_to_cart_description(),
		'rel'        => 'nofollow',
	);

	if ($product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock() && $product->is_type('simple')) {
		$button_classes[] = 'add_to_cart_button';
		$button_classes[] = 'ajax_add_to_cart';
		$button_classes[] = 'product_type_simple';
		$button_attrs['data-quantity']    = 1;
		$button_attrs['data-product_id']  = $product->get_id();
		$button_attrs['data-product_sku'] = $product->get_sku();
	}
	?>
	<article class="mad-product-card single-catch-report__product-card" data-mad-product-id="<?php echo esc_attr((string) $product_id); ?>">
		<a class="mad-product-card__image-link single-catch-report__product-image-link" href="<?php echo esc_url($product_url); ?>">
			<?php if ($thumbnail_id && function_exists('mad_baits_get_product_card_image_html')) : ?>
				<?php
				echo mad_baits_get_product_card_image_html(
					$product_id,
					array(
						'class' => 'mad-product-card__image single-catch-report__product-image',
						'alt'   => $product_name,
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php elseif ($thumbnail_id) : ?>
				<?php
				echo wp_get_attachment_image(
					$thumbnail_id,
					'woocommerce_thumbnail',
					false,
					array(
						'class'    => 'mad-product-card__image single-catch-report__product-image',
						'loading'  => 'lazy',
						'decoding' => 'async',
						'alt'      => $product_name,
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php else : ?>
				<img
					class="mad-product-card__image mad-product-card__image--placeholder single-catch-report__product-image-fallback"
					src="<?php echo esc_url(get_theme_file_uri('/assets/img/CUP-LOGOv2-1.svg')); ?>"
					loading="lazy"
					alt="<?php esc_attr_e('Mad Baits logo', 'mad-baits'); ?>"
				/>
			<?php endif; ?>
		</a>
		<div class="mad-product-card__content single-catch-report__product-content">
			<h3 class="mad-product-card__title">
				<a href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($product_name); ?></a>
			</h3>
			<?php if ('' !== $short_description) : ?>
				<p class="single-catch-report__product-excerpt"><?php echo esc_html(wp_trim_words($short_description, 22)); ?></p>
			<?php endif; ?>
			<div class="mad-product-card__price single-catch-report__product-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
			<div class="single-catch-report__product-actions">
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($product_url); ?>"><?php esc_html_e('View Product', 'mad-baits'); ?></a>
				<?php if ($product->is_type('variable')) : ?>
					<a class="mad-button mad-button--small" href="<?php echo esc_url($product_url); ?>"><?php esc_html_e('View Options', 'mad-baits'); ?></a>
				<?php elseif ($product->is_purchasable() && $product->is_in_stock()) : ?>
					<a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" <?php echo mad_baits_implode_html_attributes($button_attrs); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php esc_html_e('Add To Basket', 'mad-baits'); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Get recent catch report IDs.
 *
 * @param int $limit Number of rows to return.
 * @return int[]
 */
function mad_baits_get_recent_catch_report_ids($limit = 6) {
	$query = new WP_Query(
		array(
			'post_type'              => 'catch_report',
			'post_status'            => 'publish',
			'posts_per_page'         => max(1, absint($limit)),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		)
	);

	$ids = $query->posts;
	wp_reset_postdata();

	return array_values(array_filter(array_map('absint', is_array($ids) ? $ids : array())));
}

/**
 * Resolve related catch reports for a product.
 *
 * @param int $product_id Product ID.
 * @param int $limit Max number of reports.
 * @return int[]
 */
function mad_baits_get_related_catch_reports_for_product($product_id, $limit = 3) {
	$product_id = absint($product_id);
	$limit      = max(1, absint($limit));
	if (! $product_id) {
		return array();
	}

	$product_permalink = get_permalink($product_id);
	if (! is_string($product_permalink) || '' === $product_permalink) {
		return array();
	}

	$meta_keys = mad_baits_get_catch_report_meta_keys();
	$matches   = array();
	$slug_hint = basename((string) wp_parse_url($product_permalink, PHP_URL_PATH));
	$slug_hint = sanitize_title((string) $slug_hint);

	$product_link_conditions = array(
		array(
			'key'     => $meta_keys['product_ids'],
			'value'   => 'i:' . $product_id . ';',
			'compare' => 'LIKE',
		),
		array(
			'key'     => $meta_keys['product_link'],
			'value'   => $product_permalink,
			'compare' => '=',
		),
		array(
			'key'     => $meta_keys['product_link'],
			'value'   => untrailingslashit($product_permalink),
			'compare' => '=',
		),
	);
	if ('' !== $slug_hint) {
		$product_link_conditions[] = array(
			'key'     => $meta_keys['product_link'],
			'value'   => $slug_hint,
			'compare' => 'LIKE',
		);
	}

	$product_link_query = new WP_Query(
		array(
			'post_type'              => 'catch_report',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'fields'                 => 'ids',
			'meta_query'             => array_merge(array('relation' => 'OR'), $product_link_conditions), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	$matches = array_values(array_filter(array_map('absint', (array) $product_link_query->posts)));
	wp_reset_postdata();

	if (count($matches) >= $limit) {
		return array_slice(array_values(array_unique($matches)), 0, $limit);
	}

	$bait_terms = array();
	if (function_exists('wc_get_product')) {
		$product = wc_get_product($product_id);
		if ($product) {
			$bait_terms[] = $product->get_name();
		}
	}

	$product_terms = get_the_terms($product_id, 'product_cat');
	if (is_array($product_terms)) {
		foreach ($product_terms as $product_term) {
			if (! $product_term instanceof WP_Term) {
				continue;
			}
			$bait_terms[] = (string) $product_term->name;
		}
	}

	$bait_terms = array_values(array_unique(array_filter(array_map('sanitize_text_field', $bait_terms))));
	if (empty($bait_terms)) {
		return array_slice(array_values(array_unique($matches)), 0, $limit);
	}

	$bait_conditions = array();
	foreach ($bait_terms as $term_name) {
		if ('' === $term_name) {
			continue;
		}

		$bait_conditions[] = array(
			'key'     => $meta_keys['bait_used'],
			'value'   => $term_name,
			'compare' => 'LIKE',
		);
	}

	if (empty($bait_conditions)) {
		return array_slice(array_values(array_unique($matches)), 0, $limit);
	}

	$fallback_query = new WP_Query(
		array(
			'post_type'              => 'catch_report',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => $matches,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'fields'                 => 'ids',
			'meta_query'             => array_merge(array('relation' => 'OR'), $bait_conditions), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	$fallback_ids = array_values(array_filter(array_map('absint', (array) $fallback_query->posts)));
	wp_reset_postdata();

	$merged_ids = array_values(array_unique(array_merge($matches, $fallback_ids)));
	return array_slice($merged_ids, 0, $limit);
}

/**
 * Render a premium catch report card.
 *
 * @param int   $post_id Catch report post ID.
 * @param array $args Optional render arguments.
 */
function mad_baits_render_catch_report_card($post_id, $args = array()) {
	$post_id = absint($post_id);
	$post    = get_post($post_id);
	if (! $post instanceof WP_Post || 'catch_report' !== $post->post_type) {
		return;
	}

	$args = wp_parse_args(
		$args,
		array(
			'class'        => '',
			'image_size'   => 'large',
			'show_excerpt' => false,
		)
	);

	$card_classes = array('mad-catch-card');
	if (! empty($args['class']) && is_string($args['class'])) {
		$card_classes[] = sanitize_html_class($args['class']);
	}

	$fish_weight  = mad_baits_get_catch_report_meta($post_id, 'fish_weight');
	$venue        = mad_baits_get_catch_report_meta($post_id, 'venue');
	$bait_used    = mad_baits_get_catch_report_meta($post_id, 'bait_used');
	$product_link = mad_baits_get_catch_report_meta($post_id, 'product_link');
	$report_link  = get_permalink($post_id);
	$angler_name  = get_the_title($post_id);
	$image_size   = is_string($args['image_size']) && '' !== $args['image_size'] ? $args['image_size'] : 'large';

	$excerpt = '';
	if (! empty($args['show_excerpt'])) {
		$raw_excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags((string) $post->post_content), 20);
		$excerpt     = is_string($raw_excerpt) ? trim($raw_excerpt) : '';
	}
	?>
	<article class="<?php echo esc_attr(implode(' ', array_filter($card_classes))); ?>">
		<a class="mad-catch-card__media" href="<?php echo esc_url((string) $report_link); ?>">
			<?php if (has_post_thumbnail($post_id)) : ?>
				<?php
				echo get_the_post_thumbnail(
					$post_id,
					$image_size,
					array(
						'class'   => 'mad-catch-card__image',
						'loading' => 'lazy',
						'alt'     => $angler_name,
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php else : ?>
				<span class="mad-catch-card__image-fallback" aria-hidden="true"></span>
			<?php endif; ?>
			<?php if ('' !== $bait_used) : ?>
				<span class="mad-catch-card__bait-label"><?php echo esc_html($bait_used); ?></span>
			<?php endif; ?>
		</a>
		<div class="mad-catch-card__content">
			<p class="mad-catch-card__eyebrow"><?php esc_html_e('Recently Caught On', 'mad-baits'); ?></p>
			<h3 class="mad-catch-card__title"><a href="<?php echo esc_url((string) $report_link); ?>"><?php echo esc_html($angler_name); ?></a></h3>
			<ul class="mad-catch-card__stats">
				<?php if ('' !== $fish_weight) : ?>
					<li><span><?php esc_html_e('Weight', 'mad-baits'); ?></span><strong><?php echo esc_html($fish_weight); ?></strong></li>
				<?php endif; ?>
				<?php if ('' !== $venue) : ?>
					<li><span><?php esc_html_e('Venue', 'mad-baits'); ?></span><strong><?php echo esc_html($venue); ?></strong></li>
				<?php endif; ?>
			</ul>
			<?php if ('' !== $excerpt) : ?>
				<p class="mad-catch-card__excerpt"><?php echo esc_html($excerpt); ?></p>
			<?php endif; ?>
			<div class="mad-catch-card__actions">
				<a class="text-link" href="<?php echo esc_url((string) $report_link); ?>"><?php esc_html_e('Read report', 'mad-baits'); ?></a>
				<?php if ('' !== $product_link) : ?>
					<a class="text-link" href="<?php echo esc_url($product_link); ?>"><?php esc_html_e('View bait', 'mad-baits'); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Canonical Mad Baits contact details.
 *
 * @return array<string, mixed>
 */
function mad_baits_get_contact_details() {
	$details = array(
		'phone_display'      => '01480 470862',
		'phone_tel'          => '+441480470862',
		'email'              => 'mark@madbaits.com',
		'collection_heading' => __('Collecting an order?', 'mad-baits'),
		'collection_address' => array(
			'Unit A, The Woodlands, Chawston',
			'BEDFORDSHIRE MK44 3BH',
			'United Kingdom',
		),
		'collection_hours'   => __('Collections can be made Monday – Friday 8.00am – 4.00pm', 'mad-baits'),
	);

	return (array) apply_filters('mad_baits_contact_details', $details);
}

/**
 * Contact form destination email.
 *
 * @return string
 */
function mad_baits_get_contact_email_address() {
	$details = mad_baits_get_contact_details();
	$email   = isset($details['email']) ? sanitize_email((string) $details['email']) : 'mark@madbaits.com';
	if ('' === $email) {
		$email = 'mark@madbaits.com';
	}

	return (string) apply_filters('mad_baits_contact_email', $email);
}

/**
 * Render contact form shortcode.
 *
 * @return string
 */
function mad_baits_contact_form_shortcode() {
	$status = isset($_GET['contact_status']) ? sanitize_key(wp_unslash((string) $_GET['contact_status'])) : '';
	$message = '';
	$message_class = '';

	if ('success' === $status) {
		$message = __('Thanks, your message has been sent. We will get back to you shortly.', 'mad-baits');
		$message_class = 'is-success';
	} elseif ('invalid' === $status) {
		$message = __('Please complete all required fields and use a valid email address.', 'mad-baits');
		$message_class = 'is-error';
	} elseif ('failed' === $status) {
		$message = __('We could not send your message right now. Please try again or email us directly.', 'mad-baits');
		$message_class = 'is-error';
	}

	ob_start();
	?>
	<form class="mad-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="mad_baits_contact_submit" />
		<?php wp_nonce_field('mad_baits_contact_submit', 'mad_baits_contact_nonce'); ?>
		<?php if ('' !== $message) : ?>
			<p class="mad-contact-form__status <?php echo esc_attr($message_class); ?>"><?php echo esc_html($message); ?></p>
		<?php endif; ?>
		<p class="mad-contact-form__field">
			<label for="mad-contact-name"><?php esc_html_e('Your Name', 'mad-baits'); ?></label>
			<input id="mad-contact-name" type="text" name="contact_name" required />
		</p>
		<p class="mad-contact-form__field">
			<label for="mad-contact-email"><?php esc_html_e('Email Address', 'mad-baits'); ?></label>
			<input id="mad-contact-email" type="email" name="contact_email" required />
		</p>
		<p class="mad-contact-form__field">
			<label for="mad-contact-subject"><?php esc_html_e('Subject', 'mad-baits'); ?></label>
			<input id="mad-contact-subject" type="text" name="contact_subject" required />
		</p>
		<p class="mad-contact-form__field">
			<label for="mad-contact-message"><?php esc_html_e('Message', 'mad-baits'); ?></label>
			<textarea id="mad-contact-message" name="contact_message" rows="6" required></textarea>
		</p>
		<p class="mad-contact-form__honeypot" aria-hidden="true">
			<label for="mad-contact-company"><?php esc_html_e('Company', 'mad-baits'); ?></label>
			<input id="mad-contact-company" type="text" name="contact_company" tabindex="-1" autocomplete="off" />
		</p>
		<p class="mad-contact-form__actions">
			<button type="submit" class="mad-button"><?php esc_html_e('Send Message', 'mad-baits'); ?></button>
		</p>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode('mad_baits_contact_form', 'mad_baits_contact_form_shortcode');

/**
 * Handle contact form submission.
 *
 * @return void
 */
function mad_baits_handle_contact_submit() {
	$referer = wp_get_referer();
	$fallback_url = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('contact', 'contact') : home_url('/contact/');
	$redirect_url = $referer ? $referer : $fallback_url;

	if (! isset($_POST['mad_baits_contact_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mad_baits_contact_nonce'])), 'mad_baits_contact_submit')) {
		wp_safe_redirect(add_query_arg('contact_status', 'invalid', $redirect_url));
		exit;
	}

	$honeypot = isset($_POST['contact_company']) ? trim((string) wp_unslash($_POST['contact_company'])) : '';
	if ('' !== $honeypot) {
		wp_safe_redirect(add_query_arg('contact_status', 'success', $redirect_url));
		exit;
	}

	$name = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash((string) $_POST['contact_name'])) : '';
	$email = isset($_POST['contact_email']) ? sanitize_email(wp_unslash((string) $_POST['contact_email'])) : '';
	$subject = isset($_POST['contact_subject']) ? sanitize_text_field(wp_unslash((string) $_POST['contact_subject'])) : '';
	$message = isset($_POST['contact_message']) ? trim((string) wp_unslash($_POST['contact_message'])) : '';
	$message = sanitize_textarea_field($message);

	if ('' === $name || '' === $subject || '' === $message || ! is_email($email)) {
		wp_safe_redirect(add_query_arg('contact_status', 'invalid', $redirect_url));
		exit;
	}

	$to = mad_baits_get_contact_email_address();
	$mail_subject = sprintf(__('Contact Form: %s', 'mad-baits'), $subject);
	$mail_body = sprintf(
		"Name: %s\nEmail: %s\n\nMessage:\n%s",
		$name,
		$email,
		$message
	);
	$headers = array('Reply-To: ' . $name . ' <' . $email . '>');

	$sent = wp_mail($to, $mail_subject, $mail_body, $headers);
	wp_safe_redirect(add_query_arg('contact_status', $sent ? 'success' : 'failed', $redirect_url));
	exit;
}
add_action('admin_post_mad_baits_contact_submit', 'mad_baits_handle_contact_submit');
add_action('admin_post_nopriv_mad_baits_contact_submit', 'mad_baits_handle_contact_submit');
