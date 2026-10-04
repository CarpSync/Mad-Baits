<?php
/**
 * Single-product conversion helpers (pairing + sticky add-to-cart).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Return canonical core bait range slugs.
 *
 * @return string[]
 */
function mad_baits_get_core_range_slugs() {
	$catalog = function_exists('mad_baits_get_range_tag_catalog')
		? array_keys(mad_baits_get_range_tag_catalog())
		: array();

	if (! empty($catalog)) {
		return array_values(array_unique(array_map('sanitize_title', $catalog)));
	}

	return array(
		'asbo',
		'calamino',
		'compulsive-angler',
		'nutz-plus',
		'nutz-banana',
		'pandemic',
		'p-fish-2',
		'wicked-white',
		'stp',
	);
}

/**
 * Tag slugs that should match a canonical range (for queries).
 *
 * @param string $range_slug Canonical range slug.
 * @return string[]
 */
function mad_baits_get_range_tag_search_slugs($range_slug) {
	$range_slug = function_exists('mad_baits_resolve_range_tag_slug')
		? mad_baits_resolve_range_tag_slug($range_slug)
		: sanitize_title((string) $range_slug);

	if ('' === $range_slug) {
		return array();
	}

	$aliases = array(
		'asbo'              => array('asbo'),
		'calamino'          => array('calamino'),
		'stp'               => array('stp'),
		'swan-mussel'       => array('swan-mussel', 'swan'),
		'compulsive-angler' => array('compulsive-angler', 'compulsive'),
		'nutz-plus'         => array('nutz-plus', 'nutzplus', 'nutz-plus-2'),
		'nutz-banana'       => array('nutz-banana', 'nutzbanana', 'banana'),
		'pandemic'          => array('pandemic'),
		'p-fish-2'          => array('p-fish-2', 'p-fish', 'pfish'),
		'wicked-white'      => array('wicked-white', 'wicked-whites', 'wickedwhite'),
	);

	if (class_exists('MBPO_Ranges') && method_exists('MBPO_Ranges', 'get_range_aliases')) {
		$mbpo = MBPO_Ranges::get_range_aliases();
		if (isset($mbpo[ $range_slug ]) && is_array($mbpo[ $range_slug ])) {
			$aliases[ $range_slug ] = array_values(array_unique(array_map('sanitize_title', $mbpo[ $range_slug ])));
		}
	}

	$slugs = isset($aliases[ $range_slug ]) ? $aliases[ $range_slug ] : array($range_slug);

	return array_values(array_unique(array_filter(array_map('sanitize_title', $slugs))));
}

/**
 * Resolve the signature range slug for a product (tags, attributes, title).
 *
 * @param int $product_id Product ID.
 * @return string Canonical range slug or empty string.
 */
function mad_baits_resolve_product_range_slug($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	$core_ranges = mad_baits_get_core_range_slugs();
	$tag_slugs   = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
	$tag_slugs   = is_array($tag_slugs) && ! is_wp_error($tag_slugs) ? array_map('sanitize_title', $tag_slugs) : array();

	foreach ($core_ranges as $canonical) {
		$needles = mad_baits_get_range_tag_search_slugs((string) $canonical);
		if (array_intersect($needles, $tag_slugs)) {
			return sanitize_title((string) $canonical);
		}
	}

	if (taxonomy_exists('pa_range')) {
		$attr_slugs = wp_get_post_terms($product_id, 'pa_range', array('fields' => 'slugs'));
		$attr_slugs = is_array($attr_slugs) && ! is_wp_error($attr_slugs) ? array_map('sanitize_title', $attr_slugs) : array();
		foreach ($core_ranges as $canonical) {
			$needles = mad_baits_get_range_tag_search_slugs((string) $canonical);
			if (array_intersect($needles, $attr_slugs)) {
				return sanitize_title((string) $canonical);
			}
		}
	}

	$title = strtolower((string) get_the_title($product_id));
	if ('' === $title) {
		return '';
	}

	$catalog = function_exists('mad_baits_get_range_tag_catalog') ? mad_baits_get_range_tag_catalog() : array();
	foreach ($catalog as $slug => $label) {
		$slug  = sanitize_title((string) $slug);
		$label = strtolower((string) $label);
		if ('' !== $slug && (false !== strpos($title, $slug) || ( '' !== $label && false !== strpos($title, $label)))) {
			return $slug;
		}
	}

	return '';
}

/**
 * Resolve "Pair It With" candidate product IDs for a single product.
 *
 * @param int $product_id Product ID.
 * @param int $limit      Max IDs.
 * @return int[]
 */
