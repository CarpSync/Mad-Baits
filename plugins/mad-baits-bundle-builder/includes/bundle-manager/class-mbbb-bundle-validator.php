<?php
/**
 * Plain-English bundle validation.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Validator.
 */
final class MBBB_Bundle_Validator {

	/**
	 * Problems that block every save.
	 *
	 * @param array<string, mixed> $config Owner config.
	 * @return string[]
	 */
	public static function basic_errors(array $config) {
		$errors = array();
		if ('' === trim((string) ($config['name'] ?? ''))) {
			$errors[] = __('Give this bundle a name before saving.', 'mad-baits-bundle-builder');
		}

		$start = (string) ($config['start_date'] ?? '');
		$end   = (string) ($config['end_date'] ?? '');
		if ('' !== $start && '' !== $end && $start > $end) {
			$errors[] = __('The start date needs to be before the end date.', 'mad-baits-bundle-builder');
		}

		if ('minimum' === ($config['quantity_mode'] ?? '') && (int) ($config['max_quantity'] ?? 0) > 0 && (int) $config['max_quantity'] < (int) ($config['min_quantity'] ?? 0)) {
			$errors[] = __('The maximum quantity needs to be at least the minimum.', 'mad-baits-bundle-builder');
		}

		$choices = MBBB_Bundle_Compiler::slot_count($config);
		$raw     = self::group_quantity_sum($config);
		if ($choices > MBBB_Bundle_Config::MAX_CHOICES || $raw > MBBB_Bundle_Config::MAX_CHOICES) {
			$errors[] = sprintf(
				/* translators: %d: maximum choices */
				__('Keep the quantity at %d or fewer so the bundle stays easy to build.', 'mad-baits-bundle-builder'),
				MBBB_Bundle_Config::MAX_CHOICES
			);
		}

		return $errors;
	}

