<?php
/**
 * Admin UI: product tab, global pools, tools page.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Admin.
 */
final class MBBB_Admin {

	/**
	 * @return void
	 */
	public function init() {
		add_filter('woocommerce_product_data_tabs', array($this, 'product_tab'));
		add_action('woocommerce_product_data_panels', array($this, 'product_panel'));
		// Prefer WC product object save (meta written with the product being persisted).
		add_action('woocommerce_admin_process_product_object', array($this, 'save_product_object'), 20);
		// Fallback for older WC / edge cases that only fire the post-meta hook.
		add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'), 20);
		add_action('admin_menu', array($this, 'register_advanced_menu'), 57);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('wp_ajax_mbbb_admin_preview_slots', array($this, 'ajax_admin_preview_slots'));
	}

	/**
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function product_tab($tabs) {
		$tabs['mbbb'] = array(
			'label'    => __('Bundle Builder', 'mad-baits-bundle-builder'),
			'target'   => 'mbbb_product_data',
			'class'    => array('show_if_simple', 'show_if_variable'),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Product data panel: variation-led Bundle Builder for shop editors.
	 *
	 * Recommended workflow:
	 * 1) Set product type to Variable
	 * 2) Create attributes + variations
	 * 3) Open Bundle Builder tab
	 * 4) Create slots from variations (or apply a preset)
	 * 5) Link each slot to a variation attribute → Update product
	 *
	 * @return void
	 */
	public function product_panel() {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		$plugin     = MBBB_Plugin::instance();
		$settings   = $plugin->get_settings($product_id);
		$slots      = $plugin->migrate_slots_attribute_links($plugin->get_slots($product_id), $product_id);
		$presets    = MBBB_Presets::get_presets();
		$detected   = $product_id ? MBBB_Presets::detect_preset_from_title(get_the_title($product_id)) : '';
		$slot_count = is_array($slots) ? count($slots) : 0;
		$is_enabled = $plugin->is_enabled($product_id);
		$product    = $product_id ? wc_get_product($product_id) : null;
		$is_variable = $product instanceof WC_Product_Variable;
		$variation_attrs = $plugin->get_admin_variation_attributes($product_id);
		$summary    = $plugin->get_admin_slot_summary($product_id);
		$global_style = MBBB_Plugin::get_global_settings()['display_style'];
		?>
		<div id="mbbb_product_data" class="panel woocommerce_options_panel hidden">
			<div class="mbbb-admin-panel">
				<div class="mbbb-admin-panel__intro">
					<h3><?php esc_html_e('Bundle Builder (Advanced)', 'mad-baits-bundle-builder'); ?></h3>
					<p class="description mbbb-admin-panel__workflow">
						<?php
						printf(
							/* translators: %s: Bundle Deals link */
							esc_html__('For everyday deals, use %s instead. This tab is for fine-grained slot editing on individual products.', 'mad-baits-bundle-builder'),
							'<a href="' . esc_url(admin_url('admin.php?page=mbbb-deals')) . '">' . esc_html__('Bundle Deals', 'mad-baits-bundle-builder') . '</a>'
						);
						?>
					</p>
				</div>

				<?php if ($product_id > 0 && ! $is_variable) : ?>
					<div class="mbbb-admin-notice mbbb-admin-notice--info">
						<p><?php esc_html_e('Tip: For most Mad Baits deals, use a Variable product with one attribute per choice (e.g. Split 1, Split 2, Hookbait). Then create slots from those variations below.', 'mad-baits-bundle-builder'); ?></p>
					</div>
				<?php endif; ?>

				<?php if ($product_id > 0 && $is_variable && empty($variation_attrs)) : ?>
					<div class="mbbb-admin-notice mbbb-admin-notice--warn">
						<p><?php esc_html_e('This variable product has no variation attributes yet. Add attributes under the Attributes tab, generate variations, then return here to create bundle slots.', 'mad-baits-bundle-builder'); ?></p>
					</div>
				<?php endif; ?>

				<?php if ($slot_count > 0 && ! $is_enabled) : ?>
					<div class="mbbb-admin-notice mbbb-admin-notice--warn">
						<p><?php esc_html_e('Slots are configured but Bundle Builder is not enabled. Tick “Enable Bundle Builder” below (or customers may see the builder without being able to add to cart).', 'mad-baits-bundle-builder'); ?></p>
					</div>
				<?php endif; ?>

				<div class="mbbb-admin-section mbbb-admin-section--enable">
					<div class="options_group">
						<?php
						woocommerce_wp_checkbox(
							array(
								'id'          => 'mbbb_enabled',
								'label'       => __('Enable Bundle Builder', 'mad-baits-bundle-builder'),
								'description' => __('Turn on the custom choice UI on this product page and allow add-to-cart.', 'mad-baits-bundle-builder'),
								'value'       => $is_enabled ? 'yes' : 'no',
							)
						);
						woocommerce_wp_checkbox(
							array(
								'id'          => 'mbbb_hide_variations',
								'label'       => __('Hide variation dropdowns', 'mad-baits-bundle-builder'),
								'description' => __('Recommended. Hides the default WooCommerce dropdowns so customers only use Bundle Builder.', 'mad-baits-bundle-builder'),
								'value'       => $settings['hide_variations'],
							)
						);
						woocommerce_wp_checkbox(
							array(
								'id'          => 'mbbb_fixed_price',
								'label'       => __('Use fixed product price', 'mad-baits-bundle-builder'),
								'description' => __('Recommended for deals. Customers pay the product price regardless of choices.', 'mad-baits-bundle-builder'),
								'value'       => $settings['fixed_price'],
							)
						);
						woocommerce_wp_select(
							array(
								'id'          => 'mbbb_display_style',
								'label'       => __('Builder display style', 'mad-baits-bundle-builder'),
								'options'     => MBBB_Plugin::display_style_choices(),
								'value'       => $settings['display_style'],
								'description' => sprintf(
									/* translators: %s: global display style label */
									__('Site-wide style is set under WooCommerce → Bundle Deals → Advanced → Settings (currently: %s). That setting overrides this field on the storefront.', 'mad-baits-bundle-builder'),
									MBBB_Plugin::display_style_choices()[ $global_style ] ?? $global_style
								),
							)
						);
						woocommerce_wp_checkbox(
							array(
								'id'          => 'mbbb_sticky_atc',
								'label'       => __('Sticky mobile add-to-cart', 'mad-baits-bundle-builder'),
								'value'       => $settings['sticky_atc'],
							)
						);
						woocommerce_wp_checkbox(
							array(
								'id'          => 'mbbb_show_summary',
								'label'       => __('Show summary panel', 'mad-baits-bundle-builder'),
								'value'       => $settings['show_summary'],
							)
						);
						?>
					</div>
				</div>

				<div class="mbbb-admin-section mbbb-quick-setup">
					<h4><?php esc_html_e('1. Quick setup', 'mad-baits-bundle-builder'); ?></h4>
					<p class="description">
						<?php
						if ($slot_count > 0 && $is_enabled) {
							esc_html_e('This product already has bundle slots. You can keep editing below, create slots from variations, or replace everything with a preset.', 'mad-baits-bundle-builder');
						} elseif ($detected) {
							printf(
								/* translators: %s: preset label */
								esc_html__('Suggested preset for this product title: %s', 'mad-baits-bundle-builder'),
								'<strong>' . esc_html($presets[ $detected ]['label'] ?? $detected) . '</strong>'
							);
						} else {
							esc_html_e('Start from this product’s variations (recommended), or apply a named preset for common deal layouts.', 'mad-baits-bundle-builder');
						}
						?>
					</p>
					<p class="mbbb-quick-setup__actions">
						<button type="button" class="button button-primary" id="mbbb-create-from-variations" <?php disabled(! $is_variable || empty($variation_attrs)); ?>>
							<?php esc_html_e('Create slots from variations', 'mad-baits-bundle-builder'); ?>
						</button>
						<button type="button" class="button" id="mbbb-auto-map-attributes" <?php disabled(! $is_variable || empty($variation_attrs) || $slot_count < 1); ?>>
							<?php esc_html_e('Auto-link slots to attributes', 'mad-baits-bundle-builder'); ?>
						</button>
					</p>
					<p class="description"><?php esc_html_e('“Create slots from variations” makes one slot per variation attribute and links it automatically. Existing slots are replaced — click Update to save.', 'mad-baits-bundle-builder'); ?></p>

					<div class="mbbb-preset-row">
						<p class="form-field">
							<label for="mbbb_apply_preset"><?php esc_html_e('Or apply a preset', 'mad-baits-bundle-builder'); ?></label>
							<select id="mbbb_apply_preset" name="mbbb_apply_preset">
								<option value=""><?php esc_html_e('— Select preset —', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($presets as $id => $preset) : ?>
									<option value="<?php echo esc_attr($id); ?>" <?php selected($detected, $id); ?>><?php echo esc_html($preset['label']); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button" id="mbbb-apply-preset-btn"><?php esc_html_e('Apply Preset', 'mad-baits-bundle-builder'); ?></button>
							<button type="button" class="button button-primary" id="mbbb-quick-enable-preset"><?php esc_html_e('Apply preset & enable', 'mad-baits-bundle-builder'); ?></button>
						</p>
						<p class="mbbb-quick-setup__actions">
							<label class="mbbb-quick-setup__save">
								<input type="checkbox" name="mbbb_apply_preset_on_save" value="1" <?php checked($detected && $slot_count < 1); ?> />
								<?php esc_html_e('Apply selected preset when I save this product', 'mad-baits-bundle-builder'); ?>
							</label>
						</p>
						<p class="description"><?php esc_html_e('Presets fill the slot list. After applying, review the summary table and click Update product to save.', 'mad-baits-bundle-builder'); ?></p>
					</div>
				</div>

				<div class="mbbb-admin-section mbbb-slots-summary-wrap">
					<h4><?php esc_html_e('2. Slot summary', 'mad-baits-bundle-builder'); ?></h4>
					<p class="description"><?php esc_html_e('A clear overview of every bundle choice and which WooCommerce variation attribute it uses. Edit details in the list below.', 'mad-baits-bundle-builder'); ?></p>
					<div class="mbbb-slots-summary__table-wrap">
						<table class="widefat striped mbbb-slots-summary" id="mbbb-slots-summary">
							<thead>
								<tr>
									<th><?php esc_html_e('#', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Slot label', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Key', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Required', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Source', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Linked variation', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Display', 'mad-baits-bundle-builder'); ?></th>
								</tr>
							</thead>
							<tbody id="mbbb-slots-summary-body">
								<?php if (empty($summary)) : ?>
									<tr class="mbbb-slots-summary__empty">
										<td colspan="7"><?php esc_html_e('No slots yet. Create slots from variations or apply a preset.', 'mad-baits-bundle-builder'); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ($summary as $row) : ?>
										<tr>
											<td><?php echo esc_html((string) $row['index']); ?></td>
											<td><?php echo esc_html($row['label']); ?></td>
											<td><code><?php echo esc_html($row['key']); ?></code></td>
											<td><?php echo $row['required'] ? '✓' : '—'; ?></td>
											<td><?php echo esc_html($row['source_label']); ?></td>
											<td><?php echo esc_html($row['attribute_label']); ?></td>
											<td><?php echo esc_html($row['display']); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="mbbb-admin-section mbbb-slots-admin">
					<h4><?php esc_html_e('3. Edit slots', 'mad-baits-bundle-builder'); ?></h4>
					<?php if (MBBB_Plugin::forces_product_attributes()) : ?>
						<p class="description"><?php esc_html_e('Global setting: Manual and Global pool slots still use this product’s variation attributes on the storefront.', 'mad-baits-bundle-builder'); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e('Each slot is one customer choice. Prefer Source = Product attribute and pick the matching variation attribute. Drag to reorder. Expand a row to edit.', 'mad-baits-bundle-builder'); ?></p>
					<div id="mbbb-admin-validation" class="mbbb-admin-validation" hidden></div>
					<div class="mbbb-slots-admin__list-wrap">
						<div id="mbbb-slots-list"></div>
					</div>
					<p class="mbbb-slots-admin__actions">
						<button type="button" class="button button-primary" id="mbbb-add-slot"><?php esc_html_e('Add Slot', 'mad-baits-bundle-builder'); ?></button>
						<button type="button" class="button" id="mbbb-expand-all-slots"><?php esc_html_e('Expand all', 'mad-baits-bundle-builder'); ?></button>
						<button type="button" class="button" id="mbbb-collapse-all-slots"><?php esc_html_e('Collapse all', 'mad-baits-bundle-builder'); ?></button>
					</p>
					<input type="hidden" id="mbbb_slots_json" name="mbbb_slots_json" value="<?php echo esc_attr(wp_json_encode($slots)); ?>" />
				</div>

				<div class="mbbb-admin-section mbbb-customer-preview">
					<h4><?php esc_html_e('4. Customer steps preview', 'mad-baits-bundle-builder'); ?></h4>
					<p class="description"><?php esc_html_e('Live preview of the storefront choice steps from the current slot list. Broken required steps are highlighted before you publish.', 'mad-baits-bundle-builder'); ?></p>
					<div id="mbbb-customer-preview" class="mbbb-customer-preview__panel" aria-live="polite">
						<p class="mbbb-customer-preview__empty"><?php esc_html_e('Add slots to preview the customer steps.', 'mad-baits-bundle-builder'); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: resolve option counts for admin customer-steps preview.
	 *
	 * @return void
	 */
	public function ajax_admin_preview_slots() {
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-bundle-builder')), 403);
		}

		check_ajax_referer('mbbb_admin_preview', 'nonce');

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$raw        = isset($_POST['slots']) ? wp_unslash($_POST['slots']) : '';
		$decoded    = is_string($raw) ? json_decode($raw, true) : $raw;
		if (! is_array($decoded)) {
			wp_send_json_error(array('message' => __('Invalid slots payload.', 'mad-baits-bundle-builder')), 400);
		}

		$plugin = MBBB_Plugin::instance();
		$slots  = $this->sanitize_slots($decoded);
		$steps  = $plugin->build_admin_preview_steps($slots, $product_id);
		$errors = $plugin->validate_slots_for_save($slots, $product_id);

		wp_send_json_success(
			array(
				'steps'  => $steps,
				'errors' => array_values($errors),
			)
		);
	}

	/**
	 * Whether this request already persisted Bundle Builder meta via the product object hook.
	 *
	 * @var bool
	 */
	private $saved_via_product_object = false;

	/**
	 * Preferred save path: attach meta to the WC_Product being saved.
	 *
	 * Flow:
	 * 1) Read enable + settings checkboxes
	 * 2) Optional preset-on-save path (writes slots after object save via apply_preset)
	 * 3) Else decode mbbb_slots_json, sanitize, migrate attribute links, validate
	 * 4) update_meta_data on the product object (persisted by WooCommerce)
	 *
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public function save_product_object($product) {
		if (! $product instanceof WC_Product || ! current_user_can('manage_woocommerce')) {
			return;
		}

		$this->saved_via_product_object = true;
		$this->persist_bundle_meta_from_request($product->get_id(), $product);
	}

	/**
	 * Fallback save when only the post-meta hook fires.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_product_meta($post_id) {
		if ($this->saved_via_product_object || ! current_user_can('manage_woocommerce')) {
			return;
		}
		$this->persist_bundle_meta_from_request(absint($post_id), null);
	}

	/**
	 * Shared save logic for product object + post-meta hooks.
	 *
	 * @param int             $product_id Product ID.
	 * @param WC_Product|null $product    Product object when available.
	 * @return void
	 */
	private function persist_bundle_meta_from_request($product_id, $product = null) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return;
		}

