<?php
/**
 * My Account navigation (Mad Baits) — single menu output.
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 9.3.0
 */

defined('ABSPATH') || exit;

if (! is_user_logged_in()) {
	return;
}

do_action('woocommerce_before_account_navigation');

$endpoint_icons = array(
	'dashboard'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8"></rect><rect x="13" y="3" width="8" height="5"></rect><rect x="13" y="10" width="8" height="11"></rect><rect x="3" y="13" width="8" height="8"></rect></svg>',
	'orders'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h10"></path></svg>',
	'downloads'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11"></path><path d="M8 10.5 12 14.5 16 10.5"></path><path d="M4 20h16"></path></svg>',
	'edit-address'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>',
	'edit-account'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M5 20a7 7 0 0 1 14 0"></path></svg>',
	'payment-methods' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2.5"></rect><path d="M2.5 10h19"></path></svg>',
	'customer-logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 16l4-4-4-4"></path><path d="M19 12H9"></path><path d="M12 19H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h7"></path></svg>',
);
$svg_allowed = array(
	'svg'    => array(
		'viewbox'          => true,
		'fill'             => true,
		'stroke'           => true,
		'stroke-width'     => true,
		'stroke-linecap'   => true,
		'stroke-linejoin'  => true,
	),
	'path'   => array('d' => true),
	'rect'   => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true),
	'circle' => array('cx' => true, 'cy' => true, 'r' => true),
);
?>
<nav class="woocommerce-MyAccount-navigation mad-myaccount-nav" aria-label="<?php esc_attr_e('Account pages', 'woocommerce'); ?>">
	<ul>
		<?php foreach (wc_get_account_menu_items() as $endpoint => $label) : ?>
			<?php
			$li_classes = function_exists('wc_get_account_menu_item_classes') ? wc_get_account_menu_item_classes($endpoint) : '';
			$li_classes = is_string($li_classes) ? $li_classes : '';
			$icon_markup = isset($endpoint_icons[ $endpoint ]) ? (string) $endpoint_icons[ $endpoint ] : $endpoint_icons['dashboard'];
			?>
			<li class="<?php echo esc_attr($li_classes); ?>">
				<a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>">
					<span class="mad-myaccount-nav__icon" aria-hidden="true"><?php echo wp_kses($icon_markup, $svg_allowed); ?></span>
					<span class="mad-myaccount-nav__label"><?php echo esc_html($label); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
<?php
do_action('woocommerce_after_account_navigation');
