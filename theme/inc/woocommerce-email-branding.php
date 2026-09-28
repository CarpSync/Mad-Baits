<?php
/**
 * Mad Baits WooCommerce email branding (HTML emails).
 *
 * Uses WC hooks + theme overrides for email-header.php / email-footer.php.
 * Keep WooCommerce > Settings > Emails colour fields aligned with brand (see readme in user docs).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Logo URL: WooCommerce header image if set, else first existing theme asset.
 *
 * @return string
 */
function mad_baits_get_wc_email_brand_logo_url() {
	$wc_img = get_option('woocommerce_email_header_image', '');
	if (is_string($wc_img) && '' !== trim($wc_img)) {
		return esc_url($wc_img);
	}

	static $resolved = null;
	if (null !== $resolved) {
		return $resolved;
	}

	$candidates = array(
		'assets/img/mad-yellow-logo-1.png',
		'assets/img/CUP-LOGOv2-1.svg',
	);
	foreach ($candidates as $rel) {
		$abs = trailingslashit(get_template_directory()) . ltrim($rel, '/');
		if (file_exists($abs)) {
			$resolved = esc_url(trailingslashit(get_template_directory_uri()) . ltrim($rel, '/'));

			return $resolved;
		}
	}

	$resolved = '';

	return $resolved;
}

/**
 * Admin URL for editing an order.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function mad_baits_wc_email_admin_order_edit_url($order) {
	if (! $order instanceof WC_Order) {
		return '';
	}

	return admin_url('post.php?post=' . (int) $order->get_id() . '&action=edit');
}

/**
 * Free shipping reassurance line (filterable threshold copy).
 *
 * @return string
 */
function mad_baits_wc_email_free_shipping_sentence() {
	$default = __('Free UK delivery usually unlocks from £100 before shipping — your checkout always shows the live total and any discount.', 'mad-baits');

	return (string) apply_filters('mad_baits_wc_email_free_shipping_sentence', $default);
}

/**
 * Social profiles as plaintext link lines for emails.
 *
 * @return array<int, array{href:string,label:string}>
 */
function mad_baits_wc_email_social_links() {
	if (! function_exists('mad_baits_get_active_social_profiles')) {
		return array();
	}

	$out = array();
	foreach (mad_baits_get_active_social_profiles() as $profile) {
		if (empty($profile['url'])) {
			continue;
		}
		$out[] = array(
			'href'  => esc_url($profile['url']),
			'label' => sanitize_text_field($profile['label']),
		);
	}

	return $out;
}

/**
 * Append responsive + brand overrides (Gmail-safe, table layout preserved).
 *
 * @param string $css Existing CSS from WooCommerce email-styles.php.
 * @return string
 */
