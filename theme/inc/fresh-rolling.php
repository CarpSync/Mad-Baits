<?php
/**
 * Fresh rolling / dispatch widget.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Editable fresh rolling status data.
 *
 * @return array<string, mixed>
 */
function mad_baits_get_fresh_rolling_status_data() {
	return array(
		// Keep these labels editable for staff updates.
		'currently_rolling' => array('ASBO', 'Nutz Plus', 'Pandemic'),
		'next_dispatch'     => __('Tomorrow', 'mad-baits'),
		'order_cutoff'      => __('12PM for next working day dispatch', 'mad-baits'),
		'message'           => __('Fresh bait rolled to order for peak attraction and consistent campaign confidence.', 'mad-baits'),
	);
}

/**
 * Render fresh rolling widget markup.
 *
 * @param string $context Optional context class suffix.
 * @return string
 */
function mad_baits_get_fresh_rolling_widget_markup($context = 'default') {
	$data    = mad_baits_get_fresh_rolling_status_data();
	$context = sanitize_html_class((string) $context);

	ob_start();
	?>
	<section class="mad-fresh-rolling mad-fresh-rolling--<?php echo esc_attr($context); ?>" aria-label="<?php esc_attr_e('Fresh Rolling and Dispatch', 'mad-baits'); ?>">
		<p class="mad-fresh-rolling__kicker"><?php esc_html_e('Fresh Rolling & Dispatch', 'mad-baits'); ?></p>
		<h3><?php esc_html_e('Fresh bait, campaign-ready timing', 'mad-baits'); ?></h3>
		<p class="mad-fresh-rolling__message"><?php echo esc_html((string) $data['message']); ?></p>
		<ul class="mad-fresh-rolling__list">
			<li>
				<span><?php esc_html_e('Currently rolling', 'mad-baits'); ?></span>
				<strong><?php echo esc_html(implode(' / ', array_map('strval', (array) $data['currently_rolling']))); ?></strong>
			</li>
			<li>
				<span><?php esc_html_e('Next dispatch', 'mad-baits'); ?></span>
				<strong><?php echo esc_html((string) $data['next_dispatch']); ?></strong>
			</li>
			<li>
				<span><?php esc_html_e('Order before', 'mad-baits'); ?></span>
				<strong><?php echo esc_html((string) $data['order_cutoff']); ?></strong>
			</li>
		</ul>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Shortcode handler for fresh rolling widget.
 *
 * @param array<string, mixed> $atts Shortcode attributes.
 * @return string
 */
function mad_baits_fresh_rolling_shortcode($atts = array()) {
	$atts = shortcode_atts(
		array(
			'context' => 'default',
		),
		(array) $atts,
		'mad_baits_fresh_rolling'
	);

	return mad_baits_get_fresh_rolling_widget_markup((string) $atts['context']);
}
add_shortcode('mad_baits_fresh_rolling', 'mad_baits_fresh_rolling_shortcode');

/**
 * Show fresh rolling widget on checkout.
 */
function mad_baits_render_checkout_fresh_rolling_widget() {
	echo mad_baits_get_fresh_rolling_widget_markup('checkout'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('woocommerce_before_checkout_form', 'mad_baits_render_checkout_fresh_rolling_widget', 5);
