<?php
/**
 * Front page template.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

get_header();
?>

<?php
$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$contact_url = home_url('/contact/');
$catch_reports_url = function_exists('mad_baits_get_catch_reports_url') ? mad_baits_get_catch_reports_url() : home_url('/catch-reports/');
$mad_baits_tv_url = function_exists('mad_baits_get_mad_baits_tv_url') ? mad_baits_get_mad_baits_tv_url() : home_url('/mad-baits-tv/');
$about_page       = get_page_by_path('about');
$about_page_url   = $about_page instanceof WP_Post ? get_permalink($about_page) : home_url('/about/');
$about_page_url   = is_string($about_page_url) && '' !== $about_page_url ? $about_page_url : home_url('/about/');
$bundle_deals_url = function_exists('mad_baits_get_bundle_deals_url')
	? mad_baits_get_bundle_deals_url()
	: (function_exists('mad_baits_get_product_cat_link')
		? mad_baits_get_product_cat_link(array('bundles-deals', 'bundle-deals', 'bundles', 'deals'), $shop_url)
		: $shop_url);
$boilie_range_url = function_exists('mad_baits_get_boilie_range_url')
	? mad_baits_get_boilie_range_url()
	: (function_exists('mad_baits_get_product_cat_link')
		? mad_baits_get_product_cat_link(array('boilies', 'boilie', 'boilies-range'), $shop_url)
		: $shop_url);
$build_my_session_url = function_exists('mad_baits_get_build_my_session_page_url')
	? mad_baits_get_build_my_session_page_url()
	: (function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('build-my-session', 'build-my-session') : home_url('/build-my-session/'));

$resolve_theme_image = static function ($candidates) {
	foreach ((array) $candidates as $filename) {
		$filename = trim((string) $filename);
		if ('' === $filename) {
			continue;
		}

		$relative_path = '/assets/img/' . ltrim($filename, '/');
		$absolute_path = get_theme_file_path($relative_path);

		if ($absolute_path && file_exists($absolute_path)) {
			return get_theme_file_uri($relative_path);
		}
	}

	return '';
};

$verified_theme_candidates = function_exists('mad_baits_get_existing_theme_image_candidates')
	? mad_baits_get_existing_theme_image_candidates('hero')
	: array('CUP-LOGOv2-1.svg');

$hero_image_url = '';
$main_hero_candidates = array(
	'652683570_1340175298154264_5204164835026563315_n.jpg',
	'jerry-rigging-bivvy.png',
	'jerry-wading-rod-setup.png',
);
$preferred_hero_candidates = array_values(array_unique(array_merge(
	$main_hero_candidates,
	function_exists('mad_baits_get_moody_background_candidates') ? mad_baits_get_moody_background_candidates('hero') : array(),
	$verified_theme_candidates
)));
$preferred_hero = $resolve_theme_image($preferred_hero_candidates);

if (is_string($preferred_hero) && '' !== $preferred_hero) {
	$hero_image_url = $preferred_hero;
}

if (! $hero_image_url) {
	$hero_image_url = $resolve_theme_image($preferred_hero_candidates);
}
$campaign_candidates = array_values(array_unique(array_merge(
	function_exists('mad_baits_get_moody_background_candidates') ? mad_baits_get_moody_background_candidates('campaign') : array(),
	function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : array('CUP-LOGOv2-1.svg')
)));

$why_section_candidates = array(
	'jerry-rigging-bivvy.png',
	'HAMMONDAPRIL_22_031.jpg',
	'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
	'IMG_6209.jpeg',
	'CUP-LOGOv2-1.svg',
);
$why_section_image_url = $resolve_theme_image(array_values(array_unique(array_merge($why_section_candidates, $campaign_candidates))));
$why_campaign_candidates = array(
	'Jerry_Hammond_March_22_Englefield_Lagoon_016.png',
	'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
	'02_02_22_Jerry_Hammond_058-e1676481405465.jpg',
	'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
	'p-fish-glug-waterline.png',
	'IMG_6270-1.jpeg',
	'IMG_6276.jpeg',
	'CUP-LOGOv2-1.svg',
);
$brand_story_candidates = array(
	'compulsive-pink-popups-hand.png',
	'IMG_0820-scaled.jpeg',
	'IMG_6277.jpeg',
	'Photoroom_20240306_123557.jpeg',
	'CUP-LOGOv2-1.svg',
);
$bundle_section_candidates = array(
	'jerry-rigging-bivvy.png',
	'session-pack.png',
	'IMG_6276.jpeg',
	'IMG_6271.jpeg',
	'CUP-LOGOv2-1.svg',
);
$testimonials_candidates = array(
	'jerry-sunset-cast.png',
	'jerry-wading-rod-setup.png',
	'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
	'IMG_6270-1.jpeg',
	'IMG_6273.jpeg',
	'CUP-LOGOv2-1.svg',
);
$catch_reports_candidates = array(
	'jerry-wading-rod-setup.png',
	'jerry-sunset-cast.png',
	'Jerry_Hammond_March_22_Englefield_Lagoon_014.jpg',
	'02_02_22_Jerry_Hammond_058-e1676481405465.jpg',
	'IMG_6277.jpeg',
	'CUP-LOGOv2-1.svg',
);
$bank_candidates = array(
	'p-fish-glug-waterline.png',
	'IMG_6273.jpeg',
	'IMG_6276.jpeg',
	'IMG_6270-1.jpeg',
	'CUP-LOGOv2-1.svg',
);
$session_cta_candidates = array(
	'compulsive-yellow-popups-bank.png',
	'session-pack.png',
	'IMG_6271.jpeg',
	'IMG_6209.jpeg',
	'CUP-LOGOv2-1.svg',
);
$mad_baits_tv_candidates = array(
	'IMG_2905.jpeg',
	'IMG_6270-1.jpeg',
	'IMG_6277.jpeg',
	'CUP-LOGOv2-1.svg',
);
(array_values(array_unique(array_merge($why_section_candidates, $campaign_candidates))));
$why_campaign_bg_url    = '';
$why_campaign_candidates_merged = array_values(array_unique(array_merge($why_campaign_candidates, $campaign_candidates)));
if (function_exists('mad_baits_resolve_context_hero_image')) {
	$why_campaign_bg_url = mad_baits_resolve_context_hero_image(
		array(
			'slug'               => 'home-why-campaign',
			'title'              => __('Designed For Serious Campaign Anglers', 'mad-baits'),
			'intro'              => __('Cinematic campaign atmosphere with real on-the-bank context.', 'mad-baits'),
			'candidates'         => $why_campaign_candidates_merged,
			'term_taxonomy'      => 'product_cat',
			'product_cat_slugs'  => array('boilies', 'hookbaits', 'bundle-deals', 'compulsive-anglers'),
			'semantic_terms'     => array('campaign', 'angler', 'session', 'water', 'bank', 'bait prep'),
			'allow_latest_product' => true,
		)
	);
}
if (! is_string($why_campaign_bg_url) || '' === $why_campaign_bg_url) {
	$why_campaign_bg_url = $resolve_theme_image($why_campaign_candidates_merged);
}
$bundle_section_bg_url  = $resolve_theme_image(array_values(array_unique(array_merge($bundle_section_candidates, $campaign_candidates))));
$testimonials_image_url = $resolve_theme_image(array_values(array_unique(array_merge($testimonials_candidates, $campaign_candidates))));
$session_cta_bg_url     = '';
$session_cta_bg_url = $resolve_theme_image(array_values(array_unique(array_merge($session_cta_candidates, $campaign_candidates))));

$bank_bg_url = $resolve_theme_image(array_values(array_unique(array_merge($bank_candidates, $campaign_candidates))));
$category_image_map     = array(
	'asbo'               => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('hero') : $verified_theme_candidates,
	'wicked-white'       => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'wicked-whites'      => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'nutz-plus'          => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('hero') : $verified_theme_candidates,
	'nutz-banana'        => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'stp'                => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'p-fish-2'           => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'p-fish'             => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'pandemic'           => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('hero') : $verified_theme_candidates,
	'bundle-deals'       => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'bundles-deals'      => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'compulsive-angler'  => array('compulsive-orange-popups-night.png', 'compulsive-pink-popups-hand.png', 'compulsive-yellow-popups-bank.png', 'compulsive-triple-hookbaits.png'),
	'compulsive-anglers' => array('compulsive-orange-popups-night.png', 'compulsive-pink-popups-hand.png', 'compulsive-yellow-popups-bank.png', 'compulsive-triple-hookbaits.png'),
	'calamino'           => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'boilies'            => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('hero') : $verified_theme_candidates,
	'hookbaits'          => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'bundles'            => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'deals'              => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'terminal'           => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'terminal-tackle'    => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'clothing'           => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'liquids'            => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
	'pellets'            => function_exists('mad_baits_get_existing_theme_image_candidates') ? mad_baits_get_existing_theme_image_candidates('campaign') : $verified_theme_candidates,
);

if (class_exists('WooCommerce') && function_exists('wc_get_products')) {
	$hero_product_query = array(
		'status' => 'publish',
		'limit'  => 4,
		'return' => 'ids',
	);
	if (function_exists('mad_baits_filter_homepage_wc_product_query_args')) {
		$hero_product_query = mad_baits_filter_homepage_wc_product_query_args($hero_product_query);
	}
	$hero_product_ids = wc_get_products($hero_product_query);

	if (! empty($hero_product_ids)) {
		$fallback_hero = get_the_post_thumbnail_url((int) $hero_product_ids[0], 'large');
		$fallback_why  = isset($hero_product_ids[1]) ? get_the_post_thumbnail_url((int) $hero_product_ids[1], 'large') : '';
		$fallback_pack = isset($hero_product_ids[2]) ? get_the_post_thumbnail_url((int) $hero_product_ids[2], 'large') : '';
		$fallback_test = isset($hero_product_ids[3]) ? get_the_post_thumbnail_url((int) $hero_product_ids[3], 'large') : '';

		if (! $hero_image_url && is_string($fallback_hero) && '' !== $fallback_hero) {
			$hero_image_url = $fallback_hero;
		}
		if (! $why_section_image_url && is_string($fallback_why) && '' !== $fallback_why) {
			$why_section_image_url = $fallback_why;
		}
		if (! $bundle_section_bg_url && is_string($fallback_pack) && '' !== $fallback_pack) {
			$bundle_section_bg_url = $fallback_pack;
		}
		if (! $testimonials_image_url && is_string($fallback_test) && '' !== $fallback_test) {
			$testimonials_image_url = $fallback_test;
		}
	}
}

$campaign_section_bg_url = $resolve_theme_image($campaign_candidates);

if (! $campaign_section_bg_url) {
	$campaign_section_bg_url = $why_section_image_url;
}

if (! $why_campaign_bg_url) {
	$why_campaign_bg_url = $campaign_section_bg_url;
}

$campaign_anglers_fixed_bg_url  = '';
$campaign_anglers_fixed_bg_file = '/assets/img/Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg';
if (file_exists(get_theme_file_path($campaign_anglers_fixed_bg_file))) {
	$campaign_anglers_fixed_bg_url = get_theme_file_uri($campaign_anglers_fixed_bg_file);
} elseif (file_exists(get_theme_file_path('/assets/img/Jerry_Hammond_March_22_Englefield_Lagoon_016.png'))) {
	$campaign_anglers_fixed_bg_url = get_theme_file_uri('/assets/img/Jerry_Hammond_March_22_Englefield_Lagoon_016.png');
}

$why_section_style = '';
if ($why_section_image_url || $campaign_section_bg_url || $campaign_anglers_fixed_bg_url) {
	$why_section_vars = array();

	if ($why_section_image_url) {
		$why_section_vars[] = "--section-bg-image: url('" . esc_url_raw($why_section_image_url) . "')";
	}

	if ($campaign_section_bg_url) {
		$why_section_vars[] = "--campaign-bg-image: url('" . esc_url_raw($campaign_section_bg_url) . "')";
	}

	if ($why_campaign_bg_url) {
		$why_section_vars[] = "--why-campaign-image: url('" . esc_url_raw($why_campaign_bg_url) . "')";
	}

	if ($campaign_anglers_fixed_bg_url) {
		$why_section_vars[] = "--campaign-anglers-fixed-bg: url('" . esc_url_raw($campaign_anglers_fixed_bg_url) . "')";
	}

	if (! empty($why_section_vars)) {
		$why_section_style = ' style="' . esc_attr(implode('; ', $why_section_vars) . ';') . '"';
	}
}

$bundle_promo_style = '';
if ($bundle_section_bg_url) {
	$bundle_promo_style = ' style="' . esc_attr("--bundle-bg-image: url('" . esc_url_raw($bundle_section_bg_url) . "');") . '"';
}

$testimonials_style = '';
if ($testimonials_image_url) {
	$testimonials_style = ' style="' . esc_attr("--section-bg-image: url('" . esc_url_raw($testimonials_image_url) . "');") . '"';
}

$brand_story_image_url = $resolve_theme_image(array_values(array_unique(array_merge($brand_story_candidates, $campaign_candidates))));
$catch_reports_bg_url  = $resolve_theme_image(array_values(array_unique(array_merge($catch_reports_candidates, $campaign_candidates))));
$mad_baits_tv_bg_url   = $resolve_theme_image(array_values(array_unique(array_merge($mad_baits_tv_candidates, $campaign_candidates))));
$editorial_split_bg_url = $resolve_theme_image(
	array_values(
		array_unique(
			array_merge(
				array(
					'jerry-rigging-bivvy.png',
					'Jerry_Hammond_March_22_Englefield_Lagoon_016.jpg',
					'IMG_6276.jpeg',
					'IMG_6273.jpeg',
				),
				$campaign_candidates
			)
		)
	)
);
$new_this_week_bg_url = $resolve_theme_image(
	array_values(
		array_unique(
			array_merge(
				array(
					'compulsive-orange-popups-night.png',
					'session-pack.png',
					'IMG_6271.jpeg',
				),
				$campaign_candidates
			)
		)
	)
);

$brand_story_style = '';
if ($brand_story_image_url) {
	$brand_story_style = ' style="' . esc_attr("--brand-story-image: url('" . esc_url_raw($brand_story_image_url) . "');") . '"';
}

$session_cta_style = '';
if ($session_cta_bg_url) {
	$session_cta_style = ' style="' . esc_attr("--campaign-cta-image: url('" . esc_url_raw($session_cta_bg_url) . "');") . '"';
}

$bank_section_style = '';
if ($bank_bg_url) {
	$bank_section_style = ' style="' . esc_attr("--bank-section-image: url('" . esc_url_raw($bank_bg_url) . "');") . '"';
}

$editorial_split_style = '';
if ($editorial_split_bg_url) {
	$editorial_split_style = ' style="' . esc_attr("--mb-editorial-image: url('" . esc_url_raw($editorial_split_bg_url) . "');") . '"';
}

$new_this_week_style = '';
if ($new_this_week_bg_url) {
	$new_this_week_style = ' style="' . esc_attr("--new-week-image: url('" . esc_url_raw($new_this_week_bg_url) . "');") . '"';
}

$catch_reports_style = '';
if ($catch_reports_bg_url) {
	$catch_reports_style = ' style="' . esc_attr("--catch-scene-image: url('" . esc_url_raw($catch_reports_bg_url) . "');") . '"';
}

$mad_baits_tv_style = '';
if ($mad_baits_tv_bg_url) {
	$mad_baits_tv_style = ' style="' . esc_attr("--mb-tv-scene-image: url('" . esc_url_raw($mad_baits_tv_bg_url) . "');") . '"';
}

$recent_catch_report_ids = function_exists('mad_baits_get_recent_catch_report_ids')
	? mad_baits_get_recent_catch_report_ids(6)
	: array();

$mad_home_product_exclude_tax = function_exists('mad_baits_get_homepage_product_exclude_tax_query')
	? mad_baits_get_homepage_product_exclude_tax_query()
	: array();

$mad_merge_home_tax_query = static function ($tax_query) use ($mad_home_product_exclude_tax) {
	if (empty($mad_home_product_exclude_tax)) {
		return $tax_query;
	}

	if (empty($tax_query)) {
		return $mad_home_product_exclude_tax;
	}

	return array(
		'relation' => 'AND',
		$tax_query,
		$mad_home_product_exclude_tax[0],
	);
};
?>

<section class="hero hero--immersive">
	<div class="hero__cinema-layers" aria-hidden="true">
		<span class="hero__grain"></span>
		<span class="hero__light hero__light--one"></span>
		<span class="hero__light hero__light--two"></span>
		<span class="hero__beam"></span>
	</div>
	<div class="hero__media<?php echo $hero_image_url ? '' : ' hero__media--fallback'; ?>" <?php if ($hero_image_url) : ?>style="<?php echo esc_attr("--hero-bg-image: url('" . esc_url_raw($hero_image_url) . "');"); ?>"<?php endif; ?> aria-hidden="true"></div>
	<div class="container hero__inner">
		<div class="hero__content">
			<p class="hero__eyebrow"><?php esc_html_e('High-Performance Carp Bait', 'mad-baits'); ?></p>
			<h1><?php esc_html_e('Premium Carp Bait Built for Serious Sessions', 'mad-baits'); ?></h1>
			<p><?php esc_html_e('Mad Baits combines proven nutrition, aggressive attraction and campaign-level consistency to keep you catching on hard waters.', 'mad-baits'); ?></p>
			<p class="hero__trust-line"><?php esc_html_e('Trusted by campaign anglers across the UK & Europe.', 'mad-baits'); ?></p>
			<ul class="hero__meta" aria-label="<?php esc_attr_e('Mad Baits performance points', 'mad-baits'); ?>">
				<li><?php esc_html_e('Campaign Tested', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Fast UK Dispatch', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Premium Food Signal', 'mad-baits'); ?></li>
			</ul>
			<div class="hero__actions">
				<a class="mad-button" href="<?php echo esc_url($boilie_range_url); ?>">
					<?php esc_html_e('Shop Boilie Range', 'mad-baits'); ?>
				</a>
				<a class="mad-button mad-button--ghost" href="#why-mad-baits">
					<?php esc_html_e('Why Mad Baits', 'mad-baits'); ?>
				</a>
			</div>
		</div>
		<aside class="hero__depth-card" aria-label="<?php esc_attr_e('Mad Baits campaign highlight', 'mad-baits'); ?>">
			<span class="hero__depth-card-kicker"><?php esc_html_e('Campaign Focus', 'mad-baits'); ?></span>
			<h2><?php esc_html_e('Designed To Hold Fish In Your Area Longer.', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Nutritional consistency, high leakage and confidence-led match-ups built for serious waters.', 'mad-baits'); ?></p>
		</aside>
	</div>
</section>

<?php
if (function_exists('mad_baits_render_news_ticker')) {
	mad_baits_render_news_ticker();
}

if (function_exists('mad_baits_render_mobile_home_urgency')) {
	mad_baits_render_mobile_home_urgency();
}

if (function_exists('mad_baits_render_home_app_promo')) {
	mad_baits_render_home_app_promo();
}
?>

<section class="section section--tight bundle-sales">
	<div class="container">
		<div class="section__heading section__heading--with-actions">
			<div>
				<p class="section__kicker"><?php esc_html_e('Featured', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Top Bundles & Session Picks', 'mad-baits'); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url($bundle_deals_url); ?>"><?php esc_html_e('View all bundles', 'mad-baits'); ?></a>
		</div>
		<?php if (class_exists('WooCommerce')) : ?>
			<?php
			$bundle_ids      = array();
			$bundle_tax_args = array();

			if (taxonomy_exists('product_cat')) {
				$bundle_tax_args[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array('bundles-deals', 'bundle-deals', 'bundles', 'deals', 'offers'),
				);
			}

			$bundle_query = new WP_Query(array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 6,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => $mad_merge_home_tax_query($bundle_tax_args),
			));

			if ($bundle_query->have_posts()) {
				while ($bundle_query->have_posts()) {
					$bundle_query->the_post();
					$bundle_ids[] = (int) get_the_ID();
				}
				wp_reset_postdata();
			}

			$bundle_ids = array_values(array_unique(array_map('absint', $bundle_ids)));
			// Track IDs shown on the homepage so later grids do not repeat the same products.
			$home_featured_ids = $bundle_ids;
			?>
			<?php if (! empty($bundle_ids)) : ?>
				<div class="bundle-sales__uniform-grid product-grid">
					<?php foreach (array_slice($bundle_ids, 0, 6) as $bundle_id) : ?>
						<?php mad_baits_render_product_card($bundle_id, true); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No Bundles & Deals products found yet. Assign products to the Bundles & Deals category to feature them here.', 'mad-baits'); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to display featured products.', 'mad-baits'); ?></p>
		<?php endif; ?>
	</div>
</section>

<section id="why-mad-baits" class="section section--contrast section--immersive-bg mad-campaign-anglers-section"<?php echo $why_section_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="section__heading">
			<div>
				<p class="section__kicker"><?php esc_html_e('Why Mad Baits', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Designed For Serious Campaign Anglers', 'mad-baits'); ?></h2>
				<p class="section__lede"><?php esc_html_e('Built around long-session confidence, repeat-feeding response and on-the-bank practicality for anglers who fish hard through changing conditions.', 'mad-baits'); ?></p>
			</div>
		</div>
		<div class="why-grid">
			<article class="why-card">
				<span class="why-card__icon" aria-hidden="true">01</span>
				<h3><?php esc_html_e('Food Signal Depth', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Nutritional architecture that keeps fish returning to your area over short trips and long campaigns.', 'mad-baits'); ?></p>
			</article>
			<article class="why-card">
				<span class="why-card__icon" aria-hidden="true">02</span>
				<h3><?php esc_html_e('Lab-Led Consistency', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Ingredient and process control that protects leakage, texture and confidence from batch to batch.', 'mad-baits'); ?></p>
			</article>
			<article class="why-card">
				<span class="why-card__icon" aria-hidden="true">03</span>
				<h3><?php esc_html_e('Built On The Bank', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Products are validated on pressured waters so you can fish with intent in any season.', 'mad-baits'); ?></p>
			</article>
		</div>
	</div>
</section>

<section class="section section--contrast home-brand-story"<?php echo $brand_story_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="home-brand-story__layout">
			<div class="home-brand-story__content">
				<p class="section__kicker"><?php esc_html_e('Brand Story', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Built On Quality, Not Cheap Fillers', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Mad Baits is built on cutting-edge bait technology with an old-school respect for proven ingredients. Every bait is made with high-quality ingredients, not cheap bulking agents, and rolled to order so it reaches anglers as fresh as possible.', 'mad-baits'); ?></p>
				<p><?php esc_html_e('Our range is deliberately focused — products that have proven themselves for customers, testers and consultants both at home and abroad.', 'mad-baits'); ?></p>
				<div class="home-brand-story__proof-grid">
					<article class="home-brand-story__proof-card">
						<h3><?php esc_html_e('Rolled To Order', 'mad-baits'); ?></h3>
					</article>
					<article class="home-brand-story__proof-card">
						<h3><?php esc_html_e('Quality Ingredients', 'mad-baits'); ?></h3>
					</article>
					<article class="home-brand-story__proof-card">
						<h3><?php esc_html_e('Proven Track Record', 'mad-baits'); ?></h3>
					</article>
				</div>
				<a class="mad-button" href="<?php echo esc_url($about_page_url); ?>">
					<?php esc_html_e('Read Our Story', 'mad-baits'); ?>
				</a>
			</div>
			<div class="home-brand-story__media" aria-hidden="true">
				<span class="home-brand-story__media-mark"></span>
			</div>
		</div>
	</div>
</section>

<section class="section home-choose-your-edge">
	<div class="container">
		<div class="section__heading section__heading--with-actions">
			<div>
				<p class="section__kicker"><?php esc_html_e('Shop by Bait Range', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Choose Your Edge', 'mad-baits'); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Browse all bait ranges', 'mad-baits'); ?></a>
		</div>
		<?php
		$edge_items = function_exists('mad_baits_get_home_choose_your_edge_items')
			? mad_baits_get_home_choose_your_edge_items()
			: array();
		$edge_product_image_cache = array();
		$get_edge_product_image = static function ($range_slug) use (&$edge_product_image_cache) {
			$range_slug = sanitize_title((string) $range_slug);
			if ('' === $range_slug) {
				return '';
			}

			$lookup_slug = 'p-fish' === $range_slug ? 'p-fish-2' : $range_slug;
			if (array_key_exists($lookup_slug, $edge_product_image_cache)) {
				return (string) $edge_product_image_cache[ $lookup_slug ];
			}

			$edge_product_image_cache[ $lookup_slug ] = '';
			if (! class_exists('WooCommerce') || ! function_exists('wc_get_products')) {
				return '';
			}

			$product_ids = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => 1,
					'return'       => 'ids',
					'orderby'      => 'date',
					'order'        => 'DESC',
					'stock_status' => 'instock',
					'tag'          => array($lookup_slug),
				)
			);

			if (empty($product_ids)) {
				$product_ids = wc_get_products(
					array(
						'status'  => 'publish',
						'limit'   => 1,
						'return'  => 'ids',
						'orderby' => 'date',
						'order'   => 'DESC',
						'tag'     => array($lookup_slug),
					)
				);
			}

			if (! empty($product_ids)) {
				$image_url = get_the_post_thumbnail_url((int) $product_ids[0], 'woocommerce_thumbnail');
				if (is_string($image_url) && '' !== $image_url) {
					$edge_product_image_cache[ $lookup_slug ] = $image_url;
				}
			}

			return (string) $edge_product_image_cache[ $lookup_slug ];
		};
		?>
		<?php if (! empty($edge_items)) : ?>
			<div class="category-grid">
				<?php foreach ($edge_items as $edge_item) : ?>
					<?php
					$category    = isset($edge_item['term']) && $edge_item['term'] instanceof WP_Term ? $edge_item['term'] : null;
					$term_link   = isset($edge_item['link']) ? (string) $edge_item['link'] : '';
					$card_name   = isset($edge_item['name']) ? (string) $edge_item['name'] : '';
					$card_count  = array_key_exists('count', $edge_item) ? $edge_item['count'] : null;
					$slug_key    = isset($edge_item['slug_key']) ? sanitize_title((string) $edge_item['slug_key']) : '';
					$meta_text   = isset($edge_item['meta_text']) ? (string) $edge_item['meta_text'] : '';
					$thumbnail_id = $category ? absint((int) get_term_meta($category->term_id, 'thumbnail_id', true)) : 0;
					$image_stack  = isset($category_image_map[ $slug_key ]) ? $category_image_map[ $slug_key ] : $verified_theme_candidates;
					$heroish_url  = $resolve_theme_image($image_stack);
					$manual_edge_image_url = function_exists('mad_baits_get_choose_edge_custom_image_url')
						? mad_baits_get_choose_edge_custom_image_url($slug_key, 'large')
						: '';
					if (is_string($manual_edge_image_url) && '' !== $manual_edge_image_url) {
						$heroish_url = $manual_edge_image_url;
					}
					$range_product_image_url = $get_edge_product_image($slug_key);
					if ('' === $manual_edge_image_url && '' !== $range_product_image_url) {
						$heroish_url = $range_product_image_url;
					}
					if (! is_string($heroish_url) || '' === $heroish_url) {
						$heroish_url = get_theme_file_uri('/assets/img/CUP-LOGOv2-1.svg');
					}
					$card_style   = '';
					$index        = $category ? (int) $category->term_id : (int) crc32($slug_key);
					$bg_positions = array('center 24%', 'center 44%', 'center 62%', 'center 36%', '58% 52%', '42% 48%');
					$accent_swatches = array(
						'rgba(255,242,2,0.34)',
						'rgba(255,198,61,0.28)',
						'rgba(255,242,2,0.2)',
						'rgba(255,214,89,0.3)',
					);
					$position = $bg_positions[ abs($index) % count($bg_positions) ];
					$accent   = $accent_swatches[ abs($index) % count($accent_swatches) ];
					$card_classes = array('category-card');
					if ('' !== $slug_key) {
						$card_classes[] = 'category-card--' . sanitize_html_class($slug_key);
					}

					$card_vars = array(
						"--category-bg-position: {$position}",
						"--category-accent: {$accent}",
					);

					if ($heroish_url) {
						$card_vars[] = "--category-bg-image: url('" . esc_url_raw($heroish_url) . "')";
					}

					$card_style = ' style="' . esc_attr(implode('; ', $card_vars) . ';') . '"';
					?>
					<?php if ('' !== $term_link && '' !== $card_name) : ?>
						<a class="<?php echo esc_attr(implode(' ', array_values(array_unique($card_classes)))); ?>" href="<?php echo esc_url($term_link); ?>"<?php echo $card_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<span class="category-card__image-wrap">
								<?php if ($thumbnail_id) : ?>
									<?php
									echo wp_get_attachment_image(
										$thumbnail_id,
										'woocommerce_thumbnail',
										false,
										array(
											'class'   => 'category-card__image',
											'loading' => 'lazy',
											'alt'     => $card_name,
										)
									); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								<?php else : ?>
									<img
										class="category-card__image"
										src="<?php echo esc_url($heroish_url); ?>"
										loading="lazy"
										alt="<?php echo esc_attr($card_name); ?>"
									/>
								<?php endif; ?>
							</span>
							<span class="category-card__content">
								<span class="category-card__title"><?php echo esc_html($card_name); ?></span>
								<span class="category-card__meta">
									<?php if (null !== $card_count && $card_count >= 0) : ?>
										<?php
										printf(
											esc_html(_n('%s product', '%s products', absint($card_count), 'mad-baits')),
											esc_html(number_format_i18n(absint($card_count)))
										);
										?>
									<?php elseif ('' !== $meta_text) : ?>
										<?php echo esc_html($meta_text); ?>
									<?php endif; ?>
								</span>
								<span class="category-card__cta">
									<?php esc_html_e('Shop by Range', 'mad-baits'); ?>
								</span>
							</span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="section__empty-state"><?php esc_html_e('Add the listed product categories in WooCommerce, or publish the Bundle Deals and Compulsive Anglers pages, to populate this section.', 'mad-baits'); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
if (function_exists('mad_baits_render_compulsive_specials_section')) {
	mad_baits_render_compulsive_specials_section();
}
?>

<section class="section section--contrast home-editorial-split"<?php echo $editorial_split_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="home-editorial-split__layout">
			<div class="home-editorial-split__media" aria-hidden="true"></div>
			<div class="home-editorial-split__content">
				<p class="section__kicker"><?php esc_html_e('Built Different', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Made For Anglers Who Demand More', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('From rolling table to waterside execution, every batch is built for confidence-led campaign fishing with consistency you can trust trip after trip.', 'mad-baits'); ?></p>
				<div class="home-editorial-split__actions">
					<a class="mad-button" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Shop Boilies', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($about_page_url); ?>"><?php esc_html_e('Read About Mad Baits', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--contrast home-bestsellers">
	<div class="container">
		<div class="section__heading section__heading--with-actions">
			<div>
				<p class="section__kicker"><?php esc_html_e('Bestsellers', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Most Trusted On The Bank', 'mad-baits'); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('See full shop', 'mad-baits'); ?></a>
		</div>
		<div class="product-grid">
			<?php
			if (class_exists('WooCommerce')) {
				if (! isset($home_featured_ids) || ! is_array($home_featured_ids)) {
					$home_featured_ids = array();
				}
				$bestseller_query = new WP_Query(array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'posts_per_page'      => 4,
					'meta_key'            => 'total_sales',
					'orderby'             => 'meta_value_num',
					'order'               => 'DESC',
					'post__not_in'        => array_map('absint', $home_featured_ids),
					'ignore_sticky_posts' => true,
					'tax_query'           => $mad_merge_home_tax_query(array()),
				));

				if ($bestseller_query->have_posts()) :
					while ($bestseller_query->have_posts()) :
						$bestseller_query->the_post();
						$bestseller_id = (int) get_the_ID();
						$home_featured_ids[] = $bestseller_id;
						mad_baits_render_product_card($bestseller_id, true);
					endwhile;
					wp_reset_postdata();
				else :
					echo '<p class="section__empty-state">' . esc_html__('No bestselling products available yet.', 'mad-baits') . '</p>';
				endif;
			}
			?>
		</div>
	</div>
</section>

<section class="section section--tight home-new-this-week"<?php echo $new_this_week_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="section__heading section__heading--with-actions">
			<div>
				<p class="section__kicker"><?php esc_html_e('Fresh Drops', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('New This Week', 'mad-baits'); ?></h2>
				<p class="section__lede"><?php esc_html_e('Latest releases, fresh rolls and new campaign-ready additions landed this week.', 'mad-baits'); ?></p>
			</div>
			<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('View latest products', 'mad-baits'); ?></a>
		</div>
		<div class="product-grid">
			<?php
			if (class_exists('WooCommerce')) {
				if (! isset($home_featured_ids) || ! is_array($home_featured_ids)) {
					$home_featured_ids = array();
				}
				$fresh_query = new WP_Query(array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'posts_per_page'      => 4,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'post__not_in'        => array_map('absint', $home_featured_ids),
					'ignore_sticky_posts' => true,
					'tax_query'           => $mad_merge_home_tax_query(array()),
				));

				if ($fresh_query->have_posts()) :
					while ($fresh_query->have_posts()) :
						$fresh_query->the_post();
						mad_baits_render_product_card(get_the_ID(), true);
					endwhile;
					wp_reset_postdata();
				else :
					echo '<p class="section__empty-state">' . esc_html__('No recent products yet.', 'mad-baits') . '</p>';
				endif;
			}
			?>
		</div>
	</div>
</section>

<section class="section">
	<div class="container">
		<div class="bundle-promo bundle-promo--photo"<?php echo $bundle_promo_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="bundle-promo__content">
				<p class="section__kicker"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Create A Custom Bundle For Your Next Campaign', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Stack hookbaits, liquids and food baits into one high-performance order designed for your water and approach.', 'mad-baits'); ?></p>
				<div class="bundle-promo__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Build A Bundle', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($contact_url); ?>"><?php esc_html_e('Talk To The Team', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--contrast section--immersive-bg home-results-section"<?php echo $testimonials_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="section__heading">
			<div>
				<p class="section__kicker"><?php esc_html_e('Social Proof', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Backed By Real Session Results', 'mad-baits'); ?></h2>
			</div>
		</div>
		<div class="home-results-section__stats">
			<article class="home-results-section__stat-card">
				<strong>2mil+ KG</strong>
				<span><?php esc_html_e('Rolled', 'mad-baits'); ?></span>
			</article>
			<article class="home-results-section__stat-card">
				<strong>OVER 5000+</strong>
				<span><?php esc_html_e('Customer Captures', 'mad-baits'); ?></span>
			</article>
			<article class="home-results-section__stat-card">
				<strong><?php esc_html_e('UK + Europe', 'mad-baits'); ?></strong>
				<span><?php esc_html_e('Waters Fished', 'mad-baits'); ?></span>
			</article>
			<article class="home-results-section__stat-card">
				<strong><?php esc_html_e('Campaign Trusted', 'mad-baits'); ?></strong>
				<span><?php esc_html_e('By Serious Anglers', 'mad-baits'); ?></span>
			</article>
		</div>
	</div>
</section>

<section class="section home-catch-reports"<?php echo $catch_reports_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="section__heading section__heading--with-actions">
			<div>
				<p class="section__kicker"><?php esc_html_e('Social Proof', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Recently Caught On', 'mad-baits'); ?></h2>
				<p class="section__lede"><?php esc_html_e('Real captures, real lakes and proven bait choices from active campaign sessions.', 'mad-baits'); ?></p>
			</div>
			<a class="text-link" href="<?php echo esc_url($catch_reports_url); ?>"><?php esc_html_e('View all catch reports', 'mad-baits'); ?></a>
		</div>
		<?php if (! empty($recent_catch_report_ids) && function_exists('mad_baits_render_catch_report_card')) : ?>
			<div class="mad-catch-grid mad-catch-grid--homepage" data-catch-grid>
				<?php foreach ($recent_catch_report_ids as $catch_report_id) : ?>
					<?php mad_baits_render_catch_report_card((int) $catch_report_id, array('class' => 'mad-catch-card--home')); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="section__empty-state"><?php esc_html_e('Catch reports will appear here once your first session results are published.', 'mad-baits'); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="section section--tight home-bank"<?php echo $bank_section_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<div class="section__heading section__heading--with-actions">
			<div>
				<p class="section__kicker"><?php esc_html_e('Bankside Culture', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('From The Bank', 'mad-baits'); ?></h2>
			</div>
			<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop what you see', 'mad-baits'); ?></a>
		</div>
		<div class="bank-culture__grid">
			<?php
			if (class_exists('WooCommerce')) :
				$social_query = new WP_Query(array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 6,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'tax_query'      => $mad_merge_home_tax_query(array()),
				));

				if ($social_query->have_posts()) :
					while ($social_query->have_posts()) :
						$social_query->the_post();
						$product_id = get_the_ID();
						if (function_exists('mad_baits_should_show_product_on_homepage') && ! mad_baits_should_show_product_on_homepage($product_id)) {
							continue;
						}
						$product_link = get_permalink($product_id);
						$title        = get_the_title($product_id);
						$image_id     = get_post_thumbnail_id($product_id);
						?>
						<a class="bank-culture__card" href="<?php echo esc_url($product_link); ?>">
							<?php if ($image_id) : ?>
								<?php
								echo wp_get_attachment_image(
									$image_id,
									'woocommerce_thumbnail',
									false,
									array(
										'class'   => 'bank-culture__image',
										'loading' => 'lazy',
										'alt'     => $title,
									)
								); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							<?php else : ?>
								<span class="bank-culture__fallback" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="bank-culture__label"><?php echo esc_html($title); ?></span>
						</a>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="section__empty-state"><?php esc_html_e('Add products to fill this social strip.', 'mad-baits'); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('WooCommerce is required to display this section.', 'mad-baits'); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if (function_exists('mad_baits_render_mad_baits_tv_component')) : ?>
	<div class="home-mb-tv-shell"<?php echo $mad_baits_tv_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<section class="section section--contrast home-mb-tv-intro">
		<div class="container">
			<p class="section__kicker"><?php esc_html_e('MAD BAITS TV', 'mad-baits'); ?></p>
			<h2><?php esc_html_e('Latest Session Films, Bait Testing & Big Fish', 'mad-baits'); ?></h2>
			<p class="home-mb-tv-intro__support"><?php esc_html_e('LATEST SESSION FILMS • BAIT TESTING • BIG FISH', 'mad-baits'); ?></p>
		</div>
	</section>
	<?php
	mad_baits_render_mad_baits_tv_component(
		array(
			'section_class' => 'section section--contrast mad-baits-tv mad-baits-tv--home',
			'show_header'   => true,
			'show_cta'      => true,
			'show_filters'  => false,
			'grid_limit'    => 3,
			'headline'      => __('Mad Baits TV', 'mad-baits'),
			'kicker'        => __('Mad Baits TV', 'mad-baits'),
			'cta_url'       => $mad_baits_tv_url,
		)
	);
	?>
	</div>
<?php endif; ?>

<section class="section section--campaign-cta">
	<div class="container">
		<div class="session-confidence"<?php echo $session_cta_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="session-confidence__content">
				<p class="section__kicker"><?php esc_html_e('Session Confidence', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Fish With Premium Confidence This Weekend', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Proven food baits, campaign-ready bundles and hard-wearing hookbait systems to keep fish in your zone for longer.', 'mad-baits'); ?></p>
				<div class="session-confidence__actions">
					<a class="mad-button" href="<?php echo esc_url($bundle_deals_url); ?>"><?php esc_html_e('Shop Bundle Deals', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Browse Boilies', 'mad-baits'); ?></a>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="section section--contrast home-campaign-confidence">
	<div class="container container--narrow">
		<div class="home-campaign-confidence__inner">
			<p class="section__kicker"><?php esc_html_e('Built On Campaign Confidence', 'mad-baits'); ?></p>
			<h2><?php esc_html_e('Designed for anglers who want consistency, confidence and results every session.', 'mad-baits'); ?></h2>
			<div class="home-campaign-confidence__actions">
				<a class="mad-button" href="<?php echo esc_url($boilie_range_url); ?>"><?php esc_html_e('Shop Boilies', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($build_my_session_url); ?>"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></a>
			</div>
		</div>
	</div>
</section>

<section class="section section--contrast trust-strip">
	<div class="container">
		<div class="trust-strip__grid">
			<article class="trust-strip__item">
				<h3><?php esc_html_e('Fast UK Delivery', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Reliable dispatch windows with clear tracking updates.', 'mad-baits'); ?></p>
			</article>
			<article class="trust-strip__item">
				<h3><?php esc_html_e('Secure Checkout', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Trusted payment flow for quick, safe ordering.', 'mad-baits'); ?></p>
			</article>
			<article class="trust-strip__item">
				<h3><?php esc_html_e('Angler-Tested Bait', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Proven on pressured waters by campaign anglers.', 'mad-baits'); ?></p>
			</article>
			<article class="trust-strip__item">
				<h3><?php esc_html_e('Direct Support', 'mad-baits'); ?></h3>
				<p><?php esc_html_e('Need help matching products? Speak to the team.', 'mad-baits'); ?></p>
			</article>
		</div>
	</div>
</section>

<?php
get_footer();

