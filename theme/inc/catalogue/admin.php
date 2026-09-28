<?php
/**
 * Mad Baits Catalogue admin UI.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

add_action('wp_ajax_mad_baits_catalogue_bulk_assign_tags', 'mad_baits_catalogue_ajax_bulk_assign_tags');

/**
 * Create or fetch a product_tag term for catalogue bulk assign.
 *
 * @param string $slug Tag slug.
 * @param string $name Tag name.
 * @return int Term ID or 0.
 */
function mad_baits_catalogue_ensure_product_tag_term($slug, $name) {
	$slug = sanitize_title((string) $slug);
	$name = trim((string) $name);
	if ('' === $slug || '' === $name) {
		return 0;
	}

	$term = get_term_by('slug', $slug, 'product_tag');
	if ($term instanceof WP_Term) {
		return (int) $term->term_id;
	}

	$result = wp_insert_term($name, 'product_tag', array('slug' => $slug));
	if (is_wp_error($result)) {
		$existing = get_term_by('slug', $slug, 'product_tag');
		return $existing instanceof WP_Term ? (int) $existing->term_id : 0;
	}

	return isset($result['term_id']) ? (int) $result['term_id'] : 0;
}

/**
 * Tag options for bulk assign multiselect on audit screen.
 *
 * @return array<int, array{id: int, slug: string, name: string, group: string}>
 */
function mad_baits_catalogue_get_bulk_tag_options() {
	if (class_exists('MBPO_Product_Tags')) {
		return MBPO_Product_Tags::get_assignable_tag_options();
	}

	$options = array();
	$seen    = array();

	foreach (mad_baits_catalogue_get_ranges() as $key => $def) {
		$label = isset($def['label']) ? (string) $def['label'] : (string) $key;
		$slug  = isset($def['slug']) ? sanitize_title((string) $def['slug']) : sanitize_title($key);
		$id    = mad_baits_catalogue_ensure_product_tag_term($slug, $label);
		if ($id < 1) {
			continue;
		}
		$options[] = array(
			'id'    => $id,
			'slug'  => $slug,
			'name'  => $label,
			'group' => 'range',
		);
		$seen[ $slug ] = true;
	}

	$marketing = array(
		'best-seller'         => 'Best Seller',
		'big-carp'            => 'Big Carp',
		'winter-carp-fishing' => 'Winter Carp Fishing',
		'high-attraction'     => 'High Attraction',
		'high-leakage'        => 'High Leakage',
		'cold-water'          => 'Cold Water',
		'food-signal'         => 'Food Signal',
		'nut-based'           => 'Nut Based',
		'fishmeal'            => 'Fishmeal',
		'session-ready'       => 'Session Ready',
		'long-session'        => 'Long Session',
		'confidence-bait'     => 'Confidence Bait',
	);

	foreach ($marketing as $slug => $label) {
		if (isset($seen[ $slug ])) {
			continue;
		}
		$id = mad_baits_catalogue_ensure_product_tag_term($slug, $label);
		if ($id < 1) {
			continue;
		}
		$options[] = array(
			'id'    => $id,
			'slug'  => $slug,
			'name'  => $label,
			'group' => 'marketing',
		);
	}

	usort(
		$options,
		static function ($a, $b) {
			$group_order = array('range' => 0, 'marketing' => 1, 'existing' => 2);
			$ga          = $group_order[ (string) ( $a['group'] ?? '' ) ] ?? 9;
			$gb          = $group_order[ (string) ( $b['group'] ?? '' ) ] ?? 9;
			if ($ga !== $gb) {
				return $ga <=> $gb;
			}
			return strcasecmp((string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ));
		}
	);

	return $options;
}

/**
 * Merge tag term IDs onto products (append).
 *
 * @param int[] $product_ids Product IDs.
 * @param int[] $tag_ids     Tag term IDs.
 * @return array{updated: int, errors: int}
 */
