<?php
/**
 * Template Name: Mad Baits TV
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="site-main page-main page-main--mad-baits-tv">
	<?php
	while (have_posts()) :
		the_post();

		$hero_image = function_exists('mad_baits_get_theme_image_uri')
			? mad_baits_get_theme_image_uri(
				array(
					'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
					'HAMMONDAPRIL_22_031.jpg',
					'IMG_6277.jpeg',
				)
			)
			: '';
		$hero_style = '';
		if (is_string($hero_image) && '' !== $hero_image) {
			$hero_style = ' style="' . esc_attr("--mbtv-hero-image: url('" . esc_url_raw($hero_image) . "');") . '"';
		}
		?>
		<section class="mbtv-hero"<?php echo $hero_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="container mbtv-hero__inner">
				<p class="mbtv-hero__kicker"><?php esc_html_e('Mad Baits TV', 'mad-baits'); ?></p>
				<h1><?php echo esc_html(get_the_title()); ?></h1>
				<p><?php esc_html_e('Premium session footage, tactical breakdowns and product insight from anglers who fish to win.', 'mad-baits'); ?></p>
			</div>
		</section>

		<?php if (function_exists('mad_baits_render_mad_baits_tv_component')) : ?>
			<?php
			mad_baits_render_mad_baits_tv_component(
				array(
					'section_class' => 'section section--contrast mad-baits-tv mad-baits-tv--page',
					'show_header'   => false,
					'show_cta'      => false,
					'show_filters'  => true,
					'grid_limit'    => 0,
				)
			);
			?>
		<?php else : ?>
			<section class="section section--contrast mad-baits-tv mad-baits-tv--unavailable">
				<div class="container">
					<p class="section__empty-state"><?php esc_html_e('Mad Baits TV is not available yet. Ensure the current theme includes Mad Baits TV helper functions.', 'mad-baits'); ?></p>
				</div>
			</section>
		<?php endif; ?>

		<section class="section section--page-content">
			<div class="container">
				<div class="entry-content-wrap mb-content-card">
					<div class="entry-content">
						<?php the_content(); ?>
					</div>
				</div>
			</div>
		</section>
		<?php
	endwhile;
	?>
</main>
<?php
get_footer();
