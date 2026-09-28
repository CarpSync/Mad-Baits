<?php
/**
 * Template Name: Clothing
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');

$hero_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => (int) get_queried_object_id(),
			'candidate_filenames' => array('clothing-hero.jpg', 'clothing.jpg', 'IMG_0820-scaled.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('clothing', 'merchandise', 'merch'),
			'semantic_terms'      => array('clothing', 'hoodie', 'shirt', 'cap'),
		)
	)
	: '';

$clothing_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('clothing', 'merch', 'merchandise', 'hoodie', 'tee', 'shirt', 'cap'), 12)
	: array();
?>

<main id="primary" class="site-main page-main mb-premium-page mb-premium-page--clothing">
	<section class="mb-premium-hero<?php echo $hero_image_url ? '' : ' mb-premium-hero--fallback'; ?>"<?php if ($hero_image_url) : ?> style="<?php echo esc_attr("--mb-premium-hero-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?>>
		<div class="container mb-premium-hero__inner">
			<p class="mb-premium-hero__kicker"><?php esc_html_e('Lifestyle Collection', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('Clothing', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Premium on- and off-bank apparel for anglers who want practical comfort and strong Mad Baits identity in every session.', 'mad-baits'); ?></p>
			<div class="mb-premium-hero__actions">
				<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Wear The Brand', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse Full Shop', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>

	<section class="section mb-premium-lifestyle">
		<div class="container">
			<div class="mb-premium-lifestyle__panel">
				<p class="section__kicker"><?php esc_html_e('Built For The Bank', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Practical layers. Clean branding. Session-ready comfort.', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('From travel to overnighters, this range is designed to handle changing weather and long days while keeping the Mad Baits identity front and center.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Clothing', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>

	<section class="section section--contrast mb-premium-products">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Featured Drops', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Clothing & Merch Grid', 'mad-baits'); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse all products', 'mad-baits'); ?></a>
			</div>
			<?php if (class_exists('WooCommerce') && ! empty($clothing_ids)) : ?>
				<div class="product-grid mb-premium-products__grid mb-premium-products__grid--image-forward">
					<?php foreach ($clothing_ids as $product_id) : ?>
						<?php if (function_exists('mad_baits_render_product_card')) : ?>
							<?php mad_baits_render_product_card((int) $product_id, true); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php elseif (! class_exists('WooCommerce')) : ?>
				<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to show Clothing products.', 'mad-baits'); ?></p>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No clothing products matched yet. Add category/tag/name hints like clothing, merch, hoodie or cap.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section mb-premium-final-cta">
		<div class="container">
			<div class="mb-premium-final-cta__card">
				<p class="section__kicker"><?php esc_html_e('Represent Mad Baits', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Wear The Brand', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Take your bank-side identity beyond bait with premium apparel made for anglers who fish hard and live the culture.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Wear The Brand', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
