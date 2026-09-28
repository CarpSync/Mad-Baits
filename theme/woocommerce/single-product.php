<?php
/**
 * WooCommerce Single Product
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 1.6.4
 */

defined('ABSPATH') || exit;

get_header('shop');

do_action('woocommerce_before_main_content');

?>
<section class="section section--product-flow mad-pdp-shell">
<div class="container mad-product-page">
<section class="mb-woo-breadcrumb-strip">
	<div class="container">
		<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
			<?php mad_baits_render_woo_breadcrumbs('mb-woo-breadcrumbs'); ?>
		<?php endif; ?>
	</div>
</section>
<?php
if (function_exists('mad_baits_dedupe_woocommerce_notices')) {
	mad_baits_dedupe_woocommerce_notices();
}
if (function_exists('woocommerce_output_all_notices')) {
	echo '<div class="mad-pdp-notices" aria-live="polite">';
	woocommerce_output_all_notices();
	echo '</div>';
}

while (have_posts()) :
	the_post();
	wc_get_template_part('content', 'single-product');
endwhile;

do_action('woocommerce_after_main_content');

?>
</div>
</section>
<?php

get_footer('shop');
