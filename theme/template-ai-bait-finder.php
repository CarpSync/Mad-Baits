<?php
/**
 * Template Name: AI Bait Finder
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="site-main page-main page-main--ai-bait-finder">
	<?php
	while (have_posts()) :
		the_post();
		?>
		<?php if (function_exists('mad_baits_render_page_hero')) : ?>
			<?php mad_baits_render_page_hero(array('post_id' => (int) get_the_ID(), 'slug' => 'ai-bait-finder', 'title' => get_the_title())); ?>
		<?php endif; ?>

		<section class="section section--page-content">
			<div class="container">
				<?php
				// Always render finder UI in template regardless of page content.
				$ai_finder_markup = '';
				if (shortcode_exists('mad_baits_ai_bait_finder')) {
					$ai_finder_markup = do_shortcode('[mad_baits_ai_bait_finder]');
				} elseif (shortcode_exists('mad_baits_ai_finder')) {
					$ai_finder_markup = do_shortcode('[mad_baits_ai_finder]');
				} elseif (function_exists('mad_baits_ai_bait_finder_shortcode')) {
					$ai_finder_markup = (string) mad_baits_ai_bait_finder_shortcode();
				}
				?>
				<?php if ('' !== trim((string) $ai_finder_markup)) : ?>
					<?php echo $ai_finder_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<section class="mad-ai-bait-finder mad-ai-bait-finder--unavailable" aria-live="polite">
						<div class="mad-ai-bait-finder__shell">
							<div class="mad-ai-bait-finder__intro">
								<p class="section__kicker"><?php esc_html_e('AI Bait Finder', 'mad-baits'); ?></p>
								<h2><?php esc_html_e('Finder is not available yet', 'mad-baits'); ?></h2>
								<p><?php esc_html_e('Setup required: ensure this page uses the "AI Bait Finder" template and the active theme includes the finder shortcode registration.', 'mad-baits'); ?></p>
							</div>
						</div>
					</section>
				<?php endif; ?>
				<?php
				// Strip finder shortcodes from page content so the form is not rendered twice.
				$content = get_the_content();
				$content = preg_replace('/\[(?:mad_baits_ai_bait_finder|mad_baits_ai_finder)[^\]]*\]/i', '', (string) $content);
				$content = trim((string) $content);
				if ('' !== $content) :
					?>
				<div class="entry-content-wrap mb-content-card">
					<div class="entry-content">
						<?php echo apply_filters('the_content', $content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
