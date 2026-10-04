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
 * Confirmed STP prices in pounds.
 *
 * 10kg is two 5kg units and 20kg is four. The 1kg price is not derived from that rate.
 * Both sizes of a pack share the pack price. Swan has no price here.
 *
 * @return array<string, array<string, string>>
 */
function mad_baits_stp_confirmed_prices() {
	return array(
		'MB-STP-SL'   => array(
			'15mm' => '12.95',
			'18mm' => '12.95',
		),
		'MB-STP-10KG' => array(
			'15mm' => '119.90',
			'18mm' => '119.90',
		),
		'MB-STP-20KG' => array(
			'15mm' => '239.80',
			'18mm' => '239.80',
		),
	);
}

/**
 * @param string $sku_base Parent SKU, such as MB-STP-SL.
 * @param string $size     15mm or 18mm.
 * @return string Empty when this variation has no confirmed price.
 */
function mad_baits_stp_confirmed_variation_price($sku_base, $size) {
	$prices = mad_baits_stp_confirmed_prices();
	$sku    = strtoupper(trim((string) $sku_base));
	$size   = mad_baits_launch_slug($size);
	if (! isset($prices[ $sku ][ $size ])) {
		return '';
	}

	return $prices[ $sku ][ $size ];
}

/**
 * Convert a pounds string to pence without floating point.
 *
 * @param string $amount Amount such as 12.95.
 * @return int|null
 */
function mad_baits_money_to_pence($amount) {
	$amount = trim((string) $amount);
	if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
		return null;
	}

	$parts  = explode('.', $amount, 2);
	$pounds = (int) $parts[0];
	$pence  = isset($parts[1]) ? (int) str_pad(substr($parts[1], 0, 2), 2, '0', STR_PAD_RIGHT) : 0;

	return ($pounds * 100) + $pence;
}

/**
 * Fill a blank variation price. A price already above zero is left unchanged.
 *
 * @param mixed  $current   Current regular price.
 * @param string $confirmed Confirmed price.
 * @return bool
 */
