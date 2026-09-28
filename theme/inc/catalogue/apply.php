<?php
/**
 * Safe bulk apply + rollback logging for catalogue suggestions.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Option key for rollback log entries.
 *
 * @return string
 */
function mad_baits_catalogue_rollback_option_key() {
	return 'mad_baits_catalogue_rollback_log';
}

/**
 * Get rollback log entries (newest first).
 *
 * @param int $limit Max entries.
 * @return array<int, array<string, mixed>>
 */
function mad_baits_catalogue_get_rollback_log($limit = 50) {
	$log = get_option(mad_baits_catalogue_rollback_option_key(), array());

	if (! is_array($log)) {
		return array();
	}

	return array_slice($log, 0, max(1, $limit));
}

/**
 * Capture product taxonomy state before changes.
 *
 * @param int $product_id Product ID.
 * @return array<string, mixed>
 */
function mad_baits_catalogue_capture_product_state($product_id) {
	$product_id = absint($product_id);
	$state      = array(
		'product_id' => $product_id,
		'categories' => array(),
		'tags'       => array(),
		'attributes' => array(),
	);

	$cat_ids = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
	if (! is_wp_error($cat_ids)) {
		$state['categories'] = array_map('absint', (array) $cat_ids);
	}

	$tag_ids = wp_get_object_terms($product_id, 'product_tag', array('fields' => 'ids'));
	if (! is_wp_error($tag_ids)) {
		$state['tags'] = array_map('absint', (array) $tag_ids);
	}

	$product = wc_get_product($product_id);
	if ($product) {
		foreach ($product->get_attributes() as $attribute) {
			if (! $attribute instanceof WC_Product_Attribute || ! $attribute->is_taxonomy()) {
				continue;
			}
			$taxonomy = $attribute->get_name();
			$terms    = wc_get_product_terms($product_id, $taxonomy, array('fields' => 'ids'));
			if (! is_wp_error($terms) && ! empty($terms)) {
				$state['attributes'][ $taxonomy ] = array_map('absint', (array) $terms);
			}
		}
	}

	return $state;
}

/**
 * Append rollback log entry.
 *
 * @param int                  $product_id Product ID.
 * @param array<string, mixed> $before     State before apply.
 * @param array<string, mixed> $options    Apply options used.
 * @param array<string, mixed> $result     Apply result.
 * @return void
 */
function mad_baits_catalogue_log_apply_rollback($product_id, $before, $options, $result) {
	$log = get_option(mad_baits_catalogue_rollback_option_key(), array());
	if (! is_array($log)) {
		$log = array();
	}

	array_unshift(
		$log,
		array(
			'timestamp'  => current_time('mysql'),
			'user_id'    => get_current_user_id(),
			'product_id' => absint($product_id),
			'before'     => $before,
			'options'    => $options,
			'result'     => $result,
		)
	);

	$log = array_slice($log, 0, 200);
	update_option(mad_baits_catalogue_rollback_option_key(), $log, false);
}

/**
 * Apply suggested global attributes to a simple product (never deletes variations).
 *
 * @param int                  $product_id Product ID.
 * @param array<string, string> $suggested  pa_* => term name.
 * @param bool                 $append     Append vs replace attribute terms.
 * @return array{success: bool, message: string}
 */
function mad_baits_catalogue_apply_attributes_to_product($product_id, $suggested, $append = true) {
	$product_id = absint($product_id);
	$product    = wc_get_product($product_id);

	if (! $product) {
		return array(
			'success' => false,
			'message' => __('Invalid product.', 'mad-baits'),
		);
	}

	if ($product->is_type('variable')) {
		return array(
			'success' => false,
			'message' => __('Skipped attributes on variable product (manage variations manually).', 'mad-baits'),
		);
	}

	if (empty($suggested) || ! is_array($suggested)) {
		return array(
			'success' => false,
			'message' => __('No attributes to assign.', 'mad-baits'),
		);
	}

	$attributes     = $product->get_attributes();
	$applied_labels = array();

	foreach ($suggested as $taxonomy => $term_name) {
		$taxonomy = sanitize_key((string) $taxonomy);
		$term_name = trim((string) $term_name);
		if ('' === $taxonomy || '' === $term_name || ! taxonomy_exists($taxonomy)) {
			continue;
		}

		$term = get_term_by('name', $term_name, $taxonomy);
		if (! $term) {
			$term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
		}
		if (! $term instanceof WP_Term) {
			continue;
		}

		wp_set_object_terms($product_id, array((int) $term->term_id), $taxonomy, (bool) $append);

		if (! isset($attributes[ $taxonomy ])) {
			$attr = new WC_Product_Attribute();
			$attr->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
			$attr->set_name($taxonomy);
			$attr->set_options(array((int) $term->term_id));
			$attr->set_visible(true);
			$attr->set_variation(false);
			$attributes[ $taxonomy ] = $attr;
		} else {
			$existing = $attributes[ $taxonomy ];
			if ($existing instanceof WC_Product_Attribute) {
				$options = array_map('absint', (array) $existing->get_options());
				if ($append) {
					$options[] = (int) $term->term_id;
					$options   = array_values(array_unique($options));
				} else {
					$options = array((int) $term->term_id);
				}
				$existing->set_options($options);
				$attributes[ $taxonomy ] = $existing;
			}
		}

		$applied_labels[] = wc_attribute_label($taxonomy) . ': ' . $term_name;
	}

	if (empty($applied_labels)) {
		return array(
			'success' => false,
			'message' => __('No matching attribute terms found — run Structure setup first.', 'mad-baits'),
		);
	}

	$product->set_attributes($attributes);
	$product->save();

	return array(
		'success' => true,
		'message' => sprintf(
			/* translators: %s: attribute list */
			__('Attributes: %s', 'mad-baits'),
			implode('; ', $applied_labels)
		),
	);
}

