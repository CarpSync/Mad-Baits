<?php
/**
 * Pure launch-date decisions for Mad Baits ranges and deals.
 *
 * No WordPress calls. Storefront code passes the current timestamp and timezone.
 *
 * @package MadBaits
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Default local datetime when the STP-only bulk deals become public.
 */
function mad_baits_stp_bulk_launch_default() {
	return '2026-10-23 00:00:00';
}

/**
 * Earliest local datetime Swan Mussel may be made public.
 */
function mad_baits_swan_mussel_earliest_default() {
	return '2027-01-01 00:00:00';
}

/**
 * Parse a site-local datetime into a unix timestamp.
 *
 * @param string                    $value    Local datetime, e.g. 2026-10-23 00:00:00.
 * @param DateTimeZone|string|null  $timezone Timezone. Defaults to UTC.
 * @return int Unix timestamp, or 0 when the value is empty or invalid.
 */
function mad_baits_parse_launch_datetime($value, $timezone = null) {
	$value = trim((string) $value);
	if ('' === $value) {
		return 0;
	}

	if ($timezone instanceof DateTimeZone) {
		$tz = $timezone;
	} else {
		$tz_name = is_string($timezone) && '' !== $timezone ? $timezone : 'UTC';
		try {
			$tz = new DateTimeZone($tz_name);
		} catch (Exception $exception) {
			unset($exception);
			$tz = new DateTimeZone('UTC');
		}
	}

	$formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d');
	foreach ($formats as $format) {
		$parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $tz);
		if ($parsed instanceof DateTimeImmutable) {
			$errors = DateTimeImmutable::getLastErrors();
			if (is_array($errors) && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0)) {
				continue;
			}
			return $parsed->getTimestamp();
		}
	}

	return 0;
}

/**
 * Whether a launch datetime has been reached.
 *
 * An empty launch datetime is not open. Callers that mean "always public"
 * should not pass a launch datetime at all.
 *
 * @param string                   $launch_at Local datetime.
 * @param int                      $now       Current unix timestamp.
 * @param DateTimeZone|string|null $timezone  Timezone used to read $launch_at.
 * @return bool
 */
function mad_baits_launch_is_open($launch_at, $now, $timezone = null) {
	$launch_ts = mad_baits_parse_launch_datetime($launch_at, $timezone);
	if ($launch_ts < 1) {
		return false;
	}

	return (int) $now >= $launch_ts;
}

/**
 * Decide whether a catalogue item may be shown on the public storefront.
 *
 * @param array<string, mixed>     $args {
 *     @type bool   $retired          Retired ranges (BBB) stay hidden.
 *     @type string $range_mode       hidden|live. Used for ranges such as Swan Mussel.
 *     @type string $range_earliest   Earliest local datetime a hidden range may go live.
 *     @type string $public_from      Optional product launch datetime. Empty means no product schedule.
 *     @type int    $now              Current unix timestamp.
 *     @type mixed  $timezone         Timezone for local datetimes.
 * }
 * @return bool True when the item must stay off the public storefront.
 */
function mad_baits_storefront_item_is_hidden(array $args) {
	$now      = isset($args['now']) ? (int) $args['now'] : 0;
	$timezone = $args['timezone'] ?? 'UTC';

	if (! empty($args['retired'])) {
		return true;
	}

	$public_from = isset($args['public_from']) ? trim((string) $args['public_from']) : '';
	if ('' !== $public_from && ! mad_baits_launch_is_open($public_from, $now, $timezone)) {
		return true;
	}

	$range_mode = isset($args['range_mode']) ? strtolower(trim((string) $args['range_mode'])) : '';
	if ('' === $range_mode) {
		return false;
	}

	if ('live' !== $range_mode) {
		return true;
	}

	$earliest = isset($args['range_earliest']) ? trim((string) $args['range_earliest']) : '';
	if ('' !== $earliest && ! mad_baits_launch_is_open($earliest, $now, $timezone)) {
		return true;
	}

	return false;
}
