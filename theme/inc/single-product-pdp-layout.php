<?php
/**
 * Single product page layout — badges, bulk pricing, gallery, summary structure.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Whether the current/specified product uses the Mad bundle builder PDP.
 *
 * @param WC_Product|int|null $product Product object or ID.
 * @return bool
 */
function mad_baits_pdp_resolve_product_id($product = null) {
	if (null === $product) {
		global $product;
	}

	if ($product instanceof WC_Product) {
		$product_id = $product->get_id();
	} else {
		$product_id = absint($product);
	}

	if ($product_id < 1 && function_exists('is_product') && is_product()) {
		$product_id = get_queried_object_id();
	}

	return $product_id;
}

/**
 * Fallback bundle detection when MBBB_Plugin is not loaded yet.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function mad_baits_pdp_has_bundle_builder_slots($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return false;
	}

	$enabled_meta = (string) get_post_meta($product_id, '_mbbb_enabled', true);
	if ('no' === $enabled_meta) {
		return false;
	}

	$slots = get_post_meta($product_id, '_mbbb_slots', true);
	if (empty($slots) || ! is_array($slots)) {
		return false;
	}

	if ('yes' === $enabled_meta) {
		return true;
	}

	$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
	$cat_slugs = is_array($cat_slugs) && ! is_wp_error($cat_slugs) ? array_map('sanitize_title', $cat_slugs) : array();

	return in_array('bundles-deals', $cat_slugs, true)
		|| in_array('bundle-deals', $cat_slugs, true);
}

function mad_baits_is_bundle_builder_product($product = null) {
	$product_id = mad_baits_pdp_resolve_product_id($product);
	if ($product_id < 1) {
		return false;
	}

	if (class_exists('MBBB_Plugin')) {
		$plugin = MBBB_Plugin::instance();
		if ($plugin && method_exists($plugin, 'uses_bundle_builder_ui')) {
			return (bool) $plugin->uses_bundle_builder_ui($product_id);
		}
	}

	return mad_baits_pdp_has_bundle_builder_slots($product_id);
}

/**
 * Whether the current product should use standard (non–bundle) PDP polish.
 *
 * @param WC_Product|int|null $product Product object or ID.
 * @return bool
 */
function mad_baits_is_standard_product_pdp($product = null) {
	if (! function_exists('is_product') || ! is_product()) {
		return false;
	}

	return ! mad_baits_is_bundle_builder_product($product);
}

/**
 * Body class for standard (non–bundle-builder) product PDP styling.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function mad_baits_pdp_body_class($classes) {
	$classes[] = 'mad-pdp-shell';

	if (mad_baits_is_bundle_builder_product()) {
		$classes[] = 'mbbb-product';
		$classes[] = 'mad-bundle-builder-pdp';

		return $classes;
	}

	if (mad_baits_is_standard_product_pdp()) {
		$classes[] = 'mad-standard-product-page';
	}

	return $classes;
}
add_filter('body_class', 'mad_baits_pdp_body_class', 20);

/**
 * Configure WooCommerce single product summary hooks.
 *
 * @return void
 */
function mad_baits_configure_single_product_layout_hooks() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);

	if (mad_baits_is_bundle_builder_product()) {
		mad_baits_configure_bundle_product_layout_hooks();
		return;
	}

	mad_baits_configure_standard_product_layout_hooks();
}
add_action('wp', 'mad_baits_configure_single_product_layout_hooks', 25);

/**
 * Bundle builder — minimal theme markup; plugin owns builder UI.
 *
 * @return void
 */
function mad_baits_configure_bundle_product_layout_hooks() {
	add_action('woocommerce_single_product_summary', 'mad_baits_pdp_summary_open', 1);
	add_action('woocommerce_single_product_summary', 'mad_baits_pdp_bundle_summary_close_before_cart', 28);
}

/**
 * Standard product PDP hooks.
 *
 * @return void
 */
