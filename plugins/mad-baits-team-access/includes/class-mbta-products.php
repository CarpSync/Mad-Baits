<?php
/**
 * Private product visibility and product editor meta.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Products {
	/** @var bool */
	private static $resolving_private_ids = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
		add_action('save_post_product', array(__CLASS__, 'save_product_meta'), 10, 2);

		add_action('pre_get_posts', array(__CLASS__, 'filter_queries'));
		add_action('woocommerce_product_query', array(__CLASS__, 'woocommerce_product_query'), 10, 1);
		add_filter('woocommerce_shortcode_products_query', array(__CLASS__, 'filter_shortcode_query'), 10, 3);
		add_filter('woocommerce_blocks_product_grid_query_args', array(__CLASS__, 'filter_blocks_query'));
		add_filter('woocommerce_product_is_visible', array(__CLASS__, 'product_is_visible'), 10, 2);
		add_filter('woocommerce_related_products', array(__CLASS__, 'filter_product_ids'), 10, 2);
		add_filter('woocommerce_upsell_ids', array(__CLASS__, 'filter_product_ids'), 10, 2);
		add_filter('woocommerce_cross_sell_ids', array(__CLASS__, 'filter_product_ids'), 10, 2);
		add_filter('woocommerce_is_purchasable', array(__CLASS__, 'product_is_purchasable'), 10, 2);
		add_filter('woocommerce_variation_is_purchasable', array(__CLASS__, 'variation_is_purchasable'), 10, 2);
		add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'validate_add_to_cart'), 10, 6);
		add_action('woocommerce_check_cart_items', array(__CLASS__, 'enforce_cart_access'));

		add_action('template_redirect', array(__CLASS__, 'guard_single_product'));
		add_filter('rest_prepare_product', array(__CLASS__, 'rest_hide_private'), 10, 3);
		add_filter('rest_product_query', array(__CLASS__, 'rest_product_query'), 10, 2);
		add_filter('woocommerce_rest_prepare_product_object', array(__CLASS__, 'wc_rest_hide_private'), 10, 3);
		add_filter('woocommerce_rest_product_object_query', array(__CLASS__, 'wc_rest_product_query'), 10, 2);

		add_action('transition_post_status', array(__CLASS__, 'maybe_notify_test_bait_publish'), 10, 3);
		add_filter('wpseo_sitemap_entry', array(__CLASS__, 'exclude_from_sitemap'), 10, 3);
		add_filter('wp_sitemaps_posts_entry', array(__CLASS__, 'exclude_from_core_sitemap'), 10, 2);

		// Bundle builder options can include products from shared pools; enforce private access server-side.
		add_filter('mbbb_slot_options', array(__CLASS__, 'filter_bundle_slot_options'), 10, 3);
	}

	/**
	 * Meta box.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'mbta_product_access',
			__('Team Access', 'mad-baits-team-access'),
			array(__CLASS__, 'render_meta_box'),
			'product',
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box($post) {
		wp_nonce_field('mbta_save_product', 'mbta_product_nonce');
		$selected = get_post_meta($post->ID, '_mbta_access_groups', true);
		$selected = is_array($selected) ? $selected : array();
		$notify   = (bool) get_post_meta($post->ID, '_mbta_notify_on_publish', true);
		?>
		<p><?php esc_html_e('Override access groups (optional). Category rules still apply when empty.', 'mad-baits-team-access'); ?></p>
		<p><em><?php esc_html_e('Products with selected Team Access groups are hidden from the public shop and only visible to eligible logged-in users.', 'mad-baits-team-access'); ?></em></p>
		<?php foreach (MBTA_Roles::get_access_groups() as $key => $conf) : ?>
			<label style="display:block;margin:0.35rem 0;">
				<input type="checkbox" name="mbta_access_groups[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected, true)); ?> />
				<?php echo esc_html((string) $conf['label']); ?>
			</label>
		<?php endforeach; ?>
		<hr />
		<label>
			<input type="checkbox" name="mbta_notify_on_publish" value="1" <?php checked($notify); ?> />
			<?php esc_html_e('Notify eligible private members when published', 'mad-baits-team-access'); ?>
		</label>
		<?php
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_product_meta($post_id, $post) {
		if (! isset($_POST['mbta_product_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mbta_product_nonce'])), 'mbta_save_product')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$groups = isset($_POST['mbta_access_groups']) ? array_map('sanitize_key', (array) wp_unslash($_POST['mbta_access_groups'])) : array();
		update_post_meta($post_id, '_mbta_access_groups', array_values(array_unique($groups)));
		update_post_meta($post_id, '_mbta_notify_on_publish', ! empty($_POST['mbta_notify_on_publish']) ? '1' : '0');
	}

	/**
	 * Hide private products from WooCommerce product loops.
	 *
	 * WooCommerce 9+ passes WP_Query to woocommerce_product_query; older versions
	 * used a query-vars array. Support both shapes.
	 *
	 * @param WP_Query|array<string,mixed> $query Query or legacy query vars.
	 * @return WP_Query|array<string,mixed>
	 */
	public static function woocommerce_product_query($query) {
		if (self::$resolving_private_ids) {
			return $query;
		}

		$hidden = self::get_hidden_product_ids_for_current_user();
		if (empty($hidden)) {
			return $query;
		}

		if ($query instanceof WP_Query) {
			$exclude = $query->get('post__not_in');
			$exclude = is_array($exclude) ? $exclude : array();
			$query->set('post__not_in', array_values(array_unique(array_merge($exclude, $hidden))));
			return $query;
		}

		if (is_array($query)) {
			$exclude = isset($query['post__not_in']) ? (array) $query['post__not_in'] : array();
			$query['post__not_in'] = array_values(array_unique(array_merge($exclude, $hidden)));
		}

		return $query;
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function filter_queries($query) {
		if (self::$resolving_private_ids) {
			return;
		}

		if (is_admin() || ! $query instanceof WP_Query) {
			return;
		}
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return;
		}
		if (MBTA_Roles::is_private_member() && current_user_can('manage_woocommerce')) {
			return;
		}

		if (! self::query_targets_products($query)) {
			return;
		}

		self::merge_query_exclusions($query);
	}

	/**
	 * @param bool $visible Visible.
	 * @param int  $id      Product ID.
	 * @return bool
	 */
	public static function product_is_visible($visible, $id) {
		if (! $visible) {
			return false;
		}
		return MBTA_Roles::user_can_view_product($id) ? $visible : false;
	}

	/**
	 * @param int[] $ids Product IDs.
	 * @return int[]
	 */
	public static function filter_product_ids($ids) {
		if (! is_array($ids)) {
			return $ids;
		}
		return array_values(array_filter(array_map('absint', $ids), static function ($id) {
			return MBTA_Roles::user_can_view_product($id);
		}));
	}

	/**
	 * Block direct URL access.
	 */
	public static function guard_single_product() {
		if (! function_exists('is_product') || ! is_product()) {
			return;
		}
		$product_id = get_queried_object_id();
		if (MBTA_Roles::user_can_view_product($product_id)) {
			if (MBTA_Roles::is_product_private($product_id)) {
				add_action('wp_head', static function () {
					echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
				}, 1);
			}
			return;
		}
		if (! MBTA_Roles::is_product_private($product_id)) {
			return;
		}

		if (! is_user_logged_in()) {
			wp_safe_redirect(wp_login_url(get_permalink($product_id)));
			exit;
		}

		status_header(403);
		include MBTA_PATH . 'templates/private-product-denied.php';
		exit;
	}

	/**
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Post.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_REST_Response
	 */
	public static function rest_hide_private($response, $post, $request) {
		if (! MBTA_Roles::user_can_view_product($post->ID)) {
			return new WP_REST_Response(array('code' => 'mbta_forbidden', 'message' => 'Private product'), 403);
		}
		return $response;
	}

	/**
	 * @param array           $args    Args.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function rest_product_query($args, $request) {
		$args = self::merge_excluded_ids_into_args($args);
		return $args;
	}

	/**
	 * WooCommerce REST product listing query filter.
	 *
	 * @param array           $args Query args.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function wc_rest_product_query($args, $request) {
		return self::merge_excluded_ids_into_args($args);
	}

	/**
	 * WooCommerce REST single product filter.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WC_Product       $product Product.
	 * @param WP_REST_Request  $request Request.
	 * @return WP_REST_Response
	 */
	public static function wc_rest_hide_private($response, $product, $request) {
		if ($product instanceof WC_Product && ! MBTA_Roles::user_can_view_product($product->get_id())) {
			return new WP_REST_Response(array('code' => 'mbta_forbidden', 'message' => 'Private product'), 403);
		}
		return $response;
	}

	/**
	 * WooCommerce shortcode query args.
	 *
	 * @param array  $query_args Shortcode query args.
	 * @param array  $atts Shortcode attrs.
	 * @param string $type Shortcode type.
	 * @return array
	 */
	public static function filter_shortcode_query($query_args, $atts, $type) {
		return self::merge_excluded_ids_into_args($query_args);
	}

	/**
	 * WooCommerce blocks product grid query args.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function filter_blocks_query($args) {
		return self::merge_excluded_ids_into_args($args);
	}

	/**
	 * Restrict purchasability for private products.
	 *
	 * @param bool       $purchasable Purchasable.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function product_is_purchasable($purchasable, $product) {
		if (! $purchasable || ! $product instanceof WC_Product) {
			return $purchasable;
		}
		$product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
		if (! MBTA_Roles::is_product_private($product_id)) {
			return $purchasable;
		}
		return MBTA_Roles::user_can_view_product($product_id);
	}

	/**
	 * Restrict variation purchasability for private parent products.
	 *
	 * @param bool                 $purchasable Purchasable.
	 * @param WC_Product_Variation $variation Variation product.
	 * @return bool
	 */
	public static function variation_is_purchasable($purchasable, $variation) {
		if (! $purchasable || ! $variation instanceof WC_Product_Variation) {
			return $purchasable;
		}
		$parent_id = $variation->get_parent_id();
		if (! MBTA_Roles::is_product_private($parent_id)) {
			return $purchasable;
		}
		return MBTA_Roles::user_can_view_product($parent_id);
	}

	/**
	 * Prevent add-to-cart for non-eligible users.
	 *
	 * @param bool   $passed Validation pass.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Qty.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variations Variations.
	 * @param array  $cart_item_data Cart item data.
	 * @return bool
	 */
	public static function validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
		if (! $passed) {
			return false;
		}
		$target_id = $variation_id > 0 ? wp_get_post_parent_id($variation_id) : $product_id;
		$target_id = absint($target_id ?: $product_id);
		if ($target_id < 1 || ! MBTA_Roles::is_product_private($target_id)) {
			return $passed;
		}
		if (MBTA_Roles::user_can_view_product($target_id)) {
			return $passed;
		}

		if (! is_user_logged_in()) {
			wc_add_notice(__('Please log in with an eligible team account to purchase this private product.', 'mad-baits-team-access'), 'error');
		} else {
			wc_add_notice(__('You do not have access to purchase this private product.', 'mad-baits-team-access'), 'error');
		}
		return false;
	}

	/**
	 * Remove restricted products from cart if user is not eligible.
	 */
	public static function enforce_cart_access() {
		if (! function_exists('WC') || ! WC()->cart) {
			return;
		}

		$removed = false;
		foreach (WC()->cart->get_cart() as $cart_key => $item) {
			$product_id = isset($item['product_id']) ? absint($item['product_id']) : 0;
			if ($product_id < 1 || ! MBTA_Roles::is_product_private($product_id)) {
				continue;
			}
			if (MBTA_Roles::user_can_view_product($product_id)) {
				continue;
			}

			WC()->cart->remove_cart_item($cart_key);
			$removed = true;
		}

		if ($removed) {
			wc_add_notice(__('Some private products were removed from your basket because your account does not have access.', 'mad-baits-team-access'), 'error');
		}
	}

	/**
	 * Notify on first publish of test bait products.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function maybe_notify_test_bait_publish($new_status, $old_status, $post) {
		if ('publish' !== $new_status || 'product' !== $post->post_type) {
			return;
		}
		if ('publish' === $old_status) {
			return;
		}
		if (! get_post_meta($post->ID, '_mbta_notify_on_publish', true)) {
			return;
		}

		$groups = MBTA_Roles::get_product_access_groups($post->ID);
		if (! in_array('test_baits', $groups, true)) {
			return;
		}

		if (self::notification_already_sent($post->ID, 'test_bait_publish')) {
			return;
		}

		MBTA_Push::send_role_notification(
			array(MBTA_Roles::ROLE_TEAM, MBTA_Roles::ROLE_RNT, MBTA_Roles::ROLE_TESTER),
			array(
				'title' => __('New test bait available', 'mad-baits-team-access'),
				'body'  => sprintf(
					/* translators: %s: product name */
					__('A new test bait is ready: %s', 'mad-baits-team-access'),
					get_the_title($post->ID)
				),
				'url'   => get_permalink($post->ID),
				'image' => get_the_post_thumbnail_url($post->ID, 'mad_product_card'),
				'tag'   => 'mbta-test-bait-' . $post->ID,
			),
			'test_bait_publish',
			$post->ID
		);
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $type       Type.
	 * @return bool
	 */
	public static function notification_already_sent($product_id, $type) {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . MBTA_DB::notification_log_table() . ' WHERE product_id = %d AND notification_type = %s',
				absint($product_id),
				sanitize_key($type)
			)
		);
		return $count > 0;
	}

	/**
	 * Log notification send.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       Type.
	 * @param int    $sent       Sent count.
	 */
	public static function log_notification($product_id, $type, $sent) {
		global $wpdb;
		$wpdb->insert(
			MBTA_DB::notification_log_table(),
			array(
				'product_id'        => absint($product_id),
				'notification_type' => sanitize_key($type),
				'target_role'       => 'multi',
				'sent_count'        => absint($sent),
				'created_at'        => current_time('mysql', true),
			),
			array('%d', '%s', '%s', '%d', '%s')
		);
	}

	/**
	 * Product IDs the current user cannot see.
	 *
	 * @return int[]
	 */
	public static function get_hidden_product_ids_for_current_user() {
		if (MBTA_Roles::is_private_member() || current_user_can('manage_woocommerce')) {
			return self::get_private_product_ids_not_allowed_for_user();
		}
		return self::get_all_private_product_ids();
	}

	/**
	 * Filter bundle slot options against Team Access permissions.
	 *
	 * @param array<int, array<string, mixed>> $options Slot options.
	 * @param array<string, mixed>             $slot Slot config.
	 * @param int                              $product_id Parent bundle product.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_bundle_slot_options($options, $slot, $product_id) {
		if (! is_array($options) || empty($options)) {
			return $options;
		}

		$filtered = array();
		foreach ($options as $row) {
			if (! is_array($row)) {
				continue;
			}
			$option_product_id = isset($row['product_id']) ? absint($row['product_id']) : 0;
			if ($option_product_id > 0 && ! MBTA_Roles::user_can_view_product($option_product_id)) {
				continue;
			}
			$filtered[] = $row;
		}

		return $filtered;
	}

	/**
	 * @return int[]
	 */
	public static function get_all_private_product_ids() {
		static $cache = null;
		if (null !== $cache) {
			return $cache;
		}

		self::$resolving_private_ids = true;
		try {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						self::private_tax_query(),
					),
				)
			);

			$meta_ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'key'     => '_mbta_access_groups',
							'compare' => 'EXISTS',
						),
					),
				)
			);
		} finally {
			self::$resolving_private_ids = false;
		}

		$merged = array_unique(array_merge(array_map('absint', (array) $ids), array_map('absint', (array) $meta_ids)));
		$cache  = array_values(
			array_filter(
				$merged,
				static function ($product_id) {
					return MBTA_Roles::is_product_private($product_id);
				}
			)
		);
		return $cache;
	}

	/**
	 * Published private products the user may view and purchase.
	 *
	 * @param WP_User|int|null $user  User.
	 * @param int              $limit Max products.
	 * @return WC_Product[]
	 */
	public static function get_viewable_private_products_for_user($user = null, $limit = 48) {
		if (! function_exists('wc_get_product')) {
			return array();
		}

		$limit = max(1, min(100, absint($limit)));
		$out   = array();

		foreach (self::get_all_private_product_ids() as $product_id) {
			if (! MBTA_Roles::user_can_view_product($product_id, $user)) {
				continue;
			}
			$product = wc_get_product($product_id);
			if (! $product instanceof WC_Product || 'publish' !== $product->get_status()) {
				continue;
			}
			$out[] = $product;
			if (count($out) >= $limit) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @return int[]
	 */
	private static function get_private_product_ids_not_allowed_for_user() {
		$hidden = array();
		foreach (self::get_all_private_product_ids() as $product_id) {
			if (! MBTA_Roles::user_can_view_product($product_id)) {
				$hidden[] = $product_id;
			}
		}
		return $hidden;
	}

	/**
	 * @return array
	 */
	private static function private_tax_query() {
		$slugs = array();
		foreach (MBTA_Roles::get_access_groups() as $conf) {
			foreach ((array) ($conf['slugs'] ?? array()) as $slug) {
				$slugs[] = sanitize_title((string) $slug);
			}
		}
		$slugs = array_values(array_unique(array_filter($slugs)));
		return array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $slugs,
			'operator' => 'IN',
		);
	}

	/**
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private static function query_targets_products($query) {
		$post_type = $query->get('post_type');
		$product_taxonomies = get_object_taxonomies('product');

		$is_product_query = ('product' === $post_type)
			|| (is_array($post_type) && in_array('product', $post_type, true))
			|| $query->is_post_type_archive('product')
			|| $query->is_tax($product_taxonomies);

		if ($is_product_query) {
			return true;
		}

		$tax_query = $query->get('tax_query');
		if (is_array($tax_query) && ! empty($product_taxonomies)) {
			foreach ($tax_query as $clause) {
				if (! is_array($clause) || empty($clause['taxonomy'])) {
					continue;
				}
				if (in_array((string) $clause['taxonomy'], $product_taxonomies, true)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param WP_Query $query Query.
	 * @return void
	 */
	private static function merge_query_exclusions($query) {
		$hidden = self::get_hidden_product_ids_for_current_user();
		if (empty($hidden)) {
			return;
		}
		$exclude = $query->get('post__not_in');
		$exclude = is_array($exclude) ? $exclude : array();
		$query->set('post__not_in', array_values(array_unique(array_merge($exclude, $hidden))));
	}

	/**
	 * Merge hidden IDs into query args.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	private static function merge_excluded_ids_into_args($args) {
		$hidden = self::get_hidden_product_ids_for_current_user();
		if (empty($hidden)) {
			return $args;
		}
		$exclude = isset($args['post__not_in']) ? (array) $args['post__not_in'] : array();
		$args['post__not_in'] = array_values(array_unique(array_merge($exclude, $hidden)));
		return $args;
	}

	/**
	 * @param mixed $entry Sitemap entry.
	 * @param mixed $object Object.
	 * @return mixed
	 */
	public static function exclude_from_sitemap($entry, $object) {
		if ($object instanceof WP_Post && 'product' === $object->post_type && MBTA_Roles::is_product_private($object->ID)) {
			return false;
		}
		return $entry;
	}

	/**
	 * @param array   $entry Entry.
	 * @param WP_Post $post  Post.
	 * @return array|false
	 */
	public static function exclude_from_core_sitemap($entry, $post) {
		if ($post instanceof WP_Post && 'product' === $post->post_type && MBTA_Roles::is_product_private($post->ID)) {
			return false;
		}
		return $entry;
	}
}
