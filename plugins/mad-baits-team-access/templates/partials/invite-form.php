<?php
/**
 * Invite registration form partial.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

$code  = isset($_GET['code']) ? MBTA_Invites::normalize_code(wp_unslash((string) $_GET['code'])) : '';
$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash((string) $_GET['error'])) : '';
$invite = $code ? MBTA_Invites::get_by_code($code) : null;
$role_label = '';
if ($invite && isset($invite['role'])) {
	$labels = MBTA_Roles::get_role_labels();
	$role_label = isset($labels[ $invite['role'] ]) ? (string) $labels[ $invite['role'] ] : '';
}
?>
<section class="mbta-invite">
	<header class="mbta-invite__header">
		<p class="mbta-invite__eyebrow"><?php esc_html_e('Mad Baits Private Access', 'mad-baits-team-access'); ?></p>
		<h1><?php esc_html_e('Team invite registration', 'mad-baits-team-access'); ?></h1>
		<?php if ($role_label) : ?>
			<p class="mbta-invite__role"><?php echo esc_html(sprintf(/* translators: %s role name */ __('Invited as: %s', 'mad-baits-team-access'), $role_label)); ?></p>
		<?php endif; ?>
	</header>

	<?php if ($error) : ?>
		<p class="mbta-invite__error" role="alert"><?php echo esc_html($error); ?></p>
	<?php endif; ?>

	<form class="mbta-invite__form" method="post" action="">
		<?php wp_nonce_field('mbta_register', 'mbta_register_nonce'); ?>
		<input type="hidden" name="mbta_register_submit" value="1" />
		<input type="hidden" name="invite_code" value="<?php echo esc_attr($code); ?>" />

		<div class="mbta-field-row">
			<label for="mbta_first_name"><?php esc_html_e('First name', 'mad-baits-team-access'); ?> *</label>
			<input id="mbta_first_name" name="first_name" type="text" required autocomplete="given-name" />
		</div>
		<div class="mbta-field-row">
			<label for="mbta_last_name"><?php esc_html_e('Last name', 'mad-baits-team-access'); ?> *</label>
			<input id="mbta_last_name" name="last_name" type="text" required autocomplete="family-name" />
		</div>
		<div class="mbta-field-row">
			<label for="mbta_email"><?php esc_html_e('Email', 'mad-baits-team-access'); ?> *</label>
			<input id="mbta_email" name="email" type="email" required autocomplete="email" />
		</div>
		<div class="mbta-field-row">
			<label for="mbta_password"><?php esc_html_e('Password', 'mad-baits-team-access'); ?> *</label>
			<input id="mbta_password" name="password" type="password" required minlength="8" autocomplete="new-password" />
		</div>
		<div class="mbta-field-row">
			<label for="mbta_phone"><?php esc_html_e('Phone (optional)', 'mad-baits-team-access'); ?></label>
			<input id="mbta_phone" name="phone" type="tel" autocomplete="tel" />
		</div>
		<div class="mbta-field-row">
			<label for="mbta_instagram"><?php esc_html_e('Instagram handle (optional)', 'mad-baits-team-access'); ?></label>
			<input id="mbta_instagram" name="instagram" type="text" placeholder="@handle" />
		</div>

		<button type="submit" class="mbta-btn mbta-btn--primary"><?php esc_html_e('Create account & enter portal', 'mad-baits-team-access'); ?></button>
	</form>
</section>
