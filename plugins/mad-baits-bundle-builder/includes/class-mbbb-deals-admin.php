<?php
/**
 * Dedicated Bundle Deals admin for shop managers.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Bundle Deals list + simplified deal editor.
 */
final class MBBB_Deals_Admin {

	/**
	 * @return void
	 */
	public function init() {
		// Must run after WooCommerce registers its menu (priority 9).
		add_action('admin_menu', array($this, 'register_menu'), 56);
		add_action('admin_menu', array($this, 'reorder_deals_menu'), 999);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_init', array($this, 'maybe_redirect_legacy_urls'), 1);
		add_action('admin_init', array($this, 'handle_actions'));
		add_action('wp_ajax_mbbb_deal_preview', array($this, 'ajax_preview'));
		add_action('wp_ajax_mbbb_pool_product_suggest', array($this, 'ajax_pool_product_suggest'));
	}

	/**
	 * Register client-friendly Bundle Deals menu items under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__('Bundle Deals', 'mad-baits-bundle-builder'),
			__('Bundle Deals', 'mad-baits-bundle-builder'),
			'manage_woocommerce',
			'mbbb-deals',
			array($this, 'render_page')
		);

		add_submenu_page(
			'woocommerce',
			__('All Bundle Deals', 'mad-baits-bundle-builder'),
			__('All Bundle Deals', 'mad-baits-bundle-builder'),
			'manage_woocommerce',
			'mbbb-deals-all',
			array($this, 'render_page')
		);

		add_submenu_page(
			'woocommerce',
			__('Add New Deal', 'mad-baits-bundle-builder'),
			__('Add New Deal', 'mad-baits-bundle-builder'),
			'manage_woocommerce',
			'mbbb-deals-add',
			array($this, 'render_add_deal_page')
		);

		add_submenu_page(
			'woocommerce',
			__('Bundle Product Options', 'mad-baits-bundle-builder'),
			__('Bundle Product Options', 'mad-baits-bundle-builder'),
			'manage_woocommerce',
			'mbbb-deals-options',
			array($this, 'render_options_page')
		);
	}

	/**
	 * Keep Bundle Deals submenu items grouped together under WooCommerce.
	 *
	 * @return void
	 */
	public function reorder_deals_menu() {
		global $submenu;

		if (empty($submenu['woocommerce']) || ! is_array($submenu['woocommerce'])) {
			return;
		}

		$deal_slugs = array('mbbb-deals', 'mbbb-deals-all', 'mbbb-deals-add', 'mbbb-deals-options', 'mbbb-tools');
		$deal_items = array();
		$rest       = array();

		foreach ($submenu['woocommerce'] as $item) {
			$slug = isset($item[2]) ? (string) $item[2] : '';
			if (in_array($slug, $deal_slugs, true)) {
				$deal_items[] = $item;
			} else {
				$rest[] = $item;
			}
		}

		if (count($deal_items) < 2) {
			return;
		}

		$bundle_deals_label     = wp_strip_all_tags((string) __('Bundle Deals', 'mad-baits-bundle-builder'));
		$all_bundle_deals_label = wp_strip_all_tags((string) __('All Bundle Deals', 'mad-baits-bundle-builder'));

		usort(
			$deal_items,
			static function ($a, $b) use ($bundle_deals_label, $all_bundle_deals_label) {
				$priority = static function ($item) use ($bundle_deals_label, $all_bundle_deals_label) {
					$slug  = isset($item[2]) ? (string) $item[2] : '';
					$title = wp_strip_all_tags((string) ($item[0] ?? ''));

					if ('mbbb-deals' === $slug && $title === $bundle_deals_label) {
						return 0;
					}
					if ('mbbb-deals-all' === $slug || ('mbbb-deals' === $slug && $title === $all_bundle_deals_label)) {
						return 1;
					}
					if ('mbbb-deals-add' === $slug) {
						return 2;
					}
					if ('mbbb-deals-options' === $slug) {
						return 3;
					}
					if ('mbbb-tools' === $slug) {
						return 4;
					}

					return 99;
				};

				return $priority($a) <=> $priority($b);
			}
		);

		$ordered = $deal_items;

		$insert_at = null;
		foreach ($rest as $index => $item) {
			if (isset($item[2]) && false !== strpos((string) $item[2], 'wc-admin')) {
				$insert_at = $index + 1;
				break;
			}
		}

		if (null === $insert_at) {
			$submenu['woocommerce'] = array_merge($ordered, $rest);
			return;
		}

		$before = array_slice($rest, 0, $insert_at);
		$after  = array_slice($rest, $insert_at);
		$submenu['woocommerce'] = array_merge($before, $ordered, $after);
	}

