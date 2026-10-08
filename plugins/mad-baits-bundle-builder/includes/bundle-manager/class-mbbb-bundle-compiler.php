<?php
/**
 * Turns owner bundle settings into the existing slot format.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Slot compiler.
 */
final class MBBB_Bundle_Compiler {

	/**
	 * @param array<string, mixed>             $config         Owner config.
	 * @param array<int, array<string, mixed>> $options        Slot options for a single pool.
	 * @param array<int, array<string, mixed>> $catalogue_rows Catalogue rows used by item groups.
	 * @return array<int, array<string, mixed>>
	 */
	public static function compile(array $config, array $options, array $catalogue_rows = array()) {
		if ('fixed' === ($config['bundle_type'] ?? '')) {
			return self::compile_fixed($config, $options);
		}
		$groups = isset($config['groups']) && is_array($config['groups']) ? $config['groups'] : array();
		if (! empty($groups)) {
			return self::compile_groups($config, $catalogue_rows);
		}
		return self::compile_mix($config, $options);
	}

	/**
	 * How many choice steps the customer will see.
	 *
	 * @param array<string, mixed> $config Owner config.
	 * @return int
	 */
	public static function slot_count(array $config) {
		if ('fixed' === ($config['bundle_type'] ?? '')) {
			$count = 0;
			foreach ((array) ($config['fixed_items'] ?? array()) as $item) {
				if (is_array($item)) {
					$count += max(1, (int) ($item['quantity'] ?? 1));
				}
			}
			return min(MBBB_Bundle_Config::MAX_CHOICES, $count);
		}

		$groups = isset($config['groups']) && is_array($config['groups']) ? $config['groups'] : array();
		if (! empty($groups)) {
			$count = 0;
			foreach ($groups as $group) {
				if (is_array($group)) {
					$count += max(0, (int) ($group['quantity'] ?? 0));
				}
			}
			return min(MBBB_Bundle_Config::MAX_CHOICES, $count);
		}

		if ('exact' === ($config['quantity_mode'] ?? 'exact')) {
			return min(MBBB_Bundle_Config::MAX_CHOICES, max(0, (int) ($config['quantity'] ?? 0)));
		}

		$max = (int) ($config['max_quantity'] ?? 0);
		$min = (int) ($config['min_quantity'] ?? 0);
		if ($max < 1) {
			$max = $min;
		}
		return min(MBBB_Bundle_Config::MAX_CHOICES, max(0, $max));
	}

	/**
	 * @param array<string, mixed> $config Owner config.
	 * @return int
	 */
	public static function required_count(array $config) {
		$groups = isset($config['groups']) && is_array($config['groups']) ? $config['groups'] : array();
		if ('fixed' === ($config['bundle_type'] ?? '') || 'exact' === ($config['quantity_mode'] ?? 'exact') || ! empty($groups)) {
			return self::slot_count($config);
		}
		return min(self::slot_count($config), max(0, (int) ($config['min_quantity'] ?? 0)));
	}

	/**
	 * @param array<string, mixed>             $config  Owner config.
	 * @param array<int, array<string, mixed>> $options Options shared by every step.
	 * @return array<int, array<string, mixed>>
	 */
	private static function compile_mix(array $config, array $options) {
		$total    = self::slot_count($config);
		$required = self::required_count($config);
		$slots    = array();
		$singular = MBBB_Bundle_Config::unit_word($config, 1);
		$help     = trim((string) ($config['display']['helper_text'] ?? ''));
		if ('' === $help) {
			$help = MBBB_Bundle_Config::choice_sentence($config);
		}

		for ($index = 1; $index <= $total; $index++) {
			$is_required = $index <= $required;
			$slots[]     = self::slot(
				sprintf(
					/* translators: 1: unit singular 2: step number */
					__('%1$s %2$d', 'mad-baits-bundle-builder'),
					ucfirst($singular),
					$index
				),
				'choice-' . $index,
				$is_required,
				$options,
				1 === $index ? $help : '',
				false
			);
		}

		return $slots;
	}

