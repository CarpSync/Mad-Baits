<?php
/**
 * Admin settings and moderation.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Admin UI.
 */
class MBS_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'register_menu'));
		add_action('admin_init', array(__CLASS__, 'register_settings'));
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings() {
		return array(
			'enabled'              => 'yes',
			'order_statuses'       => 'pending,processing,completed',
			'allow_on_hold'        => 'no',
			'mobile_only'          => 'yes',
			'weather_enabled'      => 'no',
			'weather_provider'     => 'openweathermap',
			'weather_api_key'      => '',
			'catch_photos'         => 'yes',
			'max_photos_per_catch' => 3,
			'public_catch_consent' => 'yes',
			'auto_publish_public'  => 'no',
			'team_reports'         => 'yes',
			'pdf_export'           => 'yes',
			'offline_draft'        => 'yes',
			'reminders_enabled'    => 'yes',
			'haptics'              => 'yes',
		);
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__('Session App', 'mad-baits-session'),
			__('Session', 'mad-baits-session'),
			'manage_woocommerce',
			'mad-baits-session',
			array(__CLASS__, 'render_settings_page')
		);
	}

	/**
	 * @return void
	 */
	public static function register_settings() {
		register_setting('mbs_settings_group', MBS_Plugin::OPTION_SETTINGS, array(
			'type'              => 'array',
			'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
		));
	}

	/**
	 * @param mixed $input Input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings($input) {
		$defaults = self::default_settings();
		if (! is_array($input)) {
			return $defaults;
		}
		$out = array();
		$yes_no = array('enabled', 'allow_on_hold', 'mobile_only', 'weather_enabled', 'catch_photos', 'public_catch_consent', 'auto_publish_public', 'team_reports', 'pdf_export', 'offline_draft', 'reminders_enabled', 'haptics');
		foreach ($yes_no as $key) {
			$out[ $key ] = ! empty($input[ $key ]) && 'no' !== $input[ $key ] ? 'yes' : 'no';
		}
		$out['weather_provider']     = sanitize_key((string) ($input['weather_provider'] ?? 'openweathermap'));
		$out['weather_api_key']      = sanitize_text_field((string) ($input['weather_api_key'] ?? ''));
		$out['max_photos_per_catch'] = min(10, max(1, absint($input['max_photos_per_catch'] ?? 3)));
		$allowed_order_statuses = array('pending', 'processing', 'completed', 'on-hold');
		$selected_statuses      = array();
		if (! empty($input['order_status_slugs']) && is_array($input['order_status_slugs'])) {
			foreach ($input['order_status_slugs'] as $slug) {
				$slug = sanitize_key((string) $slug);
				if (in_array($slug, $allowed_order_statuses, true)) {
					$selected_statuses[] = $slug;
				}
			}
		}
		$out['order_statuses'] = ! empty($selected_statuses)
			? implode(',', array_values(array_unique($selected_statuses)))
			: (string) $defaults['order_statuses'];
		return array_merge($defaults, $out);
	}

	/**
	 * @return void
	 */
	public static function render_settings_page() {
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		$settings = MBS_Plugin::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Mad Baits Session App', 'mad-baits-session'); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields('mbs_settings_group'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e('Enable Session app', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[enabled]" value="yes" <?php checked('yes', $settings['enabled']); ?> /> <?php esc_html_e('Enabled', 'mad-baits-session'); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Unlock with order status', 'mad-baits-session'); ?></th>
						<td>
							<?php
							$stored_statuses = array_filter(array_map('sanitize_key', explode(',', (string) ($settings['order_statuses'] ?? ''))));
							if (empty($stored_statuses)) {
								$stored_statuses = array('pending', 'processing', 'completed');
							}
							$status_choices = array(
								'pending'    => __('Pending payment (order placed)', 'mad-baits-session'),
								'processing' => __('Processing / confirmed (paid)', 'mad-baits-session'),
								'completed'  => __('Completed', 'mad-baits-session'),
								'on-hold'    => __('On hold', 'mad-baits-session'),
							);
							foreach ($status_choices as $slug => $label) :
								?>
								<label style="display:block;margin:0 0 0.35rem;">
									<input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[order_status_slugs][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $stored_statuses, true)); ?> />
									<?php echo esc_html($label); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e('Customers need at least one order on their account in any selected status to use Session. Checkout account creation + pending orders unlock access immediately.', 'mad-baits-session'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Also allow on-hold', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[allow_on_hold]" value="yes" <?php checked('yes', $settings['allow_on_hold']); ?> /> <?php esc_html_e('Include on-hold even if unchecked above', 'mad-baits-session'); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Mobile-only enforcement', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[mobile_only]" value="yes" <?php checked('yes', $settings['mobile_only']); ?> /></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Weather lookup', 'mad-baits-session'); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[weather_enabled]" value="yes" <?php checked('yes', $settings['weather_enabled']); ?> /></label>
							<p><label><?php esc_html_e('Provider', 'mad-baits-session'); ?>
								<select name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[weather_provider]">
									<option value="openweathermap" <?php selected('openweathermap', $settings['weather_provider']); ?>>OpenWeatherMap</option>
								</select>
							</label></p>
							<p><label><?php esc_html_e('API key', 'mad-baits-session'); ?>
								<input type="password" class="regular-text" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[weather_api_key]" value="<?php echo esc_attr((string) $settings['weather_api_key']); ?>" autocomplete="off" />
							</label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Catch photos', 'mad-baits-session'); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[catch_photos]" value="yes" <?php checked('yes', $settings['catch_photos']); ?> /></label>
							<p><?php esc_html_e('Max photos per catch', 'mad-baits-session'); ?>
								<input type="number" min="1" max="10" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[max_photos_per_catch]" value="<?php echo esc_attr((string) $settings['max_photos_per_catch']); ?>" />
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Public catch consent', 'mad-baits-session'); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[public_catch_consent]" value="yes" <?php checked('yes', $settings['public_catch_consent']); ?> /></label>
							<p><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[auto_publish_public]" value="yes" <?php checked('yes', $settings['auto_publish_public']); ?> /> <?php esc_html_e('Auto-publish consented catches (not recommended)', 'mad-baits-session'); ?></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Team reports', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[team_reports]" value="yes" <?php checked('yes', $settings['team_reports']); ?> /></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('PDF / print export', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[pdf_export]" value="yes" <?php checked('yes', $settings['pdf_export']); ?> /></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Offline draft mode', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[offline_draft]" value="yes" <?php checked('yes', $settings['offline_draft']); ?> /></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Reminders', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[reminders_enabled]" value="yes" <?php checked('yes', $settings['reminders_enabled']); ?> /></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Haptics', 'mad-baits-session'); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr(MBS_Plugin::OPTION_SETTINGS); ?>[haptics]" value="yes" <?php checked('yes', $settings['haptics']); ?> /></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e('Support tools', 'mad-baits-session'); ?></h2>
			<p><?php esc_html_e('Sessions and venues are private user-owned records. Browse by customer in Users or query post types mad_fishing_session, mad_saved_venue, mad_team_report.', 'mad-baits-session'); ?></p>
			<?php self::render_public_catch_queue(); ?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	private static function render_public_catch_queue() {
		$posts = get_posts(
			array(
				'post_type'      => MBS_CPT::SESSION,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
			)
		);
		$pending = array();
		foreach ($posts as $post) {
			$data = MBS_Meta::get_session_data($post->ID);
			$catches = isset($data['catches']) && is_array($data['catches']) ? $data['catches'] : array();
			foreach ($catches as $catch) {
				if (! is_array($catch) || empty($catch['public_consent'])) {
					continue;
				}
				$pending[] = array(
					'session_id' => $post->ID,
					'user_id'    => (int) $post->post_author,
					'catch'      => $catch,
				);
			}
		}
		?>
		<h3><?php esc_html_e('Public catch submissions (consented)', 'mad-baits-session'); ?></h3>
		<?php if (empty($pending)) : ?>
			<p><?php esc_html_e('No pending consented catches.', 'mad-baits-session'); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('User', 'mad-baits-session'); ?></th>
						<th><?php esc_html_e('Species', 'mad-baits-session'); ?></th>
						<th><?php esc_html_e('Weight', 'mad-baits-session'); ?></th>
						<th><?php esc_html_e('Consent', 'mad-baits-session'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($pending as $row) : ?>
						<tr>
							<td><?php
							$user = get_userdata($row['user_id']);
							echo esc_html($user ? (string) $user->user_login : '—');
							?></td>
							<td><?php echo esc_html((string) ($row['catch']['species'] ?? '')); ?></td>
							<td><?php echo esc_html((string) ($row['catch']['weight_lb'] ?? '') . 'lb ' . (string) ($row['catch']['weight_oz'] ?? '') . 'oz'); ?></td>
							<td><?php echo esc_html((string) ($row['catch']['consent_at'] ?? '')); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}
}
