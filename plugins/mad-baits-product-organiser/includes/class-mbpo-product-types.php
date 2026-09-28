<?php
/**
 * Product type classification for range sections (category → pa_type → name → tags).
 *
 * @package MadBaitsProductOrganiser
 */

defined('ABSPATH') || exit;

class MBPO_Product_Types {
	const CACHE_META_KEY    = '_mbpo_product_type';
	const CACHE_LABEL_KEY   = '_mbpo_product_type_label';
	const CACHE_SECTION_KEY = '_mbpo_product_section';
	const CACHE_REASON_KEY  = '_mbpo_product_type_reason';

	/**
	 * Type rules in strict priority order (first match wins per layer).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_type_rules() {
		return array(
			array(
				'key'          => 'bundles-deals',
				'label'        => 'BUNDLE',
				'section'      => 'bundles-deals',
				'needles'      => array('bundle', 'session pack', 'ultimate trip', '50kg', ' deal', 'deals', 'multibuy', 'multi buy'),
				'cat_slugs'    => array('bundles-deals', 'bundle-deals', 'deals', 'bundles'),
				'tag_slugs'    => array('bundle', 'bundles', 'deals', 'session-pack'),
				'attr_slugs'   => array('bundle', 'bundles-deals', 'deal', 'deals'),
			),
			array(
				'key'          => 'hardened_hookbaits',
				'label'        => 'HARDENED HOOKBAITS',
				'section'      => 'hardened-hookbaits',
				'needles'      => array('hardened hookbait', 'hardened hook bait', 'skinz hardened', 'hard hooker', 'hard hookbait'),
				'cat_slugs'    => array('hardened-hookbaits', 'hardened-hookbait'),
				'tag_slugs'    => array('hardened-hookbaits', 'hardened-hookbait', 'hard-hooker'),
				'attr_slugs'   => array('hardened-hookbaits', 'hardened-hookbait', 'hard-hooker'),
			),
			array(
				'key'          => 'pop-ups',
				'label'        => 'POP UPS',
				'section'      => 'pop-ups',
				'needles'      => array('pop up', 'pop-up', 'popup', 'pop ups', 'pop-ups'),
				'cat_slugs'    => array('pop-ups', 'popups', 'pop-up'),
				'tag_slugs'    => array('pop-ups', 'popups', 'pop-up'),
				'attr_slugs'   => array('pop-ups', 'pop-up', 'popup'),
			),
			array(
				'key'          => 'paste',
				'label'        => 'PASTE',
				'section'      => 'paste',
				'needles'      => array(),
				'name_regex'   => '/\bpaste\b/i',
				'cat_slugs'    => array('paste'),
				'tag_slugs'    => array('paste'),
				'attr_slugs'   => array('paste'),
			),
			array(
				'key'          => 'wafters',
				'label'        => 'WAFTERS',
				'section'      => 'wafters',
				'needles'      => array('wafter', 'wafters'),
				'cat_slugs'    => array('wafters', 'skinz-wafters', 'wafter'),
				'tag_slugs'    => array('wafters', 'wafter', 'skinz-wafters'),
				'attr_slugs'   => array('wafters', 'wafter'),
			),
			array(
				'key'          => 'boilies',
				'label'        => 'BOILIES',
				'section'      => 'boilies',
				'needles'      => array('boilie', 'boilies', 'shelf life', 'shelf-life', 'freezer bait', 'freezer boilie', 'freezer boilies'),
				'cat_slugs'    => array('boilies', 'boilie', 'freezer-baits', 'shelf-life', 'shelf-life-boilies'),
				'tag_slugs'    => array('boilies', 'boilie', 'shelf-life', 'freezer-baits'),
				'attr_slugs'   => array('boilies', 'boilie', 'shelf-life', 'freezer-bait'),
			),
			array(
				'key'          => 'pellets',
				'label'        => 'PELLETS',
				'section'      => 'pellets',
				'needles'      => array('pellet', 'pellets'),
				'cat_slugs'    => array('pellets', 'pellet'),
				'tag_slugs'    => array('pellets', 'pellet'),
				'attr_slugs'   => array('pellets', 'pellet'),
			),
			array(
				'key'          => 'liquids',
				'label'        => 'LIQUID',
				'section'      => 'liquids',
				'needles'      => array('liquid', 'food dip', 'liquid food', ' glug', 'glug', ' dip', 'dips', 'soak', 'syrup'),
				'cat_slugs'    => array('liquids', 'liquid-foods', 'dips', 'liquid-additives', 'liquid'),
				'tag_slugs'    => array('liquids', 'liquid', 'dips', 'glug'),
				'attr_slugs'   => array('liquids', 'liquid', 'dip', 'dips', 'glug'),
				'block_if'     => array('boilies', 'pellets'),
			),
			array(
				'key'          => 'hookbaits',
				'label'        => 'HOOKBAITS',
				'section'      => 'hookbaits',
				'needles'      => array('hookbait', 'hook bait', 'skinz', 'dumbell', 'dumbbell'),
				'cat_slugs'    => array('hookbaits', 'hookbait', 'hook-baits'),
				'tag_slugs'    => array('hookbaits', 'hookbait', 'skinz'),
				'attr_slugs'   => array('hookbaits', 'hookbait'),
			),
		);
	}

	/**
	 * @return string[]
	 */
	public static function get_match_layers() {
		return array('category', 'attribute', 'name', 'tag');
	}