function mad_baits_catalogue_bulk_assign_tags_to_products(array $product_ids, array $tag_ids) {
	if (class_exists('MBPO_Product_Tags')) {
		$result = MBPO_Product_Tags::bulk_assign_tags($product_ids, $tag_ids);
		return array(
			'updated' => (int) ($result['updated'] ?? 0),
			'errors'  => (int) ($result['errors'] ?? 0),
		);
	}

	$tag_ids = array_values(array_unique(array_filter(array_map('absint', $tag_ids))));
	$updated = 0;
	$errors  = 0;

	foreach ($product_ids as $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			continue;
		}
		$current = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
		if (is_wp_error($current)) {
			++$errors;
			continue;
		}
		$merged = array_values(array_unique(array_merge(array_map('absint', (array) $current), $tag_ids)));
		$set    = wp_set_object_terms($product_id, $merged, 'product_tag', false);
		if (is_wp_error($set)) {
			++$errors;
			continue;
		}
		++$updated;
	}

	return array('updated' => $updated, 'errors' => $errors);
}

/**
 * AJAX: bulk assign tags from catalogue audit.
 */
function mad_baits_catalogue_ajax_bulk_assign_tags() {
	check_ajax_referer('mad_baits_catalogue_bulk_tags', 'nonce');

	if (! current_user_can('manage_woocommerce')) {
		wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits')), 403);
	}

	$product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) wp_unslash($_POST['product_ids'])) : array();
	$tag_ids     = isset($_POST['tag_ids']) ? array_map('absint', (array) wp_unslash($_POST['tag_ids'])) : array();

	$product_ids = array_values(array_filter($product_ids));
	$tag_ids     = array_values(array_filter($tag_ids));

	if (empty($product_ids)) {
		wp_send_json_error(array('message' => __('Select at least one product.', 'mad-baits')), 400);
	}
	if (empty($tag_ids)) {
		wp_send_json_error(array('message' => __('Select at least one tag.', 'mad-baits')), 400);
	}

	$result = mad_baits_catalogue_bulk_assign_tags_to_products($product_ids, $tag_ids);

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: 1: products updated, 2: tag count */
				__('Added %2$d tag(s) to %1$d product(s). Refresh the scan to see updated tags.', 'mad-baits'),
				(int) $result['updated'],
				count($tag_ids)
			),
			'result'  => $result,
		)
	);
}

/**
 * Render log section in admin.
 *
 * @param string             $heading Heading.
 * @param array<int, string> $created Created lines.
 * @param array<int, string> $skipped Skipped lines.
 * @param string             $css_class Optional list class.
 * @return void
 */
