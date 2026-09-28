<?php
/**
 * Branded wp-login.php screen for Mad Baits.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Resolve login logo URL (prefers yellow mark, falls back to cup SVG).
 *
 * @return string
 */
function mad_baits_get_login_logo_url() {
	$candidates = array(
		'assets/img/mad-yellow-logo-1.png',
		'assets/img/CUP-LOGOv2-1.svg',
	);

	foreach ($candidates as $relative_path) {
		if (file_exists(get_theme_file_path($relative_path))) {
			return get_theme_file_uri($relative_path);
		}
	}

	return get_theme_file_uri('assets/img/CUP-LOGOv2-1.svg');
}

/**
 * Optional subtle background image for login.
 *
 * @return string
 */
function mad_baits_get_login_background_image_url() {
	$candidates = array(
		'assets/img/jerry-sunset-cast.png',
		'assets/img/HAMMONDAPRIL_22_031-scene.jpg',
		'assets/img/HAMMONDAPRIL_22_031.jpg',
	);

	foreach ($candidates as $relative_path) {
		if (file_exists(get_theme_file_path($relative_path))) {
			return get_theme_file_uri($relative_path);
		}
	}

	return '';
}

/**
 * Enqueue login screen styles.
 *
 * @return void
 */
function mad_baits_enqueue_login_styles() {
	$css_path = get_theme_file_path('assets/css/scoped/wp-login.css');
	if (! file_exists($css_path)) {
		return;
	}

	wp_enqueue_style(
		'mad-baits-wp-login',
		get_theme_file_uri('assets/css/scoped/wp-login.css'),
		array(),
		(string) filemtime($css_path)
	);

	$bg_image = mad_baits_get_login_background_image_url();
	$logo_url = mad_baits_get_login_logo_url();

	$inline_rules = array();
	if ($bg_image) {
		$inline_rules[] = sprintf(
			'body.login{--mb-login-bg-image:url("%s");}',
			esc_url_raw($bg_image)
		);
	}
	if ($logo_url) {
		$inline_rules[] = sprintf(
			'body.login #login h1 a{background-image:url("%s") !important;}',
			esc_url_raw($logo_url)
		);
	}

	if (! empty($inline_rules)) {
		wp_add_inline_style('mad-baits-wp-login', implode('', $inline_rules));
	}
}
add_action('login_enqueue_scripts', 'mad_baits_enqueue_login_styles');

/**
 * Login logo link target.
 *
 * @return string
 */
function mad_baits_login_header_url() {
	return home_url('/');
}
add_filter('login_headerurl', 'mad_baits_login_header_url');

/**
 * Login logo link title.
 *
 * @return string
 */
function mad_baits_login_header_text() {
	return get_bloginfo('name');
}
add_filter('login_headertext', 'mad_baits_login_header_text');

/**
 * Output a visible logo image above the login form (fallback if CSS background fails).
 *
 * @return void
 */
function mad_baits_login_logo_markup() {
	$logo_url = mad_baits_get_login_logo_url();
	if (! $logo_url) {
		return;
	}
	?>
	<div class="mad-login-logo-wrap">
		<a class="mad-login-logo-wrap__link" href="<?php echo esc_url(home_url('/')); ?>">
			<img
				class="mad-login-logo-wrap__img"
				src="<?php echo esc_url($logo_url); ?>"
				alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
				width="220"
				height="100"
				decoding="async"
			/>
		</a>
	</div>
	<?php
}
add_action('login_header', 'mad_baits_login_logo_markup', 20);

/**
 * Footer link back to the storefront.
 *
 * @return void
 */
function mad_baits_login_footer_markup() {
	$shop_url = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	?>
	<div class="mad-login-footer">
		<a class="mad-login-footer__link" href="<?php echo esc_url(home_url('/')); ?>">
			<?php esc_html_e('Back to Mad Baits', 'mad-baits'); ?>
		</a>
		<a class="mad-login-footer__link mad-login-footer__link--ghost" href="<?php echo esc_url($shop_url); ?>">
			<?php esc_html_e('Visit the shop', 'mad-baits'); ?>
		</a>
	</div>
	<p class="mad-login-credit">
		<?php esc_html_e('Managed by', 'mad-baits'); ?>
		<a href="<?php echo esc_url('https://karpstudio.co.uk'); ?>" target="_blank" rel="noopener noreferrer">Karp Studio</a>
	</p>
	<?php
}
add_action('login_footer', 'mad_baits_login_footer_markup');
