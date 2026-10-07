<?php
/**
 * Owner bundle workflows: save, duplicate, enable, and restore.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Bundle service.
 */
final class MBBB_Bundle_Service {

	/**
	 * Decide what a save is allowed to change.
	 *
	 * @param array<string, mixed>|null $existing       Stored owner config, or null for a new bundle.
	 * @param array<string, mixed>      $posted         Sanitised form.
	 * @param bool                      $update_choices Owner asked to replace customer choices.
	 * @param string                    $intent         draft|activate|save|disable.
	 * @param array<string, mixed>      $context        Eligibility counts and priced-slot flag.
	 * @return array{compile: bool, config: array<string, mixed>, errors: string[]}
	 */
	public static function plan_save($existing, array $posted, $update_choices, $intent, array $context = array()) {
		$intent = sanitize_key($intent);
		if (! in_array($intent, array('draft', 'activate', 'save', 'disable'), true)) {
			$intent = 'save';
		}

		$is_new   = ! is_array($existing);
		$existing = is_array($existing) ? MBBB_Bundle_Config::sanitize($existing) : null;
		$config   = MBBB_Bundle_Config::sanitize($posted);

		if ('draft' === $intent) {
			$config['status'] = 'draft';
		} elseif ('activate' === $intent) {
			$config['status'] = 'active';
		} elseif ('disable' === $intent) {
			$config['status'] = 'disabled';
		}

		$legacy_locked = ! $is_new
			&& ! empty($existing['preserve_slots'])
			&& MBBB_Bundle_Config::MANAGED_BY !== ($existing['managed_by'] ?? '');

		$structure_changed = ! $is_new && MBBB_Bundle_Config::structure_signature($existing) !== MBBB_Bundle_Config::structure_signature($config);
		$compile           = $is_new || $update_choices || (! $legacy_locked && MBBB_Bundle_Config::MANAGED_BY === ($existing['managed_by'] ?? ''));
		$errors            = array();

		if ($legacy_locked && $structure_changed && ! $update_choices) {
			$errors[] = __('Tick “Update what customers can choose” before changing the bait ranges, sizes, or quantity. The current bundle stays as it is until you do that.', 'mad-baits-bundle-builder');
			$compile  = false;
		}

		if (! $compile && $existing) {
			$config = self::merge_cosmetic($existing, $config);
		}

		if ($compile) {
			$config['preserve_slots'] = false;
			$config['managed_by']     = MBBB_Bundle_Config::MANAGED_BY;
			$config['legacy']         = false;
		}

		$errors = array_merge($errors, MBBB_Bundle_Validator::basic_errors($config));
		$activating = empty($errors) && ('activate' === $intent || ('save' === $intent && 'active' === $config['status']));
		if ($activating) {
			$priced_choices = ($compile && ! empty($context['will_compile_priced_options'])) || ! empty($context['legacy_slots_priced']);
			if (in_array((string) ($config['pricing']['mode'] ?? ''), array('percent', 'amount'), true) && ! $priced_choices) {
				$errors[] = __('Use a fixed bundle price for this bundle, or choose products that have a price, before using a discount.', 'mad-baits-bundle-builder');
			}
			$errors = array_merge($errors, MBBB_Bundle_Validator::activation_errors($config, $context));
		}

		return array(
			'compile' => $compile && empty($errors),
			'config'  => $config,
			'errors'  => array_values(array_unique($errors)),
		);
	}

