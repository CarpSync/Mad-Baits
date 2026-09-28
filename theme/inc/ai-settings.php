<?php
/**
 * Mad Baits AI Bait Finder settings (OpenAI key + model).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register AI finder settings.
 *
 * @return void
 */
function mad_baits_register_ai_settings() {
	register_setting(
		'mad_baits_ai_settings',
		'mad_baits_openai_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'mad_baits_sanitize_openai_api_key',
			'default'           => '',
		)
	);

	register_setting(
		'mad_baits_ai_settings',
		'mad_baits_openai_model',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gpt-4.1-mini',
		)
	);
}
add_action('admin_init', 'mad_baits_register_ai_settings');

/**
 * Sanitize stored API key (allow standard OpenAI key characters).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function mad_baits_sanitize_openai_api_key($value) {
	$value = is_string($value) ? trim($value) : '';
	if ('' === $value) {
		return '';
	}

	return preg_replace('/[^a-zA-Z0-9_\-]/', '', $value);
}

/**
 * Add settings page under Settings.
 *
 * @return void
 */
function mad_baits_add_ai_settings_page() {
	add_options_page(
		__('Mad Baits AI', 'mad-baits'),
		__('Mad Baits AI', 'mad-baits'),
		'manage_options',
		'mad-baits-ai',
		'mad_baits_render_ai_settings_page'
	);
}
add_action('admin_menu', 'mad_baits_add_ai_settings_page');

/**
 * Render AI settings admin page.
 *
 * @return void
 */
function mad_baits_render_ai_settings_page() {
	if (! current_user_can('manage_options')) {
		return;
	}

	$has_constant = defined('MAD_BAITS_OPENAI_API_KEY') && MAD_BAITS_OPENAI_API_KEY;
	$has_env      = function_exists('getenv') && getenv('MAD_BAITS_OPENAI_API_KEY');
	$model        = mad_baits_get_openai_model();
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Mad Baits AI Bait Finder', 'mad-baits'); ?></h1>
		<p><?php esc_html_e('Configure OpenAI so the AI Bait Finder can return live recommendations instead of the built-in fallback.', 'mad-baits'); ?></p>

		<?php if ($has_constant) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e('The API key is currently supplied via the MAD_BAITS_OPENAI_API_KEY constant in wp-config.php. Settings below are ignored until that constant is removed.', 'mad-baits'); ?></p>
			</div>
		<?php elseif ($has_env) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e('The API key is currently supplied via the MAD_BAITS_OPENAI_API_KEY environment variable.', 'mad-baits'); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields('mad_baits_ai_settings'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mad_baits_openai_api_key"><?php esc_html_e('OpenAI API Key', 'mad-baits'); ?></label></th>
					<td>
						<input
							type="password"
							id="mad_baits_openai_api_key"
							name="mad_baits_openai_api_key"
							value="<?php echo esc_attr(get_option('mad_baits_openai_api_key', '')); ?>"
							class="regular-text"
							autocomplete="off"
							<?php echo $has_constant ? 'disabled' : ''; ?>
						/>
						<p class="description">
							<?php esc_html_e('Create a restricted key at platform.openai.com. You can also define MAD_BAITS_OPENAI_API_KEY in wp-config.php.', 'mad-baits'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mad_baits_openai_model"><?php esc_html_e('Model', 'mad-baits'); ?></label></th>
					<td>
						<input
							type="text"
							id="mad_baits_openai_model"
							name="mad_baits_openai_model"
							value="<?php echo esc_attr($model); ?>"
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e('Default: gpt-4.1-mini', 'mad-baits'); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(__('Save AI Settings', 'mad-baits')); ?>
		</form>
	</div>
	<?php
}
