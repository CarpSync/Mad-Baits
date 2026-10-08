<?php
/**
 * Frontend bundle builder.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Frontend renderer.
 */
final class MBBB_Frontend {

	/**
	 * @return void
	 */
	public function init() {
		add_filter('body_class', array($this, 'body_class'));
		add_filter('wc_get_template_part', array($this, 'maybe_use_bundle_content_template'), 20, 3);
		add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'), 65);
		add_action('wp_enqueue_scripts', array($this, 'maybe_dequeue_variation_script'), 100);
		add_action('woocommerce_before_add_to_cart_button', array($this, 'render_builder'), 5);
		add_action('woocommerce_before_add_to_cart_button', array($this, 'render_variation_reset_field'), 99);
		add_action('woocommerce_before_add_to_cart_quantity', array($this, 'render_checkout_panel_open'), 10);
		add_action('woocommerce_after_add_to_cart_button', array($this, 'render_checkout_actions_close'), 5);
		add_action('woocommerce_after_add_to_cart_button', array($this, 'render_express_checkout_section'), 8);
		add_action('woocommerce_after_add_to_cart_button', array($this, 'render_trust_strip'), 12);
		add_action('woocommerce_after_add_to_cart_button', array($this, 'render_checkout_panel_close'), 20);
		add_filter('woocommerce_product_supports', array($this, 'disable_variation_support'), 10, 3);
		add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'maybe_hide_variation_dropdown_html'), 999, 2);
		add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'add_to_cart_button_text'), 10, 2);
		// Before wp_print_footer_scripts (priority 20) so drawer/sticky exist when mbbb-frontend.js runs.
		add_action('wp_footer', array($this, 'render_mobile_chrome'), 5);
		add_action('wp', array($this, 'maybe_clear_stale_wc_notices'), 5);
		add_filter('woocommerce_product_tabs', array($this, 'hide_additional_information_tab'), 98);
	}

	/**
	 * Bundle PDPs use the builder — raw attribute tables expose internal slot labels.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function hide_additional_information_tab($tabs) {
		$product_id = $this->get_context_product_id();
		if ($product_id < 1 || ! MBBB_Plugin::instance()->uses_bundle_builder_ui($product_id)) {
			return $tabs;
		}

		unset($tabs['additional_information']);

		return $tabs;
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class($classes) {
		$product_id = $this->get_context_product_id();
		$plugin     = MBBB_Plugin::instance();
		if ($product_id > 0 && $plugin->uses_bundle_builder_ui($product_id)) {
			$settings = $plugin->get_settings($product_id);
			$classes[] = 'mbbb-product';
			$classes[] = 'mbbb-product--premium';
			$classes[] = 'mad-bundle-product-page';
			$classes[] = 'mad-bundle-builder-active';
			$classes[] = 'mbbb-builder-style-' . sanitize_html_class((string) $settings['display_style']);
			$slot_count = count($plugin->get_resolved_slots($product_id));
			if ($slot_count >= 10) {
				$classes[] = 'mbbb-product--large-bundle';
			}
			if (wp_is_mobile()) {
				$classes[] = 'mbbb-product--mobile-builder';
				$classes[] = 'mbbb-product--app-shell';
			}
		}
		return $classes;
	}

	/**
	 * Resolve product ID on single product pages (global $product is often unset during wp_enqueue_scripts).
	 *
	 * @return int
	 */
	private function get_context_product_id() {
		if (! is_product()) {
			return 0;
		}

		$product_id = get_queried_object_id();
		if ($product_id > 0 && 'product' === get_post_type($product_id)) {
			return $product_id;
		}

		global $product;
		if ($product instanceof WC_Product) {
			return $product->get_id();
		}

		return 0;
	}

	/**
	 * Remove stale WooCommerce variation error notices on bundle product page loads.
	 *
	 * @return void
	 */
	public function maybe_clear_stale_wc_notices() {
		if (is_admin() || wp_doing_ajax() || ! is_product()) {
			return;
		}

		$product_id = $this->get_context_product_id();
		if ($product_id < 1 || ! MBBB_Plugin::instance()->uses_bundle_builder_ui($product_id)) {
			return;
		}

		if ('GET' !== strtoupper((string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ))) {
			return;
		}

		wc_clear_notices('error');
	}

	/**
	 * Enqueue assets once the main query / product context is available.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		$product_id = $this->get_context_product_id();
		if ($product_id < 1 || ! MBBB_Plugin::instance()->uses_bundle_builder_ui($product_id)) {
			return;
		}

		$this->enqueue_assets($product_id);
	}

	/**
	 * Bundle builder replaces variation selection — stop WC from disabling the cart area.
	 *
	 * @return void
	 */
	public function maybe_dequeue_variation_script() {
		$product_id = $this->get_context_product_id();
		if ($product_id < 1 || ! MBBB_Plugin::instance()->uses_bundle_builder_ui($product_id)) {
			return;
		}

		wp_dequeue_script('wc-add-to-cart-variation');
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function enqueue_assets($product_id) {
		$asset_version = apply_filters('mbbb_asset_version', MBBB_VERSION);
		$style_deps    = array();

		if (wp_style_is('mad-baits-product-pdp-layout-fixes', 'registered')) {
			$style_deps[] = 'mad-baits-product-pdp-layout-fixes';
		}

		wp_enqueue_style(
			'mbbb-frontend',
			MBBB_PLUGIN_URL . 'assets/css/mbbb-frontend.css',
			$style_deps,
			$asset_version
		);

		wp_enqueue_style(
			'mbbb-mobile-app',
			MBBB_PLUGIN_URL . 'assets/css/mbbb-mobile-app.css',
			array( 'mbbb-frontend' ),
			$asset_version
		);

		wp_enqueue_style(
			'mbbb-bundle-desktop',
			MBBB_PLUGIN_URL . 'assets/css/mbbb-bundle-desktop.css',
			array( 'mbbb-mobile-app' ),
			$asset_version . '.' . (string) @filemtime(MBBB_PLUGIN_DIR . 'assets/css/mbbb-bundle-desktop.css')
		);

		wp_enqueue_style(
			'mbbb-bundle-template',
			MBBB_PLUGIN_URL . 'assets/css/mbbb-bundle-template.css',
			array( 'mbbb-bundle-desktop' ),
			$asset_version . '.' . (string) @filemtime(MBBB_PLUGIN_DIR . 'assets/css/mbbb-bundle-template.css')
		);

		wp_enqueue_script(
			'mbbb-frontend',
			MBBB_PLUGIN_URL . 'assets/js/mbbb-frontend.js',
			array(),
			$asset_version . '.' . (string) @filemtime(MBBB_PLUGIN_DIR . 'assets/js/mbbb-frontend.js'),
			true
		);

		$product         = wc_get_product($product_id);
		$settings        = MBBB_Plugin::instance()->get_settings($product_id);
		$global_settings = MBBB_Plugin::get_global_settings();
		$slots           = $this->build_frontend_slots($product_id);

		wp_localize_script(
			'mbbb-frontend',
			'mbbbFrontend',
			array(
				'productId'       => $product_id,
				'productTitle'    => $product instanceof WC_Product ? $product->get_name() : '',
				'displayStyle'    => $settings['display_style'],
				'mobileStepFlow'  => MBBB_Plugin::uses_mobile_step_flow($product_id) ? 1 : 0,
				'forceAttributes' => MBBB_Plugin::forces_product_attributes() ? 1 : 0,
				'stickyAtc'       => 'yes' === $settings['sticky_atc'],
				'showSummary'     => 'yes' === $settings['show_summary'],
				'autoAdvance'     => 'yes' === $global_settings['auto_advance'],
				'autoAdvanceDelayMs' => (int) $global_settings['auto_advance_delay_ms'],
				'manualNextSlotThreshold' => (int) $global_settings['manual_next_slot_threshold'],
				'slots'           => $slots,
				'quantityRules'   => $this->owner_quantity_rules($product_id),
				'searchThreshold' => 12,
				'filterThreshold' => 8,
				'filterChips'     => $this->get_filter_chip_definitions(),
				'rangeFilterIds'  => array(
					'asbo',
					'wicked-whites',
					'p-fish-2',
					'pandemic',
					'nutz',
					'stp',
					'calamino',
					'compulsive-angler',
					'pellets',
					'liquids',
					'pop-ups',
					'wafters',
					'skinz',
					'shelf-life',
					'freezer-bait',
				),
				'slotCount'       => count($slots),
				'ajaxUrl'         => admin_url('admin-ajax.php'),
				'nonce'           => wp_create_nonce('mbbb_add_to_cart'),
				'cartUrl'         => wc_get_cart_url(),
				'checkoutUrl'     => wc_get_checkout_url(),
				'i18n'            => array(
					'buildDeal'       => __('Build Your Deal', 'mad-baits-bundle-builder'),
					'stepOf'          => __('Step %1$d of %2$d', 'mad-baits-bundle-builder'),
					'searchPlaceholder' => __('Search your bait…', 'mad-baits-bundle-builder'),
					'viewSummary'     => __('View Bundle Summary', 'mad-baits-bundle-builder'),
					'editChoice'      => __('Edit', 'mad-baits-bundle-builder'),
					'drawerTitle'     => __('Your Bundle Summary', 'mad-baits-bundle-builder'),
					'progress'        => __('%1$d of %2$d choices complete', 'mad-baits-bundle-builder'),
					'almostThere'     => __('Almost there — %d choices left', 'mad-baits-bundle-builder'),
					'bundleComplete'  => __('Bundle complete — ready to add to basket', 'mad-baits-bundle-builder'),
					'remaining'       => __('%d choice(s) remaining', 'mad-baits-bundle-builder'),
					'remainingNone'   => __('Ready to add', 'mad-baits-bundle-builder'),
					'addBundle'       => '' !== $this->owner_button_text($product_id) ? $this->owner_button_text($product_id) : __('Add Bundle To Basket', 'mad-baits-bundle-builder'),
					'completeChoices' => __('Complete Your Choices', 'mad-baits-bundle-builder'),
					'adding'          => __('Adding…', 'mad-baits-bundle-builder'),
					'choicesLeft'     => __('%d choices left', 'mad-baits-bundle-builder'),
					'choicesLeftShort' => __('%d left', 'mad-baits-bundle-builder'),
					'ready'           => __('Ready', 'mad-baits-bundle-builder'),
					'progressRatio'   => __('%1$d/%2$d complete', 'mad-baits-bundle-builder'),
					'needToChoose'    => __('You still need to choose: %s', 'mad-baits-bundle-builder'),
					'completeChoicesShort' => __('Complete Choices', 'mad-baits-bundle-builder'),
					'completeFirst'   => __('Please complete all required choices.', 'mad-baits-bundle-builder'),
					'expressTitle'    => __('Express checkout', 'mad-baits-bundle-builder'),
					'expressLead'     => __('Or pay quickly with PayPal', 'mad-baits-bundle-builder'),
					'trustDelivery'   => __('Free UK delivery on selected deals', 'mad-baits-bundle-builder'),
					'trustSecure'     => __('Secure checkout', 'mad-baits-bundle-builder'),
					'trustFresh'      => __('Fresh bait packed with care', 'mad-baits-bundle-builder'),
					'summaryTitle'    => __('Your Bundle Summary', 'mad-baits-bundle-builder'),
					'summaryToggle'   => __('View your bundle summary', 'mad-baits-bundle-builder'),
					'notSelected'     => __('Tap to choose', 'mad-baits-bundle-builder'),
					'choosePrefix'    => __('Choose', 'mad-baits-bundle-builder'),
					'search'          => __('Search options…', 'mad-baits-bundle-builder'),
					'noResults'       => __('No options match your search.', 'mad-baits-bundle-builder'),
					'noFilterResults' => __('No options found for this filter.', 'mad-baits-bundle-builder'),
					'filterAll'       => __('All', 'mad-baits-bundle-builder'),
					'selectedPrefix'  => __('Selected:', 'mad-baits-bundle-builder'),
					'nextChoice'      => __('Next Choice', 'mad-baits-bundle-builder'),
					'statusRequired'  => __('Required', 'mad-baits-bundle-builder'),
					'statusChoose'    => __('Choose', 'mad-baits-bundle-builder'),
					'statusComplete'  => __('Complete', 'mad-baits-bundle-builder'),
					'statusMissing'   => __('Missing', 'mad-baits-bundle-builder'),
					'statusOptional'  => __('Optional', 'mad-baits-bundle-builder'),
					'changeChoice'    => __('Change', 'mad-baits-bundle-builder'),
					'showingFilter'   => __('Showing %s', 'mad-baits-bundle-builder'),
					'clearFilter'     => __('Clear filter', 'mad-baits-bundle-builder'),
					'optionsCount'    => __('%d options', 'mad-baits-bundle-builder'),
					'filteredCount'   => __('%1$d %2$s options', 'mad-baits-bundle-builder'),
					'addedTitle'      => __('Bundle added to basket', 'mad-baits-bundle-builder'),
					'viewBasket'      => __('View Basket', 'mad-baits-bundle-builder'),
					'checkout'        => __('Checkout', 'mad-baits-bundle-builder'),
					'continue'        => __('Continue shopping', 'mad-baits-bundle-builder'),
					'errorGeneric'    => __('Something went wrong. Please try again.', 'mad-baits-bundle-builder'),
				),
			)
		);
	}

	/**
	 * Route bundle-builder products to a dedicated content template.
	 *
	 * @param string $template Located template path.
	 * @param string $slug     Template slug.
	 * @param string $name     Template name.
	 * @return string
	 */
	public function maybe_use_bundle_content_template($template, $slug, $name) {
		if ('content' !== $slug || 'single-product' !== $name || ! is_product()) {
			return $template;
		}

		$product_id = $this->get_context_product_id();
		if ($product_id < 1 || ! MBBB_Plugin::instance()->uses_bundle_builder_ui($product_id)) {
			return $template;
		}

		$bundle_template = MBBB_PLUGIN_DIR . 'templates/content-single-product-bundle.php';
		if (file_exists($bundle_template)) {
			return $bundle_template;
		}

		return $template;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_frontend_slots($product_id) {
		$plugin = MBBB_Plugin::instance();
		$out    = array();

		foreach ($plugin->get_resolved_slots($product_id) as $index => $slot) {
			$key = isset($slot['key']) ? (string) $slot['key'] : 'slot_' . $index;
			$out[] = array(
				'key'      => $key,
				'label'    => isset($slot['label']) ? (string) $slot['label'] : $key,
				'step'     => $index + 1,
				'help'     => isset($slot['help']) ? (string) $slot['help'] : '',
				'required' => ! empty($slot['required']),
				'min'      => isset($slot['min']) ? (int) $slot['min'] : 1,
				'max'      => isset($slot['max']) ? (int) $slot['max'] : 1,
				'display'  => isset($slot['display']) ? (string) $slot['display'] : 'cards',
				'options'  => $plugin->resolve_slot_options($slot, $product_id),
			);
		}

		return $out;
	}

	/**
	 * @param bool       $support Support flag.
	 * @param string     $feature Feature.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function disable_variation_support($support, $feature, $product) {
		if ('ajax_add_to_cart' === $feature && MBBB_Plugin::instance()->uses_bundle_builder_ui($product->get_id())) {
			return false;
		}
		return $support;
	}

	/**
	 * Remove default WooCommerce variation dropdown markup on bundle builder PDPs.
	 *
	 * @param string $html Option HTML.
	 * @param array  $args Dropdown args.
	 * @return string
	 */
	public function maybe_hide_variation_dropdown_html($html, $args) {
		unset($args);

		if (! is_product()) {
			return $html;
		}

		$product_id = $this->get_context_product_id();
		if ($product_id < 1 || ! MBBB_Plugin::instance()->should_hide_wc_variation_ui($product_id)) {
			return $html;
		}

		return '';
	}

	/**
	 * Resolve display mode (cards on mobile — no tiny dropdowns).
	 *
	 * @param string $display Admin display setting.
	 * @return string
	 */
	private function resolve_display_mode($display) {
		if (wp_is_mobile() && 'dropdown' === $display) {
			return 'cards';
		}
		return $display;
	}

	/**
	 * @return void
	 */
	public function render_builder() {
		global $product;
		if (! $product instanceof WC_Product) {
			return;
		}

		$product_id = $product->get_id();
		$plugin     = MBBB_Plugin::instance();

		if (! $plugin->uses_bundle_builder_ui($product_id)) {
			return;
		}

		$owner = class_exists('MBBB_Bundle_Runtime') ? MBBB_Bundle_Runtime::config_for($product_id) : null;
		if (is_array($owner) && ! MBBB_Bundle_Config::is_purchasable($owner)) {
			echo '<p class="mbbb-notice">' . esc_html__('This bundle is not available right now.', 'mad-baits-bundle-builder') . '</p>';
			return;
		}

		$settings      = $plugin->get_settings($product_id);
		$slots         = $plugin->get_resolved_slots($product_id);
		$display_style = sanitize_html_class((string) $settings['display_style']);

		if (empty($slots)) {
			echo '<p class="mbbb-notice">' . esc_html__('Bundle builder is enabled but no slots are configured yet.', 'mad-baits-bundle-builder') . '</p>';
			return;
		}

		$style_class = 'mbbb-style-' . $display_style;
		$total       = count($slots);
		$kicker      = $this->get_builder_kicker($product);
		?>
		<?php $this->render_owner_intro($product_id); ?>
		<div class="mbbb-shell mbbb-product mbbb-product--premium bundle-builder-shell" id="mbbb-shell">
			<div class="mbbb-builder <?php echo esc_attr($style_class); ?>" id="mbbb-builder" data-product-id="<?php echo esc_attr((string) $product_id); ?>" data-slot-count="<?php echo esc_attr((string) $total); ?>" data-display-style="<?php echo esc_attr($display_style); ?>">
				<div class="mbbb-progress-dock mbbb-mobile-header" id="mbbb-progress-dock">
					<div class="mbbb-progress-dock__inner">
						<div class="mbbb-mobile-header__top">
							<p class="mbbb-builder__kicker" id="mbbb-builder-kicker"><?php esc_html_e('Build Your Deal', 'mad-baits-bundle-builder'); ?></p>
							<p class="mbbb-mobile-header__product" id="mbbb-mobile-product-name"><?php echo esc_html($product->get_name()); ?></p>
						</div>
						<p class="mbbb-builder__progress" id="mbbb-progress-text" aria-live="polite"></p>
						<div class="mbbb-builder__progress-bar" aria-hidden="true"><span id="mbbb-progress-fill"></span></div>
					</div>
				</div>

				<div class="mbbb-builder__slots" id="mbbb-slots">
					<?php $this->render_builder_slots($slots, $product_id, $plugin); ?>
				</div>

				<?php if ('yes' === $settings['show_summary']) : ?>
					<div class="mbbb-summary mbbb-summary--collapsible bundle-summary" id="mbbb-summary">
						<button type="button" class="mbbb-summary__toggle" id="mbbb-summary-toggle" aria-expanded="false" aria-controls="mbbb-summary-panel">
							<span class="mbbb-summary__toggle-label"><?php esc_html_e('Your Bundle Summary', 'mad-baits-bundle-builder'); ?></span>
							<span class="mbbb-summary__toggle-icon" aria-hidden="true"></span>
						</button>
						<div class="mbbb-summary__panel" id="mbbb-summary-panel" hidden>
							<ul class="mbbb-summary__list" id="mbbb-summary-list"></ul>
						</div>
					</div>
				<?php endif; ?>

				<p class="mbbb-validation" id="mbbb-validation" role="alert" hidden></p>
			</div>
		</div>

		<div class="mbbb-builder-spacer" id="mbbb-builder-spacer" aria-hidden="true"></div>
		<?php
	}

	/**
	 * Force variation_id to 0 so legacy WooCommerce variation validation does not run.
	 *
	 * @return void
	 */
	public function render_variation_reset_field() {
		global $product;
		if (! $product instanceof WC_Product || ! MBBB_Plugin::instance()->is_enabled($product->get_id())) {
			return;
		}
		echo '<input type="hidden" name="variation_id" class="mbbb-variation-reset" value="0" />';
	}

	/**
	 * @param string     $text Button label.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function add_to_cart_button_text($text, $product) {
		if ($product instanceof WC_Product && MBBB_Plugin::instance()->is_enabled($product->get_id())) {
			$button = $this->owner_button_text($product->get_id());
			if ('' !== $button) {
				return $button;
			}
			return __('Add Bundle To Basket', 'mad-baits-bundle-builder');
		}
		return $text;
	}

	/**
	 * Open premium checkout panel (wraps qty + CTA + express + trust).
	 *
	 * @return void
	 */
	public function render_checkout_panel_open() {
		if (! $this->is_bundle_product_context()) {
			return;
		}
		?>
		<div class="mbbb-checkout-panel" id="mbbb-checkout-panel">
			<p class="mbbb-checkout-status" id="mbbb-checkout-status" role="status" aria-live="polite" hidden></p>
			<div class="mbbb-checkout-panel__actions">
		<?php
	}

	/**
	 * Close qty + CTA row wrapper.
	 *
	 * @return void
	 */
	public function render_checkout_actions_close() {
		if (! $this->is_bundle_product_context()) {
			return;
		}
		echo '</div>';
	}

	/**
	 * Express checkout slot — PayPal / payment buttons moved here via JS.
	 *
	 * @return void
	 */
	public function render_express_checkout_section() {
		if (! $this->is_bundle_product_context()) {
			return;
		}
		?>
			<div class="mbbb-express-checkout" id="mbbb-express-checkout" hidden>
				<h3 class="mbbb-express-checkout__title"><?php esc_html_e('Express checkout', 'mad-baits-bundle-builder'); ?></h3>
				<p class="mbbb-express-checkout__lead"><?php esc_html_e('Or pay quickly with PayPal', 'mad-baits-bundle-builder'); ?></p>
				<div class="mbbb-express-checkout__slot" id="mbbb-express-slot" data-mbbb-express-slot></div>
			</div>
		<?php
	}

	/**
	 * Branded trust strip below express checkout.
	 *
	 * @return void
	 */
	public function render_trust_strip() {
		if (! $this->is_bundle_product_context()) {
			return;
		}
		?>
			<ul class="mbbb-trust-strip" id="mbbb-trust-strip" aria-label="<?php esc_attr_e('Why shop with Mad Baits', 'mad-baits-bundle-builder'); ?>">
				<li class="mbbb-trust-strip__item">
					<span class="mbbb-trust-strip__icon" aria-hidden="true">✓</span>
					<span><?php esc_html_e('Secure checkout', 'mad-baits-bundle-builder'); ?></span>
				</li>
				<li class="mbbb-trust-strip__item">
					<span class="mbbb-trust-strip__icon" aria-hidden="true">✓</span>
					<span><?php esc_html_e('Fast dispatch', 'mad-baits-bundle-builder'); ?></span>
				</li>
				<li class="mbbb-trust-strip__item">
					<span class="mbbb-trust-strip__icon" aria-hidden="true">✓</span>
					<span><?php esc_html_e('Fresh bait packed daily', 'mad-baits-bundle-builder'); ?></span>
				</li>
			</ul>
		<?php
	}

	/**
	 * Close checkout panel wrapper.
	 *
	 * @return void
	 */
	public function render_checkout_panel_close() {
		if (! $this->is_bundle_product_context()) {
			return;
		}
		echo '</div>';
	}

	/**
	 * @return bool
	 */
	private function is_bundle_product_context() {
		global $product;
		return $product instanceof WC_Product && MBBB_Plugin::instance()->is_enabled($product->get_id());
	}

	/**
	 * Wrap consecutive item-group slots without turning the wrapper into a step.
	 *
	 * @param array<int, array<string, mixed>> $slots      Slots.
	 * @param int                              $product_id Product ID.
	 * @param MBBB_Plugin                      $plugin     Plugin.
	 * @return void
	 */
	private function render_builder_slots(array $slots, $product_id, $plugin) {
		$total = count($slots);
		$open  = '';
		foreach ($slots as $index => $slot) {
			$group = isset($slot['group_key']) ? (string) $slot['group_key'] : '';
			if ($group !== $open) {
				if ('' !== $open) {
					echo '</div>';
				}
				if ('' !== $group) {
					$step     = (int) ($slot['group_step'] ?? 1);
					$sentence = (string) ($slot['group_sentence'] ?? '');
					$needed   = (int) ($slot['group_total'] ?? 0);
					echo '<div class="mbbb-group" data-group-key="' . esc_attr($group) . '" data-group-total="' . esc_attr((string) $needed) . '">';
					echo '<h3 class="mbbb-group__title">' . esc_html(sprintf(
						/* translators: 1: step number 2: instruction such as Choose your 10 boilies */
						__('Step %1$d — %2$s', 'mad-baits-bundle-builder'),
						$step,
						$sentence
					)) . '</h3>';
					echo '<p class="mbbb-group__progress" data-group-progress>' . esc_html(sprintf(
						/* translators: %d: number of choices in this group */
						__('0 of %d chosen', 'mad-baits-bundle-builder'),
						$needed
					)) . '</p>';
				}
				$open = $group;
			}
			$this->render_slot($slot, (int) $index, $total, $product_id, $plugin);
		}
		if ('' !== $open) {
			echo '</div>';
		}
	}

	/**
	 * @param array<string, mixed> $slot Slot config.
	 * @param int                  $index Zero-based index.
	 * @param int                  $total Total slots.
	 * @param int                  $product_id Product ID.
	 * @param MBBB_Plugin          $plugin Plugin instance.
	 * @return void
	 */
	private function render_slot($slot, $index, $total, $product_id, $plugin) {
		$key        = isset($slot['key']) ? (string) $slot['key'] : 'slot_' . $index;
		$label      = isset($slot['label']) ? (string) $slot['label'] : $key;
		$help       = isset($slot['help']) ? (string) $slot['help'] : $this->get_slot_help_text($label);
		$display    = $this->resolve_display_mode(isset($slot['display']) ? (string) $slot['display'] : 'cards');
		$options    = $plugin->resolve_slot_options($slot, $product_id);
		$req        = ! empty($slot['required']);
		$grouped    = '' !== (string) ($slot['group_key'] ?? '');
		$heading    = $grouped ? $label : $this->get_slot_heading($label, (int) $index + 1, (int) $total);
		$badge_step = $grouped ? (string) ($slot['group_index'] ?? ($index + 1)) : (string) ($index + 1);
		$option_count           = count($options);
		$available_filter_chips = $this->get_available_filter_chips($options, $product_id);
		$has_filter_chips       = ! empty($available_filter_chips);
		$searchable             = $option_count >= 12;
		$show_filters           = $has_filter_chips;
		$show_slot_tools        = $searchable || $show_filters;
		$scrollable             = $option_count > 6;
		$body_id    = 'mbbb-slot-body-' . $key;
		$is_first   = 0 === (int) $index;
		$is_open    = $is_first;
		$default_status_text = $req
			? __('Choose', 'mad-baits-bundle-builder')
			: __('Optional', 'mad-baits-bundle-builder');
		$default_status_class = $req ? 'mbbb-slot__status-badge--required' : 'mbbb-slot__status-badge--optional';
		?>
		<section
			class="mbbb-slot<?php echo $req ? ' mbbb-slot--required' : ''; ?><?php echo $show_slot_tools ? ' mbbb-slot--searchable' : ''; ?><?php echo $show_filters ? ' mbbb-slot--filterable' : ''; ?><?php echo $is_open ? ' is-active' : ' is-collapsed'; ?>"
			<?php echo ! empty($slot['auto_select']) ? 'data-auto-select="1"' : ''; ?>
			id="mbbb-slot-<?php echo esc_attr($key); ?>"
			data-slot-key="<?php echo esc_attr($key); ?>"
			data-required="<?php echo $req ? '1' : '0'; ?>"
			data-step="<?php echo esc_attr((string) ( $index + 1 )); ?>"
			data-option-count="<?php echo esc_attr((string) count($options)); ?>"
			tabindex="-1"
		>
			<button
				type="button"
				class="mbbb-slot__trigger"
				id="mbbb-slot-trigger-<?php echo esc_attr($key); ?>"
				aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr($body_id); ?>"
			>
				<span class="mbbb-slot__trigger-main">
					<span class="mbbb-slot__step-badge" aria-hidden="true"><?php echo esc_html($badge_step); ?></span>
					<span class="mbbb-slot__trigger-copy">
						<span class="mbbb-slot__trigger-title" id="mbbb-slot-title-<?php echo esc_attr($key); ?>"><?php echo esc_html($heading); ?></span>
						<span class="mbbb-slot__trigger-selection" data-slot-status="<?php echo esc_attr($key); ?>"><?php esc_html_e('Tap to choose', 'mad-baits-bundle-builder'); ?></span>
					</span>
				</span>
					<span class="mbbb-slot__status-badge <?php echo esc_attr($default_status_class); ?>" data-slot-required-badge="<?php echo esc_attr($key); ?>">
						<?php echo esc_html($default_status_text); ?>
					</span>
				<span class="mbbb-slot__change" data-slot-change="<?php echo esc_attr($key); ?>" hidden><?php esc_html_e('Change', 'mad-baits-bundle-builder'); ?></span>
				<span class="mbbb-slot__chevron" aria-hidden="true"></span>
			</button>

			<div class="mbbb-slot__body" id="<?php echo esc_attr($body_id); ?>"<?php echo $is_open ? '' : ' hidden'; ?>>
				<?php if ($help) : ?>
					<p class="mbbb-slot__help"><?php echo esc_html($help); ?></p>
				<?php endif; ?>

				<?php if ($show_slot_tools) : ?>
					<div class="mbbb-slot__tools">
						<?php if ($searchable) : ?>
						<label class="mbbb-slot__search-wrap">
							<span class="screen-reader-text"><?php esc_html_e('Search options', 'mad-baits-bundle-builder'); ?></span>
							<input type="search" class="mbbb-slot__search" data-slot-search="<?php echo esc_attr($key); ?>" placeholder="<?php esc_attr_e('Search your bait…', 'mad-baits-bundle-builder'); ?>" autocomplete="off" inputmode="search" />
							<button type="button" class="mbbb-slot__search-clear" data-slot-search-clear="<?php echo esc_attr($key); ?>" aria-label="<?php esc_attr_e('Clear search', 'mad-baits-bundle-builder'); ?>">×</button>
						</label>
						<?php endif; ?>
						<?php if ($show_filters) : ?>
							<div class="mbbb-slot__filter-meta" data-slot-filter-meta="<?php echo esc_attr($key); ?>" aria-live="polite">
								<span class="mbbb-slot__filter-count" data-slot-filter-count="<?php echo esc_attr($key); ?>"></span>
								<span class="mbbb-slot__filter-active" data-slot-filter-active="<?php echo esc_attr($key); ?>" hidden></span>
								<button type="button" class="mbbb-slot__filter-clear" data-slot-filter-clear="<?php echo esc_attr($key); ?>" hidden><?php esc_html_e('Clear filter', 'mad-baits-bundle-builder'); ?></button>
							</div>
							<div class="mbbb-slot__filters mbbb-slot__filters--scroll" role="toolbar" aria-label="<?php esc_attr_e('Filter options', 'mad-baits-bundle-builder'); ?>">
								<button type="button" class="mbbb-filter-chip is-active" data-filter="all" data-filter-label="<?php esc_attr_e('All', 'mad-baits-bundle-builder'); ?>" data-slot-filter="<?php echo esc_attr($key); ?>"><?php esc_html_e('All', 'mad-baits-bundle-builder'); ?></button>
								<?php foreach ($available_filter_chips as $filter_id => $filter_label) : ?>
									<button type="button" class="mbbb-filter-chip" data-filter="<?php echo esc_attr($filter_id); ?>" data-filter-label="<?php echo esc_attr($filter_label); ?>" data-slot-filter="<?php echo esc_attr($key); ?>"><?php echo esc_html($filter_label); ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<p class="mbbb-slot__selection-feedback" data-slot-selection-feedback="<?php echo esc_attr($key); ?>" hidden aria-live="polite"></p>
				<button type="button" class="mbbb-slot__next-choice" data-slot-next-choice="<?php echo esc_attr($key); ?>" hidden><?php esc_html_e('Next Choice', 'mad-baits-bundle-builder'); ?></button>

				<div class="mbbb-slot__options-wrap">
					<div class="mbbb-slot__options mbbb-display-<?php echo esc_attr($display); ?><?php echo $scrollable ? ' mbbb-slot__scroll' : ''; ?><?php echo $searchable ? ' mbbb-slot--searchable' : ''; ?>" role="group" aria-labelledby="mbbb-slot-title-<?php echo esc_attr($key); ?>">
						<?php if ('dropdown' === $display) : ?>
							<select name="mbbb_choices[<?php echo esc_attr($key); ?>]" class="mbbb-select" data-slot="<?php echo esc_attr($key); ?>" aria-required="<?php echo $req ? 'true' : 'false'; ?>">
								<option value=""><?php esc_html_e('Choose…', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($options as $opt) : ?>
									<option value="<?php echo esc_attr((string) $opt['value']); ?>"><?php echo esc_html((string) $opt['label']); ?></option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<?php foreach ($options as $opt) : ?>
								<?php $this->render_option_button($key, $opt); ?>
							<?php endforeach; ?>
							<p class="mbbb-slot__empty" data-slot-empty="<?php echo esc_attr($key); ?>" hidden aria-live="polite"></p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<input type="hidden" name="mbbb_choices[<?php echo esc_attr($key); ?>]" class="mbbb-choice-input" data-slot="<?php echo esc_attr($key); ?>" value="" autocomplete="off" />
		</section>
		<?php
	}

	/**
	 * @param string               $slot_key Slot key.
	 * @param array<string, mixed> $opt Option row.
	 * @return void
	 */
	private function render_option_button($slot_key, $opt) {
		$opt_label = (string) $opt['label'];
		$tags      = $this->option_filter_tags($opt);
		$long      = strlen($opt_label) > 32;
		$has_image = ! empty($opt['image']);
		$badge     = ! empty($tags) ? $tags[0] : '';
		?>
		<button
			type="button"
			class="mbbb-option<?php echo $long ? ' mbbb-option--long' : ''; ?><?php echo $has_image ? '' : ' mbbb-option--text'; ?>"
			data-slot="<?php echo esc_attr($slot_key); ?>"
			data-value="<?php echo esc_attr((string) $opt['value']); ?>"
			data-label="<?php echo esc_attr($opt_label); ?>"
			data-search="<?php echo esc_attr(strtolower($opt_label)); ?>"
			<?php if (! empty($tags)) : ?>
				data-tags="<?php echo esc_attr(implode(',', $tags)); ?>"
			<?php endif; ?>
			aria-pressed="false"
		>
			<span class="mbbb-option__check" aria-hidden="true"></span>
			<?php if ($badge) : ?>
				<span class="mbbb-option__badge"><?php echo esc_html($this->filter_tag_label($badge)); ?></span>
			<?php endif; ?>
			<?php if ($has_image) : ?>
				<span class="mbbb-option__image">
					<img src="<?php echo esc_url((string) $opt['image']); ?>" alt="" loading="lazy" decoding="async" width="48" height="48" />
				</span>
			<?php endif; ?>
			<span class="mbbb-option__label"><?php echo esc_html($opt_label); ?></span>
		</button>
		<?php
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function owner_button_text($product_id) {
		if (! class_exists('MBBB_Bundle_Runtime')) {
			return '';
		}
		$config = MBBB_Bundle_Runtime::config_for($product_id);
		if (! is_array($config)) {
			return '';
		}
		return trim((string) ($config['display']['button_text'] ?? ''));
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	private function owner_quantity_rules($product_id) {
		if (! class_exists('MBBB_Bundle_Runtime')) {
			return null;
		}
		$config = MBBB_Bundle_Runtime::config_for($product_id);
		if (! is_array($config) || ! empty($config['preserve_slots'])) {
			return null;
		}
		return array(
			'mode'     => (string) ($config['quantity_mode'] ?? 'exact'),
			'exact'    => (int) ($config['quantity'] ?? 0),
			'min'      => (int) ($config['min_quantity'] ?? 0),
			'max'      => (int) ($config['max_quantity'] ?? 0),
			'multiple' => (int) ($config['multiple_of'] ?? 0),
			'fixed'    => 'fixed' === ($config['bundle_type'] ?? ''),
		);
	}

	/**
	 * Badge and helper text for bundles edited in the owner manager.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function render_owner_intro($product_id) {
		if (! class_exists('MBBB_Bundle_Runtime')) {
			return;
		}
		$config = MBBB_Bundle_Runtime::config_for($product_id);
		if (! is_array($config) || ! empty($config['preserve_slots'])) {
			return;
		}
		$badge  = trim((string) ($config['display']['badge'] ?? ''));
		$helper = trim((string) ($config['display']['helper_text'] ?? ''));
		if ('' === $badge && '' === $helper) {
			return;
		}
		echo '<div class="mbbb-bundle-intro">';
		if ('' !== $badge) {
			echo '<p class="mbbb-bundle-intro__badge">' . esc_html($badge) . '</p>';
		}
		if ('' !== $helper) {
			echo '<p class="mbbb-bundle-intro__helper">' . esc_html($helper) . '</p>';
		}
		echo '</div>';
	}

	private function get_builder_kicker($product) {
		$button = $this->owner_button_text($product->get_id());
		if ('' !== $button) {
			return $button;
		}
		$name = trim((string) $product->get_name());
		if ('' === $name) {
			return __('Build Your Deal', 'mad-baits-bundle-builder');
		}
		if (false !== stripos($name, '50kg') || false !== stripos($name, 'ultimate trip')) {
			return __('Build Your 50kg Deal', 'mad-baits-bundle-builder');
		}
		return sprintf(
			/* translators: %s: product name */
			__('Build Your %s Deal', 'mad-baits-bundle-builder'),
			$name
		);
	}

	/**
	 * @param string $label Slot label.
	 * @param int    $step Step number.
	 * @param int    $total Total steps.
	 * @return string
	 */
	private function get_slot_heading($label, $step, $total) {
		unset($total);
		return sprintf(
			/* translators: 1: step number, 2: slot label */
			__('Step %1$d — %2$s', 'mad-baits-bundle-builder'),
			$step,
			$label
		);
	}

	/**
	 * @param string $label Slot label.
	 * @return string
	 */
	private function get_slot_help_text($label) {
		$hay = strtolower($label);
		if (false !== strpos($hay, 'split') || false !== strpos($hay, 'boilie') || false !== strpos($hay, '5kg') || false !== strpos($hay, '10kg')) {
			return __('Choose your boilie splits for this part of the deal.', 'mad-baits-bundle-builder');
		}
		if (false !== strpos($hay, 'hook')) {
			return __('Choose your hookbaits — pick the flavours you want on the bank.', 'mad-baits-bundle-builder');
		}
		if (false !== strpos($hay, 'liquid') || false !== strpos($hay, 'dip') || false !== strpos($hay, 'plume')) {
			return __('Choose your liquids to match your session approach.', 'mad-baits-bundle-builder');
		}
		if (false !== strpos($hay, 'pellet')) {
			return __('Choose the pellet that matches your boilie choice.', 'mad-baits-bundle-builder');
		}
		return '';
	}

	/**
	 * @return array<string, string>
	 */
	private function get_filter_chip_definitions() {
		return array(
			'asbo'              => 'ASBO',
			'wicked-whites'     => 'Wicked Whites',
			'p-fish-2'          => 'P-Fish',
			'pandemic'          => 'Pandemic',
			'nutz'              => 'Nutz',
			'stp'               => 'STP',
			'calamino'          => 'Calamino',
			'compulsive-angler' => 'Compulsive Angler',
			'pop-ups'           => __('Pop Ups', 'mad-baits-bundle-builder'),
			'wafters'           => __('Wafters', 'mad-baits-bundle-builder'),
			'skinz'             => 'Skinz',
			'pellets'           => __('Pellets', 'mad-baits-bundle-builder'),
			'liquids'           => __('Liquids', 'mad-baits-bundle-builder'),
			'shelf-life'        => __('Shelf Life', 'mad-baits-bundle-builder'),
			'freezer-bait'      => __('Freezer Bait', 'mad-baits-bundle-builder'),
		);
	}

	/**
	 * @param string $tag_id Tag id.
	 * @return string
	 */
	private function filter_tag_label($tag_id) {
		$defs = $this->get_filter_chip_definitions();
		$tag_id = $this->normalize_filter_tag_id($tag_id);
		if (isset($defs[ $tag_id ])) {
			return (string) $defs[ $tag_id ];
		}
		return ucwords(trim(str_replace(array('-', '_'), ' ', (string) $tag_id)));
	}

	/**
	 * Normalise filter tag IDs to canonical slug form.
	 *
	 * @param string $tag_id Raw tag id.
	 * @return string
	 */
	private function normalize_filter_tag_id($tag_id) {
		$tag_id = sanitize_title((string) $tag_id);
		if (in_array($tag_id, array('wicked-white', 'wicked_whites', 'wickedwhite', 'wicked-whites'), true)) {
			return 'wicked-whites';
		}
		if (in_array($tag_id, array('nutz-plus', 'nutz-banana', 'nutz_plus', 'nutzbanana'), true)) {
			return 'nutz';
		}
		return $tag_id;
	}

	/**
	 * Add taxonomy/attribute context strings for a product (and parent variation product).
	 *
	 * @param array<int, string> $hays       Search strings accumulator.
	 * @param int                $product_id Product or variation ID.
	 * @return void
	 */
	private function append_product_context_hays(&$hays, $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return;
		}

		$candidate_ids = array($product_id);
		$product       = wc_get_product($product_id);
		if ($product instanceof WC_Product_Variation) {
			$parent_id = (int) $product->get_parent_id();
			if ($parent_id > 0) {
				$candidate_ids[] = $parent_id;
			}

			foreach ((array) $product->get_attributes() as $attr_key => $attr_value) {
				$attr_key = (string) $attr_key;
				$attr_value = (string) $attr_value;
				if ('' === $attr_value) {
					continue;
				}
				$hays[] = $attr_key;
				$hays[] = $attr_value;
				$hays[] = 'attribute:' . $attr_value;
			}
		}

		$taxonomies = array('product_cat', 'product_tag');
		if (function_exists('wc_get_attribute_taxonomy_names')) {
			$taxonomies = array_merge($taxonomies, (array) wc_get_attribute_taxonomy_names());
		}
		$taxonomies = array_values(array_unique(array_filter(array_map('strval', $taxonomies))));

		foreach (array_unique($candidate_ids) as $candidate_id) {
			foreach ($taxonomies as $taxonomy) {
				if (! taxonomy_exists($taxonomy)) {
					continue;
				}
				$terms = get_the_terms((int) $candidate_id, $taxonomy);
				if (! is_array($terms)) {
					continue;
				}
				foreach ($terms as $term) {
					if (! $term instanceof WP_Term) {
						continue;
					}
					$hays[] = (string) $term->name;
					$hays[] = (string) $term->slug;
					$hays[] = $taxonomy . ':' . (string) $term->slug;
				}
			}
		}
	}

	/**
	 * @param string $label Option label.
	 * @return string[]
	 */
	private function option_filter_tags($option_or_label) {
		$label = is_array($option_or_label) ? (string) ($option_or_label['label'] ?? '') : (string) $option_or_label;
		$value = is_array($option_or_label) ? (string) ($option_or_label['value'] ?? '') : '';
		$hays  = array($label, $value);
		$product_id = is_array($option_or_label) ? absint($option_or_label['product_id'] ?? 0) : 0;
		if ($product_id > 0) {
			$this->append_product_context_hays($hays, $product_id);
		}

		$hay  = strtolower(implode(' ', array_filter(array_map('strval', $hays))));
		$tags = array();

		$add = function ($tag_id) use (&$tags) {
			$tag_id = $this->normalize_filter_tag_id($tag_id);
			if ('' === $tag_id) {
				return;
			}
			if (! in_array($tag_id, $tags, true)) {
				$tags[] = $tag_id;
			}
		};

		if (preg_match('/\basbo\b/', $hay)) {
			$add('asbo');
		}
		if (preg_match('/wicked[\s\-_]*whites?/', $hay)) {
			$add('wicked-whites');
		}
		if (preg_match('/p[\s\-]?fish|pfish/', $hay)) {
			$add('p-fish-2');
		}
		if (false !== strpos($hay, 'pandemic')) {
			$add('pandemic');
		}
		if (preg_match('/(^|[\s:_-])nutz($|[\s:_\-+])|nutz[\s\-_\+]*(plus|banana)|product_(cat|tag):nutz/', $hay)) {
			$add('nutz');
		}
		if (preg_match('/\bstp\b/', $hay)) {
			$add('stp');
		}
		if (false !== strpos($hay, 'calamino')) {
			$add('calamino');
		}
		if (preg_match('/compulsive(\s+angler)?/', $hay)) {
			$add('compulsive-angler');
		}
		if (preg_match('/pop[\s\-]?ups?|popups/', $hay)) {
			$add('pop-ups');
		}
		if (preg_match('/wafters?/', $hay)) {
			$add('wafters');
		}
		if (preg_match('/skinz/', $hay)) {
			$add('skinz');
		}
		if (preg_match('/pellet/', $hay)) {
			$add('pellets');
		}
		if (preg_match('/liquid|oil|goo|plume|glug|dip/', $hay)) {
			$add('liquids');
		}
		if (preg_match('/shelf[\s\-]?life/', $hay)) {
			$add('shelf-life');
		}
		if (preg_match('/freezer/', $hay)) {
			$add('freezer-bait');
		}

		// Fallback: if visible option carries explicit product tag/category slugs, expose those as chips too.
		if (preg_match_all('/(?:product_cat|product_tag):([a-z0-9\-]+)/', $hay, $matches)) {
			$blocklist = array(
				'all',
				'boilies',
				'hookbaits',
				'bundles',
				'bundles-deals',
				'deals',
				'shop',
			);
			foreach ((array) ($matches[1] ?? array()) as $slug) {
				$slug = $this->normalize_filter_tag_id($slug);
				if ('' === $slug || in_array($slug, $blocklist, true) || strlen($slug) < 3) {
					continue;
				}
				$add($slug);
			}
		}

		return $tags;
	}

	/**
	 * Build filter chips that are actually present in this slot's options.
	 *
	 * @param array<int, array<string, mixed>> $options Slot options.
	 * @param int                              $product_id Bundle product ID.
	 * @return array<string, string>
	 */
	private function get_available_filter_chips($options, $product_id = 0) {
		$defs = $this->get_filter_chip_definitions();
		$seen = array();

		foreach ($options as $opt) {
			foreach ($this->option_filter_tags($opt) as $tag_id) {
				$tag_id = $this->normalize_filter_tag_id($tag_id);
				if ('' === $tag_id) {
					continue;
				}
				$seen[ $tag_id ] = isset($defs[ $tag_id ])
					? (string) $defs[ $tag_id ]
					: $this->filter_tag_label($tag_id);
			}
		}
		unset($product_id);

		$ordered = array();
		foreach ($defs as $id => $chip_label) {
			if (isset($seen[ $id ])) {
				$ordered[ $id ] = (string) $chip_label;
			}
		}

		foreach ($seen as $id => $chip_label) {
			if (! isset($ordered[ $id ])) {
				$ordered[ $id ] = (string) $chip_label;
			}
		}

		return $ordered;
	}

	/**
	 * Human-readable label for a variation attribute value (slug → term name).
	 *
	 * @param int    $product_id    Product ID.
	 * @param string $attribute_key Attribute key.
	 * @param string $value         Raw/slug value.
	 * @return string
	 */
	private function variation_attribute_value_label($product_id, $attribute_key, $value) {
		$value = trim((string) $value);
		if ('' === $value) {
			return '';
		}

		$taxonomy = $attribute_key;
		if (0 === strpos($taxonomy, 'attribute_')) {
			$taxonomy = substr($taxonomy, 10);
		}
		if (function_exists('wc_attribute_taxonomy_name') && 0 !== strpos($taxonomy, 'pa_')) {
			$maybe = wc_attribute_taxonomy_name($taxonomy);
			if ($maybe) {
				$taxonomy = $maybe;
			}
		}

		if (taxonomy_exists($taxonomy)) {
			$term = get_term_by('slug', $value, $taxonomy);
			if ($term instanceof WP_Term && ! is_wp_error($term)) {
				return (string) $term->name;
			}
		}

		return str_replace(array('-', '_'), ' ', $value);
	}

	/**
	 * Sticky bar + success drawer (mobile / PWA).
	 *
	 * @return void
	 */
	public function render_mobile_chrome() {
		if (! is_product()) {
			return;
		}
		global $product;
		if (! $product instanceof WC_Product || ! MBBB_Plugin::instance()->is_enabled($product->get_id())) {
			return;
		}
		?>
		<div class="mbbb-summary-drawer" id="mbbb-summary-drawer" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mbbb-summary-drawer-title">
			<div class="mbbb-summary-drawer__backdrop" data-mbbb-drawer-close tabindex="-1" aria-hidden="true"></div>
			<div class="mbbb-summary-drawer__sheet">
				<div class="mbbb-summary-drawer__handle" aria-hidden="true"></div>
				<div class="mbbb-summary-drawer__head">
					<h2 class="mbbb-summary-drawer__title" id="mbbb-summary-drawer-title"><?php esc_html_e('Your Bundle Summary', 'mad-baits-bundle-builder'); ?></h2>
					<button type="button" class="mbbb-summary-drawer__close" data-mbbb-drawer-close aria-label="<?php esc_attr_e('Close', 'mad-baits-bundle-builder'); ?>">×</button>
				</div>
				<ul class="mbbb-summary-drawer__list" id="mbbb-summary-drawer-list"></ul>
				<p class="mbbb-summary-drawer__price" id="mbbb-summary-drawer-price"><?php echo wp_kses_post($product->get_price_html()); ?></p>
				<button type="button" class="mbbb-summary-drawer__cta mbbb-cta mbbb-sticky__btn" id="mbbb-summary-drawer-cta" aria-disabled="true">
					<span class="mbbb-sticky__btn-label"><?php esc_html_e('Complete Your Choices', 'mad-baits-bundle-builder'); ?></span>
				</button>
			</div>
		</div>

		<div class="mbbb-sticky" id="mbbb-sticky" aria-hidden="false">
			<div class="mbbb-sticky__inner">
				<div class="mbbb-sticky__row">
					<div class="mbbb-sticky__meta">
						<strong class="mbbb-sticky__status" id="mbbb-sticky-status" aria-live="polite"></strong>
						<span class="mbbb-sticky__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
					</div>
					<button type="button" class="mbbb-sticky__summary-btn" id="mbbb-sticky-summary" data-mbbb-open-summary aria-expanded="false" aria-controls="mbbb-summary-drawer">
						<?php esc_html_e('View Summary', 'mad-baits-bundle-builder'); ?>
					</button>
					<button type="button" class="mbbb-sticky__btn mbbb-cta" id="mbbb-sticky-submit" aria-disabled="true" aria-busy="false">
						<span class="mbbb-sticky__btn-label"><?php esc_html_e('Complete Choices', 'mad-baits-bundle-builder'); ?></span>
						<span class="mbbb-sticky__spinner" hidden aria-hidden="true"></span>
					</button>
				</div>
			</div>
		</div>

		<div class="mbbb-success" id="mbbb-success" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mbbb-success-title">
			<div class="mbbb-success__backdrop" data-mbbb-success-close tabindex="-1" aria-hidden="true"></div>
			<div class="mbbb-success__sheet">
				<div class="mbbb-success__handle" aria-hidden="true"></div>
				<h2 class="mbbb-success__title" id="mbbb-success-title"><?php esc_html_e('Bundle added to basket', 'mad-baits-bundle-builder'); ?></h2>
				<p class="mbbb-success__lead"><?php esc_html_e('Your selections have been added to the basket.', 'mad-baits-bundle-builder'); ?></p>
				<ul class="mbbb-success__list" id="mbbb-success-list"></ul>
				<div class="mbbb-success__actions">
					<a class="mbbb-success__btn mbbb-success__btn--primary" id="mbbb-success-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php esc_html_e('View Basket', 'mad-baits-bundle-builder'); ?></a>
					<a class="mbbb-success__btn mbbb-success__btn--accent" id="mbbb-success-checkout" href="<?php echo esc_url(wc_get_checkout_url()); ?>"><?php esc_html_e('Checkout', 'mad-baits-bundle-builder'); ?></a>
					<button type="button" class="mbbb-success__btn mbbb-success__btn--ghost" data-mbbb-success-close><?php esc_html_e('Continue shopping', 'mad-baits-bundle-builder'); ?></button>
				</div>
			</div>
		</div>
		<script>
		(function () {
			function mbbbRemoveDuplicateSummaryButtons() {
				var keep = document.getElementById('mbbb-sticky-summary');
				document.querySelectorAll('[data-mbbb-open-summary], .mbbb-summary-drawer-trigger, #mbbb-summary-drawer-open').forEach(function (node) {
					if (node !== keep) {
						node.remove();
					}
				});
			}
			mbbbRemoveDuplicateSummaryButtons();
			if ('loading' === document.readyState) {
				document.addEventListener('DOMContentLoaded', mbbbRemoveDuplicateSummaryButtons);
			}
		})();
		</script>
		<?php
	}
}
