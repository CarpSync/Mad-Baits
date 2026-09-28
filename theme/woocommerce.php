<?php
/**
 * Generic WooCommerce wrapper template.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

if (function_exists('is_shop') && is_shop()) {
	$shop_archive_template = get_template_directory() . '/template-shop-archive.php';
	if (file_exists($shop_archive_template)) {
		require $shop_archive_template;
		return;
	}
}

if (function_exists('is_tax') && is_tax('product_cat')) {
	$product_cat_template = get_template_directory() . '/taxonomy-product_cat.php';
	if (file_exists($product_cat_template)) {
		require $product_cat_template;
		return;
	}
}

if (function_exists('is_tax') && is_tax('product_tag')) {
	$product_tag_template = get_template_directory() . '/taxonomy-product_tag.php';
	if (file_exists($product_tag_template)) {
		require $product_tag_template;
		return;
	}
}

get_header();

do_action('woocommerce_before_main_content');

$shell_title = '';
$shell_intro = '';
$shell_label = __('Mad Baits', 'mad-baits');
$hero_preset = array(
	'position'   => '56% 34%',
	'accent'     => '82% 12%',
	'candidates' => array('HAMMONDAPRIL_22_031.jpg', 'IMG_6277.jpeg', 'CUP-LOGOv2-1.svg'),
	'product_cat_slugs' => array('bundles-deals', 'bundle-deals', 'boilies', 'hookbaits'),
	'semantic_terms' => array('bundle', 'boilie', 'hookbait'),
);
$hero_actions = array();
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
$ai_url = function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/');
$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;

if (function_exists('is_cart') && is_cart()) {
	$shell_title = __('Your Basket', 'mad-baits');
	$shell_label = __('Mad Baits Checkout', 'mad-baits');
	$shell_intro = __('Review your session kit, adjust quantities, and continue to secure checkout when ready.', 'mad-baits');
	$hero_preset['product_cat_slugs'] = array('bundles-deals', 'bundle-deals', 'bundles');
	$hero_preset['semantic_terms'] = array('bundle', 'session');
	$hero_actions[] = array('label' => __('Continue Shopping', 'mad-baits'), 'url' => $shop_url);
} elseif (function_exists('is_checkout') && is_checkout() && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
	$shell_title = __('Order Confirmed', 'mad-baits');
	$shell_label = __('Mad Baits', 'mad-baits');
	$shell_intro = __('Your order is in. We will get your bait and tackle packed and dispatched as quickly as possible.', 'mad-baits');
	$hero_preset['product_cat_slugs'] = array('bundles-deals', 'bundle-deals', 'boilies', 'hookbaits');
	$hero_preset['semantic_terms'] = array('delivery', 'packing', 'session');
	$hero_actions[] = array('label' => __('View Orders', 'mad-baits'), 'url' => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : home_url('/my-account/orders/'));
} elseif (function_exists('is_checkout') && is_checkout()) {
	$shell_title = __('Secure Checkout', 'mad-baits');
	$shell_label = __('Mad Baits Checkout', 'mad-baits');
	$shell_intro = __('Complete your order with secure payment and fast UK dispatch from the Mad Baits team.', 'mad-baits');
	$hero_preset['product_cat_slugs'] = array('bundles-deals', 'bundle-deals', 'boilies');
	$hero_preset['semantic_terms'] = array('checkout', 'session');
	$hero_actions[] = array('label' => __('Back to Basket', 'mad-baits'), 'url' => $cart_url);
} elseif (function_exists('is_account_page') && is_account_page()) {
	$shell_title = is_user_logged_in() ? __('My Account', 'mad-baits') : __('Account Access', 'mad-baits');
	$shell_label = __('Mad Baits Customer Hub', 'mad-baits');
	$shell_intro = is_user_logged_in()
		? __('Track orders, manage account details, and plan your next session from one premium dashboard.', 'mad-baits')
		: __('Sign in to view orders, saved details, and all customer account tools.', 'mad-baits');
	$hero_preset['product_cat_slugs'] = array('compulsive-anglers', 'bundles-deals', 'bundle-deals', 'boilies');
	$hero_preset['semantic_terms'] = array('account', 'campaign', 'bundle');
	$hero_actions[] = array('label' => __('Try AI Bait Finder', 'mad-baits'), 'url' => $ai_url);
	$hero_actions[] = array('label' => __('Shop Bundles & Deals', 'mad-baits'), 'url' => $bundle_url, 'ghost' => true);
}

if ($shell_title) :
	$hero_style = '';
	$hero_image = function_exists('mad_baits_resolve_context_hero_image')
		? mad_baits_resolve_context_hero_image(
			array(
				'candidate_filenames' => isset($hero_preset['candidates']) ? (array) $hero_preset['candidates'] : array(),
				'product_cat_slugs'   => isset($hero_preset['product_cat_slugs']) ? (array) $hero_preset['product_cat_slugs'] : array(),
				'semantic_terms'      => isset($hero_preset['semantic_terms']) ? (array) $hero_preset['semantic_terms'] : array(),
			)
		)
		: '';

	$hero_vars = array(
		'--shop-hero-position' => isset($hero_preset['position']) ? (string) $hero_preset['position'] : '56% 34%',
		'--shop-hero-accent'   => isset($hero_preset['accent']) ? (string) $hero_preset['accent'] : '82% 12%',
	);
	if (is_string($hero_image) && '' !== $hero_image) {
		$hero_vars['--shop-hero-image'] = "url('" . esc_url_raw($hero_image) . "')";
	}
	$hero_style = ' style="' . esc_attr(function_exists('mad_baits_build_css_var_string') ? mad_baits_build_css_var_string($hero_vars) : '') . '"';
	?>
	<section class="shop-hero shop-hero--utility"<?php echo $hero_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="container">
			<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
				<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs'); ?>
			<?php endif; ?>
			<p class="shop-hero__eyebrow"><?php echo esc_html($shell_label); ?></p>
			<h1 class="shop-hero__title"><?php echo esc_html($shell_title); ?></h1>
			<?php if ('' !== $shell_intro) : ?>
				<p class="shop-hero__copy"><?php echo esc_html($shell_intro); ?></p>
			<?php endif; ?>
			<?php if (! empty($hero_actions)) : ?>
				<div class="shop-hero__actions">
					<?php foreach ($hero_actions as $hero_action) : ?>
						<?php
						$action_url = isset($hero_action['url']) ? (string) $hero_action['url'] : '';
						if ('' === $action_url) {
							continue;
						}
						$action_class = ! empty($hero_action['ghost']) ? 'mad-button mad-button--small mad-button--ghost' : 'mad-button mad-button--small';
						?>
						<a class="<?php echo esc_attr($action_class); ?>" href="<?php echo esc_url($action_url); ?>">
							<?php echo esc_html((string) $hero_action['label']); ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<section class="section section--utility-woo">
	<div class="container">
		<div class="mad-woo-shell">
			<?php
			if (function_exists('woocommerce_content')) {
				woocommerce_content();
			}
			?>
		</div>
	</div>
</section>

<?php
do_action('woocommerce_after_main_content');

get_footer();

