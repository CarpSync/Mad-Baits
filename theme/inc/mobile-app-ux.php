<?php
/**
 * Mobile / PWA UX helpers.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Routes that show the fixed mobile app top bar (back + title).
 *
 * @return bool
 */
function mad_baits_should_show_mobile_back_bar() {
	if (is_admin()) {
		return false;
	}

	return (bool) apply_filters(
		'mad_baits_show_mobile_back_bar',
		mad_baits_should_show_mobile_back_bar_default()
	);
}

/**
 * Default visibility rules for the mobile app top bar.
 *
 * @return bool
 */
function mad_baits_should_show_mobile_back_bar_default() {
	if (is_front_page()) {
		return false;
	}

	if (class_exists('WooCommerce', false)) {
		if (function_exists('is_cart') && is_cart()) {
			return true;
		}
		if (function_exists('is_checkout') && is_checkout()) {
			return true;
		}
		if (function_exists('is_account_page') && is_account_page()) {
			return true;
		}
		if (function_exists('is_product') && is_product()) {
			return true;
		}
		if (function_exists('is_shop') && is_shop()) {
			return true;
		}
		if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
			return true;
		}
	}

	if (function_exists('is_search') && is_search()) {
		return true;
	}

	if (is_page()) {
		$page_id = get_queried_object_id();
		if ($page_id > 0) {
			$slug = sanitize_title((string) get_post_field('post_name', $page_id));
			if (in_array(
				$slug,
				array(
					'session',
					'build-my-session',
					'build-your-session',
					'bundle-deals',
					'bundles-deals',
					'whats-in-the-water',
					'ai-bait-finder',
					'catch-reports',
					'catch-reports-news',
				),
				true
			)) {
				return true;
			}
			$tmpl = get_page_template_slug($page_id);
			if (is_string($tmpl) && in_array($tmpl, array('template-build-your-session.php', 'template-bundle-deals.php'), true)) {
				return true;
			}
		}
	}

	if (is_singular('catch_report') || is_post_type_archive('catch_report')) {
		return true;
	}

	if (is_post_type_archive('mad_event') || is_singular('mad_event')) {
		return true;
	}

	return false;
}

/**
 * Heading text for the mobile top bar.
 *
 * @return string
 */
