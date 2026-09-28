<?php
/**
 * Range tag shop: products grouped by bait type on product_tag archives.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Canonical range tag slugs used on the storefront.
 *
 * @return array<string, string> slug => label
 */
function mad_baits_get_range_tag_catalog() {
	return array(
		'asbo'              => 'ASBO',
		'bbb'               => 'BBB',
		'calamino'          => 'Calamino',
		'nutz-plus'         => 'Nutz Plus',
		'nutz-banana'       => 'Nutz Banana',
		'pandemic'          => 'Pandemic',
		'p-fish-2'          => 'P-Fish',
		'wicked-white'      => 'Wicked Whites',
		'compulsive-angler' => 'Compulsive Angler',
	);
}

/**
 * Display label for a range tag slug (catalog overrides term name).
 *
 * @param string $slug     Tag slug.
 * @param string $fallback Fallback label.
 * @return string
 */
function mad_baits_get_range_display_label($slug, $fallback = '') {
	$slug = mad_baits_resolve_range_tag_slug((string) $slug);
	$catalog = mad_baits_get_range_tag_catalog();

	if (isset($catalog[ $slug ])) {
		return (string) $catalog[ $slug ];
	}

	$fallback = trim((string) $fallback);

	return '' !== $fallback ? $fallback : ucwords(str_replace('-', ' ', $slug));
}

/**
 * Premium intro copy by signature range slug.
 *
 * @return array<string, string>
 */
function mad_baits_get_range_landing_intro_map() {
	return array(
		'bbb'               => __('The iconic Big Black Berry profile with deep food signal and proven campaign consistency across seasons.', 'mad-baits'),
		'asbo'              => __('A bold, aggressive attractor profile built to trigger quick bites and keep pressure on feeding fish.', 'mad-baits'),
		'calamino'          => __('Balanced attraction and confidence across boilies, hookbaits and liquids — built for consistent session results.', 'mad-baits'),
		'wicked-white'      => __('High-visibility confidence baiting with a proven milk-protein edge for singles, traps and spread feed.', 'mad-baits'),
		'p-fish-2'          => __('A fishmeal-forward campaign range balancing digestibility, leakage and long-term confidence baiting.', 'mad-baits'),
		'pandemic'          => __('A complex food profile engineered for heavy baiting, repeat captures and reliable year-round performance.', 'mad-baits'),
		'nutz-plus'         => __('Nut-based attraction with creamy depth and balanced nutrition designed for steady, repeatable results.', 'mad-baits'),
		'nutz-banana'       => __('Sweet nut and banana notes blended for instant pull and sustained feeding response in pressured waters.', 'mad-baits'),
		'compulsive-angler' => __('Compulsive Angler editions tuned for standout presentation, confidence and decisive takes.', 'mad-baits'),
	);
}

/**
 * Heading text for range landing archives.
 *
 * @param string $range_label Display range label.
 * @return string
 */
function mad_baits_get_range_landing_heading($range_label) {
	$range_label = trim((string) $range_label);
	if ('' === $range_label) {
		return __('Shop the Range', 'mad-baits');
	}

	return sprintf(
		/* translators: %s: range name */
		__('Shop the %s Range', 'mad-baits'),
		$range_label
	);
}

/**
 * Map display slug to WooCommerce product_tag slug.
 *
 * @param string $slug Display or canonical slug.
 * @return string
 */
function mad_baits_resolve_range_tag_slug($slug) {
	$slug = sanitize_title((string) $slug);

	$aliases = array(
		'p-fish'         => 'p-fish-2',
		'pfish'          => 'p-fish-2',
		'wicked-whites'  => 'wicked-white',
		'wickedwhite'    => 'wicked-white',
	);

	if (isset($aliases[ $slug ])) {
		return $aliases[ $slug ];
	}

	return $slug;
}

/**
 * Whether a slug is a known signature range tag.
 *
 * @param string $slug Tag slug.
 * @return bool
 */
function mad_baits_is_signature_range_tag($slug) {
	$slug = mad_baits_resolve_range_tag_slug($slug);
	$catalog = mad_baits_get_range_tag_catalog();

	return isset($catalog[ $slug ]);
}

/**
 * Bait-type section keys hidden from signature range / boilie range product grids.
 *
 * @return string[]
 */
function mad_baits_get_range_hub_excluded_section_keys() {
	$keys = array('bundles-deals');

	/**
	 * @param string[] $keys Section keys to exclude from range hubs.
	 */
	return array_values(array_unique(array_map('sanitize_key', (array) apply_filters('mad_baits_range_hub_excluded_section_keys', $keys))));
}

/**
 * Product categories that must not appear on range hub grids.
 *
 * @return string[]
 */
function mad_baits_get_range_hub_excluded_category_slugs() {
	$slugs = array(
		'bundles-deals',
		'bundle-deals',
		'bundles',
		'deals',
		'offers',
		'bundle-offers',
		'accessories',
		'accessories-2',
		'accesories',
		'clothing',
		'merchandise',
		'merch',
		'extras',
		'rock-salt',
		'rocksalt',
	);

	/**
	 * @param string[] $slugs Category slugs excluded from range hubs.
	 */
	return array_values(array_unique(array_map('sanitize_title', (array) apply_filters('mad_baits_range_hub_excluded_category_slugs', $slugs))));
}

