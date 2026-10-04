<?php
/**
 * Product catalogue suggestion engine (dry-run analysis).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Product title suggestion rules.
 *
 * @return array<string, mixed>
 */
function mad_baits_catalogue_get_suggestion_rules() {
	$defs = mad_baits_catalogue_setup_get_definitions();

	$range_patterns = array();
	foreach ($defs['ranges'] as $key => $range) {
		$patterns = array(
			preg_quote((string) $range['label'], '/'),
			preg_quote((string) $key, '/'),
			preg_quote(str_replace(' ', '-', strtolower((string) $key)), '/'),
		);
		if ('P-Fish' === $key) {
			$patterns[] = 'p[\s\-]?fish';
			$patterns[] = 'pfish';
		}
		if ('Nutz Plus' === $key) {
			$patterns[] = 'nutz[\s\-]?plus';
			$patterns[] = 'nutzplus';
		}
		if ('Wicked White' === $key) {
			$patterns[] = 'wicked[\s\-]?white';
			$patterns[] = 'wicked[\s\-]?whites';
		}
		if ('Compulsive Angler' === $key) {
			$patterns[] = 'compulsive';
			$patterns[] = 'compulsive[\s\-]?angler';
		}
		if ('STP' === $key) {
			$patterns = array('\\bSTP\\b');
		}
		if ('Swan Mussel' === $key) {
			$patterns[] = 'swan[\s\-]?mussel';
		}
		$range_patterns[ $key ] = array_unique($patterns);
	}

	$type_rules = array(
		array(
			'keywords' => array('hoodie', 't-shirt', 'tshirt', 'jacket', 'merch', 'clothing', 'cap ', ' hat'),
			'type'     => 'Clothing',
			'parent'   => 'Clothing',
		),
		array(
			'keywords' => array('pop up', 'pop-up', 'popup', 'popups'),
			'type'     => 'Pop Ups',
			'parent'   => 'Pop Ups',
		),
		array(
			'keywords' => array('skinz', 'skin z'),
			'type'     => 'Skinz Wafters',
			'parent'   => 'Skinz Wafters',
		),
		array(
			'keywords' => array('wafer', 'waft'),
			'type'     => 'Wafters',
			'parent'   => 'Wafters',
			'exclude'  => array('skinz'),
		),
		array(
			'keywords' => array('pellet'),
			'type'     => 'Pellets',
			'parent'   => 'Pellets',
		),
		array(
			'keywords' => array('groundbait', 'ground bait', 'bag mix', 'stick mix'),
			'type'     => 'Groundbait & Bag Mix',
			'parent'   => 'Groundbait & Bag Mix',
		),
		array(
			'keywords' => array('liquid', 'glug', 'dip', 'boosted'),
			'type'     => 'Liquid Foods',
			'parent'   => 'Liquid Foods',
		),
		array(
			'keywords' => array('bundle', 'deal', 'pack', 'session pack'),
			'type'     => 'Bundle / Deal',
			'parent'   => 'Bundles & Deals',
		),
		array(
			'keywords' => array('hook', 'terminal', 'lead', 'rig', 'needle', 'braid', 'line'),
			'type'     => 'Accessory',
			'parent'   => 'Accessories',
		),
		array(
			'keywords' => array('boilie', 'boilies', 'freezer bait', 'shelf life bait'),
			'type'     => 'Boilies',
			'parent'   => 'Boilies',
		),
	);

	$tag_hints = array(
		'freezer'        => 'Freezer',
		'shelf life'     => 'Shelf Life',
		'fishmeal'       => 'Fishmeal',
		'krill'          => 'Krill',
		'sweet'          => 'Sweet',
		'nutty'          => 'Nutty',
		'fruity'         => 'Fruity',
		'winter'         => 'Winter',
		'summer'         => 'Summer',
		'fluoro'         => 'Fluoro',
		'pop up'         => 'Pop Ups',
		'wafer'          => 'Wafters',
		'pellet'         => 'Feed Pellet',
		'liquid'         => 'Liquid Food',
		'12mm'           => '12mm',
		'14mm'           => '14mm',
		'15mm'           => '15mm',
		'18mm'           => '18mm',
		'22mm'           => '22mm',
	);

	return array(
		'range_patterns' => $range_patterns,
		'type_rules'     => $type_rules,
		'tag_hints'      => $tag_hints,
		'parents'        => $defs['parents'],
		'ranges'         => $defs['ranges'],
	);
}

