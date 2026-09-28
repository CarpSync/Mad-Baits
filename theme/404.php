<?php
/**
 * Branded 404 template.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

get_header();

$shop_url    = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));
$bundle_url  = function_exists('mad_baits_get_bundle_deals_url')
	? mad_baits_get_bundle_deals_url()
	: home_url('/product-category/bundles-deals/');
$ai_url      = function_exists('mad_baits_get_ai_bait_finder_url')
	? mad_baits_get_ai_bait_finder_url()
	: home_url('/ai-bait-finder/');
$home_url    = home_url('/');
$hero_style  = '';
$hero_image  = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'candidate_filenames' => array('jerry-rigging-bivvy.png', 'jerry-sunset-cast.png', 'HAMMONDAPRIL_22_031.jpg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('bundles-deals', 'boilies', 'hookbaits'),
			'semantic_terms'      => array('session', 'campaign', 'bank'),
		)
	)
	: '';
if (is_string($hero_image) && '' !== $hero_image && function_exists('mad_baits_build_css_var_string')) {
	$hero_style = ' style="' . esc_attr(
		mad_baits_build_css_var_string(
			array(
				'--shop-hero-image'    => "url('" . esc_url_raw($hero_image) . "')",
				'--shop-hero-position' => '56% 32%',
				'--shop-hero-accent'   => '84% 12%',
			)
		)
	) . '"';
}
?>

<section class="shop-hero shop-hero--catalogue mad-not-found-hero"<?php echo $hero_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<nav class="mb-hero-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'mad-baits'); ?>">
			<a class="mad-breadcrumb-pill" href="<?php echo esc_url($home_url); ?>"><?php esc_html_e('Home', 'mad-baits'); ?></a>
			<span class="mad-breadcrumb-pill mad-breadcrumb-pill--current" aria-current="page"><?php esc_html_e('Not found', 'mad-baits'); ?></span>
		</nav>
		<p class="shop-hero__eyebrow"><?php esc_html_e('Mad Baits', 'mad-baits'); ?></p>
		<h1 class="shop-hero__title"><?php esc_html_e('Page not found', 'mad-baits'); ?></h1>
		<p class="shop-hero__copy"><?php esc_html_e('That link may be outdated or the product has moved. Head back to the shop or start with a bundle deal.', 'mad-baits'); ?></p>
		<div class="shop-hero__actions">
			<a class="mad-button mad-button--small" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop all products', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Browse bundle deals', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($ai_url); ?>"><?php esc_html_e('Try AI Bait Finder', 'mad-baits'); ?></a>
		</div>
	</div>
</section>

<section class="section mad-not-found-panel">
	<div class="container container--narrow">
		<div class="mad-empty-cart-panel mad-not-found-panel__card">
			<p class="mad-empty-cart-panel__eyebrow"><?php esc_html_e('Need a hand?', 'mad-baits'); ?></p>
			<h2><?php esc_html_e('Let us point you in the right direction', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Browse the full Mad Baits catalogue, explore session-ready bundle deals, or use AI Bait Finder to match bait to your water.', 'mad-baits'); ?></p>
			<div class="mad-empty-cart-panel__actions">
				<a class="mad-button" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Start with a bundle', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Back to shop', 'mad-baits'); ?></a>
				<a class="text-link" href="<?php echo esc_url($home_url); ?>"><?php esc_html_e('Return home', 'mad-baits'); ?></a>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