	/**
	 * One required slot per customer choice, with each item group keeping its own products.
	 *
	 * @param array<string, mixed>             $config         Owner config.
	 * @param array<int, array<string, mixed>> $catalogue_rows Catalogue rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function compile_groups(array $config, array $catalogue_rows) {
		$slots = array();
		$step  = 0;
		$used  = array();
		foreach ((array) ($config['groups'] ?? array()) as $group) {
			if (! is_array($group) || count($slots) >= MBBB_Bundle_Config::MAX_CHOICES) {
				continue;
			}
			$quantity = max(0, (int) ($group['quantity'] ?? 0));
			if ($quantity < 1) {
				continue;
			}
			++$step;
			$type    = (string) ($group['type'] ?? 'other');
			$base    = MBBB_Bundle_Config::content_type_key($type);
			$rows    = MBBB_Bundle_Eligibility::matching_group($catalogue_rows, $group, $config);
			$options = MBBB_Bundle_Eligibility::to_slot_options(MBBB_Bundle_Eligibility::purchasable_only($rows, $config));
			$sentence = MBBB_Bundle_Config::group_choice_sentence($group);
			$label    = MBBB_Bundle_Config::content_type_label($type, 1);
			for ($index = 1; $index <= $quantity && count($slots) < MBBB_Bundle_Config::MAX_CHOICES; $index++) {
				$number = $index;
				$key    = $base . '-' . $number;
				while (isset($used[ $key ])) {
					++$number;
					$key = $base . '-' . $number;
				}
				$used[ $key ] = true;
				$slots[]      = self::slot(
					$quantity > 1 ? $label . ' ' . $index : $label,
					$key,
					true,
					$options,
					'',
					false,
					array(
						'group_key'      => $base . '-group-' . $step,
						'group_label'    => MBBB_Bundle_Config::content_type_label($type, 2),
						'group_step'     => $step,
						'group_index'    => $index,
						'group_total'    => $quantity,
						'group_sentence' => $sentence,
					)
				);
			}
		}

		return $slots;
	}

	/**
	 * @param array<string, mixed>             $config  Owner config.
	 * @param array<int, array<string, mixed>> $options Eligible options.
	 * @return array<int, array<string, mixed>>
	 */
	private static function compile_fixed(array $config, array $options) {
		$slots = array();
		$step  = 1;
		foreach ((array) ($config['fixed_items'] ?? array()) as $item) {
			if (! is_array($item) || $step > MBBB_Bundle_Config::MAX_CHOICES) {
				continue;
			}
			$option = self::option_for_fixed_item($item, $options);
			$times  = max(1, (int) ($item['quantity'] ?? 1));
			for ($copy = 0; $copy < $times && $step <= MBBB_Bundle_Config::MAX_CHOICES; $copy++) {
				$label = '' !== (string) ($item['label'] ?? '') ? (string) $item['label'] : (string) ($option['label'] ?? __('Included product', 'mad-baits-bundle-builder'));
				if ($times > 1) {
					$label = sprintf(
						/* translators: 1: product name 2: copy number */
						__('%1$s %2$d', 'mad-baits-bundle-builder'),
						$label,
						$copy + 1
					);
				}
				$slots[] = self::slot($label, 'included-' . $step, true, array($option), 1 === $step ? trim((string) ($config['display']['helper_text'] ?? '')) : '', true);
				++$step;
			}
		}
		return $slots;
	}

	/**
	 * @param array<string, mixed>             $item    Fixed item.
	 * @param array<int, array<string, mixed>> $options Options.
	 * @return array<string, mixed>
	 */
	private static function option_for_fixed_item(array $item, array $options) {
		$variation_id = absint($item['variation_id'] ?? 0);
		$product_id   = absint($item['product_id'] ?? 0);
		foreach ($options as $option) {
			$option_id = absint($option['product_id'] ?? $option['value'] ?? 0);
			if ($variation_id > 0 && $option_id === $variation_id) {
				return $option;
			}
			if ($variation_id < 1 && $product_id > 0 && $option_id === $product_id) {
				return $option;
			}
		}

		$id = $variation_id > 0 ? $variation_id : $product_id;
		return array(
			'label'      => '' !== (string) ($item['label'] ?? '') ? (string) $item['label'] : __('Included product', 'mad-baits-bundle-builder'),
			'value'      => (string) $id,
			'product_id' => $id,
			'image'      => '',
			'active'     => true,
		);
	}

	/**
	 * @param string                           $label      Slot label.
	 * @param string                           $key        Slot key.
	 * @param bool                             $required   Required choice.
	 * @param array<int, array<string, mixed>> $options    Options.
	 * @param string                           $help       Help text.
	 * @param bool                             $auto_select Preselect the only option.
	 * @return array<string, mixed>
	 */
	private static function slot($label, $key, $required, array $options, $help, $auto_select, array $extra = array()) {
		$slot = array(
			'label'          => $label,
			'key'            => $key,
			'required'       => $required,
			'min'            => $required ? 1 : 0,
			'max'            => 1,
			'help'           => $help,
			'source'         => 'manual',
			'pool_id'        => '',
			'attribute'      => '',
			'category_id'    => 0,
			'tag_id'         => 0,
			'product_ids'    => array(),
			'display'        => 'cards',
			'manual_options' => array_values($options),
			'allow_same'     => true,
			'use_images'     => true,
			'auto_select'    => $auto_select,
		);
		foreach ($extra as $extra_key => $value) {
			$slot[ $extra_key ] = $value;
		}

		return $slot;
	}
}
