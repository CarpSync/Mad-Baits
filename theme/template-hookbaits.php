<?php
/**
 * Template Name: Hookbaits
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
$boilie_range_url = function_exists('mad_baits_get_boilie_range_url') ? mad_baits_get_boilie_range_url() : $shop_url;

$hero_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'                 => (int) get_queried_object_id(),
			'candidate_filenames'     => array('hookbaits-hero.png', 'compulsive-triple-hookbaits.png', 'compulsive-pink-popups-hand.png', 'compulsive-yellow-popups-bank.png', 'compulsive-orange-popups-night.png', 'bait-spikez-product.webp', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'       => array('hookbaits', 'hookbait', 'pop-ups', 'wafters'),
			'semantic_terms'          => array('hookbait', 'pop-up', 'wafter', 'hardened'),
			'prefer_theme_candidates' => true,
			'allow_latest_product'    => false,
		)
	)
	: '';

$hookbait_product_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('hookbait', 'hookbaits', 'pop-up', 'popup', 'wafter', 'balanced', 'hardened'), 12)
	: array();

$pop_up_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('pop-up', 'popup', 'high attract', 'fluoro'), 4)
	: array();

$wafter_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('wafter', 'balanced hookbait', 'balanced'), 4)
	: array();

$boosted_ids = function_exists('mad_baits_get_semantic_product_ids')
	? mad_baits_get_semantic_product_ids(array('boosted', 'hardened', 'special', 'dumbell'), 4)
	: array();

$approach_cards = array(
	array(
		'title' => __('Match The Hatch', 'mad-baits'),
		'copy'  => __('Keep colour and profile aligned to your feed for low-risk presentation and repeat action.', 'mad-baits'),
	),
	array(
		'title' => __('High-Attract Trigger', 'mad-baits'),
		'copy'  => __('Use boosted pop-ups and washed-out singles when you need instant attention over sparse baiting.', 'mad-baits'),
	),
	array(
		'title' => __('Balanced Approach', 'mad-baits'),
		'copy'  => __('Rotate wafters and neutrally balanced hookbaits to maintain mechanics and subtle movement over each rig.', 'mad-baits'),
	),
);
?>

<main id="primary" class="site-main page-main mb-premium-page mb-premium-page--hookbaits">
	<section class="mb-premium-hero<?php echo $hero_image_url ? '' : ' mb-premium-hero--fallback'; ?>"<?php if ($hero_image_url) : ?> style="<?php echo esc_attr("--mb-premium-hero-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?>>
		<div class="container mb-premium-hero__inner">
			<p class="mb-premium-hero__kicker"><?php esc_html_e('Presentation Edge', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('Hookbaits', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Dial in pop-ups, wafters and boosted hookbaits that complement your feed and keep your rigs converting pressured chances.', 'mad-baits'); ?></p>
			<div class="mb-premium-hero__actions">
				<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Hookbaits', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Browse Boilies', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>

	<section class="section mb-premium-benefits">
		<div class="container">
			<div class="mb-premium-benefits__grid">
				<?php foreach ($approach_cards as $approach_card) : ?>
					<article class="mb-premium-benefits__card">
						<h2><?php echo esc_html((string) $approach_card['title']); ?></h2>
						<p><?php echo esc_html((string) $approach_card['copy']); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section--contrast mb-premium-products">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Hookbait Selection', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Featured Hookbaits', 'mad-baits'); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse all products', 'mad-baits'); ?></a>
			</div>
			<?php if (class_exists('WooCommerce') && ! empty($hookbait_product_ids)) : ?>
				<div class="product-grid mb-premium-products__grid">
					<?php foreach ($hookbait_product_ids as $product_id) : ?>
						<?php if (function_exists('mad_baits_render_product_card')) : ?>
							<?php mad_baits_render_product_card((int) $product_id, true); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php elseif (! class_exists('WooCommerce')) : ?>
				<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to show Hookbait products.', 'mad-baits'); ?></p>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No hookbait products matched yet. Add category/tag/name keywords like hookbait, pop-up or wafter.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section mb-premium-system-grid">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="section__kicker"><?php esc_html_e('By Hookbait Type', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Pop-Ups, Wafters and Boosted', 'mad-baits'); ?></h2>
				</div>
			</div>
			<div class="mb-premium-system-grid__cards">
				<article class="mb-premium-system-grid__card">
					<h3><?php esc_html_e('Pop-Ups', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Go bright and buoyant when you need instant takes and visual confidence over cleaner spots.', 'mad-baits'); ?></p>
					<?php if (! empty($pop_up_ids)) : ?>
						<ul class="mb-premium-system-grid__list">
							<?php foreach (array_slice($pop_up_ids, 0, 3) as $product_id) : ?>
								<li><a href="<?php echo esc_url(get_permalink((int) $product_id)); ?>"><?php echo esc_html(get_the_title((int) $product_id)); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>
				<article class="mb-premium-system-grid__card">
					<h3><?php esc_html_e('Wafters', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('A balanced presentation for harder-fished venues where subtle movement often outperforms loud singles.', 'mad-baits'); ?></p>
					<?php if (! empty($wafter_ids)) : ?>
						<ul class="mb-premium-system-grid__list">
							<?php foreach (array_slice($wafter_ids, 0, 3) as $product_id) : ?>
								<li><a href="<?php echo esc_url(get_permalink((int) $product_id)); ?>"><?php echo esc_html(get_the_title((int) $product_id)); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>
				<article class="mb-premium-system-grid__card">
					<h3><?php esc_html_e('Boosted Hookbaits', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Increase pull with elevated flavour and leakage when fish activity windows are short or patchy.', 'mad-baits'); ?></p>
					<?php if (! empty($boosted_ids)) : ?>
						<ul class="mb-premium-system-grid__list">
							<?php foreach (array_slice($boosted_ids, 0, 3) as $product_id) : ?>
								<li><a href="<?php echo esc_url(get_permalink((int) $product_id)); ?>"><?php echo esc_html(get_the_title((int) $product_id)); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</article>
			</div>
		</div>
	</section>

	<section class="section mb-premium-final-cta">
		<div class="container">
			<div class="mb-premium-final-cta__card">
				<p class="section__kicker"><?php esc_html_e('Hookbait Confidence', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Refine Your Presentation For More Chances', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Build a hookbait lineup that fits your feed, water pressure and rig mechanics so every cast feels deliberate.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Hookbaits', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Browse Boilies', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
