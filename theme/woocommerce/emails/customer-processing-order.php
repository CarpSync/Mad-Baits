<?php
/**
 * Customer processing order email (Mad Baits override).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (! defined('ABSPATH')) {
	exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled('email_improvements');
$first_name                 = trim((string) $order->get_billing_first_name());
$order_number               = (string) $order->get_order_number();
$order_date                 = $order->get_date_created() ? wc_format_datetime($order->get_date_created()) : '';
$account_url                = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
$shop_url                   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');

do_action('woocommerce_email_header', $email_heading, $email);
?>

<table role="presentation" class="mad-email-status-panel" cellpadding="0" cellspacing="0" border="0" width="100%">
	<tr>
		<td>
			<p class="mad-email-kicker"><?php esc_html_e('Order confirmed', 'mad-baits'); ?></p>
			<p class="mad-email-status-title"><?php esc_html_e('Order locked in. We are on it.', 'mad-baits'); ?></p>
			<p class="mad-email-status-copy">
				<?php
				if ('' !== $first_name) {
					printf(
						/* translators: 1: customer first name 2: order number */
						esc_html__('Hi %1$s, order #%2$s is confirmed. Your bait is now in prep with the team.', 'mad-baits'),
						esc_html($first_name),
						esc_html($order_number)
					);
				} else {
					printf(
						/* translators: %s: order number */
						esc_html__('Order #%s is confirmed. Your bait is now in prep with the team.', 'mad-baits'),
						esc_html($order_number)
					);
				}
				?>
			</p>
			<?php if ('' !== $order_date) : ?>
				<p class="mad-email-status-copy" style="margin-top:6px;">
					<strong><?php esc_html_e('Order date:', 'mad-baits'); ?></strong> <?php echo esc_html($order_date); ?>
				</p>
			<?php endif; ?>
			<p class="mad-email-badges">
				<span class="mad-email-badge"><?php esc_html_e('Secure checkout', 'mad-baits'); ?></span>
				<span class="mad-email-badge"><?php esc_html_e('Fast dispatch', 'mad-baits'); ?></span>
				<span class="mad-email-badge"><?php esc_html_e('Fresh bait packed daily', 'mad-baits'); ?></span>
			</p>
		</td>
	</tr>
</table>

<table role="presentation" class="mad-email-dark-panel" cellpadding="0" cellspacing="0" border="0" width="100%">
	<tr>
		<td>
			<p class="mad-email-panel-title"><?php esc_html_e('Next steps', 'mad-baits'); ?></p>
			<ul class="mad-email-checklist">
				<li><?php esc_html_e('Picked and packed by the Mad Baits team.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('You will get another update as soon as it is completed.', 'mad-baits'); ?></li>
				<li><?php esc_html_e('Need a hand? Reply to this email and we will sort it.', 'mad-baits'); ?></li>
			</ul>
		</td>
	</tr>
</table>

<p class="mad-email-cta-buttons">
	<a class="woocommerce-button" href="<?php echo esc_url($account_url); ?>">
		<?php esc_html_e('View account', 'mad-baits'); ?>
	</a>
	<a class="woocommerce-button mad-email-secondary-cta" href="<?php echo esc_url($shop_url); ?>">
		<?php esc_html_e('Top up your bait', 'mad-baits'); ?>
	</a>
</p>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<p><?php esc_html_e('Order summary:', 'mad-baits'); ?></p>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

if ($additional_content) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">' : '';
	echo wp_kses_post(wpautop(wptexturize($additional_content)));
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

do_action('woocommerce_email_footer', $email);