function mad_baits_get_pair_it_with_product_ids($product_id, $limit = 4) {
	$product_id = absint($product_id);
	$limit      = max(1, absint($limit));
	if ($product_id < 1 || ! function_exists('wc_get_products')) {
		return array();
	}

	$product = wc_get_product($product_id);
	if (! is_object($product) || ! is_a($product, 'WC_Product')) {
		return array();
	}

	$range_slug = function_exists('mad_baits_resolve_product_range_slug')
		? mad_baits_resolve_product_range_slug($product_id)
		: '';

	$matched_ranges = '' !== $range_slug
		? mad_baits_get_range_tag_search_slugs($range_slug)
		: array();

	$product_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
	$product_tags = is_array($product_tags) && ! is_wp_error($product_tags) ? array_map('sanitize_title', $product_tags) : array();

	$matching_topics = array_values(
		array_intersect(
			$product_tags,
			array(
				'hookbait',
				'hookbaits',
				'liquid',
				'liquids',
				'bundle',
				'bundles',
				'extras',
				'session-pack',
			)
		)
	);

	$range_topic_query = array('relation' => 'OR');
	if (! empty($matched_ranges)) {
		$range_topic_query[] = array(
			'taxonomy' => 'product_tag',
			'field'    => 'slug',
			'terms'    => $matched_ranges,
			'operator' => 'IN',
		);
		if (taxonomy_exists('pa_range')) {
			$range_topic_query[] = array(
				'taxonomy' => 'pa_range',
				'field'    => 'slug',
				'terms'    => $matched_ranges,
				'operator' => 'IN',
			);
		}
	}
	if (! empty($matching_topics)) {
		$range_topic_query[] = array(
			'taxonomy' => 'product_tag',
			'field'    => 'slug',
			'terms'    => $matching_topics,
			'operator' => 'IN',
		);
	}
	if (1 === count($range_topic_query)) {
		return array();
	}

	$allowed_product_types = array('hookbaits', 'liquids', 'pellets', 'boilies', 'bundles-deals', 'bundle-deals');
	$tax_query = array(
		'relation' => 'AND',
		$range_topic_query,
		array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $allowed_product_types,
			'operator' => 'IN',
		),
	);

	$query = new WP_Query(
		array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => max($limit * 2, 8),
			'post__not_in'        => array($product_id),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'fields'              => 'ids',
		)
	);

	$ids = array();
	if ($query->have_posts()) {
		$ids = array_map('absint', (array) $query->posts);
	}
	wp_reset_postdata();

	if (count($ids) < $limit) {
		$fallback_ids = wc_get_products(
			array(
				'status'       => 'publish',
				'limit'        => $limit,
				'return'       => 'ids',
				'featured'     => true,
				'exclude'      => array_merge(array($product_id), $ids),
				'stock_status' => 'instock',
			)
		);
		$ids = array_merge($ids, array_map('absint', (array) $fallback_ids));
	}

	return array_slice(array_values(array_unique(array_filter($ids))), 0, $limit);
}

/**
 * Legacy hook — PDP output is handled by mad_baits_render_complete_your_session_section().
 *
 * @deprecated 2.0.0
 * @return void
 */
function mad_baits_render_pair_it_with_section() {
	if (function_exists('mad_baits_render_complete_your_session_section')) {
		mad_baits_render_complete_your_session_section();
	}
}

/**
 * Render sticky add-to-cart bar markup for single products.
 */