/**
 * Whether a product should be omitted from boilie/range landing grids.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_is_range_hub_excluded_product($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return true;
	}

	if (function_exists('mad_baits_resolve_product_range_section_key')) {
		$section_key = sanitize_key((string) mad_baits_resolve_product_range_section_key($product_id));
		if ('' !== $section_key && in_array($section_key, mad_baits_get_range_hub_excluded_section_keys(), true)) {
			return true;
		}
	}

	if (taxonomy_exists('product_cat')) {
		$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		if (! is_wp_error($cat_slugs) && is_array($cat_slugs) && ! empty($cat_slugs)) {
			$cat_slugs = array_map('sanitize_title', array_map('strval', $cat_slugs));
			if (! empty(array_intersect($cat_slugs, mad_baits_get_range_hub_excluded_category_slugs()))) {
				return true;
			}
		}
	}

	$title = strtolower((string) get_the_title($product_id));
	$name_needles = array('sticker', 'stickers', 'merch', 'merchandise', 'bundle deal', 'session pack', 'ultimate trip', '50kg');
	foreach ($name_needles as $needle) {
		if ('' !== $needle && false !== strpos($title, $needle)) {
			return true;
		}
	}

	/**
	 * @param bool $excluded   Whether the product is excluded.
	 * @param int  $product_id Product ID.
	 */
	return (bool) apply_filters('mad_baits_is_range_hub_excluded_product', false, $product_id);
}

/**
 * Remove non-bait products from range hub ID lists.
 *
 * @param int[] $product_ids Candidate product IDs.
 * @return int[]
 */
function mad_baits_filter_range_hub_product_ids(array $product_ids) {
	$filtered = array();

	foreach ($product_ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || mad_baits_is_range_hub_excluded_product($product_id)) {
			continue;
		}
		$filtered[] = $product_id;
	}

	return array_values(array_unique($filtered));
}

/**
 * Product-type sections shown on range tag archives.
 *
 * @return array<string, array<string, mixed>>
 */
function mad_baits_get_range_product_sections_config() {
	$type_map = function_exists('mad_baits_get_range_product_type_map')
		? mad_baits_get_range_product_type_map()
		: array();

	$sections = array(
		'boilies' => array(
			'key'       => 'boilies',
			'label'     => isset($type_map['boilies']['label']) ? (string) $type_map['boilies']['label'] : __('Boilies', 'mad-baits'),
			'intro'     => __('Shelf-life and freezer boilies for this range.', 'mad-baits'),
			'empty_msg' => __('No boilies in this range yet.', 'mad-baits'),
		),
		'hookbaits' => array(
			'key'       => 'hookbaits',
			'label'     => isset($type_map['hookbaits']['label']) ? (string) $type_map['hookbaits']['label'] : __('Hookbaits', 'mad-baits'),
			'intro'     => __('Hookbaits to match the range profile.', 'mad-baits'),
			'empty_msg' => __('No hookbaits in this range yet.', 'mad-baits'),
		),
		'hardened-hookbaits' => array(
			'key'       => 'hardened-hookbaits',
			'label'     => __('Hardened Hookbaits', 'mad-baits'),
			'intro'     => __('Hardened and Skinz hookbaits for this range.', 'mad-baits'),
			'empty_msg' => __('No hardened hookbaits in this range yet.', 'mad-baits'),
		),
		'pop-ups' => array(
			'key'       => 'pop-ups',
			'label'     => isset($type_map['pop-ups']['label']) ? (string) $type_map['pop-ups']['label'] : __('Pop Ups', 'mad-baits'),
			'intro'     => '',
			'empty_msg' => __('No pop ups in this range yet.', 'mad-baits'),
		),
		'wafters' => array(
			'key'       => 'wafters',
			'label'     => isset($type_map['wafters']['label']) ? (string) $type_map['wafters']['label'] : __('Wafters', 'mad-baits'),
			'intro'     => '',
			'empty_msg' => __('No wafters in this range yet.', 'mad-baits'),
		),
		'liquids' => array(
			'key'       => 'liquids',
			'label'     => isset($type_map['liquids']['label']) ? (string) $type_map['liquids']['label'] : __('Liquids', 'mad-baits'),
			'intro'     => __('Food dips, glugs and liquid additives.', 'mad-baits'),
			'empty_msg' => __('No liquids in this range yet.', 'mad-baits'),
		),
		'pellets' => array(
			'key'       => 'pellets',
			'label'     => isset($type_map['pellets']['label']) ? (string) $type_map['pellets']['label'] : __('Pellets', 'mad-baits'),
			'intro'     => __('Matching feed and floating pellets.', 'mad-baits'),
			'empty_msg' => __('No pellets in this range yet.', 'mad-baits'),
		),
		'bag-mix' => array(
			'key'       => 'bag-mix',
			'label'     => isset($type_map['bag-mix']['label']) ? (string) $type_map['bag-mix']['label'] : __('Groundbait & Bag Mix', 'mad-baits'),
			'intro'     => __('Groundbait and bag mixes for session baiting.', 'mad-baits'),
			'empty_msg' => __('No groundbait or bag mix in this range yet.', 'mad-baits'),
		),
		'paste' => array(
			'key'       => 'paste',
			'label'     => isset($type_map['paste']['label']) ? (string) $type_map['paste']['label'] : __('Paste', 'mad-baits'),
			'intro'     => '',
			'empty_msg' => __('No paste products in this range yet.', 'mad-baits'),
		),
		'sprays' => array(
			'key'       => 'sprays',
			'label'     => isset($type_map['sprays']['label']) ? (string) $type_map['sprays']['label'] : __('Sprays', 'mad-baits'),
			'intro'     => '',
			'empty_msg' => __('No sprays in this range yet.', 'mad-baits'),
		),
		'bundles-deals' => array(
			'key'       => 'bundles-deals',
			'label'     => isset($type_map['bundles-deals']['label']) ? (string) $type_map['bundles-deals']['label'] : __('Bundles & Deals', 'mad-baits'),
			'intro'     => __('Session-ready bundles and deals.', 'mad-baits'),
			'empty_msg' => __('No bundle deals in this range yet.', 'mad-baits'),
		),
	);

	/**
	 * @param array<string, array<string, mixed>> $sections
	 */
	return apply_filters('mad_baits_range_product_sections', $sections);
}

