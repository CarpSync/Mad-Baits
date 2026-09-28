<?php
/**
 * Technical SEO: titles, meta fallbacks, breadcrumbs, robots, schema, redirects.
 *
 * Works alongside Rank Math or Yoast; theme fallbacks apply when plugin fields are empty.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Whether Rank Math SEO is active.
 *
 * @return bool
 */
function mad_baits_seo_is_rank_math_active() {
	return defined('RANK_MATH_VERSION') || class_exists('RankMath', false);
}

/**
 * Whether Yoast SEO is active.
 *
 * @return bool
 */
function mad_baits_seo_is_yoast_active() {
	return defined('WPSEO_VERSION') || class_exists('WPSEO_Options', false);
}

/**
 * Whether a major SEO plugin handles head meta.
 *
 * @return bool
 */
function mad_baits_seo_plugin_handles_head() {
	return mad_baits_seo_is_rank_math_active() || mad_baits_seo_is_yoast_active();
}

/**
 * Brand suffix for document titles.
 *
 * @return string
 */
function mad_baits_seo_brand_suffix() {
	return 'Mad Baits';
}

/**
 * Default branded OG image URL (large, not thumbnail).
 *
 * @return string
 */
function mad_baits_seo_get_default_og_image_url() {
	$candidates = array(
		'/assets/img/email/website-launch/product-hero.png',
		'/assets/img/hookbaits-hero.png',
		'/assets/img/CUP-LOGOv2-1.svg',
	);

	foreach ($candidates as $relative) {
		$path = get_theme_file_path($relative);
		if ($path && file_exists($path)) {
			return get_theme_file_uri($relative);
		}
	}

	$custom_logo_id = (int) get_theme_mod('custom_logo');
	if ($custom_logo_id > 0) {
		$url = wp_get_attachment_image_url($custom_logo_id, 'large');
		if (is_string($url) && '' !== $url) {
			return $url;
		}
	}

	return home_url('/wp-content/themes/mad-baits/assets/img/CUP-LOGOv2-1.svg');
}

/**
 * Replace staging host in URLs with the current site host (launch safety).
 *
 * @param string $url URL.
 * @return string
 */
function mad_baits_seo_normalize_public_url($url) {
	if (! is_string($url) || '' === $url) {
		return '';
	}

	$parsed = wp_parse_url($url);
	if (! is_array($parsed) || empty($parsed['host'])) {
		return $url;
	}

	$staging_hosts = array('staging.madbaits.com', 'www.staging.madbaits.com');
	$home_host     = wp_parse_url(home_url('/'), PHP_URL_HOST);

	if (! is_string($home_host) || '' === $home_host) {
		return $url;
	}

	if (! in_array(strtolower((string) $parsed['host']), $staging_hosts, true)) {
		return $url;
	}

	$parsed['host'] = $home_host;
	$scheme         = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
	if (is_string($scheme) && '' !== $scheme) {
		$parsed['scheme'] = $scheme;
	}
	$normalized = '';
	if (! empty($parsed['scheme'])) {
		$normalized .= (string) $parsed['scheme'] . '://';
	}
	if (! empty($parsed['user'])) {
		$normalized .= (string) $parsed['user'];
		if (! empty($parsed['pass'])) {
			$normalized .= ':' . (string) $parsed['pass'];
		}
		$normalized .= '@';
	}
	$normalized .= (string) $parsed['host'];
	if (! empty($parsed['port'])) {
		$normalized .= ':' . (int) $parsed['port'];
	}
	$normalized .= isset($parsed['path']) ? (string) $parsed['path'] : '/';
	if (isset($parsed['query']) && '' !== (string) $parsed['query']) {
		$normalized .= '?' . (string) $parsed['query'];
	}
	if (isset($parsed['fragment']) && '' !== (string) $parsed['fragment']) {
		$normalized .= '#' . (string) $parsed['fragment'];
	}

	return '' !== $normalized ? $normalized : $url;
}

/**
 * Category meta description fallbacks keyed by slug.
 *
 * @return array<string, string>
 */
function mad_baits_seo_get_category_meta_descriptions() {
	return array(
		'boilies'            => 'Premium Mad Baits boilies for serious carp anglers. Shop shelf life, freezer and session-ready boilie options built for confidence on the bank.',
		'hookbaits'          => 'Shop Mad Baits hookbaits including pop ups, wafters and Skinz options designed to match your baiting approach.',
		'pellets'            => 'Shop matching feed pellets and floating pellets from Mad Baits, ideal for completing your session setup.',
		'liquids'            => 'Food dips, oils and liquid attractors built to boost your bait and complete your Mad Baits range.',
		'groundbait-bag-mix' => 'High-attraction groundbait and bag mix options for PVA bags, spod work and confident feeding.',
		'paste'              => 'Shop Mad Baits paste for wrapping hookbaits, boosting attraction and matching your baiting approach.',
		'sprays'             => 'Hookbait sprays and bait boosters designed to add fast attraction when it matters.',
		'bundles-deals'      => 'Save with Mad Baits bundle deals and session packs. Build your bait setup and get session-ready faster.',
		'tackle'             => 'Terminal tackle and bankside essentials for serious carp anglers.',
		'clothing'           => 'Mad Baits clothing and apparel for anglers on and off the bank.',
		'accessories'        => 'Useful bait tools, accessories and bankside essentials from Mad Baits.',
		'extras'             => 'Mad Baits extras, add-ons and session essentials for completing your setup.',
	);
}

/**
 * Range meta description fallbacks keyed by tag slug.
 *
 * @return array<string, string>
 */
