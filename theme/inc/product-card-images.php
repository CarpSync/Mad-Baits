<?php
/**
 * High-quality responsive images for Mad Baits product cards.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register product card image sizes.
 */
function mad_baits_register_product_card_image_sizes() {
	add_image_size('mad_product_card', 800, 800, true);
	add_image_size('mad_product_card_wide', 1000, 800, true);
}
add_action('after_setup_theme', 'mad_baits_register_product_card_image_sizes', 20);

/**
 * Recommended WooCommerce catalog thumbnail dimensions (requires thumbnail regen).
 *
 * @param array<string, int|bool> $size Size array.
 * @return array<string, int|bool>
 */
function mad_baits_filter_woocommerce_thumbnail_size($size) {
	return array(
		'width'  => 800,
		'height' => 800,
		'crop'   => 1,
	);
}
add_filter('woocommerce_get_image_size_woocommerce_thumbnail', 'mad_baits_filter_woocommerce_thumbnail_size');
add_filter('woocommerce_get_image_size_thumbnail', 'mad_baits_filter_woocommerce_thumbnail_size');

/**
 * Image size slug used on product cards site-wide.
 *
 * @return string
 */
function mad_baits_get_product_card_image_size() {
	return 'mad_product_card';
}

/**
 * Responsive sizes attribute for product card grids (retina-safe).
 *
 * @return string
 */
function mad_baits_get_product_card_image_sizes_attr() {
	return '(max-width: 480px) 50vw, (max-width: 768px) 33vw, (max-width: 1200px) 25vw, 420px';
}

/**
 * Branded logo URL used when a product has no image.
 *
 * @return string
 */
function mad_baits_get_branded_placeholder_image_url() {
	$path = get_theme_file_path('/assets/img/CUP-LOGOv2-1.svg');
	if (is_string($path) && file_exists($path)) {
		return get_theme_file_uri('/assets/img/CUP-LOGOv2-1.svg');
	}

	return function_exists('wc_placeholder_img_src') ? (string) wc_placeholder_img_src() : '';
}

/**
 * Resolve a fallback attachment ID for products without a featured image.
 * Tries category thumbnail, then product_tag (range) thumbnail.
 *
 * @param int $product_id Product ID.
 * @return int Attachment ID or 0.
 */
function mad_baits_get_product_fallback_image_id($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return 0;
	}

	foreach (array('product_cat', 'product_tag') as $taxonomy) {
		$terms = get_the_terms($product_id, $taxonomy);
		if (! is_array($terms) || is_wp_error($terms)) {
			continue;
		}
		foreach ($terms as $term) {
			$thumb_id = (int) get_term_meta((int) $term->term_id, 'thumbnail_id', true);
			if ($thumb_id > 0) {
				return $thumb_id;
			}
		}
	}

	return 0;
}

/**
 * Best available image attachment for a product (featured → category/range → 0).
 *
 * @param int $product_id Product ID.
 * @return int
 */
function mad_baits_get_product_display_image_id($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('wc_get_product')) {
		return 0;
	}

	$product = wc_get_product($product_id);
	if (! $product) {
		return 0;
	}

	$thumbnail_id = (int) $product->get_image_id();
	if ($thumbnail_id > 0) {
		return $thumbnail_id;
	}

	// Variable products: try first variation image.
	if ($product->is_type('variable') && method_exists($product, 'get_children')) {
		foreach ((array) $product->get_children() as $child_id) {
			$child = wc_get_product($child_id);
			if ($child && (int) $child->get_image_id() > 0) {
				return (int) $child->get_image_id();
			}
		}
	}

	return mad_baits_get_product_fallback_image_id($product_id);
}

/**
 * Range hero image URL when a product has no featured image.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_get_product_range_fallback_image_url($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('mad_baits_get_range_brand_profiles') || ! function_exists('mad_baits_resolve_context_hero_image')) {
		return '';
	}

	$tag_slugs = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
	if (! is_array($tag_slugs) || is_wp_error($tag_slugs)) {
		$tag_slugs = array();
	}
	$tag_slugs = array_map('sanitize_title', array_filter(array_map('strval', $tag_slugs)));
	if (empty($tag_slugs)) {
		return '';
	}

	foreach (mad_baits_get_range_brand_profiles() as $profile_slug => $profile) {
		$aliases = array_merge(
			array(sanitize_title((string) $profile_slug)),
			array_map('sanitize_title', array_map('strval', (array) ($profile['aliases'] ?? array())))
		);
		if (! array_intersect($aliases, $tag_slugs)) {
			continue;
		}

		$hero = is_array($profile['hero'] ?? null) ? $profile['hero'] : array();
		$url  = mad_baits_resolve_context_hero_image(
			array(
				'post_id'             => $product_id,
				'candidate_filenames' => (array) ($hero['candidates'] ?? array()),
				'product_cat_slugs'   => (array) ($hero['hero_slugs'] ?? array($profile_slug)),
				'semantic_terms'      => (array) ($hero['semantic_terms'] ?? array()),
			)
		);

		return is_string($url) ? $url : '';
	}

	return '';
}

/**
 * Replace WooCommerce grey "Awaiting product image" with Mad Baits branding.
 *
 * @param string $src  Placeholder src.
 * @param string $size Size slug.
 * @return string
 */
function mad_baits_filter_placeholder_img_src($src, $size = '') { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$url = mad_baits_get_branded_placeholder_image_url();
	return $url ? $url : $src;
}
add_filter('woocommerce_placeholder_img_src', 'mad_baits_filter_placeholder_img_src', 10, 2);

/**
 * @param string $image_html Placeholder HTML.
 * @param string $size       Size.
 * @param array  $dimensions Dimensions.
 * @return string
 */