		$plugin   = MBBB_Plugin::instance();
		$enabled  = isset($_POST['mbbb_enabled']) ? 'yes' : 'no';
		$settings = array(
			'hide_variations' => isset($_POST['mbbb_hide_variations']) ? 'yes' : 'no',
			'fixed_price'     => isset($_POST['mbbb_fixed_price']) ? 'yes' : 'no',
			'display_style'   => isset($_POST['mbbb_display_style']) ? MBBB_Plugin::sanitize_display_style(wp_unslash($_POST['mbbb_display_style'])) : MBBB_Plugin::get_global_settings()['display_style'],
			'sticky_atc'      => isset($_POST['mbbb_sticky_atc']) ? 'yes' : 'no',
			'show_summary'    => isset($_POST['mbbb_show_summary']) ? 'yes' : 'no',
		);

		$preset_on_save = ! empty($_POST['mbbb_apply_preset_on_save']);
		$preset_id      = isset($_POST['mbbb_apply_preset']) ? sanitize_key(wp_unslash((string) $_POST['mbbb_apply_preset'])) : '';

		if ($preset_on_save && '' !== $preset_id) {
			// Build preset slots onto the same product object so WC save does not wipe them.
			$preset_slots = MBBB_Presets::get_preset_slots($preset_id);
			if (! empty($preset_slots)) {
				$preset_slots = $plugin->migrate_slots_attribute_links($preset_slots, $product_id);
				$preset_settings = MBBB_Plugin::default_settings();
				$errors          = $plugin->validate_enabled_bundle_config('yes', $preset_slots, $product_id);
				if (! empty($errors)) {
					$this->write_bundle_meta($product_id, $product, $enabled, $settings, null);
					set_transient('mbbb_slot_save_errors_' . get_current_user_id(), $errors, 60);
					return;
				}
				$this->write_bundle_meta($product_id, $product, 'yes', $preset_settings, $preset_slots);
				MBBB_Admin_Setup::ensure_bundle_category($product_id);
				$plugin->log(
					sprintf(
						/* translators: 1: product ID 2: preset id */
						__('Applied preset "%2$s" to product #%1$d on save.', 'mad-baits-bundle-builder'),
						$product_id,
						$preset_id
					)
				);
			} else {
				$this->write_bundle_meta($product_id, $product, $enabled, $settings, null);
			}
			return;
		}

