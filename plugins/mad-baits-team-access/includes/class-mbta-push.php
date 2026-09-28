<?php
/**
 * Role-targeted push notifications (integrates with Mad Baits Push plugin).
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Push {
	/**
	 * @return void
	 */
	public static function init() {
		add_action('admin_notices', array(__CLASS__, 'vapid_admin_notice'));
	}

	/**
	 * Warn admins when VAPID keys are missing.
	 */
	public static function vapid_admin_notice() {
		if (! current_user_can('manage_options')) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || false === strpos((string) $screen->id, 'mbta-team-access')) {
			return;
		}
		if (! class_exists('Mad_Baits_Push_Admin')) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('Mad Baits Push Notifications plugin is not active. Private push will not work until it is installed.', 'mad-baits-team-access') . '</p></div>';
			return;
		}
		$public = Mad_Baits_Push_Admin::get_public_vapid_key();
		if ('' === $public) {
			echo '<div class="notice notice-warning"><p>' . esc_html__('VAPID keys are not configured. Set them under Settings → Push Notifications before sending private alerts.', 'mad-baits-team-access') . '</p></div>';
		}
	}

	/**
	 * Send notification to users with any of the given roles.
	 *
	 * @param string[]            $roles    Role slugs.
	 * @param array<string,mixed> $payload  title, body, url, image, tag.
	 * @param string              $log_type Log type key.
	 * @param int                 $product_id Optional product ID for log.
	 * @return int Sent count.
	 */
	public static function send_role_notification($roles, $payload, $log_type = 'manual', $product_id = 0) {
		if (! class_exists('Mad_Baits_Push_Sender') || ! class_exists('Mad_Baits_Push_DB')) {
			return 0;
		}

		$roles = array_values(array_unique(array_map('sanitize_key', (array) $roles)));
		$subscribers = self::get_subscribers_for_roles($roles);
		if (empty($subscribers)) {
			return 0;
		}

		$audience = 'mbta_' . implode('_', $roles);
		add_filter(
			'mad_baits_push_subscribers',
			static function ($rows) use ($subscribers) {
				return $subscribers;
			},
			10,
			1
		);

		$result = Mad_Baits_Push_Sender::send(
			array(
				'title' => isset($payload['title']) ? (string) $payload['title'] : '',
				'body'  => isset($payload['body']) ? (string) $payload['body'] : '',
				'url'   => isset($payload['url']) ? (string) $payload['url'] : MBTA_Portal::get_portal_url(),
				'image' => isset($payload['image']) ? (string) $payload['image'] : '',
				'tag'   => isset($payload['tag']) ? (string) $payload['tag'] : 'mbta-private',
			),
			$audience
		);

		remove_all_filters('mad_baits_push_subscribers');

		$sent = isset($result['sent']) ? (int) $result['sent'] : 0;
		if ($product_id > 0 && class_exists('MBTA_Products')) {
			MBTA_Products::log_notification($product_id, $log_type, $sent);
		}

		return $sent;
	}

	/**
	 * @param string[] $roles Roles.
	 * @return array<int, array<string,mixed>>
	 */
	public static function get_subscribers_for_roles($roles) {
		$all = Mad_Baits_Push_DB::get_active_subscribers('logged_in');
		$roles = array_map('sanitize_key', (array) $roles);

		return array_values(
			array_filter(
				$all,
				static function ($row) use ($roles) {
					$user_id = isset($row['user_id']) ? absint($row['user_id']) : 0;
					if ($user_id < 1) {
						return false;
					}
					$user = get_user_by('id', $user_id);
					if (! $user instanceof WP_User) {
						return false;
					}
					foreach ((array) $user->roles as $user_role) {
						if (in_array(sanitize_key((string) $user_role), $roles, true)) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	/**
	 * Audience options for admin send form.
	 *
	 * @return array<string, string>
	 */
	public static function get_audience_options() {
		return array(
			'all_private' => __('All private members', 'mad-baits-team-access'),
			MBTA_Roles::ROLE_TEAM => __('Team only', 'mad-baits-team-access'),
			MBTA_Roles::ROLE_RNT => __('RNT only', 'mad-baits-team-access'),
			MBTA_Roles::ROLE_AMBASSADOR => __('Ambassador only', 'mad-baits-team-access'),
			MBTA_Roles::ROLE_TESTER => __('Tester only', 'mad-baits-team-access'),
			MBTA_Roles::ROLE_MEDIA => __('Media team only', 'mad-baits-team-access'),
		);
	}

	/**
	 * Resolve audience key to role list.
	 *
	 * @param string $audience Audience key.
	 * @return string[]
	 */
	public static function audience_to_roles($audience) {
		$audience = sanitize_key($audience);
		if ('all_private' === $audience) {
			return array_keys(MBTA_Roles::get_role_labels());
		}
		if (isset(MBTA_Roles::get_role_labels()[ $audience ])) {
			return array($audience);
		}
		return array();
	}
}
