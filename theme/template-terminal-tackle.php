<?php
/**
 * Template Name: Terminal Tackle
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');

$hero_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => (int) get_queried_object_id(),
			'candidate_filenames' => array('terminal-tackle-hero.png', 'ecac2f50a97548228dc72bbf424d39bes1900x490.png', 'jerry-rigging-bivvy.png', 'terminal-tackle-hero.jpg', 'terminal-tackle.jpg', 'jerry-wading-rod-setup.png', 'IMG_6273.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('terminal', 'terminal-tackle', 'tackle'),
			'semantic_terms'      => array('terminal', 'rig', 'hook', 'line', 'lead'),
		)
	)
	: '';

$tackle_product_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('terminal', 'tackle', 'rig', 'hook', 'line', 'lead', 'accessory'), 12)
	: array();

$group_map = array(
	'rigs' => array(
		'label' => __('Rigs', 'mad-baits'),
		'copy'  => __('Pre-tied and rig-building essentials for consistent presentation under pressure.', 'mad-baits'),
		'needles' => array('rig', 'combi', 'spinner', 'chod'),
	),
	'hooks' => array(
		'label' => __('Hooks', 'mad-baits'),
		'copy'  => __('Pattern options to suit pop-up, wafter and bottom-bait mechanics across different lakebeds.', 'mad-baits'),
		'needles' => array('hook', 'curve', 'wide gape', 'beaked'),
	),
	'line' => array(
		'label' => __('Line', 'mad-baits'),
		'copy'  => __('Hooklink and leader options for abrasion resistance, concealment and clean movement.', 'mad-baits'),
		'needles' => array('line', 'hooklink', 'leader', 'braid', 'fluorocarbon'),
	),
	'leads-accessories' => array(
		'label' => __('Leads & Accessories', 'mad-baits'),
		'copy'  => __('Lead systems, clips and accessories to complete a dependable end-tackle setup.', 'mad-baits'),
		'needles' => array('lead', 'accessory', 'clip', 'swivel', 'terminal'),
	),
);

$matched_groups = array();
if (taxonomy_exists('product_cat') && function_exists('mad_baits_get_matching_product_term_ids')) {
	foreach ($group_map as $group_key => $group_data) {
		$term_ids = mad_baits_get_matching_product_term_ids('product_cat', (array) $group_data['needles'], true);
		if (! empty($term_ids)) {
			$matched_groups[ $group_key ] = $term_ids;
		}
	}
}
?>

<main id="primary" class="site-main page-main mb-premium-page mb-premium-page--terminal-tackle">
	<section class="mb-premium-hero<?php echo $hero_image_url ? '' : ' mb-premium-hero--fallback'; ?>"<?php if ($hero_image_url) : ?> style="<?php echo esc_attr("--mb-premium-hero-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?>>
		<div class="container mb-premium-hero__inner">
			<p class="mb-premium-hero__kicker"><?php esc_html_e('Rig Confidence', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('Terminal Tackle', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Build reliable end tackle systems with hooks, lines, leads and accessories designed for clean presentation and fish-safe performance.', 'mad-baits'); ?></p>
			<div class="mb-premium-hero__actions">
				<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Terminal Tackle', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('View Full Shop', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>

	<section class="section section--contrast mb-premium-products">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Core Tackle', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Terminal Products', 'mad-baits'); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse all products', 'mad-baits'); ?></a>
			</div>
			<?php if (class_exists('WooCommerce') && ! empty($tackle_product_ids)) : ?>
				<div class="product-grid mb-premium-products__grid">
					<?php foreach ($tackle_product_ids as $product_id) : ?>
						<?php if (function_exists('mad_baits_render_product_card')) : ?>
							<?php mad_baits_render_product_card((int) $product_id, true); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php elseif (! class_exists('WooCommerce')) : ?>
				<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to show Terminal Tackle products.', 'mad-baits'); ?></p>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No tackle products matched yet. Add category/tag/name hints containing terminal, rig, hook, line or lead.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section mb-premium-system-grid">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="section__kicker"><?php esc_html_e('Category Breakdown', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Rigs, Hooks, Line and Leads', 'mad-baits'); ?></h2>
				</div>
			</div>
			<?php if (! empty($matched_groups) && taxonomy_exists('product_cat')) : ?>
				<div class="mb-premium-system-grid__cards">
					<?php foreach ($group_map as $group_key => $group_data) : ?>
						<?php
						$group_ids = isset($matched_groups[ $group_key ]) ? (array) $matched_groups[ $group_key ] : array();
						$group_link = $shop_url;
						if (! empty($group_ids)) {
							$group_term = get_term((int) $group_ids[0], 'product_cat');
							if ($group_term instanceof WP_Term) {
								$term_link = get_term_link($group_term);
								if (! is_wp_error($term_link) && is_string($term_link) && '' !== $term_link) {
									$group_link = $term_link;
								}
							}
						}
						?>
						<article class="mb-premium-system-grid__card">
							<h3><?php echo esc_html((string) $group_data['label']); ?></h3>
							<p><?php echo esc_html((string) $group_data['copy']); ?></p>
							<?php if (! empty($group_ids)) : ?>
								<a class="text-link" href="<?php echo esc_url($group_link); ?>"><?php esc_html_e('View category', 'mad-baits'); ?></a>
							<?php else : ?>
								<p class="mb-premium-system-grid__fallback"><?php esc_html_e('No matching category found yet. Create one with similar naming and this card will auto-link.', 'mad-baits'); ?></p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No rigs/hooks/line/leads-accessories categories found yet. Add product categories and this section will populate automatically.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section mb-premium-final-cta">
		<div class="container">
			<div class="mb-premium-final-cta__card">
				<p class="section__kicker"><?php esc_html_e('Precision End Tackle', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Finish Every Setup With Confidence', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Pair proven bait strategy with reliable terminal components so your presentation stays sharp from cast one to final light.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Tackle', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Ask Setup Advice', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