function mad_baits_render_mobile_sticky_add_to_cart() {
	if (! function_exists('is_product') || ! is_product() || is_cart() || is_checkout() || is_account_page()) {
		return;
	}

	global $product;
	if (! is_object($product) || ! is_a($product, 'WC_Product')) {
		return;
	}

	if (class_exists('MBBB_Plugin', false) && MBBB_Plugin::instance()->is_enabled($product->get_id())) {
		return;
	}

	$is_variable = $product->is_type('variable');
	$price_html  = (string) $product->get_price_html();
	?>
	<div class="mad-mobile-sticky-atc" data-mobile-sticky-atc data-product-type="<?php echo esc_attr($is_variable ? 'variable' : 'simple'); ?>" aria-live="polite">
		<div class="mad-mobile-sticky-atc__content">
			<div class="mad-mobile-sticky-atc__meta">
				<p class="mad-mobile-sticky-atc__title"><?php echo esc_html((string) $product->get_name()); ?></p>
				<p class="mad-mobile-sticky-atc__price"><?php echo wp_kses_post($price_html); ?></p>
				<p class="mad-mobile-sticky-atc__variation" data-sticky-variation-status><?php esc_html_e('Ready to add', 'mad-baits'); ?></p>
			</div>
			<div class="mad-mobile-sticky-atc__actions">
				<div class="mad-mobile-sticky-atc__quantity" aria-label="<?php esc_attr_e('Quantity', 'mad-baits'); ?>">
					<button type="button" class="mad-mobile-sticky-atc__qty-btn" data-sticky-qty-decrease aria-label="<?php esc_attr_e('Decrease quantity', 'mad-baits'); ?>">−</button>
					<input class="mad-mobile-sticky-atc__qty-input" data-sticky-qty type="number" min="1" step="1" value="1" inputmode="numeric" />
					<button type="button" class="mad-mobile-sticky-atc__qty-btn" data-sticky-qty-increase aria-label="<?php esc_attr_e('Increase quantity', 'mad-baits'); ?>">+</button>
				</div>
				<button type="button" class="mad-button mad-button--small" data-sticky-add-to-cart><?php esc_html_e('Add To Basket', 'mad-baits'); ?></button>
			</div>
		</div>
		<p class="mad-mobile-sticky-atc__message" data-sticky-atc-message></p>
	</div>
	<?php
}
add_action('woocommerce_after_single_product', 'mad_baits_render_mobile_sticky_add_to_cart', 30);

/**
 * Product type groups shown on signature range hub category pages.
 *
 * @return array<string, array<string, mixed>>
 */
function mad_baits_get_range_product_type_map() {
	return array(
		'boilies'   => array(
			'label'        => __('Boilies', 'mad-baits'),
			'cat_slugs'    => array('boilies', 'boilie', 'freezer-baits', 'freezer', 'shelf-life', 'shelf-life-boilies'),
			'tag_slugs'    => array('boilie', 'boilies', 'freezer', 'shelf-life'),
			'name_needles' => array('boilie', 'boilies', 'freezer bait', 'shelf life'),
		),
		'hookbaits' => array(
			'label'        => __('Hookbaits', 'mad-baits'),
			'cat_slugs'    => array('hookbaits', 'hookbait', 'hook-baits', 'compulsive-hookbaits'),
			'tag_slugs'    => array(),
			'name_needles' => array(),
		),
		'liquids'   => array(
			'label'        => __('Liquid', 'mad-baits'),
			'cat_slugs'    => array('liquids', 'liquid-foods', 'other-liquid-foods', 'dips', 'liquid-additives', 'liquid'),
			'tag_slugs'    => array('liquid', 'liquids', 'dip', 'dips', 'food-liquid', 'glug'),
			'name_needles' => array('liquid', 'glug', 'dip', 'syrup', 'oil'),
		),
		'pellets'   => array(
			'label'        => __('Pellet', 'mad-baits'),
			'cat_slugs'    => array('pellets', 'pellet', 'feed', 'particle'),
			'tag_slugs'    => array('pellet', 'pellets', 'feed', 'particle'),
			'name_needles' => array('pellet', 'feed'),
		),
		'bag-mix'   => array(
			'label'        => __('Bag Mix', 'mad-baits'),
			'cat_slugs'    => array('bag-mix', 'bag-mixes', 'base-mix', 'base-mixes', 'groundbait', 'groundbaits', 'groundbait-bag-mix'),
			'tag_slugs'    => array('bag-mix', 'base-mix', 'groundbait'),
			'name_needles' => array('bag mix', 'base mix', 'groundbait', 'stick mix'),
		),
		'pop-ups'   => array(
			'label'        => __('Pop Ups', 'mad-baits'),
			'cat_slugs'    => array('pop-ups', 'popups', 'pop-up'),
			'tag_slugs'    => array('pop-up', 'popup', 'pop-ups'),
			'name_needles' => array('pop up', 'pop-up', 'popup'),
		),
		'wafters'   => array(
			'label'        => __('Wafters', 'mad-baits'),
			'cat_slugs'    => array('wafters', 'skinz-wafters', 'wafter'),
			'tag_slugs'    => array('wafter', 'wafters', 'skinz'),
			'name_needles' => array('wafter', 'skinz'),
		),
		'paste'     => array(
			'label'        => __('Paste', 'mad-baits'),
			'cat_slugs'    => array('paste'),
			'tag_slugs'    => array('paste'),
			'name_needles' => array('paste'),
		),
		'sprays'    => array(
			'label'        => __('Sprays', 'mad-baits'),
			'cat_slugs'    => array('sprays', 'spray'),
			'tag_slugs'    => array('spray', 'sprays'),
			'name_needles' => array('spray'),
		),
		'bundles-deals' => array(
			'label'        => __('Bundles & Deals', 'mad-baits'),
			'cat_slugs'    => array('bundles-deals', 'bundle-deals', 'deals', 'bundles'),
			'tag_slugs'    => array('bundle', 'bundles', 'deal', 'deals'),
			'name_needles' => array('bundle', 'deal', 'session pack'),
		),
	);
}