		if (isset($_POST['mbbb_slots_json'])) {
			$raw     = wp_unslash($_POST['mbbb_slots_json']);
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				// Validate against the posted payload first so empty labels are not silently dropped.
				$errors = $plugin->validate_enabled_bundle_config($enabled, $decoded, $product_id);
				if (! empty($errors)) {
					$this->write_bundle_meta($product_id, $product, $enabled, $settings, null);
					set_transient('mbbb_slot_save_errors_' . get_current_user_id(), $errors, 60);
					return;
				}
				$slots = $this->sanitize_slots($decoded);
				$slots = $plugin->migrate_slots_attribute_links($slots, $product_id);
				// Re-check after sanitize/migration (attribute links, unique keys).
				$errors = $plugin->validate_enabled_bundle_config($enabled, $slots, $product_id);
				if (! empty($errors)) {
					$this->write_bundle_meta($product_id, $product, $enabled, $settings, null);
					set_transient('mbbb_slot_save_errors_' . get_current_user_id(), $errors, 60);
					return;
				}
				$this->write_bundle_meta($product_id, $product, $enabled, $settings, $slots);
				return;
			}
		}

		if ('yes' === $enabled) {
			$existing = $plugin->get_slots($product_id);
			$errors   = $plugin->validate_enabled_bundle_config($enabled, $existing, $product_id);
			if (! empty($errors)) {
				set_transient('mbbb_slot_save_errors_' . get_current_user_id(), $errors, 60);
				// Keep previous slots; still persist enable/settings so editors can fix.
				$this->write_bundle_meta($product_id, $product, $enabled, $settings, null);
				return;
			}
		}

		$this->write_bundle_meta($product_id, $product, $enabled, $settings, null);
	}

	/**
	 * Write enabled/settings/slots onto a product object or via plugin helpers.
	 *
	 * @param int                       $product_id Product ID.
	 * @param WC_Product|null           $product    Product object.
	 * @param string                    $enabled    yes|no.
	 * @param array<string, mixed>      $settings   Settings.
	 * @param array<int, array>|null    $slots      Slots or null to leave unchanged.
	 * @return void
	 */
	private function write_bundle_meta($product_id, $product, $enabled, $settings, $slots) {
		$plugin   = MBBB_Plugin::instance();
		$enabled  = MBBB_Plugin::sanitize_yes_no($enabled);
		$defaults = MBBB_Plugin::default_settings();
		$clean    = array(
			'hide_variations' => MBBB_Plugin::sanitize_yes_no($settings['hide_variations'] ?? $defaults['hide_variations']),
			'fixed_price'     => MBBB_Plugin::sanitize_yes_no($settings['fixed_price'] ?? $defaults['fixed_price']),
			'display_style'   => MBBB_Plugin::sanitize_display_style($settings['display_style'] ?? $defaults['display_style']),
			'sticky_atc'      => MBBB_Plugin::sanitize_yes_no($settings['sticky_atc'] ?? $defaults['sticky_atc']),
			'show_summary'    => MBBB_Plugin::sanitize_yes_no($settings['show_summary'] ?? $defaults['show_summary']),
		);

		if ($product instanceof WC_Product) {
			$product->update_meta_data(MBBB_Plugin::META_ENABLED, $enabled);
			$product->update_meta_data(MBBB_Plugin::META_SETTINGS, $clean);
			if (null !== $slots) {
				$product->update_meta_data(MBBB_Plugin::META_SLOTS, MBBB_Plugin::ensure_unique_slot_keys($slots));
			}
			return;
		}

		$plugin->save_product_bundle_meta($product_id, $enabled, $clean, $slots);
	}

	/**
	 * Sanitize slot rows from admin JSON.
	 *
	 * Preserves legacy fields (min/max, pool_id, manual_options, taxonomy IDs)
	 * so existing products and the frontend keep working unchanged.
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_slots($slots) {
		$clean   = array();
		$allowed_sources = array('attribute', 'global_pool', 'manual', 'category', 'tag', 'products');
		$allowed_display = array('cards', 'pills', 'dropdown');

		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$label = isset($slot['label']) ? sanitize_text_field((string) $slot['label']) : '';
			// Keep empty-label rows out of storage; validation surfaces the error in admin JS first.
			if ('' === $label) {
				continue;
			}

			$source = isset($slot['source']) ? sanitize_key((string) $slot['source']) : 'attribute';
			if (! in_array($source, $allowed_sources, true)) {
				$source = 'attribute';
			}

			$display = isset($slot['display']) ? sanitize_key((string) $slot['display']) : 'cards';
			if (! in_array($display, $allowed_display, true)) {
				$display = 'cards';
			}

			$manual_options = array();
			if (isset($slot['manual_options']) && is_array($slot['manual_options'])) {
				foreach ($slot['manual_options'] as $row) {
					if (! is_array($row)) {
						continue;
					}
					$opt_label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
					if ('' === $opt_label) {
						continue;
					}
					$manual_options[] = array(
						'label'      => $opt_label,
						'value'      => isset($row['value']) ? sanitize_title((string) $row['value']) : sanitize_title($opt_label),
						'product_id' => isset($row['product_id']) ? absint($row['product_id']) : 0,
						'image'      => isset($row['image']) ? esc_url_raw((string) $row['image']) : '',
						'active'     => ! array_key_exists('active', $row) || ! empty($row['active']),
					);
				}
			}

			$product_ids = array();
			if (isset($slot['product_ids'])) {
				if (is_string($slot['product_ids'])) {
					$parts = preg_split('/[\s,]+/', $slot['product_ids']);
					$product_ids = array_values(array_filter(array_map('absint', (array) $parts)));
				} else {
					$product_ids = array_values(array_filter(array_map('absint', (array) $slot['product_ids'])));
				}
			}

			$clean[] = array(
				'label'          => $label,
				'key'            => isset($slot['key']) && '' !== trim((string) $slot['key']) ? sanitize_title((string) $slot['key']) : MBBB_Plugin::sanitize_slot_key($label),
				'required'       => ! empty($slot['required']),
				'min'            => isset($slot['min']) ? absint($slot['min']) : 1,
				'max'            => isset($slot['max']) ? max(1, absint($slot['max'])) : 1,
				'help'           => isset($slot['help']) ? sanitize_textarea_field((string) $slot['help']) : '',
				'source'         => $source,
				'pool_id'        => isset($slot['pool_id']) ? sanitize_key((string) $slot['pool_id']) : '',
				'attribute'      => isset($slot['attribute']) ? sanitize_text_field((string) $slot['attribute']) : '',
				'category_id'    => isset($slot['category_id']) ? absint($slot['category_id']) : 0,
				'tag_id'         => isset($slot['tag_id']) ? absint($slot['tag_id']) : 0,
				'product_ids'    => $product_ids,
				'allow_same'     => ! empty($slot['allow_same']),
				'use_images'     => ! empty($slot['use_images']),
				'display'        => $display,
				'manual_options' => $manual_options,
			);
		}
		return MBBB_Plugin::ensure_unique_slot_keys($clean);
	}

	/**
	 * @return void
	 */
	/**
	 * Advanced/developer tools — registered under Bundle Deals menu group.
	 *
	 * @return void
	 */
	public function register_advanced_menu() {
		add_submenu_page(
			'woocommerce',
			__('Advanced bundle deal tools', 'mad-baits-bundle-builder'),
			__('Advanced', 'mad-baits-bundle-builder'),
			'manage_woocommerce',
			'mbbb-tools',
			array($this, 'render_tools_page')
		);
	}

	/**
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_admin_assets($hook) {
		$load = false;
		$product_id = 0;
		if ('post.php' === $hook || 'post-new.php' === $hook) {
			$screen = get_current_screen();
			if ($screen && 'product' === $screen->post_type) {
				$load = true;
				$product_id = isset($_GET['post']) ? absint($_GET['post']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		if ('woocommerce_page_mbbb-tools' === $hook) {
			$load = true;
		}
		if (! $load) {
			return;
		}

		// Surface validation errors from the previous save attempt.
		$save_errors = get_transient('mbbb_slot_save_errors_' . get_current_user_id());
		if (is_array($save_errors) && ! empty($save_errors)) {
			delete_transient('mbbb_slot_save_errors_' . get_current_user_id());
			add_action(
				'admin_notices',
				static function () use ($save_errors) {
					echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__('Bundle Builder could not save slots:', 'mad-baits-bundle-builder') . '</strong></p><ul style="list-style:disc;margin-left:1.5em;">';
					foreach ($save_errors as $error) {
						echo '<li>' . esc_html($error) . '</li>';
					}
					echo '</ul></div>';
				}
			);
		}

		$admin_style_deps = array();
		if (wp_style_is('woocommerce_admin_styles', 'registered')) {
			$admin_style_deps[] = 'woocommerce_admin_styles';
		}
		wp_enqueue_style('mbbb-admin', MBBB_PLUGIN_URL . 'assets/css/mbbb-admin.css', $admin_style_deps, MBBB_VERSION);
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_script('mbbb-admin', MBBB_PLUGIN_URL . 'assets/js/mbbb-admin.js', array('jquery', 'jquery-ui-sortable'), MBBB_VERSION, true);

		$plugin  = MBBB_Plugin::instance();
		$pools   = $plugin->get_pools();
		$presets = MBBB_Presets::get_presets();
		$pools_js = array();
		foreach ($pools as $id => $pool) {
			$pools_js[] = array(
				'id'   => $id,
				'name' => isset($pool['name']) ? (string) $pool['name'] : $id,
			);
		}
		$presets_js = array();
		foreach ($presets as $id => $preset) {
			$presets_js[] = array(
				'id'    => $id,
				'label' => $preset['label'],
				'slots' => $preset['slots'],
			);
		}

		$variation_attrs = $product_id ? $plugin->get_admin_variation_attributes($product_id) : array();
		$from_variations = $product_id ? $plugin->build_slots_from_variation_attributes($product_id) : array();

		wp_localize_script(
			'mbbb-admin',
			'mbbbAdmin',
			array(
				'ajaxUrl'            => admin_url('admin-ajax.php'),
				'previewNonce'       => wp_create_nonce('mbbb_admin_preview'),
				'pools'              => $pools_js,
				'presets'            => $presets_js,
				'variationAttributes'=> $variation_attrs,
				'slotsFromVariations'=> $from_variations,
				'productId'          => $product_id,
				'isVariable'         => $product_id ? (wc_get_product($product_id) instanceof WC_Product_Variable) : false,
				'i18n'               => array(
					'confirmPreset'       => __('Apply this preset? Unsaved slot changes will be replaced.', 'mad-baits-bundle-builder'),
					'confirmFromVars'     => __('Create one slot per variation attribute? This replaces the current slot list. Click Update to save.', 'mad-baits-bundle-builder'),
					'quickSetupDone'      => __('Preset applied and builder enabled. Click Update to save this product.', 'mad-baits-bundle-builder'),
					'fromVarsDone'        => __('Slots created from variations and builder enabled. Review the summary, then click Update to save.', 'mad-baits-bundle-builder'),
					'autoMapDone'         => __('Slots linked to matching variation attributes where possible. Click Update to save.', 'mad-baits-bundle-builder'),
					'noVariations'        => __('No variation attributes found. Add attributes and variations first.', 'mad-baits-bundle-builder'),
					'selectPreset'        => __('Please choose a bundle preset first.', 'mad-baits-bundle-builder'),
					'duplicate'           => __('Duplicate', 'mad-baits-bundle-builder'),
					'remove'              => __('Delete', 'mad-baits-bundle-builder'),
					'slotLabel'           => __('Slot %d', 'mad-baits-bundle-builder'),
					'expand'              => __('Show', 'mad-baits-bundle-builder'),
					'collapse'            => __('Hide', 'mad-baits-bundle-builder'),
					'drag'                => __('Drag to reorder', 'mad-baits-bundle-builder'),
					'missingLabel'        => __('Each slot needs a label.', 'mad-baits-bundle-builder'),
					'duplicateKey'        => __('Duplicate slot key: %s', 'mad-baits-bundle-builder'),
					'requiredNoSource'    => __('Required slot “%s” needs a source (attribute, pool, manual options, or products).', 'mad-baits-bundle-builder'),
					'attrNotFound'        => __('Variation attribute “%s” was not found on this product.', 'mad-baits-bundle-builder'),
					'attrMissing'         => __('Slot “%s” uses Product attribute — pick a linked variation attribute.', 'mad-baits-bundle-builder'),
					'fixBlocked'         => __('Fix the Bundle Builder errors below before saving.', 'mad-baits-bundle-builder'),
					'emptySummary'        => __('No slots yet. Create slots from variations or apply a preset.', 'mad-baits-bundle-builder'),
					'yes'                 => __('Yes', 'mad-baits-bundle-builder'),
					'no'                  => __('No', 'mad-baits-bundle-builder'),
					'sourceAttribute'     => __('Product attribute', 'mad-baits-bundle-builder'),
					'sourcePool'          => __('Global pool', 'mad-baits-bundle-builder'),
					'sourceManual'        => __('Manual options', 'mad-baits-bundle-builder'),
					'sourceCategory'      => __('Product category', 'mad-baits-bundle-builder'),
					'sourceTag'           => __('Product tag', 'mad-baits-bundle-builder'),
					'sourceProducts'      => __('Manual products', 'mad-baits-bundle-builder'),
					'helpLabel'           => __('Customer-facing name for this choice.', 'mad-baits-bundle-builder'),
					'helpKey'             => __('Internal ID used in cart data. Keep stable once the product is live.', 'mad-baits-bundle-builder'),
					'helpRequired'        => __('Customer must pick an option before adding to cart.', 'mad-baits-bundle-builder'),
					'helpSource'          => __('Where options come from. Prefer Product attribute for variable deals.', 'mad-baits-bundle-builder'),
					'helpAttribute'       => __('WooCommerce variation attribute this slot maps to.', 'mad-baits-bundle-builder'),
					'helpDisplay'         => __('How options appear in this slot (cards recommended).', 'mad-baits-bundle-builder'),
					'helpHelp'            => __('Optional tip shown under the slot on the product page.', 'mad-baits-bundle-builder'),
					'helpPool'            => __('Shared option list from WooCommerce → Bundle Deals → Advanced → Global Option Pools.', 'mad-baits-bundle-builder'),
					'helpManual'          => __('One option per line: Label or Label|value', 'mad-baits-bundle-builder'),
					'helpProducts'        => __('Comma-separated product IDs customers can choose from.', 'mad-baits-bundle-builder'),
					'helpCategory'        => __('Product category ID to pull purchasable products from.', 'mad-baits-bundle-builder'),
					'helpTag'             => __('Product tag ID to pull purchasable products from.', 'mad-baits-bundle-builder'),
					'unlinked'            => __('— Not linked —', 'mad-baits-bundle-builder'),
					'enabledNoSlots'      => __('Bundle Builder is enabled but no slots are configured.', 'mad-baits-bundle-builder'),
					'previewEmpty'        => __('Add slots to preview the customer steps.', 'mad-baits-bundle-builder'),
					'previewBroken'       => __('Broken — no options', 'mad-baits-bundle-builder'),
					'previewRequired'     => __('Required', 'mad-baits-bundle-builder'),
					'previewOptional'     => __('Optional', 'mad-baits-bundle-builder'),
					'previewOptions'      => __('Options: %d', 'mad-baits-bundle-builder'),
					'previewDefault'      => __('Default: %s', 'mad-baits-bundle-builder'),
					'previewQty'          => __('Qty %1$d–%2$d', 'mad-baits-bundle-builder'),
					'previewLoading'      => __('Refreshing preview…', 'mad-baits-bundle-builder'),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_tools_page() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'mad-baits-bundle-builder'));
		}

		$tab            = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'products';
		$migrator       = new MBBB_Migrator();
		$plugin         = MBBB_Plugin::instance();
		$global_settings = MBBB_Plugin::get_global_settings();
		$notice         = '';

		if (isset($_POST['mbbb_save_global_settings']) && check_admin_referer('mbbb_save_global_settings')) {
			$plugin->save_global_settings(
				array(
					'display_style'              => isset($_POST['mbbb_global_display_style']) ? wp_unslash($_POST['mbbb_global_display_style']) : 'card',
					'force_product_attributes'   => isset($_POST['mbbb_force_product_attributes']) ? 'yes' : 'no',
					'auto_advance'               => isset($_POST['mbbb_auto_advance']) ? 'yes' : 'no',
					'auto_advance_delay_ms'      => isset($_POST['mbbb_auto_advance_delay_ms']) ? wp_unslash($_POST['mbbb_auto_advance_delay_ms']) : '750',
					'manual_next_slot_threshold' => isset($_POST['mbbb_manual_next_slot_threshold']) ? wp_unslash($_POST['mbbb_manual_next_slot_threshold']) : '8',
				)
			);
			$global_settings = MBBB_Plugin::get_global_settings();
			$notice          = __('Global bundle builder settings saved.', 'mad-baits-bundle-builder');
		}

		if (isset($_POST['mbbb_save_pools']) && check_admin_referer('mbbb_save_pools')) {
			$raw   = isset($_POST['mbbb_pools_json']) ? wp_unslash($_POST['mbbb_pools_json']) : '[]';
			$pools = json_decode($raw, true);
			if (is_array($pools)) {
				$plugin->save_pools($pools);
				$notice = __('Global pools saved.', 'mad-baits-bundle-builder');
			}
		}

		if (isset($_POST['mbbb_import_product']) && check_admin_referer('mbbb_import_product')) {
			$pid    = absint($_POST['product_id'] ?? 0);
			$enable = ! empty($_POST['enable_after_import']);
			if ($migrator->import_to_product($pid, $enable)) {
				$notice = sprintf(__('Imported bundle slots to product #%d.', 'mad-baits-bundle-builder'), $pid);
			} else {
				$notice = __('Import failed — no slots detected.', 'mad-baits-bundle-builder');
			}
		}

		if (isset($_POST['mbbb_bulk_preset']) && check_admin_referer('mbbb_bulk_preset')) {
			$ids    = isset($_POST['bulk_product_ids']) ? array_map('absint', (array) $_POST['bulk_product_ids']) : array();
			$preset = isset($_POST['bulk_preset_id']) ? sanitize_key(wp_unslash($_POST['bulk_preset_id'])) : '';
			$enable = ! empty($_POST['bulk_enable_builder']);
			$count  = 0;
			foreach ($ids as $pid) {
				if ($pid < 1) {
					continue;
				}
				if (MBBB_Admin_Setup::apply_preset($pid, $preset, $enable, true)) {
					++$count;
				}
			}
			$notice = sprintf(__('Applied preset to %d product(s).', 'mad-baits-bundle-builder'), $count);
		}

		if (isset($_POST['mbbb_create_bundle']) && check_admin_referer('mbbb_create_bundle')) {
			$title   = isset($_POST['new_bundle_title']) ? sanitize_text_field(wp_unslash((string) $_POST['new_bundle_title'])) : '';
			$preset  = isset($_POST['new_bundle_preset']) ? sanitize_key(wp_unslash((string) $_POST['new_bundle_preset'])) : '';
			$price   = isset($_POST['new_bundle_price']) ? wc_format_decimal(wp_unslash((string) $_POST['new_bundle_price'])) : '';
			$status  = ! empty($_POST['new_bundle_publish']) ? 'publish' : 'draft';
			$product_id = MBBB_Admin_Setup::create_bundle_product(
				$title,
				$preset,
				array(
					'regular_price' => $price,
					'status'        => $status,
				)
			);
			if ($product_id > 0) {
				wp_safe_redirect(get_edit_post_link($product_id, 'redirect'));
				exit;
			}
			$notice = __('Could not create bundle product. Check the title and preset.', 'mad-baits-bundle-builder');
		}

		if (isset($_POST['mbbb_duplicate_bundle']) && check_admin_referer('mbbb_duplicate_bundle')) {
			$template_id = absint($_POST['template_product_id'] ?? 0);
			$title       = isset($_POST['duplicate_bundle_title']) ? sanitize_text_field(wp_unslash((string) $_POST['duplicate_bundle_title'])) : '';
			$price       = isset($_POST['duplicate_bundle_price']) ? wc_format_decimal(wp_unslash((string) $_POST['duplicate_bundle_price'])) : '';
			$status      = ! empty($_POST['duplicate_bundle_publish']) ? 'publish' : 'draft';
			$product_id  = MBBB_Admin_Setup::duplicate_from_template(
				$template_id,
				$title,
				array(
					'regular_price' => $price,
					'status'        => $status,
				)
			);
			if ($product_id > 0) {
				wp_safe_redirect(get_edit_post_link($product_id, 'redirect'));
				exit;
			}
			$notice = __('Could not duplicate bundle. Check the template product and new title.', 'mad-baits-bundle-builder');
		}

		if (isset($_POST['mbbb_quick_setup_product']) && check_admin_referer('mbbb_quick_setup_product')) {
			$pid    = absint($_POST['quick_setup_product_id'] ?? 0);
			$preset = isset($_POST['quick_setup_preset']) ? sanitize_key(wp_unslash((string) $_POST['quick_setup_preset'])) : '';
			if ($pid > 0 && '' === $preset) {
				$preset_detect = MBBB_Presets::detect_preset_from_title(get_the_title($pid));
				if ($preset_detect) {
					$preset = $preset_detect;
				}
			}
			if ($pid > 0 && '' !== $preset && MBBB_Admin_Setup::apply_preset($pid, $preset, true, true)) {
				$notice = sprintf(__('Bundle setup applied to product #%d.', 'mad-baits-bundle-builder'), $pid);
			} else {
				$notice = __('Quick setup failed. Choose a product and preset.', 'mad-baits-bundle-builder');
			}
		}

		$scan_rows = isset($_POST['mbbb_scan']) && check_admin_referer('mbbb_scan') ? $migrator->scan_bundle_products() : null;
		$logs      = $plugin->get_pools() ? get_option(MBBB_Plugin::OPTION_LOGS, array()) : array();
		$pools     = $plugin->get_pools();
		$presets   = MBBB_Presets::get_presets();
		$templates = MBBB_Admin_Setup::get_template_product_options();
		$bundles   = MBBB_Admin_Setup::get_bundle_product_rows();
		$unconfigured = MBBB_Admin_Setup::get_unconfigured_deal_products();

		$base = admin_url('admin.php?page=mbbb-tools');
		?>
		<div class="wrap mbbb-admin-wrap">
			<h1><?php esc_html_e('Advanced bundle deal tools', 'mad-baits-bundle-builder'); ?></h1>
			<p class="description">
				<?php
				printf(
					/* translators: %s: Bundle Deals admin link */
					esc_html__('For everyday deal management, use %s. This area is for developers and technical maintenance only.', 'mad-baits-bundle-builder'),
					'<a href="' . esc_url(admin_url('admin.php?page=mbbb-deals')) . '"><strong>' . esc_html__('WooCommerce → Bundle Deals', 'mad-baits-bundle-builder') . '</strong></a>'
				);
				?>
			</p>
			<?php if ($notice) : ?>
				<div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'products' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&tab=products'); ?>"><?php esc_html_e('Quick Setup', 'mad-baits-bundle-builder'); ?></a>
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&tab=settings'); ?>"><?php esc_html_e('Settings', 'mad-baits-bundle-builder'); ?></a>
				<a class="nav-tab <?php echo 'migration' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&tab=migration'); ?>"><?php esc_html_e('Migration Tool', 'mad-baits-bundle-builder'); ?></a>
				<a class="nav-tab <?php echo 'pools' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&tab=pools'); ?>"><?php esc_html_e('Global Option Pools', 'mad-baits-bundle-builder'); ?></a>
				<a class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&tab=logs'); ?>"><?php esc_html_e('Logs', 'mad-baits-bundle-builder'); ?></a>
			</nav>

			<?php
			if (function_exists('mad_baits_supplier_render_admin_toolbar')) {
				mad_baits_supplier_render_admin_toolbar();
			}
			?>

			<?php if ('settings' === $tab) : ?>
				<div class="mbbb-panel">
					<h2><?php esc_html_e('Global bundle builder settings', 'mad-baits-bundle-builder'); ?></h2>
					<p><?php esc_html_e('These defaults apply to every product with Bundle Builder enabled unless you change them here.', 'mad-baits-bundle-builder'); ?></p>
					<form method="post">
						<?php wp_nonce_field('mbbb_save_global_settings'); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="mbbb_global_display_style"><?php esc_html_e('Builder display style', 'mad-baits-bundle-builder'); ?></label>
								</th>
								<td>
									<select id="mbbb_global_display_style" name="mbbb_global_display_style">
										<?php foreach (MBBB_Plugin::display_style_choices() as $slug => $label) : ?>
											<option value="<?php echo esc_attr($slug); ?>" <?php selected($global_settings['display_style'], $slug); ?>><?php echo esc_html($label); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e('Card layout shows image-led option tiles (recommended for mobile).', 'mad-baits-bundle-builder'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Option source', 'mad-baits-bundle-builder'); ?></th>
								<td>
									<label for="mbbb_force_product_attributes">
										<input type="checkbox" id="mbbb_force_product_attributes" name="mbbb_force_product_attributes" value="1" <?php checked($global_settings['force_product_attributes'], 'yes'); ?> />
										<?php esc_html_e('Force all bundle slots to use product attributes', 'mad-baits-bundle-builder'); ?>
									</label>
									<p class="description"><?php esc_html_e('When enabled, slots configured as Manual or Global pool read options from this product’s WooCommerce variation attributes instead (matched by slot label). Category, tag, and hand-picked product sources are unchanged.', 'mad-baits-bundle-builder'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Auto-advance after selection', 'mad-baits-bundle-builder'); ?></th>
								<td>
									<label for="mbbb_auto_advance">
										<input type="checkbox" id="mbbb_auto_advance" name="mbbb_auto_advance" value="1" <?php checked($global_settings['auto_advance'], 'yes'); ?> />
										<?php esc_html_e('Move to the next choice automatically after a selection (mobile)', 'mad-baits-bundle-builder'); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="mbbb_auto_advance_delay_ms"><?php esc_html_e('Auto-advance delay (ms)', 'mad-baits-bundle-builder'); ?></label>
								</th>
								<td>
									<input type="number" id="mbbb_auto_advance_delay_ms" name="mbbb_auto_advance_delay_ms" value="<?php echo esc_attr($global_settings['auto_advance_delay_ms']); ?>" min="600" max="900" step="50" class="small-text" />
									<p class="description"><?php esc_html_e('Pause before scrolling to the next slot (600–900 ms). Default 750.', 'mad-baits-bundle-builder'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="mbbb_manual_next_slot_threshold"><?php esc_html_e('Manual “Next choice” threshold', 'mad-baits-bundle-builder'); ?></label>
								</th>
								<td>
									<input type="number" id="mbbb_manual_next_slot_threshold" name="mbbb_manual_next_slot_threshold" value="<?php echo esc_attr($global_settings['manual_next_slot_threshold']); ?>" min="4" max="24" step="1" class="small-text" />
									<p class="description"><?php esc_html_e('Slots with this many options or more show a “Next choice” button instead of auto-advancing (when auto-advance is on).', 'mad-baits-bundle-builder'); ?></p>
								</td>
							</tr>
						</table>
						<p><button type="submit" name="mbbb_save_global_settings" class="button button-primary"><?php esc_html_e('Save Settings', 'mad-baits-bundle-builder'); ?></button></p>
					</form>
				</div>

			<?php elseif ('migration' === $tab) : ?>
				<div class="mbbb-panel">
					<h2><?php esc_html_e('Migration Tool', 'mad-baits-bundle-builder'); ?></h2>
					<p><?php esc_html_e('Scans variable products with legacy bundle attributes. Dry-run first. Import does not delete attributes or variations.', 'mad-baits-bundle-builder'); ?></p>
					<form method="post"><?php wp_nonce_field('mbbb_scan'); ?>
						<button type="submit" name="mbbb_scan" value="1" class="button button-primary"><?php esc_html_e('Scan Bundle Products', 'mad-baits-bundle-builder'); ?></button>
					</form>
					<?php if (is_array($scan_rows)) : ?>
						<table class="widefat striped" style="margin-top:1rem;">
							<thead>
								<tr>
									<th><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Slots detected', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Suggested preset', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Builder enabled', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Actions', 'mad-baits-bundle-builder'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($scan_rows as $row) : ?>
									<tr>
										<td><a href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['title']); ?></a> (#<?php echo esc_html((string) $row['id']); ?>)</td>
										<td><?php echo esc_html((string) $row['slot_count']); ?></td>
										<td><?php echo esc_html($row['preset_label'] ?: '—'); ?></td>
										<td><?php echo $row['enabled'] ? '✓' : '—'; ?></td>
										<td>
											<form method="post" style="display:inline;">
												<?php wp_nonce_field('mbbb_import_product'); ?>
												<input type="hidden" name="product_id" value="<?php echo esc_attr((string) $row['id']); ?>" />
												<label><input type="checkbox" name="enable_after_import" value="1" /> <?php esc_html_e('Enable builder', 'mad-baits-bundle-builder'); ?></label>
												<button type="submit" name="mbbb_import_product" class="button" onclick="return confirm('<?php echo esc_js(__('Import slots to this product?', 'mad-baits-bundle-builder')); ?>');"><?php esc_html_e('Import', 'mad-baits-bundle-builder'); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

			<?php elseif ('pools' === $tab) : ?>
				<div class="mbbb-panel">
					<h2><?php esc_html_e('Global Option Pools', 'mad-baits-bundle-builder'); ?></h2>
					<form method="post">
						<?php wp_nonce_field('mbbb_save_pools'); ?>
						<textarea name="mbbb_pools_json" rows="24" class="large-text code"><?php echo esc_textarea(wp_json_encode($pools, JSON_PRETTY_PRINT)); ?></textarea>
						<p class="description"><?php esc_html_e('Edit pool JSON carefully or use product slot UI with “Global Pool” source. Save to update all bundles using each pool.', 'mad-baits-bundle-builder'); ?></p>
						<p><button type="submit" name="mbbb_save_pools" class="button button-primary"><?php esc_html_e('Save Pools', 'mad-baits-bundle-builder'); ?></button></p>
					</form>
				</div>

			<?php elseif ('logs' === $tab) : ?>
				<div class="mbbb-panel">
					<h2><?php esc_html_e('Operation log', 'mad-baits-bundle-builder'); ?></h2>
					<ul>
						<?php foreach ((array) $logs as $entry) : ?>
							<li><code><?php echo esc_html((string) ($entry['time'] ?? '')); ?></code> — <?php echo esc_html((string) ($entry['message'] ?? '')); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>

			<?php else : ?>
				<div class="mbbb-panel mbbb-panel--grid">
					<div class="mbbb-setup-card">
						<h2><?php esc_html_e('Create a new bundle', 'mad-baits-bundle-builder'); ?></h2>
						<p><?php esc_html_e('Creates a product, applies a preset, enables the builder, and adds it to Bundles & Deals.', 'mad-baits-bundle-builder'); ?></p>
						<form method="post" class="mbbb-setup-form">
							<?php wp_nonce_field('mbbb_create_bundle'); ?>
							<p>
								<label for="mbbb-new-bundle-title"><strong><?php esc_html_e('Product name', 'mad-baits-bundle-builder'); ?></strong></label><br />
								<input type="text" class="regular-text" id="mbbb-new-bundle-title" name="new_bundle_title" required placeholder="<?php esc_attr_e('e.g. 30kg Boilie Deal', 'mad-baits-bundle-builder'); ?>" />
							</p>
							<p>
								<label for="mbbb-new-bundle-preset"><strong><?php esc_html_e('Bundle preset', 'mad-baits-bundle-builder'); ?></strong></label><br />
								<select id="mbbb-new-bundle-preset" name="new_bundle_preset" required>
									<option value=""><?php esc_html_e('— Select preset —', 'mad-baits-bundle-builder'); ?></option>
									<?php foreach ($presets as $id => $preset) : ?>
										<option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($preset['label']); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<label for="mbbb-new-bundle-price"><strong><?php esc_html_e('Price (£)', 'mad-baits-bundle-builder'); ?></strong></label><br />
								<input type="text" class="small-text" id="mbbb-new-bundle-price" name="new_bundle_price" placeholder="128.88" />
							</p>
							<p>
								<label><input type="checkbox" name="new_bundle_publish" value="1" /> <?php esc_html_e('Publish immediately (otherwise saved as draft)', 'mad-baits-bundle-builder'); ?></label>
							</p>
							<p><button type="submit" name="mbbb_create_bundle" class="button button-primary"><?php esc_html_e('Create bundle product', 'mad-baits-bundle-builder'); ?></button></p>
						</form>
					</div>

					<div class="mbbb-setup-card">
						<h2><?php esc_html_e('Duplicate an existing bundle', 'mad-baits-bundle-builder'); ?></h2>
						<p><?php esc_html_e('Copy slot setup from a live bundle, then tweak price or slots on the new product.', 'mad-baits-bundle-builder'); ?></p>
						<form method="post" class="mbbb-setup-form">
							<?php wp_nonce_field('mbbb_duplicate_bundle'); ?>
							<p>
								<label for="mbbb-template-product"><strong><?php esc_html_e('Copy setup from', 'mad-baits-bundle-builder'); ?></strong></label><br />
								<select id="mbbb-template-product" name="template_product_id" required>
									<option value=""><?php esc_html_e('— Select template —', 'mad-baits-bundle-builder'); ?></option>
									<?php foreach ($templates as $pid => $label) : ?>
										<option value="<?php echo esc_attr((string) $pid); ?>"><?php echo esc_html($label); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<label for="mbbb-duplicate-title"><strong><?php esc_html_e('New product name', 'mad-baits-bundle-builder'); ?></strong></label><br />
								<input type="text" class="regular-text" id="mbbb-duplicate-title" name="duplicate_bundle_title" required />
							</p>
							<p>
								<label for="mbbb-duplicate-price"><strong><?php esc_html_e('Price (£)', 'mad-baits-bundle-builder'); ?></strong></label><br />
								<input type="text" class="small-text" id="mbbb-duplicate-price" name="duplicate_bundle_price" placeholder="<?php esc_attr_e('Leave blank to copy template price', 'mad-baits-bundle-builder'); ?>" />
							</p>
							<p>
								<label><input type="checkbox" name="duplicate_bundle_publish" value="1" /> <?php esc_html_e('Publish immediately', 'mad-baits-bundle-builder'); ?></label>
							</p>
							<p><button type="submit" name="mbbb_duplicate_bundle" class="button button-primary"><?php esc_html_e('Duplicate bundle', 'mad-baits-bundle-builder'); ?></button></p>
						</form>
					</div>
				</div>

				<?php if (! empty($unconfigured)) : ?>
					<div class="mbbb-panel">
						<h2><?php esc_html_e('Set up existing deal products', 'mad-baits-bundle-builder'); ?></h2>
						<p><?php esc_html_e('These products are in Bundles & Deals but do not have builder slots yet.', 'mad-baits-bundle-builder'); ?></p>
						<form method="post">
							<?php wp_nonce_field('mbbb_quick_setup_product'); ?>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="mbbb-quick-setup-product"><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<select id="mbbb-quick-setup-product" name="quick_setup_product_id" required>
											<option value=""><?php esc_html_e('— Select product —', 'mad-baits-bundle-builder'); ?></option>
											<?php foreach ($unconfigured as $pid => $label) : ?>
												<option value="<?php echo esc_attr((string) $pid); ?>"><?php echo esc_html($label); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="mbbb-quick-setup-preset"><?php esc_html_e('Preset', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<select id="mbbb-quick-setup-preset" name="quick_setup_preset">
											<option value=""><?php esc_html_e('Auto-detect from product title', 'mad-baits-bundle-builder'); ?></option>
											<?php foreach ($presets as $id => $preset) : ?>
												<option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($preset['label']); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							</table>
							<p><button type="submit" name="mbbb_quick_setup_product" class="button button-primary"><?php esc_html_e('Apply preset & enable builder', 'mad-baits-bundle-builder'); ?></button></p>
						</form>
					</div>
				<?php endif; ?>

				<div class="mbbb-panel">
					<h2><?php esc_html_e('Configured bundle products', 'mad-baits-bundle-builder'); ?></h2>
					<?php if (empty($bundles)) : ?>
						<p><?php esc_html_e('No bundle products configured yet. Use the forms above to create one.', 'mad-baits-bundle-builder'); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Status', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Slots', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Suggested preset', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Builder', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Actions', 'mad-baits-bundle-builder'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($bundles as $row) : ?>
									<tr>
										<td>
											<strong><a href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['title']); ?></a></strong>
											<br /><span class="description">#<?php echo esc_html((string) $row['id']); ?></span>
										</td>
										<td><?php echo esc_html($row['status']); ?></td>
										<td><?php echo esc_html((string) $row['slot_count']); ?></td>
										<td><?php echo esc_html($row['preset_label'] ?: '—'); ?></td>
										<td><?php echo $row['enabled'] ? '✓' : '—'; ?></td>
										<td>
											<a class="button button-small" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'mad-baits-bundle-builder'); ?></a>
											<?php if (! empty($row['view_url'])) : ?>
												<a class="button button-small" href="<?php echo esc_url($row['view_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'mad-baits-bundle-builder'); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div class="mbbb-panel">
					<h2><?php esc_html_e('Bulk apply preset', 'mad-baits-bundle-builder'); ?></h2>
					<p><?php esc_html_e('Apply the same preset to multiple products at once (replaces their slot configuration).', 'mad-baits-bundle-builder'); ?></p>
					<form method="post">
						<?php wp_nonce_field('mbbb_bulk_preset'); ?>
						<p>
							<label for="mbbb-bulk-preset"><strong><?php esc_html_e('Preset', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select id="mbbb-bulk-preset" name="bulk_preset_id" required>
								<option value=""><?php esc_html_e('— Select preset —', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($presets as $id => $preset) : ?>
									<option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($preset['label']); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label><input type="checkbox" name="bulk_enable_builder" value="1" checked /> <?php esc_html_e('Enable bundle builder on selected products', 'mad-baits-bundle-builder'); ?></label>
						</p>
						<fieldset>
							<legend><strong><?php esc_html_e('Products', 'mad-baits-bundle-builder'); ?></strong></legend>
							<div class="mbbb-bulk-products">
								<?php foreach ($templates as $pid => $label) : ?>
									<label class="mbbb-bulk-products__item">
										<input type="checkbox" name="bulk_product_ids[]" value="<?php echo esc_attr((string) $pid); ?>" />
										<?php echo esc_html($label); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</fieldset>
						<p style="margin-top:1rem;"><button type="submit" name="mbbb_bulk_preset" class="button button-primary"><?php esc_html_e('Apply to selected products', 'mad-baits-bundle-builder'); ?></button></p>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