function mad_baits_seo_get_range_meta_descriptions() {
	return array(
		'asbo'              => 'Shop the ASBO range from Mad Baits — bold, high-attraction bait built for confident carp fishing.',
		'bbb'               => 'Shop the BBB range from Mad Baits — trusted bait options for confident feeding and consistent results.',
		'calamino'          => 'Shop the Calamino range from Mad Baits, built for attraction and confidence across your session.',
		'compulsive-angler' => 'Shop the Compulsive Angler range from Mad Baits — specialist bait options for committed carp anglers.',
		'nutz-banana'       => 'Shop the Nutz Banana range from Mad Baits — sweet banana attraction across boilies, hookbaits and liquids.',
		'nutz-plus'         => 'Shop the Nutz Plus range from Mad Baits — nut-based attraction across proven carp bait products.',
		'p-fish-2'          => 'Shop the P-Fish range from Mad Baits — fishmeal-based bait options built for food-source attraction and confidence.',
		'pandemic'          => 'Shop the Pandemic range from Mad Baits — powerful attraction across boilies, hookbaits, liquids and session-ready bait.',
		'wicked-white'      => 'Shop the Wicked Whites range from Mad Baits — bright, high-attraction bait designed to stand out.',
	);
}

/**
 * Resolve SEO context for the current request.
 *
 * @return array<string, mixed>
 */
function mad_baits_seo_get_context() {
	$context = array(
		'type'        => 'generic',
		'title'       => '',
		'description' => '',
		'image'       => mad_baits_seo_get_default_og_image_url(),
		'canonical'   => '',
	);

	if (is_front_page()) {
		$context['type']        = 'home';
		$context['title']       = 'Mad Baits | Premium Carp Bait, Boilies, Hookbaits & Session Deals';
		$context['description'] = 'Shop premium Mad Baits carp bait — boilies, hookbaits, pellets, liquids, bundle deals and session-ready tackle. Fast UK dispatch.';
		$context['canonical']   = home_url('/');
		return $context;
	}

	if (function_exists('is_shop') && is_shop() && ! is_search()) {
		$context['type']        = 'shop';
		$context['title']       = 'Shop Carp Bait | Mad Baits';
		$context['description'] = 'Browse the full Mad Baits shop — boilies, hookbaits, pellets, liquids, bundles and tackle for serious carp anglers.';
		$context['canonical']   = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
		return $context;
	}

	if (is_tax('product_cat')) {
		$term = get_queried_object();
		if ($term instanceof WP_Term) {
			$slug = sanitize_title((string) $term->slug);
			$name = (string) $term->name;

			if ('team-testing' === $slug) {
				$context['type'] = 'noindex';
			} else {
				$context['type']  = 'product_cat';
				$context['title'] = sprintf('%s | Mad Baits Carp Bait', $name);
				$map              = mad_baits_seo_get_category_meta_descriptions();
				$context['description'] = isset($map[ $slug ]) ? (string) $map[ $slug ] : wp_strip_all_tags((string) term_description($term));
				if ('' === $context['description']) {
					$context['description'] = sprintf(
						/* translators: %s: category name */
						__('Shop %s from Mad Baits — premium carp bait and session essentials.', 'mad-baits'),
						$name
					);
				}
			}

			$link = get_term_link($term);
			if (! is_wp_error($link)) {
				$context['canonical'] = (string) $link;
			}

			$thumb_id = (int) get_term_meta((int) $term->term_id, 'thumbnail_id', true);
			if ($thumb_id > 0) {
				$img = wp_get_attachment_image_url($thumb_id, 'large');
				if (is_string($img) && '' !== $img) {
					$context['image'] = $img;
				}
			}
		}

		return $context;
	}

	if (is_tax('product_tag')) {
		$term = get_queried_object();
		if ($term instanceof WP_Term) {
			$slug  = function_exists('mad_baits_resolve_range_tag_slug')
				? mad_baits_resolve_range_tag_slug((string) $term->slug)
				: sanitize_title((string) $term->slug);
			$label = function_exists('mad_baits_get_range_display_label')
				? mad_baits_get_range_display_label($slug, (string) $term->name)
				: (string) $term->name;

			$context['type']  = 'product_tag';
			$context['title'] = sprintf('%s Carp Bait Range | Mad Baits', $label);
			$map              = mad_baits_seo_get_range_meta_descriptions();
			$context['description'] = isset($map[ $slug ]) ? (string) $map[ $slug ] : wp_strip_all_tags((string) term_description($term));
			if ('' === $context['description']) {
				$context['description'] = sprintf(
					/* translators: %s: range name */
					__('Shop the %s range from Mad Baits — boilies, hookbaits, liquids and session-ready carp bait.', 'mad-baits'),
					$label
				);
			}

			$link = function_exists('mad_baits_get_range_tag_shop_url')
				? mad_baits_get_range_tag_shop_url($slug)
				: get_term_link($term);
			if (! is_wp_error($link) && is_string($link)) {
				$context['canonical'] = $link;
			}
		}

		return $context;
	}

	if (function_exists('is_product') && is_product()) {
		global $product;
		if ($product instanceof WC_Product) {
			$context['type']        = 'product';
			$context['title']       = sprintf('%s | Mad Baits', $product->get_name());
			$short                  = wp_strip_all_tags($product->get_short_description());
			$long                   = wp_strip_all_tags($product->get_description());
			$context['description'] = '' !== $short ? $short : $long;
			if ('' === $context['description']) {
				$context['description'] = sprintf(
					/* translators: %s: product name */
					__('Buy %s from Mad Baits — premium carp bait with fast UK dispatch.', 'mad-baits'),
					$product->get_name()
				);
			}
			$context['canonical'] = $product->get_permalink();
			$image_id             = (int) $product->get_image_id();
			if ($image_id > 0) {
				$img = wp_get_attachment_image_url($image_id, 'large');
				if (is_string($img) && '' !== $img) {
					$context['image'] = $img;
				}
			}
		}

		return $context;
	}

	if (is_singular('page')) {
		$post_id  = get_queried_object_id();
		$template = (string) get_page_template_slug($post_id);
		$slug     = sanitize_title((string) get_post_field('post_name', $post_id));
		$cat_map  = mad_baits_seo_get_category_meta_descriptions();

		$landing = mad_baits_seo_get_landing_page_meta($template, $slug, $cat_map);
		if (! empty($landing['title'])) {
			$context['type']        = (string) $landing['type'];
			$context['title']       = (string) $landing['title'];
			$context['description'] = (string) $landing['description'];
			$context['canonical']   = get_permalink($post_id);
			if (has_post_thumbnail($post_id)) {
				$img = get_the_post_thumbnail_url($post_id, 'large');
				if (is_string($img) && '' !== $img) {
					$context['image'] = $img;
				}
			}
			return $context;
		}
	}

	if (is_singular()) {
		$post_id = get_queried_object_id();
		$context['type']        = 'singular';
		$context['title']       = sprintf('%s | Mad Baits', get_the_title($post_id));
		$context['description'] = has_excerpt($post_id) ? wp_strip_all_tags(get_the_excerpt($post_id)) : '';
		$context['canonical']   = get_permalink($post_id);
		if (has_post_thumbnail($post_id)) {
			$img = get_the_post_thumbnail_url($post_id, 'large');
			if (is_string($img) && '' !== $img) {
				$context['image'] = $img;
			}
		}
	}

	return $context;
}

