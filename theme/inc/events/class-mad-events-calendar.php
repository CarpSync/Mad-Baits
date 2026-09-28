<?php
/**
 * Add to calendar helpers.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Google Calendar and ICS generation.
 */
class Mad_Events_Calendar {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('template_redirect', array(__CLASS__, 'maybe_serve_ics'), 1);
	}

	/**
	 * Serve ICS download.
	 *
	 * @return void
	 */
	public static function maybe_serve_ics() {
		$slug = isset($_GET['mad_baits_event_ics']) ? sanitize_title(wp_unslash((string) $_GET['mad_baits_event_ics'])) : '';
		if ('' === $slug) {
			return;
		}

		$post = get_page_by_path($slug, OBJECT, 'mad_event');
		if (! $post instanceof WP_Post) {
			status_header(404);
			exit;
		}

		$event = Mad_Events_Data::get_event((int) $post->ID);
		if (! $event) {
			status_header(404);
			exit;
		}

		$ics = self::build_ics($event);
		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($event['slug']) . '.ics"');
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Google Calendar URL.
	 *
	 * @param array<string, mixed> $event Event.
	 * @return string
	 */
	public static function get_google_url($event) {
		$start = self::format_google_datetime((int) $event['start_datetime']);
		$end   = self::format_google_datetime((int) $event['end_datetime']);
		if ('' === $start) {
			return '';
		}
		if ('' === $end || $end <= $start) {
			$end = self::format_google_datetime((int) $event['start_datetime'] + 3 * HOUR_IN_SECONDS);
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => (string) $event['title'],
			'dates'    => $start . '/' . $end,
			'details'  => wp_strip_all_tags((string) ($event['short_description'] ?: $event['excerpt'])),
			'location' => trim((string) $event['location_name'] . ' ' . (string) $event['address']),
		);

		return add_query_arg($params, 'https://calendar.google.com/calendar/render');
	}

	/**
	 * ICS download URL.
	 *
	 * @param array<string, mixed> $event Event.
	 * @return string
	 */
	public static function get_ics_url($event) {
		return add_query_arg(
			array('mad_baits_event_ics' => (string) $event['slug']),
			home_url('/')
		);
	}

	/**
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	private static function format_google_datetime($timestamp) {
		if (! $timestamp) {
			return '';
		}
		return gmdate('Ymd\THis\Z', $timestamp);
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @return string
	 */
	private static function build_ics($event) {
		$uid     = 'mad-event-' . (int) $event['id'] . '@' . wp_parse_url(home_url('/'), PHP_URL_HOST);
		$start   = self::format_ics_datetime((int) $event['start_datetime']);
		$end     = self::format_ics_datetime((int) $event['end_datetime']);
		$summary = self::ics_escape((string) $event['title']);
		$desc    = self::ics_escape(wp_strip_all_tags((string) ($event['short_description'] ?: $event['excerpt'])));
		$loc     = self::ics_escape(trim((string) $event['location_name'] . ', ' . (string) $event['address'], ', '));

		if ('' === $end) {
			$end = self::format_ics_datetime((int) $event['start_datetime'] + 3 * HOUR_IN_SECONDS);
		}

		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Mad Baits//Events//EN\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:{$start}\r\nDTEND:{$end}\r\nSUMMARY:{$summary}\r\nDESCRIPTION:{$desc}\r\nLOCATION:{$loc}\r\nURL:" . esc_url_raw((string) $event['permalink']) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	}

	/**
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	private static function format_ics_datetime($timestamp) {
		return $timestamp ? gmdate('Ymd\THis\Z', $timestamp) : '';
	}

	/**
	 * @param string $value Value.
	 * @return string
	 */
	private static function ics_escape($value) {
		return str_replace(array("\r", "\n", ','), array('', '\\n', '\\,'), $value);
	}
}
