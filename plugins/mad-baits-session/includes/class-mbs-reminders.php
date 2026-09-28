<?php
/**
 * In-app reminder hooks (push integration ready).
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Reminders.
 */
class MBS_Reminders {

	/**
	 * @return void
	 */
	public static function init() {
		add_action('rest_api_init', array(__CLASS__, 'register_reminder_hooks'));
	}

	/**
	 * @return void
	 */
	public static function register_reminder_hooks() {
		// Placeholder for future cron / push integration.
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_in_app_reminders($user_id) {
		if ('yes' !== MBS_Plugin::get_setting('reminders_enabled', 'yes')) {
			return array();
		}

		$reminders = array();
		$sessions  = get_posts(
			array(
				'post_type'      => MBS_CPT::SESSION,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => 5,
				'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		foreach ($sessions as $post) {
			$data = MBS_Meta::get_session_data($post->ID);
			if ('active' !== ($data['status'] ?? '')) {
				continue;
			}
			$reminders[] = array(
				'type'    => 'end_session',
				'message' => __('Don\'t forget to end your session when you pack up.', 'mad-baits-session'),
				'action'  => 'active',
				'session_id' => (string) $post->ID,
			);
			$reminders[] = array(
				'type'    => 'update_conditions',
				'message' => __('Update your session conditions while you\'re on the bank.', 'mad-baits-session'),
				'action'  => 'conditions',
				'session_id' => (string) $post->ID,
			);
			break;
		}

		/**
		 * Filter in-app session reminders.
		 *
		 * @param array<int, array<string, string>> $reminders Reminders.
		 * @param int                               $user_id   User ID.
		 */
		return apply_filters('mbs_in_app_reminders', $reminders, $user_id);
	}

	/**
	 * Fire action for push plugin integration.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Reminder type.
	 * @param string $message Message.
	 * @return void
	 */
	public static function dispatch_push_hook($user_id, $type, $message) {
		do_action('mbs_session_reminder_push', $user_id, $type, $message);
	}
}