function mad_baits_wc_email_styles_append($css, $email = null) {
	$css .= '

/* Mad Baits transactional email overrides */
body, #outer_wrapper {
	background-color: #eaeaea !important;
}
#wrapper {
	padding: 32px 12px !important;
	max-width: 600px !important;
	margin: 0 auto !important;
}
#template_container {
	border: none !important;
	box-shadow: 0 15px 50px rgba(0, 0, 0, 0.28) !important;
	border-radius: 0 !important;
	overflow: hidden;
}
.mad-email-brand-shell {
	width: 100%;
	border-collapse: collapse;
}
.mad-email-brand-shell td {
	padding: 0 !important;
}
#template_header {
	background-color: #0a0a0a !important;
	border-radius: 0 !important;
	color: #ffffff !important;
	border-bottom: 4px solid #fff202 !important;
}
#template_header h1,
#template_header h1 a {
	color: #ffffff !important;
	text-shadow: none !important;
	font-weight: 800 !important;
	font-size: 22px !important;
	line-height: 1.3 !important;
	letter-spacing: 0.02em;
}
#header_wrapper {
	padding: 24px 28px !important;
}
#body_content {
	background-color: #ffffff !important;
}
#body_content table > tbody > tr > td {
	padding: 28px 24px 20px !important;
}
#body_content_inner {
	color: #151515 !important;
	font-size: 15.5px !important;
	line-height: 1.62 !important;
}
#body_content_inner p {
	color: #151515 !important;
	margin: 0 0 14px !important;
}
#body_content_inner h2 {
	color: #0a0a0a !important;
	font-size: 19px !important;
	margin: 28px 0 12px !important;
	border-bottom: 2px solid #fff202;
	padding-bottom: 6px;
	display: inline-block;
}
#body_content_inner h3 {
	color: #0a0a0a !important;
}
#addresses .address {
	background-color: #f7f7f7 !important;
	border-color: #e4e4e4 !important;
	font-size: 14px !important;
	line-height: 1.5 !important;
}
.td {
	color: #151515 !important;
	border-color: #e8e8e8 !important;
	font-size: 14px !important;
}
thead .td {
	background-color: #0a0a0a !important;
	color: #ffffff !important;
	font-weight: 700 !important;
	border-color: #0a0a0a !important;
	text-transform: uppercase;
	font-size: 11px !important;
	letter-spacing: 0.06em;
}
table.shop_table.order-details {
	border-collapse: collapse !important;
	width: 100% !important;
	margin: 8px 0 30px !important;
}
.order-items .order-table-item-meta {
	font-size: 13px !important;
	color: #4a4a4a !important;
}
.shop_table.order-details td,
.shop_table.order-details th,
.shop_table.order-details .td {
	padding: 12px 10px !important;
}
#body_content_inner h2 a.link,
#body_content_inner h2 a {
	display: inline !important;
	padding: 0 !important;
	background-color: transparent !important;
	border: none !important;
	border-radius: 0 !important;
	color: #0a0a0a !important;
	font-weight: 700 !important;
	text-decoration: underline !important;
	text-transform: none !important;
	letter-spacing: normal !important;
	font-size: inherit !important;
}
#body_content_inner p a.link,
#body_content_inner a.woocommerce-button,
#body_content_inner .woocommerce-email-button-link {
	display: inline-block !important;
	padding: 13px 24px !important;
	background-color: #fff202 !important;
	color: #0a0a0a !important;
	font-weight: 700 !important;
	text-decoration: none !important;
	border-radius: 4px !important;
	border: 2px solid #0a0a0a !important;
	text-transform: uppercase;
	font-size: 13.5px !important;
	letter-spacing: 0.04em;
}
#body_content_inner a:hover {
	text-decoration: underline !important;
}
.mad-email-panel {
	width: 100%;
	border-collapse: collapse;
	margin: 0 0 20px;
	background-color: #f7f7f7;
	border: 1px solid #e4e4e4;
}
.mad-email-panel td {
	padding: 16px 18px !important;
	font-size: 14px !important;
	line-height: 1.5 !important;
	color: #151515 !important;
}
.mad-email-panel strong {
	color: #0a0a0a;
}
.mad-email-status-panel {
	width: 100%;
	border-collapse: collapse;
	margin: 0 0 18px;
	border: 1px solid rgba(255, 242, 2, 0.4);
	background: linear-gradient(180deg, #111111 0%, #0a0a0a 100%);
}
.mad-email-status-panel td {
	padding: 22px 22px !important;
	color: #f6f6f6 !important;
}
.mad-email-kicker {
	margin: 0 0 8px;
	font-size: 11px;
	font-weight: 800;
	letter-spacing: 0.08em;
	text-transform: uppercase;
	color: #fff202;
}
.mad-email-status-title {
	margin: 0;
	font-size: 24px;
	line-height: 1.25;
	font-weight: 800;
	color: #ffffff;
}
.mad-email-status-copy {
	margin: 12px 0 0;
	font-size: 15px;
	line-height: 1.62;
	color: #e8e8e8;
}
.mad-email-badges {
	margin: 16px 0 0;
}
.mad-email-badge {
	display: inline-block;
	margin: 0 6px 6px 0;
	padding: 6px 10px;
	background-color: rgba(255, 242, 2, 0.14);
	border: 1px solid rgba(255, 242, 2, 0.35);
	color: #fff7a8;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.05em;
	text-transform: uppercase;
}
.mad-email-dark-panel {
	width: 100%;
	border-collapse: collapse;
	margin: 0 0 18px;
	background: #111111;
	border: 1px solid rgba(255, 242, 2, 0.2);
}
.mad-email-dark-panel td {
	padding: 18px 18px !important;
	color: #f0f0f0 !important;
}
.mad-email-panel-title {
	margin: 0 0 10px;
	font-size: 12px;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: #fff202;
}
.mad-email-checklist {
	margin: 0;
	padding: 0;
	list-style: none;
}
.mad-email-checklist li {
	margin: 0 0 9px;
	padding-left: 18px;
	position: relative;
	color: #f0f0f0;
	font-size: 14px;
	line-height: 1.5;
}
.mad-email-checklist li::before {
	content: "•";
	position: absolute;
	left: 0;
	top: 0;
	color: #fff202;
	font-weight: 800;
}
.mad-email-cta-buttons {
	margin: 0 0 20px;
}
.mad-email-cta-buttons .woocommerce-button {
	margin: 0 10px 10px 0 !important;
}
.mad-email-secondary-cta {
	background: #ffffff !important;
	color: #0a0a0a !important;
	border-color: #0a0a0a !important;
}
.mad-email-admin-alert {
	background-color: #fffef0 !important;
	border: 2px solid #fff202 !important;
}
.mad-email-cta-row td {
	padding: 8px 0 !important;
}
.mad-email-small {
	font-size: 13px !important;
	color: #3d3d3d !important;
	line-height: 1.5 !important;
}
.mad-email-footer-brand {
	background-color: #0a0a0a !important;
	color: #f2f2f2 !important;
}
.mad-email-footer-brand a {
	color: #fff202 !important;
	font-weight: 600;
}
#template_footer {
	background-color: #0a0a0a !important;
}
#template_footer #credit {
	color: #c8c8c8 !important;
	padding: 20px 16px 28px !important;
	font-size: 12px !important;
	line-height: 1.55 !important;
}
#template_footer #credit,
#template_footer #credit p,
#template_footer #credit a {
	color: #c8c8c8 !important;
}
@media screen and (max-width: 600px) {
	#body_content_inner {
		font-size: 15px !important;
		line-height: 1.58 !important;
	}
	#body_content table > tbody > tr > td {
		padding: 18px 16px !important;
	}
	.mad-email-status-panel td {
		padding: 18px 16px !important;
	}
	.mad-email-status-title {
		font-size: 21px;
	}
	.mad-email-cta-buttons .woocommerce-button {
		display: block !important;
		margin: 0 0 10px 0 !important;
		text-align: center !important;
	}
	#header_wrapper {
		padding: 20px !important;
	}
	thead .td {
		font-size: 10px !important;
	}
}
';

	return $css;
}
add_filter('woocommerce_email_styles', 'mad_baits_wc_email_styles_append', 999, 2);

