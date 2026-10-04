<?php
/**
 * Idempotent WooCommerce products for STP and Swan Mussel shelf-life boilies.
 *
 * Reuses existing Size, Weight / Volume, Bait Format and Range attributes.
 * Prices and images already saved in WooCommerce are left untouched.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

const MAD_BAITS_RANGE_PRODUCTS_VERSION = '2026-10-04.2';

/**
 * Create or refresh STP, Swan Mussel and Compulsive Special catalogue data.
 *
 * @return void
 */
function mad_baits_maybe_provision_range_products() {
	if (! function_exists('wc_get_product')) {
		return;
	}

	$signature = md5(
		MAD_BAITS_RANGE_PRODUCTS_VERSION . '|' . mad_baits_get_stp_bulk_public_from() . '|' . mad_baits_get_swan_mussel_mode()
	);
	if ((string) get_option('mad_baits_range_products_sig', '') === $signature) {
		return;
	}

	mad_baits_provision_range_products();
	update_option('mad_baits_range_products_sig', $signature, false);
	delete_option('mad_baits_visibility_sig');
}
add_action('init', 'mad_baits_maybe_provision_range_products', 25);

/**
 * @return void
 */
function mad_baits_provision_range_products() {
	if (! function_exists('wc_get_product') || ! function_exists('mad_baits_catalogue_run_structure_setup')) {
		return;
	}

	mad_baits_catalogue_run_structure_setup();
	mad_baits_ensure_product_tag('Compulsive Special', MAD_BAITS_COMPULSIVE_SPECIAL_TAG);

	$boilies_id = mad_baits_ensure_product_category('Boilies', 'boilies', 0);
	$bundles_id = mad_baits_ensure_product_category('Bundles & Deals', 'bundles-deals', 0);
	$stp_cat    = mad_baits_ensure_product_category('STP', 'boilies-stp', $boilies_id);
	$swan_cat   = mad_baits_ensure_product_category('Swan Mussel', 'boilies-swan-mussel', $boilies_id);

	$stp_tag  = mad_baits_ensure_product_tag('STP', 'stp');
	$swan_tag = mad_baits_ensure_product_tag('Swan Mussel', 'swan-mussel');
	$shelf_tag = mad_baits_ensure_product_tag('Shelf Life', 'shelf-life');

	mad_baits_ensure_attribute_term('pa_range', 'STP');
	mad_baits_ensure_attribute_term('pa_range', 'Swan Mussel', 'swan-mussel');
	mad_baits_ensure_attribute_term('pa_size', '15mm');
	mad_baits_ensure_attribute_term('pa_size', '18mm');
	mad_baits_ensure_attribute_term('pa_weight-volume', '1kg');
	mad_baits_ensure_attribute_term('pa_weight-volume', '10kg');
	mad_baits_ensure_attribute_term('pa_weight-volume', '20kg');
	mad_baits_ensure_attribute_term('pa_bait-format', 'Shelf Life', 'shelf-life');

	mad_baits_upsert_shelf_life_boilie(
		array(
			'sku'           => 'MB-STP-SL',
			'name'          => 'STP Shelf Life Boilies',
			'range_slug'    => 'stp',
			'range_label'   => 'STP',
			'weight'        => '1kg',
			'status'        => 'publish',
			'visibility'    => 'visible',
			'public_from'   => '',
			'deal_range'    => '',
			'category_ids'  => array_filter(array($boilies_id, $stp_cat)),
			'tag_ids'       => array_filter(array($stp_tag, $shelf_tag)),
			'short'         => 'STP shelf life boilies in 1kg bags. Choose 15mm or 18mm.',
			'description'   => 'STP shelf life boilies. Pick a size to add a 1kg bag to your order. Product photography, range story and confirmed pricing still need to be added in WooCommerce.',
			'variation_sku' => 'MB-STP-SL',
		)
	);

	$stp_launch = mad_baits_get_stp_bulk_public_from();
	foreach (array('10kg' => 'MB-STP-10KG', '20kg' => 'MB-STP-20KG') as $weight => $sku) {
		mad_baits_upsert_shelf_life_boilie(
			array(
				'sku'           => $sku,
				'name'          => sprintf('STP %s Bulk Deal', $weight),
				'range_slug'    => 'stp',
				'range_label'   => 'STP',
				'weight'        => $weight,
				'status'        => 'draft',
				'visibility'    => 'hidden',
				'public_from'   => $stp_launch,
				'deal_range'    => 'stp',
				'follow_option' => true,
				'category_ids'  => array_filter(array($boilies_id, $bundles_id, $stp_cat)),
				'tag_ids'       => array_filter(array($stp_tag, $shelf_tag)),
				'short'         => sprintf('STP only. %s of shelf life boilies. Choose 15mm or 18mm. This deal does not include Swan Mussel.', $weight),
				'description'   => sprintf('Bulk deal for the STP shelf life boilie range only. %s total, in the size you choose. It is scheduled separately from Swan Mussel and stays hidden until the STP launch date.', $weight),
				'variation_sku' => $sku,
			)
		);
	}

	mad_baits_upsert_shelf_life_boilie(
		array(
			'sku'           => 'MB-SWAN-SL',
			'name'          => 'Swan Mussel Shelf Life Boilies',
			'range_slug'    => 'swan-mussel',
			'range_label'   => 'Swan Mussel',
			'weight'        => '1kg',
			'status'        => 'draft',
			'visibility'    => 'hidden',
			'public_from'   => '',
			'deal_range'    => '',
			'category_ids'  => array_filter(array($boilies_id, $swan_cat)),
			'tag_ids'       => array_filter(array($swan_tag, $shelf_tag)),
			'short'         => 'Swan Mussel shelf life boilies in 1kg bags. Choose 15mm or 18mm. Not on sale until the range is launched.',
			'description'   => 'Swan Mussel shelf life boilies, prepared as a normal Mad Baits variable boilie. The range stays off the storefront until it is switched live for 2027. Product photography, range story and confirmed pricing still need to be added in WooCommerce.',
			'variation_sku' => 'MB-SWAN-SL',
		)
	);
}

