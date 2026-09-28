<?php
/**
 * Idempotent WooCommerce catalogue structure creation.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Flush attribute taxonomy cache.
 *
 * @return void
 */
function mad_baits_catalogue_setup_flush_attribute_taxonomies() {
	if (function_exists('wc_clear_transient')) {
		delete_transient('wc_attribute_taxonomies');
	}
	if (class_exists('WC_Cache_Helper', false)) {
		WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
	}
	if (function_exists('wc_get_attribute_taxonomies')) {
		wc_get_attribute_taxonomies();
	}
}

/**
 * Build unique child category slug (product_cat slugs are global).
 *
 * @param string $parent_slug Parent slug.
 * @param string $range_slug  Range slug.
 * @return string
 */
function mad_baits_catalogue_child_category_slug($parent_slug, $range_slug) {
	return sanitize_title($parent_slug . '-' . $range_slug);
}

/**
 * Find existing product_cat by slug or exact name under optional parent (no overwrite).
 *
 * @param string $name   Display name.
 * @param string $slug   Slug.
 * @param int    $parent Parent term ID (0 = any).
 * @return WP_Term|null
 */
function mad_baits_catalogue_find_product_cat($name, $slug, $parent = 0) {
	$by_slug = get_term_by('slug', $slug, 'product_cat');
	if ($by_slug instanceof WP_Term) {
		if ($parent <= 0 || (int) $by_slug->parent === $parent) {
			return $by_slug;
		}
	}

	if ($parent > 0) {
		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => false,
			)
		);
		if (! is_wp_error($children)) {
			foreach ($children as $child_term) {
				if ($child_term instanceof WP_Term && $child_term->name === $name) {
					return $child_term;
				}
			}
		}
	}

	$by_name = get_term_by('name', $name, 'product_cat');
	if ($by_name instanceof WP_Term && ($parent <= 0 || (int) $by_name->parent === $parent)) {
		return $by_name;
	}

	return null;
}

/**
 * Find existing product_tag by slug or name.
 *
 * @param string $name Tag name.
 * @param string $slug Slug.
 * @return WP_Term|null
 */
function mad_baits_catalogue_find_product_tag($name, $slug) {
	$by_slug = get_term_by('slug', $slug, 'product_tag');
	if ($by_slug instanceof WP_Term) {
		return $by_slug;
	}
	$by_name = get_term_by('name', $name, 'product_tag');
	if ($by_name instanceof WP_Term) {
		return $by_name;
	}

	return null;
}

/**
 * Insert product category if missing.
 *
 * @param string $name   Name.
 * @param string $slug   Slug.
 * @param int    $parent Parent term ID.
 * @param array  $log    Log reference.
 * @return int Term ID or 0.
 */
function mad_baits_catalogue_maybe_create_product_cat($name, $slug, $parent, &$log) {
	$existing = mad_baits_catalogue_find_product_cat($name, $slug, $parent);
	if ($existing instanceof WP_Term) {
		$log['categories_skipped'][] = sprintf(
			/* translators: 1: category name, 2: term id, 3: slug */
			__('Category exists: %1$s (ID %2$d, slug: %3$s)', 'mad-baits'),
			$existing->name,
			(int) $existing->term_id,
			$existing->slug
		);

		return (int) $existing->term_id;
	}

	$args = array('slug' => $slug);
	if ($parent > 0) {
		$args['parent'] = $parent;
	}

	$result = wp_insert_term($name, 'product_cat', $args);
	if (is_wp_error($result)) {
		$log['errors'][] = sprintf(
			/* translators: 1: category name, 2: error message */
			__('Category failed (%1$s): %2$s', 'mad-baits'),
			$name,
			$result->get_error_message()
		);

		return 0;
	}

	$term_id = isset($result['term_id']) ? (int) $result['term_id'] : 0;
	$log['categories_created'][] = sprintf(
		/* translators: 1: name, 2: id, 3: slug */
		__('%1$s (ID %2$d, slug: %3$s)', 'mad-baits'),
		$name,
		$term_id,
		$slug
	);

	return $term_id;
}

/**
 * Insert product tag if missing.
 *
 * @param string $name Tag name.
 * @param array  $log  Log reference.
 * @return void
 */
