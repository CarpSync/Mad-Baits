<?php
/**
 * Admin pages and settings.
 *
 * @package MadBaitsPush
 */

if (! defined('ABSPATH')) {
	exit;
}

class Mad_Baits_Push_Admin {
	const OPTION_PUBLIC_KEY  = 'mad_baits_push_vapid_public_key';
	const OPTION_PRIVATE_KEY = 'mad_baits_push_vapid_private_key';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'register_menu'));
		add_action('admin_post_mad_baits_push_save_settings', array(__CLASS__, 'handle_save_settings'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__('Mad Notifications', 'mad-baits-push'),
			__('Mad Notifications', 'mad-baits-push'),
			'manage_options',
			'mad-baits-push',
			array(__CLASS__, 'render_notifications_page'),
			'dashicons-megaphone',
			56
		);

		add_submenu_page(
			'mad-baits-push',
			__('Mad Notifications', 'mad-baits-push'),
			__('Mad Notifications', 'mad-baits-push'),
			'manage_options',
			'mad-baits-push',
			array(__CLASS__, 'render_notifications_page')
		);

		add_submenu_page(
			'mad-baits-push',
			__('Push Settings', 'mad-baits-push'),
			__('Settings', 'mad-baits-push'),
			'manage_options',
			'mad-baits-push-settings',
			array(__CLASS__, 'render_settings_page')
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets($hook) {
		if (false === strpos((string) $hook, 'mad-baits-push')) {
			return;
		}

		wp_enqueue_style(
			'mad-baits-push-admin',
			MAD_BAITS_PUSH_URL . 'assets/css/mad-push.css',
			array(),
			function_exists('mad_baits_push_asset_version') ? mad_baits_push_asset_version('assets/css/mad-push.css') : MAD_BAITS_PUSH_VERSION
		);

		wp_enqueue_script(
			'mad-baits-push-admin',
			MAD_BAITS_PUSH_URL . 'assets/js/mad-push.js',
			array(),
			function_exists('mad_baits_push_asset_version') ? mad_baits_push_asset_version('assets/js/mad-push.js') : MAD_BAITS_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'mad-baits-push-admin',
			'madBaitsPushAdmin',
			array(
				'restRoot'         => esc_url_raw(rest_url('mad-baits/v1/push/')),
				'restNonce'        => wp_create_nonce('wp_rest'),
				'publicVapidKey'   => self::get_public_vapid_key(),
				'serviceWorkerUrl' => esc_url_raw(home_url('/?mad_baits_pwa=service-worker')),
				'adminPage'        => true,
			)
		);
	}

	/**
	 * Get public key.
	 *
	 * @return string
	 */
	public static function get_public_vapid_key() {
		return (string) get_option(self::OPTION_PUBLIC_KEY, '');
	}

	/**
	 * Get private key.
	 *
	 * @return string
	 */
	public static function get_private_vapid_key() {
		return (string) get_option(self::OPTION_PRIVATE_KEY, '');
	}

	/**
	 * Handle save settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do this.', 'mad-baits-push'));
		}

		check_admin_referer('mad_baits_push_save_settings');

		$action = isset($_POST['settings_action']) ? sanitize_key(wp_unslash((string) $_POST['settings_action'])) : 'save';
		if ('generate' === $action) {
			$keys = self::generate_vapid_keys();
			if (isset($keys['public'], $keys['private'])) {
				update_option(self::OPTION_PUBLIC_KEY, $keys['public']);
				update_option(self::OPTION_PRIVATE_KEY, $keys['private']);
				set_transient('mad_baits_push_admin_notice', __('New VAPID keys generated.', 'mad-baits-push'), 30);
			} else {
				set_transient('mad_baits_push_admin_notice', __('Could not generate VAPID keys. Check OpenSSL support.', 'mad-baits-push'), 30);
			}
		} else {
			$public_key  = isset($_POST['vapid_public']) ? sanitize_text_field(wp_unslash((string) $_POST['vapid_public'])) : '';
			$private_key = isset($_POST['vapid_private']) ? sanitize_text_field(wp_unslash((string) $_POST['vapid_private'])) : '';
			update_option(self::OPTION_PUBLIC_KEY, $public_key);
			update_option(self::OPTION_PRIVATE_KEY, $private_key);
			set_transient('mad_baits_push_admin_notice', __('Push settings saved.', 'mad-baits-push'), 30);
		}

		wp_safe_redirect(admin_url('admin.php?page=mad-baits-push-settings'));
		exit;
	}

	/**
	 * Generate VAPID keys.
	 *
	 * @return array<string,string>
	 */
	public static function generate_vapid_keys() {
		if (class_exists('\\Minishlink\\WebPush\\VAPID')) {
			$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
			if (is_array($keys) && isset($keys['publicKey'], $keys['privateKey'])) {
				return array(
					'public'  => (string) $keys['publicKey'],
					'private' => (string) $keys['privateKey'],
				);
			}
		}

		$key_resource = openssl_pkey_new(
			array(
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name'       => 'prime256v1',
			)
		);
		if (! $key_resource) {
			return array();
		}

		$details = openssl_pkey_get_details($key_resource);
		if (! is_array($details) || empty($details['ec']['x']) || empty($details['ec']['y']) || empty($details['ec']['d'])) {
			return array();
		}

		$public_key_raw  = "\x04" . $details['ec']['x'] . $details['ec']['y'];
		$private_key_raw = $details['ec']['d'];

		return array(
			'public'  => self::base64url_encode($public_key_raw),
			'private' => self::base64url_encode($private_key_raw),
		);
	}

	/**
	 * Base64 URL-safe encode.
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private static function base64url_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if (! current_user_can('manage_options')) {
			return;
		}

		$notice      = (string) get_transient('mad_baits_push_admin_notice');
		$public_key  = self::get_public_vapid_key();
		$private_key = self::get_private_vapid_key();
		$masked      = '' !== $private_key ? str_repeat('*', max(12, strlen($private_key) - 6)) . substr($private_key, -6) : '';
		?>
		<div class="wrap mad-push-admin">
			<h1><?php esc_html_e('Push Notification Settings', 'mad-baits-push'); ?></h1>
			<?php if ('' !== $notice) : ?>
				<div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
				<?php delete_transient('mad_baits_push_admin_notice'); ?>
			<?php endif; ?>
			<div class="mad-push-admin-card">
				<h2><?php esc_html_e('VAPID Keys', 'mad-baits-push'); ?></h2>
				<p><?php esc_html_e('Public key is used by the browser to subscribe. Private key stays server-side only.', 'mad-baits-push'); ?></p>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('mad_baits_push_save_settings'); ?>
					<input type="hidden" name="action" value="mad_baits_push_save_settings" />
					<input type="hidden" name="settings_action" value="save" />
					<label for="vapid_public"><?php esc_html_e('Public Key', 'mad-baits-push'); ?></label>
					<textarea id="vapid_public" name="vapid_public" rows="3"><?php echo esc_textarea($public_key); ?></textarea>
					<label for="vapid_private"><?php esc_html_e('Private Key (masked in UI)', 'mad-baits-push'); ?></label>
					<input id="vapid_private" type="password" name="vapid_private" value="<?php echo esc_attr($private_key); ?>" />
					<p class="description"><?php echo esc_html($masked); ?></p>
					<p>
						<button class="button button-primary" type="submit"><?php esc_html_e('Save Settings', 'mad-baits-push'); ?></button>
					</p>
				</form>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('mad_baits_push_save_settings'); ?>
					<input type="hidden" name="action" value="mad_baits_push_save_settings" />
					<input type="hidden" name="settings_action" value="generate" />
					<button class="button" type="submit"><?php esc_html_e('Generate Keys', 'mad-baits-push'); ?></button>
				</form>
				<?php if (! Mad_Baits_Push_Sender::has_webpush_library()) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e('Web Push library missing. Run composer require minishlink/web-push', 'mad-baits-push'); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render portal page.
	 *
	 * @return void
	 */
	public static function render_notifications_page() {
		if (! current_user_can('manage_options')) {
			return;
		}

		$stats       = Mad_Baits_Push_DB::get_subscriber_stats();
		$logs        = Mad_Baits_Push_DB::get_recent_logs(10);
		$subscribers = Mad_Baits_Push_DB::get_recent_subscribers(8);
		?>
		<div class="wrap mad-push-admin">
			<h1><?php esc_html_e('Mad Notifications', 'mad-baits-push'); ?></h1>
			<div class="notice notice-info inline">
				<p><?php esc_html_e('Installed app does not equal active subscriber. A device becomes active only after notification permission is allowed and a push subscription is saved.', 'mad-baits-push'); ?></p>
			</div>
			<div class="mad-push-grid mad-push-stats">
				<div class="mad-push-admin-card"><strong><?php esc_html_e('Active', 'mad-baits-push'); ?></strong><span><?php echo esc_html((string) $stats['total_active']); ?></span></div>
				<div class="mad-push-admin-card"><strong><?php esc_html_e('PWA Users', 'mad-baits-push'); ?></strong><span><?php echo esc_html((string) $stats['pwa_users']); ?></span></div>
				<div class="mad-push-admin-card"><strong><?php esc_html_e('Logged-in', 'mad-baits-push'); ?></strong><span><?php echo esc_html((string) $stats['logged_in']); ?></span></div>
				<div class="mad-push-admin-card"><strong><?php esc_html_e('Inactive/Error', 'mad-baits-push'); ?></strong><span><?php echo esc_html((string) $stats['inactive']); ?></span></div>
			</div>

			<div class="mad-push-admin-card">
				<h2><?php esc_html_e('Support Diagnostics', 'mad-baits-push'); ?></h2>
				<p><?php esc_html_e('Open the browser console on the target device and look for logs prefixed with [PWA Push]. These include standalone mode, permission state, service-worker readiness, existing subscription status, and subscribe endpoint responses.', 'mad-baits-push'); ?></p>
			</div>

			<div class="mad-push-admin-card">
				<h2><?php esc_html_e('Send Notification', 'mad-baits-push'); ?></h2>
				<p><?php esc_html_e('Use templates for quick campaign sends, or write your own.', 'mad-baits-push'); ?></p>
				<form id="mad-push-send-form">
					<label><?php esc_html_e('Title', 'mad-baits-push'); ?></label>
					<input type="text" name="title" maxlength="90" required />
					<label><?php esc_html_e('Message', 'mad-baits-push'); ?></label>
					<textarea name="message" rows="3" maxlength="220" required></textarea>
					<label><?php esc_html_e('Target URL', 'mad-baits-push'); ?></label>
					<input type="url" name="url" required value="<?php echo esc_url(home_url('/shop/')); ?>" />
					<label><?php esc_html_e('Image URL (optional)', 'mad-baits-push'); ?></label>
					<input type="url" name="image" />
					<label><?php esc_html_e('Audience', 'mad-baits-push'); ?></label>
					<select name="audience">
						<option value="all"><?php esc_html_e('All active subscribers', 'mad-baits-push'); ?></option>
						<option value="pwa"><?php esc_html_e('PWA users only', 'mad-baits-push'); ?></option>
						<option value="logged_in"><?php esc_html_e('Logged-in customers only', 'mad-baits-push'); ?></option>
					</select>
					<div class="mad-push-actions">
						<button type="button" class="button" data-mad-push-template="drop"><?php esc_html_e('New bait drop', 'mad-baits-push'); ?></button>
						<button type="button" class="button" data-mad-push-template="weekend"><?php esc_html_e('Weekend deal', 'mad-baits-push'); ?></button>
						<button type="button" class="button" data-mad-push-template="restock"><?php esc_html_e('Restock alert', 'mad-baits-push'); ?></button>
						<button type="button" class="button" data-mad-push-template="last_chance"><?php esc_html_e('Last chance offer', 'mad-baits-push'); ?></button>
					</div>
					<div class="mad-push-actions">
						<button type="button" class="button" id="mad-push-send-test"><?php esc_html_e('Send test to current browser', 'mad-baits-push'); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e('Send Notification', 'mad-baits-push'); ?></button>
					</div>
					<p id="mad-push-send-status" class="description"></p>
				</form>
			</div>

			<div class="mad-push-admin-card">
				<h2><?php esc_html_e('Recent Sends', 'mad-baits-push'); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e('Date/Time', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Title', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Audience', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Attempted', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Sent', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Failed', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Failure summary', 'mad-baits-push'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($logs as $log) : ?>
							<tr>
								<td><?php echo esc_html((string) $log['created_at']); ?></td>
								<td><?php echo esc_html((string) $log['title']); ?></td>
								<td><?php echo esc_html((string) $log['audience']); ?></td>
								<td><?php echo esc_html((string) $log['attempted']); ?></td>
								<td><?php echo esc_html((string) $log['sent']); ?></td>
								<td><?php echo esc_html((string) $log['failed']); ?></td>
								<td><?php echo esc_html(wp_trim_words((string) $log['failures_summary'], 16, '...')); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($logs)) : ?>
							<tr><td colspan="7"><?php esc_html_e('No sends logged yet.', 'mad-baits-push'); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="mad-push-admin-card">
				<h2><?php esc_html_e('Recent Subscribers', 'mad-baits-push'); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e('Created', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('User', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Device', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('PWA', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Permission', 'mad-baits-push'); ?></th>
							<th><?php esc_html_e('Active', 'mad-baits-push'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($subscribers as $sub) : ?>
							<tr>
								<td><?php echo esc_html((string) $sub['created_at']); ?></td>
								<td><?php echo esc_html((string) $sub['user_id']); ?></td>
								<td><?php echo esc_html((string) $sub['device_label']); ?></td>
								<td><?php echo ! empty($sub['is_pwa']) ? 'yes' : 'no'; ?></td>
								<td><?php echo esc_html((string) $sub['permission_status']); ?></td>
								<td><?php echo ! empty($sub['active']) ? 'yes' : 'no'; ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($subscribers)) : ?>
							<tr><td colspan="6"><?php esc_html_e('No subscribers recorded yet.', 'mad-baits-push'); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