/**
 * Dedicated meta for key landing page templates / slugs.
 *
 * @param string               $template Page template slug.
 * @param string               $slug     Page slug.
 * @param array<string,string> $cat_map  Category meta map.
 * @return array{type:string,title:string,description:string}
 */
function mad_baits_seo_get_landing_page_meta($template, $slug, array $cat_map) {
	$template = (string) $template;
	$slug     = sanitize_title((string) $slug);

	if ('template-ai-bait-finder.php' === $template || 'ai-bait-finder' === $slug) {
		return array(
			'type'        => 'ai_bait_finder',
			'title'       => __('AI Bait Finder | Mad Baits', 'mad-baits'),
			'description' => __(
				'Use the Mad Baits AI Bait Finder to match boilies, hookbaits, liquids and pellets to your water, season and session style.',
				'mad-baits'
			),
		);
	}

	if ('template-boilie-range.php' === $template || 'boilie-range' === $slug) {
		return array(
			'type'        => 'landing_boilies',
			'title'       => __('Boilies | Mad Baits Carp Bait', 'mad-baits'),
			'description' => isset($cat_map['boilies'])
				? (string) $cat_map['boilies']
				: __('Premium Mad Baits boilies for serious carp anglers.', 'mad-baits'),
		);
	}

	if ('template-hookbaits.php' === $template || 'hookbaits' === $slug) {
		return array(
			'type'        => 'landing_hookbaits',
			'title'       => __('Hookbaits | Mad Baits Carp Bait', 'mad-baits'),
			'description' => isset($cat_map['hookbaits'])
				? (string) $cat_map['hookbaits']
				: __('Shop Mad Baits hookbaits including pop ups, wafters and Skinz options.', 'mad-baits'),
		);
	}

	if ('template-bundle-deals.php' === $template || in_array($slug, array('bundles-deals', 'bundle-deals', 'bundles'), true)) {
		return array(
			'type'        => 'landing_bundles',
			'title'       => __('Bundles & Deals | Mad Baits', 'mad-baits'),
			'description' => isset($cat_map['bundles-deals'])
				? (string) $cat_map['bundles-deals']
				: __('Save with Mad Baits bundle deals and session packs.', 'mad-baits'),
		);
	}

	return array(
		'type'        => '',
		'title'       => '',
		'description' => '',
	);
}

/**
 * Fallback document title when no SEO plugin supplies one.
 *
 * @param string $title Existing title.
 * @return string
 */
function mad_baits_seo_filter_document_title($title) {
	if (mad_baits_seo_plugin_handles_head()) {
		return $title;
	}

	$context = mad_baits_seo_get_context();
	if (! empty($context['title'])) {
		return (string) $context['title'];
	}

	return $title;
}
add_filter('pre_get_document_title', 'mad_baits_seo_filter_document_title', 20);

/**
 * Rank Math title fallback.
 *
 * @param string $title Title from Rank Math.
 * @return string
 */
function mad_baits_seo_rank_math_title($title) {
	$title = trim((string) $title);
	if ('' !== $title) {
		return $title;
	}

	$context = mad_baits_seo_get_context();

	return ! empty($context['title']) ? (string) $context['title'] : $title;
}
add_filter('rank_math/frontend/title', 'mad_baits_seo_rank_math_title', 99);

/**
 * Rank Math meta description fallback.
 *
 * @param string $description Description.
 * @return string
 */
function mad_baits_seo_rank_math_description($description) {
	$description = trim((string) $description);
	if ('' !== $description) {
		return $description;
	}

	$context = mad_baits_seo_get_context();

	return ! empty($context['description']) ? (string) $context['description'] : $description;
}
add_filter('rank_math/frontend/description', 'mad_baits_seo_rank_math_description', 99);

/**
 * Yoast title fallback.
 *
 * @param string $title Title.
 * @return string
 */
function mad_baits_seo_yoast_title($title) {
	$title = trim((string) $title);
	if ('' !== $title) {
		return $title;
	}

	$context = mad_baits_seo_get_context();

	return ! empty($context['title']) ? (string) $context['title'] : $title;
}
add_filter('wpseo_title', 'mad_baits_seo_yoast_title', 99);

/**
 * Yoast meta description fallback.
 *
 * @param string $description Description.
 * @return string
 */
function mad_baits_seo_yoast_metadesc($description) {
	$description = trim((string) $description);
	if ('' !== $description) {
		return $description;
	}

	$context = mad_baits_seo_get_context();

	return ! empty($context['description']) ? (string) $context['description'] : $description;
}
add_filter('wpseo_metadesc', 'mad_baits_seo_yoast_metadesc', 99);

