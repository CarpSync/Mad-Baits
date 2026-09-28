<?php
/**
 * Shop archive template used by woocommerce.php router.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

do_action('woocommerce_before_main_content');

$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));
$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
$ai_finder_url = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/');
$category_links = array();
if (function_exists('mad_baits_get_final_shop_category_definitions') && function_exists('mad_baits_get_product_cat_link')) {
	$category_defs = mad_baits_get_final_shop_category_definitions();
	foreach ($category_defs as $category_key => $category_def) {
		if (empty($category_def['public']) || 'team-testing' === (string) $category_key) {
			continue;
		}
		$category_links[] = array(
			'label' => (string) ($category_def['label'] ?? ''),
			'url'   => mad_baits_get_product_cat_link((array) ($category_def['slugs'] ?? array()), $shop_url),
		);
	}
}

$range_links = array();
if (function_exists('mad_baits_get_final_shop_range_definitions') && function_exists('mad_baits_get_range_filter_url')) {
	foreach (mad_baits_get_final_shop_range_definitions() as $range_def) {
		$range_links[] = array(
			'label' => (string) ($range_def['label'] ?? ''),
			'url'   => mad_baits_get_range_filter_url((string) ($range_def['slug'] ?? ''), $shop_url),
		);
	}
}
$enable_compact_card_variations = function_exists('mad_baits_enable_compact_card_variations')
	? mad_baits_enable_compact_card_variations()
	: false;
$hero_style = '';
$hero_image = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'candidate_filenames' => array('jerry-rigging-bivvy.png', 'jerry-sunset-cast.png', 'HAMMONDAPRIL_22_031.jpg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('bundles-deals', 'bundle-deals', 'boilies', 'hookbaits'),
			'semantic_terms'      => array('bundle', 'boilie', 'hookbait'),
		)
	)
	: '';
$hero_vars = array(
	'--shop-hero-position' => '56% 32%',
	'--shop-hero-accent'   => '84% 12%',
);
if (is_string($hero_image) && '' !== $hero_image) {
	$hero_vars['--shop-hero-image'] = "url('" . esc_url_raw($hero_image) . "')";
}
$hero_style = ' style="' . esc_attr(function_exists('mad_baits_build_css_var_string') ? mad_baits_build_css_var_string($hero_vars) : '') . '"';

$catalogue_section_style = '';
$catalogue_section_image = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'candidate_filenames' => array('jerry-rigging-bivvy.png', '652683570_1340175298154264_5204164835026563315_n.jpg', 'IMG_6270-1.jpeg', 'HAMMONDAPRIL_22_031.jpg'),
			'product_cat_slugs'   => array('boilies', 'hookbaits', 'bundles-deals', 'bundle-deals', 'compulsive-anglers'),
			'semantic_terms'      => array('session', 'campaign', 'tackle', 'bank'),
		)
	)
	: '';
if (is_string($catalogue_section_image) && '' !== $catalogue_section_image) {
	$catalogue_section_style = ' style="' . esc_attr("--shop-catalogue-image: url('" . esc_url_raw($catalogue_section_image) . "');") . '"';
}
?>

<section class="shop-hero shop-hero--catalogue"<?php echo $hero_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
			<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs'); ?>
		<?php endif; ?>
		<p class="shop-hero__eyebrow"><?php esc_html_e('Mad Baits Store', 'mad-baits'); ?></p>
		<h1 class="shop-hero__title"><?php esc_html_e('Shop Mad Baits', 'mad-baits'); ?></h1>
		<p class="shop-hero__copy"><?php esc_html_e('Browse the full Mad Baits catalogue by final category or shop by range using attribute-led filters.', 'mad-baits'); ?></p>
		<ul class="shop-hero__highlights" aria-label="<?php esc_attr_e('Shop highlights', 'mad-baits'); ?>">
			<li><?php esc_html_e('Hand-finished bait and proven hookbait ranges', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Bundle deals and multi-buy value for session anglers', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Tackle and essentials selected to pair with our bait systems', 'mad-baits'); ?></li>
		</ul>
		<div class="shop-hero__actions">
			<a class="mad-button mad-button--small" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Categories', 'mad-baits'); ?></a>
			<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($ai_finder_url); ?>"><?php esc_html_e('Try AI Bait Finder', 'mad-baits'); ?></a>
		</div>
		<?php do_action('woocommerce_archive_description'); ?>
	</div>
</section>

<section id="mad-shop-grid" class="section mad-shop-catalogue-section"<?php echo $catalogue_section_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container mad-shop-catalogue">
		<?php if (woocommerce_product_loop()) : ?>
			<div class="mad-shop-toolbar" aria-label="<?php esc_attr_e('Shop sorting and results', 'mad-baits'); ?>">
				<?php do_action('woocommerce_before_shop_loop'); ?>
			</div>

			<?php if (! empty($category_links)) : ?>
				<nav class="mad-shop-quick-filters" aria-label="<?php esc_attr_e('Shop by category', 'mad-baits'); ?>">
					<?php foreach ($category_links as $category_link) : ?>
						<a class="mad-shop-quick-filters__link" href="<?php echo esc_url((string) $category_link['url']); ?>"><?php echo esc_html((string) $category_link['label']); ?></a>
					<?php endforeach; ?>
					<a class="mad-shop-quick-filters__link mad-shop-quick-filters__link--accent" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Bundles & Deals', 'mad-baits'); ?></a>
				</nav>
			<?php endif; ?>

			<?php if (! empty($range_links)) : ?>
				<nav class="mad-shop-quick-filters" aria-label="<?php esc_attr_e('Shop by range', 'mad-baits'); ?>">
					<?php foreach ($range_links as $range_link) : ?>
						<a class="mad-shop-quick-filters__link" href="<?php echo esc_url((string) $range_link['url']); ?>"><?php echo esc_html((string) $range_link['label']); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<div class="mad-shop-trust-strip" aria-label="<?php esc_attr_e('Mad Baits catalogue trust points', 'mad-baits'); ?>">
				<span><?php esc_html_e('Small-batch quality control', 'mad-baits'); ?></span>
				<span><?php esc_html_e('Fast dispatch and tracked delivery', 'mad-baits'); ?></span>
				<span><?php esc_html_e('Field-tested by real campaign anglers', 'mad-baits'); ?></span>
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
			<div class="mb-premium-empty-state">
				<h2><?php esc_html_e('No products found yet', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('We are updating this section right now. Browse bundles, ranges or the full catalogue while we refresh stock and listings.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Back to Shop', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('View Bundles & Deals', 'mad-baits'); ?></a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
do_action('woocommerce_after_main_content');

get_footer();

