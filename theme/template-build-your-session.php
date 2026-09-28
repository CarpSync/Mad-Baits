<?php
/**
 * Template Name: Build Your Session
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="site-main page-main page-main--build-session">
	<?php
	while (have_posts()) :
		the_post();
		?>
		<?php if (function_exists('mad_baits_render_page_hero')) : ?>
			<?php mad_baits_render_page_hero(array('post_id' => (int) get_the_ID(), 'slug' => 'build-my-session', 'title' => get_the_title())); ?>
		<?php endif; ?>

		<section class="section section--page-content">
			<div class="container">
				<?php if (function_exists('mad_baits_session_builder_is_coming_soon') && mad_baits_session_builder_is_coming_soon()) : ?>
					<?php mad_baits_render_session_app_coming_soon_page(); ?>
				<?php elseif (shortcode_exists('mad_baits_session_builder')) : ?>
					<?php echo do_shortcode('[mad_baits_session_builder]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<p class="section__empty-state"><?php esc_html_e('Session builder shortcode is not available.', 'mad-baits'); ?></p>
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
