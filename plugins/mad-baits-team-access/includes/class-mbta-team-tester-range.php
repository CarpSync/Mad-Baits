<?php
/**
 * Team & Tester Range page, navigation, and account entry points.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Team_Tester_Range {
	const OPTION_PAGE_ID = 'mbta_team_tester_range_page_id';
	const OPTION_TEAM_SHOP_PAGE_ID = 'mbta_team_shop_page_id';
	const PAGE_SLUG      = 'team-tester-range';
	const TEAM_SHOP_SLUG = 'team-shop';

	/**
	 * @return void
	 */
	public static function init() {
		add_action('init', array(__CLASS__, 'maybe_ensure_page'), 20);
		add_shortcode('mbta_team_tester_range', array(__CLASS__, 'shortcode_range'));
		add_shortcode('mbta_team_shop', array(__CLASS__, 'shortcode_range'));
		add_filter('mad_baits_get_primary_nav_items', array(__CLASS__, 'filter_primary_nav_items'), 25);
		add_action('woocommerce_account_dashboard', array(__CLASS__, 'render_account_dashboard_card'), 7);
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('wp_head', array(__CLASS__, 'maybe_output_noindex'), 1);
		add_filter('wpseo_robots', array(__CLASS__, 'filter_yoast_robots'));
		add_filter('rank_math/frontend/robots', array(__CLASS__, 'filter_rank_math_robots'));
		add_filter('wp_sitemaps_posts_query_args', array(__CLASS__, 'exclude_page_from_sitemap'), 10, 2);
	}

	/**
	 * Ensure page exists on existing installs.
	 */
	public static function maybe_ensure_page() {
		if (get_option(self::OPTION_PAGE_ID, 0) > 0) {
			self::ensure_team_shop_page();
			return;
		}
		self::ensure_page();
	}

	/**
	 * Create page on activation.
	 */
	public static function ensure_page() {
		$page_id = absint(get_option(self::OPTION_PAGE_ID, 0));
		if ($page_id > 0 && get_post_status($page_id)) {
			self::sync_page_content($page_id);
			return;
		}

		$existing = get_page_by_path(self::PAGE_SLUG);
		if ($existing instanceof WP_Post) {
			update_option(self::OPTION_PAGE_ID, $existing->ID);
			self::sync_page_content($existing->ID);
			return;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => __('Team & Tester Range', 'mad-baits-team-access'),
				'post_name'    => self::PAGE_SLUG,
				'post_content' => '[mbta_team_tester_range]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if (! is_wp_error($new_id)) {
			update_option(self::OPTION_PAGE_ID, (int) $new_id);
		}

		self::ensure_team_shop_page();
	}

	/**
	 * @return int
	 */
	public static function get_page_id() {
		return absint(get_option(self::OPTION_PAGE_ID, 0));
	}

	/**
	 * @return string
	 */
	public static function get_page_url() {
		$team_shop_id = self::get_team_shop_page_id();
		if ($team_shop_id > 0) {
			$url = get_permalink($team_shop_id);
			if (is_string($url) && '' !== $url) {
				return $url;
			}
		}

		$page_id = self::get_page_id();
		if ($page_id > 0) {
			$url = get_permalink($page_id);
			if (is_string($url) && '' !== $url) {
				return $url;
			}
		}
		return home_url('/' . self::PAGE_SLUG . '/');
	}

	/**
	 * @return int
	 */
	public static function get_team_shop_page_id() {
		return absint(get_option(self::OPTION_TEAM_SHOP_PAGE_ID, 0));
	}

	/**
	 * Whether the current user should see Team & Tester Range navigation.
	 *
	 * @param WP_User|int|null $user User.
	 * @return bool
	 */
	public static function user_should_show_nav($user = null) {
		if (! is_user_logged_in()) {
			return false;
		}

		$allowed_groups = MBTA_Roles::get_allowed_access_groups($user);
		if (empty($allowed_groups)) {
			return false;
		}

		return ! empty(self::get_range_products($user));
	}

	/**
	 * Products for the range page (server-side filtered).
	 *
	 * @param WP_User|int|null $user User.
	 * @return WC_Product[]
	 */
	public static function get_range_products($user = null) {
		return MBTA_Products::get_viewable_private_products_for_user($user, 96);
	}

	/**
	 * @param array $items Nav items.
	 * @return array
	 */
	public static function filter_primary_nav_items($items) {
		if (! is_array($items) || ! self::user_should_show_nav()) {
			return $items;
		}

		$nav_item = array(
			'label'   => __('Team & Tester Range', 'mad-baits-team-access'),
			'url'     => self::get_page_url(),
			'badge'   => __('PRIVATE', 'mad-baits-team-access'),
			'classes' => array('menu-item-team-tester-range', 'mbta-nav-team-tester'),
		);

		$insert_at = 3;
		array_splice($items, $insert_at, 0, array($nav_item));

		return $items;
	}

	/**
	 * @return string
	 */
	public static function shortcode_range() {
		$state    = 'guest';
		$products = array();

		if (is_user_logged_in()) {
			if (self::user_should_show_nav()) {
				$state    = 'eligible';
				$products = self::get_range_products();
			} else {
				$state = 'denied';
			}
		}

		ob_start();
		include MBTA_PATH . 'templates/team-tester-range.php';
		return (string) ob_get_clean();
	}

	/**
	 * Account dashboard card for eligible users.
	 */
	public static function render_account_dashboard_card() {
		if (! self::user_should_show_nav()) {
			return;
		}
		?>
		<section class="mbta-account-range-card mad-account-dashboard__card">
			<p class="mbta-account-range-card__eyebrow"><?php esc_html_e('Private range', 'mad-baits-team-access'); ?></p>
			<h3 class="mbta-account-range-card__title"><?php esc_html_e('Team & Tester Range', 'mad-baits-team-access'); ?></h3>
			<p class="mbta-account-range-card__copy"><?php esc_html_e('View private Mad Baits products available to your account.', 'mad-baits-team-access'); ?></p>
			<a class="mbta-btn mbta-btn--primary" href="<?php echo esc_url(self::get_page_url()); ?>"><?php esc_html_e('View Range', 'mad-baits-team-access'); ?></a>
		</section>
		<?php
	}

	/**
	 * Enqueue styles on the range page and for eligible members (nav badge).
	 */
	public static function enqueue_assets() {
		$page_ids = array_filter(array(self::get_page_id(), self::get_team_shop_page_id()));
		if (! is_page($page_ids) && ! self::user_should_show_nav()) {
			return;
		}

		wp_enqueue_style(
			'mbta-team-access',
			MBTA_URL . 'assets/css/team-access.css',
			array(),
			MBTA_VERSION
		);
	}

	/**
	 * @return void
	 */
	public static function maybe_output_noindex() {
		if (! self::is_range_page()) {
			return;
		}
		echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
	}

	/**
	 * @param string $robots Robots string.
	 * @return string
	 */
	public static function filter_yoast_robots($robots) {
		if (self::is_range_page()) {
			return 'noindex, nofollow';
		}
		return $robots;
	}

	/**
	 * @param array $robots Robots array.
	 * @return array
	 */
	public static function filter_rank_math_robots($robots) {
		if (self::is_range_page() && is_array($robots)) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';
		}
		return $robots;
	}

	/**
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function exclude_page_from_sitemap($args, $post_type) {
		if ('page' !== $post_type) {
			return $args;
		}
		$page_ids = array_filter(array(self::get_page_id(), self::get_team_shop_page_id()));
		if (empty($page_ids)) {
			return $args;
		}
		$exclude   = isset($args['post__not_in']) ? (array) $args['post__not_in'] : array();
		$exclude   = array_merge($exclude, $page_ids);
		$args['post__not_in'] = array_values(array_unique(array_map('absint', $exclude)));
		return $args;
	}

	/**
	 * @return bool
	 */
	private static function is_range_page() {
		$page_ids = array_filter(array(self::get_page_id(), self::get_team_shop_page_id()));
		return ! empty($page_ids) && is_page($page_ids);
	}

	/**
	 * @param int $page_id Page ID.
	 */
	private static function sync_page_content($page_id) {
		$post = get_post(absint($page_id));
		if (! $post instanceof WP_Post) {
			return;
		}
		if (has_shortcode((string) $post->post_content, 'mbta_team_tester_range')) {
			return;
		}
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => '[mbta_team_tester_range]',
			)
		);
	}

	/**
	 * Ensure a hidden Team Shop page exists as an optional private entrypoint.
	 *
	 * @return void
	 */
	private static function ensure_team_shop_page() {
		$page_id = self::get_team_shop_page_id();
		if ($page_id > 0 && get_post_status($page_id)) {
			self::sync_page_content($page_id);
			return;
		}

		$existing = get_page_by_path(self::TEAM_SHOP_SLUG);
		if ($existing instanceof WP_Post) {
			update_option(self::OPTION_TEAM_SHOP_PAGE_ID, $existing->ID);
			self::sync_page_content($existing->ID);
			return;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => __('Team Shop', 'mad-baits-team-access'),
				'post_name'    => self::TEAM_SHOP_SLUG,
				'post_content' => '[mbta_team_shop]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if (! is_wp_error($new_id)) {
			update_option(self::OPTION_TEAM_SHOP_PAGE_ID, (int) $new_id);
		}
	}
}
