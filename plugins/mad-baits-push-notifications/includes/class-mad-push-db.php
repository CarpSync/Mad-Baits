<?php
/**
 * DB layer for push subscribers and send logs.
 *
 * @package MadBaitsPush
 */

if (! defined('ABSPATH')) {
	exit;
}

class Mad_Baits_Push_DB {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('init', array(__CLASS__, 'maybe_upgrade_schema'));
	}

	/**
	 * Subscribers table name.
	 *
	 * @return string
	 */
	public static function subscribers_table() {
		global $wpdb;
		return $wpdb->prefix . 'mad_push_subscribers';
	}

	/**
	 * Send logs table name.
	 *
	 * @return string
	 */
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'mad_push_logs';
	}

	/**
	 * Create/upgrade schema.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$subscribers_table = self::subscribers_table();
		$logs_table        = self::logs_table();

		$sql_subscribers = "CREATE TABLE {$subscribers_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			endpoint TEXT NOT NULL,
			public_key TEXT NOT NULL,
			auth_token TEXT NOT NULL,
			user_agent TEXT NULL,
			device_label VARCHAR(191) NULL,
			is_pwa TINYINT(1) NOT NULL DEFAULT 0,
			permission_status VARCHAR(32) NOT NULL DEFAULT 'default',
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			last_sent_at DATETIME NULL,
			last_error TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_unique (endpoint(191)),
			KEY user_id_idx (user_id),
			KEY active_idx (active),
			KEY is_pwa_idx (is_pwa)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(191) NOT NULL,
			message TEXT NOT NULL,
			target_url TEXT NOT NULL,
			image_url TEXT NULL,
			audience VARCHAR(32) NOT NULL,
			attempted INT UNSIGNED NOT NULL DEFAULT 0,
			sent INT UNSIGNED NOT NULL DEFAULT 0,
			failed INT UNSIGNED NOT NULL DEFAULT 0,
			failures_summary TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at_idx (created_at)
		) {$charset_collate};";

		dbDelta($sql_subscribers);
		dbDelta($sql_logs);
		update_option('mad_baits_push_db_version', MAD_BAITS_PUSH_VERSION);
	}

	/**
	 * Upgrade schema on version mismatch.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_schema() {
		$installed = get_option('mad_baits_push_db_version', '');
		if ((string) $installed !== (string) MAD_BAITS_PUSH_VERSION) {
			self::create_tables();
		}
	}

	/**
	 * Upsert subscriber by endpoint.
	 *
	 * @param array<string,mixed> $data Data payload.
	 * @return int|false
	 */
	public static function upsert_subscriber($data) {
		global $wpdb;
		$table = self::subscribers_table();

		$endpoint = isset($data['endpoint']) ? (string) $data['endpoint'] : '';
		if ('' === $endpoint) {
			return false;
		}

		$now = current_time('mysql', true);
		$row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table} WHERE endpoint = %s LIMIT 1", $endpoint), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$fields = array(
			'user_id'           => isset($data['user_id']) ? absint($data['user_id']) : null,
			'endpoint'          => $endpoint,
			'public_key'        => isset($data['public_key']) ? (string) $data['public_key'] : '',
			'auth_token'        => isset($data['auth_token']) ? (string) $data['auth_token'] : '',
			'user_agent'        => isset($data['user_agent']) ? (string) $data['user_agent'] : '',
			'device_label'      => isset($data['device_label']) ? (string) $data['device_label'] : '',
			'is_pwa'            => ! empty($data['is_pwa']) ? 1 : 0,
			'permission_status' => isset($data['permission_status']) ? (string) $data['permission_status'] : 'default',
			'active'            => isset($data['active']) ? (int) (bool) $data['active'] : 1,
			'updated_at'        => $now,
		);

		if ($row && isset($row['id'])) {
			$wpdb->update($table, $fields, array('id' => (int) $row['id'])); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return (int) $row['id'];
		}

		$fields['created_at'] = $now;
		$inserted = $wpdb->insert($table, $fields); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if (! $inserted) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Mark subscription as inactive.
	 *
	 * @param string $endpoint Endpoint.
	 * @param string $error Last error.
	 * @return void
	 */
	public static function mark_inactive($endpoint, $error = '') {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::subscribers_table(),
			array(
				'active'     => 0,
				'last_error' => (string) $error,
				'updated_at' => current_time('mysql', true),
			),
			array('endpoint' => (string) $endpoint)
		);
	}

	/**
	 * Save last error while keeping subscription active.
	 *
	 * @param string $endpoint Endpoint.
	 * @param string $error Error text.
	 * @return void
	 */
	public static function mark_error($endpoint, $error = '') {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::subscribers_table(),
			array(
				'last_error' => (string) $error,
				'updated_at' => current_time('mysql', true),
			),
			array('endpoint' => (string) $endpoint)
		);
	}

	/**
	 * Mark endpoint recently sent.
	 *
	 * @param string $endpoint Endpoint.
	 * @return void
	 */
	public static function mark_sent($endpoint) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::subscribers_table(),
			array(
				'last_sent_at' => current_time('mysql', true),
				'last_error'   => null,
				'updated_at'   => current_time('mysql', true),
			),
			array('endpoint' => (string) $endpoint)
		);
	}

	/**
	 * Set inactive by endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @return void
	 */
	public static function unsubscribe($endpoint) {
		self::mark_inactive($endpoint, 'Unsubscribed by client');
	}

	/**
	 * Get active subscribers for audience.
	 *
	 * @param string      $audience Audience key.
	 * @param string|null $endpoint Optional endpoint for tests.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_active_subscribers($audience = 'all', $endpoint = null) {
		global $wpdb;
		$table = self::subscribers_table();

		if (is_string($endpoint) && '' !== $endpoint) {
			$sql = $wpdb->prepare("SELECT * FROM {$table} WHERE endpoint = %s AND active = 1", $endpoint);
		} elseif ('pwa' === $audience) {
			$sql = "SELECT * FROM {$table} WHERE active = 1 AND is_pwa = 1";
		} elseif ('logged_in' === $audience) {
			$sql = "SELECT * FROM {$table} WHERE active = 1 AND user_id IS NOT NULL AND user_id > 0";
		} else {
			$sql = "SELECT * FROM {$table} WHERE active = 1";
		}

		$rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = is_array($rows) ? $rows : array();
		return apply_filters('mad_baits_push_active_subscribers', $rows, $audience);
	}

	/**
	 * Record send log.
	 *
	 * @param array<string,mixed> $log Log row.
	 * @return void
	 */
	public static function insert_log($log) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::logs_table(),
			array(
				'title'            => isset($log['title']) ? (string) $log['title'] : '',
				'message'          => isset($log['message']) ? (string) $log['message'] : '',
				'target_url'       => isset($log['target_url']) ? (string) $log['target_url'] : '',
				'image_url'        => isset($log['image_url']) ? (string) $log['image_url'] : '',
				'audience'         => isset($log['audience']) ? (string) $log['audience'] : 'all',
				'attempted'        => isset($log['attempted']) ? absint($log['attempted']) : 0,
				'sent'             => isset($log['sent']) ? absint($log['sent']) : 0,
				'failed'           => isset($log['failed']) ? absint($log['failed']) : 0,
				'failures_summary' => isset($log['failures_summary']) ? (string) $log['failures_summary'] : '',
				'created_at'       => current_time('mysql', true),
			)
		);
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_logs($limit = 20) {
		global $wpdb;
		$limit = max(1, absint($limit));
		$sql   = $wpdb->prepare("SELECT * FROM " . self::logs_table() . " ORDER BY created_at DESC LIMIT %d", $limit);
		$rows  = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_array($rows) ? $rows : array();
	}

	/**
	 * Dashboard stats.
	 *
	 * @return array<string,int>
	 */
	public static function get_subscriber_stats() {
		global $wpdb;
		$table = self::subscribers_table();

		$total_active = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$pwa_users    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1 AND is_pwa = 1"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$logged_in    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 1 AND user_id IS NOT NULL AND user_id > 0"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inactive     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE active = 0"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return array(
			'total_active' => $total_active,
			'pwa_users'    => $pwa_users,
			'logged_in'    => $logged_in,
			'inactive'     => $inactive,
		);
	}

	/**
	 * Get latest subscribers.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_subscribers($limit = 10) {
		global $wpdb;
		$limit = max(1, absint($limit));
		$sql   = $wpdb->prepare("SELECT * FROM " . self::subscribers_table() . " ORDER BY created_at DESC LIMIT %d", $limit);
		$rows  = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return is_array($rows) ? $rows : array();
	}
}
