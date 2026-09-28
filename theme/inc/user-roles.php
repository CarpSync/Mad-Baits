<?php
/**
 * Custom customer roles used for Discount Rules targeting.
 *
 * Legacy roles (tester, team, ambassador, rnt) were retired — use Team Access
 * plugin roles (mad_*) or WooCommerce customer instead.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Retired role slugs removed from the site (no longer registered).
 *
 * @return string[]
 */
function mad_baits_get_retired_discount_target_role_slugs() {
	return array('tester', 'team', 'ambassador', 'rnt');
}

/**
 * Return custom role metadata used for targeting and frontend badge display.
 *
 * @return array<string, array<string, string>>
 */
function mad_baits_get_discount_target_role_meta() {
	return array();
}

/**
 * Return custom role labels used for Discount Rules plugin targeting.
 *
 * @return array<string, string>
 */
function mad_baits_get_discount_target_roles() {
	$roles = array();
	foreach (mad_baits_get_discount_target_role_meta() as $role_key => $meta) {
		$roles[ $role_key ] = isset($meta['label']) ? (string) $meta['label'] : '';
	}

	return $roles;
}

/**
 * Register missing Mad Baits custom roles with Subscriber-level capabilities.
 *
 * @param string               $role_key      Role slug.
 * @param string               $role_label    Display name.
 * @param array<string, bool>  $capabilities  Capabilities.
 * @return bool
 */
function mad_baits_register_role_if_missing($role_key, $role_label, $capabilities) {
	$role_key = sanitize_key((string) $role_key);
	if ('' === $role_key || get_role($role_key)) {
		return false;
	}

	return (null !== add_role($role_key, (string) $role_label, (array) $capabilities));
}

/**
 * Register discount-target roles (none defined; kept for future use).
 */
function mad_baits_register_discount_target_roles() {
	$capabilities = array(
		'read'    => true,
		'level_0' => true,
	);

	foreach (mad_baits_get_discount_target_roles() as $role_key => $role_label) {
		mad_baits_register_role_if_missing($role_key, $role_label, $capabilities);
	}
}
add_action('init', 'mad_baits_register_discount_target_roles');
add_action('after_switch_theme', 'mad_baits_register_discount_target_roles');

/**
 * Reassign users off retired roles and remove role definitions from WordPress.
 */
function mad_baits_remove_retired_discount_target_roles() {
	if (get_option('mad_baits_retired_roles_removed_v1')) {
		return;
	}

	$fallback = function_exists('wc') ? 'customer' : 'subscriber';
	$slugs    = mad_baits_get_retired_discount_target_role_slugs();

	foreach (get_users(array('fields' => 'all')) as $user) {
		if (! $user instanceof WP_User) {
			continue;
		}

		$had_retired = false;
		foreach ($slugs as $slug) {
			if (in_array($slug, (array) $user->roles, true)) {
				$had_retired = true;
				$user->remove_role($slug);
			}
		}

		if (! $had_retired) {
			continue;
		}

		if (empty($user->roles)) {
			$user->add_role($fallback);
		}
	}

	foreach ($slugs as $slug) {
		if (get_role($slug)) {
			remove_role($slug);
		}
	}

	update_option('mad_baits_retired_roles_removed_v1', 1, false);
}
add_action('init', 'mad_baits_remove_retired_discount_target_roles', 20);

/**
 * Return the current user's first matching custom role metadata.
 *
 * @param WP_User|int|null $user Optional user object or user ID.
 * @return array<string, string>
 */
function mad_baits_get_user_discount_target_role_data($user = null) {
	$wp_user = null;

	if ($user instanceof WP_User) {
		$wp_user = $user;
	} elseif (is_numeric($user)) {
		$wp_user = get_user_by('id', (int) $user);
	} else {
		$wp_user = wp_get_current_user();
	}

	if (! $wp_user instanceof WP_User || ! $wp_user->exists()) {
		return array();
	}

	$role_map = mad_baits_get_discount_target_role_meta();
	foreach ((array) $wp_user->roles as $role_slug) {
		$role_slug = sanitize_key((string) $role_slug);
		if (isset($role_map[ $role_slug ])) {
			$badge = array(
				'slug'        => $role_slug,
				'label'       => isset($role_map[ $role_slug ]['label']) ? (string) $role_map[ $role_slug ]['label'] : '',
				'badge_class' => isset($role_map[ $role_slug ]['badge_class']) ? (string) $role_map[ $role_slug ]['badge_class'] : '',
			);
			return apply_filters('mad_baits_get_user_discount_target_role_data', $badge, $wp_user);
		}
	}

	return apply_filters('mad_baits_get_user_discount_target_role_data', array(), $wp_user);
}

/**
 * Return the current user's first matching custom role label.
 *
 * @param WP_User|int|null $user Optional user object or user ID.
 * @return string
 */
function mad_baits_get_user_discount_target_role_label($user = null) {
	$role_data = mad_baits_get_user_discount_target_role_data($user);
	return isset($role_data['label']) ? (string) $role_data['label'] : '';
}
