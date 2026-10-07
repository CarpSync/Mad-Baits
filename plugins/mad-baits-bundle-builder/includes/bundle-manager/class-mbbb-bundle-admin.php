<?php
/**
 * MadBaits admin menu and owner bundle screens.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Admin UI.
 */
final class MBBB_Bundle_Admin {

	/**
	 * @return string
	 */
	public static function capability() {
		return 'manage_woocommerce';
	}

	/**
	 * @return void
	 */
	public function init() {
		add_action('admin_menu', array($this, 'register_menu'), 58);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_init', array($this, 'handle_actions'));
		add_action('wp_ajax_mb_bundle_eligibility', array($this, 'ajax_eligibility'));
		add_filter('admin_body_class', array($this, 'body_class'));
	}

	/**
	 * @return void
	 */
	public function register_menu() {
		if (! current_user_can(self::capability())) {
			return;
		}

		add_menu_page(
			__('MadBaits', 'mad-baits-bundle-builder'),
			__('MadBaits', 'mad-baits-bundle-builder'),
			self::capability(),
			'madbaits',
			array($this, 'render_products'),
			'dashicons-cart',
			56
		);
		add_submenu_page('madbaits', __('Products', 'mad-baits-bundle-builder'), __('Products', 'mad-baits-bundle-builder'), self::capability(), 'madbaits', array($this, 'render_products'));
		add_submenu_page('madbaits', __('Bundles', 'mad-baits-bundle-builder'), __('Bundles', 'mad-baits-bundle-builder'), self::capability(), 'madbaits-bundles', array($this, 'render_bundles'));
		add_submenu_page('madbaits', __('Discounts', 'mad-baits-bundle-builder'), __('Discounts', 'mad-baits-bundle-builder'), self::capability(), 'madbaits-discounts', array($this, 'render_discounts'));
		add_submenu_page('madbaits', __('Stock', 'mad-baits-bundle-builder'), __('Stock', 'mad-baits-bundle-builder'), self::capability(), 'madbaits-stock', array($this, 'render_stock'));
		add_submenu_page('madbaits', __('Orders', 'mad-baits-bundle-builder'), __('Orders', 'mad-baits-bundle-builder'), self::capability(), 'madbaits-orders', array($this, 'render_orders'));
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function body_class($classes) {
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
		if (0 === strpos($page, 'madbaits')) {
			$classes .= ' mb-manager-screen';
		}
		return $classes;
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets($hook) {
		unset($hook);
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
		if (0 !== strpos($page, 'madbaits')) {
			return;
		}

		wp_enqueue_style(
			'mb-bundle-manager',
			MBBB_PLUGIN_URL . 'assets/css/mb-bundle-manager.css',
			array(),
			MBBB_VERSION
		);

		if ('madbaits-bundles' !== $page) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'mb-bundle-manager',
			MBBB_PLUGIN_URL . 'assets/js/mb-bundle-manager.js',
			array('jquery'),
			MBBB_VERSION,
			true
		);
		wp_localize_script(
			'mb-bundle-manager',
			'mbBundleManager',
			array(
				'ajaxUrl'  => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('mb_bundle_admin'),
				'currency' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '£',
				'i18n'     => array(
					'eligible'   => __('%d eligible product variations', 'mad-baits-bundle-builder'),
					'eligibleOne'=> __('1 eligible product variation', 'mad-baits-bundle-builder'),
					'choose'     => __('Choose any %1$d %2$s', 'mad-baits-bundle-builder'),
					'between'    => __('Choose %1$d to %2$d %3$s', 'mad-baits-bundle-builder'),
					'pays'       => __('Customer pays: %s', 'mad-baits-bundle-builder'),
					'percent'    => __('Customer receives: %d%% discount', 'mad-baits-bundle-builder'),
					'amount'     => __('Customer receives: %s off', 'mad-baits-bundle-builder'),
					'confirmDelete' => __('Delete this bundle? It will be moved to the trash.', 'mad-baits-bundle-builder'),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function handle_actions() {
		if (! is_admin() || ! current_user_can(self::capability())) {
			return;
		}
		$page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash((string) $_REQUEST['page'])) : '';
		if ('madbaits-bundles' !== $page) {
			return;
		}

		$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : '';
		$bundle_id = isset($_REQUEST['bundle_id']) ? absint($_REQUEST['bundle_id']) : 0;

		if ('POST' === strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) && isset($_POST['mb_bundle_save'])) {
			check_admin_referer('mb_bundle_save');
			$posted = wp_unslash($_POST);
			$posted = self::normalise_fixed_items($posted);
			$result = MBBB_Bundle_Service::save_from_post($posted);
			if ($result['success']) {
				$this->flash(__('Bundle saved.', 'mad-baits-bundle-builder'));
				wp_safe_redirect(admin_url('admin.php?page=madbaits-bundles&action=edit&bundle_id=' . absint($result['product_id'])));
				exit;
			}
			set_transient('mb_bundle_errors_' . get_current_user_id(), $result['errors'], 60);
			set_transient('mb_bundle_posted_' . get_current_user_id(), $posted, 60);
			$back = absint($result['product_id']) > 0
				? admin_url('admin.php?page=madbaits-bundles&action=edit&bundle_id=' . absint($result['product_id']))
				: admin_url('admin.php?page=madbaits-bundles&action=new');
			wp_safe_redirect($back);
			exit;
		}

		if ('duplicate' === $action && $bundle_id > 0) {
			check_admin_referer('mb_bundle_duplicate_' . $bundle_id);
			$result = MBBB_Bundle_Service::duplicate($bundle_id);
			if ($result['success']) {
				$this->flash(__('Bundle duplicated as a draft.', 'mad-baits-bundle-builder'));
				wp_safe_redirect(admin_url('admin.php?page=madbaits-bundles&action=edit&bundle_id=' . absint($result['product_id'])));
				exit;
			}
			$this->flash(implode(' ', $result['errors']));
			wp_safe_redirect(admin_url('admin.php?page=madbaits-bundles'));
			exit;
		}

		if ('toggle' === $action && $bundle_id > 0) {
			check_admin_referer('mb_bundle_toggle_' . $bundle_id);
			$state  = MBBB_Bundle_Repository::editor_state($bundle_id);
			$target = 'active' === MBBB_Bundle_Config::effective_status($state['config'], (string) current_time('Y-m-d')) ? 'disabled' : 'active';
			$result = MBBB_Bundle_Service::change_status($bundle_id, $target);
			if (! $result['success']) {
				$this->flash(implode(' ', $result['errors']));
			} else {
				$saved     = MBBB_Bundle_Repository::get_owner_config($bundle_id);
				$today     = (string) current_time('Y-m-d');
				$effective = is_array($saved) ? MBBB_Bundle_Config::effective_status($saved, $today) : $target;
				if ('active' === $effective) {
					$this->flash(__('Bundle is now active.', 'mad-baits-bundle-builder'));
				} elseif ('disabled' === $effective || 'draft' === $effective) {
					$this->flash(__('Bundle disabled. Customers cannot buy it.', 'mad-baits-bundle-builder'));
				} else {
					$this->flash(
						sprintf(
							/* translators: %s: Scheduled or Ended */
							__('Bundle saved. Customers cannot buy it yet (%s).', 'mad-baits-bundle-builder'),
							MBBB_Bundle_Config::status_label($effective, is_array($saved) ? $saved : array(), $today)
						)
					);
				}
			}
			wp_safe_redirect(admin_url('admin.php?page=madbaits-bundles'));
			exit;
		}

		if ('restore' === $action && $bundle_id > 0) {
			check_admin_referer('mb_bundle_restore_' . $bundle_id);
			$restored = MBBB_Bundle_Repository::restore_snapshot($bundle_id);
			$this->flash($restored
				? __('Original customer choices restored.', 'mad-baits-bundle-builder')
				: __('There were no saved original choices to restore.', 'mad-baits-bundle-builder'));
			wp_safe_redirect(admin_url('admin.php?page=madbaits-bundles&action=edit&bundle_id=' . $bundle_id));
			exit;
		}

		if ('delete' === $action && $bundle_id > 0 && isset($_POST['mb_bundle_delete_confirm'])) {
			check_admin_referer('mb_bundle_delete_' . $bundle_id);
			$trashed = MBBB_Bundle_Repository::trash($bundle_id);
			$this->flash($trashed
				? __('Bundle moved to the bin.', 'mad-baits-bundle-builder')
				: __('That bundle could not be removed.', 'mad-baits-bundle-builder'));
			wp_safe_redirect(admin_url('admin.php?page=madbaits-bundles'));
			exit;
		}
	}

	/**
	 * @return void
	 */
	public function ajax_eligibility() {
		check_ajax_referer('mb_bundle_admin', 'nonce');
		if (! current_user_can(self::capability())) {
			wp_send_json_error(array('message' => __('You do not have permission to manage bundles.', 'mad-baits-bundle-builder')), 403);
		}
		$posted  = isset($_POST['mb_bundle']) && is_array($_POST['mb_bundle']) ? wp_unslash($_POST['mb_bundle']) : array();
		$config  = MBBB_Bundle_Config::sanitize($posted);
		$catalog = MBBB_Bundle_Repository::catalogue();
		$matched = MBBB_Bundle_Eligibility::matching($catalog['variations'], $config);
		$live    = MBBB_Bundle_Eligibility::purchasable($catalog['variations'], $config);
		$names   = array();
		foreach (array_slice($live, 0, 8) as $row) {
			$names[] = (string) ($row['name'] ?? '');
		}
		wp_send_json_success(
			array(
				'total' => count($matched),
				'live'  => count($live),
				'names' => $names,
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_bundles() {
		$this->guard();
		$action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
		if ('delete' === $action) {
			$this->render_delete_confirm();
			return;
		}
		if ('edit' === $action || 'new' === $action) {
			$this->render_editor();
			return;
		}
		$this->render_list();
	}

	/**
	 * @return void
	 */
	public function render_products() {
		$this->guard();
		$this->open_shell(__('Products', 'mad-baits-bundle-builder'), __('Jump into the WooCommerce catalogue. Bundle deals are managed separately so everyday product edits stay where they are.', 'mad-baits-bundle-builder'));
		$this->link_cards(
			array(
				array('label' => __('All products', 'mad-baits-bundle-builder'), 'url' => admin_url('edit.php?post_type=product'), 'text' => __('View and edit the live catalogue.', 'mad-baits-bundle-builder')),
				array('label' => __('Add a product', 'mad-baits-bundle-builder'), 'url' => admin_url('post-new.php?post_type=product'), 'text' => __('Create a single bait, liquid, or item.', 'mad-baits-bundle-builder')),
				array('label' => __('Bundles', 'mad-baits-bundle-builder'), 'url' => admin_url('admin.php?page=madbaits-bundles'), 'text' => __('Create mix-and-match and fixed bundles.', 'mad-baits-bundle-builder')),
			)
		);
		$this->close_shell();
	}

	/**
	 * @return void
	 */
	public function render_discounts() {
		$this->guard();
		$this->open_shell(__('Discounts', 'mad-baits-bundle-builder'), __('Bundle prices are set on each bundle. Role discounts, such as team pricing, still apply at checkout when that plugin is installed. There is no separate bundle pricing-rule language to learn.', 'mad-baits-bundle-builder'));
		$this->link_cards(
			array(
				array('label' => __('Bundle prices', 'mad-baits-bundle-builder'), 'url' => admin_url('admin.php?page=madbaits-bundles'), 'text' => __('Set a fixed price, a percentage off, or an amount off.', 'mad-baits-bundle-builder')),
				array('label' => __('Coupons', 'mad-baits-bundle-builder'), 'url' => admin_url('edit.php?post_type=shop_coupon'), 'text' => __('WooCommerce coupons customers can enter at checkout.', 'mad-baits-bundle-builder')),
			)
		);
		$this->close_shell();
	}

	/**
	 * @return void
	 */
	public function render_stock() {
		$this->guard();
		$this->open_shell(__('Stock', 'mad-baits-bundle-builder'), __('Bundles do not keep a second stock count. If a bait is out of stock in WooCommerce, customers cannot pick it inside a bundle.', 'mad-baits-bundle-builder'));
		$this->link_cards(
			array(
				array('label' => __('Product stock', 'mad-baits-bundle-builder'), 'url' => admin_url('edit.php?post_type=product'), 'text' => __('Update stock on the products customers choose from.', 'mad-baits-bundle-builder')),
				array('label' => __('Bundles', 'mad-baits-bundle-builder'), 'url' => admin_url('admin.php?page=madbaits-bundles'), 'text' => __('See a warning when a bundle does not have enough products available.', 'mad-baits-bundle-builder')),
			)
		);
		$this->close_shell();
	}

	/**
	 * @return void
	 */
	public function render_orders() {
		$this->guard();
		$this->open_shell(__('Orders', 'mad-baits-bundle-builder'), __('Orders stay in WooCommerce. A bundle is one line, with the customer’s choices saved on that line.', 'mad-baits-bundle-builder'));
		$orders_url = admin_url('admin.php?page=wc-orders');
		$this->link_cards(
			array(
				array('label' => __('View orders', 'mad-baits-bundle-builder'), 'url' => $orders_url, 'text' => __('Open the WooCommerce orders list.', 'mad-baits-bundle-builder')),
			)
		);
		$this->close_shell();
	}

	/**
	 * @return void
	 */
	private function render_list() {
		$status = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : 'all';
		$type   = isset($_GET['bundle_type']) ? sanitize_key(wp_unslash((string) $_GET['bundle_type'])) : 'all';
		$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
		$rows   = MBBB_Bundle_Repository::list_bundles(
			array(
				'status' => $status,
				'type'   => $type,
				'search' => $search,
			)
		);

		$this->open_shell(__('Bundles', 'mad-baits-bundle-builder'), __('Create and update bait bundles without using the advanced product screens.', 'mad-baits-bundle-builder'));
		$this->render_notice();
		?>
		<div class="mb-manager__toolbar">
			<a class="mb-manager__button" href="<?php echo esc_url(admin_url('admin.php?page=madbaits-bundles&action=new')); ?>"><?php esc_html_e('Create Bundle', 'mad-baits-bundle-builder'); ?></a>
			<form class="mb-manager__search-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
				<input type="hidden" name="page" value="madbaits-bundles" />
				<?php if ('all' !== $status) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" />
				<?php endif; ?>
				<?php if ('all' !== $type) : ?>
					<input type="hidden" name="bundle_type" value="<?php echo esc_attr($type); ?>" />
				<?php endif; ?>
				<label class="screen-reader-text" for="mb-bundle-search"><?php esc_html_e('Search bundles', 'mad-baits-bundle-builder'); ?></label>
				<input id="mb-bundle-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search bundles', 'mad-baits-bundle-builder'); ?>" />
			</form>
		</div>
		<nav class="mb-manager__filters" aria-label="<?php esc_attr_e('Filter bundles', 'mad-baits-bundle-builder'); ?>">
			<?php
			$this->filter_link(__('All', 'mad-baits-bundle-builder'), 'all', $status, 'status');
			$this->filter_link(__('Active', 'mad-baits-bundle-builder'), 'active', $status, 'status');
			$this->filter_link(__('Draft', 'mad-baits-bundle-builder'), 'draft', $status, 'status');
			$this->filter_link(__('Disabled', 'mad-baits-bundle-builder'), 'disabled', $status, 'status');
			$this->filter_link(__('Scheduled', 'mad-baits-bundle-builder'), 'scheduled', $status, 'status');
			$this->filter_link(__('Mix & match', 'mad-baits-bundle-builder'), 'mix_and_match', $type, 'bundle_type');
			$this->filter_link(__('Fixed bundle', 'mad-baits-bundle-builder'), 'fixed', $type, 'bundle_type');
			?>
		</nav>
		<?php if (empty($rows)) : ?>
			<div class="mb-manager__empty">
				<h2><?php esc_html_e('No bundles match this view', 'mad-baits-bundle-builder'); ?></h2>
				<p><?php esc_html_e('Create a bundle, or clear the filters to see every existing deal.', 'mad-baits-bundle-builder'); ?></p>
			</div>
		<?php else : ?>
			<div class="mb-manager__cards">
				<?php foreach ($rows as $row) : ?>
					<article class="mb-manager__card">
						<div class="mb-manager__card-media">
							<?php if (! empty($row['image'])) : ?>
								<img src="<?php echo esc_url((string) $row['image']); ?>" alt="" />
							<?php else : ?>
								<span class="mb-manager__card-fallback" aria-hidden="true">MB</span>
							<?php endif; ?>
						</div>
						<div class="mb-manager__card-body">
							<div class="mb-manager__card-title">
								<h2><a href="<?php echo esc_url((string) $row['edit_url']); ?>"><?php echo esc_html((string) $row['name']); ?></a></h2>
								<span class="mb-manager__badge mb-manager__badge--<?php echo esc_attr((string) $row['status_key']); ?>"><?php echo esc_html((string) $row['status_label']); ?></span>
							</div>
							<p class="mb-manager__meta"><?php echo esc_html((string) $row['type_label']); ?></p>
							<ul class="mb-manager__facts">
								<li><?php echo esc_html((string) $row['quantity_label']); ?></li>
								<li><?php echo esc_html((string) $row['price_label']); ?></li>
								<li>
									<?php
									if (null === $row['eligible_count']) {
										esc_html_e('Uses the existing choices', 'mad-baits-bundle-builder');
									} else {
										echo esc_html(
											sprintf(
												/* translators: %d: eligible variation count */
												_n('%d eligible product', '%d eligible products', (int) $row['eligible_count'], 'mad-baits-bundle-builder'),
												(int) $row['eligible_count']
											)
										);
									}
									?>
								</li>
								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: date */
											__('Updated %s', 'mad-baits-bundle-builder'),
											(string) $row['modified']
										)
									);
									?>
								</li>
							</ul>
							<div class="mb-manager__card-actions">
								<a class="mb-manager__button mb-manager__button--small" href="<?php echo esc_url((string) $row['edit_url']); ?>"><?php esc_html_e('Edit', 'mad-baits-bundle-builder'); ?></a>
								<a class="mb-manager__button mb-manager__button--small mb-manager__button--ghost" href="<?php echo esc_url((string) $row['duplicate_url']); ?>"><?php esc_html_e('Duplicate', 'mad-baits-bundle-builder'); ?></a>
								<a class="mb-manager__button mb-manager__button--small mb-manager__button--ghost" href="<?php echo esc_url((string) $row['toggle_url']); ?>"><?php echo esc_html((string) $row['toggle_label']); ?></a>
								<a class="mb-manager__button mb-manager__button--small mb-manager__button--danger" href="<?php echo esc_url((string) $row['delete_url']); ?>"><?php esc_html_e('Delete', 'mad-baits-bundle-builder'); ?></a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
		$this->close_shell();
	}

	/**
	 * @return void
	 */
	private function render_editor() {
		$bundle_id = isset($_GET['bundle_id']) ? absint($_GET['bundle_id']) : 0;
		$state     = MBBB_Bundle_Repository::editor_state($bundle_id);
		$errors    = get_transient('mb_bundle_errors_' . get_current_user_id());
		$posted    = get_transient('mb_bundle_posted_' . get_current_user_id());
		delete_transient('mb_bundle_errors_' . get_current_user_id());
		delete_transient('mb_bundle_posted_' . get_current_user_id());
		if (is_array($posted)) {
			$state['config'] = MBBB_Bundle_Config::sanitize(MBBB_Bundle_Service::flatten_post($posted));
			if ($bundle_id > 0) {
				$state['config']['preserve_slots'] = ! empty($posted['update_choices']) ? false : $state['config']['preserve_slots'];
			}
		}
		$config  = $state['config'];
		$catalog = $state['catalog'];
		$is_new  = $bundle_id < 1;

		$this->open_shell(
			$is_new ? __('Create bundle', 'mad-baits-bundle-builder') : __('Edit bundle', 'mad-baits-bundle-builder'),
			__('Fill in what the customer chooses and what they pay. The website keeps using the existing bundle builder.', 'mad-baits-bundle-builder')
		);
		$this->render_notice();
		if (is_array($errors) && ! empty($errors)) {
			echo '<div class="mb-manager__errors" role="alert"><ul>';
			foreach ($errors as $error) {
				echo '<li>' . esc_html((string) $error) . '</li>';
			}
			echo '</ul></div>';
		}
		if (! empty($state['warning'])) {
			echo '<div class="mb-manager__warning" role="status">' . esc_html((string) $state['warning']) . '</div>';
		}
		if (! empty($state['legacy_locked'])) {
			echo '<div class="mb-manager__warning" role="status">' . esc_html__('This bundle is already on the website. Changing the name, price, or dates is safe. Tick “Update what customers can choose” only when you want to replace the current choices.', 'mad-baits-bundle-builder') . '</div>';
		}
		?>
		<form method="post" class="mb-manager__editor" id="mb-bundle-editor">
			<?php wp_nonce_field('mb_bundle_save'); ?>
			<input type="hidden" name="page" value="madbaits-bundles" />
			<input type="hidden" name="mb_bundle_save" value="1" />
			<input type="hidden" name="product_id" value="<?php echo esc_attr((string) $bundle_id); ?>" />
			<input type="hidden" name="intent" id="mb-bundle-intent" value="save" />
			<div class="mb-manager__layout">
				<div class="mb-manager__main">
					<?php $this->render_basic_section($config, (string) $state['image_url']); ?>
					<?php $this->render_type_section($config); ?>
					<?php $this->render_eligibility_section($config, $catalog, (int) $state['eligible_live']); ?>
					<?php $this->render_pricing_section($config); ?>
					<?php $this->render_rules_section($config); ?>
					<?php $this->render_stock_section($config); ?>
					<?php $this->render_display_section($config); ?>
					<?php if (! empty($state['legacy_locked'])) : ?>
						<label class="mb-manager__confirm">
							<input type="checkbox" name="update_choices" value="1" />
							<?php esc_html_e('Update what customers can choose', 'mad-baits-bundle-builder'); ?>
						</label>
					<?php endif; ?>
					<?php if (! empty($state['has_snapshot'])) : ?>
						<p><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=madbaits-bundles&action=restore&bundle_id=' . $bundle_id), 'mb_bundle_restore_' . $bundle_id)); ?>"><?php esc_html_e('Restore the original customer choices', 'mad-baits-bundle-builder'); ?></a></p>
					<?php endif; ?>
				</div>
				<?php $this->render_preview($config, (string) $state['image_url'], (array) $state['preview_names']); ?>
			</div>
			<div class="mb-manager__savebar">
				<?php if ($is_new || 'draft' === ($config['status'] ?? '')) : ?>
					<button type="submit" class="mb-manager__button mb-manager__button--ghost" data-intent="draft"><?php esc_html_e('Save Draft', 'mad-baits-bundle-builder'); ?></button>
					<button type="submit" class="mb-manager__button" data-intent="activate"><?php esc_html_e('Save & Activate', 'mad-baits-bundle-builder'); ?></button>
				<?php else : ?>
					<button type="submit" class="mb-manager__button" data-intent="save"><?php esc_html_e('Save Changes', 'mad-baits-bundle-builder'); ?></button>
					<button type="submit" class="mb-manager__button mb-manager__button--ghost" data-intent="disable"><?php esc_html_e('Disable Bundle', 'mad-baits-bundle-builder'); ?></button>
				<?php endif; ?>
				<?php if (! $is_new) : ?>
					<a class="mb-manager__button mb-manager__button--ghost" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=madbaits-bundles&action=duplicate&bundle_id=' . $bundle_id), 'mb_bundle_duplicate_' . $bundle_id)); ?>"><?php esc_html_e('Duplicate Bundle', 'mad-baits-bundle-builder'); ?></a>
				<?php endif; ?>
				<a class="mb-manager__textlink" href="<?php echo esc_url(admin_url('admin.php?page=madbaits-bundles')); ?>"><?php esc_html_e('Back to bundles', 'mad-baits-bundle-builder'); ?></a>
			</div>
		</form>
		<?php
		$this->close_shell();
	}

	/**
	 * @return void
	 */
	private function render_delete_confirm() {
		$bundle_id = isset($_GET['bundle_id']) ? absint($_GET['bundle_id']) : 0;
		check_admin_referer('mb_bundle_delete_' . $bundle_id);
		$product = function_exists('wc_get_product') ? wc_get_product($bundle_id) : null;
		$name    = $product instanceof WC_Product ? $product->get_name() : __('this bundle', 'mad-baits-bundle-builder');
		$this->open_shell(__('Delete bundle', 'mad-baits-bundle-builder'), '');
		?>
		<div class="mb-manager__confirm-card">
			<h2><?php echo esc_html(sprintf(
				/* translators: %s: bundle name */
				__('Delete %s?', 'mad-baits-bundle-builder'),
				$name
			)); ?></h2>
			<p><?php esc_html_e('This moves the bundle to the bin. Customers will no longer be able to buy it. You can restore it from the bin if you change your mind.', 'mad-baits-bundle-builder'); ?></p>
			<form method="post">
				<?php wp_nonce_field('mb_bundle_delete_' . $bundle_id); ?>
				<input type="hidden" name="page" value="madbaits-bundles" />
				<input type="hidden" name="action" value="delete" />
				<input type="hidden" name="bundle_id" value="<?php echo esc_attr((string) $bundle_id); ?>" />
				<input type="hidden" name="mb_bundle_delete_confirm" value="1" />
				<button type="submit" class="mb-manager__button mb-manager__button--danger"><?php esc_html_e('Delete bundle', 'mad-baits-bundle-builder'); ?></button>
				<a class="mb-manager__button mb-manager__button--ghost" href="<?php echo esc_url(admin_url('admin.php?page=madbaits-bundles')); ?>"><?php esc_html_e('Cancel', 'mad-baits-bundle-builder'); ?></a>
			</form>
		</div>
		<?php
		$this->close_shell();
	}

	/**
	 * @param array<string, mixed> $config    Config.
	 * @param string               $image_url Image URL.
	 * @return void
	 */
	private function render_basic_section(array $config, $image_url) {
		?>
		<section class="mb-manager__section">
			<h2><?php esc_html_e('Basic details', 'mad-baits-bundle-builder'); ?></h2>
			<label for="mb-bundle-name"><?php esc_html_e('Bundle name', 'mad-baits-bundle-builder'); ?></label>
			<input id="mb-bundle-name" name="mb_bundle[name]" type="text" required value="<?php echo esc_attr((string) $config['name']); ?>" placeholder="<?php esc_attr_e('10kg Mix & Match Boilie Bundle', 'mad-baits-bundle-builder'); ?>" />
			<label for="mb-bundle-short"><?php esc_html_e('Short description', 'mad-baits-bundle-builder'); ?></label>
			<textarea id="mb-bundle-short" name="mb_bundle[short_description]" rows="3"><?php echo esc_textarea((string) $config['short_description']); ?></textarea>
			<div class="mb-manager__image">
				<label><?php esc_html_e('Bundle image', 'mad-baits-bundle-builder'); ?></label>
				<input type="hidden" id="mb-bundle-image-id" name="mb_bundle[image_id]" value="<?php echo esc_attr((string) $config['image_id']); ?>" />
				<div class="mb-manager__image-preview" id="mb-bundle-image-preview">
					<?php if ('' !== $image_url) : ?>
						<img src="<?php echo esc_url($image_url); ?>" alt="" />
					<?php endif; ?>
				</div>
				<button type="button" class="mb-manager__button mb-manager__button--small" id="mb-bundle-image-pick"><?php esc_html_e('Choose image', 'mad-baits-bundle-builder'); ?></button>
			</div>
			<fieldset>
				<legend><?php esc_html_e('Status', 'mad-baits-bundle-builder'); ?></legend>
				<?php
				foreach (array(
					'draft'    => __('Draft', 'mad-baits-bundle-builder'),
					'active'   => __('Active', 'mad-baits-bundle-builder'),
					'disabled' => __('Disabled', 'mad-baits-bundle-builder'),
				) as $value => $label) {
					echo '<label class="mb-manager__inline"><input type="radio" name="mb_bundle[status]" value="' . esc_attr($value) . '" ' . checked($config['status'], $value, false) . ' /> ' . esc_html($label) . '</label>';
				}
				?>
			</fieldset>
			<div class="mb-manager__dates">
				<label for="mb-bundle-start"><?php esc_html_e('Start date', 'mad-baits-bundle-builder'); ?> <span><?php esc_html_e('Optional', 'mad-baits-bundle-builder'); ?></span></label>
				<input id="mb-bundle-start" type="date" name="mb_bundle[start_date]" value="<?php echo esc_attr((string) $config['start_date']); ?>" />
				<label for="mb-bundle-end"><?php esc_html_e('End date', 'mad-baits-bundle-builder'); ?> <span><?php esc_html_e('Optional', 'mad-baits-bundle-builder'); ?></span></label>
				<input id="mb-bundle-end" type="date" name="mb_bundle[end_date]" value="<?php echo esc_attr((string) $config['end_date']); ?>" />
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return void
	 */
	private function render_type_section(array $config) {
		?>
		<section class="mb-manager__section">
			<h2><?php esc_html_e('Bundle type', 'mad-baits-bundle-builder'); ?></h2>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[bundle_type]" value="mix_and_match" <?php checked($config['bundle_type'], 'mix_and_match'); ?> /> <span><strong><?php esc_html_e('Mix & match', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('Customer chooses the products.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[bundle_type]" value="fixed" <?php checked($config['bundle_type'], 'fixed'); ?> /> <span><strong><?php esc_html_e('Fixed product bundle', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('Customer receives a set mix.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<div class="mb-manager__quantity" data-show-for="mix_and_match">
				<label for="mb-bundle-quantity"><?php esc_html_e('Customer chooses', 'mad-baits-bundle-builder'); ?></label>
				<input id="mb-bundle-quantity" type="number" min="1" max="<?php echo esc_attr((string) MBBB_Bundle_Config::MAX_CHOICES); ?>" name="mb_bundle[quantity]" value="<?php echo esc_attr((string) $config['quantity']); ?>" />
				<label for="mb-bundle-unit"><?php esc_html_e('Unit', 'mad-baits-bundle-builder'); ?></label>
				<select id="mb-bundle-unit" name="mb_bundle[unit]">
					<?php
					foreach (array(
						'bags'    => __('Bags', 'mad-baits-bundle-builder'),
						'tubs'    => __('Tubs', 'mad-baits-bundle-builder'),
						'bottles' => __('Bottles', 'mad-baits-bundle-builder'),
						'items'   => __('Items', 'mad-baits-bundle-builder'),
						'custom'  => __('Custom', 'mad-baits-bundle-builder'),
					) as $value => $label) {
						echo '<option value="' . esc_attr($value) . '" ' . selected($config['unit'], $value, false) . '>' . esc_html($label) . '</option>';
					}
					?>
				</select>
				<label for="mb-bundle-unit-custom" data-show-for-unit="custom"><?php esc_html_e('Custom unit label', 'mad-baits-bundle-builder'); ?></label>
				<input id="mb-bundle-unit-custom" data-show-for-unit="custom" type="text" name="mb_bundle[unit_custom]" value="<?php echo esc_attr((string) $config['unit_custom']); ?>" placeholder="<?php esc_attr_e('bags', 'mad-baits-bundle-builder'); ?>" />
				<p class="mb-manager__hint" id="mb-bundle-choice-preview"><?php echo esc_html(MBBB_Bundle_Config::choice_sentence($config)); ?></p>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config  Config.
	 * @param array<string, mixed> $catalog Catalogue.
	 * @param int                  $live    Live count.
	 * @return void
	 */
	private function render_eligibility_section(array $config, array $catalog, $live) {
		?>
		<section class="mb-manager__section">
			<h2><?php esc_html_e('Eligible products', 'mad-baits-bundle-builder'); ?></h2>
			<p><?php esc_html_e('Tick what the customer is allowed to choose. Leave a group empty if it should not limit the bundle. A product has to match every group you use.', 'mad-baits-bundle-builder'); ?></p>
			<p class="mb-manager__count" id="mb-eligible-count" aria-live="polite">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: variation count */
						_n('%d eligible product variation', '%d eligible product variations', $live, 'mad-baits-bundle-builder'),
						$live
					)
				);
				?>
			</p>
			<div data-show-for="mix_and_match">
				<?php
				$this->render_checks(__('Available bait ranges', 'mad-baits-bundle-builder'), 'mb_bundle[ranges][]', (array) $catalog['ranges'], (array) $config['ranges'], 'ranges');
				$this->render_checks(__('Allowed sizes', 'mad-baits-bundle-builder'), 'mb_bundle[sizes][]', (array) $catalog['sizes'], (array) $config['sizes'], 'sizes');
				$this->render_checks(__('Product categories', 'mad-baits-bundle-builder'), 'mb_bundle[categories][]', (array) $catalog['categories'], (array) $config['categories'], 'categories');
				$this->render_checks(__('Specific products', 'mad-baits-bundle-builder'), 'mb_bundle[product_ids][]', (array) $catalog['products'], (array) $config['product_ids'], 'products');
				$variation_options = array();
				foreach ((array) $catalog['variations'] as $row) {
					if (is_array($row) && ! empty($row['id'])) {
						$variation_options[ (int) $row['id'] ] = (string) ($row['name'] ?? '');
					}
				}
				$this->render_checks(__('Individual bait choices', 'mad-baits-bundle-builder'), 'mb_bundle[variation_ids][]', $variation_options, (array) $config['variation_ids'], 'variations');
				foreach ((array) $catalog['attributes'] as $taxonomy => $attribute) {
					if (! is_array($attribute)) {
						continue;
					}
					$selected = isset($config['attributes'][ $taxonomy ]) ? (array) $config['attributes'][ $taxonomy ] : array();
					$label    = trim((string) ($attribute['label'] ?? ''));
					if ('' === $label) {
						$label = ucwords(trim(str_replace(array('pa_', '-', '_'), ' ', (string) $taxonomy)));
					}
					$this->render_checks($label, 'mb_bundle[attributes][' . $taxonomy . '][]', (array) ($attribute['terms'] ?? array()), $selected, 'attr-' . sanitize_key((string) $taxonomy));
				}
				?>
			</div>
			<div data-show-for="fixed">
				<p><?php esc_html_e('Tick the products included in this set. Add a quantity when the bundle contains more than one of the same product.', 'mad-baits-bundle-builder'); ?></p>
				<?php $this->render_fixed_items($catalog, (array) $config['fixed_items']); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return void
	 */
	private function render_pricing_section(array $config) {
		$pricing = (array) $config['pricing'];
		?>
		<section class="mb-manager__section">
			<h2><?php esc_html_e('Pricing', 'mad-baits-bundle-builder'); ?></h2>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[pricing_mode]" value="fixed" <?php checked($pricing['mode'], 'fixed'); ?> /> <span><strong><?php esc_html_e('Fixed bundle price', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('Example: the customer pays £74.99.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[pricing_mode]" value="percent" <?php checked($pricing['mode'], 'percent'); ?> /> <span><strong><?php esc_html_e('Percentage discount', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('Example: 10% off the products they choose.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[pricing_mode]" value="amount" <?php checked($pricing['mode'], 'amount'); ?> /> <span><strong><?php esc_html_e('Fixed discount', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('Example: £10 off the calculated total.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<p class="mb-manager__hint"><?php esc_html_e('Team and trade discounts still apply at checkout when they are set up. This screen sets the bundle price itself.', 'mad-baits-bundle-builder'); ?></p>
			<label for="mb-bundle-fixed-price" data-price-for="fixed"><?php esc_html_e('Bundle price', 'mad-baits-bundle-builder'); ?></label>
			<input data-price-for="fixed" id="mb-bundle-fixed-price" type="number" min="0" step="0.01" name="mb_bundle[fixed_price]" value="<?php echo esc_attr((string) $pricing['fixed_price']); ?>" />
			<label for="mb-bundle-percent" data-price-for="percent"><?php esc_html_e('Percent off', 'mad-baits-bundle-builder'); ?></label>
			<input data-price-for="percent" id="mb-bundle-percent" type="number" min="1" max="100" name="mb_bundle[percent]" value="<?php echo esc_attr((string) $pricing['percent']); ?>" />
			<label for="mb-bundle-amount" data-price-for="amount"><?php esc_html_e('Amount off', 'mad-baits-bundle-builder'); ?></label>
			<input data-price-for="amount" id="mb-bundle-amount" type="number" min="0" step="0.01" name="mb_bundle[amount]" value="<?php echo esc_attr((string) $pricing['amount']); ?>" />
			<p class="mb-manager__summary" id="mb-price-summary"><?php echo esc_html(MBBB_Bundle_Pricing::summary($config)); ?></p>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return void
	 */
	private function render_rules_section(array $config) {
		?>
		<section class="mb-manager__section" data-show-for="mix_and_match">
			<h2><?php esc_html_e('Rules', 'mad-baits-bundle-builder'); ?></h2>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[quantity_mode]" value="exact" <?php checked($config['quantity_mode'], 'exact'); ?> /> <span><strong><?php esc_html_e('Exact quantity', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('A 10kg bundle uses 10. A 20kg bundle uses 20.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<label class="mb-manager__choice"><input type="radio" name="mb_bundle[quantity_mode]" value="minimum" <?php checked($config['quantity_mode'], 'minimum'); ?> /> <span><strong><?php esc_html_e('Minimum and maximum', 'mad-baits-bundle-builder'); ?></strong><small><?php esc_html_e('Example: at least 10 and no more than 20.', 'mad-baits-bundle-builder'); ?></small></span></label>
			<div class="mb-manager__dates" data-show-for-mode="minimum">
				<label for="mb-bundle-min"><?php esc_html_e('Minimum', 'mad-baits-bundle-builder'); ?></label>
				<input id="mb-bundle-min" type="number" min="0" name="mb_bundle[min_quantity]" value="<?php echo esc_attr((string) $config['min_quantity']); ?>" />
				<label for="mb-bundle-max"><?php esc_html_e('Maximum', 'mad-baits-bundle-builder'); ?></label>
				<input id="mb-bundle-max" type="number" min="0" name="mb_bundle[max_quantity]" value="<?php echo esc_attr((string) $config['max_quantity']); ?>" />
			</div>
			<label for="mb-bundle-multiple"><?php esc_html_e('Optional multiples', 'mad-baits-bundle-builder'); ?></label>
			<input id="mb-bundle-multiple" type="number" min="0" name="mb_bundle[multiple_of]" value="<?php echo esc_attr((string) $config['multiple_of']); ?>" />
			<p class="mb-manager__hint"><?php esc_html_e('Leave blank unless the customer must buy 10, 20, 30, and so on.', 'mad-baits-bundle-builder'); ?></p>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return void
	 */
	private function render_stock_section(array $config) {
		$stock = (array) $config['stock'];
		?>
		<section class="mb-manager__section">
			<h2><?php esc_html_e('Stock and availability', 'mad-baits-bundle-builder'); ?></h2>
			<p><?php esc_html_e('Availability follows the normal WooCommerce stock on each product. This bundle does not keep its own stock number.', 'mad-baits-bundle-builder'); ?></p>
			<label class="mb-manager__inline"><input type="hidden" name="mb_bundle[hide_unavailable]" value="0" /><input type="checkbox" name="mb_bundle[hide_unavailable]" value="1" <?php checked(! empty($stock['hide_unavailable'])); ?> /> <?php esc_html_e('Hide unavailable products', 'mad-baits-bundle-builder'); ?></label>
			<label class="mb-manager__inline"><input type="hidden" name="mb_bundle[prevent_oos]" value="0" /><input type="checkbox" name="mb_bundle[prevent_oos]" value="1" <?php checked(! empty($stock['prevent_oos'])); ?> /> <?php esc_html_e('Prevent selection of out-of-stock variations', 'mad-baits-bundle-builder'); ?></label>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return void
	 */
	private function render_display_section(array $config) {
		$display = (array) $config['display'];
		?>
		<section class="mb-manager__section">
			<h2><?php esc_html_e('Customer display', 'mad-baits-bundle-builder'); ?></h2>
			<label for="mb-bundle-button"><?php esc_html_e('Button text', 'mad-baits-bundle-builder'); ?></label>
			<input id="mb-bundle-button" type="text" name="mb_bundle[button_text]" value="<?php echo esc_attr((string) $display['button_text']); ?>" placeholder="<?php esc_attr_e('Build Your Bundle', 'mad-baits-bundle-builder'); ?>" />
			<label for="mb-bundle-helper"><?php esc_html_e('Helper text', 'mad-baits-bundle-builder'); ?></label>
			<input id="mb-bundle-helper" type="text" name="mb_bundle[helper_text]" value="<?php echo esc_attr((string) $display['helper_text']); ?>" placeholder="<?php esc_attr_e('Choose any 10 bags from the ranges below.', 'mad-baits-bundle-builder'); ?>" />
			<label for="mb-bundle-badge"><?php esc_html_e('Optional badge', 'mad-baits-bundle-builder'); ?></label>
			<input id="mb-bundle-badge" type="text" name="mb_bundle[badge]" value="<?php echo esc_attr((string) $display['badge']); ?>" placeholder="<?php esc_attr_e('Save £15', 'mad-baits-bundle-builder'); ?>" />
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $config     Config.
	 * @param string               $image_url  Image.
	 * @param string[]             $names      Sample product names.
	 * @return void
	 */
	private function render_preview(array $config, $image_url, array $names) {
		$display = (array) $config['display'];
		?>
		<aside class="mb-manager__preview" aria-label="<?php esc_attr_e('Customer preview', 'mad-baits-bundle-builder'); ?>">
			<p class="mb-manager__preview-kicker"><?php esc_html_e('Customer preview', 'mad-baits-bundle-builder'); ?></p>
			<div class="mb-preview" id="mb-bundle-preview">
				<div class="mb-preview__image" id="mb-preview-image">
					<?php if ('' !== $image_url) : ?>
						<img src="<?php echo esc_url($image_url); ?>" alt="" />
					<?php endif; ?>
				</div>
				<p class="mb-preview__badge" id="mb-preview-badge" <?php echo '' === (string) $display['badge'] ? 'hidden' : ''; ?>><?php echo esc_html((string) $display['badge']); ?></p>
				<h3 id="mb-preview-name"><?php echo esc_html('' !== (string) $config['name'] ? (string) $config['name'] : __('Bundle name', 'mad-baits-bundle-builder')); ?></h3>
				<p id="mb-preview-helper"><?php echo esc_html((string) ($display['helper_text'] ?: MBBB_Bundle_Config::choice_sentence($config))); ?></p>
				<p id="mb-preview-qty"><?php echo esc_html(MBBB_Bundle_Config::choice_sentence($config)); ?></p>
				<ul id="mb-preview-ranges">
					<?php foreach ($names as $name) : ?>
						<li><?php echo esc_html((string) $name); ?></li>
					<?php endforeach; ?>
				</ul>
				<p id="mb-preview-price"><?php echo esc_html(MBBB_Bundle_Pricing::summary($config)); ?></p>
				<span class="mb-preview__button" id="mb-preview-button"><?php echo esc_html('' !== (string) $display['button_text'] ? (string) $display['button_text'] : __('Build Your Bundle', 'mad-baits-bundle-builder')); ?></span>
			</div>
		</aside>
		<?php
	}

	/**
	 * @param string               $legend   Group label.
	 * @param string               $name     Input name.
	 * @param array<string, mixed> $options  Value => label.
	 * @param array<int, mixed>    $selected Selected values.
	 * @param string               $list_id  List key.
	 * @return void
	 */
	private function render_checks($legend, $name, array $options, array $selected, $list_id) {
		$selected = array_map('strval', $selected);
		echo '<fieldset class="mb-manager__group">';
		echo '<legend>' . esc_html($legend) . '</legend>';
		if (empty($options)) {
			echo '<p class="mb-manager__hint">' . esc_html__('Nothing is available here yet. Add products in WooCommerce and they will show up in this list.', 'mad-baits-bundle-builder') . '</p>';
			echo '</fieldset>';
			return;
		}
		echo '<div class="mb-manager__group-tools">';
		echo '<label class="screen-reader-text" for="mb-filter-' . esc_attr($list_id) . '">' . esc_html__('Search', 'mad-baits-bundle-builder') . '</label>';
		echo '<input id="mb-filter-' . esc_attr($list_id) . '" type="search" data-filter-list="' . esc_attr($list_id) . '" placeholder="' . esc_attr__('Search', 'mad-baits-bundle-builder') . '" />';
		echo '<button type="button" class="mb-manager__button mb-manager__button--small mb-manager__button--ghost" data-select-all="' . esc_attr($list_id) . '">' . esc_html__('Select all', 'mad-baits-bundle-builder') . '</button>';
		echo '<button type="button" class="mb-manager__button mb-manager__button--small mb-manager__button--ghost" data-clear="' . esc_attr($list_id) . '">' . esc_html__('Clear', 'mad-baits-bundle-builder') . '</button>';
		echo '</div><div class="mb-manager__checks" data-check-list="' . esc_attr($list_id) . '">';
		foreach ($options as $value => $label) {
			$value = (string) $value;
			echo '<label data-filter-item="' . esc_attr($list_id) . '"><input type="checkbox" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . checked(in_array($value, $selected, true) || in_array((string) absint($value), $selected, true), true, false) . ' /> <span>' . esc_html((string) $label) . '</span></label>';
		}
		echo '</div></fieldset>';
	}

	/**
	 * @param array<string, mixed>              $catalog Catalogue.
	 * @param array<int, array<string, mixed>>  $items   Fixed items.
	 * @return void
	 */
	private function render_fixed_items(array $catalog, array $items) {
		$selected = array();
		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}
			$id = absint($item['variation_id'] ?? $item['product_id'] ?? 0);
			if ($id > 0) {
				$selected[ $id ] = max(1, (int) ($item['quantity'] ?? 1));
			}
		}
		echo '<div class="mb-manager__checks mb-manager__checks--fixed" data-check-list="fixed">';
		foreach ((array) $catalog['variations'] as $row) {
			if (! is_array($row) || empty($row['id'])) {
				continue;
			}
			$id  = (int) $row['id'];
			$qty = $selected[ $id ] ?? 1;
			echo '<label data-filter-item="fixed"><input type="checkbox" name="mb_bundle[fixed_selected][]" value="' . esc_attr((string) $id) . '" ' . checked(isset($selected[ $id ]), true, false) . ' /> <span>' . esc_html((string) ($row['name'] ?? '')) . '</span>';
			echo ' <input class="mb-manager__qty" type="number" min="1" max="' . esc_attr((string) MBBB_Bundle_Config::MAX_CHOICES) . '" name="mb_bundle[fixed_qty][' . esc_attr((string) $id) . ']" value="' . esc_attr((string) $qty) . '" aria-label="' . esc_attr__('Quantity', 'mad-baits-bundle-builder') . '" /></label>';
		}
		if (empty($catalog['variations'])) {
			echo '<p class="mb-manager__hint">' . esc_html__('No products are available to include yet.', 'mad-baits-bundle-builder') . '</p>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @return array<string, mixed>
	 */
	private static function normalise_fixed_items(array $posted) {
		if (empty($posted['mb_bundle']) || ! is_array($posted['mb_bundle'])) {
			return $posted;
		}
		$selected = isset($posted['mb_bundle']['fixed_selected']) && is_array($posted['mb_bundle']['fixed_selected'])
			? $posted['mb_bundle']['fixed_selected']
			: array();
		$qty = isset($posted['mb_bundle']['fixed_qty']) && is_array($posted['mb_bundle']['fixed_qty'])
			? $posted['mb_bundle']['fixed_qty']
			: array();
		$items = array();
		foreach ($selected as $id) {
			$id = absint($id);
			if ($id < 1) {
				continue;
			}
			$items[] = array(
				'variation_id' => $id,
				'product_id'   => 0,
				'quantity'     => absint($qty[ $id ] ?? 1),
				'label'        => '',
			);
		}
		$posted['mb_bundle']['fixed_items'] = $items;
		return $posted;
	}

	/**
	 * @param string $label   Label.
	 * @param string $value   Filter value.
	 * @param string $current Current value.
	 * @param string $key     Query key.
	 * @return void
	 */
	private function filter_link($label, $value, $current, $key) {
		$args = array(
			'page' => 'madbaits-bundles',
		);
		$status = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
		$type   = isset($_GET['bundle_type']) ? sanitize_key(wp_unslash((string) $_GET['bundle_type'])) : '';
		$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
		if ('status' !== $key && '' !== $status && 'all' !== $status) {
			$args['status'] = $status;
		}
		if ('bundle_type' !== $key && '' !== $type && 'all' !== $type) {
			$args['bundle_type'] = $type;
		}
		if ('' !== $search) {
			$args['s'] = $search;
		}
		if ('all' !== $value) {
			$args[ $key ] = $value;
		}
		$url = add_query_arg($args, admin_url('admin.php'));
		$active = $current === $value ? ' is-active' : '';
		echo '<a class="mb-manager__filter' . esc_attr($active) . '" href="' . esc_url($url) . '"' . ($current === $value ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
	}

	/**
	 * @param string $message Message.
	 * @return void
	 */
	private function flash($message) {
		set_transient('mb_bundle_notice_' . get_current_user_id(), $message, 30);
	}

	/**
	 * @return void
	 */
	private function render_notice() {
		$notice = get_transient('mb_bundle_notice_' . get_current_user_id());
		if (! is_string($notice) || '' === $notice) {
			return;
		}
		delete_transient('mb_bundle_notice_' . get_current_user_id());
		echo '<div class="mb-manager__notice" role="status">' . esc_html($notice) . '</div>';
	}

	/**
	 * @return void
	 */
	private function guard() {
		if (! current_user_can(self::capability())) {
			wp_die(esc_html__('You do not have permission to manage MadBaits bundles.', 'mad-baits-bundle-builder'));
		}
	}

	/**
	 * @param string $title Title.
	 * @param string $intro Intro.
	 * @return void
	 */
	private function open_shell($title, $intro) {
		echo '<div class="wrap mb-manager">';
		echo '<h1>' . esc_html($title) . '</h1>';
		if ('' !== $intro) {
			echo '<p class="mb-manager__intro">' . esc_html($intro) . '</p>';
		}
	}

	/**
	 * @return void
	 */
	private function close_shell() {
		echo '</div>';
	}

	/**
	 * @param array<int, array{label: string, url: string, text: string}> $cards Cards.
	 * @return void
	 */
	private function link_cards(array $cards) {
		echo '<div class="mb-manager__cards">';
		foreach ($cards as $card) {
			echo '<a class="mb-manager__card mb-manager__card--link" href="' . esc_url($card['url']) . '"><h2>' . esc_html($card['label']) . '</h2><p>' . esc_html($card['text']) . '</p></a>';
		}
		echo '</div>';
	}
}
