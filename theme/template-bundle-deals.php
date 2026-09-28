<?php
/**
 * Template Name: Bundle Deals
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');

$bundle_deals_url = function_exists('mad_baits_get_bundle_deals_url')
	? mad_baits_get_bundle_deals_url()
	: $shop_url;

$build_bundle_url = $shop_url;
$contact_url      = mad_baits_get_page_url('contact', 'contact');

$hero_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => (int) get_queried_object_id(),
			'candidate_filenames' => array('jerry-rigging-bivvy.png', 'IMG_6277.jpeg', 'IMG_6273.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('bundles-deals', 'bundle-deals', 'bundles', 'deals'),
			'semantic_terms'      => array('bundle', 'deal', 'session pack'),
		)
	)
	: '';

$cta_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'candidate_filenames' => array('compulsive-pink-popups-hand.png', 'IMG_6271.jpeg', 'IMG_6209.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('bundles-deals', 'bundle-deals', 'boilies', 'hookbaits'),
			'semantic_terms'      => array('bundle', 'session', 'boilie'),
		)
	)
	: '';

$bundle_products     = array();
$bundle_products_map = array();

if (class_exists('WooCommerce') && function_exists('wc_get_product') && (taxonomy_exists('product_cat') || taxonomy_exists('product_tag'))) {
	$semantics = array('bundle deals', 'bundles', 'deals', 'offers', 'bundle', 'deal', 'offer');
	$tax_query = array('relation' => 'OR');

	foreach (array('product_cat', 'product_tag') as $taxonomy) {
		if (! taxonomy_exists($taxonomy)) {
			continue;
		}

		$matched_ids = array();
		$terms       = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

		if (! is_wp_error($terms) && is_array($terms)) {
			foreach ($terms as $term) {
				if (! $term instanceof WP_Term) {
					continue;
				}

				$slug = sanitize_title((string) $term->slug);
				$name = sanitize_title((string) $term->name);

				foreach ($semantics as $needle) {
					$needle = sanitize_title((string) $needle);
					if ('' === $needle) {
						continue;
					}

					if (false !== strpos($slug, $needle) || false !== strpos($name, $needle)) {
						$matched_ids[] = (int) $term->term_id;
						break;
					}
				}
			}
		}

		$matched_ids = array_values(array_unique(array_filter(array_map('absint', $matched_ids))));
		if (! empty($matched_ids)) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $matched_ids,
			);
		}
	}

	if (count($tax_query) > 1) {
		$bundle_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 24,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'tax_query'      => $tax_query,
			)
		);

		if ($bundle_query->have_posts()) {
			while ($bundle_query->have_posts()) {
				$bundle_query->the_post();
				$product = wc_get_product(get_the_ID());
				if (! $product instanceof WC_Product) {
					continue;
				}

				$bundle_products[] = $product;
			}
			wp_reset_postdata();
		}
	}
}

if (! empty($bundle_products)) {
	foreach ($bundle_products as $product) {
		$bundle_products_map[ (int) $product->get_id() ] = $product;
	}
}

$featured_bundle = null;
if (! empty($bundle_products_map)) {
	foreach ($bundle_products_map as $product) {
		if ($product->is_featured()) {
			$featured_bundle = $product;
			break;
		}
	}

	if (! $featured_bundle) {
		foreach ($bundle_products_map as $product) {
			if ($product->is_on_sale()) {
				$featured_bundle = $product;
				break;
			}
		}
	}

	if (! $featured_bundle) {
		$featured_bundle = array_reduce(
			array_values($bundle_products_map),
			static function ($carry, $candidate) {
				if (! $candidate instanceof WC_Product) {
					return $carry;
				}
				if (! $carry instanceof WC_Product) {
					return $candidate;
				}

				return (float) $candidate->get_price() > (float) $carry->get_price() ? $candidate : $carry;
			}
		);
	}
}

$faq_items = array(
	array(
		'question' => __('What is included in a bundle deal?', 'mad-baits'),
		'answer'   => __('Bundle deals combine proven food bait, matching attraction support and campaign-friendly add-ons so you can stock up in one order.', 'mad-baits'),
	),
	array(
		'question' => __('How much can I save with bundles?', 'mad-baits'),
		'answer'   => __('Savings vary by pack, but each bundle is priced to reward session planning versus buying the same items individually.', 'mad-baits'),
	),
	array(
		'question' => __('Can I build my own bundle from the shop?', 'mad-baits'),
		'answer'   => __('Yes. Use Build Your Bundle to combine your preferred boilies, hookbaits and liquids for the exact session setup you need.', 'mad-baits'),
	),
	array(
		'question' => __('Are bundle products available for quick dispatch?', 'mad-baits'),
		'answer'   => __('Bundle availability tracks live stock, and in-stock deals are prepared for fast dispatch so you can get session-ready quickly.', 'mad-baits'),
	),
);
?>
<main id="primary" class="site-main page-main bundle-page">
	<section class="bundle-page__hero">
		<div class="bundle-page__hero-media<?php echo $hero_image_url ? '' : ' bundle-page__hero-media--fallback'; ?>"<?php if ($hero_image_url) : ?> style="<?php echo esc_attr("--bundle-hero-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?> aria-hidden="true"></div>
		<div class="container bundle-page__hero-inner">
			<div class="bundle-page__hero-copy">
				<p class="bundle-page__kicker"><?php esc_html_e('Premium Session Value', 'mad-baits'); ?></p>
				<h1><?php esc_html_e('Bundle Deals Built For Big Sessions', 'mad-baits'); ?></h1>
				<p><?php esc_html_e('Save more while you stock up on session-ready packs engineered to keep your campaign baiting consistent from first cast to final night.', 'mad-baits'); ?></p>
				<div class="bundle-page__hero-actions">
					<a class="mad-button" href="<?php echo esc_url($bundle_deals_url); ?>"><?php esc_html_e('Shop Bundle Deals', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($build_bundle_url); ?>"><?php esc_html_e('Build Your Bundle', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>

	<section class="section bundle-page__product-section">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="bundle-page__kicker"><?php esc_html_e('Live Bundle Selection', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Current Bundle Deals', 'mad-baits'); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Browse full shop', 'mad-baits'); ?></a>
			</div>
			<?php if (class_exists('WooCommerce') && ! empty($bundle_products_map)) : ?>
				<div class="bundle-page__grid product-grid bundle-page__uniform-product-grid">
					<?php
					$enable_compact_variations = function_exists('mad_baits_enable_compact_card_variations') ? mad_baits_enable_compact_card_variations() : true;
					foreach (array_slice(array_keys($bundle_products_map), 0, 12) as $product_id) :
						if (function_exists('mad_baits_render_product_card')) {
							mad_baits_render_product_card((int) $product_id, (bool) $enable_compact_variations);
						}
					endforeach;
					?>
				</div>
			<?php elseif (! class_exists('WooCommerce')) : ?>
				<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to display bundle products.', 'mad-baits'); ?></p>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No bundle deals found yet. Add product categories or tags using terms like Bundle Deals, Bundles, Deals or Offers.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section section--contrast bundle-page__featured-section">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="bundle-page__kicker"><?php esc_html_e('Featured Bundle', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Campaign Pick Of The Week', 'mad-baits'); ?></h2>
				</div>
			</div>
			<?php if ($featured_bundle instanceof WC_Product) : ?>
				<?php
				$featured_id          = (int) $featured_bundle->get_id();
				$featured_image_html  = get_the_post_thumbnail($featured_id, 'large', array('loading' => 'lazy', 'class' => 'bundle-page__featured-image'));
				$featured_link        = $featured_bundle->get_permalink();
				$featured_description = $featured_bundle->get_short_description();
				if ('' === trim((string) $featured_description)) {
					$featured_description = wp_trim_words(wp_strip_all_tags((string) get_the_excerpt($featured_id)), 28);
				}
				?>
				<article class="bundle-page__featured-card">
					<a class="bundle-page__featured-media" href="<?php echo esc_url($featured_link); ?>">
						<?php if ($featured_image_html) : ?>
							<?php echo $featured_image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							<span class="bundle-page__featured-placeholder" aria-hidden="true"></span>
						<?php endif; ?>
					</a>
					<div class="bundle-page__featured-content">
						<p class="bundle-page__featured-kicker"><?php esc_html_e('Session-ready offer', 'mad-baits'); ?></p>
						<h3><a href="<?php echo esc_url($featured_link); ?>"><?php echo esc_html($featured_bundle->get_name()); ?></a></h3>
						<div class="bundle-page__featured-price"><?php echo wp_kses_post($featured_bundle->get_price_html()); ?></div>
						<div class="bundle-page__featured-description"><?php echo wp_kses_post(wpautop((string) $featured_description)); ?></div>
						<div class="bundle-page__featured-actions">
							<a class="mad-button" href="<?php echo esc_url($featured_link); ?>"><?php esc_html_e('View Bundle', 'mad-baits'); ?></a>
							<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_deals_url); ?>"><?php esc_html_e('View All Deals', 'mad-baits'); ?></a>
						</div>
					</div>
				</article>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('We will feature a top-performing bundle here as soon as qualifying products are available.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section bundle-page__why-section">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="bundle-page__kicker"><?php esc_html_e('Why Buy Bundles', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Built Around Value, Consistency And Results', 'mad-baits'); ?></h2>
				</div>
			</div>
			<div class="bundle-page__info-grid">
				<article class="bundle-page__info-card">
					<h3><?php esc_html_e('Better Session Economics', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Secure campaign-level quantities at better value than ad-hoc single-item purchases.', 'mad-baits'); ?></p>
				</article>
				<article class="bundle-page__info-card">
					<h3><?php esc_html_e('Stock Up In One Hit', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Get boilies, hookbaits and support products aligned for your next run of sessions.', 'mad-baits'); ?></p>
				</article>
				<article class="bundle-page__info-card">
					<h3><?php esc_html_e('Matched Product Logic', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Bundles pair products that work together so your approach stays consistent across changing conditions.', 'mad-baits'); ?></p>
				</article>
				<article class="bundle-page__info-card">
					<h3><?php esc_html_e('Less Guesswork, More Fishing', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Spend less time piecing orders together and more time executing a clean session plan.', 'mad-baits'); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="section section--contrast bundle-page__guidance-section" id="bundle-guidance">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="bundle-page__kicker"><?php esc_html_e('Bundle Guidance', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('How To Choose The Right Deal', 'mad-baits'); ?></h2>
				</div>
			</div>
			<div class="bundle-page__guidance-grid">
				<article class="bundle-page__guidance-card">
					<span>01</span>
					<h3><?php esc_html_e('Match Session Length', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Prioritise larger food-bait packs for longer trips and campaign waters where consistency matters.', 'mad-baits'); ?></p>
				</article>
				<article class="bundle-page__guidance-card">
					<span>02</span>
					<h3><?php esc_html_e('Balance Attraction Layers', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Select bundles that include both food bait and high-attract hookbait options for flexibility.', 'mad-baits'); ?></p>
				</article>
				<article class="bundle-page__guidance-card">
					<span>03</span>
					<h3><?php esc_html_e('Plan For Top-Ups', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Choose deals that leave enough bait for prebaiting, top-ups and a confidence reserve.', 'mad-baits'); ?></p>
				</article>
				<article class="bundle-page__guidance-card">
					<span>04</span>
					<h3><?php esc_html_e('Get Advice When Needed', 'mad-baits'); ?></h3>
					<p><?php esc_html_e('Not sure which deal fits your water? Contact the team for practical setup guidance.', 'mad-baits'); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="section bundle-page__faq-section">
		<div class="container container--narrow">
			<div class="section__heading">
				<div>
					<p class="bundle-page__kicker"><?php esc_html_e('Bundle FAQ', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Common Questions', 'mad-baits'); ?></h2>
				</div>
			</div>
			<div class="bundle-page__faq" data-bundle-faq>
				<?php foreach ($faq_items as $index => $faq_item) : ?>
					<?php $is_open = 0 === (int) $index; ?>
					<article class="bundle-page__faq-item<?php echo $is_open ? ' is-open' : ''; ?>">
						<button class="bundle-page__faq-toggle" type="button" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
							<span><?php echo esc_html((string) $faq_item['question']); ?></span>
						</button>
						<div class="bundle-page__faq-panel"<?php echo $is_open ? '' : ' hidden'; ?>>
							<p><?php echo esc_html((string) $faq_item['answer']); ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section bundle-page__final-cta">
		<div class="container">
			<div class="bundle-page__final-cta-card<?php echo $cta_image_url ? '' : ' bundle-page__final-cta-card--fallback'; ?>"<?php if ($cta_image_url) : ?> style="<?php echo esc_attr("--bundle-cta-image: url('" . esc_url_raw($cta_image_url) . "');"); ?>"<?php endif; ?>>
				<div class="bundle-page__final-cta-content">
					<p class="bundle-page__kicker"><?php esc_html_e('Campaign Ready', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Stock Up Before Your Next Session', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('Lock in your bundle savings now and head to the bank with a complete, confidence-led bait setup.', 'mad-baits'); ?></p>
					<div class="bundle-page__hero-actions">
						<a class="mad-button" href="<?php echo esc_url($bundle_deals_url); ?>"><?php esc_html_e('View All Deals', 'mad-baits'); ?></a>
						<a class="mad-button mad-button--ghost" href="<?php echo esc_url($contact_url); ?>"><?php esc_html_e('Contact For Advice', 'mad-baits'); ?></a>
					</div>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
