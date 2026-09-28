<?php
/**
 * Team invite registration page template.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="content" class="site-main mbta-invite-page">
	<div class="container">
		<?php echo do_shortcode('[mbta_team_invite]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</main>
<?php
get_footer();