/**
 * Suggest global attribute values from title.
 *
 * @param string $title Product title.
 * @param string $parent_name Parent category name.
 * @param string $range_key Range key.
 * @return array<string, string>
 */
function mad_baits_catalogue_suggest_attributes_for_title($title, $parent_name, $range_key) {
	$haystack = strtolower($title);
	$attrs    = array();

	if ('' !== $range_key) {
		$defs = mad_baits_catalogue_setup_get_definitions();
		if (isset($defs['ranges'][ $range_key ]['label'])) {
			$attrs['pa_range'] = (string) $defs['ranges'][ $range_key ]['label'];
		}
	}

	$size_map = array('12mm', '14mm', '15mm', '18mm', '22mm');
	foreach ($size_map as $size) {
		if (false !== strpos($haystack, $size)) {
			$attrs['pa_size'] = $size;
			break;
		}
	}

	$weight_map = array('500ml' => '500ml', '1l' => '1L', '1kg' => '1kg', '5kg' => '5kg');
	foreach ($weight_map as $needle => $label) {
		if (false !== strpos($haystack, $needle)) {
			$attrs['pa_weight-volume'] = $label;
			break;
		}
	}

	$bait_type = '';
	if ('' !== $parent_name) {
		$map = array(
			'Boilies'              => 'Shelf Life',
			'Pop Ups'              => 'Pop Ups',
			'Wafters'              => 'Wafters',
			'Skinz Wafters'        => 'Skinz Wafters',
			'Liquid Foods'         => 'Liquid Food',
			'Pellets'              => 'Feed Pellet',
			'Groundbait & Bag Mix' => 'Mixed Pellet',
		);
		$bait_type = isset($map[ $parent_name ]) ? $map[ $parent_name ] : '';
	}
	if (false !== strpos($haystack, 'freezer')) {
		$bait_type = 'Freezer';
	}
	if (false !== strpos($haystack, 'shelf life')) {
		$bait_type = 'Shelf Life';
	}
	if ('' !== $bait_type) {
		$attrs['pa_bait-type'] = $bait_type;
	}

	return $attrs;
}

/**
 * Numeric confidence score 0–100.
 *
 * @param string $parent_name Parent.
 * @param string $range_key   Range.
 * @param array  $warnings    Warnings.
 * @return int
 */
function mad_baits_catalogue_calculate_confidence_score($parent_name, $range_key, $warnings) {
	$score = 0;
	if ('' !== $parent_name) {
		$score += 45;
	}
	if ('' !== $range_key) {
		$score += 40;
	}
	if ($parent_name && $range_key) {
		$score += 10;
	}
	$score -= min(30, count($warnings) * 8);

	return max(0, min(100, $score));
}

/**
 * Suggest catalogue mapping for one product title (dry-run).
 *
 * @param string $title Product title.
 * @return array<string, mixed>
 */