function mad_baits_configure_standard_product_layout_hooks() {
	add_action('woocommerce_single_product_summary', 'mad_baits_pdp_summary_open', 1);
	add_action('woocommerce_single_product_summary', 'mad_baits_render_pdp_range_badge', 4);
	add_action('woocommerce_single_product_summary', 'mad_baits_render_pdp_detail_chips', 21);
	add_action('woocommerce_single_product_summary', 'mad_baits_render_pdp_trust_row', 36);

	add_action('woocommerce_before_add_to_cart_button', 'mad_baits_render_pdp_bulk_pricing_panel', 3);
	add_action('woocommerce_after_add_to_cart_button', 'mad_baits_pdp_express_checkout_open', 5);
	add_action('woocommerce_after_add_to_cart_form', 'mad_baits_pdp_express_checkout_close', 999);
	add_action('woocommerce_single_product_summary', 'mad_baits_pdp_summary_close_at_end', 99);

	add_action('woocommerce_before_single_product_summary', 'mad_baits_pdp_gallery_column_open', 5);
	add_action('woocommerce_before_single_product_summary', 'mad_baits_pdp_gallery_column_close', 21);
	add_action('woocommerce_before_single_product_summary', 'mad_baits_render_pdp_gallery_highlights', 22);
}

/**
 * Open summary inner wrapper.
 *
 * @return void
 */
function mad_baits_pdp_summary_open() {
	echo '<div class="mad-pdp-summary-inner mad-single-product__summary">';
}

/**
 * Close summary inner wrapper.
 *
 * @return void
 */
function mad_baits_pdp_summary_close() {
	echo '</div>';
}

/**
 * Bundle builder PDP: close hero block before the cart form so title/price sit above the builder.
 *
 * @return void
 */
function mad_baits_pdp_bundle_summary_close_before_cart() {
	if (! mad_baits_is_bundle_builder_product()) {
		return;
	}

	mad_baits_pdp_summary_close();
}

/**
 * Close summary inner wrapper at end of summary (non-bundle products only).
 *
 * @return void
 */
function mad_baits_pdp_summary_close_at_end() {
	if (mad_baits_is_bundle_builder_product()) {
		return;
	}

	mad_baits_pdp_summary_close();
}

/**
 * Use high-resolution gallery images on standard product pages only.
 *
 * @param string $size Image size slug.
 * @return string
 */
function mad_baits_pdp_gallery_image_size($size) {
	if (mad_baits_is_bundle_builder_product()) {
		return 'large';
	}

	if (mad_baits_is_standard_product_pdp()) {
		return 'woocommerce_single';
	}

	return $size;
}
add_filter('woocommerce_gallery_image_size', 'mad_baits_pdp_gallery_image_size');

/**
 * @param string $size Image size slug.
 * @return string
 */
function mad_baits_pdp_gallery_full_size($size) {
	if (mad_baits_is_bundle_builder_product()) {
		return 'large';
	}

	if (mad_baits_is_standard_product_pdp()) {
		return 'full';
	}

	return $size;
}
add_filter('woocommerce_gallery_full_size', 'mad_baits_pdp_gallery_full_size');

/**
 * Whether the product is an eligible 5kg boilie for bulk pricing display.
 *
 * @param WC_Product|null $product Product.
 * @return bool
 */
