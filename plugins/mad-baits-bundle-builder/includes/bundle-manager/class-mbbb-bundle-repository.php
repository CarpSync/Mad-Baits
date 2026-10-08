<?php
/**
 * Reads and writes bundle products through WooCommerce.
 *
 * @package MadBaitsBundleBuilder
 */

defined('ABSPATH') || exit;

/**
 * Bundle repository.
 */
final class MBBB_Bundle_Repository {

	/**
	 * @var array<string, mixed>|null
	 */
	private static $catalogue_cache = null;

	/**
	 * @param array<string, mixed> $args status, type, search.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_bundles(array $args = array()) {
		$status = sanitize_key((string) ($args['status'] ?? 'all'));
		$type   = sanitize_key((string) ($args['type'] ?? 'all'));
		$search = strtolower(trim(sanitize_text_field((string) ($args['search'] ?? ''))));
		$today  = self::today();
		$rows   = array();

		foreach (self::bundle_ids() as $product_id) {
			$record = self::list_record($product_id);
			if (null === $record) {
				continue;
			}

			$config = self::get_owner_config($product_id);
			$legacy = false;
			if (null === $config) {
				$config = self::project_legacy($product_id);
				$legacy = true;
			}
			if (null === $config) {
				continue;
			}

			$effective = MBBB_Bundle_Config::effective_status($config, $today);
			if ('all' !== $status && $status !== $effective) {
				continue;
			}
			if ('all' !== $type && $type !== (string) $config['bundle_type']) {
				continue;
			}
			if ('' !== $search && false === strpos(strtolower((string) $record['name']), $search)) {
				continue;
			}

			$image_id = (int) $record['image_id'];
			$rows[]   = array(
				'id'             => $product_id,
				'name'           => (string) $record['name'],
				'image'          => $image_id && function_exists('wp_get_attachment_image_url') ? (string) wp_get_attachment_image_url($image_id, 'thumbnail') : '',
				'status_key'     => $effective,
				'status_label'   => MBBB_Bundle_Config::status_label($effective, $config, $today),
				'type_key'       => (string) $config['bundle_type'],
				'type_label'     => MBBB_Bundle_Config::type_label($config),
				'quantity_label' => $legacy || ! empty($config['legacy'])
					? (string) ($config['display']['helper_text'] ?? MBBB_Bundle_Config::choice_sentence($config))
					: MBBB_Bundle_Config::choice_sentence($config),
				'price_label'    => MBBB_Bundle_Pricing::summary($config),
				'eligible_count' => self::eligible_count_for_list($product_id, $config),
				'modified'       => get_post_modified_time(get_option('date_format') . ' ' . get_option('time_format'), false, $product_id),
				'legacy'         => $legacy || ! empty($config['legacy']),
				'edit_url'       => admin_url('admin.php?page=madbaits-bundles&action=edit&bundle_id=' . $product_id),
				'preview_url'    => 'publish' === (string) $record['post_status'] ? get_permalink($product_id) : '',
				'duplicate_url'  => wp_nonce_url(admin_url('admin.php?page=madbaits-bundles&action=duplicate&bundle_id=' . $product_id), 'mb_bundle_duplicate_' . $product_id),
				'toggle_url'     => wp_nonce_url(admin_url('admin.php?page=madbaits-bundles&action=toggle&bundle_id=' . $product_id), 'mb_bundle_toggle_' . $product_id),
				'delete_url'     => wp_nonce_url(admin_url('admin.php?page=madbaits-bundles&action=delete&bundle_id=' . $product_id), 'mb_bundle_delete_' . $product_id),
				'toggle_label'   => 'active' === $effective ? __('Disable', 'mad-baits-bundle-builder') : __('Enable', 'mad-baits-bundle-builder'),
			);
		}

		usort(
			$rows,
			static function ($a, $b) {
				return strcasecmp((string) $a['name'], (string) $b['name']);
			}
		);

		return $rows;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function editor_state($product_id) {
		$product_id = absint($product_id);
		$today      = self::today();
		$config     = $product_id > 0 ? self::get_owner_config($product_id) : null;
		$legacy     = false;
		if ($product_id > 0 && null === $config) {
			$config = self::project_legacy($product_id);
			$legacy = true;
		}
		if (null === $config) {
			$config = MBBB_Bundle_Config::defaults();
			$config['display']['helper_text'] = __('Choose any 10 bags from the ranges below.', 'mad-baits-bundle-builder');
		}

		$catalog = self::catalogue();
		$matched = MBBB_Bundle_Eligibility::matching($catalog['variations'], $config);
		$live    = MBBB_Bundle_Eligibility::purchasable($catalog['variations'], $config);
		$needed  = MBBB_Bundle_Compiler::required_count($config);
		$warning = '';
		if (! $legacy && empty($config['preserve_slots']) && $needed > 0 && count($live) > 0 && count($live) < $needed) {
			$warning = sprintf(
				/* translators: %d: in-stock product count */
				__('Only %d products are in stock. Customers can still pick the same product more than once.', 'mad-baits-bundle-builder'),
				count($live)
			);
		} elseif (! $legacy && empty($config['preserve_slots']) && count($live) < 1 && count($matched) > 0) {
			$warning = __('None of the selected products can be bought right now. Check stock before activating this bundle.', 'mad-baits-bundle-builder');
		}

		$image_id = (int) ($config['image_id'] ?? 0);
		return array(
			'product_id'       => $product_id,
			'config'           => $config,
			'image_url'        => $image_id && function_exists('wp_get_attachment_image_url') ? (string) wp_get_attachment_image_url($image_id, 'medium') : '',
			'eligible_total'   => count($matched),
			'eligible_live'    => count($live),
			'warning'          => $warning,
			'has_snapshot'     => $product_id > 0 && ! empty(get_post_meta($product_id, MBBB_Bundle_Config::SNAPSHOT_KEY, true)),
			'legacy_locked'    => $legacy || (! empty($config['preserve_slots']) && MBBB_Bundle_Config::MANAGED_BY !== ($config['managed_by'] ?? '')),
			'modified'         => $product_id > 0 ? (string) get_post_modified_time('Y-m-d H:i', false, $product_id) : '',
			'preview_names'    => array_slice(array_map(static function ($row) {
				return (string) ($row['name'] ?? '');
			}, $live), 0, 6),
			'catalog'          => $catalog,
		);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_owner_config($product_id) {
		$stored = get_post_meta(absint($product_id), MBBB_Bundle_Config::META_KEY, true);
		if (! is_array($stored)) {
			return null;
		}
		return MBBB_Bundle_Config::sanitize($stored);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	public static function project_legacy($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || ! function_exists('wc_get_product') || ! self::is_bundle_product($product_id)) {
			return null;
		}
		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product) {
			return null;
		}
		$plugin = class_exists('MBBB_Plugin') ? MBBB_Plugin::instance() : null;
		$meta   = get_post_meta($product_id, '_mbbb_deal_meta', true);
		return MBBB_Bundle_Legacy::project(
			array(
				'name'              => $product->get_name(),
				'short_description' => $product->get_short_description(),
				'image_id'          => (int) $product->get_image_id(),
				'post_status'       => $product->get_status(),
				'enabled'           => $plugin ? $plugin->is_enabled($product_id) : ('yes' === get_post_meta($product_id, '_mbbb_enabled', true)),
				'regular_price'     => class_exists('MBBB_Deal_Builder') ? MBBB_Deal_Builder::get_product_bundle_price($product) : (string) $product->get_regular_price(),
				'slots'             => $plugin ? $plugin->get_slots($product_id) : array(),
				'deal_meta'         => is_array($meta) ? $meta : array(),
			)
		);
	}

	/**
	 * @param int                              $product_id Product ID. 0 creates one.
	 * @param array<string, mixed>             $config     Owner config.
	 * @param bool                             $compile    Replace slots.
	 * @param array<int, array<string, mixed>> $live_rows  Purchasable matches.
	 * @return array{success: bool, product_id: int, errors: string[]}
	 */
	public static function persist($product_id, array $config, $compile, array $live_rows = array()) {
		$product_id = absint($product_id);
		$creating   = $product_id < 1;
		if (! function_exists('wc_get_product') || ! class_exists('WC_Product_Simple')) {
			return self::failed_save($product_id, $creating, 'WooCommerce is not available');
		}

		try {
			$config = MBBB_Bundle_Config::sanitize($config);
			if ($creating) {
				$product = new WC_Product_Simple();
				$product->set_catalog_visibility('visible');
				$product->set_sold_individually(false);
				$product->set_manage_stock(false);
				$product->set_stock_status('instock');
			} else {
				$product = wc_get_product($product_id);
				if (! $product instanceof WC_Product) {
					self::log_failure('Product ' . $product_id . ' could not be loaded for saving.');
					return array(
						'success'    => false,
						'product_id' => $product_id,
						'errors'     => array(__('That bundle could not be found.', 'mad-baits-bundle-builder')),
					);
				}
			}

			if ($compile && ! $creating) {
				self::maybe_snapshot($product_id);
			}

			$product->set_name($config['name']);
			$product->set_short_description($config['short_description']);
			$product->set_image_id((int) $config['image_id']);
			self::apply_price($product, $config);
			self::apply_post_status($product, $config);

			$enabled = 'active' === (string) $config['status'] ? 'yes' : 'no';
			$slots   = null;
			if ($compile) {
				$options = MBBB_Bundle_Eligibility::to_slot_options($live_rows);
				$slots   = MBBB_Bundle_Compiler::compile($config, $options);
			}
			if (method_exists($product, 'update_meta_data')) {
				$product->update_meta_data(MBBB_Bundle_Config::META_KEY, $config);
				$product->update_meta_data('_mbbb_enabled', $enabled);
				if (null !== $slots) {
					$product->update_meta_data('_mbbb_slots', $slots);
				}
			}

			$saved = $product->save();
			if (is_wp_error($saved)) {
				return self::failed_save($creating ? 0 : $product_id, $creating, 'Product save failed: ' . $saved->get_error_code() . ' ' . $saved->get_error_message());
			}
			$product_id = absint($saved);
			if ($product_id < 1) {
				return self::failed_save(0, true, 'Product save did not return an ID.');
			}

			update_post_meta($product_id, MBBB_Bundle_Config::META_KEY, $config);
			if (class_exists('MBBB_Plugin')) {
				$plugin   = MBBB_Plugin::instance();
				$settings = $plugin->get_settings($product_id);
				$written  = $plugin->save_product_bundle_meta($product_id, $enabled, $settings, $slots);
				if (! $written) {
					return self::failed_save($product_id, $creating, 'Bundle meta was not written for product ' . $product_id . '.');
				}
			} else {
				update_post_meta($product_id, '_mbbb_enabled', $enabled);
				if (null !== $slots) {
					update_post_meta($product_id, '_mbbb_slots', $slots);
				}
			}

			self::sync_deal_meta($product_id, $config, $compile);
			if (class_exists('MBBB_Admin_Setup')) {
				MBBB_Admin_Setup::ensure_bundle_category($product_id);
			}

			$problem = self::persistence_problem($product_id, $config, (bool) $compile, $enabled);
			if ('' !== $problem) {
				return self::failed_save($product_id, $creating, 'Product ' . $product_id . ' failed read-back: ' . $problem);
			}

			return array(
				'success'    => true,
				'product_id' => $product_id,
				'errors'     => array(),
			);
		} catch (Throwable $error) {
			return self::failed_save($product_id, $creating, $error::class . ': ' . $error->getMessage());
		}
	}

	/**
	 * Record a save failure without exposing the internal reason to the owner.
	 *
	 * @param string $message Safe diagnostic text.
	 * @return void
	 */
	public static function log_failure($message) {
		$message = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $message)));
		if ('' === $message) {
			return;
		}
		if (function_exists('mb_substr')) {
			$message = mb_substr($message, 0, 300);
		} else {
			$message = substr($message, 0, 300);
		}
		$line = 'Mad Baits Bundle Manager: ' . $message;
		if (function_exists('error_log')) {
			error_log($line);
		}
		if (class_exists('MBBB_Plugin', false)) {
			MBBB_Plugin::instance()->log($line, 'error');
		}
	}

	/**
	 * @param int $product_id Source product.
	 * @return array{success: bool, product_id: int, errors: string[]}
	 */
	public static function duplicate($product_id) {
		$product_id = absint($product_id);
		$product    = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		if (! $product instanceof WC_Product) {
			return array(
				'success'    => false,
				'product_id' => 0,
				'errors'     => array(__('That bundle could not be found.', 'mad-baits-bundle-builder')),
			);
		}

		$source = self::get_owner_config($product_id);
		if (null === $source) {
			$source = self::project_legacy($product_id);
		}
		if (null === $source) {
			$source = MBBB_Bundle_Config::defaults();
			$source['name'] = $product->get_name();
		}
		$copy  = MBBB_Bundle_Config::duplicate_config($source, $product->get_name());
		$price = (string) ($copy['pricing']['fixed_price'] ?? $product->get_regular_price());

		$new_id = 0;
		if (class_exists('MBBB_Deal_Builder')) {
			$new_id = (int) MBBB_Deal_Builder::clone_deal_product($product_id, $copy['name'], $price);
		}
		if ($new_id < 1) {
			$result = self::persist(0, $copy, empty($copy['preserve_slots']), array());
			return $result;
		}

		update_post_meta($new_id, MBBB_Bundle_Config::META_KEY, $copy);
		delete_post_meta($new_id, MBBB_Bundle_Config::SNAPSHOT_KEY);
		if (class_exists('MBBB_Plugin')) {
			$plugin = MBBB_Plugin::instance();
			$plugin->save_product_bundle_meta($new_id, 'no', $plugin->get_settings($new_id), null);
		}
		$duplicate = wc_get_product($new_id);
		if ($duplicate instanceof WC_Product) {
			$duplicate->set_name($copy['name']);
			$duplicate->set_status('draft');
			$duplicate->save();
		}

		return array(
			'success'    => true,
			'product_id' => $new_id,
			'errors'     => array(),
		);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function restore_snapshot($product_id) {
		$product_id = absint($product_id);
		$snapshot   = get_post_meta($product_id, MBBB_Bundle_Config::SNAPSHOT_KEY, true);
		if (! is_array($snapshot) || ! function_exists('wc_get_product')) {
			return false;
		}

		$product = wc_get_product($product_id);
		if (! $product instanceof WC_Product) {
			return false;
		}

		if (isset($snapshot['regular_price'])) {
			$product->set_regular_price((string) $snapshot['regular_price']);
		}
		if (! empty($snapshot['post_status'])) {
			$product->set_status(sanitize_key((string) $snapshot['post_status']));
		}
		if (! empty($snapshot['name'])) {
			$product->set_name(sanitize_text_field((string) $snapshot['name']));
		}
		$product->save();

		$enabled  = isset($snapshot['enabled']) ? (string) $snapshot['enabled'] : 'no';
		$settings = isset($snapshot['settings']) && is_array($snapshot['settings']) ? $snapshot['settings'] : array();
		$slots    = isset($snapshot['slots']) && is_array($snapshot['slots']) ? $snapshot['slots'] : array();
		if (class_exists('MBBB_Plugin')) {
			MBBB_Plugin::instance()->save_product_bundle_meta($product_id, $enabled, $settings, $slots);
		}
		if (isset($snapshot['deal_meta']) && is_array($snapshot['deal_meta'])) {
			update_post_meta($product_id, '_mbbb_deal_meta', $snapshot['deal_meta']);
		}

		$owner = self::project_legacy($product_id);
		if (is_array($owner)) {
			$owner['preserve_slots'] = true;
			$owner['managed_by']     = '';
			$owner['legacy']         = true;
			update_post_meta($product_id, MBBB_Bundle_Config::META_KEY, $owner);
		}

		return true;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function trash($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1 || ! self::is_bundle_product($product_id)) {
			return false;
		}
		return (bool) wp_trash_post($product_id);
	}

	/**
	 * Catalogue used by the editor and eligibility checks.
	 *
	 * @return array{variations: array<int, array<string, mixed>>, ranges: array<string, string>, sizes: array<string, string>, categories: array<string, string>, attributes: array<string, array{label: string, terms: array<string, string>}>, products: array<int, string>}
	 */
	public static function catalogue() {
		if (null !== self::$catalogue_cache) {
			return self::$catalogue_cache;
		}

		$empty = array(
			'variations'  => array(),
			'ranges'      => array(),
			'sizes'       => array(),
			'categories'  => array(),
			'attributes'  => array(),
			'products'    => array(),
		);
		if (! function_exists('wc_get_products')) {
			$empty['ranges'] = self::fallback_ranges();
			$empty['sizes']  = self::fallback_sizes();
			self::$catalogue_cache = $empty;
			return self::$catalogue_cache;
		}

		$ranges = self::fallback_ranges();
		$sizes  = self::fallback_sizes();
		$categories = self::category_options();
		$attributes = self::attribute_options();
		$products   = array();
		$variations = array();

		$ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 300,
				'return' => 'ids',
				'type'   => array('simple', 'variable'),
				'orderby'=> 'title',
				'order'  => 'ASC',
			)
		);

		foreach ((array) $ids as $product_id) {
			$product_id = absint($product_id);
			if ($product_id < 1 || self::is_bundle_product($product_id)) {
				continue;
			}
			$product = wc_get_product($product_id);
			if (! $product instanceof WC_Product) {
				continue;
			}
			$products[ $product_id ] = $product->get_name();
			$rows = $product->is_type('variable') ? self::variation_rows($product, $ranges) : array(self::product_row($product, $product, $ranges));
			foreach ($rows as $row) {
				$variations[] = $row;
				if (! empty($row['range_slug']) && ! isset($ranges[ $row['range_slug'] ])) {
					$ranges[ $row['range_slug'] ] = (string) ($row['range_label'] ?? $row['range_slug']);
				}
				if (! empty($row['size_slug']) && ! isset($sizes[ $row['size_slug'] ])) {
					$sizes[ $row['size_slug'] ] = (string) ($row['size_label'] ?? $row['size_slug']);
				}
			}
		}

		asort($ranges);
		asort($sizes);

		self::$catalogue_cache = array(
			'variations' => $variations,
			'ranges'     => $ranges,
			'sizes'      => $sizes,
			'categories' => $categories,
			'attributes' => $attributes,
			'products'   => $products,
		);
		return self::$catalogue_cache;
	}

	/**
	 * @return int[]
	 */
	private static function bundle_ids() {
		$ids = array();
		if (class_exists('MBBB_Deal_Builder')) {
			$ids = array_merge($ids, MBBB_Deal_Builder::get_all_deal_product_ids());
		}
		global $wpdb;
		if ($wpdb instanceof wpdb && isset($wpdb->posts, $wpdb->postmeta)) {
			$keys = array(
				MBBB_Bundle_Config::META_KEY,
				'_mbbb_enabled',
				'_mbbb_deal_meta',
				'_mbbb_slots',
			);
			$sql      = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = 'product' AND p.post_status IN ('publish','draft','private','pending','future') AND pm.meta_key IN (" . implode(', ', array_fill(0, count($keys), '%s')) . ')';
			$meta_ids = $wpdb->get_col($wpdb->prepare($sql, ...$keys));
			$ids      = array_merge($ids, array_map('absint', (array) $meta_ids));
		}
		$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
		return $ids;
	}

	/**
	 * Product fields for the admin list. Catalogue visibility is not used.
	 *
	 * @param int $product_id Product ID.
	 * @return array{name: string, post_status: string, image_id: int}|null
	 */
	private static function list_record($product_id) {
		$product_id = absint($product_id);
		$allowed    = array('publish', 'draft', 'private', 'pending', 'future');
		$product    = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		if ($product instanceof WC_Product && (int) $product->get_id() === $product_id) {
			$status = (string) $product->get_status();
			if (! in_array($status, $allowed, true)) {
				return null;
			}
			return array(
				'name'        => $product->get_name(),
				'post_status' => $status,
				'image_id'    => (int) $product->get_image_id(),
			);
		}

		$post = function_exists('get_post') ? get_post($product_id) : null;
		if (! $post instanceof WP_Post || 'product' !== $post->post_type || ! in_array($post->post_status, $allowed, true)) {
			return null;
		}
		return array(
			'name'        => (string) $post->post_title,
			'post_status' => (string) $post->post_status,
			'image_id'    => absint(get_post_meta($product_id, '_thumbnail_id', true)),
		);
	}

	/**
	 * @param int    $product_id Product ID, when one exists.
	 * @param bool   $creating   True when this request was a new bundle.
	 * @param string $reason     Internal reason, logged separately.
	 * @return array{success: bool, product_id: int, errors: string[]}
	 */
	private static function failed_save($product_id, $creating, $reason) {
		self::log_failure($reason);
		$product_id = absint($product_id);
		$missing    = $creating && ! self::product_record_exists($product_id);
		return array(
			'success'    => false,
			'product_id' => $missing ? 0 : $product_id,
			'errors'     => array(
				$missing
					? __('Bundle could not be created. No product record was saved.', 'mad-baits-bundle-builder')
					: __('Bundle could not be saved. The bundle settings were not stored.', 'mad-baits-bundle-builder'),
			),
		);
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function product_record_exists($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}
		$post = function_exists('get_post') ? get_post($product_id) : null;
		if ($post instanceof WP_Post) {
			return 'product' === $post->post_type && ! in_array($post->post_status, array('trash', 'auto-draft'), true);
		}
		$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		return $product instanceof WC_Product && (int) $product->get_id() === $product_id;
	}

	/**
	 * Confirm the product and bundle meta can be read back after save.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $config     Config that was written.
	 * @param bool                 $compile    Whether slots were written.
	 * @param string               $enabled    Expected yes|no.
	 * @return string Empty when the save can be read back.
	 */
	private static function persistence_problem($product_id, array $config, $compile, $enabled) {
		if (! self::product_record_exists($product_id)) {
			return 'no product record';
		}
		$stored = get_post_meta($product_id, MBBB_Bundle_Config::META_KEY, true);
		if (! is_array($stored)) {
			return 'owner meta missing';
		}
		$stored = MBBB_Bundle_Config::sanitize($stored);
		if ((string) $stored['name'] !== (string) $config['name']) {
			return 'owner meta name mismatch';
		}
		if ($stored['ranges'] !== $config['ranges'] || $stored['sizes'] !== $config['sizes']) {
			return 'owner meta choices mismatch';
		}
		if ((string) get_post_meta($product_id, '_mbbb_enabled', true) !== $enabled) {
			return 'enabled meta mismatch';
		}
		if ($compile && ! is_array(get_post_meta($product_id, '_mbbb_slots', true))) {
			return 'slots meta missing';
		}
		$pricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : array();
		if ('fixed' === (string) ($pricing['mode'] ?? '') && is_numeric($pricing['fixed_price'] ?? null) && (float) $pricing['fixed_price'] > 0) {
			$price = get_post_meta($product_id, '_regular_price', true);
			if (! is_numeric($price) || round((float) $price, 2) !== round((float) $pricing['fixed_price'], 2)) {
				$price = get_post_meta($product_id, '_price', true);
			}
			if (! is_numeric($price) || round((float) $price, 2) !== round((float) $pricing['fixed_price'], 2)) {
				return 'price meta mismatch';
			}
		}
		return '';
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function is_bundle_product($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return false;
		}
		$enabled = get_post_meta($product_id, '_mbbb_enabled', true);
		if ('yes' === $enabled || 'no' === $enabled) {
			$slots = get_post_meta($product_id, '_mbbb_slots', true);
			if ('yes' === $enabled || ! empty($slots)) {
				return true;
			}
		}
		if (is_array(get_post_meta($product_id, '_mbbb_deal_meta', true))) {
			return true;
		}
		if (is_array(get_post_meta($product_id, MBBB_Bundle_Config::META_KEY, true))) {
			return true;
		}
		if (class_exists('MBBB_Admin_Setup')) {
			foreach (MBBB_Admin_Setup::bundle_category_slugs() as $slug) {
				if (has_term($slug, 'product_cat', $product_id)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param WC_Product           $parent Parent product.
	 * @param array<string, string> $ranges Known ranges.
	 * @return array<int, array<string, mixed>>
	 */
	private static function variation_rows(WC_Product $parent, array $ranges) {
		$rows = array();
		if (! $parent->is_type('variable')) {
			return $rows;
		}
		$children = $parent->get_children();
		if (empty($children)) {
			$rows[] = self::product_row($parent, $parent, $ranges);
			return $rows;
		}
		foreach ($children as $child_id) {
			$variation = wc_get_product($child_id);
			if ($variation instanceof WC_Product) {
				$rows[] = self::product_row($variation, $parent, $ranges);
			}
		}
		return $rows;
	}

	/**
	 * Parent attribute options, including custom attributes that are not taxonomies.
	 *
	 * @param WC_Product $parent Parent product.
	 * @return array<string, string[]>
	 */
	private static function parent_attribute_options(WC_Product $parent) {
		$options = array();
		if (! method_exists($parent, 'get_attributes')) {
			return $options;
		}
		foreach ($parent->get_attributes() as $key => $attribute) {
			if (is_object($attribute) && method_exists($attribute, 'get_name')) {
				$name    = (string) $attribute->get_name();
				$values  = method_exists($attribute, 'get_options') ? (array) $attribute->get_options() : array();
				$is_tax  = method_exists($attribute, 'is_taxonomy') && $attribute->is_taxonomy();
				$labels  = array();
				foreach ($values as $value) {
					if ($is_tax && function_exists('get_term')) {
						$term = get_term($value);
						if ($term instanceof WP_Term) {
							$labels[] = (string) $term->name;
							continue;
						}
					}
					$labels[] = (string) $value;
				}
				$options[ $name ] = $labels;
				continue;
			}
			if (is_string($key)) {
				$options[ $key ] = array_map('strval', (array) $attribute);
			}
		}
		return $options;
	}

	/**
	 * @param WC_Product            $product Product or variation.
	 * @param WC_Product            $parent  Parent product.
	 * @param array<string, string> $ranges  Known ranges.
	 * @return array<string, mixed>
	 */
	private static function product_row(WC_Product $product, WC_Product $parent, array $ranges) {
		$raw_attributes = array();
		if ($product->is_type('variation') && method_exists($product, 'get_attributes')) {
			foreach ($product->get_attributes() as $key => $value) {
				$raw_attributes[ (string) $key ] = is_array($value) ? $value : (string) $value;
			}
		}
		$parent_attributes = self::parent_attribute_options($parent);
		foreach (array('pa_size', 'pa_range', 'pa_flavour') as $taxonomy) {
			if (! empty($parent_attributes[ $taxonomy ]) || ! function_exists('taxonomy_exists') || ! taxonomy_exists($taxonomy) || ! function_exists('wc_get_product_terms')) {
				continue;
			}
			$terms = wc_get_product_terms($parent->get_id(), $taxonomy, array('fields' => 'names'));
			if (is_array($terms) && ! is_wp_error($terms) && ! empty($terms)) {
				$parent_attributes[ $taxonomy ] = array_map('strval', $terms);
			}
		}

		$category_slugs = function_exists('wp_get_post_terms') ? wp_get_post_terms($parent->get_id(), 'product_cat', array('fields' => 'slugs')) : array();
		$category_slugs = is_array($category_slugs) && ! is_wp_error($category_slugs) ? array_map('sanitize_title', $category_slugs) : array();
		$tags = function_exists('wp_get_post_terms') ? wp_get_post_terms($parent->get_id(), 'product_tag', array('fields' => 'slugs')) : array();
		$tags = is_array($tags) && ! is_wp_error($tags) ? array_map('sanitize_title', $tags) : array();
		$meta_range = function_exists('get_post_meta') ? (string) get_post_meta($parent->get_id(), '_mad_baits_range_slug', true) : '';

		$choice = class_exists('MBBB_Bundle_Admin')
			? MBBB_Bundle_Admin::describe_catalogue_choice(
				array(
					'meta_range'          => $meta_range,
					'pa_range'            => (string) ($raw_attributes['pa_range'] ?? ''),
					'pa_flavour'          => (string) ($raw_attributes['pa_flavour'] ?? ''),
					'name'                => $parent->get_name(),
					'tags'                => $tags,
					'categories'          => $category_slugs,
					'attributes'          => $raw_attributes,
					'parent_attributes'   => $parent_attributes,
				),
				$ranges
			)
			: array(
				'range_slug' => sanitize_title($meta_range),
				'range_slugs' => array(),
				'size_slug' => '',
				'size_slugs' => array(),
				'size_label' => '',
				'formats' => array(),
				'boilie' => false,
			);

		$attributes = array();
		foreach ($raw_attributes as $key => $value) {
			if (is_array($value)) {
				$value = (string) reset($value);
			}
			$attributes[ sanitize_title((string) $key) ] = sanitize_title((string) $value);
		}
		$range_slug = (string) ($choice['range_slug'] ?? '');
		$size_slug  = (string) ($choice['size_slug'] ?? '');
		$size_label = (string) ($choice['size_label'] ?? '');
		if ('' !== $size_slug) {
			$attributes['pa_size'] = $size_slug;
		}
		if ('' !== $range_slug) {
			$attributes['pa_range'] = $range_slug;
		}
		$range_label = $ranges[ $range_slug ] ?? '';
		if ('' === $range_label && '' !== $range_slug) {
			$range_label = ucwords(str_replace('-', ' ', $range_slug));
		}

		$name = $product->is_type('variation') ? wc_get_formatted_variation($product, true, false, true) : '';
		$name = trim($parent->get_name() . ('' !== $name ? ' — ' . wp_strip_all_tags($name) : ''));

		$in_stock = $product->is_in_stock();
		return array(
			'id'              => (int) $product->get_id(),
			'parent_id'       => (int) $parent->get_id(),
			'name'            => $name,
			'parent_name'     => $parent->get_name(),
			'range_slug'      => $range_slug,
			'range_slugs'     => (array) ($choice['range_slugs'] ?? array()),
			'range_label'     => $range_label,
			'size_slug'       => $size_slug,
			'size_slugs'      => (array) ($choice['size_slugs'] ?? array()),
			'size_label'      => $size_label,
			'formats'         => array_values((array) ($choice['formats'] ?? array())),
			'boilie'          => ! empty($choice['boilie']),
			'category_slugs'  => $category_slugs,
			'attributes'      => $attributes,
			'in_stock'        => $in_stock,
			'purchasable'     => $product->is_purchasable() && $in_stock,
			'price'           => (float) $product->get_price(),
			'image'           => (string) wp_get_attachment_image_url((int) ($product->get_image_id() ?: $parent->get_image_id()), 'thumbnail'),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function fallback_ranges() {
		$ranges = array();
		if (function_exists('mad_baits_catalogue_get_ranges')) {
			foreach (mad_baits_catalogue_get_ranges() as $definition) {
				if (! is_array($definition)) {
					continue;
				}
				$slug = sanitize_title((string) ($definition['slug'] ?? ''));
				if (isset($definition['storefront']) && false === $definition['storefront']) {
					continue;
				}
				if ('' !== $slug) {
					$ranges[ $slug ] = (string) ($definition['label'] ?? $slug);
				}
			}
		}
		if (class_exists('MBBB_Deal_Builder')) {
			foreach (MBBB_Deal_Builder::get_boilie_ranges() as $slug => $label) {
				$ranges[ sanitize_title((string) $slug) ] = (string) $label;
			}
		}
		if (taxonomy_exists('pa_flavour')) {
			$terms = get_terms(array('taxonomy' => 'pa_flavour', 'hide_empty' => false));
			if (is_array($terms)) {
				foreach ($terms as $term) {
					if ($term instanceof WP_Term) {
						$ranges[ $term->slug ] = $term->name;
					}
				}
			}
		}
		asort($ranges);
		return $ranges;
	}

	/**
	 * @return array<string, string>
	 */
	private static function fallback_sizes() {
		$sizes = array();
		if (taxonomy_exists('pa_size')) {
			$terms = get_terms(array('taxonomy' => 'pa_size', 'hide_empty' => false));
			if (is_array($terms)) {
				foreach ($terms as $term) {
					if ($term instanceof WP_Term) {
						$sizes[ $term->slug ] = $term->name;
					}
				}
			}
		}
		asort($sizes);
		return $sizes;
	}

	/**
	 * @return array<string, string>
	 */
	private static function category_options() {
		$out = array();
		if (! taxonomy_exists('product_cat')) {
			return $out;
		}
		$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
		if (! is_array($terms)) {
			return $out;
		}
		foreach ($terms as $term) {
			if ($term instanceof WP_Term && ! in_array($term->slug, array('bundles-deals', 'bundle-deals'), true)) {
				$out[ $term->slug ] = $term->name;
			}
		}
		asort($out);
		return $out;
	}

	/**
	 * @return array<string, array{label: string, terms: array<string, string>}>
	 */
	private static function attribute_options() {
		$out = array();
		if (! function_exists('wc_get_attribute_taxonomies')) {
			return $out;
		}
		foreach (wc_get_attribute_taxonomies() as $attribute) {
			$taxonomy = 'pa_' . sanitize_title((string) $attribute->attribute_name);
			if (in_array($taxonomy, array('pa_size', 'pa_range', 'pa_flavour'), true) || ! taxonomy_exists($taxonomy)) {
				continue;
			}
			$terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
			if (! is_array($terms) || empty($terms)) {
				continue;
			}
			$options = array();
			foreach ($terms as $term) {
				if ($term instanceof WP_Term) {
					$options[ $term->slug ] = $term->name;
				}
			}
			if (! empty($options)) {
				$out[ $taxonomy ] = array(
					'label' => (string) $attribute->attribute_label,
					'terms' => $options,
				);
			}
		}
		return $out;
	}

	/**
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $config     Config.
	 * @return int|null
	 */
	private static function eligible_count_for_list($product_id, array $config) {
		if (empty($config['preserve_slots']) && MBBB_Bundle_Config::MANAGED_BY === ($config['managed_by'] ?? '')) {
			$catalog = self::catalogue();
			return count(MBBB_Bundle_Eligibility::purchasable($catalog['variations'], $config));
		}
		if (! class_exists('MBBB_Plugin')) {
			return null;
		}
		$slots = MBBB_Plugin::instance()->get_slots($product_id);
		$count = 0;
		foreach ($slots as $slot) {
			if (! is_array($slot)) {
				continue;
			}
			$options = isset($slot['manual_options']) && is_array($slot['manual_options']) ? $slot['manual_options'] : array();
			$count   = max($count, count($options));
		}
		return $count > 0 ? $count : null;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private static function maybe_snapshot($product_id) {
		$existing = get_post_meta($product_id, MBBB_Bundle_Config::SNAPSHOT_KEY, true);
		if (is_array($existing) && ! empty($existing)) {
			return;
		}
		$product = wc_get_product($product_id);
		$plugin  = class_exists('MBBB_Plugin') ? MBBB_Plugin::instance() : null;
		$deal    = get_post_meta($product_id, '_mbbb_deal_meta', true);
		update_post_meta(
			$product_id,
			MBBB_Bundle_Config::SNAPSHOT_KEY,
			array(
				'slots'         => $plugin ? $plugin->get_slots($product_id) : get_post_meta($product_id, '_mbbb_slots', true),
				'settings'      => $plugin ? $plugin->get_settings($product_id) : array(),
				'enabled'       => $plugin && $plugin->is_enabled($product_id) ? 'yes' : (string) get_post_meta($product_id, '_mbbb_enabled', true),
				'deal_meta'     => is_array($deal) ? $deal : array(),
				'regular_price' => $product instanceof WC_Product ? (string) $product->get_regular_price() : '',
				'post_status'   => $product instanceof WC_Product ? $product->get_status() : '',
				'name'          => $product instanceof WC_Product ? $product->get_name() : '',
			)
		);
	}

	/**
	 * @param WC_Product           $product Product.
	 * @param array<string, mixed> $config  Config.
	 * @return void
	 */
	private static function apply_price(WC_Product $product, array $config) {
		$pricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : array();
		if ('fixed' !== (string) ($pricing['mode'] ?? 'fixed')) {
			return;
		}
		$next = (string) ($pricing['fixed_price'] ?? '');
		if ('' === $next || ! is_numeric($next) || (float) $next <= 0) {
			return;
		}
		if (! empty($config['preserve_slots'])) {
			$current = $product->get_price('edit');
			if (is_numeric($current) && round((float) $current, 2) === round((float) $next, 2)) {
				return;
			}
		}
		$product->set_regular_price($next);
		$product->set_sale_price('');
		$product->set_price($next);
	}

	/**
	 * @param WC_Product           $product Product.
	 * @param array<string, mixed> $config  Config.
	 * @return void
	 */
	private static function apply_post_status(WC_Product $product, array $config) {
		$today = function_exists('current_time') ? (string) current_time('Y-m-d') : gmdate('Y-m-d');
		$state = MBBB_Bundle_Config::publication_state($config, $today);
		$product->set_status($state['post_status']);
		$product->set_catalog_visibility($state['catalog_visibility']);
	}

	/**
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $config     Config.
	 * @param bool                 $compile    Whether slots were replaced.
	 * @return void
	 */
	private static function sync_deal_meta($product_id, array $config, $compile) {
		$existing = get_post_meta($product_id, '_mbbb_deal_meta', true);
		$existing = is_array($existing) ? $existing : array();
		$existing['badge_label']       = (string) ($config['display']['badge'] ?? '');
		$existing['admin_description'] = (string) ($config['short_description'] ?? '');
		if ($compile) {
			$existing['build_mode']  = 'bundle-manager';
			$existing['template_id'] = 'bundle-manager';
			$existing['deal_type']   = 'fixed' === ($config['bundle_type'] ?? '') ? 'fixed_bundle' : 'mix_and_match';
			$existing['boilie_ranges'] = (array) ($config['ranges'] ?? array());
		}
		update_post_meta($product_id, '_mbbb_deal_meta', $existing);
		if (class_exists('MBBB_Deal_Builder') && method_exists('MBBB_Deal_Builder', 'apply_badge_tag')) {
			// Badge tags stay optional and are handled by the existing deal builder when public.
		}
	}

	/**
	 * @return string
	 */
	private static function today() {
		if (function_exists('current_time')) {
			return (string) current_time('Y-m-d');
		}
		return gmdate('Y-m-d');
	}
}
