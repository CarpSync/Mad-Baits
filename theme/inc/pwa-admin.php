<?php
/**
 * Admin tools for Mad Baits PWA / mobile app refresh.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register admin menu and handlers.
 *
 * @return void
 */
function mad_baits_pwa_admin_init() {
	if (! class_exists('WooCommerce', false)) {
		add_options_page(
			__('Mad Baits App / PWA', 'mad-baits'),
			__('Mad Baits App / PWA', 'mad-baits'),
			'manage_options',
			'mad-baits-pwa-tools',
			'mad_baits_render_pwa_admin_page'
		);
		return;
	}

	add_submenu_page(
		'woocommerce',
		__('Mad Baits App / PWA', 'mad-baits'),
		__('App / PWA', 'mad-baits'),
		'manage_options',
		'mad-baits-pwa-tools',
		'mad_baits_render_pwa_admin_page'
	);
}
add_action('admin_menu', 'mad_baits_pwa_admin_init', 58);

/**
 * Handle admin POST actions.
 *
 * @return void
 */
function mad_baits_pwa_admin_handle_actions() {
	if (! is_admin() || ! current_user_can('manage_options')) {
		return;
	}

	$page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
	if ('mad-baits-pwa-tools' !== $page) {
		return;
	}

	if (! isset($_POST['mad_baits_pwa_action'])) {
		return;
	}

	$action = sanitize_key(wp_unslash((string) $_POST['mad_baits_pwa_action']));

	if ('force_refresh' === $action) {
		check_admin_referer('mad_baits_force_app_refresh', 'mad_baits_pwa_nonce');
		mad_baits_bump_app_version();
		add_settings_error(
			'mad_baits_pwa_tools',
			'mad_baits_app_refresh',
			__('Mobile app refresh requested. Users will receive the latest version when they next open or interact with the app.', 'mad-baits'),
			'success'
		);
		return;
	}

	if ('reset_version' === $action) {
		check_admin_referer('mad_baits_reset_app_version', 'mad_baits_pwa_nonce');
		$fresh = mad_baits_get_default_app_version();
		update_option(MAD_BAITS_APP_VERSION_OPTION, $fresh, false);
		update_option(MAD_BAITS_APP_VERSION_REFRESH_OPTION, time(), false);
		mad_baits_clear_pwa_transients();
		add_settings_error(
			'mad_baits_pwa_tools',
			'mad_baits_app_reset',
			__('PWA cache version reset to today’s baseline. Use “Force Mobile App Refresh” after your next deploy.', 'mad-baits'),
			'updated'
		);
	}
}
add_action('admin_init', 'mad_baits_pwa_admin_handle_actions');

/**
 * Render admin App / PWA tools page.
 *
 * @return void
 */
function mad_baits_render_pwa_admin_page() {
	if (! current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'mad-baits'));
	}

	$version       = mad_baits_get_app_version();
	$refreshed_at  = mad_baits_get_app_version_refreshed_at();
	$sw_url        = function_exists('mad_baits_get_pwa_endpoint_url') ? mad_baits_get_pwa_endpoint_url('service-worker') : '';
	$refreshed_txt = $refreshed_at > 0
		? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $refreshed_at)
		: __('Never', 'mad-baits');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Mad Baits App / PWA Settings', 'mad-baits'); ?></h1>

		<?php settings_errors('mad_baits_pwa_tools'); ?>

		<div class="notice notice-warning inline" style="margin:1rem 0;padding:0.75rem 1rem;">
			<p><?php esc_html_e('Do not use during active checkout testing unless needed. Users may be prompted to refresh.', 'mad-baits'); ?></p>
		</div>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e('Current app version', 'mad-baits'); ?></th>
					<td><code><?php echo esc_html($version); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Last refresh requested', 'mad-baits'); ?></th>
					<td><?php echo esc_html($refreshed_txt); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Service worker cache', 'mad-baits'); ?></th>
					<td><code><?php echo esc_html(mad_baits_get_pwa_cache_name()); ?></code></td>
				</tr>
				<?php if ('' !== $sw_url) : ?>
					<tr>
						<th scope="row"><?php esc_html_e('Service worker file', 'mad-baits'); ?></th>
						<td>
							<a href="<?php echo esc_url($sw_url); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html($sw_url); ?>
							</a>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<hr />

		<h2><?php esc_html_e('Force Mobile App Refresh', 'mad-baits'); ?></h2>
		<p class="description">
			<?php esc_html_e('Use this after design, menu, checkout or bundle builder updates to make installed app users load the latest version.', 'mad-baits'); ?>
		</p>

		<form method="post" action="" style="margin:1rem 0;">
			<?php wp_nonce_field('mad_baits_force_app_refresh', 'mad_baits_pwa_nonce'); ?>
			<input type="hidden" name="mad_baits_pwa_action" value="force_refresh" />
			<?php submit_button(__('Force Mobile App Refresh', 'mad-baits'), 'primary', 'submit', false); ?>
		</form>

		<h2><?php esc_html_e('Optional tools', 'mad-baits'); ?></h2>
		<form method="post" action="" style="margin:0.5rem 0 1.5rem;">
			<?php wp_nonce_field('mad_baits_reset_app_version', 'mad_baits_pwa_nonce'); ?>
			<input type="hidden" name="mad_baits_pwa_action" value="reset_version" />
			<?php submit_button(__('Clear PWA Cache Version', 'mad-baits'), 'secondary', 'submit', false); ?>
			<p class="description"><?php esc_html_e('Resets the version counter to today’s baseline without deploying new files.', 'mad-baits'); ?></p>
		</form>
	</div>
	<?php
}
