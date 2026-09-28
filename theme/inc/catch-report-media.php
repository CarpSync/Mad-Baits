<?php
/**
 * Catch report WordPress storage + media team email notifications.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/** @var string */
const MAD_BAITS_CATCH_REPORT_MEDIA_EMAIL = 'media@madbaits.com';

/**
 * Extended catch report meta keys.
 *
 * @return array<string, string>
 */
function mad_baits_get_catch_report_extended_meta_keys() {
	$base = function_exists('mad_baits_get_catch_report_meta_keys') ? mad_baits_get_catch_report_meta_keys() : array();
	return array_merge(
		$base,
		array(
			'angler_name'      => '_mad_catch_angler_name',
			'angler_email'     => '_mad_catch_angler_email',
			'angler_phone'     => '_mad_catch_angler_phone',
			'swim'             => '_mad_catch_swim',
			'hookbait'         => '_mad_catch_hookbait',
			'rig'              => '_mad_catch_rig',
			'date_caught'      => '_mad_catch_date_caught',
			'session_length'   => '_mad_catch_session_length',
			'weather_summary'  => '_mad_catch_weather_summary',
			'marketing_consent'=> '_mad_catch_marketing_consent',
			'session_id'       => '_mad_catch_session_id',
			'session_catch_id' => '_mad_catch_session_catch_id',
			'source'           => '_mad_catch_source',
			'email_sent'       => '_madbaits_email_sent',
			'email_error'      => '_madbaits_email_error',
			'email_sent_at'    => '_madbaits_email_sent_at',
			'social_ready'     => '_mad_catch_social_ready',
			'moderation_status'=> '_mad_catch_moderation_status',
		)
	);
}

/** @var array<string, string> */
const MAD_BAITS_CATCH_MODERATION_STATUSES = array(
	'pending'  => 'Pending',
	'approved' => 'Approved',
	'featured' => 'Featured',
	'rejected' => 'Rejected',
);

/**
 * @return string
 */
function mad_baits_get_catch_report_media_recipient() {
	return apply_filters('mad_baits_catch_report_media_email', MAD_BAITS_CATCH_REPORT_MEDIA_EMAIL);
}

/**
 * Detect password-manager / bot junk in single-line text fields.
 *
 * @param string $value Field value.
 * @return bool
 */
function mad_baits_catch_text_looks_like_autofill_garbage($value) {
	$value = trim(wp_strip_all_tags((string) $value));
	if ('' === $value || strlen($value) < 16) {
		return false;
	}
	if (preg_match('/\s/u', $value)) {
		return false;
	}
	return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $value);
}

/**
 * Validate fish weight looks human-entered.
 *
 * @param string $value Fish weight.
 * @return bool
 */
function mad_baits_is_valid_catch_fish_weight($value) {
	$value = trim((string) $value);
	if ('' === $value || mad_baits_catch_text_looks_like_autofill_garbage($value)) {
		return false;
	}
	if (preg_match('/\d/', $value)) {
		return true;
	}
	return (bool) preg_match('/(lb|lbs|kg|kilo|oz|ounce|gram|g)\b/i', $value);
}

/**
 * Validate sanitized catch payload before save.
 *
 * @param array<string, mixed> $clean Sanitized payload.
 * @return true|WP_Error
 */
function mad_baits_validate_catch_report_payload(array $clean) {
	if (mad_baits_catch_text_looks_like_autofill_garbage((string) ($clean['angler_name'] ?? ''))) {
		return new WP_Error(
			'mad_catch_invalid_name',
			__('Please enter your angler name (browser autofill may have filled this incorrectly — clear the field and type it again).', 'mad-baits')
		);
	}
	if (! mad_baits_is_valid_catch_fish_weight((string) ($clean['fish_weight'] ?? ''))) {
		return new WP_Error(
			'mad_catch_invalid_weight',
			__('Please enter a valid fish weight (e.g. 24lb 8oz).', 'mad-baits')
		);
	}
	if ('' === trim((string) ($clean['venue'] ?? ''))) {
		return new WP_Error('mad_catch_missing_venue', __('Venue is required.', 'mad-baits'));
	}
	if (mad_baits_catch_text_looks_like_autofill_garbage((string) ($clean['venue'] ?? ''))) {
		return new WP_Error(
			'mad_catch_invalid_venue',
			__('Please enter a valid venue or lake type.', 'mad-baits')
		);
	}
	if ('' === trim((string) ($clean['bait_used'] ?? ''))) {
		return new WP_Error('mad_catch_missing_bait', __('Bait used is required.', 'mad-baits'));
	}
	if (mad_baits_catch_text_looks_like_autofill_garbage((string) ($clean['bait_used'] ?? ''))) {
		return new WP_Error(
			'mad_catch_invalid_bait',
			__('Please enter a valid bait name.', 'mad-baits')
		);
	}
	return true;
}