function mad_baits_catalogue_render_log_section($heading, $created, $skipped, $css_class = '') {
	echo '<h3>' . esc_html($heading) . '</h3>';
	$list_class = 'mad-catalogue-log mad-catalogue-log--created';
	if ($css_class) {
		$list_class .= ' ' . sanitize_html_class($css_class);
	}
	if (! empty($created)) {
		echo '<p><strong>' . esc_html__('Created', 'mad-baits') . ' (' . count($created) . ')</strong></p><ul class="' . esc_attr($list_class) . '">';
		foreach ($created as $line) {
			echo '<li>' . esc_html($line) . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<p>' . esc_html__('Created: none', 'mad-baits') . '</p>';
	}
	if (! empty($skipped)) {
		echo '<p><strong>' . esc_html__('Already existed / skipped', 'mad-baits') . ' (' . count($skipped) . ')</strong></p><ul class="mad-catalogue-log mad-catalogue-log--skipped">';
		foreach ($skipped as $line) {
			echo '<li>' . esc_html($line) . '</li>';
		}
		echo '</ul>';
	}
}

/**
 * Admin page renderer.
 *
 * @return void
 */
function mad_baits_catalogue_setup_render_admin_page() {
	if (! current_user_can('manage_woocommerce')) {
		wp_die(esc_html__('You do not have permission to manage the catalogue.', 'mad-baits'));
	}

	$tab           = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'setup';
	$log           = null;
	$scan          = null;
	$ran           = false;
	$scanned       = false;
	$apply_results = array();
	$applied_count = 0;
	$audit_filter  = isset($_GET['audit_filter']) ? sanitize_key(wp_unslash((string) $_GET['audit_filter'])) : '';

	if ('setup' === $tab && isset($_POST['mad_baits_catalogue_setup']) && check_admin_referer('mad_baits_catalogue_setup')) {
		$log = mad_baits_catalogue_run_structure_setup();
		$ran = true;
	}

	if ('audit' === $tab && isset($_POST['mad_baits_catalogue_scan']) && check_admin_referer('mad_baits_catalogue_scan')) {
		$limit = isset($_POST['scan_limit']) ? absint($_POST['scan_limit']) : 0;
		$scan  = mad_baits_catalogue_scan_product_suggestions($limit);
		set_transient(mad_baits_catalogue_scan_transient_key(), $scan, HOUR_IN_SECONDS);
		$scanned = true;
	}

	if ('audit' === $tab && isset($_POST['mad_baits_catalogue_apply']) && check_admin_referer('mad_baits_catalogue_apply')) {
		$selected = isset($_POST['product_ids']) ? array_map('absint', (array) wp_unslash($_POST['product_ids'])) : array();
		$selected = array_values(array_filter($selected));

		$apply_options = array(
			'apply_categories'   => ! empty($_POST['apply_categories']),
			'append_categories'  => ! isset($_POST['replace_categories']),
			'apply_tags'         => ! empty($_POST['apply_tags']),
			'append_tags'        => ! isset($_POST['replace_tags']),
			'apply_attributes'   => ! empty($_POST['apply_attributes']),
			'append_attributes'  => ! isset($_POST['replace_attributes']),
		);

		$overrides = isset($_POST['product_override']) && is_array($_POST['product_override'])
			? wp_unslash($_POST['product_override'])
			: array();

		foreach ($selected as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1) {
				continue;
			}

			$row_override = isset($overrides[ $product_id ]) && is_array($overrides[ $product_id ])
				? $overrides[ $product_id ]
				: array();

			$parent_name = isset($row_override['parent']) ? sanitize_text_field((string) $row_override['parent']) : '';
			$range_key   = isset($row_override['range']) ? sanitize_text_field((string) $row_override['range']) : '';
			$tags_raw    = isset($row_override['tags']) ? sanitize_text_field((string) $row_override['tags']) : '';

			if ('' === $parent_name) {
				$product    = wc_get_product($product_id);
				$suggestion = $product ? mad_baits_catalogue_suggest_for_title((string) $product->get_name()) : array();
			} else {
				$suggestion = mad_baits_catalogue_build_suggestion_from_selection($parent_name, $range_key);
			}

			if ('' !== $tags_raw) {
				$suggestion['tag_names'] = mad_baits_catalogue_parse_tag_list($tags_raw);
				$suggestion['tags']      = implode(', ', $suggestion['tag_names']);
			} else {
				$product_for_tags = wc_get_product($product_id);
				if ($product_for_tags) {
					$auto_tags = mad_baits_catalogue_suggest_for_title((string) $product_for_tags->get_name());
					if (! empty($auto_tags['tag_names']) && is_array($auto_tags['tag_names'])) {
						$suggestion['tag_names'] = array_values(
							array_unique(
								array_merge(
									isset($suggestion['tag_names']) ? (array) $suggestion['tag_names'] : array(),
									$auto_tags['tag_names']
								)
							)
						);
						$suggestion['tags'] = implode(', ', $suggestion['tag_names']);
					}
					if (! empty($auto_tags['suggested_attributes'])) {
						$suggestion['suggested_attributes'] = $auto_tags['suggested_attributes'];
						$suggestion['attributes_display']   = $auto_tags['attributes_display'];
					}
				}
			}

			$suggestion['category_term_ids'] = mad_baits_catalogue_resolve_category_term_ids(
				(string) ($suggestion['parent_name'] ?? ''),
				(string) ($suggestion['range_key'] ?? '')
			);

			$result          = mad_baits_catalogue_apply_suggestion_to_product($product_id, $suggestion, $apply_options);
			$apply_results[] = $result;
			if (! empty($result['success'])) {
				++$applied_count;
			}
		}

		$scan    = mad_baits_catalogue_scan_product_suggestions(0);
		set_transient(mad_baits_catalogue_scan_transient_key(), $scan, HOUR_IN_SECONDS);
		$scanned = true;
	}

	if ('audit' === $tab && ! $scanned) {
		$cached = get_transient(mad_baits_catalogue_scan_transient_key());
		if (is_array($cached) && ! empty($cached)) {
			$scan    = $cached;
			$scanned = true;
		}
	}

	if ($scanned && is_array($scan) && '' !== $audit_filter) {
		$scan = array_values(
			array_filter(
				$scan,
				static function ($row) use ($audit_filter) {
					switch ($audit_filter) {
						case 'low_confidence':
							return (int) ($row['confidence_score'] ?? 0) < 55;
						case 'missing':
							return '' === trim((string) ($row['current_cats'] ?? '')) || '' === trim((string) ($row['current_tags'] ?? ''));
						case 'duplicates':
							return ! empty($row['is_duplicate']);
						case 'warnings':
							return ! empty($row['warnings']);
						default:
							return true;
					}
				}
			)
		);
	}

	$bulk_tag_options = array();
	if ('audit' === $tab && $scanned && is_array($scan) && ! empty($scan)) {
		$bulk_tag_options = mad_baits_catalogue_get_bulk_tag_options();
	}

	$setup_url = admin_url('admin.php?page=mad-baits-catalogue-setup&tab=setup');
	$audit_url = admin_url('admin.php?page=mad-baits-catalogue-setup&tab=audit');
	$log_url   = admin_url('admin.php?page=mad-baits-catalogue-setup&tab=log');
	$defs      = mad_baits_catalogue_setup_get_definitions();
	?>
	<div class="wrap mad-catalogue-admin">
		<h1><?php esc_html_e('Mad Baits Catalogue', 'mad-baits'); ?></h1>
		<p class="description">
			<?php esc_html_e('Safe, idempotent catalogue tools for a live store. Nothing is deleted. Existing terms and product data are preserved unless you explicitly bulk-apply suggestions.', 'mad-baits'); ?>
		</p>

		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url($setup_url); ?>" class="nav-tab <?php echo 'setup' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e('Structure setup', 'mad-baits'); ?>
			</a>
			<a href="<?php echo esc_url($audit_url); ?>" class="nav-tab <?php echo 'audit' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e('Product audit', 'mad-baits'); ?>
			</a>
			<a href="<?php echo esc_url($log_url); ?>" class="nav-tab <?php echo 'log' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e('Apply log', 'mad-baits'); ?>
			</a>
		</nav>

		<?php
		if (function_exists('mad_baits_supplier_render_admin_toolbar')) {
			mad_baits_supplier_render_admin_toolbar();
		}
		?>

		<?php if ('setup' === $tab) : ?>
			<div class="mad-catalogue-panel" style="margin-top:1.5rem;max-width:820px;">
				<h2><?php esc_html_e('Run catalogue structure', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Creates parent categories, range subcategories, product tags, and global attributes only when missing.', 'mad-baits'); ?></p>
				<ul>
					<li><?php esc_html_e('11 parent categories including Groundbait & Bag Mix, Clothing, and Extras', 'mad-baits'); ?></li>
					<li><?php esc_html_e('Range subcategories use unique slugs (e.g. boilies-asbo) — required by WooCommerce', 'mad-baits'); ?></li>
					<li><?php esc_html_e('Groundbait range children are created only when groundbait/bag mix products exist', 'mad-baits'); ?></li>
				</ul>
				<form method="post">
					<?php wp_nonce_field('mad_baits_catalogue_setup'); ?>
					<p>
						<button type="submit" name="mad_baits_catalogue_setup" value="1" class="button button-primary">
							<?php esc_html_e('Run structure setup', 'mad-baits'); ?>
						</button>
					</p>
				</form>

				<?php
				if (function_exists('mad_baits_supplier_render_sync_panel')) {
					mad_baits_supplier_render_sync_panel();
				}
				?>

				<h3 style="margin-top:2rem;"><?php esc_html_e('Planned structure preview', 'mad-baits'); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e('Parent', 'mad-baits'); ?></th>
							<th><?php esc_html_e('Range children', 'mad-baits'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($defs['parents'] as $parent_name => $parent_data) : ?>
							<tr>
								<td><strong><?php echo esc_html($parent_name); ?></strong><br /><code><?php echo esc_html((string) $parent_data['slug']); ?></code></td>
								<td>
									<?php
									$children = isset($parent_data['children']) ? (array) $parent_data['children'] : array();
									if (empty($children)) {
										esc_html_e('—', 'mad-baits');
									} else {
										$labels = array();
										foreach ($children as $range_key) {
											if (isset($defs['ranges'][ $range_key ]['label'])) {
												$labels[] = (string) $defs['ranges'][ $range_key ]['label'];
											}
										}
										echo esc_html(implode(', ', $labels));
										if (! empty($parent_data['optional_children'])) {
											echo ' <em>(' . esc_html__('optional if no matching products', 'mad-baits') . ')</em>';
										}
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ($ran && is_array($log)) : ?>
				<div class="mad-catalogue-results" style="margin-top:2rem;">
					<h2><?php esc_html_e('Setup log', 'mad-baits'); ?></h2>
					<?php if (! empty($log['errors'])) : ?>
						<div class="notice notice-error">
							<p><strong><?php esc_html_e('Errors', 'mad-baits'); ?></strong></p>
							<ul>
								<?php foreach ($log['errors'] as $err) : ?>
									<li><?php echo esc_html($err); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php else : ?>
						<div class="notice notice-success"><p><?php esc_html_e('Structure setup completed without errors.', 'mad-baits'); ?></p></div>
					<?php endif; ?>

					<?php
					mad_baits_catalogue_render_log_section(__('Categories', 'mad-baits'), $log['categories_created'] ?? array(), $log['categories_skipped'] ?? array());
					mad_baits_catalogue_render_log_section(__('Product tags', 'mad-baits'), $log['tags_created'] ?? array(), $log['tags_skipped'] ?? array());
					mad_baits_catalogue_render_log_section(__('Global attributes', 'mad-baits'), $log['attributes_created'] ?? array(), $log['attributes_skipped'] ?? array());
					mad_baits_catalogue_render_log_section(__('Attribute terms', 'mad-baits'), $log['attribute_terms_created'] ?? array(), $log['attribute_terms_skipped'] ?? array());
					if (! empty($log['audit_warnings'])) {
						mad_baits_catalogue_render_log_section(__('Warnings', 'mad-baits'), $log['audit_warnings'], array(), 'mad-catalogue-log--warning');
					}
					?>
				</div>
			<?php endif; ?>

		<?php elseif ('log' === $tab) : ?>
			<?php $rollback_log = mad_baits_catalogue_get_rollback_log(100); ?>
			<div style="margin-top:1.5rem;">
				<h2><?php esc_html_e('Bulk apply rollback log', 'mad-baits'); ?></h2>
				<p class="description"><?php esc_html_e('Each bulk apply stores the previous category, tag, and attribute state. Use this log to manually restore products if needed.', 'mad-baits'); ?></p>
				<?php if (empty($rollback_log)) : ?>
					<p><?php esc_html_e('No apply operations logged yet.', 'mad-baits'); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e('When', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Product', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Before (IDs)', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Result', 'mad-baits'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($rollback_log as $entry) : ?>
								<tr>
									<td><?php echo esc_html((string) ($entry['timestamp'] ?? '')); ?></td>
									<td>
										<?php
										$pid = (int) ($entry['product_id'] ?? 0);
										$edit = get_edit_post_link($pid, 'raw');
										if ($edit) {
											printf('<a href="%s">#%d</a>', esc_url($edit), $pid);
										} else {
											echo '#' . esc_html((string) $pid);
										}
										?>
									</td>
									<td>
										<?php
										$before = isset($entry['before']) && is_array($entry['before']) ? $entry['before'] : array();
										printf(
											esc_html__('Cats: %1$s · Tags: %2$s', 'mad-baits'),
											esc_html(implode(',', (array) ($before['categories'] ?? array()))),
											esc_html(implode(',', (array) ($before['tags'] ?? array())))
										);
										?>
									</td>
									<td><?php echo esc_html((string) ($entry['result']['message'] ?? '')); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		<?php else : ?>
			<?php $option_lists = mad_baits_catalogue_get_assignment_option_lists(); ?>
			<div class="mad-catalogue-panel" style="margin-top:1.5rem;max-width:960px;">
				<h2><?php esc_html_e('Product audit (dry run)', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('Scans all published products and suggests categories, tags, and attributes. No changes until you confirm bulk apply.', 'mad-baits'); ?></p>
				<form method="post">
					<?php wp_nonce_field('mad_baits_catalogue_scan'); ?>
					<p>
						<label for="scan_limit"><?php esc_html_e('Limit (0 = all published products)', 'mad-baits'); ?></label><br />
						<input type="number" id="scan_limit" name="scan_limit" value="0" min="0" step="1" class="small-text" />
					</p>
					<p>
						<button type="submit" name="mad_baits_catalogue_scan" value="1" class="button button-secondary">
							<?php esc_html_e('Run product scan', 'mad-baits'); ?>
						</button>
					</p>
				</form>
			</div>

			<?php if (! empty($apply_results)) : ?>
				<div class="notice notice-success is-dismissible" style="margin-top:1rem;">
					<p>
						<?php
						printf(
							esc_html__('Updated %1$d of %2$d selected product(s). See Apply log tab for rollback data.', 'mad-baits'),
							(int) $applied_count,
							count($apply_results)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ($scanned && is_array($scan)) : ?>
				<div class="mad-catalogue-filter-bar">
					<a class="button <?php echo '' === $audit_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url($audit_url); ?>"><?php esc_html_e('All', 'mad-baits'); ?></a>
					<a class="button <?php echo 'low_confidence' === $audit_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('audit_filter', 'low_confidence', $audit_url)); ?>"><?php esc_html_e('Low confidence', 'mad-baits'); ?></a>
					<a class="button <?php echo 'missing' === $audit_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('audit_filter', 'missing', $audit_url)); ?>"><?php esc_html_e('Missing data', 'mad-baits'); ?></a>
					<a class="button <?php echo 'duplicates' === $audit_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('audit_filter', 'duplicates', $audit_url)); ?>"><?php esc_html_e('Possible duplicates', 'mad-baits'); ?></a>
					<a class="button <?php echo 'warnings' === $audit_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('audit_filter', 'warnings', $audit_url)); ?>"><?php esc_html_e('Has warnings', 'mad-baits'); ?></a>
				</div>

				<form method="post" class="mad-catalogue-apply-form" style="margin-top:1rem;">
					<?php wp_nonce_field('mad_baits_catalogue_apply'); ?>

					<div class="mad-catalogue-apply-options" style="margin-bottom:1rem;padding:1rem;background:#fff;border:1px solid #c3c4c7;border-radius:4px;">
						<h2 style="margin-top:0;"><?php esc_html_e('Bulk apply (optional)', 'mad-baits'); ?></h2>
						<p>
							<label><input type="checkbox" name="apply_categories" value="1" checked /> <?php esc_html_e('Apply suggested categories', 'mad-baits'); ?></label>
							&nbsp;|&nbsp;
							<label><input type="checkbox" name="replace_categories" value="1" /> <?php esc_html_e('Replace existing categories (default: append)', 'mad-baits'); ?></label>
						</p>
						<p>
							<label><input type="checkbox" name="apply_tags" value="1" checked /> <?php esc_html_e('Apply suggested tags', 'mad-baits'); ?></label>
							&nbsp;|&nbsp;
							<label><input type="checkbox" name="replace_tags" value="1" /> <?php esc_html_e('Replace existing tags (default: append)', 'mad-baits'); ?></label>
						</p>
						<p>
							<label><input type="checkbox" name="apply_attributes" value="1" /> <?php esc_html_e('Apply suggested attributes (simple products only)', 'mad-baits'); ?></label>
							&nbsp;|&nbsp;
							<label><input type="checkbox" name="replace_attributes" value="1" /> <?php esc_html_e('Replace attribute terms (default: append)', 'mad-baits'); ?></label>
						</p>
						<p>
							<button type="submit" name="mad_baits_catalogue_apply" value="1" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('Apply catalogue changes to all checked products? This updates live product data.', 'mad-baits')); ?>');">
								<?php esc_html_e('Apply to selected products', 'mad-baits'); ?>
							</button>
						</p>

						<?php if (! empty($bulk_tag_options)) : ?>
							<hr style="margin:1.25rem 0;" />
							<h3 style="margin:0 0 0.5rem;"><?php esc_html_e('Bulk add tags (multi-select)', 'mad-baits'); ?></h3>
							<p class="description" style="margin-top:0;">
								<?php esc_html_e('Tick products in the table, pick tags below, then assign to WooCommerce or copy into the Suggested tags column first.', 'mad-baits'); ?>
							</p>
							<div class="mad-catalogue-bulk-tags">
								<label for="mad-catalogue-bulk-tag-select" class="mad-catalogue-bulk-tags__label">
									<strong><?php esc_html_e('Tags to add', 'mad-baits'); ?></strong>
								</label>
								<select
									id="mad-catalogue-bulk-tag-select"
									class="mad-catalogue-bulk-tag-select wc-enhanced-select"
									multiple="multiple"
									data-placeholder="<?php esc_attr_e('Search and select tags…', 'mad-baits'); ?>"
									style="min-width:320px;width:100%;max-width:520px;"
								>
									<?php
									$tag_group_labels = array(
										'range'     => __('Range tags', 'mad-baits'),
										'marketing' => __('Marketing tags', 'mad-baits'),
										'existing'  => __('Other tags', 'mad-baits'),
									);
									$tag_group_current = '';
									foreach ($bulk_tag_options as $tag_option) :
										$tag_group = (string) ($tag_option['group'] ?? 'existing');
										if ($tag_group !== $tag_group_current) {
											if ('' !== $tag_group_current) {
												echo '</optgroup>';
											}
											$tag_group_current = $tag_group;
											echo '<optgroup label="' . esc_attr($tag_group_labels[ $tag_group ] ?? ucfirst($tag_group)) . '">';
										}
										$term_id = (int) ($tag_option['id'] ?? 0);
										if ($term_id < 1) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr((string) $term_id); ?>" data-label="<?php echo esc_attr((string) ($tag_option['name'] ?? '')); ?>">
											<?php echo esc_html((string) ($tag_option['name'] ?? '')); ?>
										</option>
									<?php endforeach; ?>
									<?php if ('' !== $tag_group_current) : ?>
										</optgroup>
									<?php endif; ?>
								</select>
								<p class="mad-catalogue-bulk-tags__actions">
									<button
										type="button"
										class="button button-secondary"
										id="mad-catalogue-bulk-assign-tags"
										data-nonce="<?php echo esc_attr(wp_create_nonce('mad_baits_catalogue_bulk_tags')); ?>"
									>
										<?php esc_html_e('Assign tags to selected products', 'mad-baits'); ?>
									</button>
									<button type="button" class="button" id="mad-catalogue-fill-tag-fields">
										<?php esc_html_e('Copy into “Suggested tags” fields only', 'mad-baits'); ?>
									</button>
									<span class="mad-catalogue-bulk-tags__status" id="mad-catalogue-bulk-tags-status" role="status" aria-live="polite"></span>
								</p>
							</div>
						<?php endif; ?>
					</div>

					<h2><?php esc_html_e('Audit results', 'mad-baits'); ?> (<?php echo esc_html((string) count($scan)); ?>)</h2>
					<table class="widefat striped mad-catalogue-suggestions-table">
						<thead>
							<tr>
								<th class="check-column"><input type="checkbox" id="mad-catalogue-select-all" /></th>
								<th><?php esc_html_e('ID', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Product name', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Current categories', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Suggested categories', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Current tags', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Suggested tags', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Suggested attributes', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Score', 'mad-baits'); ?></th>
								<th><?php esc_html_e('Warnings', 'mad-baits'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($scan as $row) : ?>
								<?php
								$s             = $row['suggestion'];
								$pid           = (int) $row['id'];
								$parent_val    = isset($s['parent_name']) ? (string) $s['parent_name'] : '';
								$range_val     = isset($s['range_key']) ? (string) $s['range_key'] : '';
								$tags_val      = isset($s['tag_names']) && is_array($s['tag_names']) ? implode(', ', $s['tag_names']) : '';
								$score         = (int) ($row['confidence_score'] ?? 0);
								$conf_class    = $score >= 85 ? 'high' : ($score >= 55 ? 'medium' : 'low');
								$check_default = $score >= 55 && empty($row['is_duplicate']);
								$range_colour  = $range_val ? mad_baits_catalogue_get_range_colour($range_val) : '';
								?>
								<tr data-confidence="<?php echo esc_attr((string) $score); ?>" <?php echo ! empty($row['is_duplicate']) ? 'style="background:#fff8e5"' : ''; ?>>
									<th scope="row" class="check-column">
										<input type="checkbox" name="product_ids[]" value="<?php echo esc_attr((string) $pid); ?>" class="mad-catalogue-row-check" <?php checked($check_default); ?> />
									</th>
									<td><?php echo esc_html((string) $pid); ?></td>
									<td>
										<strong><?php echo esc_html($row['title']); ?></strong>
										<?php if (function_exists('mad_baits_supplier_render_badge')) : ?>
											<?php echo ' ' . mad_baits_supplier_render_badge($pid); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php endif; ?>
										<?php if (! empty($row['edit_url'])) : ?>
											<br /><a href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'mad-baits'); ?></a>
										<?php endif; ?>
										<?php if ($range_val && $range_colour) : ?>
											<br /><span class="mad-range-pill" style="background:<?php echo esc_attr($range_colour); ?>"><?php echo esc_html($range_val); ?></span>
										<?php endif; ?>
										<div style="margin-top:6px;">
											<select name="product_override[<?php echo esc_attr((string) $pid); ?>][parent]" style="max-width:140px;">
												<option value=""><?php esc_html_e('Auto parent', 'mad-baits'); ?></option>
												<?php foreach ($option_lists['parents'] as $p_key => $p_label) : ?>
													<option value="<?php echo esc_attr($p_key); ?>" <?php selected($parent_val, $p_key); ?>><?php echo esc_html($p_label); ?></option>
												<?php endforeach; ?>
											</select>
											<select name="product_override[<?php echo esc_attr((string) $pid); ?>][range]" style="max-width:120px;">
												<option value=""><?php esc_html_e('Auto range', 'mad-baits'); ?></option>
												<?php foreach ($option_lists['ranges'] as $r_key => $r_label) : ?>
													<option value="<?php echo esc_attr($r_key); ?>" <?php selected($range_val, $r_key); ?>><?php echo esc_html($r_label); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</td>
									<td><?php echo esc_html($row['current_cats'] ?: '—'); ?></td>
									<td><strong><?php echo esc_html($row['suggested_category'] ?? '—'); ?></strong></td>
									<td><?php echo esc_html($row['current_tags'] ?: '—'); ?></td>
									<td>
										<input type="text" class="large-text" name="product_override[<?php echo esc_attr((string) $pid); ?>][tags]" value="<?php echo esc_attr($tags_val); ?>" />
									</td>
									<td><?php echo esc_html($row['suggested_attributes'] ?? '—'); ?></td>
									<td><span class="mad-confidence mad-confidence--<?php echo esc_attr($conf_class); ?>"><?php echo esc_html((string) $score); ?>%</span></td>
									<td class="mad-warning-cell">
										<?php
										if (! empty($row['warnings'])) {
											echo esc_html(implode(' · ', (array) $row['warnings']));
										} else {
											echo '—';
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