function mad_baits_product_is_5kg_boilie_deal($product) {
	if (! $product instanceof WC_Product) {
		return false;
	}

	if (mad_baits_is_bundle_builder_product($product)) {
		return false;
	}

	$name = strtolower($product->get_name());
	if (false === strpos($name, '5kg') && false === strpos($name, '5 kg')) {
		return false;
	}

	if (false === strpos($name, 'boilie')) {
		return false;
	}

	$exclude_slugs = array('hookbaits', 'hookbait', 'tackle', 'clothing', 'apparel', 'bundles', 'bundle-deals', 'accessories');
	$cat_slugs     = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
	$cat_slugs     = is_array($cat_slugs) && ! is_wp_error($cat_slugs) ? array_map('sanitize_title', $cat_slugs) : array();

	foreach ($exclude_slugs as $slug) {
		if (in_array($slug, $cat_slugs, true)) {
			return false;
		}
	}

	$type_slugs = wp_get_post_terms($product->get_id(), 'product_type', array('fields' => 'slugs'));
	$type_slugs = is_array($type_slugs) && ! is_wp_error($type_slugs) ? $type_slugs : array();
	if (in_array('bundle', $type_slugs, true)) {
		return false;
	}

	return true;
}

/**
 * Human-readable range label for chips.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_pdp_get_range_label($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return '';
	}

	if (function_exists('mad_baits_resolve_product_range_slug')) {
		$slug = mad_baits_resolve_product_range_slug($product_id);
		if ('' !== $slug && function_exists('mad_baits_get_range_tag_catalog')) {
			$catalog = mad_baits_get_range_tag_catalog();
			if (isset($catalog[ $slug ])) {
				return (string) $catalog[ $slug ];
			}
			return ucwords(str_replace('-', ' ', $slug));
		}
	}

	return '';
}

/**
 * Collect clean badge labels (no raw tag dump).
 *
 * @param WC_Product $product Product.
 * @return array<int, string>
 */
function mad_baits_pdp_get_product_badges($product) {
	$badges = array();
	$pid    = (int) $product->get_id();

	$range = mad_baits_pdp_get_range_label($pid);
	if ('' !== $range) {
		$badges[] = $range;
	}

	$cat_slugs = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
	$cat_slugs = is_array($cat_slugs) && ! is_wp_error($cat_slugs) ? array_map('sanitize_title', $cat_slugs) : array();
	$primary_cats = array('boilies', 'boilie', 'hookbaits', 'liquids', 'pellets', 'bundles-deals');
	foreach ($primary_cats as $slug) {
		if (in_array($slug, $cat_slugs, true)) {
			$term = get_term_by('slug', $slug, 'product_cat');
			$badges[] = $term && ! is_wp_error($term) ? $term->name : ucwords(str_replace('-', ' ', $slug));
			break;
		}
	}

	$name_lower = strtolower($product->get_name());
	if (preg_match('/\b5\s*kg\b/i', $product->get_name())) {
		$badges[] = '5kg';
	}

	if (preg_match('/\b(shelf\s*life|freezer)\b/i', $product->get_name(), $matches)) {
		$badges[] = ucwords(strtolower(trim($matches[1])));
	}

	$badges = array_values(array_unique(array_filter(array_map('trim', $badges))));

	return array_slice($badges, 0, 5);
}

/**
 * Range badge above title.
 *
 * @return void
 */
function mad_baits_render_pdp_range_badge() {
	global $product;
	if (! $product instanceof WC_Product) {
		return;
	}

	$range = mad_baits_pdp_get_range_label((int) $product->get_id());
	if ('' === $range) {
		return;
	}
	?>
	<p class="mad-pdp-range-badge"><?php echo esc_html($range); ?></p>
	<?php
}

/**
 * Structured detail chips (label + value).
 *
 * @param WC_Product $product Product.
 * @return array<int, array{label: string, value: string}>
 */