/**
 * Default section order (type keys).
 *
 * @return string[]
 */
function mad_baits_get_range_section_order_keys() {
	$default = array(
		'boilies',
		'pop-ups',
		'hardened-hookbaits',
		'wafters',
		'hookbaits',
		'pellets',
		'paste',
		'liquids',
		'bag-mix',
		'sprays',
		'bundles-deals',
	);

	$saved = get_option('mad_baits_range_section_order', array());
	if (is_array($saved) && ! empty($saved)) {
		$default = array_values(array_unique(array_map('sanitize_key', $saved)));
	}

	$config_keys = array_keys(mad_baits_get_range_product_sections_config());
	$ordered     = array_values(array_intersect($default, $config_keys));
	$remaining   = array_values(array_diff($config_keys, $ordered));

	return array_merge($ordered, $remaining);
}

/**
 * Resolve which section key a product belongs in (single assignment).
 *
 * @param int $product_id Product ID.
 * @return string Section key or empty string.
 */
function mad_baits_resolve_product_range_section_key($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	if (function_exists('mbpo_resolve_product_section_key')) {
		$resolved = mbpo_resolve_product_section_key($product_id);
	} elseif (function_exists('mad_baits_resolve_product_section_from_categories')) {
		$resolved = mad_baits_resolve_product_section_from_categories($product_id);
	} else {
		return '';
	}

	$resolved = is_string($resolved) ? sanitize_key($resolved) : '';

	if ('' === $resolved) {
		$resolved = mad_baits_resolve_product_grid_type_from_name($product_id);
	}

	if ('' === $resolved) {
		$resolved = mad_baits_resolve_product_grid_type_from_tags_fallback($product_id);
	}

	if ('' === $resolved) {
		return '';
	}

	$order = mad_baits_get_range_section_order_keys();
	if (in_array($resolved, $order, true)) {
		return $resolved;
	}

	return $resolved;
}

/**
 * Resolve range section from WooCommerce product categories only (not tags).
 *
 * @param int $product_id Product ID.
 * @return string Section key or empty string.
 */
function mad_baits_resolve_product_section_from_categories($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
	if (is_wp_error($cat_slugs) || ! is_array($cat_slugs) || empty($cat_slugs)) {
		return '';
	}

	$cat_slugs = array_values(array_unique(array_map('sanitize_title', array_map('strval', $cat_slugs))));
	$matched   = array();
	$type_map  = function_exists('mad_baits_get_range_product_type_map') ? mad_baits_get_range_product_type_map() : array();

	foreach ($cat_slugs as $cat_slug) {
		if (in_array($cat_slug, array('hardened-hookbaits', 'hardened-hookbait', 'skinz-hardened'), true)) {
			$matched[] = 'hardened-hookbaits';
			continue;
		}
		foreach ($type_map as $section_key => $conf) {
			$map_cats = isset($conf['cat_slugs']) ? array_map('sanitize_title', (array) $conf['cat_slugs']) : array();
			if (in_array($cat_slug, $map_cats, true)) {
				$matched[] = sanitize_key((string) $section_key);
				break;
			}
		}
	}

	$matched = array_values(array_unique($matched));
	if (empty($matched)) {
		return '';
	}

	foreach (mad_baits_get_range_section_order_keys() as $section_key) {
		if (in_array($section_key, $matched, true)) {
			return $section_key;
		}
	}

	return $matched[0];
}

/**
 * Name-based product type detection (fallback after categories).
 *
 * @param int $product_id Product ID.
 * @return string Section key or empty string.
 */
function mad_baits_resolve_product_grid_type_from_name($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	$name = strtolower((string) get_the_title($product_id));
	if ('' === $name) {
		return '';
	}

	$rules = array(
		'bundles-deals'      => array('bundle', 'session pack', 'session-pack', 'ultimate trip', '50kg', ' deal', 'deals'),
		'hardened-hookbaits' => array('hardened hookbait', 'hardened-hookbait', 'skinz hardened', 'hard hooker'),
		'pop-ups'            => array('pop up', 'pop-up', 'popup', 'pop-ups', 'pop ups'),
		'wafters'            => array('wafter', 'wafters'),
		'boilies'            => array('boilie', 'boilies', 'shelf life', 'freezer bait', 'freezer'),
		'pellets'            => array('pellet', 'pellets'),
		'paste'              => array('paste'),
		'liquids'            => array('liquid', ' food dip', 'dip', 'glug', 'soak', 'liquid food', 'syrup'),
		'hookbaits'          => array('hookbait', 'hook bait', 'hookbaits'),
	);

	foreach ($rules as $section_key => $needles) {
		foreach ($needles as $needle) {
			if ('' !== $needle && false !== strpos($name, $needle)) {
				return sanitize_key((string) $section_key);
			}
		}
	}

	return '';
}