function mad_baits_catalogue_suggest_for_title($title) {
	$rules    = mad_baits_catalogue_get_suggestion_rules();
	$haystack = strtolower($title);

	$range_key = '';
	foreach ($rules['range_patterns'] as $key => $patterns) {
		foreach ($patterns as $pattern) {
			if (preg_match('/' . $pattern . '/i', $title)) {
				$range_key = $key;
				break 2;
			}
		}
	}

	$type_label  = '';
	$parent_name = '';
	foreach ($rules['type_rules'] as $rule) {
		$matched = false;
		foreach ($rule['keywords'] as $keyword) {
			if (false !== strpos($haystack, strtolower($keyword))) {
				if (! empty($rule['exclude'])) {
					foreach ((array) $rule['exclude'] as $ex) {
						if (false !== strpos($haystack, strtolower($ex))) {
							continue 2;
						}
					}
				}
				$matched     = true;
				$type_label  = (string) $rule['type'];
				$parent_name = (string) $rule['parent'];
				break;
			}
		}
		if ($matched) {
			break;
		}
	}

	$suggested_category = $parent_name;
	if ($range_key && $parent_name && isset($rules['parents'][ $parent_name ])) {
		$children = (array) $rules['parents'][ $parent_name ]['children'];
		if (in_array($range_key, $children, true)) {
			$child_label = isset($rules['ranges'][ $range_key ]['label'])
				? (string) $rules['ranges'][ $range_key ]['label']
				: $range_key;
			$suggested_category = $parent_name . ' → ' . $child_label;
		}
	}

	$suggested_tags = array();
	if ($range_key) {
		$suggested_tags[] = $range_key;
	}
	foreach ($rules['tag_hints'] as $needle => $tag) {
		if (false !== strpos($haystack, $needle)) {
			$suggested_tags[] = $tag;
		}
	}
	$suggested_tags = array_values(array_unique($suggested_tags));

	$suggested_attributes = mad_baits_catalogue_suggest_attributes_for_title($title, $parent_name, $range_key);
	$attribute_display    = array();
	foreach ($suggested_attributes as $tax => $value) {
		$attribute_display[] = str_replace('pa_', '', $tax) . ': ' . $value;
	}

	$confidence_label = 'low';
	if ($parent_name && $range_key) {
		$confidence_label = 'high';
	} elseif ($parent_name || $range_key) {
		$confidence_label = 'medium';
	}

	$warnings = array();
	if ('' === $parent_name) {
		$warnings[] = __('No product type detected from title.', 'mad-baits');
	}
	if ('' === $range_key) {
		$warnings[] = __('No range detected from title.', 'mad-baits');
	}
	if (preg_match('/\b(test|sample|copy|duplicate)\b/i', $title)) {
		$warnings[] = __('Title may indicate test/duplicate product.', 'mad-baits');
	}

	$confidence_score = mad_baits_catalogue_calculate_confidence_score($parent_name, $range_key, $warnings);

	return array(
		'range'                 => $range_key ?: '—',
		'range_key'             => $range_key,
		'range_label'           => $range_key && isset($rules['ranges'][ $range_key ]['label']) ? (string) $rules['ranges'][ $range_key ]['label'] : '',
		'parent_name'           => $parent_name,
		'parent_slug'           => $parent_name && isset($rules['parents'][ $parent_name ]['slug']) ? (string) $rules['parents'][ $parent_name ]['slug'] : '',
		'range_slug'            => $range_key && isset($rules['ranges'][ $range_key ]['slug']) ? (string) $rules['ranges'][ $range_key ]['slug'] : '',
		'type'                  => $type_label ?: '—',
		'category'              => $suggested_category ?: '—',
		'tag_names'             => $suggested_tags,
		'tags'                  => ! empty($suggested_tags) ? implode(', ', $suggested_tags) : '—',
		'suggested_attributes'  => $suggested_attributes,
		'attributes_display'    => ! empty($attribute_display) ? implode(' · ', $attribute_display) : '—',
		'confidence'            => $confidence_label,
		'confidence_score'      => $confidence_score,
		'warnings'              => $warnings,
		'category_term_ids'     => mad_baits_catalogue_resolve_category_term_ids($parent_name, $range_key),
		'can_apply'             => '' !== $parent_name,
	);
}

/**
 * Resolve parent (+ optional range child) category term IDs for assignment.
 *
 * @param string $parent_name Parent category name.
 * @param string $range_key   Range key from definitions.
 * @return array<int, int>
 */
