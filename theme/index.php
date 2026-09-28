<?php
/**
 * Fallback template.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

get_header();
?>

<section class="section">
	<div class="container container--narrow">
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class('entry-content-wrap'); ?>>
					<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry-content"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p><?php esc_html_e('No content found.', 'mad-baits'); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();