/**
 * Tag-based product type fallback (never use range signature tags).
 *
 * @param int $product_id Product ID.
 * @return string Section key or empty string.
 */
function mad_baits_resolve_product_grid_type_from_tags_fallback($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	$tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
	if (is_wp_error($tags) || ! is_array($tags) || empty($tags)) {
		return '';
	}

	$tags       = array_map('sanitize_title', array_map('strval', $tags));
	$range_tags = array_keys(mad_baits_get_range_tag_catalog());
	$type_keys  = array_keys(mad_baits_get_range_product_sections_config());

	foreach ($tags as $tag) {
		if (in_array($tag, $range_tags, true)) {
			continue;
		}
		if (in_array($tag, $type_keys, true)) {
			return sanitize_key($tag);
		}
	}

	return '';
}

/**
 * Map internal section key to grid filter / data-product-type value.
 *
 * @param string $section_key Section key.
 * @return string
 */
function mad_baits_map_section_key_to_grid_type($section_key) {
	$section_key = sanitize_key((string) $section_key);
	$map         = array(
		'bundles-deals' => 'bundles',
	);

	return isset($map[ $section_key ]) ? (string) $map[ $section_key ] : $section_key;
}

/**
 * Product grid type attribute for a product.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_get_product_grid_type_data_attr($product_id) {
	$section_key = mad_baits_resolve_product_range_section_key($product_id);

	return '' !== $section_key ? mad_baits_map_section_key_to_grid_type($section_key) : '';
}

/**
 * Filter chip definitions for range hub pages (display order).
 *
 * @return array<string, string> grid type => label
 */
function mad_baits_get_range_hub_filter_chip_definitions() {
	return array(
		'all'                => __('All', 'mad-baits'),
		'boilies'            => __('Boilies', 'mad-baits'),
		'hookbaits'          => __('Hookbaits', 'mad-baits'),
		'pop-ups'            => __('Pop Ups', 'mad-baits'),
		'wafters'            => __('Wafters', 'mad-baits'),
		'hardened-hookbaits' => __('Hardened Hookbaits', 'mad-baits'),
		'liquids'            => __('Liquids', 'mad-baits'),
		'pellets'            => __('Pellets', 'mad-baits'),
		'paste'              => __('Paste', 'mad-baits'),
		'bag-mix'            => __('Groundbait & Bag Mix', 'mad-baits'),
		'sprays'             => __('Sprays', 'mad-baits'),
	);
}

/**
 * Build unified range hub product rows (preserves organiser menu order).
 *
 * @param string $range_slug Range tag slug.
 * @return array<int, array{id: int, section: string, grid_type: string}>
 */
function mad_baits_get_range_hub_product_rows($range_slug) {
	$rows        = array();
	$product_ids = mad_baits_get_range_tag_product_ids($range_slug);

	foreach ($product_ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || ! wc_get_product($product_id)) {
			continue;
		}

		$section_key = mad_baits_resolve_product_range_section_key($product_id);
		$grid_type   = '' !== $section_key ? mad_baits_map_section_key_to_grid_type($section_key) : '';

		$rows[] = array(
			'id'         => $product_id,
			'section'    => $section_key,
			'grid_type'  => $grid_type,
		);
	}

	return $rows;
}

/**
 * Filter chips that have at least one product in this range.
 *
 * @param array<int, array{id: int, section: string, grid_type: string}> $rows Product rows.
 * @return array<string, string>
 */
function mad_baits_get_range_hub_active_filter_chips(array $rows) {
	$definitions = mad_baits_get_range_hub_filter_chip_definitions();
	$present     = array('all' => true);

	foreach ($rows as $row) {
		$type = isset($row['grid_type']) ? sanitize_key((string) $row['grid_type']) : '';
		if ('' !== $type) {
			$present[ $type ] = true;
		}
	}

	$chips = array();
	if (! empty($rows)) {
		$chips['all'] = $definitions['all'];
	}

	foreach ($definitions as $type => $label) {
		if ('all' === $type) {
			continue;
		}
		if (! empty($present[ $type ])) {
			$chips[ $type ] = $label;
		}
	}

	return $chips;
}

/**
 * Enqueue range hub assets on signature product_tag archives.
 *
 * @return void
 */
