<?php
/**
 * Team portal template (included from shortcode).
 *
 * @var string       $role
 * @var WC_Product[] $products
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

$badge = MBTA_Roles::get_badge_label();
$groups = MBTA_Roles::get_access_groups();
?>
<section class="mbta-portal">
	<header class="mbta-portal__hero">
		<p class="mbta-portal__eyebrow"><?php esc_html_e('Mad Baits Private', 'mad-baits-team-access'); ?></p>
		<h1><?php esc_html_e('Team Portal', 'mad-baits-team-access'); ?></h1>
		<?php if ($badge) : ?>
			<span class="mbta-role-badge mbta-role-badge--<?php echo esc_attr(sanitize_html_class($role)); ?>"><?php echo esc_html($badge); ?></span>
		<?php endif; ?>
	</header>

	<div class="mbta-portal__grid">
		<aside class="mbta-portal__aside">
			<?php if (in_array($role, array(MBTA_Roles::ROLE_TEAM, MBTA_Roles::ROLE_RNT, MBTA_Roles::ROLE_TESTER), true)) : ?>
				<section class="mbta-portal__panel">
					<h2><?php esc_html_e('Announcements', 'mad-baits-team-access'); ?></h2>
					<p><?php esc_html_e('Private drops, test bait updates and team news appear here and via push alerts.', 'mad-baits-team-access'); ?></p>
				</section>
			<?php endif; ?>

			<?php if (MBTA_Roles::ROLE_AMBASSADOR === $role) : ?>
				<section class="mbta-portal__panel">
					<h2><?php esc_html_e('Ambassador assets', 'mad-baits-team-access'); ?></h2>
					<p><?php esc_html_e('Your ambassador pricing is handled by Discount Rules Pro. Use your role badge at checkout.', 'mad-baits-team-access'); ?></p>
				</section>
			<?php endif; ?>

			<?php if (in_array($role, array(MBTA_Roles::ROLE_TESTER, MBTA_Roles::ROLE_TEAM), true)) : ?>
				<section class="mbta-portal__panel">
					<h2><?php esc_html_e('Testing feedback', 'mad-baits-team-access'); ?></h2>
					<p><?php esc_html_e('Log bait performance notes and report issues to the Mad Baits team.', 'mad-baits-team-access'); ?></p>
				</section>
			<?php endif; ?>

			<section class="mbta-portal__panel mbta-portal__push">
				<h2><?php esc_html_e('Private notifications', 'mad-baits-team-access'); ?></h2>
				<p><?php esc_html_e('Get alerts when new test baits or private products drop.', 'mad-baits-team-access'); ?></p>
				<button type="button" class="mbta-btn mbta-btn--primary" id="mbta-enable-push"><?php esc_html_e('Enable Mad Baits private notifications', 'mad-baits-team-access'); ?></button>
				<button type="button" class="mbta-btn" id="mbta-disable-push"><?php esc_html_e('Turn off notifications', 'mad-baits-team-access'); ?></button>
				<p class="mbta-portal__push-status" id="mbta-push-status" aria-live="polite"></p>
			</section>
		</aside>

		<div class="mbta-portal__main">
			<h2><?php esc_html_e('Your private products', 'mad-baits-team-access'); ?></h2>
			<?php if (empty($products)) : ?>
				<p><?php esc_html_e('No private products are available for your role yet.', 'mad-baits-team-access'); ?></p>
			<?php else : ?>
				<ul class="mbta-portal__products">
					<?php foreach ($products as $product) : ?>
						<li class="mbta-portal__product">
							<a href="<?php echo esc_url($product->get_permalink()); ?>">
								<?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="mbta-portal__product-title"><?php echo esc_html($product->get_name()); ?></span>
								<span class="mbta-portal__product-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