function mad_baits_catalogue_resolve_category_term_ids($parent_name, $range_key) {
	$parent_name = (string) $parent_name;
	$range_key   = (string) $range_key;

	if ('' === $parent_name) {
		return array();
	}

	$defs = mad_baits_catalogue_setup_get_definitions();
	if (! isset($defs['parents'][ $parent_name ])) {
		return array();
	}

	$parent_slug = (string) $defs['parents'][ $parent_name ]['slug'];
	$parent_term = mad_baits_catalogue_find_product_cat($parent_name, $parent_slug, 0);
	$ids         = array();

	if ($parent_term instanceof WP_Term) {
		$ids[] = (int) $parent_term->term_id;
	}

	if ('' !== $range_key && isset($defs['ranges'][ $range_key ])) {
		$children = (array) $defs['parents'][ $parent_name ]['children'];
		if (in_array($range_key, $children, true)) {
			$range_slug  = (string) $defs['ranges'][ $range_key ]['slug'];
			$child_slug  = mad_baits_catalogue_child_category_slug($parent_slug, $range_slug);
			$child_label = (string) $defs['ranges'][ $range_key ]['label'];
			$parent_id   = $parent_term instanceof WP_Term ? (int) $parent_term->term_id : 0;
			$child_term  = mad_baits_catalogue_find_product_cat($child_label, $child_slug, $parent_id);
			if ($child_term instanceof WP_Term) {
				$ids[] = (int) $child_term->term_id;
			}
		}
	}

	return array_values(array_unique(array_filter($ids)));
}

/**
 * Resolve product_tag term IDs from tag names (existing tags only).
 *
 * @param array<int, string> $tag_names Tag names.
 * @return array<int, int>
 */
function mad_baits_catalogue_resolve_tag_term_ids($tag_names) {
	$ids = array();
	foreach ((array) $tag_names as $name) {
		$name = trim((string) $name);
		if ('' === $name) {
			continue;
		}
		$term = get_term_by('name', $name, 'product_tag');
		if (! $term) {
			$term = get_term_by('slug', sanitize_title($name), 'product_tag');
		}
		if ($term instanceof WP_Term) {
			$ids[] = (int) $term->term_id;
		}
	}

	return array_values(array_unique($ids));
}

/**
 * Build suggestion from admin override fields.
 *
 * @param string $parent_name Parent name.
 * @param string $range_key   Range key or empty.
 * @return array<string, mixed>
 */
function mad_baits_catalogue_build_suggestion_from_selection($parent_name, $range_key) {
	$defs        = mad_baits_catalogue_setup_get_definitions();
	$parent_name = (string) $parent_name;
	$range_key   = (string) $range_key;

	if ('' === $parent_name || ! isset($defs['parents'][ $parent_name ])) {
		return array(
			'range_key'            => '',
			'range_label'          => '',
			'parent_name'          => '',
			'parent_slug'          => '',
			'range_slug'           => '',
			'type'                 => '—',
			'category'             => '—',
			'tag_names'            => array(),
			'tags'                 => '—',
			'suggested_attributes' => array(),
			'attributes_display'   => '—',
			'confidence'           => 'manual',
			'confidence_score'     => 0,
			'warnings'             => array(),
			'category_term_ids'    => array(),
			'can_apply'            => false,
		);
	}

	$type_label = $parent_name;
	foreach (mad_baits_catalogue_get_suggestion_rules()['type_rules'] as $rule) {
		if (isset($rule['parent']) && $rule['parent'] === $parent_name) {
			$type_label = (string) $rule['type'];
			break;
		}
	}

	$category_display = $parent_name;
	if ('' !== $range_key && isset($defs['ranges'][ $range_key ]['label'])) {
		$category_display = $parent_name . ' → ' . (string) $defs['ranges'][ $range_key ]['label'];
	}

	$tag_names = array();
	if ('' !== $range_key) {
		$tag_names[] = $range_key;
	}

	$suggested_attributes = mad_baits_catalogue_suggest_attributes_for_title('', $parent_name, $range_key);

	return array(
		'range_key'            => $range_key,
		'range_label'          => '' !== $range_key && isset($defs['ranges'][ $range_key ]['label']) ? (string) $defs['ranges'][ $range_key ]['label'] : '',
		'parent_name'          => $parent_name,
		'parent_slug'          => (string) $defs['parents'][ $parent_name ]['slug'],
		'range_slug'           => '' !== $range_key && isset($defs['ranges'][ $range_key ]['slug']) ? (string) $defs['ranges'][ $range_key ]['slug'] : '',
		'type'                 => $type_label,
		'category'             => $category_display,
		'tag_names'            => $tag_names,
		'tags'                 => ! empty($tag_names) ? implode(', ', $tag_names) : '—',
		'suggested_attributes' => $suggested_attributes,
		'attributes_display'   => '—',
		'confidence'           => 'manual',
		'confidence_score'     => 100,
		'warnings'             => array(),
		'category_term_ids'    => mad_baits_catalogue_resolve_category_term_ids($parent_name, $range_key),
		'can_apply'            => true,
	);
}

