<?php
/**
 * Team portal page, shortcode, nav and assets.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Portal {
	const OPTION_PORTAL_PAGE   = 'mbta_portal_page_id';
	const OPTION_INVITE_PAGE   = 'mbta_invite_page_id';

	/**
	 * @return void
	 */
	public static function init() {
		add_shortcode('mbta_team_portal', array(__CLASS__, 'shortcode_portal'));
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_filter('wp_nav_menu_items', array(__CLASS__, 'nav_menu_items'), 20, 2);
		add_filter('mad_baits_get_user_discount_target_role_data', array(__CLASS__, 'filter_role_badge'), 10, 2);
		add_action('mad_baits_after_account_nav', array(__CLASS__, 'render_account_portal_link'));
	}

	/**
	 * Create portal pages on activation.
	 */
	public static function ensure_pages() {
		self::ensure_page(
			self::OPTION_INVITE_PAGE,
			'team-invite',
			__('Team Invite', 'mad-baits-team-access'),
			'[mbta_team_invite]'
		);
		self::ensure_page(
			self::OPTION_PORTAL_PAGE,
			'team-portal',
			__('Team Portal', 'mad-baits-team-access'),
			'[mbta_team_portal]'
		);
		if (class_exists('MBTA_Team_Tester_Range')) {
			MBTA_Team_Tester_Range::ensure_page();
		}
	}

	/**
	 * @return int
	 */
	public static function get_portal_page_id() {
		return absint(get_option(self::OPTION_PORTAL_PAGE, 0));
	}

	/**
	 * @return int
	 */
	public static function get_invite_page_id() {
		return absint(get_option(self::OPTION_INVITE_PAGE, 0));
	}

	/**
	 * @return string
	 */
	public static function get_portal_url() {
		$page_id = self::get_portal_page_id();
		if ($page_id > 0) {
			$url = get_permalink($page_id);
			if (is_string($url) && '' !== $url) {
				return $url;
			}
		}
		return home_url('/team-portal/');
	}

	/**
	 * @return string
	 */
	public static function shortcode_portal() {
		if (! is_user_logged_in() || ! MBTA_Roles::is_private_member()) {
			return '<div class="mbta-portal mbta-portal--denied"><p>' . esc_html__('This area is for Mad Baits team and testers only. Use your invite link to register.', 'mad-baits-team-access') . '</p><p><a class="mbta-btn" href="' . esc_url(wp_login_url(self::get_portal_url())) . '">' . esc_html__('Log in', 'mad-baits-team-access') . '</a></p></div>';
		}

		ob_start();
		$role = MBTA_Roles::get_primary_role();
		$products = self::get_portal_products();
		include MBTA_PATH . 'templates/team-portal.php';
		return (string) ob_get_clean();
	}

	/**
	 * Products visible in portal for current user.
	 *
	 * @return WC_Product[]
	 */
	public static function get_portal_products() {
		if (! function_exists('wc_get_products')) {
			return array();
		}

		$slugs = MBTA_Roles::get_allowed_category_slugs();
		if (empty($slugs)) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 48,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => $slugs,
						'operator' => 'IN',
					),
				),
			)
		);

		return array_values(
			array_filter(
				(array) $products,
				static function ($product) {
					return $product instanceof WC_Product && MBTA_Roles::user_can_view_product($product->get_id());
				}
			)
		);
	}

	/**
	 * @param array<string, string> $badge Badge data.
	 * @return array<string, string>
	 */
	public static function filter_role_badge($badge, $user = null) {
		if (! empty($badge)) {
			return $badge;
		}
		if (! is_user_logged_in()) {
			return $badge;
		}
		$role = MBTA_Roles::get_primary_role();
		if ('' === $role) {
			return $badge;
		}
		$label = MBTA_Roles::get_badge_label();
		return array(
			'slug'        => $role,
			'label'       => $label,
			'badge_class' => 'mbta-role-badge mbta-role-badge--' . sanitize_html_class($role),
		);
	}

	/**
	 * @param string   $items Menu HTML.
	 * @param stdClass $args  Args.
	 * @return string
	 */
	public static function nav_menu_items($items, $args) {
		if (! is_user_logged_in() || ! MBTA_Roles::is_private_member()) {
			return $items;
		}
		$theme_location = isset($args->theme_location) ? (string) $args->theme_location : '';
		if (! in_array($theme_location, array('primary', 'mobile'), true)) {
			return $items;
		}
		$link = '<li class="menu-item menu-item-type-custom mbta-nav-portal"><a href="' . esc_url(self::get_portal_url()) . '">' . esc_html__('Team Portal', 'mad-baits-team-access') . '</a></li>';
		return $items . $link;
	}

	/**
	 * Account area portal link.
	 */
	public static function render_account_portal_link() {
		if (! MBTA_Roles::is_private_member()) {
			return;
		}
		echo '<p class="mbta-account-portal-link"><a href="' . esc_url(self::get_portal_url()) . '">' . esc_html__('Open Team Portal', 'mad-baits-team-access') . '</a></p>';
	}

	/**
	 * Enqueue portal styles/scripts for members.
	 */
	public static function enqueue_assets() {
		if (! MBTA_Roles::is_private_member() && ! get_query_var('mbta_team_invite') && ! is_page(array(self::get_portal_page_id(), self::get_invite_page_id()))) {
			return;
		}

		wp_enqueue_style(
			'mbta-team-access',
			MBTA_URL . 'assets/css/team-access.css',
			array(),
			MBTA_VERSION
		);

		if (MBTA_Roles::is_private_member()) {
			wp_enqueue_script(
				'mbta-team-portal',
				MBTA_URL . 'assets/js/team-portal.js',
				array(),
				MBTA_VERSION,
				true
			);

			$config = array(
				'restRoot'       => esc_url_raw(rest_url('mad-baits/v1/push/')),
				'restNonce'      => wp_create_nonce('wp_rest'),
				'isLoggedIn'     => true,
				'serviceWorkerUrl' => function_exists('mad_baits_get_pwa_service_worker_url')
					? mad_baits_get_pwa_service_worker_url()
					: home_url('/service-worker.js'),
				'publicVapidKey' => class_exists('Mad_Baits_Push_Admin') ? Mad_Baits_Push_Admin::get_public_vapid_key() : '',
				'dismissKey'     => 'mbtaPushPromptDismissedAt',
			);
			wp_localize_script('mbta-team-portal', 'mbtaPortalConfig', $config);
		}
	}

	/**
	 * @param string $option_key Option key.
	 * @param string $slug       Page slug.
	 * @param string $title      Title.
	 * @param string $content    Content.
	 */
	private static function ensure_page($option_key, $slug, $title, $content) {
		$page_id = absint(get_option($option_key, 0));
		if ($page_id > 0 && get_post_status($page_id)) {
			self::sync_page_shortcode($page_id, $content);
			return;
		}

		$existing = get_page_by_path($slug);
		if ($existing instanceof WP_Post) {
			update_option($option_key, $existing->ID);
			self::sync_page_shortcode($existing->ID, $content);
			return;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if (! is_wp_error($new_id)) {
			update_option($option_key, (int) $new_id);
		}
	}

	/**
	 * Ensure required shortcode exists in page content (block editor safe).
	 *
	 * @param int    $page_id Page ID.
	 * @param string $content Expected shortcode content.
	 */
	private static function sync_page_shortcode($page_id, $content) {
		$page_id = absint($page_id);
		$content = trim((string) $content);
		if ($page_id < 1 || '' === $content) {
			return;
		}

		$post = get_post($page_id);
		if (! $post instanceof WP_Post) {
			return;
		}

		$current = (string) $post->post_content;
		if (has_shortcode($current, trim($content, '[]')) || false !== strpos($current, $content)) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $content,
			)
		);
	}
}