	/**
	 * Problems that block making a bundle live.
	 *
	 * @param array<string, mixed> $config  Owner config.
	 * @param array<string, mixed> $context eligible_total, eligible_purchasable.
	 * @return string[]
	 */
	public static function activation_errors(array $config, array $context = array()) {
		$errors = self::basic_errors($config);
		if (! empty($config['preserve_slots'])) {
			$errors = array_merge($errors, self::price_errors($config, false));
			return $errors;
		}

		if ('fixed' === ($config['bundle_type'] ?? '')) {
			if (empty($config['fixed_items'])) {
				$errors[] = __('Add at least one product to this bundle before activating it.', 'mad-baits-bundle-builder');
			}
		} elseif (! empty($config['groups'])) {
			$group_errors = isset($context['group_errors']) && is_array($context['group_errors']) ? $context['group_errors'] : array();
			if (! empty($group_errors)) {
				$errors = array_merge($errors, $group_errors);
			} elseif (! self::has_selection($config)) {
				$errors[] = __('Add at least one item group before activating this bundle.', 'mad-baits-bundle-builder');
			} elseif ((int) ($context['eligible_purchasable'] ?? 0) < 1) {
				$errors[] = __('None of the selected products can be bought right now. Check stock before activating this bundle.', 'mad-baits-bundle-builder');
			}
		} else {
			$quantity = 'minimum' === ($config['quantity_mode'] ?? '')
				? (int) ($config['min_quantity'] ?? 0)
				: (int) ($config['quantity'] ?? 0);
			if ($quantity < 1) {
				$errors[] = __('Enter how many items the customer chooses. Use a whole number greater than zero.', 'mad-baits-bundle-builder');
			}
			if ('exact' === ($config['quantity_mode'] ?? '') && (int) ($config['multiple_of'] ?? 0) > 1 && $quantity > 0 && 0 !== ($quantity % (int) $config['multiple_of'])) {
				$errors[] = __('The exact quantity needs to be a multiple of the amount you entered.', 'mad-baits-bundle-builder');
			}
			if (! self::has_selection($config)) {
				$errors[] = __('Choose at least one bait range before activating this bundle.', 'mad-baits-bundle-builder');
			}
		}

		$errors = array_merge($errors, self::price_errors($config, true));

		$live = (int) ($context['eligible_purchasable'] ?? 0);

		if (empty($config['groups']) && (self::has_selection($config) || 'fixed' === ($config['bundle_type'] ?? '')) && $live < 1) {
			$errors[] = __('None of the selected products can be bought right now. Check stock before activating this bundle.', 'mad-baits-bundle-builder');
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @param array<string, mixed> $config         Owner config.
	 * @param int                  $selected_count Choices the customer made.
	 * @return string[]
	 */
	public static function selection_errors(array $config, $selected_count) {
		if (! empty($config['preserve_slots'])) {
			return array();
		}

		$selected_count = (int) $selected_count;
		$errors         = array();
		$unit           = MBBB_Bundle_Config::unit_word($config, max(2, $selected_count));

		$grouped = ! empty($config['groups']) && 'fixed' !== ($config['bundle_type'] ?? '');
		if ('fixed' === ($config['bundle_type'] ?? '') || 'exact' === ($config['quantity_mode'] ?? 'exact') || $grouped) {
			$need = MBBB_Bundle_Compiler::slot_count($config);
			if ($selected_count !== $need) {
				if ($grouped && count((array) $config['groups']) > 1) {
					$errors[] = sprintf(
						/* translators: %d: number of choices */
						__('Choose all %d items in this bundle.', 'mad-baits-bundle-builder'),
						$need
					);
				} else {
					$errors[] = sprintf(
						/* translators: 1: quantity 2: unit */
						__('Choose exactly %1$d %2$s.', 'mad-baits-bundle-builder'),
						$need,
						MBBB_Bundle_Config::unit_word($config, $need)
					);
				}
			}
		} else {
			$min = (int) ($config['min_quantity'] ?? 0);
			$max = (int) ($config['max_quantity'] ?? 0);
			if ($selected_count < $min) {
				$errors[] = sprintf(
					/* translators: 1: minimum 2: unit */
					__('Choose at least %1$d %2$s.', 'mad-baits-bundle-builder'),
					$min,
					$unit
				);
			}
			if ($max > 0 && $selected_count > $max) {
				$errors[] = sprintf(
					/* translators: 1: maximum 2: unit */
					__('Choose no more than %1$d %2$s.', 'mad-baits-bundle-builder'),
					$max,
					$unit
				);
			}
		}

		$multiple = (int) ($config['multiple_of'] ?? 0);
		if ($multiple > 1 && $selected_count > 0 && 0 !== ($selected_count % $multiple)) {
			$errors[] = sprintf(
				/* translators: %d: multiple */
				__('Choose a quantity in multiples of %d.', 'mad-baits-bundle-builder'),
				$multiple
			);
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $config          Owner config.
	 * @param bool                 $require_dynamic Whether discount values are required.
	 * @return string[]
	 */
	private static function price_errors(array $config, $require_dynamic) {
		$pricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : array();
		$mode    = (string) ($pricing['mode'] ?? 'fixed');
		$errors  = array();

		if ('fixed' === $mode) {
			$price = (string) ($pricing['fixed_price'] ?? '');
			if ('' === $price || (float) $price <= 0) {
				$errors[] = __('Enter the bundle price before activating this bundle.', 'mad-baits-bundle-builder');
			}
		} elseif ($require_dynamic && 'percent' === $mode) {
			$percent = (int) ($pricing['percent'] ?? 0);
			if ($percent < 1 || $percent > 100) {
				$errors[] = __('Enter a discount between 1% and 100% before activating this bundle.', 'mad-baits-bundle-builder');
			}
		} elseif ($require_dynamic && 'amount' === $mode) {
			$amount = (string) ($pricing['amount'] ?? '');
			if ('' === $amount || (float) $amount <= 0) {
				$errors[] = __('Enter how much money to take off before activating this bundle.', 'mad-baits-bundle-builder');
			}
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $config Owner config.
	 * @return int
	 */
	private static function group_quantity_sum(array $config) {
		if ('fixed' === ($config['bundle_type'] ?? '') || empty($config['groups']) || ! is_array($config['groups'])) {
			return 0;
		}
		$sum = 0;
		foreach ($config['groups'] as $group) {
			if (is_array($group)) {
				$sum += max(0, (int) ($group['quantity'] ?? 0));
			}
		}
		return $sum;
	}

	/**
	 * @param array<string, mixed> $config Owner config.
	 * @return bool
	 */
	private static function has_selection(array $config) {
		if ('fixed' === ($config['bundle_type'] ?? '')) {
			return ! empty($config['fixed_items']);
		}
		if (! empty($config['groups']) && is_array($config['groups'])) {
			foreach ($config['groups'] as $group) {
				if (! is_array($group) || (int) ($group['quantity'] ?? 0) < 1) {
					continue;
				}
				if ('other' === ($group['type'] ?? '') && empty($group['product_ids'])) {
					continue;
				}
				return true;
			}
			return false;
		}
		return ! empty($config['ranges'])
			|| ! empty($config['sizes'])
			|| ! empty($config['categories'])
			|| ! empty($config['product_ids'])
			|| ! empty($config['variation_ids'])
			|| ! empty($config['attributes']);
	}
}
