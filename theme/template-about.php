<?php
/**
 * Template Name: About
 * Description: Premium brand story and bait philosophy page.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$bundle_url = function_exists('mad_baits_get_bundle_deals_url')
	? mad_baits_get_bundle_deals_url()
	: (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('bundles-deals', 'bundle-deals', 'bundles', 'deals'), $shop_url) : $shop_url);
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
$bundle_url = is_string($bundle_url) && '' !== $bundle_url ? $bundle_url : $shop_url;

$about_page = get_page_by_path('about');
if ($about_page instanceof WP_Post) {
	setup_postdata($about_page);
}
?>

<?php if (function_exists('mad_baits_render_page_hero')) : ?>
	<?php mad_baits_render_page_hero(array('post_id' => $about_page instanceof WP_Post ? (int) $about_page->ID : (int) get_queried_object_id(), 'slug' => 'about', 'title' => __('The Ultimate Collection Of Quality Carp Baits', 'mad-baits'))); ?>
<?php endif; ?>

<section class="section section--contrast about-story">
	<div class="container">
		<div class="about-story__grid">
			<article class="entry-content-wrap mb-content-card about-story__panel">
				<p class="section__kicker"><?php esc_html_e('Intro Story', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Built On Quality, Not Cheap Fillers', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Mad Baits is built on cutting-edge bait technology with an old-school respect for proven ingredients.', 'mad-baits'); ?></p>
				<p><?php esc_html_e('Every bait is made with high-quality ingredients, not cheap bulking agents, and rolled to order so it reaches anglers as fresh as possible.', 'mad-baits'); ?></p>
			</article>
			<article class="entry-content-wrap mb-content-card about-story__panel">
				<p class="section__kicker"><?php esc_html_e('Quality Ingredients', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Nutrition That Fish Keep Coming Back To', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Each mix is built from proven, digestible ingredients selected for food signal, leakage and consistent performance over long campaigns.', 'mad-baits'); ?></p>
			</article>
			<article class="entry-content-wrap mb-content-card about-story__panel">
				<p class="section__kicker"><?php esc_html_e('Rolled To Order', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Freshness You Can Fish With Confidence', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('We keep production tight and roll to order where possible, so your bait arrives in prime condition and ready for immediate use.', 'mad-baits'); ?></p>
			</article>
			<article class="entry-content-wrap mb-content-card about-story__panel">
				<p class="section__kicker"><?php esc_html_e('Proven Track Record', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Tested At Home And Abroad', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Our products are deliberately proven by customers, testers and consultants on varied waters, from UK day-ticket circuits to demanding continental trips.', 'mad-baits'); ?></p>
			</article>
			<article class="entry-content-wrap mb-content-card about-story__panel">
				<p class="section__kicker"><?php esc_html_e('Focused Range', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('A Tight Line-Up With Purpose', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('We would rather keep a smaller range that consistently catches than release endless extras. Everything in the lineup has earned its place on the bank.', 'mad-baits'); ?></p>
			</article>
			<article class="entry-content-wrap mb-content-card about-story__panel about-story__panel--cta">
				<p class="section__kicker"><?php esc_html_e('Ready To Fish', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Take The Same Standards To Your Next Session', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('This same quality-first mindset is championed by the wider Mad Baits team and trusted by anglers such as Jerry Hammond on serious campaign waters.', 'mad-baits'); ?></p>
				<div class="about-story__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Mad Baits', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('View Bundle Deals', 'mad-baits'); ?></a>
				</div>
			</article>
		</div>
	</div>
</section>

<?php
if ($about_page instanceof WP_Post) {
	wp_reset_postdata();
}
get_footer();

