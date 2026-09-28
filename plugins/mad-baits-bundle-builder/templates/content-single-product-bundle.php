<?php
/**
 * Dedicated bundle-builder single product content template.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

global $product;

do_action('woocommerce_before_single_product');

if (post_password_required()) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$hero_style = '';
if ($product instanceof WC_Product) {
	$hero_image = wp_get_attachment_image_url($product->get_image_id(), 'large');
	if ($hero_image) {
		$hero_style = '--mbbb-hero-image:url("' . esc_url($hero_image) . '");';
	}
}

$bundle_highlights = array();
if ($product instanceof WC_Product && class_exists('MBBB_Plugin', false)) {
	$slots = MBBB_Plugin::instance()->get_resolved_slots($product->get_id());
	if (is_array($slots) && ! empty($slots)) {
		$boilie_count = 0;
		$hookbait_count = 0;
		$liquid_count = 0;

		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$key   = strtolower((string) ($slot['key'] ?? ''));
			$label = strtolower((string) ($slot['label'] ?? ''));
			$hay   = trim($key . ' ' . $label);
			if ('' === $hay) {
				continue;
			}

			if (false !== strpos($hay, 'split') || false !== strpos($hay, 'boilie')) {
				$boilie_count++;
				continue;
			}
			if (false !== strpos($hay, 'hook')) {
				$hookbait_count++;
				continue;
			}
			if (false !== strpos($hay, 'liquid') || false !== strpos($hay, 'dip')) {
				$liquid_count++;
			}
		}

		if ($boilie_count > 0) {
			$bundle_highlights[] = sprintf(
				/* translators: %d: boilie split count */
				_n('%d Boilie Split', '%d Boilie Splits', $boilie_count, 'mad-baits-bundle-builder'),
				$boilie_count
			);
		}
		if ($hookbait_count > 0) {
			$bundle_highlights[] = sprintf(
				/* translators: %d: hookbait count */
				_n('%d Hookbait', '%d Hookbaits', $hookbait_count, 'mad-baits-bundle-builder'),
				$hookbait_count
			);
		}
		if ($liquid_count > 0) {
			$bundle_highlights[] = sprintf(
				/* translators: %d: liquid count */
				_n('%d Liquid', '%d Liquids', $liquid_count, 'mad-baits-bundle-builder'),
				$liquid_count
			);
		}
	}
}

$add_to_cart_callback = 'woocommerce_template_single_add_to_cart';
$add_to_cart_priority = has_action('woocommerce_single_product_summary', $add_to_cart_callback);
if (false !== $add_to_cart_priority) {
	remove_action('woocommerce_single_product_summary', $add_to_cart_callback, (int) $add_to_cart_priority);
}
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class('mad-bundle-product-template', $product); ?>>
	<section class="mad-bundle-product-template__hero"<?php echo $hero_style ? ' style="' . esc_attr($hero_style) . '"' : ''; ?>>
		<div class="mad-bundle-product-template__media">
			<?php do_action('woocommerce_before_single_product_summary'); ?>
		</div>

		<div class="summary entry-summary mad-bundle-product-template__summary">
			<?php do_action('woocommerce_single_product_summary'); ?>
			<?php if (! empty($bundle_highlights)) : ?>
				<ul class="mad-bundle-product-template__deal-highlights" aria-label="<?php esc_attr_e('Deal highlights', 'mad-baits-bundle-builder'); ?>">
					<?php foreach (array_slice($bundle_highlights, 0, 3) as $highlight) : ?>
						<li><?php echo esc_html((string) $highlight); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<ul class="mad-bundle-product-template__trust-badges" aria-label="<?php esc_attr_e('Buying confidence', 'mad-baits-bundle-builder'); ?>">
				<li><?php esc_html_e('Secure checkout', 'mad-baits-bundle-builder'); ?></li>
				<li><?php esc_html_e('Fast dispatch', 'mad-baits-bundle-builder'); ?></li>
				<li><?php esc_html_e('Fresh bait packed daily', 'mad-baits-bundle-builder'); ?></li>
			</ul>
		</div>
	</section>

	<section class="mad-bundle-product-template__builder" aria-label="<?php esc_attr_e('Bundle builder', 'mad-baits-bundle-builder'); ?>">
		<?php woocommerce_template_single_add_to_cart(); ?>
	</section>

	<section class="mad-bundle-product-template__after">
		<?php do_action('woocommerce_after_single_product_summary'); ?>
	</section>
</div>

<?php
if (false !== $add_to_cart_priority) {
	add_action('woocommerce_single_product_summary', $add_to_cart_callback, (int) $add_to_cart_priority);
}
?>
