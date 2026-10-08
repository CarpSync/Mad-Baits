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
			'groups'            => array(),
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
		$config['groups']         = 'fixed' === $config['bundle_type'] ? array() : self::sanitize_groups($input['groups'] ?? array());
		if (! empty($config['groups'])) {
			$config = self::mirror_groups($config);
		}

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

		$group_sentence = self::groups_sentence((array) ($config['groups'] ?? array()));
		if ('' !== $group_sentence) {
			return $group_sentence;
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
	 * Owner-facing product types, in the order they are offered.
	 *
	 * @return string[]
	 */
	public static function product_types() {
		return array('boilies', 'popups', 'wafters', 'hookbaits', 'pellets', 'liquids', 'other');
	}

	/**
	 * @param string $type  Product type key.
	 * @param int    $count 1 for singular.
	 * @return string
	 */
	public static function content_type_label($type, $count = 2) {
		$map  = array(
			'boilies'   => array(__('Boilie', 'mad-baits-bundle-builder'), __('Boilies', 'mad-baits-bundle-builder')),
			'popups'    => array(__('Pop-up', 'mad-baits-bundle-builder'), __('Pop-ups', 'mad-baits-bundle-builder')),
			'wafters'   => array(__('Wafter', 'mad-baits-bundle-builder'), __('Wafters', 'mad-baits-bundle-builder')),
			'hookbaits' => array(__('Hookbait', 'mad-baits-bundle-builder'), __('Hookbaits', 'mad-baits-bundle-builder')),
			'pellets'   => array(__('Pellet', 'mad-baits-bundle-builder'), __('Pellets', 'mad-baits-bundle-builder')),
			'liquids'   => array(__('Liquid', 'mad-baits-bundle-builder'), __('Liquids', 'mad-baits-bundle-builder')),
			'other'     => array(__('Item', 'mad-baits-bundle-builder'), __('Items', 'mad-baits-bundle-builder')),
		);
		$pair = $map[ $type ] ?? $map['other'];

		return 1 === (int) $count ? $pair[0] : $pair[1];
	}

	/**
	 * Lower-case plural used in customer steps and validation.
	 *
	 * @param string $type Product type key.
	 * @return string
	 */
	public static function content_type_plural($type) {
		$map = array(
			'boilies'   => __('boilies', 'mad-baits-bundle-builder'),
			'popups'    => __('pop-ups', 'mad-baits-bundle-builder'),
			'wafters'   => __('wafters', 'mad-baits-bundle-builder'),
			'hookbaits' => __('hookbaits', 'mad-baits-bundle-builder'),
			'pellets'   => __('pellets', 'mad-baits-bundle-builder'),
			'liquids'   => __('liquids', 'mad-baits-bundle-builder'),
			'other'     => __('products', 'mad-baits-bundle-builder'),
		);

		return $map[ $type ] ?? $map['other'];
	}

	/**
	 * @param string $type Product type key.
	 * @return string
	 */
	public static function content_type_singular($type) {
		$map = array(
			'boilies'   => __('boilie', 'mad-baits-bundle-builder'),
			'popups'    => __('pop-up', 'mad-baits-bundle-builder'),
			'wafters'   => __('wafter', 'mad-baits-bundle-builder'),
			'hookbaits' => __('hookbait', 'mad-baits-bundle-builder'),
			'pellets'   => __('pellet', 'mad-baits-bundle-builder'),
			'liquids'   => __('liquid', 'mad-baits-bundle-builder'),
			'other'     => __('item', 'mad-baits-bundle-builder'),
		);

		return $map[ $type ] ?? $map['other'];
	}

	/**
	 * @param string $type Product type key.
	 * @return string
	 */
	public static function default_unit_for_type($type) {
		$map = array(
			'boilies'   => 'bags',
			'popups'    => 'tubs',
			'wafters'   => 'tubs',
			'hookbaits' => 'tubs',
			'pellets'   => 'bags',
			'liquids'   => 'bottles',
			'other'     => 'items',
		);

		return $map[ $type ] ?? 'items';
	}

	/**
	 * @param string $type Product type key.
	 * @return string
	 */
	public static function content_type_key($type) {
		$map = array(
			'boilies'   => 'boilie',
			'popups'    => 'popup',
			'wafters'   => 'wafter',
			'hookbaits' => 'hookbait',
			'pellets'   => 'pellet',
			'liquids'   => 'liquid',
			'other'     => 'item',
		);

		return $map[ $type ] ?? 'item';
	}

	/**
	 * Customer step heading for one item group.
	 *
	 * @param array<string, mixed> $group Item group.
	 * @return string
	 */
	public static function group_choice_sentence(array $group) {
		$qty  = max(0, (int) ($group['quantity'] ?? 0));
		$type = (string) ($group['type'] ?? 'other');
		if (1 === $qty) {
			return sprintf(
				/* translators: %s: product type singular, such as pop-up */
				__('Choose your %s', 'mad-baits-bundle-builder'),
				self::content_type_singular($type)
			);
		}

		return sprintf(
			/* translators: 1: quantity 2: product type plural */
			__('Choose your %1$d %2$s', 'mad-baits-bundle-builder'),
			$qty,
			self::content_type_plural($type)
		);
	}

	/**
	 * Groups shown in the simple editor.
	 *
	 * Legacy slot bundles are not converted. Existing manager bundles without
	 * groups become one boilie group so a name-only save keeps their filters.
	 *
	 * @param array<string, mixed> $config Sanitised config.
	 * @return array<int, array<string, mixed>>
	 */
	public static function editor_groups(array $config) {
		$groups = isset($config['groups']) && is_array($config['groups']) ? $config['groups'] : array();
		if (! empty($groups)) {
			return array_values($groups);
		}
		if (! empty($config['legacy']) || ! empty($config['preserve_slots']) || 'fixed' === ($config['bundle_type'] ?? '')) {
			return array();
		}

		$unit = (string) ($config['unit'] ?? 'bags');
		if (! in_array($unit, array('bags', 'tubs', 'bottles', 'items', 'custom'), true)) {
			$unit = 'bags';
		}

		return array(
			array(
				'type'        => 'boilies',
				'quantity'    => max(1, (int) ($config['quantity'] ?? 10)),
				'unit'        => $unit,
				'unit_custom' => (string) ($config['unit_custom'] ?? ''),
				'ranges'      => array_values((array) ($config['ranges'] ?? array())),
				'sizes'       => array_values((array) ($config['sizes'] ?? array())),
				'bait_format' => (string) ($config['bait_format'] ?? ''),
				'product_ids' => array(),
			),
		);
	}

	/**
	 * Realistic deals guided by the legacy 5kg, 10kg, and 20kg slot lists.
	 *
	 * Legacy deals use shared hookbait pools and 5kg splits. These presets use
	 * 1kg bag quantities and separate pop-up and wafter groups instead.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function content_presets() {
		return array(
			'10kg'  => array(
				'groups' => array(
					self::preset_group('boilies', 10, 'bags', array('15mm', '18mm')),
					self::preset_group('popups', 1, 'tubs'),
					self::preset_group('wafters', 1, 'tubs'),
				),
			),
			'20kg'  => array(
				'groups' => array(
					self::preset_group('boilies', 20, 'bags', array('15mm', '18mm')),
					self::preset_group('popups', 1, 'tubs'),
					self::preset_group('wafters', 1, 'tubs'),
					self::preset_group('liquids', 1, 'bottles'),
				),
			),
			'5kg'   => array(
				'groups' => array(
					self::preset_group('boilies', 5, 'bags', array('15mm', '18mm')),
					self::preset_group('popups', 1, 'tubs'),
					self::preset_group('wafters', 1, 'tubs'),
					self::preset_group('liquids', 1, 'bottles'),
				),
			),
			'blank' => array(
				'clear'  => true,
				'groups' => array(
					self::preset_group('boilies', 10, 'bags'),
				),
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $groups Item groups.
	 * @return string
	 */
	private static function groups_sentence(array $groups) {
		$parts = array();
		foreach ($groups as $group) {
			if (! is_array($group)) {
				continue;
			}
			$count = (int) ($group['quantity'] ?? 0);
			if ($count < 1) {
				continue;
			}
			$parts[] = sprintf(
				/* translators: 1: quantity 2: unit 3: product type plural */
				__('%1$d %2$s of %3$s', 'mad-baits-bundle-builder'),
				$count,
				self::unit_word($group, $count),
				self::content_type_plural((string) ($group['type'] ?? 'other'))
			);
		}

		return implode(', ', $parts);
	}

	/**
	 * Copy the first boilie group onto the original single-pool fields.
	 *
	 * @param array<string, mixed> $config Config with groups.
	 * @return array<string, mixed>
	 */
	private static function mirror_groups(array $config) {
		$source = null;
		$sum    = 0;
		foreach ($config['groups'] as $group) {
			if (! is_array($group)) {
				continue;
			}
			$sum += (int) ($group['quantity'] ?? 0);
			if (null === $source && 'boilies' === ($group['type'] ?? '')) {
				$source = $group;
			}
		}
		if (null === $source) {
			$source = $config['groups'][0];
		}
		$config['quantity']     = $sum;
		$config['min_quantity'] = $sum;
		$config['max_quantity'] = $sum;
		$config['ranges']       = (array) ($source['ranges'] ?? array());
		$config['sizes']        = (array) ($source['sizes'] ?? array());
		$config['bait_format']  = 'boilies' === ($source['type'] ?? '') ? (string) ($source['bait_format'] ?? '') : '';
		$config['unit']         = (string) ($source['unit'] ?? 'items');
		$config['unit_custom']  = (string) ($source['unit_custom'] ?? '');

		return $config;
	}

	/**
	 * @param string   $type  Product type.
	 * @param int      $qty   Customer quantity.
	 * @param string   $unit  Unit key.
	 * @param string[] $sizes Optional size slugs.
	 * @return array<string, mixed>
	 */
	private static function preset_group($type, $qty, $unit, array $sizes = array()) {
		return array(
			'type'        => $type,
			'quantity'    => $qty,
			'unit'        => $unit,
			'unit_custom' => '',
			'ranges'      => array(),
			'sizes'       => $sizes,
			'bait_format' => '',
			'product_ids' => array(),
		);
	}

	/**
	 * @param mixed $groups Posted groups.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_groups($groups) {
		if (! is_array($groups)) {
			return array();
		}
		$out = array();
		foreach ($groups as $group) {
			if (! is_array($group)) {
				continue;
			}
			$type = sanitize_key((string) ($group['type'] ?? ''));
			if (! in_array($type, self::product_types(), true)) {
				continue;
			}
			$unit = sanitize_key((string) ($group['unit'] ?? ''));
			if (! in_array($unit, array('bags', 'tubs', 'bottles', 'items', 'custom'), true)) {
				$unit = self::default_unit_for_type($type);
			}
			$format = sanitize_key((string) ($group['bait_format'] ?? ''));
			if ('boilies' !== $type || ! in_array($format, array('shelf_life', 'freezer', 'both'), true)) {
				$format = '';
			}
			$out[] = array(
				'type'        => $type,
				'quantity'    => min(self::MAX_CHOICES, absint($group['quantity'] ?? 0)),
				'unit'        => $unit,
				'unit_custom' => sanitize_text_field((string) ($group['unit_custom'] ?? '')),
				'ranges'      => self::string_list($group['ranges'] ?? array()),
				'sizes'       => self::string_list($group['sizes'] ?? array()),
				'bait_format' => $format,
				'product_ids' => self::int_list($group['product_ids'] ?? array()),
			);
		}

		return $out;
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
			'groups'        => self::sanitize_groups($config['groups'] ?? array()),
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