function mad_baits_pdp_get_detail_chips($product) {
	$chips = array();
	$pid   = (int) $product->get_id();

	$range = mad_baits_pdp_get_range_label($pid);
	if ('' !== $range) {
		$chips[] = array(
			'label' => __('Range', 'mad-baits'),
			'value' => $range,
		);
	}

	if (preg_match('/\b5\s*kg\b/i', $product->get_name())) {
		$chips[] = array(
			'label' => __('Weight', 'mad-baits'),
			'value' => '5kg',
		);
	}

	$format = '';
	if (preg_match('/\b(shelf\s*life|freezer)\b/i', $product->get_name(), $matches)) {
		$format = ucwords(strtolower(trim($matches[1])));
	} else {
		$attrs = $product->get_attributes();
		foreach ($attrs as $attr) {
			if (! $attr->get_variation()) {
				continue;
			}
			$name = strtolower($attr->get_name());
			if (false === strpos($name, 'freezer') && false === strpos($name, 'shelf')) {
				continue;
			}
			$options = $attr->get_options();
			if (! empty($options[0])) {
				$term = get_term($options[0]);
				$format = $term && ! is_wp_error($term) ? $term->name : (string) $options[0];
				break;
			}
		}
	}
	if ('' !== $format) {
		$chips[] = array(
			'label' => __('Format', 'mad-baits'),
			'value' => $format,
		);
	}

	$cat_slugs = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
	$cat_slugs = is_array($cat_slugs) && ! is_wp_error($cat_slugs) ? array_map('sanitize_title', $cat_slugs) : array();
	$primary_cats = array('boilies', 'boilie', 'hookbaits', 'liquids', 'pellets');
	foreach ($primary_cats as $slug) {
		if (! in_array($slug, $cat_slugs, true)) {
			continue;
		}
		$term = get_term_by('slug', $slug, 'product_cat');
		$chips[] = array(
			'label' => __('Category', 'mad-baits'),
			'value' => $term && ! is_wp_error($term) ? $term->name : ucwords(str_replace('-', ' ', $slug)),
		);
		break;
	}

	return $chips;
}

/**
 * Detail chips under short description.
 *
 * @return void
 */