/**
 * Highlights for admin New order email only.
 *
 * @param WC_Order         $order         Order.
 * @param bool             $sent_to_admin Admin recipient.
 * @param bool             $plain_text    Plain email.
 * @param WC_Email|null    $email         Email instance.
 *
 * @return void
 */
function mad_baits_wc_email_admin_new_order_highlights($order, $sent_to_admin, $plain_text, $email) {
	if ($plain_text || ! $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email) {
		return;
	}
	if ('new_order' !== $email->id) {
		return;
	}

	$details     = function_exists('mad_baits_get_contact_details') ? mad_baits_get_contact_details() : array();
	$admin_url   = mad_baits_wc_email_admin_order_edit_url($order);
	$ship_method = $order->get_shipping_method();
	$pay_title   = $order->get_payment_method_title();
	$cnote       = $order->get_customer_note();

	?>
	<table role="presentation" class="mad-email-panel mad-email-admin-alert" cellpadding="0" cellspacing="0" border="0" width="100%">
		<tr>
			<td>
				<p style="margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:0.08em;font-weight:800;color:#0a0a0a;">
					<?php echo esc_html__('New order — action this in admin', 'mad-baits'); ?>
				</p>
				<p style="margin:0 0 6px;"><strong><?php echo esc_html__('Order', 'mad-baits'); ?>:</strong>
					#<?php echo esc_html((string) $order->get_order_number()); ?> — <?php echo esc_html(wp_strip_all_tags($order->get_formatted_billing_full_name())); ?>
				</p>
				<p style="margin:0 0 6px;"><strong><?php echo esc_html__('Email', 'mad-baits'); ?>:</strong>
					<a href="mailto:<?php echo esc_attr($order->get_billing_email()); ?>"><?php echo esc_html($order->get_billing_email()); ?></a>
					<?php if ($order->get_billing_phone()) : ?>
						&nbsp;·&nbsp; <strong><?php echo esc_html__('Phone', 'mad-baits'); ?>:</strong>
						<?php echo wp_kses_post(wc_make_phone_clickable($order->get_billing_phone())); ?>
					<?php endif; ?>
				</p>
				<p style="margin:0 0 6px;"><strong><?php echo esc_html__('Payment', 'mad-baits'); ?>:</strong>
					<?php echo $pay_title ? esc_html($pay_title) : esc_html__('N/A', 'mad-baits'); ?>
					&nbsp;·&nbsp; <strong><?php echo esc_html__('Total', 'mad-baits'); ?>:</strong>
					<?php echo wp_kses_post($order->get_formatted_order_total()); ?>
				</p>
				<?php if ($ship_method) : ?>
					<p style="margin:0 0 6px;"><strong><?php echo esc_html__('Shipping method', 'mad-baits'); ?>:</strong>
						<?php echo esc_html($ship_method); ?>
					</p>
				<?php endif; ?>
				<?php if ($cnote) : ?>
					<p style="margin:10px 0 0;padding-top:10px;border-top:1px solid rgba(10,10,10,0.12);">
						<strong><?php echo esc_html__('Customer note', 'mad-baits'); ?>:</strong><br />
						<?php echo esc_html(wptexturize($cnote)); ?>
					</p>
				<?php endif; ?>
				<?php if ('' !== $admin_url) : ?>
				<p style="margin:14px 0 0;">
					<a class="woocommerce-button mad-email-admin-edit" href="<?php echo esc_url($admin_url); ?>" style="background-color:#fff202;color:#0a0a0a;display:inline-block;padding:12px 20px;text-decoration:none;font-weight:800;text-transform:uppercase;font-size:12px;border-radius:4px;border:2px solid #0a0a0a;">
						<?php echo esc_html__('View order in admin', 'mad-baits'); ?>
					</a>
				</p>
				<?php endif; ?>
				<p class="mad-email-small" style="margin:12px 0 0;color:#444;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s store phone */
							__('Warehouse / support: %s', 'mad-baits'),
							isset($details['phone_display']) ? (string) $details['phone_display'] : ''
						)
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}
add_action('woocommerce_email_before_order_table', 'mad_baits_wc_email_admin_new_order_highlights', 5, 4);

