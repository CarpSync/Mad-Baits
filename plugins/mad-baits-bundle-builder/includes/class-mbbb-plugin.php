<?php
/**
 * Core plugin helpers.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Plugin core.
 */
final class MBBB_Plugin {

	const META_ENABLED   = '_mbbb_enabled';
	const META_SETTINGS  = '_mbbb_settings';
	const META_SLOTS     = '_mbbb_slots';
	const OPTION_POOLS   = 'mbbb_global_pools';
	const OPTION_SETTINGS = 'mbbb_global_settings';
	const OPTION_LOGS    = 'mbbb_logs';
	const CART_META_KEY  = 'mbbb_choices';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Default product settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings() {
		$global = self::get_global_settings();

		return array(
			'hide_variations' => 'yes',
			'fixed_price'     => 'yes',
			'display_style'   => $global['display_style'],
			'sticky_atc'      => 'yes',
			'show_summary'    => 'yes',
		);
	}

	/**
	 * Default global plugin settings.
	 *
	 * @return array<string, string>
	 */
	public static function default_global_settings() {
		return array(
			'display_style'               => 'card',
			'force_product_attributes'    => 'no',
			'auto_advance'                => 'yes',
			'auto_advance_delay_ms'       => '750',
			'manual_next_slot_threshold'  => '8',
			'suggest_new_pool_products'   => 'no',
		);
	}

	/**
	 * @param mixed $value Raw delay in ms.
	 * @return int
	 */
	public static function sanitize_auto_advance_delay_ms($value) {
		$delay = absint($value);
		if ($delay < 600) {
			return 600;
		}
		if ($delay > 900) {
			return 900;
		}

		return $delay;
	}

	/**
	 * @param mixed $value Raw option count threshold.
	 * @return int
	 */
	public static function sanitize_manual_next_slot_threshold($value) {
		$threshold = absint($value);
		if ($threshold < 4) {
			return 4;
		}
		if ($threshold > 24) {
			return 24;
		}

		return $threshold;
	}

	/**
	 * @param mixed $value Raw yes/no value.
	 * @return string yes|no
	 */
	public static function sanitize_yes_no($value) {
		return ! empty($value) && 'no' !== $value ? 'yes' : 'no';
	}

	/**
	 * Whether all bundle slots should resolve from WooCommerce product attributes.
	 *
	 * @return bool
	 */
	public static function forces_product_attributes() {
		return 'yes' === self::get_global_settings()['force_product_attributes'];
	}

	/**
	 * Allowed builder display styles.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function display_style_choices() {
		return array(
			'card'      => __('Cards', 'mad-baits-bundle-builder'),
			'step'      => __('Step-by-step', 'mad-baits-bundle-builder'),
			'accordion' => __('Accordion', 'mad-baits-bundle-builder'),
		);
	}

	/**
	 * Normalize display style slug (legacy compact => card).
	 *
	 * @param string $style Raw style.
	 * @return string
	 */
	public static function sanitize_display_style($style) {
		$style = sanitize_key((string) $style);
		if ('compact' === $style) {
			$style = 'card';
		}

		return array_key_exists($style, self::display_style_choices()) ? $style : 'card';
	}

	/**
	 * Global bundle builder settings (display style, etc.).
	 *
	 * @return array<string, string>
	 */
	public static function get_global_settings() {
		$stored = get_option(self::OPTION_SETTINGS, array());
		if (! is_array($stored)) {
			$stored = array();
		}

		$settings = array_merge(self::default_global_settings(), $stored);
		$settings['display_style']              = self::sanitize_display_style($settings['display_style']);
		$settings['force_product_attributes']   = self::sanitize_yes_no($settings['force_product_attributes'] ?? 'no');
		$settings['auto_advance']               = self::sanitize_yes_no($settings['auto_advance'] ?? 'yes');
		$settings['auto_advance_delay_ms']      = (string) self::sanitize_auto_advance_delay_ms($settings['auto_advance_delay_ms'] ?? 750);
		$settings['manual_next_slot_threshold'] = (string) self::sanitize_manual_next_slot_threshold($settings['manual_next_slot_threshold'] ?? 8);
		$settings['suggest_new_pool_products']  = self::sanitize_yes_no($settings['suggest_new_pool_products'] ?? 'no');

		return $settings;
	}

	/**
	 * @param array<string, string> $settings Settings.
	 * @return void
	 */
	public function save_global_settings($settings) {
		$existing = self::get_global_settings();
		$clean    = array(
			'display_style'              => self::sanitize_display_style($settings['display_style'] ?? $existing['display_style']),
			'force_product_attributes'   => self::sanitize_yes_no($settings['force_product_attributes'] ?? $existing['force_product_attributes']),
			'auto_advance'               => self::sanitize_yes_no($settings['auto_advance'] ?? $existing['auto_advance']),
			'auto_advance_delay_ms'      => (string) self::sanitize_auto_advance_delay_ms($settings['auto_advance_delay_ms'] ?? $existing['auto_advance_delay_ms']),
			'manual_next_slot_threshold' => (string) self::sanitize_manual_next_slot_threshold($settings['manual_next_slot_threshold'] ?? $existing['manual_next_slot_threshold']),
		);
		update_option(self::OPTION_SETTINGS, $clean, false);
		wp_cache_delete(self::OPTION_SETTINGS, 'options');
	}

	/**
	 * Apply global slot rules (e.g. force attribute source instead of manual/global pools).
	 *
	 * @param array<string, mixed> $slot       Slot config.
	 * @param int                  $product_id Parent product ID.
	 * @return array<string, mixed>
	 */
	public function apply_global_slot_rules($slot, $product_id = 0) {
		$global = self::get_global_settings();

		if ('card' === $global['display_style']) {
			$slot['display'] = 'cards';
		}

		if (! self::forces_product_attributes()) {
			return $slot;
		}

		$source  = isset($slot['source']) ? (string) $slot['source'] : 'manual';
		$pool_id = isset($slot['pool_id']) ? trim((string) $slot['pool_id']) : '';

		// Presets use source=attribute with a pool_id fallback — still force live variation attributes.
		if (! in_array($source, array('manual', 'global_pool', 'attribute', ''), true) && '' === $pool_id) {
			return $slot;
		}

		$slot['source'] = 'attribute';
		unset($slot['pool_id']);

		$attribute = isset($slot['attribute']) ? trim((string) $slot['attribute']) : '';
		if ('' === $attribute) {
			$product = wc_get_product(absint($product_id));
			if ($product instanceof WC_Product_Variable) {
				$hint_options = array();
				if (! empty($slot['manual_options']) && is_array($slot['manual_options'])) {
					foreach ($slot['manual_options'] as $row) {
						$hint_options[] = $this->normalize_option_row($row);
					}
				}
				$matched = $this->resolve_slot_variation_attribute_key($slot, $product, $hint_options);
				if ('' !== $matched) {
					$slot['attribute'] = $this->normalize_wc_attribute_key($matched);
				}
			}
		}

		return $slot;
	}