function mad_baits_render_pdp_detail_chips() {
	global $product;
	if (! $product instanceof WC_Product) {
		return;
	}

	$chips = mad_baits_pdp_get_detail_chips($product);
	if (empty($chips)) {
		return;
	}
	?>
	<ul class="mad-pdp-chips" aria-label="<?php esc_attr_e('Product details', 'mad-baits'); ?>">
		<?php foreach ($chips as $chip) : ?>
			<li class="mad-pdp-chips__item">
				<span class="mad-pdp-chips__label"><?php echo esc_html((string) $chip['label']); ?></span>
				<span class="mad-pdp-chips__value"><?php echo esc_html((string) $chip['value']); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Open gallery column wrapper (image + highlights stack).
 *
 * @return void
 */
/**
 * Visual group for a variation label. Empty means keep the existing order with no heading.
 *
 * @param string $label Attribute label.
 * @return string
 */
function mad_baits_pdp_variation_group_label($label) {
	$text = strtolower(wp_strip_all_tags((string) $label));
	if ('' === $text) {
		return '';
	}

	if (preg_match('/hook\s*bait|hookbait/', $text)) {
		return __('Choose your hookbaits', 'mad-baits');
	}

	if (preg_match('/liquid|\bdip\b/', $text)) {
		return __('Choose your liquid', 'mad-baits');
	}

	if (false !== strpos($text, 'pellet')) {
		return __('Choose your pellets', 'mad-baits');
	}

	if (preg_match('/boilie|\bsplit\b/', $text)) {
		return __('Choose your boilies', 'mad-baits');
	}

	return '';
}

function mad_baits_pdp_gallery_column_open() {
	echo '<div class="mad-pdp-gallery-col">';
}

/**
 * Close gallery column wrapper.
 *
 * @return void
 */
function mad_baits_pdp_gallery_column_close() {
	echo '</div>';
}

/**
 * Build key feature bullets for the gallery column (derived from product, no new meta).
 *
 * @param WC_Product $product Product.
 * @return array<int, string>
 */
function mad_baits_pdp_get_gallery_highlights($product) {
	if (! $product instanceof WC_Product) {
		return array();
	}

	$highlights = array();
	$short      = (string) $product->get_short_description();

	if ('' !== $short && preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $short, $matches)) {
		foreach ($matches[1] as $item) {
			$text = trim(wp_strip_all_tags((string) $item));
			if (strlen($text) > 2 && strlen($text) < 140) {
				$highlights[] = $text;
			}
		}
	}

	if (empty($highlights) && '' !== $short) {
		$plain = trim(wp_strip_all_tags($short));
		if (strlen($plain) > 12 && strlen($plain) < 220) {
			$sentences = preg_split('/(?<=[.!?])\s+/', $plain);
			if (is_array($sentences)) {
				foreach ($sentences as $sentence) {
					$sentence = trim((string) $sentence);
					if (strlen($sentence) > 8 && strlen($sentence) < 140) {
						$highlights[] = $sentence;
					}
					if (count($highlights) >= 2) {
						break;
					}
				}
			}
		}
	}

	foreach ($product->get_attributes() as $attribute) {
		if ($attribute->get_variation() || ! $attribute->get_visible()) {
			continue;
		}

		$name  = (string) $attribute->get_name();
		$label = wc_attribute_label($name, $product);
		$value = trim((string) $product->get_attribute($name));
		if ('' === $value) {
			continue;
		}

		$line = $label . ': ' . $value;
		if (strlen($line) < 120) {
			$highlights[] = $line;
		}
	}

	$pid       = (int) $product->get_id();
	$cat_slugs = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
	$cat_slugs = is_array($cat_slugs) && ! is_wp_error($cat_slugs) ? array_map('sanitize_title', $cat_slugs) : array();

	$defaults = array();
	if (array_intersect(array('boilies', 'boilie'), $cat_slugs)) {
		$defaults = array(
			__('Premium rolled boilies for long sessions', 'mad-baits'),
			__('High-quality ingredients & proven attractors', 'mad-baits'),
			__('Shelf life and freezer options available', 'mad-baits'),
			__('Mix & match deals on eligible 5kg lines', 'mad-baits'),
		);
	} elseif (in_array('hookbaits', $cat_slugs, true) || in_array('hookbait', $cat_slugs, true)) {
		$defaults = array(
			__('Balanced hookbait presentation', 'mad-baits'),
			__('Pairs with matching boilie ranges', 'mad-baits'),
			__('Session-ready single-hook confidence', 'mad-baits'),
		);
	} elseif (in_array('liquids', $cat_slugs, true)) {
		$defaults = array(
			__('High-attraction liquid boosters', 'mad-baits'),
			__('Ideal for glug, dip and spod workflows', 'mad-baits'),
			__('Designed to complement Mad ranges', 'mad-baits'),
		);
	} elseif (array_intersect(array('pellets', 'particles'), $cat_slugs)) {
		$defaults = array(
			__('Feeder and spod friendly formats', 'mad-baits'),
			__('Consistent breakdown and attraction', 'mad-baits'),
		);
	} else {
		$defaults = array(
			__('Premium Mad Baits quality', 'mad-baits'),
			__('Trusted by UK campaign anglers', 'mad-baits'),
			__('Fast, secure UK dispatch', 'mad-baits'),
		);
	}

	foreach ($defaults as $line) {
		if (count($highlights) >= 5) {
			break;
		}
		if (! in_array($line, $highlights, true)) {
			$highlights[] = $line;
		}
	}

	$range = mad_baits_pdp_get_range_label($pid);
	if ('' !== $range && count($highlights) < 5) {
		array_unshift(
			$highlights,
			sprintf(
				/* translators: %s: product range name */
				__('Part of the %s range', 'mad-baits'),
				$range
			)
		);
	}

	$highlights = array_values(array_unique(array_filter(array_map('trim', $highlights))));

	return array_slice($highlights, 0, 5);
}

/**
 * Key features panel below the product gallery (desktop column filler).
 *
 * @return void
 */