/**
 * Customer-facing reassurance before order table (not plain text).
 *
 * @param WC_Order      $order         Order.
 * @param bool          $sent_to_admin Admin recipient.
 * @param bool          $plain_text    Plain email.
 * @param WC_Email|null $email         Email instance.
 *
 * @return void
 */
function mad_baits_wc_email_customer_intro_before_table($order, $sent_to_admin, $plain_text, $email) {
	if ($plain_text || $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email) {
		return;
	}

	$defs = mad_baits_wc_email_customer_intro_copy($email->id);
	if (null === $defs) {
		return;
	}

	$thank = isset($defs['thank']) ? (string) $defs['thank'] : '';
	$body  = isset($defs['body']) ? (string) $defs['body'] : '';
	if ('' !== $thank) {
		printf('<p style="margin-top:0;font-size:16px;line-height:1.45;"><strong>%s</strong></p>', esc_html($thank));
	}
	if ('' !== $body) {
		printf('<p class="mad-email-small" style="margin-top:8px;color:#2a2a2a;">%s</p>', esc_html($body));
	}
}

/**
 * Map WC email IDs to messaging.
 *
 * @param string $id Email ID.
 * @return array<string, string>|null
 */
function mad_baits_wc_email_customer_intro_copy($id) {
	static $defs = null;

	if (null === $defs) {
		$defs = array(
			'customer_processing_order'  => array(
				'thank' => __("Thanks — we've got your order.", 'mad-baits'),
				'body'  => __("Your payment is confirmed and the Mad Baits team is preparing everything for dispatch. You will receive another email when your order ships. Most UK orders dispatch within our standard handling window — we'll move as quickly as stock and demand allow.", 'mad-baits'),
			),
			'customer_on_hold_order'     => array(
				'thank' => __('Your order is temporarily on hold.', 'mad-baits'),
				'body'  => __('We might be checking payment details or stock for your line-up. Sit tight — if we need anything, we will contact you using the email on this order.', 'mad-baits'),
			),
			'customer_completed_order'   => array(
				'thank' => __('Your order is complete.', 'mad-baits'),
				/* translators: %s shop name */
				'body'  => __("Thanks for choosing Mad Baits. If courier tracking was provided on dispatch, that's the best place to watch progress. Questions? Hit reply or use the contact page on our site.", 'mad-baits'),
			),
			'customer_refunded_order'    => array(
				'thank' => __('Your refund update', 'mad-baits'),
				'body'  => __('This email confirms an adjustment to your order. Depending on your bank, funds can take a few working days to appear. If anything looks wrong, reply to this email and we will sort it.', 'mad-baits'),
			),
			'customer_invoice'           => array(
				'thank' => __('Payment needed for your Mad Baits order', 'mad-baits'),
				'body'  => __('Complete payment using the instructions below so we can get your bait rolling without delay.', 'mad-baits'),
			),
			'customer_note'              => array(
				'thank' => __('Update about your order', 'mad-baits'),
				'body'  => __('We added a note to your order — see below for details.', 'mad-baits'),
			),
			'cancelled_order'            => array(
				'thank' => __('Order cancelled', 'mad-baits'),
				'body'  => __('Your order has been cancelled as noted below. If you did not request this or want to reorder, contact us right away.', 'mad-baits'),
			),
			'failed_order'               => array(
				'thank' => __('Payment issue — action needed', 'mad-baits'),
				'body'  => __('We could not complete payment for your order. No worries — retry checkout or use a different card. Stock is not secured until payment succeeds.', 'mad-baits'),
			),
		);
	}

	if (! isset($defs[ $id ])) {
		return null;
	}

	return apply_filters('mad_baits_wc_email_customer_intro_copy', $defs[ $id ], $id);
}
add_action('woocommerce_email_before_order_table', 'mad_baits_wc_email_customer_intro_before_table', 8, 4);

