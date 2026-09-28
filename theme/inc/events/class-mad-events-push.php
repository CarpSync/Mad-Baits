<?php
/**
 * Event push notification hooks.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Admin push controls for events.
 */
class Mad_Events_Push {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('admin_post_mad_baits_event_push', array(__CLASS__, 'handle_admin_push'));
		add_action('admin_notices', array(__CLASS__, 'admin_notices'));
	}

	/**
	 * Show push send feedback in admin.
	 *
	 * @return void
	 */
	public static function admin_notices() {
		if (! isset($_GET['mad_event_push'])) {
			return;
		}
		$status = sanitize_key(wp_unslash((string) $_GET['mad_event_push']));
		if ('sent' === $status) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Event push notification sent.', 'mad-baits') . '</p></div>';
		} elseif ('failed' === $status) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Event push could not be sent. Check subscriber count and VAPID keys.', 'mad-baits') . '</p></div>';
		} elseif ('unavailable' === $status) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Push notifications need to be configured first.', 'mad-baits') . '</p></div>';
		}
	}

	/**
	 * Whether push sender is ready.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists('Mad_Baits_Push_Sender')
			&& class_exists('Mad_Baits_Push_Admin')
			&& '' !== Mad_Baits_Push_Admin::get_public_vapid_key()
			&& '' !== Mad_Baits_Push_Admin::get_private_vapid_key();
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_admin_push_controls($post) {
		$event = Mad_Events_Data::get_event((int) $post->ID);
		if (! $event) {
			return;
		}
		$available = self::is_available();
		?>
		<hr />
		<h3><?php esc_html_e('Push notifications', 'mad-baits'); ?></h3>
		<?php if (! $available) : ?>
			<p class="description"><?php esc_html_e('Push notifications need to be configured first (VAPID keys in Mad Notifications settings).', 'mad-baits'); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
				<?php wp_nonce_field('mad_baits_event_push', 'mad_baits_event_push_nonce'); ?>
				<input type="hidden" name="action" value="mad_baits_event_push" />
				<input type="hidden" name="event_id" value="<?php echo esc_attr((string) $post->ID); ?>" />
				<button type="submit" name="push_type" value="reminder" class="button"><?php esc_html_e('Send event reminder', 'mad-baits'); ?></button>
				<button type="submit" name="push_type" value="update" class="button"><?php esc_html_e('Send event update', 'mad-baits'); ?></button>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Handle admin push send.
	 *
	 * @return void
	 */
	public static function handle_admin_push() {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'mad-baits'));
		}
		check_admin_referer('mad_baits_event_push', 'mad_baits_event_push_nonce');

		$event_id  = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
		$push_type = isset($_POST['push_type']) ? sanitize_key(wp_unslash((string) $_POST['push_type'])) : 'reminder';
		$event     = Mad_Events_Data::get_event($event_id);

		$redirect = $event ? get_edit_post_link($event_id, 'raw') : admin_url('edit.php?post_type=mad_event');
		if (! is_string($redirect) || '' === $redirect) {
			$redirect = admin_url('edit.php?post_type=mad_event');
		}

		if (! $event || ! self::is_available()) {
			wp_safe_redirect(add_query_arg('mad_event_push', 'unavailable', $redirect));
			exit;
		}

		$title = (string) $event['title'];
		$body  = '' !== (string) $event['push_message']
			? (string) $event['push_message']
			: (
				'update' === $push_type
					? sprintf(
						/* translators: %s: event title */
						__('Update for %s — tap for details.', 'mad-baits'),
						$title
					)
					: sprintf(
						/* translators: %s: date label */
						__('%1$s — free BBQ, bait deals and factory open day. Tap for details.', 'mad-baits'),
						$event['date_label'] ?: $title
					)
			);

		$result = Mad_Baits_Push_Sender::send(
			array(
				'title' => $title,
				'body'  => $body,
				'url'   => (string) $event['permalink'],
				'image' => (string) $event['hero_image'],
				'tag'   => 'mad-event-' . (int) $event['id'],
			),
			'all'
		);

		$status = ! empty($result['sent']) ? 'sent' : 'failed';
		wp_safe_redirect(add_query_arg('mad_event_push', $status, $redirect));
		exit;
	}
}
