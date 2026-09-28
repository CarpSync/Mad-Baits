<?php
/**
 * Premium mobile app enhancement layer.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Option key for app enhancement toggles.
 */
const MAD_BAITS_APP_ENHANCEMENTS_OPTION = 'mad_baits_app_enhancements';

/**
 * Default enhancement settings.
 *
 * @return array<string, bool>
 */
function mad_baits_get_app_enhancement_defaults() {
	return array(
		'enable_smart_matching'  => true,
		'enable_recommender'     => true,
		'enable_motion_effects'  => true,
		'enable_haptics'         => false,
		'enable_install_prompt'  => true,
		'enable_mad_deal_style'  => true,
		'enable_premium_loading' => true,
	);
}

/**
 * Read enhancement settings.
 *
 * @return array<string, bool>
 */
function mad_baits_get_app_enhancement_settings() {
	$defaults = mad_baits_get_app_enhancement_defaults();
	$stored   = get_option(MAD_BAITS_APP_ENHANCEMENTS_OPTION, array());
	$stored   = is_array($stored) ? $stored : array();

	$settings = array();
	foreach ($defaults as $key => $default) {
		$settings[ $key ] = array_key_exists($key, $stored) ? (bool) $stored[ $key ] : (bool) $default;
	}

	return $settings;
}

/**
 * Check if a feature is enabled.
 *
 * @param string $key Feature key.
 * @return bool
 */
function mad_baits_app_enhancement_enabled($key) {
	$settings = mad_baits_get_app_enhancement_settings();
	return ! empty($settings[ $key ]);
}

/**
 * Add enhancement setting classes to body.
 *
 * @param array<string> $classes Classes.
 * @return array<string>
 */
function mad_baits_app_enhancement_body_classes($classes) {
	$settings = mad_baits_get_app_enhancement_settings();
	foreach ($settings as $key => $enabled) {
		$classes[] = $enabled
			? 'mad-app-enhance--' . sanitize_html_class((string) $key)
			: 'mad-app-enhance--' . sanitize_html_class((string) $key) . '-off';
	}
	return $classes;
}
add_filter('body_class', 'mad_baits_app_enhancement_body_classes', 35);

/**
 * Register app enhancement options.
 *
 * @return void
 */
function mad_baits_register_app_enhancement_settings() {
	register_setting(
		'mad_baits_app_enhancements_group',
		MAD_BAITS_APP_ENHANCEMENTS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mad_baits_sanitize_app_enhancement_settings',
			'default'           => mad_baits_get_app_enhancement_defaults(),
		)
	);
}
add_action('admin_init', 'mad_baits_register_app_enhancement_settings');

/**
 * Sanitize settings array.
 *
 * @param mixed $value Raw settings.
 * @return array<string, bool>
 */
function mad_baits_sanitize_app_enhancement_settings($value) {
	$defaults = mad_baits_get_app_enhancement_defaults();
	$value    = is_array($value) ? $value : array();
	$clean    = array();

	foreach ($defaults as $key => $default) {
		$clean[ $key ] = ! empty($value[ $key ]);
	}

	return $clean;
}

/**
 * Add settings page.
 *
 * @return void
 */
function mad_baits_add_app_enhancement_settings_page() {
	add_options_page(
		__('Mad Baits App UX', 'mad-baits'),
		__('Mad Baits App UX', 'mad-baits'),
		'manage_options',
		'mad-baits-app-ux',
		'mad_baits_render_app_enhancement_settings_page'
	);
}
add_action('admin_menu', 'mad_baits_add_app_enhancement_settings_page');

/**
 * Render settings page.
 *
 * @return void
 */
