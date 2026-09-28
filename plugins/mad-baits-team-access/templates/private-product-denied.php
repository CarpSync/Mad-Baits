<?php
/**
 * Private product access denied.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="content" class="site-main mbta-private-denied">
	<div class="container">
		<section class="mbta-private-denied__card">
			<p class="mbta-private-denied__eyebrow"><?php esc_html_e('Mad Baits Private', 'mad-baits-team-access'); ?></p>
			<h1><?php esc_html_e('This product is private', 'mad-baits-team-access'); ?></h1>
			<p><?php esc_html_e('You need team, RNT, ambassador or tester access to view this bait. Log in with your private account or use your invite link.', 'mad-baits-team-access'); ?></p>
			<p>
				<a class="mbta-btn mbta-btn--primary" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"><?php esc_html_e('Log in', 'mad-baits-team-access'); ?></a>
				<a class="mbta-btn" href="<?php echo esc_url(home_url('/team-invite/')); ?>"><?php esc_html_e('Have an invite?', 'mad-baits-team-access'); ?></a>
			</p>
		</section>
	</div>
</main>
<?php
get_footer();