/**
 * @param string $name Name.
 * @param string $slug Slug.
 * @param int    $parent Parent term ID.
 * @return int
 */
function mad_baits_ensure_product_category($name, $slug, $parent) {
	return mad_baits_ensure_term('product_cat', $name, $slug, $parent);
}

/**
 * @param string $name Name.
 * @param string $slug Slug.
 * @return int
 */
function mad_baits_ensure_product_tag($name, $slug) {
	return mad_baits_ensure_term('product_tag', $name, $slug, 0);
}

/**
 * @param string $taxonomy Taxonomy.
 * @param string $name     Name.
 * @param string $slug     Slug.
 * @param int    $parent   Parent.
 * @return int
 */
function mad_baits_ensure_term($taxonomy, $name, $slug, $parent = 0) {
	if (! taxonomy_exists($taxonomy)) {
		return 0;
	}

	$slug = sanitize_title($slug);
	$term = get_term_by('slug', $slug, $taxonomy);
	if ($term instanceof WP_Term) {
		return (int) $term->term_id;
	}

	$args = array('slug' => $slug);
	if ($parent > 0) {
		$args['parent'] = $parent;
	}
	$result = wp_insert_term($name, $taxonomy, $args);
	if (is_wp_error($result)) {
		$term = get_term_by('slug', $slug, $taxonomy);
		return $term instanceof WP_Term ? (int) $term->term_id : 0;
	}

	return isset($result['term_id']) ? (int) $result['term_id'] : 0;
}

/**
 * @param string $taxonomy Attribute taxonomy, including pa_.
 * @param string $name     Term name.
 * @param string $slug     Optional slug.
 * @return int
 */
function mad_baits_ensure_attribute_term($taxonomy, $name, $slug = '') {
	if (! taxonomy_exists($taxonomy)) {
		return 0;
	}
	$slug = '' !== $slug ? sanitize_title($slug) : sanitize_title($name);

	return mad_baits_ensure_term($taxonomy, $name, $slug, 0);
}

/**
 * Create a variable shelf-life boilie with 15mm and 18mm variations.
 *
 * @param array<string, mixed> $config Product config.
 * @return int Product ID.
 */
