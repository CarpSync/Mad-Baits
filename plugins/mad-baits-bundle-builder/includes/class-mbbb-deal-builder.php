<?php
/**
 * Shop-manager friendly deal form ↔ existing bundle slot translation.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Builds and reads bundle deals without exposing slot internals to editors.
 */
final class MBBB_Deal_Builder {

	const META_DEAL = '_mbbb_deal_meta';

	/**
	 * Deal template groups for the admin UI.
	 *
	 * @return array<string, array{label: string, description: string, preset_id: string, bundle_size: string, deal_type: string}>
	 */
	public static function get_deal_templates() {
		$presets = MBBB_Presets::get_presets();

		$map = array(
			'1kg-boilie-deal'              => array('bundle_size' => '1kg', 'deal_type' => 'quantity_bundle'),
			'nutz-banana-5kg-deal'         => array('bundle_size' => '5kg', 'deal_type' => 'mix_and_match'),
			'10kg-boilie-deal'             => array('bundle_size' => '10kg', 'deal_type' => 'mix_and_match'),
			'nutz-banana-10kg-deal'        => array('bundle_size' => '10kg', 'deal_type' => 'mix_and_match'),
			'20kg-boilie-deal'             => array('bundle_size' => '20kg', 'deal_type' => 'mix_and_match'),
			'25kg-boilie-pellet-deal'      => array('bundle_size' => '25kg', 'deal_type' => 'mix_and_match'),
			'30kg-boilie-deal'             => array('bundle_size' => '30kg', 'deal_type' => 'mix_and_match'),
			'50kg-ultimate-trip'           => array('bundle_size' => '50kg', 'deal_type' => 'mix_and_match'),
			'weekend-session-pack'         => array('bundle_size' => '', 'deal_type' => 'fixed_bundle'),
			'nutz-banana-session-bucket'   => array('bundle_size' => '', 'deal_type' => 'fixed_bundle'),
			'pandemic-barbel-pack'         => array('bundle_size' => '', 'deal_type' => 'fixed_bundle'),
		);

		$templates = array();
		foreach ($map as $preset_id => $meta) {
			if (! isset($presets[ $preset_id ])) {
				continue;
			}
			$templates[ $preset_id ] = array_merge(
				$meta,
				array(
					'label'       => $presets[ $preset_id ]['label'],
					'description' => self::template_description($preset_id, $presets[ $preset_id ]['slots']),
					'preset_id'   => $preset_id,
				)
			);
		}

		return $templates;
	}

	/**
	 * Human-readable deal type labels.
	 *
	 * @return array<string, string>
	 */
	public static function deal_type_labels() {
		return array(
			'mix_and_match'   => __('Mix & match — customer chooses products', 'mad-baits-bundle-builder'),
			'quantity_bundle' => __('Quantity bundle — fixed split sizes', 'mad-baits-bundle-builder'),
			'fixed_bundle'    => __('Fixed bundle — preset product mix', 'mad-baits-bundle-builder'),
		);
	}

	/**
	 * Boilie range names available for filtering customer choices.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function get_boilie_ranges() {
		$out = array();
		if (function_exists('mad_baits_catalogue_get_ranges')) {
			foreach (mad_baits_catalogue_get_ranges() as $def) {
				$slug  = sanitize_title((string) ($def['slug'] ?? ''));
				$label = (string) ($def['label'] ?? $slug);
				if ('' !== $slug && self::is_deal_boilie_range_slug($slug)) {
					$out[ $slug ] = $label;
				}
			}
		}
		if (empty($out)) {
			$out = array(
				'asbo'         => 'ASBO',
				'p-fish-2'     => 'P-Fish',
				'pandemic'     => 'Pandemic',
				'nutz-plus'    => 'Nutz Plus',
				'wicked-white' => 'Wicked Whites',
				'nutz-banana'  => 'Nutz Banana',
			);
		}
		if (class_exists('MBBB_Pool_Options', false)) {
			foreach (MBBB_Pool_Options::get_registered_ranges() as $slug => $label) {
				$out[ $slug ] = $label;
			}
			foreach (MBBB_Pool_Options::discover_ranges_from_pools() as $slug => $label) {
				if (! isset($out[ $slug ])) {
					$out[ $slug ] = $label;
				}
			}
		}
		ksort($out);
		return $out;
	}

	/**
	 * Range slugs offered on the deal builder boilie picker.
	 *
	 * @param string $slug Range slug.
	 * @return bool
	 */
	private static function is_deal_boilie_range_slug($slug) {
		$slug = sanitize_title((string) $slug);
		if (in_array(
			$slug,
			array('asbo', 'p-fish-2', 'pandemic', 'nutz-plus', 'nutz-banana', 'wicked-white'),
			true
		)) {
			return true;
		}
		if (class_exists('MBBB_Pool_Options', false)) {
			return MBBB_Pool_Options::is_known_range_slug($slug);
		}
		return false;
	}

	/**
	 * @return int[]
	 */
	public static function get_all_deal_product_ids() {
		return self::collect_deal_product_ids();
	}

	/**
	 * Search aliases used to match pool option labels to stable range slugs.
	 *
	 * @return array<string, string[]> slug => aliases
	 */
	public static function boilie_range_match_aliases() {
		$aliases = array(
			'asbo'         => array('asbo'),
			'p-fish-2'     => array('p-fish', 'p fish', 'pfish'),
			'pandemic'     => array('pandemic'),
			'nutz-plus'    => array('nutz plus', 'nutzplus'),
			'nutz-banana'  => array('nutz banana', 'nutzbanana'),
			'wicked-white' => array('wicked white', 'wicked whites', 'wickedwhite'),
		);

		if (function_exists('mad_baits_catalogue_get_ranges')) {
			foreach (mad_baits_catalogue_get_ranges() as $def) {
				$slug  = sanitize_title((string) ($def['slug'] ?? ''));
				$label = strtolower((string) ($def['label'] ?? ''));
				if ('' === $slug || ! self::is_deal_boilie_range_slug($slug)) {
					continue;
				}
				if (! isset($aliases[ $slug ])) {
					$aliases[ $slug ] = array();
				}
				$aliases[ $slug ][] = $slug;
				if ('' !== $label) {
					$aliases[ $slug ][] = $label;
				}
			}
		}

		foreach ($aliases as $slug => $values) {
			$aliases[ $slug ] = array_values(array_unique(array_filter(array_map('strtolower', $values))));
		}

		return $aliases;
	}

