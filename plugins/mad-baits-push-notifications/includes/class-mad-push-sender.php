<?php
/**
 * Push sender abstraction.
 *
 * @package MadBaitsPush
 */

if (! defined('ABSPATH')) {
	exit;
}

class Mad_Baits_Push_Sender {
	/**
	 * Check if Web Push library is available.
	 *
	 * @return bool
	 */
	public static function has_webpush_library() {
		return class_exists('\\Minishlink\\WebPush\\WebPush') && class_exists('\\Minishlink\\WebPush\\Subscription');
	}

	/**
	 * Send payload to subscribers.
	 *
	 * @param array<string,mixed> $payload Notification payload.
	 * @param string              $audience Audience key.
	 * @param string|null         $test_endpoint Optional endpoint for test send.
	 * @return array<string,mixed>
	 */
	public static function send($payload, $audience = 'all', $test_endpoint = null) {
		$title = isset($payload['title']) ? sanitize_text_field((string) $payload['title']) : '';
		$body  = isset($payload['body']) ? sanitize_textarea_field((string) $payload['body']) : '';
		$url   = isset($payload['url']) ? esc_url_raw((string) $payload['url']) : home_url('/shop/');
		$image = isset($payload['image']) ? esc_url_raw((string) $payload['image']) : '';
		$tag   = isset($payload['tag']) ? sanitize_key((string) $payload['tag']) : 'mad-baits-push';

		if (is_string($test_endpoint) && '' !== $test_endpoint) {
			$subscribers = Mad_Baits_Push_DB::get_active_subscribers('all', $test_endpoint);
		} else {
			$subscribers = Mad_Baits_Push_DB::get_active_subscribers($audience, null);
		}
		$subscribers = apply_filters('mad_baits_push_subscribers', $subscribers, $audience, $test_endpoint);
		$attempted   = count($subscribers);

		if ($attempted < 1) {
			return array(
				'attempted' => 0,
				'sent'      => 0,
				'failed'    => 0,
				'errors'    => array(__('No active subscribers matched this audience.', 'mad-baits-push')),
				'library'   => self::has_webpush_library(),
			);
		}

		if (! self::has_webpush_library()) {
			return array(
				'attempted' => $attempted,
				'sent'      => 0,
				'failed'    => $attempted,
				'errors'    => array(__('Web Push library missing. Run composer require minishlink/web-push', 'mad-baits-push')),
				'library'   => false,
			);
		}

		$public_key  = Mad_Baits_Push_Admin::get_public_vapid_key();
		$private_key = Mad_Baits_Push_Admin::get_private_vapid_key();
		if ('' === $public_key || '' === $private_key) {
			return array(
				'attempted' => $attempted,
				'sent'      => 0,
				'failed'    => $attempted,
				'errors'    => array(__('VAPID keys are missing. Configure keys in Push Notification Settings.', 'mad-baits-push')),
				'library'   => true,
			);
		}

		$auth = array(
			'VAPID' => array(
				'subject'    => home_url('/'),
				'publicKey'  => $public_key,
				'privateKey' => $private_key,
			),
		);

		$web_push = new \Minishlink\WebPush\WebPush($auth);
		$errors   = array();

		foreach ($subscribers as $subscriber) {
			$endpoint = isset($subscriber['endpoint']) ? (string) $subscriber['endpoint'] : '';
			if ('' === $endpoint) {
				continue;
			}

			$subscription = \Minishlink\WebPush\Subscription::create(
				array(
					'endpoint' => $endpoint,
					'keys'     => array(
						'p256dh' => isset($subscriber['public_key']) ? (string) $subscriber['public_key'] : '',
						'auth'   => isset($subscriber['auth_token']) ? (string) $subscriber['auth_token'] : '',
					),
				)
			);

			$body_payload = array(
				'title' => $title,
				'body'  => $body,
				'icon'  => home_url('/wp-content/themes/mad-baits/assets/img/icons/icon-192.png'),
				'badge' => home_url('/wp-content/themes/mad-baits/assets/img/icons/icon-192.png'),
				'data'  => array('url' => $url),
				'tag'   => $tag,
			);
			if ('' !== $image) {
				$body_payload['image'] = $image;
			}

			$web_push->queueNotification($subscription, wp_json_encode($body_payload));
		}

		$sent = 0;
		$failed = 0;
		foreach ($web_push->flush() as $report) {
			$endpoint = (string) $report->getRequest()->getUri();
			if ($report->isSuccess()) {
				$sent++;
				Mad_Baits_Push_DB::mark_sent($endpoint);
				continue;
			}

			$failed++;
			$reason = (string) $report->getReason();
			$errors[] = sprintf('%s: %s', $endpoint, $reason);
			$is_gone = false !== stripos($reason, '404') || false !== stripos($reason, '410');
			if ($is_gone) {
				Mad_Baits_Push_DB::mark_inactive($endpoint, $reason);
			} else {
				Mad_Baits_Push_DB::mark_error($endpoint, $reason);
			}
		}

		return array(
			'attempted' => $attempted,
			'sent'      => $sent,
			'failed'    => $failed,
			'errors'    => array_values(array_unique($errors)),
			'library'   => true,
		);
	}
}
