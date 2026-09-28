<?php
/**
 * My Account dashboard (Mad Baits).
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 4.4.0
 */

defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$display_name = $current_user instanceof WP_User && $current_user->exists() ? $current_user->display_name : '';

$menu_items = function_exists('wc_get_account_menu_items') ? wc_get_account_menu_items() : array();
$ai_url     = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');
$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));
$orders_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/');
$role_badge = function_exists('mad_baits_get_user_discount_target_role_data') ? mad_baits_get_user_discount_target_role_data($current_user) : array();

$quick_cards = array(
	'orders'       => __('Orders', 'mad-baits'),
	'edit-address' => __('Addresses', 'mad-baits'),
	'edit-account' => __('Account Details', 'mad-baits'),
);

$recent_orders = array();
if (function_exists('wc_get_orders')) {
	$recent_orders = wc_get_orders(
		array(
			'customer' => get_current_user_id(),
			'limit'    => 3,
			'status'   => array('processing', 'on-hold', 'completed', 'pending'),
			'orderby'  => 'date',
			'order'    => 'DESC',
		)
	);
}

$processing_count = 0;
$downloads_count = 0;
$saved_addresses_count = 0;
if (function_exists('wc_get_orders')) {
	$processing_count = count(
		(array) wc_get_orders(
			array(
				'customer' => get_current_user_id(),
				'limit'    => 20,
				'status'   => array('processing', 'on-hold', 'pending'),
				'return'   => 'ids',
			)
		)
	);
}
$recent_total = is_array($recent_orders) ? count($recent_orders) : 0;

if (function_exists('wc_get_customer_available_downloads')) {
	$downloads = wc_get_customer_available_downloads(get_current_user_id());
	$downloads_count = is_array($downloads) ? count($downloads) : 0;
}

if (function_exists('wc_get_account_formatted_address')) {
	$billing_address  = wc_get_account_formatted_address('billing');
	$shipping_address = wc_get_account_formatted_address('shipping');
	if (is_string($billing_address) && '' !== trim(wp_strip_all_tags($billing_address))) {
		$saved_addresses_count++;
	}
	if (is_string($shipping_address) && '' !== trim(wp_strip_all_tags($shipping_address))) {
		$saved_addresses_count++;
	}
}

?>