	/**
	 * Add New Deal entry point.
	 *
	 * @return void
	 */
	public function render_add_deal_page() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'mad-baits-bundle-builder'));
		}

		$this->render_notice();
		$this->render_edit_screen('add');
	}

	/**
	 * Bundle Product Options entry point.
	 *
	 * @return void
	 */
	public function render_options_page() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'mad-baits-bundle-builder'));
		}

		$this->render_notice();
		$this->render_pools_screen();
	}

	/**
	 * Preserve old admin URLs while routing shop managers to the new screens.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_urls() {
		if (! is_admin() || wp_doing_ajax()) {
			return;
		}

		$page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
		if (! in_array($page, array('mbbb-deals', 'mbbb-deals-all'), true)) {
			return;
		}

		if ('GET' !== strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
			return;
		}

		if ('mbbb-deals-all' === $page) {
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals'));
			exit;
		}

		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';
		if ('pools' === $tab) {
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}
	}

	/**
	 * @return void
	 */
	public function handle_actions() {
		if (! is_admin() || ! current_user_can('manage_woocommerce')) {
			return;
		}

		$page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
		if (! in_array($page, array('mbbb-deals', 'mbbb-deals-all', 'mbbb-deals-add', 'mbbb-deals-options'), true)) {
			return;
		}

		$action     = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
		$deal_id    = isset($_GET['deal_id']) ? absint($_GET['deal_id']) : 0;
		$redirect   = admin_url('admin.php?page=mbbb-deals');
		$notice_key = 'mbbb_deals_notice';

		if ('duplicate' === $action && $deal_id > 0) {
			check_admin_referer('mbbb_deal_duplicate_' . $deal_id);
			$source = wc_get_product($deal_id);
			if ($source) {
				$new_title = sprintf(
					/* translators: %s: original deal name */
					__('%s (Copy)', 'mad-baits-bundle-builder'),
					$source->get_name()
				);
				$new_id = MBBB_Deal_Builder::clone_deal_product($deal_id, $new_title, (string) $source->get_regular_price());
				if ($new_id > 0) {
					set_transient($notice_key . get_current_user_id(), __('Deal duplicated as a draft. Edit the copy and enable when ready.', 'mad-baits-bundle-builder'), 30);
					wp_safe_redirect(admin_url('admin.php?page=mbbb-deals&action=edit&deal_id=' . $new_id));
					exit;
				}
			}
			set_transient($notice_key . get_current_user_id(), __('Could not duplicate this deal.', 'mad-baits-bundle-builder'), 30);
			wp_safe_redirect($redirect);
			exit;
		}

		if ('toggle' === $action && $deal_id > 0) {
			check_admin_referer('mbbb_deal_toggle_' . $deal_id);
			$plugin   = MBBB_Plugin::instance();
			$product  = wc_get_product($deal_id);
			$activate = ! ($product && 'publish' === $product->get_status() && $plugin->is_enabled($deal_id));

			if ($activate) {
				$errors = $plugin->validate_enabled_bundle_config('yes', $plugin->get_slots($deal_id), $deal_id);
				if (! empty($errors)) {
					set_transient($notice_key . get_current_user_id(), implode(' ', $errors), 30);
					wp_safe_redirect(admin_url('admin.php?page=mbbb-deals&action=edit&deal_id=' . $deal_id));
					exit;
				}
				if ($product) {
					$product->set_status('publish');
					$product->save();
				}
				$plugin->save_product_bundle_meta($deal_id, 'yes', $plugin->get_settings($deal_id), null);
				$message = __('Deal is now active on the website.', 'mad-baits-bundle-builder');
			} else {
				$plugin->save_product_bundle_meta($deal_id, 'no', $plugin->get_settings($deal_id), null);
				if ($product) {
					$product->set_status('draft');
					$product->save();
				}
				$message = __('Deal switched off and saved as draft.', 'mad-baits-bundle-builder');
			}

			set_transient($notice_key . get_current_user_id(), $message, 30);
			wp_safe_redirect($redirect);
			exit;
		}

		if ('trash' === $action && $deal_id > 0) {
			check_admin_referer('mbbb_deal_trash_' . $deal_id);
			wp_trash_post($deal_id);
			set_transient($notice_key . get_current_user_id(), __('Deal moved to trash.', 'mad-baits-bundle-builder'), 30);
			wp_safe_redirect($redirect);
			exit;
		}

		if (isset($_POST['mbbb_save_pool_options_settings']) && check_admin_referer('mbbb_save_pool_options_settings')) {
			MBBB_Pool_Options::set_suggest_enabled(! empty($_POST['suggest_new_pool_products']));
			set_transient($notice_key . get_current_user_id(), __('Bundle Product Options settings saved.', 'mad-baits-bundle-builder'), 30);
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}

		if (isset($_GET['mbbb_ignore_suggestion'], $_GET['product_id']) && check_admin_referer('mbbb_ignore_suggestion')) {
			MBBB_Pool_Options::ignore_suggestion(absint($_GET['product_id']));
			set_transient($notice_key . get_current_user_id(), __('Suggestion dismissed.', 'mad-baits-bundle-builder'), 30);
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}

		if (isset($_POST['mbbb_add_pool_option']) && check_admin_referer('mbbb_add_pool_option')) {
			$type    = sanitize_key((string) ($_POST['bundle_type'] ?? ''));
			$pool_id = MBBB_Pool_Options::pool_id_for_type($type);
			$result  = MBBB_Pool_Options::add_option_to_pool(
				$pool_id,
				array(
					'product_id'     => absint($_POST['wc_product_id'] ?? 0),
					'label'          => sanitize_text_field((string) ($_POST['customer_label'] ?? '')),
					'range_slug'     => sanitize_title((string) ($_POST['range_slug'] ?? '')),
					'range_label'    => sanitize_text_field((string) ($_POST['range_label'] ?? '')),
					'register_range' => ! empty($_POST['register_range']),
					'active'         => ! empty($_POST['option_visible']),
				)
			);
			if ($result['success']) {
				set_transient($notice_key . get_current_user_id(), __('Product option added.', 'mad-baits-bundle-builder'), 30);
			} else {
				set_transient('mbbb_deal_form_errors_' . get_current_user_id(), array($result['error']), 60);
			}
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}

		if (isset($_POST['mbbb_bulk_add_pool_options']) && check_admin_referer('mbbb_bulk_add_pool_options')) {
			$rows    = isset($_POST['bulk_rows']) && is_array($_POST['bulk_rows']) ? wp_unslash($_POST['bulk_rows']) : array();
			$added   = 0;
			$errors  = array();
			$range   = sanitize_title((string) ($_POST['bulk_range_slug'] ?? ''));
			$range_label = sanitize_text_field((string) ($_POST['bulk_range_label'] ?? ''));
			foreach ($rows as $row) {
				if (! is_array($row) || empty($row['selected'])) {
					continue;
				}
				$type    = sanitize_key((string) ($row['type'] ?? ''));
				$pool_id = MBBB_Pool_Options::pool_id_for_type($type);
				$result  = MBBB_Pool_Options::add_option_to_pool(
					$pool_id,
					array(
						'product_id'     => absint($row['product_id'] ?? 0),
						'label'          => sanitize_text_field((string) ($row['label'] ?? '')),
						'range_slug'     => sanitize_title((string) ($row['range_slug'] ?? $range)),
						'range_label'    => '' !== sanitize_text_field((string) ($row['range_label'] ?? '')) ? sanitize_text_field((string) ($row['range_label'] ?? '')) : $range_label,
						'register_range' => '' !== $range || '' !== sanitize_title((string) ($row['range_slug'] ?? '')),
						'active'         => ! empty($row['visible']),
					)
				);
				if ($result['success']) {
					++$added;
				} elseif ('' !== $result['error']) {
					$errors[] = $result['error'];
				}
			}
			if ($added > 0) {
				set_transient($notice_key . get_current_user_id(), sprintf(
					/* translators: %d: number of products added */
					_n('%d product option added.', '%d product options added.', $added, 'mad-baits-bundle-builder'),
					$added
				), 30);
			}
			if (! empty($errors)) {
				set_transient('mbbb_deal_form_errors_' . get_current_user_id(), array_values(array_unique($errors)), 60);
			}
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}

		if (isset($_POST['mbbb_remove_pool_option']) && check_admin_referer('mbbb_remove_pool_option')) {
			$pool_id = sanitize_key((string) ($_POST['pool_id'] ?? ''));
			$value   = sanitize_title((string) ($_POST['option_value'] ?? ''));
			$pools   = MBBB_Plugin::instance()->get_pools();
			$option  = null;
			foreach ((array) ($pools[ $pool_id ]['options'] ?? array()) as $opt) {
				if (is_array($opt) && (string) ($opt['value'] ?? '') === $value) {
					$option = $opt;
					break;
				}
			}
			$usage = $option ? MBBB_Pool_Options::count_deals_using_option($pool_id, $option) : 0;
			if ($usage > 0 && empty($_POST['confirm_remove_used'])) {
				set_transient('mbbb_deal_form_errors_' . get_current_user_id(), array(
					sprintf(
						/* translators: %d: number of deals */
						_n('This option is used by %d live deal. Tick the confirmation box to remove it anyway.', 'This option is used by %d live deals. Tick the confirmation box to remove it anyway.', $usage, 'mad-baits-bundle-builder'),
						$usage
					),
				), 60);
				wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
				exit;
			}
			MBBB_Pool_Options::remove_option_from_pool($pool_id, $value);
			set_transient($notice_key . get_current_user_id(), __('Product option removed.', 'mad-baits-bundle-builder'), 30);
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}

		if (isset($_POST['mbbb_save_deal_pools']) && check_admin_referer('mbbb_save_deal_pools')) {
			$plugin = MBBB_Plugin::instance();
			$pools  = array();
			$posted = isset($_POST['pool']) && is_array($_POST['pool']) ? wp_unslash($_POST['pool']) : array();
			foreach ($posted as $pool_id => $rows) {
				$pool_id = sanitize_key((string) $pool_id);
				if ('' === $pool_id || ! is_array($rows)) {
					continue;
				}
				$options = array();
				foreach ($rows as $row) {
					if (! is_array($row)) {
						continue;
					}
					$label = sanitize_text_field((string) ($row['label'] ?? ''));
					if ('' === $label) {
						continue;
					}
					$options[] = array(
						'label'      => $label,
						'value'      => sanitize_title((string) ($row['value'] ?? $label)),
						'product_id' => absint($row['product_id'] ?? 0),
						'range_slug' => sanitize_title((string) ($row['range_slug'] ?? '')),
						'image'      => esc_url_raw((string) ($row['image'] ?? '')),
						'active'     => ! empty($row['active']),
					);
				}
				$existing = $plugin->get_pools();
				$pools[ $pool_id ] = array(
					'id'      => $pool_id,
					'name'    => sanitize_text_field((string) ($existing[ $pool_id ]['name'] ?? $pool_id)),
					'active'  => true,
					'options' => $options,
				);
			}
			if (! empty($pools)) {
				$all = $plugin->get_pools();
				foreach ($pools as $pool_id => $pool) {
					$all[ $pool_id ] = array_merge($all[ $pool_id ] ?? array(), $pool);
				}
				$plugin->save_pools($all);
			}
			set_transient($notice_key . get_current_user_id(), __('Bundle product options saved.', 'mad-baits-bundle-builder'), 30);
			wp_safe_redirect(admin_url('admin.php?page=mbbb-deals-options'));
			exit;
		}

		if ('save' === $action && isset($_POST['mbbb_deal_save'])) {
			check_admin_referer('mbbb_deal_save');
			$result = MBBB_Deal_Builder::save_deal(wp_unslash($_POST));
			if ($result['success']) {
				set_transient($notice_key . get_current_user_id(), __('Deal saved.', 'mad-baits-bundle-builder'), 30);
				wp_safe_redirect(admin_url('admin.php?page=mbbb-deals&action=edit&deal_id=' . absint($result['product_id'])));
				exit;
			}
			$failed_product_id = absint($result['product_id'] ?? 0);
			if ($failed_product_id < 1) {
				$failed_product_id = absint($_POST['product_id'] ?? 0);
			}
			$posted_form = wp_unslash($_POST);
			if ($failed_product_id > 0) {
				$posted_form['product_id'] = $failed_product_id;
			}
			set_transient('mbbb_deal_form_errors_' . get_current_user_id(), $result['errors'], 60);
			set_transient('mbbb_deal_form_posted_' . get_current_user_id(), $posted_form, 60);
			$back = $failed_product_id > 0
				? admin_url('admin.php?page=mbbb-deals&action=edit&deal_id=' . $failed_product_id)
				: admin_url('admin.php?page=mbbb-deals&action=add');
			wp_safe_redirect($back);
			exit;
		}
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets($hook) {
		$deal_pages = array(
			'woocommerce_page_mbbb-deals',
			'woocommerce_page_mbbb-deals-all',
			'woocommerce_page_mbbb-deals-add',
			'woocommerce_page_mbbb-deals-options',
		);
		if (! in_array($hook, $deal_pages, true)) {
			return;
		}

		wp_enqueue_media();
		if (in_array($hook, array('woocommerce_page_mbbb-deals', 'woocommerce_page_mbbb-deals-all', 'woocommerce_page_mbbb-deals-add', 'woocommerce_page_mbbb-deals-options'), true)) {
			wp_enqueue_script('wc-enhanced-select');
			wp_enqueue_style('woocommerce_admin_styles');
		}
		wp_enqueue_style('mbbb-deals-admin', MBBB_PLUGIN_URL . 'assets/css/mbbb-deals-admin.css', array(), MBBB_VERSION);
		wp_enqueue_script('mbbb-deals-admin', MBBB_PLUGIN_URL . 'assets/js/mbbb-deals-admin.js', array('jquery', 'wc-enhanced-select'), MBBB_VERSION, true);
		if ('woocommerce_page_mbbb-deals-options' === $hook) {
			wp_enqueue_script(
				'mbbb-pool-options-admin',
				MBBB_PLUGIN_URL . 'assets/js/mbbb-pool-options-admin.js',
				array('jquery', 'wc-enhanced-select', 'mbbb-deals-admin'),
				MBBB_VERSION,
				true
			);
			wp_localize_script(
				'mbbb-pool-options-admin',
				'mbbbPoolOptionsAdmin',
				array(
					'ajaxUrl'     => admin_url('admin-ajax.php'),
					'nonce'       => wp_create_nonce('mbbb_pool_options'),
					'searchNonce' => wp_create_nonce('search-products'),
					'types'       => MBBB_Pool_Options::get_bundle_types(),
					'ranges'      => MBBB_Pool_Options::get_all_ranges(),
					'i18n'        => array(
						'searchProducts'   => __('Search WooCommerce products…', 'mad-baits-bundle-builder'),
						'suggestedType'    => __('Suggested type', 'mad-baits-bundle-builder'),
						'confirmType'      => __('Please confirm the bundle type.', 'mad-baits-bundle-builder'),
						'confirmRemove'    => __('Remove this option from shared bundle product options?', 'mad-baits-bundle-builder'),
						'confirmRemoveUsed'=> __('This option is used by live deals. Remove it anyway?', 'mad-baits-bundle-builder'),
						'alreadyIn'        => __('Already available in', 'mad-baits-bundle-builder'),
						'addSelected'      => __('Add selected products', 'mad-baits-bundle-builder'),
					),
				)
			);
		}

		$templates = MBBB_Deal_Builder::get_deal_templates();
		$templates_js = array();
		foreach ($templates as $id => $template) {
			$templates_js[] = array_merge($template, array('id' => $id));
		}

		wp_localize_script(
			'mbbb-deals-admin',
			'mbbbDealsAdmin',
			array(
				'ajaxUrl'   => admin_url('admin-ajax.php'),
				'nonce'     => wp_create_nonce('mbbb_deal_preview'),
				'templates' => $templates_js,
				'ranges'    => MBBB_Deal_Builder::get_boilie_ranges(),
				'searchNonce' => wp_create_nonce('search-products'),
				'i18n'        => array(
					'selectTemplate'     => __('Choose a deal type or build a new deal to preview customer steps.', 'mad-baits-bundle-builder'),
					'customerWillChoose' => __('Customer will choose:', 'mad-baits-bundle-builder'),
					'previewLoading'     => __('Loading preview…', 'mad-baits-bundle-builder'),
					'chooseImage'        => __('Choose deal image', 'mad-baits-bundle-builder'),
					'removeImage'        => __('Remove image', 'mad-baits-bundle-builder'),
					'confirmTrash'       => __('Move this deal to trash?', 'mad-baits-bundle-builder'),
					'searchProducts'     => __('Search WooCommerce products…', 'mad-baits-bundle-builder'),
					'savingsUnavailable' => __('Savings estimate unavailable — not all choices link to individual product prices.', 'mad-baits-bundle-builder'),
				),
			)
		);
	}

	/**
	 * AJAX preview of customer steps.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-bundle-builder')), 403);
		}
		check_ajax_referer('mbbb_deal_preview', 'nonce');

		$posted = isset($_POST['form']) ? wp_unslash($_POST['form']) : array();
		if (! is_array($posted)) {
			wp_send_json_error(array('message' => __('Invalid form data.', 'mad-baits-bundle-builder')), 400);
		}

		$product_id = absint($posted['product_id'] ?? 0);
		$slots      = MBBB_Deal_Builder::build_slots_from_form($posted);
		$plugin     = MBBB_Plugin::instance();
		$materialized = MBBB_Deal_Builder::materialize_slots_for_product($slots, $posted);
		$steps      = $plugin->build_admin_preview_steps($materialized, $product_id);
		$errors     = $plugin->validate_slots_for_save($materialized, $product_id);
		$summary    = 'custom' === sanitize_key((string) ($posted['build_mode'] ?? ''))
			? MBBB_Deal_Builder::build_custom_customer_summary($posted)
			: self::customer_summary_from_slots($slots, $posted);

		foreach (MBBB_Deal_Builder::parse_included_products_from_post($posted) as $included) {
			$label = (string) ($included['label'] ?? '');
			$qty   = max(1, absint($included['quantity'] ?? 1));
			if ('' === $label) {
				continue;
			}
			$steps[] = array(
				'label'       => __('Included automatically', 'mad-baits-bundle-builder') . ': ' . $label . ($qty > 1 ? ' × ' . $qty : ''),
				'is_included' => true,
				'is_broken'   => false,
			);
		}

		wp_send_json_success(
			array(
				'steps'   => $steps,
				'errors'  => array_values($errors),
				'summary' => $summary,
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Permission denied.', 'mad-baits-bundle-builder'));
		}

		$this->render_notice();
		$tab    = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';
		$action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : 'list';

		if ('pools' === $tab) {
			$this->render_pools_screen();
			return;
		}

		if ('add' === $action || 'edit' === $action) {
			$this->render_edit_screen($action);
			return;
		}

		$this->render_list_screen();
	}

	/**
	 * @return void
	 */
	private function render_notice() {
		$key     = 'mbbb_deals_notice' . get_current_user_id();
		$message = get_transient($key);
		if ($message) {
			delete_transient($key);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html((string) $message) . '</p></div>';
		}
	}

	/**
	 * @return void
	 */
	private function render_list_screen() {
		$filter = isset($_GET['deal_filter']) ? sanitize_key(wp_unslash((string) $_GET['deal_filter'])) : 'all';
		if (! in_array($filter, array('all', 'active', 'disabled', 'draft'), true)) {
			$filter = 'all';
		}
		$rows      = MBBB_Deal_Builder::get_deal_rows($filter);
		$list_base = admin_url('admin.php?page=mbbb-deals');
		?>
		<div class="wrap mbbb-deals-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e('Bundle Deals', 'mad-baits-bundle-builder'); ?></h1>
			<a href="<?php echo esc_url(admin_url('admin.php?page=mbbb-deals-add')); ?>" class="page-title-action"><?php esc_html_e('Add New Deal', 'mad-baits-bundle-builder'); ?></a>
			<p class="description mbbb-deals-wrap__intro">
				<?php esc_html_e('Create and manage bundle deals for customers to buy on the website. Pick a deal type, set the price, choose which product ranges are included, and switch the deal on when you are ready.', 'mad-baits-bundle-builder'); ?>
			</p>

			<ul class="subsubsub mbbb-deals-filters">
				<?php
				$filters = array(
					'all'      => __('All deals', 'mad-baits-bundle-builder'),
					'active'   => __('Active', 'mad-baits-bundle-builder'),
					'disabled' => __('Disabled', 'mad-baits-bundle-builder'),
					'draft'    => __('Draft', 'mad-baits-bundle-builder'),
				);
				$links   = array();
				foreach ($filters as $key => $label) {
					$url     = 'all' === $key ? $list_base : add_query_arg('deal_filter', $key, $list_base);
					$class   = $filter === $key ? 'current' : '';
					$links[] = '<li><a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a></li>';
				}
				echo implode(' | ', $links); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>

			<?php if (empty($rows)) : ?>
				<div class="mbbb-deals-empty">
					<h2><?php esc_html_e('No bundle deals yet', 'mad-baits-bundle-builder'); ?></h2>
					<p><?php esc_html_e('Start with a deal such as a 20KG Session Bundle, or duplicate an existing deal and change the price.', 'mad-baits-bundle-builder'); ?></p>
					<p><a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('admin.php?page=mbbb-deals-add')); ?>"><?php esc_html_e('Add your first deal', 'mad-baits-bundle-builder'); ?></a></p>
				</div>
			<?php else : ?>
				<table class="widefat striped mbbb-deals-table">
					<thead>
						<tr>
							<th scope="col" class="column-deal"><?php esc_html_e('Deal', 'mad-baits-bundle-builder'); ?></th>
							<th scope="col"><?php esc_html_e('Active', 'mad-baits-bundle-builder'); ?></th>
							<th scope="col"><?php esc_html_e('Size / type', 'mad-baits-bundle-builder'); ?></th>
							<th scope="col"><?php esc_html_e('Price', 'mad-baits-bundle-builder'); ?></th>
							<th scope="col"><?php esc_html_e('Customer choices', 'mad-baits-bundle-builder'); ?></th>
							<th scope="col"><?php esc_html_e('Updated', 'mad-baits-bundle-builder'); ?></th>
							<th scope="col"><?php esc_html_e('Actions', 'mad-baits-bundle-builder'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $row) : ?>
							<?php
							$state_key = (string) ($row['deal_state'] ?? 'disabled');
							$badge_class = 'active' === $state_key ? 'mbbb-deals-badge--live' : ('draft' === $state_key ? 'mbbb-deals-badge--draft' : 'mbbb-deals-badge--off');
							?>
							<tr>
								<td class="column-deal">
									<a class="mbbb-deals-table__deal-name" href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['name']); ?></a>
								</td>
								<td>
									<span class="mbbb-deals-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html((string) ($row['deal_state_label'] ?? '')); ?></span>
								</td>
								<td>
									<?php
									echo esc_html('—' !== (string) $row['bundle_size'] ? strtoupper((string) $row['bundle_size']) : '—');
									if ('' !== (string) $row['template_label'] && '—' !== (string) $row['template_label']) {
										echo '<br /><span class="description">' . esc_html((string) $row['template_label']) . '</span>';
									}
									?>
								</td>
								<td><?php echo wp_kses_post($row['price']); ?></td>
								<td><?php echo esc_html((string) ($row['choices_label'] ?? '—')); ?></td>
								<td><?php echo esc_html((string) $row['modified']); ?></td>
								<td class="mbbb-deals-table__actions">
									<a class="button button-small" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'mad-baits-bundle-builder'); ?></a>
									<a class="button button-small" href="<?php echo esc_url($row['duplicate_url']); ?>"><?php esc_html_e('Duplicate', 'mad-baits-bundle-builder'); ?></a>
									<a class="button button-small" href="<?php echo esc_url($row['toggle_url']); ?>"><?php echo $row['enabled'] ? esc_html__('Disable', 'mad-baits-bundle-builder') : esc_html__('Enable', 'mad-baits-bundle-builder'); ?></a>
									<?php if (! empty($row['preview_url'])) : ?>
										<a class="button button-small" href="<?php echo esc_url($row['preview_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview', 'mad-baits-bundle-builder'); ?></a>
									<?php endif; ?>
									<a class="button button-small button-link-delete" href="<?php echo esc_url($row['trash_url']); ?>" data-mbbb-confirm-trash="1"><?php esc_html_e('Trash', 'mad-baits-bundle-builder'); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p class="mbbb-deals-wrap__advanced">
				<?php
				printf(
					/* translators: %s: link to advanced tools */
					esc_html__('Developer-only tools are under %s.', 'mad-baits-bundle-builder'),
					'<a href="' . esc_url(admin_url('admin.php?page=mbbb-tools')) . '">' . esc_html__('Bundle Deals → Advanced', 'mad-baits-bundle-builder') . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * @param string $action add|edit.
	 * @return void
	 */
	private function render_edit_screen($action) {
		$deal_id = isset($_GET['deal_id']) ? absint($_GET['deal_id']) : 0;
		$posted  = get_transient('mbbb_deal_form_posted_' . get_current_user_id());
		$errors  = get_transient('mbbb_deal_form_errors_' . get_current_user_id());

		if (is_array($posted)) {
			delete_transient('mbbb_deal_form_posted_' . get_current_user_id());
			$form = wp_parse_args($posted, MBBB_Deal_Builder::default_form());
		} elseif ('edit' === $action && $deal_id > 0) {
			$form = MBBB_Deal_Builder::form_from_product($deal_id);
		} else {
			$form = MBBB_Deal_Builder::default_form();
		}

		if (is_array($errors) && ! empty($errors)) {
			delete_transient('mbbb_deal_form_errors_' . get_current_user_id());
			echo '<div class="notice notice-error"><p><strong>' . esc_html__('Please fix the following:', 'mad-baits-bundle-builder') . '</strong></p><ul style="list-style:disc;margin-left:1.5em;">';
			foreach ($errors as $error) {
				echo '<li>' . esc_html($error) . '</li>';
			}
			echo '</ul></div>';
		}

		$templates       = MBBB_Deal_Builder::get_deal_templates();
		$deal_types      = MBBB_Deal_Builder::deal_type_labels();
		$ranges          = MBBB_Pool_Options::get_all_ranges();
		$pool_options_url = admin_url('admin.php?page=mbbb-deals-options');
		$is_edit         = $deal_id > 0;
		$build_mode      = sanitize_key((string) ($form['build_mode'] ?? 'template'));
		$is_custom_build = ('custom' === $build_mode);
		$preview_url     = $is_edit ? get_permalink($deal_id) : '';
		$advanced_url    = admin_url('admin.php?page=mbbb-tools');
		$reference_val   = $is_edit ? MBBB_Deal_Builder::estimate_reference_value($deal_id) : 0;
		$image_url       = ! empty($form['image_id']) ? wp_get_attachment_image_url(absint($form['image_id']), 'medium') : '';
		$existing_slots  = $is_edit ? MBBB_Plugin::instance()->get_slots($deal_id) : array();
		$has_custom_slots = $is_edit && ! $is_custom_build && ! empty($existing_slots) && ! empty($form['template_id'])
			&& count($existing_slots) !== count(MBBB_Deal_Builder::build_slots_from_form($form));
		$pool_options = array(
			'hookbait' => MBBB_Deal_Builder::get_pool_admin_options('standard-hookbait-choices'),
			'liquid'   => MBBB_Deal_Builder::get_pool_admin_options('standard-500ml-liquid-choices'),
			'dip'      => MBBB_Deal_Builder::get_pool_admin_options('standard-250ml-dip-choices'),
			'pellet'   => MBBB_Deal_Builder::get_pool_admin_options('standard-pellet-choices'),
		);
		$included_products = isset($form['included_products']) && is_array($form['included_products']) ? $form['included_products'] : array();
		if (empty($included_products)) {
			$included_products = array(
				array(
					'product_id'   => 0,
					'variation_id' => 0,
					'quantity'     => 1,
					'label'        => '',
				),
			);
		}

		$back_url = admin_url('admin.php?page=mbbb-deals');
		?>
		<div class="wrap mbbb-deals-wrap mbbb-deals-wrap--edit">
			<h1><?php echo $is_edit ? esc_html__('Edit Bundle Deal', 'mad-baits-bundle-builder') : esc_html__('Add New Bundle Deal', 'mad-baits-bundle-builder'); ?></h1>
			<p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to all deals', 'mad-baits-bundle-builder'); ?></a></p>

			<?php if ($has_custom_slots) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e('This deal uses a custom choice setup managed in Advanced. You can still update the name, price, image, and active status here. To rebuild customer choices from a deal type, tick the box below.', 'mad-baits-bundle-builder'); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=mbbb-deals&action=save')); ?>" id="mbbb-deal-form" class="mbbb-deal-form">
				<?php wp_nonce_field('mbbb_deal_save'); ?>
				<input type="hidden" name="mbbb_deal_save" value="1" />
				<input type="hidden" name="product_id" value="<?php echo esc_attr((string) absint($form['product_id'])); ?>" />
				<input type="hidden" name="build_mode" id="mbbb-build-mode" value="<?php echo esc_attr($is_custom_build ? 'custom' : 'template'); ?>" />

				<div class="mbbb-deal-form__grid">
					<div class="mbbb-deal-form__main">
						<div class="mbbb-deal-panel">
							<h2><?php esc_html_e('Deal details', 'mad-baits-bundle-builder'); ?></h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="mbbb-deal-name"><?php esc_html_e('Deal name', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<input type="text" class="regular-text" id="mbbb-deal-name" name="name" required value="<?php echo esc_attr((string) $form['name']); ?>" placeholder="<?php esc_attr_e('e.g. 20KG Session Bundle', 'mad-baits-bundle-builder'); ?>" />
										<p class="description"><?php esc_html_e('Shown to customers on the website and in their order.', 'mad-baits-bundle-builder'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="mbbb-deal-description"><?php esc_html_e('Internal note', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<textarea id="mbbb-deal-description" name="admin_description" rows="3" class="large-text"><?php echo esc_textarea((string) $form['admin_description']); ?></textarea>
										<p class="description"><?php esc_html_e('Optional — only visible here in admin, not on the website.', 'mad-baits-bundle-builder'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e('Deal active', 'mad-baits-bundle-builder'); ?></th>
									<td>
										<label class="mbbb-deal-toggle mbbb-deal-toggle--enable">
											<input type="checkbox" name="deal_active" value="1" <?php checked($form['deal_active'], 'yes'); ?> />
											<span><?php esc_html_e('Show this deal on the website and let customers build their bundle', 'mad-baits-bundle-builder'); ?></span>
										</label>
										<p class="description"><?php esc_html_e('When off, the deal is saved as a draft and hidden from customers. Turn off to pause a deal without deleting it.', 'mad-baits-bundle-builder'); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="mbbb-deal-panel">
							<h2><?php esc_html_e('Start from', 'mad-baits-bundle-builder'); ?></h2>
							<p class="description"><?php esc_html_e('Use an existing deal layout for a quick setup, or build a completely new bundle structure from scratch.', 'mad-baits-bundle-builder'); ?></p>
							<div class="mbbb-deal-build-mode">
								<label class="mbbb-deal-build-mode__option<?php echo ! $is_custom_build ? ' is-selected' : ''; ?>">
									<input type="radio" name="mbbb_build_mode_ui" value="template" <?php checked(! $is_custom_build); ?> />
									<span>
										<strong><?php esc_html_e('Existing deal layout', 'mad-baits-bundle-builder'); ?></strong>
										<small><?php esc_html_e('Fastest route — pick a proven deal size and layout.', 'mad-baits-bundle-builder'); ?></small>
									</span>
								</label>
								<label class="mbbb-deal-build-mode__option<?php echo $is_custom_build ? ' is-selected' : ''; ?>">
									<input type="radio" name="mbbb_build_mode_ui" value="custom" <?php checked($is_custom_build); ?> />
									<span>
										<strong><?php esc_html_e('Build a new deal', 'mad-baits-bundle-builder'); ?></strong>
										<small><?php esc_html_e('Define your own customer choices without using Advanced tools.', 'mad-baits-bundle-builder'); ?></small>
									</span>
								</label>
							</div>

							<div id="mbbb-build-template" class="mbbb-deal-build-section" <?php echo $is_custom_build ? 'hidden' : ''; ?>>
								<h3><?php esc_html_e('Deal type & size', 'mad-baits-bundle-builder'); ?></h3>
								<p class="description"><?php esc_html_e('Pick the deal size and layout. This controls how many choices the customer makes (for example, four 5kg bags in a 20kg deal).', 'mad-baits-bundle-builder'); ?></p>
								<div class="mbbb-deal-templates" id="mbbb-deal-templates">
									<?php foreach ($templates as $template_id => $template) : ?>
										<?php
										$summary_lines = array_filter(array_map('trim', explode(',', (string) ($template['description'] ?? ''))));
										$is_selected   = $form['template_id'] === $template_id;
										?>
										<label class="mbbb-deal-template-card<?php echo $is_selected ? ' is-selected' : ''; ?>">
											<input type="radio" name="template_id" value="<?php echo esc_attr($template_id); ?>" <?php checked($form['template_id'], $template_id); ?> data-deal-type="<?php echo esc_attr($template['deal_type']); ?>" data-bundle-size="<?php echo esc_attr($template['bundle_size']); ?>" />
											<span class="mbbb-deal-template-card__body">
												<strong class="mbbb-deal-template-card__title"><?php echo esc_html($template['label']); ?></strong>
												<?php if (! empty($template['bundle_size'])) : ?>
													<span class="mbbb-deal-template-card__size"><?php echo esc_html(strtoupper($template['bundle_size'])); ?></span>
												<?php endif; ?>
												<?php if (! empty($summary_lines)) : ?>
													<ul class="mbbb-deal-template-card__summary">
														<?php foreach ($summary_lines as $line) : ?>
															<li><?php echo esc_html($line); ?></li>
														<?php endforeach; ?>
													</ul>
												<?php endif; ?>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
								<input type="hidden" name="deal_type" id="mbbb-deal-type" value="<?php echo esc_attr((string) $form['deal_type']); ?>" />
								<input type="hidden" name="bundle_size" id="mbbb-bundle-size" value="<?php echo esc_attr((string) $form['bundle_size']); ?>" />
								<?php if ($has_custom_slots) : ?>
									<p>
										<label>
											<input type="checkbox" name="update_slots" value="1" />
											<?php esc_html_e('Rebuild customer choices from the selected deal type', 'mad-baits-bundle-builder'); ?>
										</label>
									</p>
								<?php endif; ?>
							</div>

							<div id="mbbb-build-custom" class="mbbb-deal-build-section" <?php echo $is_custom_build ? '' : 'hidden'; ?>>
								<h3><?php esc_html_e('What does the customer choose?', 'mad-baits-bundle-builder'); ?></h3>
								<p class="description"><?php esc_html_e('Set how many of each product type the customer picks when building their bundle.', 'mad-baits-bundle-builder'); ?></p>
								<?php
								$choice_rows = array(
									'custom_boilie_choices'   => __('Boilie choices', 'mad-baits-bundle-builder'),
									'custom_hookbait_choices' => __('Hookbait choices', 'mad-baits-bundle-builder'),
									'custom_liquid_choices'   => __('500ml liquids', 'mad-baits-bundle-builder'),
									'custom_dip_choices'      => __('250ml dips', 'mad-baits-bundle-builder'),
									'custom_pellet_choices'   => __('Pellet choices', 'mad-baits-bundle-builder'),
								);
								?>
								<div class="mbbb-deal-choice-counts">
									<?php foreach ($choice_rows as $field => $label) : ?>
										<div class="mbbb-deal-choice-count" data-mbbb-choice-field="<?php echo esc_attr($field); ?>">
											<span class="mbbb-deal-choice-count__label"><?php echo esc_html($label); ?></span>
											<div class="mbbb-deal-choice-count__controls">
												<button type="button" class="button button-small mbbb-deal-count-btn" data-action="minus" aria-label="<?php esc_attr_e('Decrease', 'mad-baits-bundle-builder'); ?>">−</button>
												<input type="number" class="small-text mbbb-deal-count-input" name="<?php echo esc_attr($field); ?>" min="0" max="20" step="1" value="<?php echo esc_attr((string) absint($form[ $field ] ?? 0)); ?>" />
												<button type="button" class="button button-small mbbb-deal-count-btn" data-action="plus" aria-label="<?php esc_attr_e('Increase', 'mad-baits-bundle-builder'); ?>">+</button>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="mbbb-deal-panel" id="mbbb-deal-template-choices" <?php echo $is_custom_build ? 'hidden' : ''; ?>>
							<h2><?php esc_html_e('Customer product choices', 'mad-baits-bundle-builder'); ?></h2>
							<p class="description"><?php esc_html_e('Choose which boilie ranges customers can pick. Hookbaits, liquids, and other extras follow the deal type you selected above.', 'mad-baits-bundle-builder'); ?></p>

							<fieldset class="mbbb-deal-ranges" data-mbbb-option-group="boilie_ranges">
								<legend><?php esc_html_e('Boilie ranges included', 'mad-baits-bundle-builder'); ?></legend>
								<?php $this->render_deal_option_toggle_actions(); ?>
								<?php foreach ($ranges as $slug => $label) : ?>
									<label class="mbbb-deal-range">
										<input type="checkbox" name="boilie_ranges[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $form['boilie_ranges'], true)); ?> />
										<?php echo esc_html($label); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e('Included extras', 'mad-baits-bundle-builder'); ?></th>
									<td>
										<label class="mbbb-deal-extra"><input type="checkbox" name="include_hookbaits" value="1" <?php checked($form['include_hookbaits'], 'yes'); ?> /> <?php esc_html_e('Hookbaits', 'mad-baits-bundle-builder'); ?></label>
										<label class="mbbb-deal-extra"><input type="checkbox" name="include_liquids" value="1" <?php checked($form['include_liquids'], 'yes'); ?> /> <?php esc_html_e('500ml liquids', 'mad-baits-bundle-builder'); ?></label>
										<label class="mbbb-deal-extra"><input type="checkbox" name="include_dips" value="1" <?php checked($form['include_dips'], 'yes'); ?> /> <?php esc_html_e('250ml dips', 'mad-baits-bundle-builder'); ?></label>
										<label class="mbbb-deal-extra"><input type="checkbox" name="include_pellets" value="1" <?php checked($form['include_pellets'], 'yes'); ?> /> <?php esc_html_e('Pellets (when included in this deal type)', 'mad-baits-bundle-builder'); ?></label>
									</td>
								</tr>
							</table>

							<div class="mbbb-deal-customer-summary" id="mbbb-deal-customer-summary">
								<strong><?php esc_html_e('Customer will:', 'mad-baits-bundle-builder'); ?></strong>
								<p><?php echo esc_html((string) $form['customer_summary']); ?></p>
							</div>
						</div>

						<div class="mbbb-deal-panel" id="mbbb-deal-custom-options" <?php echo $is_custom_build ? '' : 'hidden'; ?>>
							<h2><?php esc_html_e('Product options', 'mad-baits-bundle-builder'); ?></h2>
							<p class="description">
								<?php esc_html_e('Choose which products customers can pick in each category. All standard options are selected by default — narrow them if needed.', 'mad-baits-bundle-builder'); ?>
								<?php
								printf(
									' <a href="%1$s">%2$s</a>',
									esc_url($pool_options_url),
									esc_html__('Manage bundle product options', 'mad-baits-bundle-builder')
								);
								?>
							</p>

							<fieldset class="mbbb-deal-ranges" data-mbbb-option-group="boilie_ranges">
								<legend><?php esc_html_e('Boilie ranges available', 'mad-baits-bundle-builder'); ?></legend>
								<?php $this->render_deal_option_toggle_actions(); ?>
								<?php foreach ($ranges as $slug => $label) : ?>
									<label class="mbbb-deal-range">
										<input type="checkbox" name="boilie_ranges[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $form['boilie_ranges'], true)); ?> />
										<?php echo esc_html($label); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>

							<?php
							$custom_option_groups = array(
								'hookbait' => array(
									'title' => __('Hookbaits available', 'mad-baits-bundle-builder'),
									'field' => 'hookbait_options',
									'count' => absint($form['custom_hookbait_choices'] ?? 0),
								),
								'liquid' => array(
									'title' => __('500ml liquids available', 'mad-baits-bundle-builder'),
									'field' => 'liquid_options',
									'count' => absint($form['custom_liquid_choices'] ?? 0),
								),
								'dip' => array(
									'title' => __('250ml dips available', 'mad-baits-bundle-builder'),
									'field' => 'dip_options',
									'count' => absint($form['custom_dip_choices'] ?? 0),
								),
								'pellet' => array(
									'title' => __('Pellets available', 'mad-baits-bundle-builder'),
									'field' => 'pellet_options',
									'count' => absint($form['custom_pellet_choices'] ?? 0),
								),
							);
							foreach ($custom_option_groups as $group_key => $group) :
								$selected = isset($form[ $group['field'] ]) && is_array($form[ $group['field'] ]) ? $form[ $group['field'] ] : array();
								?>
								<fieldset class="mbbb-deal-ranges mbbb-deal-ranges--pool" data-mbbb-pool-group="<?php echo esc_attr($group_key); ?>" data-mbbb-option-group="<?php echo esc_attr($group['field']); ?>" <?php echo $group['count'] < 1 ? 'hidden' : ''; ?>>
									<legend><?php echo esc_html($group['title']); ?></legend>
									<?php $this->render_deal_option_toggle_actions(); ?>
									<?php $this->render_pool_option_checkboxes($group['field'], $pool_options[ $group_key ], $selected, $ranges); ?>
								</fieldset>
							<?php endforeach; ?>

							<h3><?php esc_html_e('Fixed included products', 'mad-baits-bundle-builder'); ?></h3>
							<p class="description"><?php esc_html_e('Optional products included automatically in every bundle — for example buckets, merchandise, or a specific bait item.', 'mad-baits-bundle-builder'); ?></p>
							<div id="mbbb-included-products" class="mbbb-included-products">
								<?php foreach ($included_products as $index => $included) : ?>
									<?php
									$included_id    = absint($included['product_id'] ?? 0);
									$included_label = (string) ($included['label'] ?? '');
									if ($included_id > 0 && '' === $included_label) {
										$included_product = wc_get_product($included_id);
										$included_label   = $included_product ? $included_product->get_formatted_name() : '';
									}
									?>
									<div class="mbbb-included-product-row">
										<label class="screen-reader-text"><?php esc_html_e('Included product', 'mad-baits-bundle-builder'); ?></label>
										<select class="wc-product-search mbbb-included-product-search" name="included_products[<?php echo esc_attr((string) $index); ?>][product_id]" data-placeholder="<?php esc_attr_e('Search WooCommerce products…', 'mad-baits-bundle-builder'); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
											<?php if ($included_id > 0) : ?>
												<option value="<?php echo esc_attr((string) $included_id); ?>" selected="selected"><?php echo esc_html($included_label); ?></option>
											<?php endif; ?>
										</select>
										<label>
											<span><?php esc_html_e('Quantity', 'mad-baits-bundle-builder'); ?></span>
											<input type="number" class="small-text" name="included_products[<?php echo esc_attr((string) $index); ?>][quantity]" min="1" step="1" value="<?php echo esc_attr((string) max(1, absint($included['quantity'] ?? 1))); ?>" />
										</label>
										<button type="button" class="button button-link-delete mbbb-included-product-remove"><?php esc_html_e('Remove', 'mad-baits-bundle-builder'); ?></button>
									</div>
								<?php endforeach; ?>
							</div>
							<p><button type="button" class="button" id="mbbb-included-product-add"><?php esc_html_e('Add included product', 'mad-baits-bundle-builder'); ?></button></p>

							<div class="mbbb-deal-customer-summary" id="mbbb-deal-custom-summary">
								<strong><?php esc_html_e('Customer will choose:', 'mad-baits-bundle-builder'); ?></strong>
								<p><?php echo esc_html($is_custom_build ? (string) MBBB_Deal_Builder::build_custom_customer_summary($form) : ''); ?></p>
							</div>
						</div>

						<div class="mbbb-deal-panel">
							<h2><?php esc_html_e('Price', 'mad-baits-bundle-builder'); ?></h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="mbbb-deal-price"><?php esc_html_e('Bundle price (£)', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<input type="number" class="regular-text mbbb-deal-price-input" id="mbbb-deal-price" name="price" required min="0" step="0.01" inputmode="decimal" value="<?php echo esc_attr((string) $form['price']); ?>" />
										<p class="description"><?php esc_html_e('Fixed price customers pay regardless of their product choices.', 'mad-baits-bundle-builder'); ?></p>
										<?php if ($reference_val > 0 && (float) $form['price'] > 0) : ?>
											<?php
											$bundle_price = (float) $form['price'];
											$saving       = max(0, $reference_val - $bundle_price);
											$percent      = $reference_val > 0 ? round(($saving / $reference_val) * 100) : 0;
											?>
											<p class="mbbb-deal-savings">
												<?php
												printf(
													/* translators: 1: reference value 2: bundle price 3: saving amount 4: percent */
													esc_html__('Estimated reference value: £%1$s · Bundle price: £%2$s · Customer saves ~£%3$s (%4$s%%)', 'mad-baits-bundle-builder'),
													esc_html(wc_format_localized_price($reference_val)),
													esc_html(wc_format_localized_price($bundle_price)),
													esc_html(wc_format_localized_price($saving)),
													esc_html((string) $percent)
												);
												?>
											</p>
											<p class="description"><?php esc_html_e('Reference value is an estimate from linked product prices — actual savings depend on customer choices.', 'mad-baits-bundle-builder'); ?></p>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="mbbb-deal-form__side">
						<div class="mbbb-deal-panel">
							<h2><?php esc_html_e('Deal image', 'mad-baits-bundle-builder'); ?></h2>
							<div class="mbbb-deal-image" id="mbbb-deal-image">
								<input type="hidden" name="image_id" id="mbbb-deal-image-id" value="<?php echo esc_attr((string) absint($form['image_id'])); ?>" />
								<div class="mbbb-deal-image__preview" <?php echo $image_url ? '' : 'hidden'; ?>>
									<img src="<?php echo esc_url((string) $image_url); ?>" alt="" />
								</div>
								<p>
									<button type="button" class="button" id="mbbb-deal-image-select"><?php esc_html_e('Choose from Media Library', 'mad-baits-bundle-builder'); ?></button>
									<button type="button" class="button" id="mbbb-deal-image-remove" <?php echo $image_url ? '' : 'hidden'; ?>><?php esc_html_e('Remove', 'mad-baits-bundle-builder'); ?></button>
								</p>
								<p class="description"><?php esc_html_e('Optional. If empty, WooCommerce uses the product image or catalogue defaults.', 'mad-baits-bundle-builder'); ?></p>
							</div>
						</div>

						<div class="mbbb-deal-panel">
							<h2><?php esc_html_e('Display & shipping', 'mad-baits-bundle-builder'); ?></h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="mbbb-display-order"><?php esc_html_e('Display order', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<input type="number" class="small-text" id="mbbb-display-order" name="display_order" min="0" step="1" value="<?php echo esc_attr((string) absint($form['display_order'])); ?>" />
										<p class="description"><?php esc_html_e('Lower numbers appear first in the deals list here. Does not change website sort order.', 'mad-baits-bundle-builder'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e('Free shipping', 'mad-baits-bundle-builder'); ?></th>
									<td>
										<label>
											<input type="checkbox" name="free_shipping" value="1" <?php checked($form['free_shipping'], 'yes'); ?> disabled checked />
											<?php esc_html_e('Included automatically when a bundle is in the cart', 'mad-baits-bundle-builder'); ?>
										</label>
										<p class="description"><?php esc_html_e('Mad Baits bundle deals qualify for free UK shipping at checkout. No extra setup needed.', 'mad-baits-bundle-builder'); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="mbbb-badge-label"><?php esc_html_e('Deal badge (optional)', 'mad-baits-bundle-builder'); ?></label></th>
									<td>
										<input type="text" class="regular-text" id="mbbb-badge-label" name="badge_label" value="<?php echo esc_attr((string) $form['badge_label']); ?>" placeholder="<?php esc_attr_e('e.g. Best Seller', 'mad-baits-bundle-builder'); ?>" />
										<p class="description"><?php esc_html_e('“Bundle Deal” shows automatically on deal cards. Optional labels like Best Seller map to product tags when recognised.', 'mad-baits-bundle-builder'); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="mbbb-deal-panel mbbb-deal-preview-panel">
							<h2><?php esc_html_e('Customer steps preview', 'mad-baits-bundle-builder'); ?></h2>
							<div id="mbbb-deal-preview-steps" class="mbbb-deal-preview-steps" aria-live="polite">
								<p class="description mbbb-deal-preview-steps__empty"><?php esc_html_e('Choose a deal type to preview what the customer will select.', 'mad-baits-bundle-builder'); ?></p>
							</div>
							<?php if ($preview_url) : ?>
								<p><a class="button" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview on website', 'mad-baits-bundle-builder'); ?></a></p>
							<?php endif; ?>
						</div>

						<p class="mbbb-deal-advanced-link">
							<a href="<?php echo esc_url($advanced_url); ?>"><?php esc_html_e('Need more control? Open Advanced tools', 'mad-baits-bundle-builder'); ?></a>
						</p>
					</div>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e('Save deal', 'mad-baits-bundle-builder'); ?></button>
					<a class="button button-large" href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Cancel', 'mad-baits-bundle-builder'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Build plain-English summary from slots for preview AJAX.
	 *
	 * @param array<int, array<string, mixed>> $slots  Slots.
	 * @param array<string, mixed>             $posted Posted form fragment.
	 * @return string
	 */
	private static function customer_summary_from_slots(array $slots, array $posted) {
		$template_id = sanitize_key((string) ($posted['template_id'] ?? ''));
		$templates   = MBBB_Deal_Builder::get_deal_templates();
		$template    = isset($templates[ $template_id ]) ? $templates[ $template_id ] : null;

		$stats = array(
			'boilie_splits' => 0,
			'hookbaits'     => 0,
			'liquids'       => 0,
			'dips'          => 0,
			'pellets'       => 0,
		);

		foreach ($slots as $slot) {
			$label = strtolower((string) ($slot['label'] ?? ''));
			if (false !== strpos($label, 'split') || false !== strpos($label, 'boilie')) {
				++$stats['boilie_splits'];
			} elseif (false !== strpos($label, 'hook')) {
				++$stats['hookbaits'];
			} elseif (false !== strpos($label, 'liquid') || false !== strpos($label, '500ml')) {
				++$stats['liquids'];
			} elseif (false !== strpos($label, 'dip') || false !== strpos($label, '250ml')) {
				++$stats['dips'];
			} elseif (false !== strpos($label, 'pellet')) {
				++$stats['pellets'];
			}
		}

		$lines = array();
		if ($stats['boilie_splits'] > 0) {
			$size = is_array($template) ? (string) ($template['bundle_size'] ?? '') : '';
			if ('' !== $size) {
				$lines[] = sprintf(
					/* translators: 1: split count 2: total size */
					__('Choose %1$d boilie bag(s) totalling %2$s', 'mad-baits-bundle-builder'),
					$stats['boilie_splits'],
					$size
				);
			} else {
				$lines[] = sprintf(
					/* translators: %d: count */
					__('Choose %d boilie product(s)', 'mad-baits-bundle-builder'),
					$stats['boilie_splits']
				);
			}
		}
		if ($stats['hookbaits'] > 0) {
			$lines[] = sprintf(__('Choose %d hookbait(s)', 'mad-baits-bundle-builder'), $stats['hookbaits']);
		}
		if ($stats['liquids'] > 0) {
			$lines[] = sprintf(__('Choose %d liquid(s)', 'mad-baits-bundle-builder'), $stats['liquids']);
		}
		if ($stats['dips'] > 0) {
			$lines[] = sprintf(__('Choose %d dip(s)', 'mad-baits-bundle-builder'), $stats['dips']);
		}
		if ($stats['pellets'] > 0) {
			$lines[] = __('Choose a pellet', 'mad-baits-bundle-builder');
		}

		return implode('. ', $lines);
	}

	/**
	 * Select all / Deselect all controls for a deal option group.
	 *
	 * @return void
	 */
	private function render_deal_option_toggle_actions() {
		?>
		<div class="mbbb-deal-range-actions">
			<button type="button" class="button-link mbbb-deal-select-all"><?php esc_html_e('Select all', 'mad-baits-bundle-builder'); ?></button>
			<span class="mbbb-deal-range-actions__sep" aria-hidden="true">|</span>
			<button type="button" class="button-link mbbb-deal-deselect-all"><?php esc_html_e('Deselect all', 'mad-baits-bundle-builder'); ?></button>
		</div>
		<?php
	}

	/**
	 * @param string                               $field_name   Form field name.
	 * @param array<int, array<string, mixed>>     $options      Pool options.
	 * @param string[]                             $selected     Selected values.
	 * @param array<string, string>                $range_labels Range slug => label.
	 * @return void
	 */
	private function render_pool_option_checkboxes($field_name, array $options, array $selected, array $range_labels) {
		$selected    = array_map('strval', $selected);
		$range_slugs = array();
		foreach ($options as $opt) {
			$slug = sanitize_title((string) ($opt['range_slug'] ?? ''));
			if ('' !== $slug) {
				$range_slugs[ $slug ] = true;
			}
		}
		$use_groups = count($range_slugs) >= 2;

		if (! $use_groups) {
			foreach ($options as $opt) {
				$this->render_single_pool_option_checkbox($field_name, $opt, $selected);
			}
			return;
		}

		$grouped   = array();
		$ungrouped = array();
		foreach ($options as $opt) {
			$slug = sanitize_title((string) ($opt['range_slug'] ?? ''));
			if ('' === $slug) {
				$ungrouped[] = $opt;
				continue;
			}
			if (! isset($grouped[ $slug ])) {
				$grouped[ $slug ] = array();
			}
			$grouped[ $slug ][] = $opt;
		}
		ksort($grouped);
		foreach ($grouped as $slug => $items) {
			$title = $range_labels[ $slug ] ?? ucwords(str_replace('-', ' ', $slug));
			echo '<div class="mbbb-deal-range-group">';
			echo '<div class="mbbb-deal-range-group__title">' . esc_html($title) . '</div>';
			foreach ($items as $opt) {
				$this->render_single_pool_option_checkbox($field_name, $opt, $selected);
			}
			echo '</div>';
		}
		foreach ($ungrouped as $opt) {
			$this->render_single_pool_option_checkbox($field_name, $opt, $selected);
		}
	}

	/**
	 * @param string                           $field_name Form field.
	 * @param array<string, mixed>             $opt        Option row.
	 * @param string[]                         $selected   Selected values.
	 * @return void
	 */
	private function render_single_pool_option_checkbox($field_name, array $opt, array $selected) {
		$value = (string) ($opt['value'] ?? '');
		$label = (string) ($opt['label'] ?? $value);
		if ('' === $value) {
			return;
		}
		?>
		<label class="mbbb-deal-range">
			<input type="checkbox" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $selected, true)); ?> />
			<?php echo esc_html($label); ?>
		</label>
		<?php
	}

	/**
	 * AJAX: product suggestions when adding bundle options.
	 *
	 * @return void
	 */
	public function ajax_pool_product_suggest() {
		check_ajax_referer('mbbb_pool_options', 'nonce');
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'mad-baits-bundle-builder')), 403);
		}

		$product_id = absint($_POST['product_id'] ?? 0);
		if ($product_id < 1) {
			wp_send_json_error(array('message' => __('Choose a product.', 'mad-baits-bundle-builder')));
		}

		$duplicate = null;
		foreach (MBBB_Pool_Options::get_bundle_types() as $type_key => $type_meta) {
			$found = MBBB_Pool_Options::find_existing_option($type_meta['pool_id'], $product_id);
			if ($found) {
				$duplicate = array(
					'pool_id'    => $found['pool_id'],
					'type'       => $type_key,
					'type_label' => $type_meta['label'],
					'value'      => (string) ($found['option']['value'] ?? ''),
				);
				break;
			}
		}

		wp_send_json_success(
			array(
				'display_name'    => MBBB_Pool_Options::format_product_display_name($product_id),
				'health'          => MBBB_Pool_Options::get_product_health($product_id),
				'type'            => MBBB_Pool_Options::suggest_type($product_id),
				'range'           => MBBB_Pool_Options::suggest_range($product_id),
				'duplicate'       => $duplicate,
				'suggested_label' => MBBB_Pool_Options::format_product_display_name($product_id),
			)
		);
	}

	/**
	 * Client-friendly Bundle Product Options screen (no JSON).
	 *
	 * @return void
	 */
	private function render_pools_screen() {
		$plugin    = MBBB_Plugin::instance();
		$pools     = $plugin->get_pools();
		$types     = MBBB_Pool_Options::get_bundle_types();
		$focus_ids = array();
		foreach ($types as $meta) {
			$focus_ids[] = $meta['pool_id'];
		}
		$ranges         = MBBB_Pool_Options::get_all_ranges();
		$back_url       = admin_url('admin.php?page=mbbb-deals');
		$suggestions    = MBBB_Pool_Options::get_suggested_products();
		$suggest_on     = MBBB_Pool_Options::is_suggest_enabled();
		$options_action = admin_url('admin.php?page=mbbb-deals-options');
		?>
		<div class="wrap mbbb-deals-wrap mbbb-deals-wrap--options">
			<h1><?php esc_html_e('Bundle Product Options', 'mad-baits-bundle-builder'); ?></h1>
			<p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Bundle Deals', 'mad-baits-bundle-builder'); ?></a></p>

			<div class="mbbb-deal-panel mbbb-deal-panel--intro">
				<p><?php esc_html_e('Add WooCommerce bait products here so customers can choose them inside bundle deals. You do not need product IDs or JSON — search by name, confirm the type and range, then save.', 'mad-baits-bundle-builder'); ?></p>
				<p class="mbbb-deal-panel__warning"><?php esc_html_e('Turn Visible off to hide an option temporarily. Use Remove only when you want to delete it from shared bundle options — live deals may be affected.', 'mad-baits-bundle-builder'); ?></p>
			</div>

			<div class="mbbb-pool-options-toolbar">
				<button type="button" class="button button-primary" id="mbbb-open-add-option"><?php esc_html_e('Add product option', 'mad-baits-bundle-builder'); ?></button>
				<button type="button" class="button" id="mbbb-open-bulk-add"><?php esc_html_e('Bulk add products', 'mad-baits-bundle-builder'); ?></button>
			</div>

			<form method="post" action="<?php echo esc_url($options_action); ?>" class="mbbb-pool-options-settings">
				<?php wp_nonce_field('mbbb_save_pool_options_settings'); ?>
				<div class="mbbb-deal-panel">
					<h2><?php esc_html_e('Settings', 'mad-baits-bundle-builder'); ?></h2>
					<label>
						<input type="checkbox" name="suggest_new_pool_products" value="1" <?php checked($suggest_on); ?> />
						<?php esc_html_e('Automatically suggest new bait products for bundles', 'mad-baits-bundle-builder'); ?>
					</label>
					<p class="description"><?php esc_html_e('When enabled, newly published products that look like bait are listed below for review. Nothing is added to bundle deals until you confirm.', 'mad-baits-bundle-builder'); ?></p>
					<p><button type="submit" name="mbbb_save_pool_options_settings" class="button"><?php esc_html_e('Save settings', 'mad-baits-bundle-builder'); ?></button></p>
				</div>
			</form>

			<?php if ($suggest_on && ! empty($suggestions)) : ?>
				<div class="mbbb-deal-panel mbbb-pool-suggestions">
					<h2><?php esc_html_e('Suggested products', 'mad-baits-bundle-builder'); ?></h2>
					<p class="description"><?php esc_html_e('Review these products before adding them to bundle deals.', 'mad-baits-bundle-builder'); ?></p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></th>
								<th><?php esc_html_e('Suggested type', 'mad-baits-bundle-builder'); ?></th>
								<th><?php esc_html_e('Suggested range', 'mad-baits-bundle-builder'); ?></th>
								<th><?php esc_html_e('Actions', 'mad-baits-bundle-builder'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($suggestions as $row) : ?>
								<tr>
									<td><?php echo esc_html((string) $row['name']); ?></td>
									<td><?php echo esc_html((string) ($row['type_label'] ?: '—')); ?></td>
									<td><?php echo esc_html((string) ($row['range_label'] ?: '—')); ?></td>
									<td class="mbbb-pool-suggestions__actions">
										<button type="button" class="button button-small mbbb-suggestion-add"
											data-product-id="<?php echo esc_attr((string) $row['id']); ?>"
											data-type="<?php echo esc_attr((string) $row['type']); ?>"
											data-range-slug="<?php echo esc_attr((string) $row['range_slug']); ?>"
											data-range-label="<?php echo esc_attr((string) $row['range_label']); ?>"
											data-name="<?php echo esc_attr((string) $row['name']); ?>">
											<?php esc_html_e('Add to bundle options', 'mad-baits-bundle-builder'); ?>
										</button>
										<a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('mbbb_ignore_suggestion' => '1', 'product_id' => absint($row['id'])), $options_action), 'mbbb_ignore_suggestion')); ?>">
											<?php esc_html_e('Ignore', 'mad-baits-bundle-builder'); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url($options_action); ?>" id="mbbb-deal-pools-form">
				<?php wp_nonce_field('mbbb_save_deal_pools'); ?>

				<div class="mbbb-deals-options-toolbar">
					<label class="screen-reader-text" for="mbbb-deal-options-search"><?php esc_html_e('Search bundle options', 'mad-baits-bundle-builder'); ?></label>
					<input type="search" id="mbbb-deal-options-search" class="regular-text" placeholder="<?php esc_attr_e('Search bundle options…', 'mad-baits-bundle-builder'); ?>" autocomplete="off" />
				</div>

				<div class="mbbb-deals-options-save-bar">
					<button type="submit" name="mbbb_save_deal_pools" class="button button-primary"><?php esc_html_e('Save changes', 'mad-baits-bundle-builder'); ?></button>
				</div>

				<?php foreach ($focus_ids as $pool_id) : ?>
					<?php
					$type_key     = MBBB_Pool_Options::type_for_pool_id($pool_id);
					$type_meta    = $types[ $type_key ] ?? array();
					$section_name = (string) ($type_meta['label'] ?? ($pools[ $pool_id ]['name'] ?? $pool_id));
					$has_range    = ! empty($type_meta['has_range']);
					$options      = (array) ($pools[ $pool_id ]['options'] ?? array());
					$option_count = count($options);
					$is_open      = ('standard-boilie-choices' === $pool_id);
					?>
					<details class="mbbb-deal-pool-section" <?php echo $is_open ? 'open' : ''; ?>>
						<summary class="mbbb-deal-pool-section__summary">
							<?php echo esc_html($section_name); ?>
							<span class="mbbb-deal-pool-section__count">(<?php echo esc_html((string) $option_count); ?>)</span>
						</summary>
						<div class="mbbb-deal-pool-section__body">
							<?php if (empty($options)) : ?>
								<p class="description"><?php esc_html_e('No products in this group yet. Use Add product option above.', 'mad-baits-bundle-builder'); ?></p>
							<?php else : ?>
								<table class="widefat striped mbbb-deal-pools-table">
									<thead>
										<tr>
											<th><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></th>
											<th><?php esc_html_e('Customer label', 'mad-baits-bundle-builder'); ?></th>
											<?php if ($has_range) : ?>
												<th><?php esc_html_e('Range', 'mad-baits-bundle-builder'); ?></th>
											<?php endif; ?>
											<th><?php esc_html_e('Visible', 'mad-baits-bundle-builder'); ?></th>
											<th><?php esc_html_e('Used by', 'mad-baits-bundle-builder'); ?></th>
											<th><?php esc_html_e('Actions', 'mad-baits-bundle-builder'); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($options as $index => $opt) : ?>
											<?php
											if (! is_array($opt)) {
												continue;
											}
											$label_text   = (string) ($opt['label'] ?? '');
											$product_id   = absint($opt['product_id'] ?? 0);
											$product_name = $product_id > 0 ? MBBB_Pool_Options::format_product_display_name($product_id) : '';
											$health       = MBBB_Pool_Options::get_product_health($product_id);
											$current_range = sanitize_title((string) ($opt['range_slug'] ?? MBBB_Deal_Builder::match_option_to_range_slug($label_text, $opt)));
											$range_text    = $ranges[ $current_range ] ?? $current_range;
											$usage_count   = MBBB_Pool_Options::count_deals_using_option($pool_id, $opt);
											$usage_deals   = MBBB_Pool_Options::get_deals_using_option($pool_id, $opt);
											$search_blob   = strtolower(trim($label_text . ' ' . $range_text . ' ' . $product_name));
											?>
											<tr data-mbbb-option-search="<?php echo esc_attr($search_blob); ?>">
												<td class="mbbb-pool-option-product">
													<?php if ($product_id > 0 && 'missing' !== $health['state']) : ?>
														<a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>"><?php echo esc_html($product_name); ?></a>
													<?php elseif ($product_id > 0) : ?>
														<span class="mbbb-pool-health mbbb-pool-health--missing"><?php echo esc_html($product_name); ?></span>
													<?php else : ?>
														<span class="description"><?php esc_html_e('No product linked', 'mad-baits-bundle-builder'); ?></span>
													<?php endif; ?>
													<?php if ('ok' !== $health['state'] && '' !== $health['label']) : ?>
														<div class="mbbb-pool-health mbbb-pool-health--<?php echo esc_attr($health['state']); ?>"><?php echo esc_html($health['label']); ?></div>
													<?php endif; ?>
												</td>
												<td>
													<input type="text" class="regular-text" name="pool[<?php echo esc_attr($pool_id); ?>][<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($label_text); ?>" />
													<input type="hidden" name="pool[<?php echo esc_attr($pool_id); ?>][<?php echo esc_attr((string) $index); ?>][value]" value="<?php echo esc_attr((string) ($opt['value'] ?? '')); ?>" />
													<input type="hidden" name="pool[<?php echo esc_attr($pool_id); ?>][<?php echo esc_attr((string) $index); ?>][product_id]" value="<?php echo esc_attr((string) $product_id); ?>" />
													<input type="hidden" name="pool[<?php echo esc_attr($pool_id); ?>][<?php echo esc_attr((string) $index); ?>][image]" value="<?php echo esc_attr((string) ($opt['image'] ?? '')); ?>" />
												</td>
												<?php if ($has_range) : ?>
													<td>
														<select name="pool[<?php echo esc_attr($pool_id); ?>][<?php echo esc_attr((string) $index); ?>][range_slug]">
															<option value=""><?php esc_html_e('Auto-detect', 'mad-baits-bundle-builder'); ?></option>
															<?php foreach ($ranges as $slug => $range_label) : ?>
																<option value="<?php echo esc_attr($slug); ?>" <?php selected($current_range, $slug); ?>><?php echo esc_html($range_label); ?></option>
															<?php endforeach; ?>
														</select>
													</td>
												<?php endif; ?>
												<td>
													<label>
														<input type="checkbox" name="pool[<?php echo esc_attr($pool_id); ?>][<?php echo esc_attr((string) $index); ?>][active]" value="1" <?php checked(! array_key_exists('active', (array) $opt) || ! empty($opt['active'])); ?> />
														<?php esc_html_e('Visible', 'mad-baits-bundle-builder'); ?>
													</label>
												</td>
												<td class="mbbb-pool-usage">
													<?php if ($usage_count > 0) : ?>
														<details class="mbbb-pool-usage__details">
															<summary><?php
															printf(
																/* translators: %d: number of deals */
																esc_html(_n('Used by %d deal', 'Used by %d deals', $usage_count, 'mad-baits-bundle-builder')),
																(int) $usage_count
															);
															?></summary>
															<ul>
																<?php foreach ($usage_deals as $deal) : ?>
																	<li>
																		<a href="<?php echo esc_url(admin_url('admin.php?page=mbbb-deals&action=edit&deal_id=' . absint($deal['id']))); ?>">
																			<?php echo esc_html((string) $deal['name']); ?>
																		</a>
																		<?php if (empty($deal['active'])) : ?>
																			<span class="description"><?php esc_html_e('(disabled)', 'mad-baits-bundle-builder'); ?></span>
																		<?php endif; ?>
																	</li>
																<?php endforeach; ?>
															</ul>
														</details>
													<?php else : ?>
														<span class="description">—</span>
													<?php endif; ?>
												</td>
												<td>
													<button type="submit"
														form="mbbb-remove-pool-option-<?php echo esc_attr($pool_id . '-' . sanitize_title((string) ($opt['value'] ?? (string) $index))); ?>"
														class="button button-link-delete mbbb-pool-remove-btn"
														data-usage="<?php echo esc_attr((string) $usage_count); ?>">
														<?php esc_html_e('Remove', 'mad-baits-bundle-builder'); ?>
													</button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>

				<p class="submit mbbb-deals-options-save-bottom">
					<button type="submit" name="mbbb_save_deal_pools" class="button button-primary"><?php esc_html_e('Save changes', 'mad-baits-bundle-builder'); ?></button>
				</p>
			</form>

			<?php foreach ($focus_ids as $pool_id) : ?>
				<?php
				$options = (array) ($pools[ $pool_id ]['options'] ?? array());
				foreach ($options as $index => $opt) :
					if (! is_array($opt)) {
						continue;
					}
					$value = (string) ($opt['value'] ?? (string) $index);
					$form_id = 'mbbb-remove-pool-option-' . $pool_id . '-' . sanitize_title($value);
					$usage_count = MBBB_Pool_Options::count_deals_using_option($pool_id, $opt);
					?>
					<form method="post" action="<?php echo esc_url($options_action); ?>" id="<?php echo esc_attr($form_id); ?>" class="mbbb-pool-remove-form" hidden>
						<?php wp_nonce_field('mbbb_remove_pool_option'); ?>
						<input type="hidden" name="pool_id" value="<?php echo esc_attr($pool_id); ?>" />
						<input type="hidden" name="option_value" value="<?php echo esc_attr($value); ?>" />
						<?php if ($usage_count > 0) : ?>
							<input type="hidden" name="confirm_remove_used" value="" class="mbbb-confirm-remove-used" />
						<?php endif; ?>
					</form>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<div id="mbbb-add-option-modal" class="mbbb-pool-modal" hidden>
				<div class="mbbb-pool-modal__backdrop" data-mbbb-close-modal></div>
				<div class="mbbb-pool-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mbbb-add-option-title">
					<button type="button" class="mbbb-pool-modal__close" data-mbbb-close-modal aria-label="<?php esc_attr_e('Close', 'mad-baits-bundle-builder'); ?>">&times;</button>
					<h2 id="mbbb-add-option-title"><?php esc_html_e('Add product option', 'mad-baits-bundle-builder'); ?></h2>
					<form method="post" action="<?php echo esc_url($options_action); ?>" id="mbbb-add-pool-option-form">
						<?php wp_nonce_field('mbbb_add_pool_option'); ?>
						<input type="hidden" name="wc_product_id" id="mbbb-add-wc-product-id" value="" />
						<p>
							<label for="mbbb-add-product-search"><strong><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select id="mbbb-add-product-search" class="wc-product-search mbbb-pool-product-search" data-placeholder="<?php esc_attr_e('Search WooCommerce products…', 'mad-baits-bundle-builder'); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"></select>
						</p>
						<div id="mbbb-add-duplicate-notice" class="notice notice-warning inline mbbb-pool-inline-notice" hidden></div>
						<p>
							<label for="mbbb-add-bundle-type"><strong><?php esc_html_e('Type', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select name="bundle_type" id="mbbb-add-bundle-type" required>
								<option value=""><?php esc_html_e('Choose type…', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($types as $key => $meta) : ?>
									<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($meta['label']); ?></option>
								<?php endforeach; ?>
							</select>
							<span id="mbbb-add-type-suggestion" class="description mbbb-pool-suggestion-note"></span>
						</p>
						<p>
							<label for="mbbb-add-customer-label"><strong><?php esc_html_e('Customer label', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<input type="text" class="regular-text" name="customer_label" id="mbbb-add-customer-label" />
						</p>
						<p id="mbbb-add-range-wrap">
							<label for="mbbb-add-range-slug"><strong><?php esc_html_e('Range', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select name="range_slug" id="mbbb-add-range-slug">
								<option value=""><?php esc_html_e('Choose range…', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($ranges as $slug => $range_label) : ?>
									<option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($range_label); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="regular-text" name="range_label" id="mbbb-add-range-label" placeholder="<?php esc_attr_e('New range name (optional)', 'mad-baits-bundle-builder'); ?>" />
							<label><input type="checkbox" name="register_range" id="mbbb-add-register-range" value="1" /> <?php esc_html_e('Register as a new range for bundle deals', 'mad-baits-bundle-builder'); ?></label>
							<span id="mbbb-add-range-suggestion" class="description mbbb-pool-suggestion-note"></span>
						</p>
						<p>
							<label><input type="checkbox" name="option_visible" value="1" checked /> <?php esc_html_e('Visible', 'mad-baits-bundle-builder'); ?></label>
						</p>
						<p class="submit">
							<button type="submit" name="mbbb_add_pool_option" class="button button-primary"><?php esc_html_e('Add product option', 'mad-baits-bundle-builder'); ?></button>
							<button type="button" class="button" data-mbbb-close-modal><?php esc_html_e('Cancel', 'mad-baits-bundle-builder'); ?></button>
						</p>
					</form>
				</div>
			</div>

			<div id="mbbb-bulk-add-modal" class="mbbb-pool-modal" hidden>
				<div class="mbbb-pool-modal__backdrop" data-mbbb-close-modal></div>
				<div class="mbbb-pool-modal__dialog mbbb-pool-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="mbbb-bulk-add-title">
					<button type="button" class="mbbb-pool-modal__close" data-mbbb-close-modal aria-label="<?php esc_attr_e('Close', 'mad-baits-bundle-builder'); ?>">&times;</button>
					<h2 id="mbbb-bulk-add-title"><?php esc_html_e('Bulk add products', 'mad-baits-bundle-builder'); ?></h2>
					<form method="post" action="<?php echo esc_url($options_action); ?>" id="mbbb-bulk-add-form">
						<?php wp_nonce_field('mbbb_bulk_add_pool_options'); ?>
						<p>
							<label for="mbbb-bulk-product-search"><strong><?php esc_html_e('Search products', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select id="mbbb-bulk-product-search" class="wc-product-search mbbb-pool-product-search" data-placeholder="<?php esc_attr_e('Search WooCommerce products…', 'mad-baits-bundle-builder'); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"></select>
							<span class="description"><?php esc_html_e('Search and select products one at a time to build your list, then add them together.', 'mad-baits-bundle-builder'); ?></span>
						</p>
						<p>
							<label for="mbbb-bulk-range-slug"><strong><?php esc_html_e('Range for selected products', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select name="bulk_range_slug" id="mbbb-bulk-range-slug">
								<option value=""><?php esc_html_e('Use suggestion per product', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($ranges as $slug => $range_label) : ?>
									<option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($range_label); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" class="regular-text" name="bulk_range_label" id="mbbb-bulk-range-label" placeholder="<?php esc_attr_e('New range name', 'mad-baits-bundle-builder'); ?>" />
						</p>
						<table class="widefat striped" id="mbbb-bulk-staging-table">
							<thead>
								<tr>
									<th class="check-column"><input type="checkbox" id="mbbb-bulk-select-all" checked /></th>
									<th><?php esc_html_e('Product', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Type', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Customer label', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Range', 'mad-baits-bundle-builder'); ?></th>
									<th><?php esc_html_e('Visible', 'mad-baits-bundle-builder'); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
						<p class="submit">
							<button type="submit" name="mbbb_bulk_add_pool_options" class="button button-primary"><?php esc_html_e('Add selected', 'mad-baits-bundle-builder'); ?></button>
							<button type="button" class="button" data-mbbb-close-modal><?php esc_html_e('Cancel', 'mad-baits-bundle-builder'); ?></button>
						</p>
					</form>
				</div>
			</div>

			<p class="description mbbb-deals-wrap__advanced">
				<?php
				printf(
					/* translators: %s: advanced tools link */
					esc_html__('For raw JSON and developer controls, use %s.', 'mad-baits-bundle-builder'),
					'<a href="' . esc_url(admin_url('admin.php?page=mbbb-tools&tab=pools')) . '">' . esc_html__('Bundle Deals → Advanced → Global Option Pools', 'mad-baits-bundle-builder') . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
