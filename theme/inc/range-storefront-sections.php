<?php
/**
 * Storefront sections for scheduled bulk deals and Compulsive specials.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Public bulk-deal products for one range.
 *
 * @param string $range_slug stp or swan-mussel.
 * @return WC_Product[]
 */
function mad_baits_get_public_range_bulk_deals($range_slug) {
	$range_slug = sanitize_title((string) $range_slug);
	if ('' === $range_slug || ! function_exists('wc_get_products')) {
		return array();
	}
	if ('swan-mussel' === $range_slug && ! mad_baits_range_is_storefront_visible('swan-mussel')) {
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 12,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_key'               => MAD_BAITS_DEAL_RANGE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'             => $range_slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'                => 'menu_order title',
			'order'                  => 'ASC',
		)
	);

	$products = array();
	foreach (mad_baits_filter_public_product_ids((array) $ids) as $product_id) {
		$product = wc_get_product($product_id);
		if ($product instanceof WC_Product) {
			$products[] = $product;
		}
	}

	return $products;
}

/**
 * Render one range's bulk deals. Empty when nothing is public yet.
 *
 * @param string $range_slug Range slug.
 * @param string $context    Optional context key.
 * @return void
 */
function mad_baits_render_range_bulk_deals($range_slug, $context = '') {
	$range_slug = sanitize_title((string) $range_slug);
	$products   = mad_baits_get_public_range_bulk_deals($range_slug);
	if (empty($products)) {
		return;
	}

	$headings = array(
		'stp'          => array(
			'kicker' => __('STP bulk deal', 'mad-baits'),
			'title'  => __('STP 10kg and 20kg', 'mad-baits'),
			'intro'  => __('A bulk deal for STP shelf life boilies only. Choose 15mm or 18mm on the deal.', 'mad-baits'),
		),
		'swan-mussel' => array(
			'kicker' => __('Swan Mussel', 'mad-baits'),
			'title'  => __('Swan Mussel deals', 'mad-baits'),
			'intro'  => __('Swan Mussel offers are listed on their own and are not part of the STP bulk deal.', 'mad-baits'),
		),
	);
	$heading = isset($headings[ $range_slug ]) ? $headings[ $range_slug ] : array(
		'kicker' => __('Range deal', 'mad-baits'),
		'title'  => __('Bulk deal', 'mad-baits'),
		'intro'  => '',
	);
	$embedded = in_array((string) $context, array('product', 'boilie-range'), true);
	$tag      = $embedded ? 'div' : 'section';
	?>
	<<?php echo esc_attr($tag); ?> class="<?php echo esc_attr($embedded ? 'mad-range-deals mad-range-deals--embedded' : 'section mad-range-deals'); ?> mad-range-deals--<?php echo esc_attr($range_slug); ?>">
		<div class="<?php echo esc_attr($embedded ? 'mad-range-deals__inner' : 'container'); ?>">
			<div class="section__heading">
				<div>
					<p class="section__kicker"><?php echo esc_html((string) $heading['kicker']); ?></p>
					<h2><?php echo esc_html((string) $heading['title']); ?></h2>
					<?php if ('' !== (string) $heading['intro']) : ?>
						<p><?php echo esc_html((string) $heading['intro']); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="product-grid mad-range-deals__grid">
				<?php foreach ($products as $product) : ?>
					<?php
					if (function_exists('mad_baits_render_product_card')) {
						mad_baits_render_product_card($product->get_id(), true);
					}
					?>
				<?php endforeach; ?>
			</div>
		</div>
	</<?php echo esc_html($tag); ?>>
	<?php
}

/**
 * Bundle page: STP and Swan Mussel stay in separate blocks.
 *
 * @return void
 */
function mad_baits_render_split_range_deal_groups() {
	$groups = array('stp');
	if (mad_baits_range_is_storefront_visible('swan-mussel')) {
		$groups[] = 'swan-mussel';
	}

	$visible = array();
	foreach ($groups as $range_slug) {
		$products = mad_baits_get_public_range_bulk_deals($range_slug);
		if (! empty($products)) {
			$visible[ $range_slug ] = $products;
		}
	}
	if (empty($visible)) {
		return;
	}
	?>
	<section class="section mad-range-deal-groups">
		<div class="container">
			<div class="section__heading">
				<div>
					<p class="section__kicker"><?php esc_html_e('Range bulk deals', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Separate Range Offers', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('Each range deal stands on its own. STP and Swan Mussel are not combined.', 'mad-baits'); ?></p>
				</div>
			</div>
			<div class="mad-range-deal-groups__list">
				<?php foreach ($visible as $range_slug => $products) : ?>
					<article class="mad-range-deal-groups__group mad-range-deal-groups__group--<?php echo esc_attr((string) $range_slug); ?>">
						<header class="mad-range-deal-groups__header">
							<h3><?php echo 'stp' === $range_slug ? esc_html__('STP', 'mad-baits') : esc_html__('Swan Mussel', 'mad-baits'); ?></h3>
						</header>
						<div class="product-grid mad-range-deals__grid">
							<?php foreach ($products as $product) : ?>
								<?php
								if ($product instanceof WC_Product && function_exists('mad_baits_render_product_card')) {
									mad_baits_render_product_card($product->get_id(), true);
								}
								?>
							<?php endforeach; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Compulsive products currently tagged as specials.
 *
 * @param int $limit Max products.
 * @return int[]
 */
function mad_baits_get_compulsive_special_product_ids($limit = 8) {
	$limit = max(1, absint($limit));
	if (! taxonomy_exists('product_tag')) {
		return array();
	}

	$tag = get_term_by('slug', MAD_BAITS_COMPULSIVE_SPECIAL_TAG, 'product_tag');
	if (! $tag instanceof WP_Term) {
		return array();
	}

	$range_query = mad_baits_compulsive_range_tax_query();
	if (empty($range_query)) {
		return array();
	}

	$query_args = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => max($limit, 24),
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => array((int) $tag->term_id),
			),
			$range_query,
		),
	);

	$ids = mad_baits_filter_public_product_ids((array) get_posts($query_args));
	$hide_oos = mad_baits_compulsive_specials_hide_out_of_stock();
	$matched  = array();
	foreach ($ids as $product_id) {
		$product_id = (int) $product_id;
		$terms      = mad_baits_compulsive_membership_terms($product_id);
		$in_stock   = true;
		if ($hide_oos && function_exists('wc_get_product')) {
			$product  = wc_get_product($product_id);
			$in_stock = $product instanceof WC_Product && $product->is_in_stock();
		}
		if (! mad_baits_compulsive_special_qualifies($terms['tags'], $terms['cats'], $terms['ranges'], $in_stock, $hide_oos)) {
			continue;
		}
		$matched[] = $product_id;
		if (count($matched) >= $limit) {
			break;
		}
	}

	return $matched;
}

