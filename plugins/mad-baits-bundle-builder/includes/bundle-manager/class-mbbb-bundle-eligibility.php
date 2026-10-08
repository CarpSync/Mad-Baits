<?php
/**
 * Decides which products a customer may pick inside a bundle.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Eligibility matching against a plain catalogue.
 */
final class MBBB_Bundle_Eligibility {

	/**
	 * @param array<int, array<string, mixed>> $variations Catalogue rows.
	 * @param array<string, mixed>             $config     Owner config.
	 * @return array<int, array<string, mixed>>
	 */
	public static function matching(array $variations, array $config) {
		$matched = array();
		foreach ($variations as $variation) {
			if (! is_array($variation)) {
				continue;
			}
			if (self::matches($variation, $config)) {
				$matched[] = $variation;
			}
		}
		return $matched;
	}

	/**
	 * Rows the customer is allowed to buy after stock rules.
	 *
	 * @param array<int, array<string, mixed>> $variations Catalogue rows.
	 * @param array<string, mixed>             $config     Owner config.
	 * @return array<int, array<string, mixed>>
	 */
	public static function purchasable(array $variations, array $config) {
		$stock = isset($config['stock']) && is_array($config['stock']) ? $config['stock'] : array();
		$hide  = ! empty($stock['hide_unavailable']) || ! empty($stock['prevent_oos']);
		$out   = array();

		foreach (self::matching($variations, $config) as $variation) {
			if ($hide && ! self::row_is_purchasable($variation)) {
				continue;
			}
			$out[] = $variation;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param array<string, mixed> $config    Owner config.
	 * @return bool
	 */
	public static function matches(array $variation, array $config) {
		if ('fixed' === ($config['bundle_type'] ?? '')) {
			return self::matches_fixed_item($variation, (array) ($config['fixed_items'] ?? array()));
		}

		$ranges      = array_map('sanitize_title', (array) ($config['ranges'] ?? array()));
		$sizes       = array_map('sanitize_title', (array) ($config['sizes'] ?? array()));
		$categories  = array_map('sanitize_title', (array) ($config['categories'] ?? array()));
		$product_ids = array_map('absint', (array) ($config['product_ids'] ?? array()));
		$variation_ids = array_map('absint', (array) ($config['variation_ids'] ?? array()));
		$attributes  = isset($config['attributes']) && is_array($config['attributes']) ? $config['attributes'] : array();

		$has_filter = ! empty($ranges) || ! empty($sizes) || ! empty($categories) || ! empty($product_ids) || ! empty($variation_ids) || ! empty($attributes);
		if (! $has_filter) {
			return false;
		}

		if (! empty($ranges) && ! self::matches_any_label($variation, $ranges, array('range_slug', 'pa_range', 'pa_flavour', 'pa_bait-range'))) {
			return false;
		}
		if (! empty($sizes) && ! self::matches_sizes($variation, $sizes)) {
			return false;
		}
		$format = sanitize_key((string) ($config['bait_format'] ?? ''));
		if (in_array($format, array('shelf_life', 'freezer', 'both'), true) && ! self::matches_format($variation, $format)) {
			return false;
		}
		if (! empty($categories) && ! self::matches_categories($variation, $categories)) {
			return false;
		}
		if (! empty($product_ids)) {
			$parent = absint($variation['parent_id'] ?? 0);
			$id     = absint($variation['id'] ?? 0);
			if (! in_array($parent, $product_ids, true) && ! in_array($id, $product_ids, true)) {
				return false;
			}
		}
		if (! empty($variation_ids) && ! in_array(absint($variation['id'] ?? 0), $variation_ids, true)) {
			return false;
		}
		if (! empty($attributes) && ! self::matches_attributes($variation, $attributes)) {
			return false;
		}

		return true;
	}

	/**
	 * Products for one item group, before stock rules.
	 *
	 * A type with no extra filters includes every product of that type.
	 * Other uses the chosen products only.
	 *
	 * @param array<int, array<string, mixed>> $variations Catalogue rows.
	 * @param array<string, mixed>             $group      Item group.
	 * @param array<string, mixed>             $config     Owner config, for advanced limits.
	 * @return array<int, array<string, mixed>>
	 */
	public static function matching_group(array $variations, array $group, array $config = array()) {
		$matched = array();
		foreach ($variations as $variation) {
			if (! is_array($variation)) {
				continue;
			}
			if (self::matches_group($variation, $group, $config)) {
				$matched[] = $variation;
			}
		}
		return $matched;
	}

	/**
	 * Stock rules only. Does not re-apply range or type filters.
	 *
	 * @param array<int, array<string, mixed>> $rows   Already matched rows.
	 * @param array<string, mixed>             $config Owner config.
	 * @return array<int, array<string, mixed>>
	 */
	public static function purchasable_only(array $rows, array $config) {
		$stock = isset($config['stock']) && is_array($config['stock']) ? $config['stock'] : array();
		$hide  = ! empty($stock['hide_unavailable']) || ! empty($stock['prevent_oos']);
		if (! $hide) {
			return array_values($rows);
		}
		$out = array();
		foreach ($rows as $row) {
			if (! is_array($row) || ! self::row_is_purchasable($row)) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Matched rows, purchasable rows, and any item-group gaps.
	 *
	 * @param array<int, array<string, mixed>> $variations Catalogue rows.
	 * @param array<string, mixed>             $config     Owner config.
	 * @return array{matched: array<int, array<string, mixed>>, live: array<int, array<string, mixed>>, group_errors: string[]}
	 */
	public static function selection(array $variations, array $config) {
		$groups = isset($config['groups']) && is_array($config['groups']) ? $config['groups'] : array();
		if (empty($groups) || 'fixed' === ($config['bundle_type'] ?? '')) {
			return array(
				'matched'       => self::matching($variations, $config),
				'live'          => self::purchasable($variations, $config),
				'group_errors'  => array(),
			);
		}

		$matched = array();
		$live    = array();
		$errors  = array();
		$seen_m  = array();
		$seen_l  = array();
		foreach ($groups as $group) {
			if (! is_array($group)) {
				continue;
			}
			$rows    = self::matching_group($variations, $group, $config);
			$buyable = self::purchasable_only($rows, $config);
			if ((int) ($group['quantity'] ?? 0) < 1) {
				$errors[] = sprintf(
					/* translators: %s: product type plural */
					__('Enter how many %s the customer chooses. Use a whole number greater than zero.', 'mad-baits-bundle-builder'),
					MBBB_Bundle_Config::content_type_plural((string) ($group['type'] ?? 'other'))
				);
			} elseif (count($buyable) < 1) {
				$errors[] = self::group_gap_message($group);
			}
			foreach ($rows as $row) {
				$id = absint($row['id'] ?? 0);
				if ($id > 0 && isset($seen_m[ $id ])) {
					continue;
				}
				$seen_m[ $id ] = true;
				$matched[]     = $row;
			}
			foreach ($buyable as $row) {
				$id = absint($row['id'] ?? 0);
				if ($id > 0 && isset($seen_l[ $id ])) {
					continue;
				}
				$seen_l[ $id ] = true;
				$live[]        = $row;
			}
		}

		return array(
			'matched'      => $matched,
			'live'         => $live,
			'group_errors' => array_values(array_unique($errors)),
		);
	}

	/**
	 * @param array<string, mixed> $group Item group.
	 * @return string
	 */
	public static function group_gap_message(array $group) {
		$type   = sanitize_key((string) ($group['type'] ?? 'other'));
		$plural = MBBB_Bundle_Config::content_type_plural($type);
		if ('boilies' === $type) {
			if (! empty($group['ranges']) || ! empty($group['sizes']) || ! empty($group['product_ids'])) {
				return sprintf(
					/* translators: %s: product type plural, such as boilies */
					__('No %s are available for the selected ranges.', 'mad-baits-bundle-builder'),
					$plural
				);
			}
			return sprintf(
				/* translators: %s: product type plural */
				__('No %s are available for this item group.', 'mad-baits-bundle-builder'),
				$plural
			);
		}
		if (empty($group['product_ids'])) {
			return sprintf(
				/* translators: %s: product type plural, such as liquids */
				__('Choose which %s the customer can pick.', 'mad-baits-bundle-builder'),
				$plural
			);
		}

		return sprintf(
			/* translators: %s: product type plural */
			__('No %s are available for the selected products.', 'mad-baits-bundle-builder'),
			$plural
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $variations Purchasable rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function to_slot_options(array $variations) {
		$options = array();
		foreach ($variations as $variation) {
			$id = absint($variation['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			$label = class_exists('MBBB_Bundle_Admin') ? MBBB_Bundle_Admin::customer_choice_label($variation) : '';
			if ('' === $label) {
				$label = trim((string) ($variation['name'] ?? ''));
			}
			if ('' === $label) {
				$label = __('Included product', 'mad-baits-bundle-builder');
			}
			$options[] = array(
				'label'      => $label,
				'value'      => (string) $id,
				'product_id' => $id,
				'image'      => isset($variation['image']) ? (string) $variation['image'] : '',
				'active'     => true,
			);
		}
		return $options;
	}

	/**
	 * @param array<string, mixed> $variation Catalogue row.
	 * @return bool
	 */
	public static function row_is_purchasable(array $variation) {
		if (array_key_exists('purchasable', $variation) && empty($variation['purchasable'])) {
			return false;
		}
		if (array_key_exists('in_stock', $variation) && empty($variation['in_stock'])) {
			return false;
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param array<string, mixed> $group     Item group.
	 * @param array<string, mixed> $config    Owner config.
	 * @return bool
	 */
	private static function matches_group(array $variation, array $group, array $config) {
		$type = sanitize_key((string) ($group['type'] ?? ''));
		$sizes = array_map('sanitize_title', (array) ($group['sizes'] ?? array()));
		$sizes = array_values(array_filter($sizes));
		if ('other' === $type) {
			$ids = array_map('absint', (array) ($group['product_ids'] ?? array()));
			if (empty($ids) || ! self::row_has_id($variation, $ids)) {
				return false;
			}
			if (! empty($sizes) && ! self::matches_selected_options($variation, $sizes)) {
				return false;
			}
		} elseif ('boilies' === $type) {
			if ((string) ($variation['product_type'] ?? '') !== $type) {
				return false;
			}
			$ranges = array_map('sanitize_title', (array) ($group['ranges'] ?? array()));
			$ids    = array_map('absint', (array) ($group['product_ids'] ?? array()));
			if (! empty($ranges) && ! self::matches_any_label($variation, $ranges, array('range_slug', 'pa_range', 'pa_flavour', 'pa_bait-range'))) {
				return false;
			}
			if (! empty($sizes) && ! self::matches_sizes($variation, $sizes)) {
				return false;
			}
			$format = sanitize_key((string) ($group['bait_format'] ?? ''));
			if (in_array($format, array('shelf_life', 'freezer', 'both'), true) && ! self::matches_format($variation, $format)) {
				return false;
			}
			if (! empty($ids) && ! self::row_has_id($variation, $ids)) {
				return false;
			}
		} else {
			if ((string) ($variation['product_type'] ?? '') !== $type) {
				return false;
			}
			$ids = array_map('absint', (array) ($group['product_ids'] ?? array()));
			if (empty($ids) || ! self::row_has_id($variation, $ids)) {
				return false;
			}
			if (! empty($sizes) && ! self::matches_selected_options($variation, $sizes)) {
				return false;
			}
		}

		$categories = array_map('sanitize_title', (array) ($config['categories'] ?? array()));
		if (! empty($categories) && ! self::matches_categories($variation, $categories)) {
			return false;
		}
		$variation_ids = array_map('absint', (array) ($config['variation_ids'] ?? array()));
		if (! empty($variation_ids) && ! in_array(absint($variation['id'] ?? 0), $variation_ids, true)) {
			return false;
		}
		$attributes = isset($config['attributes']) && is_array($config['attributes']) ? $config['attributes'] : array();
		if (! empty($attributes) && ! self::matches_attributes($variation, $attributes)) {
			return false;
		}
		$products = array_map('absint', (array) ($config['product_ids'] ?? array()));
		if (! empty($products) && ! self::row_has_id($variation, $products)) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param int[]                $ids       Product or variation IDs.
	 * @return bool
	 */
	private static function row_has_id(array $variation, array $ids) {
		$parent = absint($variation['parent_id'] ?? 0);
		$id     = absint($variation['id'] ?? 0);
		return in_array($parent, $ids, true) || in_array($id, $ids, true);
	}

	/**
	 * @param array<string, mixed>                               $variation Catalogue row.
	 * @param array<int, array<string, mixed>>                   $items     Fixed items.
	 * @return bool
	 */
	private static function matches_fixed_item(array $variation, array $items) {
		$id     = absint($variation['id'] ?? 0);
		$parent = absint($variation['parent_id'] ?? 0);
		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}
			$variation_id = absint($item['variation_id'] ?? 0);
			$product_id   = absint($item['product_id'] ?? 0);
			if ($variation_id > 0 && $variation_id === $id) {
				return true;
			}
			if ($variation_id < 1 && $product_id > 0 && ($product_id === $id || $product_id === $parent)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Selected options of one kind are alternatives. Different kinds all apply.
	 *
	 * A liquid bottle size does not also have to match a bait range.
	 *
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param string[]             $selected  Selected option slugs.
	 * @return bool
	 */
	private static function matches_selected_options(array $variation, array $selected) {
		$have = array();
		foreach (class_exists('MBBB_Bundle_Admin') ? MBBB_Bundle_Admin::row_option_slugs($variation) : array() as $slug) {
			$have[ $slug ] = MBBB_Bundle_Admin::option_match_kind($slug);
		}
		$wanted = array();
		foreach ($selected as $slug) {
			$slug = sanitize_title((string) $slug);
			if ('' === $slug) {
				continue;
			}
			$kind = class_exists('MBBB_Bundle_Admin') ? MBBB_Bundle_Admin::option_match_kind($slug) : 'other';
			$wanted[ $kind ][ $slug ] = $slug;
		}
		foreach ($wanted as $kind => $slugs) {
			$hit = false;
			foreach ($have as $slug => $have_kind) {
				if ($have_kind === $kind && isset($slugs[ $slug ])) {
					$hit = true;
					break;
				}
			}
			if (! $hit) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Match boilie diameters stored on the row before falling back to labels.
	 *
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param string[]             $sizes     Selected size slugs.
	 * @return bool
	 */
	private static function matches_sizes(array $variation, array $sizes) {
		$row_sizes = array();
		foreach ((array) ($variation['size_slugs'] ?? array()) as $size) {
			$row_sizes[] = (string) $size;
		}
		if (isset($variation['size_slug'])) {
			$row_sizes[] = (string) $variation['size_slug'];
		}
		$normalised = array();
		foreach ($row_sizes as $size) {
			$slug = class_exists('MBBB_Bundle_Admin')
				? MBBB_Bundle_Admin::normalize_boilie_size($size)
				: '';
			if ('' === $slug) {
				$slug = sanitize_title($size);
			}
			if ('' !== $slug) {
				$normalised[ $slug ] = $slug;
			}
		}
		if (! empty($normalised)) {
			return ! empty(array_intersect(array_values($normalised), $sizes));
		}

		return self::matches_any_label($variation, $sizes, array('size_slug', 'pa_size'));
	}

	/**
	 * Shelf life and freezer come from the catalogue row, not from a guessed name match.
	 *
	 * An empty format means the bundle does not filter by format.
	 *
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param string               $format    shelf_life|freezer|both.
	 * @return bool
	 */
	private static function matches_format(array $variation, $format) {
		$have = array();
		foreach ((array) ($variation['formats'] ?? array()) as $one) {
			$one = sanitize_key((string) $one);
			if (in_array($one, array('shelf_life', 'freezer'), true)) {
				$have[ $one ] = $one;
			}
		}
		if ('both' === $format) {
			return ! empty($have);
		}
		return isset($have[ $format ]);
	}

	/**
	 * @param array<string, mixed> $variation Catalogue row.
	 * @param string[]             $needles   Slugs.
	 * @param string[]             $fields    Fields and attribute keys to inspect.
	 * @return bool
	 */
	private static function matches_any_label(array $variation, array $needles, array $fields) {
		$haystacks = array(
			(string) ($variation['name'] ?? ''),
			(string) ($variation['parent_name'] ?? ''),
		);
		$attributes = isset($variation['attributes']) && is_array($variation['attributes']) ? $variation['attributes'] : array();

		foreach ($fields as $field) {
			if (isset($variation[ $field ])) {
				$haystacks[] = (string) $variation[ $field ];
			}
			if (isset($attributes[ $field ])) {
				$haystacks[] = (string) $attributes[ $field ];
			}
		}

		foreach ($needles as $needle) {
			$needle = sanitize_title((string) $needle);
			if ('' === $needle) {
				continue;
			}
			foreach ($haystacks as $haystack) {
				$slug = sanitize_title((string) $haystack);
				if ($slug === $needle || self::text_contains_phrase((string) $haystack, $needle)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $variation  Catalogue row.
	 * @param string[]             $categories Category slugs.
	 * @return bool
	 */
	private static function matches_categories(array $variation, array $categories) {
		$have = array_map('sanitize_title', (array) ($variation['category_slugs'] ?? array()));
		return ! empty(array_intersect($have, $categories));
	}

	/**
	 * @param array<string, mixed>          $variation  Catalogue row.
	 * @param array<string, string[]>       $attributes Required attributes.
	 * @return bool
	 */
	private static function matches_attributes(array $variation, array $attributes) {
		$have = isset($variation['attributes']) && is_array($variation['attributes']) ? $variation['attributes'] : array();
		foreach ($attributes as $taxonomy => $terms) {
			if (! is_array($terms) || empty($terms)) {
				continue;
			}
			$current = sanitize_title((string) ($have[ $taxonomy ] ?? ''));
			$allowed = array_map('sanitize_title', $terms);
			if (! in_array($current, $allowed, true)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $text Source text.
	 * @param string $slug Slug phrase.
	 * @return bool
	 */
	private static function text_contains_phrase($text, $slug) {
		$phrase = trim(str_replace('-', ' ', strtolower($slug)));
		if ('' === $phrase) {
			return false;
		}
		$normalised = strtolower((string) preg_replace('/[^a-z0-9]+/i', ' ', $text));
		$normalised = trim((string) preg_replace('/\s+/', ' ', $normalised));
		return false !== strpos(' ' . $normalised . ' ', ' ' . $phrase . ' ');
	}
}
