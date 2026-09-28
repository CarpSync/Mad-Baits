<?php
/**
 * Checkout payment UI — Stripe light appearance + readable form shell.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Stripe Payment Element appearance (classic + blocks checkout).
 *
 * @return array<string, mixed>
 */
function mad_baits_get_stripe_checkout_appearance() {
	return array(
		'theme'     => 'stripe',
		'variables' => array(
			'colorPrimary'     => '#121212',
			'colorBackground'  => '#ffffff',
			'colorText'        => '#121212',
			'colorTextSecondary' => '#4a4a4a',
			'colorDanger'      => '#c0392b',
			'fontFamily'       => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
			'fontSizeBase'     => '16px',
			'borderRadius'     => '8px',
			'spacingUnit'      => '4px',
		),
		'rules'     => array(
			'.Label'         => array(
				'color' => '#121212',
			),
			'.Input'         => array(
				'backgroundColor' => '#ffffff',
				'border'          => '1px solid #c8c8c8',
				'color'           => '#121212',
				'boxShadow'       => 'none',
			),
			'.Input--invalid' => array(
				'border' => '1px solid #c0392b',
				'color'  => '#121212',
			),
			'.Tab'           => array(
				'border'           => '1px solid #c8c8c8',
				'color'            => '#333333',
				'backgroundColor'  => '#ffffff',
			),
			'.Tab:hover'     => array(
				'color' => '#121212',
			),
			'.Tab--selected' => array(
				'border'          => '2px solid #121212',
				'color'           => '#121212',
				'backgroundColor' => '#f7f7f7',
			),
			'.Block'         => array(
				'backgroundColor' => '#ffffff',
			),
			'.CheckboxLabel' => array(
				'color' => '#121212',
			),
		),
	);
}

/**
 * Force Stripe UPE / Link to render with a light (readable) appearance.
 *
 * @param array<string, mixed> $params Stripe JS params.
 * @return array<string, mixed>
 */
function mad_baits_filter_stripe_upe_params($params) {
	if (! is_array($params)) {
		return $params;
	}

	$appearance = mad_baits_get_stripe_checkout_appearance();

	$params['appearance']       = (object) $appearance;
	$params['blocksAppearance'] = (object) $appearance;

	return $params;
}
add_filter('wc_stripe_upe_params', 'mad_baits_filter_stripe_upe_params', 20);

/**
 * Inline script: light color-scheme on Stripe mount nodes (backup for cached UPE).
 *
 * @return void
 */
function mad_baits_checkout_payment_ui_inline_script() {
	if (! function_exists('mad_baits_should_enqueue_checkout_flow_assets') || ! mad_baits_should_enqueue_checkout_flow_assets()) {
		return;
	}

	if (! wp_script_is('mad-baits-main', 'enqueued')) {
		return;
	}

	wp_add_inline_script(
		'mad-baits-main',
		"(function () {\n"
		. "\tvar selectors = [\n"
		. "\t\t'#wc-stripe-upe-form',\n"
		. "\t\t'.wc-stripe-upe-element',\n"
		. "\t\t'.wc-block-components-payment-method-content',\n"
		. "\t\t'.wc-block-components-radio-control-accordion-content',\n"
		. "\t\t'#payment .payment_box.payment_method_stripe'\n"
		. "\t];\n"
		. "\tfunction paintStripeShell() {\n"
		. "\t\tselectors.forEach(function (sel) {\n"
		. "\t\t\tdocument.querySelectorAll(sel).forEach(function (node) {\n"
		. "\t\t\t\tif (!(node instanceof HTMLElement)) {\n"
		. "\t\t\t\t\treturn;\n"
		. "\t\t\t\t}\n"
		. "\t\t\t\tnode.classList.add('mad-checkout-payment-shell');\n"
		. "\t\t\t\tnode.style.setProperty('color-scheme', 'light', 'important');\n"
		. "\t\t\t\tnode.style.setProperty('background', '#ffffff', 'important');\n"
		. "\t\t\t\tnode.style.setProperty('color', '#121212', 'important');\n"
		. "\t\t\t});\n"
		. "\t\t});\n"
		. "\t}\n"
		. "\tpaintStripeShell();\n"
		. "\tif (document.readyState === 'loading') {\n"
		. "\t\tdocument.addEventListener('DOMContentLoaded', paintStripeShell);\n"
		. "\t}\n"
		. "\tif (typeof MutationObserver !== 'undefined' && document.body) {\n"
		. "\t\tnew MutationObserver(paintStripeShell).observe(document.body, { childList: true, subtree: true });\n"
		. "\t}\n"
		. "\twindow.setTimeout(paintStripeShell, 400);\n"
		. "\twindow.setTimeout(paintStripeShell, 1200);\n"
		. "\twindow.setTimeout(paintStripeShell, 2800);\n"
		. "})();",
		'after'
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_checkout_payment_ui_inline_script', 101);