	/**
	 * Clear all cached type labels.
	 *
	 * @return int Rows cleared (approximate).
	 */
	public static function clear_all_caches() {
		global $wpdb;

		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s)",
				self::CACHE_META_KEY,
				self::CACHE_LABEL_KEY,
				self::CACHE_SECTION_KEY,
				self::CACHE_REASON_KEY
			)
		);
	}

	/**
	 * Build product classification context.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function build_context($product_id) {
		$product_id = absint($product_id);
		$name       = ' ' . strtolower((string) get_the_title($product_id)) . ' ';

		$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		$cat_slugs = is_array($cat_slugs) ? array_values(array_unique(array_map('sanitize_title', array_map('strval', $cat_slugs)))) : array();

		$tag_slugs = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'slugs'));
		$tag_slugs = is_array($tag_slugs) ? array_values(array_unique(array_map('sanitize_title', array_map('strval', $tag_slugs)))) : array();

		$attr_slugs = array();
		if (taxonomy_exists('pa_type')) {
			$type_terms = wp_get_post_terms($product_id, 'pa_type');
			if (is_array($type_terms) && ! is_wp_error($type_terms)) {
				foreach ($type_terms as $term) {
					if (! $term instanceof WP_Term) {
						continue;
					}
					$attr_slugs[] = sanitize_title((string) $term->slug);
					$attr_slugs[] = sanitize_title((string) $term->name);
				}
			}
		}
		$attr_slugs = array_values(array_unique(array_filter($attr_slugs)));

		return array(
			'product_id' => $product_id,
			'name'       => $name,
			'cat_slugs'  => $cat_slugs,
			'tag_slugs'  => $tag_slugs,
			'attr_slugs' => $attr_slugs,
		);
	}

	/**
	 * @param int  $product_id Product ID.
	 * @param bool $use_cache  Use post meta cache.
	 * @return string Type key.
	 */
	public static function resolve_type_key($product_id, $use_cache = true) {
		$result = self::resolve_with_reason($product_id, $use_cache);
		return (string) ($result['type_key'] ?? '');
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function resolve_section_key($product_id) {
		$cached = get_post_meta(absint($product_id), self::CACHE_SECTION_KEY, true);
		if (is_string($cached) && '' !== $cached) {
			return sanitize_key($cached);
		}

		$result = self::resolve_with_reason($product_id, true);
		return sanitize_key((string) ($result['section_key'] ?? ''));
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_display_label($product_id) {
		$product_id = absint($product_id);
		$cached     = get_post_meta($product_id, self::CACHE_LABEL_KEY, true);
		if (is_string($cached) && '' !== $cached) {
			return $cached;
		}

		$result = self::resolve_with_reason($product_id, true);
		if ('' !== ($result['label'] ?? '')) {
			return (string) $result['label'];
		}

		return __('Uncategorised', 'mad-baits-product-organiser');
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_classification_reason($product_id) {
		$cached = get_post_meta(absint($product_id), self::CACHE_REASON_KEY, true);
		if (is_string($cached) && '' !== $cached) {
			return $cached;
		}

		$result = self::resolve_with_reason($product_id, true);
		return (string) ($result['reason'] ?? '');
	}

	/**
	 * @param int  $product_id Product ID.
	 * @param bool $use_cache  Use cache.
	 * @return array{type_key: string, section_key: string, label: string, reason: string}
	 */
	public static function resolve_with_reason($product_id, $use_cache = true) {
		$empty = array(
			'type_key'    => '',
			'section_key' => '',
			'label'       => '',
			'reason'      => '',
		);

		$product_id = absint($product_id);
		if ($product_id < 1) {
			return $empty;
		}

		if ($use_cache) {
			$cached_type = get_post_meta($product_id, self::CACHE_META_KEY, true);
			if (is_string($cached_type) && '' !== $cached_type) {
				return array(
					'type_key'    => sanitize_key($cached_type),
					'section_key' => sanitize_key((string) get_post_meta($product_id, self::CACHE_SECTION_KEY, true)),
					'label'       => (string) get_post_meta($product_id, self::CACHE_LABEL_KEY, true),
					'reason'      => (string) get_post_meta($product_id, self::CACHE_REASON_KEY, true),
				);
			}
		}

		$context = self::build_context($product_id);

		foreach (self::get_match_layers() as $layer) {
			foreach (self::get_type_rules() as $rule) {
				if (self::is_rule_blocked($rule, $context)) {
					continue;
				}

				$match = self::rule_matches_layer($rule, $layer, $context);
				if (! $match) {
					continue;
				}

				$type_key    = sanitize_key((string) $rule['key']);
				$section_key = sanitize_key((string) ($rule['section'] ?? $type_key));
				$label       = (string) ($rule['label'] ?? strtoupper($type_key));
				$reason      = sprintf(
					'%s: matched %s (%s)',
					ucfirst($layer),
					$label,
					$match
				);

				update_post_meta($product_id, self::CACHE_META_KEY, $type_key);
				update_post_meta($product_id, self::CACHE_SECTION_KEY, $section_key);
				update_post_meta($product_id, self::CACHE_LABEL_KEY, $label);
				update_post_meta($product_id, self::CACHE_REASON_KEY, $reason);

				return array(
					'type_key'    => $type_key,
					'section_key' => $section_key,
					'label'       => $label,
					'reason'      => $reason,
				);
			}
		}

		// Freezer shelf-life boilie fallback on name when only "freezer" appears (not paste).
		if (self::name_has_freezer_boilie_signal($context['name'])) {
			$reason = 'Name: matched BOILIES (freezer shelf-life signal)';
			update_post_meta($product_id, self::CACHE_META_KEY, 'boilies');
			update_post_meta($product_id, self::CACHE_SECTION_KEY, 'boilies');
			update_post_meta($product_id, self::CACHE_LABEL_KEY, 'BOILIES');
			update_post_meta($product_id, self::CACHE_REASON_KEY, $reason);

			return array(
				'type_key'    => 'boilies',
				'section_key' => 'boilies',
				'label'       => 'BOILIES',
				'reason'      => $reason,
			);
		}

		update_post_meta($product_id, self::CACHE_META_KEY, '');
		update_post_meta($product_id, self::CACHE_SECTION_KEY, '');
		update_post_meta($product_id, self::CACHE_LABEL_KEY, '');
		update_post_meta($product_id, self::CACHE_REASON_KEY, '');

		return $empty;
	}

	/**
	 * @param array<string, mixed> $rule    Rule config.
	 * @param array<string, mixed> $context Product context.
	 * @return bool
	 */
	private static function is_rule_blocked(array $rule, array $context) {
		$rule_key = sanitize_key((string) ($rule['key'] ?? ''));
		if ('liquids' !== $rule_key) {
			if ('wafters' === $rule_key && self::context_has_type_signal('hardened_hookbaits', $context)) {
				return true;
			}
			if ('hookbaits' === $rule_key && (self::context_has_type_signal('hardened_hookbaits', $context) || self::context_has_type_signal('wafters', $context))) {
				return true;
			}
			return false;
		}

		foreach ((array) ($rule['block_if'] ?? array('boilies', 'pellets')) as $blocked_type) {
			if (self::context_has_type_signal((string) $blocked_type, $context)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string               $type_key Rule key.
	 * @param array<string, mixed> $context  Context.
	 * @return bool
	 */
	private static function context_has_type_signal($type_key, array $context) {
		foreach (self::get_type_rules() as $rule) {
			if (sanitize_key((string) ($rule['key'] ?? '')) !== sanitize_key($type_key)) {
				continue;
			}
			foreach (self::get_match_layers() as $layer) {
				$match = self::rule_matches_layer($rule, $layer, $context);
				if ($match) {
					return true;
				}
			}
			if ('boilies' === sanitize_key($type_key) && self::name_has_freezer_boilie_signal($context['name'])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $rule    Rule.
	 * @param string               $layer   Layer key.
	 * @param array<string, mixed> $context Context.
	 * @return string|false Match detail or false.
	 */
	private static function rule_matches_layer(array $rule, $layer, array $context) {
		$rule_key = sanitize_key((string) ($rule['key'] ?? ''));

		switch ($layer) {
			case 'category':
				$haystack = (array) ($context['cat_slugs'] ?? array());
				$slug_key = 'cat_slugs';
				break;
			case 'attribute':
				$haystack = (array) ($context['attr_slugs'] ?? array());
				$slug_key = 'attr_slugs';
				break;
			case 'name':
				$haystack = array((string) ($context['name'] ?? ''));
				$slug_key = 'needles';
				break;
			case 'tag':
				$haystack = (array) ($context['tag_slugs'] ?? array());
				$slug_key = 'tag_slugs';
				break;
			default:
				return false;
		}

		if ('name' === $layer) {
			if (! empty($rule['name_regex']) && is_string($rule['name_regex']) && preg_match($rule['name_regex'], (string) $context['name'])) {
				if ('paste' === $rule_key && false !== strpos((string) $context['name'], 'hardened')) {
					return false;
				}
				return 'name regex';
			}

			foreach ((array) ($rule['needles'] ?? array()) as $needle) {
				$needle = strtolower(trim((string) $needle));
				if ('' === $needle) {
					continue;
				}
				$name = (string) $context['name'];
				$pad  = false !== strpos($needle, ' ') ? $needle : ' ' . $needle . ' ';
				if (false !== strpos($name, $pad)) {
					if ('wafters' === $rule_key && false !== strpos($name, 'hardened')) {
						continue;
					}
					return 'name "' . $needle . '"';
				}
			}

			return false;
		}

		foreach ((array) ($rule[ $slug_key ] ?? array()) as $slug) {
			$slug = sanitize_title((string) $slug);
			if ('' === $slug) {
				continue;
			}
			if (in_array($slug, $haystack, true)) {
				return $layer . ' slug "' . $slug . '"';
			}
		}

		return false;
	}

	/**
	 * @param string $name Product name with padding spaces.
	 * @return bool
	 */
	private static function name_has_freezer_boilie_signal($name) {
		if (false === strpos($name, 'freezer')) {
			return false;
		}
		if (preg_match('/\bpaste\b/i', $name)) {
			return false;
		}
		return false !== strpos($name, 'boilie') || false !== strpos($name, 'shelf life') || false !== strpos($name, 'shelf-life');
	}
}