function mad_baits_render_pdp_gallery_highlights() {
	global $product;

	if (! $product instanceof WC_Product) {
		return;
	}

	if (mad_baits_is_bundle_builder_product($product)) {
		return;
	}

	$highlights = mad_baits_pdp_get_gallery_highlights($product);
	if (empty($highlights)) {
		return;
	}
	?>
	<aside class="mad-pdp-gallery-highlights" aria-labelledby="mad-pdp-gallery-highlights-title">
		<p class="mad-pdp-gallery-highlights__kicker"><?php esc_html_e('Why anglers choose it', 'mad-baits'); ?></p>
		<h2 id="mad-pdp-gallery-highlights-title" class="mad-pdp-gallery-highlights__title"><?php esc_html_e('Key features', 'mad-baits'); ?></h2>
		<ul class="mad-pdp-gallery-highlights__list">
			<?php foreach ($highlights as $highlight) : ?>
				<li><?php echo esc_html($highlight); ?></li>
			<?php endforeach; ?>
		</ul>
	</aside>
	<?php
}

/**
 * Open express checkout wrapper (payment buttons render inside).
 *
 * @return void
 */
function mad_baits_pdp_express_checkout_open() {
	if (mad_baits_is_bundle_builder_product()) {
		return;
	}

	echo '<div class="mad-pdp-express"><p class="mad-pdp-express__label"><span>' . esc_html__('or continue with express checkout', 'mad-baits') . '</span></p>';
}

/**
 * Close express checkout wrapper.
 *
 * @return void
 */
function mad_baits_pdp_express_checkout_close() {
	if (mad_baits_is_bundle_builder_product()) {
		return;
	}

	echo '</div>';
}

/**
 * 5kg boilie bulk pricing panel.
 *
 * @return void
 */
function mad_baits_render_pdp_bulk_pricing_panel() {
	global $product;
	if (! $product instanceof WC_Product || ! mad_baits_product_is_5kg_boilie_deal($product)) {
		return;
	}

	$rows = array(
		array(
			'label' => __('1 x 5kg', 'mad-baits'),
			'price' => '£44.95',
		),
		array(
			'label' => __('2 x 5kg', 'mad-baits'),
			'price' => __('£35 each', 'mad-baits'),
		),
		array(
			'label' => __('4 x 5kg', 'mad-baits'),
			'price' => '£33.75 each',
		),
		array(
			'label' => __('10 x 5kg', 'mad-baits'),
			'price' => '£32.25 each',
		),
		array(
			'label' => __('20 x 5kg', 'mad-baits'),
			'price' => '£30 each',
		),
	);
	?>
	<aside class="mad-pdp-bulk-deals" aria-labelledby="mad-pdp-bulk-deals-title">
		<h3 id="mad-pdp-bulk-deals-title" class="mad-pdp-bulk-deals__title"><?php esc_html_e('Mix & Match 5kg Boilie Deals', 'mad-baits'); ?></h3>
		<table class="mad-pdp-bulk-deals__table">
			<tbody>
				<?php foreach ($rows as $row) : ?>
					<tr>
						<th scope="row"><?php echo esc_html((string) $row['label']); ?></th>
						<td><?php echo esc_html((string) $row['price']); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="mad-pdp-bulk-deals__note"><?php esc_html_e('Mix and match eligible 5kg boilies. Discount applies automatically in basket.', 'mad-baits'); ?></p>
	</aside>
	<?php
}

/**
 * Trust row below purchase actions.
 *
 * @return void
 */
function mad_baits_render_pdp_trust_row() {
	?>
	<ul class="mad-pdp-trust" aria-label="<?php esc_attr_e('Purchase reassurance', 'mad-baits'); ?>">
		<li><?php esc_html_e('Fast UK dispatch', 'mad-baits'); ?></li>
		<li><?php esc_html_e('Secure checkout', 'mad-baits'); ?></li>
		<li><?php esc_html_e('Premium Mad Baits quality', 'mad-baits'); ?></li>
	</ul>
	<?php
}
