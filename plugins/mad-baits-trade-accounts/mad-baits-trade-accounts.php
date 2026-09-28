<?php
/**
 * Plugin Name: Mad Baits Trade Accounts
 * Description: Trade account applications, role-based trade checkout, and invoice-only ordering for WooCommerce.
 * Version: 1.0.0
 * Author: Mad Baits
 * Text Domain: mad-baits-trade
 */
defined('ABSPATH') || exit;

if (! class_exists('Mad_Baits_Trade_Accounts')) {
	class Mad_Baits_Trade_Accounts {
		const ROLE                  = 'trade_customer';
		const STATUS                = 'pending-trade-invoice';
		const GATEWAY_ID            = 'mad_trade_invoice_account';
		const OPTION_MIN_ORDER      = 'mad_trade_min_order_value';
		const OPTION_TRADE_TERMS    = 'mad_trade_terms';
		const OPTION_INVOICE_TERMS  = 'mad_trade_invoice_terms';
		const META_TRADE_STATUS     = 'mad_trade_status';
		const META_TRADE_ONLY       = '_mad_trade_only';
		const META_TRADE_PRICE      = '_mad_trade_price';
		const META_EMAIL_SENT       = '_mad_trade_customer_email_sent';

		public static function init() {
			add_action('init', array(__CLASS__, 'register_role'));
			add_action('init', array(__CLASS__, 'register_order_status'));

			add_filter('wc_order_statuses', array(__CLASS__, 'add_order_status_to_list'));
			add_action('plugins_loaded', 'mad_baits_register_trade_gateway_class');
			add_filter('woocommerce_payment_gateways', array(__CLASS__, 'register_gateway'));
			add_filter('woocommerce_available_payment_gateways', array(__CLASS__, 'filter_available_gateways'));
			add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'validate_trade_minimum_order'), 10, 2);

			add_filter('woocommerce_email_subject_new_order', array(__CLASS__, 'filter_admin_new_order_subject'), 10, 2);
			add_filter('woocommerce_email_heading_new_order', array(__CLASS__, 'filter_admin_new_order_heading'), 10, 2);
			add_action('woocommerce_email_after_order_table', array(__CLASS__, 'render_customer_trade_email_copy'), 10, 4);

			add_shortcode('mad_trade_landing', array(__CLASS__, 'render_trade_landing'));
			add_shortcode('mad_trade_application_form', array(__CLASS__, 'render_trade_application_form'));
			add_action('template_redirect', array(__CLASS__, 'handle_trade_application_submission'));
			add_action('template_redirect', array(__CLASS__, 'handle_trade_reorder'));

			add_action('admin_menu', array(__CLASS__, 'register_settings_page'));
			add_action('admin_init', array(__CLASS__, 'register_settings'));
			add_action('admin_init', array(__CLASS__, 'handle_trade_applications_admin_actions'));
			add_action('show_user_profile', array(__CLASS__, 'render_user_trade_fields'));
			add_action('edit_user_profile', array(__CLASS__, 'render_user_trade_fields'));
			add_action('personal_options_update', array(__CLASS__, 'save_user_trade_fields'));
			add_action('edit_user_profile_update', array(__CLASS__, 'save_user_trade_fields'));

			add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
			add_action('template_redirect', array(__CLASS__, 'enforce_trade_product_page_access'));
			add_action('woocommerce_account_dashboard', array(__CLASS__, 'render_my_account_trade_block'), 30);
			add_action('woocommerce_thankyou_' . self::GATEWAY_ID, array(__CLASS__, 'render_trade_thankyou_notice'));

			add_action('woocommerce_product_options_pricing', array(__CLASS__, 'add_trade_product_fields'));
			add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_trade_product_fields'));
			add_filter('woocommerce_product_get_price', array(__CLASS__, 'filter_trade_price'), 10, 2);
			add_filter('woocommerce_product_variation_get_price', array(__CLASS__, 'filter_trade_price'), 10, 2);
			add_filter('woocommerce_product_get_regular_price', array(__CLASS__, 'filter_trade_price'), 10, 2);
			add_filter('woocommerce_product_variation_get_regular_price', array(__CLASS__, 'filter_trade_price'), 10, 2);
			add_filter('woocommerce_product_get_sale_price', array(__CLASS__, 'filter_trade_sale_price'), 10, 2);
			add_filter('woocommerce_product_variation_get_sale_price', array(__CLASS__, 'filter_trade_sale_price'), 10, 2);

			add_action('woocommerce_product_query', array(__CLASS__, 'hide_trade_only_products_from_retail'));
			add_filter('woocommerce_product_is_visible', array(__CLASS__, 'hide_trade_only_product_visibility'), 10, 2);
			add_filter('woocommerce_variation_is_visible', array(__CLASS__, 'hide_trade_only_variation_visibility'), 10, 4);
			add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'validate_trade_only_add_to_cart'), 10, 5);
			add_action('woocommerce_check_cart_items', array(__CLASS__, 'validate_trade_only_cart_contents'));
		}

		public static function activate() {
			self::register_role();
			self::register_order_status();
			self::create_trade_pages();
			flush_rewrite_rules();
		}

		public static function register_role() {
			if (! get_role(self::ROLE)) {
				add_role(
					self::ROLE,
					__('Trade Customer', 'mad-baits-trade'),
					array(
						'read'    => true,
						'level_0' => true,
					)
				);
			}
		}

		public static function register_order_status() {
			register_post_status(
				'wc-' . self::STATUS,
				array(
					'label'                     => _x('Pending Trade Invoice', 'Order status', 'mad-baits-trade'),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: order count */
					'label_count'               => _n_noop('Pending Trade Invoice <span class="count">(%s)</span>', 'Pending Trade Invoice <span class="count">(%s)</span>', 'mad-baits-trade'),
				)
			);
		}

		public static function add_order_status_to_list($statuses) {
			$updated = array();
			foreach ($statuses as $key => $label) {
				$updated[ $key ] = $label;
				if ('wc-pending' === $key) {
					$updated[ 'wc-' . self::STATUS ] = __('Pending Trade Invoice', 'mad-baits-trade');
				}
			}

			if (! isset($updated[ 'wc-' . self::STATUS ])) {
				$updated[ 'wc-' . self::STATUS ] = __('Pending Trade Invoice', 'mad-baits-trade');
			}

			return $updated;
		}

		public static function register_gateway($gateways) {
			$gateways[] = 'WC_Gateway_Mad_Trade_Invoice_Account';
			return $gateways;
		}

		public static function filter_available_gateways($available_gateways) {
			if (! isset($available_gateways[ self::GATEWAY_ID ])) {
				return $available_gateways;
			}

			if (! is_user_logged_in() || ! self::is_approved_trade_user(get_current_user_id())) {
				unset($available_gateways[ self::GATEWAY_ID ]);
			}

			return $available_gateways;
		}

		public static function validate_trade_minimum_order($data, $errors) {
			if (! is_object($errors) || ! method_exists($errors, 'add')) {
				return;
			}

			if (empty($data['payment_method']) || self::GATEWAY_ID !== $data['payment_method']) {
				return;
			}

			if (! is_user_logged_in() || ! self::is_approved_trade_user(get_current_user_id())) {
				return;
			}

			$minimum = self::get_minimum_order_value();
			if ($minimum <= 0 || ! function_exists('WC') || ! WC()->cart) {
				return;
			}

			$total = (float) WC()->cart->get_subtotal();
			if ($total < $minimum) {
				$errors->add(
					'mad_trade_minimum_order',
					sprintf(
						/* translators: %s: formatted amount */
						__('Trade orders require a minimum subtotal of %s.', 'mad-baits-trade'),
						wp_strip_all_tags(wc_price($minimum))
					)
				);
			}
		}

		public static function filter_admin_new_order_subject($subject, $order) {
			if (! $order instanceof WC_Order || ! self::is_trade_order($order)) {
				return $subject;
			}

			return sprintf(
				/* translators: %s: order number */
				__('TRADE ORDER - MANUAL INVOICE REQUIRED (#%s)', 'mad-baits-trade'),
				$order->get_order_number()
			);
		}

		public static function filter_admin_new_order_heading($heading, $order) {
			if (! $order instanceof WC_Order || ! self::is_trade_order($order)) {
				return $heading;
			}

			return __('TRADE ORDER - MANUAL INVOICE REQUIRED', 'mad-baits-trade');
		}

		public static function render_customer_trade_email_copy($order, $sent_to_admin, $plain_text, $email) {
			if ($sent_to_admin || ! $order instanceof WC_Order || ! self::is_trade_order($order)) {
				return;
			}

			if (! is_object($email) || empty($email->id) || 0 !== strpos((string) $email->id, 'customer_')) {
				return;
			}

			$message = __('Thanks for your trade order. We’ll review it and send your invoice shortly.', 'mad-baits-trade');
			if ($plain_text) {
				echo "\n" . wp_strip_all_tags($message) . "\n";
				return;
			}

			echo '<p style="font-weight:600;margin:12px 0 16px;">' . esc_html($message) . '</p>';
		}

		public static function send_trade_customer_confirmation_email($order) {
			if (! $order instanceof WC_Order) {
				return;
			}

			if ('yes' === (string) $order->get_meta(self::META_EMAIL_SENT)) {
				return;
			}

			$to = sanitize_email((string) $order->get_billing_email());
			if ('' === $to || ! is_email($to)) {
				return;
			}

			$subject = __('Trade order received - Mad Baits', 'mad-baits-trade');
			$message = sprintf(
				/* translators: 1: order number, 2: order link */
				__("Thanks for your trade order. We’ll review it and send your invoice shortly.\n\nOrder: #%1\$s\nView order: %2\$s", 'mad-baits-trade'),
				$order->get_order_number(),
				$order->get_view_order_url()
			);

			$headers = array('Content-Type: text/plain; charset=UTF-8');
			if (wp_mail($to, $subject, $message, $headers)) {
				$order->update_meta_data(self::META_EMAIL_SENT, 'yes');
				$order->save();
			}
		}

		public static function render_trade_thankyou_notice($order_id) {
			$order = wc_get_order($order_id);
			if (! $order instanceof WC_Order || ! self::is_trade_order($order)) {
				return;
			}

			echo '<div class="mad-trade-notice">';
			echo '<strong>' . esc_html__('Thanks for your trade order. We’ll review it and send your invoice shortly.', 'mad-baits-trade') . '</strong>';
			echo '</div>';
		}

		public static function render_trade_landing() {
			$application_url = home_url('/trade-application/');
			$account_url     = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');

			ob_start();
			?>
			<section class="mad-trade-page">
				<header class="mad-trade-hero">
					<p class="mad-trade-kicker"><?php esc_html_e('Mad Baits Trade', 'mad-baits-trade'); ?></p>
					<h1><?php esc_html_e('Trade Ordering Built For Tackle Shops & Fisheries', 'mad-baits-trade'); ?></h1>
					<p><?php esc_html_e('Apply for a trade account, order bait at trade pricing, and receive manual invoices from our team.', 'mad-baits-trade'); ?></p>
					<div class="mad-trade-actions">
						<a class="mad-button" href="<?php echo esc_url($application_url); ?>"><?php esc_html_e('Apply for Trade Account', 'mad-baits-trade'); ?></a>
						<a class="mad-button mad-button--ghost" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('Trade Login', 'mad-baits-trade'); ?></a>
					</div>
				</header>
				<div class="mad-trade-grid">
					<article>
						<h3><?php esc_html_e('No Online Payment', 'mad-baits-trade'); ?></h3>
						<p><?php esc_html_e('Approved trade users checkout with Trade Invoice Account only. No card payment is taken.', 'mad-baits-trade'); ?></p>
					</article>
					<article>
						<h3><?php esc_html_e('Manual Invoicing', 'mad-baits-trade'); ?></h3>
						<p><?php esc_html_e('Every trade order is flagged for manual invoicing so your team can review quantities and dispatch details.', 'mad-baits-trade'); ?></p>
					</article>
					<article>
						<h3><?php esc_html_e('Quick Reordering', 'mad-baits-trade'); ?></h3>
						<p><?php esc_html_e('Reorder previous trade orders in one click from My Account to save time on repeat stock orders.', 'mad-baits-trade'); ?></p>
					</article>
				</div>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		public static function render_trade_application_form() {
			$status = isset($_GET['trade_application']) ? sanitize_text_field(wp_unslash((string) $_GET['trade_application'])) : '';
			ob_start();
			?>
			<section class="mad-trade-page">
				<header class="mad-trade-hero mad-trade-hero--small">
					<p class="mad-trade-kicker"><?php esc_html_e('Trade Application', 'mad-baits-trade'); ?></p>
					<h1><?php esc_html_e('Apply for a Mad Baits Trade Account', 'mad-baits-trade'); ?></h1>
				</header>

				<?php if ('success' === $status) : ?>
					<div class="mad-trade-notice mad-trade-notice--success">
						<?php esc_html_e('Thanks, your trade application has been submitted. Our team will review it shortly.', 'mad-baits-trade'); ?>
					</div>
				<?php elseif ('error' === $status) : ?>
					<div class="mad-trade-notice mad-trade-notice--error">
						<?php esc_html_e('There was a problem submitting your trade application. Please try again.', 'mad-baits-trade'); ?>
					</div>
				<?php endif; ?>

				<form class="mad-trade-form" method="post" action="">
					<?php wp_nonce_field('mad_trade_application_submit', 'mad_trade_application_nonce'); ?>
					<input type="hidden" name="mad_trade_action" value="apply">

					<label>
						<span><?php esc_html_e('Contact name', 'mad-baits-trade'); ?></span>
						<input type="text" name="trade_contact_name" required>
					</label>
					<label>
						<span><?php esc_html_e('Business name', 'mad-baits-trade'); ?></span>
						<input type="text" name="trade_business_name" required>
					</label>
					<label>
						<span><?php esc_html_e('Email', 'mad-baits-trade'); ?></span>
						<input type="email" name="trade_email" required>
					</label>
					<label>
						<span><?php esc_html_e('Phone', 'mad-baits-trade'); ?></span>
						<input type="text" name="trade_phone" required>
					</label>
					<label>
						<span><?php esc_html_e('VAT Number (optional)', 'mad-baits-trade'); ?></span>
						<input type="text" name="trade_vat_number">
					</label>
					<label>
						<span><?php esc_html_e('Business address', 'mad-baits-trade'); ?></span>
						<textarea name="trade_business_address" rows="3" required></textarea>
					</label>
					<label>
						<span><?php esc_html_e('Notes (optional)', 'mad-baits-trade'); ?></span>
						<textarea name="trade_notes" rows="4"></textarea>
					</label>
					<button type="submit" class="mad-button"><?php esc_html_e('Submit Trade Application', 'mad-baits-trade'); ?></button>
				</form>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		public static function handle_trade_application_submission() {
			if ('POST' !== strtoupper((string) $_SERVER['REQUEST_METHOD'])) {
				return;
			}

			$action = isset($_POST['mad_trade_action']) ? sanitize_text_field(wp_unslash((string) $_POST['mad_trade_action'])) : '';
			if ('apply' !== $action) {
				return;
			}

			$redirect = home_url('/trade-application/');
			if (! isset($_POST['mad_trade_application_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mad_trade_application_nonce'])), 'mad_trade_application_submit')) {
				wp_safe_redirect(add_query_arg('trade_application', 'error', $redirect));
				exit;
			}

			$contact_name = isset($_POST['trade_contact_name']) ? sanitize_text_field(wp_unslash((string) $_POST['trade_contact_name'])) : '';
			$business     = isset($_POST['trade_business_name']) ? sanitize_text_field(wp_unslash((string) $_POST['trade_business_name'])) : '';
			$email        = isset($_POST['trade_email']) ? sanitize_email(wp_unslash((string) $_POST['trade_email'])) : '';
			$phone        = isset($_POST['trade_phone']) ? sanitize_text_field(wp_unslash((string) $_POST['trade_phone'])) : '';
			$vat          = isset($_POST['trade_vat_number']) ? sanitize_text_field(wp_unslash((string) $_POST['trade_vat_number'])) : '';
			$address      = isset($_POST['trade_business_address']) ? sanitize_textarea_field(wp_unslash((string) $_POST['trade_business_address'])) : '';
			$notes        = isset($_POST['trade_notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['trade_notes'])) : '';

			if ('' === $contact_name || '' === $business || '' === $email || ! is_email($email) || '' === $phone || '' === $address) {
				wp_safe_redirect(add_query_arg('trade_application', 'error', $redirect));
				exit;
			}

			$user_id = email_exists($email);
			if (! $user_id) {
				$username = sanitize_user(current(explode('@', $email)), true);
				if ('' === $username) {
					$username = 'trade_customer';
				}
				$base_username = $username;
				$suffix        = 1;
				while (username_exists($username)) {
					$username = $base_username . $suffix;
					$suffix++;
				}

				$user_id = wp_create_user($username, wp_generate_password(24, true, true), $email);
				if (is_wp_error($user_id)) {
					wp_safe_redirect(add_query_arg('trade_application', 'error', $redirect));
					exit;
				}

				$user = get_user_by('id', (int) $user_id);
				if ($user instanceof WP_User) {
					$user->set_role('customer');
					wp_new_user_notification($user_id, null, 'user');
				}
			}

			if (! is_numeric($user_id) || (int) $user_id <= 0) {
				wp_safe_redirect(add_query_arg('trade_application', 'error', $redirect));
				exit;
			}

			$user_id = (int) $user_id;

			update_user_meta($user_id, self::META_TRADE_STATUS, 'pending');
			update_user_meta($user_id, 'mad_trade_contact_name', $contact_name);
			update_user_meta($user_id, 'mad_trade_business_name', $business);
			update_user_meta($user_id, 'mad_trade_phone', $phone);
			update_user_meta($user_id, 'mad_trade_vat_number', $vat);
			update_user_meta($user_id, 'mad_trade_business_address', $address);
			update_user_meta($user_id, 'mad_trade_notes', $notes);

			$admin_email = get_option('admin_email');
			$subject     = sprintf(
				/* translators: %s: business name */
				__('New Trade Application: %s', 'mad-baits-trade'),
				$business
			);
			$message     = sprintf(
				"Trade application received.\n\nBusiness: %s\nContact: %s\nEmail: %s\nPhone: %s\nVAT: %s\nAddress: %s\nNotes: %s\nUser ID: %d\n\nReview applications: %s\nApprove this user as Trade Customer to unlock trade checkout.",
				$business,
				$contact_name,
				$email,
				$phone,
				$vat,
				$address,
				$notes,
				$user_id,
				admin_url('admin.php?page=mad-baits-trade-applications')
			);
			wp_mail($admin_email, $subject, $message);

			wp_safe_redirect(add_query_arg('trade_application', 'success', $redirect));
			exit;
		}

		public static function register_settings_page() {
			add_submenu_page(
				'woocommerce',
				__('Trade Applications', 'mad-baits-trade'),
				__('Trade Applications', 'mad-baits-trade'),
				'manage_woocommerce',
				'mad-baits-trade-applications',
				array(__CLASS__, 'render_trade_applications_page')
			);

			add_submenu_page(
				'woocommerce',
				__('Trade Accounts', 'mad-baits-trade'),
				__('Trade Accounts', 'mad-baits-trade'),
				'manage_woocommerce',
				'mad-baits-trade-accounts',
				array(__CLASS__, 'render_settings_page')
			);
		}

		public static function register_settings() {
			register_setting(
				'mad_baits_trade_settings',
				self::OPTION_MIN_ORDER,
				array(
					'type'              => 'number',
					'sanitize_callback' => array(__CLASS__, 'sanitize_min_order'),
					'default'           => 0,
				)
			);

			register_setting(
				'mad_baits_trade_settings',
				self::OPTION_TRADE_TERMS,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'default'           => __('Trade prices are available only to approved trade customers.', 'mad-baits-trade'),
				)
			);

			register_setting(
				'mad_baits_trade_settings',
				self::OPTION_INVOICE_TERMS,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
					'default'           => __('Invoices are sent manually after order review. Payment terms apply per approved trade account.', 'mad-baits-trade'),
				)
			);
		}

		public static function sanitize_min_order($value) {
			$value = is_numeric($value) ? (float) $value : 0;
			return max(0, $value);
		}

		public static function render_settings_page() {
			if (! current_user_can('manage_woocommerce')) {
				return;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e('Trade Account Settings', 'mad-baits-trade'); ?></h1>
				<form method="post" action="options.php">
					<?php settings_fields('mad_baits_trade_settings'); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="<?php echo esc_attr(self::OPTION_MIN_ORDER); ?>"><?php esc_html_e('Minimum trade order value', 'mad-baits-trade'); ?></label></th>
							<td>
								<input type="number" min="0" step="0.01" id="<?php echo esc_attr(self::OPTION_MIN_ORDER); ?>" name="<?php echo esc_attr(self::OPTION_MIN_ORDER); ?>" value="<?php echo esc_attr((string) self::get_minimum_order_value()); ?>" />
								<p class="description"><?php esc_html_e('Set to 0 to disable minimum trade order validation.', 'mad-baits-trade'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr(self::OPTION_TRADE_TERMS); ?>"><?php esc_html_e('Trade terms', 'mad-baits-trade'); ?></label></th>
							<td>
								<textarea id="<?php echo esc_attr(self::OPTION_TRADE_TERMS); ?>" name="<?php echo esc_attr(self::OPTION_TRADE_TERMS); ?>" rows="3" class="large-text"><?php echo esc_textarea((string) get_option(self::OPTION_TRADE_TERMS, '')); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr(self::OPTION_INVOICE_TERMS); ?>"><?php esc_html_e('Invoice/payment terms', 'mad-baits-trade'); ?></label></th>
							<td>
								<textarea id="<?php echo esc_attr(self::OPTION_INVOICE_TERMS); ?>" name="<?php echo esc_attr(self::OPTION_INVOICE_TERMS); ?>" rows="3" class="large-text"><?php echo esc_textarea((string) get_option(self::OPTION_INVOICE_TERMS, '')); ?></textarea>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}

		public static function handle_trade_applications_admin_actions() {
			if (! is_admin() || ! current_user_can('manage_woocommerce')) {
				return;
			}

			$page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash((string) $_REQUEST['page'])) : '';
			if ('mad-baits-trade-applications' !== $page) {
				return;
			}

			if ('POST' === strtoupper((string) $_SERVER['REQUEST_METHOD']) && isset($_POST['mad_trade_bulk_nonce'])) {
				if (! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mad_trade_bulk_nonce'])), 'mad_trade_bulk_action')) {
					return;
				}

				$bulk_action = isset($_POST['mad_trade_bulk_action']) ? sanitize_key(wp_unslash((string) $_POST['mad_trade_bulk_action'])) : '';
				$status_map  = array(
					'approve' => 'approved',
					'reject'  => 'rejected',
					'pending' => 'pending',
				);
				$user_ids    = isset($_POST['trade_user_ids']) && is_array($_POST['trade_user_ids']) ? array_map('absint', wp_unslash($_POST['trade_user_ids'])) : array();

				if (! isset($status_map[ $bulk_action ]) || empty($user_ids)) {
					$redirect = add_query_arg(
						array(
							'page'              => 'mad-baits-trade-applications',
							'trade_status'      => isset($_POST['trade_status']) ? sanitize_key(wp_unslash((string) $_POST['trade_status'])) : 'pending',
							'trade_search'      => isset($_POST['trade_search']) ? sanitize_text_field(wp_unslash((string) $_POST['trade_search'])) : '',
							'paged'             => isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1,
							'orderby'           => isset($_POST['orderby']) ? sanitize_key(wp_unslash((string) $_POST['orderby'])) : 'submitted',
							'order'             => isset($_POST['order']) ? strtoupper(sanitize_key(wp_unslash((string) $_POST['order']))) : 'DESC',
							'trade_status_save' => 'error',
						),
						admin_url('admin.php')
					);
					wp_safe_redirect($redirect);
					exit;
				}

				$updated_count = 0;
				foreach ($user_ids as $user_id) {
					if ($user_id > 0 && self::apply_trade_status_to_user($user_id, $status_map[ $bulk_action ])) {
						$updated_count++;
					}
				}

				$redirect = add_query_arg(
					array(
						'page'              => 'mad-baits-trade-applications',
						'trade_status'      => isset($_POST['trade_status']) ? sanitize_key(wp_unslash((string) $_POST['trade_status'])) : 'pending',
						'trade_search'      => isset($_POST['trade_search']) ? sanitize_text_field(wp_unslash((string) $_POST['trade_search'])) : '',
						'paged'             => isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1,
						'orderby'           => isset($_POST['orderby']) ? sanitize_key(wp_unslash((string) $_POST['orderby'])) : 'submitted',
						'order'             => isset($_POST['order']) ? strtoupper(sanitize_key(wp_unslash((string) $_POST['order']))) : 'DESC',
						'trade_status_save' => $updated_count > 0 ? 'success' : 'error',
						'updated_count'     => $updated_count,
					),
					admin_url('admin.php')
				);
				wp_safe_redirect($redirect);
				exit;
			}

			$page   = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
			$action = isset($_GET['mad_trade_application_action']) ? sanitize_key(wp_unslash((string) $_GET['mad_trade_application_action'])) : '';
			$user   = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

			if ('mad-baits-trade-applications' !== $page || '' === $action || $user <= 0) {
				return;
			}

			$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';
			if (! wp_verify_nonce($nonce, 'mad_trade_application_action_' . $user)) {
				return;
			}

			$status_map = array(
				'approve' => 'approved',
				'reject'  => 'rejected',
				'pending' => 'pending',
				'reset'   => 'none',
			);
			if (! isset($status_map[ $action ])) {
				return;
			}

			$changed = self::apply_trade_status_to_user($user, $status_map[ $action ]);

			$redirect = add_query_arg(
				array(
					'page'              => 'mad-baits-trade-applications',
					'trade_status'      => isset($_GET['trade_status']) ? sanitize_key(wp_unslash((string) $_GET['trade_status'])) : 'pending',
					'trade_search'      => isset($_GET['trade_search']) ? sanitize_text_field(wp_unslash((string) $_GET['trade_search'])) : '',
					'paged'             => isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1,
					'orderby'           => isset($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : 'submitted',
					'order'             => isset($_GET['order']) ? strtoupper(sanitize_key(wp_unslash((string) $_GET['order']))) : 'DESC',
					'trade_status_save' => $changed ? 'success' : 'error',
					'updated_count'     => $changed ? 1 : 0,
				),
				admin_url('admin.php')
			);
			wp_safe_redirect($redirect);
			exit;
		}

		public static function render_trade_applications_page() {
			if (! current_user_can('manage_woocommerce')) {
				return;
			}

			$filter = isset($_GET['trade_status']) ? sanitize_key(wp_unslash((string) $_GET['trade_status'])) : 'pending';
			if (! in_array($filter, array('all', 'pending', 'approved', 'rejected'), true)) {
				$filter = 'pending';
			}

			$paged         = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
			$per_page      = (int) apply_filters('mad_baits_trade_applications_per_page', 20);
			$per_page      = max(5, $per_page);
			$sort_key      = isset($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : 'submitted';
			$sort_order    = isset($_GET['order']) ? strtoupper(sanitize_key(wp_unslash((string) $_GET['order']))) : 'DESC';
			if (! in_array($sort_key, array('submitted', 'business', 'contact', 'email', 'status'), true)) {
				$sort_key = 'submitted';
			}
			if (! in_array($sort_order, array('ASC', 'DESC'), true)) {
				$sort_order = 'DESC';
			}
			$search        = isset($_GET['trade_search']) ? sanitize_text_field(wp_unslash((string) $_GET['trade_search'])) : '';
			$status_notice = isset($_GET['trade_status_save']) ? sanitize_key(wp_unslash((string) $_GET['trade_status_save'])) : '';
			$updated_count = isset($_GET['updated_count']) ? absint($_GET['updated_count']) : 0;
			$results       = self::get_trade_application_users($filter, $search, $paged, $per_page, $sort_key, $sort_order);
			$users         = isset($results['users']) && is_array($results['users']) ? $results['users'] : array();
			$total_users   = isset($results['total']) ? (int) $results['total'] : 0;
			$total_pages   = isset($results['pages']) ? (int) $results['pages'] : 1;
			$range_start   = 0;
			$range_end     = 0;
			if ($total_users > 0) {
				$range_start = (($paged - 1) * $per_page) + 1;
				$range_end   = min($total_users, $paged * $per_page);
			}
			$tabs          = array(
				'pending'  => __('Pending', 'mad-baits-trade'),
				'approved' => __('Approved', 'mad-baits-trade'),
				'rejected' => __('Rejected', 'mad-baits-trade'),
				'all'      => __('All', 'mad-baits-trade'),
			);
			?>
			<div class="wrap">
				<h1><?php esc_html_e('Trade Applications', 'mad-baits-trade'); ?></h1>
				<?php if ('success' === $status_notice) : ?>
					<div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('Trade application status updated (%d user(s)).', 'mad-baits-trade'), max(1, $updated_count))); ?></p></div>
				<?php elseif ('error' === $status_notice) : ?>
					<div class="notice notice-error is-dismissible"><p><?php esc_html_e('Could not update trade application status.', 'mad-baits-trade'); ?></p></div>
				<?php endif; ?>

				<h2 class="nav-tab-wrapper">
					<?php foreach ($tabs as $key => $label) : ?>
						<?php
						$tab_url = add_query_arg(
							array(
								'page'         => 'mad-baits-trade-applications',
								'trade_status' => $key,
								'trade_search' => $search,
								'paged'        => 1,
								'orderby'      => $sort_key,
								'order'        => $sort_order,
							),
							admin_url('admin.php')
						);
						?>
						<a href="<?php echo esc_url($tab_url); ?>" class="nav-tab <?php echo $filter === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
					<?php endforeach; ?>
				</h2>

				<form method="get" action="">
					<input type="hidden" name="page" value="mad-baits-trade-applications">
					<input type="hidden" name="trade_status" value="<?php echo esc_attr($filter); ?>">
					<input type="hidden" name="paged" value="1">
					<input type="hidden" name="orderby" value="<?php echo esc_attr($sort_key); ?>">
					<input type="hidden" name="order" value="<?php echo esc_attr($sort_order); ?>">
					<p class="search-box">
						<label class="screen-reader-text" for="trade-search-input"><?php esc_html_e('Search applications', 'mad-baits-trade'); ?></label>
						<input type="search" id="trade-search-input" name="trade_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search business, contact, email or phone', 'mad-baits-trade'); ?>">
						<?php submit_button(__('Search', 'mad-baits-trade'), 'button', '', false); ?>
					</p>
				</form>

				<?php if ($total_users > 0) : ?>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: first item number, 2: last item number, 3: total */
								__('Showing %1$d-%2$d of %3$d applications.', 'mad-baits-trade'),
								$range_start,
								$range_end,
								$total_users
							)
						);
						?>
					</p>
				<?php endif; ?>

				<form method="post" action="">
					<?php wp_nonce_field('mad_trade_bulk_action', 'mad_trade_bulk_nonce'); ?>
					<input type="hidden" name="page" value="mad-baits-trade-applications">
					<input type="hidden" name="trade_status" value="<?php echo esc_attr($filter); ?>">
					<input type="hidden" name="trade_search" value="<?php echo esc_attr($search); ?>">
					<input type="hidden" name="paged" value="<?php echo esc_attr((string) $paged); ?>">
					<input type="hidden" name="orderby" value="<?php echo esc_attr($sort_key); ?>">
					<input type="hidden" name="order" value="<?php echo esc_attr($sort_order); ?>">
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<label for="mad-trade-bulk-action-selector" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'mad-baits-trade'); ?></label>
							<select id="mad-trade-bulk-action-selector" name="mad_trade_bulk_action">
								<option value=""><?php esc_html_e('Bulk actions', 'mad-baits-trade'); ?></option>
								<option value="approve"><?php esc_html_e('Approve', 'mad-baits-trade'); ?></option>
								<option value="reject"><?php esc_html_e('Reject', 'mad-baits-trade'); ?></option>
								<option value="pending"><?php esc_html_e('Set Pending', 'mad-baits-trade'); ?></option>
							</select>
							<?php submit_button(__('Apply', 'mad-baits-trade'), 'action', '', false); ?>
						</div>
						<?php if ($total_pages > 1) : ?>
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									paginate_links(
										array(
											'base'      => add_query_arg(
												array(
													'page'         => 'mad-baits-trade-applications',
													'trade_status' => $filter,
													'trade_search' => $search,
													'paged'        => '%#%',
													'orderby'      => $sort_key,
													'order'        => $sort_order,
												),
												admin_url('admin.php')
											),
											'format'    => '',
											'current'   => $paged,
											'total'     => $total_pages,
											'prev_text' => __('&laquo;', 'mad-baits-trade'),
											'next_text' => __('&raquo;', 'mad-baits-trade'),
											'type'      => 'plain',
										)
									)
								);
								?>
							</div>
						<?php endif; ?>
					</div>

					<table class="widefat striped" style="margin-top:16px;">
						<thead>
							<tr>
								<td class="manage-column check-column"><input type="checkbox" id="mad-trade-check-all"></td>
								<?php echo wp_kses_post(self::render_sortable_header(__('Business', 'mad-baits-trade'), 'business', $sort_key, $sort_order, $filter, $search)); ?>
								<?php echo wp_kses_post(self::render_sortable_header(__('Contact', 'mad-baits-trade'), 'contact', $sort_key, $sort_order, $filter, $search)); ?>
								<?php echo wp_kses_post(self::render_sortable_header(__('Email', 'mad-baits-trade'), 'email', $sort_key, $sort_order, $filter, $search)); ?>
								<th><?php esc_html_e('Phone', 'mad-baits-trade'); ?></th>
								<?php echo wp_kses_post(self::render_sortable_header(__('Trade Status', 'mad-baits-trade'), 'status', $sort_key, $sort_order, $filter, $search)); ?>
								<?php echo wp_kses_post(self::render_sortable_header(__('Submitted', 'mad-baits-trade'), 'submitted', $sort_key, $sort_order, $filter, $search)); ?>
								<th><?php esc_html_e('Actions', 'mad-baits-trade'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($users)) : ?>
								<tr>
									<td colspan="8"><?php esc_html_e('No trade applications found for this filter.', 'mad-baits-trade'); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ($users as $user) : ?>
									<?php
									$user_id   = (int) $user->ID;
									$email     = (string) $user->user_email;
									$contact   = (string) get_user_meta($user_id, 'mad_trade_contact_name', true);
									$business  = (string) get_user_meta($user_id, 'mad_trade_business_name', true);
									$phone     = (string) get_user_meta($user_id, 'mad_trade_phone', true);
									$status    = self::get_trade_status_slug($user_id);
									$status_ui = self::get_trade_status_label($user_id);
									$profile   = get_edit_user_link($user_id);
									$approve   = wp_nonce_url(
										add_query_arg(
											array(
												'page'                         => 'mad-baits-trade-applications',
												'trade_status'                 => $filter,
												'trade_search'                 => $search,
												'paged'                        => $paged,
												'orderby'                      => $sort_key,
												'order'                        => $sort_order,
												'mad_trade_application_action' => 'approve',
												'user_id'                      => $user_id,
											),
											admin_url('admin.php')
										),
										'mad_trade_application_action_' . $user_id
									);
									$reject    = wp_nonce_url(
										add_query_arg(
											array(
												'page'                         => 'mad-baits-trade-applications',
												'trade_status'                 => $filter,
												'trade_search'                 => $search,
												'paged'                        => $paged,
												'orderby'                      => $sort_key,
												'order'                        => $sort_order,
												'mad_trade_application_action' => 'reject',
												'user_id'                      => $user_id,
											),
											admin_url('admin.php')
										),
										'mad_trade_application_action_' . $user_id
									);
									$pending   = wp_nonce_url(
										add_query_arg(
											array(
												'page'                         => 'mad-baits-trade-applications',
												'trade_status'                 => $filter,
												'trade_search'                 => $search,
												'paged'                        => $paged,
												'orderby'                      => $sort_key,
												'order'                        => $sort_order,
												'mad_trade_application_action' => 'pending',
												'user_id'                      => $user_id,
											),
											admin_url('admin.php')
										),
										'mad_trade_application_action_' . $user_id
									);
									?>
									<tr>
										<th scope="row" class="check-column"><input type="checkbox" name="trade_user_ids[]" value="<?php echo esc_attr((string) $user_id); ?>"></th>
										<td><?php echo esc_html('' !== $business ? $business : $user->display_name); ?></td>
										<td><?php echo esc_html($contact); ?></td>
										<td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
										<td><?php echo esc_html($phone); ?></td>
										<td><strong><?php echo esc_html($status_ui); ?></strong></td>
										<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime((string) $user->user_registered))); ?></td>
										<td>
											<?php if ('approved' !== $status) : ?>
												<a class="button button-primary button-small" href="<?php echo esc_url($approve); ?>"><?php esc_html_e('Approve', 'mad-baits-trade'); ?></a>
											<?php endif; ?>
											<?php if ('rejected' !== $status) : ?>
												<a class="button button-small" href="<?php echo esc_url($reject); ?>"><?php esc_html_e('Reject', 'mad-baits-trade'); ?></a>
											<?php endif; ?>
											<?php if ('pending' !== $status) : ?>
												<a class="button button-small" href="<?php echo esc_url($pending); ?>"><?php esc_html_e('Set Pending', 'mad-baits-trade'); ?></a>
											<?php endif; ?>
											<?php if ($profile) : ?>
												<a class="button button-link" href="<?php echo esc_url($profile); ?>"><?php esc_html_e('Open User', 'mad-baits-trade'); ?></a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					<?php if ($total_pages > 1) : ?>
						<div class="tablenav bottom">
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									paginate_links(
										array(
											'base'      => add_query_arg(
												array(
													'page'         => 'mad-baits-trade-applications',
													'trade_status' => $filter,
													'trade_search' => $search,
													'paged'        => '%#%',
													'orderby'      => $sort_key,
													'order'        => $sort_order,
												),
												admin_url('admin.php')
											),
											'format'    => '',
											'current'   => $paged,
											'total'     => $total_pages,
											'prev_text' => __('&laquo;', 'mad-baits-trade'),
											'next_text' => __('&raquo;', 'mad-baits-trade'),
											'type'      => 'plain',
										)
									)
								);
								?>
							</div>
						</div>
					<?php endif; ?>
				</form>
				<script>
					(function() {
						var toggle = document.getElementById('mad-trade-check-all');
						if (! toggle) {
							return;
						}
						toggle.addEventListener('change', function() {
							var checkboxes = document.querySelectorAll('input[name="trade_user_ids[]"]');
							for (var i = 0; i < checkboxes.length; i++) {
								checkboxes[i].checked = toggle.checked;
							}
						});
					})();
				</script>
			</div>
			<?php
		}

		private static function render_sortable_header($label, $column_key, $current_sort, $current_order, $filter, $search) {
			$is_current = $column_key === $current_sort;
			$next_order = ($is_current && 'ASC' === $current_order) ? 'DESC' : 'ASC';
			$class      = 'manage-column sortable';
			if ($is_current) {
				$class = 'manage-column sorted ' . strtolower($current_order);
			}

			$url = add_query_arg(
				array(
					'page'         => 'mad-baits-trade-applications',
					'trade_status' => $filter,
					'trade_search' => $search,
					'paged'        => 1,
					'orderby'      => $column_key,
					'order'        => $next_order,
				),
				admin_url('admin.php')
			);

			return sprintf(
				'<th class="%1$s"><a href="%2$s"><span>%3$s</span><span class="sorting-indicator" aria-hidden="true"></span></a></th>',
				esc_attr($class),
				esc_url($url),
				esc_html($label)
			);
		}

		private static function get_trade_application_users($filter = 'pending', $search = '', $paged = 1, $per_page = 20, $sort_key = 'submitted', $sort_order = 'DESC') {
			$args = array(
				'number'       => max(1, (int) $per_page),
				'offset'       => (max(1, (int) $paged) - 1) * max(1, (int) $per_page),
				'orderby'      => 'registered',
				'order'        => 'DESC',
				'count_total'  => true,
				'fields'       => 'all',
			);

			$sort_key   = sanitize_key((string) $sort_key);
			$sort_order = 'ASC' === strtoupper((string) $sort_order) ? 'ASC' : 'DESC';
			if (! in_array($sort_key, array('submitted', 'business', 'contact', 'email', 'status'), true)) {
				$sort_key = 'submitted';
			}

			if ('business' === $sort_key) {
				$args['meta_key'] = 'mad_trade_business_name';
				$args['orderby']  = 'meta_value';
				$args['order']    = $sort_order;
			} elseif ('contact' === $sort_key) {
				$args['meta_key'] = 'mad_trade_contact_name';
				$args['orderby']  = 'meta_value';
				$args['order']    = $sort_order;
			} elseif ('status' === $sort_key) {
				$args['meta_key'] = self::META_TRADE_STATUS;
				$args['orderby']  = 'meta_value';
				$args['order']    = $sort_order;
			} elseif ('email' === $sort_key) {
				$args['orderby'] = 'email';
				$args['order']   = $sort_order;
			} else {
				$args['orderby'] = 'registered';
				$args['order']   = $sort_order;
			}

			$status_meta_query = array();
			if ('approved' === $filter) {
				$status_meta_query = array(
					array(
						'key'   => self::META_TRADE_STATUS,
						'value' => 'approved',
					),
				);
			} elseif ('pending' === $filter || 'rejected' === $filter) {
				$status_meta_query = array(
					array(
						'key'   => self::META_TRADE_STATUS,
						'value' => $filter,
					),
				);
			} else {
				$status_meta_query = array(
					array(
						'key'     => self::META_TRADE_STATUS,
						'compare' => 'EXISTS',
					),
				);
			}

			$search = sanitize_text_field((string) $search);
			if ('' !== $search) {
				$args['search']         = '*' . $search . '*';
				$args['search_columns'] = array('user_email', 'display_name', 'user_login');

				$search_meta_query = array(
					'relation' => 'OR',
					array(
						'key'     => 'mad_trade_business_name',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'mad_trade_contact_name',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'mad_trade_phone',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'mad_trade_vat_number',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				);

				$args['meta_query'] = array(
					'relation' => 'AND',
					$status_meta_query,
					$search_meta_query,
				);
				$query = new WP_User_Query($args);
				$total = (int) $query->get_total();
				return array(
					'users' => (array) $query->get_results(),
					'total' => $total,
					'pages' => max(1, (int) ceil($total / max(1, (int) $per_page))),
				);
			}

			$args['meta_query'] = $status_meta_query;
			$query = new WP_User_Query($args);
			$total = (int) $query->get_total();
			return array(
				'users' => (array) $query->get_results(),
				'total' => $total,
				'pages' => max(1, (int) ceil($total / max(1, (int) $per_page))),
			);
		}

		public static function render_user_trade_fields($user) {
			if (! current_user_can('manage_woocommerce')) {
				return;
			}

			$status = (string) get_user_meta($user->ID, self::META_TRADE_STATUS, true);
			if ('' === $status && in_array(self::ROLE, (array) $user->roles, true)) {
				$status = 'approved';
			} elseif ('' === $status) {
				$status = 'none';
			}
			?>
			<h2><?php esc_html_e('Trade Account', 'mad-baits-trade'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="mad_trade_status"><?php esc_html_e('Trade status', 'mad-baits-trade'); ?></label></th>
					<td>
						<select id="mad_trade_status" name="mad_trade_status">
							<option value="none" <?php selected($status, 'none'); ?>><?php esc_html_e('Not trade', 'mad-baits-trade'); ?></option>
							<option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'mad-baits-trade'); ?></option>
							<option value="approved" <?php selected($status, 'approved'); ?>><?php esc_html_e('Approved', 'mad-baits-trade'); ?></option>
							<option value="rejected" <?php selected($status, 'rejected'); ?>><?php esc_html_e('Rejected', 'mad-baits-trade'); ?></option>
						</select>
						<p class="description"><?php esc_html_e('Approved users get Trade Customer role and can use Trade Invoice Account at checkout.', 'mad-baits-trade'); ?></p>
					</td>
				</tr>
			</table>
			<?php
		}

		public static function save_user_trade_fields($user_id) {
			if (! current_user_can('manage_woocommerce')) {
				return;
			}
			if (! isset($_POST['mad_trade_status'])) {
				return;
			}

			$status = sanitize_key(wp_unslash((string) $_POST['mad_trade_status']));
			if (! in_array($status, array('none', 'pending', 'approved', 'rejected'), true)) {
				$status = 'none';
			}

			self::apply_trade_status_to_user((int) $user_id, $status);
		}

		public static function render_my_account_trade_block() {
			if (! is_user_logged_in()) {
				return;
			}

			$user_id      = get_current_user_id();
			$is_trade     = self::is_approved_trade_user($user_id);
			$trade_status = self::get_trade_status_label($user_id);
			$orders       = function_exists('wc_get_orders') ? wc_get_orders(
				array(
					'customer' => $user_id,
					'limit'    => 5,
					'orderby'  => 'date',
					'order'    => 'DESC',
					'status'   => array('pending', 'processing', 'on-hold', 'completed', self::STATUS),
				)
			) : array();

			$minimum_order = self::get_minimum_order_value();
			$trade_terms   = (string) get_option(self::OPTION_TRADE_TERMS, __('Trade prices are available only to approved trade customers.', 'mad-baits-trade'));
			$invoice_terms = (string) get_option(self::OPTION_INVOICE_TERMS, __('Invoices are sent manually after order review. Payment terms apply per approved trade account.', 'mad-baits-trade'));
			$apply_url     = home_url('/trade-application/');

			echo '<section class="mad-trade-account">';
			echo '<h3>' . esc_html__('Trade Account', 'mad-baits-trade') . '</h3>';
			echo '<p><strong>' . esc_html__('Trade status:', 'mad-baits-trade') . '</strong> ' . esc_html($trade_status) . '</p>';

			if (! $is_trade) {
				echo '<p>' . esc_html__('Your account is not currently approved for trade invoicing.', 'mad-baits-trade') . '</p>';
				echo '<p><a class="mad-button" href="' . esc_url($apply_url) . '">' . esc_html__('Apply for Trade Account', 'mad-baits-trade') . '</a></p>';
				echo '</section>';
				return;
			}

			if ($minimum_order > 0) {
				echo '<p><strong>' . esc_html__('Minimum order:', 'mad-baits-trade') . '</strong> ' . wp_kses_post(wc_price($minimum_order)) . '</p>';
			}

			echo '<div class="mad-trade-account__grid">';
			echo '<article><h4>' . esc_html__('Trade terms', 'mad-baits-trade') . '</h4><p>' . esc_html($trade_terms) . '</p></article>';
			echo '<article><h4>' . esc_html__('Invoice/payment terms', 'mad-baits-trade') . '</h4><p>' . esc_html($invoice_terms) . '</p></article>';
			echo '</div>';

			echo '<h4>' . esc_html__('Previous trade orders', 'mad-baits-trade') . '</h4>';
			if (empty($orders)) {
				echo '<p>' . esc_html__('No previous orders found.', 'mad-baits-trade') . '</p>';
			} else {
				echo '<ul class="mad-trade-account__orders">';
				foreach ($orders as $order) {
					if (! $order instanceof WC_Order) {
						continue;
					}

					$reorder_url = wp_nonce_url(
						add_query_arg(
							array(
								'mad_trade_reorder' => $order->get_id(),
							),
							wc_get_account_endpoint_url('orders')
						),
						'mad_trade_reorder_' . $order->get_id()
					);

					echo '<li>';
					echo '<span>' . esc_html('#' . $order->get_order_number()) . ' - ' . esc_html(wc_get_order_status_name($order->get_status())) . '</span>';
					echo '<span>' . wp_kses_post($order->get_formatted_order_total()) . '</span>';
					echo '<a class="mad-button mad-button--ghost" href="' . esc_url($reorder_url) . '">' . esc_html__('Quick reorder', 'mad-baits-trade') . '</a>';
					echo '</li>';
				}
				echo '</ul>';
			}

			echo '</section>';
		}

		public static function handle_trade_reorder() {
			if (! is_user_logged_in() || ! isset($_GET['mad_trade_reorder'])) {
				return;
			}

			if (! function_exists('WC') || ! WC()->cart) {
				return;
			}

			$order_id = absint($_GET['mad_trade_reorder']);
			if ($order_id <= 0 || ! wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '', 'mad_trade_reorder_' . $order_id)) {
				return;
			}

			$order = wc_get_order($order_id);
			if (! $order instanceof WC_Order || (int) $order->get_user_id() !== get_current_user_id()) {
				return;
			}

			$added = 0;
			foreach ($order->get_items() as $item) {
				if (! $item instanceof WC_Order_Item_Product) {
					continue;
				}

				$product = $item->get_product();
				if (! $product instanceof WC_Product) {
					continue;
				}

				$product_id   = $product->get_id();
				$variation_id = 0;
				$variation    = array();
				if ($product->is_type('variation')) {
					$variation_id = $product->get_id();
					$product_id   = $product->get_parent_id();
					$variation    = $item->get_variation_attributes();
				}

				$quantity = max(1, (int) $item->get_quantity());
				$result   = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
				if ($result) {
					$added++;
				}
			}

			if ($added > 0) {
				wc_add_notice(__('Previous order items added to cart.', 'mad-baits-trade'), 'success');
			} else {
				wc_add_notice(__('Could not reorder those items. Please add products manually.', 'mad-baits-trade'), 'error');
			}

			wp_safe_redirect(wc_get_cart_url());
			exit;
		}

		public static function enqueue_styles() {
			if (! function_exists('is_account_page')) {
				return;
			}

			$load = is_page('trade') || is_page('trade-application') || is_account_page();
			if (! $load) {
				return;
			}

			wp_enqueue_style(
				'mad-baits-trade-accounts',
				plugins_url('assets/css/trade-accounts.css', __FILE__),
				array(),
				'1.0.0'
			);
		}

		public static function enforce_trade_product_page_access() {
			if (! function_exists('is_product') || ! is_product() || ! self::is_approved_trade_user(get_current_user_id())) {
				return;
			}

			$product_id = get_queried_object_id();
			if ($product_id <= 0 || self::is_trade_only_product($product_id)) {
				return;
			}

			wp_safe_redirect(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));
			exit;
		}

		public static function create_trade_pages() {
			self::maybe_create_page(
				'trade',
				__('Trade', 'mad-baits-trade'),
				'[mad_trade_landing]'
			);

			self::maybe_create_page(
				'trade-application',
				__('Trade Application', 'mad-baits-trade'),
				'[mad_trade_application_form]'
			);
		}

		private static function maybe_create_page($slug, $title, $content) {
			$page = get_page_by_path($slug);
			if ($page instanceof WP_Post) {
				return;
			}

			wp_insert_post(
				array(
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $content,
				)
			);
		}

		public static function add_trade_product_fields() {
			echo '<div class="options_group">';
			woocommerce_wp_checkbox(
				array(
					'id'          => self::META_TRADE_ONLY,
					'label'       => __('Trade-only product', 'mad-baits-trade'),
					'description' => __('Show this product to approved trade customers only.', 'mad-baits-trade'),
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'          => self::META_TRADE_PRICE,
					'label'       => __('Trade price', 'mad-baits-trade'),
					'description' => __('Optional override price shown only to approved trade customers.', 'mad-baits-trade'),
					'desc_tip'    => true,
					'type'        => 'price',
				)
			);
			echo '</div>';
		}

		public static function save_trade_product_fields($product_id) {
			$trade_only = isset($_POST[ self::META_TRADE_ONLY ]) ? 'yes' : 'no';
			update_post_meta($product_id, self::META_TRADE_ONLY, $trade_only);

			$trade_price = isset($_POST[ self::META_TRADE_PRICE ]) ? wc_format_decimal(wp_unslash((string) $_POST[ self::META_TRADE_PRICE ])) : '';
			if ('' === $trade_price) {
				delete_post_meta($product_id, self::META_TRADE_PRICE);
			} else {
				update_post_meta($product_id, self::META_TRADE_PRICE, $trade_price);
			}
		}

		public static function filter_trade_price($price, $product) {
			if (! self::is_approved_trade_user(get_current_user_id()) || ! $product instanceof WC_Product) {
				return $price;
			}

			$trade_price = self::get_product_trade_price($product);
			return null !== $trade_price ? $trade_price : $price;
		}

		public static function filter_trade_sale_price($price, $product) {
			if (! self::is_approved_trade_user(get_current_user_id()) || ! $product instanceof WC_Product) {
				return $price;
			}

			$trade_price = self::get_product_trade_price($product);
			return null !== $trade_price ? '' : $price;
		}

		private static function get_product_trade_price($product) {
			$product_id = $product->get_id();
			$value      = get_post_meta($product_id, self::META_TRADE_PRICE, true);

			if ($product->is_type('variation') && '' === $value) {
				$value = get_post_meta($product->get_parent_id(), self::META_TRADE_PRICE, true);
			}

			if ('' === $value || ! is_numeric($value)) {
				return null;
			}

			return (string) wc_format_decimal((string) $value);
		}

		public static function hide_trade_only_products_from_retail($query) {
			if (is_admin()) {
				return;
			}

			$meta_query   = $query->get('meta_query');
			$meta_query   = is_array($meta_query) ? $meta_query : array();
			if (self::is_approved_trade_user(get_current_user_id())) {
				$meta_query[] = array(
					'key'     => self::META_TRADE_ONLY,
					'value'   => 'yes',
					'compare' => '=',
				);
			} else {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => self::META_TRADE_ONLY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_TRADE_ONLY,
						'value'   => 'yes',
						'compare' => '!=',
					),
				);
			}

			$query->set('meta_query', $meta_query);
		}

		public static function hide_trade_only_product_visibility($visible, $product_id) {
			if (self::is_approved_trade_user(get_current_user_id())) {
				return self::is_trade_only_product($product_id) ? $visible : false;
			}

			return self::is_trade_only_product($product_id) ? false : $visible;
		}

		public static function hide_trade_only_variation_visibility($visible, $variation_id, $parent_id, $variation) {
			if (self::is_approved_trade_user(get_current_user_id())) {
				return self::is_trade_only_product($variation_id) || self::is_trade_only_product($parent_id) ? $visible : false;
			}

			return self::is_trade_only_product($variation_id) || self::is_trade_only_product($parent_id) ? false : $visible;
		}

		public static function validate_trade_only_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
			$check_id = $variation_id > 0 ? $variation_id : $product_id;
			if (self::is_approved_trade_user(get_current_user_id())) {
				if (! self::is_trade_only_product($check_id)) {
					wc_add_notice(__('Trade accounts can only order selected trade products.', 'mad-baits-trade'), 'error');
					return false;
				}
			} else {
				if (self::is_trade_only_product($check_id)) {
					wc_add_notice(__('This product is available to approved trade customers only.', 'mad-baits-trade'), 'error');
					return false;
				}
			}

			return $passed;
		}

		public static function validate_trade_only_cart_contents() {
			if (! function_exists('WC') || ! WC()->cart) {
				return;
			}

			$is_trade_user = self::is_approved_trade_user(get_current_user_id());
			foreach (WC()->cart->get_cart() as $cart_item) {
				$product_id = isset($cart_item['variation_id']) && (int) $cart_item['variation_id'] > 0 ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
				$is_trade_product = self::is_trade_only_product($product_id);
				if (! $is_trade_user && $is_trade_product) {
					wc_add_notice(__('Trade-only items were removed from your cart.', 'mad-baits-trade'), 'error');
					WC()->cart->remove_cart_item($cart_item['key']);
				} elseif ($is_trade_user && ! $is_trade_product) {
					wc_add_notice(__('Only selected trade products can remain in a trade account cart.', 'mad-baits-trade'), 'error');
					WC()->cart->remove_cart_item($cart_item['key']);
				}
			}
		}

		public static function is_trade_only_product($product_id) {
			$value = get_post_meta($product_id, self::META_TRADE_ONLY, true);
			if ('yes' === $value) {
				return true;
			}

			$parent_id = wp_get_post_parent_id($product_id);
			if ($parent_id > 0 && 'yes' === get_post_meta($parent_id, self::META_TRADE_ONLY, true)) {
				return true;
			}

			return false;
		}

		private static function apply_trade_status_to_user($user_id, $status) {
			$user_id = (int) $user_id;
			$status  = sanitize_key((string) $status);
			if ($user_id <= 0 || ! in_array($status, array('none', 'pending', 'approved', 'rejected'), true)) {
				return false;
			}

			update_user_meta($user_id, self::META_TRADE_STATUS, $status);

			$user = get_user_by('id', $user_id);
			if (! $user instanceof WP_User) {
				return false;
			}

			if ('approved' === $status) {
				if (! in_array(self::ROLE, (array) $user->roles, true)) {
					$user->add_role(self::ROLE);
				}
				return true;
			}

			if (in_array(self::ROLE, (array) $user->roles, true)) {
				$user->remove_role(self::ROLE);
			}

			if (empty($user->roles)) {
				$user->set_role('customer');
			}

			return true;
		}

		public static function get_trade_status_slug($user_id) {
			$status = sanitize_key((string) get_user_meta((int) $user_id, self::META_TRADE_STATUS, true));
			if ('' === $status || ! in_array($status, array('pending', 'approved', 'rejected', 'none'), true)) {
				return self::is_approved_trade_user($user_id) ? 'approved' : 'none';
			}
			return $status;
		}

		public static function is_approved_trade_user($user_id) {
			$user_id = (int) $user_id;
			if ($user_id <= 0) {
				return false;
			}

			$user = get_user_by('id', $user_id);
			if (! $user instanceof WP_User) {
				return false;
			}

			$has_role = in_array(self::ROLE, (array) $user->roles, true);
			if (! $has_role) {
				return false;
			}

			$status = (string) get_user_meta($user_id, self::META_TRADE_STATUS, true);
			return '' === $status || 'approved' === $status;
		}

		public static function get_trade_status_label($user_id) {
			$status = self::get_trade_status_slug($user_id);

			switch ($status) {
				case 'approved':
					return __('Approved', 'mad-baits-trade');
				case 'pending':
					return __('Pending review', 'mad-baits-trade');
				case 'rejected':
					return __('Rejected', 'mad-baits-trade');
				default:
					return __('Not trade', 'mad-baits-trade');
			}
		}

		public static function get_minimum_order_value() {
			return (float) get_option(self::OPTION_MIN_ORDER, 0);
		}

		public static function is_trade_order($order) {
			if (! $order instanceof WC_Order) {
				return false;
			}

			return self::GATEWAY_ID === $order->get_payment_method() || self::STATUS === $order->get_status();
		}
	}
}

