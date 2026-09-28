<?php
/**
 * Uninstall handler (only when plugin deleted).
 *
 * @package MadBaitsTeamAccess
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

if (get_option('mbta_drop_tables_on_uninstall')) {
	global $wpdb;
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mbta_invites'); // phpcs:ignore WordPress.DB.PreparedSQL
	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mbta_notification_log'); // phpcs:ignore WordPress.DB.PreparedSQL
	delete_option('mbta_db_version');
}

delete_option('mbta_portal_page_id');
delete_option('mbta_invite_page_id');
delete_option('mbta_flush_rewrite');