	/**
	 * Default empty deal form values.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_form() {
		return array(
			'product_id'              => 0,
			'name'                    => '',
			'admin_description'       => '',
			'deal_active'             => 'no',
			'status'                  => 'draft',
			'enabled'                 => 'no',
			'build_mode'              => 'template',
			'template_id'             => '',
			'deal_type'               => 'mix_and_match',
			'bundle_size'             => '',
			'price'                   => '',
			'sale_price'              => '',
			'image_id'                => 0,
			'display_order'           => 0,
			'free_shipping'           => 'yes',
			'badge_label'             => '',
			'boilie_ranges'           => array_keys(self::get_boilie_ranges()),
			'include_hookbaits'       => 'yes',
			'include_liquids'         => 'yes',
			'include_dips'            => 'yes',
			'include_pellets'         => 'yes',
			'hookbait_count'          => 0,
			'liquid_count'            => 0,
			'dip_count'               => 0,
			'boilie_split_count'      => 0,
			'custom_boilie_choices'   => 0,
			'custom_hookbait_choices' => 0,
			'custom_liquid_choices'   => 0,
			'custom_dip_choices'      => 0,
			'custom_pellet_choices'   => 0,
			'hookbait_options'        => self::get_default_pool_values('standard-hookbait-choices'),
			'liquid_options'          => self::get_default_pool_values('standard-500ml-liquid-choices'),
			'dip_options'             => self::get_default_pool_values('standard-250ml-dip-choices'),
			'pellet_options'          => self::get_default_pool_values('standard-pellet-choices'),
			'included_products'       => array(),
			'customer_summary'        => '',
		);
	}

	/**
	 * Load deal form from an existing product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function form_from_product($product_id) {
		$product_id = absint($product_id);
		$form       = self::default_form();
		if ($product_id < 1) {
			return $form;
		}

		$product = wc_get_product($product_id);
		if (! $product) {
			return $form;
		}

		$plugin = MBBB_Plugin::instance();
		$meta   = get_post_meta($product_id, self::META_DEAL, true);
		$meta   = is_array($meta) ? $meta : array();
		$slots  = $plugin->get_slots($product_id);

		$build_mode  = isset($meta['build_mode']) ? sanitize_key((string) $meta['build_mode']) : '';
		$template_id = isset($meta['template_id']) ? sanitize_key((string) $meta['template_id']) : '';
		if ('custom' === $build_mode || 'custom' === $template_id) {
			$build_mode  = 'custom';
			$template_id = 'custom';
		} elseif ('' === $template_id) {
			$template_id = MBBB_Presets::detect_preset_from_title($product->get_name());
		}

		$templates = self::get_deal_templates();
		$template  = isset($templates[ $template_id ]) ? $templates[ $template_id ] : null;

		$form['product_id']        = $product_id;
		$form['build_mode']        = $build_mode ?: 'template';
		$form['name']              = $product->get_name();
		$form['admin_description'] = isset($meta['admin_description']) ? (string) $meta['admin_description'] : '';
		$form['status']            = $product->get_status();
		$form['enabled']           = $plugin->is_enabled($product_id) ? 'yes' : 'no';
		$form['deal_active']       = ('publish' === $product->get_status() && $plugin->is_enabled($product_id)) ? 'yes' : 'no';
		$form['template_id']       = $template_id;
		$form['deal_type']         = isset($meta['deal_type']) ? sanitize_key((string) $meta['deal_type']) : ($template['deal_type'] ?? 'mix_and_match');
		$form['bundle_size']       = isset($meta['bundle_size']) ? (string) $meta['bundle_size'] : ($template['bundle_size'] ?? '');
		$form['price']             = self::get_product_bundle_price($product);
		$form['sale_price']        = (string) $product->get_sale_price();
		$form['image_id']          = (int) $product->get_image_id();
		$form['display_order']     = isset($meta['display_order']) ? absint($meta['display_order']) : 0;
		$form['free_shipping']     = isset($meta['free_shipping']) ? MBBB_Plugin::sanitize_yes_no($meta['free_shipping']) : 'yes';
		$form['badge_label']       = isset($meta['badge_label']) ? sanitize_text_field((string) $meta['badge_label']) : '';
		$form['boilie_ranges']     = self::merge_boilie_range_selection(
			isset($meta['boilie_ranges']) && is_array($meta['boilie_ranges'])
				? array_map('sanitize_key', $meta['boilie_ranges'])
				: array()
		);

		$slot_stats = self::analyze_slots($slots);
		$form['include_hookbaits']  = $slot_stats['hookbaits'] > 0 ? 'yes' : 'no';
		$form['include_liquids']    = $slot_stats['liquids'] > 0 ? 'yes' : 'no';
		$form['include_dips']       = $slot_stats['dips'] > 0 ? 'yes' : 'no';
		$form['include_pellets']    = $slot_stats['pellets'] > 0 ? 'yes' : 'no';
		$form['hookbait_count']     = $slot_stats['hookbaits'];
		$form['liquid_count']       = $slot_stats['liquids'];
		$form['dip_count']          = $slot_stats['dips'];
		$form['boilie_split_count'] = $slot_stats['boilie_splits'];
		$form['customer_summary']   = self::build_customer_summary($slots, $template);

		if ('custom' === $form['build_mode']) {
			$custom_choices = isset($meta['custom_choices']) && is_array($meta['custom_choices']) ? $meta['custom_choices'] : array();
			$form['custom_boilie_choices']   = absint($custom_choices['boilie'] ?? $slot_stats['boilie_splits']);
			$form['custom_hookbait_choices'] = absint($custom_choices['hookbait'] ?? $slot_stats['hookbaits']);
			$form['custom_liquid_choices']   = absint($custom_choices['liquid'] ?? $slot_stats['liquids']);
			$form['custom_dip_choices']      = absint($custom_choices['dip'] ?? $slot_stats['dips']);
			$form['custom_pellet_choices']   = absint($custom_choices['pellet'] ?? $slot_stats['pellets']);

			$filters = isset($meta['option_filters']) && is_array($meta['option_filters']) ? $meta['option_filters'] : array();
			$form['hookbait_options'] = self::merge_pool_option_selection(
				'standard-hookbait-choices',
				isset($filters['hookbaits']) && is_array($filters['hookbaits'])
					? array_map('sanitize_text_field', $filters['hookbaits'])
					: array()
			);
			$form['liquid_options'] = self::merge_pool_option_selection(
				'standard-500ml-liquid-choices',
				isset($filters['liquids']) && is_array($filters['liquids'])
					? array_map('sanitize_text_field', $filters['liquids'])
					: array()
			);
			$form['dip_options'] = self::merge_pool_option_selection(
				'standard-250ml-dip-choices',
				isset($filters['dips']) && is_array($filters['dips'])
					? array_map('sanitize_text_field', $filters['dips'])
					: array()
			);
			$form['pellet_options'] = self::merge_pool_option_selection(
				'standard-pellet-choices',
				isset($filters['pellets']) && is_array($filters['pellets'])
					? array_map('sanitize_text_field', $filters['pellets'])
					: array()
			);
			$form['included_products'] = isset($meta['included_products']) && is_array($meta['included_products'])
				? self::normalize_included_products($meta['included_products'])
				: array();
			$form['customer_summary'] = self::build_custom_customer_summary($form);
		}

		return $form;
	}

	/**
	 * Save deal from posted form data.
	 *
	 * @param array<string, mixed> $posted Posted form.
	 * @return array{success: bool, product_id: int, errors: array<int, string>}
	 */
	public static function save_deal(array $posted) {
		$product_id = absint($posted['product_id'] ?? 0);
		$is_new     = $product_id < 1;
		$errors     = self::validate_form_input($posted);

		if (! empty($errors)) {
			return array(
				'success'    => false,
				'product_id' => $product_id,
				'errors'     => $errors,
			);
		}

		$build_mode     = sanitize_key((string) ($posted['build_mode'] ?? 'template'));
		$is_custom      = ('custom' === $build_mode);
		$template_id    = $is_custom ? 'custom' : sanitize_key((string) ($posted['template_id'] ?? ''));
		$plugin         = MBBB_Plugin::instance();
		$existing_meta  = $product_id > 0 ? get_post_meta($product_id, self::META_DEAL, true) : array();
		$existing_meta  = is_array($existing_meta) ? $existing_meta : array();
		$existing_slots = $product_id > 0 ? $plugin->get_slots($product_id) : array();
		$custom_hash    = $is_custom ? self::custom_config_hash($posted) : '';
		$existing_hash  = isset($existing_meta['custom_config_hash']) ? (string) $existing_meta['custom_config_hash'] : '';
		$deal_active    = self::is_deal_active_from_post($posted);
		$target_status  = $deal_active ? 'publish' : 'draft';
		$target_enabled = $deal_active ? 'yes' : 'no';
		$name           = sanitize_text_field((string) ($posted['name'] ?? ''));
		$price          = isset($posted['price']) ? wc_format_decimal(wp_unslash((string) $posted['price'])) : '';

		if ($is_custom) {
			$template_changed = $product_id < 1
				|| 'custom' !== sanitize_key((string) ($existing_meta['build_mode'] ?? ''))
				|| $custom_hash !== $existing_hash
				|| ! empty($posted['update_slots']);
		} else {
			$template_changed = $product_id < 1
				|| (isset($existing_meta['template_id']) && sanitize_key((string) $existing_meta['template_id']) !== $template_id)
				|| ! empty($posted['update_slots']);
		}

		$slots = ($template_changed || empty($existing_slots))
			? self::build_slots_from_form($posted)
			: $existing_slots;

		if ($is_new) {
			$product_id = $is_custom
				? self::create_custom_deal_product_shell($name, $price, 'draft')
				: self::create_deal_product_shell($template_id, $name, $price, 'draft');
			if ($product_id < 1) {
				return array(
					'success'    => false,
					'product_id' => 0,
					'errors'     => array(
						$is_custom
							? __('Could not create the deal product. Please try again or contact support if the problem continues.', 'mad-baits-bundle-builder')
							: __('Could not create the deal product. Choose a template with an existing reference deal, or ask support to configure the first deal of this type.', 'mad-baits-bundle-builder'),
					),
				);
			}
			$product = wc_get_product($product_id);
		} else {
			$product = wc_get_product($product_id);
			if (! $product) {
				return array(
					'success'    => false,
					'product_id' => 0,
					'errors'     => array(__('Deal product not found.', 'mad-baits-bundle-builder')),
				);
			}
			$product->set_name($name);
			$product->set_catalog_visibility('visible');
			$product->set_sold_individually(false);
			if ('' !== $price) {
				$product->set_regular_price($price);
			}
			$sale_price = isset($posted['sale_price']) ? wc_format_decimal(wp_unslash((string) $posted['sale_price'])) : '';
			if ('' !== $sale_price) {
				$product->set_sale_price($sale_price);
			} else {
				$product->set_sale_price('');
			}
			$image_id = absint($posted['image_id'] ?? 0);
			if ($image_id > 0) {
				$product->set_image_id($image_id);
			}
			$product_id = (int) $product->save();
		}

		if ($product_id < 1) {
			return array(
				'success'    => false,
				'product_id' => 0,
				'errors'     => array(__('Could not save the deal product.', 'mad-baits-bundle-builder')),
			);
		}

		$settings = MBBB_Plugin::default_settings();
		if ($template_changed || empty($existing_slots)) {
			$slots = self::materialize_slots_for_product($slots, $posted);
			$slots = self::enrich_slots_with_product_ids($slots);
			if ($is_custom) {
				self::configure_variable_product_for_custom_slots($product_id, $slots, $price);
			}
			$slots = $plugin->migrate_slots_attribute_links($slots, $product_id);
		}

		if ($deal_active) {
			$config_errors = self::validate_materialized_deal($posted, $product_id, $slots);
			if (! empty($config_errors)) {
				return self::finalize_failed_deal_save($product_id, $posted, $slots, $config_errors, $name, $is_new);
			}
		}

		$product = wc_get_product($product_id);
		if ($product) {
			$product->set_status($target_status);
			if ('' !== $price) {
				$product->set_regular_price($price);
			}
			if ($is_new) {
				$image_id = absint($posted['image_id'] ?? 0);
				if ($image_id > 0) {
					$product->set_image_id($image_id);
				}
			}
			$product->save();
			self::sync_bundle_product_price($product_id, $price);
		}

		if ($template_changed || empty($existing_slots)) {
			$plugin->save_product_bundle_meta($product_id, $target_enabled, $settings, $slots);
		} else {
			$plugin->save_product_bundle_meta($product_id, $target_enabled, $plugin->get_settings($product_id), null);
		}

		self::persist_deal_meta($product_id, $posted, $build_mode, $template_id, $is_custom, $custom_hash);

		MBBB_Admin_Setup::log(
			sprintf(
				/* translators: 1: product ID 2: deal name */
				$is_new ? __('Created bundle deal #%1$d (%2$s).', 'mad-baits-bundle-builder') : __('Updated bundle deal #%1$d (%2$s).', 'mad-baits-bundle-builder'),
				$product_id,
				$name
			)
		);

		return array(
			'success'    => true,
			'product_id' => $product_id,
			'errors'     => array(),
		);
	}

	/**
	 * Validate posted deal form (input checks only — full config validation runs after generation).
	 *
	 * @param array<string, mixed> $posted Posted data.
	 * @return array<int, string>
	 */
	public static function validate_form(array $posted) {
		return self::validate_form_input($posted);
	}

