<?php
/**
 * Template Name: FAQ
 * Description: FAQ page with premium accordion blocks.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

get_header();

?>

<?php if (function_exists('mad_baits_render_page_hero')) : ?>
	<?php mad_baits_render_page_hero(array('post_id' => (int) get_queried_object_id(), 'slug' => 'faq', 'title' => get_the_title())); ?>
<?php endif; ?>

<section class="section section--page-content">
	<div class="container container--narrow">
		<div class="entry-content-wrap mb-content-card">
			<div class="faq-accordion">
				<details>
					<summary><?php esc_html_e('How long does delivery take?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Standard UK delivery usually arrives within 2-4 working days after dispatch.', 'mad-baits'); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e('How should bait be stored?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Store bait in cool, dry conditions and follow product-specific guidance on each listing.', 'mad-baits'); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e('Do you offer bundles?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Yes, we regularly offer session bundles and campaign packages in our Bundle Deals category.', 'mad-baits'); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e('Can I track my order?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Tracking details are sent when your order is dispatched, where applicable.', 'mad-baits'); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e('Are hookbaits ready to use?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Most hookbaits are ready to fish straight from pack, with prep guidance on the product page.', 'mad-baits'); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e('Do you ship throughout the UK?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Yes, we ship across the UK. Regional exceptions or surcharges may apply.', 'mad-baits'); ?></p>
				</details>
				<details>
					<summary><?php esc_html_e('What if my order arrives damaged?', 'mad-baits'); ?></summary>
					<p><?php esc_html_e('Contact us as soon as possible with photos and your order number so we can resolve it quickly.', 'mad-baits'); ?></p>
				</details>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();

