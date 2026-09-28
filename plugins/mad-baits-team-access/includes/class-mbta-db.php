<?php
/**
 * Database tables.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_DB {
	/**
	 * @return void
	 */
	public static function init() {
		add_action('init', array(__CLASS__, 'maybe_upgrade'));
	}

	/**
	 * @return string
	 */
	public static function invites_table() {
		global $wpdb;
		return $wpdb->prefix . 'mbta_invites';
	}

	/**
	 * @return string
	 */
	public static function notification_log_table() {
		global $wpdb;
		return $wpdb->prefix . 'mbta_notification_log';
	}

	/**
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$invites = self::invites_table();
		$sql1    = "CREATE TABLE {$invites} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(64) NOT NULL,
			role VARCHAR(32) NOT NULL,
			expires_at DATETIME NULL,
			usage_limit INT UNSIGNED NULL,
			used_count INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(16) NOT NULL DEFAULT 'active',
			notes TEXT NULL,
			invite_email VARCHAR(190) NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code_unique (code),
			KEY role_idx (role),
			KEY status_idx (status)
		) {$charset};";

		$logs = self::notification_log_table();
		$sql2 = "CREATE TABLE {$logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			notification_type VARCHAR(32) NOT NULL,
			target_role VARCHAR(32) NOT NULL,
			sent_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY product_type_idx (product_id, notification_type)
		) {$charset};";

		dbDelta($sql1);
		dbDelta($sql2);
		update_option('mbta_db_version', MBTA_VERSION);
	}

	/**
	 * @return void
	 */
	public static function maybe_upgrade() {
		if (get_option('mbta_db_version') !== MBTA_VERSION) {
			self::create_tables();
		}
	}
}