function mad_baits_should_apply_confirmed_price($current, $confirmed) {
	if (! mad_baits_price_is_sellable($confirmed)) {
		return false;
	}

	return ! mad_baits_price_is_sellable($current);
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

/**
 * Normalise a slug without WordPress.
 *
 * @param string $value Raw slug.
 * @return string
 */
function mad_baits_launch_slug($value) {
	$value = strtolower(trim((string) $value));
	$value = str_replace(array('_', ' '), '-', $value);
	$value = preg_replace('/[^a-z0-9-]/', '', $value);

	return trim((string) $value, '-');
}

/**
 * Slugs that mean a product belongs to the Compulsive range.
 *
 * @return string[]
 */
function mad_baits_compulsive_range_slugs() {
	return array('compulsive', 'compulsive-angler', 'compulsive-anglers');
}

/**
 * Whether taxonomy slugs place a product in the Compulsive range.
 *
 * The Compulsive Special tag on its own is not a range.
 *
 * @param string[] $tag_slugs   Product tag slugs.
 * @param string[] $cat_slugs   Product category slugs.
 * @param string[] $range_slugs pa_range slugs.
 * @return bool
 */
function mad_baits_terms_match_compulsive_range(array $tag_slugs, array $cat_slugs, array $range_slugs) {
	$allowed = mad_baits_compulsive_range_slugs();
	$category_slugs = array_merge(
		$allowed,
		array('boilies-compulsive', 'boilies-compulsive-angler', 'boilies-compulsive-anglers')
	);

	foreach (array($tag_slugs, $range_slugs) as $slugs) {
		foreach ($slugs as $slug) {
			if (in_array(mad_baits_launch_slug($slug), $allowed, true)) {
				return true;
			}
		}
	}

	foreach ($cat_slugs as $slug) {
		if (in_array(mad_baits_launch_slug($slug), $category_slugs, true)) {
			return true;
		}
	}

	return false;
}

/**
 * A Compulsive special must be in the Compulsive range, carry the special tag,
 * and follow the sold-out rule.
 *
 * @param string[] $tag_slugs   Product tag slugs.
 * @param string[] $cat_slugs   Product category slugs.
 * @param string[] $range_slugs pa_range slugs.
 * @param bool     $in_stock    Whether WooCommerce reports the product in stock.
 * @param bool     $hide_oos    Whether sold-out specials are hidden.
 * @return bool
 */
function mad_baits_compulsive_special_qualifies(array $tag_slugs, array $cat_slugs, array $range_slugs, $in_stock, $hide_oos) {
	$tags = array();
	foreach ($tag_slugs as $slug) {
		$tags[] = mad_baits_launch_slug($slug);
	}

	if (! in_array('compulsive-special', $tags, true)) {
		return false;
	}

	if (! mad_baits_terms_match_compulsive_range($tag_slugs, $cat_slugs, $range_slugs)) {
		return false;
	}

	if ($hide_oos && ! $in_stock) {
		return false;
	}

	return true;
}

/**
 * WooCommerce post status for Swan Mussel.
 *
 * Live after the earliest date publishes the product. Every earlier state stays draft.
 *
 * @param string                   $mode     hidden|live.
 * @param int                      $now      Current unix timestamp.
 * @param DateTimeZone|string|null $timezone Timezone.
 * @return string publish|draft
 */
function mad_baits_swan_storefront_post_status($mode, $now, $timezone = null) {
	$hidden = mad_baits_storefront_item_is_hidden(
		array(
			'range_mode'     => $mode,
			'range_earliest' => mad_baits_swan_mussel_earliest_default(),
			'now'            => $now,
			'timezone'       => $timezone,
		)
	);

	return $hidden ? 'draft' : 'publish';
}

/**
 * Whether a price can be sold. Blank and zero stay unsellable.
 *
 * @param mixed $price Price.
 * @return bool
 */
function mad_baits_price_is_sellable($price) {
	if (is_string($price)) {
		$price = trim($price);
	}
	if (null === $price || false === $price || '' === $price || ! is_numeric($price)) {
		return false;
	}

	return (float) $price > 0;
}

/**
 * Whether one variation has a stock setup a customer can buy.
 *
 * @param array<string, mixed> $variation Variation snapshot.
 * @return bool
 */
function mad_baits_variation_stock_is_sellable(array $variation) {
	$status = strtolower(trim((string) ($variation['stock_status'] ?? '')));
	$manage = ! empty($variation['manage_stock']);
	$qty    = $variation['stock_qty'] ?? null;
	$backs  = strtolower(trim((string) ($variation['backorders'] ?? 'no')));

	if (! $manage) {
		return 'instock' === $status;
	}

	$qty_ok = is_numeric($qty) && (float) $qty > 0;
	if ('instock' === $status && $qty_ok) {
		return true;
	}

	return 'onbackorder' === $status && in_array($backs, array('yes', 'notify'), true);
}

/**
 * Whether an STP 10kg or 20kg deal is complete enough to sell.
 *
 * Prices are never filled in here. A blank price keeps the deal unavailable.
 * Tax must be the standard taxable class (empty tax class) or a real tax-class slug.
 * The product must stay a physical, shippable good.
 *
 * @param array<string, mixed> $snapshot {
 *     @type bool   $virtual
 *     @type bool   $downloadable
 *     @type bool   $needs_shipping
 *     @type string $tax_status
 *     @type string $tax_class
 *     @type array  $variations Keyed by 15mm and 18mm.
 * }
 * @return bool
 */
function mad_baits_stp_bulk_snapshot_is_purchasable(array $snapshot) {
	if (! empty($snapshot['virtual']) || ! empty($snapshot['downloadable'])) {
		return false;
	}
	if (array_key_exists('needs_shipping', $snapshot) && empty($snapshot['needs_shipping'])) {
		return false;
	}

	$tax_status = strtolower(trim((string) ($snapshot['tax_status'] ?? '')));
	if ('taxable' !== $tax_status) {
		return false;
	}

	$tax_class = strtolower(trim((string) ($snapshot['tax_class'] ?? '')));
	if ('' !== $tax_class && ! preg_match('/^[a-z0-9_-]+$/', $tax_class)) {
		return false;
	}

	$variations = isset($snapshot['variations']) && is_array($snapshot['variations']) ? $snapshot['variations'] : array();
	foreach (array('15mm', '18mm') as $size) {
		$variation = isset($variations[ $size ]) && is_array($variations[ $size ]) ? $variations[ $size ] : null;
		if (null === $variation) {
			return false;
		}
		if (! mad_baits_price_is_sellable($variation['price'] ?? '')) {
			return false;
		}
		if (! mad_baits_variation_stock_is_sellable($variation)) {
			return false;
		}
		if (! empty($variation['virtual']) || ! empty($variation['downloadable'])) {
			return false;
		}

		$variation_tax = strtolower(trim((string) ($variation['tax_status'] ?? '')));
		if ('' === $variation_tax || 'parent' === $variation_tax) {
			$variation_tax = $tax_status;
		}
		if ('taxable' !== $variation_tax) {
			return false;
		}
	}

	return true;
}

/**
 * Public only when the STP launch instant has passed and the catalogue snapshot can be sold.
 *
 * @param int                      $now      Current unix timestamp.
 * @param DateTimeZone|string|null $timezone Timezone.
 * @param array<string, mixed>     $snapshot Catalogue snapshot.
 * @param string                   $launch_at Launch datetime. Defaults to 23 October 2026.
 * @return bool
 */
function mad_baits_stp_bulk_is_public($now, $timezone, array $snapshot, $launch_at = '') {
	if ('' === (string) $launch_at) {
		$launch_at = mad_baits_stp_bulk_launch_default();
	}

	$date_hidden = mad_baits_storefront_item_is_hidden(
		array(
			'public_from' => $launch_at,
			'now'         => $now,
			'timezone'    => $timezone,
		)
	);
	if ($date_hidden) {
		return false;
	}

	return mad_baits_stp_bulk_snapshot_is_purchasable($snapshot);
}

/**
 * Post status for a newly created or not-yet-approved STP 1kg product.
 *
 * Publishing is an explicit shop action. This never follows the 23 October bulk date.
 *
 * @param bool $owner_published Whether an admin has published this product.
 * @return string publish|draft
 */
function mad_baits_stp_shelf_life_post_status($owner_published) {
	return $owner_published ? 'publish' : 'draft';
}

/**
 * Whether the normal STP 1kg product may appear on the storefront.
 *
 * There is no launch date. A blank price, missing image, or missing explicit
 * publish keeps it private. Bulk-deal scheduling is a separate decision.
 *
 * @param array<string, mixed> $snapshot         Same shape as a bulk snapshot, plus has_image.
 * @param bool                 $owner_published  Admin has published the product.
 * @return bool
 */
function mad_baits_stp_shelf_life_is_public(array $snapshot, $owner_published) {
	if (! $owner_published) {
		return false;
	}
	if (empty($snapshot['has_image'])) {
		return false;
	}

	return mad_baits_stp_bulk_snapshot_is_purchasable($snapshot);
}