/**
 * Normalize canonical URLs from SEO plugins on production.
 *
 * @param string $canonical Canonical URL.
 * @return string
 */
function mad_baits_seo_filter_canonical($canonical) {
	return mad_baits_seo_normalize_public_url((string) $canonical);
}
add_filter('rank_math/frontend/canonical', 'mad_baits_seo_filter_canonical', 99);
add_filter('wpseo_canonical', 'mad_baits_seo_filter_canonical', 99);

/**
 * Output meta + OG fallbacks when no SEO plugin is active.
 *
 * @return void
 */
function mad_baits_seo_output_head_fallbacks() {
	if (mad_baits_seo_plugin_handles_head() || is_admin()) {
		return;
	}

	$context = mad_baits_seo_get_context();
	if (empty($context['title']) && empty($context['description'])) {
		return;
	}

	if (! empty($context['description'])) {
		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr((string) $context['description'])
		);
	}

	$og_title = ! empty($context['title']) ? (string) $context['title'] : wp_get_document_title();
	$og_url   = ! empty($context['canonical']) ? mad_baits_seo_normalize_public_url((string) $context['canonical']) : mad_baits_seo_normalize_public_url((string) (is_ssl() ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '') . (isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/'));
	$og_image = ! empty($context['image']) ? mad_baits_seo_normalize_public_url((string) $context['image']) : mad_baits_seo_get_default_og_image_url();

	printf('<meta property="og:type" content="%s" />' . "\n", esc_attr(function_exists('is_product') && is_product() ? 'product' : 'website'));
	printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($og_title));
	printf('<meta property="og:description" content="%s" />' . "\n", esc_attr((string) ($context['description'] ?? '')));
	printf('<meta property="og:url" content="%s" />' . "\n", esc_url($og_url));
	printf('<meta property="og:image" content="%s" />' . "\n", esc_url($og_image));
	printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr(get_bloginfo('name')));
	printf('<meta name="twitter:card" content="summary_large_image" />' . "\n");
	printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($og_title));
	printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr((string) ($context['description'] ?? '')));
	printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($og_image));

	if (! empty($context['canonical'])) {
		printf('<link rel="canonical" href="%s" />' . "\n", esc_url(mad_baits_seo_normalize_public_url((string) $context['canonical'])));
	}
}
add_action('wp_head', 'mad_baits_seo_output_head_fallbacks', 3);

/**
 * Print a JSON-LD script tag.
 *
 * @param array<string, mixed> $data Schema payload.
 * @return void
 */
function mad_baits_seo_print_json_ld(array $data) {
	if (empty($data)) {
		return;
	}
	echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Whether a Rank Math / Yoast graph already contains a schema @type.
 *
 * @param array<string, mixed> $data Schema graph/data.
 * @param string               $type Schema.org type.
 * @return bool
 */
function mad_baits_seo_graph_has_type($data, $type) {
	$type = (string) $type;
	if ('' === $type || ! is_array($data)) {
		return false;
	}

	$aliases = array($type);
	// Rank Math variable products often use ProductGroup with nested Product offers.
	if ('Product' === $type) {
		$aliases[] = 'ProductGroup';
	}

	$check = static function ($node) use (&$check, $aliases) {
		if (! is_array($node)) {
			return false;
		}
		if (isset($node['@type'])) {
			$types = is_array($node['@type']) ? $node['@type'] : array($node['@type']);
			foreach ($types as $candidate) {
				foreach ($aliases as $alias) {
					if (strcasecmp((string) $candidate, (string) $alias) === 0) {
						return true;
					}
				}
			}
		}
		foreach ($node as $value) {
			if (is_array($value)) {
				// Walk nested objects/lists (hasVariant, isVariantOf, @graph, etc.).
				$is_list = array_keys($value) === range(0, count($value) - 1);
				if ($is_list) {
					foreach ($value as $child) {
						if ($check($child)) {
							return true;
						}
					}
				} elseif ($check($value)) {
					return true;
				}
			}
		}
		return false;
	};

	return $check($data);
}

/**
 * Build Organization schema from live site data only.
 *
 * @return array<string, mixed>
 */
function mad_baits_seo_build_organization_schema() {
	$logo = mad_baits_seo_get_default_og_image_url();
	$data = array(
		'@type' => 'Organization',
		'@id'   => mad_baits_seo_normalize_public_url(home_url('/#organization')),
		'name'  => 'Mad Baits',
		'url'   => mad_baits_seo_normalize_public_url(home_url('/')),
		'logo'  => mad_baits_seo_normalize_public_url($logo),
	);

	$social = array();
	if (function_exists('mad_baits_get_social_profile_urls')) {
		$social = array_values(array_filter((array) mad_baits_get_social_profile_urls()));
	}
	if (! empty($social)) {
		$data['sameAs'] = array_map('mad_baits_seo_normalize_public_url', $social);
	}

	return $data;
}

/**
 * Build WebSite schema.
 *
 * @return array<string, mixed>
 */
function mad_baits_seo_build_website_schema() {
	return array(
		'@type'           => 'WebSite',
		'@id'             => mad_baits_seo_normalize_public_url(home_url('/#website')),
		'url'             => mad_baits_seo_normalize_public_url(home_url('/')),
		'name'            => 'Mad Baits',
		'publisher'       => array('@id' => mad_baits_seo_normalize_public_url(home_url('/#organization'))),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => mad_baits_seo_normalize_public_url(home_url('/?s={search_term_string}')),
			'query-input' => 'required name=search_term_string',
		),
	);
}

/**
 * Build BreadcrumbList from WooCommerce breadcrumb trail when available.
 *
 * @return array<string, mixed>
 */
