<?php
/**
 * WooCommerce product list admin filters and styling.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Enqueue admin styles on product list and catalogue page.
 *
 * @param string $hook_suffix Hook suffix.
 * @return void
 */
function mad_baits_catalogue_admin_enqueue($hook_suffix) {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	$is_products = $screen && 'edit-product' === $screen->id;
	$is_catalogue  = isset($_GET['page']) && 'mad-baits-catalogue-setup' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$catalogue_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'setup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if (! $is_products && ! $is_catalogue) {
		return;
	}

	$css = '
		.mad-confidence--high { color: #1d6f42; font-weight: 700; }
		.mad-confidence--medium { color: #996800; font-weight: 600; }
		.mad-confidence--low { color: #646970; }
		.mad-confidence--manual { color: #2271b1; font-weight: 600; }
		.mad-range-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; color: #111; border: 1px solid rgba(0,0,0,.15); }
		.mad-catalogue-log { max-height: 280px; overflow: auto; background: #fff; border: 1px solid #c3c4c7; padding: 0.5rem 1rem; }
		.mad-catalogue-log--created li { color: #1d6f42; }
		.mad-catalogue-log--skipped li { color: #646970; }
		.mad-catalogue-log--warning li { color: #996800; }
		.mad-catalogue-suggestions-table { margin-top: 1rem; }
		.mad-catalogue-suggestions-table select { max-width: 100%; }
		.mad-catalogue-suggestions-table .mad-warning-cell { color: #b32d2e; font-size: 12px; }
		.mad-catalogue-filter-bar { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
		.mad-catalogue-filter-bar .button.is-active { background: #2271b1; color: #fff; border-color: #2271b1; }
		.mad-catalogue-bulk-tags { margin-top: 0.5rem; }
		.mad-catalogue-bulk-tags__label { display: block; margin-bottom: 0.35rem; }
		.mad-catalogue-bulk-tags__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0.75rem 0 0; }
		.mad-catalogue-bulk-tags__status { color: #1d2327; font-style: italic; }
		.column-mad_range { width: 120px; }
		.column-mad_image_status { width: 70px; text-align: center; }
		.column-mad_image_status .mad-warning-cell { color: #b32d2e; font-weight: 600; font-size: 12px; text-decoration: none; }
	';

	$theme_version = wp_get_theme()->get('Version');
	$theme_version = is_string($theme_version) && '' !== $theme_version ? $theme_version : '1.0.0';
	wp_register_style('mad-baits-catalogue-admin', false, array(), $theme_version);
	wp_enqueue_style('mad-baits-catalogue-admin');
	wp_add_inline_style('mad-baits-catalogue-admin', $css);

	if ($is_catalogue && 'audit' === $catalogue_tab) {
		$script_deps = array('jquery');
		if (class_exists('WooCommerce')) {
			wp_enqueue_script('selectWoo');
			if (wp_script_is('selectWoo', 'registered')) {
				$script_deps[] = 'selectWoo';
			}
		}

		wp_enqueue_script(
			'mad-baits-catalogue-admin',
			get_theme_file_uri('/assets/js/mad-catalogue-admin.js'),
			$script_deps,
			$theme_version,
			true
		);

		wp_localize_script(
			'mad-baits-catalogue-admin',
			'madCatalogueAdmin',
			array(
				'ajaxUrl'              => admin_url('admin-ajax.php'),
				'i18nSelectProducts'   => __('Select at least one product in the table.', 'mad-baits'),
				'i18nSelectTags'       => __('Select at least one tag.', 'mad-baits'),
				'i18nConfirmBulkAssign'=> __('Add the selected tags to the checked products?', 'mad-baits'),
				'i18nAssigningTags'    => __('Assigning tags…', 'mad-baits'),
				'i18nAssignFailed'     => __('Assign failed. Try again.', 'mad-baits'),
				'i18nFilledFields'     => __('Copied tags into %d product field(s).', 'mad-baits'),
			)
		);
	}
}
add_action('admin_enqueue_scripts', 'mad_baits_catalogue_admin_enqueue');

/**
 * Product list filters: range, type, missing data.
 *
 * @param string $post_type Post type.
 * @return void
 */
function mad_baits_catalogue_products_restrict_manage_posts($post_type) {
	if ('product' !== $post_type || ! current_user_can('manage_woocommerce')) {
		return;
	}

	$defs   = mad_baits_catalogue_setup_get_definitions();
	$ranges = mad_baits_catalogue_get_assignment_option_lists()['ranges'];

	$current_range  = isset($_GET['mad_range']) ? sanitize_text_field(wp_unslash((string) $_GET['mad_range'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current_parent = isset($_GET['mad_product_type']) ? sanitize_text_field(wp_unslash((string) $_GET['mad_product_type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$missing        = isset($_GET['mad_missing']) ? sanitize_key(wp_unslash((string) $_GET['mad_missing'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<select name="mad_range" id="mad_range">';
	echo '<option value="">' . esc_html__('All ranges', 'mad-baits') . '</option>';
	foreach ($ranges as $key => $label) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr($key),
			selected($current_range, $key, false),
			esc_html($label)
		);
	}
	echo '</select>';

	echo '<select name="mad_product_type" id="mad_product_type">';
	echo '<option value="">' . esc_html__('All product types', 'mad-baits') . '</option>';
	foreach ($defs['parents'] as $parent_name => $data) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr((string) $data['slug']),
			selected($current_parent, (string) $data['slug'], false),
			esc_html($parent_name)
		);
	}
	echo '</select>';

	echo '<select name="mad_missing" id="mad_missing">';
	echo '<option value="">' . esc_html__('Data completeness', 'mad-baits') . '</option>';
	echo '<option value="no_cat"' . selected($missing, 'no_cat', false) . '>' . esc_html__('Missing categories', 'mad-baits') . '</option>';
	echo '<option value="no_tags"' . selected($missing, 'no_tags', false) . '>' . esc_html__('Missing tags', 'mad-baits') . '</option>';
	echo '<option value="no_image"' . selected($missing, 'no_image', false) . '>' . esc_html__('Missing image', 'mad-baits') . '</option>';
	echo '</select>';
}
add_action('restrict_manage_posts', 'mad_baits_catalogue_products_restrict_manage_posts');

/**
 * Apply product list filters to main query.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function mad_baits_catalogue_products_parse_query($query) {
	if (! is_admin() || ! $query->is_main_query() || 'product' !== $query->get('post_type')) {
		return;
	}

	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	$tax_query = (array) $query->get('tax_query');
	if (! isset($tax_query['relation'])) {
		$tax_query['relation'] = 'AND';
	}

	if (! empty($_GET['mad_range'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$range_key = sanitize_text_field(wp_unslash((string) $_GET['mad_range']));
		$defs      = mad_baits_catalogue_setup_get_definitions();
		if (isset($defs['ranges'][ $range_key ]['slug'])) {
			$range_slug = (string) $defs['ranges'][ $range_key ]['slug'];
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => array($range_slug, sanitize_title($range_key)),
				'operator' => 'IN',
			);
		}
	}

	if (! empty($_GET['mad_product_type'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$parent_slug = sanitize_title(wp_unslash((string) $_GET['mad_product_type']));
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'slug',
			'terms'            => array($parent_slug),
			'include_children' => true,
		);
	}

	if (count($tax_query) > 1) {
		$query->set('tax_query', $tax_query);
	}

	if (! empty($_GET['mad_missing'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$missing = sanitize_key(wp_unslash((string) $_GET['mad_missing']));
		add_filter('posts_clauses', 'mad_baits_catalogue_missing_data_clauses', 10, 2);
		$query->set('mad_missing_filter', $missing);
	}
}
add_action('parse_query', 'mad_baits_catalogue_products_parse_query');

/**
 * SQL clauses for missing-data filters.
 *
 * @param string[] $clauses Clauses.
 * @param WP_Query $query   Query.
 * @return string[]
 */
function mad_baits_catalogue_missing_data_clauses($clauses, $query) {
	if (! $query->get('mad_missing_filter')) {
		return $clauses;
	}

	global $wpdb;

	$missing = sanitize_key((string) $query->get('mad_missing_filter'));

	if ('no_cat' === $missing) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} tr_cat ON ({$wpdb->posts}.ID = tr_cat.object_id)";
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} tt_cat ON (tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id AND tt_cat.taxonomy = 'product_cat')";
		$clauses['where'] .= ' AND tt_cat.term_taxonomy_id IS NULL';
	} elseif ('no_tags' === $missing) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} tr_tag ON ({$wpdb->posts}.ID = tr_tag.object_id)";
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} tt_tag ON (tr_tag.term_taxonomy_id = tt_tag.term_taxonomy_id AND tt_tag.taxonomy = 'product_tag')";
		$clauses['where'] .= ' AND tt_tag.term_taxonomy_id IS NULL';
	} elseif ('no_image' === $missing) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} pm_thumb ON ({$wpdb->posts}.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id')";
		$clauses['where'] .= " AND (pm_thumb.meta_value IS NULL OR pm_thumb.meta_value = '' OR pm_thumb.meta_value = '0')";
	}

	remove_filter('posts_clauses', 'mad_baits_catalogue_missing_data_clauses', 10);

	return $clauses;
}

/**
 * Add range column to products list.
 *
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function mad_baits_catalogue_product_columns($columns) {
	$new = array();
	foreach ($columns as $key => $label) {
		$new[ $key ] = $label;
		if ('name' === $key) {
			$new['mad_range'] = __('Range', 'mad-baits');
			$new['mad_image_status'] = __('Image', 'mad-baits');
		}
	}
	return $new;
}
add_filter('manage_edit-product_columns', 'mad_baits_catalogue_product_columns');

/**
 * Render range / image status columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function mad_baits_catalogue_product_column_content($column, $post_id) {
	if ('mad_image_status' === $column) {
		$thumb_id = (int) get_post_thumbnail_id($post_id);
		if ($thumb_id > 0) {
			echo '<span class="mad-confidence--high" title="' . esc_attr__('Featured image set', 'mad-baits') . '">✓</span>';
			return;
		}
		$filter_url = add_query_arg(
			array(
				'post_type'   => 'product',
				'mad_missing' => 'no_image',
			),
			admin_url('edit.php')
		);
		printf(
			'<a class="mad-warning-cell" href="%s" title="%s">%s</a>',
			esc_url($filter_url),
			esc_attr__('Missing featured image — click to filter all', 'mad-baits'),
			esc_html__('Missing', 'mad-baits')
		);
		return;
	}

	if ('mad_range' !== $column) {
		return;
	}

	$tags = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'names'));
	if (empty($tags) || is_wp_error($tags)) {
		echo '<span class="description">—</span>';
		return;
	}

	$defs  = mad_baits_catalogue_setup_get_definitions();
	$range = '';
	foreach ($tags as $tag_name) {
		if (isset($defs['ranges'][ $tag_name ])) {
			$range = $tag_name;
			break;
		}
	}

	if ('' === $range) {
		echo esc_html((string) $tags[0]);
		return;
	}

	$colour = mad_baits_catalogue_get_range_colour($range);
	printf(
		'<span class="mad-range-pill" style="background:%s">%s</span>',
		esc_attr($colour),
		esc_html($range)
	);
}
add_action('manage_product_posts_custom_column', 'mad_baits_catalogue_product_column_content', 10, 2);

/**
 * Admin notice on product list when filtering missing images, plus quick link.
 *
 * @return void
 */
function mad_baits_catalogue_missing_image_admin_notice() {
	if (! is_admin() || ! current_user_can('manage_woocommerce')) {
		return;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (! $screen || 'edit-product' !== $screen->id) {
		return;
	}

	$missing = isset($_GET['mad_missing']) ? sanitize_key(wp_unslash((string) $_GET['mad_missing'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ('no_image' === $missing) {
		echo '<div class="notice notice-warning"><p>';
		esc_html_e('Showing products with no featured image. Add a product photo (or category thumbnail as fallback) so shoppers do not see a blank placeholder.', 'mad-baits');
		echo '</p></div>';
		return;
	}

	// Lightweight tip once per session when not already filtering.
	if (! empty($_GET['mad_range']) || ! empty($_GET['mad_product_type']) || ! empty($_GET['s'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
}
add_action('admin_notices', 'mad_baits_catalogue_missing_image_admin_notice');
