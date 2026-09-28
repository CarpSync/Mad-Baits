<?php
/**
 * Product card loop item (shop archives).
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 9.4.0
 */

defined('ABSPATH') || exit;

global $product;

if (! is_a($product, WC_Product::class) || ! $product->is_visible()) {
	return;
}

$enable_compact = function_exists('mad_baits_enable_compact_card_variations')
	? mad_baits_enable_compact_card_variations()
	: false;
?>
<li <?php wc_product_class('mad-product-loop-item', $product); ?>>
	<?php
	if (function_exists('mad_baits_render_product_card')) {
		mad_baits_render_product_card($product->get_id(), $enable_compact, 'archive');
	}
	?>
</li>