function mad_baits_seo_build_breadcrumb_schema() {
	if (! function_exists('woocommerce_breadcrumb') || ! class_exists('WC_Breadcrumb')) {
		return array();
	}

	$breadcrumb = new WC_Breadcrumb();
	$crumbs     = $breadcrumb->generate();
	if (! is_array($crumbs) || count($crumbs) < 1) {
		return array();
	}

	$crumbs = apply_filters('woocommerce_get_breadcrumb', $crumbs, $breadcrumb);
	if (! is_array($crumbs) || count($crumbs) < 1) {
		return array();
	}

	$items = array();
	$pos   = 1;
	foreach ($crumbs as $crumb) {
		if (! is_array($crumb) || empty($crumb[0])) {
			continue;
		}
		$item = array(
			'@type'    => 'ListItem',
			'position' => $pos,
			'name'     => wp_strip_all_tags((string) $crumb[0]),
		);
		if (! empty($crumb[1])) {
			$item['item'] = mad_baits_seo_normalize_public_url((string) $crumb[1]);
		}
		$items[] = $item;
		++$pos;
	}

	if (count($items) < 1) {
		return array();
	}

	$canonical = '';
	$context   = mad_baits_seo_get_context();
	if (! empty($context['canonical'])) {
		$canonical = (string) $context['canonical'];
	} elseif (function_exists('wp_get_canonical_url')) {
		$canonical = (string) wp_get_canonical_url();
	}
	if ('' === $canonical) {
		$canonical = home_url('/');
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => mad_baits_seo_normalize_public_url(trailingslashit($canonical) . '#breadcrumb'),
		'itemListElement' => $items,
	);
}

/**
 * Build Product + Offer (+ AggregateRating when real reviews exist).
 *
 * @param WC_Product $product Product.
 * @return array<string, mixed>
 */
function mad_baits_seo_build_product_schema($product) {
	if (! $product instanceof WC_Product) {
		return array();
	}

	$url   = mad_baits_seo_normalize_public_url($product->get_permalink());
	$image = '';
	$img_id = (int) $product->get_image_id();
	if ($img_id > 0) {
		$image_url = wp_get_attachment_image_url($img_id, 'large');
		if (is_string($image_url) && '' !== $image_url) {
			$image = mad_baits_seo_normalize_public_url($image_url);
		}
	}

	$price = $product->get_price();
	if ('' === (string) $price) {
		$price = $product->get_regular_price();
	}

	$availability = $product->is_in_stock()
		? 'https://schema.org/InStock'
		: 'https://schema.org/OutOfStock';
	if ($product->is_on_backorder(1)) {
		$availability = 'https://schema.org/BackOrder';
	}

	$offer = array(
		'@type'         => 'Offer',
		'url'           => $url,
		'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'GBP',
		'availability'  => $availability,
		'itemCondition' => 'https://schema.org/NewCondition',
	);
	if ('' !== (string) $price && is_numeric($price)) {
		$offer['price'] = wc_format_decimal($price, wc_get_price_decimals());
	}

	$data = array(
		'@type'       => 'Product',
		'@id'         => $url . '#product',
		'name'        => $product->get_name(),
		'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
		'url'         => $url,
		'offers'      => $offer,
		'brand'       => array(
			'@type' => 'Brand',
			'name'  => 'Mad Baits',
		),
	);

	$sku = (string) $product->get_sku();
	if ('' !== $sku) {
		$data['sku'] = $sku;
	}
	if ('' !== $image) {
		$data['image'] = array($image);
	}

	$review_count = (int) $product->get_review_count();
	$rating       = (float) $product->get_average_rating();
	if ($review_count > 0 && $rating > 0) {
		$data['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) $rating,
			'reviewCount' => (string) $review_count,
		);
	}

	return $data;
}

/**
 * Extract FAQ pairs from real page content only (never invent).
 *
 * @param int $post_id Post ID.
 * @return array<int, array{question:string,answer:string}>
 */
function mad_baits_seo_extract_faq_pairs($post_id) {
	$post_id = absint($post_id);
	if ($post_id < 1) {
		return array();
	}

	$post = get_post($post_id);
	if (! $post instanceof WP_Post) {
		return array();
	}

	$content = (string) $post->post_content;
	$pairs   = array();

	// Rank Math / Gutenberg FAQ blocks stored as HTML comments + markup.
	if (has_block('rank-math/faq-block', $post) || false !== strpos($content, 'rank-math-faq')) {
		if (preg_match_all(
			'/<div[^>]*class="[^"]*rank-math-faq-item[^"]*"[^>]*>.*?<[^>]+class="[^"]*rank-math-question[^"]*"[^>]*>(.*?)<\/[^>]+>.*?<[^>]+class="[^"]*rank-math-answer[^"]*"[^>]*>(.*?)<\/div>/is',
			$content,
			$matches,
			PREG_SET_ORDER
		)) {
			foreach ($matches as $match) {
				$q = trim(wp_strip_all_tags((string) ($match[1] ?? '')));
				$a = trim(wp_strip_all_tags((string) ($match[2] ?? '')));
				if ('' !== $q && '' !== $a) {
					$pairs[] = array('question' => $q, 'answer' => $a);
				}
			}
		}
	}

	// Simple H3/H4 + following paragraph FAQ pattern used on some landing pages.
	if (empty($pairs) && preg_match_all('/<h[34][^>]*>(.*?)<\/h[34]>\s*<p[^>]*>(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$q = trim(wp_strip_all_tags((string) ($match[1] ?? '')));
			$a = trim(wp_strip_all_tags((string) ($match[2] ?? '')));
			if ('' === $q || '' === $a) {
				continue;
			}
			if (false === strpos($q, '?') && strlen($q) > 80) {
				continue;
			}
			$pairs[] = array('question' => $q, 'answer' => $a);
			if (count($pairs) >= 12) {
				break;
			}
		}
	}

	return $pairs;
}

/**
 * Build FAQPage schema from extracted pairs.
 *
 * @param array<int, array{question:string,answer:string}> $pairs FAQ pairs.
 * @return array<string, mixed>
 */