function mad_baits_filter_placeholder_img($image_html, $size = 'woocommerce_thumbnail', $dimensions = array()) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	$url = mad_baits_get_branded_placeholder_image_url();
	if (! $url) {
		return $image_html;
	}

	return sprintf(
		'<img src="%s" alt="%s" class="woocommerce-placeholder wp-post-image mad-product-placeholder" loading="lazy" decoding="async" />',
		esc_url($url),
		esc_attr__('Mad Baits', 'mad-baits')
	);
}
add_filter('woocommerce_placeholder_img', 'mad_baits_filter_placeholder_img', 10, 3);

/**
 * Attachment image HTML for a product card.
 *
 * @param int                  $product_id Product ID.
 * @param array<string, mixed> $attrs      Extra attributes for wp_get_attachment_image().
 * @return string
 */
function mad_baits_get_product_card_image_html($product_id, array $attrs = array()) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('wc_get_product')) {
		return '';
	}

	$product = wc_get_product($product_id);
	if (! $product) {
		return '';
	}

	$thumbnail_id = mad_baits_get_product_display_image_id($product_id);
	if ($thumbnail_id < 1) {
		$range_fallback_url = mad_baits_get_product_range_fallback_image_url($product_id);
		if ('' !== $range_fallback_url) {
			return sprintf(
				'<img src="%1$s" alt="%2$s" class="mad-product-card__image mad-product-card__image--range-fallback" loading="lazy" decoding="async" sizes="%3$s" />',
				esc_url($range_fallback_url),
				esc_attr((string) ($attrs['alt'] ?? $product->get_name())),
				esc_attr(mad_baits_get_product_card_image_sizes_attr())
			);
		}

		return '';
	}

	$defaults = array(
		'class'    => 'mad-product-card__image',
		'loading'  => 'lazy',
		'decoding' => 'async',
		'sizes'    => mad_baits_get_product_card_image_sizes_attr(),
		'alt'      => $product->get_name(),
	);

	return (string) wp_get_attachment_image(
		$thumbnail_id,
		mad_baits_get_product_card_image_size(),
		false,
		array_merge($defaults, $attrs)
	);
}

/**
 * Product card image URL.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_get_product_card_image_url($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('wc_get_product')) {
		return '';
	}

	$product = wc_get_product($product_id);
	if (! $product) {
		return '';
	}

	$thumbnail_id = mad_baits_get_product_display_image_id($product_id);
	$size         = mad_baits_get_product_card_image_size();

	if ($thumbnail_id < 1) {
		return mad_baits_get_branded_placeholder_image_url();
	}

	$url = wp_get_attachment_image_url($thumbnail_id, $size);

	return is_string($url) && '' !== $url ? $url : mad_baits_get_branded_placeholder_image_url();
}

/**
 * Flag that thumbnail regeneration is recommended after deploy.
 */
function mad_baits_flag_product_card_thumbnail_regen_notice() {
	if (get_option('mad_baits_product_card_images_notice_dismissed')) {
		return;
	}
	update_option('mad_baits_product_card_images_regen_recommended', '1', false);
}
add_action('after_switch_theme', 'mad_baits_flag_product_card_thumbnail_regen_notice');

/**
 * Admin notice: regenerate thumbnails for new card sizes.
 */
function mad_baits_product_card_images_admin_notice() {
	if (! is_admin() || ! current_user_can('manage_woocommerce')) {
		return;
	}
	if (! get_option('mad_baits_product_card_images_regen_recommended')) {
		return;
	}
	if (get_option('mad_baits_product_card_images_notice_dismissed')) {
		return;
	}

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (! $screen || ! in_array($screen->id, array('woocommerce_page_wc-status', 'themes', 'plugins', 'edit-product', 'product'), true)) {
		return;
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg('mad_baits_dismiss_card_image_notice', '1'),
		'mad_baits_dismiss_card_image_notice'
	);
	?>
	<div class="notice notice-info is-dismissible">
		<p>
			<strong><?php esc_html_e('Mad Baits product card images updated', 'mad-baits'); ?></strong>
			<?php esc_html_e('Regenerate thumbnails so shop and homepage cards use crisp 800×800 images.', 'mad-baits'); ?>
		</p>
		<p>
			<?php esc_html_e('WooCommerce → Status → Tools → Regenerate shop thumbnails, or run:', 'mad-baits'); ?>
			<code>wp media regenerate --yes</code>
		</p>
		<p>
			<a href="<?php echo esc_url(admin_url('admin.php?page=wc-status&tab=tools')); ?>"><?php esc_html_e('Open WooCommerce tools', 'mad-baits'); ?></a>
			|
			<a href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss', 'mad-baits'); ?></a>
		</p>
	</div>
	<?php
}
add_action('admin_notices', 'mad_baits_product_card_images_admin_notice');

/**
 * Dismiss thumbnail regen notice.
 */
function mad_baits_dismiss_product_card_images_notice() {
	if (! isset($_GET['mad_baits_dismiss_card_image_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if (! current_user_can('manage_woocommerce')) {
		return;
	}
	check_admin_referer('mad_baits_dismiss_card_image_notice');
	update_option('mad_baits_product_card_images_notice_dismissed', '1', false);
	delete_option('mad_baits_product_card_images_regen_recommended');
	wp_safe_redirect(remove_query_arg('mad_baits_dismiss_card_image_notice'));
	exit;
}
add_action('admin_init', 'mad_baits_dismiss_product_card_images_notice');

if (! get_option('mad_baits_product_card_images_regen_recommended') && ! get_option('mad_baits_product_card_images_notice_dismissed')) {
	update_option('mad_baits_product_card_images_regen_recommended', '1', false);
}
