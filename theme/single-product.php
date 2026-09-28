<?php
/**
 * Single Product bridge template.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/*
 * Hard-route product rendering through Woo template override path so edits in
 * /woocommerce/*.php are always the live source of truth.
 */
$woo_single_template = get_theme_file_path('woocommerce/single-product.php');
if (is_string($woo_single_template) && '' !== $woo_single_template && file_exists($woo_single_template)) {
	require $woo_single_template;
	return;
}

if (function_exists('wc_get_template_part')) {
	get_header('shop');
	do_action('woocommerce_before_main_content');
	while (have_posts()) :
		the_post();
		wc_get_template_part('content', 'single-product');
	endwhile;
	do_action('woocommerce_after_main_content');
	get_footer('shop');
	return;
}

get_header();
while (have_posts()) :
	the_post();
	the_content();
endwhile;
get_footer();
