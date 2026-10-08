<?php
/**
 * Variable product add to cart.
 *
 * Theme override keeps WooCommerce field names and only adds visual option groups.
 *
 * @package MadBaits
 * @version 9.6.0
 */

defined('ABSPATH') || exit;

global $product;

$attribute_keys  = array_keys($attributes);
$variations_json = wp_json_encode($available_variations);
$variations_attr = function_exists('wc_esc_json') ? wc_esc_json($variations_json) : _wp_specialchars($variations_json, ENT_QUOTES, 'UTF-8', true);
$option_count    = count($attributes);
$stack_class     = $option_count > 0 && $option_count <= 2 ? ' mad-pdp-options--stack' : '';
$current_group   = '';

do_action('woocommerce_before_add_to_cart_form');
?>

<form class="variations_form cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint($product->get_id()); ?>" data-product_variations="<?php echo $variations_attr; // WPCS: XSS ok. ?>">
	<?php do_action('woocommerce_before_variations_form'); ?>

	<?php if (empty($available_variations) && false !== $available_variations) : ?>
		<p class="stock out-of-stock"><?php echo esc_html(apply_filters('woocommerce_out_of_stock_message', __('This product is currently out of stock and unavailable.', 'woocommerce'))); ?></p>
	<?php else : ?>
		<table class="variations mad-pdp-options<?php echo esc_attr($stack_class); ?>" cellspacing="0" role="presentation">
			<tbody>
				<?php foreach ($attributes as $attribute_name => $options) : ?>
					<?php
					$attribute_label = wc_attribute_label($attribute_name);
					$group_label     = function_exists('mad_baits_pdp_variation_group_label') ? mad_baits_pdp_variation_group_label($attribute_label) : '';
					if ('' !== $group_label && $group_label !== $current_group) :
						$current_group = $group_label;
						?>
						<tr class="mad-pdp-option-group">
							<th colspan="2" scope="colgroup"><?php echo esc_html($group_label); ?></th>
						</tr>
					<?php endif; ?>
					<tr>
						<th class="label"><label for="<?php echo esc_attr(sanitize_title($attribute_name)); ?>"><?php echo wp_kses_post($attribute_label); ?></label></th>
						<td class="value">
							<?php
							wc_dropdown_variation_attribute_options(
								array(
									'options'   => $options,
									'attribute' => $attribute_name,
									'product'   => $product,
								)
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		echo wp_kses_post(
			apply_filters(
				'woocommerce_reset_variations_link',
				'<a class="reset_variations mad-pdp-clear" href="#" aria-label="' . esc_attr__('Clear options', 'woocommerce') . '">' . esc_html__('Clear selections', 'mad-baits') . '</a>'
			)
		);
		?>
		<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>
		<?php do_action('woocommerce_after_variations_table'); ?>

		<div class="single_variation_wrap">
			<?php
			do_action('woocommerce_before_single_variation');
			do_action('woocommerce_single_variation');
			do_action('woocommerce_after_single_variation');
			?>
		</div>
	<?php endif; ?>

	<?php do_action('woocommerce_after_variations_form'); ?>
</form>

<?php
do_action('woocommerce_after_add_to_cart_form');
