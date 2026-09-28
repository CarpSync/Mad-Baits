<?php
/**
 * Drag-and-drop product order for range tag shops (one list per range).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register admin screen under WooCommerce.
 */
function mad_baits_register_range_product_order_menu() {
	add_submenu_page(
		'woocommerce',
		__('Range Product Order', 'mad-baits'),
		__('Range Product Order', 'mad-baits'),
		'manage_woocommerce',
		'mad-baits-range-product-order',
		'mad_baits_render_range_product_order_admin_page'
	);
}
add_action('admin_menu', 'mad_baits_register_range_product_order_menu', 100);

/**
 * Enqueue sortable scripts on the order screen.
 *
 * @param string $hook_suffix Admin hook suffix.
 */
function mad_baits_range_product_order_admin_assets($hook_suffix) {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	$is_order_screen = ( $screen && 'woocommerce_page_mad-baits-range-product-order' === $screen->id )
		|| false !== strpos((string) $hook_suffix, 'mad-baits-range-product-order');

	if (! $is_order_screen) {
		return;
	}

	$theme_version = (string) wp_get_theme()->get('Version');
	$script_path   = get_theme_file_path('assets/js/mad-range-product-order-admin.js');
	$script_ver    = file_exists($script_path) ? (string) filemtime($script_path) : $theme_version;

	wp_enqueue_script('jquery-ui-sortable');
	wp_enqueue_script(
		'mad-baits-range-product-order',
		get_theme_file_uri('assets/js/mad-range-product-order-admin.js'),
		array('jquery', 'jquery-ui-sortable'),
		$script_ver,
		true
	);

	wp_localize_script(
		'mad-baits-range-product-order',
		'madBaitsRangeProductOrder',
		array(
			'ajaxUrl'            => admin_url('admin-ajax.php'),
			'i18nSaving'         => __('Saving…', 'mad-baits'),
			'i18nSaved'          => __('Saved.', 'mad-baits'),
			'i18nSaveFailed'     => __('Save failed.', 'mad-baits'),
			'i18nSaveFailedRetry' => __('Save failed. Try again.', 'mad-baits'),
		)
	);

	$style_path = get_theme_file_path('assets/css/mad-range-product-order-admin.css');
	wp_enqueue_style(
		'mad-baits-range-product-order',
		get_theme_file_uri('/assets/css/mad-range-product-order-admin.css'),
		array(),
		file_exists($style_path) ? (string) filemtime($style_path) : $theme_version
	);
}
add_action('admin_enqueue_scripts', 'mad_baits_range_product_order_admin_assets');

/**
 * AJAX: save product order for a range.
 */
function mad_baits_ajax_save_range_product_order() {
	check_ajax_referer('mad_baits_range_product_order', 'nonce');

	if (! current_user_can('manage_woocommerce')) {
		wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits')), 403);
	}

	$range_slug  = isset($_POST['range_slug']) ? sanitize_title(wp_unslash((string) $_POST['range_slug'])) : '';
	$product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) wp_unslash($_POST['product_ids'])) : array();

	if ('' === $range_slug) {
		wp_send_json_error(array('message' => __('Choose a range.', 'mad-baits')), 400);
	}

	if (! function_exists('mad_baits_save_range_product_order')) {
		wp_send_json_error(array('message' => __('Range shop module not loaded.', 'mad-baits')), 500);
	}

	$updated = mad_baits_save_range_product_order($range_slug, $product_ids);

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %d: number of products */
				_n('%d product order saved.', '%d product orders saved.', $updated, 'mad-baits'),
				$updated
			),
			'updated' => $updated,
		)
	);
}
add_action('wp_ajax_mad_baits_save_range_product_order', 'mad_baits_ajax_save_range_product_order');

/**
 * Section label for admin list rows.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_get_range_product_order_section_label($product_id) {
	if (! function_exists('mad_baits_resolve_product_range_section_key') || ! function_exists('mad_baits_get_range_product_sections_config')) {
		return '';
	}

	$section_key = mad_baits_resolve_product_range_section_key($product_id);
	if ('' === $section_key) {
		return __('Uncategorised', 'mad-baits');
	}

	$sections = mad_baits_get_range_product_sections_config();

	return (string) ($sections[ $section_key ]['label'] ?? ucfirst($section_key));
}

/**
 * Render admin page.
 */