	/**
	 * Validate form input before product generation.
	 *
	 * @param array<string, mixed> $posted Posted data.
	 * @return array<int, string>
	 */
	public static function validate_form_input(array $posted) {
		$errors      = array();
		$name        = trim((string) ($posted['name'] ?? ''));
		$build_mode  = sanitize_key((string) ($posted['build_mode'] ?? 'template'));
		$is_custom   = ('custom' === $build_mode);
		$deal_active = self::is_deal_active_from_post($posted);

		if ('' === $name) {
			$errors[] = __('Please enter a deal name.', 'mad-baits-bundle-builder');
		}

		if ($is_custom) {
			$counts   = self::parse_custom_choice_counts($posted);
			$included = self::parse_included_products_from_post($posted);
			$total    = array_sum($counts);
			if ($total < 1 && empty($included)) {
				$errors[] = __('Add at least one customer choice or an automatically included product.', 'mad-baits-bundle-builder');
			}
			if ($counts['boilie'] > 0) {
				$ranges = isset($posted['boilie_ranges']) && is_array($posted['boilie_ranges']) ? $posted['boilie_ranges'] : array();
				if (empty($ranges)) {
					$errors[] = __('Select at least one boilie range for customers to choose from.', 'mad-baits-bundle-builder');
				}
			}
			if ($deal_active) {
				$errors = array_merge($errors, self::validate_custom_option_availability($posted, $counts));
			}
		} else {
			$template_id = sanitize_key((string) ($posted['template_id'] ?? ''));
			if ('' === $template_id || ! isset(self::get_deal_templates()[ $template_id ])) {
				$errors[] = __('Please choose an existing deal layout.', 'mad-baits-bundle-builder');
			}
		}

		$price = trim((string) ($posted['price'] ?? ''));
		if (('' === $price || ! is_numeric($price)) && absint($posted['product_id'] ?? 0) > 0) {
			$existing = wc_get_product(absint($posted['product_id']));
			if ($existing) {
				$price = self::get_product_bundle_price($existing);
			}
		}
		if ('' === $price || ! is_numeric($price) || (float) $price <= 0) {
			$errors[] = __('Please enter a valid bundle price.', 'mad-baits-bundle-builder');
		}

		if (! $is_custom) {
			$template_id = sanitize_key((string) ($posted['template_id'] ?? ''));
			if ($deal_active) {
				$ranges = isset($posted['boilie_ranges']) && is_array($posted['boilie_ranges']) ? $posted['boilie_ranges'] : array();
				if (empty($ranges) && self::template_has_boilie_slots($template_id)) {
					$errors[] = __('Select at least one boilie range for customers to choose from.', 'mad-baits-bundle-builder');
				}
			}
		}

		return array_values(array_unique($errors));
	}