function mad_baits_enqueue_range_hub_assets() {
	if (! function_exists('is_product_tag') || ! is_product_tag()) {
		return;
	}

	$term = get_queried_object();
	if (! $term instanceof WP_Term || ! mad_baits_is_signature_range_tag((string) $term->slug)) {
		return;
	}

	$css_path = get_theme_file_path('assets/css/scoped/range-hub.css');
	$js_path  = get_theme_file_path('assets/js/range-hub.js');

	if (file_exists($css_path)) {
		wp_enqueue_style(
			'mad-baits-range-hub',
			get_theme_file_uri('assets/css/scoped/range-hub.css'),
			array('mad-baits-main'),
			(string) filemtime($css_path)
		);
	}

	if (file_exists($js_path)) {
		wp_enqueue_script(
			'mad-baits-range-hub',
			get_theme_file_uri('assets/js/range-hub.js'),
			array(),
			(string) filemtime($js_path),
			true
		);
	}
}
add_action('wp_enqueue_scripts', 'mad_baits_enqueue_range_hub_assets', 25);

/**
 * Render mid-grid CTA strip on range hubs.
 *
 * @return void
 */
function mad_baits_render_range_hub_mid_cta() {
	$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : home_url('/shop/');
	$build_url  = function_exists('mad_baits_get_build_my_session_page_url') ? mad_baits_get_build_my_session_page_url() : home_url('/build-my-session/');
	$ai_url     = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');
	?>
	<div class="mad-range-hub-grid__cta-break" data-range-cta-break>
		<div class="mad-range-hub-grid__cta-inner">
			<p class="mad-range-hub-grid__cta-kicker"><?php esc_html_e('Session ready', 'mad-baits'); ?></p>
			<h3 class="mad-range-hub-grid__cta-title"><?php esc_html_e('Build a complete approach for this range', 'mad-baits'); ?></h3>
			<div class="mad-range-hub-grid__cta-actions">
				<a class="mad-button mad-button--small" href="<?php echo esc_url($build_url); ?>"><?php esc_html_e('Build My Session', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost mad-button--small" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('View Bundles & Deals', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost mad-button--small" href="<?php echo esc_url($ai_url); ?>"><?php esc_html_e('Ask AI Bait Finder', 'mad-baits'); ?></a>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Unified premium product grid for signature range tag pages.
 *
 * @param string $range_slug                 Range slug.
 * @param string $range_label                Range display name.
 * @param bool   $enable_compact_variations  Card variation mode.
 * @return void
 */
function mad_baits_render_range_unified_hub($range_slug, $range_label, $enable_compact_variations = false) {
	$rows  = mad_baits_get_range_hub_product_rows($range_slug);
	$chips = mad_baits_get_range_hub_active_filter_chips($rows);
	$count = count($rows);

	$catalog    = mad_baits_get_range_tag_catalog();
	$range_slug = mad_baits_resolve_range_tag_slug($range_slug);
	$badge      = function_exists('mad_baits_get_range_display_label')
		? mad_baits_get_range_display_label($range_slug, $range_label)
		: (isset($catalog[ $range_slug ]) ? (string) $catalog[ $range_slug ] : $range_label);
	$range_copy = mad_baits_get_range_landing_intro_map();
	$intro_text = isset($range_copy[ $range_slug ]) ? (string) $range_copy[ $range_slug ] : '';
	?>
	<section class="mad-range-hub section" id="mad-range-hub-products" data-mad-range-hub data-range-slug="<?php echo esc_attr($range_slug); ?>">
		<div class="container mad-range-hub__inner">
			<header class="mad-range-hub__head">
				<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
					<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs mad-range-hub__breadcrumbs'); ?>
				<?php endif; ?>
				<p class="mad-range-hub__badge"><?php echo esc_html($badge); ?></p>
				<h1 class="mad-range-hub__title"><?php echo esc_html(mad_baits_get_range_landing_heading((string) $range_label)); ?></h1>
				<p class="mad-range-hub__intro">
					<?php echo esc_html('' !== $intro_text ? $intro_text : sprintf(__('Browse every %s product in one place — filter by bait type and add to your session in seconds.', 'mad-baits'), $range_label)); ?>
				</p>
			</header>

			<?php if (! empty($chips) && $count > 0) : ?>
				<div class="mad-range-hub__filters-wrap">
					<nav class="mad-range-hub__filters" aria-label="<?php esc_attr_e('Filter by product type', 'mad-baits'); ?>" data-range-hub-filters>
						<?php foreach ($chips as $chip_key => $chip_label) : ?>
							<button
								type="button"
								class="mad-range-hub__chip<?php echo 'all' === $chip_key ? ' is-active' : ''; ?>"
								data-range-filter="<?php echo esc_attr((string) $chip_key); ?>"
							>
								<?php echo esc_html((string) $chip_label); ?>
							</button>
						<?php endforeach; ?>
					</nav>
				</div>
			<?php endif; ?>

			<?php if (0 === $count) : ?>
				<p class="mad-range-hub__empty"><?php esc_html_e('No products found for this range yet. Tag products with this range and assign bait categories to populate the shop.', 'mad-baits'); ?></p>
			<?php else : ?>
				<div class="mad-range-hub__grid product-grid" data-range-hub-grid>
					<?php
					$index = 0;
					foreach ($rows as $row) :
						if (4 === $index && $count > 6) {
							mad_baits_render_range_hub_mid_cta();
						}
						$product_id = (int) $row['id'];
						$grid_type  = isset($row['grid_type']) ? (string) $row['grid_type'] : '';
						if (function_exists('mad_baits_render_product_card')) {
							mad_baits_render_product_card($product_id, (bool) $enable_compact_variations, 'range-hub', $grid_type);
						}
						++$index;
					endforeach;
					?>
				</div>
				<p class="mad-range-hub__filter-empty" data-range-hub-empty hidden>
					<?php esc_html_e('No products found in this section yet.', 'mad-baits'); ?>
				</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Base menu_order for a bait-type section (keeps sections from colliding).
 *
 * @param string $section_key Section key.
 * @return int
 */
function mad_baits_get_range_section_menu_order_base($section_key) {
	$bases = array(
		'boilies'       => 0,
		'pop-ups'       => 1000,
		'wafters'       => 2000,
		'hardened-hookbaits' => 2800,
		'hookbaits'     => 3000,
		'liquids'       => 4000,
		'pellets'       => 5000,
		'bag-mix'       => 6000,
		'paste'         => 7000,
		'sprays'        => 8000,
		'bundles-deals' => 9000,
	);

	$section_key = sanitize_key((string) $section_key);

	return isset($bases[ $section_key ]) ? (int) $bases[ $section_key ] : 50000;
}

/**
 * menu_order value for a product position inside a section.
 *
 * @param string $section_key Section key.
 * @param int    $position    Zero-based position in the section list.
 * @return int
 */
function mad_baits_compute_range_section_menu_order($section_key, $position) {
	return mad_baits_get_range_section_menu_order_base($section_key) + (absint($position) * 10);
}

/**
 * Published products in a range tag + bait-type section, sorted for display.
 *
 * @param string $range_slug  Range tag slug.
 * @param string $section_key Section key.
 * @return int[]
 */
function mad_baits_get_range_section_product_ids($range_slug, $section_key) {
	$section_key = sanitize_key((string) $section_key);
	if ('' === $section_key) {
		return array();
	}

	$product_ids = mad_baits_get_range_tag_product_ids($range_slug);
	$filtered    = array();

	foreach ($product_ids as $product_id) {
		if (mad_baits_resolve_product_range_section_key($product_id) === $section_key) {
			$filtered[] = $product_id;
		}
	}

	usort(
		$filtered,
		static function ($a, $b) {
			$order_a = (int) get_post_field('menu_order', $a);
			$order_b = (int) get_post_field('menu_order', $b);
			if ($order_a === $order_b) {
				return strcasecmp((string) get_the_title($a), (string) get_the_title($b));
			}
			return $order_a <=> $order_b;
		}
	);

	return $filtered;
}

/**
 * Persist drag-and-drop order for all products in a range tag.
 *
 * @param string $range_slug  Range tag slug.
 * @param int[]  $ordered_ids Product IDs in desired order.
 * @return int Number of products updated.
 */
function mad_baits_save_range_product_order($range_slug, array $ordered_ids) {
	if (class_exists('MBPO_Order')) {
		return MBPO_Order::save_order($range_slug, $ordered_ids);
	}

	$range_slug  = mad_baits_resolve_range_tag_slug($range_slug);
	$ordered_ids = array_values(array_unique(array_filter(array_map('absint', $ordered_ids))));

	if ('' === $range_slug || empty($ordered_ids)) {
		return 0;
	}

	$allowed = array_flip(mad_baits_get_range_tag_product_ids($range_slug));
	$updated = 0;

	foreach ($ordered_ids as $position => $product_id) {
		if (! isset($allowed[ $product_id ])) {
			continue;
		}

		$result = wp_update_post(
			array(
				'ID'         => $product_id,
				'menu_order' => absint($position) * 10,
			),
			true
		);

		if (! is_wp_error($result)) {
			++$updated;
		}
	}

	return $updated;
}

/**
 * Persist drag-and-drop order for a range section.
 *
 * @param string $range_slug      Range tag slug.
 * @param string $section_key     Section key.
 * @param int[]  $ordered_ids     Product IDs in desired order.
 * @return int Number of products updated.
 */
function mad_baits_save_range_section_product_order($range_slug, $section_key, array $ordered_ids) {
	$section_key = sanitize_key((string) $section_key);
	$range_slug  = mad_baits_resolve_range_tag_slug($range_slug);
	$ordered_ids = array_values(array_unique(array_filter(array_map('absint', $ordered_ids))));

	if ('' === $section_key || '' === $range_slug || empty($ordered_ids)) {
		return 0;
	}

	$allowed = mad_baits_get_range_section_product_ids($range_slug, $section_key);
	$allowed = array_flip($allowed);
	$updated = 0;

	foreach ($ordered_ids as $position => $product_id) {
		if (! isset($allowed[ $product_id ])) {
			continue;
		}

		$new_order = mad_baits_compute_range_section_menu_order($section_key, (int) $position);
		$result    = wp_update_post(
			array(
				'ID'         => $product_id,
				'menu_order' => $new_order,
			),
			true
		);

		if (! is_wp_error($result)) {
			++$updated;
		}
	}

	return $updated;
}

/**
 * Canonical signature range tag slugs used to scope range pages.
 *
 * @return string[]
 */
function mad_baits_get_signature_range_tag_slugs() {
	$slugs = array_keys(mad_baits_get_range_tag_catalog());
	$extra = array('nutz-banana', 'p-fish', 'wicked-whites');

	return array_values(
		array_unique(
			array_filter(
				array_map(
					static function ($slug) {
						return mad_baits_resolve_range_tag_slug((string) $slug);
					},
					array_merge($slugs, $extra)
				)
			)
		)
	);
}

/**
 * Signature range tags assigned to a product (normalized slugs).
 *
 * @param int $product_id Product ID.
 * @return string[]
 */
function mad_baits_get_product_signature_range_tags($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return array();
	}

	$tag_slugs = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
	if (is_wp_error($tag_slugs) || ! is_array($tag_slugs) || empty($tag_slugs)) {
		return array();
	}

	$signature_slugs = mad_baits_get_signature_range_tag_slugs();
	$matched         = array();

	foreach ($tag_slugs as $tag_slug) {
		$normalized = mad_baits_resolve_range_tag_slug((string) $tag_slug);
		if ('' === $normalized) {
			continue;
		}
		if (in_array($normalized, $signature_slugs, true)) {
			$matched[] = $normalized;
		}
	}

	return array_values(array_unique($matched));
}

/**
 * Cross-range bundle/deal products shown on every signature range hub.
 *
 * Includes bundles-deals products with no range tag (e.g. 50kg Ultimate Trip Package).
 * Products tagged for a different range only are excluded.
 *
 * @param string $range_slug Range slug.
 * @return int[]
 */
function mad_baits_get_range_shared_bundle_product_ids($range_slug) {
	$range_slug = mad_baits_resolve_range_tag_slug($range_slug);
	if ('' === $range_slug || ! mad_baits_is_signature_range_tag($range_slug) || ! function_exists('wc_get_products')) {
		return array();
	}

	$query_args = array(
		'status'   => 'publish',
		'limit'    => -1,
		'orderby'  => 'menu_order title',
		'order'    => 'ASC',
		'return'   => 'ids',
		'category' => array('bundles-deals', 'bundle-deals', 'bundles', 'deals'),
	);

	if (is_admin() && function_exists('mad_baits_supplier_wc_get_product_ids')) {
		$candidate_ids = mad_baits_supplier_wc_get_product_ids($query_args);
	} else {
		$candidate_ids = wc_get_products($query_args);
	}

	$shared = array();
	foreach ((array) $candidate_ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || ! wc_get_product($product_id)) {
			continue;
		}

		$section_key = mad_baits_resolve_product_range_section_key($product_id);
		if ('bundles-deals' !== $section_key) {
			continue;
		}

		$range_tags = mad_baits_get_product_signature_range_tags($product_id);
		if (! empty($range_tags)) {
			continue;
		}

		$shared[] = $product_id;
	}

	return array_values(array_unique($shared));
}

/**
 * Product IDs for a range tag.
 *
 * @param string $range_slug Range tag slug.
 * @return int[]
 */
function mad_baits_get_range_tag_product_ids($range_slug) {
	$range_slug = mad_baits_resolve_range_tag_slug($range_slug);
	if ('' === $range_slug) {
		return array();
	}

	if (function_exists('mbpo_get_range_product_ids')) {
		$product_ids = mbpo_get_range_product_ids($range_slug);
	} elseif (function_exists('wc_get_products')) {
		$query_args = array(
			'status'  => 'publish',
			'limit'   => -1,
			'orderby' => 'menu_order title',
			'order'   => 'ASC',
			'tag'     => array($range_slug),
		);

		if (is_admin() && function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$product_ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$product_ids          = wc_get_products($query_args);
		}
	} else {
		$product_ids = array();
	}

	$product_ids = array_values(array_filter(array_map('absint', (array) $product_ids)));
	$product_ids = mad_baits_filter_range_hub_product_ids($product_ids);

	if (! empty($product_ids) && class_exists('MBPO_Order')) {
		$product_ids = MBPO_Order::sort_ids_by_saved_order($range_slug, $product_ids);
	}

	return $product_ids;
}

/**
 * Products for one range tag, grouped by bait-type sections.
 *
 * @param string $range_slug    Range tag slug.
 * @param string $section_filter Optional section key filter.
 * @return array<int, array{key: string, label: string, intro: string, empty_msg: string, products: WC_Product[]}>
 */
function mad_baits_get_range_products_grouped($range_slug, $section_filter = '') {
	$product_ids     = mad_baits_get_range_tag_product_ids($range_slug);
	$sections_config = mad_baits_get_range_product_sections_config();
	$section_order   = mad_baits_get_range_section_order_keys();
	$section_filter  = sanitize_key((string) $section_filter);

	$grouped = array();
	foreach ($section_order as $section_key) {
		if (! isset($sections_config[ $section_key ])) {
			continue;
		}
		if ('' !== $section_filter && $section_filter !== $section_key) {
			continue;
		}
		$conf = $sections_config[ $section_key ];
		$grouped[ $section_key ] = array(
			'key'       => $section_key,
			'label'     => (string) ($conf['label'] ?? ucfirst($section_key)),
			'intro'     => (string) ($conf['intro'] ?? ''),
			'empty_msg' => (string) ($conf['empty_msg'] ?? ''),
			'products'  => array(),
		);
	}

	foreach ($product_ids as $product_id) {
		$section_key = mad_baits_resolve_product_range_section_key($product_id);
		if ('' === $section_key || ! isset($grouped[ $section_key ])) {
			continue;
		}
		$product = wc_get_product($product_id);
		if ($product) {
			$grouped[ $section_key ]['products'][] = $product;
		}
	}

	$out = array();
	foreach ($section_order as $section_key) {
		if (! isset($grouped[ $section_key ]) || empty($grouped[ $section_key ]['products'])) {
			continue;
		}
		$out[] = $grouped[ $section_key ];
	}

	return $out;
}

/**
 * Current range tag slug on range archive or filtered shop.
 *
 * @return string
 */
function mad_baits_get_current_range_tag_slug() {
	if (is_product_tag()) {
		$term = get_queried_object();
		if ($term instanceof WP_Term) {
			return sanitize_title((string) $term->slug);
		}
	}

	if (isset($_GET['product_tag'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return mad_baits_resolve_range_tag_slug(sanitize_title(wp_unslash((string) $_GET['product_tag'])));
	}

	if (isset($_GET['filter_range'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return mad_baits_resolve_range_tag_slug(sanitize_title(wp_unslash((string) $_GET['filter_range'])));
	}

	return '';
}

/**
 * Shop URL for a range tag (prefers product_tag archive).
 *
 * @param string $range_slug Range slug.
 * @return string
 */
function mad_baits_get_range_tag_shop_url($range_slug) {
	$range_slug = mad_baits_resolve_range_tag_slug($range_slug);
	$term       = get_term_by('slug', $range_slug, 'product_tag');

	if ($term instanceof WP_Term && ! is_wp_error($term)) {
		$link = get_term_link($term);
		if (! is_wp_error($link) && is_string($link)) {
			return $link;
		}
	}

	$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');

	return add_query_arg(
		array('product_tag' => $range_slug),
		$shop_url
	);
}

/**
 * Render grouped range products (shared markup with category range hubs).
 *
 * @param array<int, array<string, mixed>> $grouped_sections Grouped sections.
 * @param bool                             $enable_compact_variations Card variation mode.
 */
function mad_baits_render_range_products_grouped(array $grouped_sections, $enable_compact_variations = false) {
	if (empty($grouped_sections)) {
		echo '<p class="section__empty-state">' . esc_html__('No products found for this range yet. Tag products with this range and assign bait categories to populate the shop.', 'mad-baits') . '</p>';
		return;
	}
	?>
	<div class="mad-range-product-groups">
		<?php foreach ($grouped_sections as $section) : ?>
			<?php
			$section_key = isset($section['key']) ? sanitize_key((string) $section['key']) : '';
			$products    = isset($section['products']) ? (array) $section['products'] : array();
			if ('' === $section_key || empty($products)) {
				continue;
			}
			?>
			<section class="mad-range-product-group" id="range-type-<?php echo esc_attr($section_key); ?>">
				<header class="mad-range-product-group__header">
					<h2 class="mad-range-product-group__title"><?php echo esc_html((string) ($section['label'] ?? ucfirst($section_key))); ?></h2>
					<?php if (! empty($section['intro'])) : ?>
						<p class="mad-range-product-group__intro"><?php echo esc_html((string) $section['intro']); ?></p>
					<?php endif; ?>
				</header>
				<div class="product-grid">
					<?php foreach ($products as $product) : ?>
						<?php
						if (! $product instanceof WC_Product) {
							continue;
						}
						if (function_exists('mad_baits_render_product_card')) {
							mad_baits_render_product_card($product->get_id(), (bool) $enable_compact_variations);
						}
						?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * On shop, treat filter_range as product_tag filter.
 *
 * @param WP_Query $query Query.
 */
function mad_baits_shop_filter_by_range_tag($query) {
	if (is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query()) {
		return;
	}
	if (! function_exists('is_shop') || ! is_shop()) {
		return;
	}
	if (! isset($_GET['filter_range'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$range = mad_baits_resolve_range_tag_slug(wp_unslash((string) $_GET['filter_range']));
	if ('' === $range) {
		return;
	}

	$tax_query = $query->get('tax_query');
	if (! is_array($tax_query)) {
		$tax_query = array();
	}

	$tax_query[] = array(
		'taxonomy' => 'product_tag',
		'field'    => 'slug',
		'terms'    => array($range),
	);

	$query->set('tax_query', $tax_query);
}
add_action('pre_get_posts', 'mad_baits_shop_filter_by_range_tag', 20);

if (is_admin() && ! class_exists('MBPO_Admin', false)) {
	require_once get_theme_file_path('/inc/range-product-order-admin.php');
}

/**
 * Send shop ?product_tag=range queries to the product_tag archive for signature ranges.
 */
function mad_baits_redirect_shop_product_tag_to_range_archive() {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return;
	}
	if (! function_exists('is_shop') || ! is_shop()) {
		return;
	}
	if (! isset($_GET['product_tag'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$slug = mad_baits_resolve_range_tag_slug(wp_unslash((string) $_GET['product_tag']));
	if ('' === $slug || ! mad_baits_is_signature_range_tag($slug)) {
		return;
	}

	$target = mad_baits_get_range_tag_shop_url($slug);
	if (! is_string($target) || '' === $target || false !== strpos($target, 'product_tag=')) {
		return;
	}

	wp_safe_redirect($target, 301);
	exit;
}
add_action('template_redirect', 'mad_baits_redirect_shop_product_tag_to_range_archive', 5);