if (! function_exists('mad_baits_register_trade_gateway_class')) {
	function mad_baits_register_trade_gateway_class() {
		if (! class_exists('WC_Payment_Gateway') || class_exists('WC_Gateway_Mad_Trade_Invoice_Account')) {
			return;
		}

		class WC_Gateway_Mad_Trade_Invoice_Account extends WC_Payment_Gateway {
			public function __construct() {
				$this->id                 = Mad_Baits_Trade_Accounts::GATEWAY_ID;
				$this->icon               = '';
				$this->has_fields         = false;
				$this->method_title       = __('Trade Invoice Account', 'mad-baits-trade');
				$this->method_description = __('Approved trade customers can place orders for manual invoicing.', 'mad-baits-trade');
				$this->supports           = array('products');

				$this->title       = __('Trade Invoice Account', 'mad-baits-trade');
				$this->description = __('Place your trade order now. Our team will review and send your invoice shortly.', 'mad-baits-trade');
			}

			public function process_payment($order_id) {
				$order = wc_get_order($order_id);
				if (! $order instanceof WC_Order) {
					wc_add_notice(__('Unable to process your trade order. Please try again.', 'mad-baits-trade'), 'error');
					return array('result' => 'fail');
				}

				if (! Mad_Baits_Trade_Accounts::is_approved_trade_user((int) $order->get_user_id())) {
					wc_add_notice(__('Trade invoicing is available only to approved trade accounts.', 'mad-baits-trade'), 'error');
					return array('result' => 'fail');
				}

				$order->update_status(Mad_Baits_Trade_Accounts::STATUS, __('Trade order submitted. Manual invoice required.', 'mad-baits-trade'));
				$order->add_order_note(__('TRADE ORDER - MANUAL INVOICE REQUIRED', 'mad-baits-trade'));

				if (function_exists('WC') && WC()->cart) {
					WC()->cart->empty_cart();
				}

				Mad_Baits_Trade_Accounts::send_trade_customer_confirmation_email($order);

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url($order),
				);
			}
		}
	}
}

register_activation_hook(__FILE__, array('Mad_Baits_Trade_Accounts', 'activate'));
Mad_Baits_Trade_Accounts::init();
