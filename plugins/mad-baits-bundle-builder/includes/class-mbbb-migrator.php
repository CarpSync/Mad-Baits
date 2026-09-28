<?php
/**
 * Migration from legacy variation attributes to bundle slots.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Migrator.
 */
final class MBBB_Migrator {

	/**
	 * Attribute name patterns for bundle slots.
	 *
	 * @return string[]
	 */
	public static function bundle_attribute_patterns() {
		return array(
			'boilie',
			'hookbait',
			'hook bait',
			'pellet',
			'dip',
			'food dip',
			'liquid',
			'5kg split',
			'10kg split',
			'5 kg split',
			'10 kg split',
		);
	}

	/**
	 * Scan products that look like legacy bundle variable products.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function scan_bundle_products() {
		if (! function_exists('wc_get_products')) {
			return array();
		}

		$query_args = array(
			'status' => array('publish', 'draft', 'private'),
			'limit'  => -1,
			'type'   => array('variable', 'simple'),
		);

		if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$ids                  = wc_get_products($query_args);
		}
		$rows = array();

		foreach ((array) $ids as $product_id) {
			$product = wc_get_product((int) $product_id);
			if (! $product) {
				continue;
			}

			$detected_slots = $this->detect_slots_from_product($product);
			if (empty($detected_slots)) {
				continue;
			}

			$preset = MBBB_Presets::detect_preset_from_title($product->get_name());
			$rows[] = array(
				'id'             => (int) $product_id,
				'title'          => $product->get_name(),
				'edit_url'       => get_edit_post_link($product_id, 'raw'),
				'enabled'        => MBBB_Plugin::instance()->is_enabled($product_id),
				'slot_count'     => count($detected_slots),
				'detected_slots' => $detected_slots,
				'suggested_preset' => $preset,
				'preset_label'   => $preset ? MBBB_Presets::get_presets()[ $preset ]['label'] : '',
			);
		}

		return $rows;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_slots_from_product($product) {
		$slots = array();
		foreach ($product->get_attributes() as $attr) {
			if (! $attr instanceof WC_Product_Attribute) {
				continue;
			}
			$label = wc_attribute_label($attr->get_name());
			if (! $this->is_bundle_attribute_name($label)) {
				continue;
			}

			$options = array();
			if ($attr->is_taxonomy()) {
				$terms = wc_get_product_terms($product->get_id(), $attr->get_name(), array('fields' => 'names'));
				foreach ((array) $terms as $name) {
					$options[] = array('label' => $name, 'value' => sanitize_title($name), 'active' => true);
				}
			} else {
				foreach ($attr->get_options() as $opt) {
					$options[] = array('label' => (string) $opt, 'value' => sanitize_title((string) $opt), 'active' => true);
				}
			}

			$slots[] = array(
				'label'          => $label,
				'key'            => MBBB_Plugin::sanitize_slot_key($attr->get_name() ? $attr->get_name() : $label),
				'required'       => true,
				'min'            => 1,
				'max'            => 1,
				'help'           => '',
				'source'         => 'manual',
				'manual_options' => $options,
				'allow_same'     => false,
				'use_images'     => false,
				'display'        => 'cards',
			);
		}

		return $slots;
	}

	/**
	 * @param string $name Attribute label.
	 * @return bool
	 */
	public function is_bundle_attribute_name($name) {
		$haystack = strtolower($name);
		foreach (self::bundle_attribute_patterns() as $pattern) {
			if (false !== strpos($haystack, $pattern)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Import detected slots to product (does not enable builder, does not delete attributes).
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $enable     Enable builder after import.
	 * @return bool
	 */
	public function import_to_product($product_id, $enable = false) {
		$product = wc_get_product($product_id);
		if (! $product) {
			return false;
		}

		$slots = $this->detect_slots_from_product($product);
		if (empty($slots)) {
			$preset = MBBB_Presets::detect_preset_from_title($product->get_name());
			if ($preset) {
				$slots = MBBB_Presets::get_preset_slots($preset);
			}
		}

		if (empty($slots)) {
			return false;
		}

		MBBB_Plugin::instance()->save_slots($product_id, $slots);

		if ($enable) {
			update_post_meta($product_id, MBBB_Plugin::META_ENABLED, 'yes');
		}

		MBBB_Plugin::instance()->log(
			sprintf('Imported %d bundle slot(s) to product #%d', count($slots), $product_id),
			'import'
		);

		return true;
	}
}