function mad_baits_render_range_product_order_admin_page() {
	if (! current_user_can('manage_woocommerce')) {
		wp_die(esc_html__('You do not have permission to manage product order.', 'mad-baits'));
	}

	$range_slug = isset($_GET['range']) ? sanitize_title(wp_unslash((string) $_GET['range'])) : '';

	$ranges = function_exists('mad_baits_get_range_tag_catalog') ? mad_baits_get_range_tag_catalog() : array();

	if ('' === $range_slug && ! empty($ranges)) {
		$range_slug = (string) array_key_first($ranges);
	}

	$product_ids = array();
	if ('' !== $range_slug && function_exists('mad_baits_get_range_tag_product_ids')) {
		$product_ids = mad_baits_get_range_tag_product_ids($range_slug);
	}

	$preview_url = '';
	if ('' !== $range_slug && function_exists('mad_baits_get_range_tag_shop_url')) {
		$preview_url = mad_baits_get_range_tag_shop_url($range_slug);
	}

	$range_label = isset($ranges[ $range_slug ]) ? (string) $ranges[ $range_slug ] : $range_slug;
	$ajax_nonce  = wp_create_nonce('mad_baits_range_product_order');
	?>
	<div class="wrap mad-range-product-order">
		<h1><?php esc_html_e('Range Product Order', 'mad-baits'); ?></h1>
		<p class="description">
			<?php esc_html_e('Pick a range, then drag all products into the order you want on the range page. Products still appear under their bait-type headings (Boilies, Liquids, etc.) on the site.', 'mad-baits'); ?>
		</p>

		<?php
		if (function_exists('mad_baits_supplier_render_admin_toolbar')) {
			mad_baits_supplier_render_admin_toolbar();
		}
		?>

		<form method="get" class="mad-range-product-order__filters">
			<input type="hidden" name="page" value="mad-baits-range-product-order" />
			<label>
				<?php esc_html_e('Range', 'mad-baits'); ?>
				<select name="range" id="mad-range-product-order-range">
					<?php foreach ($ranges as $slug => $label) : ?>
						<option value="<?php echo esc_attr((string) $slug); ?>" <?php selected($range_slug, (string) $slug); ?>>
							<?php echo esc_html((string) $label); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ('' !== $preview_url) : ?>
				<a class="button button-secondary" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e('View on site', 'mad-baits'); ?>
				</a>
			<?php endif; ?>
		</form>

		<?php if ('' !== $range_slug) : ?>
			<h2 class="mad-range-product-order__range-title"><?php echo esc_html($range_label); ?></h2>
		<?php endif; ?>

		<?php if (empty($product_ids)) : ?>
			<p class="mad-range-product-order__empty">
				<?php esc_html_e('No published products found for this range. Assign the range product tag (e.g. asbo) to products first.', 'mad-baits'); ?>
			</p>
		<?php else : ?>
			<p class="mad-range-product-order__count">
				<?php
				printf(
					/* translators: %d: product count */
					esc_html(_n('%d product — drag to reorder, then save.', '%d products — drag to reorder, then save.', count($product_ids), 'mad-baits')),
					count($product_ids)
				);
				?>
			</p>
			<ul
				id="mad-range-product-order-list"
				class="mad-range-product-order__list"
				data-range="<?php echo esc_attr($range_slug); ?>"
				data-nonce="<?php echo esc_attr($ajax_nonce); ?>"
			>
				<?php foreach ($product_ids as $product_id) : ?>
					<?php
					$product = wc_get_product($product_id);
					if (! $product) {
						continue;
					}
					$thumb         = $product->get_image('thumbnail', array('class' => 'mad-range-product-order__thumb'));
					$edit          = get_edit_post_link($product_id, 'raw');
					$section_label = mad_baits_get_range_product_order_section_label($product_id);
					?>
					<li class="mad-range-product-order__item" data-product-id="<?php echo esc_attr((string) $product_id); ?>">
						<button type="button" class="mad-range-product-order__handle" aria-label="<?php esc_attr_e('Drag to reorder', 'mad-baits'); ?>">
							<span class="dashicons dashicons-menu" aria-hidden="true"></span>
						</button>
						<?php if ($thumb) : ?>
							<span class="mad-range-product-order__image"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<?php endif; ?>
						<span class="mad-range-product-order__meta">
							<strong><?php echo esc_html($product->get_name()); ?></strong>
							<span class="mad-range-product-order__sku">
								<?php
								echo esc_html(
									$product->get_sku()
										? sprintf(__('SKU: %s', 'mad-baits'), $product->get_sku())
										: sprintf(__('ID: %d', 'mad-baits'), $product_id)
								);
								?>
							</span>
						</span>
						<?php if ('' !== $section_label) : ?>
							<span class="mad-range-product-order__section"><?php echo esc_html($section_label); ?></span>
						<?php endif; ?>
						<?php if ($edit) : ?>
							<a class="button button-small" href="<?php echo esc_url($edit); ?>"><?php esc_html_e('Edit', 'mad-baits'); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="mad-range-product-order__actions">
				<button type="button" class="button button-primary" id="mad-range-product-order-save">
					<?php esc_html_e('Save order', 'mad-baits'); ?>
				</button>
				<span class="mad-range-product-order__status" id="mad-range-product-order-status" role="status" aria-live="polite"></span>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

