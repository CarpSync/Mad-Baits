<?php
/**
 * Client-friendly Bundle Product Options management.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Adds WooCommerce products to shared bundle option pools without exposing internals.
 */
final class MBBB_Pool_Options {

	const OPTION_RANGES  = 'mbbb_bundle_ranges';
	const OPTION_IGNORED = 'mbbb_ignored_pool_suggestions';
	const META_LINK      = '_mbbb_bundle_pool_option';

	/**
	 * @return void
	 */
	public static function init() {
		add_action('add_meta_boxes', array(__CLASS__, 'register_product_meta_box'), 30);
		add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_meta_box'), 30, 2);
		add_action('admin_notices', array(__CLASS__, 'render_range_available_notice'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_product_meta_assets'));
	}

	/**
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public static function enqueue_product_meta_assets($hook) {
		if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || 'product' !== $screen->post_type) {
			return;
		}
		wp_enqueue_style(
			'mbbb-product-pool-meta',
			MBBB_PLUGIN_URL . 'assets/css/mbbb-product-pool-meta.css',
			array(),
			MBBB_VERSION
		);
		wp_enqueue_script(
			'mbbb-product-pool-meta',
			MBBB_PLUGIN_URL . 'assets/js/mbbb-product-pool-meta.js',
			array('jquery'),
			MBBB_VERSION,
			true
		);
	}

	/**
	 * Show a notice after a new range becomes available in Bundle Deals.
	 *
	 * @return void
	 */
	public static function render_range_available_notice() {
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		$notice = get_transient('mbbb_range_available_notice_' . get_current_user_id());
		if (! is_array($notice) || empty($notice['label'])) {
			return;
		}
		delete_transient('mbbb_range_available_notice_' . get_current_user_id());
		$label    = (string) $notice['label'];
		$deals_url = admin_url('admin.php?page=mbbb-deals&action=add');
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: range label */
					__('%s is now available in Bundle Deals.', 'mad-baits-bundle-builder'),
					$label
				)
			),
			esc_html__('New product options are ready to use when building custom bundle deals.', 'mad-baits-bundle-builder'),
			esc_url($deals_url),
			esc_html__('Create a bundle deal', 'mad-baits-bundle-builder')
		);
	}

	/**
	 * @param string $range_slug  Range slug.
	 * @param string $range_label Range label.
	 * @return void
	 */
	private static function queue_range_available_notice($range_slug, $range_label) {
		$range_slug  = sanitize_title((string) $range_slug);
		$range_label = trim((string) $range_label);
		if ('' === $range_slug) {
			return;
		}
		if ('' === $range_label) {
			$range_label = ucwords(str_replace('-', ' ', $range_slug));
		}
		set_transient(
			'mbbb_range_available_notice_' . get_current_user_id(),
			array(
				'slug'  => $range_slug,
				'label' => $range_label,
			),
			60
		);
	}

	/**
	 * Bundle option types shown in the admin UI.
	 *
	 * @return array<string, array{pool_id: string, label: string, has_range: bool}>
	 */
	public static function get_bundle_types() {
		return array(
			'boilie'       => array(
				'pool_id'   => 'standard-boilie-choices',
				'label'     => __('Boilie', 'mad-baits-bundle-builder'),
				'has_range' => true,
			),
			'hookbait'     => array(
				'pool_id'   => 'standard-hookbait-choices',
				'label'     => __('Hookbait', 'mad-baits-bundle-builder'),
				'has_range' => true,
			),
			'liquid_500ml' => array(
				'pool_id'   => 'standard-500ml-liquid-choices',
				'label'     => __('500ml Liquid', 'mad-baits-bundle-builder'),
				'has_range' => false,
			),
			'dip_250ml'    => array(
				'pool_id'   => 'standard-250ml-dip-choices',
				'label'     => __('250ml Dip', 'mad-baits-bundle-builder'),
				'has_range' => false,
			),
			'pellet'       => array(
				'pool_id'   => 'standard-pellet-choices',
				'label'     => __('Pellet', 'mad-baits-bundle-builder'),
				'has_range' => true,
			),
		);
	}

	/**
	 * @param string $type Type key.
	 * @return string
	 */
	public static function pool_id_for_type($type) {
		$types = self::get_bundle_types();
		$type  = sanitize_key((string) $type);
		return isset($types[ $type ]['pool_id']) ? (string) $types[ $type ]['pool_id'] : '';
	}

	/**
	 * @param string $pool_id Pool ID.
	 * @return string
	 */
	public static function type_for_pool_id($pool_id) {
		$pool_id = sanitize_key((string) $pool_id);
		foreach (self::get_bundle_types() as $type => $meta) {
			if ($pool_id === ($meta['pool_id'] ?? '')) {
				return $type;
			}
		}
		return '';
	}

	/**
	 * Admin-managed boilie ranges (e.g. STP).
	 *
	 * @return array<string, string>
	 */
	public static function get_registered_ranges() {
		$stored = get_option(self::OPTION_RANGES, array());
		if (! is_array($stored)) {
			return array();
		}
		$out = array();
		foreach ($stored as $slug => $label) {
			$slug = sanitize_title((string) $slug);
			$label = trim((string) $label);
			if ('' !== $slug && '' !== $label) {
				$out[ $slug ] = $label;
			}
		}
		return $out;
	}

	/**
	 * @param string $slug  Range slug.
	 * @param string $label Range label.
	 * @return void
	 */
	public static function register_range($slug, $label) {
		$slug  = sanitize_title((string) $slug);
		$label = trim((string) $label);
		if ('' === $slug || '' === $label) {
			return;
		}
		$ranges         = self::get_registered_ranges();
		$ranges[ $slug ] = $label;
		update_option(self::OPTION_RANGES, $ranges, false);
	}

	/**
	 * All selectable boilie ranges for deal builder and options UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_ranges() {
		$ranges = MBBB_Deal_Builder::get_boilie_ranges();
		foreach (self::get_registered_ranges() as $slug => $label) {
			$ranges[ $slug ] = $label;
		}
		foreach (self::discover_ranges_from_pools() as $slug => $label) {
			if (! isset($ranges[ $slug ])) {
				$ranges[ $slug ] = $label;
			}
		}
		ksort($ranges);
		return $ranges;
	}

	/**
	 * @return array<string, string>
	 */
	public static function discover_ranges_from_pools() {
		$out    = array();
		$pools  = MBBB_Plugin::instance()->get_pools();
		$registered = self::get_registered_ranges();
		$pool_ids   = array(
			'standard-boilie-choices',
			'standard-hookbait-choices',
			'standard-pellet-choices',
		);
		foreach ($pool_ids as $pool_id) {
			foreach ((array) ($pools[ $pool_id ]['options'] ?? array()) as $opt) {
				if (! is_array($opt)) {
					continue;
				}
				$slug = sanitize_title((string) ($opt['range_slug'] ?? ''));
				if ('' === $slug) {
					continue;
				}
				if (! isset($out[ $slug ])) {
					$out[ $slug ] = $registered[ $slug ] ?? ucwords(str_replace('-', ' ', $slug));
				}
			}
		}
		ksort($out);
		return $out;
	}

	/**
	 * @param string $slug Range slug.
	 * @return bool
	 */
	public static function is_known_range_slug($slug) {
		$slug = sanitize_title((string) $slug);
		if ('' === $slug || 'bbb' === $slug) {
			return false;
		}
		if ('swan-mussel' === $slug && (! function_exists('mad_baits_range_is_storefront_visible') || ! mad_baits_range_is_storefront_visible('swan-mussel'))) {
			return false;
		}
		if (isset(self::get_registered_ranges()[ $slug ])) {
			return true;
		}
		if (isset(self::discover_ranges_from_pools()[ $slug ])) {
			return true;
		}
		return in_array(
			$slug,
			array('asbo', 'p-fish-2', 'pandemic', 'nutz-plus', 'nutz-banana', 'wicked-white', 'stp'),
			true
		);
	}

	/**
	 * @param int $wc_product_id Product or variation ID.
	 * @return string
	 */
	public static function format_product_display_name($wc_product_id) {
		$wc_product_id = absint($wc_product_id);
		if ($wc_product_id < 1) {
			return '';
		}
		$product = wc_get_product($wc_product_id);
		if (! $product) {
			return __('Product no longer exists', 'mad-baits-bundle-builder');
		}
		return $product->get_formatted_name();
	}

	/**
	 * @param int $wc_product_id Product or variation ID.
	 * @return array{state: string, label: string}
	 */
	public static function get_product_health($wc_product_id) {
		$wc_product_id = absint($wc_product_id);
		if ($wc_product_id < 1) {
			return array(
				'state' => 'missing',
				'label' => __('No product linked', 'mad-baits-bundle-builder'),
			);
		}
		$product = wc_get_product($wc_product_id);
		if (! $product) {
			return array(
				'state' => 'missing',
				'label' => __('Product no longer exists', 'mad-baits-bundle-builder'),
			);
		}
		if ('publish' !== $product->get_status()) {
			return array(
				'state' => 'unpublished',
				'label' => __('Product is not published', 'mad-baits-bundle-builder'),
			);
		}
		if (! $product->is_in_stock()) {
			return array(
				'state' => 'out_of_stock',
				'label' => __('Product is out of stock', 'mad-baits-bundle-builder'),
			);
		}
		return array(
			'state' => 'ok',
			'label' => '',
		);
	}

	/**
	 * @param int $wc_product_id Product or variation ID.
	 * @return array{type: string, confidence: string, label: string, reason: string}
	 */
	public static function suggest_type($wc_product_id) {
		$wc_product_id = absint($wc_product_id);
		$product       = $wc_product_id > 0 ? wc_get_product($wc_product_id) : null;
		if (! $product) {
			return array(
				'type'       => '',
				'confidence' => 'none',
				'label'      => '',
				'reason'     => '',
			);
		}

		$haystack = strtolower($product->get_name() . ' ' . $product->get_sku());
		$cats     = self::get_product_term_names($wc_product_id, 'product_cat');
		$tags     = self::get_product_term_names($wc_product_id, 'product_tag');
		$blob     = strtolower(implode(' ', array_merge(array($haystack), $cats, $tags)));

		$rules = array(
			'pellet'       => array('pellet', 'pellets', 'bag mix'),
			'dip_250ml'    => array('250ml dip', '250ml', 'food dip', ' dip '),
			'liquid_500ml' => array('500ml', 'liquid', 'oil', 'hydro', 'glug'),
			'hookbait'     => array('hookbait', 'hook bait', 'pop up', 'pop-up', 'popups', 'wafter', 'skinz'),
			'boilie'       => array('boilie', 'boilies', 'freezer bait', 'shelf life', '1kg', '5kg', '10kg'),
		);

		$scores = array();
		foreach ($rules as $type => $needles) {
			$score = 0;
			foreach ($needles as $needle) {
				if (false !== strpos($blob, strtolower($needle))) {
					++$score;
				}
			}
			if ($score > 0) {
				$scores[ $type ] = $score;
			}
		}

		if (empty($scores)) {
			return array(
				'type'       => '',
				'confidence' => 'none',
				'label'      => '',
				'reason'     => __('Choose the bundle type manually.', 'mad-baits-bundle-builder'),
			);
		}

		arsort($scores);
		$type  = (string) key($scores);
		$score = (int) current($scores);
		$types = self::get_bundle_types();

		return array(
			'type'       => $type,
			'confidence' => $score >= 2 ? 'high' : 'medium',
			'label'      => $types[ $type ]['label'] ?? $type,
			'reason'     => $score >= 2
				? __('Suggested from product name and categories.', 'mad-baits-bundle-builder')
				: __('Possible match — please confirm the type.', 'mad-baits-bundle-builder'),
		);
	}

	/**
	 * @param int $wc_product_id Product or variation ID.
	 * @return array{slug: string, label: string, confidence: string}
	 */
	public static function suggest_range($wc_product_id) {
		$wc_product_id = absint($wc_product_id);
		$product       = $wc_product_id > 0 ? wc_get_product($wc_product_id) : null;
		if (! $product) {
			return array(
				'slug'       => '',
				'label'      => '',
				'confidence' => 'none',
			);
		}

		if (taxonomy_exists('pa_range')) {
			$terms = wc_get_product_terms($wc_product_id, 'pa_range', array('fields' => 'all'));
			if (! empty($terms) && ! is_wp_error($terms)) {
				$term = $terms[0];
				return array(
					'slug'       => sanitize_title($term->slug),
					'label'      => $term->name,
					'confidence' => 'high',
				);
			}
		}

		$tags = wp_get_post_terms($wc_product_id, 'product_tag', array('fields' => 'all'));
		if (is_array($tags)) {
			foreach ($tags as $term) {
				$slug = sanitize_title($term->slug);
				if (self::is_known_range_slug($slug) || self::looks_like_range_name($term->name)) {
					return array(
						'slug'       => $slug,
						'label'      => $term->name,
						'confidence' => self::is_known_range_slug($slug) ? 'high' : 'medium',
					);
				}
			}
		}

		$name = strtolower($product->get_name());
		foreach (MBBB_Deal_Builder::boilie_range_match_aliases() as $slug => $aliases) {
			foreach ($aliases as $alias) {
				if ('' !== $alias && false !== strpos($name, strtolower($alias))) {
					$ranges = self::get_all_ranges();
					return array(
						'slug'       => $slug,
						'label'      => $ranges[ $slug ] ?? ucwords(str_replace('-', ' ', $slug)),
						'confidence' => 'medium',
					);
				}
			}
		}

		$first = self::guess_range_from_name($product->get_name());
		if ('' !== $first['slug']) {
			return $first;
		}

		return array(
			'slug'       => '',
			'label'      => '',
			'confidence' => 'none',
		);
	}

	/**
	 * @param string $name Product name.
	 * @return array{slug: string, label: string, confidence: string}
	 */
	private static function guess_range_from_name($name) {
		$parts = preg_split('/[\s\-–—]+/', strtolower(trim((string) $name)));
		if (! is_array($parts) || empty($parts[0])) {
			return array(
				'slug'       => '',
				'label'      => '',
				'confidence' => 'none',
			);
		}
		$candidate = sanitize_title($parts[0]);
		if ('' === $candidate) {
			return array(
				'slug'       => '',
				'label'      => '',
				'confidence' => 'none',
			);
		}
		return array(
			'slug'       => $candidate,
			'label'      => ucwords(str_replace('-', ' ', $candidate)),
			'confidence' => 'low',
		);
	}

	/**
	 * @param string $name Name.
	 * @return bool
	 */
	private static function looks_like_range_name($name) {
		$name = trim((string) $name);
		if ('' === $name || strlen($name) > 32) {
			return false;
		}
		return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\s\-\+]{1,30}$/', $name);
	}

	/**
	 * @param int    $wc_product_id Product or variation ID.
	 * @param string $taxonomy      Taxonomy.
	 * @return string[]
	 */
	private static function get_product_term_names($wc_product_id, $taxonomy) {
		$terms = wp_get_post_terms(absint($wc_product_id), $taxonomy, array('fields' => 'names'));
		return is_array($terms) ? array_map('strval', $terms) : array();
	}

	/**
	 * @param string $pool_id       Pool ID.
	 * @param int    $wc_product_id Product or variation ID.
	 * @return array{pool_id: string, index: int, option: array<string, mixed>}|null
	 */
	public static function find_existing_option($pool_id, $wc_product_id) {
		$pool_id       = sanitize_key((string) $pool_id);
		$wc_product_id = absint($wc_product_id);
		if ('' === $pool_id || $wc_product_id < 1) {
			return null;
		}
		$pools   = MBBB_Plugin::instance()->get_pools();
		$options = $pools[ $pool_id ]['options'] ?? array();
		foreach ((array) $options as $index => $opt) {
			if (! is_array($opt)) {
				continue;
			}
			if (absint($opt['product_id'] ?? 0) === $wc_product_id) {
				return array(
					'pool_id' => $pool_id,
					'index'   => (int) $index,
					'option'  => $opt,
				);
			}
		}
		return null;
	}

	/**
	 * @param string               $pool_id Pool ID.
	 * @param array<string, mixed> $args    Option args.
	 * @return array{success: bool, error: string, duplicate: array<string, mixed>|null}
	 */
	public static function add_option_to_pool($pool_id, array $args) {
		$pool_id       = sanitize_key((string) $pool_id);
		$wc_product_id = absint($args['product_id'] ?? 0);
		$label         = trim(sanitize_text_field((string) ($args['label'] ?? '')));
		$range_slug    = sanitize_title((string) ($args['range_slug'] ?? ''));
		$active        = ! array_key_exists('active', $args) || ! empty($args['active']);
		$register_range = ! empty($args['register_range']) && '' !== $range_slug && '' !== trim((string) ($args['range_label'] ?? ''));

		if ('' === $pool_id) {
			return array(
				'success'   => false,
				'error'     => __('Choose a bundle type.', 'mad-baits-bundle-builder'),
				'duplicate' => null,
			);
		}
		if ($wc_product_id < 1) {
			return array(
				'success'   => false,
				'error'     => __('Choose a WooCommerce product.', 'mad-baits-bundle-builder'),
				'duplicate' => null,
			);
		}
		if ('' === $label) {
			$label = self::format_product_display_name($wc_product_id);
		}
		if ('' === $label || __('Product no longer exists', 'mad-baits-bundle-builder') === $label) {
			return array(
				'success'   => false,
				'error'     => __('The selected product could not be found.', 'mad-baits-bundle-builder'),
				'duplicate' => null,
			);
		}

		$duplicate = self::find_existing_option($pool_id, $wc_product_id);
		if ($duplicate) {
			$types = self::get_bundle_types();
			$type  = self::type_for_pool_id($pool_id);
			return array(
				'success'   => false,
				'error'     => sprintf(
					/* translators: %s: bundle type label */
					__('Already available in %s options.', 'mad-baits-bundle-builder'),
					$types[ $type ]['label'] ?? __('this', 'mad-baits-bundle-builder')
				),
				'duplicate' => $duplicate,
			);
		}

		if ($register_range) {
			self::register_range($range_slug, sanitize_text_field((string) $args['range_label']));
		} elseif ('' !== $range_slug && ! self::is_known_range_slug($range_slug)) {
			self::register_range($range_slug, ucwords(str_replace('-', ' ', $range_slug)));
		}

		$value = sanitize_title((string) ($args['value'] ?? $label));
		if ('' === $value) {
			$value = 'option-' . $wc_product_id;
		}

		$option = array(
			'label'      => $label,
			'value'      => $value,
			'product_id' => $wc_product_id,
			'range_slug' => $range_slug,
			'image'      => '',
			'active'     => $active,
		);

		$plugin = MBBB_Plugin::instance();
		$pools  = $plugin->get_pools();
		if (empty($pools[ $pool_id ])) {
			$pools[ $pool_id ] = array(
				'id'      => $pool_id,
				'name'    => self::default_pool_name($pool_id),
				'active'  => true,
				'options' => array(),
			);
		}
		if (empty($pools[ $pool_id ]['options']) || ! is_array($pools[ $pool_id ]['options'])) {
			$pools[ $pool_id ]['options'] = array();
		}
		$pools[ $pool_id ]['options'][] = $option;
		$plugin->save_pools($pools);

		if ('' !== $range_slug) {
			$label_for_notice = trim((string) ($args['range_label'] ?? ''));
			if ('' === $label_for_notice) {
				$registered       = self::get_registered_ranges();
				$label_for_notice = $registered[ $range_slug ] ?? ucwords(str_replace('-', ' ', $range_slug));
			}
			self::queue_range_available_notice($range_slug, $label_for_notice);
		}

		update_post_meta(
			$wc_product_id,
			self::META_LINK,
			array(
				'pool_id'    => $pool_id,
				'label'      => $label,
				'value'      => $value,
				'range_slug' => $range_slug,
				'active'     => $active ? 'yes' : 'no',
			)
		);

		return array(
			'success'   => true,
			'error'     => '',
			'duplicate' => null,
		);
	}

	/**
	 * @param string $pool_id Pool ID.
	 * @param string $value   Option value.
	 * @return array{success: bool, error: string, usage: int}
	 */
	public static function remove_option_from_pool($pool_id, $value) {
		$pool_id = sanitize_key((string) $pool_id);
		$value   = sanitize_title((string) $value);
		if ('' === $pool_id || '' === $value) {
			return array(
				'success' => false,
				'error'   => __('Could not remove this option.', 'mad-baits-bundle-builder'),
				'usage'   => 0,
			);
		}

		$plugin = MBBB_Plugin::instance();
		$pools  = $plugin->get_pools();
		if (empty($pools[ $pool_id ]['options']) || ! is_array($pools[ $pool_id ]['options'])) {
			return array(
				'success' => false,
				'error'   => __('Option not found.', 'mad-baits-bundle-builder'),
				'usage'   => 0,
			);
		}

		$removed = null;
		$new     = array();
		foreach ($pools[ $pool_id ]['options'] as $opt) {
			if (! is_array($opt)) {
				continue;
			}
			if ((string) ($opt['value'] ?? '') === $value) {
				$removed = $opt;
				continue;
			}
			$new[] = $opt;
		}

		if (! $removed) {
			return array(
				'success' => false,
				'error'   => __('Option not found.', 'mad-baits-bundle-builder'),
				'usage'   => 0,
			);
		}

		$usage = self::count_deals_using_option($pool_id, $removed);
		$pools[ $pool_id ]['options'] = $new;
		$plugin->save_pools($pools);

		$pid = absint($removed['product_id'] ?? 0);
		if ($pid > 0) {
			delete_post_meta($pid, self::META_LINK);
		}

		return array(
			'success' => true,
			'error'   => '',
			'usage'   => $usage,
		);
	}

	/**
	 * @param string               $pool_id Pool ID.
	 * @param array<string, mixed> $option  Option row.
	 * @return int
	 */
	public static function count_deals_using_option($pool_id, array $option) {
		return count(self::get_deals_using_option($pool_id, $option));
	}

	/**
	 * @param string               $pool_id Pool ID.
	 * @param array<string, mixed> $option  Option row.
	 * @return array<int, array{id: int, name: string, active: bool}>
	 */
	public static function get_deals_using_option($pool_id, array $option) {
		unset($pool_id);
		$value      = (string) ($option['value'] ?? '');
		$product_id = absint($option['product_id'] ?? 0);
		$matches    = array();

		foreach (MBBB_Deal_Builder::get_all_deal_product_ids() as $deal_id) {
			$deal_id = absint($deal_id);
			if ($deal_id < 1 || ! MBBB_Deal_Builder::is_genuine_bundle_deal($deal_id)) {
				continue;
			}
			$slots = MBBB_Plugin::instance()->get_slots($deal_id);
			$hit   = false;
			foreach ((array) $slots as $slot) {
				if (! is_array($slot) || empty($slot['manual_options']) || ! is_array($slot['manual_options'])) {
					continue;
				}
				foreach ($slot['manual_options'] as $row) {
					if (! is_array($row)) {
						continue;
					}
					if ($value !== '' && (string) ($row['value'] ?? '') === $value) {
						$hit = true;
						break 2;
					}
					if ($product_id > 0 && absint($row['product_id'] ?? 0) === $product_id) {
						$hit = true;
						break 2;
					}
				}
			}
			if (! $hit) {
				continue;
			}
			$product = wc_get_product($deal_id);
			$matches[] = array(
				'id'     => $deal_id,
				'name'   => $product ? $product->get_name() : get_the_title($deal_id),
				'active' => MBBB_Plugin::instance()->is_enabled($deal_id),
			);
		}

		return $matches;
	}

	/**
	 * @return bool
	 */
	public static function is_suggest_enabled() {
		$settings = MBBB_Plugin::instance()->get_global_settings();
		return ! empty($settings['suggest_new_pool_products']) && 'yes' === MBBB_Plugin::sanitize_yes_no($settings['suggest_new_pool_products']);
	}

	/**
	 * @param bool $enabled Enabled.
	 * @return void
	 */
	public static function set_suggest_enabled($enabled) {
		$plugin   = MBBB_Plugin::instance();
		$settings = $plugin->get_global_settings();
		$settings['suggest_new_pool_products'] = $enabled ? 'yes' : 'no';
		$plugin->save_global_settings($settings);
	}

	/**
	 * @return int[]
	 */
	public static function get_ignored_suggestions() {
		$stored = get_option(self::OPTION_IGNORED, array());
		return is_array($stored) ? array_map('absint', $stored) : array();
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function ignore_suggestion($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return;
		}
		$ignored   = self::get_ignored_suggestions();
		$ignored[] = $product_id;
		update_option(self::OPTION_IGNORED, array_values(array_unique($ignored)), false);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_eligible_for_suggestion($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}
		if (in_array($product_id, self::get_ignored_suggestions(), true)) {
			return false;
		}
		if (self::product_is_linked_anywhere($product_id)) {
			return false;
		}
		$product = wc_get_product($product_id);
		if (! $product || 'publish' !== $product->get_status() || $product->is_type('variable')) {
			return false;
		}
		if (self::product_is_excluded_category($product_id)) {
			return false;
		}
		$suggest = self::suggest_type($product_id);
		return '' !== ($suggest['type'] ?? '');
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function product_is_linked_anywhere($product_id) {
		$product_id = absint($product_id);
		foreach (self::get_bundle_types() as $meta) {
			if (self::find_existing_option($meta['pool_id'], $product_id)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function product_is_excluded_category($product_id) {
		$blocked = array('clothing', 'merchandise', 'merch', 'tackle', 'terminal-tackle', 'rods', 'reels');
		$slugs   = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		if (! is_array($slugs)) {
			return false;
		}
		foreach ($slugs as $slug) {
			if (in_array(sanitize_title((string) $slug), $blocked, true)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<int, array{id: int, name: string, type: string, type_label: string, range_slug: string, range_label: string}>
	 */
	public static function get_suggested_products() {
		if (! self::is_suggest_enabled()) {
			return array();
		}

		$ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 40,
				'orderby'=> 'date',
				'order'  => 'DESC',
				'return' => 'ids',
			)
		);

		$out = array();
		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if (! self::is_eligible_for_suggestion($product_id)) {
				continue;
			}
			$type  = self::suggest_type($product_id);
			$range = self::suggest_range($product_id);
			$out[] = array(
				'id'          => $product_id,
				'name'        => self::format_product_display_name($product_id),
				'type'        => (string) ($type['type'] ?? ''),
				'type_label'  => (string) ($type['label'] ?? ''),
				'range_slug'  => (string) ($range['slug'] ?? ''),
				'range_label' => (string) ($range['label'] ?? ''),
			);
			if (count($out) >= 12) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	/**
	 * @param int $wc_product_id Product or variation ID.
	 * @return array{pool_id: string, index: int, option: array<string, mixed>, type: string}|null
	 */
	public static function find_existing_option_anywhere($wc_product_id) {
		$wc_product_id = absint($wc_product_id);
		if ($wc_product_id < 1) {
			return null;
		}
		foreach (self::get_bundle_types() as $type_key => $meta) {
			$found = self::find_existing_option($meta['pool_id'], $wc_product_id);
			if ($found) {
				$found['type'] = $type_key;
				return $found;
			}
		}
		return null;
	}

	/**
	 * @param int $wc_product_id Product or variation ID.
	 * @return void
	 */
	public static function remove_product_from_all_pools($wc_product_id) {
		$wc_product_id = absint($wc_product_id);
		if ($wc_product_id < 1) {
			return;
		}
		foreach (self::get_bundle_types() as $meta) {
			$found = self::find_existing_option($meta['pool_id'], $wc_product_id);
			if ($found && ! empty($found['option']['value'])) {
				self::remove_option_from_pool($found['pool_id'], (string) $found['option']['value']);
			}
		}
		delete_post_meta($wc_product_id, self::META_LINK);
	}

	/**
	 * @param int $parent_id Parent product ID.
	 * @return array<int, WC_Product_Variation>
	 */
	public static function get_manageable_variations($parent_id) {
		$parent = wc_get_product(absint($parent_id));
		if (! $parent || ! $parent->is_type('variable')) {
			return array();
		}
		$out = array();
		foreach ((array) $parent->get_children() as $variation_id) {
			$variation = wc_get_product(absint($variation_id));
			if (! $variation instanceof WC_Product_Variation) {
				continue;
			}
			if (in_array($variation->get_status(), array('trash', 'auto-draft'), true)) {
				continue;
			}
			$out[ absint($variation_id) ] = $variation;
		}
		return $out;
	}

	/**
	 * @param int $parent_id    Parent product ID.
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	public static function suggest_variation_customer_label($parent_id, $variation_id) {
		$parent_id    = absint($parent_id);
		$variation_id = absint($variation_id);
		$parent       = wc_get_product($parent_id);
		$variation    = wc_get_product($variation_id);
		if (! $parent || ! $variation) {
			return '';
		}

		$range       = self::suggest_range($parent_id);
		$range_slug  = sanitize_title((string) ($range['slug'] ?? ''));
		$registered  = self::get_registered_ranges();
		$range_label = isset($registered[ $range_slug ]) ? (string) $registered[ $range_slug ] : trim((string) ($range['label'] ?? ''));
		if ('' === $range_label && '' !== $range_slug) {
			$range_label = strlen($range_slug) <= 4
				? strtoupper($range_slug)
				: ucwords(str_replace('-', ' ', $range_slug));
		}

		$attr_values = array();
		foreach ((array) $variation->get_attributes() as $value) {
			$value = trim((string) $value);
			if ('' !== $value) {
				$attr_values[] = $value;
			}
		}
		$attr_text = trim(implode(' ', $attr_values));

		if ('' !== $range_label && '' !== $attr_text) {
			return trim($range_label . ' ' . $attr_text);
		}
		if ('' !== $attr_text) {
			return $attr_text;
		}
		return self::format_product_display_name($variation_id);
	}

	/**
	 * @param int               $wc_product_id Product or variation ID.
	 * @param array<string,mixed> $args      Option args (type, label, range_slug, range_label, active).
	 * @return array{success: bool, error: string}
	 */
	public static function upsert_product_pool_option($wc_product_id, array $args) {
		$wc_product_id = absint($wc_product_id);
		$type          = sanitize_key((string) ($args['type'] ?? ''));
		$pool          = self::pool_id_for_type($type);
		if ('' === $pool || $wc_product_id < 1) {
			return array(
				'success' => false,
				'error'   => __('Choose a bundle type.', 'mad-baits-bundle-builder'),
			);
		}

		foreach (self::get_bundle_types() as $meta) {
			if ($meta['pool_id'] === $pool) {
				continue;
			}
			$found = self::find_existing_option($meta['pool_id'], $wc_product_id);
			if ($found && ! empty($found['option']['value'])) {
				self::remove_option_from_pool($meta['pool_id'], (string) $found['option']['value']);
			}
		}

		$label = trim(sanitize_text_field((string) ($args['label'] ?? '')));
		if ('' === $label) {
			$label = self::format_product_display_name($wc_product_id);
		}
		$range_slug  = sanitize_title((string) ($args['range_slug'] ?? ''));
		$range_label = trim(sanitize_text_field((string) ($args['range_label'] ?? '')));
		if ('' === $range_label && '' !== $range_slug) {
			$registered  = self::get_registered_ranges();
			$range_label = $registered[ $range_slug ] ?? ucwords(str_replace('-', ' ', $range_slug));
		}
		$active = ! array_key_exists('active', $args) || ! empty($args['active']);

		if ('' !== $range_slug && ! self::is_known_range_slug($range_slug)) {
			self::register_range($range_slug, $range_label);
		}

		$existing = self::find_existing_option($pool, $wc_product_id);
		if ($existing) {
			$plugin = MBBB_Plugin::instance();
			$pools  = $plugin->get_pools();
			$pools[ $pool ]['options'][ $existing['index'] ] = array_merge(
				(array) $existing['option'],
				array(
					'label'      => $label,
					'range_slug' => $range_slug,
					'active'     => $active,
				)
			);
			$plugin->save_pools($pools);
			update_post_meta(
				$wc_product_id,
				self::META_LINK,
				array(
					'pool_id'    => $pool,
					'label'      => $label,
					'value'      => (string) ($existing['option']['value'] ?? ''),
					'range_slug' => $range_slug,
					'active'     => $active ? 'yes' : 'no',
				)
			);
			if ('' !== $range_slug) {
				self::queue_range_available_notice($range_slug, $range_label);
			}
			return array(
				'success' => true,
				'error'   => '',
			);
		}

		$result = self::add_option_to_pool(
			$pool,
			array(
				'product_id'     => $wc_product_id,
				'label'          => $label,
				'range_slug'     => $range_slug,
				'range_label'    => $range_label,
				'register_range' => '' !== $range_slug,
				'active'         => $active,
			)
		);
		return array(
			'success' => ! empty($result['success']),
			'error'   => (string) ($result['error'] ?? ''),
		);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_product_pool_config($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return null;
		}
		$meta = get_post_meta($product_id, self::META_LINK, true);
		if (is_array($meta) && ! empty($meta['pool_id'])) {
			return $meta;
		}
		foreach (self::get_bundle_types() as $type_meta) {
			$found = self::find_existing_option($type_meta['pool_id'], $product_id);
			if ($found) {
				return array_merge(
					(array) $found['option'],
					array(
						'pool_id' => $found['pool_id'],
						'type'    => self::type_for_pool_id($found['pool_id']),
					)
				);
			}
		}
		return null;
	}

	/**
	 * @return void
	 */
	public static function register_product_meta_box() {
		global $post;
		$context  = 'side';
		$priority = 'default';
		if ($post instanceof WP_Post && 'product' === $post->post_type) {
			$product = wc_get_product($post->ID);
			if ($product && $product->is_type('variable')) {
				$context  = 'normal';
				$priority = 'default';
			}
		}
		add_meta_box(
			'mbbb-bundle-pool-option',
			__('Bundle Deals', 'mad-baits-bundle-builder'),
			array(__CLASS__, 'render_product_meta_box'),
			'product',
			$context,
			$priority
		);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_product_meta_box($post) {
		$product = wc_get_product(absint($post->ID));
		wp_nonce_field('mbbb_save_product_pool_option', 'mbbb_product_pool_option_nonce');
		if ($product && $product->is_type('variable')) {
			self::render_variable_product_meta_box($product);
			return;
		}
		self::render_simple_product_meta_box(absint($post->ID));
	}

	/**
	 * @param int $product_id Simple product ID.
	 * @return void
	 */
	private static function render_simple_product_meta_box($product_id) {
		$config  = self::get_product_pool_config($product_id);
		$types   = self::get_bundle_types();
		$ranges  = self::get_all_ranges();
		$enabled = is_array($config) && ! empty($config['pool_id']);
		$type    = is_array($config) ? self::type_for_pool_id((string) ($config['pool_id'] ?? '')) : '';
		if ('' === $type) {
			$suggested = self::suggest_type($product_id);
			$type      = (string) ($suggested['type'] ?? '');
		}
		$range_slug = is_array($config) ? (string) ($config['range_slug'] ?? '') : '';
		if ('' === $range_slug) {
			$suggested  = self::suggest_range($product_id);
			$range_slug = (string) ($suggested['slug'] ?? '');
		}
		?>
		<p>
			<label>
				<input type="checkbox" name="mbbb_bundle_option_enabled" value="1" <?php checked($enabled); ?> />
				<?php esc_html_e('Available in bundle deals', 'mad-baits-bundle-builder'); ?>
			</label>
		</p>
		<p>
			<label for="mbbb-bundle-option-type"><strong><?php esc_html_e('Bundle type', 'mad-baits-bundle-builder'); ?></strong></label><br />
			<select name="mbbb_bundle_option_type" id="mbbb-bundle-option-type" class="widefat">
				<option value=""><?php esc_html_e('Choose type…', 'mad-baits-bundle-builder'); ?></option>
				<?php foreach ($types as $key => $meta) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($meta['label']); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="mbbb-bundle-option-label"><strong><?php esc_html_e('Customer label', 'mad-baits-bundle-builder'); ?></strong></label><br />
			<input type="text" class="widefat" id="mbbb-bundle-option-label" name="mbbb_bundle_option_label" value="<?php echo esc_attr(is_array($config) ? (string) ($config['label'] ?? '') : self::format_product_display_name($product_id)); ?>" />
		</p>
		<p>
			<label for="mbbb-bundle-option-range"><strong><?php esc_html_e('Range', 'mad-baits-bundle-builder'); ?></strong></label><br />
			<select name="mbbb_bundle_option_range" id="mbbb-bundle-option-range" class="widefat">
				<option value=""><?php esc_html_e('Auto-detect', 'mad-baits-bundle-builder'); ?></option>
				<?php foreach ($ranges as $slug => $label) : ?>
					<option value="<?php echo esc_attr($slug); ?>" <?php selected($range_slug, $slug); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label>
				<input type="checkbox" name="mbbb_bundle_option_visible" value="1" <?php checked(! is_array($config) || ! array_key_exists('active', $config) || ! empty($config['active'])); ?> />
				<?php esc_html_e('Visible', 'mad-baits-bundle-builder'); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e('Updates the shared Bundle Product Options list used by bundle deals.', 'mad-baits-bundle-builder'); ?></p>
		<?php
	}

	/**
	 * @param WC_Product_Variable $product Variable product.
	 * @return void
	 */
	private static function render_variable_product_meta_box($product) {
		$parent_id   = absint($product->get_id());
		$types       = self::get_bundle_types();
		$ranges      = self::get_all_ranges();
		$variations  = self::get_manageable_variations($parent_id);
		$parent_type = (string) (self::suggest_type($parent_id)['type'] ?? '');
		$parent_range = self::suggest_range($parent_id);
		$parent_range_slug = (string) ($parent_range['slug'] ?? '');
		?>
		<p class="description">
			<?php esc_html_e('This is a variable product. Choose which variations can be used inside bundle deals. Each selected variation is registered separately — the parent product is not used as a bundle option.', 'mad-baits-bundle-builder'); ?>
		</p>
		<?php if (empty($variations)) : ?>
			<p><?php esc_html_e('No variations found yet. Save attributes and generate variations first.', 'mad-baits-bundle-builder'); ?></p>
			<?php
			return;
		endif;
		?>
		<div class="mbbb-variation-bundle-actions">
			<button type="button" class="button-link mbbb-variation-select-all"><?php esc_html_e('Select all', 'mad-baits-bundle-builder'); ?></button>
			<span aria-hidden="true">|</span>
			<button type="button" class="button-link mbbb-variation-deselect-all"><?php esc_html_e('Deselect all', 'mad-baits-bundle-builder'); ?></button>
		</div>
		<div class="mbbb-variation-bundle-options">
			<?php foreach ($variations as $variation_id => $variation) : ?>
				<?php
				$config     = self::get_product_pool_config($variation_id);
				$enabled    = is_array($config) && ! empty($config['pool_id']);
				$type       = is_array($config) ? self::type_for_pool_id((string) ($config['pool_id'] ?? '')) : $parent_type;
				$range_slug = is_array($config) ? (string) ($config['range_slug'] ?? '') : $parent_range_slug;
				$label      = is_array($config) && ! empty($config['label'])
					? (string) $config['label']
					: self::suggest_variation_customer_label($parent_id, $variation_id);
				$visible    = ! is_array($config) || ! array_key_exists('active', $config) || ! empty($config['active']);
				$field_key  = 'mbbb_variation_options[' . absint($variation_id) . ']';
				?>
				<div class="mbbb-variation-bundle-option<?php echo $enabled ? ' is-enabled' : ''; ?>">
					<label class="mbbb-variation-bundle-option__toggle">
						<input type="checkbox" class="mbbb-variation-bundle-enable" name="<?php echo esc_attr($field_key); ?>[enabled]" value="1" <?php checked($enabled); ?> />
						<strong><?php echo esc_html($variation->get_formatted_name()); ?></strong>
					</label>
					<div class="mbbb-variation-bundle-option__fields">
						<p>
							<label><strong><?php esc_html_e('Customer label', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<input type="text" class="widefat" name="<?php echo esc_attr($field_key); ?>[label]" value="<?php echo esc_attr($label); ?>" />
						</p>
						<p>
							<label><strong><?php esc_html_e('Type', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select class="widefat" name="<?php echo esc_attr($field_key); ?>[type]">
								<option value=""><?php esc_html_e('Choose type…', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($types as $key => $meta) : ?>
									<option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($meta['label']); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label><strong><?php esc_html_e('Range', 'mad-baits-bundle-builder'); ?></strong></label><br />
							<select class="widefat" name="<?php echo esc_attr($field_key); ?>[range]">
								<option value=""><?php esc_html_e('Auto-detect', 'mad-baits-bundle-builder'); ?></option>
								<?php foreach ($ranges as $slug => $range_label) : ?>
									<option value="<?php echo esc_attr($slug); ?>" <?php selected($range_slug, $slug); ?>><?php echo esc_html($range_label); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label>
								<input type="checkbox" name="<?php echo esc_attr($field_key); ?>[visible]" value="1" <?php checked($visible); ?> />
								<?php esc_html_e('Visible', 'mad-baits-bundle-builder'); ?>
							</label>
						</p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e('Selected variations are added to the shared Bundle Product Options list used by bundle deals.', 'mad-baits-bundle-builder'); ?></p>
		<?php
	}

	/**
	 * @param int        $post_id Post ID.
	 * @param WC_Product $product Product.
	 * @return void
	 */
	public static function save_product_meta_box($post_id, $product) {
		if (! isset($_POST['mbbb_product_pool_option_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mbbb_product_pool_option_nonce'])), 'mbbb_save_product_pool_option')) {
			return;
		}
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$product = $product instanceof WC_Product ? $product : wc_get_product($post_id);
		if ($product && $product->is_type('variable')) {
			self::save_variable_product_meta_box($post_id, $product);
			return;
		}
		self::save_simple_product_meta_box($post_id);
	}

	/**
	 * @param int $post_id Product ID.
	 * @return void
	 */
	private static function save_simple_product_meta_box($post_id) {
		$post_id = absint($post_id);
		$enabled = ! empty($_POST['mbbb_bundle_option_enabled']);
		if (! $enabled) {
			self::remove_product_from_all_pools($post_id);
			return;
		}

		$type = sanitize_key((string) ($_POST['mbbb_bundle_option_type'] ?? ''));
		if ('' === self::pool_id_for_type($type)) {
			return;
		}

		$range_slug = sanitize_title((string) ($_POST['mbbb_bundle_option_range'] ?? ''));
		if ('' === $range_slug) {
			$suggested  = self::suggest_range($post_id);
			$range_slug = (string) ($suggested['slug'] ?? '');
		}

		self::upsert_product_pool_option(
			$post_id,
			array(
				'type'       => $type,
				'label'      => sanitize_text_field((string) ($_POST['mbbb_bundle_option_label'] ?? '')),
				'range_slug' => $range_slug,
				'active'     => ! empty($_POST['mbbb_bundle_option_visible']),
			)
		);
	}

	/**
	 * @param int                 $post_id Parent product ID.
	 * @param WC_Product_Variable $product Variable product.
	 * @return void
	 */
	private static function save_variable_product_meta_box($post_id, $product) {
		unset($product);
		$post_id = absint($post_id);

		// Never register the variable parent as a bundle option.
		self::remove_product_from_all_pools($post_id);

		$posted_rows = isset($_POST['mbbb_variation_options']) && is_array($_POST['mbbb_variation_options'])
			? wp_unslash($_POST['mbbb_variation_options'])
			: array();
		$variation_ids = array_keys(self::get_manageable_variations($post_id));

		foreach ($variation_ids as $variation_id) {
			$variation_id = absint($variation_id);
			$row          = isset($posted_rows[ $variation_id ]) && is_array($posted_rows[ $variation_id ])
				? $posted_rows[ $variation_id ]
				: array();
			$enabled      = ! empty($row['enabled']);

			if (! $enabled) {
				self::remove_product_from_all_pools($variation_id);
				continue;
			}

			$type = sanitize_key((string) ($row['type'] ?? ''));
			if ('' === $type) {
				$suggested = self::suggest_type($post_id);
				$type      = (string) ($suggested['type'] ?? '');
			}
			if ('' === self::pool_id_for_type($type)) {
				continue;
			}

			$range_slug = sanitize_title((string) ($row['range'] ?? ''));
			if ('' === $range_slug) {
				$suggested  = self::suggest_range($post_id);
				$range_slug = (string) ($suggested['slug'] ?? '');
			}

			self::upsert_product_pool_option(
				$variation_id,
				array(
					'type'       => $type,
					'label'      => sanitize_text_field((string) ($row['label'] ?? '')),
					'range_slug' => $range_slug,
					'active'     => ! empty($row['visible']),
				)
			);
		}
	}

	/**
	 * @param string $pool_id Pool ID.
	 * @return string
	 */
	private static function default_pool_name($pool_id) {
		$type = self::type_for_pool_id($pool_id);
		$types = self::get_bundle_types();
		if ('' !== $type && isset($types[ $type ]['label'])) {
			return $types[ $type ]['label'] . ' ' . __('options', 'mad-baits-bundle-builder');
		}
		return $pool_id;
	}
}
