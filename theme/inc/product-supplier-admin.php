<?php
/**
 * Supplier admin UI: toolbar, badges, product list filters, bulk sync.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Enqueue supplier admin styles.
 *
 * @param string $hook_suffix Hook suffix.
 * @return void
 */
function mad_baits_supplier_admin_enqueue($hook_suffix) {
	if (! mad_baits_supplier_is_operational_admin_screen()) {
		return;
	}

	$css = '
		.mad-supplier-toolbar { margin: 12px 0 16px; padding: 12px 14px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
		.mad-supplier-toolbar__row { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; }
		.mad-supplier-toolbar__label { font-weight: 600; margin-right: 4px; }
		.mad-supplier-toolbar__quick .button.is-active { background: #2271b1; color: #fff; border-color: #2271b1; }
		.mad-supplier-toolbar__include { margin-left: auto; }
		.mad-supplier-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; line-height: 1.4; white-space: nowrap; }
		.column-mad_supplier { width: 118px; }
	';

	$theme_version = wp_get_theme()->get('Version');
	$theme_version = is_string($theme_version) && '' !== $theme_version ? $theme_version : '1.0.0';
	wp_register_style('mad-baits-supplier-admin', false, array(), $theme_version);
	wp_enqueue_style('mad-baits-supplier-admin');
	wp_add_inline_style('mad-baits-supplier-admin', $css);
}
add_action('admin_enqueue_scripts', 'mad_baits_supplier_admin_enqueue');

/**
 * Handle supplier filter + sync POST on catalogue setup.
 *
 * @return void
 */
function mad_baits_supplier_admin_handle_actions() {
	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	if (isset($_GET['mad_supplier_filter'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		mad_baits_supplier_save_admin_filter_mode(mad_baits_supplier_get_admin_filter_mode());
	}

	if (
		isset($_POST['mad_baits_supplier_sync'])
		&& isset($_POST['_wpnonce'])
		&& wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_wpnonce'])), 'mad_baits_supplier_sync')
	) {
		$stats = mad_baits_supplier_sync_all_products(false);
		set_transient(
			'mad_baits_supplier_sync_notice_' . get_current_user_id(),
			$stats,
			60
		);
		wp_safe_redirect(remove_query_arg(array('mad_baits_supplier_synced')));
		exit;
	}
}
add_action('admin_init', 'mad_baits_supplier_admin_handle_actions');

/**
 * Build URL preserving current page args with supplier filter.
 *
 * @param string               $mode   Filter mode or supplier slug.
 * @param array<string, mixed> $extra  Extra query args.
 * @return string
 */
function mad_baits_supplier_admin_filter_url($mode, array $extra = array()) {
	$args = array_merge($_GET, $extra); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	unset($args['mad_include_external']);
	$args['mad_supplier_filter'] = sanitize_key((string) $mode);

	return esc_url(add_query_arg(array_map('sanitize_text_field', $args), admin_url('admin.php')));
}

/**
 * Render supplier filter toolbar for Mad Baits admin tools.
 *
 * @param array<string, mixed> $args Options: base_url, show_include_toggle, description.
 * @return void
 */
function mad_baits_supplier_render_admin_toolbar(array $args = array()) {
	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	$args = wp_parse_args(
		$args,
		array(
			'base_url'             => '',
			'show_include_toggle'  => true,
			'description'          => __('Operational tools default to Mad Baits products only. External supplier stock is hidden unless you change the filter.', 'mad-baits'),
		)
	);

	$current   = mad_baits_supplier_get_admin_filter_mode();
	$base_args = array();
	if ('' !== (string) $args['base_url']) {
		$parsed = wp_parse_url((string) $args['base_url']);
		if (isset($parsed['query'])) {
			parse_str((string) $parsed['query'], $base_args);
		}
	} elseif (isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base_args['page'] = sanitize_key(wp_unslash((string) $_GET['page']));
		if (isset($_GET['tab'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$base_args['tab'] = sanitize_key(wp_unslash((string) $_GET['tab']));
		}
		if (isset($_GET['range'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$base_args['range'] = sanitize_title(wp_unslash((string) $_GET['range']));
		}
	}

	$link = static function ($mode) use ($base_args) {
		return esc_url(add_query_arg(array_merge($base_args, array('mad_supplier_filter' => $mode)), admin_url('admin.php')));
	};

	?>
	<div class="mad-supplier-toolbar">
		<?php if (! empty($args['description'])) : ?>
			<p class="description" style="margin-top:0;"><?php echo esc_html((string) $args['description']); ?></p>
		<?php endif; ?>
		<div class="mad-supplier-toolbar__row mad-supplier-toolbar__quick">
			<span class="mad-supplier-toolbar__label"><?php esc_html_e('Supplier view', 'mad-baits'); ?></span>
			<a class="button <?php echo 'core' === $current ? 'is-active' : ''; ?>" href="<?php echo $link('core'); ?>">
				<?php esc_html_e('Mad Baits only', 'mad-baits'); ?>
			</a>
			<a class="button <?php echo 'external' === $current ? 'is-active' : ''; ?>" href="<?php echo $link('external'); ?>">
				<?php esc_html_e('External suppliers only', 'mad-baits'); ?>
			</a>
			<a class="button <?php echo 'all' === $current ? 'is-active' : ''; ?>" href="<?php echo $link('all'); ?>">
				<?php esc_html_e('All products', 'mad-baits'); ?>
			</a>
			<label>
				<span class="screen-reader-text"><?php esc_html_e('Supplier', 'mad-baits'); ?></span>
				<select onchange="if (this.value) { window.location.href = this.value; }">
					<option value=""><?php esc_html_e('Specific supplier…', 'mad-baits'); ?></option>
					<?php foreach (mad_baits_supplier_get_registry() as $slug => $data) : ?>
						<option value="<?php echo esc_url($link((string) $slug)); ?>" <?php selected($current, (string) $slug); ?>>
							<?php echo esc_html((string) ($data['label'] ?? $slug)); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if (! empty($args['show_include_toggle'])) : ?>
				<label class="mad-supplier-toolbar__include">
					<input type="checkbox" disabled <?php checked('all' === $current); ?> />
					<?php esc_html_e('Include external supplier products', 'mad-baits'); ?>
					<span class="description"><?php esc_html_e('(use “All products” or External filter)', 'mad-baits'); ?></span>
				</label>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Supplier column on WooCommerce products list.
 *
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function mad_baits_supplier_product_list_column($columns) {
	$new = array();
	foreach ($columns as $key => $label) {
		$new[ $key ] = $label;
		if ('name' === $key) {
			$new['mad_supplier'] = __('Supplier', 'mad-baits');
		}
	}
	return $new;
}
add_filter('manage_edit-product_columns', 'mad_baits_supplier_product_list_column', 20);

/**
 * Render supplier column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function mad_baits_supplier_product_list_column_content($column, $post_id) {
	if ('mad_supplier' !== $column) {
		return;
	}
	echo mad_baits_supplier_render_badge($post_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('manage_product_posts_custom_column', 'mad_baits_supplier_product_list_column_content', 10, 2);

/**
 * Supplier dropdown on products list.
 *
 * @param string $post_type Post type.
 * @return void
 */
function mad_baits_supplier_restrict_manage_posts($post_type) {
	if ('product' !== $post_type || ! current_user_can('manage_woocommerce')) {
		return;
	}

	$current = isset($_GET['mad_supplier_filter']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_key(wp_unslash((string) $_GET['mad_supplier_filter']))
		: '';

	echo '<select name="mad_supplier_filter" id="mad_supplier_filter">';
	echo '<option value="">' . esc_html__('All suppliers (list)', 'mad-baits') . '</option>';
	echo '<option value="core"' . selected($current, 'core', false) . '>' . esc_html__('Mad Baits only', 'mad-baits') . '</option>';
	echo '<option value="external"' . selected($current, 'external', false) . '>' . esc_html__('External suppliers', 'mad-baits') . '</option>';
	foreach (mad_baits_supplier_get_registry() as $slug => $data) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr((string) $slug),
			selected($current, (string) $slug, false),
			esc_html((string) ($data['label'] ?? $slug))
		);
	}
	echo '</select>';
}
add_action('restrict_manage_posts', 'mad_baits_supplier_restrict_manage_posts', 15);

/**
 * Apply supplier filter to main products admin query.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function mad_baits_supplier_products_parse_query($query) {
	if (! is_admin() || ! $query->is_main_query() || 'product' !== $query->get('post_type')) {
		return;
	}
	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	$mode = isset($_GET['mad_supplier_filter']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_key(wp_unslash((string) $_GET['mad_supplier_filter']))
		: '';

	if ('' === $mode || 'all' === $mode) {
		return;
	}

	$merged = mad_baits_supplier_merge_wc_product_args(
		array(
			'tax_query' => (array) $query->get('tax_query'),
		),
		$mode
	);

	if (! empty($merged['tax_query'])) {
		$query->set('tax_query', $merged['tax_query']);
	}
}
add_action('parse_query', 'mad_baits_supplier_products_parse_query', 15);

/**
 * Product edit: supplier metabox.
 *
 * @return void
 */
function mad_baits_supplier_register_product_metabox() {
	add_meta_box(
		'mad-baits-product-supplier',
		__('Supplier', 'mad-baits'),
		'mad_baits_supplier_render_product_metabox',
		'product',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'mad_baits_supplier_register_product_metabox');

/**
 * @param WP_Post $post Post.
 * @return void
 */
function mad_baits_supplier_render_product_metabox($post) {
	$current = mad_baits_supplier_get_product_slug((int) $post->ID);
	wp_nonce_field('mad_baits_save_product_supplier', 'mad_baits_product_supplier_nonce');
	?>
	<p>
		<select name="mad_product_supplier" id="mad_product_supplier" class="widefat">
			<?php foreach (mad_baits_supplier_get_registry() as $slug => $data) : ?>
				<option value="<?php echo esc_attr((string) $slug); ?>" <?php selected($current, (string) $slug); ?>>
					<?php echo esc_html((string) ($data['label'] ?? $slug)); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p><?php echo mad_baits_supplier_render_badge($current); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<?php
}

/**
 * Save product supplier from edit screen.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function mad_baits_supplier_save_product_metabox($post_id) {
	if (
		! isset($_POST['mad_baits_product_supplier_nonce'])
		|| ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mad_baits_product_supplier_nonce'])), 'mad_baits_save_product_supplier')
	) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}
	if (! isset($_POST['mad_product_supplier'])) {
		return;
	}

	$slug = sanitize_title(wp_unslash((string) $_POST['mad_product_supplier']));
	mad_baits_supplier_assign_product($post_id, $slug);
}
add_action('save_post_product', 'mad_baits_supplier_save_product_metabox');

/**
 * Render supplier sync panel (catalogue setup tab).
 *
 * @return void
 */
function mad_baits_supplier_render_sync_panel() {
	if (! current_user_can('manage_woocommerce')) {
		return;
	}

	$notice_key = 'mad_baits_supplier_sync_notice_' . get_current_user_id();
	$stats      = get_transient($notice_key);
	if (is_array($stats)) {
		delete_transient($notice_key);
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: mad baits count, 2: atomic count, 3: total */
					__('Supplier sync complete: %1$d Mad Baits, %2$d Atomic Tackle (%3$d products processed).', 'mad-baits'),
					(int) ($stats['mad_baits'] ?? 0),
					(int) ($stats['atomic'] ?? 0),
					(int) ($stats['total'] ?? 0)
				)
			)
		);
	}

	$preview = mad_baits_supplier_sync_all_products(true);
	?>
	<h3 style="margin-top:2rem;"><?php esc_html_e('Product suppliers', 'mad-baits'); ?></h3>
	<p class="description">
		<?php esc_html_e('Separates Mad Baits catalogue work from external supplier products (e.g. Atomic Tackle). Detection uses the Atomic Tackle category and product categories.', 'mad-baits'); ?>
	</p>
	<ul>
		<li><?php echo esc_html(sprintf(__('Would assign Mad Baits: %d', 'mad-baits'), (int) ($preview['mad_baits'] ?? 0))); ?></li>
		<li><?php echo esc_html(sprintf(__('Would assign Atomic Tackle: %d', 'mad-baits'), (int) ($preview['atomic'] ?? 0))); ?></li>
	</ul>
	<form method="post">
		<?php wp_nonce_field('mad_baits_supplier_sync'); ?>
		<p>
			<button type="submit" name="mad_baits_supplier_sync" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Assign supplier terms to all products based on detection rules?', 'mad-baits')); ?>');">
				<?php esc_html_e('Sync supplier assignments', 'mad-baits'); ?>
			</button>
		</p>
	</form>
	<?php
}