/**
 * Sanitize catch report payload.
 *
 * @param array<string, mixed> $payload Raw payload.
 * @return array<string, mixed>
 */
function mad_baits_sanitize_catch_report_payload(array $payload) {
	$consent = ! empty($payload['marketing_consent']) || ! empty($payload['public_consent']);
	$weight  = sanitize_text_field((string) ($payload['fish_weight'] ?? ''));
	if ('' === $weight) {
		$lb = isset($payload['weight_lb']) ? (float) $payload['weight_lb'] : 0;
		$oz = isset($payload['weight_oz']) ? (float) $payload['weight_oz'] : 0;
		if ($lb > 0 || $oz > 0) {
			$weight = trim($lb . 'lb' . ($oz > 0 ? ' ' . $oz . 'oz' : ''));
		}
	}

	return array(
		'angler_name'       => sanitize_text_field((string) ($payload['angler_name'] ?? '')),
		'angler_email'      => sanitize_email((string) ($payload['angler_email'] ?? '')),
		'angler_phone'      => sanitize_text_field((string) ($payload['angler_phone'] ?? '')),
		'fish_weight'       => $weight,
		'venue'             => sanitize_text_field((string) ($payload['venue'] ?? $payload['venue_name'] ?? '')),
		'swim'              => sanitize_text_field((string) ($payload['swim'] ?? $payload['lake_swim'] ?? '')),
		'bait_used'         => sanitize_text_field((string) ($payload['bait_used'] ?? '')),
		'hookbait'          => sanitize_text_field((string) ($payload['hookbait'] ?? '')),
		'rig'               => sanitize_text_field((string) ($payload['rig'] ?? '')),
		'date_caught'       => sanitize_text_field((string) ($payload['date_caught'] ?? $payload['catch_time'] ?? '')),
		'session_length'    => sanitize_text_field((string) ($payload['session_length'] ?? '')),
		'weather_summary'   => sanitize_textarea_field((string) ($payload['weather_summary'] ?? '')),
		'story'             => sanitize_textarea_field((string) ($payload['story'] ?? $payload['session_notes'] ?? $payload['private_note'] ?? '')),
		'marketing_consent' => $consent ? 'yes' : 'no',
		'species'           => sanitize_text_field((string) ($payload['species'] ?? 'Carp')),
		'product_ids'       => isset($payload['product_ids']) && is_array($payload['product_ids'])
			? array_values(array_filter(array_unique(array_map('absint', $payload['product_ids']))))
			: array(),
		'product_link'      => esc_url_raw((string) ($payload['product_link'] ?? '')),
		'session_id'        => absint($payload['session_id'] ?? 0),
		'session_catch_id'  => sanitize_text_field((string) ($payload['session_catch_id'] ?? '')),
		'source'            => sanitize_key((string) ($payload['source'] ?? 'app')),
		'photo_ids'         => isset($payload['photo_ids']) && is_array($payload['photo_ids'])
			? array_values(array_filter(array_unique(array_map('absint', $payload['photo_ids']))))
			: array(),
		'post_status'       => in_array((string) ($payload['post_status'] ?? 'pending'), array('pending', 'draft', 'publish'), true)
			? (string) $payload['post_status']
			: 'pending',
	);
}

/**
 * Create catch_report post from sanitized payload.
 *
 * @param array<string, mixed> $payload Sanitized payload.
 * @return int|WP_Error Post ID or error.
 */
