<?php
/**
 * Product tag cleanup: remove product-type tags, keep range + marketing tags.
 *
 * @package MadBaitsProductOrganiser
 */

defined('ABSPATH') || exit;

class MBPO_Product_Tags {
	/**
	 * Product-type tag slugs/names to strip from products.
	 *
	 * @return string[]
	 */
	public static function get_product_type_tag_slugs() {
		return array(
			'boilies',
			'boilie',
			'pellets',
			'pellet',
			'liquids',
			'liquid',
			'pop-ups',
			'pop-up',
			'popup',
			'pop-ups',
			'pop-ups',
			'wafters',
			'wafter',
			'paste',
			'terminal',
			'terminal-tackle',
			'clothing',
			'apparel',
			'bundles',
			'bundle',
			'deals',
			'hookbaits',
			'hookbait',
			'skinz',
			'dips',
			'glug',
			'feed',
			'particle',
			'sprays',
			'spray',
			'bag-mix',
			'groundbait',
		);
	}

	/**
	 * Range tag slugs to always keep.
	 *
	 * @return string[]
	 */
	public static function get_range_tag_slugs() {
		$slugs = array(
			'asbo',
			'pandemic',
			'p-fish',
			'p-fish-2',
			'pfish',
			'nutz-plus',
			'nutzplus',
			'wicked-white',
			'wicked-whites',
			'bbb',
			'nutz-banana',
			'stp',
			'swan-mussel',
			'nutzbanana',
			'compulsive-angler',
			'compulsive',
			'calamino',
			'terminal',
		);

		if (class_exists('MBPO_Ranges')) {
			foreach (array_keys(MBPO_Ranges::get_range_catalog()) as $slug) {
				$slugs[] = sanitize_title((string) $slug);
			}
			foreach (MBPO_Ranges::get_range_aliases() as $aliases) {
				foreach ((array) $aliases as $alias) {
					$slugs[] = sanitize_title((string) $alias);
				}
			}
		}

		if (function_exists('mad_baits_get_range_tag_catalog')) {
			foreach (array_keys(mad_baits_get_range_tag_catalog()) as $slug) {
				$slugs[] = sanitize_title((string) $slug);
			}
		}

		return array_values(array_unique(array_filter($slugs)));
	}

	/**
	 * Marketing / search tag slugs to keep.
	 *
	 * @return string[]
	 */
	public static function get_marketing_tag_slugs() {
		return array(
			'best-seller',
			'best-sellers',
			'big-carp',
			'winter-carp-fishing',
			'high-attraction',
			'high-leakage',
			'cold-water',
			'food-signal',
			'nut-based',
			'fishmeal',
			'session-ready',
			'long-session',
			'confidence-bait',
		);
	}

	/**
	 * Tags that must never be removed.
	 *
	 * @return string[]
	 */
	public static function get_protected_tag_slugs() {
		return array_values(
			array_unique(
				array_merge(
					self::get_range_tag_slugs(),
					self::get_marketing_tag_slugs(),
					array('team-pick', 'team-picks', 'staff-pick', 'new', 'sale', 'featured')
				)
			)
		);
	}

