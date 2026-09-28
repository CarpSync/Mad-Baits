<?php
/**
 * Admin UI for range product order and type recalculation.
 *
 * @package MadBaitsProductOrganiser
 */

defined('ABSPATH') || exit;

class MBPO_Admin {
	/**
	 * @return void
	 */
	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'register_menu'), 99);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('wp_ajax_mbpo_save_range_product_order', array(__CLASS__, 'ajax_save_order'));
		add_action('wp_ajax_mbpo_recalc_product_types', array(__CLASS__, 'ajax_recalc_types'));
		add_action('wp_ajax_mbpo_rebuild_range_sections', array(__CLASS__, 'ajax_rebuild_range_sections'));
		add_action('wp_ajax_mbpo_preview_clean_tags', array(__CLASS__, 'ajax_preview_clean_tags'));
		add_action('wp_ajax_mbpo_apply_clean_tags', array(__CLASS__, 'ajax_apply_clean_tags'));
		add_action('wp_ajax_mbpo_bulk_assign_tags', array(__CLASS__, 'ajax_bulk_assign_tags'));
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__('Product Organiser', 'mad-baits-product-organiser'),
			__('Product Organiser', 'mad-baits-product-organiser'),
			'manage_woocommerce',
			'mad-baits-product-organiser',
			array(__CLASS__, 'render_page')
		);
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets($hook_suffix) {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$is_screen = ( $screen && 'woocommerce_page_mad-baits-product-organiser' === $screen->id )
			|| false !== strpos((string) $hook_suffix, 'mad-baits-product-organiser');

		if (! $is_screen) {
			return;
		}

		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-mouse');
		wp_enqueue_script('jquery-ui-sortable');

		$touch_punch_path = MBPO_PATH . 'assets/js/jquery.ui.touch-punch.min.js';
		if (file_exists($touch_punch_path)) {
			wp_enqueue_script(
				'mbpo-touch-punch',
				MBPO_URL . 'assets/js/jquery.ui.touch-punch.min.js',
				array('jquery-ui-mouse', 'jquery-ui-sortable'),
				'0.2.3',
				true
			);
		}

		if (function_exists('wc_enqueue_js')) {
			wp_enqueue_style('woocommerce_admin_styles');
		}
		if (class_exists('WooCommerce')) {
			wp_enqueue_script('selectWoo');
			wp_enqueue_style('select2');
		}

		$style_path = MBPO_PATH . 'assets/css/mbpo-admin.css';
		wp_enqueue_style(
			'mbpo-admin',
			MBPO_URL . 'assets/css/mbpo-admin.css',
			array(),
			file_exists($style_path) ? (string) filemtime($style_path) : MBPO_VERSION
		);

		$script_deps = array('jquery', 'jquery-ui-sortable');
		if (wp_script_is('mbpo-touch-punch', 'registered')) {
			$script_deps[] = 'mbpo-touch-punch';
		}
		if (wp_script_is('selectWoo', 'registered')) {
			$script_deps[] = 'selectWoo';
		}

		$script_path = MBPO_PATH . 'assets/js/mbpo-admin.js';
		wp_enqueue_script(
			'mbpo-admin',
			MBPO_URL . 'assets/js/mbpo-admin.js',
			$script_deps,
			file_exists($script_path) ? (string) filemtime($script_path) : MBPO_VERSION,
			true
		);

		wp_localize_script(
			'mbpo-admin',
			'mbpoAdmin',
			array(
				'ajaxUrl'             => admin_url('admin-ajax.php'),
				'nonceSave'           => wp_create_nonce('mbpo_save_range_product_order'),
				'nonceRecalc'         => wp_create_nonce('mbpo_recalc_product_types'),
				'nonceRebuild'        => wp_create_nonce('mbpo_rebuild_range_sections'),
				'noncePreviewTags'    => wp_create_nonce('mbpo_preview_clean_tags'),
				'nonceApplyTags'      => wp_create_nonce('mbpo_apply_clean_tags'),
				'nonceBulkAssignTags' => wp_create_nonce('mbpo_bulk_assign_tags'),
				'i18nSaving'          => __('Saving…', 'mad-baits-product-organiser'),
				'i18nAssigningTags'   => __('Assigning tags…', 'mad-baits-product-organiser'),
				'i18nSelectProducts'  => __('Select at least one product.', 'mad-baits-product-organiser'),
				'i18nSelectTags'      => __('Select at least one tag to assign.', 'mad-baits-product-organiser'),
				'i18nConfirmBulkAssign' => __('Add the selected tags to the selected products?', 'mad-baits-product-organiser'),
				'i18nRebuilding'      => __('Rebuilding range sections…', 'mad-baits-product-organiser'),
				'i18nApplyingTags'    => __('Cleaning product tags…', 'mad-baits-product-organiser'),
				'i18nConfirmCleanTags' => __('Remove product-type tags from all listed products? Range and marketing tags will be kept.', 'mad-baits-product-organiser'),
				'i18nSaved'           => __('Saved.', 'mad-baits-product-organiser'),
				'i18nSaveFailed'      => __('Save failed.', 'mad-baits-product-organiser'),
				'i18nSaveFailedRetry' => __('Save failed. Try again.', 'mad-baits-product-organiser'),
				'i18nRecalculating'   => __('Recalculating…', 'mad-baits-product-organiser'),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_save_order() {
		check_ajax_referer('mbpo_save_range_product_order', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-product-organiser')), 403);
		}

		$range_slug  = isset($_POST['range_slug']) ? sanitize_title(wp_unslash((string) $_POST['range_slug'])) : '';
		$product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) wp_unslash($_POST['product_ids'])) : array();

		if ('' === $range_slug) {
			wp_send_json_error(array('message' => __('Choose a range.', 'mad-baits-product-organiser')), 400);
		}

		$count = MBPO_Order::save_order($range_slug, $product_ids);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of products */
					_n('%d product saved in range order.', '%d products saved in range order.', $count, 'mad-baits-product-organiser'),
					$count
				),
				'updated' => $count,
			)
		);
	}

	/**
	 * @return void
	 */
	/**
	 * @return void
	 */
	public static function ajax_preview_clean_tags() {
		check_ajax_referer('mbpo_preview_clean_tags', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-product-organiser')), 403);
		}

		$show_all = ! empty($_POST['show_all']);
		$rows     = MBPO_Product_Tags::build_preview_rows(0, ! $show_all);
		wp_send_json_success(
			array(
				'rows'     => $rows,
				'count'    => count($rows),
				'show_all' => $show_all,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_bulk_assign_tags() {
		check_ajax_referer('mbpo_bulk_assign_tags', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-product-organiser')), 403);
		}

		$product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) wp_unslash($_POST['product_ids'])) : array();
		$tag_ids     = isset($_POST['tag_ids']) ? array_map('absint', (array) wp_unslash($_POST['tag_ids'])) : array();

		if (empty($product_ids)) {
			wp_send_json_error(array('message' => __('Select at least one product.', 'mad-baits-product-organiser')), 400);
		}

		if (empty($tag_ids)) {
			wp_send_json_error(array('message' => __('Select at least one tag.', 'mad-baits-product-organiser')), 400);
		}

		$result = MBPO_Product_Tags::bulk_assign_tags($product_ids, $tag_ids);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: products updated, 2: tag count */
					__('Added %2$d tag(s) to %1$d product(s).', 'mad-baits-product-organiser'),
					(int) $result['updated'],
					count(MBPO_Product_Tags::resolve_tag_ids_for_assignment($tag_ids))
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_apply_clean_tags() {
		check_ajax_referer('mbpo_apply_clean_tags', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-product-organiser')), 403);
		}

		$result = MBPO_Product_Tags::apply_cleanup();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: updated count, 2: error count */
					__('Cleaned tags on %1$d products (%2$d errors).', 'mad-baits-product-organiser'),
					(int) $result['updated'],
					(int) $result['errors']
				),
				'result'  => $result,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_rebuild_range_sections() {
		check_ajax_referer('mbpo_rebuild_range_sections', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-product-organiser')), 403);
		}

		$cleared_meta = MBPO_Product_Types::clear_all_caches();
		delete_option('mad_baits_range_section_order');

		$recalculated = 0;
		if (function_exists('wc_get_products')) {
			$query_args = array(
				'status' => 'publish',
				'limit'  => -1,
				'type'   => array('simple', 'variable', 'grouped', 'external'),
			);
			$ids        = function_exists('mad_baits_supplier_wc_get_product_ids')
				? mad_baits_supplier_wc_get_product_ids($query_args)
				: wc_get_products(array_merge($query_args, array('return' => 'ids')));
			foreach ((array) $ids as $product_id) {
				$product_id = absint($product_id);
				if ($product_id < 1) {
					continue;
				}
				MBPO_Product_Types::resolve_with_reason($product_id, false);
				++$recalculated;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: product count, 2: meta rows cleared */
					__('Rebuilt range sections for %1$d products (cleared %2$d cached meta rows).', 'mad-baits-product-organiser'),
					$recalculated,
					$cleared_meta
				),
				'count'   => $recalculated,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_recalc_types() {
		check_ajax_referer('mbpo_recalc_product_types', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-product-organiser')), 403);
		}

		MBPO_Product_Types::clear_all_caches();

		$recalculated = 0;
		if (function_exists('wc_get_products')) {
			$query_args = array(
				'status' => 'publish',
				'limit'  => -1,
				'type'   => array('simple', 'variable', 'grouped', 'external'),
			);
			$ids        = function_exists('mad_baits_supplier_wc_get_product_ids')
				? mad_baits_supplier_wc_get_product_ids($query_args)
				: wc_get_products(array_merge($query_args, array('return' => 'ids')));
			foreach ((array) $ids as $product_id) {
				$product_id = absint($product_id);
				if ($product_id < 1) {
					continue;
				}
				MBPO_Product_Types::resolve_with_reason($product_id, false);
				++$recalculated;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: product count */
					__('Recalculated type badges for %d products.', 'mad-baits-product-organiser'),
					$recalculated
				),
				'count'   => $recalculated,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_page() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to manage products.', 'mad-baits-product-organiser'));
		}

		$ranges_with_products = MBPO_Ranges::get_ranges_with_products();
		$range_slug           = isset($_GET['range']) ? sanitize_title(wp_unslash((string) $_GET['range'])) : '';

		if ('' === $range_slug && ! empty($ranges_with_products)) {
			$range_slug = (string) array_key_first($ranges_with_products);
		}

		$range_slug = MBPO_Ranges::normalize_range_slug($range_slug);

		$product_ids = array();
		if ('' !== $range_slug) {
			$product_ids = MBPO_Ranges::get_product_ids_for_range($range_slug, true);
		}

		$range_label = isset($ranges_with_products[ $range_slug ]['label'])
			? (string) $ranges_with_products[ $range_slug ]['label']
			: ( MBPO_Ranges::get_range_catalog()[ $range_slug ] ?? $range_slug );

		$preview_url = function_exists('mad_baits_get_range_tag_shop_url')
			? mad_baits_get_range_tag_shop_url($range_slug)
			: '';

		$counts = MBPO_Ranges::get_range_product_counts();
		?>
		<div class="wrap mbpo-admin">
			<h1><?php esc_html_e('Product Organiser', 'mad-baits-product-organiser'); ?></h1>
			<p class="description">
				<?php esc_html_e('Range pages: range = product tag. Section = WooCommerce product category. Product-type tags (Boilies, Liquids, etc.) must not be used for sections.', 'mad-baits-product-organiser'); ?>
			</p>

			<?php
			if (function_exists('mad_baits_supplier_render_admin_toolbar')) {
				mad_baits_supplier_render_admin_toolbar();
			}
			?>

			<?php self::render_tag_cleanup_panel(); ?>

			<div class="mbpo-admin__debug notice notice-info inline">
				<p><strong><?php esc_html_e('Products found per range', 'mad-baits-product-organiser'); ?></strong></p>
				<ul class="mbpo-admin__range-counts">
					<?php foreach ($counts as $slug => $count) : ?>
						<?php
						$label = MBPO_Ranges::get_range_catalog()[ $slug ] ?? $slug;
						$active = ( $slug === $range_slug );
						?>
						<li class="<?php echo esc_attr($active ? 'is-active' : ''); ?>">
							<?php if ($count > 0) : ?>
								<a href="<?php echo esc_url(admin_url('admin.php?page=mad-baits-product-organiser&range=' . rawurlencode((string) $slug))); ?>">
									<?php echo esc_html(sprintf('%s (%d)', $label, $count)); ?>
								</a>
							<?php else : ?>
								<span class="mbpo-admin__range-empty"><?php echo esc_html(sprintf('%s (0)', $label)); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="mbpo-admin__toolbar">
				<form method="get" class="mbpo-admin__filters">
					<input type="hidden" name="page" value="mad-baits-product-organiser" />
					<label>
						<?php esc_html_e('Range / flavour', 'mad-baits-product-organiser'); ?>
						<select name="range" id="mbpo-range-select">
							<?php if (empty($ranges_with_products)) : ?>
								<option value=""><?php esc_html_e('No ranges with products', 'mad-baits-product-organiser'); ?></option>
							<?php else : ?>
								<?php foreach ($ranges_with_products as $slug => $data) : ?>
									<option value="<?php echo esc_attr((string) $slug); ?>" <?php selected($range_slug, (string) $slug); ?>>
										<?php
										echo esc_html(
											sprintf(
												'%s (%d)',
												(string) $data['label'],
												(int) $data['count']
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</label>
					<?php if ('' !== $preview_url) : ?>
						<a class="button button-secondary" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e('View on site', 'mad-baits-product-organiser'); ?>
						</a>
					<?php endif; ?>
				</form>

				<p class="mbpo-admin__recalc">
					<button type="button" class="button" id="mbpo-recalc-types" data-nonce="<?php echo esc_attr(wp_create_nonce('mbpo_recalc_product_types')); ?>">
						<?php esc_html_e('Recalculate Product Type Badges', 'mad-baits-product-organiser'); ?>
					</button>
					<button type="button" class="button button-secondary" id="mbpo-rebuild-sections" data-nonce="<?php echo esc_attr(wp_create_nonce('mbpo_rebuild_range_sections')); ?>">
						<?php esc_html_e('Rebuild Range Sections', 'mad-baits-product-organiser'); ?>
					</button>
					<span class="mbpo-admin__status" id="mbpo-recalc-status" role="status" aria-live="polite"></span>
				</p>
			</div>

			<?php if ('' !== $range_slug) : ?>
				<h2 class="mbpo-admin__range-title"><?php echo esc_html($range_label); ?></h2>
			<?php endif; ?>

			<?php if (empty($product_ids)) : ?>
				<p class="mbpo-admin__empty">
					<?php esc_html_e('No published products matched this range. Check pa_flavour, pa_range attributes, range product tags, or the product name.', 'mad-baits-product-organiser'); ?>
				</p>
			<?php else : ?>
				<?php
				$section_preview = array();
				foreach ($product_ids as $preview_id) {
					$section = MBPO_Sections::resolve_section_key($preview_id);
					if ('' === $section) {
						$section = 'uncategorised';
					}
					if (! isset($section_preview[ $section ])) {
						$section_preview[ $section ] = 0;
					}
					++$section_preview[ $section ];
				}
				?>
				<?php if (! empty($section_preview)) : ?>
					<div class="mbpo-admin__section-debug notice notice-info inline">
						<p><strong><?php esc_html_e('Sections that will render for this range', 'mad-baits-product-organiser'); ?></strong></p>
						<ul>
							<?php
							$section_labels = function_exists('mad_baits_get_range_product_sections_config')
								? mad_baits_get_range_product_sections_config()
								: array();
							foreach ($section_preview as $section_key => $section_count) :
								$section_label = isset($section_labels[ $section_key ]['label'])
									? (string) $section_labels[ $section_key ]['label']
									: ucwords(str_replace('-', ' ', $section_key));
								?>
								<li><?php echo esc_html(sprintf('%s (%d)', $section_label, $section_count)); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<p class="mbpo-admin__count">
					<?php
					printf(
						/* translators: %d: product count */
						esc_html(_n('%d product — drag the row or grip icon, then save.', '%d products — drag any row or grip icon, then save.', count($product_ids), 'mad-baits-product-organiser')),
						count($product_ids)
					);
					?>
				</p>
				<ul
					id="mbpo-product-order-list"
					class="mbpo-admin__list"
					data-range="<?php echo esc_attr($range_slug); ?>"
					data-nonce="<?php echo esc_attr(wp_create_nonce('mbpo_save_range_product_order')); ?>"
				>
					<?php foreach ($product_ids as $product_id) : ?>
						<?php
						$product = wc_get_product($product_id);
						if (! $product) {
							continue;
						}
						$thumb    = $product->get_image('thumbnail', array('class' => 'mbpo-admin__thumb'));
						$edit     = get_edit_post_link($product_id, 'raw');
						$type_label = MBPO_Sections::get_section_label($product_id);
						$reason     = MBPO_Sections::get_section_reason($product_id);
						?>
						<li class="mbpo-admin__item" data-product-id="<?php echo esc_attr((string) $product_id); ?>">
							<span class="mbpo-admin__handle" role="button" tabindex="0" aria-label="<?php esc_attr_e('Drag to reorder', 'mad-baits-product-organiser'); ?>">
								<span class="dashicons dashicons-menu" aria-hidden="true"></span>
							</span>
							<?php if ($thumb) : ?>
								<span class="mbpo-admin__image"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php endif; ?>
							<span class="mbpo-admin__meta">
								<strong><?php echo esc_html($product->get_name()); ?></strong>
								<?php if (function_exists('mad_baits_supplier_render_badge')) : ?>
									<?php echo ' ' . mad_baits_supplier_render_badge($product_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
								<span class="mbpo-admin__id"><?php echo esc_html(sprintf(__('ID: %d', 'mad-baits-product-organiser'), $product_id)); ?></span>
							</span>
							<?php if ('' !== $type_label) : ?>
								<span class="mbpo-admin__badge-wrap">
									<span class="mbpo-admin__badge"><?php echo esc_html($type_label); ?></span>
									<?php if ('' !== $reason) : ?>
										<span class="mbpo-admin__reason"><?php echo esc_html($reason); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<?php if ($edit) : ?>
								<a class="button button-small" href="<?php echo esc_url($edit); ?>"><?php esc_html_e('Edit', 'mad-baits-product-organiser'); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="mbpo-admin__actions">
					<button type="button" class="button button-primary" id="mbpo-save-order">
						<?php esc_html_e('Save order for this range', 'mad-baits-product-organiser'); ?>
					</button>
					<span class="mbpo-admin__status" id="mbpo-save-status" role="status" aria-live="polite"></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tag cleanup preview + apply UI.
	 *
	 * @return void
	 */
	public static function render_tag_cleanup_panel() {
		$preview_rows    = MBPO_Product_Tags::build_preview_rows(0, true);
		$preview_count   = count($preview_rows);
		$assignable_tags = MBPO_Product_Tags::get_assignable_tag_options();
		$group_labels    = array(
			'range'     => __('Range tags', 'mad-baits-product-organiser'),
			'marketing' => __('Marketing / search tags', 'mad-baits-product-organiser'),
			'existing'  => __('Other tags (safe)', 'mad-baits-product-organiser'),
		);
		?>
		<div class="mbpo-admin__tag-cleanup">
			<h2><?php esc_html_e('Clean Product Tags', 'mad-baits-product-organiser'); ?></h2>
			<p class="description">
				<?php esc_html_e('Removes product-type tags (Boilies, Liquids, Pellets, etc.) and keeps range tags (ASBO, Pandemic, …) plus marketing tags (Best Seller, Big Carp, …).', 'mad-baits-product-organiser'); ?>
			</p>
			<p class="mbpo-admin__tag-actions">
				<button type="button" class="button" id="mbpo-refresh-tag-preview" data-nonce="<?php echo esc_attr(wp_create_nonce('mbpo_preview_clean_tags')); ?>">
					<?php esc_html_e('Refresh preview', 'mad-baits-product-organiser'); ?>
				</button>
				<label class="mbpo-admin__show-all">
					<input type="checkbox" id="mbpo-tag-show-all" />
					<?php esc_html_e('Show all products', 'mad-baits-product-organiser'); ?>
				</label>
				<button type="button" class="button button-primary" id="mbpo-apply-clean-tags" data-nonce="<?php echo esc_attr(wp_create_nonce('mbpo_apply_clean_tags')); ?>" <?php disabled($preview_count < 1); ?>>
					<?php esc_html_e('Apply tag cleanup', 'mad-baits-product-organiser'); ?>
				</button>
				<span class="mbpo-admin__status" id="mbpo-tag-cleanup-status" role="status" aria-live="polite"></span>
			</p>

			<div class="mbpo-admin__bulk-assign">
				<h3><?php esc_html_e('Bulk assign tags', 'mad-baits-product-organiser'); ?></h3>
				<p class="description"><?php esc_html_e('Select products in the table below, choose tags, then assign. Existing tags are kept (merged).', 'mad-baits-product-organiser'); ?></p>
				<div class="mbpo-admin__bulk-assign-row">
					<label for="mbpo-bulk-tag-select" class="mbpo-admin__bulk-label">
						<?php esc_html_e('Tags to add', 'mad-baits-product-organiser'); ?>
					</label>
					<select id="mbpo-bulk-tag-select" class="mbpo-admin__tag-multiselect wc-enhanced-select" multiple="multiple" data-placeholder="<?php esc_attr_e('Choose range or marketing tags…', 'mad-baits-product-organiser'); ?>">
						<?php
						$current_group = '';
						foreach ($assignable_tags as $tag_option) :
							$group = (string) ($tag_option['group'] ?? 'existing');
							if ($group !== $current_group) {
								if ('' !== $current_group) {
									echo '</optgroup>';
								}
								$current_group = $group;
								echo '<optgroup label="' . esc_attr($group_labels[ $group ] ?? ucfirst($group)) . '">';
							}
							$value = (int) ($tag_option['id'] ?? 0);
							if ($value < 1) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr((string) $value); ?>" data-slug="<?php echo esc_attr((string) ($tag_option['slug'] ?? '')); ?>">
								<?php echo esc_html((string) ($tag_option['name'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
						<?php if ('' !== $current_group) : ?>
							</optgroup>
						<?php endif; ?>
					</select>
					<button type="button" class="button button-secondary" id="mbpo-bulk-assign-tags" data-nonce="<?php echo esc_attr(wp_create_nonce('mbpo_bulk_assign_tags')); ?>">
						<?php esc_html_e('Assign to selected products', 'mad-baits-product-organiser'); ?>
					</button>
				</div>
			</div>

			<p class="mbpo-admin__tag-summary">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: product count */
						_n('%d product has product-type tags to remove.', '%d products have product-type tags to remove.', $preview_count, 'mad-baits-product-organiser'),
						$preview_count
					)
				);
				?>
			</p>
			<div class="mbpo-admin__tag-table-wrap" id="mbpo-tag-preview-wrap">
				<table class="widefat striped mbpo-admin__tag-table">
					<thead>
						<tr>
							<th class="check-column">
								<input type="checkbox" id="mbpo-tag-select-all" aria-label="<?php esc_attr_e('Select all products', 'mad-baits-product-organiser'); ?>" />
							</th>
							<th><?php esc_html_e('Product', 'mad-baits-product-organiser'); ?></th>
							<th><?php esc_html_e('Current tags', 'mad-baits-product-organiser'); ?></th>
							<th><?php esc_html_e('Tags to remove', 'mad-baits-product-organiser'); ?></th>
							<th><?php esc_html_e('Tags to keep', 'mad-baits-product-organiser'); ?></th>
						</tr>
					</thead>
					<tbody id="mbpo-tag-preview-body">
						<?php if (empty($preview_rows)) : ?>
							<tr>
								<td colspan="5"><?php esc_html_e('No product-type tags found on published products.', 'mad-baits-product-organiser'); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ($preview_rows as $row) : ?>
								<tr data-product-id="<?php echo esc_attr((string) (int) $row['id']); ?>">
									<th scope="row" class="check-column">
										<input type="checkbox" class="mbpo-tag-product-cb" value="<?php echo esc_attr((string) (int) $row['id']); ?>" />
									</th>
									<td>
										<strong><?php echo esc_html((string) $row['name']); ?></strong>
										<span class="mbpo-admin__id"><?php echo esc_html(sprintf('#%d', (int) $row['id'])); ?></span>
									</td>
									<td><?php echo esc_html(implode(', ', (array) $row['current'])); ?></td>
									<td class="mbpo-admin__tag-remove"><?php echo esc_html(implode(', ', (array) $row['remove'])); ?></td>
									<td><?php echo esc_html(implode(', ', (array) $row['keep'])); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
