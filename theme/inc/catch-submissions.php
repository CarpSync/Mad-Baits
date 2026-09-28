<?php
/**
 * Catch upload / submission flow (pending moderation).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Resolve Submit Catch page URL.
 *
 * @return string
 */
function mad_baits_get_submit_catch_page_url() {
	if (function_exists('mad_baits_get_template_page_url')) {
		return mad_baits_get_template_page_url('template-submit-catch.php', 'submit-catch');
	}

	return home_url('/submit-catch/');
}

/**
 * Render submit catch form shortcode.
 *
 * @return string
 */
function mad_baits_submit_catch_form_shortcode() {
	$status = isset($_GET['catch_status']) ? sanitize_key(wp_unslash((string) $_GET['catch_status'])) : '';
	$user   = wp_get_current_user();
	$products = get_posts(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 120,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	ob_start();
	?>
	<section class="mad-catch-submit" data-catch-upload-form-wrap>
		<header class="mad-catch-submit__header">
			<p class="section__kicker"><?php esc_html_e('Community Catch Upload', 'mad-baits'); ?></p>
			<h2><?php esc_html_e('Submit Your Catch', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Share your result with the Mad Baits community. Submissions are reviewed before publishing in Catch Reports.', 'mad-baits'); ?></p>
		</header>

		<?php if ('success' === $status) : ?>
			<p class="mad-catch-submit__notice mad-catch-submit__notice--success"><?php esc_html_e('Thanks — your catch has been submitted and the Mad Baits media team has been notified.', 'mad-baits'); ?></p>
		<?php elseif ('error' === $status) : ?>
			<p class="mad-catch-submit__notice mad-catch-submit__notice--error"><?php esc_html_e('Could not submit catch. Please check required fields and try again.', 'mad-baits'); ?></p>
		<?php endif; ?>

		<form class="mad-catch-submit__form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" data-catch-report-form autocomplete="off">
			<input type="hidden" name="action" value="mad_baits_submit_catch" />
			<?php wp_nonce_field('mad_baits_submit_catch', 'mad_baits_submit_catch_nonce'); ?>
			<p class="mad-catch-submit__honeypot" aria-hidden="true">
				<label for="mad-catch-hp-website"><?php esc_html_e('Website', 'mad-baits'); ?></label>
				<input id="mad-catch-hp-website" type="text" name="catch_website" value="" tabindex="-1" autocomplete="off" />
			</p>

			<div class="mad-catch-submit__grid">
				<label>
					<span><?php esc_html_e('Angler Name', 'mad-baits'); ?></span>
					<input type="text" name="angler_name" value="<?php echo esc_attr($user->exists() ? $user->display_name : ''); ?>" required autocomplete="name" autocapitalize="words" />
				</label>
				<label>
					<span><?php esc_html_e('Email', 'mad-baits'); ?></span>
					<input type="email" name="angler_email" value="<?php echo esc_attr($user->exists() ? $user->user_email : ''); ?>" autocomplete="email" />
				</label>
				<label>
					<span><?php esc_html_e('Phone', 'mad-baits'); ?></span>
					<input type="tel" name="angler_phone" autocomplete="tel" />
				</label>
				<label>
					<span><?php esc_html_e('Fish Weight', 'mad-baits'); ?></span>
					<input type="text" name="fish_weight" required autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="text" placeholder="<?php esc_attr_e('e.g. 24lb 8oz', 'mad-baits'); ?>" />
				</label>
				<label>
					<span><?php esc_html_e('Bait Used', 'mad-baits'); ?></span>
					<input type="text" name="bait_used" required autocomplete="off" autocapitalize="words" />
				</label>
				<label>
					<span><?php esc_html_e('Hookbait', 'mad-baits'); ?></span>
					<input type="text" name="hookbait" autocomplete="off" autocapitalize="words" />
				</label>
				<label>
					<span><?php esc_html_e('Rig / approach', 'mad-baits'); ?></span>
					<input type="text" name="rig" autocomplete="off" autocapitalize="words" />
				</label>
				<label>
					<span><?php esc_html_e('Venue Type', 'mad-baits'); ?></span>
					<input type="text" name="venue_type" required autocomplete="off" autocapitalize="words" />
				</label>
				<label>
					<span><?php esc_html_e('Swim / peg', 'mad-baits'); ?></span>
					<input type="text" name="swim" autocomplete="off" autocapitalize="words" />
				</label>
				<label class="mad-catch-submit__notes">
					<span><?php esc_html_e('Session Notes', 'mad-baits'); ?></span>
					<textarea name="session_notes" rows="4" placeholder="<?php esc_attr_e('Weather, bites, rigs, feeding approach, key moments...', 'mad-baits'); ?>"></textarea>
				</label>
				<label class="mad-catch-submit__products">
					<span><?php esc_html_e('Products Used', 'mad-baits'); ?></span>
					<select name="products_used[]" multiple size="6">
						<?php foreach ((array) $products as $product_post) : ?>
							<?php if (! $product_post instanceof WP_Post) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<option value="<?php echo esc_attr((string) $product_post->ID); ?>">
								<?php echo esc_html((string) $product_post->post_title); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<small><?php esc_html_e('Select one or more baits/products used in this capture.', 'mad-baits'); ?></small>
				</label>
				<label class="mad-catch-submit__upload">
					<span><?php esc_html_e('Catch Photos', 'mad-baits'); ?></span>
					<input type="file" name="catch_images[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple data-catch-photo-input />
					<div class="mad-catch-submit__preview" data-catch-photo-preview hidden></div>
					<small><?php esc_html_e('Upload up to 3 images (JPG, PNG or WebP).', 'mad-baits'); ?></small>
				</label>
				<label class="mad-catch-submit__consent">
					<input type="checkbox" name="marketing_consent" value="1" />
					<span><?php esc_html_e('I give Mad Baits permission to use this catch report and photo on social media, the website and marketing.', 'mad-baits'); ?></span>
				</label>
			</div>

			<div class="mad-catch-submit__actions">
				<button class="mad-button" type="submit" data-catch-report-submit><?php esc_html_e('Submit Catch For Review', 'mad-baits'); ?></button>
			</div>
		</form>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode('mad_baits_submit_catch_form', 'mad_baits_submit_catch_form_shortcode');

/**
 * Handle frontend catch submission.
 *
 * @return void
 */
function mad_baits_handle_submit_catch() {
	$nonce = isset($_POST['mad_baits_submit_catch_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['mad_baits_submit_catch_nonce'])) : '';
	if (! wp_verify_nonce($nonce, 'mad_baits_submit_catch')) {
		wp_safe_redirect(add_query_arg('catch_status', 'error', mad_baits_get_submit_catch_page_url()));
		exit;
	}

	$honeypot = isset($_POST['catch_website']) ? trim((string) wp_unslash($_POST['catch_website'])) : '';
	if ('' !== $honeypot) {
		wp_safe_redirect(add_query_arg('catch_status', 'error', mad_baits_get_submit_catch_page_url()));
		exit;
	}

	$payload = array(
		'angler_name'       => isset($_POST['angler_name']) ? wp_unslash($_POST['angler_name']) : '',
		'angler_email'      => isset($_POST['angler_email']) ? wp_unslash($_POST['angler_email']) : '',
		'angler_phone'      => isset($_POST['angler_phone']) ? wp_unslash($_POST['angler_phone']) : '',
		'fish_weight'       => isset($_POST['fish_weight']) ? wp_unslash($_POST['fish_weight']) : '',
		'bait_used'         => isset($_POST['bait_used']) ? wp_unslash($_POST['bait_used']) : '',
		'hookbait'          => isset($_POST['hookbait']) ? wp_unslash($_POST['hookbait']) : '',
		'rig'               => isset($_POST['rig']) ? wp_unslash($_POST['rig']) : '',
		'venue'             => isset($_POST['venue_type']) ? wp_unslash($_POST['venue_type']) : '',
		'swim'              => isset($_POST['swim']) ? wp_unslash($_POST['swim']) : '',
		'story'             => isset($_POST['session_notes']) ? wp_unslash($_POST['session_notes']) : '',
		'marketing_consent' => ! empty($_POST['marketing_consent']),
		'source'            => 'web_form',
		'product_ids'       => isset($_POST['products_used']) && is_array($_POST['products_used'])
			? array_map('absint', wp_unslash($_POST['products_used']))
			: array(),
	);

	if ('' === sanitize_text_field((string) $payload['fish_weight']) || '' === sanitize_text_field((string) $payload['bait_used']) || '' === sanitize_text_field((string) $payload['venue'])) {
		wp_safe_redirect(add_query_arg('catch_status', 'error', mad_baits_get_submit_catch_page_url()));
		exit;
	}

	if (! function_exists('mad_baits_create_catch_report_from_payload')) {
		wp_safe_redirect(add_query_arg('catch_status', 'error', mad_baits_get_submit_catch_page_url()));
		exit;
	}

	$result = mad_baits_create_catch_report_from_payload($payload);
	if (is_wp_error($result)) {
		wp_safe_redirect(add_query_arg('catch_status', 'error', mad_baits_get_submit_catch_page_url()));
		exit;
	}

	$post_id = (int) $result;
	if (! empty($_FILES['catch_images'])) {
		mad_baits_attach_catch_report_uploads($post_id, $_FILES['catch_images']);
	}
	if (function_exists('mad_baits_send_catch_report_media_email')) {
		mad_baits_send_catch_report_media_email($post_id);
	}

	wp_safe_redirect(add_query_arg('catch_status', 'success', mad_baits_get_submit_catch_page_url()));
	exit;
}
add_action('admin_post_mad_baits_submit_catch', 'mad_baits_handle_submit_catch');
add_action('admin_post_nopriv_mad_baits_submit_catch', 'mad_baits_handle_submit_catch');
