<?php
/**
 * Template Name: Bait Comparison
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="site-main page-main page-main--bait-comparison">
	<?php
	while (have_posts()) :
		the_post();
		?>
		<?php if (function_exists('mad_baits_render_page_hero')) : ?>
			<?php mad_baits_render_page_hero(array('post_id' => (int) get_the_ID(), 'slug' => 'bait-comparison', 'title' => get_the_title())); ?>
		<?php endif; ?>

		<section class="section section--page-content">
			<div class="container">
				<?php if (shortcode_exists('mad_baits_bait_comparison')) : ?>
					<?php echo do_shortcode('[mad_baits_bait_comparison]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<p class="section__empty-state"><?php esc_html_e('Bait comparison shortcode is not available.', 'mad-baits'); ?></p>
				<?php endif; ?>

				<?php if (trim((string) get_the_content()) !== '') : ?>
					<div class="entry-content-wrap mb-content-card">
						<div class="entry-content">
							<?php the_content(); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