	/**
	 * @param WP_Term $term Tag term.
	 * @return bool
	 */
	public static function is_product_type_tag(WP_Term $term) {
		$slug = sanitize_title((string) $term->slug);
		$name = sanitize_title((string) $term->name);

		foreach (self::get_product_type_tag_slugs() as $type_slug) {
			$type_slug = sanitize_title((string) $type_slug);
			if ($slug === $type_slug || $name === $type_slug) {
				return true;
			}
		}

		$type_names = array(
			'boilies',
			'pellets',
			'liquids',
			'liquid',
			'pop ups',
			'pop-ups',
			'wafters',
			'paste',
			'terminal',
			'clothing',
			'bundles',
			'hookbaits',
		);
		$label = strtolower(trim((string) $term->name));
		foreach ($type_names as $type_name) {
			if ($label === $type_name || $label === str_replace('-', ' ', $type_name)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param WP_Term $term Tag term.
	 * @return bool
	 */
	public static function is_protected_tag(WP_Term $term) {
		$slug = sanitize_title((string) $term->slug);
		if (in_array($slug, self::get_protected_tag_slugs(), true)) {
			return true;
		}

		if (class_exists('MBPO_Ranges')) {
			$normalized = MBPO_Ranges::normalize_range_slug($slug);
			if (in_array($normalized, self::get_range_tag_slugs(), true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split product tags into remove / keep buckets.
	 *
	 * @param int $product_id Product ID.
	 * @return array{remove: string[], keep: string[], current: string[]}
	 */
	public static function analyse_product_tags($product_id) {
		$product_id = absint($product_id);
		$terms      = wp_get_post_terms($product_id, 'product_tag');
		if (is_wp_error($terms) || ! is_array($terms)) {
			$terms = array();
		}

		$current = array();
		$remove  = array();
		$keep    = array();

		foreach ($terms as $term) {
			if (! $term instanceof WP_Term) {
				continue;
			}
			$label = (string) $term->name;
			$current[] = $label;

			if (self::is_protected_tag($term)) {
				$keep[] = $label;
				continue;
			}

			if (self::is_product_type_tag($term)) {
				$remove[] = $label;
				continue;
			}

			$keep[] = $label;
		}

		return array(
			'current' => $current,
			'remove'  => array_values(array_unique($remove)),
			'keep'    => array_values(array_unique($keep)),
		);
	}

	/**
	 * Human labels for marketing tag slugs.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function get_marketing_tag_labels() {
		return array(
			'best-seller'          => 'Best Seller',
			'best-sellers'         => 'Best Sellers',
			'big-carp'             => 'Big Carp',
			'winter-carp-fishing'  => 'Winter Carp Fishing',
			'high-attraction'      => 'High Attraction',
			'high-leakage'         => 'High Leakage',
			'cold-water'           => 'Cold Water',
			'food-signal'          => 'Food Signal',
			'nut-based'            => 'Nut Based',
			'fishmeal'             => 'Fishmeal',
			'session-ready'        => 'Session Ready',
			'long-session'         => 'Long Session',
			'confidence-bait'      => 'Confidence Bait',
		);
	}

	/**
	 * Create or fetch a product_tag term.
	 *
	 * @param string $slug Tag slug.
	 * @param string $name Tag name.
	 * @return int Term ID or 0.
	 */
	public static function ensure_tag_term($slug, $name) {
		$slug = sanitize_title((string) $slug);
		$name = trim((string) $name);
		if ('' === $slug || '' === $name) {
			return 0;
		}

		$term = get_term_by('slug', $slug, 'product_tag');
		if ($term instanceof WP_Term) {
			return (int) $term->term_id;
		}

		$result = wp_insert_term($name, 'product_tag', array('slug' => $slug));
		if (is_wp_error($result)) {
			$existing = get_term_by('slug', $slug, 'product_tag');
			return $existing instanceof WP_Term ? (int) $existing->term_id : 0;
		}

		return isset($result['term_id']) ? (int) $result['term_id'] : 0;
	}

	/**
	 * Tags available in the bulk-assign multi-select (range + marketing + safe existing).
	 *
	 * @return array<int, array{id: int, slug: string, name: string, group: string}>
	 */
	public static function get_assignable_tag_options() {
		$options = array();
		$seen    = array();

		if (class_exists('MBPO_Ranges')) {
			foreach (MBPO_Ranges::get_range_catalog() as $slug => $label) {
				$slug = sanitize_title((string) $slug);
				if ('' === $slug || isset($seen[ $slug ])) {
					continue;
				}
				$options[] = array(
					'id'    => self::ensure_tag_term($slug, (string) $label),
					'slug'  => $slug,
					'name'  => (string) $label,
					'group' => 'range',
				);
				$seen[ $slug ] = true;
			}
		}

		foreach (self::get_marketing_tag_labels() as $slug => $label) {
			$slug = sanitize_title((string) $slug);
			if ('' === $slug || isset($seen[ $slug ])) {
				continue;
			}
			$options[] = array(
				'id'    => self::ensure_tag_term($slug, (string) $label),
				'slug'  => $slug,
				'name'  => (string) $label,
				'group' => 'marketing',
			);
			$seen[ $slug ] = true;
		}

		$existing = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if (is_array($existing)) {
			foreach ($existing as $term) {
				if (! $term instanceof WP_Term) {
					continue;
				}
				$slug = sanitize_title((string) $term->slug);
				if (isset($seen[ $slug ]) || self::is_product_type_tag($term)) {
					continue;
				}
				$group = self::is_protected_tag($term) ? 'existing' : 'existing';
				if (in_array($slug, self::get_range_tag_slugs(), true)) {
					$group = 'range';
				} elseif (isset(self::get_marketing_tag_labels()[ $slug ])) {
					$group = 'marketing';
				}
				$options[] = array(
					'id'    => (int) $term->term_id,
					'slug'  => $slug,
					'name'  => (string) $term->name,
					'group' => $group,
				);
				$seen[ $slug ] = true;
			}
		}

		usort(
			$options,
			static function ($a, $b) {
				$group_order = array('range' => 0, 'marketing' => 1, 'existing' => 2);
				$ga = $group_order[ $a['group'] ] ?? 9;
				$gb = $group_order[ $b['group'] ] ?? 9;
				if ($ga !== $gb) {
					return $ga <=> $gb;
				}
				return strcasecmp((string) $a['name'], (string) $b['name']);
			}
		);

		return $options;
	}

	/**
	 * Ensure tag terms exist; return term IDs.
	 *
	 * @param int[] $tag_ids Tag term IDs from the select.
	 * @return int[]|WP_Error
	 */
	public static function resolve_tag_ids_for_assignment(array $tag_ids) {
		$resolved = array();

		foreach (array_map('absint', $tag_ids) as $tag_id) {
			if ($tag_id > 0) {
				$term = get_term($tag_id, 'product_tag');
				if ($term instanceof WP_Term && ! self::is_product_type_tag($term)) {
					$resolved[] = $tag_id;
				}
			}
		}

		return array_values(array_unique($resolved));
	}

	/**
	 * Append tags to products (merge with existing; does not remove tags).
	 *
	 * @param int[] $product_ids Product IDs.
	 * @param int[] $tag_ids     Tag term IDs to add.
	 * @return array{updated: int, skipped: int, errors: int}
	 */
	public static function bulk_assign_tags(array $product_ids, array $tag_ids) {
		$result = array(
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
		);

		$tag_ids = self::resolve_tag_ids_for_assignment($tag_ids);
		if (empty($tag_ids)) {
			return $result;
		}

		foreach ($product_ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1 || 'product' !== get_post_type($product_id)) {
				++$result['skipped'];
				continue;
			}

			$current = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
			if (is_wp_error($current)) {
				++$result['errors'];
				continue;
			}

			$merged = array_values(array_unique(array_merge(array_map('absint', (array) $current), $tag_ids)));
			$set    = wp_set_object_terms($product_id, $merged, 'product_tag', false);

			if (is_wp_error($set)) {
				++$result['errors'];
				continue;
			}

			++$result['updated'];
		}

		return $result;
	}

	/**
	 * @param int  $limit               Max products (0 = all).
	 * @param bool $only_needs_cleanup  Only products with type tags to remove.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_preview_rows($limit = 500, $only_needs_cleanup = true) {
		if (! function_exists('wc_get_products')) {
			return array();
		}

		$query_args = array(
			'status' => 'publish',
			'limit'  => $limit > 0 ? $limit : -1,
			'type'   => array('simple', 'variable', 'grouped', 'external'),
		);

		if (function_exists('mad_baits_supplier_wc_get_product_ids')) {
			$ids = mad_baits_supplier_wc_get_product_ids($query_args);
		} else {
			$query_args['return'] = 'ids';
			$ids                  = wc_get_products($query_args);
		}

		$rows = array();
		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1) {
				continue;
			}

			$analysis = self::analyse_product_tags($product_id);
			if ($only_needs_cleanup && empty($analysis['remove'])) {
				continue;
			}

			$rows[] = array(
				'id'      => $product_id,
				'name'    => get_the_title($product_id),
				'current' => $analysis['current'],
				'remove'  => $analysis['remove'],
				'keep'    => $analysis['keep'],
			);
		}

		return $rows;
	}

	/**
	 * @param int[] $product_ids Optional subset; empty = all with removable tags.
	 * @return array{updated: int, skipped: int, errors: int}
	 */
	public static function apply_cleanup(array $product_ids = array()) {
		$result = array(
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
		);

		if (empty($product_ids)) {
			$rows = self::build_preview_rows(0);
			foreach ($rows as $row) {
				$product_ids[] = (int) $row['id'];
			}
		}

		foreach ($product_ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1) {
				++$result['skipped'];
				continue;
			}

			$terms = wp_get_post_terms($product_id, 'product_tag');
			if (is_wp_error($terms) || ! is_array($terms)) {
				++$result['errors'];
				continue;
			}

			$keep_ids = array();
			foreach ($terms as $term) {
				if (! $term instanceof WP_Term) {
					continue;
				}
				if (self::is_protected_tag($term) || ! self::is_product_type_tag($term)) {
					$keep_ids[] = (int) $term->term_id;
				}
			}

			$keep_ids = array_values(array_unique(array_filter($keep_ids)));
			$set      = wp_set_object_terms($product_id, $keep_ids, 'product_tag', false);

			if (is_wp_error($set)) {
				++$result['errors'];
				continue;
			}

			++$result['updated'];
		}

		return $result;
	}
}
