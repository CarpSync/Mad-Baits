<?php
/**
 * Homepage Mad Baits app promotion section.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Resolve PWA app icon URL for marketing surfaces.
 *
 * @return string
 */
function mad_baits_get_app_icon_url() {
	if (! function_exists('mad_baits_get_pwa_icon_uri')) {
		return '';
	}

	$candidates = array(
		'assets/img/icons/madbaits-app-icon.png',
		'assets/img/icons/app-icon-192.png',
		'assets/img/icons/app-icon-maskable-192.png',
		'assets/img/icons/icon-192.png',
		'assets/img/icons/icon-maskable-192.png',
	);

	foreach ($candidates as $relative_path) {
		if (file_exists(get_theme_file_path($relative_path))) {
			return mad_baits_get_pwa_icon_uri($relative_path);
		}
	}

	return mad_baits_get_pwa_icon_uri('assets/img/icons/app-icon-192.png');
}

/**
 * Resolve app mockup screenshot for homepage phone preview.
 *
 * @return string
 */
function mad_baits_get_app_mockup_screenshot_url() {
	$candidates = array(
		'assets/img/app-mockup-bundle-deals.png',
		'assets/img/app-mockup-mobile.png',
	);

	foreach ($candidates as $relative_path) {
		if (file_exists(get_theme_file_path($relative_path))) {
			return get_theme_file_uri($relative_path);
		}
	}

	return '';
}

/**
 * Resolve Android / iOS platform badge image for app promo.
 *
 * @return string
 */
function mad_baits_get_app_platform_badges_url() {
	$candidates = array(
		'assets/img/app-platform-badges.png',
		'assets/img/app-platform-badges.webp',
	);

	foreach ($candidates as $relative_path) {
		if (file_exists(get_theme_file_path($relative_path))) {
			return get_theme_file_uri($relative_path);
		}
	}

	return '';
}

/**
 * Render homepage app install promo with phone mockup.
 *
 * @return void
 */
function mad_baits_render_home_app_promo() {
	$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');
	$app_icon         = mad_baits_get_app_icon_url();
	$mockup_shot      = mad_baits_get_app_mockup_screenshot_url();
	$section_styles = array();

	if ($mockup_shot) {
		$section_styles[] = "--home-app-promo-bg: url('" . esc_url_raw($mockup_shot) . "')";
	}

	$features = array(
		__('App-only exclusive discounts and early access offers', 'mad-baits'),
		__('Push alerts for new drops, restocks and bundle deals', 'mad-baits'),
		__('Faster repeat orders with your account and favourites', 'mad-baits'),
		__('Session tools, catch reports and more features rolling out', 'mad-baits'),
	);
	?>
	<section class="home-app-promo section section--tight" id="mad-baits-app" aria-labelledby="home-app-promo-title"<?php echo $section_styles ? ' style="' . esc_attr(implode('; ', $section_styles)) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="home-app-promo__overlay" aria-hidden="true"></div>
		<div class="container home-app-promo__container">
			<div class="home-app-promo__grid">
				<div class="home-app-promo__copy">
					<p class="section__kicker"><?php esc_html_e('Mad Baits Mobile App', 'mad-baits'); ?></p>
					<h2 id="home-app-promo-title"><?php esc_html_e('Now On Android & iPhone', 'mad-baits'); ?></h2>
					<p class="home-app-promo__lead">
						<?php esc_html_e('Install the Mad Baits app for a premium mobile shopping experience — exclusive in-app savings, quicker checkout and the latest bait intelligence in your pocket.', 'mad-baits'); ?>
					</p>
					<ul class="home-app-promo__features">
						<?php foreach ($features as $feature) : ?>
							<li><?php echo esc_html((string) $feature); ?></li>
						<?php endforeach; ?>
					</ul>
					<figure class="home-app-promo__platforms" aria-label="<?php esc_attr_e('Available on Android and iPhone', 'mad-baits'); ?>">
						<ul class="home-app-promo__platform-icons">
							<li>
								<span class="home-app-promo__platform-icon home-app-promo__platform-icon--android" aria-hidden="true">
									<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
										<path fill="currentColor" d="M17.6 9.48l1.19-2.07c.09-.17-.02-.38-.2-.38-.13 0-.23.06-.28.13l-1.22 2.11c-1.01-.46-2.14-.72-3.29-.72s-2.28.26-3.29.72L9.71 7.16c-.05-.07-.15-.13-.28-.13-.18 0-.29.21-.2.38l1.19 2.07C8.55 10.62 7.27 12.53 7 14.73h10c-.27-2.2-1.55-4.11-3.4-5.25zM11 11.5c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm2 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm2.5 5.5H8.5v-1h4.5v1z" />
									</svg>
								</span>
								<span class="screen-reader-text"><?php esc_html_e('Android', 'mad-baits'); ?></span>
							</li>
							<li>
								<span class="home-app-promo__platform-icon home-app-promo__platform-icon--ios" aria-hidden="true">
									<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
										<path fill="currentColor" d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z" />
									</svg>
								</span>
								<span class="screen-reader-text"><?php esc_html_e('iPhone and iPad', 'mad-baits'); ?></span>
							</li>
						</ul>
					</figure>
					<div class="home-app-promo__actions">
						<button type="button" class="mad-button" data-pwa-promo-install>
							<?php esc_html_e('Get The Mad Baits App', 'mad-baits'); ?>
						</button>
						<a class="mad-button mad-button--ghost" href="<?php echo esc_url($shop_url); ?>">
							<?php esc_html_e('Continue In Browser', 'mad-baits'); ?>
						</a>
					</div>
					<button type="button" class="home-app-promo__helper-link" data-pwa-promo-help>
						<?php esc_html_e('Can’t install? Show steps', 'mad-baits'); ?>
					</button>
					<p class="home-app-promo__hint" data-home-app-install-hint>
						<?php esc_html_e('Free to install. Android: use Google Chrome (menu, then Install app). iPhone: Share, then Add to Home Screen.', 'mad-baits'); ?>
					</p>
				</div>

				<div class="home-app-promo__device-wrap" aria-hidden="true">
					<div class="home-app-promo__glow"></div>
					<div class="home-app-promo__phone">
						<div class="home-app-promo__phone-notch"></div>
						<div class="home-app-promo__screen">
							<?php if ($mockup_shot) : ?>
								<img
									class="home-app-promo__screen-shot"
									src="<?php echo esc_url($mockup_shot); ?>"
									alt="<?php esc_attr_e('Mad Baits app showing bundle deals on mobile', 'mad-baits'); ?>"
									width="390"
									height="844"
									loading="lazy"
									decoding="async"
								/>
							<?php else : ?>
								<div class="home-app-promo__screen-fallback">
									<?php if ($app_icon) : ?>
										<img class="home-app-promo__app-icon" src="<?php echo esc_url($app_icon); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" />
									<?php endif; ?>
									<p><?php esc_html_e('Mad Baits App', 'mad-baits'); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php
}