function mad_baits_create_catch_report_from_payload(array $payload) {
	$clean = mad_baits_sanitize_catch_report_payload($payload);
	if ('' === $clean['angler_name']) {
		$user = wp_get_current_user();
		if ($user && $user->exists()) {
			$clean['angler_name'] = $user->display_name ? $user->display_name : $user->user_login;
		}
	}
	if ('' === $clean['angler_name']) {
		$clean['angler_name'] = __('Mad Baits Angler', 'mad-baits');
	}
	if ('' === $clean['fish_weight']) {
		return new WP_Error('mad_catch_missing_weight', __('Fish weight is required.', 'mad-baits'));
	}

	$validation = mad_baits_validate_catch_report_payload($clean);
	if (is_wp_error($validation)) {
		return $validation;
	}

	$title = sprintf(
		/* translators: 1: angler name, 2: fish weight */
		__('%1$s — %2$s', 'mad-baits'),
		$clean['angler_name'],
		$clean['fish_weight']
	);

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'catch_report',
			'post_status'  => $clean['post_status'],
			'post_title'   => $title,
			'post_content' => $clean['story'],
			'post_author'  => get_current_user_id() > 0 ? get_current_user_id() : 0,
		),
		true
	);

	if (is_wp_error($post_id) || ! $post_id) {
		return is_wp_error($post_id) ? $post_id : new WP_Error('mad_catch_insert_failed', __('Could not save catch report.', 'mad-baits'));
	}

	$meta_keys = mad_baits_get_catch_report_extended_meta_keys();
	foreach (array('fish_weight', 'venue', 'bait_used', 'product_link') as $field) {
		if ('' !== $clean[ $field ] && isset($meta_keys[ $field ])) {
			update_post_meta($post_id, $meta_keys[ $field ], $clean[ $field ]);
		}
	}
	foreach (array('angler_name', 'angler_email', 'angler_phone', 'swim', 'hookbait', 'rig', 'date_caught', 'session_length', 'weather_summary', 'marketing_consent', 'session_id', 'session_catch_id', 'source') as $field) {
		if (isset($meta_keys[ $field ])) {
			update_post_meta($post_id, $meta_keys[ $field ], $clean[ $field ]);
		}
	}
	update_post_meta($post_id, $meta_keys['social_ready'], 'yes' === $clean['marketing_consent'] ? 'yes' : 'no');
	update_post_meta($post_id, $meta_keys['moderation_status'], 'yes' === $clean['marketing_consent'] ? 'pending' : 'pending');

	if (! empty($meta_keys['product_ids']) && ! empty($clean['product_ids'])) {
		update_post_meta($post_id, $meta_keys['product_ids'], $clean['product_ids']);
	}

	if (! empty($clean['photo_ids'])) {
		set_post_thumbnail($post_id, (int) $clean['photo_ids'][0]);
		update_post_meta($post_id, '_mad_baits_catch_gallery_ids', implode(',', array_map('absint', $clean['photo_ids'])));
	}

	update_post_meta($post_id, $meta_keys['email_sent'], 'no');
	delete_post_meta($post_id, $meta_keys['email_error']);
	delete_post_meta($post_id, $meta_keys['email_sent_at']);

	/**
	 * Fires after a catch report post is created.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $clean   Sanitized payload.
	 */
	do_action('mad_baits_catch_report_created', $post_id, $clean);

	return (int) $post_id;
}

/**
 * Collect report data for email/admin display.
 *
 * @param int $post_id Catch report ID.
 * @return array<string, mixed>
 */
