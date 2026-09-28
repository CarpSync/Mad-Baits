<?php
/**
 * Single Catch Report template.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
	the_post();
	$post_id       = (int) get_the_ID();
	$angler_name   = get_the_title($post_id);
	$fish_weight   = function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'fish_weight') : '';
	$venue         = function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'venue') : '';
	$bait_used     = function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'bait_used') : '';
	$product_link  = function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'product_link') : '';
	$products_used = function_exists('mad_baits_get_catch_report_products_used') ? mad_baits_get_catch_report_products_used($post_id) : array();
	$report_date   = get_the_date('', $post_id);

	$gallery_meta = get_post_meta($post_id, '_mad_baits_catch_gallery_ids', true);
	$gallery_ids    = is_string($gallery_meta) && '' !== trim($gallery_meta)
		? array_values(array_filter(array_unique(array_map('absint', array_map('trim', explode(',', $gallery_meta))))))
		: array();
	?>
	<main id="primary" class="site-main single-catch-report">
		<section class="section single-catch-report__hero">
			<div class="container">
				<div class="single-catch-report__media-wrap">
					<?php if (has_post_thumbnail($post_id)) : ?>
						<?php echo get_the_post_thumbnail($post_id, 'full', array('class' => 'single-catch-report__hero-image', 'loading' => 'eager')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<div class="single-catch-report__hero-fallback">
							<span><?php esc_html_e('No capture image supplied', 'mad-baits'); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<article class="single-catch-report__summary">
					<p class="section__kicker"><?php esc_html_e('Catch Report', 'mad-baits'); ?></p>
					<h1><?php echo esc_html((string) $angler_name); ?></h1>
					<ul class="single-catch-report__meta">
						<?php if ('' !== $fish_weight) : ?>
							<li><span><?php esc_html_e('Catch Weight', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $fish_weight); ?></strong></li>
						<?php endif; ?>
						<?php if ('' !== $venue) : ?>
							<li><span><?php esc_html_e('Venue', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $venue); ?></strong></li>
						<?php endif; ?>
						<?php if (is_string($report_date) && '' !== $report_date) : ?>
							<li><span><?php esc_html_e('Date', 'mad-baits'); ?></span><strong><?php echo esc_html($report_date); ?></strong></li>
						<?php endif; ?>
					</ul>
					<div class="single-catch-report__story entry-content">
						<?php the_content(); ?>
					</div>
				</article>
			</div>
		</section>

		<?php if (! empty($gallery_ids) && count($gallery_ids) > 1) : ?>
			<section class="section single-catch-report__gallery">
				<div class="container">
					<div class="single-catch-report__gallery-grid">
						<?php foreach ($gallery_ids as $gallery_id) : ?>
							<?php if ((int) get_post_thumbnail_id($post_id) === $gallery_id) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<figure class="single-catch-report__gallery-item">
								<?php echo wp_get_attachment_image($gallery_id, 'large', false, array('loading' => 'lazy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</figure>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section class="section single-catch-report__products">
			<div class="container">
				<div class="section__heading">
					<div>
						<p class="section__kicker"><?php esc_html_e('Session Bait Breakdown', 'mad-baits'); ?></p>
						<h2><?php esc_html_e('Products Used In This Capture', 'mad-baits'); ?></h2>
					</div>
				</div>

				<?php if (! empty($products_used) && function_exists('mad_baits_render_catch_report_product_card')) : ?>
					<div class="product-grid single-catch-report__product-grid">
						<?php foreach ($products_used as $product) : ?>
							<?php mad_baits_render_catch_report_product_card($product->get_id()); ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="single-catch-report__products-empty">
						<?php if ('' !== $bait_used) : ?>
							<p class="single-catch-report__bait"><?php echo esc_html(sprintf(__('Bait noted: %s', 'mad-baits'), $bait_used)); ?></p>
						<?php else : ?>
							<p><?php esc_html_e('No linked WooCommerce products were supplied for this report yet.', 'mad-baits'); ?></p>
						<?php endif; ?>
						<?php if ('' !== $product_link) : ?>
							<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($product_link); ?>"><?php esc_html_e('View Referenced Product Link', 'mad-baits'); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</main>
	<?php
endwhile;

get_footer();
