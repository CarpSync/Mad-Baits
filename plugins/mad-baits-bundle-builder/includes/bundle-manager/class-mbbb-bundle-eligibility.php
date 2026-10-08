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
			$label = trim((string) ($variation['name'] ?? ''));
			if ('' === $label) {
				$label = sprintf(
					/* translators: %s: product name fallback */
					__('Included product', 'mad-baits-bundle-builder')
				);
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
				: sanitize_title($size);
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