	/**
	 * Slots with global builder rules applied (attribute source, card display, etc.).
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_resolved_slots($product_id) {
		$slots = $this->get_slots($product_id);
		$out   = array();

		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$out[] = $this->apply_global_slot_rules($slot, $product_id);
		}

		return $out;
	}

	/**
	 * Whether the builder should use one-at-a-time mobile step flow.
	 *
	 * Global display_style=step always uses step flow.
	 * Bundles with 4+ slots also force step flow on mobile to avoid a wall of slots.
	 *
	 * @param int $product_id Optional product ID (uses slot count when provided).
	 * @return bool
	 */
	public static function uses_mobile_step_flow($product_id = 0) {
		if ('step' === self::get_global_settings()['display_style']) {
			return true;
		}

		$product_id = absint($product_id);
		if ($product_id > 0) {
			$slots = self::instance()->get_resolved_slots($product_id);
			if (count($slots) >= 4) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is bundle builder enabled for product?
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function is_enabled($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}
		return 'yes' === get_post_meta($product_id, self::META_ENABLED, true);
	}

	/**
	 * Whether the product should use the bundle-builder PDP.
	 *
	 * The builder is disabled for every product. Stored slot meta is not read or removed.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function uses_bundle_builder_ui($product_id) {
		// Off for every product. Slot meta and order history stay stored.
		unset($product_id);
		return false;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function should_hide_wc_variation_ui($product_id) {
		return $this->uses_bundle_builder_ui($product_id);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public function get_settings($product_id) {
		$stored = get_post_meta(absint($product_id), self::META_SETTINGS, true);
		if (! is_array($stored)) {
			$stored = array();
		}

		$settings = array_merge(self::default_settings(), $stored);
		$global   = self::get_global_settings();
		if (! empty($global['display_style'])) {
			$settings['display_style'] = $global['display_style'];
		}

		return $settings;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_slots($product_id) {
		$slots = get_post_meta(absint($product_id), self::META_SLOTS, true);
		return is_array($slots) ? array_values($slots) : array();
	}

	/**
	 * Persist bundle slots using WooCommerce product meta APIs when available.
	 *
	 * Data structure (stored in `_mbbb_slots`):
	 * [
	 *   [
	 *     'label' => '10kg Split 1',
	 *     'key' => '10kg-split-1',          // POST key for mbbb_choices[key]
	 *     'required' => true,
	 *     'min' => 1, 'max' => 1,
	 *     'help' => '',
	 *     'source' => 'attribute',         // attribute|global_pool|manual|category|tag|products
	 *     'pool_id' => '',
	 *     'attribute' => 'pa_boilie_1',    // linked WC variation attribute (preferred)
	 *     'category_id' => 0,
	 *     'tag_id' => 0,
	 *     'product_ids' => [],
	 *     'display' => 'cards',            // cards|pills|dropdown
	 *     'manual_options' => [],
	 *     'allow_same' => false,
	 *     'use_images' => false,
	 *   ],
	 *   ...
	 * ]
	 *
	 * Save flow: admin JS → mbbb_slots_json → MBBB_Admin::sanitize_slots()
	 * → validate_slots_for_save() → save_slots() → WC product meta / post meta.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $slots      Slots.
	 * @return bool True when meta was written.
	 */
	public function save_slots($product_id, $slots) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}

		$slots   = self::ensure_unique_slot_keys($slots);
		$product = wc_get_product($product_id);

		if ($product instanceof WC_Product) {
			$product->update_meta_data(self::META_SLOTS, $slots);
			// save_meta_data() persists meta without a full product save (safer mid-request).
			$product->save_meta_data();
			return true;
		}