function mad_baits_get_mobile_back_bar_title() {
	$title = '';

	if (function_exists('is_cart') && is_cart()) {
		$title = __('Basket', 'mad-baits');
	} elseif (function_exists('is_order_received_page') && function_exists('is_checkout') && is_checkout() && is_order_received_page()) {
		$title = __('Order confirmed', 'mad-baits');
	} elseif (function_exists('is_checkout') && is_checkout()) {
		$title = __('Checkout', 'mad-baits');
	} elseif (function_exists('is_account_page') && is_account_page()) {
		if (function_exists('is_wc_endpoint_url') && ! is_wc_endpoint_url()) {
			$title = __('Account', 'mad-baits');
		} elseif (function_exists('is_wc_endpoint_url')) {
			if (is_wc_endpoint_url('orders')) {
				$title = __('Orders', 'mad-baits');
			} elseif (is_wc_endpoint_url('view-order')) {
				$title = __('Order details', 'mad-baits');
			} elseif (is_wc_endpoint_url('edit-account')) {
				$title = __('Account details', 'mad-baits');
			} elseif (is_wc_endpoint_url('downloads')) {
				$title = __('Downloads', 'mad-baits');
			} elseif (is_wc_endpoint_url('edit-address')) {
				$title = __('Addresses', 'mad-baits');
			} elseif (is_wc_endpoint_url('payment-methods')) {
				$title = __('Payment methods', 'mad-baits');
			} else {
				$title = __('Account', 'mad-baits');
			}
		} else {
			$title = __('Account', 'mad-baits');
		}
	} elseif (function_exists('is_product') && is_product()) {
		$title = get_the_title();
	} elseif (function_exists('is_product_category') && is_product_category()) {
		$title = single_term_title('', false) ?: '';
	} elseif (function_exists('is_product_tag') && is_product_tag()) {
		$title = single_term_title('', false) ?: '';
	} elseif (function_exists('is_shop') && is_shop()) {
		$title = __('Shop', 'mad-baits');
	} elseif (is_singular('catch_report')) {
		$title = get_the_title();
	} elseif (is_post_type_archive('catch_report')) {
		$title = __('Catch reports', 'mad-baits');
	} elseif (is_singular('mad_event')) {
		$title = get_the_title();
	} elseif (is_post_type_archive('mad_event')) {
		$title = __('Events', 'mad-baits');
	} elseif (is_page()) {
		$title = get_the_title();
	}

	$title = is_string($title) ? wp_strip_all_tags(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';

	return apply_filters('mad_baits_mobile_back_bar_title', $title);
}

/**
 * Fallback URL when history/referrer cannot go back in-app.
 *
 * @return string
 */
function mad_baits_get_mobile_back_bar_fallback_url() {
	if (class_exists('WooCommerce', false)) {
		if (function_exists('is_order_received_page') && function_exists('is_checkout') && is_checkout() && is_order_received_page()) {
			$url = wc_get_page_permalink('shop');
			return is_string($url) && '' !== $url ? $url : home_url('/');
		}
		if ((function_exists('is_cart') && is_cart())) {
			$url = wc_get_page_permalink('shop');
			return is_string($url) && '' !== $url ? $url : home_url('/');
		}
		if ((function_exists('is_checkout') && is_checkout())) {
			$url = wc_get_cart_url();
			return is_string($url) && '' !== $url ? $url : home_url('/');
		}
		if ((function_exists('is_shop') && is_shop()) || (function_exists('is_product_taxonomy') && is_product_taxonomy())) {
			$url = wc_get_page_permalink('shop');
			return is_string($url) && '' !== $url ? $url : home_url('/');
		}
		if (function_exists('is_product') && is_product()) {
			if (function_exists('mad_baits_is_bundle_builder_product') && mad_baits_is_bundle_builder_product()) {
				$bundles_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/');
				return is_string($bundles_url) && '' !== $bundles_url ? $bundles_url : home_url('/');
			}
			$url = wc_get_page_permalink('shop');
			return is_string($url) && '' !== $url ? $url : home_url('/');
		}
	}

	if (is_singular('catch_report') || is_post_type_archive('catch_report')) {
		$catch_url = function_exists('mad_baits_get_catch_reports_url') ? mad_baits_get_catch_reports_url() : home_url('/catch-reports/');
		return is_string($catch_url) && '' !== $catch_url ? $catch_url : home_url('/');
	}

	return home_url('/');
}

/**
 * Ordered fallback URLs for app back navigation.
 *
 * @return array<int, string>
 */
function mad_baits_get_mobile_back_bar_fallback_urls() {
	$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$bundles_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/');
	$home_url    = home_url('/');
	$contextual  = mad_baits_get_mobile_back_bar_fallback_url();

	$urls = array_filter(
		array(
			is_string($contextual) ? $contextual : '',
			is_string($shop_url) ? $shop_url : '',
			is_string($bundles_url) ? $bundles_url : '',
			is_string($home_url) ? $home_url : '',
		),
		static function ($url) {
			return is_string($url) && '' !== $url;
		}
	);

	return array_values(array_unique($urls));
}

/**
 * Resolve the logo image URL for the app top bar.
 *
 * @return string
 */
function mad_baits_get_mobile_top_bar_logo_url() {
	if (function_exists('has_custom_logo') && has_custom_logo()) {
		$logo_id = (int) get_theme_mod('custom_logo');
		if ($logo_id > 0) {
			$src = wp_get_attachment_image_url($logo_id, 'full');
			if (is_string($src) && '' !== $src) {
				return $src;
			}
		}
	}

	$fallback_path = get_theme_file_path('/assets/img/CUP-LOGOv2-1.svg');
	if (is_string($fallback_path) && file_exists($fallback_path)) {
		return get_theme_file_uri('/assets/img/CUP-LOGOv2-1.svg');
	}

	return '';
}

/**
 * Render fixed mobile top navigation (app shell).
 *
 * @return void
 */
function mad_baits_render_mobile_app_top_bar() {
	if (! mad_baits_should_show_mobile_back_bar()) {
		return;
	}

	$cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
	$cart_url = is_string($cart_url) ? $cart_url : home_url('/cart/');

	$hide_cart_icon = false;
	if (function_exists('is_cart') && is_cart()) {
		$hide_cart_icon = true;
	}

	$title      = mad_baits_get_mobile_back_bar_title();
	$fallback   = mad_baits_get_mobile_back_bar_fallback_url();
	$fallbacks  = mad_baits_get_mobile_back_bar_fallback_urls();
	$cart_count = (function_exists('WC') && WC()->cart) ? (int) WC()->cart->get_cart_contents_count() : 0;
	$logo_url   = mad_baits_get_mobile_top_bar_logo_url();
	$home_url   = home_url('/');

	if ('' === trim($title)) {
		$title = get_bloginfo('name');
	}

	?>
	<div
		class="mad-app-top-bar"
		id="mad-app-top-bar"
		data-mad-app-top-bar
		data-fallback-url="<?php echo esc_url($fallback); ?>"
		data-fallback-urls="<?php echo esc_attr(wp_json_encode($fallbacks)); ?>"
		aria-hidden="false"
	>
		<div class="mad-app-top-bar__inner">
			<button type="button" class="mad-app-top-bar__btn mad-app-top-bar__back" data-app-back aria-label="<?php esc_attr_e('Go back', 'mad-baits'); ?>">
				<span class="mad-app-top-bar__btn-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</span>
			</button>
			<div class="mad-app-top-bar__brand-wrap">
				<a class="mad-app-top-bar__brand" href="<?php echo esc_url($home_url); ?>" aria-label="<?php esc_attr_e('Mad Baits home', 'mad-baits'); ?>">
					<?php if ('' !== $logo_url) : ?>
						<img
							class="mad-app-top-bar__logo"
							src="<?php echo esc_url($logo_url); ?>"
							alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
							loading="eager"
							decoding="async"
						/>
					<?php else : ?>
						<span class="mad-app-top-bar__logo-text"><?php echo esc_html(get_bloginfo('name')); ?></span>
					<?php endif; ?>
				</a>
				<p class="mad-app-top-bar__title"><?php echo esc_html($title); ?></p>
			</div>
			<div class="mad-app-top-bar__actions">
				<?php if (! $hide_cart_icon) : ?>
					<a class="mad-app-top-bar__btn mad-app-top-bar__cart" href="<?php echo esc_url($cart_url); ?>" aria-label="<?php esc_attr_e('Basket', 'mad-baits'); ?>">
						<span class="mad-app-top-bar__btn-icon mad-app-top-bar__btn-icon--cart" aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M6 7h15l-1.5 9H7.5z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M9 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="9" cy="20" r="1.35" fill="currentColor"/><circle cx="18" cy="20" r="1.35" fill="currentColor"/></svg>
							<span class="mad-app-top-bar__cart-count" data-app-top-cart-count<?php echo $cart_count < 1 ? ' hidden' : ''; ?>><?php echo esc_html((string) $cart_count); ?></span>
						</span>
					</a>
				<?php endif; ?>
				<button type="button" class="mad-app-top-bar__btn mad-app-top-bar__menu" data-app-top-open-menu aria-label="<?php esc_attr_e('Open menu', 'mad-baits'); ?>">
					<span class="mad-app-top-bar__btn-icon mad-app-top-bar__btn-icon--menu" aria-hidden="true">
						<span></span><span></span><span></span>
					</span>
				</button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * App-first quick links block inside the mobile drawer menu.
 *
 * @return void
 */
function mad_baits_render_mobile_app_quick_links() {
	$home_url    = home_url('/');
	$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$bundles_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/');
	$hookbaits   = function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('hookbaits', 'hookbait', 'hook-baits'), (string) $shop_url) : home_url('/product-category/hookbaits/');
	$liquids     = function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('liquids', 'liquid-foods', 'other-liquid-foods'), (string) $shop_url) : home_url('/product-category/liquids/');
	$bait_ranges = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('boilie-range', 'boilie-range') : home_url('/boilie-range/');
	$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
	$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
	$contact_url = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('contact', 'contact') : home_url('/contact/');

	$links = array(
		array('label' => __('Home', 'mad-baits'), 'url' => $home_url),
		array('label' => __('Shop', 'mad-baits'), 'url' => $shop_url),
		array('label' => __('Bundles & Deals', 'mad-baits'), 'url' => $bundles_url),
		array('label' => __('Bait Ranges', 'mad-baits'), 'url' => $bait_ranges),
		array('label' => __('Hookbaits', 'mad-baits'), 'url' => $hookbaits),
		array('label' => __('Liquids', 'mad-baits'), 'url' => $liquids),
		array('label' => __('Basket', 'mad-baits'), 'url' => $cart_url),
		array('label' => __('Account', 'mad-baits'), 'url' => $account_url),
		array('label' => __('Contact', 'mad-baits'), 'url' => $contact_url),
	);
	?>
	<div class="mad-mobile-app-links" aria-label="<?php esc_attr_e('Quick app links', 'mad-baits'); ?>">
		<p class="mad-mobile-app-links__title"><?php esc_html_e('Quick links', 'mad-baits'); ?></p>
		<div class="mad-mobile-app-links__grid">
			<?php foreach ($links as $link) : ?>
				<a class="mad-mobile-app-links__item" href="<?php echo esc_url((string) $link['url']); ?>">
					<?php echo esc_html((string) $link['label']); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
add_action('mad_baits_mobile_nav_after', 'mad_baits_render_mobile_app_quick_links', 12);

/**
 * Enqueue mobile app stylesheet.
 *
 * @return void
 */
function mad_baits_enqueue_mobile_app_styles() {
	$path = get_theme_file_path('assets/css/scoped/mobile-app.css');
	if (! file_exists($path)) {
		return;
	}

	wp_enqueue_style(
		'mad-baits-mobile-app',
		get_theme_file_uri('assets/css/scoped/mobile-app.css'),
		array('mad-baits-overrides'),
		function_exists('mad_baits_get_asset_version') ? mad_baits_get_asset_version($path) : (string) filemtime($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_mobile_app_styles', 40);

/**
 * Free shipping threshold for UK (filterable).
 *
 * @return float
 */
function mad_baits_get_free_shipping_threshold() {
	return (float) apply_filters('mad_baits_free_shipping_threshold', 100);
}

/**
 * Render free-shipping progress on cart / checkout.
 *
 * @return void
 */
function mad_baits_render_free_shipping_progress() {
	if (! function_exists('WC') || ! WC()->cart || WC()->cart->is_empty()) {
		return;
	}

	if (function_exists('is_cart') && ! is_cart() && function_exists('is_checkout') && ! is_checkout()) {
		return;
	}

	if (function_exists('is_checkout') && is_checkout() && ! is_cart()) {
		// Show on checkout only when cart has items and not order-received.
		if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
			return;
		}
	}

	$threshold = mad_baits_get_free_shipping_threshold();
	if ($threshold <= 0) {
		return;
	}

	$subtotal = (float) WC()->cart->get_displayed_subtotal();
	if (WC()->cart->display_prices_including_tax()) {
		$subtotal = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
	}

	$remaining = max(0, $threshold - $subtotal);
	$percent   = min(100, max(0, ($subtotal / $threshold) * 100));
	$qualified = $remaining <= 0.009;

	$classes = 'mad-free-shipping-bar' . ($qualified ? ' is-qualified' : '');
	?>
	<div class="<?php echo esc_attr($classes); ?>" role="status" aria-live="polite">
		<p class="mad-free-shipping-bar__label">
			<?php if ($qualified) : ?>
				<?php esc_html_e('You qualify for', 'mad-baits'); ?> <strong><?php esc_html_e('FREE UK shipping', 'mad-baits'); ?></strong>
			<?php else : ?>
				<?php
				printf(
					/* translators: %s: formatted money amount */
					esc_html__('Spend %s more for', 'mad-baits'),
					wp_kses_post(wc_price($remaining))
				);
				?>
				<strong><?php esc_html_e('FREE UK shipping', 'mad-baits'); ?></strong>
			<?php endif; ?>
		</p>
		<div class="mad-free-shipping-bar__track" aria-hidden="true">
			<span class="mad-free-shipping-bar__fill" style="width: <?php echo esc_attr((string) round($percent, 1)); ?>%;"></span>
		</div>
	</div>
	<?php
}
add_action('woocommerce_before_cart', 'mad_baits_render_free_shipping_progress', 6);
add_action('woocommerce_before_checkout_form', 'mad_baits_render_free_shipping_progress', 6);

/**
 * Homepage urgency pills (mobile).
 *
 * @return void
 */
function mad_baits_render_mobile_home_urgency() {
	if (! is_front_page()) {
		return;
	}

	$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$events_url = function_exists('mad_baits_get_events_url') ? mad_baits_get_events_url() : home_url('/events/');
	$deals_url  = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
	?>
	<div class="mad-mobile-urgency" aria-label="<?php esc_attr_e('Current offers', 'mad-baits'); ?>">
		<a class="mad-mobile-urgency__pill" href="<?php echo esc_url($events_url); ?>">
			<?php esc_html_e('Open Day & Events', 'mad-baits'); ?>
		</a>
		<span class="mad-mobile-urgency__pill">
			<?php esc_html_e('Free UK shipping', 'mad-baits'); ?> <strong><?php esc_html_e('£100+', 'mad-baits'); ?></strong>
		</span>
		<a class="mad-mobile-urgency__pill" href="<?php echo esc_url($deals_url); ?>">
			<?php esc_html_e('Weekend', 'mad-baits'); ?> <strong><?php esc_html_e('Deals', 'mad-baits'); ?></strong>
		</a>
	</div>
	<?php
}

/**
 * Append bottom-nav cart count to Woo fragments.
 *
 * @param array<string, string> $fragments Fragments.
 * @return array<string, string>
 */
function mad_baits_mobile_app_cart_fragments($fragments) {
	if (! function_exists('WC') || ! WC()->cart) {
		return $fragments;
	}

	$count = (int) WC()->cart->get_cart_contents_count();

	ob_start();
	?>
	<span class="mad-app-bottom-nav__cart-count" data-app-cart-count<?php echo $count < 1 ? ' hidden' : ''; ?>><?php echo esc_html((string) $count); ?></span>
	<?php
	$fragments['[data-app-cart-count]'] = ob_get_clean();

	ob_start();
	?>
	<span class="mad-app-top-bar__cart-count" data-app-top-cart-count<?php echo $count < 1 ? ' hidden' : ''; ?>><?php echo esc_html((string) $count); ?></span>
	<?php
	$fragments['[data-app-top-cart-count]'] = ob_get_clean();

	// Mini-cart drawer fragments are owned by mad_baits_cart_count_fragment() in functions.php.
	return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'mad_baits_mobile_app_cart_fragments', 20);

// Mini-cart drawer markup + helpers live in functions.php (single global instance).

require_once get_theme_file_path('/inc/app-mobile-ux-enhancements.php');
require_once get_theme_file_path('/inc/capacitor-ios-shell.php');
