<?php
/**
 * Template Name: Boilie Range
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
$boilie_range_url = function_exists('mad_baits_get_boilie_range_url') ? mad_baits_get_boilie_range_url() : $shop_url;
$hookbaits_url = function_exists('mad_baits_get_hookbaits_page_url') ? mad_baits_get_hookbaits_page_url() : $shop_url;
$ai_bait_finder_url = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');
$build_my_session_url = function_exists('mad_baits_get_build_my_session_page_url') ? mad_baits_get_build_my_session_page_url() : home_url('/build-my-session/');

$hero_image_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => (int) get_queried_object_id(),
			'candidate_filenames' => array('compulsive-yellow-popups-bank.png', 'compulsive-pink-popups-hand.png', 'boilie-range-hero.jpg', 'boilie-range.jpg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'   => array('boilies', 'boilie'),
			'semantic_terms'      => array('boilie', 'food bait', 'campaign'),
		)
	)
	: '';

$quick_nav_bg_url = function_exists('mad_baits_resolve_context_hero_image')
	? mad_baits_resolve_context_hero_image(
		array(
			'post_id'             => (int) get_queried_object_id(),
			'candidate_filenames' => array(
				'p-fish-open-boilie-shot.jpg',
				'p-fish-glug-waterline.png',
				'jerry-rigging-bivvy.png',
				'jerry-sunset-cast.png',
				'asbo-boilie-close-1.jpg',
				'IMG_6273.jpeg',
			),
			'product_cat_slugs'   => array('boilies', 'hookbaits', 'pellets', 'liquids', 'bundles-deals'),
			'semantic_terms'      => array('boilie', 'bait prep', 'campaign', 'water', 'session'),
		)
	)
	: '';

$range_map = array(
	'asbo' => array(
		'label'          => __('ASBO', 'mad-baits'),
		'description'    => __('High-impact food bait profile designed for confident campaign feeding and repeat bites.', 'mad-baits'),
		'category_slugs' => array(),
		'category_names' => array('ASBO'),
		'tag_slugs'      => array('asbo'),
		'tag_names'      => array('ASBO'),
	),
	'wicked-white' => array(
		'label'          => __('Wicked White', 'mad-baits'),
		'description'    => __('Clean, standout attractor profile for anglers who want a sharp visual and food-signal edge.', 'mad-baits'),
		'category_slugs' => array(),
		'category_names' => array('Wicked White'),
		'tag_slugs'      => array('wicked-white'),
		'tag_names'      => array('Wicked White'),
	),
	'nutz-plus' => array(
		'label'          => __('Nutz Plus', 'mad-baits'),
		'description'    => __('Proven nut-led nutrition profile built for sustained feeding confidence and consistency.', 'mad-baits'),
		'category_slugs' => array(),
		'category_names' => array('Nutz Plus'),
		'tag_slugs'      => array('nutz-plus'),
		'tag_names'      => array('Nutz Plus'),
	),
	'nutz-banana' => array(
		'label'          => __('Nutz Banana', 'mad-baits'),
		'description'    => __('Balanced sweet-nut blend for anglers wanting food-value confidence with instant attraction.', 'mad-baits'),
		'category_slugs' => array(),
		'category_names' => array('Nutz Banana'),
		'tag_slugs'      => array('nutz-banana'),
		'tag_names'      => array('Nutz Banana'),
	),
	'compulsive-angler' => array(
		'label'          => __('Compulsive Angler', 'mad-baits'),
		'description'    => __('Compulsive Angler editions with the same range treatment as the rest of the Mad Baits boilie line.', 'mad-baits'),
		'category_slugs' => array('compulsive-anglers', 'compulsive'),
		'category_names' => array('Compulsive Angler'),
		'tag_slugs'      => array('compulsive-angler'),
		'tag_names'      => array('Compulsive Angler'),
	),
	'p-fish' => array(
		'label'          => __('P-Fish', 'mad-baits'),
		'description'    => __('Fishmeal-forward profile made for strong pull and repeat confidence on pressured venues.', 'mad-baits'),
		'category_slugs' => array(),
		'category_names' => array('P-Fish'),
		'tag_slugs'      => array('p-fish-2'),
		'tag_names'      => array('P-Fish'),
	),
	'pandemic' => array(
		'label'          => __('Pandemic', 'mad-baits'),
		'description'    => __('A focused, high-confidence boilie option built for anglers targeting serious campaign results.', 'mad-baits'),
		'category_slugs' => array(),
		'category_names' => array('Pandemic'),
		'tag_slugs'      => array('pandemic'),
		'tag_names'      => array('Pandemic'),
	),
	'stp' => array(
		'label'          => __('STP', 'mad-baits'),
		'description'    => __('Shelf life boilies in 15mm and 18mm, packed in 1kg bags.', 'mad-baits'),
		'category_slugs' => array('boilies-stp'),
		'category_names' => array('STP'),
		'tag_slugs'      => array('stp'),
		'tag_names'      => array('STP'),
	),
);

if (function_exists('mad_baits_range_is_storefront_visible') && mad_baits_range_is_storefront_visible('swan-mussel')) {
	$range_map['swan-mussel'] = array(
		'label'          => __('Swan Mussel', 'mad-baits'),
		'description'    => __('Swan Mussel shelf life boilies in 15mm and 18mm, packed in 1kg bags.', 'mad-baits'),
		'category_slugs' => array('boilies-swan-mussel'),
		'category_names' => array('Swan Mussel'),
		'tag_slugs'      => array('swan-mussel'),
		'tag_names'      => array('Swan Mussel'),
	);
}

$is_admin_debug = current_user_can('manage_options') || current_user_can('manage_woocommerce');

$resolve_term_ids = static function ($taxonomy, $slugs, $names) {
	if (! taxonomy_exists((string) $taxonomy)) {
		return array();
	}

	$term_ids = array();

	foreach ((array) $slugs as $slug) {
		$slug = sanitize_title((string) $slug);
		if ('' === $slug) {
			continue;
		}

		$term = get_term_by('slug', $slug, (string) $taxonomy);
		if ($term instanceof WP_Term) {
			$term_ids[] = (int) $term->term_id;
		}
	}

	foreach ((array) $names as $name) {
		$name = trim((string) $name);
		if ('' === $name) {
			continue;
		}

		$term = get_term_by('name', $name, (string) $taxonomy);
		if ($term instanceof WP_Term) {
			$term_ids[] = (int) $term->term_id;
			continue;
		}

		$name_slug = sanitize_title($name);
		$terms     = get_terms(
			array(
				'taxonomy'   => (string) $taxonomy,
				'hide_empty' => false,
			)
		);
		if (is_wp_error($terms) || ! is_array($terms)) {
			continue;
		}

		foreach ($terms as $candidate) {
			if (! $candidate instanceof WP_Term) {
				continue;
			}

			if ($name_slug === sanitize_title((string) $candidate->name)) {
				$term_ids[] = (int) $candidate->term_id;
			}
		}
	}

	return array_values(array_unique(array_map('absint', $term_ids)));
};

$resolve_primary_term_link = static function ($taxonomy, $term_ids, $fallback_url) {
	foreach ((array) $term_ids as $term_id) {
		$term = get_term((int) $term_id, (string) $taxonomy);
		if (! $term instanceof WP_Term) {
			continue;
		}

		$link = get_term_link($term);
		if (! is_wp_error($link) && is_string($link) && '' !== $link) {
			return $link;
		}
	}

	return $fallback_url;
};

$query_range_products = static function ($category_ids, $tag_ids) {
	if (! class_exists('WooCommerce')) {
		return array();
	}

	$tax_query = array('relation' => 'OR');

	if (! empty($category_ids) && taxonomy_exists('product_cat')) {
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => array_map('absint', (array) $category_ids),
		);
	}

	if (! empty($tag_ids) && taxonomy_exists('product_tag')) {
		$tax_query[] = array(
			'taxonomy' => 'product_tag',
			'field'    => 'term_id',
			'terms'    => array_map('absint', (array) $tag_ids),
		);
	}

	if (count($tax_query) < 2) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'tax_query'      => $tax_query,
			'fields'         => 'ids',
		)
	);

	return array_map('absint', (array) $query->posts);
};

$range_sections = array();

foreach ($range_map as $anchor_key => $range) {
	$category_ids = $resolve_term_ids(
		'product_cat',
		isset($range['category_slugs']) ? (array) $range['category_slugs'] : array(),
		isset($range['category_names']) ? (array) $range['category_names'] : array()
	);
	$tag_ids = $resolve_term_ids(
		'product_tag',
		isset($range['tag_slugs']) ? (array) $range['tag_slugs'] : array(),
		isset($range['tag_names']) ? (array) $range['tag_names'] : array()
	);

	$product_ids = $query_range_products($category_ids, $tag_ids);
	if (function_exists('mad_baits_filter_range_hub_product_ids')) {
		$product_ids = mad_baits_filter_range_hub_product_ids($product_ids);
	}
	$has_products = ! empty($product_ids);

	$section_url = $resolve_primary_term_link('product_cat', $category_ids, $boilie_range_url);
	if ($boilie_range_url === $section_url) {
		$section_url = $resolve_primary_term_link('product_tag', $tag_ids, $boilie_range_url);
	}

	$range_bg_url = function_exists('mad_baits_resolve_context_hero_image')
		? mad_baits_resolve_context_hero_image(
			array(
				'slug'               => 'boilie-range-' . sanitize_title((string) $anchor_key),
				'title'              => isset($range['label']) ? (string) $range['label'] : '',
				'intro'              => isset($range['description']) ? (string) $range['description'] : '',
				'candidate_filenames' => array(
					sanitize_title((string) $anchor_key) . '-hero-scene.jpg',
					sanitize_title((string) $anchor_key) . '-hero.jpg',
					'IMG_6273.jpeg',
					'IMG_6276.jpeg',
					'jerry-sunset-cast.png',
					'p-fish-open-boilie-shot.jpg',
				),
				'product_cat_slugs'  => isset($range['category_slugs']) ? (array) $range['category_slugs'] : array('boilies', 'boilie'),
				'semantic_terms'     => array('boilie', 'campaign', 'session', sanitize_title((string) $anchor_key)),
			)
		)
		: '';

	$range_sections[] = array(
		'anchor'       => sanitize_title((string) $anchor_key),
		'label'        => isset($range['label']) ? (string) $range['label'] : '',
		'description'  => isset($range['description']) ? (string) $range['description'] : '',
		'category_slugs' => isset($range['category_slugs']) ? (array) $range['category_slugs'] : array(),
		'tag_slugs'      => isset($range['tag_slugs']) ? (array) $range['tag_slugs'] : array(),
		'category_ids' => $category_ids,
		'tag_ids'      => $tag_ids,
		'product_ids'  => $product_ids,
		'product_count' => count((array) $product_ids),
		'section_url'  => $section_url,
		'has_products' => $has_products,
		'background_url' => is_string($range_bg_url) ? $range_bg_url : '',
	);
}
?>

<main id="primary" class="site-main page-main mb-premium-page mb-premium-page--boilie-range">
	<section class="mb-premium-hero<?php echo $hero_image_url ? '' : ' mb-premium-hero--fallback'; ?>"<?php if ($hero_image_url) : ?> style="<?php echo esc_attr("--mb-premium-hero-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?>>
		<div class="container mb-premium-hero__inner">
			<p class="mb-premium-hero__kicker"><?php esc_html_e('Food Bait Systems', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('Boilie Range', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('From fresh freezer options to session-ready shelf-life, this range is engineered for campaign confidence and repeat captures on pressured waters.', 'mad-baits'); ?></p>
			<div class="mb-premium-hero__actions">
				<a class="mad-button" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Shop Boilie Range', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($hookbaits_url); ?>"><?php esc_html_e('Pair With Hookbaits', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>

	<section class="section mb-boilie-range-nav"<?php if (is_string($quick_nav_bg_url) && '' !== $quick_nav_bg_url) : ?> style="<?php echo esc_attr("--mb-boilie-nav-image: url('" . esc_url_raw($quick_nav_bg_url) . "');"); ?>"<?php endif; ?>>
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Quick Navigation', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Jump To A Boilie Range', 'mad-baits'); ?></h2>
				</div>
				<a class="text-link" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Browse Boilies', 'mad-baits'); ?></a>
			</div>
			<?php if (! empty($range_sections)) : ?>
				<div class="mb-boilie-range-nav__grid">
					<?php foreach ($range_sections as $range_section) : ?>
						<a class="mb-boilie-range-nav__card" href="#range-<?php echo esc_attr((string) $range_section['anchor']); ?>">
							<span class="mb-boilie-range-nav__label"><?php echo esc_html((string) $range_section['label']); ?></span>
							<span class="mb-boilie-range-nav__count"><?php esc_html_e('Featured range overview', 'mad-baits'); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No boilie ranges available yet. Add products to range categories or tags to populate this page.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<?php foreach ($range_sections as $section_index => $range_section) : ?>
		<section id="range-<?php echo esc_attr((string) $range_section['anchor']); ?>" class="section section--contrast mb-boilie-range-section"<?php if (! empty($range_section['background_url'])) : ?> style="<?php echo esc_attr("--mb-range-section-image: url('" . esc_url_raw((string) $range_section['background_url']) . "');"); ?>"<?php endif; ?>>
			<div class="container">
				<div class="section__heading section__heading--with-actions mb-boilie-range-section__intro">
					<div>
						<p class="section__kicker"><?php esc_html_e('Boilie Range', 'mad-baits'); ?></p>
						<h2><?php echo esc_html((string) $range_section['label']); ?></h2>
						<p><?php echo esc_html((string) $range_section['description']); ?></p>
					</div>
					<div class="mb-boilie-range-section__meta">
						<span class="mb-boilie-range-section__count"><?php esc_html_e('4 featured products', 'mad-baits'); ?></span>
						<a class="mad-button mad-button--small" href="<?php echo esc_url((string) $range_section['section_url']); ?>">
							<?php
							printf(
								/* translators: %s: boilie range name. */
								esc_html__('View All %s', 'mad-baits'),
								esc_html((string) $range_section['label'])
							);
							?>
						</a>
					</div>
				</div>

				<?php if ('stp' === (string) $range_section['anchor'] && function_exists('mad_baits_render_range_bulk_deals')) : ?>
					<?php mad_baits_render_range_bulk_deals('stp', 'boilie-range'); ?>
				<?php endif; ?>

				<?php if (! empty($range_section['product_ids'])) : ?>
					<div class="mb-boilie-range__grid">
						<?php foreach ((array) $range_section['product_ids'] as $product_id) : ?>
							<?php if (function_exists('mad_baits_render_product_card')) : ?>
								<?php mad_baits_render_product_card((int) $product_id, true); ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php elseif ($is_admin_debug) : ?>
					<div class="mb-boilie-range-section__admin-note">
						<p>
							<strong><?php esc_html_e('Admin note:', 'mad-baits'); ?></strong>
							<?php esc_html_e('No products found for this range via product categories or tags.', 'mad-baits'); ?>
						</p>
						<p>
							<?php
							printf(
								/* translators: 1: category slugs, 2: tag slugs */
								esc_html__('Checked category slugs: %1$s | tag slugs: %2$s', 'mad-baits'),
								esc_html(implode(', ', (array) $range_section['category_slugs'])),
								esc_html(implode(', ', (array) $range_section['tag_slugs']))
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<p class="section__empty-state"><?php esc_html_e('Products for this range will appear once matching category/tag assignments are available.', 'mad-baits'); ?></p>
				<?php endif; ?>
			</div>
		</section>
		<?php if ($section_index < (count($range_sections) - 1)) : ?>
			<section class="section mb-boilie-range-divider" aria-hidden="true">
				<div class="container">
					<span class="mb-boilie-range-divider__line"></span>
				</div>
			</section>
		<?php endif; ?>
	<?php endforeach; ?>

	<section class="section section--contrast mb-boilie-range-final-cta">
		<div class="container container--narrow">
			<div class="mb-boilie-range-final-cta__inner">
				<p class="section__kicker"><?php esc_html_e('Need Guidance?', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Not Sure Which Range To Choose?', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Get a tailored recommendation based on your lake, session style and confidence goals.', 'mad-baits'); ?></p>
				<div class="mb-boilie-range-final-cta__actions">
					<a class="mad-button" href="<?php echo esc_url($ai_bait_finder_url); ?>"><?php esc_html_e('Try AI Bait Finder', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($build_my_session_url); ?>"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
