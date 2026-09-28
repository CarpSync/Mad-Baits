<?php
/**
 * Single event template.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$event_id = 0;
$queried  = get_queried_object();
if ($queried instanceof WP_Post && 'mad_event' === $queried->post_type) {
	$event_id = (int) $queried->ID;
} elseif ('mad_event' === get_post_type()) {
	$event_id = (int) get_the_ID();
}
$event = Mad_Events_Data::get_event($event_id);
?>
<main id="primary" class="site-main page-main page-main--event-single">
	<?php
	if ($event) {
		Mad_Events_Render::render_single($event);
	} else {
		echo '<div class="container"><p class="section__empty-state">' . esc_html__('Event not found.', 'mad-baits') . '</p></div>';
	}
	?>
</main>
<?php
get_footer();
