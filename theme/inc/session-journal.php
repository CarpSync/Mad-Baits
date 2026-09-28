<?php
/**
 * Session journal foundation widgets.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Render lightweight journal cards on account dashboard.
 *
 * @return void
 */
function mad_baits_render_account_session_journal_panel() {
	if (! function_exists('is_account_page') || ! is_account_page()) {
		return;
	}

	$session_app_url = function_exists('mad_baits_get_session_app_url')
		? mad_baits_get_session_app_url()
		: home_url('/session/');
	?>
	<section class="mad-account-app-panel mad-account-app-panel--journal" data-session-journal-panel>
		<header>
			<h3><?php esc_html_e('Session Journal', 'mad-baits'); ?></h3>
			<p><?php esc_html_e('Open Session for your private fishing logbook, catches and bait performance — synced to your account.', 'mad-baits'); ?></p>
		</header>
		<div class="mad-account-app-panel__cards">
			<article class="mad-account-app-stat">
				<span><?php esc_html_e('Recent Sessions', 'mad-baits'); ?></span>
				<strong data-journal-stat="sessions">0</strong>
			</article>
			<article class="mad-account-app-stat">
				<span><?php esc_html_e('Favourite Ranges', 'mad-baits'); ?></span>
				<strong data-journal-stat="ranges">0</strong>
			</article>
			<article class="mad-account-app-stat">
				<span><?php esc_html_e('Most Used Baits', 'mad-baits'); ?></span>
				<strong data-journal-stat="baits">0</strong>
			</article>
		</div>
		<div class="mad-account-app-panel__actions">
			<a class="mad-button mad-button--small" href="<?php echo esc_url(is_string($session_app_url) ? $session_app_url : home_url('/session/')); ?>">
				<?php esc_html_e('Open Session', 'mad-baits'); ?>
			</a>
			<button class="mad-button mad-button--small mad-button--ghost" type="button" data-journal-log-quick>
				<?php esc_html_e('Log Quick Session', 'mad-baits'); ?>
			</button>
		</div>
		<div class="mad-account-app-panel__journal-list" data-journal-list></div>
	</section>
	<?php
}
add_action('woocommerce_account_dashboard', 'mad_baits_render_account_session_journal_panel', 10);