/**
 * Apply categories/tags/attributes to a product (append or replace).
 *
 * @param int                  $product_id Product ID.
 * @param array<string, mixed> $suggestion Suggestion payload.
 * @param array<string, bool>  $options    apply_* flags.
 * @return array<string, mixed> Result row.
 */
function mad_baits_catalogue_apply_suggestion_to_product($product_id, $suggestion, $options) {
	$product_id = absint($product_id);
	$result     = array(
		'product_id' => $product_id,
		'success'    => false,
		'message'    => '',
	);

	if ($product_id < 1 || ! function_exists('wc_get_product') || ! wc_get_product($product_id)) {
		$result['message'] = __('Invalid product.', 'mad-baits');

		return $result;
	}

	$before = mad_baits_catalogue_capture_product_state($product_id);

	$messages = array();
	$had_error = false;
	$did_work  = false;

	if (! empty($options['apply_categories'])) {
		$cat_ids = isset($suggestion['category_term_ids']) ? array_map('absint', (array) $suggestion['category_term_ids']) : array();
		$cat_ids = array_values(array_filter($cat_ids));

		if (empty($cat_ids)) {
			$messages[] = __('No categories to assign.', 'mad-baits');
		} else {
			if (! empty($options['append_categories'])) {
				$existing = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
				if (is_wp_error($existing)) {
					$existing = array();
				}
				$cat_ids = array_values(array_unique(array_merge(array_map('absint', $existing), $cat_ids)));
			}

			$set = wp_set_object_terms($product_id, $cat_ids, 'product_cat', false);
			if (is_wp_error($set)) {
				$had_error  = true;
				$messages[] = $set->get_error_message();
			} else {
				$did_work   = true;
				$messages[] = sprintf(
					/* translators: %d: number of categories */
					_n('%d category assigned', '%d categories assigned', count($cat_ids), 'mad-baits'),
					count($cat_ids)
				);
			}
		}
	}

	if (! empty($options['apply_tags'])) {
		$tag_names = isset($suggestion['tag_names']) ? (array) $suggestion['tag_names'] : array();
		$tag_ids   = mad_baits_catalogue_resolve_tag_term_ids($tag_names);

		if (empty($tag_ids) && ! empty($tag_names)) {
			$messages[] = __('Some tags were not found — run Structure setup first.', 'mad-baits');
		} elseif (! empty($tag_ids)) {
			if (! empty($options['append_tags'])) {
				$existing = wp_get_object_terms($product_id, 'product_tag', array('fields' => 'ids'));
				if (is_wp_error($existing)) {
					$existing = array();
				}
				$tag_ids = array_values(array_unique(array_merge(array_map('absint', $existing), $tag_ids)));
			}

			$set = wp_set_object_terms($product_id, $tag_ids, 'product_tag', false);
			if (is_wp_error($set)) {
				$had_error  = true;
				$messages[] = $set->get_error_message();
			} else {
				$did_work   = true;
				$messages[] = sprintf(
					/* translators: %d: number of tags */
					_n('%d tag assigned', '%d tags assigned', count($tag_ids), 'mad-baits'),
					count($tag_ids)
				);
			}
		}
	}

	if (! empty($options['apply_attributes'])) {
		$suggested_attrs = isset($suggestion['suggested_attributes']) ? (array) $suggestion['suggested_attributes'] : array();
		$attr_result     = mad_baits_catalogue_apply_attributes_to_product(
			$product_id,
			$suggested_attrs,
			! empty($options['append_attributes'])
		);
		if (! empty($attr_result['success'])) {
			$did_work = true;
		} elseif (! empty($attr_result['message'])) {
			if (false === strpos((string) $attr_result['message'], 'Skipped')) {
				$had_error = true;
			}
		}
		if (! empty($attr_result['message'])) {
			$messages[] = (string) $attr_result['message'];
		}
	}

	$result['success'] = $did_work && ! $had_error;
	$result['message'] = implode(' ', array_filter($messages));

	mad_baits_catalogue_log_apply_rollback($product_id, $before, $options, $result);

	return $result;
}
