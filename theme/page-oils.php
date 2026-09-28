<?php
/**
 * Oils landing page.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url    = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
$bundle_url  = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
$product_ids = function_exists('mad_baits_get_landing_product_ids_by_category_slugs')
	? mad_baits_get_landing_product_ids_by_category_slugs(array('oils', 'oil'), 24)
	: array();
$compact_cards = function_exists('mad_baits_enable_compact_card_variations') ? mad_baits_enable_compact_card_variations() : false;
?>

<main id="primary" class="site-main page-main">
	<section class="section mad-shop-catalogue-section" id="mad-shop-grid">
		<div class="container mad-shop-catalogue">
			<header class="mad-shop-catalogue__header">
				<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
					<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs'); ?>
				<?php endif; ?>
				<h1 class="mad-shop-catalogue__title"><?php esc_html_e('Oils', 'mad-baits'); ?></h1>
				<p class="mad-shop-catalogue__intro"><?php esc_html_e('Browse oils and liquid boosts formulated to carry attraction through the water column and feed zone.', 'mad-baits'); ?></p>
			</header>

			<?php if (! empty($product_ids)) : ?>
				<div class="product-grid">
					<?php foreach ($product_ids as $product_id) : ?>
						<?php if (function_exists('mad_baits_render_product_card')) : ?>
							<?php mad_baits_render_product_card((int) $product_id, (bool) $compact_cards); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="mb-premium-empty-state">
					<h2><?php esc_html_e('Oils products coming soon', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('This page is ready. Products will show here when they are assigned to a matching Oils category.', 'mad-baits'); ?></p>
					<div class="mb-premium-hero__actions">
						<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Back to Shop', 'mad-baits'); ?></a>
						<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('View Bundles & Deals', 'mad-baits'); ?></a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
