<?php
/**
 * Default page template.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

get_header();

$page_id   = (int) get_queried_object_id();
$page_slug = sanitize_title((string) get_post_field('post_name', $page_id));
$is_legal_page = in_array($page_slug, array('privacy-policy', 'terms-conditions', 'cookie-policy', 'terms-and-conditions', 'privacy', 'terms', 'cookies'), true);
?>

<?php
if (function_exists('mad_baits_render_page_hero')) {
	mad_baits_render_page_hero(
		array(
			'post_id' => $page_id,
			'slug'    => $page_slug,
			'title'   => single_post_title('', false),
		)
	);
}
?>

<section class="section section--page-content">
	<div class="container <?php echo $is_legal_page ? 'container--narrow' : ''; ?>">
		<?php
		while (have_posts()) :
			the_post();
			$content_card_classes = $is_legal_page ? 'entry-content-wrap mb-legal-content-card' : 'entry-content-wrap mb-content-card';
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class($content_card_classes); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();