function mad_baits_seo_build_faq_schema(array $pairs) {
	if (empty($pairs)) {
		return array();
	}

	$entities = array();
	foreach ($pairs as $pair) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => (string) $pair['question'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => (string) $pair['answer'],
			),
		);
	}

	return array(
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
}

/**
 * Collect theme schema nodes for the current request.
 *
 * @return array<int, array<string, mixed>>
 */
function mad_baits_seo_collect_schema_nodes() {
	$nodes = array();

	if (is_front_page()) {
		$nodes[] = mad_baits_seo_build_organization_schema();
		$nodes[] = mad_baits_seo_build_website_schema();
	}

	$breadcrumb = mad_baits_seo_build_breadcrumb_schema();
	if (! empty($breadcrumb)) {
		$nodes[] = $breadcrumb;
	}

	if (function_exists('is_product') && is_product()) {
		global $product;
		if (! $product instanceof WC_Product && function_exists('wc_get_product')) {
			$product = wc_get_product(get_queried_object_id());
		}
		$product_schema = mad_baits_seo_build_product_schema($product instanceof WC_Product ? $product : null);
		if (! empty($product_schema)) {
			$nodes[] = $product_schema;
		}
	}

	if (is_singular()) {
		$faq = mad_baits_seo_build_faq_schema(mad_baits_seo_extract_faq_pairs(get_queried_object_id()));
		if (! empty($faq)) {
			$nodes[] = $faq;
		}
	}

	return array_values(array_filter($nodes));
}

/**
 * Theme JSON-LD when no major SEO plugin is active.
 *
 * @return void
 */
function mad_baits_seo_output_theme_schema() {
	if (is_admin() || mad_baits_seo_plugin_handles_head()) {
		return;
	}

	$nodes = mad_baits_seo_collect_schema_nodes();
	if (empty($nodes)) {
		return;
	}

	mad_baits_seo_print_json_ld(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		)
	);
}
add_action('wp_head', 'mad_baits_seo_output_theme_schema', 20);

/**
 * Enrich Rank Math JSON-LD with missing types only (never invent ratings/FAQs).
 *
 * @param array<string, mixed> $data Rank Math schema data.
 * @return array<string, mixed>
 */
function mad_baits_seo_enrich_rank_math_json_ld($data) {
	if (! is_array($data)) {
		return $data;
	}

	$nodes = mad_baits_seo_collect_schema_nodes();
	if (empty($nodes)) {
		return $data;
	}

	if (! isset($data['@graph']) || ! is_array($data['@graph'])) {
		// Rank Math sometimes returns a flat entity; wrap if needed.
		if (isset($data['@type'])) {
			$data = array('@graph' => array($data));
		} else {
			$data['@graph'] = array();
		}
	}

	foreach ($nodes as $node) {
		$type = isset($node['@type']) ? (string) $node['@type'] : '';
		if ('' === $type || mad_baits_seo_graph_has_type($data, $type)) {
			continue;
		}
		$data['@graph'][] = $node;
	}

	return $data;
}
add_filter('rank_math/json_ld', 'mad_baits_seo_enrich_rank_math_json_ld', 99);

/**
 * Robots directives for non-indexable surfaces.
 *
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
function mad_baits_seo_wp_robots($robots) {
	if (is_admin()) {
		return $robots;
	}

	$noindex = false;

	if (function_exists('mad_baits_is_session_app_request') && mad_baits_is_session_app_request()) {
		$noindex = true;
	}

	if (is_search()) {
		$noindex = true;
	}

	if (function_exists('is_cart') && is_cart()) {
		$noindex = true;
	}

	if (function_exists('is_checkout') && is_checkout()) {
		$noindex = true;
	}

	if (function_exists('is_account_page') && is_account_page()) {
		$noindex = true;
	}

	if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
		$noindex = true;
	}

	if (is_tax('product_cat')) {
		$term = get_queried_object();
		if ($term instanceof WP_Term && 'team-testing' === sanitize_title((string) $term->slug)) {
			$noindex = true;
		}
	}

	if (isset($_GET['range_type']) && is_tax('product_cat')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$noindex = true;
	}

	if (function_exists('is_shop') && is_shop() && ! empty($_GET)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed = array('paged', 'page');
		$keys    = array_diff(array_keys($_GET), $allowed);
		if (! empty($keys)) {
			$noindex = true;
		}
	}

	if ($noindex) {
		$robots['noindex']   = true;
		$robots['nofollow']  = false;
		$robots['noarchive'] = true;
	}

	return $robots;
}
add_filter('wp_robots', 'mad_baits_seo_wp_robots', 20);

/**
 * Legacy taxonomy path redirects (301), including paths that may not 404.
 *
 * @return void
 */
function mad_baits_seo_legacy_taxonomy_redirects() {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return;
	}

	$request_uri  = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
	$request_path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
	if ('' === $request_path) {
		return;
	}

	$shop_fallback = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	$bundles_url   = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/product-category/bundles-deals/');

	$legacy_redirects = array(
		'product-category/bundles'           => $bundles_url,
		'product-category/bundle-deals'      => $bundles_url,
		'product-category/bundle-offers'     => $bundles_url,
		'product-category/deals'             => $bundles_url,
		'product-category/accesories'        => home_url('/product-category/accessories/'),
		'product-category/asbo'              => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('asbo', $shop_fallback) : home_url('/product-tag/asbo/'),
		'product-category/pandemic'          => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('pandemic', $shop_fallback) : home_url('/product-tag/pandemic/'),
		'product-category/p-fish'            => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('p-fish-2', $shop_fallback) : home_url('/product-tag/p-fish-2/'),
		'product-category/p-fish-2'          => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('p-fish-2', $shop_fallback) : home_url('/product-tag/p-fish-2/'),
		'product-category/nutz-plus'         => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('nutz-plus', $shop_fallback) : home_url('/product-tag/nutz-plus/'),
		'product-category/nutz-banana'       => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('nutz-banana', $shop_fallback) : home_url('/product-tag/nutz-banana/'),
		'product-category/wicked-white'      => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('wicked-white', $shop_fallback) : home_url('/product-tag/wicked-white/'),
		'product-category/bbb'               => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('bbb', $shop_fallback) : home_url('/product-tag/bbb/'),
		'product-category/calamino'          => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('calamino', $shop_fallback) : home_url('/product-tag/calamino/'),
		'product-category/compulsive'        => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('compulsive-angler', $shop_fallback) : home_url('/product-tag/compulsive-angler/'),
		'product-category/compulsive-angler' => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('compulsive-angler', $shop_fallback) : home_url('/product-tag/compulsive-angler/'),
	);

	if (! isset($legacy_redirects[ $request_path ])) {
		return;
	}

	$target = mad_baits_seo_normalize_public_url((string) $legacy_redirects[ $request_path ]);
	$target_path = trim((string) wp_parse_url($target, PHP_URL_PATH), '/');
	if ('' === $target_path || $target_path === $request_path) {
		return;
	}

	wp_safe_redirect($target, 301);
	exit;
}
add_action('template_redirect', 'mad_baits_seo_legacy_taxonomy_redirects', 0);