/**
 * Filter tabs for range hubs (includes "all").
 *
 * @return array<string, array<string, mixed>>
 */
function mad_baits_get_range_product_type_filters() {
	$filters = array(
		'all' => array(
			'label' => __('All', 'mad-baits'),
		),
	);

	return array_merge($filters, mad_baits_get_range_product_type_map());
}

/**
 * Resolve which range product type a product belongs to.
 *
 * @param int $product_id Product ID.
 * @return string Type key or empty string.
 */
function mad_baits_resolve_product_range_type($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	if (function_exists('mbpo_resolve_product_section_key')) {
		return mbpo_resolve_product_section_key($product_id);
	}

	if (function_exists('mad_baits_resolve_product_section_from_categories')) {
		return mad_baits_resolve_product_section_from_categories($product_id);
	}

	return '';
}

/**
 * Build tax_query for a range hub product query.
 *
 * @param int    $term_id   Range category term ID.
 * @param string $type_key  Range type key or "all".
 * @return array
 */
function mad_baits_build_range_hub_tax_query($term_id, $type_key = 'all') {
	$term_id  = absint($term_id);
	$type_key = sanitize_key((string) $type_key);

	$tax_query = array(
		'relation' => 'AND',
		array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => array($term_id),
		),
	);

	if ('all' === $type_key || '' === $type_key) {
		return $tax_query;
	}

	$type_map = mad_baits_get_range_product_type_map();
	if (! isset($type_map[ $type_key ])) {
		return $tax_query;
	}

	$type_conf = (array) $type_map[ $type_key ];
	$type_or   = array('relation' => 'OR');

	foreach (array('product_cat', 'product_tag') as $taxonomy) {
		$slug_key = 'product_cat' === $taxonomy ? 'cat_slugs' : 'tag_slugs';
		$slugs    = isset($type_conf[ $slug_key ]) ? (array) $type_conf[ $slug_key ] : array();
		$term_ids = array();

		foreach ($slugs as $slug) {
			$slug = sanitize_title((string) $slug);
			if ('' === $slug || ! taxonomy_exists($taxonomy)) {
				continue;
			}
			$term = get_term_by('slug', $slug, $taxonomy);
			if ($term instanceof WP_Term) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		$term_ids = array_values(array_unique(array_filter(array_map('absint', $term_ids))));
		if (! empty($term_ids)) {
			$type_or[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			);
		}
	}

	if (count($type_or) > 1) {
		$tax_query[] = $type_or;
	}

	return $tax_query;
}

/**
 * Query products for a range hub.
 *
 * @param int    $term_id   Range category term ID.
 * @param string $type_key  Range type key or "all".
 * @param int    $paged     Page number.
 * @param int    $per_page  Products per page (-1 for all).
 * @return WP_Query
 */
function mad_baits_query_range_hub_products($term_id, $type_key = 'all', $paged = 1, $per_page = 24) {
	return new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $per_page,
			'paged'          => max(1, absint($paged)),
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'tax_query'      => mad_baits_build_range_hub_tax_query($term_id, $type_key), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		)
	);
}

/**
 * Group range hub products by type in display order.
 *
 * @param int $term_id Range category term ID.
 * @return array<string, int[]>
 */
function mad_baits_get_range_hub_products_grouped($term_id) {
	$term_id = absint($term_id);
	$type_map = mad_baits_get_range_product_type_map();
	$grouped  = array();

	foreach (array_keys($type_map) as $type_key) {
		$grouped[ (string) $type_key ] = array();
	}

	$query = mad_baits_query_range_hub_products($term_id, 'all', 1, -1);
	if (! $query->have_posts()) {
		return $grouped;
	}

	while ($query->have_posts()) {
		$query->the_post();
		$product_id = (int) get_the_ID();
		$type_key   = mad_baits_resolve_product_range_type($product_id);

		if ('' === $type_key || ! isset($grouped[ $type_key ])) {
			continue;
		}

		$grouped[ $type_key ][] = $product_id;
	}
	wp_reset_postdata();

	return $grouped;
}