	/**
	 * Validate a fully materialised deal against the generated product configuration.
	 *
	 * @param array<string, mixed>             $posted     Posted form.
	 * @param int                                $product_id Product ID.
	 * @param array<int, array<string, mixed>>   $slots      Materialised slots.
	 * @return array<int, string>
	 */
	public static function validate_materialized_deal(array $posted, $product_id, array $slots) {
		$errors = array();
		if (empty($slots) && empty(self::parse_included_products_from_post($posted))) {
			$errors[] = __('This deal has no customer choices configured.', 'mad-baits-bundle-builder');
			return $errors;
		}

		if (! empty($slots)) {
			$config_errors = MBBB_Plugin::instance()->validate_enabled_bundle_config('yes', $slots, absint($product_id));
			foreach ($config_errors as $message) {
				$errors[] = self::plain_english_config_error((string) $message);
			}
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @return bool
	 */
	private static function is_deal_active_from_post(array $posted) {
		return ! empty($posted['deal_active']) || (
			! isset($posted['deal_active']) && ! empty($posted['enabled']) && 'yes' === MBBB_Plugin::sanitize_yes_no($posted['enabled'])
			&& ! empty($posted['status']) && 'publish' === sanitize_key((string) $posted['status'])
		);
	}

	/**
	 * Keep a failed save safely disabled while preserving generated configuration for retry.
	 *
	 * @param int                                $product_id Product ID.
	 * @param array<string, mixed>               $posted     Posted form.
	 * @param array<int, array<string, mixed>>   $slots      Materialised slots.
	 * @param array<int, string>                 $errors     Validation errors.
	 * @param string                             $name       Deal name.
	 * @param bool                               $is_new     Whether this was a new deal.
	 * @return array{success: bool, product_id: int, errors: array<int, string>}
	 */
	private static function finalize_failed_deal_save($product_id, array $posted, array $slots, array $errors, $name, $is_new) {
		unset($name, $is_new);
		$product_id = absint($product_id);
		$plugin     = MBBB_Plugin::instance();
		$product    = wc_get_product($product_id);

		if ($product) {
			$product->set_status('draft');
			$product->save();
		}

		$plugin->save_product_bundle_meta($product_id, 'no', MBBB_Plugin::default_settings(), $slots);
		$build_mode  = sanitize_key((string) ($posted['build_mode'] ?? 'template'));
		$is_custom   = ('custom' === $build_mode);
		$template_id = $is_custom ? 'custom' : sanitize_key((string) ($posted['template_id'] ?? ''));
		$custom_hash = $is_custom ? self::custom_config_hash($posted) : '';
		self::persist_deal_meta($product_id, $posted, $build_mode, $template_id, $is_custom, $custom_hash);
		MBBB_Admin_Setup::ensure_bundle_category($product_id);

		return array(
			'success'    => false,
			'product_id' => $product_id,
			'errors'     => array_values(array_unique($errors)),
		);
	}

	/**
	 * @param int    $product_id  Product ID.
	 * @param string $price       Bundle price.
	 * @return void
	 */
	private static function sync_bundle_product_price($product_id, $price) {
		$product_id = absint($product_id);
		$price      = '' !== (string) $price ? wc_format_decimal((string) $price) : '';
		if ($product_id < 1 || '' === $price) {
			return;
		}

		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product_Variable) {
			return;
		}

		foreach ($product->get_children() as $child_id) {
			$variation = wc_get_product($child_id);
			if ($variation instanceof WC_Product_Variation) {
				$variation->set_regular_price($price);
				$variation->save();
			}
		}
		WC_Product_Variable::sync($product_id);
		update_post_meta($product_id, '_regular_price', $price);
		update_post_meta($product_id, '_price', $price);
	}

	/**
	 * @param int                  $product_id  Product ID.
	 * @param array<string, mixed> $posted      Posted form.
	 * @param string               $build_mode  Build mode.
	 * @param string               $template_id Template ID.
	 * @param bool                 $is_custom   Custom deal flag.
	 * @param string               $custom_hash Custom config hash.
	 * @return void
	 */
	private static function persist_deal_meta($product_id, array $posted, $build_mode, $template_id, $is_custom, $custom_hash) {
		$included_products = self::parse_included_products_from_post($posted);
		$custom_counts     = self::parse_custom_choice_counts($posted);
		$deal_meta         = array(
			'admin_description'  => sanitize_textarea_field((string) ($posted['admin_description'] ?? '')),
			'build_mode'         => $build_mode,
			'template_id'        => $template_id,
			'deal_type'          => $is_custom ? 'mix_and_match' : sanitize_key((string) ($posted['deal_type'] ?? 'mix_and_match')),
			'bundle_size'        => $is_custom ? '' : sanitize_text_field((string) ($posted['bundle_size'] ?? '')),
			'display_order'      => absint($posted['display_order'] ?? 0),
			'free_shipping'      => ! empty($posted['free_shipping']) ? 'yes' : 'no',
			'badge_label'        => sanitize_text_field((string) ($posted['badge_label'] ?? '')),
			'boilie_ranges'      => isset($posted['boilie_ranges']) && is_array($posted['boilie_ranges'])
				? array_values(array_filter(array_map('sanitize_key', $posted['boilie_ranges'])))
				: array(),
			'custom_config_hash' => $is_custom ? $custom_hash : '',
			'custom_choices'     => $is_custom ? $custom_counts : array(),
			'option_filters'     => $is_custom ? self::parse_option_filters_from_post($posted) : array(),
			'included_products'  => $included_products,
		);
		update_post_meta($product_id, self::META_DEAL, $deal_meta);
		MBBB_Admin_Setup::ensure_bundle_category($product_id);
		self::apply_display_order($product_id, $deal_meta['display_order']);
		self::apply_badge_tag($product_id, $deal_meta['badge_label']);
	}

	/**
	 * Build slot array from friendly form fields.
	 *
	 * @param array<string, mixed> $posted Posted form.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_slots_from_form(array $posted) {
		if ('custom' === sanitize_key((string) ($posted['build_mode'] ?? ''))) {
			return self::build_custom_slots_from_form($posted);
		}

		$template_id = sanitize_key((string) ($posted['template_id'] ?? ''));
		$slots       = MBBB_Presets::get_preset_slots($template_id);
		if (empty($slots)) {
			return array();
		}

		$include_hookbaits = ! empty($posted['include_hookbaits']);
		$include_liquids   = ! empty($posted['include_liquids']);
		$include_dips      = ! empty($posted['include_dips']);
		$include_pellets   = ! empty($posted['include_pellets']);

		$filtered = array();
		foreach ($slots as $slot) {
			$type = self::classify_slot((string) ($slot['label'] ?? ''));
			if ('hookbait' === $type && ! $include_hookbaits) {
				continue;
			}
			if ('liquid' === $type && ! $include_liquids) {
				continue;
			}
			if ('dip' === $type && ! $include_dips) {
				continue;
			}
			if ('pellet' === $type && ! $include_pellets) {
				continue;
			}

			$filtered[] = $slot;
		}

		return MBBB_Plugin::ensure_unique_slot_keys($filtered);
	}

	/**
	 * Deal rows for the admin list table.
	 *
	 * @param string $filter all|active|disabled|draft
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_deal_rows($filter = 'all') {
		$plugin = MBBB_Plugin::instance();
		$rows   = array();
		$seen   = array();
		$filter = sanitize_key((string) $filter);

		$ids = self::collect_deal_product_ids();

		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1 || isset($seen[ $product_id ])) {
				continue;
			}
			$seen[ $product_id ] = true;

			if (! self::is_genuine_bundle_deal($product_id)) {
				continue;
			}

			$enabled = $plugin->is_enabled($product_id);
			$slots   = $plugin->get_resolved_slots($product_id);
			$meta    = get_post_meta($product_id, self::META_DEAL, true);
			$meta    = is_array($meta) ? $meta : array();

			$product = wc_get_product($product_id);
			if (! $product) {
				continue;
			}

			$template_id = isset($meta['template_id']) ? (string) $meta['template_id'] : MBBB_Presets::detect_preset_from_title($product->get_name());
			$templates   = self::get_deal_templates();
			$template    = isset($templates[ $template_id ]) ? $templates[ $template_id ] : null;
			$deal_state  = self::get_deal_state_label($product->get_status(), $enabled);

			if ('all' !== $filter && $filter !== $deal_state['key']) {
				continue;
			}

			$rows[] = array(
				'id'               => $product_id,
				'name'             => $product->get_name(),
				'status'           => $product->get_status(),
				'enabled'          => $enabled,
				'deal_active'      => ('active' === $deal_state['key']),
				'deal_state'       => $deal_state['key'],
				'deal_state_label' => $deal_state['label'],
				'price'            => $product->get_price_html(),
				'raw_price'        => $product->get_regular_price(),
				'choices_label'    => self::format_customer_choices_label($slots),
				'slot_count'       => self::count_meaningful_slots($slots),
				'template_id'      => $template_id,
				'template_label'   => ('custom' === $template_id || (is_array($meta) && 'custom' === sanitize_key((string) ($meta['build_mode'] ?? ''))))
					? __('Custom deal', 'mad-baits-bundle-builder')
					: ($template['label'] ?? ($template_id ?: '—')),
				'bundle_size'      => $meta['bundle_size'] ?? ($template['bundle_size'] ?? '—'),
				'deal_type'        => $meta['deal_type'] ?? ($template['deal_type'] ?? ''),
				'display_order'    => isset($meta['display_order']) ? absint($meta['display_order']) : 0,
				'modified'         => get_post_modified_time('Y-m-d H:i', false, $product_id),
				'edit_url'         => admin_url('admin.php?page=mbbb-deals&action=edit&deal_id=' . $product_id),
				'preview_url'      => get_permalink($product_id),
				'duplicate_url'    => wp_nonce_url(
					admin_url('admin.php?page=mbbb-deals&action=duplicate&deal_id=' . $product_id),
					'mbbb_deal_duplicate_' . $product_id
				),
				'toggle_url'       => wp_nonce_url(
					admin_url('admin.php?page=mbbb-deals&action=toggle&deal_id=' . $product_id),
					'mbbb_deal_toggle_' . $product_id
				),
				'trash_url'        => wp_nonce_url(
					admin_url('admin.php?page=mbbb-deals&action=trash&deal_id=' . $product_id),
					'mbbb_deal_trash_' . $product_id
				),
			);
		}

		usort(
			$rows,
			static function ($a, $b) {
				$order_a = (int) ($a['display_order'] ?? 0);
				$order_b = (int) ($b['display_order'] ?? 0);
				if ($order_a !== $order_b) {
					return $order_a <=> $order_b;
				}
				return strcasecmp((string) $a['name'], (string) $b['name']);
			}
		);

		return $rows;
	}

	/**
	 * Estimate reference value from slot option product IDs (when available).
	 *
	 * @param int $product_id Deal product ID.
	 * @return float
	 */
	public static function estimate_reference_value($product_id) {
		$plugin = MBBB_Plugin::instance();
		$slots  = $plugin->get_slots($product_id);
		$total  = 0.0;
		$count  = 0;

		foreach ($slots as $slot) {
			$options = $plugin->resolve_slot_options($slot, $product_id);
			foreach ($options as $option) {
				$pid = absint($option['product_id'] ?? 0);
				if ($pid < 1) {
					continue;
				}
				$p = wc_get_product($pid);
				if ($p) {
					$total += (float) $p->get_regular_price();
					++$count;
					break;
				}
			}
		}

		return $count > 0 ? $total : 0.0;
	}

	/**
	 * @param string               $preset_id Preset ID.
	 * @param array<int, array>    $slots     Slots.
	 * @return string
	 */
	private static function template_description($preset_id, array $slots) {
		$stats = self::analyze_slots($slots);
		$parts = array();

		if ($stats['boilie_splits'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: number of boilie choices */
				_n('%d boilie choice', '%d boilie choices', $stats['boilie_splits'], 'mad-baits-bundle-builder'),
				$stats['boilie_splits']
			);
		}
		if ($stats['hookbaits'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: number of hookbaits */
				_n('%d hookbait', '%d hookbaits', $stats['hookbaits'], 'mad-baits-bundle-builder'),
				$stats['hookbaits']
			);
		}
		if ($stats['liquids'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: number of liquids */
				_n('%d liquid', '%d liquids', $stats['liquids'], 'mad-baits-bundle-builder'),
				$stats['liquids']
			);
		}
		if ($stats['dips'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: number of dips */
				_n('%d dip', '%d dips', $stats['dips'], 'mad-baits-bundle-builder'),
				$stats['dips']
			);
		}
		if ($stats['pellets'] > 0) {
			$parts[] = __('includes pellet', 'mad-baits-bundle-builder');
		}

		return implode(', ', $parts);
	}

	/**
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return array{boilie_splits: int, hookbaits: int, liquids: int, dips: int, pellets: int}
	 */
	private static function analyze_slots(array $slots) {
		$stats = array(
			'boilie_splits' => 0,
			'hookbaits'     => 0,
			'liquids'       => 0,
			'dips'          => 0,
			'pellets'       => 0,
		);

		foreach ($slots as $slot) {
			$type = self::classify_slot((string) ($slot['label'] ?? ''));
			if ('boilie' === $type) {
				++$stats['boilie_splits'];
			} elseif ('hookbait' === $type) {
				++$stats['hookbaits'];
			} elseif ('liquid' === $type) {
				++$stats['liquids'];
			} elseif ('dip' === $type) {
				++$stats['dips'];
			} elseif ('pellet' === $type) {
				++$stats['pellets'];
			}
		}

		return $stats;
	}

	/**
	 * @param string $label Slot label.
	 * @return string boilie|hookbait|liquid|dip|pellet|other
	 */
	private static function classify_slot($label) {
		$haystack = strtolower($label);
		if (false !== strpos($haystack, 'split') || false !== strpos($haystack, 'boilie') || false !== strpos($haystack, '5kg') || false !== strpos($haystack, '10kg')) {
			if (false !== strpos($haystack, 'pellet')) {
				return 'pellet';
			}
			return 'boilie';
		}
		if (false !== strpos($haystack, 'hook')) {
			return 'hookbait';
		}
		if (false !== strpos($haystack, 'liquid') || false !== strpos($haystack, '500ml')) {
			return 'liquid';
		}
		if (false !== strpos($haystack, 'dip') || false !== strpos($haystack, '250ml')) {
			return 'dip';
		}
		if (false !== strpos($haystack, 'pellet')) {
			return 'pellet';
		}
		return 'other';
	}

	/**
	 * @param array<int, array<string, mixed>> $slots    Slots.
	 * @param array<string, mixed>|null          $template Template.
	 * @return string
	 */
	private static function build_customer_summary(array $slots, $template) {
		if (empty($slots)) {
			return isset($template['description']) ? (string) $template['description'] : '';
		}

		$stats = self::analyze_slots($slots);
		$lines = array();

		if ($stats['boilie_splits'] > 0) {
			$size = is_array($template) ? (string) ($template['bundle_size'] ?? '') : '';
			if ('' !== $size && $stats['boilie_splits'] > 1) {
				$lines[] = sprintf(
					/* translators: 1: number of splits 2: bundle size e.g. 20kg */
					__('Customer chooses %1$d splits totalling %2$s', 'mad-baits-bundle-builder'),
					$stats['boilie_splits'],
					$size
				);
			} else {
				$lines[] = sprintf(
					/* translators: %d: number of boilie choices */
					__('Customer chooses %d boilie product(s)', 'mad-baits-bundle-builder'),
					$stats['boilie_splits']
				);
			}
		}
		if ($stats['hookbaits'] > 0) {
			$lines[] = sprintf(
				/* translators: %d: hookbait count */
				__('Customer chooses %d hookbait(s)', 'mad-baits-bundle-builder'),
				$stats['hookbaits']
			);
		}
		if ($stats['liquids'] > 0) {
			$lines[] = sprintf(
				/* translators: %d: liquid count */
				__('Customer chooses %d liquid(s)', 'mad-baits-bundle-builder'),
				$stats['liquids']
			);
		}
		if ($stats['dips'] > 0) {
			$lines[] = sprintf(
				/* translators: %d: dip count */
				__('Customer chooses %d dip(s)', 'mad-baits-bundle-builder'),
				$stats['dips']
			);
		}
		if ($stats['pellets'] > 0) {
			$lines[] = __('Includes a pellet choice', 'mad-baits-bundle-builder');
		}

		return implode('. ', $lines);
	}

	/**
	 * Filter boilie slot options to selected product ranges.
	 *
	 * @param array<string, mixed> $slot   Slot.
	 * @param array<string, mixed> $posted Form data.
	 * @return array<string, mixed>
	 */
	private static function apply_boilie_range_filter(array $slot, array $posted) {
		$ranges = isset($posted['boilie_ranges']) && is_array($posted['boilie_ranges'])
			? array_map('sanitize_title', $posted['boilie_ranges'])
			: array();

		$all_ranges = array_keys(self::get_boilie_ranges());
		if (empty($ranges) || count($ranges) >= count($all_ranges)) {
			return $slot;
		}

		$pool_id = isset($slot['pool_id']) ? (string) $slot['pool_id'] : 'standard-boilie-choices';
		$pools   = MBBB_Plugin::instance()->get_pools();
		$options = isset($pools[ $pool_id ]['options']) && is_array($pools[ $pool_id ]['options'])
			? $pools[ $pool_id ]['options']
			: array();

		$manual = array();
		foreach ($options as $opt) {
			$label = (string) ($opt['label'] ?? '');
			if ('' === $label) {
				continue;
			}
			$matched_slug = self::match_option_to_range_slug($label, $opt);
			if ('' === $matched_slug || ! in_array($matched_slug, $ranges, true)) {
				continue;
			}
			$product_id = absint($opt['product_id'] ?? 0);
			if ($product_id < 1) {
				$product_id = self::resolve_option_product_id($label, $matched_slug);
			}
			$manual[] = array(
				'label'      => $label,
				'value'      => isset($opt['value']) ? (string) $opt['value'] : sanitize_title($label),
				'product_id' => $product_id,
				'range_slug' => $matched_slug,
				'image'      => isset($opt['image']) ? (string) $opt['image'] : '',
				'active'     => true,
			);
		}

		if (empty($manual)) {
			return $slot;
		}

		$slot['source']         = 'manual';
		$slot['manual_options'] = $manual;
		$slot['pool_id']        = $pool_id;

		return $slot;
	}

	/**
	 * Match a pool option label to a stable boilie range slug.
	 *
	 * @param string               $label Option label.
	 * @param array<string, mixed> $opt   Option row.
	 * @return string
	 */
	public static function match_option_to_range_slug($label, array $opt = array()) {
		if (! empty($opt['range_slug'])) {
			return sanitize_title((string) $opt['range_slug']);
		}

		$product_id = absint($opt['product_id'] ?? 0);
		if ($product_id > 0) {
			$slug = self::product_range_slug($product_id);
			if ('' !== $slug) {
				return $slug;
			}
		}

		$normalized = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) $label));
		foreach (self::boilie_range_match_aliases() as $range_slug => $aliases) {
			foreach ($aliases as $alias) {
				$alias = strtolower(trim($alias));
				if ('' !== $alias && false !== strpos($normalized, $alias)) {
					return $range_slug;
				}
			}
		}

		return '';
	}

	/**
	 * Resolve WooCommerce product ID for a labelled pool option.
	 *
	 * @param string $label      Option label.
	 * @param string $range_slug Range slug.
	 * @return int
	 */
	public static function resolve_option_product_id($label, $range_slug = '') {
		$label = trim((string) $label);
		if ('' === $label) {
			return 0;
		}

		$query_args = array(
			'status' => 'publish',
			'limit'  => 5,
			's'      => $label,
		);

		if ('' !== $range_slug) {
			$query_args['tag'] = array(sanitize_title($range_slug));
		}

		$ids = function_exists('mad_baits_supplier_wc_get_product_ids')
			? mad_baits_supplier_wc_get_product_ids($query_args)
			: wc_get_products(array_merge($query_args, array('return' => 'ids')));

		foreach ((array) $ids as $product_id) {
			$product = wc_get_product(absint($product_id));
			if (! $product) {
				continue;
			}
			if (0 === strcasecmp($product->get_name(), $label)) {
				return (int) $product->get_id();
			}
		}

		foreach ((array) $ids as $product_id) {
			$product = wc_get_product(absint($product_id));
			if ($product && false !== stripos($product->get_name(), $label)) {
				return (int) $product->get_id();
			}
		}

		return 0;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private static function product_range_slug($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return '';
		}

		if (taxonomy_exists('pa_range')) {
			$terms = wc_get_product_terms($product_id, 'pa_range', array('fields' => 'slugs'));
			if (is_array($terms) && ! empty($terms[0])) {
				return sanitize_title((string) $terms[0]);
			}
		}

		$tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
		if (is_array($tags)) {
			foreach ($tags as $tag) {
				$tag = sanitize_title((string) $tag);
				if (self::is_deal_boilie_range_slug($tag)) {
					return $tag;
				}
			}
		}

		return '';
	}

	/**
	 * Attach product IDs to manual slot options where possible.
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return array<int, array<string, mixed>>
	 */
	public static function enrich_slots_with_product_ids(array $slots) {
		foreach ($slots as $index => $slot) {
			if ('manual' !== ($slot['source'] ?? '') || empty($slot['manual_options']) || ! is_array($slot['manual_options'])) {
				continue;
			}
			foreach ($slot['manual_options'] as $opt_index => $opt) {
				if (! is_array($opt) || absint($opt['product_id'] ?? 0) > 0) {
					continue;
				}
				$label = (string) ($opt['label'] ?? '');
				$slug  = self::match_option_to_range_slug($label, $opt);
				$pid   = self::resolve_option_product_id($label, $slug);
				if ($pid > 0) {
					$slots[ $index ]['manual_options'][ $opt_index ]['product_id'] = $pid;
				}
			}
		}
		return $slots;
	}

	/**
	 * Whether a product is a genuine MBBB bundle deal (not a normal catalogue product).
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_genuine_bundle_deal($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}

		$meta = get_post_meta($product_id, self::META_DEAL, true);
		if (is_array($meta)) {
			if (! empty($meta['build_mode']) && 'custom' === sanitize_key((string) $meta['build_mode'])) {
				return self::has_meaningful_deal_slots($product_id) || ! empty($meta['included_products']);
			}
			if (! empty($meta['template_id'])) {
				return true;
			}
		}

		$enabled_meta = (string) get_post_meta($product_id, MBBB_Plugin::META_ENABLED, true);
		if ('no' === $enabled_meta && ! self::has_meaningful_deal_slots($product_id)) {
			return false;
		}

		if (! self::has_meaningful_deal_slots($product_id)) {
			return false;
		}

		if ('yes' === $enabled_meta) {
			return true;
		}

		if (self::is_in_bundle_category($product_id)) {
			return true;
		}

		$product = wc_get_product($product_id);
		if ($product) {
			$preset_id = MBBB_Presets::detect_preset_from_title($product->get_name());
			if ('' !== $preset_id && ! empty(MBBB_Presets::get_preset_slots($preset_id))) {
				return true;
			}
		}

		return 'no' !== $enabled_meta && self::count_meaningful_slots(MBBB_Plugin::instance()->get_resolved_slots($product_id)) >= 2;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function has_meaningful_deal_slots($product_id) {
		return self::count_meaningful_slots(MBBB_Plugin::instance()->get_resolved_slots($product_id)) >= 2;
	}

	/**
	 * Count configured customer choice steps (ignore empty slot stubs).
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return int
	 */
	public static function count_meaningful_slots(array $slots) {
		$count = 0;
		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$label  = trim((string) ($slot['label'] ?? ''));
			$key    = trim((string) ($slot['key'] ?? ''));
			$pool   = trim((string) ($slot['pool'] ?? $slot['pool_id'] ?? ''));
			$manual = array();
			if (isset($slot['manual_options']) && is_array($slot['manual_options'])) {
				foreach ($slot['manual_options'] as $opt) {
					if (! is_array($opt)) {
						continue;
					}
					if ('' !== trim((string) ($opt['label'] ?? '')) || absint($opt['product_id'] ?? 0) > 0) {
						$manual[] = $opt;
					}
				}
			}
			if ('' !== $label || '' !== $key || '' !== $pool || ! empty($manual)) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Plain-English customer choice summary for list tables.
	 *
	 * @param array<int, array<string, mixed>> $slots Slots.
	 * @return string
	 */
	public static function format_customer_choices_label(array $slots) {
		$stats = self::analyze_slots($slots);
		$parts = array();
		if ($stats['boilie_splits'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: count */
				_n('%d boilie', '%d boilie', $stats['boilie_splits'], 'mad-baits-bundle-builder'),
				$stats['boilie_splits']
			);
		}
		if ($stats['hookbaits'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: count */
				_n('%d hookbait', '%d hookbaits', $stats['hookbaits'], 'mad-baits-bundle-builder'),
				$stats['hookbaits']
			);
		}
		if ($stats['liquids'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: count */
				_n('%d liquid', '%d liquids', $stats['liquids'], 'mad-baits-bundle-builder'),
				$stats['liquids']
			);
		}
		if ($stats['dips'] > 0) {
			$parts[] = sprintf(
				/* translators: %d: count */
				_n('%d dip', '%d dips', $stats['dips'], 'mad-baits-bundle-builder'),
				$stats['dips']
			);
		}
		if ($stats['pellets'] > 0) {
			$parts[] = __('pellet', 'mad-baits-bundle-builder');
		}

		if (empty($parts)) {
			return '—';
		}

		return implode(', ', $parts);
	}

	/**
	 * @param string $post_status Post status.
	 * @param bool   $enabled     Builder enabled.
	 * @return array{key: string, label: string}
	 */
	public static function get_deal_state_label($post_status, $enabled) {
		if ('draft' === $post_status) {
			return array(
				'key'   => 'draft',
				'label' => __('Draft', 'mad-baits-bundle-builder'),
			);
		}
		if ($enabled) {
			return array(
				'key'   => 'active',
				'label' => __('Active', 'mad-baits-bundle-builder'),
			);
		}
		return array(
			'key'   => 'disabled',
			'label' => __('Disabled', 'mad-baits-bundle-builder'),
		);
	}

	/**
	 * Collect product IDs that may be bundle deals.
	 *
	 * @return int[]
	 */
	private static function collect_deal_product_ids() {
		global $wpdb;

		$ids = array();
		$meta_ids = $wpdb->get_col(
			"SELECT DISTINCT pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status IN ('publish','draft','private')
			AND (
				(pm.meta_key = '_mbbb_enabled' AND pm.meta_value IN ('yes','no'))
				OR pm.meta_key = '_mbbb_deal_meta'
				OR (pm.meta_key = '_mbbb_slots' AND pm.meta_value <> '' AND pm.meta_value <> 'a:0:{}')
			)"
		);
		$ids = array_merge($ids, array_map('absint', (array) $meta_ids));

		foreach (MBBB_Admin_Setup::bundle_category_slugs() as $slug) {
			$term = get_term_by('slug', $slug, 'product_cat');
			if (! $term || is_wp_error($term)) {
				continue;
			}
			$cat_args = array(
				'status'   => array('publish', 'draft', 'private'),
				'limit'    => 200,
				'category' => array((int) $term->term_id),
			);
			if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
				$ids = array_merge($ids, (array) mad_baits_supplier_wc_get_product_ids($cat_args));
			} else {
				$cat_args['return'] = 'ids';
				$ids                = array_merge($ids, (array) wc_get_products($cat_args));
			}
		}

		return array_values(array_unique(array_map('absint', $ids)));
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function is_in_bundle_category($product_id) {
		foreach (MBBB_Admin_Setup::bundle_category_slugs() as $slug) {
			if (has_term($slug, 'product_cat', $product_id)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $template_id Template ID.
	 * @return bool
	 */
	private static function template_has_boilie_slots($template_id) {
		$slots = MBBB_Presets::get_preset_slots(sanitize_key((string) $template_id));
		foreach ($slots as $slot) {
			if ('boilie' === self::classify_slot((string) ($slot['label'] ?? ''))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Store menu order for deal list sorting.
	 *
	 * @param int $product_id Product ID.
	 * @param int $order      Display order.
	 * @return void
	 */
	private static function apply_display_order($product_id, $order) {
		wp_update_post(
			array(
				'ID'         => absint($product_id),
				'menu_order' => absint($order),
			)
		);
	}

	/**
	 * Map optional badge label to known product tags when possible.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $badge      Badge label.
	 * @return void
	 */
	/**
	 * Duplicate a live deal as a draft with copied bundle configuration.
	 *
	 * @param int    $source_id Source product ID.
	 * @param string $new_title New product title.
	 * @param string $price     Optional price override.
	 * @return int New product ID or 0.
	 */
	/**
	 * Resolve the bundle price shown in admin for simple and variable products.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function get_product_bundle_price($product) {
		if (! $product instanceof WC_Product) {
			return '';
		}

		$price = (string) $product->get_regular_price();
		if ('' !== $price) {
			return $price;
		}

		$meta_price = (string) get_post_meta($product->get_id(), '_regular_price', true);
		if ('' !== $meta_price) {
			return $meta_price;
		}

		if ($product instanceof WC_Product_Variable) {
			$min = $product->get_variation_price('min', false);
			return '' !== $min ? (string) $min : '';
		}

		return (string) $product->get_price();
	}

	public static function clone_deal_product($source_id, $new_title, $price = '') {
		$source_id = absint($source_id);
		$new_title = sanitize_text_field((string) $new_title);
		if ($source_id < 1 || '' === $new_title) {
			return 0;
		}

		$source = wc_get_product($source_id);
		if (! $source) {
			return 0;
		}

		$duplicate = $source instanceof WC_Product_Variable ? new WC_Product_Variable() : new WC_Product_Simple();
		$duplicate->set_name($new_title);
		$duplicate->set_status('draft');
		$duplicate->set_catalog_visibility('visible');
		$duplicate->set_sold_individually(false);
		$duplicate->set_regular_price('' !== (string) $price ? wc_format_decimal((string) $price) : (string) $source->get_regular_price());
		if ($source instanceof WC_Product_Variable && $duplicate instanceof WC_Product_Variable) {
			$duplicate->set_attributes($source->get_attributes());
		}
		$duplicate->set_category_ids($source->get_category_ids());
		$duplicate->set_tag_ids($source->get_tag_ids());
		if ($source->get_image_id()) {
			$duplicate->set_image_id($source->get_image_id());
		}

		$new_id = (int) $duplicate->save();
		if ($new_id < 1) {
			return 0;
		}

		self::copy_reference_variations($source_id, $new_id, (string) $duplicate->get_regular_price());
		MBBB_Admin_Setup::copy_bundle_config($source_id, $new_id);
		MBBB_Plugin::instance()->save_product_bundle_meta($new_id, 'no', MBBB_Plugin::instance()->get_settings($source_id), MBBB_Plugin::instance()->get_slots($source_id));
		$meta = get_post_meta($source_id, self::META_DEAL, true);
		if (is_array($meta)) {
			update_post_meta($new_id, self::META_DEAL, $meta);
		}
		MBBB_Admin_Setup::ensure_bundle_category($new_id);

		return $new_id;
	}

	/**
	 * Find an existing bundle product to use as a variable-product shell for a template.
	 *
	 * @param string $template_id Template ID.
	 * @return int
	 */
	public static function find_reference_product_id($template_id) {
		$template_id = sanitize_key((string) $template_id);
		if ('' === $template_id) {
			return 0;
		}

		$candidates = array();
		foreach (self::collect_deal_product_ids() as $product_id) {
			$product = wc_get_product($product_id);
			if (! $product instanceof WC_Product_Variable) {
				continue;
			}
			$meta = get_post_meta($product_id, self::META_DEAL, true);
			$meta = is_array($meta) ? $meta : array();
			$match_template = isset($meta['template_id']) ? sanitize_key((string) $meta['template_id']) : MBBB_Presets::detect_preset_from_title($product->get_name());
			if ($match_template === $template_id) {
				return (int) $product_id;
			}
			if (MBBB_Plugin::instance()->is_enabled($product_id) && ! empty(MBBB_Plugin::instance()->get_slots($product_id))) {
				$candidates[] = (int) $product_id;
			}
		}

		return ! empty($candidates) ? (int) $candidates[0] : 0;
	}

	/**
	 * Create a new variable bundle product by cloning attributes from a reference deal.
	 *
	 * @param string $template_id Template ID.
	 * @param string $name        Product name.
	 * @param string $price       Regular price.
	 * @param string $status      post status.
	 * @return int
	 */
	public static function create_deal_product_shell($template_id, $name, $price, $status) {
		$reference_id = self::find_reference_product_id($template_id);
		if ($reference_id < 1) {
			$reference_id = self::find_any_variable_bundle_reference();
		}
		if ($reference_id < 1) {
			return 0;
		}
		return self::create_deal_product_shell_from_reference($reference_id, $name, $price, $status);
	}

	/**
	 * Create a custom deal product without a preset template.
	 *
	 * @param string $name   Product name.
	 * @param string $price  Regular price.
	 * @param string $status Post status.
	 * @return int
	 */
	public static function create_custom_deal_product_shell($name, $price, $status) {
		$duplicate = new WC_Product_Variable();
		$duplicate->set_name($name);
		$duplicate->set_status($status);
		$duplicate->set_catalog_visibility('visible');
		$duplicate->set_sold_individually(false);
		if ('' !== $price) {
			$duplicate->set_regular_price($price);
		}

		$product_id = (int) $duplicate->save();
		if ($product_id < 1) {
			return 0;
		}

		MBBB_Admin_Setup::ensure_bundle_category($product_id);
		return $product_id;
	}

	/**
	 * Find any enabled variable bundle product to clone attributes/variations from.
	 *
	 * @return int
	 */
	public static function find_any_variable_bundle_reference() {
		foreach (self::collect_deal_product_ids() as $product_id) {
			$product = wc_get_product($product_id);
			if ($product instanceof WC_Product_Variable && self::is_genuine_bundle_deal($product_id)) {
				return (int) $product_id;
			}
		}
		return 0;
	}

	/**
	 * Clone a reference variable bundle into a new deal product shell.
	 *
	 * @param int    $reference_id Reference product ID.
	 * @param string $name         Product name.
	 * @param string $price        Regular price.
	 * @param string $status       Post status.
	 * @return int
	 */
	public static function create_deal_product_shell_from_reference($reference_id, $name, $price, $status) {
		$reference_id = absint($reference_id);
		$reference    = $reference_id > 0 ? wc_get_product($reference_id) : null;
		if (! $reference instanceof WC_Product_Variable) {
			return 0;
		}

		$duplicate = new WC_Product_Variable();
		$duplicate->set_name($name);
		$duplicate->set_status($status);
		$duplicate->set_catalog_visibility('visible');
		$duplicate->set_sold_individually(false);
		if ('' !== $price) {
			$duplicate->set_regular_price($price);
		}
		$duplicate->set_attributes($reference->get_attributes());
		$duplicate->set_category_ids($reference->get_category_ids());
		$duplicate->set_tag_ids($reference->get_tag_ids());

		$product_id = (int) $duplicate->save();
		if ($product_id < 1) {
			return 0;
		}

		self::copy_reference_variations($reference_id, $product_id, $price);
		MBBB_Admin_Setup::ensure_bundle_category($product_id);
		return $product_id;
	}

	/**
	 * Expand preset slots to manual options from global pools where needed.
	 *
	 * @param array<int, array<string, mixed>> $slots  Slots.
	 * @param array<string, mixed>             $posted Posted form.
	 * @return array<int, array<string, mixed>>
	 */
	/**
	 * Copy variation rows from a reference bundle product so add-to-cart works.
	 *
	 * @param int    $reference_id Reference product ID.
	 * @param int    $target_id    Target product ID.
	 * @param string $price        Optional price override.
	 * @return void
	 */
	public static function copy_reference_variations($reference_id, $target_id, $price = '') {
		$reference_id = absint($reference_id);
		$target_id    = absint($target_id);
		if ($reference_id < 1 || $target_id < 1) {
			return;
		}

		$reference = wc_get_product($reference_id);
		if (! $reference instanceof WC_Product_Variable) {
			return;
		}

		$target = wc_get_product($target_id);
		if (! $target instanceof WC_Product_Variable) {
			return;
		}

		$price = '' !== (string) $price ? wc_format_decimal((string) $price) : (string) $reference->get_regular_price();
		foreach ($reference->get_children() as $child_id) {
			$variation = wc_get_product($child_id);
			if (! $variation instanceof WC_Product_Variation) {
				continue;
			}
			$new_variation = new WC_Product_Variation();
			$new_variation->set_parent_id($target_id);
			$new_variation->set_attributes($variation->get_attributes());
			$new_variation->set_status('publish');
			$new_variation->set_regular_price('' !== $price ? $price : (string) $variation->get_regular_price());
			$new_variation->set_manage_stock($variation->get_manage_stock());
			$new_variation->set_stock_status($variation->get_stock_status());
			$new_variation->save();
		}

		WC_Product_Variable::sync($target_id);
		if ('' !== $price) {
			update_post_meta($target_id, '_regular_price', $price);
			update_post_meta($target_id, '_price', $price);
		}
	}

	public static function materialize_slots_for_product(array $slots, array $posted) {
		$plugin = MBBB_Plugin::instance();
		$pools  = $plugin->get_pools();
		$out    = array();
		$is_custom = ('custom' === sanitize_key((string) ($posted['build_mode'] ?? '')));

		foreach ($slots as $slot) {
			$type = self::classify_slot((string) ($slot['label'] ?? ''));
			if ('boilie' === $type) {
				$slot = self::apply_boilie_range_filter($slot, $posted);
			}

			if (empty($slot['manual_options']) && ! empty($slot['pool_id'])) {
				$pool_id = (string) $slot['pool_id'];
				if (isset($pools[ $pool_id ]['options']) && is_array($pools[ $pool_id ]['options'])) {
					$manual = array();
					foreach ($pools[ $pool_id ]['options'] as $opt) {
						if (array_key_exists('active', (array) $opt) && empty($opt['active'])) {
							continue;
						}
						$label = (string) ($opt['label'] ?? '');
						if ('' === $label) {
							continue;
						}
						if ('boilie' === $type) {
							$matched_slug = self::match_option_to_range_slug($label, $opt);
							$ranges       = isset($posted['boilie_ranges']) && is_array($posted['boilie_ranges'])
								? array_map('sanitize_title', $posted['boilie_ranges'])
								: array();
							$all_ranges   = array_keys(self::get_boilie_ranges());
							if (! empty($ranges) && count($ranges) < count($all_ranges)) {
								if ('' === $matched_slug || ! in_array($matched_slug, $ranges, true)) {
									continue;
								}
							}
						}
						$manual[] = array(
							'label'      => $label,
							'value'      => (string) ($opt['value'] ?? sanitize_title($label)),
							'product_id' => absint($opt['product_id'] ?? 0),
							'range_slug' => sanitize_title((string) ($opt['range_slug'] ?? '')),
							'image'      => (string) ($opt['image'] ?? ''),
							'active'     => true,
						);
					}
					if (! empty($manual)) {
						$slot['manual_options'] = $manual;
						if ('boilie' === $type) {
							$slot['source'] = 'manual';
						}
					}
				}
			}

			if ($is_custom) {
				if ('hookbait' === $type) {
					$slot = self::apply_selected_pool_values_filter($slot, $posted, 'hookbait_options');
				} elseif ('liquid' === $type) {
					$slot = self::apply_selected_pool_values_filter($slot, $posted, 'liquid_options');
				} elseif ('dip' === $type) {
					$slot = self::apply_selected_pool_values_filter($slot, $posted, 'dip_options');
				} elseif ('pellet' === $type) {
					$slot = self::apply_selected_pool_values_filter($slot, $posted, 'pellet_options');
				}
			}

			$out[] = $slot;
		}

		return MBBB_Plugin::ensure_unique_slot_keys($out);
	}

	/**
	 * Active option values from a shared product options pool.
	 *
	 * @param string $pool_id Pool ID.
	 * @return string[]
	 */
	public static function get_default_pool_values($pool_id) {
		$options = self::get_pool_admin_options($pool_id);
		$values  = array();
		foreach ($options as $opt) {
			$value = (string) ($opt['value'] ?? '');
			if ('' !== $value) {
				$values[] = $value;
			}
		}
		return $values;
	}

	/**
	 * @param string $pool_id Pool ID.
	 * @return array<int, array{label: string, value: string, product_id: int}>
	 */
	public static function get_pool_admin_options($pool_id) {
		$pools = MBBB_Plugin::instance()->get_pools();
		$pool_id = sanitize_key((string) $pool_id);
		if (empty($pools[ $pool_id ]['options']) || ! is_array($pools[ $pool_id ]['options'])) {
			return array();
		}

		$out = array();
		foreach ($pools[ $pool_id ]['options'] as $opt) {
			if (! is_array($opt)) {
				continue;
			}
			if (array_key_exists('active', $opt) && empty($opt['active'])) {
				continue;
			}
			$label = trim((string) ($opt['label'] ?? ''));
			if ('' === $label) {
				continue;
			}
			$out[] = array(
				'label'      => $label,
				'value'      => (string) ($opt['value'] ?? sanitize_title($label)),
				'product_id' => absint($opt['product_id'] ?? 0),
				'range_slug' => sanitize_title((string) ($opt['range_slug'] ?? '')),
			);
		}
		return $out;
	}

	/**
	 * Merge saved option filters with any newly added pool options.
	 *
	 * @param string   $pool_id Pool ID.
	 * @param string[] $saved   Saved option values.
	 * @return string[]
	 */
	public static function merge_pool_option_selection($pool_id, array $saved) {
		$available = self::get_default_pool_values($pool_id);
		if (empty($available)) {
			return array_values(array_filter(array_map('strval', $saved)));
		}
		if (empty($saved)) {
			return $available;
		}
		$saved = array_map('strval', $saved);
		foreach ($available as $value) {
			if (! in_array((string) $value, $saved, true)) {
				$saved[] = (string) $value;
			}
		}
		return array_values(array_unique($saved));
	}

	/**
	 * Merge saved boilie range filters with any newly registered/discovered ranges.
	 *
	 * @param string[] $saved Saved range slugs.
	 * @return string[]
	 */
	public static function merge_boilie_range_selection(array $saved) {
		$available = array_keys(self::get_boilie_ranges());
		if (empty($available)) {
			return array_values(array_filter(array_map('sanitize_key', $saved)));
		}
		if (empty($saved)) {
			return $available;
		}
		$saved = array_map('sanitize_key', $saved);
		foreach ($available as $slug) {
			if (! in_array($slug, $saved, true)) {
				$saved[] = $slug;
			}
		}
		return array_values(array_unique($saved));
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @return array{boilie: int, hookbait: int, liquid: int, dip: int, pellet: int}
	 */
	public static function parse_custom_choice_counts(array $posted) {
		return array(
			'boilie'   => max(0, min(20, absint($posted['custom_boilie_choices'] ?? 0))),
			'hookbait' => max(0, min(20, absint($posted['custom_hookbait_choices'] ?? 0))),
			'liquid'   => max(0, min(20, absint($posted['custom_liquid_choices'] ?? 0))),
			'dip'      => max(0, min(20, absint($posted['custom_dip_choices'] ?? 0))),
			'pellet'   => max(0, min(20, absint($posted['custom_pellet_choices'] ?? 0))),
		);
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @return array<int, array{product_id: int, variation_id: int, quantity: int, label: string}>
	 */
	public static function parse_included_products_from_post(array $posted) {
		$raw = isset($posted['included_products']) && is_array($posted['included_products'])
			? $posted['included_products']
			: array();
		return self::normalize_included_products($raw);
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Included rows.
	 * @return array<int, array{product_id: int, variation_id: int, quantity: int, label: string}>
	 */
	public static function normalize_included_products(array $rows) {
		$out = array();
		foreach ($rows as $row) {
			if (! is_array($row)) {
				continue;
			}
			$product_id = absint($row['product_id'] ?? 0);
			if ($product_id < 1) {
				continue;
			}
			$variation_id = absint($row['variation_id'] ?? 0);
			$product      = $variation_id > 0 ? wc_get_product($variation_id) : wc_get_product($product_id);
			if (! $product) {
				continue;
			}
			$out[] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'quantity'     => max(1, absint($row['quantity'] ?? 1)),
				'label'        => $product->get_name(),
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @return array<string, string[]>
	 */
	public static function parse_option_filters_from_post(array $posted) {
		$map = array(
			'hookbaits' => 'hookbait_options',
			'liquids'   => 'liquid_options',
			'dips'      => 'dip_options',
			'pellets'   => 'pellet_options',
		);
		$out = array();
		foreach ($map as $key => $field) {
			if (! isset($posted[ $field ]) || ! is_array($posted[ $field ])) {
				$out[ $key ] = array();
				continue;
			}
			$out[ $key ] = array_values(array_filter(array_map('sanitize_text_field', $posted[ $field ])));
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @return string
	 */
	public static function custom_config_hash(array $posted) {
		$payload = array(
			'counts'   => self::parse_custom_choice_counts($posted),
			'ranges'   => isset($posted['boilie_ranges']) && is_array($posted['boilie_ranges']) ? array_values($posted['boilie_ranges']) : array(),
			'filters'  => self::parse_option_filters_from_post($posted),
			'included' => self::parse_included_products_from_post($posted),
		);
		return md5(wp_json_encode($payload));
	}

	/**
	 * Build customer choice slots from plain-English custom deal form fields.
	 *
	 * @param array<string, mixed> $posted Posted form.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_custom_slots_from_form(array $posted) {
		$counts = self::parse_custom_choice_counts($posted);
		$slots  = array();

		for ($i = 1; $i <= $counts['boilie']; $i++) {
			$label = $counts['boilie'] > 1
				? sprintf(
					/* translators: %d: choice number */
					__('Boilie choice %d', 'mad-baits-bundle-builder'),
					$i
				)
				: __('Boilie choice', 'mad-baits-bundle-builder');
			$slots[] = self::make_choice_slot($label, 'standard-boilie-choices');
		}
		for ($i = 1; $i <= $counts['hookbait']; $i++) {
			$label = $counts['hookbait'] > 1
				? sprintf(__('Hookbait choice %d', 'mad-baits-bundle-builder'), $i)
				: __('Hookbait choice', 'mad-baits-bundle-builder');
			$slots[] = self::make_choice_slot($label, 'standard-hookbait-choices');
		}
		for ($i = 1; $i <= $counts['liquid']; $i++) {
			$label = $counts['liquid'] > 1
				? sprintf(__('500ml liquid choice %d', 'mad-baits-bundle-builder'), $i)
				: __('500ml liquid choice', 'mad-baits-bundle-builder');
			$slots[] = self::make_choice_slot($label, 'standard-500ml-liquid-choices');
		}
		for ($i = 1; $i <= $counts['dip']; $i++) {
			$label = $counts['dip'] > 1
				? sprintf(__('250ml dip choice %d', 'mad-baits-bundle-builder'), $i)
				: __('250ml dip choice', 'mad-baits-bundle-builder');
			$slots[] = self::make_choice_slot($label, 'standard-250ml-dip-choices');
		}
		for ($i = 1; $i <= $counts['pellet']; $i++) {
			$label = $counts['pellet'] > 1
				? sprintf(__('Pellet choice %d', 'mad-baits-bundle-builder'), $i)
				: __('Pellet choice', 'mad-baits-bundle-builder');
			$slots[] = self::make_choice_slot($label, 'standard-pellet-choices');
		}

		return MBBB_Plugin::ensure_unique_slot_keys($slots);
	}

	/**
	 * @param string $label   Slot label.
	 * @param string $pool_id Pool ID.
	 * @return array<string, mixed>
	 */
	private static function make_choice_slot($label, $pool_id) {
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
	 * @param array<string, mixed> $slot         Slot.
	 * @param array<string, mixed> $posted       Posted form.
	 * @param string               $options_field Field name for selected values.
	 * @return array<string, mixed>
	 */
	private static function apply_selected_pool_values_filter(array $slot, array $posted, $options_field) {
		$selected = isset($posted[ $options_field ]) && is_array($posted[ $options_field ])
			? array_map('sanitize_text_field', $posted[ $options_field ])
			: array();
		if (empty($selected) || empty($slot['manual_options']) || ! is_array($slot['manual_options'])) {
			return $slot;
		}

		$filtered = array();
		foreach ($slot['manual_options'] as $opt) {
			$value = (string) ($opt['value'] ?? '');
			if ('' !== $value && in_array($value, $selected, true)) {
				$filtered[] = $opt;
			}
		}
		if (! empty($filtered)) {
			$slot['manual_options'] = $filtered;
		}
		return $slot;
	}

	/**
	 * @param array<string, mixed> $posted Posted form.
	 * @param array<string, int>   $counts Choice counts.
	 * @return array<int, string>
	 */
	private static function validate_custom_option_availability(array $posted, array $counts) {
		$errors = array();
		$checks = array(
			'hookbait' => array('field' => 'hookbait_options', 'pool' => 'standard-hookbait-choices', 'label' => __('Hookbait choices need at least one available product option.', 'mad-baits-bundle-builder')),
			'liquid'   => array('field' => 'liquid_options', 'pool' => 'standard-500ml-liquid-choices', 'label' => __('500ml liquid choices need at least one available product option.', 'mad-baits-bundle-builder')),
			'dip'      => array('field' => 'dip_options', 'pool' => 'standard-250ml-dip-choices', 'label' => __('250ml dip choices need at least one available product option.', 'mad-baits-bundle-builder')),
			'pellet'   => array('field' => 'pellet_options', 'pool' => 'standard-pellet-choices', 'label' => __('Pellet choices need at least one available product option.', 'mad-baits-bundle-builder')),
		);

		foreach ($checks as $key => $check) {
			if (($counts[ $key ] ?? 0) < 1) {
				continue;
			}
			$selected = isset($posted[ $check['field'] ]) && is_array($posted[ $check['field'] ]) ? $posted[ $check['field'] ] : array();
			if (empty($selected)) {
				$errors[] = $check['label'];
				continue;
			}
			$available = self::get_default_pool_values($check['pool']);
			$matched   = array_intersect($available, array_map('sanitize_text_field', $selected));
			if (empty($matched)) {
				$errors[] = $check['label'];
			}
		}

		if (($counts['boilie'] ?? 0) > 0) {
			$slots = self::build_custom_slots_from_form($posted);
			if (! empty($slots)) {
				$materialized = self::materialize_slots_for_product($slots, $posted);
				foreach ($materialized as $slot) {
					if ('boilie' !== self::classify_slot((string) ($slot['label'] ?? ''))) {
						continue;
					}
					if (empty($slot['manual_options'])) {
						$errors[] = __('Boilie choices need at least one available product option in the selected ranges.', 'mad-baits-bundle-builder');
						break;
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * @param string $message Raw validation message.
	 * @return string
	 */
	private static function plain_english_config_error($message) {
		$message = trim((string) $message);
		if ('' === $message) {
			return __('This deal configuration is not valid yet.', 'mad-baits-bundle-builder');
		}
		$replacements = array(
			'slot' => __('choice step', 'mad-baits-bundle-builder'),
			'pool' => __('product options', 'mad-baits-bundle-builder'),
		);
		return str_ireplace(array_keys($replacements), array_values($replacements), $message);
	}

	/**
	 * @param array<string, mixed> $form Deal form fragment.
	 * @return string
	 */
	public static function build_custom_customer_summary(array $form) {
		$lines = array();
		$counts = self::parse_custom_choice_counts($form);
		for ($i = 1; $i <= $counts['boilie']; $i++) {
			$lines[] = $counts['boilie'] > 1
				? sprintf(__('Boilie choice %d', 'mad-baits-bundle-builder'), $i)
				: __('Boilie choice', 'mad-baits-bundle-builder');
		}
		for ($i = 1; $i <= $counts['hookbait']; $i++) {
			$lines[] = $counts['hookbait'] > 1
				? sprintf(__('Hookbait choice %d', 'mad-baits-bundle-builder'), $i)
				: __('Hookbait choice', 'mad-baits-bundle-builder');
		}
		for ($i = 1; $i <= $counts['liquid']; $i++) {
			$lines[] = $counts['liquid'] > 1
				? sprintf(__('500ml liquid choice %d', 'mad-baits-bundle-builder'), $i)
				: __('500ml liquid choice', 'mad-baits-bundle-builder');
		}
		for ($i = 1; $i <= $counts['dip']; $i++) {
			$lines[] = $counts['dip'] > 1
				? sprintf(__('250ml dip choice %d', 'mad-baits-bundle-builder'), $i)
				: __('250ml dip choice', 'mad-baits-bundle-builder');
		}
		for ($i = 1; $i <= $counts['pellet']; $i++) {
			$lines[] = $counts['pellet'] > 1
				? sprintf(__('Pellet choice %d', 'mad-baits-bundle-builder'), $i)
				: __('Pellet choice', 'mad-baits-bundle-builder');
		}

		if (empty($lines)) {
			return '';
		}

		return sprintf(
			/* translators: %s: comma-separated list of customer steps */
			__('Customer will choose: %s', 'mad-baits-bundle-builder'),
			implode(', ', $lines)
		);
	}

	/**
	 * Included product rows for cart and order display.
	 *
	 * @param int $product_id Deal product ID.
	 * @return array<int, array{label: string, value: string}>
	 */
	/**
	 * Build WooCommerce variation attributes + shell variation for a custom deal.
	 *
	 * @param int                                $product_id Product ID.
	 * @param array<int, array<string, mixed>>   $slots      Materialized slots.
	 * @param string                             $price      Bundle price.
	 * @return void
	 */
	public static function configure_variable_product_for_custom_slots($product_id, array $slots, $price = '') {
		$product_id = absint($product_id);
		$product    = wc_get_product($product_id);
		if (! $product instanceof WC_Product_Variable) {
			return;
		}

		foreach ($product->get_children() as $child_id) {
			wp_delete_post(absint($child_id), true);
		}

		$attributes      = array();
		$variation_attrs = array();
		$position        = 0;

		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$label = trim((string) ($slot['label'] ?? ''));
			if ('' === $label) {
				continue;
			}

			$options = array();
			if (! empty($slot['manual_options']) && is_array($slot['manual_options'])) {
				foreach ($slot['manual_options'] as $opt) {
					if (! is_array($opt)) {
						continue;
					}
					$opt_label = trim((string) ($opt['label'] ?? ''));
					if ('' !== $opt_label) {
						$options[] = $opt_label;
					}
				}
			}
			$options = array_values(array_unique($options));
			if (empty($options)) {
				continue;
			}

			$slug = sanitize_title($label);
			$attr = new WC_Product_Attribute();
			$attr->set_id(0);
			$attr->set_name($label);
			$attr->set_options($options);
			$attr->set_position($position++);
			$attr->set_visible(false);
			$attr->set_variation(true);
			$attributes[ $slug ] = $attr;
			$variation_attrs[ 'attribute_' . $slug ] = '';
		}

		if (empty($attributes)) {
			return;
		}

		$product->set_attributes($attributes);
		$product->save();

		$price = '' !== (string) $price ? wc_format_decimal((string) $price) : (string) $product->get_regular_price();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id($product_id);
		$variation->set_attributes($variation_attrs);
		$variation->set_status('publish');
		if ('' !== $price) {
			$variation->set_regular_price($price);
		}
		$variation->set_manage_stock(false);
		$variation->set_stock_status('instock');
		$variation->save();

		WC_Product_Variable::sync($product_id);
		if ('' !== $price) {
			update_post_meta($product_id, '_regular_price', $price);
			update_post_meta($product_id, '_price', $price);
		}
	}

	public static function get_included_product_display_rows($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return array();
		}
		$meta = get_post_meta($product_id, self::META_DEAL, true);
		if (! is_array($meta) || empty($meta['included_products']) || ! is_array($meta['included_products'])) {
			return array();
		}

		$rows = array();
		foreach (self::normalize_included_products($meta['included_products']) as $item) {
			$qty   = max(1, absint($item['quantity'] ?? 1));
			$label = (string) ($item['label'] ?? '');
			if ('' === $label) {
				continue;
			}
			$value = $qty > 1 ? sprintf('%s × %d', $label, $qty) : $label;
			$rows[] = array(
				'label' => __('Included automatically', 'mad-baits-bundle-builder'),
				'value' => $value,
			);
		}
		return $rows;
	}

	private static function apply_badge_tag($product_id, $badge) {
		$badge = trim((string) $badge);
		if ('' === $badge) {
			return;
		}

		$tag_map = array(
			'best seller'  => 'team-pick',
			'best-seller'  => 'team-pick',
			'team pick'    => 'team-pick',
			'new'          => 'new',
			'limited deal' => 'limited',
			'limited'      => 'limited',
		);

		$key = strtolower($badge);
		if (! isset($tag_map[ $key ])) {
			return;
		}

		$slug = $tag_map[ $key ];
		$term = get_term_by('slug', $slug, 'product_tag');
		if (! $term) {
			wp_insert_term(ucwords(str_replace('-', ' ', $slug)), 'product_tag', array('slug' => $slug));
			$term = get_term_by('slug', $slug, 'product_tag');
		}
		if ($term && ! is_wp_error($term)) {
			wp_set_object_terms($product_id, array((int) $term->term_id), 'product_tag', true);
		}
	}
}
