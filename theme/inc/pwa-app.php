<?php
/**
 * App shell and mobile PWA navigation.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * WooCommerce body classes where bottom app nav must not appear.
 *
 * @return array<int, string>
 */
function mad_baits_get_bottom_app_nav_hidden_wc_body_classes() {
	return array(
		'woocommerce-cart',
		'woocommerce-checkout',
		'woocommerce-order-pay',
		'woocommerce-order-received',
	);
}

/**
 * Whether the current request is a WooCommerce cart/checkout/payment flow page.
 *
 * @return bool
 */
function mad_baits_is_wc_checkout_flow_request() {
	if (function_exists('is_cart') && is_cart()) {
		return true;
	}

	if (function_exists('is_checkout') && is_checkout()) {
		return true;
	}

	if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received'))) {
		return true;
	}

	global $wp;
	if ($wp instanceof WP && is_array($wp->query_vars)) {
		if (! empty($wp->query_vars['order-pay']) || ! empty($wp->query_vars['order-received'])) {
			return true;
		}
	}

	// Pay-for-order links that land on checkout before endpoints resolve.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if (isset($_GET['pay_for_order']) && '' !== (string) wp_unslash($_GET['pay_for_order'])) {
		return true;
	}

	return false;
}

/**
 * Determine if bottom app nav should be hidden for current request.
 *
 * @return bool
 */
function mad_baits_should_hide_bottom_app_nav() {
	if (is_admin()) {
		return true;
	}

	if (mad_baits_is_wc_checkout_flow_request()) {
		return true;
	}

	if (function_exists('is_account_page') && is_account_page() && ! is_user_logged_in()) {
		return true;
	}

	/**
	 * Filter bottom app nav visibility (return true to hide).
	 *
	 * @param bool $hide Whether to hide the nav.
	 */
	return (bool) apply_filters('mad_baits_should_hide_bottom_app_nav', false);
}

/**
 * Resolve app nav links.
 *
 * @return array<string, array<string, string>>
 */
function mad_baits_get_bottom_app_nav_items() {
	$home_url    = home_url('/');
	$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$deals_url   = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
	$session_url = function_exists('mad_baits_get_session_app_url') ? mad_baits_get_session_app_url() : (function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('session', 'session') : home_url('/session/'));
	$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');

	return array(
		'home' => array(
			'label' => __('Home', 'mad-baits'),
			'url'   => is_string($home_url) ? $home_url : home_url('/'),
			'icon'  => 'home',
		),
		'shop' => array(
			'label' => __('Shop', 'mad-baits'),
			'url'   => is_string($shop_url) ? $shop_url : home_url('/shop/'),
			'icon'  => 'shop',
		),
		'deals' => array(
			'label' => __('Deals', 'mad-baits'),
			'url'   => is_string($deals_url) && '' !== $deals_url ? $deals_url : home_url('/product-category/bundles-deals/'),
			'icon'  => 'deals',
		),
		'session' => array(
			'label' => __('Session', 'mad-baits'),
			'url'   => is_string($session_url) ? $session_url : home_url('/whats-in-the-water/'),
			'icon'  => 'session',
		),
		'cart' => array(
			'label'      => __('Basket', 'mad-baits'),
			'url'        => is_string($cart_url) ? $cart_url : home_url('/cart/'),
			'icon'       => 'cart',
			'show_count' => true,
		),
	);
}

/**
 * Render simple inline app nav icon.
 *
 * @param string $icon Icon key.
 * @return string
 */