/**
 * Customize WooCommerce breadcrumbs for shop taxonomy SEO.
 *
 * @param array<int, array<int, string>> $crumbs Breadcrumb crumbs.
 * @param WC_Breadcrumb                    $breadcrumb Breadcrumb instance.
 * @return array<int, array<int, string>>
 */
function mad_baits_seo_filter_woocommerce_breadcrumb($crumbs, $breadcrumb) {
	unset($breadcrumb);

	$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	$home     = array(_x('Home', 'breadcrumb', 'mad-baits'), home_url('/'));

	if (is_tax('product_tag')) {
		$term = get_queried_object();
		if (! $term instanceof WP_Term) {
			return $crumbs;
		}

		$slug  = function_exists('mad_baits_resolve_range_tag_slug')
			? mad_baits_resolve_range_tag_slug((string) $term->slug)
			: sanitize_title((string) $term->slug);
		$label = function_exists('mad_baits_get_range_display_label')
			? mad_baits_get_range_display_label($slug, (string) $term->name)
			: (string) $term->name;
		$range_url = function_exists('mad_baits_get_range_tag_shop_url')
			? mad_baits_get_range_tag_shop_url($slug)
			: get_term_link($term);
		if (is_wp_error($range_url)) {
			$range_url = $shop_url;
		}

		if (function_exists('mad_baits_is_signature_range_tag') && mad_baits_is_signature_range_tag($slug)) {
			return array(
				$home,
				array(__('Shop', 'mad-baits'), $shop_url),
				array(__('Bait Ranges', 'mad-baits'), $shop_url),
				array($label, ''),
			);
		}

		return array(
			$home,
			array(__('Shop', 'mad-baits'), $shop_url),
			array($label, ''),
		);
	}

	if (is_tax('product_cat')) {
		$term = get_queried_object();
		if (! $term instanceof WP_Term) {
			return $crumbs;
		}

		if ('team-testing' === sanitize_title((string) $term->slug)) {
			return $crumbs;
		}

		$trail = array($home, array(__('Shop', 'mad-baits'), $shop_url));
		$link  = get_term_link($term);
		$trail[] = array((string) $term->name, is_wp_error($link) ? '' : (string) $link);

		return $trail;
	}

	if (function_exists('is_product') && is_product()) {
		global $product;
		if (! $product instanceof WC_Product) {
			return $crumbs;
		}

		$trail = array($home, array(__('Shop', 'mad-baits'), $shop_url));

		$cat_ids = wc_get_product_term_ids($product->get_id(), 'product_cat');
		if (! empty($cat_ids)) {
			$primary_id = (int) $cat_ids[0];
			$term       = get_term($primary_id, 'product_cat');
			if ($term instanceof WP_Term && ! is_wp_error($term) && 'team-testing' !== sanitize_title((string) $term->slug)) {
				$cat_link = get_term_link($term);
				$trail[]  = array((string) $term->name, is_wp_error($cat_link) ? '' : (string) $cat_link);
			}
		}

		$range_slugs = function_exists('mad_baits_get_product_signature_range_tags')
			? mad_baits_get_product_signature_range_tags((int) $product->get_id())
			: array();
		if (! empty($range_slugs)) {
			$range_slug = (string) $range_slugs[0];
			$label      = function_exists('mad_baits_get_range_display_label')
				? mad_baits_get_range_display_label($range_slug, '')
				: $range_slug;
			$range_url  = function_exists('mad_baits_get_range_tag_shop_url')
				? mad_baits_get_range_tag_shop_url($range_slug)
				: '';
			if ('' !== $range_url && '' !== $label) {
				$trail[] = array($label, $range_url);
			}
		}

		$trail[] = array($product->get_name(), '');

		return $trail;
	}

	if (function_exists('is_shop') && is_shop()) {
		return array(
			$home,
			array(__('Shop', 'mad-baits'), ''),
		);
	}

	return $crumbs;
}
add_filter('woocommerce_get_breadcrumb', 'mad_baits_seo_filter_woocommerce_breadcrumb', 30, 2);

/**
 * Image alt fallback for products and terms.
 *
 * @param array<string, mixed> $attr Attributes.
 * @param WP_Post              $attachment Attachment.
 * @return array<string, mixed>
 */
