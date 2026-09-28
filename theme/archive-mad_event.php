<?php
/**
 * Events archive / hub.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="site-main page-main page-main--events">
	<?php Mad_Events_Render::render_hub(); ?>
</main>
<?php
get_footer();