		update_post_meta($product_id, self::META_SLOTS, $slots);
		return true;
	}

	/**
	 * Persist enabled flag + settings via WC product meta APIs.
	 *
	 * @param int                       $product_id Product ID.
	 * @param string                    $enabled    yes|no.
	 * @param array<string, mixed>      $settings   Product settings.
	 * @param array<int, array>|null    $slots      Optional slots to save in the same write.
	 * @return bool
	 */
	public function save_product_bundle_meta($product_id, $enabled, $settings, $slots = null) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}

		$enabled  = self::sanitize_yes_no($enabled);
		$defaults = self::default_settings();
		$clean    = array(
			'hide_variations' => self::sanitize_yes_no($settings['hide_variations'] ?? $defaults['hide_variations']),
			'fixed_price'     => self::sanitize_yes_no($settings['fixed_price'] ?? $defaults['fixed_price']),
			'display_style'   => self::sanitize_display_style($settings['display_style'] ?? $defaults['display_style']),
			'sticky_atc'      => self::sanitize_yes_no($settings['sticky_atc'] ?? $defaults['sticky_atc']),
			'show_summary'    => self::sanitize_yes_no($settings['show_summary'] ?? $defaults['show_summary']),
		);

		$slots_to_save = null;
		if (null !== $slots) {
			$slots_to_save = self::ensure_unique_slot_keys($slots);
		}

		$product = wc_get_product($product_id);
		if ($product instanceof WC_Product) {
			$product->update_meta_data(self::META_ENABLED, $enabled);
			$product->update_meta_data(self::META_SETTINGS, $clean);
			if (null !== $slots_to_save) {
				$product->update_meta_data(self::META_SLOTS, $slots_to_save);
			}
			$product->save_meta_data();
			return true;
		}

		update_post_meta($product_id, self::META_ENABLED, $enabled);
		update_post_meta($product_id, self::META_SETTINGS, $clean);
		if (null !== $slots_to_save) {
			update_post_meta($product_id, self::META_SLOTS, $slots_to_save);
		}
		return true;
	}

	/**
	 * Variation attributes available for Bundle Builder admin mapping.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array{key: string, label: string, values: array<int, string>, value_count: int}>
	 */
	public function get_admin_variation_attributes($product_id) {
		$product = wc_get_product(absint($product_id));
		if (! $product instanceof WC_Product_Variable) {
			return array();
		}

		$variation_attributes = (array) $product->get_variation_attributes();
		$rows                 = array();

		foreach ($variation_attributes as $attr_key => $values) {
			$attr_key = (string) $attr_key;
			$clean    = $this->normalize_wc_attribute_key($attr_key);
			$values   = array_values(array_filter(array_map('strval', (array) $values)));
			$rows[]   = array(
				'key'         => $clean ? $clean : $attr_key,
				'label'       => wc_attribute_label($clean ? $clean : $attr_key, $product),
				'values'      => $values,
				'value_count' => count($values),
			);
		}

		return $rows;
	}

	/**
	 * Resolve the linked variation attribute key for a slot (explicit or auto-matched).
	 *
	 * @param array<string, mixed> $slot       Slot.
	 * @param int                  $product_id Product ID.
	 * @return string Normalized attribute key, or empty string.
	 */
	public function get_slot_linked_attribute($slot, $product_id) {
		$product = wc_get_product(absint($product_id));
		if (! $product instanceof WC_Product_Variable || ! is_array($slot)) {
			$explicit = isset($slot['attribute']) ? $this->normalize_wc_attribute_key((string) $slot['attribute']) : '';
			return $explicit;
		}

		$matched = $this->resolve_slot_variation_attribute_key($slot, $product);
		return '' !== $matched ? $this->normalize_wc_attribute_key($matched) : '';
	}

	/**
	 * Back-compat: enrich saved slots with explicit attribute links when missing.
	 * Does not change source/options — only fills `attribute` for clearer admin UX
	 * and more reliable cart variation matching on next save.
	 *
	 * @param array<int, array<string, mixed>> $slots      Slots.
	 * @param int                              $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function migrate_slots_attribute_links($slots, $product_id) {
		if (! is_array($slots)) {
			return array();
		}

		$product = wc_get_product(absint($product_id));
		if (! $product instanceof WC_Product_Variable) {
			return array_values($slots);
		}

		$out = array();
		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$explicit = isset($slot['attribute']) ? trim((string) $slot['attribute']) : '';
			if ('' === $explicit) {
				$matched = $this->resolve_slot_variation_attribute_key($slot, $product);
				if ('' !== $matched) {
					$slot['attribute'] = $this->normalize_wc_attribute_key($matched);
				}
			} else {
				$slot['attribute'] = $this->normalize_wc_attribute_key($explicit);
			}
			$out[] = $slot;
		}

		return $out;
	}

	/**
	 * Build one slot per variation attribute (variation-led setup).
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function build_slots_from_variation_attributes($product_id) {
		$attrs = $this->get_admin_variation_attributes($product_id);
		$slots = array();

		foreach ($attrs as $attr) {
			$label = isset($attr['label']) ? (string) $attr['label'] : '';
			$key   = isset($attr['key']) ? (string) $attr['key'] : '';
			if ('' === $label) {
				continue;
			}
			$slots[] = array(
				'label'          => $label,
				'key'            => self::sanitize_slot_key($label),
				'required'       => true,
				'min'            => 1,
				'max'            => 1,
				'help'           => '',
				'source'         => 'attribute',
				'pool_id'        => '',
				'attribute'      => $key,
				'category_id'    => 0,
				'tag_id'         => 0,
				'product_ids'    => array(),
				'allow_same'     => false,
				'use_images'     => false,
				'display'        => 'cards',
				'manual_options' => array(),
			);
		}

		return self::ensure_unique_slot_keys($slots);
	}

	/**
	 * Validate slots before save. Returns human-readable error messages.
	 *
	 * @param array<int, array<string, mixed>> $slots      Slots.
	 * @param int                              $product_id Product ID.
	 * @return array<int, string>
	 */
	public function validate_slots_for_save($slots, $product_id = 0) {
		$errors   = array();
		$keys     = array();
		$attr_map = array();
		$pools    = $this->get_pools();

		foreach ($this->get_admin_variation_attributes($product_id) as $attr) {
			$attr_map[ $this->normalize_option_lookup((string) $attr['key']) ] = true;
			$attr_map[ $this->normalize_option_lookup((string) $attr['label']) ] = true;
		}

		if (! is_array($slots)) {
			return array(__('Bundle slots data is invalid.', 'mad-baits-bundle-builder'));
		}

		foreach (array_values($slots) as $index => $slot) {
			if (! is_array($slot)) {
				continue;
			}

			$n     = $index + 1;
			$label = isset($slot['label']) ? trim((string) $slot['label']) : '';
			$key   = isset($slot['key']) ? sanitize_title((string) $slot['key']) : '';
			if ('' === $key && '' !== $label) {
				$key = self::sanitize_slot_key($label);
			}

			if ('' === $label) {
				$errors[] = sprintf(
					/* translators: %d: slot number */
					__('Slot %d is missing a label.', 'mad-baits-bundle-builder'),
					$n
				);
				continue;
			}

			if ('' === $key) {
				$errors[] = sprintf(
					/* translators: %s: slot label */
					__('Slot “%s” is missing a key.', 'mad-baits-bundle-builder'),
					$label
				);
			} elseif (isset($keys[ $key ])) {
				$errors[] = sprintf(
					/* translators: 1: slot key, 2: slot label */
					__('Duplicate slot key “%1$s” on “%2$s”. Each slot needs a unique key.', 'mad-baits-bundle-builder'),
					$key,
					$label
				);
			} else {
				$keys[ $key ] = true;
			}

			$required = ! empty($slot['required']);
			$source   = isset($slot['source']) ? sanitize_key((string) $slot['source']) : '';
			if ($required && ! $this->slot_has_configured_source($slot)) {
				$errors[] = sprintf(
					/* translators: %s: slot label */
					__('Required slot “%s” has no option source configured.', 'mad-baits-bundle-builder'),
					$label
				);
			}

			$entity_errors = $this->validate_slot_source_entities($slot, $label, $pools);
			foreach ($entity_errors as $entity_error) {
				$errors[] = $entity_error;
			}

			$attribute = isset($slot['attribute']) ? trim((string) $slot['attribute']) : '';
			if ('' !== $attribute && ! empty($attr_map)) {
				$needle = $this->normalize_option_lookup($attribute);
				if ('' !== $needle && empty($attr_map[ $needle ])) {
					// Also accept fuzzy match via resolver for legacy keys.
					$linked = $this->get_slot_linked_attribute(
						array_merge($slot, array('attribute' => $attribute)),
						$product_id
					);
					if ('' === $linked) {
						$errors[] = sprintf(
							/* translators: 1: attribute key, 2: slot label */
							__('Variation attribute “%1$s” was not found for slot “%2$s”.', 'mad-baits-bundle-builder'),
							$attribute,
							$label
						);
					}
				}
			} elseif ('attribute' === $source && $required && '' === $attribute && ! empty($attr_map)) {
				$linked = $this->get_slot_linked_attribute($slot, $product_id);
				if ('' === $linked) {
					$errors[] = sprintf(
						/* translators: %s: slot label */
						__('Slot “%s” is set to Product attribute but no matching variation attribute was found. Link one explicitly.', 'mad-baits-bundle-builder'),
						$label
					);
				}
			}

			if ($required && $this->slot_has_configured_source($slot)) {
				$options = $this->resolve_slot_options($slot, $product_id);
				if (empty($options)) {
					$errors[] = sprintf(
						/* translators: %s: slot label */
						__('Required slot “%s” resolves to zero customer options. Check the source products, attribute, pool, or term.', 'mad-baits-bundle-builder'),
						$label
					);
				} else {
					$commerce_errors = $this->validate_slot_option_commerce($options, $label);
					foreach ($commerce_errors as $commerce_error) {
						$errors[] = $commerce_error;
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate that configured source entities exist.
	 *
	 * @param array<string, mixed>         $slot  Slot.
	 * @param string                       $label Slot label.
	 * @param array<string, array<mixed>>  $pools Global pools.
	 * @return array<int, string>
	 */
	public function validate_slot_source_entities($slot, $label, array $pools = array()) {
		$errors = array();
		$source = isset($slot['source']) ? sanitize_key((string) $slot['source']) : '';
		$label  = (string) $label;

		switch ($source) {
			case 'global_pool':
				$pool_id = trim((string) ($slot['pool_id'] ?? ''));
				if ('' !== $pool_id && empty($pools[ $pool_id ])) {
					$errors[] = sprintf(
						/* translators: 1: pool id, 2: slot label */
						__('Global pool “%1$s” was not found for slot “%2$s”.', 'mad-baits-bundle-builder'),
						$pool_id,
						$label
					);
				}
				break;
			case 'category':
				$term_id = absint($slot['category_id'] ?? 0);
				if ($term_id > 0) {
					$term = get_term($term_id, 'product_cat');
					if (! $term instanceof WP_Term || is_wp_error($term)) {
						$errors[] = sprintf(
							/* translators: %s: slot label */
							__('Category source for slot “%s” does not exist.', 'mad-baits-bundle-builder'),
							$label
						);
					}
				}
				break;
			case 'tag':
				$term_id = absint($slot['tag_id'] ?? 0);
				if ($term_id > 0) {
					$term = get_term($term_id, 'product_tag');
					if (! $term instanceof WP_Term || is_wp_error($term)) {
						$errors[] = sprintf(
							/* translators: %s: slot label */
							__('Tag source for slot “%s” does not exist.', 'mad-baits-bundle-builder'),
							$label
						);
					}
				}
				break;
			case 'products':
				$ids = isset($slot['product_ids']) ? array_filter(array_map('absint', (array) $slot['product_ids'])) : array();
				foreach ($ids as $pid) {
					$product = wc_get_product($pid);
					if (! $product instanceof WC_Product) {
						$errors[] = sprintf(
							/* translators: 1: product id, 2: slot label */
							__('Product #%1$d for slot “%2$s” does not exist.', 'mad-baits-bundle-builder'),
							$pid,
							$label
						);
					}
				}
				break;
		}

		return $errors;
	}

	/**
	 * Validate purchasable/stock state for product-backed slot options.
	 *
	 * @param array<int, array<string, mixed>> $options Resolved options.
	 * @param string                           $label   Slot label.
	 * @return array<int, string>
	 */
	public function validate_slot_option_commerce(array $options, $label) {
		$errors         = array();
		$label          = (string) $label;
		$checked        = 0;
		$invalid_count  = 0;

		foreach ($options as $option) {
			if (! is_array($option)) {
				continue;
			}
			$product_id = absint($option['product_id'] ?? 0);
			if ($product_id < 1) {
				continue;
			}
			++$checked;
			$product = wc_get_product($product_id);
			if (! $product instanceof WC_Product) {
				++$invalid_count;
				continue;
			}
			if (! $product->is_purchasable()) {
				++$invalid_count;
				continue;
			}
			if (! $product->is_in_stock() && ! $product->backorders_allowed()) {
				++$invalid_count;
			}
		}

		if ($checked > 0 && $invalid_count === $checked) {
			$errors[] = sprintf(
				/* translators: %s: slot label */
				__('Slot “%s” only contains products that are missing, not purchasable, or out of stock (without backorders).', 'mad-baits-bundle-builder'),
				$label
			);
		}

		return $errors;
	}

	/**
	 * Validate a full enabled-bundle configuration (slots required when enabled).
	 *
	 * @param string                           $enabled    yes|no.
	 * @param array<int, array<string, mixed>> $slots      Slots.
	 * @param int                              $product_id Product ID.
	 * @return array<int, string>
	 */
	public function validate_enabled_bundle_config($enabled, $slots, $product_id = 0) {
		$errors = array();
		if ('yes' !== self::sanitize_yes_no($enabled)) {
			return $errors;
		}

		if (! is_array($slots) || count($slots) < 1) {
			$errors[] = __('Bundle Builder is enabled but no slots are configured.', 'mad-baits-bundle-builder');
			return $errors;
		}

		return $this->validate_slots_for_save($slots, $product_id);
	}

	/**
	 * Admin preview rows for customer-facing steps.
	 *
	 * @param array<int, array<string, mixed>> $slots      Slots.
	 * @param int                              $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function build_admin_preview_steps($slots, $product_id = 0) {
		$rows = array();
		if (! is_array($slots)) {
			return $rows;
		}

		foreach (array_values($slots) as $index => $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$label    = isset($slot['label']) ? trim((string) $slot['label']) : '';
			$required = ! empty($slot['required']);
			$source   = isset($slot['source']) ? sanitize_key((string) $slot['source']) : '';
			$min      = isset($slot['min']) ? absint($slot['min']) : 1;
			$max      = isset($slot['max']) ? absint($slot['max']) : 1;
			$options  = $this->resolve_slot_options($slot, $product_id);
			$default  = '';
			foreach ($options as $option) {
				if (! empty($option['default']) || ! empty($option['is_default'])) {
					$default = (string) ($option['label'] ?? $option['value'] ?? '');
					break;
				}
			}

			$rows[] = array(
				'index'         => $index + 1,
				'label'         => $label,
				'required'      => $required,
				'source'        => $source,
				'min'           => $min,
				'max'           => max($min, $max),
				'option_count'  => count($options),
				'default_label' => $default,
				'is_broken'     => $required && count($options) < 1,
			);
		}

		return $rows;
	}

	/**
	 * Whether a slot has enough source config to resolve options.
	 *
	 * @param array<string, mixed> $slot Slot.
	 * @return bool
	 */
	public function slot_has_configured_source($slot) {
		if (! is_array($slot)) {
			return false;
		}

		$source = isset($slot['source']) ? sanitize_key((string) $slot['source']) : '';
		switch ($source) {
			case 'attribute':
				return true; // May auto-match from label/key at runtime.
			case 'global_pool':
				return '' !== trim((string) ($slot['pool_id'] ?? ''));
			case 'manual':
				return ! empty($slot['manual_options']) && is_array($slot['manual_options']);
			case 'category':
				return absint($slot['category_id'] ?? 0) > 0;
			case 'tag':
				return absint($slot['tag_id'] ?? 0) > 0;
			case 'products':
				$ids = isset($slot['product_ids']) ? array_filter(array_map('absint', (array) $slot['product_ids'])) : array();
				return ! empty($ids);
			default:
				return false;
		}
	}

	/**
	 * Admin summary rows for the slots table (label, key, source, linked attribute).
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_admin_slot_summary($product_id) {
		$slots   = $this->get_slots($product_id);
		$attrs   = $this->get_admin_variation_attributes($product_id);
		$labels  = array();
		foreach ($attrs as $attr) {
			$labels[ (string) $attr['key'] ] = (string) $attr['label'];
		}

		$source_labels = array(
			'attribute'   => __('Product attribute', 'mad-baits-bundle-builder'),
			'global_pool' => __('Global pool', 'mad-baits-bundle-builder'),
			'manual'      => __('Manual options', 'mad-baits-bundle-builder'),
			'category'    => __('Product category', 'mad-baits-bundle-builder'),
			'tag'         => __('Product tag', 'mad-baits-bundle-builder'),
			'products'    => __('Manual products', 'mad-baits-bundle-builder'),
		);

		$rows = array();
		foreach ($slots as $index => $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$linked = $this->get_slot_linked_attribute($slot, $product_id);
			$rows[] = array(
				'index'          => $index + 1,
				'label'          => (string) ($slot['label'] ?? ''),
				'key'            => (string) ($slot['key'] ?? ''),
				'required'       => ! empty($slot['required']),
				'source'         => (string) ($slot['source'] ?? ''),
				'source_label'   => $source_labels[ (string) ($slot['source'] ?? '') ] ?? (string) ($slot['source'] ?? ''),
				'attribute'      => $linked,
				'attribute_label'=> $linked && isset($labels[ $linked ]) ? $labels[ $linked ] : ($linked ? $linked : '—'),
				'display'        => (string) ($slot['display'] ?? 'cards'),
				'help'           => (string) ($slot['help'] ?? ''),
			);
		}

		return $rows;
	}

	/**
	 * Guarantee each slot has a stable unique key (duplicate keys collapse customer choices).
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ensure_unique_slot_keys($slots) {
		if (! is_array($slots)) {
			return array();
		}

		$used = array();
		$out  = array();

		foreach (array_values($slots) as $index => $slot) {
			if (! is_array($slot)) {
				continue;
			}

			$label = isset($slot['label']) ? trim((string) $slot['label']) : '';
			if ('' === $label) {
				continue;
			}

			$key = isset($slot['key']) ? sanitize_title((string) $slot['key']) : '';
			if ('' === $key) {
				$key = self::sanitize_slot_key($label);
			}

			$base = $key;
			$suffix = 2;
			while (isset($used[ $key ])) {
				$key = $base . '-' . $suffix;
				++$suffix;
			}

			$used[ $key ] = true;
			$slot['key']  = $key;
			$out[]        = $slot;
		}

		return $out;
	}

	/**
	 * Sanitize slot key.
	 *
	 * @param string $label Label.
	 * @return string
	 */
	public static function sanitize_slot_key($label) {
		$key = sanitize_title($label);
		return $key ? $key : 'slot';
	}

	/**
	 * Get global pools.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_pools() {
		$pools = get_option(self::OPTION_POOLS, array());
		return is_array($pools) ? $pools : array();
	}

	/**
	 * @param array<string, array<string, mixed>> $pools Pools.
	 * @return void
	 */
	public function save_pools($pools) {
		update_option(self::OPTION_POOLS, $pools, false);
	}

	/**
	 * @param string $message Log message.
	 * @param string $level   Level.
	 * @return void
	 */
	public function log($message, $level = 'info') {
		$logs = get_option(self::OPTION_LOGS, array());
		if (! is_array($logs)) {
			$logs = array();
		}
		array_unshift(
			$logs,
			array(
				'time'    => current_time('mysql'),
				'level'   => sanitize_key($level),
				'message' => sanitize_text_field($message),
				'user_id' => get_current_user_id(),
			)
		);
		$logs = array_slice($logs, 0, 100);
		update_option(self::OPTION_LOGS, $logs, false);
	}

	/**
	 * Resolve options for a slot.
	 *
	 * @param array<string, mixed> $slot Slot config.
	 * @param int                  $product_id Parent product ID.
	 * @return array<int, array{value: string, label: string, image: string, product_id: int}>
	 */
	public function resolve_slot_options($slot, $product_id = 0) {
		$slot    = $this->apply_global_slot_rules($slot, $product_id);
		$source  = isset($slot['source']) ? (string) $slot['source'] : 'manual';
		$options = array();

		switch ($source) {
			case 'global_pool':
				// Default behavior: use product variation attributes when available.
				// Global pools remain as fallback for legacy configurations.
				$product = wc_get_product(absint($product_id));
				if ($product instanceof WC_Product_Variable) {
					$matched_attr = $this->resolve_slot_variation_attribute_key($slot, $product);
					if ('' !== $matched_attr) {
						$options = $this->options_from_product_attribute(
							$product_id,
							$this->normalize_wc_attribute_key($matched_attr)
						);
						if (! empty($options)) {
							break;
						}
					}
				}
				$pool_id = isset($slot['pool_id']) ? (string) $slot['pool_id'] : '';
				$pools   = $this->get_pools();
				if ($pool_id && isset($pools[ $pool_id ]['options']) && is_array($pools[ $pool_id ]['options'])) {
					foreach ($pools[ $pool_id ]['options'] as $opt) {
						if (empty($opt['active']) && array_key_exists('active', (array) $opt)) {
							continue;
						}
						$options[] = $this->normalize_option_row($opt);
					}
				}
				break;

			case 'attribute':
				$attr = isset($slot['attribute']) ? (string) $slot['attribute'] : '';
				if ('' === trim($attr)) {
					$product = wc_get_product(absint($product_id));
					if ($product instanceof WC_Product_Variable) {
						$matched_attr = $this->resolve_slot_variation_attribute_key($slot, $product);
						if ('' !== $matched_attr) {
							$attr = $matched_attr;
						}
					}
				}
				$attr = $this->normalize_wc_attribute_key($attr);
				$options = $this->options_from_product_attribute($product_id, $attr);
				break;

			case 'category':
				$cat_id = isset($slot['category_id']) ? absint($slot['category_id']) : 0;
				$options = $this->options_from_products_query(
					array(
						'limit'    => 50,
						'category' => array($cat_id),
					)
				);
				break;

			case 'tag':
				$tag_id = isset($slot['tag_id']) ? absint($slot['tag_id']) : 0;
				$tag    = $tag_id ? get_term($tag_id, 'product_tag') : null;
				$slug   = $tag && ! is_wp_error($tag) ? $tag->slug : '';
				$options = $slug ? $this->options_from_products_query(array('limit' => 50, 'tag' => array($slug))) : array();
				break;

			case 'products':
				$ids = isset($slot['product_ids']) ? array_map('absint', (array) $slot['product_ids']) : array();
				foreach ($ids as $id) {
					$p = wc_get_product($id);
					if ($p && $p->is_purchasable() && $p->is_in_stock()) {
						$options[] = array(
							'value'      => (string) $id,
							'label'      => $p->get_name(),
							'image'      => wp_get_attachment_image_url(
								$p->get_image_id(),
								function_exists('mad_baits_get_product_card_image_size') ? mad_baits_get_product_card_image_size() : 'woocommerce_thumbnail'
							) ?: '',
							'product_id' => (int) $id,
						);
					}
				}
				break;

			case 'manual':
			default:
				$manual = isset($slot['manual_options']) && is_array($slot['manual_options']) ? $slot['manual_options'] : array();
				foreach ($manual as $row) {
					$options[] = $this->normalize_option_row($row);
				}
				break;
		}

		$options = array_values(array_filter($options, static function ($row) {
			return '' !== ($row['label'] ?? '');
		}));
		$options = $this->filter_options_by_product_variations($options, $slot, $product_id);

		/**
		 * Filter resolved slot options.
		 *
		 * @param array $options Options.
		 * @param array $slot Slot.
		 * @param int   $product_id Product ID.
		 */
		return (array) apply_filters('mbbb_slot_options', $options, $slot, $product_id);
	}

	/**
	 * Constrain resolved slot options to the product's active variation values.
	 *
	 * This keeps Bundle Builder in sync with WooCommerce Variations: if an option
	 * is removed from the matching variation attribute on a product, it will no
	 * longer render in this slot for that product.
	 *
	 * @param array<int, array{value: string, label: string, image: string, product_id: int}> $options
	 * @param array<string, mixed> $slot
	 * @param int                  $product_id
	 * @return array<int, array{value: string, label: string, image: string, product_id: int}>
	 */
	private function filter_options_by_product_variations($options, $slot, $product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || empty($options)) {
			return $options;
		}

		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product_Variable) {
			return $options;
		}

		$variation_attributes = (array) $product->get_variation_attributes();
		if (empty($variation_attributes)) {
			return $options;
		}

		$attribute_key = $this->resolve_slot_variation_attribute_key($slot, $product, $options);
		if ('' === $attribute_key || empty($variation_attributes[ $attribute_key ])) {
			$all_allowed = $this->build_allowed_lookup_from_variation_attributes($variation_attributes);
			if (empty($all_allowed)) {
				return $options;
			}
			$fallback_filtered = array_values(array_filter($options, function ($row) use ($all_allowed) {
				$value = $this->normalize_option_lookup((string) ($row['value'] ?? ''));
				$label = $this->normalize_option_lookup((string) ($row['label'] ?? ''));
				return isset($all_allowed[ $value ]) || isset($all_allowed[ $label ]);
			}));
			return ! empty($fallback_filtered) ? $fallback_filtered : $options;
		}

		$allowed = $this->build_allowed_lookup((array) $variation_attributes[ $attribute_key ]);
		if (empty($allowed)) {
			return $options;
		}

		$filtered = array_values(array_filter($options, function ($row) use ($allowed) {
			$value = $this->normalize_option_lookup((string) ($row['value'] ?? ''));
			$label = $this->normalize_option_lookup((string) ($row['label'] ?? ''));
			return isset($allowed[ $value ]) || isset($allowed[ $label ]);
		}));

		return ! empty($filtered) ? $filtered : $options;
	}

	/**
	 * Resolve best variation attribute key for a slot.
	 *
	 * Tries explicit slot attribute, then label/key matching, then overlap scoring
	 * against resolved option labels/values.
	 *
	 * @param array<string, mixed>                                     $slot
	 * @param WC_Product_Variable                                      $product
	 * @param array<int, array{value: string, label: string, image: string, product_id: int}> $options
	 * @return string
	 */
	private function resolve_slot_variation_attribute_key($slot, WC_Product_Variable $product, $options = array()) {
		$variation_attributes = (array) $product->get_variation_attributes();
		if (empty($variation_attributes)) {
			return '';
		}

		$explicit = isset($slot['attribute']) ? (string) $slot['attribute'] : '';
		if ('' !== trim($explicit)) {
			$explicit_match = $this->resolve_attribute_key_from_hint($variation_attributes, $explicit);
			if ('' !== $explicit_match) {
				return $explicit_match;
			}
		}

		$slot_label = isset($slot['label']) ? (string) $slot['label'] : '';
		$slot_key   = isset($slot['key']) ? (string) $slot['key'] : '';
		$name_match = $this->match_variation_attribute_key($variation_attributes, $slot_label, $slot_key);
		if ('' !== $name_match) {
			return $name_match;
		}

		if (empty($options)) {
			return '';
		}

		$best_key = '';
		$best_score = 0;
		foreach ($variation_attributes as $attr_key => $values) {
			$allowed = $this->build_allowed_lookup((array) $values);
			if (empty($allowed)) {
				continue;
			}
			$score = 0;
			foreach ($options as $row) {
				$value = $this->normalize_option_lookup((string) ($row['value'] ?? ''));
				$label = $this->normalize_option_lookup((string) ($row['label'] ?? ''));
				if (isset($allowed[ $value ]) || isset($allowed[ $label ])) {
					++$score;
				}
			}
			if ($score > $best_score) {
				$best_score = $score;
				$best_key   = (string) $attr_key;
			}
		}

		return $best_score > 0 ? $best_key : '';
	}

	/**
	 * Build normalized lookup map from variation values.
	 *
	 * @param array<int, string> $values
	 * @return array<string, bool>
	 */
	private function build_allowed_lookup($values) {
		$allowed = array();
		foreach ($values as $raw) {
			$normalized = $this->normalize_option_lookup((string) $raw);
			if ('' !== $normalized) {
				$allowed[ $normalized ] = true;
			}
		}
		return $allowed;
	}

	/**
	 * Build normalized lookup map across all variation attributes.
	 *
	 * @param array<string, mixed> $variation_attributes
	 * @return array<string, bool>
	 */
	private function build_allowed_lookup_from_variation_attributes($variation_attributes) {
		$allowed = array();
		foreach ($variation_attributes as $values) {
			foreach ($this->build_allowed_lookup((array) $values) as $normalized => $_is_allowed) {
				$allowed[ $normalized ] = true;
			}
		}
		return $allowed;
	}

	/**
	 * Resolve the variation attribute key that corresponds to a slot.
	 *
	 * @param array<string, mixed> $variation_attributes
	 * @param string               $slot_label
	 * @param string               $slot_key
	 * @return string
	 */
	private function match_variation_attribute_key($variation_attributes, $slot_label, $slot_key) {
		foreach (array($slot_label, $slot_key) as $hint) {
			$match = $this->resolve_attribute_key_from_hint($variation_attributes, (string) $hint);
			if ('' !== $match) {
				return $match;
			}
		}

		return '';
	}

	/**
	 * Resolve an attribute key from an arbitrary hint (label/slug/key).
	 *
	 * @param array<string, mixed> $variation_attributes
	 * @param string               $hint
	 * @return string
	 */
	private function resolve_attribute_key_from_hint($variation_attributes, $hint) {
		$needle = $this->normalize_option_lookup($hint);
		if ('' === $needle) {
			return '';
		}

		$fuzzy_match  = '';
		$fuzzy_length = 0;

		foreach (array_keys($variation_attributes) as $attr_key) {
			$attr_key  = (string) $attr_key;
			$clean_key = $this->normalize_wc_attribute_key($attr_key);
			$labels    = array(
				$attr_key,
				$clean_key,
				wc_attribute_label($clean_key),
				preg_replace('/^pa_/', '', $clean_key),
				str_replace('pa_', '', $clean_key),
			);

			foreach ($labels as $label) {
				$normalized = $this->normalize_option_lookup((string) $label);
				if ('' === $normalized) {
					continue;
				}

				if ($normalized === $needle) {
					return $attr_key;
				}

				$is_fuzzy = false !== strpos($normalized, $needle) || false !== strpos($needle, $normalized);
				if (! $is_fuzzy) {
					continue;
				}

				$overlap = min(strlen($normalized), strlen($needle));
				if ($overlap > $fuzzy_length) {
					$fuzzy_length = $overlap;
					$fuzzy_match  = $attr_key;
				}
			}
		}

		return $fuzzy_match;
	}

	/**
	 * Normalize values for robust option/attribute comparison.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_option_lookup($value) {
		$value = strtolower((string) $value);
		$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
		$value = str_replace(array('&amp;', '&'), 'and', $value);
		$value = preg_replace('/[^a-z0-9]+/', '', $value);
		return is_string($value) ? $value : '';
	}

	/**
	 * Normalize Woo attribute key to product attribute name format.
	 *
	 * Example: attribute_pa_boilie_size => pa_boilie_size
	 *
	 * @param string $attribute Raw attribute key.
	 * @return string
	 */
	private function normalize_wc_attribute_key($attribute) {
		$attribute = trim((string) $attribute);
		if ('' === $attribute) {
			return '';
		}
		if (0 === strpos($attribute, 'attribute_')) {
			$attribute = substr($attribute, 10);
		}
		return (string) $attribute;
	}

	/**
	 * @param array<string, mixed> $row Option row.
	 * @return array{value: string, label: string, image: string, product_id: int}
	 */
	public function normalize_option_row($row) {
		$label = isset($row['label']) ? (string) $row['label'] : '';
		$value = isset($row['value']) && '' !== (string) $row['value'] ? (string) $row['value'] : sanitize_title($label);
		$pid   = isset($row['product_id']) ? absint($row['product_id']) : 0;
		$image = isset($row['image']) ? (string) $row['image'] : '';

		if ($pid && ! $image) {
			$p = wc_get_product($pid);
			if ($p) {
				$image = wp_get_attachment_image_url(
					$p->get_image_id(),
					function_exists('mad_baits_get_product_card_image_size') ? mad_baits_get_product_card_image_size() : 'woocommerce_thumbnail'
				) ?: '';
				if ('' === $label) {
					$label = $p->get_name();
				}
			}
		}

		return array(
			'value'      => $value,
			'label'      => $label,
			'image'      => esc_url_raw($image),
			'product_id' => $pid,
		);
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $attribute  Attribute name or slug.
	 * @return array<int, array{value: string, label: string, image: string, product_id: int}>
	 */
	public function options_from_product_attribute($product_id, $attribute) {
		$product = wc_get_product($product_id);
		$attribute = $this->normalize_wc_attribute_key((string) $attribute);
		if (! $product || '' === $attribute) {
			return array();
		}

		$options = array();
		foreach ($product->get_attributes() as $attr) {
			if (! $attr instanceof WC_Product_Attribute) {
				continue;
			}
			$attr_name  = $this->normalize_wc_attribute_key((string) $attr->get_name());
			$attr_label = wc_attribute_label($attr_name);
			$attr_name_norm = $this->normalize_option_lookup($attr_name);
			$attr_label_norm = $this->normalize_option_lookup((string) $attr_label);
			$target_norm = $this->normalize_option_lookup($attribute);
			if (
				$attr_label !== $attribute
				&& $attr_name !== $attribute
				&& sanitize_title($attr_label) !== sanitize_title($attribute)
				&& sanitize_title($attr_name) !== sanitize_title($attribute)
				&& ('' === $target_norm || (
					$attr_name_norm !== $target_norm
					&& $attr_label_norm !== $target_norm
					&& false === strpos($attr_name_norm, $target_norm)
					&& false === strpos($target_norm, $attr_name_norm)
					&& false === strpos($attr_label_norm, $target_norm)
					&& false === strpos($target_norm, $attr_label_norm)
				))
			) {
				continue;
			}
			if ($attr->is_taxonomy()) {
				$terms = wc_get_product_terms($product_id, (string) $attr->get_name(), array('fields' => 'all'));
				foreach ($terms as $term) {
					if ($term instanceof WP_Term) {
						$options[] = array(
							'value'      => $term->slug,
							'label'      => $term->name,
							'image'      => '',
							'product_id' => 0,
						);
					}
				}
			} else {
				foreach ($attr->get_options() as $opt) {
					$options[] = array(
						'value'      => sanitize_title((string) $opt),
						'label'      => (string) $opt,
						'image'      => '',
						'product_id' => 0,
					);
				}
			}
		}

		return $options;
	}

	/**
	 * @param array<string, mixed> $args WC product query args.
	 * @return array<int, array{value: string, label: string, image: string, product_id: int}>
	 */
	public function options_from_products_query($args) {
		if (! function_exists('wc_get_products')) {
			return array();
		}

		$defaults = array(
			'status'  => 'publish',
			'limit'   => 50,
			'return'  => 'ids',
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		$merged = array_merge($defaults, $args);
		if (is_admin() && function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$ids = mad_baits_supplier_wc_get_product_ids($merged);
		} else {
			$ids = wc_get_products($merged);
		}
		$out = array();
		foreach ((array) $ids as $id) {
			$p = wc_get_product((int) $id);
			if (! $p) {
				continue;
			}
			$out[] = array(
				'value'      => (string) $id,
				'label'      => $p->get_name(),
				'image'      => wp_get_attachment_image_url(
					$p->get_image_id(),
					function_exists('mad_baits_get_product_card_image_size') ? mad_baits_get_product_card_image_size() : 'woocommerce_thumbnail'
				) ?: '',
				'product_id' => (int) $id,
			);
		}
		return $out;
	}

	/**
	 * Validate customer choices against product config.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     Posted choices slot_key => value.
	 * @return true|WP_Error
	 */
	/**
	 * Map posted bundle slot values to WooCommerce variation attribute slugs.
	 *
	 * Used when adding variable bundle products to the cart so the internal variation
	 * row matches customer selections (not the product default).
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     slot_key => value.
	 * @return array<string, string> pa_* => slug.
	 */
	public function map_posted_choices_to_variation_attributes($product_id, $posted) {
		$product_id = absint($product_id);
		if ($product_id < 1 || empty($posted) || ! is_array($posted)) {
			return array();
		}

		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product_Variable) {
			return array();
		}

		$variation_attributes = (array) $product->get_variation_attributes();
		if (empty($variation_attributes)) {
			return array();
		}

		$map = array();
		foreach ($this->get_resolved_slots($product_id) as $slot) {
			if (! is_array($slot)) {
				continue;
			}

			$slot_key = isset($slot['key']) ? (string) $slot['key'] : '';
			if ('' === $slot_key) {
				continue;
			}

			$raw = $this->get_posted_slot_value($posted, $slot_key, '');
			if ('' === $raw || (is_array($raw) && empty($raw))) {
				continue;
			}

			$value_raw = is_array($raw) ? (string) reset($raw) : (string) $raw;
			$value_raw = trim($value_raw);
			if ('' === $value_raw) {
				continue;
			}

			$attribute_key = isset($slot['attribute']) ? trim((string) $slot['attribute']) : '';
			if ('' === $attribute_key) {
				$attribute_key = $this->resolve_slot_variation_attribute_key($slot, $product, array());
			}
			if ('' === $attribute_key) {
				$slot_label = isset($slot['label']) ? (string) $slot['label'] : '';
				$attribute_key = $this->match_variation_attribute_key($variation_attributes, $slot_label, $slot_key);
			}
			if ('' === $attribute_key) {
				continue;
			}

			$normalized_attribute_key = $this->normalize_wc_attribute_key($attribute_key);
			$value = $value_raw;
			$attribute_options = array();
			if (isset($variation_attributes[ $normalized_attribute_key ]) && is_array($variation_attributes[ $normalized_attribute_key ])) {
				$attribute_options = $variation_attributes[ $normalized_attribute_key ];
			} else {
				$prefixed_key = 'attribute_' . $normalized_attribute_key;
				if (isset($variation_attributes[ $prefixed_key ]) && is_array($variation_attributes[ $prefixed_key ])) {
					$attribute_options = $variation_attributes[ $prefixed_key ];
				}
			}

			if (! empty($attribute_options)) {
				$target_slug = sanitize_title($value_raw);
				$target_norm = $this->normalize_lookup_key($value_raw);
				foreach ($attribute_options as $candidate) {
					$candidate = (string) $candidate;
					if ('' === $candidate) {
						continue;
					}
					if (
						$candidate === $value_raw
						|| sanitize_title($candidate) === $target_slug
						|| $this->normalize_lookup_key($candidate) === $target_norm
					) {
						$value = $candidate;
						break;
					}
				}
			}

			$map[ $normalized_attribute_key ] = $value;
		}

		return array_filter($map);
	}

	/**
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     Posted choices slot_key => value.
	 * @return true|WP_Error
	 */
	public function validate_choices($product_id, $posted) {
		$slots = $this->get_resolved_slots($product_id);
		if (empty($slots)) {
			return new WP_Error('mbbb_no_slots', __('This bundle is not configured yet.', 'mad-baits-bundle-builder'));
		}

		foreach ($slots as $slot) {
			$key      = isset($slot['key']) ? (string) $slot['key'] : '';
			$label    = isset($slot['label']) ? (string) $slot['label'] : $key;
			$required = ! empty($slot['required']);
			$min      = isset($slot['min']) ? max(0, (int) $slot['min']) : ($required ? 1 : 0);
			$max      = isset($slot['max']) ? max(1, (int) $slot['max']) : 1;
			if ('' === $key) {
				continue;
			}

			$raw = $this->get_posted_slot_value($posted, $key, '');
			$selected = array();
			if (is_array($raw)) {
				$selected = array_map('sanitize_text_field', $raw);
			} elseif ('' !== (string) $raw) {
				$selected = array(sanitize_text_field((string) $raw));
			}

			$selected = array_values(array_filter($selected, static function ($v) {
				return '' !== $v;
			}));

			if (count($selected) < $min) {
				return new WP_Error(
					'mbbb_required',
					sprintf(
						/* translators: %s: slot label */
						__('Please choose an option for %s.', 'mad-baits-bundle-builder'),
						$label
					)
				);
			}

			if (count($selected) > $max) {
				return new WP_Error(
					'mbbb_max',
					sprintf(
						/* translators: 1: slot label 2: max */
						__('Too many selections for %1$s (max %2$d).', 'mad-baits-bundle-builder'),
						$label,
						$max
					)
				);
			}

			$allowed = $this->resolve_slot_options($slot, $product_id);
			$allowed_values = array_map(static function ($row) {
				return (string) $row['value'];
			}, $allowed);

			foreach ($selected as $value) {
				if (! in_array($value, $allowed_values, true)) {
					return new WP_Error(
						'mbbb_invalid',
						sprintf(
							/* translators: %s: slot label */
							__('Invalid selection for %s.', 'mad-baits-bundle-builder'),
							$label
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Build readable labels for choices.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     slot_key => value(s).
	 * @return array<string, string> slot_label => display label.
	 */
	public function format_choices_for_display($product_id, $posted) {
		$rows = $this->format_choices_rows($product_id, $posted);
		$out  = array();

		foreach ($rows as $row) {
			$label = (string) $row['label'];
			$value = (string) $row['value'];
			if ('' === $label || '' === $value) {
				continue;
			}

			if (! array_key_exists($label, $out)) {
				$out[ $label ] = $value;
				continue;
			}

			// Duplicate slot labels must not collapse earlier boilie split choices.
			$disambiguated = sprintf(
				/* translators: 1: slot label, 2: slot key */
				__('%1$s (%2$s)', 'mad-baits-bundle-builder'),
				$label,
				(string) $row['key']
			);
			$out[ $disambiguated ] = $value;
		}

		return $out;
	}

	/**
	 * Ordered slot rows for cart/order display (one row per configured slot).
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     slot_key => value(s).
	 * @return array<int, array{key: string, label: string, value: string}>
	 */
	public function format_choices_rows($product_id, $posted) {
		$slots = $this->get_resolved_slots($product_id);
		$rows  = array();

		foreach ($slots as $slot) {
			$key   = isset($slot['key']) ? (string) $slot['key'] : '';
			$label = isset($slot['label']) ? (string) $slot['label'] : $key;
			if ('' === $key) {
				continue;
			}

			$raw = $this->get_posted_slot_value($posted, $key, null);
			if (null === $raw) {
				continue;
			}

			$values  = is_array($raw) ? $raw : array($raw);
			$allowed = $this->resolve_slot_options($slot, $product_id);
			$map     = array();
			foreach ($allowed as $row) {
				$map[ (string) $row['value'] ] = (string) $row['label'];
			}

			$labels = array();
			foreach ($values as $v) {
				$v = (string) $v;
				$labels[] = isset($map[ $v ]) ? $map[ $v ] : $v;
			}

			$value = trim(implode(', ', array_filter($labels, static function ($entry) {
				return '' !== (string) $entry;
			})));
			if ('' === $value) {
				continue;
			}

			$rows[] = array(
				'key'   => $key,
				'label' => $label,
				'value' => $value,
			);
		}

		return $rows;
	}

	/**
	 * Extract canonical posted choices keyed by configured slot keys only.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $posted     Raw posted values.
	 * @return array<string, string|array>
	 */
	public function canonicalize_posted_choices($product_id, $posted) {
		$out = array();
		foreach ($this->get_resolved_slots($product_id) as $slot) {
			$key = isset($slot['key']) ? (string) $slot['key'] : '';
			if ('' === $key) {
				continue;
			}

			$raw = $this->get_posted_slot_value($posted, $key, null);
			if (null === $raw || (is_array($raw) && empty($raw)) || '' === (string) $raw) {
				continue;
			}

			$out[ $key ] = $raw;
		}

		return $out;
	}

	/**
	 * Resolve a posted choice value for a slot key across common key normalizations.
	 *
	 * @param array<string, mixed> $posted   Posted choices.
	 * @param string               $slot_key Slot key from configuration.
	 * @param mixed                $default  Fallback when not found.
	 * @return mixed
	 */
	public function get_posted_slot_value($posted, $slot_key, $default = null) {
		if (array_key_exists($slot_key, $posted)) {
			return $posted[ $slot_key ];
		}

		$candidates = array(
			sanitize_key($slot_key),
			sanitize_title($slot_key),
			str_replace(' ', '_', $slot_key),
			str_replace(array(' ', '-', '.'), '_', $slot_key),
		);

		foreach ($candidates as $candidate) {
			if ('' !== $candidate && array_key_exists($candidate, $posted)) {
				return $posted[ $candidate ];
			}
		}

		$needle = $this->normalize_lookup_key($slot_key);
		if ('' === $needle) {
			return $default;
		}

		foreach ($posted as $posted_key => $posted_value) {
			if ($needle === $this->normalize_lookup_key((string) $posted_key)) {
				return $posted_value;
			}
		}

		return $default;
	}

	/**
	 * Build a canonical key for resilient slot lookup.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private function normalize_lookup_key($key) {
		$key = strtolower((string) $key);
		$key = preg_replace('/[^a-z0-9]+/', '', $key);
		return is_string($key) ? $key : '';
	}
}
