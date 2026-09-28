<?php
/**
 * Social profiles, community feed, share CTAs, and product social proof.
 *
 * Configure URLs:
 * add_filter( 'mad_baits_social_profiles', function ( $profiles ) {
 *     $profiles['instagram']['url'] = 'https://www.instagram.com/madbaits/';
 *     $profiles['facebook']['url']  = 'https://www.facebook.com/madbaits/';
 *     return $profiles;
 * } );
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Optional URLs from wp_options (set via WP-CLI or custom admin field).
 *
 * @param array<string, array{label:string, url:string, handle:string}> $profiles Profiles.
 * @return array<string, array{label:string, url:string, handle:string}>
 */
function mad_baits_social_profiles_from_options($profiles) {
	$option_map = array(
		'instagram' => 'mad_baits_social_instagram_url',
		'facebook'  => 'mad_baits_social_facebook_url',
		'tiktok'    => 'mad_baits_social_tiktok_url',
	);

	foreach ($option_map as $network => $option_name) {
		$url = get_option($option_name, '');
		if (is_string($url) && '' !== $url && isset($profiles[ $network ])) {
			$profiles[ $network ]['url'] = esc_url_raw($url);
		}
	}

	return $profiles;
}
add_filter('mad_baits_social_profiles', 'mad_baits_social_profiles_from_options', 5);

/**
 * Registered social networks (extensible).
 *
 * @return array<string, array{label:string, url:string, handle:string}>
 */
function mad_baits_get_social_profiles() {
	$defaults = array(
		'instagram' => array(
			'label'  => __('Instagram', 'mad-baits'),
			'url'    => '',
			'handle' => '',
		),
		'facebook'  => array(
			'label'  => __('Facebook', 'mad-baits'),
			'url'    => '',
			'handle' => '',
		),
		'tiktok'    => array(
			'label'  => __('TikTok', 'mad-baits'),
			'url'    => '',
			'handle' => '',
		),
	);

	$profiles = apply_filters('mad_baits_social_profiles', $defaults);

	foreach ($profiles as $key => $profile) {
		if (! is_array($profile)) {
			unset($profiles[ $key ]);
			continue;
		}
		$profiles[ $key ]['url']    = esc_url_raw((string) ($profile['url'] ?? ''));
		$profiles[ $key ]['label']  = (string) ($profile['label'] ?? ucfirst($key));
		$profiles[ $key ]['handle'] = sanitize_text_field((string) ($profile['handle'] ?? ''));
	}

	return $profiles;
}

/**
 * Profiles with a non-empty URL.
 *
 * @return array<string, array{label:string, url:string, handle:string}>
 */
function mad_baits_get_active_social_profiles() {
	return array_filter(
		mad_baits_get_social_profiles(),
		static function ($profile) {
			return ! empty($profile['url']);
		}
	);
}

/**
 * SVG icon for a network.
 *
 * @param string $network Network key.
 * @return string
 */
function mad_baits_social_icon_svg($network) {
	$icons = array(
		'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
		'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
		'tiktok'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.27 6.27 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.76a4.85 4.85 0 0 1-1.01-.07z"/></svg>',
	);

	$svg = $icons[ $network ] ?? $icons['instagram'];

	return apply_filters('mad_baits_social_icon_svg', $svg, $network);
}

/**
 * Render social link list.
 *
 * @param string $context CSS modifier: footer, mobile, compact, product.
 * @return void
 */