function mad_baits_catalogue_maybe_create_product_tag($name, &$log) {
	$slug     = sanitize_title($name);
	$existing = mad_baits_catalogue_find_product_tag($name, $slug);
	if ($existing instanceof WP_Term) {
		$log['tags_skipped'][] = sprintf(
			__('Tag exists: %1$s (ID %2$d)', 'mad-baits'),
			$existing->name,
			(int) $existing->term_id
		);

		return;
	}

	$result = wp_insert_term($name, 'product_tag', array('slug' => $slug));
	if (is_wp_error($result)) {
		$log['errors'][] = sprintf(__('Tag failed (%1$s): %2$s', 'mad-baits'), $name, $result->get_error_message());

		return;
	}

	$term_id = isset($result['term_id']) ? (int) $result['term_id'] : 0;
	$log['tags_created'][] = sprintf(__('%1$s (ID %2$d)', 'mad-baits'), $name, $term_id);
}

/**
 * Find WooCommerce global attribute by slug or label.
 *
 * @param string $label Attribute label.
 * @param string $slug  Attribute slug (without pa_).
 * @return object|null
 */
function mad_baits_catalogue_find_wc_attribute($label, $slug) {
	if (! function_exists('wc_get_attribute_taxonomies')) {
		return null;
	}

	foreach (wc_get_attribute_taxonomies() as $attr) {
		if (! is_object($attr)) {
			continue;
		}
		if (isset($attr->attribute_name) && $attr->attribute_name === $slug) {
			return $attr;
		}
		if (isset($attr->attribute_label) && $attr->attribute_label === $label) {
			return $attr;
		}
	}

	return null;
}

/**
 * Ensure global attribute exists; return taxonomy name e.g. pa_size.
 *
 * @param string $label Attribute label.
 * @param string $slug  Slug without pa_.
 * @param array  $log   Log reference.
 * @return string Taxonomy name or empty.
 */
function mad_baits_catalogue_maybe_create_attribute($label, $slug, &$log) {
	$taxonomy = wc_attribute_taxonomy_name($slug);

	$existing = mad_baits_catalogue_find_wc_attribute($label, $slug);
	if ($existing && isset($existing->attribute_name)) {
		$log['attributes_skipped'][] = sprintf(
			__('Attribute exists: %1$s (slug: %2$s)', 'mad-baits'),
			$label,
			$existing->attribute_name
		);

		return wc_attribute_taxonomy_name($existing->attribute_name);
	}

	if (! function_exists('wc_create_attribute')) {
		$log['errors'][] = __('wc_create_attribute() unavailable — update WooCommerce.', 'mad-baits');

		return '';
	}

	$attribute_id = wc_create_attribute(
		array(
			'name'         => $label,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		)
	);

	if (is_wp_error($attribute_id)) {
		$log['errors'][] = sprintf(__('Attribute failed (%1$s): %2$s', 'mad-baits'), $label, $attribute_id->get_error_message());

		return '';
	}

	$log['attributes_created'][] = sprintf(__('%1$s (ID %2$s, taxonomy: %3$s)', 'mad-baits'), $label, (string) $attribute_id, $taxonomy);

	mad_baits_catalogue_setup_flush_attribute_taxonomies();

	register_taxonomy(
		$taxonomy,
		apply_filters('woocommerce_taxonomy_objects_' . $taxonomy, array('product')),
		apply_filters(
			'woocommerce_taxonomy_args_' . $taxonomy,
			array(
				'labels'       => array('name' => $label),
				'hierarchical' => false,
				'show_ui'      => false,
				'query_var'    => true,
				'rewrite'      => false,
			)
		)
	);

	return $taxonomy;
}

/**
 * Insert attribute term if missing.
 *
 * @param string $taxonomy Taxonomy e.g. pa_size.
 * @param string $term     Term name.
 * @param array  $log      Log reference.
 * @return void
 */
function mad_baits_catalogue_maybe_create_attribute_term($taxonomy, $term, &$log) {
	if (! taxonomy_exists($taxonomy)) {
		$log['errors'][] = sprintf(__('Taxonomy missing for term %1$s: %2$s', 'mad-baits'), $term, $taxonomy);

		return;
	}

	$slug     = sanitize_title($term);
	$existing = get_term_by('slug', $slug, $taxonomy);
	if (! $existing) {
		$existing = get_term_by('name', $term, $taxonomy);
	}

	if ($existing instanceof WP_Term) {
		$log['attribute_terms_skipped'][] = sprintf(
			__('%1$s → %2$s (exists, ID %3$d)', 'mad-baits'),
			$taxonomy,
			$term,
			(int) $existing->term_id
		);

		return;
	}

	$result = wp_insert_term($term, $taxonomy, array('slug' => $slug));
	if (is_wp_error($result)) {
		$log['errors'][] = sprintf(
			__('Attribute term failed (%1$s / %2$s): %3$s', 'mad-baits'),
			$taxonomy,
			$term,
			$result->get_error_message()
		);

		return;
	}

	$term_id = isset($result['term_id']) ? (int) $result['term_id'] : 0;
	$log['attribute_terms_created'][] = sprintf(__('%1$s → %2$s (ID %3$d)', 'mad-baits'), $taxonomy, $term, $term_id);
}

