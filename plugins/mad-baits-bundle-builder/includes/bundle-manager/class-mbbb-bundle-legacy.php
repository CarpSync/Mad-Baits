<?php
/**
 * Read-only view of bundles created before the Bundle Manager.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Projects existing deal data into the owner form without writing.
 */
final class MBBB_Bundle_Legacy {

	/**
	 * @param array<string, mixed> $context Product, slots, and deal meta.
	 * @return array<string, mixed>
	 */
	public static function project(array $context) {
		$config = MBBB_Bundle_Config::defaults();
		$slots  = isset($context['slots']) && is_array($context['slots']) ? $context['slots'] : array();
		$meta   = isset($context['deal_meta']) && is_array($context['deal_meta']) ? $context['deal_meta'] : array();

		$config['name']              = sanitize_text_field((string) ($context['name'] ?? ''));
		$config['short_description'] = sanitize_textarea_field((string) ($context['short_description'] ?? ''));
		$config['image_id']          = absint($context['image_id'] ?? 0);
		$config['status']            = self::status_from_product($context);
		$config['bundle_type']       = self::type_from_meta($meta, $slots);
		$config['quantity_mode']     = 'exact';

		$quantity = self::customer_quantity($slots);
		$config['quantity']     = $quantity;
		$config['min_quantity'] = $quantity;
		$config['max_quantity'] = $quantity;
		$config['unit']         = 'items';

		$ranges = isset($meta['boilie_ranges']) && is_array($meta['boilie_ranges']) ? $meta['boilie_ranges'] : array();
		$config['ranges'] = array_values(array_filter(array_map('sanitize_title', $ranges)));

		$price = MBBB_Bundle_Config::sanitize_decimal($context['regular_price'] ?? '');
		$config['pricing'] = array(
			'mode'        => 'fixed',
			'fixed_price' => $price,
			'percent'     => 10,
			'amount'      => '',
		);

		$config['display']['badge']       = sanitize_text_field((string) ($meta['badge_label'] ?? ''));
		$config['display']['button_text'] = '';
		$config['display']['helper_text'] = self::quantity_label($slots);
		$config['preserve_slots']          = true;
		$config['legacy']                  = true;
		$config['managed_by']              = '';

		return MBBB_Bundle_Config::sanitize($config);
	}

	/**
	 * Customer-facing quantity text for an existing slot bundle.
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return string
	 */
	public static function quantity_label(array $slots) {
		$counts = self::classify_counts($slots);
		$parts  = array();

		if ($counts['boilie'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: boilie choices */
				_n('%d boilie choice', '%d boilie choices', $counts['boilie'], 'mad-baits-bundle-builder'),
				$counts['boilie']
			);
		}
		if ($counts['hookbait'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: hookbait choices */
				_n('%d hookbait', '%d hookbaits', $counts['hookbait'], 'mad-baits-bundle-builder'),
				$counts['hookbait']
			);
		}
		if ($counts['liquid'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: liquids */
				_n('%d liquid', '%d liquids', $counts['liquid'], 'mad-baits-bundle-builder'),
				$counts['liquid']
			);
		}
		if ($counts['dip'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: dips */
				_n('%d dip', '%d dips', $counts['dip'], 'mad-baits-bundle-builder'),
				$counts['dip']
			);
		}
		if ($counts['pellet'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: pellets */
				_n('%d pellet', '%d pellets', $counts['pellet'], 'mad-baits-bundle-builder'),
				$counts['pellet']
			);
		}
		if (empty($parts) && $counts['other'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: choices */
				_n('%d choice', '%d choices', $counts['other'], 'mad-baits-bundle-builder'),
				$counts['other']
			);
		}

		return empty($parts) ? __('Existing bundle choices', 'mad-baits-bundle-builder') : implode(', ', $parts);
	}

	/**
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return int
	 */
	public static function customer_quantity(array $slots) {
		$counts = self::classify_counts($slots);
		if ($counts['boilie'] > 0) {
			return $counts['boilie'];
		}
		$total = $counts['hookbait'] + $counts['liquid'] + $counts['dip'] + $counts['pellet'] + $counts['other'];
		return $total;
	}

	/**
	 * @param array<string, mixed>             $meta  Deal meta.
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return string
	 */
	private static function type_from_meta(array $meta, array $slots) {
		$type = sanitize_key((string) ($meta['deal_type'] ?? ''));
		if ('fixed_bundle' === $type) {
			return 'fixed';
		}
		if (in_array($type, array('mix_and_match', 'quantity_bundle'), true)) {
			return 'mix_and_match';
		}
		unset($slots);
		return 'mix_and_match';
	}

	/**
	 * @param array<string, mixed> $context Product context.
	 * @return string
	 */
	private static function status_from_product(array $context) {
		$post_status = sanitize_key((string) ($context['post_status'] ?? 'draft'));
		$enabled     = ! empty($context['enabled']);
		if ('draft' === $post_status || 'auto-draft' === $post_status) {
			return 'draft';
		}
		if (! $enabled) {
			return 'disabled';
		}
		return 'active';
	}

	/**
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return array{boilie: int, hookbait: int, liquid: int, dip: int, pellet: int, other: int}
	 */
	private static function classify_counts(array $slots) {
		$counts = array(
			'boilie'   => 0,
			'hookbait' => 0,
			'liquid'   => 0,
			'dip'      => 0,
			'pellet'   => 0,
			'other'    => 0,
		);

		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$label = strtolower((string) ($slot['label'] ?? ''));
			if ('' === trim($label) && '' === trim((string) ($slot['key'] ?? ''))) {
				continue;
			}
			if (false !== strpos($label, 'pellet')) {
				++$counts['pellet'];
			} elseif (false !== strpos($label, 'hook')) {
				++$counts['hookbait'];
			} elseif (false !== strpos($label, 'liquid') || false !== strpos($label, '500ml')) {
				++$counts['liquid'];
			} elseif (false !== strpos($label, 'dip') || false !== strpos($label, '250ml')) {
				++$counts['dip'];
			} elseif (false !== strpos($label, 'split') || false !== strpos($label, 'boilie') || false !== strpos($label, '5kg') || false !== strpos($label, '10kg')) {
				++$counts['boilie'];
			} else {
				++$counts['other'];
			}
		}

		return $counts;
	}
}