function mad_baits_render_social_links($context = 'footer') {
	$profiles = mad_baits_get_active_social_profiles();
	if (empty($profiles)) {
		return;
	}

	$classes = 'mad-social-links mad-social-links--' . sanitize_html_class($context);
	echo '<nav class="' . esc_attr($classes) . '" aria-label="' . esc_attr__('Follow Mad Baits', 'mad-baits') . '">';
	echo '<ul class="mad-social-links__list">';
	foreach ($profiles as $key => $profile) {
		printf(
			'<li><a class="mad-social-links__item mad-social-links__item--%1$s" href="%2$s" target="_blank" rel="noopener noreferrer" aria-label="%3$s">%4$s<span class="screen-reader-text">%3$s</span></a></li>',
			esc_attr($key),
			esc_url($profile['url']),
			esc_attr($profile['label']),
			mad_baits_social_icon_svg($key) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
	echo '</ul></nav>';
}

/**
 * Follow CTA strip (footer / account / thank-you).
 *
 * @param string $context Context slug.
 * @return void
 */
function mad_baits_render_social_follow_cta($context = 'footer') {
	$profiles = mad_baits_get_active_social_profiles();
	if (empty($profiles)) {
		return;
	}

	$primary = reset($profiles);
	?>
	<div class="mad-social-cta mad-social-cta--<?php echo esc_attr(sanitize_html_class($context)); ?>">
		<p class="mad-social-cta__eyebrow"><?php esc_html_e('Join the community', 'mad-baits'); ?></p>
		<p class="mad-social-cta__title"><?php esc_html_e('Tag us in your catches — we love resharing anglers on the bank.', 'mad-baits'); ?></p>
		<div class="mad-social-cta__actions">
			<?php mad_baits_render_social_links($context); ?>
			<?php if (! empty($primary['url'])) : ?>
				<a class="mad-btn mad-btn--outline mad-social-cta__primary" href="<?php echo esc_url($primary['url']); ?>" target="_blank" rel="noopener noreferrer">
					<?php
					printf(
						/* translators: %s: social network name */
						esc_html__('Follow on %s', 'mad-baits'),
						esc_html($primary['label'])
					);
					?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Normalised community feed items from catch reports.
 *
 * @param int $limit Max items.
 * @return array<int, array<string, mixed>>
 */
function mad_baits_get_community_feed_items($limit = 8) {
	$limit = max(1, min(20, absint($limit)));

	if (! function_exists('mad_baits_get_recent_catch_report_ids')) {
		return array();
	}

	$ids   = mad_baits_get_recent_catch_report_ids($limit);
	$items = array();

	foreach ($ids as $post_id) {
		$post_id = absint($post_id);
		if ($post_id < 1) {
			continue;
		}

		$products        = function_exists('mad_baits_get_catch_report_products_used')
			? mad_baits_get_catch_report_products_used($post_id)
			: array();
		$primary_product = ! empty($products) ? $products[0] : null;

		$items[] = array(
			'id'           => $post_id,
			'title'        => get_the_title($post_id),
			'permalink'    => get_permalink($post_id),
			'image_url'    => get_the_post_thumbnail_url($post_id, 'medium_large') ?: '',
			'fish_weight'  => function_exists('mad_baits_get_catch_report_meta')
				? mad_baits_get_catch_report_meta($post_id, 'fish_weight')
				: '',
			'venue'        => function_exists('mad_baits_get_catch_report_meta')
				? mad_baits_get_catch_report_meta($post_id, 'venue')
				: '',
			'bait_used'    => function_exists('mad_baits_get_catch_report_meta')
				? mad_baits_get_catch_report_meta($post_id, 'bait_used')
				: '',
			'product_id'   => $primary_product ? $primary_product->get_id() : 0,
			'product_url'  => $primary_product ? $primary_product->get_permalink() : '',
			'product_name' => $primary_product ? $primary_product->get_name() : '',
			'share_title'  => sprintf(
				/* translators: %s: catch title */
				__('%s — Mad Baits Catch', 'mad-baits'),
				get_the_title($post_id)
			),
		);
	}

	return apply_filters('mad_baits_community_feed_items', $items, $limit);
}

/**
 * Render a community feed card.
 *
 * @param array<string, mixed> $item Feed item.
 * @return void
 */
function mad_baits_render_community_feed_card($item) {
	if (empty($item['id'])) {
		return;
	}

	$image_url = (string) ($item['image_url'] ?? '');
	$venue     = (string) ($item['venue'] ?? '');
	$weight    = (string) ($item['fish_weight'] ?? '');
	$bait      = (string) ($item['bait_used'] ?? '');
	?>
	<article class="mad-community-card">
		<a class="mad-community-card__media" href="<?php echo esc_url($item['permalink']); ?>">
			<?php if ($image_url) : ?>
				<img src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy" decoding="async" />
			<?php else : ?>
				<span class="mad-community-card__placeholder" aria-hidden="true"></span>
			<?php endif; ?>
		</a>
		<div class="mad-community-card__body">
			<h3 class="mad-community-card__title">
				<a href="<?php echo esc_url($item['permalink']); ?>"><?php echo esc_html($item['title']); ?></a>
			</h3>
			<?php if ($weight || $venue) : ?>
				<p class="mad-community-card__meta">
					<?php
					if ($weight) {
						echo esc_html($weight);
					}
					if ($weight && $venue) {
						echo ' · ';
					}
					if ($venue) {
						echo esc_html($venue);
					}
					?>
				</p>
			<?php endif; ?>
			<?php if ($bait) : ?>
				<span class="mad-community-card__chip"><?php echo esc_html($bait); ?></span>
			<?php endif; ?>
			<div class="mad-community-card__actions">
				<?php if (! empty($item['product_url'])) : ?>
					<a class="mad-community-card__shop" href="<?php echo esc_url($item['product_url']); ?>">
						<?php
						printf(
							/* translators: %s: product name */
							esc_html__('Shop %s', 'mad-baits'),
							esc_html($item['product_name'])
						);
						?>
					</a>
				<?php endif; ?>
				<button type="button" class="mad-share-btn" data-mad-share
					data-share-title="<?php echo esc_attr($item['share_title']); ?>"
					data-share-url="<?php echo esc_url($item['permalink']); ?>"
					data-share-text="<?php echo esc_attr__('Check out this catch on Mad Baits', 'mad-baits'); ?>">
					<?php esc_html_e('Share', 'mad-baits'); ?>
				</button>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Community feed section (homepage / dedicated blocks).
 *
 * @param array<string, mixed> $args Section args.
 * @return void
 */
function mad_baits_render_community_feed_section($args = array()) {
	$args = wp_parse_args(
		$args,
		array(
			'title'    => __('Mad Baits on the bank', 'mad-baits'),
			'subtitle' => __('Real catches from anglers using our baits.', 'mad-baits'),
			'limit'    => 8,
			'class'    => '',
		)
	);

	$items = mad_baits_get_community_feed_items((int) $args['limit']);
	if (empty($items)) {
		return;
	}

	$section_class = 'mad-community-feed';
	if (! empty($args['class'])) {
		$section_class .= ' ' . sanitize_html_class($args['class']);
	}
	?>
	<section class="<?php echo esc_attr($section_class); ?>">
		<header class="mad-community-feed__header">
			<h2 class="mad-community-feed__title"><?php echo esc_html($args['title']); ?></h2>
			<?php if (! empty($args['subtitle'])) : ?>
				<p class="mad-community-feed__subtitle"><?php echo esc_html($args['subtitle']); ?></p>
			<?php endif; ?>
			<?php mad_baits_render_social_links('compact'); ?>
		</header>
		<div class="mad-community-feed__rail">
			<?php foreach ($items as $item) : ?>
				<?php mad_baits_render_community_feed_card($item); ?>
			<?php endforeach; ?>
		</div>
		<?php if (get_post_type_archive_link('catch_report')) : ?>
			<p class="mad-community-feed__more">
				<a href="<?php echo esc_url(get_post_type_archive_link('catch_report')); ?>"><?php esc_html_e('View all catches', 'mad-baits'); ?></a>
			</p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Catch reports linked to a product.
 *
 * @param int $product_id Product ID.
 * @param int $limit Max results.
 * @return array<int, int> Post IDs.
 */
function mad_baits_get_catches_for_product($product_id, $limit = 4) {
	$product_id = absint($product_id);
	$limit      = max(1, min(12, absint($limit)));

	if ($product_id < 1 || ! function_exists('mad_baits_get_recent_catch_report_ids')) {
		return array();
	}

	$candidate_ids = mad_baits_get_recent_catch_report_ids(30);
	$matched         = array();

	foreach ($candidate_ids as $catch_id) {
		$catch_id = absint($catch_id);
		if ($catch_id < 1) {
			continue;
		}

		$products = function_exists('mad_baits_get_catch_report_products_used')
			? mad_baits_get_catch_report_products_used($catch_id)
			: array();

		foreach ($products as $product) {
			if ($product && (int) $product->get_id() === $product_id) {
				$matched[] = $catch_id;
				break;
			}
		}

		if (count($matched) >= $limit) {
			break;
		}
	}

	return apply_filters('mad_baits_catches_for_product', $matched, $product_id, $limit);
}

/**
 * Product page social proof block.
 *
 * @return void
 */
function mad_baits_render_product_social_proof() {
	if (! function_exists('is_product') || ! is_product()) {
		return;
	}

	global $product;
	if (! $product instanceof WC_Product) {
		return;
	}

	$catch_ids = mad_baits_get_catches_for_product($product->get_id(), 4);
	if (empty($catch_ids)) {
		return;
	}
	?>
	<section class="mad-product-social-proof" aria-labelledby="mad-product-social-proof-title">
		<h2 id="mad-product-social-proof-title" class="mad-product-social-proof__title">
			<?php esc_html_e('Anglers catching on this bait', 'mad-baits'); ?>
		</h2>
		<div class="mad-product-social-proof__grid">
			<?php
			foreach ($catch_ids as $catch_id) {
				if (function_exists('mad_baits_render_catch_report_card')) {
					mad_baits_render_catch_report_card($catch_id, array('class' => 'mad-catch-card--compact'));
				}
			}
			?>
		</div>
		<?php mad_baits_render_social_follow_cta('product'); ?>
	</section>
	<?php
}

/**
 * Share button attributes helper for products.
 *
 * @param WC_Product $product Product.
 * @return string HTML attributes.
 */
function mad_baits_product_share_attributes($product) {
	if (! $product instanceof WC_Product) {
		return '';
	}

	return sprintf(
		'data-mad-share data-share-title="%s" data-share-url="%s" data-share-text="%s"',
		esc_attr($product->get_name()),
		esc_url(get_permalink($product->get_id())),
		esc_attr__('Check this out on Mad Baits', 'mad-baits')
	);
}

/**
 * Enqueue social assets.
 *
 * @return void
 */
function mad_baits_social_enqueue_assets() {
	if (is_admin()) {
		return;
	}

	$theme_version = wp_get_theme()->get('Version');
	$base_uri      = get_template_directory_uri();

	$social_css_path = get_theme_file_path('assets/css/scoped/social.css');
	$social_js_path  = get_theme_file_path('assets/js/social-integration.js');
	$social_css_ver  = file_exists($social_css_path) ? (string) filemtime($social_css_path) : $theme_version;
	$social_js_ver   = file_exists($social_js_path) ? (string) filemtime($social_js_path) : $theme_version;

	wp_enqueue_style(
		'mad-baits-social',
		$base_uri . '/assets/css/scoped/social.css',
		array('mad-baits-main'),
		$social_css_ver
	);

	wp_enqueue_script(
		'mad-baits-social',
		$base_uri . '/assets/js/social-integration.js',
		array('jquery'),
		$social_js_ver,
		true
	);

	wp_localize_script(
		'mad-baits-social',
		'madBaitsSocial',
		array(
			'i18nShareCopied' => __('Link copied — paste anywhere to share.', 'mad-baits'),
			'i18nShareFailed' => __('Could not share right now. Try copying the link.', 'mad-baits'),
		)
	);
}
add_action('wp_enqueue_scripts', 'mad_baits_social_enqueue_assets', 35);

/**
 * Share button on single product summary.
 *
 * @return void
 */
function mad_baits_render_product_share_button() {
	global $product;
	if (! $product instanceof WC_Product) {
		return;
	}
	?>
	<p class="mad-product-share-wrap">
		<button type="button" class="mad-share-btn mad-product-share" <?php echo mad_baits_product_share_attributes($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php esc_html_e('Share this bait', 'mad-baits'); ?>
		</button>
	</p>
	<?php
}
add_action('woocommerce_single_product_summary', 'mad_baits_render_product_share_button', 45);

/** Template hooks */
add_action('mad_baits_footer_social', 'mad_baits_render_social_follow_cta', 10);
add_action('woocommerce_after_single_product_summary', 'mad_baits_render_product_social_proof', 18);
add_action('woocommerce_thankyou', static function () {
	mad_baits_render_social_follow_cta('thankyou');
}, 25);

/**
 * Inject social links into mobile menu if theme fires hook.
 *
 * @return void
 */
function mad_baits_mobile_menu_social() {
	echo '<div class="mad-mobile-menu-social">';
	mad_baits_render_social_follow_cta('mobile');
	echo '</div>';
}
add_action('mad_baits_mobile_nav_after', 'mad_baits_mobile_menu_social', 15);

/**
 * Cart drawer / mini-cart social nudge.
 *
 * @return void
 */
function mad_baits_cart_social_nudge() {
	$profiles = mad_baits_get_active_social_profiles();
	if (empty($profiles)) {
		return;
	}
	?>
	<div class="mad-cart-social-nudge">
		<p><?php esc_html_e('Show us your session — tag @madbaits for a chance to feature.', 'mad-baits'); ?></p>
		<?php mad_baits_render_social_links('compact'); ?>
	</div>
	<?php
}
add_action('woocommerce_after_mini_cart', 'mad_baits_cart_social_nudge', 30);
