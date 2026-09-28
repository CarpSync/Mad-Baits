<?php
/**
 * Admin Team Access page.
 *
 * @var string $tab
 * @var array  $invites
 * @var string $notice
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;
?>
<div class="wrap mbta-admin">
	<h1><?php esc_html_e('Mad Baits Team Access', 'mad-baits-team-access'); ?></h1>

	<nav class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'invites' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=mbta-team-access&tab=invites')); ?>"><?php esc_html_e('Invites', 'mad-baits-team-access'); ?></a>
		<a class="nav-tab <?php echo 'notifications' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=mbta-team-access&tab=notifications')); ?>"><?php esc_html_e('Push notifications', 'mad-baits-team-access'); ?></a>
	</nav>

	<?php if ($notice) : ?>
		<?php $notice_class = isset($notice_type) && 'error' === $notice_type ? 'notice-error' : 'notice-success'; ?>
		<div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
	<?php endif; ?>

	<?php if ('notifications' === $tab) : ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mbta-admin__form">
			<?php wp_nonce_field('mbta_send_notification'); ?>
			<input type="hidden" name="action" value="mbta_send_notification" />
			<table class="form-table">
				<tr>
					<th><label for="mbta_title"><?php esc_html_e('Title', 'mad-baits-team-access'); ?></label></th>
					<td><input name="title" id="mbta_title" type="text" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="mbta_message"><?php esc_html_e('Message', 'mad-baits-team-access'); ?></label></th>
					<td><textarea name="message" id="mbta_message" rows="4" class="large-text" required></textarea></td>
				</tr>
				<tr>
					<th><label for="mbta_audience"><?php esc_html_e('Target role', 'mad-baits-team-access'); ?></label></th>
					<td>
						<select name="audience" id="mbta_audience">
							<?php foreach (MBTA_Push::get_audience_options() as $key => $label) : ?>
								<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="mbta_url"><?php esc_html_e('Product / page link', 'mad-baits-team-access'); ?></label></th>
					<td><input name="url" id="mbta_url" type="url" class="regular-text" placeholder="<?php echo esc_attr(MBTA_Portal::get_portal_url()); ?>" /></td>
				</tr>
				<tr>
					<th><label for="mbta_image"><?php esc_html_e('Image URL (optional)', 'mad-baits-team-access'); ?></label></th>
					<td><input name="image" id="mbta_image" type="url" class="regular-text" /></td>
				</tr>
			</table>
			<?php submit_button(__('Send private notification', 'mad-baits-team-access')); ?>
		</form>
	<?php else : ?>
		<h2><?php esc_html_e('Create invite', 'mad-baits-team-access'); ?></h2>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mbta-admin__form">
			<?php wp_nonce_field('mbta_save_invite'); ?>
			<input type="hidden" name="action" value="mbta_save_invite" />
			<table class="form-table">
				<tr>
					<th><label for="mbta_role"><?php esc_html_e('Role', 'mad-baits-team-access'); ?></label></th>
					<td>
						<select name="role" id="mbta_role" required>
							<?php foreach (MBTA_Roles::get_role_labels() as $slug => $label) : ?>
								<option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="mbta_code"><?php esc_html_e('Code (optional)', 'mad-baits-team-access'); ?></label></th>
					<td><input name="code" id="mbta_code" type="text" class="regular-text" placeholder="<?php esc_attr_e('Auto-generate if empty', 'mad-baits-team-access'); ?>" /></td>
				</tr>
				<tr>
					<th><label for="mbta_expires"><?php esc_html_e('Expiry date', 'mad-baits-team-access'); ?></label></th>
					<td><input name="expires_at" id="mbta_expires" type="datetime-local" /></td>
				</tr>
				<tr>
					<th><label for="mbta_limit"><?php esc_html_e('Usage limit', 'mad-baits-team-access'); ?></label></th>
					<td><input name="usage_limit" id="mbta_limit" type="number" min="1" step="1" /></td>
				</tr>
				<tr>
					<th><label for="mbta_invite_email"><?php esc_html_e('Invitee email', 'mad-baits-team-access'); ?></label></th>
					<td>
						<input name="invite_email" id="mbta_invite_email" type="email" class="regular-text" autocomplete="email" placeholder="name@example.com" />
						<p class="description"><?php esc_html_e('Optional. Required if you use “Create & send email”.', 'mad-baits-team-access'); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="mbta_notes"><?php esc_html_e('Notes', 'mad-baits-team-access'); ?></label></th>
					<td><textarea name="notes" id="mbta_notes" rows="3" class="large-text"></textarea></td>
				</tr>
			</table>
			<p class="submit">
				<?php submit_button(__('Create invite', 'mad-baits-team-access'), 'secondary', 'submit', false); ?>
				<?php submit_button(__('Create & send email', 'mad-baits-team-access'), 'primary', 'send_invite_email', false); ?>
			</p>
		</form>

		<h2><?php esc_html_e('Invite codes', 'mad-baits-team-access'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Code', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Email', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Role', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Used', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Limit', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Expires', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Status', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Link', 'mad-baits-team-access'); ?></th>
					<th><?php esc_html_e('Actions', 'mad-baits-team-access'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($invites)) : ?>
					<tr><td colspan="9"><?php esc_html_e('No invites yet.', 'mad-baits-team-access'); ?></td></tr>
				<?php else : ?>
					<?php foreach ($invites as $invite) : ?>
						<tr>
							<td><code><?php echo esc_html((string) $invite['code']); ?></code></td>
							<td>
								<?php if (! empty($invite['invite_email'])) : ?>
									<a href="mailto:<?php echo esc_attr((string) $invite['invite_email']); ?>"><?php echo esc_html((string) $invite['invite_email']); ?></a>
								<?php else : ?>
									<span aria-hidden="true">—</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html((string) $invite['role']); ?></td>
							<td><?php echo esc_html((string) ($invite['used_count'] ?? 0)); ?></td>
							<td><?php echo esc_html(isset($invite['usage_limit']) && $invite['usage_limit'] ? (string) $invite['usage_limit'] : '—'); ?></td>
							<td><?php echo esc_html(isset($invite['expires_at']) && $invite['expires_at'] ? (string) $invite['expires_at'] : '—'); ?></td>
							<td><?php echo esc_html((string) ($invite['status'] ?? '')); ?></td>
							<td><a href="<?php echo esc_url(MBTA_Invites::get_invite_url($invite)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open', 'mad-baits-team-access'); ?></a></td>
							<td>
								<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mbta_toggle_invite&id=' . absint($invite['id'])), 'mbta_toggle_invite')); ?>"><?php esc_html_e('Toggle active', 'mad-baits-team-access'); ?></a>
								|
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mbta-admin__inline-send" style="display:inline;">
									<?php wp_nonce_field('mbta_send_invite_email'); ?>
									<input type="hidden" name="action" value="mbta_send_invite_email" />
									<input type="hidden" name="invite_id" value="<?php echo esc_attr((string) absint($invite['id'])); ?>" />
									<?php if (! empty($invite['invite_email'])) : ?>
										<input type="hidden" name="invite_email" value="<?php echo esc_attr((string) $invite['invite_email']); ?>" />
										<button type="submit" class="button-link"><?php esc_html_e('Send email', 'mad-baits-team-access'); ?></button>
									<?php else : ?>
										<input type="email" name="invite_email" required placeholder="<?php esc_attr_e('Email', 'mad-baits-team-access'); ?>" class="regular-text" style="max-width:11rem;" />
										<button type="submit" class="button-link"><?php esc_html_e('Send email', 'mad-baits-team-access'); ?></button>
									<?php endif; ?>
								</form>
							</td>
						</tr>
						<?php if (! empty($invite['notes'])) : ?>
							<tr><td colspan="9"><em><?php echo esc_html((string) $invite['notes']); ?></em></td></tr>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