function mad_baits_get_bottom_app_nav_icon_svg($icon) {
	$icon = sanitize_key((string) $icon);

	$map = array(
		'home'    => '<path d="M3 10.5 12 3l9 7.5v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
		'shop'    => '<path d="M4 8h16l-1.4 12.2a1 1 0 0 1-1 .8H6.4a1 1 0 0 1-1-.8z"/><path d="M8 8V6a4 4 0 1 1 8 0v2"/>',
		'deals'   => '<path d="M7 3h10l1 4H6z"/><path d="M5 9h14v11a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z"/><path d="M9 13h2v5H9zm4 0h2v5h-2z"/>',
		'session' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
		'cart'    => '<path d="M6 7h15l-1.5 9H7.5z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/><circle cx="9" cy="20" r="1.2"/><circle cx="18" cy="20" r="1.2"/>',
		'discover'=> '<path d="m12 3 2.6 5.4L20 11l-5.4 2.6L12 19l-2.6-5.4L4 11l5.4-2.6z"/>',
		'account' => '<circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
	);

	$path = isset($map[ $icon ]) ? $map[ $icon ] : $map['discover'];

	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

/**
 * Render bottom app navigation (mobile/PWA shell).
 *
 * @return void
 */
function mad_baits_render_bottom_app_nav() {
	if (mad_baits_should_hide_bottom_app_nav()) {
		return;
	}

	$items      = mad_baits_get_bottom_app_nav_items();
	$cart_count = 0;
	if (function_exists('WC') && WC()->cart) {
		$cart_count = (int) WC()->cart->get_cart_contents_count();
	}
	if (empty($items)) {
		return;
	}
	?>
	<nav class="mad-app-bottom-nav" data-app-bottom-nav aria-label="<?php esc_attr_e('App navigation', 'mad-baits'); ?>">
		<div class="mad-app-bottom-nav__inner">
			<?php foreach ($items as $key => $item) : ?>
				<?php
				$is_coming_soon = isset($item['behavior']) && 'coming_soon' === (string) $item['behavior'];
				$show_count     = ! empty($item['show_count']);
				$label          = isset($item['label']) ? (string) $item['label'] : '';
				$icon_markup    = mad_baits_get_bottom_app_nav_icon_svg(isset($item['icon']) ? (string) $item['icon'] : 'discover');
				?>
				<?php if ($is_coming_soon) : ?>
					<button
						type="button"
						class="mad-app-bottom-nav__item mad-app-bottom-nav__item--button"
						data-app-nav-item
						data-app-nav-key="<?php echo esc_attr((string) $key); ?>"
						data-app-session-open
						aria-haspopup="dialog"
						aria-controls="mad-app-session-sheet-title"
					>
						<span class="mad-app-bottom-nav__icon"><?php echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="mad-app-bottom-nav__label"><?php echo esc_html($label); ?></span>
					</button>
				<?php else : ?>
					<a
						class="mad-app-bottom-nav__item"
						data-app-nav-item
						data-app-nav-key="<?php echo esc_attr((string) $key); ?>"
						href="<?php echo esc_url(isset($item['url']) ? (string) $item['url'] : home_url('/')); ?>"
					>
						<span class="mad-app-bottom-nav__icon">
							<?php echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ($show_count) : ?>
								<span class="mad-app-bottom-nav__cart-count" data-app-cart-count<?php echo $cart_count < 1 ? ' hidden' : ''; ?>><?php echo esc_html((string) $cart_count); ?></span>
							<?php endif; ?>
						</span>
						<span class="mad-app-bottom-nav__label"><?php echo esc_html($label); ?></span>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if (function_exists('mad_baits_render_app_version_label')) : ?>
			<?php mad_baits_render_app_version_label('mad-app-bottom-nav__version'); ?>
		<?php endif; ?>
	</nav>
	<?php
}
add_action('wp_footer', 'mad_baits_render_bottom_app_nav', 20);

/**
 * Whether the Session App / builder should show coming soon instead of the tool.
 *
 * @return bool
 */
function mad_baits_session_app_is_coming_soon() {
	return (bool) apply_filters('mad_baits_session_app_is_coming_soon', true);
}

/**
 * Whether the commerce Build Your Session kit builder shows coming soon.
 * Separate from the fishing log Session app at /session/.
 *
 * @return bool
 */
function mad_baits_session_builder_is_coming_soon() {
	return (bool) apply_filters('mad_baits_session_builder_is_coming_soon', true);
}

/**
 * Feature list for Session App coming soon messaging.
 *
 * @return array<int, string>
 */
function mad_baits_get_session_app_coming_soon_features() {
	return array(
		__('Session tracking', 'mad-baits'),
		__('Capture logging with photos', 'mad-baits'),
		__('Bait & product tracking', 'mad-baits'),
		__('Session timeline', 'mad-baits'),
		__('Catch reports integration', 'mad-baits'),
		__('Weather tracking', 'mad-baits'),
		__('Personal capture stats', 'mad-baits'),
		__('Quick re-order of successful bait', 'mad-baits'),
	);
}

/**
 * Render shared Session App coming soon body copy.
 *
 * @return void
 */
function mad_baits_render_session_app_coming_soon_body() {
	$features = mad_baits_get_session_app_coming_soon_features();
	?>
	<p><?php esc_html_e('The new Mad Baits Session App is designed to become the ultimate carp fishing companion — giving anglers a premium app-style experience directly from their phone.', 'mad-baits'); ?></p>
	<p><?php esc_html_e('Track your sessions, log captures, record bait used, monitor conditions and build a full history of your fishing all in one place.', 'mad-baits'); ?></p>
	<p class="mad-app-session-sheet__features-heading"><?php esc_html_e('Features coming soon include:', 'mad-baits'); ?></p>
	<ul class="mad-app-session-sheet__features">
		<?php foreach ($features as $feature) : ?>
			<li><?php echo esc_html((string) $feature); ?></li>
		<?php endforeach; ?>
	</ul>
	<p><?php esc_html_e('Built for serious carp anglers, the Session App is being designed to work seamlessly alongside the Mad Baits range — helping anglers track what works and build confidence in their baiting approach over time.', 'mad-baits'); ?></p>
	<p class="mad-app-session-sheet__closing"><?php esc_html_e('This is just the beginning.', 'mad-baits'); ?></p>
	<?php
}

/**
 * Render Session App coming soon on the Build My Session page.
 *
 * @return void
 */
function mad_baits_render_session_app_coming_soon_page() {
	$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
	?>
	<article class="mad-app-session-coming-soon-page mb-content-card" aria-labelledby="mad-session-app-coming-soon-title">
		<header class="mad-app-session-sheet__header mad-app-session-sheet__header--page">
			<div>
				<p class="mad-app-session-sheet__kicker"><?php esc_html_e('Coming Soon', 'mad-baits'); ?></p>
				<h2 id="mad-session-app-coming-soon-title"><?php esc_html_e('Mad Baits Session App', 'mad-baits'); ?></h2>
			</div>
		</header>
		<div class="mad-app-session-sheet__body mad-app-session-sheet__body--page">
			<?php mad_baits_render_session_app_coming_soon_body(); ?>
		</div>
		<footer class="mad-app-session-sheet__footer mad-app-session-sheet__footer--page">
			<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>">
				<?php esc_html_e('Browse The Shop', 'mad-baits'); ?>
			</a>
		</footer>
	</article>
	<?php
}

/**
 * Render Session App coming soon sheet (mobile/PWA).
 *
 * @return void
 */
function mad_baits_render_session_app_coming_soon_sheet() {
	if (mad_baits_should_hide_bottom_app_nav()) {
		return;
	}
	?>
	<div class="mad-app-session-sheet-shell" data-app-session-sheet hidden aria-hidden="true">
		<div class="mad-app-session-sheet__backdrop" data-app-session-close tabindex="-1" aria-hidden="true"></div>
		<aside class="mad-app-session-sheet" role="dialog" aria-modal="true" aria-labelledby="mad-app-session-sheet-title">
			<header class="mad-app-session-sheet__header">
				<div>
					<p class="mad-app-session-sheet__kicker"><?php esc_html_e('Coming Soon', 'mad-baits'); ?></p>
					<h2 id="mad-app-session-sheet-title"><?php esc_html_e('Mad Baits Session App', 'mad-baits'); ?></h2>
				</div>
				<button type="button" class="mad-app-session-sheet__close" data-app-session-close aria-label="<?php esc_attr_e('Close', 'mad-baits'); ?>">
					&times;
				</button>
			</header>
			<div class="mad-app-session-sheet__body">
				<?php mad_baits_render_session_app_coming_soon_body(); ?>
			</div>
			<footer class="mad-app-session-sheet__footer">
				<button type="button" class="mad-button mad-button--ghost mad-app-session-sheet__dismiss" data-app-session-close>
					<?php esc_html_e('Close', 'mad-baits'); ?>
				</button>
			</footer>
		</aside>
	</div>
	<?php
}
add_action('wp_footer', 'mad_baits_render_session_app_coming_soon_sheet', 25);

/**
 * Render app-only dashboard section on homepage.
 *
 * @return void
 */
function mad_baits_render_app_home_modules() {
	if (! is_front_page()) {
		return;
	}

	static $rendered = false;
	if ($rendered) {
		return;
	}
	$rendered = true;

	$shop_url     = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));
	$ai_url       = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');
	$tv_url       = function_exists('mad_baits_get_mad_baits_tv_url') ? mad_baits_get_mad_baits_tv_url() : home_url('/mad-baits-tv/');
	$bundle_url   = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/');
	$discover_url = function_exists('mad_baits_get_catch_reports_url') ? mad_baits_get_catch_reports_url() : home_url('/catch-reports/');
	$session_url  = function_exists('mad_baits_get_session_app_url') ? mad_baits_get_session_app_url() : (function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('session', 'session') : home_url('/session/'));
	$catch_submit = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('submit-catch-report', 'submit-catch-report') : home_url('/submit-catch-report/');
	?>
	<section class="mad-app-home section section--tight" data-app-home-shell>
		<div class="container">
			<div class="mad-app-home__hero">
				<p class="mad-app-home__hero-kicker"><?php esc_html_e('Mad Baits App', 'mad-baits'); ?></p>
				<h2 class="mad-app-home__hero-title"><?php esc_html_e('Welcome to the madness', 'mad-baits'); ?></h2>
				<p class="mad-app-home__hero-copy"><?php esc_html_e('Build your bait plan, start a session, and shop premium carp bait — built for life on the bank.', 'mad-baits'); ?></p>
			</div>

			<?php do_action('mad_baits_app_home_before_quick_grid'); ?>

			<div class="mad-app-home__quick-grid">
				<a href="<?php echo esc_url(is_string($shop_url) ? $shop_url : home_url('/shop/')); ?>" class="mad-app-home__quick-card mad-app-home__quick-card--primary"><?php esc_html_e('Shop Bait', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url(is_string($bundle_url) ? $bundle_url : home_url('/product-category/bundles-deals/')); ?>" class="mad-app-home__quick-card"><?php esc_html_e('Build Bundle', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url(is_string($session_url) ? $session_url : home_url('/whats-in-the-water/')); ?>" class="mad-app-home__quick-card"><?php esc_html_e('Start Session', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url(is_string($catch_submit) ? $catch_submit : $discover_url); ?>" class="mad-app-home__quick-card"><?php esc_html_e('Submit Catch', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url(is_string($bundle_url) ? $bundle_url : home_url('/product-category/bundles-deals/')); ?>" class="mad-app-home__quick-card"><?php esc_html_e('View Deals', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url(is_string($discover_url) ? $discover_url : home_url('/catch-reports/')); ?>" class="mad-app-home__quick-card"><?php esc_html_e('Catch Reports', 'mad-baits'); ?></a>
			</div>

			<div class="mad-app-home__lists">
				<section class="mad-app-home__list-card" data-app-list="recently-viewed">
					<h3><?php esc_html_e('Recently Viewed', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Products you looked at recently will show here.', 'mad-baits'); ?></p>
					<div data-app-list-items></div>
				</section>
				<section class="mad-app-home__list-card" data-app-list="favourites">
					<h3><?php esc_html_e('Your Favourite Baits', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Save bait systems and reopen your best combinations fast.', 'mad-baits'); ?></p>
					<div data-app-list-items></div>
				</section>
				<section class="mad-app-home__list-card" data-app-list="trending">
					<h3><?php esc_html_e('Trending This Week', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Community momentum and top-performing bait interest.', 'mad-baits'); ?></p>
					<div data-app-list-items></div>
				</section>
			</div>
		</div>
	</section>
	<?php
}
add_action('wp_footer', 'mad_baits_render_app_home_modules', 12);
