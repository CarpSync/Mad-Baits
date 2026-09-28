<?php
/**
 * Template Name: Delivery Information
 * Description: Delivery and dispatch policy page.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

?>

<?php if (function_exists('mad_baits_render_page_hero')) : ?>
	<?php mad_baits_render_page_hero(array('post_id' => (int) get_queried_object_id(), 'slug' => 'delivery-information', 'title' => get_the_title())); ?>
<?php endif; ?>

<section class="section section--page-content">
	<div class="container container--narrow">
		<div class="entry-content-wrap mb-content-card">
			<h2><?php esc_html_e('Dispatch & Delivery', 'mad-baits'); ?></h2>
			<ul class="mad-list">
				<li><?php esc_html_e('Standard dispatch target: 1-2 working days.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Typical UK delivery: 2-4 working days after dispatch.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Order cut-off placeholder: 1:00pm weekdays.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Tracking details sent by email when available.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Fresh/frozen bait dispatch may have product-specific timing.', 'mad-baits'); ?></li>
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

