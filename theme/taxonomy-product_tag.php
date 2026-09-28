<?php
/**
 * Product tag archive — signature ranges use unified premium grid.
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 4.7.0
 */

defined('ABSPATH') || exit;

get_header();

do_action('woocommerce_before_main_content');

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
$boilies_url = function_exists('mad_baits_get_boilie_range_url')
	? mad_baits_get_boilie_range_url()
	: (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('boilies', 'boilie'), $shop_url) : $shop_url);
$hookbaits_url = function_exists('mad_baits_get_product_cat_link')
	? mad_baits_get_product_cat_link(array('hookbaits', 'hookbait', 'hook-baits'), $shop_url)
	: $shop_url;

$enable_compact_card_variations = function_exists('mad_baits_enable_compact_card_variations')
	? mad_baits_enable_compact_card_variations()
	: false;

$current_term = get_queried_object();
$current_slug = ($current_term instanceof WP_Term) ? sanitize_title((string) $current_term->slug) : '';
$current_name = ($current_term instanceof WP_Term) ? (string) $current_term->name : __('Range', 'mad-baits');
if ($current_term instanceof WP_Term && function_exists('mad_baits_get_range_display_label')) {
	$current_name = mad_baits_get_range_display_label($current_slug, $current_name);
}
$term_link    = ($current_term instanceof WP_Term) ? get_term_link($current_term) : '';
$term_link    = (! is_wp_error($term_link) && is_string($term_link) && '' !== $term_link) ? $term_link : $shop_url;

$profile_slug = $current_slug;
if ('p-fish-2' === $profile_slug) {
	$profile_slug = 'p-fish';
}

$range_profile = function_exists('mad_baits_get_range_brand_profile')
	? mad_baits_get_range_brand_profile($profile_slug)
	: array();

$is_signature_range = function_exists('mad_baits_is_signature_range_tag')
	? mad_baits_is_signature_range_tag($current_slug)
	: false;

if (! empty($range_profile) && function_exists('mad_baits_render_range_brand_section')) {
	mad_baits_render_range_brand_section(
		$range_profile,
		array(
			'term'             => $current_term,
			'shop_url'         => $shop_url,
			'term_link'        => $term_link,
			'boilie_range_url' => $boilies_url,
			'hookbaits_url'    => $hookbaits_url,
			'ai_url'           => function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/'),
			'build_url'        => function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('build-my-session', 'build-my-session') : home_url('/build-my-session/'),
			'compact'          => true,
		)
	);
}

if ($is_signature_range && function_exists('mad_baits_render_range_unified_hub')) {
	mad_baits_render_range_unified_hub(
		$current_slug,
		function_exists('mad_baits_get_range_display_label') ? mad_baits_get_range_display_label($current_slug, $current_name) : $current_name,
		$enable_compact_card_variations
	);
} else {
	?>
	<section class="section mad-shop-catalogue-section" id="mad-shop-grid">
		<div class="container mad-shop-catalogue">
			<header class="mad-shop-catalogue__header">
				<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
					<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs'); ?>
				<?php endif; ?>
				<h1 class="mad-shop-catalogue__title"><?php echo esc_html(function_exists('mad_baits_get_range_landing_heading') ? mad_baits_get_range_landing_heading($current_name) : $current_name); ?></h1>
			</header>

			<?php if (woocommerce_product_loop()) : ?>
				<div class="mad-shop-toolbar" aria-label="<?php esc_attr_e('Shop sorting and results', 'mad-baits'); ?>">
					<?php do_action('woocommerce_before_shop_loop'); ?>
				</div>

				<div class="product-grid">
					<?php while (have_posts()) : ?>
						<?php the_post(); ?>
						<?php mad_baits_render_product_card(get_the_ID(), $enable_compact_card_variations); ?>
					<?php endwhile; ?>
				</div>

				<div class="mad-shop-pagination">
					<?php do_action('woocommerce_after_shop_loop'); ?>
				</div>
			<?php else : ?>
				<?php do_action('woocommerce_no_products_found'); ?>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

if ($is_signature_range) {
	?>
	<section class="section section--cta mad-range-hub__footer-cta">
		<div class="container">
			<div class="footer-cta mb-cta-panel">
				<div>
					<p class="section__kicker"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Need A Complete Match-Up For This Range?', 'mad-baits'); ?></h2>
				</div>
				<div class="mb-premium-hero__actions mad-range-hub__footer-actions">
					<a class="mad-button" href="<?php echo esc_url(function_exists('mad_baits_get_build_my_session_page_url') ? mad_baits_get_build_my_session_page_url() : home_url('/build-my-session/')); ?>">
						<?php esc_html_e('Build My Session', 'mad-baits'); ?>
					</a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>">
						<?php esc_html_e('View Bundles & Deals', 'mad-baits'); ?>
					</a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url(function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/')); ?>">
						<?php esc_html_e('Ask AI Bait Finder', 'mad-baits'); ?>
					</a>
				</div>
			</div>
		</div>
	</section>
	<?php
}

do_action('woocommerce_after_main_content');
get_footer();
