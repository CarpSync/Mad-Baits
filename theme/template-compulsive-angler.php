<?php
/**
 * Template Name: Compulsive Angler
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
$ai_bait_finder_url = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');

$compulsive_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('compulsive', 'campaign', 'big fish', 'food bait', 'session confidence'), 12)
	: array();

$compulsive_profile = function_exists('mad_baits_get_range_brand_profile')
	? mad_baits_get_range_brand_profile('compulsive')
	: array();

$hookbaits_url = function_exists('mad_baits_get_hookbaits_page_url') ? mad_baits_get_hookbaits_page_url() : $shop_url;

$campaign_sections = array(
	array(
		'title' => __('Campaign Bait Core', 'mad-baits'),
		'copy'  => __('Food-bait-first options for anglers investing in longer-term patterns and regular feeding windows.', 'mad-baits'),
		'needles' => array('compulsive', 'boilie', 'food bait', 'campaign'),
	),
	array(
		'title' => __('Session Refinement', 'mad-baits'),
		'copy'  => __('Hookbait and attractor options for switching pressure points without abandoning your confidence profile.', 'mad-baits'),
		'needles' => array('hookbait', 'wafter', 'pop-up', 'boosted'),
	),
	array(
		'title' => __('Stock-Up Priority', 'mad-baits'),
		'copy'  => __('Bundle-friendly products that keep prep simple and bait consistency high across the month.', 'mad-baits'),
		'needles' => array('bundle', 'deal', 'session pack', 'multi'),
	),
);
?>

<main id="primary" class="site-main page-main mb-premium-page mb-premium-page--compulsive-angler">
	<?php
	if (! empty($compulsive_profile) && function_exists('mad_baits_render_range_brand_section')) {
		mad_baits_render_range_brand_section(
			$compulsive_profile,
			array(
				'layout'        => 'story',
				'shop_url'      => $shop_url,
				'hookbaits_url' => $hookbaits_url,
			)
		);
	}
	?>

	<section class="section mb-premium-benefits">
		<div class="container">
			<div class="mb-premium-benefits__grid">
				<article class="mb-premium-benefits__card">
					<h2><?php esc_html_e('Campaign-Level Thinking', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('Built around repeatable baiting systems for anglers fishing beyond one-off trips.', 'mad-baits'); ?></p>
				</article>
				<article class="mb-premium-benefits__card">
					<h2><?php esc_html_e('Premium Product Logic', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('Products grouped around purpose so every order supports a deliberate angling plan.', 'mad-baits'); ?></p>
				</article>
				<article class="mb-premium-benefits__card">
					<h2><?php esc_html_e('Decision Support Built In', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('Use AI recommendations and bundle routes to remove guesswork before your next campaign push.', 'mad-baits'); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="section section--contrast mb-premium-products">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Compulsive Selection', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Products For Serious Sessions', 'mad-baits'); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse all products', 'mad-baits'); ?></a>
			</div>
			<?php if (class_exists('WooCommerce') && ! empty($compulsive_ids)) : ?>
				<div class="product-grid mb-premium-products__grid">
					<?php foreach ($compulsive_ids as $product_id) : ?>
						<?php if (function_exists('mad_baits_render_product_card')) : ?>
							<?php mad_baits_render_product_card((int) $product_id, true); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php elseif (! class_exists('WooCommerce')) : ?>
				<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to show Compulsive Angler products.', 'mad-baits'); ?></p>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No matching products found. Add category/tag/name hints containing compulsive or campaign to populate this grid.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section mb-premium-system-grid">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="section__kicker"><?php esc_html_e('Campaign Focus', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Three Paths For Dedicated Anglers', 'mad-baits'); ?></h2>
				</div>
			</div>
			<div class="mb-premium-system-grid__cards">
				<?php foreach ($campaign_sections as $section) : ?>
					<?php
					$section_ids = function_exists('mad_baits_get_semantic_product_ids')
						? mad_baits_get_semantic_product_ids((array) $section['needles'], 3)
						: array();
					?>
					<article class="mb-premium-system-grid__card">
						<h3><?php echo esc_html((string) $section['title']); ?></h3>
						<p><?php echo esc_html((string) $section['copy']); ?></p>
						<?php if (! empty($section_ids)) : ?>
							<ul class="mb-premium-system-grid__list">
								<?php foreach ($section_ids as $section_product_id) : ?>
									<li><a href="<?php echo esc_url(get_permalink((int) $section_product_id)); ?>"><?php echo esc_html(get_the_title((int) $section_product_id)); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section mb-premium-final-cta">
		<div class="container">
			<div class="mb-premium-final-cta__card">
				<p class="section__kicker"><?php esc_html_e('Ready To Scale Up', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Build A Serious Session Plan', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Combine campaign-grade products with live stock support and practical recommendations to keep your angling momentum moving.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Bundle Deals', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($ai_bait_finder_url); ?>"><?php esc_html_e('Use AI Bait Finder', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
