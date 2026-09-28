<?php
/**
 * Team invite email delivery.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Invite_Mail {
	/**
	 * Send invite registration link to the invitee.
	 *
	 * @param array<string, mixed> $invite Invite row.
	 * @param string               $to   Recipient email (falls back to invite row).
	 * @return bool|WP_Error
	 */
	public static function send_invite_link($invite, $to = '') {
		if (! is_array($invite) || empty($invite['code'])) {
			return new WP_Error('mbta_invalid_invite', __('Invalid invite.', 'mad-baits-team-access'));
		}

		$to = sanitize_email($to);
		if ('' === $to) {
			$to = sanitize_email((string) ($invite['invite_email'] ?? ''));
		}
		if ('' === $to || ! is_email($to)) {
			return new WP_Error('mbta_invalid_email', __('A valid email address is required.', 'mad-baits-team-access'));
		}

		$error = MBTA_Invites::validate_invite($invite);
		if ('' !== $error) {
			return new WP_Error('mbta_invite_unusable', $error);
		}

		$labels = MBTA_Roles::get_role_labels();
		$role   = sanitize_key((string) ($invite['role'] ?? ''));
		$role_label = isset($labels[ $role ]) ? (string) $labels[ $role ] : $role;
		$invite_url = MBTA_Invites::get_invite_url($invite);
		$site_name  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

		$subject = sprintf(
			/* translators: %s: site name */
			__('Your Mad Baits team invite — %s', 'mad-baits-team-access'),
			$site_name
		);

		$message = sprintf(
			/* translators: 1: role label, 2: invite URL, 3: site name */
			__(
				"Hello,\n\nYou have been invited to join Mad Baits private access as: %1\$s.\n\nUse this link to create your account:\n%2\$s\n\nThis link is personal to you. If it expires or stops working, contact the Mad Baits team for a new invite.\n\n— %3\$s",
				'mad-baits-team-access'
			),
			$role_label,
			$invite_url,
			$site_name
		);

		$headers = array('Content-Type: text/plain; charset=UTF-8');
		$sent = wp_mail($to, $subject, $message, $headers);

		if ($sent) {
			/**
			 * Fires after a team invite email was sent successfully.
			 *
			 * @param array<string, mixed> $invite Invite row.
			 * @param string               $to     Recipient email.
			 */
			do_action('mbta_invite_email_sent', $invite, $to);
		}

		return $sent ? true : new WP_Error('mbta_mail_failed', __('The invite email could not be sent. Check your WordPress mail settings.', 'mad-baits-team-access'));
	}
}
