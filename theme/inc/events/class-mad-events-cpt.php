<?php
/**
 * Events custom post types.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register event post types.
 */
class Mad_Events_CPT {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_post_types();
	}

	/**
	 * Register mad_event and mad_event_rsvp.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			'mad_event',
			array(
				'labels'              => array(
					'name'               => __('Events', 'mad-baits'),
					'singular_name'      => __('Event', 'mad-baits'),
					'add_new'            => __('Add Event', 'mad-baits'),
					'add_new_item'       => __('Add New Event', 'mad-baits'),
					'edit_item'          => __('Edit Event', 'mad-baits'),
					'new_item'           => __('New Event', 'mad-baits'),
					'view_item'          => __('View Event', 'mad-baits'),
					'search_items'       => __('Search Events', 'mad-baits'),
					'not_found'          => __('No events found', 'mad-baits'),
					'not_found_in_trash' => __('No events found in Trash', 'mad-baits'),
					'all_items'          => __('Upcoming Events', 'mad-baits'),
					'menu_name'          => __('Events', 'mad-baits'),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-calendar-alt',
				'menu_position'       => 26,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => 'events',
					'with_front' => false,
				),
				'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
				'capability_type'     => 'post',
			)
		);

		register_post_type(
			'mad_event_rsvp',
			array(
				'labels'              => array(
					'name'          => __('Event RSVPs', 'mad-baits'),
					'singular_name' => __('Event RSVP', 'mad-baits'),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=mad_event',
				'supports'            => array('title'),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}