function mad_baits_get_catch_report_email_context($post_id) {
	$post_id = absint($post_id);
	$keys    = mad_baits_get_catch_report_extended_meta_keys();
	$post    = get_post($post_id);

	$gallery = array();
	$raw_gallery = get_post_meta($post_id, '_mad_baits_catch_gallery_ids', true);
	if (is_string($raw_gallery) && '' !== $raw_gallery) {
		$gallery = array_values(array_filter(array_map('absint', explode(',', $raw_gallery))));
	}
	$thumb = (int) get_post_thumbnail_id($post_id);
	if ($thumb > 0 && ! in_array($thumb, $gallery, true)) {
		array_unshift($gallery, $thumb);
	}

	$photos = array();
	foreach ($gallery as $attachment_id) {
		$url = wp_get_attachment_image_url($attachment_id, 'medium');
		if ($url) {
			$photos[] = array(
				'id'  => $attachment_id,
				'url' => $url,
				'full'=> wp_get_attachment_url($attachment_id),
			);
		}
	}

	$consent = get_post_meta($post_id, $keys['marketing_consent'], true);
	return array(
		'post_id'          => $post_id,
		'admin_url'        => get_edit_post_link($post_id, 'raw'),
		'angler_name'      => (string) get_post_meta($post_id, $keys['angler_name'], true),
		'angler_email'     => (string) get_post_meta($post_id, $keys['angler_email'], true),
		'angler_phone'     => (string) get_post_meta($post_id, $keys['angler_phone'], true),
		'fish_weight'      => function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'fish_weight') : '',
		'venue'            => function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'venue') : '',
		'swim'             => (string) get_post_meta($post_id, $keys['swim'], true),
		'bait_used'        => function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'bait_used') : '',
		'hookbait'         => (string) get_post_meta($post_id, $keys['hookbait'], true),
		'rig'              => (string) get_post_meta($post_id, $keys['rig'], true),
		'date_caught'      => (string) get_post_meta($post_id, $keys['date_caught'], true),
		'session_length'   => (string) get_post_meta($post_id, $keys['session_length'], true),
		'weather_summary'  => (string) get_post_meta($post_id, $keys['weather_summary'], true),
		'story'            => $post instanceof WP_Post ? (string) $post->post_content : '',
		'marketing_consent'=> 'yes' === $consent,
		'source'           => (string) get_post_meta($post_id, $keys['source'], true),
		'photos'           => $photos,
		'submitted_at'     => $post instanceof WP_Post ? get_date_from_gmt($post->post_date_gmt, get_option('date_format') . ' ' . get_option('time_format')) : '',
	);
}

/**
 * Build HTML email body.
 *
 * @param array<string, mixed> $ctx Context.
 * @return string
 */