/**
 * Parse comma-separated tag string into names.
 *
 * @param string $raw Raw input.
 * @return array<int, string>
 */
function mad_baits_catalogue_parse_tag_list($raw) {
	$parts = preg_split('/\s*,\s*/', (string) $raw);
	if (! is_array($parts)) {
		return array();
	}

	return array_values(
		array_filter(
			array_map(
				static function ($part) {
					return trim((string) $part);
				},
				$parts
			)
		)
	);
}

/**
 * Parent/range option lists for admin dropdowns.
 *
 * @return array{parents: array<string, string>, ranges: array<string, string>}
 */
function mad_baits_catalogue_get_assignment_option_lists() {
	$defs    = mad_baits_catalogue_setup_get_definitions();
	$parents = array();
	$ranges  = array();

	foreach ($defs['parents'] as $name => $data) {
		$parents[ $name ] = $name;
	}
	foreach ($defs['ranges'] as $key => $data) {
		$ranges[ $key ] = (string) $data['label'];
	}

	return array(
		'parents' => $parents,
		'ranges'  => $ranges,
	);
}

/**
 * Normalise title for duplicate detection.
 *
 * @param string $title Title.
 * @return string
 */
function mad_baits_catalogue_normalize_title_key($title) {
	$key = strtolower($title);
	$key = preg_replace('/\b\d+\s?mm\b/', '', $key);
	$key = preg_replace('/\b\d+\s?(kg|g|ml|l)\b/', '', $key);
	$key = preg_replace('/[^a-z0-9]+/', ' ', (string) $key);

	return trim((string) $key);
}

/**
 * Detect likely duplicate product groups from scan rows.
 *
 * @param array<int, array<string, mixed>> $rows Scan rows.
 * @return array<string, array<int, int>> Map of normalised key => product IDs.
 */
function mad_baits_catalogue_detect_duplicate_groups($rows) {
	$groups = array();

	foreach ($rows as $row) {
		$key = mad_baits_catalogue_normalize_title_key((string) ($row['title'] ?? ''));
		if (strlen($key) < 8) {
			continue;
		}
		if (! isset($groups[ $key ])) {
			$groups[ $key ] = array();
		}
		$groups[ $key ][] = (int) ($row['id'] ?? 0);
	}

	return array_filter(
		$groups,
		static function ($ids) {
			$ids = array_values(array_filter(array_map('absint', $ids)));
			return count($ids) > 1;
		}
	);
}

/**
 * Get current product global attributes as readable string.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_catalogue_get_product_attributes_display($product_id) {
	$product = wc_get_product($product_id);
	if (! $product) {
		return '';
	}

	$parts = array();
	foreach ($product->get_attributes() as $attribute) {
		if (! $attribute instanceof WC_Product_Attribute) {
			continue;
		}
		if (! $attribute->is_taxonomy()) {
			continue;
		}
		$taxonomy = $attribute->get_name();
		$terms    = wc_get_product_terms($product_id, $taxonomy, array('fields' => 'names'));
		if (! empty($terms) && is_array($terms)) {
			$parts[] = wc_attribute_label($taxonomy) . ': ' . implode(', ', $terms);
		}
	}

	return implode(' · ', $parts);
}

/**
 * Analyse product for audit warnings.
 *
 * @param int                  $product_id Product ID.
 * @param array<string, mixed> $suggestion Suggestion.
 * @param string               $current_cats Current categories string.
 * @param string               $current_tags Current tags string.
 * @return array<int, string>
 */