function mad_baits_seo_attachment_image_alt($attr, $attachment) {
	$current_alt = isset($attr['alt']) ? trim((string) $attr['alt']) : '';
	if ('' !== $current_alt) {
		return $attr;
	}

	$attachment_id = $attachment instanceof WP_Post ? (int) $attachment->ID : 0;
	if ($attachment_id < 1) {
		return $attr;
	}

	$parent_id = (int) wp_get_post_parent_id($attachment_id);
	if ($parent_id < 1 || 'product' !== get_post_type($parent_id)) {
		return $attr;
	}

	$product = function_exists('wc_get_product') ? wc_get_product($parent_id) : null;
	if (! $product instanceof WC_Product) {
		return $attr;
	}

	$attr['alt'] = sprintf(
		/* translators: %s: product name */
		__('%s from Mad Baits', 'mad-baits'),
		$product->get_name()
	);

	return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'mad_baits_seo_attachment_image_alt', 20, 2);

/**
 * Render internal category/type links on taxonomy archives.
 *
 * @return void
 */
function mad_baits_seo_render_archive_internal_links() {
	if (! (is_tax('product_cat') || is_tax('product_tag'))) {
		return;
	}

	$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	$links    = array();

	if (is_tax('product_tag')) {
		$term = get_queried_object();
		if (! $term instanceof WP_Term) {
			return;
		}

		$slug = function_exists('mad_baits_resolve_range_tag_slug')
			? mad_baits_resolve_range_tag_slug((string) $term->slug)
			: sanitize_title((string) $term->slug);

		if (! function_exists('mad_baits_is_signature_range_tag') || ! mad_baits_is_signature_range_tag($slug)) {
			return;
		}

		$links = array(
			array(__('Boilies', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('boilies', 'boilie'), $shop_url) : $shop_url),
			array(__('Hookbaits', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('hookbaits', 'hookbait'), $shop_url) : $shop_url),
			array(__('Liquids', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('liquids'), $shop_url) : $shop_url),
			array(__('Pellets', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('pellets'), $shop_url) : $shop_url),
			array(__('Bundles & Deals', 'mad-baits'), function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url),
		);
	} elseif (is_tax('product_cat')) {
		$term = get_queried_object();
		if (! $term instanceof WP_Term || 'team-testing' === sanitize_title((string) $term->slug)) {
			return;
		}

		$links = array(
			array(__('Shop', 'mad-baits'), $shop_url),
			array(__('Boilies', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('boilies', 'boilie'), $shop_url) : $shop_url),
			array(__('Hookbaits', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('hookbaits', 'hookbait'), $shop_url) : $shop_url),
			array(__('Liquids', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('liquids', 'liquid'), $shop_url) : $shop_url),
			array(__('Pellets', 'mad-baits'), function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('pellets', 'pellet'), $shop_url) : $shop_url),
			array(__('Bundles & Deals', 'mad-baits'), function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url),
		);

		if (function_exists('mad_baits_get_ai_bait_finder_url')) {
			$links[] = array(__('AI Bait Finder', 'mad-baits'), mad_baits_get_ai_bait_finder_url());
		}
	}

	if (empty($links)) {
		return;
	}
	?>
	<section class="section mad-seo-internal-links" aria-label="<?php esc_attr_e('Related shop links', 'mad-baits'); ?>">
		<div class="container">
			<h2 class="mad-seo-internal-links__title"><?php esc_html_e('Related Categories', 'mad-baits'); ?></h2>
			<nav class="mad-shop-quick-filters mad-seo-internal-links__nav">
				<?php foreach ($links as $link) : ?>
					<a class="mad-shop-quick-filters__link" href="<?php echo esc_url((string) $link[1]); ?>"><?php echo esc_html((string) $link[0]); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</section>
	<?php
}
add_action('woocommerce_after_main_content', 'mad_baits_seo_render_archive_internal_links', 12);

/**
 * Exclude Team & Testing from Yoast sitemaps.
 *
 * @param array<int> $excluded Term IDs.
 * @return array<int>
 */
function mad_baits_seo_yoast_exclude_team_testing($excluded) {
	$term = get_term_by('slug', 'team-testing', 'product_cat');
	if ($term instanceof WP_Term && ! is_wp_error($term)) {
		$excluded[] = (int) $term->term_id;
	}
	return array_values(array_unique(array_map('absint', (array) $excluded)));
}
add_filter('wpseo_exclude_from_sitemap_by_term_ids', 'mad_baits_seo_yoast_exclude_team_testing');

/**
 * Admin notice when duplicate Accessories categories exist.
 *
 * @return void
 */
function mad_baits_seo_admin_duplicate_accessories_notice() {
	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	$accessories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'slug'       => array('accessories', 'accessories-2', 'accesories'),
			'hide_empty' => false,
		)
	);

	if (is_wp_error($accessories) || count($accessories) < 2) {
		return;
	}

	$lines = array();
	foreach ($accessories as $term) {
		if (! $term instanceof WP_Term) {
			continue;
		}
		$count   = (int) $term->count;
		$lines[] = sprintf('%s (/%s/, %d products)', $term->name, $term->slug, $count);
	}

	if (empty($lines)) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Mad Baits SEO: duplicate Accessories categories detected', 'mad-baits') . '</strong></p>';
	echo '<p>' . esc_html__('Public menus and links prefer /product-category/accessories/. Review and merge in WooCommerce when ready — do not rename slugs in code.', 'mad-baits') . '</p>';
	echo '<ul>';
	foreach ($lines as $line) {
		echo '<li>' . esc_html($line) . '</li>';
	}
	echo '</ul></div>';
}
add_action('admin_notices', 'mad_baits_seo_admin_duplicate_accessories_notice');

/**
 * Enqueue SEO archive styles on shop taxonomies.
 *
 * @return void
 */
function mad_baits_seo_enqueue_styles() {
	if (! (is_tax(array('product_cat', 'product_tag')) || (function_exists('is_shop') && is_shop()))) {
		return;
	}

	$path = get_theme_file_path('assets/css/scoped/seo.css');
	if (! file_exists($path)) {
		return;
	}

	wp_enqueue_style(
		'mad-baits-seo',
		get_theme_file_uri('assets/css/scoped/seo.css'),
		array('mad-baits-main'),
		mad_baits_get_asset_version($path)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_seo_enqueue_styles', 40);