function mad_baits_build_catch_report_email_html(array $ctx) {
	$consent_label = ! empty($ctx['marketing_consent'])
		? __('Marketing consent given', 'mad-baits')
		: __('Marketing consent not given', 'mad-baits');
	$consent_color = ! empty($ctx['marketing_consent']) ? '#9dff9d' : '#ffb4a2';

	$rows = array(
		__('Angler', 'mad-baits')           => $ctx['angler_name'],
		__('Email', 'mad-baits')           => $ctx['angler_email'] ?: '—',
		__('Phone', 'mad-baits')           => $ctx['angler_phone'] ?: '—',
		__('Venue', 'mad-baits')           => $ctx['venue'] ?: '—',
		__('Swim / peg', 'mad-baits')      => $ctx['swim'] ?: '—',
		__('Date caught', 'mad-baits')     => $ctx['date_caught'] ?: '—',
		__('Fish weight', 'mad-baits')     => $ctx['fish_weight'],
		__('Bait used', 'mad-baits')       => $ctx['bait_used'] ?: '—',
		__('Hookbait', 'mad-baits')        => $ctx['hookbait'] ?: '—',
		__('Rig / approach', 'mad-baits')  => $ctx['rig'] ?: '—',
		__('Session length', 'mad-baits')  => $ctx['session_length'] ?: '—',
		__('Weather', 'mad-baits')         => $ctx['weather_summary'] ?: '—',
		__('Consent', 'mad-baits')         => '<span style="color:' . esc_attr($consent_color) . '">' . esc_html($consent_label) . '</span>',
	);

	$table_rows = '';
	foreach ($rows as $label => $value) {
		$table_rows .= '<tr><th align="left" style="padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#fff202;font-size:12px;text-transform:uppercase;letter-spacing:0.06em;width:34%">' . esc_html($label) . '</th><td style="padding:8px 12px;border-bottom:1px solid #2a2a2a;color:#f2f2f2">' . (strpos($value, '<') !== false ? $value : esc_html((string) $value)) . '</td></tr>';
	}

	$photo_html = '<p style="color:#bdbdbd;margin:0">' . esc_html__('No photo uploaded with this report.', 'mad-baits') . '</p>';
	if (! empty($ctx['photos'])) {
		$photo_html = '<table cellpadding="0" cellspacing="8"><tr>';
		$count = 0;
		foreach ($ctx['photos'] as $photo) {
			if ($count >= 3) {
				break;
			}
			$photo_html .= '<td><a href="' . esc_url((string) $photo['full']) . '"><img src="' . esc_url((string) $photo['url']) . '" alt="" width="140" style="display:block;border-radius:10px;border:1px solid #333" /></a></td>';
			++$count;
		}
		$photo_html .= '</tr></table>';
	}

	$story = '' !== (string) $ctx['story']
		? '<div style="margin-top:18px;padding:14px;border-radius:12px;background:#141414;border:1px solid #2a2a2a"><h3 style="margin:0 0 8px;color:#fff202;font-size:14px">' . esc_html__('Story / notes', 'mad-baits') . '</h3><p style="margin:0;color:#f0f0f0;line-height:1.5">' . nl2br(esc_html((string) $ctx['story'])) . '</p></div>'
		: '';

	return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#080808;font-family:Arial,sans-serif">' .
		'<table width="100%" cellpadding="0" cellspacing="0" style="background:#080808;padding:24px 12px"><tr><td align="center">' .
		'<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#101010;border:1px solid #2a2a2a;border-radius:16px;overflow:hidden">' .
		'<tr><td style="padding:22px 24px;background:linear-gradient(135deg,#141414,#080808);border-bottom:2px solid #fff202">' .
		'<p style="margin:0 0 6px;color:#fff202;font-size:12px;letter-spacing:0.12em;text-transform:uppercase">Mad Baits</p>' .
		'<h1 style="margin:0;color:#ffffff;font-size:22px">New Catch Report</h1>' .
		'<p style="margin:8px 0 0;color:#d0d0d0;font-size:13px">' . esc_html((string) $ctx['submitted_at']) . ' · ' . esc_html__('Submitted via the Mad Baits App', 'mad-baits') . '</p>' .
		'</td></tr><tr><td style="padding:22px 24px">' .
		'<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $table_rows . '</table>' .
		$story .
		'<div style="margin-top:18px"><h3 style="margin:0 0 10px;color:#fff202;font-size:14px">' . esc_html__('Photos', 'mad-baits') . '</h3>' . $photo_html . '</div>' .
		'<p style="margin:24px 0 0"><a href="' . esc_url((string) $ctx['admin_url']) . '" style="display:inline-block;padding:12px 18px;background:#fff202;color:#080808;text-decoration:none;font-weight:700;border-radius:999px">' . esc_html__('View Catch Report', 'mad-baits') . '</a></p>' .
		'</td></tr><tr><td style="padding:14px 24px;background:#0a0a0a;color:#8a8a8a;font-size:11px">' . esc_html__('Submitted via the Mad Baits App', 'mad-baits') . '</td></tr>' .
		'</table></td></tr></table></body></html>';
}

/**
 * Build plain-text email.
 *
 * @param array<string, mixed> $ctx Context.
 * @return string
 */
function mad_baits_build_catch_report_email_plain(array $ctx) {
	$lines = array(
		__('New Mad Baits Catch Report', 'mad-baits'),
		'',
		__('Angler', 'mad-baits') . ': ' . $ctx['angler_name'],
		__('Email', 'mad-baits') . ': ' . ($ctx['angler_email'] ?: '—'),
		__('Phone', 'mad-baits') . ': ' . ($ctx['angler_phone'] ?: '—'),
		__('Venue', 'mad-baits') . ': ' . ($ctx['venue'] ?: '—'),
		__('Swim / peg', 'mad-baits') . ': ' . ($ctx['swim'] ?: '—'),
		__('Date caught', 'mad-baits') . ': ' . ($ctx['date_caught'] ?: '—'),
		__('Fish weight', 'mad-baits') . ': ' . $ctx['fish_weight'],
		__('Bait used', 'mad-baits') . ': ' . ($ctx['bait_used'] ?: '—'),
		__('Hookbait', 'mad-baits') . ': ' . ($ctx['hookbait'] ?: '—'),
		__('Rig / approach', 'mad-baits') . ': ' . ($ctx['rig'] ?: '—'),
		__('Session length', 'mad-baits') . ': ' . ($ctx['session_length'] ?: '—'),
		__('Weather', 'mad-baits') . ': ' . ($ctx['weather_summary'] ?: '—'),
		__('Consent', 'mad-baits') . ': ' . (! empty($ctx['marketing_consent']) ? __('Given', 'mad-baits') : __('Not given', 'mad-baits')),
	);
	if ('' !== (string) $ctx['story']) {
		$lines[] = '';
		$lines[] = __('Story / notes', 'mad-baits') . ':';
		$lines[] = $ctx['story'];
	}
	$lines[] = '';
	if (! empty($ctx['photos'])) {
		$lines[] = __('Photos', 'mad-baits') . ':';
		foreach ($ctx['photos'] as $photo) {
			$lines[] = (string) $photo['full'];
		}
	} else {
		$lines[] = __('No photo uploaded.', 'mad-baits');
	}
	$lines[] = '';
	$lines[] = __('View in WordPress', 'mad-baits') . ': ' . $ctx['admin_url'];
	$lines[] = __('Submitted via the Mad Baits App', 'mad-baits');
	return implode("\n", $lines);
}