/**
 * Whether any published product looks like groundbait / bag mix.
 *
 * @return bool
 */
function mad_baits_catalogue_has_groundbait_products() {
	if (! function_exists('wc_get_products')) {
		return false;
	}

	$query_args = array(
		'status' => 'publish',
		'limit'  => 50,
		's'      => 'groundbait',
	);

	if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
		$ids = mad_baits_supplier_wc_get_product_ids($query_args, 'core');
	} else {
		$query_args['return'] = 'ids';
		$ids                  = wc_get_products($query_args);
	}

	if (! empty($ids)) {
		return true;
	}

	$keywords = array('groundbait', 'bag mix', 'stick mix', 'ground bait');
	if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
		$all_ids = mad_baits_supplier_wc_get_product_ids(
			array(
				'status' => 'publish',
				'limit'  => -1,
			),
			'core'
		);
	} else {
		$all_ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);
	}

	foreach ((array) $all_ids as $product_id) {
		$product = wc_get_product((int) $product_id);
		if (! $product) {
			continue;
		}
		$haystack = strtolower((string) $product->get_name());
		foreach ($keywords as $keyword) {
			if (false !== strpos($haystack, $keyword)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Run full catalogue structure setup.
 *
 * @return array<string, array<int, string>>
 */
function mad_baits_catalogue_run_structure_setup() {
	$defs = mad_baits_catalogue_setup_get_definitions();
	$log  = array(
		'categories_created'      => array(),
		'categories_skipped'      => array(),
		'tags_created'            => array(),
		'tags_skipped'            => array(),
		'attributes_created'      => array(),
		'attributes_skipped'      => array(),
		'attribute_terms_created' => array(),
		'attribute_terms_skipped' => array(),
		'audit_warnings'          => array(),
		'duplicate_warnings'      => array(),
		'errors'                  => array(),
	);

	$has_groundbait_products = mad_baits_catalogue_has_groundbait_products();

	foreach ($defs['parents'] as $parent_name => $parent_data) {
		$parent_slug = (string) $parent_data['slug'];
		$parent_id   = mad_baits_catalogue_maybe_create_product_cat($parent_name, $parent_slug, 0, $log);
		if ($parent_id <= 0) {
			continue;
		}

		$optional_children = ! empty($parent_data['optional_children']);
		if ($optional_children && ! $has_groundbait_products) {
			$log['audit_warnings'][] = sprintf(
				__('Skipped optional range subcategories under %1$s (no groundbait/bag mix products detected).', 'mad-baits'),
				$parent_name
			);
			continue;
		}

		$children = isset($parent_data['children']) ? (array) $parent_data['children'] : array();
		foreach ($children as $range_key) {
			if (! isset($defs['ranges'][ $range_key ])) {
				continue;
			}
			$range      = $defs['ranges'][ $range_key ];
			$child_name = (string) $range['label'];
			$child_slug = mad_baits_catalogue_child_category_slug($parent_slug, (string) $range['slug']);
			mad_baits_catalogue_maybe_create_product_cat($child_name, $child_slug, $parent_id, $log);
		}
	}

	foreach ($defs['tags'] as $tag_name) {
		mad_baits_catalogue_maybe_create_product_tag((string) $tag_name, $log);
	}

	foreach ($defs['attributes'] as $attr_label => $attr_data) {
		$attr_slug = (string) $attr_data['slug'];
		$taxonomy  = mad_baits_catalogue_maybe_create_attribute($attr_label, $attr_slug, $log);
		if ('' === $taxonomy) {
			continue;
		}
		foreach ((array) $attr_data['terms'] as $term_name) {
			mad_baits_catalogue_maybe_create_attribute_term($taxonomy, (string) $term_name, $log);
		}
	}

	mad_baits_catalogue_setup_flush_attribute_taxonomies();

	return $log;
}