/**
 * Free shipping shout-out + CTAs + community prompt after order table.
 *
 * @param WC_Order      $order         Order.
 * @param bool          $sent_to_admin Admin.
 * @param bool          $plain_text    Plain.
 * @param WC_Email|null $email         Email.
 *
 * @return void
 */
function mad_baits_wc_email_customer_after_order_table($order, $sent_to_admin, $plain_text, $email) {
	if ($plain_text || $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email) {
		return;
	}

	$order_emails = array(
		'customer_processing_order',
		'customer_completed_order',
		'customer_on_hold_order',
		'customer_refunded_order',
		'customer_invoice',
		'customer_note',
		'cancelled_order',
		'failed_order',
	);
	if (! in_array($email->id, $order_emails, true)) {
		return;
	}

	$shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
	$acct = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');

	?>
	<table role="presentation" class="mad-email-panel" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:8px;">
		<tr>
			<td>
				<p style="margin:0 0 8px;font-weight:800;color:#0a0a0a;font-size:14px;text-transform:uppercase;letter-spacing:0.06em;">
					<?php echo esc_html__('UK shipping note', 'mad-baits'); ?>
				</p>
				<p class="mad-email-small" style="margin:0;">
					<?php echo esc_html(mad_baits_wc_email_free_shipping_sentence()); ?>
				</p>
			</td>
		</tr>
	</table>
	<table role="presentation" class="mad-email-cta-row" cellpadding="0" cellspacing="0" border="0" width="100%">
		<tr>
			<td style="padding-top:12px;text-align:center;">
				<a class="woocommerce-button" href="<?php echo esc_url($shop); ?>">
					<?php echo esc_html__('Continue shopping', 'mad-baits'); ?>
				</a>
			</td>
		</tr>
		<tr>
			<td style="padding-top:10px;text-align:center;">
				<a class="woocommerce-button mad-email-cta-secondary" href="<?php echo esc_url($acct); ?>" style="background-color:#ffffff !important;color:#0a0a0a !important;border:2px solid #0a0a0a !important;">
					<?php echo esc_html__('My account', 'mad-baits'); ?>
				</a>
			</td>
		</tr>
	</table>
	<?php
	$social = mad_baits_wc_email_social_links();
	if (! empty($social)) {
		$links = array();
		foreach ($social as $row) {
			$links[] = '<a href="' . esc_url($row['href']) . '">' . esc_html($row['label']) . '</a>';
		}
		?>
		<p class="mad-email-small" style="margin:18px 0 0;padding-top:14px;border-top:1px solid #ececec;color:#333;">
			<?php echo esc_html__('Bankside proof: tag us in your catches — we feature anglers from the community.', 'mad-baits'); ?>
			<br />
			<?php echo wp_kses_post(implode(' · ', $links)); ?>
		</p>
		<?php
	} else {
		?>
		<p class="mad-email-small" style="margin:18px 0 0;padding-top:14px;border-top:1px solid #ececec;color:#333;">
			<?php echo esc_html__('Land a campaign fish? Share the story — we love seeing Mad Baits on the bank.', 'mad-baits'); ?>
		</p>
		<?php
	}
}
add_action('woocommerce_email_after_order_table', 'mad_baits_wc_email_customer_after_order_table', 20, 4);