function mad_baits_upsert_shelf_life_boilie(array $config) {
	$sku = (string) $config['sku'];
	$product_id = (int) wc_get_product_id_by_sku($sku);
	$created = $product_id < 1;
	$product = $product_id > 0 ? wc_get_product($product_id) : null;

	if (! $product instanceof WC_Product) {
		$product = new WC_Product_Variable();
		$product->set_sku($sku);
		$product->set_name((string) $config['name']);
		$product->set_short_description((string) $config['short']);
		$product->set_description((string) $config['description']);
	}

	if ('swan-mussel' === (string) $config['range_slug']) {
		$product->set_status(
			mad_baits_swan_storefront_post_status(
				mad_baits_get_swan_mussel_mode(),
				mad_baits_launch_now(),
				mad_baits_launch_timezone()
			)
		);
		$product->set_catalog_visibility('publish' === $product->get_status() ? 'visible' : 'hidden');
	} elseif ('stp' === (string) $config['deal_range']) {
		$product->set_status('draft');
		$product->set_catalog_visibility('hidden');
	} else {
		$product->set_status((string) $config['status']);
		$product->set_catalog_visibility((string) $config['visibility']);
	}

	if ($created) {
		$product->set_tax_status('taxable');
		$product->set_tax_class('');
		$product->set_virtual(false);
		$product->set_downloadable(false);
	}
	$product->set_reviews_allowed(true);
	$product->set_sold_individually(false);

	$size_15 = mad_baits_ensure_attribute_term('pa_size', '15mm');
	$size_18 = mad_baits_ensure_attribute_term('pa_size', '18mm');
	$weight_id = mad_baits_ensure_attribute_term('pa_weight-volume', (string) $config['weight']);
	$format_id = mad_baits_ensure_attribute_term('pa_bait-format', 'Shelf Life', 'shelf-life');
	$range_id  = mad_baits_ensure_attribute_term('pa_range', (string) $config['range_label'], (string) $config['range_slug']);

	$attributes = array();
	$attributes[] = mad_baits_make_taxonomy_attribute('pa_size', array_filter(array($size_15, $size_18)), true);
	$attributes[] = mad_baits_make_taxonomy_attribute('pa_weight-volume', array_filter(array($weight_id)), false);
	$attributes[] = mad_baits_make_taxonomy_attribute('pa_bait-format', array_filter(array($format_id)), false);
	$attributes[] = mad_baits_make_taxonomy_attribute('pa_range', array_filter(array($range_id)), false);
	$product->set_attributes($attributes);

	$category_ids = array_values(array_unique(array_map('absint', (array) $config['category_ids'])));
	$tag_ids      = array_values(array_unique(array_map('absint', (array) $config['tag_ids'])));
	if ($product_id > 0) {
		$category_ids = array_values(array_unique(array_merge($product->get_category_ids(), $category_ids)));
		$tag_ids      = array_values(array_unique(array_merge($product->get_tag_ids(), $tag_ids)));
	}
	$product->set_category_ids($category_ids);
	$product->set_tag_ids($tag_ids);

	$product_id = (int) $product->save();
	if ($product_id < 1) {
		return 0;
	}

	update_post_meta($product_id, MAD_BAITS_RANGE_SLUG_META, sanitize_title((string) $config['range_slug']));
	if ('' !== (string) $config['deal_range']) {
		update_post_meta($product_id, MAD_BAITS_DEAL_RANGE_META, sanitize_title((string) $config['deal_range']));
	}

	$public_from = trim((string) $config['public_from']);
	$follows     = (string) get_post_meta($product_id, '_mad_baits_launch_follows_option', true);
	if (! empty($config['follow_option']) && 'no' !== $follows) {
		update_post_meta($product_id, MAD_BAITS_RANGE_PUBLIC_FROM_META, $public_from);
		update_post_meta($product_id, '_mad_baits_launch_follows_option', 'yes');
	} elseif ($created && '' !== $public_from) {
		update_post_meta($product_id, MAD_BAITS_RANGE_PUBLIC_FROM_META, $public_from);
	}

	$variation_status = 'publish' === $product->get_status() ? 'publish' : 'draft';
	foreach (array('15mm', '18mm') as $size) {
		mad_baits_upsert_size_variation($product_id, (string) $config['variation_sku'], $size, $variation_status);
	}

	if (class_exists('WC_Product_Variable')) {
		WC_Product_Variable::sync($product_id);
		wc_delete_product_transients($product_id);
	}

	return $product_id;
}

/**
 * @param string $taxonomy Attribute taxonomy.
 * @param int[]  $term_ids Term IDs.
 * @param bool   $variation Used for variations.
 * @return WC_Product_Attribute
 */
function mad_baits_make_taxonomy_attribute($taxonomy, array $term_ids, $variation) {
	$attribute = new WC_Product_Attribute();
	$attribute_id = function_exists('wc_attribute_taxonomy_id_by_name') ? (int) wc_attribute_taxonomy_id_by_name($taxonomy) : 0;
	$attribute->set_id($attribute_id);
	$attribute->set_name($taxonomy);
	$attribute->set_options(array_values(array_filter(array_map('absint', $term_ids))));
	$attribute->set_position(0);
	$attribute->set_visible(true);
	$attribute->set_variation((bool) $variation);

	return $attribute;
}

/**
 * @param int    $parent_id Parent product ID.
 * @param string $sku_base  Parent SKU.
 * @param string $size      Size label, 15mm or 18mm.
 * @param string $status    publish when the parent is public, otherwise draft.
 * @return int
 */
function mad_baits_upsert_size_variation($parent_id, $sku_base, $size, $status = 'publish') {
	$sku = $sku_base . '-' . sanitize_title($size);
	$variation_id = (int) wc_get_product_id_by_sku($sku);
	$variation = $variation_id > 0 ? wc_get_product($variation_id) : null;
	$created = ! $variation instanceof WC_Product_Variation;

	if ($created) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id($parent_id);
		$variation->set_sku($sku);
	}

	$variation->set_attributes(array('pa_size' => sanitize_title($size)));
	$variation->set_status('publish' === $status ? 'publish' : 'draft');
	if ($created) {
		$variation->set_manage_stock(false);
		$variation->set_stock_status('instock');
		$variation->set_tax_status('taxable');
		$variation->set_tax_class('');
		$variation->set_virtual(false);
		$variation->set_downloadable(false);
		$variation->set_regular_price('');
	} elseif ('' === (string) $variation->get_stock_status()) {
		$variation->set_stock_status('instock');
	}
	$variation->save();

	return (int) $variation->get_id();
}