	/**
	 * @param array<string, mixed> $posted Posted form, including intent.
	 * @return array{success: bool, product_id: int, errors: string[]}
	 */
	public static function save_from_post(array $posted) {
		$product_id = absint($posted['product_id'] ?? 0);
		$existing   = $product_id > 0 ? MBBB_Bundle_Repository::get_owner_config($product_id) : null;
		if ($product_id > 0 && null === $existing) {
			$existing = MBBB_Bundle_Repository::project_legacy($product_id);
		}

		$intent  = sanitize_key((string) ($posted['intent'] ?? 'save'));
		$config  = MBBB_Bundle_Config::sanitize(self::flatten_post($posted));
		$catalog = MBBB_Bundle_Repository::catalogue();
		$matched = MBBB_Bundle_Eligibility::matching($catalog['variations'], $config);
		$live    = MBBB_Bundle_Eligibility::purchasable($catalog['variations'], $config);
		$slots   = ($product_id > 0 && class_exists('MBBB_Plugin')) ? MBBB_Plugin::instance()->get_slots($product_id) : array();
		$plan    = self::plan_save(
			$existing,
			$config,
			! empty($posted['update_choices']),
			$intent,
			array(
				'eligible_total'              => count($matched),
				'eligible_purchasable'        => count($live),
				'legacy_slots_priced'         => MBBB_Bundle_Pricing::slots_have_priced_choices($slots),
				'will_compile_priced_options' => self::options_are_priced($live),
			)
		);

		if (! empty($plan['errors'])) {
			return array(
				'success'    => false,
				'product_id' => $product_id,
				'errors'     => $plan['errors'],
			);
		}

		return MBBB_Bundle_Repository::persist($product_id, $plan['config'], $plan['compile'], $live);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array{success: bool, product_id: int, errors: string[]}
	 */
	public static function duplicate($product_id) {
		return MBBB_Bundle_Repository::duplicate(absint($product_id));
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $status     active|disabled|draft.
	 * @return array{success: bool, product_id: int, errors: string[]}
	 */
	public static function change_status($product_id, $status) {
		$product_id = absint($product_id);
		$status     = sanitize_key($status);
		$existing   = MBBB_Bundle_Repository::get_owner_config($product_id);
		if (null === $existing) {
			$existing = MBBB_Bundle_Repository::project_legacy($product_id);
		}
		if (null === $existing) {
			return array(
				'success'    => false,
				'product_id' => $product_id,
				'errors'     => array(__('That bundle could not be found.', 'mad-baits-bundle-builder')),
			);
		}

		$intent  = 'active' === $status ? 'activate' : ('disabled' === $status ? 'disable' : 'draft');
		$catalog = MBBB_Bundle_Repository::catalogue();
		$live    = MBBB_Bundle_Eligibility::purchasable($catalog['variations'], $existing);
		$slots   = ($product_id > 0 && class_exists('MBBB_Plugin')) ? MBBB_Plugin::instance()->get_slots($product_id) : array();
		$plan    = self::plan_save($existing, $existing, false, $intent, array(
			'eligible_total'              => count(MBBB_Bundle_Eligibility::matching($catalog['variations'], $existing)),
			'eligible_purchasable'        => count($live),
			'legacy_slots_priced'         => MBBB_Bundle_Pricing::slots_have_priced_choices($slots),
			'will_compile_priced_options' => self::options_are_priced($live),
		));

		if (! empty($plan['errors'])) {
			return array(
				'success'    => false,
				'product_id' => $product_id,
				'errors'     => $plan['errors'],
			);
		}

		return MBBB_Bundle_Repository::persist($product_id, $plan['config'], false, $live);
	}

	/**
	 * @param array<string, mixed> $existing Stored config.
	 * @param array<string, mixed> $posted   Posted config.
	 * @return array<string, mixed>
	 */
	private static function merge_cosmetic(array $existing, array $posted) {
		$merged                        = $existing;
		$merged['name']                = $posted['name'];
		$merged['short_description']   = $posted['short_description'];
		$merged['image_id']            = $posted['image_id'];
		$merged['status']              = $posted['status'];
		$merged['start_date']          = $posted['start_date'];
		$merged['end_date']            = $posted['end_date'];
		$merged['pricing']             = $posted['pricing'];
		$merged['stock']               = $posted['stock'];
		$merged['display']             = $posted['display'];
		return $merged;
	}

	/**
	 * Accept either nested arrays or flat field names from the admin form.
	 *
	 * @param array<string, mixed> $posted Posted form.
	 * @return array<string, mixed>
	 */
	public static function flatten_post(array $posted) {
		$bundle = isset($posted['mb_bundle']) && is_array($posted['mb_bundle']) ? $posted['mb_bundle'] : $posted;
		if (isset($bundle['pricing_mode']) && ! isset($bundle['pricing'])) {
			$bundle['pricing'] = array(
				'mode'        => $bundle['pricing_mode'],
				'fixed_price' => $bundle['fixed_price'] ?? '',
				'percent'     => $bundle['percent'] ?? 0,
				'amount'      => $bundle['amount'] ?? '',
			);
		}
		return $bundle;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Catalogue rows.
	 * @return bool
	 */
	private static function options_are_priced(array $rows) {
		if (empty($rows)) {
			return false;
		}
		foreach ($rows as $row) {
			if (absint($row['id'] ?? 0) < 1) {
				return false;
			}
		}
		return true;
	}
}
