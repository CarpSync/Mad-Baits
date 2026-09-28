<?php
/**
 * Bundle presets and default global pools.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Presets and pools registry.
 */
final class MBBB_Presets {

	/**
	 * Install default global pools.
	 *
	 * @return void
	 */
	public static function install_default_pools() {
		$existing = get_option(MBBB_Plugin::OPTION_POOLS, array());
		if (is_array($existing) && ! empty($existing)) {
			return;
		}
		update_option(MBBB_Plugin::OPTION_POOLS, self::get_default_pools(), false);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_default_pools() {
		$pools = array();
		foreach (self::pool_definitions() as $id => $def) {
			$pools[ $id ] = array(
				'id'      => $id,
				'name'    => $def['name'],
				'active'  => true,
				'options' => self::labels_to_options($def['options']),
			);
		}
		return $pools;
	}

	/**
	 * @param string[] $labels Labels.
	 * @return array<int, array<string, mixed>>
	 */
	private static function labels_to_options($labels) {
		$out = array();
		foreach ($labels as $label) {
			$out[] = array(
				'label'      => $label,
				'value'      => sanitize_title($label),
				'product_id' => 0,
				'image'      => '',
				'active'     => true,
			);
		}
		return $out;
	}

	/**
	 * @return array<string, array{name: string, options: string[]}>
	 */
	public static function pool_definitions() {
		return array(
			'standard-boilie-choices' => array(
				'name'    => 'Standard Boilie Choices',
				'options' => array(
					'ASBO 15mm', 'ASBO 18mm', 'ASBO 22mm', 'ASBO 12mm Shelf Life', 'ASBO 15mm Shelf Life', 'ASBO 18mm Shelf Life', 'ASBO 22mm Shelf Life',
					'P-Fish 12mm Freezer', 'P-Fish 15mm Freezer', 'P-Fish 18mm Freezer', 'P-Fish 22mm Freezer',
					'P-Fish 12mm Shelf Life', 'P-Fish 15mm Shelf Life', 'P-Fish 18mm Shelf Life',
					'Pandemic 12mm Freezer', 'Pandemic 15mm Freezer', 'Pandemic 18mm Freezer', 'Pandemic 22mm Freezer',
					'Pandemic 12mm Shelf Life', 'Pandemic 15mm Shelf Life', 'Pandemic 18mm Shelf Life',
					'Nutz Plus 12mm Freezer', 'Nutz Plus 15mm Freezer', 'Nutz Plus 18mm Freezer', 'Nutz Plus 22mm Freezer',
					'Nutz Plus 12mm Shelf Life', 'Nutz Plus 15mm Shelf Life', 'Nutz Plus 18mm Shelf Life',
					'Wicked White 12mm', 'Wicked White 15mm', 'Wicked White 18mm',
					'Nutz Banana 12mm', 'Nutz Banana 15mm', 'Nutz Banana 18mm',
				),
			),
			'standard-hookbait-choices' => array(
				'name'    => 'Standard Hookbait Choices',
				'options' => array(
					'Banana Pop Ups', 'Banana Mixed Wafters', 'Banana Spray', '15mm Banana Skinz', '18mm Banana Skinz',
					'P-Fish Pop Ups', 'P-Fish Pastel Pops', 'P-Fish Mixed Wafters', 'P-Fish 18mm Wafters', 'P-Fish 15mm Skinz', 'P-Fish 18mm Skinz', 'P-Fish 22mm Skinz',
					'Nutz Pop Ups', 'Nutz Pastel Pops', 'Nutz Mixed Wafters', 'Nutz 18mm Wafter', 'Nutz 15mm Skinz', 'Nutz 18mm Skinz', 'Nutz 22mm Skinz',
					'ASBO Pop Ups', 'ASBO Pastel Pops', 'ASBO Mixed Wafters', 'ASBO 18mm Wafter', 'ASBO 15mm Skinz', 'ASBO 18mm Skinz', 'ASBO 22mm Skinz',
					'Pandemic Pop Ups', 'Pandemic Pink Pop Ups', 'Pandemic Pastel Pops', 'Pandemic Mixed Wafters', 'Pandemic 18mm Wafter', 'Pandemic 15mm Skinz', 'Pandemic 18mm Skinz', 'Pandemic 22mm Skinz',
					'Wicked White Pop Ups', 'Wicked White Mixed Wafters', 'Wicked White 18mm Wafter',
				),
			),
			'standard-pellet-choices' => array(
				'name'    => 'Standard Pellet Choices',
				'options' => array('ASBO', 'Nutz Plus', 'P-Fish', 'Pandemic', 'Nutz Banana', 'Calamino', 'BBB'),
			),
			'standard-250ml-dip-choices' => array(
				'name'    => 'Standard 250ml Dip Choices',
				'options' => array('ASBO', 'Nutz Plus', 'Nutz Banana', 'Pandemic', 'P-Fish', 'Wicked White'),
			),
			'standard-500ml-liquid-choices' => array(
				'name'    => 'Standard 500ml Liquid Choices',
				'options' => array(
					'ASBO Food Dip', 'Nutz Food Dip', 'Pandemic Food Dip', 'Wicked White Food Dip', 'P-Fish Food Dip',
					'Salmon Oil', 'Hemp Oil', 'Hot Chilli Extract', 'Nut Supreme', 'Chilli Liver', 'Liver Liquid', 'Fish Hydro',
				),
			),
			'nutz-banana-boilie-sizes' => array(
				'name'    => 'Nutz Banana Boilie Sizes',
				'options' => array('12mm', '15mm', '18mm'),
			),
			'nutz-banana-hookbait-choices' => array(
				'name'    => 'Nutz Banana Hookbait Choices',
				'options' => array('Nutz Banana Pop Ups', 'Nutz Banana Mixed Wafters', 'Nutz Banana Skinz 15mm', 'Nutz Banana Skinz 18mm', 'Nutz Banana Spray'),
			),
			'pandemic-barbel-boilie-size' => array(
				'name'    => 'Pandemic Barbel Boilie Size',
				'options' => array('12mm', '15mm', '18mm', '22mm', '15x18mm Barrels'),
			),
			'pandemic-barbel-skinz-hookbaits' => array(
				'name'    => 'Pandemic Barbel Skinz Hookbaits',
				'options' => array('15mm', '18mm', '22mm'),
			),
		);
	}

	/**
	 * @return array<string, array{label: string, slots: array<int, array<string, mixed>>}>
	 */
	public static function get_presets() {
		$defs = array(
			'weekend-session-pack' => array(
				'label' => 'Weekend Session Pack',
				'slots' => array(
					self::slot('Boilie 1', 'standard-boilie-choices'),
					self::slot('Boilie 2', 'standard-boilie-choices'),
					self::slot('Hookbait 1', 'standard-hookbait-choices'),
					self::slot('Hookbait 2', 'standard-hookbait-choices'),
					self::slot('Pellet', 'standard-pellet-choices'),
					self::slot('Food Dip 250ml', 'standard-250ml-dip-choices'),
				),
			),
			'nutz-banana-10kg-deal' => array(
				'label' => 'Nutz Banana 10kg Deal',
				'slots' => array(
					self::slot('Boilie 1', 'nutz-banana-boilie-sizes'),
					self::slot('Boilie 2', 'nutz-banana-boilie-sizes'),
					self::slot('Hookbait 1', 'nutz-banana-hookbait-choices'),
					self::slot('Hookbait 2', 'nutz-banana-hookbait-choices'),
					self::slot('500ml Dip', 'standard-500ml-liquid-choices'),
				),
			),
			'30kg-boilie-deal' => array(
				'label' => '30kg Boilie Deal',
				'slots' => array(
					self::slot('10kg Split 1', 'standard-boilie-choices'),
					self::slot('10kg Split 2', 'standard-boilie-choices'),
					self::slot('10kg Split 3', 'standard-boilie-choices'),
					self::slot('Hookbait 1', 'standard-hookbait-choices'),
					self::slot('Hookbait 2', 'standard-hookbait-choices'),
					self::slot('Liquid 1', 'standard-500ml-liquid-choices'),
					self::slot('Liquid 2', 'standard-500ml-liquid-choices'),
				),
			),
			'20kg-boilie-deal' => array(
				'label' => '20KG Boilie Deal',
				'slots' => array(
					self::slot('5KG Split 1', 'standard-boilie-choices'),
					self::slot('5KG Split 2', 'standard-boilie-choices'),
					self::slot('5KG Split 3', 'standard-boilie-choices'),
					self::slot('5KG Split 4', 'standard-boilie-choices'),
					self::slot('Hookbait 1', 'standard-hookbait-choices'),
					self::slot('Hookbait 2', 'standard-hookbait-choices'),
					self::slot('Liquid', 'standard-500ml-liquid-choices'),
				),
			),
			'10kg-boilie-deal' => array(
				'label' => '10KG Boilie Deal',
				'slots' => array(
					self::slot('5KG Split 1', 'standard-boilie-choices'),
					self::slot('5KG Split 2', 'standard-boilie-choices'),
					self::slot('Hookbait 1', 'standard-hookbait-choices'),
					self::slot('Hookbait 2', 'standard-hookbait-choices'),
				),
			),
			'50kg-ultimate-trip' => array(
				'label' => '50kg Ultimate Trip Package',
				'slots' => array(
					self::slot('10kg Split 1', 'standard-boilie-choices'),
					self::slot('10kg Split 2', 'standard-boilie-choices'),
					self::slot('10kg Split 3', 'standard-boilie-choices'),
					self::slot('10kg Split 4', 'standard-boilie-choices'),
					self::slot('10kg Split 5', 'standard-boilie-choices'),
					self::slot('Hookbait 1', 'standard-hookbait-choices'),
					self::slot('Hookbait 2', 'standard-hookbait-choices'),
					self::slot('Hookbait 3', 'standard-hookbait-choices'),
					self::slot('Hookbait 4', 'standard-hookbait-choices'),
					self::slot('Liquid 1', 'standard-500ml-liquid-choices'),
					self::slot('Liquid 2', 'standard-500ml-liquid-choices'),
					self::slot('Liquid 3', 'standard-500ml-liquid-choices'),
					self::slot('Liquid 4', 'standard-500ml-liquid-choices'),
				),
			),
			'pandemic-barbel-pack' => array(
				'label' => 'Pandemic Barbel Pack',
				'slots' => array(
					self::slot('Boilie Size', 'pandemic-barbel-boilie-size'),
					self::slot('Skinz Hookbaits', 'pandemic-barbel-skinz-hookbaits'),
				),
			),
			'25kg-boilie-pellet-deal' => array(
				'label' => '25KG Boilie & Pellet Deal',
				'slots' => array(
					self::slot('5KG Split 1', 'standard-boilie-choices'),
					self::slot('5KG Split 2', 'standard-boilie-choices'),
					self::slot('5KG Split 3', 'standard-boilie-choices'),
					self::slot('5KG Split 4', 'standard-boilie-choices'),
					self::slot('Hookbait 1', 'standard-hookbait-choices'),
					self::slot('Hookbait 2', 'standard-hookbait-choices'),
					self::slot('Liquid', 'standard-500ml-liquid-choices'),
					self::slot('Pellet 5kg', 'standard-pellet-choices'),
				),
			),
			'nutz-banana-session-bucket' => array(
				'label' => 'Nutz Banana Session Bucket Deal',
				'slots' => array(
					self::slot('Boilie 1', 'nutz-banana-boilie-sizes'),
					self::slot('Boilie 2', 'nutz-banana-boilie-sizes'),
					self::slot('Hookbait 1', 'nutz-banana-hookbait-choices'),
					self::slot('Hookbait 2', 'nutz-banana-hookbait-choices'),
					self::slot('Hookbait 3', 'nutz-banana-hookbait-choices'),
				),
			),
			'nutz-banana-5kg-deal' => array(
				'label' => 'Nutz Banana 5kg Deal',
				'slots' => array(
					self::slot('Boilie 1', 'nutz-banana-boilie-sizes'),
					self::slot('Boilie 2', 'nutz-banana-boilie-sizes'),
					self::slot('Boilie 3', 'nutz-banana-boilie-sizes'),
					self::slot('Boilie 4', 'nutz-banana-boilie-sizes'),
					self::slot('Boilie 5', 'nutz-banana-boilie-sizes'),
					self::slot('Hookbait 1', 'nutz-banana-hookbait-choices'),
					self::slot('Hookbait 2', 'nutz-banana-hookbait-choices'),
					self::slot('500ml Dip', 'standard-500ml-liquid-choices'),
				),
			),
			'1kg-boilie-deal' => array(
				'label' => '1kg Boilie Deal',
				'slots' => array(
					self::slot('Boilie', 'standard-boilie-choices'),
					self::slot('Hookbait', 'standard-hookbait-choices'),
					self::slot('Dip', 'standard-250ml-dip-choices'),
				),
			),
		);

		return $defs;
	}

	/**
	 * @param string $label   Slot label.
	 * @param string $pool_id Pool ID.
	 * @return array<string, mixed>
	 */
	private static function slot($label, $pool_id) {
		return array(
			'label'          => $label,
			'key'            => MBBB_Plugin::sanitize_slot_key($label),
			'required'       => true,
			'min'            => 1,
			'max'            => 1,
			'help'           => '',
			'source'         => 'attribute',
			'pool_id'        => $pool_id,
			'allow_same'     => false,
			'use_images'     => false,
			'display'        => 'cards',
			'manual_options' => array(),
		);
	}

	/**
	 * Detect preset from product title.
	 *
	 * @param string $title Product title.
	 * @return string Preset ID or empty.
	 */
	public static function detect_preset_from_title($title) {
		$haystack = strtolower($title);
		$map      = array(
			'weekend session pack'           => 'weekend-session-pack',
			'nutz banana 10kg'               => 'nutz-banana-10kg-deal',
			'30kg boilie deal'               => '30kg-boilie-deal',
			'20kg boilie deal'               => '20kg-boilie-deal',
			'10kg boilie deal'               => '10kg-boilie-deal',
			'50kg ultimate trip'             => '50kg-ultimate-trip',
			'pandemic barbel pack'           => 'pandemic-barbel-pack',
			'25kg boilie & pellet'           => '25kg-boilie-pellet-deal',
			'25kg boilie and pellet'         => '25kg-boilie-pellet-deal',
			'nutz banana session bucket'     => 'nutz-banana-session-bucket',
			'nutz banana 5kg'                => 'nutz-banana-5kg-deal',
			'1kg boilie deal'                => '1kg-boilie-deal',
		);

		foreach ($map as $needle => $preset_id) {
			if (false !== strpos($haystack, $needle)) {
				return $preset_id;
			}
		}

		return '';
	}

	/**
	 * @param string $preset_id Preset ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_preset_slots($preset_id) {
		$presets = self::get_presets();
		if (! isset($presets[ $preset_id ]['slots'])) {
			return array();
		}
		return $presets[ $preset_id ]['slots'];
	}
}