function mad_baits_render_app_enhancement_settings_page() {
	if (! current_user_can('manage_options')) {
		return;
	}

	$settings = mad_baits_get_app_enhancement_settings();
	$labels   = array(
		'enable_smart_matching'  => __('Enable Smart Matching', 'mad-baits'),
		'enable_recommender'     => __('Enable Recommender', 'mad-baits'),
		'enable_motion_effects'  => __('Enable Motion Effects', 'mad-baits'),
		'enable_haptics'         => __('Enable Haptics (mobile only)', 'mad-baits'),
		'enable_install_prompt'  => __('Enable PWA Install Prompt', 'mad-baits'),
		'enable_mad_deal_style'  => __('Enable Mad Deal Styling', 'mad-baits'),
		'enable_premium_loading' => __('Enable Premium Loading States', 'mad-baits'),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Mad Baits App UX Enhancements', 'mad-baits'); ?></h1>
		<p><?php esc_html_e('Control premium mobile/PWA enhancement layers without affecting checkout and order flow.', 'mad-baits'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('mad_baits_app_enhancements_group'); ?>
			<table class="form-table" role="presentation">
				<?php foreach ($labels as $key => $label) : ?>
					<tr>
						<th scope="row"><?php echo esc_html($label); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr(MAD_BAITS_APP_ENHANCEMENTS_OPTION . '[' . $key . ']'); ?>"
									value="1"
									<?php checked(! empty($settings[ $key ])); ?>
								/>
								<?php esc_html_e('Enabled', 'mad-baits'); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(__('Save App UX Settings', 'mad-baits')); ?>
		</form>
	</div>
	<?php
}

/**
 * Resolve likely core range slug from a product.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function mad_baits_get_product_core_range_slug($product_id) {
	if (function_exists('mad_baits_resolve_product_range_slug')) {
		return mad_baits_resolve_product_range_slug($product_id);
	}

	return '';
}

/**
 * Smart matching IDs for a product.
 *
 * @param int $product_id Product ID.
 * @param int $limit Max products.
 * @return int[]
 */
function mad_baits_get_smart_match_product_ids($product_id, $limit = 4) {
	$product_id = absint($product_id);
	$limit      = max(1, absint($limit));
	if ($product_id < 1) {
		return array();
	}

	$ids = array();
	if (function_exists('mad_baits_get_pair_it_with_product_ids')) {
		$ids = mad_baits_get_pair_it_with_product_ids($product_id, $limit);
	}

	$ids = array_values(
		array_filter(
			array_map('absint', $ids),
			static function ($id) use ($product_id) {
				return $id > 0 && $id !== $product_id;
			}
		)
	);

	if (count($ids) >= $limit) {
		return array_slice($ids, 0, $limit);
	}

	$range = mad_baits_get_product_core_range_slug($product_id);
	if ('' === $range || ! function_exists('mad_baits_get_semantic_product_ids')) {
		return array_slice($ids, 0, $limit);
	}

	$needles = array_filter(
		array(
			$range,
			'hookbait',
			'liquid',
			'pellet',
		)
	);
	$semantic_ids = mad_baits_get_semantic_product_ids($needles, $limit * 2);
	$semantic_ids = array_values(
		array_filter(
			array_map('absint', (array) $semantic_ids),
			static function ($id) use ($product_id, $ids) {
				return $id > 0 && $id !== $product_id && ! in_array($id, $ids, true);
			}
		)
	);

	$ids = array_values(array_unique(array_merge($ids, $semantic_ids)));

	return array_slice($ids, 0, $limit);
}

/**
 * Render "Perfect Match" section on product pages.
 *
 * @return void
 */
function mad_baits_render_perfect_match_section() {
	if (! mad_baits_app_enhancement_enabled('enable_smart_matching')) {
		return;
	}
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	global $product;
	if (! is_object($product) || ! is_a($product, 'WC_Product')) {
		return;
	}

	$product_id = (int) $product->get_id();
	$is_bundle_product = (
		(function_exists('mad_baits_is_bundle_builder_product') && mad_baits_is_bundle_builder_product($product))
		|| has_term(array('bundles-deals', 'bundle-deals', 'bundles', 'deals'), 'product_cat', $product_id)
	);
	if ($is_bundle_product) {
		return;
	}

	$ids        = mad_baits_get_smart_match_product_ids($product_id, 4);
	if (empty($ids)) {
		return;
	}
	?>
	<section class="mad-perfect-match section section--contrast" aria-labelledby="mad-perfect-match-title" data-mad-perfect-match>
		<div class="container">
			<header class="mad-perfect-match__header">
				<p class="section__kicker"><?php esc_html_e('Perfect Match', 'mad-baits'); ?></p>
				<h2 id="mad-perfect-match-title"><?php esc_html_e('Complete the range for maximum attraction.', 'mad-baits'); ?></h2>
			</header>
			<div class="mad-perfect-match__grid">
				<?php foreach ($ids as $match_id) : ?>
					<?php if (function_exists('mad_baits_render_product_card')) : ?>
						<?php mad_baits_render_product_card((int) $match_id, true, 'perfect-match'); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}
add_action('woocommerce_after_single_product_summary', 'mad_baits_render_perfect_match_section', 18);

/**
 * Render compact perfect-match rail for bundle products (hidden until complete).
 *
 * @return void
 */
function mad_baits_render_bundle_perfect_match_inline() {
	if (! mad_baits_app_enhancement_enabled('enable_smart_matching')) {
		return;
	}
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}
	if (! class_exists('MBBB_Plugin', false)) {
		return;
	}

	global $product;
	if (! is_object($product) || ! is_a($product, 'WC_Product')) {
		return;
	}
	if (! MBBB_Plugin::instance()->is_enabled($product->get_id())) {
		return;
	}

	$ids = mad_baits_get_smart_match_product_ids((int) $product->get_id(), 3);
	if (empty($ids)) {
		return;
	}
	?>
	<div class="mad-bundle-match-rail" id="mad-bundle-match-rail" hidden>
		<div class="mad-bundle-match-rail__header">
			<strong><?php esc_html_e('Perfect Match', 'mad-baits'); ?></strong>
			<span><?php esc_html_e('Session-ready add-ons', 'mad-baits'); ?></span>
		</div>
		<div class="mad-bundle-match-rail__items">
			<?php foreach ($ids as $match_id) : ?>
				<?php if (function_exists('mad_baits_render_product_card')) : ?>
					<?php mad_baits_render_product_card((int) $match_id, true, 'perfect-match-inline'); ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render What's In The Water quiz shell.
 *
 * @return void
 */
function mad_baits_get_water_quiz_fallback_url() {
	$page = get_page_by_path('whats-in-the-water');
	if ($page instanceof WP_Post) {
		$url = get_permalink($page);
		if (is_string($url) && '' !== $url) {
			return $url;
		}
	}
	return home_url('/whats-in-the-water/');
}

/**
 * Render fallback content when /whats-in-the-water/ is requested but no page exists.
 *
 * @return void
 */
function mad_baits_render_water_quiz_fallback_page() {
	$fallback_url = mad_baits_get_water_quiz_fallback_url();
	status_header(200);
	nocache_headers();
	get_header();
	?>
	<section class="section section--contrast">
		<div class="container" style="padding-top:2rem;padding-bottom:2rem;">
			<header class="section__heading">
				<p class="section__kicker"><?php esc_html_e('Recommender', 'mad-baits'); ?></p>
				<h1><?php esc_html_e('What\'s In The Water?', 'mad-baits'); ?></h1>
				<p><?php esc_html_e('Use the quick recommender to match bait choices to your session conditions.', 'mad-baits'); ?></p>
			</header>
			<p>
				<a class="mad-button" href="<?php echo esc_url($fallback_url); ?>">
					<?php esc_html_e('Open Recommender', 'mad-baits'); ?>
				</a>
			</p>
			<noscript>
				<p>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url(home_url('/shop/')); ?>">
						<?php esc_html_e('Browse the full range', 'mad-baits'); ?>
					</a>
				</p>
			</noscript>
		</div>
	</section>
	<?php
	get_footer();
	exit;
}

/**
 * Serve fallback page when slug is requested but no page exists.
 *
 * @return void
 */
function mad_baits_maybe_render_water_quiz_fallback_page() {
	if (is_admin() || ! is_404()) {
		return;
	}
	global $wp;
	$request_path = isset($wp->request) ? trim((string) $wp->request, '/') : '';
	if ('whats-in-the-water' !== $request_path) {
		return;
	}
	mad_baits_render_water_quiz_fallback_page();
}
add_action('template_redirect', 'mad_baits_maybe_render_water_quiz_fallback_page', 1);

/**
 * Render quiz shell and launcher.
 *
 * @return void
 */
function mad_baits_render_water_quiz_shell() {
	if (! mad_baits_app_enhancement_enabled('enable_recommender')) {
		return;
	}
	if (is_admin()) {
		return;
	}
	if (function_exists('is_page') && is_page('whats-in-the-water')) {
		return;
	}
	?>
	<div class="mad-water-quiz" data-water-quiz hidden aria-hidden="true">
		<div class="mad-water-quiz__backdrop" data-water-quiz-close></div>
		<div class="mad-water-quiz__sheet" role="dialog" aria-modal="true" aria-labelledby="mad-water-quiz-title">
			<div class="mad-water-quiz__head">
				<p class="mad-water-quiz__kicker"><?php esc_html_e('Recommender', 'mad-baits'); ?></p>
				<h2 id="mad-water-quiz-title"><?php esc_html_e('What\'s In The Water?', 'mad-baits'); ?></h2>
				<button type="button" class="mad-water-quiz__close" data-water-quiz-close aria-label="<?php esc_attr_e('Close', 'mad-baits'); ?>">×</button>
			</div>
			<div class="mad-water-quiz__progress"><span data-water-quiz-progress></span></div>
			<div class="mad-water-quiz__body" data-water-quiz-body></div>
		</div>
	</div>
	<a href="<?php echo esc_url(mad_baits_get_water_quiz_fallback_url()); ?>" class="mad-water-quiz-launcher" role="button">
		<?php esc_html_e('What\'s In The Water?', 'mad-baits'); ?>
	</a>
	<?php
}
add_action('wp_footer', 'mad_baits_render_water_quiz_shell', 28);

/**
 * Build recommendation keywords from quiz answers.
 *
 * @param array<string, string> $answers Quiz answers.
 * @return string[]
 */
function mad_baits_build_water_quiz_needles($answers) {
	$answers = array_map('sanitize_title', $answers);
	$needles = array();

	if (! empty($answers['style'])) {
		$needles[] = $answers['style'];
	}
	if (! empty($answers['condition'])) {
		$needles[] = $answers['condition'];
	}

	if ('winter' === ($answers['season'] ?? '')) {
		$needles = array_merge($needles, array('wicked-white', 'high-attraction', 'liquid'));
	}
	if ('french-venue' === ($answers['waterType'] ?? '') || 'week-trip' === ($answers['sessionLength'] ?? '')) {
		$needles = array_merge($needles, array('asbo', 'pandemic', 'nutz-plus', 'bundle'));
	}
	if ('clear' === ($answers['condition'] ?? '') || 'pressured' === ($answers['condition'] ?? '')) {
		$needles = array_merge($needles, array('hookbait', 'washed-out', 'match-the-hatch'));
	}
	if ('silty' === ($answers['condition'] ?? '') || 'weedy' === ($answers['condition'] ?? '')) {
		$needles = array_merge($needles, array('pop-up', 'high-leakage', 'liquid'));
	}

	return array_values(array_unique(array_filter(array_map('sanitize_title', $needles))));
}

/**
 * Quiz AJAX endpoint.
 *
 * @return void
 */
function mad_baits_ajax_water_recommender() {
	check_ajax_referer('mad_baits_water_recommender', 'nonce');

	$answers = isset($_POST['answers']) && is_array($_POST['answers']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? array_map('sanitize_text_field', wp_unslash($_POST['answers'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: array();

	$needles = mad_baits_build_water_quiz_needles($answers);
	$ids     = function_exists('mad_baits_get_semantic_product_ids') ? mad_baits_get_semantic_product_ids($needles, 8) : array();
	$ids     = array_values(array_filter(array_map('absint', (array) $ids)));

	$products = array();
	foreach ($ids as $id) {
		$product = function_exists('wc_get_product') ? wc_get_product($id) : null;
		if (! $product) {
			continue;
		}
		$products[] = array(
			'id'    => $id,
			'name'  => $product->get_name(),
			'url'   => $product->get_permalink(),
			'price' => wp_kses_post($product->get_price_html()),
			'image' => function_exists('mad_baits_get_product_card_image_url')
				? mad_baits_get_product_card_image_url((int) $product->get_id())
				: wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail'),
		);
	}

	wp_send_json_success(
		array(
			'needles'  => $needles,
			'products' => $products,
		)
	);
}
add_action('wp_ajax_mad_baits_water_recommender', 'mad_baits_ajax_water_recommender');
add_action('wp_ajax_nopriv_mad_baits_water_recommender', 'mad_baits_ajax_water_recommender');

/**
 * Build page recommender output payload.
 *
 * @param array<string,string> $answers Quiz answers.
 * @return array<string,mixed>
 */
function mad_baits_build_water_finder_page_payload($answers) {
	$answers = is_array($answers) ? array_map('sanitize_text_field', $answers) : array();
	$venue   = sanitize_title((string) ($answers['venueType'] ?? ''));
	$cond    = sanitize_title((string) ($answers['condition'] ?? ''));
	$length  = sanitize_title((string) ($answers['sessionLength'] ?? ''));
	$season  = sanitize_title((string) ($answers['season'] ?? ''));
	$style   = sanitize_title((string) ($answers['style'] ?? ''));

	$recommended_range = 'Nutz Plus';
	$hookbait          = 'Washed-out Hookbaits';
	$liquid            = 'Matching Liquid Food';
	$pellet            = 'Session Pellet';
	$bundle            = '10kg Session Deal';
	$needles           = array('bundle', 'deal', 'hookbait', 'liquid', 'pellet');

	if ('winter' === $season || 'instant-bite' === $style) {
		$recommended_range = 'Wicked White';
		$hookbait          = 'High Attraction Pop Ups';
		$liquid            = 'High Attraction Liquids';
		$needles           = array_merge($needles, array('wicked-white', 'high-attraction', 'liquid'));
	}

	if ('french-venue' === $venue || 'week-trip' === $length) {
		$recommended_range = 'ASBO / Pandemic / Nutz Plus';
		$bundle            = '20kg+ Bulk Session Deals';
		$needles           = array_merge($needles, array('asbo', 'pandemic', 'nutz-plus', 'bulk', 'bundles-deals'));
	}

	if ('river' === $venue) {
		$recommended_range = 'Pandemic';
		$bundle            = 'Barbel Pack';
		$needles           = array_merge($needles, array('pandemic', 'barbel', 'river'));
	}

	if ('pressured' === $cond || 'clear' === $cond || 'match-the-hatch' === $style) {
		$hookbait = 'Washed-out Wafters';
		$needles  = array_merge($needles, array('washed-out', 'wafters', 'match-the-hatch'));
	}

	if ('silty' === $cond || 'weedy' === $cond) {
		$hookbait = 'Balanced Pop Ups';
		$liquid   = 'High Leakage Liquid';
		$needles  = array_merge($needles, array('pop-up', 'high-leakage', 'liquid'));
	}

	if ('big-hit-feeding' === $style) {
		$bundle  = '10kg / 20kg / 30kg Deals';
		$needles = array_merge($needles, array('10kg', '20kg', '30kg', 'bundles-deals'));
	}

	if (false !== strpos($style, 'nutty') || false !== strpos($style, 'sweet')) {
		$recommended_range = 'Nutz Plus / Nutz Banana';
		$needles           = array_merge($needles, array('nutz-plus', 'nutz-banana'));
	}

	$needles = array_values(array_unique(array_filter(array_map('sanitize_title', $needles))));

	$product_ids = function_exists('mad_baits_get_semantic_product_ids')
		? mad_baits_get_semantic_product_ids($needles, 14)
		: array();
	$product_ids = array_values(array_filter(array_map('absint', (array) $product_ids)));

	$products = array();
	foreach ($product_ids as $product_id) {
		$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		if (! $product) {
			continue;
		}
		$products[] = array(
			'id'    => $product_id,
			'title' => $product->get_name(),
			'url'   => $product->get_permalink(),
			'price' => wp_kses_post($product->get_price_html()),
			'image' => function_exists('mad_baits_get_product_card_image_url')
				? mad_baits_get_product_card_image_url((int) $product->get_id())
				: wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail'),
		);
		if (count($products) >= 5) {
			break;
		}
	}

	return array(
		'recommendations' => array(
			array('label' => __('Recommended Range', 'mad-baits'), 'value' => $recommended_range),
			array('label' => __('Recommended Hookbait', 'mad-baits'), 'value' => $hookbait),
			array('label' => __('Recommended Liquid', 'mad-baits'), 'value' => $liquid),
			array('label' => __('Recommended Pellet', 'mad-baits'), 'value' => $pellet),
			array('label' => __('Recommended Bundle', 'mad-baits'), 'value' => $bundle),
		),
		'products' => $products,
		'shopUrl'  => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
		'needles'  => $needles,
	);
}

/**
 * AJAX endpoint for the dedicated recommender page.
 *
 * @return void
 */
function mad_baits_ajax_water_finder_page_recommend() {
	check_ajax_referer('mad_baits_water_finder_page', 'nonce');

	$answers = isset($_POST['answers']) && is_array($_POST['answers']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? array_map('sanitize_text_field', wp_unslash($_POST['answers'])) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: array();

	wp_send_json_success(mad_baits_build_water_finder_page_payload($answers));
}
add_action('wp_ajax_mad_baits_water_finder_page_recommend', 'mad_baits_ajax_water_finder_page_recommend');
add_action('wp_ajax_nopriv_mad_baits_water_finder_page_recommend', 'mad_baits_ajax_water_finder_page_recommend');
