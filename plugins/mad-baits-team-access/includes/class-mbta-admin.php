<?php
/**
 * Team Access admin UI.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Admin {
	/**
	 * @return void
	 */
	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'register_menu'));
		add_action('admin_post_mbta_save_invite', array(__CLASS__, 'handle_save_invite'));
		add_action('admin_post_mbta_send_invite_email', array(__CLASS__, 'handle_send_invite_email'));
		add_action('admin_post_mbta_toggle_invite', array(__CLASS__, 'handle_toggle_invite'));
		add_action('admin_post_mbta_send_notification', array(__CLASS__, 'handle_send_notification'));
	}

	/**
	 * Register admin menu.
	 */
	public static function register_menu() {
		add_menu_page(
			__('Team Access', 'mad-baits-team-access'),
			__('Team Access', 'mad-baits-team-access'),
			'manage_woocommerce',
			'mbta-team-access',
			array(__CLASS__, 'render_page'),
			'dashicons-groups',
			56
		);
	}

	/**
	 * Human-readable admin notices.
	 *
	 * @param string $code Notice code.
	 * @return string
	 */
	public static function get_notice_message($code) {
		$messages = array(
			'invite_created'      => __('Invite created.', 'mad-baits-team-access'),
			'invite_failed'       => __('Could not create invite. Check the role and code.', 'mad-baits-team-access'),
			'invalid_role'        => __('Could not create invite — choose a valid role.', 'mad-baits-team-access'),
			'invalid_code'        => __('Could not create invite — the code must use letters and numbers only.', 'mad-baits-team-access'),
			'duplicate_code'      => __('Could not create invite — that code is already in use.', 'mad-baits-team-access'),
			'invite_db_failed'    => __('Could not save invite. Try again or contact support if this persists.', 'mad-baits-team-access'),
			'invite_sent'         => __('Invite link emailed to the recipient.', 'mad-baits-team-access'),
			'invite_send_failed'  => __('Invite created, but the email could not be sent.', 'mad-baits-team-access'),
			'invite_email_failed' => __('The invite email could not be sent.', 'mad-baits-team-access'),
			'invite_email_sent'   => __('Invite link emailed.', 'mad-baits-team-access'),
			'invite_missing'      => __('Invite not found.', 'mad-baits-team-access'),
			'toggled'             => __('Invite status updated.', 'mad-baits-team-access'),
		);

		return isset($messages[ $code ]) ? (string) $messages[ $code ] : $code;
	}

	/**
	 * Render admin page.
	 */
	public static function render_page() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'mad-baits-team-access'));
		}

		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'invites';
		$invites = MBTA_Invites::get_all();
		$notice_code = isset($_GET['mbta_notice']) ? sanitize_key(wp_unslash((string) $_GET['mbta_notice'])) : '';
		$notice = '' !== $notice_code ? self::get_notice_message($notice_code) : '';
		$notice_type = in_array(
			$notice_code,
			array('invite_failed', 'invalid_role', 'invalid_code', 'duplicate_code', 'invite_db_failed', 'invite_send_failed', 'invite_email_failed', 'send_failed'),
			true
		)
			? 'error'
			: 'success';

		include MBTA_PATH . 'templates/admin/team-access-page.php';
	}

	/**
	 * Save new invite.
	 */
	public static function handle_save_invite() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Forbidden', 'mad-baits-team-access'));
		}
		check_admin_referer('mbta_save_invite');

		$send_email = ! empty($_POST['send_invite_email']);
		$invite_email = isset($_POST['invite_email']) ? sanitize_email(wp_unslash((string) $_POST['invite_email'])) : '';

		$payload = array(
			'role'         => isset($_POST['role']) ? sanitize_key(wp_unslash((string) $_POST['role'])) : '',
			'code'         => isset($_POST['code']) ? sanitize_text_field(wp_unslash((string) $_POST['code'])) : '',
			'expires_at'   => isset($_POST['expires_at']) ? sanitize_text_field(wp_unslash((string) $_POST['expires_at'])) : '',
			'usage_limit'  => isset($_POST['usage_limit']) ? wp_unslash((string) $_POST['usage_limit']) : '',
			'status'       => 'active',
			'notes'        => isset($_POST['notes']) ? wp_unslash((string) $_POST['notes']) : '',
			'invite_email' => $invite_email,
		);

		$precheck = MBTA_Invites::get_create_error_code($payload);
		if ('' !== $precheck) {
			wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=invites&mbta_notice=' . rawurlencode($precheck)));
			exit;
		}

		$id = MBTA_Invites::create($payload);

		if (! $id) {
			wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=invites&mbta_notice=invite_db_failed'));
			exit;
		}

		$notice = 'invite_created';
		if ($send_email) {
			$invite = MBTA_Invites::get((int) $id);
			$result = $invite ? MBTA_Invite_Mail::send_invite_link($invite, $invite_email) : false;
			if (true === $result) {
				$notice = 'invite_sent';
			} else {
				$notice = 'invite_send_failed';
			}
		}

		wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=invites&mbta_notice=' . rawurlencode($notice)));
		exit;
	}

	/**
	 * Send invite link email for an existing invite.
	 */
	public static function handle_send_invite_email() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Forbidden', 'mad-baits-team-access'));
		}
		check_admin_referer('mbta_send_invite_email');

		$id = isset($_POST['invite_id']) ? absint($_POST['invite_id']) : 0;
		$invite = MBTA_Invites::get($id);
		if (! $invite) {
			wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=invites&mbta_notice=invite_missing'));
			exit;
		}

		$email = isset($_POST['invite_email']) ? sanitize_email(wp_unslash((string) $_POST['invite_email'])) : '';
		if ('' === $email) {
			$email = sanitize_email((string) ($invite['invite_email'] ?? ''));
		}
		if ('' !== $email && $email !== sanitize_email((string) ($invite['invite_email'] ?? ''))) {
			MBTA_Invites::update($id, array('invite_email' => $email));
			$invite = MBTA_Invites::get($id);
		}

		$result = $invite ? MBTA_Invite_Mail::send_invite_link($invite, $email) : false;
		$notice = true === $result ? 'invite_email_sent' : 'invite_email_failed';

		wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=invites&mbta_notice=' . rawurlencode($notice)));
		exit;
	}

	/**
	 * Toggle invite active status.
	 */
	public static function handle_toggle_invite() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Forbidden', 'mad-baits-team-access'));
		}
		check_admin_referer('mbta_toggle_invite');

		$id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		$invite = MBTA_Invites::get($id);
		if ($invite) {
			$new_status = 'active' === (string) ($invite['status'] ?? '') ? 'inactive' : 'active';
			MBTA_Invites::update($id, array('status' => $new_status));
		}

		wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=invites&mbta_notice=toggled'));
		exit;
	}

	/**
	 * Send role notification from admin.
	 */
	public static function handle_send_notification() {
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Forbidden', 'mad-baits-team-access'));
		}
		check_admin_referer('mbta_send_notification');

		$title    = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
		$message  = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['message'])) : '';
		$url      = isset($_POST['url']) ? esc_url_raw(wp_unslash((string) $_POST['url'])) : '';
		$image    = isset($_POST['image']) ? esc_url_raw(wp_unslash((string) $_POST['image'])) : '';
		$audience = isset($_POST['audience']) ? sanitize_key(wp_unslash((string) $_POST['audience'])) : 'all_private';

		if ('' === $title || '' === $message) {
			wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=notifications&mbta_notice=send_failed'));
			exit;
		}

		$roles = MBTA_Push::audience_to_roles($audience);
		$sent  = MBTA_Push::send_role_notification(
			$roles,
			array(
				'title' => $title,
				'body'  => $message,
				'url'   => '' !== $url ? $url : MBTA_Portal::get_portal_url(),
				'image' => $image,
				'tag'   => 'mbta-admin-' . gmdate('YmdHis'),
			),
			'admin_manual'
		);

		$notice = $sent > 0 ? 'sent_' . $sent : 'no_subscribers';
		wp_safe_redirect(admin_url('admin.php?page=mbta-team-access&tab=notifications&mbta_notice=' . rawurlencode($notice)));
		exit;
	}
}
