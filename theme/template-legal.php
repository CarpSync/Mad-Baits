<?php
/**
 * Template Name: Legal Page
 * Description: Styled legal placeholder template for Privacy, Terms and Cookie pages.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>

<?php if (function_exists('mad_baits_render_page_hero')) : ?>
	<?php mad_baits_render_page_hero(array('post_id' => (int) get_queried_object_id(), 'slug' => sanitize_title((string) get_post_field('post_name', (int) get_queried_object_id())), 'title' => get_the_title())); ?>
<?php endif; ?>

<section class="section section--page-content">
	<div class="container container--narrow">
		<?php while (have_posts()) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class('entry-content-wrap mb-legal-content-card'); ?>>
				<div class="legal-note">
					<strong><?php esc_html_e('Important:', 'mad-baits'); ?></strong>
					<?php esc_html_e('This legal content is a placeholder and must be reviewed by a qualified professional before publishing.', 'mad-baits'); ?>
				</div>
				<div class="entry-content">
					<?php if (trim((string) get_the_content())) : ?>
						<?php the_content(); ?>
					<?php else : ?>
						<p><?php esc_html_e('Add your approved policy text here.', 'mad-baits'); ?></p>
					<?php endif; ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();