function mad_baits_catalogue_analyse_product_warnings($product_id, $suggestion, $current_cats, $current_tags) {
	$warnings = isset($suggestion['warnings']) && is_array($suggestion['warnings']) ? $suggestion['warnings'] : array();

	if ('' === trim($current_cats)) {
		$warnings[] = __('Missing categories.', 'mad-baits');
	}
	if ('' === trim($current_tags)) {
		$warnings[] = __('Missing tags.', 'mad-baits');
	}

	$product = wc_get_product($product_id);
	if ($product && $product->is_type('variable')) {
		$attrs = $product->get_attributes();
		if (empty($attrs)) {
			$warnings[] = __('Variable product has no attributes.', 'mad-baits');
		}
	}

	$title = $product ? (string) $product->get_name() : '';
	if ($title && preg_match('/\s{2,}/', $title)) {
		$warnings[] = __('Inconsistent spacing in product name.', 'mad-baits');
	}

	return array_values(array_unique($warnings));
}

/**
 * Transient key for stored scan results.
 *
 * @return string
 */
function mad_baits_catalogue_scan_transient_key() {
	return 'mad_baits_catalogue_scan_' . get_current_user_id();
}

/**
 * Scan published products for suggestions (no writes).
 *
 * @param int $limit Max products (0 = all).
 * @return array<int, array<string, mixed>>
 */
function mad_baits_catalogue_scan_product_suggestions($limit = 0) {
	if (! function_exists('wc_get_products')) {
		return array();
	}

	if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
		$ids = mad_baits_supplier_wc_get_product_ids(
			array(
				'status'  => array('publish'),
				'limit'   => $limit > 0 ? $limit : -1,
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);
	} else {
		$args = array(
			'status'  => array('publish'),
			'limit'   => $limit > 0 ? $limit : -1,
			'return'  => 'ids',
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		$ids  = wc_get_products($args);
	}
	$rows = array();

	foreach ($ids as $product_id) {
		$product_id = (int) $product_id;
		$product    = wc_get_product($product_id);
		if (! $product) {
			continue;
		}

		$title      = (string) $product->get_name();
		$suggestion = mad_baits_catalogue_suggest_for_title($title);

		$current_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
		$current_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'));
		$current_cats_str = is_array($current_cats) ? implode(', ', $current_cats) : '';
		$current_tags_str = is_array($current_tags) ? implode(', ', $current_tags) : '';

		$warnings = mad_baits_catalogue_analyse_product_warnings($product_id, $suggestion, $current_cats_str, $current_tags_str);

		$rows[] = array(
			'id'                  => $product_id,
			'title'               => $title,
			'edit_url'            => get_edit_post_link($product_id, 'raw'),
			'current_cats'        => $current_cats_str,
			'current_tags'        => $current_tags_str,
			'current_attributes'  => mad_baits_catalogue_get_product_attributes_display($product_id),
			'suggested_category'  => (string) ($suggestion['category'] ?? '—'),
			'suggested_tags'      => (string) ($suggestion['tags'] ?? '—'),
			'suggested_attributes'=> (string) ($suggestion['attributes_display'] ?? '—'),
			'confidence_score'    => (int) ($suggestion['confidence_score'] ?? 0),
			'warnings'            => $warnings,
			'suggestion'          => $suggestion,
		);
	}

	$duplicate_groups = mad_baits_catalogue_detect_duplicate_groups($rows);
	foreach ($rows as &$row) {
		$key = mad_baits_catalogue_normalize_title_key((string) $row['title']);
		if (isset($duplicate_groups[ $key ])) {
			$row['warnings'][] = sprintf(
				/* translators: %s: comma-separated product IDs */
				__('Possible duplicate of product(s): %s', 'mad-baits'),
				implode(', ', array_diff($duplicate_groups[ $key ], array((int) $row['id'])))
			);
			$row['is_duplicate'] = true;
		} else {
			$row['is_duplicate'] = false;
		}
		$row['warnings'] = array_values(array_unique($row['warnings']));
	}
	unset($row);

	return $rows;
}
