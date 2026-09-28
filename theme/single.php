<?php
/**
 * Default single post template.
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
		<?php
		while (have_posts()) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class('entry-content-wrap'); ?>>
				<p class="entry-meta"><?php echo esc_html(get_the_date()); ?></p>
				<h1 class="entry-title"><?php the_title(); ?></h1>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();

