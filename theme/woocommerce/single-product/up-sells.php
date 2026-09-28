<?php
/**
 * Up-sells
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.6.0
 */

defined('ABSPATH') || exit;

if ($upsells) : ?>

	<section class="up-sells upsells products mad-session-upsell" aria-labelledby="mad-upsell-products-heading">
		<header class="mad-session-upsell__intro">
			<h2 id="mad-upsell-products-heading"><?php esc_html_e('Complete Your Session', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Pair this product with proven session-ready essentials.', 'mad-baits'); ?></p>
		</header>

		<div class="mad-session-upsell__products">
			<?php woocommerce_product_loop_start(); ?>

			<?php foreach ($upsells as $upsell) : ?>
				<?php
				$post_object = get_post($upsell->get_id());

				if (! $post_object instanceof WP_Post) {
					continue;
				}

				setup_postdata($GLOBALS['post'] = $post_object); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				?>
				<?php if (function_exists('mad_baits_render_product_card')) : ?>
					<li class="product type-product">
						<?php mad_baits_render_product_card($upsell); ?>
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
