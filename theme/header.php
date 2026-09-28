<?php
/**
 * Theme header.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

$account_url = wp_login_url();
$cart_url    = '#';
$cart_count  = 0;
$shop_url    = home_url('/shop/');
$cup_logo    = '';
$account_role_badge = array();
$build_session_url = function_exists('mad_baits_get_build_my_session_page_url') ? mad_baits_get_build_my_session_page_url() : home_url('/build-my-session/');

if (function_exists('wc_get_page_permalink')) {
	$account_url = wc_get_page_permalink('myaccount');
	$shop_url    = wc_get_page_permalink('shop');
}

if (function_exists('wc_get_cart_url')) {
	$cart_url = wc_get_cart_url();
}

if (function_exists('WC') && WC()->cart) {
	$cart_count = WC()->cart->get_cart_contents_count();
}

if (is_user_logged_in() && function_exists('mad_baits_get_user_discount_target_role_data')) {
	$account_role_badge = mad_baits_get_user_discount_target_role_data(wp_get_current_user());
}

$cup_logo_path = get_theme_file_path('/assets/img/CUP-LOGOv2-1.svg');
if ($cup_logo_path && file_exists($cup_logo_path)) {
	$cup_logo = get_theme_file_uri('/assets/img/CUP-LOGOv2-1.svg');
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e('Skip to content', 'mad-baits'); ?></a>
<?php
if (function_exists('mad_baits_render_mobile_app_top_bar')) {
	mad_baits_render_mobile_app_top_bar();
}
?>
<header class="site-header site-header--glass" data-site-header>
	<div class="site-header__announcement">
		<div class="container site-header__announcement-inner">
			<p><?php esc_html_e('Free delivery over £100 | Fast dispatch on in-stock bait', 'mad-baits'); ?></p>
		</div>
	</div>
	<div class="site-header__inner site-header__inner--full">
		<div class="site-branding">
			<a class="site-branding__link" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
				<?php
				if (function_exists('the_custom_logo') && has_custom_logo()) {
					the_custom_logo();
				} elseif ($cup_logo) {
					?>
					<img class="site-branding__cup-logo" src="<?php echo esc_url($cup_logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" />
					<?php
				} else {
					echo '<span class="site-branding__text">' . esc_html(get_bloginfo('name')) . '</span>';
				}
				?>
			</a>
		</div>

		<nav class="primary-nav" aria-label="<?php esc_attr_e('Primary menu', 'mad-baits'); ?>">
			<?php
			if (function_exists('mad_baits_use_enforced_primary_nav') && mad_baits_use_enforced_primary_nav()) {
				mad_baits_render_enforced_primary_nav();
			} else {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'primary-nav__menu',
						'fallback_cb'    => 'mad_baits_primary_menu_fallback',
					)
				);
			}
			?>
		</nav>

		<div class="site-header__actions">
			<a class="mad-button mad-button--small site-header__shop-button" href="<?php echo esc_url($shop_url); ?>">
				<?php esc_html_e('Shop Now', 'mad-baits'); ?>
			</a>
			<a class="site-header__link site-header__link--account" href="<?php echo esc_url($account_url); ?>">
				<span class="site-header__link-label"><?php esc_html_e('Account', 'mad-baits'); ?></span>
				<?php if (! empty($account_role_badge['label'])) : ?>
					<span class="mad-role-badge site-header__role-badge <?php echo esc_attr(isset($account_role_badge['badge_class']) ? (string) $account_role_badge['badge_class'] : ''); ?>">
						<?php echo esc_html((string) $account_role_badge['label']); ?>
					</span>
				<?php endif; ?>
			</a>
			<a class="site-header__link site-header__cart-link" href="<?php echo esc_url($cart_url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Basket with %d items', 'mad-baits'), absint($cart_count))); ?>">
				<span class="site-header__link-label"><?php esc_html_e('Basket', 'mad-baits'); ?></span>
				<span class="site-header__cart-count"><?php echo esc_html((string) $cart_count); ?></span>
			</a>
			<button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-haspopup="true">
				<span class="mobile-nav-toggle__line"></span>
				<span class="mobile-nav-toggle__line"></span>
				<span class="mobile-nav-toggle__line"></span>
				<span class="screen-reader-text"><?php esc_html_e('Toggle menu', 'mad-baits'); ?></span>
			</button>
		</div>
	</div>
</header>

<div class="mobile-nav-backdrop" id="mobile-menu-backdrop" data-mobile-nav-backdrop hidden aria-hidden="true"></div>
<nav id="mobile-menu" class="mobile-nav" hidden aria-hidden="true" aria-label="<?php esc_attr_e('Mobile menu', 'mad-baits'); ?>">
		<div class="mobile-nav__inner">
			<div class="mobile-nav__top">
				<p><?php esc_html_e('Mad Baits Navigation', 'mad-baits'); ?></p>
				<button class="mobile-nav__close" type="button" aria-label="<?php esc_attr_e('Close menu', 'mad-baits'); ?>" data-mobile-nav-close>
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="mobile-nav__brand"><?php echo esc_html(get_bloginfo('name')); ?></div>
			<?php
			if (function_exists('mad_baits_use_enforced_primary_nav') && mad_baits_use_enforced_primary_nav()) {
				mad_baits_render_enforced_mobile_nav();
			} else {
				wp_nav_menu(
					array(
						'theme_location' => 'mobile',
						'container'      => false,
						'menu_class'     => 'mobile-nav__menu',
						'fallback_cb'    => 'mad_baits_mobile_menu_fallback',
					)
				);
			}
			?>
			<div class="mobile-nav__actions">
				<a class="mad-button mad-button--small" href="<?php echo esc_url($shop_url); ?>">
					<?php esc_html_e('Shop Baits', 'mad-baits'); ?>
				</a>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($build_session_url); ?>">
					<?php esc_html_e('Build Your Session', 'mad-baits'); ?>
				</a>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($account_url); ?>">
					<?php esc_html_e('My Account', 'mad-baits'); ?>
				</a>
			</div>
			<?php do_action('mad_baits_mobile_nav_after'); ?>
		</div>
</nav>

<main id="content" class="site-main">

