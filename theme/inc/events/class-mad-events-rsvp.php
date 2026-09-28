<?php
/**
 * Event RSVP handling.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * RSVP storage and emails.
 */
class Mad_Events_RSVP {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('wp_ajax_mad_baits_event_rsvp', array(__CLASS__, 'handle_ajax'));
		add_action('wp_ajax_nopriv_mad_baits_event_rsvp', array(__CLASS__, 'handle_ajax'));
	}

	/**
	 * Count RSVPs for event.
	 *
	 * @param int $event_id Event ID.
	 * @return int
	 */
	public static function count_for_event($event_id) {
		$query = new WP_Query(
			array(
				'post_type'      => 'mad_event_rsvp',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_mad_event_rsvp_event_id',
						'value' => absint($event_id),
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Get RSVPs for event.
	 *
	 * @param int $event_id Event ID.
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_event($event_id, $limit = 50) {
		$posts = get_posts(
			array(
				'post_type'      => 'mad_event_rsvp',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => '_mad_event_rsvp_event_id',
						'value' => absint($event_id),
					),
				),
			)
		);

		$rows = array();
		foreach ($posts as $post) {
			if (! $post instanceof WP_Post) {
				continue;
			}
			$rows[] = array(
				'name'      => (string) get_post_meta($post->ID, '_mad_event_rsvp_name', true),
				'email'     => (string) get_post_meta($post->ID, '_mad_event_rsvp_email', true),
				'phone'     => (string) get_post_meta($post->ID, '_mad_event_rsvp_phone', true),
				'attendees' => (int) get_post_meta($post->ID, '_mad_event_rsvp_attendees', true),
				'notes'     => (string) get_post_meta($post->ID, '_mad_event_rsvp_notes', true),
				'date'      => get_the_date('', $post),
			);
		}
		return $rows;
	}

	/**
	 * Handle RSVP AJAX.
	 *
	 * @return void
	 */
	public static function handle_ajax() {
		check_ajax_referer('mad_baits_event_rsvp', 'nonce');

		if (! empty($_POST['mad_event_hp'])) {
			wp_send_json_error(array('message' => __('Submission blocked.', 'mad-baits')), 400);
		}

		$event_id  = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
		$name      = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
		$email     = isset($_POST['email']) ? sanitize_email(wp_unslash((string) $_POST['email'])) : '';
		$phone     = isset($_POST['phone']) ? sanitize_text_field(wp_unslash((string) $_POST['phone'])) : '';
		$attendees = isset($_POST['attendees']) ? max(1, min(20, absint($_POST['attendees']))) : 1;
		$notes     = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['notes'])) : '';

		$event = Mad_Events_Data::get_event($event_id);
		if (! $event || ! $event['rsvp_enabled']) {
			wp_send_json_error(array('message' => __('RSVP is not available for this event.', 'mad-baits')), 400);
		}

		if ('' === $name || '' === $email || ! is_email($email)) {
			wp_send_json_error(array('message' => __('Please enter a valid name and email.', 'mad-baits')), 400);
		}

		if ($event['max_attendees'] > 0) {
			$current = self::sum_attendees($event_id);
			if (($current + $attendees) > $event['max_attendees']) {
				wp_send_json_error(array('message' => __('This event has reached RSVP capacity.', 'mad-baits')), 400);
			}
		}

		$rsvp_id = wp_insert_post(
			array(
				'post_type'   => 'mad_event_rsvp',
				'post_status' => 'publish',
				'post_title'  => sprintf('%s — %s', $name, $event['title']),
			),
			true
		);

		if (is_wp_error($rsvp_id) || ! $rsvp_id) {
			wp_send_json_error(array('message' => __('Could not save RSVP.', 'mad-baits')), 500);
		}

		update_post_meta($rsvp_id, '_mad_event_rsvp_event_id', $event_id);
		update_post_meta($rsvp_id, '_mad_event_rsvp_name', $name);
		update_post_meta($rsvp_id, '_mad_event_rsvp_email', $email);
		update_post_meta($rsvp_id, '_mad_event_rsvp_phone', $phone);
		update_post_meta($rsvp_id, '_mad_event_rsvp_attendees', $attendees);
		update_post_meta($rsvp_id, '_mad_event_rsvp_notes', $notes);

		self::send_emails($event, compact('name', 'email', 'phone', 'attendees', 'notes'));

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: event title */
					__('Thanks for registering your interest in %s. We will send event updates closer to the day.', 'mad-baits'),
					$event['title']
				),
			)
		);
	}

	/**
	 * Sum attendee count.
	 *
	 * @param int $event_id Event ID.
	 * @return int
	 */
	private static function sum_attendees($event_id) {
		$rsvps = self::get_for_event($event_id, 500);
		$sum   = 0;
		foreach ($rsvps as $rsvp) {
			$sum += max(1, (int) $rsvp['attendees']);
		}
		return $sum;
	}

	/**
	 * Send admin and customer emails.
	 *
	 * @param array<string, mixed> $event Event.
	 * @param array<string, mixed> $data RSVP data.
	 * @return void
	 */
	private static function send_emails($event, $data) {
		$admin_email = get_option('admin_email');
		$subject     = __('New Mad Baits Event RSVP', 'mad-baits');
		$body        = sprintf(
			"Event: %s\nName: %s\nEmail: %s\nPhone: %s\nAttending: %d\nNotes: %s\n",
			$event['title'],
			$data['name'],
			$data['email'],
			$data['phone'],
			(int) $data['attendees'],
			$data['notes']
		);
		wp_mail($admin_email, $subject, $body);

		$customer_subject = sprintf(
			/* translators: %s: event name */
			__('Thanks for your interest in %s', 'mad-baits'),
			$event['title']
		);
		$customer_body = sprintf(
			/* translators: 1: name, 2: event title */
			__('Hi %1$s, thanks for registering your interest in %2$s. We will send event updates closer to the day.', 'mad-baits'),
			$data['name'],
			$event['title']
		);
		wp_mail($data['email'], $customer_subject, $customer_body);
	}
}