/**
 * Taxonomy clause that requires Compulsive range membership.
 *
 * @return array<string, mixed>
 */
function mad_baits_compulsive_range_tax_query() {
	$clauses = array('relation' => 'OR');
	$tag_slugs = array('compulsive-angler', 'compulsive');
	$cat_slugs = array('compulsive', 'compulsive-angler', 'compulsive-anglers', 'boilies-compulsive', 'boilies-compulsive-angler', 'boilies-compulsive-anglers');
	$range_slugs = array('compulsive', 'compulsive-angler', 'compulsive-anglers');

	if (taxonomy_exists('product_tag')) {
		$clauses[] = array(
			'taxonomy' => 'product_tag',
			'field'    => 'slug',
			'terms'    => $tag_slugs,
		);
	}
	if (taxonomy_exists('product_cat')) {
		$clauses[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $cat_slugs,
		);
	}
	if (taxonomy_exists('pa_range')) {
		$clauses[] = array(
			'taxonomy' => 'pa_range',
			'field'    => 'slug',
			'terms'    => $range_slugs,
		);
	}

	return count($clauses) > 1 ? $clauses : array();
}

/**
 * Tag, category and range slugs used to confirm Compulsive membership.
 *
 * @param int $product_id Product ID.
 * @return array{tags: string[], cats: string[], ranges: string[]}
 */
function mad_baits_compulsive_membership_terms($product_id) {
	$product_id = absint($product_id);
	$parent_id  = (int) wp_get_post_parent_id($product_id);
	if ($parent_id > 0) {
		$product_id = $parent_id;
	}

	$slugs = static function ($taxonomy) use ($product_id) {
		if (! taxonomy_exists($taxonomy)) {
			return array();
		}
		$terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'slugs'));

		return is_array($terms) ? array_map('strval', $terms) : array();
	};

	return array(
		'tags'   => $slugs('product_tag'),
		'cats'   => $slugs('product_cat'),
		'ranges' => $slugs('pa_range'),
	);
}

/**
 * Dedicated Compulsive Specials block. Renders nothing when no specials are live.
 *
 * @return void
 */
function mad_baits_render_compulsive_specials_section() {
	$product_ids = mad_baits_get_compulsive_special_product_ids(8);
	if (empty($product_ids)) {
		return;
	}

	$shop_url = function_exists('mad_baits_get_compulsive_angler_url') ? mad_baits_get_compulsive_angler_url() : home_url('/shop/');
	?>
	<section class="section mad-compulsive-specials">
		<div class="container">
			<div class="section__heading section__heading--with-actions">
				<div>
					<p class="section__kicker"><?php esc_html_e('Compulsive Angler', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Compulsive Specials', 'mad-baits'); ?></h2>
					<p><?php esc_html_e('Limited runs and special products from the Compulsive range. Available while stock lasts.', 'mad-baits'); ?></p>
				</div>
				<a class="text-link" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Compulsive Angler', 'mad-baits'); ?></a>
			</div>
			<div class="product-grid mad-compulsive-specials__grid">
				<?php foreach ($product_ids as $product_id) : ?>
					<?php
					if (function_exists('mad_baits_render_product_card')) {
						mad_baits_render_product_card((int) $product_id, true);
					}
					?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Show STP bulk deals on STP product pages once they are public.
 *
 * @return void
 */
function mad_baits_render_stp_deals_on_product() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	$product_id = (int) get_queried_object_id();
	if ($product_id < 1) {
		return;
	}

	$range = sanitize_title((string) get_post_meta($product_id, MAD_BAITS_RANGE_SLUG_META, true));
	$deal  = sanitize_title((string) get_post_meta($product_id, MAD_BAITS_DEAL_RANGE_META, true));
	if ('stp' !== $range && 'stp' !== $deal) {
		$tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
		$tags = is_array($tags) ? array_map('sanitize_title', $tags) : array();
		if (! in_array('stp', $tags, true)) {
			return;
		}
	}

	if ('stp' === $deal) {
		return;
	}

	mad_baits_render_range_bulk_deals('stp', 'product');
}
add_action('woocommerce_after_single_product_summary', 'mad_baits_render_stp_deals_on_product', 18);
