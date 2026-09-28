<?php
/**
 * Related products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/related.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.3.0
 */

defined('ABSPATH') || exit;

if ($related_products) :
	/**
	 * Keep product-card images lazily loaded consistently with core template behavior.
	 */
	if (function_exists('wp_increase_content_media_count') && function_exists('wp_omit_loading_attr_threshold')) {
		$content_media_count = wp_increase_content_media_count(0);
		if ($content_media_count < wp_omit_loading_attr_threshold()) {
			wp_increase_content_media_count(wp_omit_loading_attr_threshold() - $content_media_count);
		}
	}
	?>

	<section class="related products mad-session-upsell" aria-labelledby="mad-related-products-heading">
		<header class="mad-session-upsell__intro">
			<h2 id="mad-related-products-heading"><?php esc_html_e('Complete Your Session', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Pair this product with proven session-ready essentials.', 'mad-baits'); ?></p>
		</header>

		<div class="mad-session-upsell__products">
			<?php woocommerce_product_loop_start(); ?>

			<?php foreach ($related_products as $related_product) : ?>
				<?php
				$post_object = get_post($related_product->get_id());

				if (! $post_object instanceof WP_Post) {
					continue;
				}

				setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				?>
				<?php if (function_exists('mad_baits_render_product_card')) : ?>
					<li class="product type-product">
						<?php
						$enable_compact = function_exists('mad_baits_enable_compact_card_variations')
							? mad_baits_enable_compact_card_variations()
							: true;
						mad_baits_render_product_card($related_product, $enable_compact, 'related');
						?>
					</li>
				<?php else : ?>
					<?php wc_get_template_part('content', 'product'); ?>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php woocommerce_product_loop_end(); ?>
		</div>
	</section>

	<?php
endif;

wp_reset_postdata();

