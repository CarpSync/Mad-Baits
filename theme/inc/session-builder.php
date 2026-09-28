<?php
/**
 * Build Your Session tools.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Resolve Build Your Session page URL by template assignment.
 *
 * @return string
 */
function mad_baits_get_build_my_session_page_url() {
	if (function_exists('mad_baits_get_template_page_url')) {
		return mad_baits_get_template_page_url('template-build-your-session.php', 'build-my-session');
	}

	return home_url('/build-my-session/');
}

/**
 * Session type options.
 *
 * @return array<string, string>
 */
function mad_baits_get_session_builder_types() {
	return array(
		'24hr'       => __('24hr Session', 'mad-baits'),
		'48hr'       => __('48hr Session', 'mad-baits'),
		'weekend'    => __('Weekend Session', 'mad-baits'),
		'campaign'   => __('Campaign Session', 'mad-baits'),
		'frenchtrip' => __('French Trip', 'mad-baits'),
	);
}

/**
 * Range metadata for session builder.
 *
 * @return array<string, array<string, mixed>>
 */
function mad_baits_get_session_builder_ranges() {
	return array(
		'asbo' => array(
			'label'      => __('ASBO', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('asbo'),
		),
		'bbb' => array(
			'label'      => __('BBB', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('bbb'),
		),
		'calamino' => array(
			'label'      => __('Calamino', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('calamino'),
		),
		'compulsive-angler' => array(
			'label'      => __('Compulsive Angler', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('compulsive-angler'),
		),
		'nutz-plus' => array(
			'label'      => __('Nutz Plus', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('nutz-plus'),
		),
		'nutz-banana' => array(
			'label'      => __('Nutz Banana', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('nutz-banana'),
		),
		'pandemic' => array(
			'label'      => __('Pandemic', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('pandemic'),
		),
		'p-fish' => array(
			'label'      => __('P-Fish', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('p-fish-2'),
		),
		'wicked-white' => array(
			'label'      => __('Wicked White', 'mad-baits'),
			'cat_slugs'  => array(),
			'tag_slugs'  => array('wicked-white'),
		),
	);
}

/**
 * Convert a Woo product into builder-safe payload.
 *
 * @param WC_Product $product Product object.
 * @return array<string, mixed>
 */
function mad_baits_get_session_builder_product_payload($product) {
	$product_id = (int) $product->get_id();
	$image_size = function_exists('mad_baits_get_product_card_image_size') ? mad_baits_get_product_card_image_size() : 'woocommerce_thumbnail';
	$image_url  = '';
	if (function_exists('wp_get_attachment_image_url')) {
		$image_id = (int) $product->get_image_id();
		if ($image_id > 0) {
			$image_url = (string) wp_get_attachment_image_url($image_id, $image_size);
		}
	}
	if ('' === $image_url) {
		$image_url = wc_placeholder_img_src($image_size);
	}

	return array(
		'id'          => $product_id,
		'name'        => (string) $product->get_name(),
		'price_html'  => (string) $product->get_price_html(),
		'price_value' => (float) wc_get_price_to_display($product),
		'image_url'   => (string) $image_url,
		'permalink'   => (string) get_permalink($product_id),
		'type'        => (string) $product->get_type(),
	);
}

/**
 * Query product payload list by category/tag slugs.
 *
 * @param array<string> $category_slugs Product category slugs.
 * @param array<string> $tag_slugs Product tag slugs.
 * @param int           $limit Max products.
 * @return array<int, array<string, mixed>>
 */
function mad_baits_get_session_builder_product_group($category_slugs = array(), $tag_slugs = array(), $limit = 8) {
	$limit = max(1, absint($limit));
	if (! function_exists('wc_get_products')) {
		return array();
	}

	$args = array(
		'status'       => 'publish',
		'limit'        => $limit,
		'orderby'      => 'date',
		'order'        => 'DESC',
		'stock_status' => 'instock',
		'return'       => 'objects',
	);

	$category_slugs = array_values(array_filter(array_map('sanitize_title', (array) $category_slugs)));
	$tag_slugs      = array_values(array_filter(array_map('sanitize_title', (array) $tag_slugs)));

	if (! empty($category_slugs)) {
		$args['category'] = $category_slugs;
	}
	if (! empty($tag_slugs)) {
		$args['tag'] = $tag_slugs;
	}

	$products = wc_get_products($args);
	$payload  = array();

	foreach ((array) $products as $product) {
		if (! is_object($product) || ! is_a($product, 'WC_Product')) {
			continue;
		}

		$payload[] = mad_baits_get_session_builder_product_payload($product);
	}

	return $payload;
}

/**
 * Build complete builder dataset.
 *
 * @return array<string, mixed>
 */
function mad_baits_get_session_builder_dataset() {
	$ranges = mad_baits_get_session_builder_ranges();
	foreach ($ranges as $range_slug => $range_meta) {
		$range_products = mad_baits_get_session_builder_product_group(
			isset($range_meta['cat_slugs']) ? (array) $range_meta['cat_slugs'] : array(),
			isset($range_meta['tag_slugs']) ? (array) $range_meta['tag_slugs'] : array($range_slug),
			6
		);

		$ranges[ $range_slug ]['products'] = $range_products;
		$ranges[ $range_slug ]['shop_url'] = function_exists('mad_baits_get_range_filter_url')
			? mad_baits_get_range_filter_url(isset($range_meta['tag_slugs'][0]) ? (string) $range_meta['tag_slugs'][0] : (string) $range_slug, home_url('/shop/'))
			: home_url('/shop/');
	}

	$hookbaits = mad_baits_get_session_builder_product_group(
		array('hookbaits', 'hookbait', 'compulsive-hookbaits'),
		array('hookbait', 'hookbaits', 'wafter', 'wafters', 'popup', 'pop-up'),
		10
	);
	$liquids = mad_baits_get_session_builder_product_group(
		array('liquids', 'other-liquid-foods', 'liquid-foods', 'bundles'),
		array('liquid', 'liquids', 'glug', 'attractor', 'bundle', 'extras'),
		10
	);

	return array(
		'session_types' => mad_baits_get_session_builder_types(),
		'ranges'        => $ranges,
		'hookbaits'     => $hookbaits,
		'liquids'       => $liquids,
	);
}

/**
 * Render Build Your Session shortcode.
 *
 * @return string
 */
function mad_baits_session_builder_shortcode() {
	if (function_exists('mad_baits_session_builder_is_coming_soon') && mad_baits_session_builder_is_coming_soon()) {
		ob_start();
		if (function_exists('mad_baits_render_session_app_coming_soon_page')) {
			mad_baits_render_session_app_coming_soon_page();
		}
		return (string) ob_get_clean();
	}

	if (! class_exists('WooCommerce')) {
		return '<p class="section__empty-state">' . esc_html__('WooCommerce is required for Build Your Session.', 'mad-baits') . '</p>';
	}

	$dataset = mad_baits_get_session_builder_dataset();
	$nonce   = wp_create_nonce('mad_baits_session_builder_add_all');

	ob_start();
	?>
	<section class="mad-session-builder" data-session-builder data-session-builder-action="mad_baits_session_builder_add_all" data-session-builder-nonce="<?php echo esc_attr($nonce); ?>">
		<header class="mad-session-builder__header">
			<p class="section__kicker"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></p>
			<h2><?php esc_html_e('Plan a complete kit in minutes', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Choose your session style, lock in your bait range, then add matching hookbaits and liquids before dropping the full kit into your basket.', 'mad-baits'); ?></p>
		</header>

		<ol class="mad-session-builder__steps" aria-label="<?php esc_attr_e('Session builder steps', 'mad-baits'); ?>">
			<li data-session-step-indicator="1" class="is-active"><?php esc_html_e('Session Type', 'mad-baits'); ?></li>
			<li data-session-step-indicator="2"><?php esc_html_e('Bait Range', 'mad-baits'); ?></li>
			<li data-session-step-indicator="3"><?php esc_html_e('Hookbait', 'mad-baits'); ?></li>
			<li data-session-step-indicator="4"><?php esc_html_e('Liquids & Extras', 'mad-baits'); ?></li>
			<li data-session-step-indicator="5"><?php esc_html_e('Review', 'mad-baits'); ?></li>
			<li data-session-step-indicator="6"><?php esc_html_e('Add Kit', 'mad-baits'); ?></li>
		</ol>

		<div class="mad-session-builder__panel is-active" data-session-step-panel="1">
			<h3><?php esc_html_e('Step 1: Choose session type', 'mad-baits'); ?></h3>
			<div class="mad-session-builder__choice-grid">
				<?php foreach ((array) $dataset['session_types'] as $session_key => $session_label) : ?>
					<button type="button" class="mad-session-builder__choice" data-session-choice data-session-choice-type="session" data-session-choice-value="<?php echo esc_attr((string) $session_key); ?>">
						<?php echo esc_html((string) $session_label); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="mad-session-builder__actions">
				<button type="button" class="mad-button mad-button--small" data-session-next><?php esc_html_e('Continue', 'mad-baits'); ?></button>
			</div>
		</div>

		<div class="mad-session-builder__panel" data-session-step-panel="2">
			<h3><?php esc_html_e('Step 2: Choose bait range + core bait', 'mad-baits'); ?></h3>
			<div class="mad-session-builder__choice-grid mad-session-builder__choice-grid--ranges">
				<?php foreach ((array) $dataset['ranges'] as $range_slug => $range_meta) : ?>
					<button type="button" class="mad-session-builder__choice" data-session-choice data-session-choice-type="range" data-session-choice-value="<?php echo esc_attr((string) $range_slug); ?>">
						<?php echo esc_html(isset($range_meta['label']) ? (string) $range_meta['label'] : (string) $range_slug); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php foreach ((array) $dataset['ranges'] as $range_slug => $range_meta) : ?>
				<div class="mad-session-builder__range-group" data-session-range-products="<?php echo esc_attr((string) $range_slug); ?>" hidden>
					<p class="mad-session-builder__range-title"><?php echo esc_html(sprintf(__('Core bait options in %s', 'mad-baits'), isset($range_meta['label']) ? (string) $range_meta['label'] : (string) $range_slug)); ?></p>
					<div class="mad-session-builder__product-options">
						<?php
						$range_products = isset($range_meta['products']) ? (array) $range_meta['products'] : array();
						if (empty($range_products)) :
							?>
							<p class="section__empty-state"><?php esc_html_e('No products mapped yet for this range.', 'mad-baits'); ?></p>
						<?php else : ?>
							<?php foreach ($range_products as $item) : ?>
								<label class="mad-session-builder__product-option" data-session-product-option>
									<input type="checkbox" class="mad-session-builder__product-input" data-session-product-input data-session-product-id="<?php echo esc_attr((string) $item['id']); ?>" data-session-product-name="<?php echo esc_attr((string) $item['name']); ?>" data-session-product-price="<?php echo esc_attr((string) $item['price_value']); ?>" />
									<span class="mad-session-builder__product-media" style="<?php echo esc_attr("--builder-product-image: url('" . esc_url_raw((string) $item['image_url']) . "');"); ?>"></span>
									<span class="mad-session-builder__product-copy">
										<strong><?php echo esc_html((string) $item['name']); ?></strong>
										<span><?php echo wp_kses_post((string) $item['price_html']); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="mad-session-builder__actions">
				<button type="button" class="mad-button mad-button--small mad-button--ghost" data-session-prev><?php esc_html_e('Back', 'mad-baits'); ?></button>
				<button type="button" class="mad-button mad-button--small" data-session-next><?php esc_html_e('Continue', 'mad-baits'); ?></button>
			</div>
		</div>

		<div class="mad-session-builder__panel" data-session-step-panel="3">
			<h3><?php esc_html_e('Step 3: Choose hookbait / add-on', 'mad-baits'); ?></h3>
			<div class="mad-session-builder__product-options">
				<?php foreach ((array) $dataset['hookbaits'] as $item) : ?>
					<label class="mad-session-builder__product-option" data-session-product-option>
						<input type="checkbox" class="mad-session-builder__product-input" data-session-product-input data-session-product-id="<?php echo esc_attr((string) $item['id']); ?>" data-session-product-name="<?php echo esc_attr((string) $item['name']); ?>" data-session-product-price="<?php echo esc_attr((string) $item['price_value']); ?>" />
						<span class="mad-session-builder__product-media" style="<?php echo esc_attr("--builder-product-image: url('" . esc_url_raw((string) $item['image_url']) . "');"); ?>"></span>
						<span class="mad-session-builder__product-copy">
							<strong><?php echo esc_html((string) $item['name']); ?></strong>
							<span><?php echo wp_kses_post((string) $item['price_html']); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="mad-session-builder__actions">
				<button type="button" class="mad-button mad-button--small mad-button--ghost" data-session-prev><?php esc_html_e('Back', 'mad-baits'); ?></button>
				<button type="button" class="mad-button mad-button--small" data-session-next><?php esc_html_e('Continue', 'mad-baits'); ?></button>
			</div>
		</div>

		<div class="mad-session-builder__panel" data-session-step-panel="4">
			<h3><?php esc_html_e('Step 4: Choose liquids / extras', 'mad-baits'); ?></h3>
			<div class="mad-session-builder__product-options">
				<?php foreach ((array) $dataset['liquids'] as $item) : ?>
					<label class="mad-session-builder__product-option" data-session-product-option>
						<input type="checkbox" class="mad-session-builder__product-input" data-session-product-input data-session-product-id="<?php echo esc_attr((string) $item['id']); ?>" data-session-product-name="<?php echo esc_attr((string) $item['name']); ?>" data-session-product-price="<?php echo esc_attr((string) $item['price_value']); ?>" />
						<span class="mad-session-builder__product-media" style="<?php echo esc_attr("--builder-product-image: url('" . esc_url_raw((string) $item['image_url']) . "');"); ?>"></span>
						<span class="mad-session-builder__product-copy">
							<strong><?php echo esc_html((string) $item['name']); ?></strong>
							<span><?php echo wp_kses_post((string) $item['price_html']); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="mad-session-builder__actions">
				<button type="button" class="mad-button mad-button--small mad-button--ghost" data-session-prev><?php esc_html_e('Back', 'mad-baits'); ?></button>
				<button type="button" class="mad-button mad-button--small" data-session-next><?php esc_html_e('Continue', 'mad-baits'); ?></button>
			</div>
		</div>

		<div class="mad-session-builder__panel" data-session-step-panel="5">
			<h3><?php esc_html_e('Step 5: Review your session kit', 'mad-baits'); ?></h3>
			<div class="mad-session-builder__review">
				<p><strong><?php esc_html_e('Session type:', 'mad-baits'); ?></strong> <span data-session-review-type><?php esc_html_e('Not selected', 'mad-baits'); ?></span></p>
				<p><strong><?php esc_html_e('Bait range:', 'mad-baits'); ?></strong> <span data-session-review-range><?php esc_html_e('Not selected', 'mad-baits'); ?></span></p>
				<ul class="mad-session-builder__review-list" data-session-review-list>
					<li><?php esc_html_e('No products selected yet.', 'mad-baits'); ?></li>
				</ul>
				<p class="mad-session-builder__total"><?php esc_html_e('Running total:', 'mad-baits'); ?> <span data-session-review-total>£0.00</span></p>
			</div>
			<div class="mad-session-builder__actions">
				<button type="button" class="mad-button mad-button--small mad-button--ghost" data-session-prev><?php esc_html_e('Back', 'mad-baits'); ?></button>
				<button type="button" class="mad-button mad-button--small" data-session-next><?php esc_html_e('Continue', 'mad-baits'); ?></button>
			</div>
		</div>

		<div class="mad-session-builder__panel" data-session-step-panel="6">
			<h3><?php esc_html_e('Step 6: Add session kit to basket', 'mad-baits'); ?></h3>
			<p><?php esc_html_e('When ready, add every selected item in one action.', 'mad-baits'); ?></p>
			<div class="mad-session-builder__actions">
				<button type="button" class="mad-button" data-session-submit><?php esc_html_e('Add All To Basket', 'mad-baits'); ?></button>
			</div>
			<p class="mad-session-builder__status" data-session-status aria-live="polite"></p>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode('mad_baits_session_builder', 'mad_baits_session_builder_shortcode');

/**
 * Add selected session builder products to cart in a single AJAX call.
 */
function mad_baits_ajax_session_builder_add_all() {
	check_ajax_referer('mad_baits_session_builder_add_all', 'nonce');

	if (! function_exists('WC') || ! WC() || ! WC()->cart) {
		wp_send_json_error(
			array(
				'message' => __('Basket is unavailable right now.', 'mad-baits'),
			)
		);
	}

	$raw_product_ids = isset($_POST['product_ids']) ? wp_unslash($_POST['product_ids']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if (! is_array($raw_product_ids)) {
		$raw_product_ids = array();
	}

	$product_ids = array_slice(array_values(array_unique(array_filter(array_map('absint', $raw_product_ids)))), 0, 24);
	if (empty($product_ids)) {
		wp_send_json_error(
			array(
				'message' => __('Select at least one product first.', 'mad-baits'),
			)
		);
	}

	$added   = array();
	$skipped = array();

	foreach ($product_ids as $product_id) {
		$product = wc_get_product($product_id);
		if (! is_object($product) || ! is_a($product, 'WC_Product') || ! $product->is_purchasable() || ! $product->is_in_stock()) {
			$skipped[] = $product_id;
			continue;
		}

		// Variable products require explicit variation selection, so we skip safely.
		if ($product->is_type('variable')) {
			$skipped[] = $product_id;
			continue;
		}

		$did_add = WC()->cart->add_to_cart($product_id, 1);
		if ($did_add) {
			$added[] = $product_id;
		} else {
			$skipped[] = $product_id;
		}
	}

	if (empty($added)) {
		wp_send_json_error(
			array(
				'message' => __('Could not add selected products. Check stock/variation options.', 'mad-baits'),
				'skipped' => $skipped,
			)
		);
	}

	wp_send_json_success(
		array(
			'message'     => __('Session kit added to basket.', 'mad-baits'),
			'added'       => $added,
			'skipped'     => $skipped,
			'cart_url'    => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
			'cart_count'  => WC()->cart->get_cart_contents_count(),
		)
	);
}
add_action('wp_ajax_mad_baits_session_builder_add_all', 'mad_baits_ajax_session_builder_add_all');
add_action('wp_ajax_nopriv_mad_baits_session_builder_add_all', 'mad_baits_ajax_session_builder_add_all');

/**
 * Detect direct requests to the Build My Session URL path.
 *
 * @return bool
 */
function mad_baits_is_build_my_session_request() {
	if (is_admin() || wp_doing_ajax()) {
		return false;
	}

	$uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
	$path = (string) parse_url($uri, PHP_URL_PATH);
	if ('' === $path) {
		return false;
	}

	$path = trim($path, '/');
	$home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

	if ('' !== $home_path && str_starts_with($path, $home_path)) {
		$path = trim(substr($path, strlen($home_path)), '/');
	}

	$slug = strtolower($path);
	if (false !== strpos($slug, '/')) {
		$parts = explode('/', $slug);
		$slug  = (string) end($parts);
	}

	return in_array($slug, array('build-my-session', 'build-your-session'), true);
}

/**
 * Build a virtual page object for theme rendering when no WP page exists yet.
 *
 * @return WP_Post
 */
function mad_baits_get_virtual_build_my_session_post() {
	$virtual = new stdClass();
	$virtual->ID                    = 0;
	$virtual->post_author           = 1;
	$virtual->post_date             = current_time('mysql');
	$virtual->post_date_gmt         = current_time('mysql', 1);
	$virtual->post_content          = '';
	$virtual->post_title            = __('Build My Session', 'mad-baits');
	$virtual->post_excerpt          = '';
	$virtual->post_status           = 'publish';
	$virtual->post_comment_status   = 'closed';
	$virtual->post_ping_status      = 'closed';
	$virtual->post_password         = '';
	$virtual->post_name             = 'build-my-session';
	$virtual->to_ping               = '';
	$virtual->pinged                = '';
	$virtual->post_modified         = current_time('mysql');
	$virtual->post_modified_gmt     = current_time('mysql', 1);
	$virtual->post_content_filtered = '';
	$virtual->post_parent           = 0;
	$virtual->guid                  = home_url('/build-my-session/');
	$virtual->menu_order            = 0;
	$virtual->post_type             = 'page';
	$virtual->post_mime_type        = '';
	$virtual->comment_count         = 0;
	$virtual->filter                = 'raw';

	return new WP_Post($virtual);
}

/**
 * Serve Build My Session when linked URL has no published WordPress page yet.
 *
 * @return void
 */
function mad_baits_serve_virtual_build_my_session_page() {
	if (! mad_baits_is_build_my_session_request()) {
		return;
	}

	$existing = get_page_by_path('build-my-session', OBJECT, 'page');
	if ($existing instanceof WP_Post && 'publish' === $existing->post_status) {
		return;
	}

	global $wp_query, $post;

	$virtual_post = mad_baits_get_virtual_build_my_session_post();
	$post         = $virtual_post;

	$wp_query->posts                 = array($virtual_post);
	$wp_query->post                  = $virtual_post;
	$wp_query->queried_object        = $virtual_post;
	$wp_query->queried_object_id     = 0;
	$wp_query->post_count            = 1;
	$wp_query->found_posts           = 1;
	$wp_query->max_num_pages         = 1;
	$wp_query->is_404                = false;
	$wp_query->is_page               = true;
	$wp_query->is_singular           = true;
	$wp_query->is_home               = false;

	status_header(200);
	nocache_headers();

	$template = get_theme_file_path('template-build-your-session.php');
	if (! file_exists($template)) {
		return;
	}

	include $template;
	exit;
}
add_action('template_redirect', 'mad_baits_serve_virtual_build_my_session_page', 0);

/**
 * Force the Build My Session template when a published page exists.
 *
 * @param string $template Current template path.
 * @return string
 */
function mad_baits_force_build_my_session_template($template) {
	if (! is_page()) {
		return $template;
	}

	$page = get_queried_object();
	if (! $page instanceof WP_Post) {
		return $template;
	}

	if (! in_array($page->post_name, array('build-my-session', 'build-your-session'), true)) {
		return $template;
	}

	$custom = get_theme_file_path('template-build-your-session.php');
	if (file_exists($custom)) {
		return $custom;
	}

	return $template;
}
add_filter('template_include', 'mad_baits_force_build_my_session_template', 99);
