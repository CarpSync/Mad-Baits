<?php
/**
 * Theme footer.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

$shop_url    = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));
$contact_url = home_url('/contact/');
$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
$cart_count  = function_exists('WC') && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
$build_url   = function_exists('mad_baits_get_build_my_session_page_url') ? mad_baits_get_build_my_session_page_url() : home_url('/build-my-session/');
$mobile_deals_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/');

$link_bundle   = function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('bundles-deals', 'bundle-deals', 'bundles', 'deals'), $shop_url) : $shop_url;
$link_boilies  = function_exists('mad_baits_get_boilie_range_url') ? mad_baits_get_boilie_range_url() : (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('boilies', 'boilie'), $shop_url) : $shop_url);
$link_hook     = function_exists('mad_baits_get_hookbaits_page_url') ? mad_baits_get_hookbaits_page_url() : (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('hookbaits', 'hook-baits'), $shop_url) : $shop_url);
$link_compulsive = function_exists('mad_baits_get_compulsive_angler_url') ? mad_baits_get_compulsive_angler_url() : (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('compulsive'), $shop_url) : $shop_url);
$link_terminal = function_exists('mad_baits_get_terminal_tackle_url') ? mad_baits_get_terminal_tackle_url() : (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('terminal', 'terminal-tackle'), $shop_url) : $shop_url);
$link_clothing = function_exists('mad_baits_get_clothing_page_url') ? mad_baits_get_clothing_page_url() : (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('clothing', 'merchandise'), $shop_url) : $shop_url);
$link_catch_reports = function_exists('mad_baits_get_catch_reports_url') ? mad_baits_get_catch_reports_url() : home_url('/catch-reports/');
$link_food_dips = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('food-dips', 'food-dips') : home_url('/food-dips/');
$link_oils      = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('oils', 'oils') : home_url('/oils/');
$link_rock_salt = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('rock-salt', 'rock-salt') : home_url('/rock-salt/');

$link_about    = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('about', 'about') : home_url('/about/');
$link_trade    = home_url('/trade/');
$link_ai_finder = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');
$link_delivery = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('delivery-information', 'delivery-information') : home_url('/delivery-information/');
$link_returns  = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('returns-refunds', 'returns-refunds') : home_url('/returns-refunds/');
$link_faq      = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('faq', 'faq') : home_url('/faq/');
$link_privacy  = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('privacy-policy', 'privacy-policy') : home_url('/privacy-policy/');
$link_terms    = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('terms-conditions', 'terms-conditions') : home_url('/terms-conditions/');
$link_cookies  = function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('cookie-policy', 'cookie-policy') : home_url('/cookie-policy/');

$footer_bg_image_candidates = array(
	'assets/img/jerry-sunset-cast.png',
	'assets/img/Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
	'assets/img/HAMMONDAPRIL_22_031.jpg',
	'assets/img/IMG_0820-scaled.jpeg',
	'assets/img/IMG_6277.jpeg',
	'assets/img/CUP-LOGOv2-1.svg',
);
$footer_logo_candidates = array(
	'assets/img/mad-yellow-logo-1.png',
	'assets/img/CUP-LOGOv2-1.svg',
);

if (function_exists('mad_baits_get_moody_background_candidates')) {
	$helper_footer_candidates = array_map(
		static function ($filename) {
			return 'assets/img/' . ltrim((string) $filename, '/');
		},
		mad_baits_get_moody_background_candidates('footer')
	);

	$footer_bg_image_candidates = array_values(array_unique(array_merge($helper_footer_candidates, $footer_bg_image_candidates)));
}

$footer_bg_image_url = '';
foreach ($footer_bg_image_candidates as $footer_bg_candidate) {
	$footer_bg_path = trailingslashit(get_template_directory()) . ltrim($footer_bg_candidate, '/');
	if (file_exists($footer_bg_path)) {
		$footer_bg_image_url = trailingslashit(get_template_directory_uri()) . ltrim($footer_bg_candidate, '/');
		break;
	}
}

if (! $footer_bg_image_url && function_exists('mad_baits_resolve_context_hero_image')) {
	$footer_bg_image_url = mad_baits_resolve_context_hero_image(
		array(
			'candidate_filenames' => array_map(
				static function ($path) {
					return basename((string) $path);
				},
				$footer_bg_image_candidates
			),
			'product_cat_slugs'   => array('bundles-deals', 'bundle-deals', 'boilies', 'hookbaits', 'compulsive-anglers'),
			'semantic_terms'      => array('campaign', 'session', 'boilie', 'hookbait'),
		)
	);
}

$footer_logo_url = '';
foreach ($footer_logo_candidates as $footer_logo_candidate) {
	$footer_logo_path = trailingslashit(get_template_directory()) . ltrim((string) $footer_logo_candidate, '/');
	if (file_exists($footer_logo_path)) {
		$footer_logo_url = trailingslashit(get_template_directory_uri()) . ltrim((string) $footer_logo_candidate, '/');
		break;
	}
}

$footer_classes = 'site-footer site-footer--premium';
if ($footer_bg_image_url) {
	$footer_classes .= ' site-footer--has-bg';
} else {
	$footer_classes .= ' site-footer--no-bg';
}
?>

<?php if (function_exists('mad_baits_render_app_home_modules')) : ?>
	<?php mad_baits_render_app_home_modules(); ?>
<?php endif; ?>

</main>

<footer
	class="<?php echo esc_attr($footer_classes); ?>"
	<?php if ($footer_bg_image_url) : ?>
		style="<?php echo esc_attr("--footer-bg-image: url('" . esc_url($footer_bg_image_url) . "');"); ?>"
	<?php endif; ?>
>
	<div class="container site-footer__premium-top">
		<div class="site-footer__brand-block">
			<?php if ($footer_logo_url) : ?>
				<img class="site-footer__logo-mark" src="<?php echo esc_url($footer_logo_url); ?>" alt="<?php esc_attr_e('Mad Baits logo', 'mad-baits'); ?>" loading="lazy" decoding="async" />
			<?php endif; ?>
			<p class="site-footer__brand"><?php echo esc_html(get_bloginfo('name')); ?></p>
			<p class="site-footer__tagline"><?php esc_html_e('Premium carp bait engineered for serious anglers and serious waters.', 'mad-baits'); ?></p>
			<p class="site-footer__meta"><?php esc_html_e('From food bait systems to campaign hookbait options, Mad Baits is built for confidence, consistency and results.', 'mad-baits'); ?></p>
		</div>

		<div class="site-footer__links-columns">
			<nav class="site-footer__column" aria-label="<?php esc_attr_e('Shop links', 'mad-baits'); ?>">
				<h3><?php esc_html_e('Shop', 'mad-baits'); ?></h3>
				<ul>
					<li><a href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_bundle); ?>"><?php esc_html_e('Bundles & Deals', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_boilies); ?>"><?php esc_html_e('Boilies', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_hook); ?>"><?php esc_html_e('Hookbaits', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_compulsive); ?>"><?php esc_html_e('Compulsive Angler', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_terminal); ?>"><?php esc_html_e('Terminal Tackle', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_food_dips); ?>"><?php esc_html_e('Food Dips', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_oils); ?>"><?php esc_html_e('Oils', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_rock_salt); ?>"><?php esc_html_e('Rock Salt', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_clothing); ?>"><?php esc_html_e('Clothing', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_catch_reports); ?>"><?php esc_html_e('Catch Reports', 'mad-baits'); ?></a></li>
				</ul>
			</nav>

			<nav class="site-footer__column" aria-label="<?php esc_attr_e('Support links', 'mad-baits'); ?>">
				<h3><?php esc_html_e('Support', 'mad-baits'); ?></h3>
				<ul>
					<li><a href="<?php echo esc_url($contact_url); ?>"><?php esc_html_e('Contact', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_ai_finder); ?>"><?php esc_html_e('AI Bait Finder', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_delivery); ?>"><?php esc_html_e('Delivery Information', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_returns); ?>"><?php esc_html_e('Returns & Refunds', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_faq); ?>"><?php esc_html_e('FAQ', 'mad-baits'); ?></a></li>
				</ul>
			</nav>

			<nav class="site-footer__column" aria-label="<?php esc_attr_e('Brand links', 'mad-baits'); ?>">
				<h3><?php esc_html_e('Brand', 'mad-baits'); ?></h3>
				<ul>
					<li><a href="<?php echo esc_url($link_about); ?>"><?php esc_html_e('About', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_trade); ?>"><?php esc_html_e('Trade', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_privacy); ?>"><?php esc_html_e('Privacy Policy', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_terms); ?>"><?php esc_html_e('Terms & Conditions', 'mad-baits'); ?></a></li>
					<li><a href="<?php echo esc_url($link_cookies); ?>"><?php esc_html_e('Cookie Policy', 'mad-baits'); ?></a></li>
				</ul>
			</nav>
		</div>

		<aside class="site-footer__support-card">
			<p class="site-footer__cta-kicker"><?php esc_html_e('Campaign Support', 'mad-baits'); ?></p>
			<h3 class="site-footer__cta-title"><?php esc_html_e('Need help choosing your next bait line?', 'mad-baits'); ?></h3>
			<p><?php esc_html_e('Speak with the Mad Baits team for session-ready recommendations.', 'mad-baits'); ?></p>
			<a class="mad-button" href="<?php echo esc_url($link_ai_finder); ?>"><?php esc_html_e('Try AI Bait Finder', 'mad-baits'); ?></a>
		</aside>

		<?php if (function_exists('mad_baits_get_fresh_rolling_widget_markup')) : ?>
			<?php echo mad_baits_get_fresh_rolling_widget_markup('footer'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>

		<?php do_action('mad_baits_footer_social'); ?>
	</div>

	<div class="site-footer__bottom site-footer__bottom--premium">
		<div class="container site-footer__bottom-inner">
			<div class="site-footer__company-details">
				<p class="site-footer__company-name"><?php esc_html_e('Mad Baits Supplies LTD', 'mad-baits'); ?></p>
				<p><?php esc_html_e('Company Number – 15736640', 'mad-baits'); ?></p>
				<p><?php esc_html_e('EC Animal Feed Hygiene Regulations Number GB702/00634', 'mad-baits'); ?></p>
			</div>
			<div class="site-footer__bottom-copy">
				<p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
				<p class="site-footer__credit">
					<a class="site-footer__credit-link" href="<?php echo esc_url('https://karpstudio.co.uk'); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e('Designed & Maintained by Karp Studio', 'mad-baits'); ?>
					</a>
				</p>
			</div>
			<div class="site-footer__legal-links">
				<a href="<?php echo esc_url($link_privacy); ?>"><?php esc_html_e('Privacy', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url($link_terms); ?>"><?php esc_html_e('Terms', 'mad-baits'); ?></a>
				<a href="<?php echo esc_url($link_cookies); ?>"><?php esc_html_e('Cookies', 'mad-baits'); ?></a>
			</div>
		</div>
		<?php if (function_exists('mad_baits_render_app_version_label')) : ?>
			<?php mad_baits_render_app_version_label('site-footer__app-version'); ?>
		<?php endif; ?>
	</div>
</footer>

<?php if (! (function_exists('is_checkout') && is_checkout()) && ! (function_exists('is_cart') && is_cart())) : ?>
	<nav class="mad-mobile-sales-bar" aria-label="<?php esc_attr_e('Quick mobile shopping', 'mad-baits'); ?>">
		<a href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop', 'mad-baits'); ?></a>
		<a href="<?php echo esc_url($mobile_deals_url); ?>"><?php esc_html_e('Deals', 'mad-baits'); ?></a>
		<a href="<?php echo esc_url($build_url); ?>"><?php esc_html_e('Build', 'mad-baits'); ?></a>
		<a href="<?php echo esc_url($cart_url); ?>">
			<?php esc_html_e('Basket', 'mad-baits'); ?>
			<?php if ($cart_count > 0) : ?>
				<span class="mad-mobile-sales-bar__count"><?php echo esc_html((string) $cart_count); ?></span>
			<?php endif; ?>
		</a>
	</nav>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>

