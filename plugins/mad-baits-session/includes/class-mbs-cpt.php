<?php
/**
 * Custom post types for Session app.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * CPT registration.
 */
class MBS_CPT {

	const SESSION = 'mad_fishing_session';
	const VENUE   = 'mad_saved_venue';
	const REPORT  = 'mad_team_report';

	/**
	 * @return void
	 */
	public static function init() {
		add_action('init', array(__CLASS__, 'register_post_types'), 5);
		add_filter('map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4);
	}

	/**
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			self::SESSION,
			array(
				'labels'              => array(
					'name'          => __('Fishing Sessions', 'mad-baits-session'),
					'singular_name' => __('Fishing Session', 'mad-baits-session'),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array('title', 'author'),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		register_post_type(
			self::VENUE,
			array(
				'labels'              => array(
					'name'          => __('Saved Venues', 'mad-baits-session'),
					'singular_name' => __('Saved Venue', 'mad-baits-session'),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'supports'            => array('title', 'author'),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		register_post_type(
			self::REPORT,
			array(
				'labels'              => array(
					'name'          => __('Team Reports', 'mad-baits-session'),
					'singular_name' => __('Team Report', 'mad-baits-session'),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'supports'            => array('title', 'author'),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Restrict edit/delete to owner or admin.
	 *
	 * @param array<string> $caps    Caps.
	 * @param string        $cap     Cap.
	 * @param int           $user_id User.
	 * @param array<mixed>  $args    Args.
	 * @return array<string>
	 */
	public static function map_meta_cap($caps, $cap, $user_id, $args) {
		$owned = array('edit_post', 'delete_post', 'read_post');
		if (! in_array($cap, $owned, true) || empty($args[0])) {
			return $caps;
		}

		$post = get_post((int) $args[0]);
		if (! $post || ! in_array($post->post_type, array(self::SESSION, self::VENUE, self::REPORT), true)) {
			return $caps;
		}

		if (user_can($user_id, 'manage_options')) {
			return array('exist');
		}

		if ((int) $post->post_author === (int) $user_id) {
			return array('exist');
		}

		return array('do_not_allow');
	}
}
