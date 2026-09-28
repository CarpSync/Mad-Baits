<?php
/**
 * Template Name: Session App
 * Template Post Type: page
 *
 * Mobile-only Mad Baits Session fishing logbook shell.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$bundles_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
$login_url    = class_exists('MBS_Access') ? MBS_Access::get_auth_url('login') : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url());
$register_url = class_exists('MBS_Access') ? MBS_Access::get_auth_url('register') : $login_url;
$is_mobile   = class_exists('MBS_Plugin') ? MBS_Plugin::is_mobile_viewport_context() : wp_is_mobile();
$mobile_only = class_exists('MBS_Plugin') ? MBS_Plugin::is_mobile_only_enforced() : true;
?>

<main id="primary" class="site-main mbs-session-shell" data-mbs-session-root>
	<div id="mbs-session-app" class="mbs-session-app" data-mbs-app
		data-mobile="<?php echo $is_mobile ? '1' : '0'; ?>"
		data-mobile-only="<?php echo $mobile_only ? '1' : '0'; ?>"
		aria-live="polite">
		<div class="mbs-session-app__loader" data-mbs-loader>
			<div class="mbs-skeleton mbs-skeleton--hero"></div>
			<div class="mbs-skeleton mbs-skeleton--card"></div>
			<div class="mbs-skeleton mbs-skeleton--card"></div>
			<p class="mbs-session-app__loader-text"><?php esc_html_e('Loading Session…', 'mad-baits'); ?></p>
		</div>
	</div>

	<noscript>
		<div class="mbs-session-noscript mb-content-card">
			<h1><?php esc_html_e('Session', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('JavaScript is required for the Session app. Enable JavaScript or open the Mad Baits mobile app.', 'mad-baits'); ?></p>
			<a class="mad-button" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to shop', 'mad-baits'); ?></a>
		</div>
	</noscript>

	<template id="mbs-tpl-desktop-locked">
		<section class="mbs-locked mbs-locked--desktop" aria-labelledby="mbs-desktop-title">
			<div class="mbs-locked__glow" aria-hidden="true"></div>
			<p class="mbs-locked__kicker"><?php esc_html_e('Built for the bank', 'mad-baits'); ?></p>
			<h1 id="mbs-desktop-title"><?php esc_html_e('Session is built for the bank.', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Open the Mad Baits mobile app to access your private logbook, weather tracker, catch tools and session insights.', 'mad-baits'); ?></p>
			<div class="mbs-locked__actions">
				<a class="mbs-btn mbs-btn--primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Open Mobile App', 'mad-baits'); ?></a>
				<a class="mbs-btn mbs-btn--ghost" href="<?php echo esc_url(is_string($shop_url) ? $shop_url : home_url('/shop/')); ?>"><?php esc_html_e('Shop Mad Baits', 'mad-baits'); ?></a>
			</div>
		</section>
	</template>

	<template id="mbs-tpl-guest-locked">
		<section class="mbs-locked" aria-labelledby="mbs-guest-title">
			<div class="mbs-locked__glow" aria-hidden="true"></div>
			<h1 id="mbs-guest-title"><?php esc_html_e('Unlock Session', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Session is for Mad Baits customers. Place an order (this creates your account at checkout), then log in with that email to open your private fishing logbook.', 'mad-baits'); ?></p>
			<div class="mbs-locked__actions">
				<a class="mbs-btn mbs-btn--primary" href="<?php echo esc_url(is_string($login_url) ? $login_url : wp_login_url()); ?>"><?php esc_html_e('Log In', 'mad-baits'); ?></a>
				<a class="mbs-btn mbs-btn--ghost" href="<?php echo esc_url(is_string($register_url) ? $register_url : wp_login_url()); ?>"><?php esc_html_e('Create Account', 'mad-baits'); ?></a>
			</div>
		</section>
	</template>

	<template id="mbs-tpl-customer-locked">
		<section class="mbs-locked" aria-labelledby="mbs-customer-title">
			<div class="mbs-locked__glow" aria-hidden="true"></div>
			<h1 id="mbs-customer-title"><?php esc_html_e('Session is for Mad Baits customers', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Session unlocks when this account has a Mad Baits order — pending or confirmed counts. Place an order with this email, or log in with the account you used at checkout.', 'mad-baits'); ?></p>
			<div class="mbs-locked__actions">
				<a class="mbs-btn mbs-btn--primary" href="<?php echo esc_url(is_string($shop_url) ? $shop_url : home_url('/shop/')); ?>"><?php esc_html_e('Shop Baits', 'mad-baits'); ?></a>
				<a class="mbs-btn mbs-btn--ghost" href="<?php echo esc_url(is_string($bundles_url) ? $bundles_url : home_url('/shop/')); ?>"><?php esc_html_e('View Bundles & Deals', 'mad-baits'); ?></a>
			</div>
		</section>
	</template>

	<p class="mbs-session-privacy" data-mbs-privacy hidden>
		<?php esc_html_e('Your Session logs are private to your account. Location is only used when you allow it.', 'mad-baits'); ?>
	</p>
</main>

<?php
get_footer();
