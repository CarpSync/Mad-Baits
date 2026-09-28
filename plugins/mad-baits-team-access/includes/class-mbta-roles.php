<?php
/**
 * Custom roles and access matrix.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Roles {
	const ROLE_TEAM       = 'mad_team';
	const ROLE_RNT        = 'mad_rnt';
	const ROLE_AMBASSADOR = 'mad_ambassador';
	const ROLE_TESTER     = 'mad_tester';
	const ROLE_MEDIA      = 'mad_media';
	const USER_META_ACCESS = 'mad_team_access';

	/**
	 * @return void
	 */
	public static function init() {
		add_action('init', array(__CLASS__, 'register_roles'), 5);
		add_filter('body_class', array(__CLASS__, 'body_class'));
	}

	/**
	 * Register roles on init (idempotent).
	 */
	public static function register_roles() {
		$subscriber = get_role('subscriber');
		$caps       = $subscriber ? (array) $subscriber->capabilities : array('read' => true);

		$roles = self::get_role_labels();
		foreach ($roles as $slug => $label) {
			if (! get_role($slug)) {
				add_role($slug, $label, $caps);
			}
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_role_labels() {
		return array(
			self::ROLE_TEAM       => __('Mad Baits Team', 'mad-baits-team-access'),
			self::ROLE_RNT        => __('Mad Baits RNT', 'mad-baits-team-access'),
			self::ROLE_AMBASSADOR => __('Mad Baits Ambassador', 'mad-baits-team-access'),
			self::ROLE_TESTER     => __('Mad Baits Tester', 'mad-baits-team-access'),
			self::ROLE_MEDIA      => __('Mad Baits Media Team', 'mad-baits-team-access'),
		);
	}

	/**
	 * Badge labels for UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_badge_labels() {
		return array(
			self::ROLE_TEAM       => __('TEAM MEMBER', 'mad-baits-team-access'),
			self::ROLE_RNT        => __('RNT', 'mad-baits-team-access'),
			self::ROLE_AMBASSADOR => __('AMBASSADOR', 'mad-baits-team-access'),
			self::ROLE_TESTER     => __('TESTER', 'mad-baits-team-access'),
			self::ROLE_MEDIA      => __('MEDIA TEAM', 'mad-baits-team-access'),
		);
	}

	/**
	 * Private product access groups (product_cat slugs).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_access_groups() {
		return array(
			'test_baits' => array(
				'label' => __('Test Baits', 'mad-baits-team-access'),
				'slugs' => array('test-baits', 'test-bait', 'team-testing'),
			),
			'team_only' => array(
				'label' => __('Team Only', 'mad-baits-team-access'),
				'slugs' => array('team-only', 'team-testing'),
			),
			'rnt_specials' => array(
				'label' => __('RNT Specials', 'mad-baits-team-access'),
				'slugs' => array('rnt-specials', 'rnt-special'),
			),
			'ambassador_access' => array(
				'label' => __('Ambassador Access', 'mad-baits-team-access'),
				'slugs' => array('ambassador-access', 'ambassador'),
			),
			'tester_access' => array(
				'label' => __('Tester Access', 'mad-baits-team-access'),
				'slugs' => array('tester-access', 'tester'),
			),
			'media_team_access' => array(
				'label' => __('Mad Baits Media Team', 'mad-baits-team-access'),
				'slugs' => array('media-team-access', 'media-access', 'media-team'),
			),
		);
	}

	/**
	 * Which access groups each role may view.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_role_access_map() {
		return array(
			self::ROLE_TEAM       => array('team_only', 'test_baits'),
			self::ROLE_RNT        => array('rnt_specials'),
			self::ROLE_AMBASSADOR => array('ambassador_access'),
			self::ROLE_TESTER     => array('tester_access', 'test_baits'),
			self::ROLE_MEDIA      => array('media_team_access'),
		);
	}

	/**
	 * Map legacy / alternate role slugs to canonical mad_* roles.
	 *
	 * @return array<string, string>
	 */
	public static function get_role_aliases() {
		return array(
			'mad_baits_team'       => self::ROLE_TEAM,
			'team'                 => self::ROLE_TEAM,
			'mad_baits_rnt'        => self::ROLE_RNT,
			'rnt'                  => self::ROLE_RNT,
			'mad_baits_ambassador' => self::ROLE_AMBASSADOR,
			'ambassador'           => self::ROLE_AMBASSADOR,
			'mad_baits_tester'     => self::ROLE_TESTER,
			'tester'               => self::ROLE_TESTER,
			'mad_baits_media'      => self::ROLE_MEDIA,
			'mad_baits_media_team' => self::ROLE_MEDIA,
			'media_team'           => self::ROLE_MEDIA,
			'media'                => self::ROLE_MEDIA,
		);
	}

	/**
	 * @param string $role Role slug.
	 * @return string
	 */
	public static function normalize_role_slug($role) {
		$role    = sanitize_key((string) $role);
		$aliases = self::get_role_aliases();
		return isset($aliases[ $role ]) ? (string) $aliases[ $role ] : $role;
	}

	/**
	 * @param WP_User|int|null $user User.
	 * @return string[]
	 */
	public static function get_user_roles($user = null) {
		$user = self::resolve_user($user);
		if (! $user) {
			return array();
		}
		return array_map('sanitize_key', (array) $user->roles);
	}

	/**
	 * Primary private role for user.
	 *
	 * @param WP_User|int|null $user User.
	 * @return string
	 */
	public static function get_primary_role($user = null) {
		$priority = array(
			self::ROLE_TEAM,
			self::ROLE_RNT,
			self::ROLE_AMBASSADOR,
			self::ROLE_TESTER,
			self::ROLE_MEDIA,
		);
		$user_roles = array_map(array(__CLASS__, 'normalize_role_slug'), self::get_user_roles($user));
		foreach ($priority as $role) {
			if (in_array($role, $user_roles, true)) {
				return $role;
			}
		}
		return '';
	}

	/**
	 * @param WP_User|int|null $user User.
	 * @return bool
	 */
	public static function is_private_member($user = null) {
		return '' !== self::get_primary_role($user);
	}

	/**
	 * @param WP_User|int|null $user User.
	 * @return string
	 */
	public static function get_badge_label($user = null) {
		$role = self::get_primary_role($user);
		$labels = self::get_badge_labels();
		return isset($labels[ $role ]) ? (string) $labels[ $role ] : '';
	}

	/**
	 * Access groups allowed for user.
	 *
	 * @param WP_User|int|null $user User.
	 * @return string[]
	 */
	public static function get_allowed_access_groups($user = null) {
		$user = self::resolve_user($user);
		if (! $user) {
			return array();
		}
		if (user_can($user, 'manage_woocommerce')) {
			return array_keys(self::get_access_groups());
		}

		$allowed = array();
		$map     = self::get_role_access_map();
		foreach (self::get_user_roles($user) as $role) {
			$role = self::normalize_role_slug($role);
			if (isset($map[ $role ])) {
				$allowed = array_merge($allowed, $map[ $role ]);
			}
		}
		$allowed = array_merge($allowed, self::get_user_meta_access_groups($user));
		return self::normalize_access_group_keys($allowed);
	}

	/**
	 * Read optional user-level access groups from user meta.
	 *
	 * Supported formats:
	 * - array('team_only', 'tester_access')
	 * - comma separated string "team_only,tester_access"
	 *
	 * @param WP_User $user User object.
	 * @return string[]
	 */
	public static function get_user_meta_access_groups($user) {
		if (! $user instanceof WP_User) {
			return array();
		}

		$raw = get_user_meta($user->ID, self::USER_META_ACCESS, true);
		if (empty($raw)) {
			return array();
		}

		if (is_string($raw)) {
			$raw = explode(',', $raw);
		}

		if (! is_array($raw)) {
			return array();
		}

		return self::normalize_access_group_keys($raw);
	}

	/**
	 * Normalize and keep only known access groups.
	 *
	 * @param array<int, mixed> $groups Group keys.
	 * @return string[]
	 */
	private static function normalize_access_group_keys($groups) {
		$known = array_keys(self::get_access_groups());
		$out   = array();

		foreach ((array) $groups as $group) {
			$group = sanitize_key((string) $group);
			if ('' === $group || ! in_array($group, $known, true)) {
				continue;
			}
			$out[] = $group;
		}

		return array_values(array_unique($out));
	}

	/**
	 * @param string           $group_key Access group key.
	 * @param WP_User|int|null $user      User.
	 * @return bool
	 */
	public static function user_can_access_group($group_key, $user = null) {
		$group_key = sanitize_key($group_key);
		return in_array($group_key, self::get_allowed_access_groups($user), true);
	}

	/**
	 * @param WP_User|int|null $user User.
	 * @return string[]
	 */
	public static function get_allowed_category_slugs($user = null) {
		$groups = self::get_access_groups();
		$slugs  = array();
		foreach (self::get_allowed_access_groups($user) as $group_key) {
			if (! isset($groups[ $group_key ]['slugs'])) {
				continue;
			}
			foreach ((array) $groups[ $group_key ]['slugs'] as $slug) {
				$slug = sanitize_title((string) $slug);
				if ('' !== $slug) {
					$slugs[] = $slug;
				}
			}
		}
		return array_values(array_unique($slugs));
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public static function get_product_access_groups($product_id) {
		$product_id = absint($product_id);
		if ($product_id < 1) {
			return array();
		}

		$stored = get_post_meta($product_id, '_mbta_access_groups', true);
		if (is_array($stored) && ! empty($stored)) {
			return array_values(array_unique(array_map('sanitize_key', $stored)));
		}

		$groups = self::get_access_groups();
		$cat_slugs = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
		if (is_wp_error($cat_slugs) || empty($cat_slugs)) {
			return array();
		}

		$matched = array();
		foreach ($groups as $group_key => $conf) {
			$needles = isset($conf['slugs']) ? (array) $conf['slugs'] : array();
			foreach ($cat_slugs as $cat_slug) {
				if (in_array(sanitize_title((string) $cat_slug), array_map('sanitize_title', $needles), true)) {
					$matched[] = $group_key;
					break;
				}
			}
		}
		return array_values(array_unique($matched));
	}

	/**
	 * @param int              $product_id Product ID.
	 * @param WP_User|int|null $user       User.
	 * @return bool
	 */
	public static function user_can_view_product($product_id, $user = null) {
		$groups = self::get_product_access_groups($product_id);
		if (empty($groups)) {
			return true;
		}
		foreach ($groups as $group_key) {
			if (self::user_can_access_group($group_key, $user)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param WP_User|int|null $user User.
	 * @return bool
	 */
	public static function is_product_private($product_id) {
		return ! empty(self::get_product_access_groups($product_id));
	}

	/**
	 * Short badge label for a product access group (product cards).
	 *
	 * @param string $group_key Group key.
	 * @return string
	 */
	public static function get_access_group_badge_label($group_key) {
		$labels = array(
			'test_baits'        => __('Test Baits', 'mad-baits-team-access'),
			'team_only'         => __('Team Only', 'mad-baits-team-access'),
			'rnt_specials'      => __('RNT Special', 'mad-baits-team-access'),
			'ambassador_access' => __('Ambassador', 'mad-baits-team-access'),
			'tester_access'     => __('Tester', 'mad-baits-team-access'),
			'media_team_access' => __('Media Team', 'mad-baits-team-access'),
		);
		$group_key = sanitize_key($group_key);
		return isset($labels[ $group_key ]) ? (string) $labels[ $group_key ] : '';
	}

	/**
	 * Primary access badge for a product (first group the viewer is allowed to see).
	 *
	 * @param int              $product_id Product ID.
	 * @param WP_User|int|null $user       User.
	 * @return string
	 */
	public static function get_product_access_badge_for_user($product_id, $user = null) {
		$groups = self::get_product_access_groups($product_id);
		foreach ($groups as $group_key) {
			if (self::user_can_access_group($group_key, $user)) {
				return self::get_access_group_badge_label($group_key);
			}
		}
		return '';
	}

	/**
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class($classes) {
		if (! is_user_logged_in()) {
			return $classes;
		}
		$role = self::get_primary_role();
		if ('' !== $role) {
			$classes[] = 'mbta-private-member';
			$classes[] = 'mbta-role-' . sanitize_html_class($role);
			$classes[] = 'mad-baits-discount-role-' . sanitize_html_class($role);
		}
		return $classes;
	}

	/**
	 * @param WP_User|int|null $user User.
	 * @return WP_User|null
	 */
	private static function resolve_user($user) {
		if ($user instanceof WP_User) {
			return $user->exists() ? $user : null;
		}
		if (is_numeric($user)) {
			$u = get_user_by('id', absint($user));
			return $u instanceof WP_User ? $u : null;
		}
		if (is_user_logged_in()) {
			$u = wp_get_current_user();
			return $u instanceof WP_User ? $u : null;
		}
		return null;
	}
}
