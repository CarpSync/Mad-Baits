<?php
/**
 * Template Name: Returns & Refunds
 * Description: Returns and damaged order guidance.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

?>

<?php if (function_exists('mad_baits_render_page_hero')) : ?>
	<?php mad_baits_render_page_hero(array('post_id' => (int) get_queried_object_id(), 'slug' => 'returns-refunds', 'title' => get_the_title())); ?>
<?php endif; ?>

<section class="section section--page-content">
	<div class="container container--narrow">
		<div class="entry-content-wrap mb-content-card">
			<h2><?php esc_html_e('Refund & Return Guidance', 'mad-baits'); ?></h2>
			<ul class="mad-list">
				<li><?php esc_html_e('Contact support with your order number before returning items.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Damaged deliveries should be reported promptly with photos.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Perishable bait may be subject to product-specific return limits.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Approved refunds are returned to the original payment method.', 'mad-baits'); ?></li>
			</ul>
			<div class="entry-content">
				<?php while (have_posts()) : the_post(); ?>
					<?php the_content(); ?>
				<?php endwhile; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