/**
 * Send media notification email for a catch report.
 *
 * @param int $post_id Catch report post ID.
 * @return bool
 */
function mad_baits_send_catch_report_media_email($post_id) {
	$post_id = absint($post_id);
	if ($post_id < 1 || 'catch_report' !== get_post_type($post_id)) {
		return false;
	}

	$keys = mad_baits_get_catch_report_extended_meta_keys();
	$ctx  = mad_baits_get_catch_report_email_context($post_id);
	$to   = mad_baits_get_catch_report_media_recipient();
	if (! is_email($to)) {
		update_post_meta($post_id, $keys['email_sent'], 'no');
		update_post_meta($post_id, $keys['email_error'], 'Invalid media recipient email.');
		return false;
	}

	$subject = sprintf(
		/* translators: 1: fish weight, 2: angler name */
		__('New Mad Baits Catch Report — %1$s from %2$s', 'mad-baits'),
		$ctx['fish_weight'],
		$ctx['angler_name']
	);

	$headers = array('Content-Type: text/html; charset=UTF-8');
	if (is_email($ctx['angler_email'])) {
		$headers[] = 'Reply-To: ' . $ctx['angler_email'];
	}

	$attachments = array();
	if (! empty($ctx['photos'][0]['id'])) {
		$file = get_attached_file((int) $ctx['photos'][0]['id']);
		if (is_string($file) && $file && file_exists($file)) {
			$size = filesize($file);
			if (false !== $size && $size > 0 && $size <= 2 * 1024 * 1024) {
				$attachments[] = $file;
			}
		}
	}

	$html_body  = mad_baits_build_catch_report_email_html($ctx);
	$plain_body = mad_baits_build_catch_report_email_plain($ctx);

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- PHPMailer class from wp_mail.
	global $phpmailer;
	if (isset($phpmailer) && is_object($phpmailer)) {
		$phpmailer->AltBody = $plain_body;
	}

	$sent = wp_mail($to, $subject, $html_body, $headers, $attachments);

	if ($sent) {
		update_post_meta($post_id, $keys['email_sent'], 'yes');
		delete_post_meta($post_id, $keys['email_error']);
		update_post_meta($post_id, $keys['email_sent_at'], gmdate('Y-m-d H:i:s'));
	} else {
		update_post_meta($post_id, $keys['email_sent'], 'no');
		update_post_meta($post_id, $keys['email_error'], 'wp_mail failed — check SMTP / mail logs.');
		if (function_exists('error_log')) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[Mad Baits] Catch report email failed for post ' . $post_id);
		}
	}

	return (bool) $sent;
}

/**
 * Create report and notify media (email failure does not fail create).
 *
 * @param array<string, mixed> $payload Payload.
 * @return array<string, mixed>|WP_Error
 */
function mad_baits_submit_catch_report(array $payload) {
	$post_id = mad_baits_create_catch_report_from_payload($payload);
	if (is_wp_error($post_id)) {
		return $post_id;
	}

	$email_sent = mad_baits_send_catch_report_media_email((int) $post_id);
	$keys       = mad_baits_get_catch_report_extended_meta_keys();

	return array(
		'catch_report_id' => (int) $post_id,
		'email_sent'      => $email_sent,
		'email_error'     => (string) get_post_meta((int) $post_id, $keys['email_error'], true),
		'admin_url'       => get_edit_post_link((int) $post_id, 'raw'),
	);
}

