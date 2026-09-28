<?php
/**
 * Dedicated "What's In The Water?" recommender page.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>

<section class="water-finder-page" data-water-finder-app>
	<div class="water-finder-page__hero">
		<div class="container">
			<p class="water-finder-page__kicker"><?php esc_html_e('Mad Baits Recommender', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('What\'s In The Water?', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Answer a few quick questions and we\'ll build your ideal Mad Baits setup.', 'mad-baits'); ?></p>
			<button type="button" class="mad-button water-finder-page__start" data-water-finder-start>
				<?php esc_html_e('Start Bait Finder', 'mad-baits'); ?>
			</button>
		</div>
	</div>

	<div class="container water-finder-page__shell" data-water-finder-shell>
		<div class="water-finder-page__progress" aria-hidden="true">
			<span data-water-finder-progress></span>
		</div>

		<div class="water-finder-page__quiz" data-water-finder-quiz hidden>
			<div class="water-finder-page__question-head">
				<p data-water-finder-step-label></p>
				<h2 data-water-finder-question-title></h2>
			</div>
			<div class="water-finder-page__options" data-water-finder-options></div>
		</div>

		<div class="water-finder-page__result" data-water-finder-result hidden>
			<div class="water-finder-page__result-head">
				<p><?php esc_html_e('Recommended Setup', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Built for your session', 'mad-baits'); ?></h2>
			</div>
			<div class="water-finder-page__result-grid" data-water-finder-recommendations></div>
			<div class="water-finder-page__products" data-water-finder-products></div>
			<div class="water-finder-page__result-actions">
				<a class="mad-button" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>" data-water-finder-shop>
					<?php esc_html_e('Shop Recommendation', 'mad-baits'); ?>
				</a>
				<button type="button" class="mad-button mad-button--ghost" data-water-finder-reset>
					<?php esc_html_e('Build Another Setup', 'mad-baits'); ?>
				</button>
			</div>
		</div>

		<div class="water-finder-page__fallback" data-water-finder-fallback>
			<h2><?php esc_html_e('Quick setup questions', 'mad-baits'); ?></h2>
			<ol>
				<li><?php esc_html_e('Where are you fishing? Lake / River / Canal / Commercial / French Venue', 'mad-baits'); ?></li>
				<li><?php esc_html_e('What are the conditions? Clear / Coloured / Weedy / Silty / Pressured', 'mad-baits'); ?></li>
				<li><?php esc_html_e('How long is your session? Day Session / Overnight / Weekend / Week Trip', 'mad-baits'); ?></li>
				<li><?php esc_html_e('What season? Spring / Summer / Autumn / Winter', 'mad-baits'); ?></li>
				<li><?php esc_html_e('What\'s your approach? Instant Bite / Big Hit Feeding / Match The Hatch / High Attraction', 'mad-baits'); ?></li>
			</ol>
			<p>
				<a class="mad-button" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>">
					<?php esc_html_e('Shop Baits', 'mad-baits'); ?>
				</a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url(home_url('/contact/')); ?>">
					<?php esc_html_e('Contact the team', 'mad-baits'); ?>
				</a>
			</p>
		</div>

		<noscript>
			<div class="water-finder-page__noscript">
				<h2><?php esc_html_e('Quick setup questions', 'mad-baits'); ?></h2>
				<ol>
					<li><?php esc_html_e('Where are you fishing? Lake / River / Canal / Commercial / French Venue', 'mad-baits'); ?></li>
					<li><?php esc_html_e('What are the conditions? Clear / Coloured / Weedy / Silty / Pressured', 'mad-baits'); ?></li>
					<li><?php esc_html_e('How long is your session? Day Session / Overnight / Weekend / Week Trip', 'mad-baits'); ?></li>
					<li><?php esc_html_e('What season? Spring / Summer / Autumn / Winter', 'mad-baits'); ?></li>
					<li><?php esc_html_e('What\'s your approach? Instant Bite / Big Hit Feeding / Match The Hatch / High Attraction', 'mad-baits'); ?></li>
				</ol>
				<p>
					<a class="mad-button" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')); ?>">
						<?php esc_html_e('Shop Baits', 'mad-baits'); ?>
					</a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url(home_url('/contact/')); ?>">
						<?php esc_html_e('Contact the team', 'mad-baits'); ?>
					</a>
				</p>
			</div>
		</noscript>
	</div>
</section>

<?php
get_footer();
