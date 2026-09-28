<?php
/**
 * Template Name: Catch Reports
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
$submit_url = function_exists('mad_baits_get_submit_catch_page_url') ? mad_baits_get_submit_catch_page_url() : home_url('/submit-catch/');

$hero_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => (int) get_queried_object_id(),
			'candidate_filenames' => array('jerry-wading-rod-setup.png', 'jerry-sunset-cast.png', 'catch-reports-hero.jpg', 'catch-reports.jpg', 'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg', 'CUP-LOGOv2-1.svg'),
			'semantic_terms'      => array('catch', 'angler', 'session', 'boilie'),
		)
	)
	: '';

$selected_bait  = isset($_GET['bait']) ? sanitize_text_field(wp_unslash($_GET['bait'])) : '';
$selected_range = isset($_GET['range']) ? sanitize_text_field(wp_unslash($_GET['range'])) : '';
$current_page   = max(1, absint(get_query_var('paged') ? get_query_var('paged') : get_query_var('page')));

$meta_keys = function_exists('mad_baits_get_catch_report_meta_keys')
	? mad_baits_get_catch_report_meta_keys()
	: array(
		'fish_weight' => '_mad_catch_fish_weight',
		'venue' => '_mad_catch_venue',
		'bait_used' => '_mad_catch_bait_used',
		'product_link' => '_mad_catch_product_link',
	);

$filter_meta_query = array('relation' => 'AND');
if ('' !== $selected_bait) {
	$filter_meta_query[] = array(
		'key'     => $meta_keys['bait_used'],
		'value'   => $selected_bait,
		'compare' => 'LIKE',
	);
}

if ('' !== $selected_range) {
	$filter_meta_query[] = array(
		'key'     => $meta_keys['bait_used'],
		'value'   => $selected_range,
		'compare' => 'LIKE',
	);
}

$query_args = array(
	'post_type'      => 'catch_report',
	'post_status'    => 'publish',
	'posts_per_page' => 12,
	'paged'          => $current_page,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

if (count($filter_meta_query) > 1) {
	$query_args['meta_query'] = $filter_meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
}

$catch_query = new WP_Query($query_args);

$bait_options = array();
$venue_options = array();
$option_ids   = get_posts(
	array(
		'post_type'      => 'catch_report',
		'post_status'    => 'publish',
		'posts_per_page' => 150,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

if (is_array($option_ids) && function_exists('mad_baits_get_catch_report_meta')) {
	foreach ($option_ids as $option_id) {
		$bait_value = mad_baits_get_catch_report_meta((int) $option_id, 'bait_used');
		$venue_value = mad_baits_get_catch_report_meta((int) $option_id, 'venue');
		if ('' === $bait_value) {
			$bait_value = '';
		}
		if ('' !== $bait_value) {
			$bait_options[] = $bait_value;
		}
		if ('' !== $venue_value) {
			$venue_options[] = $venue_value;
		}
	}
}

$bait_options = array_values(array_unique(array_filter($bait_options)));
sort($bait_options, SORT_NATURAL | SORT_FLAG_CASE);

$venue_options = array_values(array_unique(array_filter($venue_options)));
$placeholder_cards = array(
	array(
		'title' => __('Angler Name Placeholder', 'mad-baits'),
		'weight' => __('Fish Weight Placeholder', 'mad-baits'),
		'bait' => __('Bait Used Placeholder', 'mad-baits'),
		'venue' => __('Venue Placeholder', 'mad-baits'),
		'copy' => __('Example layout: replace with your first real catch report to begin building social proof.', 'mad-baits'),
	),
	array(
		'title' => __('Campaign Capture Placeholder', 'mad-baits'),
		'weight' => __('Weight Placeholder', 'mad-baits'),
		'bait' => __('Hookbait Placeholder', 'mad-baits'),
		'venue' => __('Water Type Placeholder', 'mad-baits'),
		'copy' => __('Add report images, bait data and venue details in the Catch Report CPT for auto-population.', 'mad-baits'),
	),
	array(
		'title' => __('Session Story Placeholder', 'mad-baits'),
		'weight' => __('Weight Placeholder', 'mad-baits'),
		'bait' => __('Bait System Placeholder', 'mad-baits'),
		'venue' => __('Venue Placeholder', 'mad-baits'),
		'copy' => __('Keep each report structured with angler title, fish weight, bait used and venue for best presentation.', 'mad-baits'),
	),
);
?>

<main id="primary" class="site-main page-main catch-reports-page mb-premium-page mb-premium-page--catch-reports">
	<section class="mb-premium-hero catch-reports-page__hero<?php echo $hero_image_url ? '' : ' mb-premium-hero--fallback'; ?>"<?php if ($hero_image_url) : ?> style="<?php echo esc_attr("--mb-premium-hero-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?>>
		<div class="container catch-reports-page__hero-inner">
			<p class="mb-premium-hero__kicker"><?php esc_html_e('Mad Baits Social Proof', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('Catch Reports', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Real captures from real anglers, showing proven bait match-ups, fish weights and venue context you can apply to your own campaign planning.', 'mad-baits'); ?></p>
			<div class="mb-premium-hero__actions">
				<a class="mad-button" href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Submit Your Catch', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Proven Baits', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>

	<section class="section mb-premium-benefits">
		<div class="container">
			<div class="mb-premium-benefits__grid">
				<article class="mb-premium-benefits__card">
					<h2><?php esc_html_e('Report Count', 'mad-baits'); ?></h2>
					<p><?php echo esc_html(sprintf(_n('%s published report', '%s published reports', (int) $catch_query->found_posts, 'mad-baits'), number_format_i18n((int) $catch_query->found_posts))); ?></p>
				</article>
				<article class="mb-premium-benefits__card">
					<h2><?php esc_html_e('Unique Bait Mentions', 'mad-baits'); ?></h2>
					<p><?php echo esc_html(sprintf(_n('%s tracked bait', '%s tracked baits', count($bait_options), 'mad-baits'), number_format_i18n((int) count($bait_options)))); ?></p>
				</article>
				<article class="mb-premium-benefits__card">
					<h2><?php esc_html_e('Venue Coverage', 'mad-baits'); ?></h2>
					<p><?php echo esc_html(sprintf(_n('%s venue style logged', '%s venue styles logged', count($venue_options), 'mad-baits'), number_format_i18n((int) count($venue_options)))); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section id="catch-reports-filter" class="section catch-reports-page__filters">
		<div class="container">
			<form method="get" class="mad-catch-filters">
				<label for="mad-catch-bait-filter">
					<span><?php esc_html_e('Filter by bait', 'mad-baits'); ?></span>
					<select id="mad-catch-bait-filter" name="bait">
						<option value=""><?php esc_html_e('All bait types', 'mad-baits'); ?></option>
						<?php foreach ($bait_options as $bait_option) : ?>
							<option value="<?php echo esc_attr((string) $bait_option); ?>"<?php selected($selected_bait, (string) $bait_option); ?>>
								<?php echo esc_html((string) $bait_option); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label for="mad-catch-range-filter">
					<span><?php esc_html_e('Range keyword', 'mad-baits'); ?></span>
					<input id="mad-catch-range-filter" type="text" name="range" value="<?php echo esc_attr($selected_range); ?>" placeholder="<?php esc_attr_e('e.g. Nutz, Fishmeal, Pop-Up', 'mad-baits'); ?>" />
				</label>
				<div class="mad-catch-filters__actions">
					<button type="submit" class="mad-button mad-button--small"><?php esc_html_e('Apply Filter', 'mad-baits'); ?></button>
					<a class="text-link" href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('Reset', 'mad-baits'); ?></a>
				</div>
			</form>
		</div>
	</section>

	<section class="section section--contrast catch-reports-page__results mb-premium-products">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Latest Reports', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Session Results', 'mad-baits'); ?></h2>
				</div>
				<span class="catch-reports-page__count">
					<?php
					printf(
						esc_html(_n('%s report', '%s reports', (int) $catch_query->found_posts, 'mad-baits')),
						esc_html(number_format_i18n((int) $catch_query->found_posts))
					);
					?>
				</span>
			</div>
			<?php if ($catch_query->have_posts()) : ?>
				<div class="mad-catch-grid mad-catch-grid--archive">
					<?php while ($catch_query->have_posts()) : ?>
						<?php $catch_query->the_post(); ?>
						<?php if (function_exists('mad_baits_render_catch_report_card')) : ?>
							<?php mad_baits_render_catch_report_card(get_the_ID(), array('class' => 'mad-catch-card--archive', 'show_excerpt' => true)); ?>
						<?php else : ?>
							<?php
							$post_id = (int) get_the_ID();
							$angler_name = get_the_title($post_id);
							$fish_weight = get_post_meta($post_id, $meta_keys['fish_weight'], true);
							$bait_used = get_post_meta($post_id, $meta_keys['bait_used'], true);
							$venue = get_post_meta($post_id, $meta_keys['venue'], true);
							?>
							<article class="mad-catch-card mad-catch-card--archive">
								<a class="mad-catch-card__media" href="<?php echo esc_url(get_permalink($post_id)); ?>">
									<?php if (has_post_thumbnail($post_id)) : ?>
										<?php echo get_the_post_thumbnail($post_id, 'large', array('class' => 'mad-catch-card__image', 'loading' => 'lazy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php else : ?>
										<span class="mad-catch-card__image-fallback" aria-hidden="true"></span>
									<?php endif; ?>
									<?php if (is_string($bait_used) && '' !== trim($bait_used)) : ?>
										<span class="mad-catch-card__bait-label"><?php echo esc_html((string) $bait_used); ?></span>
									<?php endif; ?>
								</a>
								<div class="mad-catch-card__content">
									<p class="mad-catch-card__eyebrow"><?php esc_html_e('Catch Report', 'mad-baits'); ?></p>
									<h3 class="mad-catch-card__title"><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html($angler_name); ?></a></h3>
									<ul class="mad-catch-card__stats">
										<?php if (is_string($fish_weight) && '' !== trim($fish_weight)) : ?>
											<li><span><?php esc_html_e('Weight', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $fish_weight); ?></strong></li>
										<?php endif; ?>
										<?php if (is_string($bait_used) && '' !== trim($bait_used)) : ?>
											<li><span><?php esc_html_e('Bait Used', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $bait_used); ?></strong></li>
										<?php endif; ?>
										<?php if (is_string($venue) && '' !== trim($venue)) : ?>
											<li><span><?php esc_html_e('Venue', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $venue); ?></strong></li>
										<?php endif; ?>
									</ul>
								</div>
							</article>
						<?php endif; ?>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No catch reports match this filter yet. Placeholder cards are shown below until reports are added.', 'mad-baits'); ?></p>
				<div class="mad-catch-grid mad-catch-grid--archive">
					<?php foreach ($placeholder_cards as $placeholder_card) : ?>
						<article class="mad-catch-card mad-catch-card--placeholder">
							<div class="mad-catch-card__media">
								<span class="mad-catch-card__image-fallback" aria-hidden="true"></span>
								<span class="mad-catch-card__bait-label"><?php echo esc_html((string) $placeholder_card['bait']); ?></span>
							</div>
							<div class="mad-catch-card__content">
								<p class="mad-catch-card__eyebrow"><?php esc_html_e('Placeholder Report', 'mad-baits'); ?></p>
								<h3 class="mad-catch-card__title"><?php echo esc_html((string) $placeholder_card['title']); ?></h3>
								<ul class="mad-catch-card__stats">
									<li><span><?php esc_html_e('Weight', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $placeholder_card['weight']); ?></strong></li>
									<li><span><?php esc_html_e('Bait Used', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $placeholder_card['bait']); ?></strong></li>
									<li><span><?php esc_html_e('Venue', 'mad-baits'); ?></span><strong><?php echo esc_html((string) $placeholder_card['venue']); ?></strong></li>
								</ul>
								<p class="mad-catch-card__excerpt"><?php echo esc_html((string) $placeholder_card['copy']); ?></p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			$pagination = paginate_links(
				array(
					'total'   => max(1, (int) $catch_query->max_num_pages),
					'current' => $current_page,
					'type'    => 'array',
				)
			);
			?>
			<?php if (! empty($pagination) && is_array($pagination)) : ?>
				<nav class="mad-catch-pagination" aria-label="<?php esc_attr_e('Catch report pages', 'mad-baits'); ?>">
					<?php foreach ($pagination as $pagination_link) : ?>
						<?php echo wp_kses_post($pagination_link); ?>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
	</section>

	<section class="section mb-premium-final-cta">
		<div class="container">
			<div class="mb-premium-final-cta__card">
				<p class="section__kicker"><?php esc_html_e('Share Your Session', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Turn Your Catch Into Proof', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Submit your report with key bait details and help other anglers fish smarter with proven Mad Baits setups.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Submit Your Catch', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Proven Baits', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
