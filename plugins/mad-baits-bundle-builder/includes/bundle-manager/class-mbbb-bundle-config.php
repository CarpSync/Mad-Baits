<?php
/**
 * Owner-facing bundle settings.
 *
 * Stored separately from WooCommerce slot meta so existing deals can be
 * displayed without rewriting them.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Normalises the plain-English bundle form.
 */
final class MBBB_Bundle_Config {

	const META_KEY     = '_mbbb_owner_bundle';
	const SNAPSHOT_KEY = '_mbbb_owner_bundle_snapshot';
	const MANAGED_BY   = 'bundle-manager';
	const MAX_CHOICES  = 30;

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'name'              => '',
			'short_description' => '',
			'image_id'          => 0,
			'status'            => 'draft',
			'start_date'        => '',
			'end_date'          => '',
			'bundle_type'       => 'mix_and_match',
			'quantity_mode'     => 'exact',
			'quantity'          => 10,
			'min_quantity'      => 10,
			'max_quantity'      => 10,
			'multiple_of'       => 0,
			'unit'              => 'bags',
			'unit_custom'       => '',
			'ranges'            => array(),
			'sizes'             => array(),
			'bait_format'       => '',
			'categories'        => array(),
			'product_ids'       => array(),
			'variation_ids'     => array(),
			'attributes'        => array(),
			'fixed_items'       => array(),
			'pricing'           => array(
				'mode'        => 'fixed',
				'fixed_price' => '',
				'percent'     => 10,
				'amount'      => '',
			),
			'stock'             => array(
				'hide_unavailable' => true,
				'prevent_oos'      => true,
			),
			'display'           => array(
				'button_text' => __('Build Your Bundle', 'mad-baits-bundle-builder'),
				'helper_text' => '',
				'badge'       => '',
			),
			'preserve_slots'    => false,
			'managed_by'        => '',
			'legacy'            => false,
		);
	}

	/**
	 * @param array<string, mixed> $input Raw form or stored config.
	 * @return array<string, mixed>
	 */
	public static function sanitize(array $input) {
		$defaults = self::defaults();
		$config   = $defaults;

		$config['name']              = sanitize_text_field((string) ($input['name'] ?? ''));
		$config['short_description'] = sanitize_textarea_field((string) ($input['short_description'] ?? ''));
		$config['image_id']          = absint($input['image_id'] ?? 0);

		$status = sanitize_key((string) ($input['status'] ?? 'draft'));
		$config['status'] = in_array($status, array('draft', 'active', 'disabled'), true) ? $status : 'draft';

		$config['start_date'] = self::sanitize_date($input['start_date'] ?? '');
		$config['end_date']   = self::sanitize_date($input['end_date'] ?? '');

		$type = sanitize_key((string) ($input['bundle_type'] ?? 'mix_and_match'));
		$config['bundle_type'] = in_array($type, array('mix_and_match', 'fixed'), true) ? $type : 'mix_and_match';

		$mode = sanitize_key((string) ($input['quantity_mode'] ?? 'exact'));
		$config['quantity_mode'] = in_array($mode, array('exact', 'minimum'), true) ? $mode : 'exact';

		$quantity = absint($input['quantity'] ?? 0);
		$min      = absint($input['min_quantity'] ?? 0);
		$max      = absint($input['max_quantity'] ?? 0);
		if ('exact' === $config['quantity_mode']) {
			$config['quantity']      = $quantity;
			$config['min_quantity']  = $quantity;
			$config['max_quantity']  = $quantity;
		} else {
			$config['min_quantity'] = $min;
			$config['max_quantity'] = $max;
			$config['quantity']     = $min > 0 ? $min : $max;
		}

		$multiple = absint($input['multiple_of'] ?? 0);
		$config['multiple_of'] = $multiple >= 2 ? $multiple : 0;

		$unit = sanitize_key((string) ($input['unit'] ?? 'bags'));
		$config['unit'] = in_array($unit, array('bags', 'tubs', 'bottles', 'items', 'custom'), true) ? $unit : 'bags';
		$config['unit_custom'] = sanitize_text_field((string) ($input['unit_custom'] ?? ''));

		$config['ranges']        = self::string_list($input['ranges'] ?? array());
		$config['sizes']         = self::string_list($input['sizes'] ?? array());
		$bait_format             = sanitize_key((string) ($input['bait_format'] ?? ''));
		$config['bait_format']   = in_array($bait_format, array('shelf_life', 'freezer', 'both'), true) ? $bait_format : '';
		$config['categories']    = self::string_list($input['categories'] ?? array());
		$config['product_ids']   = self::int_list($input['product_ids'] ?? array());
		$config['variation_ids'] = self::int_list($input['variation_ids'] ?? array());
		$config['attributes']    = self::sanitize_attributes($input['attributes'] ?? array());
		$config['fixed_items']   = self::sanitize_fixed_items($input['fixed_items'] ?? array());

		$pricing = isset($input['pricing']) && is_array($input['pricing']) ? $input['pricing'] : $input;
		$price_mode = sanitize_key((string) ($pricing['mode'] ?? $pricing['pricing_mode'] ?? 'fixed'));
		if (! in_array($price_mode, array('fixed', 'percent', 'amount'), true)) {
			$price_mode = 'fixed';
		}
		$config['pricing'] = array(
			'mode'        => $price_mode,
			'fixed_price' => self::sanitize_decimal($pricing['fixed_price'] ?? ''),
			'percent'     => max(0, min(100, absint($pricing['percent'] ?? 0))),
			'amount'      => self::sanitize_decimal($pricing['amount'] ?? ''),
		);

		$stock = isset($input['stock']) && is_array($input['stock']) ? $input['stock'] : $input;
		$config['stock'] = array(
			'hide_unavailable' => self::is_checked($stock['hide_unavailable'] ?? true),
			'prevent_oos'      => self::is_checked($stock['prevent_oos'] ?? true),
		);

		$display = isset($input['display']) && is_array($input['display']) ? $input['display'] : $input;
		$config['display'] = array(
			'button_text' => sanitize_text_field((string) ($display['button_text'] ?? $defaults['display']['button_text'])),
			'helper_text' => sanitize_text_field((string) ($display['helper_text'] ?? '')),
			'badge'       => sanitize_text_field((string) ($display['badge'] ?? '')),
		);

		$config['preserve_slots'] = self::is_checked($input['preserve_slots'] ?? false);
		$config['legacy']         = self::is_checked($input['legacy'] ?? false);
		$managed                  = sanitize_key((string) ($input['managed_by'] ?? ''));
		$config['managed_by']     = self::MANAGED_BY === $managed ? self::MANAGED_BY : '';

		return $config;
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @param string               $today  Y-m-d.
	 * @return string active|draft|disabled|scheduled
	 */
	public static function effective_status(array $config, $today = '') {
		$status = isset($config['status']) ? (string) $config['status'] : 'draft';
		if ('draft' === $status) {
			return 'draft';
		}
		if ('disabled' === $status) {
			return 'disabled';
		}

		$today = self::sanitize_date($today);
		if ('' === $today) {
			$today = gmdate('Y-m-d');
		}
		$start = (string) ($config['start_date'] ?? '');
		$end   = (string) ($config['end_date'] ?? '');
		if ('' !== $start && $today < $start) {
			return 'scheduled';
		}
		if ('' !== $end && $today > $end) {
			return 'scheduled';
		}

		return 'active';
	}

	/**
	 * @param string               $key    Effective status key.
	 * @param array<string, mixed> $config Config.
	 * @param string               $today  Y-m-d.
	 * @return string
	 */
	public static function status_label($key, array $config = array(), $today = '') {
		if ('scheduled' === $key) {
			$today = self::sanitize_date($today);
			if ('' === $today) {
				$today = gmdate('Y-m-d');
			}
			$end = (string) ($config['end_date'] ?? '');
			if ('' !== $end && $today > $end) {
				return __('Ended', 'mad-baits-bundle-builder');
			}
			return __('Scheduled', 'mad-baits-bundle-builder');
		}

		$labels = array(
			'active'   => __('Active', 'mad-baits-bundle-builder'),
			'draft'    => __('Draft', 'mad-baits-bundle-builder'),
			'disabled' => __('Disabled', 'mad-baits-bundle-builder'),
		);

		return $labels[ $key ] ?? $labels['draft'];
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @param string               $today  Y-m-d.
	 * @return bool
	 */
	public static function is_purchasable(array $config, $today = '') {
		return 'active' === self::effective_status($config, $today);
	}

	/**
	 * How a bundle should be stored and whether customers can buy it.
	 *
	 * Draft and disabled products are unpublished. Scheduled and ended bundles
	 * stay published so the page can explain they are unavailable, but they are
	 * hidden from the catalogue and not purchasable.
	 *
	 * @param array<string, mixed> $config Owner config.
	 * @param string               $today  Y-m-d.
	 * @return array{post_status: string, catalog_visibility: string, purchasable: bool, effective: string}
	 */
	public static function publication_state(array $config, $today = '') {
		$stored    = (string) ($config['status'] ?? 'draft');
		$effective = self::effective_status($config, $today);
		if ('draft' === $stored || 'disabled' === $stored) {
			return array(
				'post_status'         => 'draft',
				'catalog_visibility'  => 'hidden',
				'purchasable'         => false,
				'effective'           => $effective,
			);
		}

		return array(
			'post_status'        => 'publish',
			'catalog_visibility' => 'active' === $effective ? 'visible' : 'hidden',
			'purchasable'        => 'active' === $effective,
			'effective'          => $effective,
		);
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @param int                  $count  Quantity used for plural wording.
	 * @return string
	 */
	public static function unit_word(array $config, $count = 2) {
		$unit = isset($config['unit']) ? (string) $config['unit'] : 'items';
		if ('custom' === $unit) {
			$custom = trim((string) ($config['unit_custom'] ?? ''));
			if ('' !== $custom) {
				return $custom;
			}
			$unit = 'items';
		}

		$map = array(
			'bags'    => array(__('bag', 'mad-baits-bundle-builder'), __('bags', 'mad-baits-bundle-builder')),
			'tubs'    => array(__('tub', 'mad-baits-bundle-builder'), __('tubs', 'mad-baits-bundle-builder')),
			'bottles' => array(__('bottle', 'mad-baits-bundle-builder'), __('bottles', 'mad-baits-bundle-builder')),
			'items'   => array(__('item', 'mad-baits-bundle-builder'), __('items', 'mad-baits-bundle-builder')),
		);
		$pair = $map[ $unit ] ?? $map['items'];

		return 1 === (int) $count ? $pair[0] : $pair[1];
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return string
	 */
	public static function choice_sentence(array $config) {
		if ('fixed' === ($config['bundle_type'] ?? '')) {
			$count = 0;
			foreach ((array) ($config['fixed_items'] ?? array()) as $item) {
				if (is_array($item)) {
					$count += max(1, (int) ($item['quantity'] ?? 1));
				}
			}
			if ($count < 1) {
				return __('Fixed set of products', 'mad-baits-bundle-builder');
			}
			return sprintf(
				/* translators: %d: number of included products */
				_n('%d included product', '%d included products', $count, 'mad-baits-bundle-builder'),
				$count
			);
		}

		if ('minimum' === ($config['quantity_mode'] ?? 'exact')) {
			$min  = (int) ($config['min_quantity'] ?? 0);
			$max  = (int) ($config['max_quantity'] ?? 0);
			$unit = self::unit_word($config, max($max, $min, 2));
			if ($max > 0) {
				return sprintf(
					/* translators: 1: minimum 2: maximum 3: unit plural */
					__('Choose %1$d to %2$d %3$s', 'mad-baits-bundle-builder'),
					$min,
					$max,
					$unit
				);
			}
			return sprintf(
				/* translators: 1: minimum 2: unit */
				__('Choose at least %1$d %2$s', 'mad-baits-bundle-builder'),
				$min,
				$unit
			);
		}

		$count = (int) ($config['quantity'] ?? 0);
		return sprintf(
			/* translators: 1: quantity 2: unit */
			__('Choose any %1$d %2$s', 'mad-baits-bundle-builder'),
			$count,
			self::unit_word($config, $count)
		);
	}

	/**
	 * @param array<string, mixed> $config Config.
	 * @return string
	 */
	public static function type_label(array $config) {
		if ('fixed' === ($config['bundle_type'] ?? '')) {
			return __('Fixed product bundle', 'mad-baits-bundle-builder');
		}
		return __('Mix & match', 'mad-baits-bundle-builder');
	}

	/**
	 * Stable signature for the customer-choice fields.
	 *
	 * @param array<string, mixed> $config Config.
	 * @return string
	 */
	public static function structure_signature(array $config) {
		$data = array(
			'bundle_type'   => (string) ($config['bundle_type'] ?? ''),
			'quantity_mode' => (string) ($config['quantity_mode'] ?? ''),
			'quantity'      => (int) ($config['quantity'] ?? 0),
			'min_quantity'  => (int) ($config['min_quantity'] ?? 0),
			'max_quantity'  => (int) ($config['max_quantity'] ?? 0),
			'multiple_of'   => (int) ($config['multiple_of'] ?? 0),
			'unit'          => (string) ($config['unit'] ?? ''),
			'unit_custom'   => (string) ($config['unit_custom'] ?? ''),
			'ranges'        => self::string_list($config['ranges'] ?? array()),
			'sizes'         => self::string_list($config['sizes'] ?? array()),
			'bait_format'   => sanitize_key((string) ($config['bait_format'] ?? '')),
			'categories'    => self::string_list($config['categories'] ?? array()),
			'product_ids'   => self::int_list($config['product_ids'] ?? array()),
			'variation_ids' => self::int_list($config['variation_ids'] ?? array()),
			'attributes'    => self::sanitize_attributes($config['attributes'] ?? array()),
			'fixed_items'   => self::sanitize_fixed_items($config['fixed_items'] ?? array()),
		);
		sort($data['ranges']);
		sort($data['sizes']);
		sort($data['categories']);

		return md5((string) wp_json_encode($data));
	}

	/**
	 * @param array<string, mixed> $config Original config.
	 * @param string               $name   Original product name.
	 * @return array<string, mixed>
	 */
	public static function duplicate_config(array $config, $name) {
		$copy           = self::sanitize($config);
		$copy['name']   = trim((string) $name) . ' Copy';
		$copy['status'] = 'draft';
		return $copy;
	}

	/**
	 * @param mixed $value Raw date.
	 * @return string
	 */
	public static function sanitize_date($value) {
		$value = trim(sanitize_text_field((string) $value));
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $value;
		}
		return '';
	}

	/**
	 * @param mixed $value Raw decimal.
	 * @return string
	 */
	public static function sanitize_decimal($value) {
		$value = trim((string) $value);
		if ('' === $value) {
			return '';
		}
		if (function_exists('wc_format_decimal')) {
			return (string) wc_format_decimal(wc_clean($value));
		}
		$normalised = str_replace(',', '', $value);
		if (! is_numeric($normalised)) {
			return '';
		}
		return number_format((float) $normalised, 2, '.', '');
	}

	/**
	 * @param mixed $value Checkbox-ish value.
	 * @return bool
	 */
	private static function is_checked($value) {
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value === 1;
		}
		$value = strtolower(trim((string) $value));
		return in_array($value, array('1', 'yes', 'true', 'on'), true);
	}

	/**
	 * @param mixed $values Raw list.
	 * @return string[]
	 */
	private static function string_list($values) {
		if (! is_array($values)) {
			return array();
		}
		$out = array();
		foreach ($values as $value) {
			$clean = sanitize_title((string) $value);
			if ('' !== $clean) {
				$out[] = $clean;
			}
		}
		$out = array_values(array_unique($out));
		sort($out);
		return $out;
	}

	/**
	 * @param mixed $values Raw list.
	 * @return int[]
	 */
	private static function int_list($values) {
		if (! is_array($values)) {
			return array();
		}
		$out = array();
		foreach ($values as $value) {
			$id = absint($value);
			if ($id > 0) {
				$out[] = $id;
			}
		}
		$out = array_values(array_unique($out));
		sort($out);
		return $out;
	}

	/**
	 * @param mixed $attributes taxonomy => terms.
	 * @return array<string, string[]>
	 */
	private static function sanitize_attributes($attributes) {
		if (! is_array($attributes)) {
			return array();
		}
		$out = array();
		foreach ($attributes as $taxonomy => $terms) {
			$taxonomy = sanitize_key((string) $taxonomy);
			if ('' === $taxonomy || ! is_array($terms)) {
				continue;
			}
			$clean_terms = self::string_list($terms);
			if (! empty($clean_terms)) {
				$out[ $taxonomy ] = $clean_terms;
			}
		}
		ksort($out);
		return $out;
	}

	/**
	 * @param mixed $items Posted fixed items.
	 * @return array<int, array{product_id: int, variation_id: int, quantity: int, label: string}>
	 */
	private static function sanitize_fixed_items($items) {
		if (! is_array($items)) {
			return array();
		}
		$out = array();
		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}
			$product_id   = absint($item['product_id'] ?? 0);
			$variation_id = absint($item['variation_id'] ?? 0);
			if ($product_id < 1 && $variation_id < 1) {
				continue;
			}
			$quantity = absint($item['quantity'] ?? 1);
			$out[]    = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'quantity'     => max(1, min(self::MAX_CHOICES, $quantity > 0 ? $quantity : 1)),
				'label'        => sanitize_text_field((string) ($item['label'] ?? '')),
			);
		}
		return $out;
	}
}