/**
 * Attach uploaded images to catch report.
 *
 * @param int   $post_id Catch report ID.
 * @param array $files   $_FILES-like structure for catch_images.
 * @return int[] Attachment IDs.
 */
function mad_baits_attach_catch_report_uploads($post_id, $files) {
	$post_id = absint($post_id);
	if ($post_id < 1 || empty($files['name']) || ! is_array($files['name'])) {
		return array();
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$allowed = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
	$max     = min(3, count($files['name']));
	$ids     = array();

	for ($i = 0; $i < $max; $i++) {
		if (empty($files['name'][ $i ]) || ! empty($files['error'][ $i ])) {
			continue;
		}
		$type = isset($files['type'][ $i ]) ? (string) $files['type'][ $i ] : '';
		if (! in_array(strtolower($type), $allowed, true)) {
			continue;
		}
		$size = isset($files['size'][ $i ]) ? (int) $files['size'][ $i ] : 0;
		if ($size < 1 || $size > 8 * 1024 * 1024) {
			continue;
		}
		$file_array = array(
			'name'     => sanitize_file_name((string) $files['name'][ $i ]),
			'type'     => $type,
			'tmp_name' => isset($files['tmp_name'][ $i ]) ? (string) $files['tmp_name'][ $i ] : '',
			'error'    => 0,
			'size'     => $size,
		);
		$attachment_id = media_handle_sideload($file_array, $post_id);
		if (! is_wp_error($attachment_id) && $attachment_id > 0) {
			$ids[] = (int) $attachment_id;
		}
	}

	if (! empty($ids)) {
		set_post_thumbnail($post_id, $ids[0]);
		update_post_meta($post_id, '_mad_baits_catch_gallery_ids', implode(',', $ids));
	}

	return $ids;
}

/**
 * Admin meta box: email delivery status + resend.
 */
function mad_baits_catch_report_email_meta_box() {
	add_meta_box(
		'mad_baits_catch_report_email',
		__('Media email', 'mad-baits'),
		'mad_baits_render_catch_report_email_meta_box',
		'catch_report',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'mad_baits_catch_report_email_meta_box');

/**
 * @param WP_Post $post Post.
 */
function mad_baits_render_catch_report_email_meta_box($post) {
	if (! $post instanceof WP_Post) {
		return;
	}
	$keys = mad_baits_get_catch_report_extended_meta_keys();
	$sent = get_post_meta($post->ID, $keys['email_sent'], true);
	$error = get_post_meta($post->ID, $keys['email_error'], true);
	$sent_at = get_post_meta($post->ID, $keys['email_sent_at'], true);
	$consent = get_post_meta($post->ID, $keys['marketing_consent'], true);
	?>
	<p><strong><?php esc_html_e('Recipient', 'mad-baits'); ?>:</strong><br /><?php echo esc_html(mad_baits_get_catch_report_media_recipient()); ?></p>
	<p><strong><?php esc_html_e('Email sent', 'mad-baits'); ?>:</strong><br /><?php echo 'yes' === $sent ? esc_html__('Yes', 'mad-baits') : esc_html__('No', 'mad-baits'); ?></p>
	<?php if ($sent_at) : ?>
		<p><strong><?php esc_html_e('Sent at', 'mad-baits'); ?>:</strong><br /><?php echo esc_html((string) $sent_at); ?></p>
	<?php endif; ?>
	<?php if ($error) : ?>
		<p style="color:#b32d2e"><strong><?php esc_html_e('Last error', 'mad-baits'); ?>:</strong><br /><?php echo esc_html((string) $error); ?></p>
	<?php endif; ?>
	<p><strong><?php esc_html_e('Marketing consent', 'mad-baits'); ?>:</strong><br /><?php echo 'yes' === $consent ? esc_html__('Given', 'mad-baits') : esc_html__('Not given', 'mad-baits'); ?></p>
	<?php
	$mod_status = get_post_meta($post->ID, $keys['moderation_status'], true);
	if (! $mod_status) {
		$mod_status = 'pending';
	}
	?>
	<p><strong><?php esc_html_e('Review status', 'mad-baits'); ?>:</strong><br /><?php echo esc_html(MAD_BAITS_CATCH_MODERATION_STATUSES[ $mod_status ] ?? $mod_status); ?></p>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('mad_baits_resend_catch_report_email', 'mad_baits_resend_catch_email_nonce'); ?>
		<input type="hidden" name="action" value="mad_baits_resend_catch_report_email" />
		<input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post->ID); ?>" />
		<?php submit_button(__('Resend to Media Team', 'mad-baits'), 'secondary', 'submit', false); ?>
	</form>
	<?php
}

/**
 * Resend handler.
 */
function mad_baits_handle_resend_catch_report_email() {
	if (! current_user_can('edit_posts')) {
		wp_die(esc_html__('Forbidden', 'mad-baits'));
	}
	check_admin_referer('mad_baits_resend_catch_report_email', 'mad_baits_resend_catch_email_nonce');
	$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
	$redirect = get_edit_post_link($post_id, 'raw');
	if (! $redirect) {
		$redirect = admin_url('edit.php?post_type=catch_report');
	}
	if ($post_id > 0) {
		$sent = mad_baits_send_catch_report_media_email($post_id);
		$redirect = add_query_arg('mad_catch_email', $sent ? 'resent' : 'failed', $redirect);
	}
	wp_safe_redirect($redirect);
	exit;
}
add_action('admin_post_mad_baits_resend_catch_report_email', 'mad_baits_handle_resend_catch_report_email');

/**
 * Admin notices for resend.
 */
function mad_baits_catch_report_email_admin_notices() {
	if (! isset($_GET['mad_catch_email'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$code = sanitize_key(wp_unslash((string) $_GET['mad_catch_email']));
	if ('resent' === $code) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Catch report email resent to the media team.', 'mad-baits') . '</p></div>';
	} elseif ('failed' === $code) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Catch report email could not be sent. Check the Media email box for details.', 'mad-baits') . '</p></div>';
	}
}
add_action('admin_notices', 'mad_baits_catch_report_email_admin_notices');

/**
 * Admin list columns.
 *
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function mad_baits_catch_report_admin_columns($columns) {
	$new = array();
	foreach ($columns as $key => $label) {
		$new[ $key ] = $label;
		if ('title' === $key) {
			$new['mad_catch_angler'] = __('Angler', 'mad-baits');
			$new['mad_catch_weight'] = __('Weight', 'mad-baits');
			$new['mad_catch_venue']  = __('Venue', 'mad-baits');
			$new['mad_catch_bait']   = __('Bait', 'mad-baits');
			$new['mad_catch_consent']= __('Consent', 'mad-baits');
			$new['mad_catch_email']  = __('Media email', 'mad-baits');
			$new['mad_catch_status'] = __('Status', 'mad-baits');
		}
	}
	return $new;
}
add_filter('manage_catch_report_posts_columns', 'mad_baits_catch_report_admin_columns');

/**
 * @param string $column Column.
 * @param int    $post_id Post ID.
 */
function mad_baits_catch_report_admin_column_content($column, $post_id) {
	$keys = mad_baits_get_catch_report_extended_meta_keys();
	switch ($column) {
		case 'mad_catch_angler':
			echo esc_html((string) get_post_meta($post_id, $keys['angler_name'], true));
			break;
		case 'mad_catch_weight':
			echo esc_html(function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'fish_weight') : '');
			break;
		case 'mad_catch_venue':
			echo esc_html(function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'venue') : '');
			break;
		case 'mad_catch_bait':
			echo esc_html(function_exists('mad_baits_get_catch_report_meta') ? mad_baits_get_catch_report_meta($post_id, 'bait_used') : '');
			break;
		case 'mad_catch_consent':
			echo 'yes' === get_post_meta($post_id, $keys['marketing_consent'], true) ? esc_html__('Yes', 'mad-baits') : esc_html__('No', 'mad-baits');
			break;
		case 'mad_catch_email':
			echo 'yes' === get_post_meta($post_id, $keys['email_sent'], true) ? esc_html__('Sent', 'mad-baits') : esc_html__('Failed', 'mad-baits');
			break;
		case 'mad_catch_status':
			$st = get_post_meta($post_id, $keys['moderation_status'], true);
			echo esc_html(MAD_BAITS_CATCH_MODERATION_STATUSES[ $st ] ?? ($st ? $st : 'Pending'));
			break;
	}
}
add_action('manage_catch_report_posts_custom_column', 'mad_baits_catch_report_admin_column_content', 10, 2);
