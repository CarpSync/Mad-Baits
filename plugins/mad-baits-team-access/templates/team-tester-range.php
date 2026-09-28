<?php
/**
 * Team & Tester Range page template (shortcode).
 *
 * @var string       $state    guest|denied|eligible
 * @var WC_Product[] $products
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

$shop_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$login_url  = wp_login_url(MBTA_Team_Tester_Range::get_page_url());
$badge_role = MBTA_Roles::get_badge_label();
?>
<section class="mbta-range-page">
	<header class="mbta-range-page__hero">
		<p class="mbta-range-page__eyebrow"><?php esc_html_e('Mad Baits Private', 'mad-baits-team-access'); ?></p>
		<h1><?php esc_html_e('Team & Tester Range', 'mad-baits-team-access'); ?></h1>
		<?php if ('eligible' === $state && $badge_role) : ?>
			<span class="mbta-role-badge"><?php echo esc_html($badge_role); ?></span>
		<?php endif; ?>
	</header>

	<?php if ('guest' === $state) : ?>
		<div class="mbta-range-page__state mbta-range-page__state--locked">
			<h2><?php esc_html_e('Log in to view Team & Tester products', 'mad-baits-team-access'); ?></h2>
			<p><?php esc_html_e('This range is exclusive to approved Mad Baits team and tester accounts.', 'mad-baits-team-access'); ?></p>
			<a class="mbta-btn mbta-btn--primary" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Log In', 'mad-baits-team-access'); ?></a>
		</div>
	<?php elseif ('denied' === $state) : ?>
		<div class="mbta-range-page__state mbta-range-page__state--denied">
			<h2><?php esc_html_e('You do not currently have access to this range', 'mad-baits-team-access'); ?></h2>
			<p><?php esc_html_e('If you believe you should have access, contact Mad Baits or use your team invite link.', 'mad-baits-team-access'); ?></p>
			<a class="mbta-btn mbta-btn--primary" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Shop Mad Baits', 'mad-baits-team-access'); ?></a>
		</div>
	<?php else : ?>
		<p class="mbta-range-page__intro"><?php esc_html_e('Exclusive Mad Baits products available to your account.', 'mad-baits-team-access'); ?></p>

		<?php if (empty($products)) : ?>
			<div class="mbta-range-page__state">
				<p><?php esc_html_e('No private products are currently available to your account.', 'mad-baits-team-access'); ?></p>
			</div>
		<?php else : ?>
			<ul class="mbta-range-page__grid">
				<?php foreach ($products as $product) : ?>
					<?php
					$product_id   = $product->get_id();
					$access_badge = MBTA_Roles::get_product_access_badge_for_user($product_id);
					?>
					<li class="mbta-range-card">
						<a class="mbta-range-card__image" href="<?php echo esc_url($product->get_permalink()); ?>">
							<?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
						<div class="mbta-range-card__body">
							<?php if ($access_badge) : ?>
								<span class="mbta-range-card__access"><?php echo esc_html($access_badge); ?></span>
							<?php endif; ?>
							<h2 class="mbta-range-card__title">
								<a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a>
							</h2>
							<p class="mbta-range-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></p>
							<?php if ($product->is_in_stock()) : ?>
								<span class="mbta-range-card__stock mbta-range-card__stock--in"><?php esc_html_e('In stock', 'mad-baits-team-access'); ?></span>
							<?php else : ?>
								<span class="mbta-range-card__stock mbta-range-card__stock--out"><?php esc_html_e('Out of stock', 'mad-baits-team-access'); ?></span>
							<?php endif; ?>
							<div class="mbta-range-card__actions">
								<?php if ($product->is_purchasable() && $product->is_in_stock()) : ?>
									<a class="mbta-btn mbta-btn--primary mbta-range-card__atc" href="<?php echo esc_url($product->add_to_cart_url()); ?>"><?php esc_html_e('Add to Basket', 'mad-baits-team-access'); ?></a>
								<?php endif; ?>
								<a class="mbta-btn mbta-range-card__view" href="<?php echo esc_url($product->get_permalink()); ?>"><?php esc_html_e('View Product', 'mad-baits-team-access'); ?></a>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</section>
