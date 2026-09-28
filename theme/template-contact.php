<?php
/**
 * Template Name: Contact
 * Description: Premium contact page with support and form area.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

$contact = function_exists('mad_baits_get_contact_details') ? mad_baits_get_contact_details() : array();
$phone_display = isset($contact['phone_display']) ? (string) $contact['phone_display'] : '01480 470862';
$phone_tel     = isset($contact['phone_tel']) ? (string) $contact['phone_tel'] : '+441480470862';
$email         = isset($contact['email']) ? sanitize_email((string) $contact['email']) : 'mark@madbaits.com';
$collection_heading = isset($contact['collection_heading']) ? (string) $contact['collection_heading'] : __('Collecting an order?', 'mad-baits');
$collection_address = isset($contact['collection_address']) && is_array($contact['collection_address']) ? $contact['collection_address'] : array();
$collection_hours   = isset($contact['collection_hours']) ? (string) $contact['collection_hours'] : '';

?>

<?php if (function_exists('mad_baits_render_page_hero')) : ?>
	<?php mad_baits_render_page_hero(array('post_id' => 0, 'slug' => 'contact', 'title' => get_the_title())); ?>
<?php endif; ?>

<section class="section section--page-content">
	<div class="container">
		<div class="contact-layout">
			<div class="entry-content-wrap mb-content-card mad-contact-details">
				<h2><?php esc_html_e('Get In Touch', 'mad-baits'); ?></h2>
				<ul class="mad-contact-details__list">
					<li>
						<span class="mad-contact-details__label"><?php esc_html_e('Call', 'mad-baits'); ?></span>
						<a href="<?php echo esc_url('tel:' . preg_replace('/\s+/', '', $phone_tel)); ?>"><?php echo esc_html($phone_display); ?></a>
					</li>
					<li>
						<span class="mad-contact-details__label"><?php esc_html_e('Email', 'mad-baits'); ?></span>
						<a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a>
					</li>
				</ul>

				<div class="mad-contact-details__collection">
					<h3><?php echo esc_html($collection_heading); ?></h3>
					<?php if (! empty($collection_address)) : ?>
						<address class="mad-contact-details__address">
							<?php foreach ($collection_address as $address_line) : ?>
								<?php if (is_string($address_line) && '' !== trim($address_line)) : ?>
									<span><?php echo esc_html($address_line); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</address>
					<?php endif; ?>
					<p class="mad-contact-details__phone">
						<a href="<?php echo esc_url('tel:' . preg_replace('/\s+/', '', $phone_tel)); ?>"><?php echo esc_html($phone_display); ?></a>
					</p>
					<?php if ('' !== $collection_hours) : ?>
						<p class="mad-contact-details__hours"><?php echo esc_html($collection_hours); ?></p>
					<?php endif; ?>
				</div>

				<div class="mad-contact-details__social">
					<h3><?php esc_html_e('Follow Mad Baits', 'mad-baits'); ?></h3>
					<ul class="mad-list">
						<li><?php esc_html_e('Instagram: @madbaits', 'mad-baits'); ?></li>
						<li><?php esc_html_e('Facebook: @madbaits', 'mad-baits'); ?></li>
						<li><?php esc_html_e('YouTube: @madbaitstv', 'mad-baits'); ?></li>
					</ul>
				</div>
			</div>
			<div class="entry-content-wrap mb-content-card">
				<h2><?php esc_html_e('Send a Message', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('We usually respond within one business day.', 'mad-baits'); ?></p>
				<div class="entry-content">
					<?php echo do_shortcode('[mad_baits_contact_form]'); ?>
				</div>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