<section class="mad-account-dashboard mad-account-dashboard--template">
	<div class="mad-account-dashboard__welcome-card">
		<p class="mad-account-dashboard__eyebrow"><?php esc_html_e('Dashboard', 'mad-baits'); ?></p>
		<h2>
			<?php
			printf(
				/* translators: %s: user display name */
				esc_html__('Welcome back, %s', 'mad-baits'),
				esc_html('' !== $display_name ? $display_name : __('angler', 'mad-baits'))
			);
			?>
		</h2>
		<?php if (! empty($role_badge['label'])) : ?>
			<p class="mad-account-dashboard__role-badge-wrap">
				<span class="mad-role-badge mad-account-dashboard__role-badge <?php echo esc_attr(isset($role_badge['badge_class']) ? (string) $role_badge['badge_class'] : ''); ?>">
					<?php
					printf(
						/* translators: %s: customer custom role label */
						esc_html__('Role: %s', 'mad-baits'),
						esc_html((string) $role_badge['label'])
					);
					?>
				</span>
			</p>
		<?php endif; ?>
		<p><?php esc_html_e('Manage orders, addresses, and account details from your Mad Baits customer hub.', 'mad-baits'); ?></p>
	</div>

	<div class="mad-account-dashboard__quick-links" role="list">
		<?php foreach ($quick_cards as $endpoint => $label) : ?>
			<?php if (! isset($menu_items[ $endpoint ])) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<a class="mad-account-dashboard__quick-link" role="listitem" href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>">
				<span class="mad-account-dashboard__quick-link-title"><?php echo esc_html($label); ?></span>
				<span class="mad-account-dashboard__quick-link-arrow" aria-hidden="true">&rarr;</span>
			</a>
		<?php endforeach; ?>

		<a class="mad-account-dashboard__quick-link mad-account-dashboard__quick-link--accent" role="listitem" href="<?php echo esc_url($ai_url); ?>">
			<span class="mad-account-dashboard__quick-link-title"><?php esc_html_e('AI Bait Finder', 'mad-baits'); ?></span>
			<span class="mad-account-dashboard__quick-link-arrow" aria-hidden="true">&rarr;</span>
		</a>
	</div>

	<div class="mad-account-dashboard__cta">
		<p><?php esc_html_e('Need help choosing your next bait line?', 'mad-baits'); ?></p>
		<a class="mad-button" href="<?php echo esc_url($ai_url); ?>"><?php esc_html_e('Try AI Bait Finder', 'mad-baits'); ?></a>
	</div>

	<div class="mad-account-dashboard__status-grid" role="list">
		<article class="mad-account-dashboard__status-card" role="listitem">
			<span class="mad-account-dashboard__status-label"><?php esc_html_e('Orders', 'mad-baits'); ?></span>
			<strong class="mad-account-dashboard__status-value"><?php echo esc_html((string) $recent_total); ?></strong>
		</article>
		<article class="mad-account-dashboard__status-card" role="listitem">
			<span class="mad-account-dashboard__status-label"><?php esc_html_e('Active orders', 'mad-baits'); ?></span>
			<strong class="mad-account-dashboard__status-value"><?php echo esc_html((string) $processing_count); ?></strong>
		</article>
		<article class="mad-account-dashboard__status-card" role="listitem">
			<span class="mad-account-dashboard__status-label"><?php esc_html_e('Downloads', 'mad-baits'); ?></span>
			<strong class="mad-account-dashboard__status-value"><?php echo esc_html((string) $downloads_count); ?></strong>
		</article>
		<article class="mad-account-dashboard__status-card" role="listitem">
			<span class="mad-account-dashboard__status-label"><?php esc_html_e('Saved addresses', 'mad-baits'); ?></span>
			<strong class="mad-account-dashboard__status-value"><?php echo esc_html((string) $saved_addresses_count); ?></strong>
		</article>
	</div>

	<aside class="mad-account-dashboard__next-steps">
		<h3><?php esc_html_e('Recommended Next Steps', 'mad-baits'); ?></h3>
		<p><?php esc_html_e('Keep your campaign momentum moving with these quick actions.', 'mad-baits'); ?></p>
		<div class="mad-account-dashboard__next-links">
			<a href="<?php echo esc_url($ai_url); ?>"><?php esc_html_e('Try AI Bait Finder', 'mad-baits'); ?></a>
			<a href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Shop Bundle Deals', 'mad-baits'); ?></a>
			<a href="<?php echo esc_url($orders_url); ?>"><?php esc_html_e('View Orders', 'mad-baits'); ?></a>
		</div>
	</aside>

	<?php if (! empty($recent_orders)) : ?>
		<div class="mad-account-dashboard__recent">
			<h3 class="mad-account-dashboard__recent-title"><?php esc_html_e('Recent orders', 'mad-baits'); ?></h3>
			<ul class="mad-account-dashboard__recent-list">
				<?php foreach ($recent_orders as $order) : ?>
					<?php
					if (! $order instanceof WC_Order) {
						continue;
					}
					$view_url = $order->get_view_order_url();
					?>
					<li>
						<a href="<?php echo esc_url($view_url); ?>">
							<span class="mad-account-dashboard__recent-order"><?php echo esc_html('#' . $order->get_order_number()); ?></span>
							<span class="mad-account-dashboard__recent-meta">
								<?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>
								&middot;
								<?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
							</span>
							<span class="mad-account-dashboard__recent-total"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<a class="text-link mad-account-dashboard__recent-all" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">
				<?php esc_html_e('View all orders', 'mad-baits'); ?>
			</a>
		</div>
	<?php endif; ?>
</section>

<?php
/**
 * Allow plugins to append to the dashboard after Mad Baits layout.
 */
do_action('woocommerce_account_dashboard');
