<?php
/**
 * Database schema and queries for mobile connector tables.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_DB
 */
class MBMC_DB {

	/**
	 * Schema version — bump when tables change.
	 */
	const DB_VERSION = '1.8.0';

	/**
	 * Devices table name including prefix.
	 *
	 * @return string
	 */
	public static function devices_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_app_devices';
	}

	/**
	 * Feedback table name including prefix.
	 *
	 * @return string
	 */
	public static function feedback_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_app_feedback';
	}

	/**
	 * Team catch reports table name including prefix.
	 *
	 * @return string
	 */
	public static function team_catch_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_team_catch_reports';
	}

	/**
	 * Fisheries table name including prefix.
	 *
	 * @return string
	 */
	public static function fisheries_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_fisheries';
	}

	/**
	 * Community catches table name including prefix.
	 *
	 * @return string
	 */
	public static function community_catches_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_community_catches';
	}

	/**
	 * Loyalty transactions table name including prefix.
	 *
	 * @return string
	 */
	public static function loyalty_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_transactions';
	}

	/**
	 * Loyalty config key/value table.
	 *
	 * @return string
	 */
	public static function loyalty_config_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_config';
	}

	/**
	 * Loyalty rewards catalog table.
	 *
	 * @return string
	 */
	public static function loyalty_rewards_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_rewards';
	}

	/**
	 * Loyalty achievements catalog table.
	 *
	 * @return string
	 */
	public static function loyalty_achievements_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_achievements';
	}

	/**
	 * Referrals table.
	 *
	 * @return string
	 */
	public static function loyalty_referrals_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_referrals';
	}

	/**
	 * Loyalty campaigns table.
	 *
	 * @return string
	 */
	public static function loyalty_campaigns_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_campaigns';
	}

	/**
	 * User achievement unlocks table.
	 *
	 * @return string
	 */
	public static function user_achievements_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_user_achievements';
	}

	/**
	 * Personal diary entries table.
	 *
	 * @return string
	 */
	public static function diary_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_diary_entries';
	}

	/**
	 * Team hub posts table.
	 *
	 * @return string
	 */
	public static function team_posts_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_team_posts';
	}

	/**
	 * Loyalty idempotency keys table.
	 *
	 * @return string
	 */
	public static function loyalty_idempotency_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_loyalty_idempotency';
	}

	/**
	 * Ask Mark / AI request logs table.
	 *
	 * @return string
	 */
	public static function ai_logs_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_ai_logs';
	}

	/**
	 * Notification logs table name including prefix.
	 *
	 * @return string
	 */
	public static function notification_logs_table() {
		global $wpdb;

		return $wpdb->prefix . 'madbaits_app_notification_logs';
	}

	/**
	 * Back-compat alias.
	 *
	 * @return string
	 */
	public static function table_name() {
		return self::devices_table();
	}

	/**
	 * Create or upgrade all plugin tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$devices = self::devices_table();
		$sql     = "CREATE TABLE {$devices} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL DEFAULT NULL,
			customer_id bigint(20) unsigned NULL DEFAULT NULL,
			device_id varchar(64) NOT NULL,
			push_token text NOT NULL,
			platform varchar(16) NOT NULL,
			app_version varchar(32) NULL DEFAULT NULL,
			notification_preferences longtext NULL,
			last_seen_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY device_id (device_id),
			KEY push_token (push_token(191)),
			KEY user_id (user_id),
			KEY customer_id (customer_id),
			KEY platform (platform)
		) {$charset};";
		dbDelta( $sql );

		$feedback = self::feedback_table();
		$sql      = "CREATE TABLE {$feedback} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL DEFAULT NULL,
			device_id varchar(64) NULL DEFAULT NULL,
			feedback_type varchar(32) NOT NULL DEFAULT 'app',
			title varchar(255) NULL DEFAULT NULL,
			message longtext NOT NULL,
			priority varchar(16) NOT NULL DEFAULT 'normal',
			app_version varchar(32) NULL DEFAULT NULL,
			platform varchar(16) NULL DEFAULT NULL,
			device_info longtext NULL,
			status varchar(32) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY device_id (device_id),
			KEY feedback_type (feedback_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$team = self::team_catch_table();
		$sql  = "CREATE TABLE {$team} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL DEFAULT NULL,
			fishery_id bigint(20) unsigned NULL DEFAULT NULL,
			team_role varchar(32) NOT NULL DEFAULT 'customer',
			angler_name varchar(255) NULL DEFAULT NULL,
			fish_weight varchar(64) NULL DEFAULT NULL,
			venue varchar(255) NOT NULL,
			bait_used varchar(255) NOT NULL,
			rig_used varchar(255) NULL DEFAULT NULL,
			notes longtext NULL,
			photo_url varchar(512) NULL DEFAULT NULL,
			permission_to_use tinyint(1) NOT NULL DEFAULT 0,
			social_caption longtext NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'submitted',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY fishery_id (fishery_id),
			KEY team_role (team_role),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$fisheries = self::fisheries_table();
		$sql       = "CREATE TABLE {$fisheries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fishery_name varchar(191) NOT NULL,
			lake_name varchar(191) NULL DEFAULT NULL,
			postcode varchar(32) NULL DEFAULT NULL,
			county varchar(128) NULL DEFAULT NULL,
			latitude decimal(10,7) NULL DEFAULT NULL,
			longitude decimal(10,7) NULL DEFAULT NULL,
			description longtext NULL,
			photos longtext NULL,
			created_by_user bigint(20) unsigned NULL DEFAULT NULL,
			approved tinyint(1) NOT NULL DEFAULT 0,
			rejection_reason varchar(255) NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY fishery_name (fishery_name),
			KEY postcode (postcode),
			KEY county (county),
			KEY approved (approved),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$community = self::community_catches_table();
		$sql       = "CREATE TABLE {$community} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			local_catch_id varchar(64) NOT NULL,
			angler_name varchar(255) NOT NULL,
			species varchar(128) NOT NULL,
			weight_display varchar(64) NULL DEFAULT NULL,
			venue varchar(255) NOT NULL,
			peg varchar(64) NULL DEFAULT NULL,
			bait_used varchar(255) NULL DEFAULT NULL,
			rig_used varchar(255) NULL DEFAULT NULL,
			notes longtext NULL,
			photo_url varchar(512) NULL DEFAULT NULL,
			caught_at datetime NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			rejection_reason varchar(255) NULL DEFAULT NULL,
			featured tinyint(1) NOT NULL DEFAULT 0,
			published_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_local_catch (user_id, local_catch_id),
			KEY status (status),
			KEY caught_at (caught_at),
			KEY published_at (published_at),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$logs = self::notification_logs_table();
		$sql  = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			device_id varchar(64) NULL DEFAULT NULL,
			push_token text NULL,
			notification_type varchar(64) NOT NULL,
			title varchar(255) NOT NULL,
			message longtext NOT NULL,
			payload longtext NULL,
			provider varchar(32) NOT NULL DEFAULT 'expo',
			status varchar(16) NOT NULL DEFAULT 'queued',
			provider_response longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY device_id (device_id),
			KEY notification_type (notification_type),
			KEY provider (provider),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$loyalty = self::loyalty_table();
		$sql     = "CREATE TABLE {$loyalty} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(16) NOT NULL DEFAULT 'earn',
			points int(11) NOT NULL DEFAULT 0,
			source varchar(64) NOT NULL,
			source_id varchar(128) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'approved',
			title varchar(255) NOT NULL,
			notes longtext NULL,
			created_at datetime NOT NULL,
			expires_at datetime NULL DEFAULT NULL,
			redeemed_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_source (user_id, source, source_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY expires_at (expires_at),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$config = self::loyalty_config_table();
		$sql    = "CREATE TABLE {$config} (
			config_key varchar(64) NOT NULL,
			config_value longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (config_key)
		) {$charset};";
		dbDelta( $sql );

		$rewards = self::loyalty_rewards_table();
		$sql     = "CREATE TABLE {$rewards} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(64) NOT NULL,
			title varchar(255) NOT NULL,
			description longtext NULL,
			image_url varchar(512) NULL DEFAULT NULL,
			points_cost int(11) NOT NULL DEFAULT 0,
			stock_qty int(11) NULL DEFAULT NULL,
			category varchar(64) NULL DEFAULT NULL,
			tier_minimum varchar(32) NULL DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			starts_at datetime NULL DEFAULT NULL,
			ends_at datetime NULL DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active),
			KEY category (category),
			KEY sort_order (sort_order)
		) {$charset};";
		dbDelta( $sql );

		$achievements = self::loyalty_achievements_table();
		$sql          = "CREATE TABLE {$achievements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(64) NOT NULL,
			title varchar(255) NOT NULL,
			description longtext NULL,
			category varchar(64) NOT NULL DEFAULT 'loyalty',
			icon varchar(64) NULL DEFAULT NULL,
			icon_url varchar(512) NULL DEFAULT NULL,
			criteria_type varchar(64) NOT NULL,
			criteria_value int(11) NOT NULL DEFAULT 1,
			criteria_meta longtext NULL,
			bonus_points int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			is_visible tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active),
			KEY category (category)
		) {$charset};";
		dbDelta( $sql );

		$referrals = self::loyalty_referrals_table();
		$sql       = "CREATE TABLE {$referrals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			referrer_user_id bigint(20) unsigned NOT NULL,
			referred_user_id bigint(20) unsigned NULL DEFAULT NULL,
			referred_email varchar(191) NULL DEFAULT NULL,
			referral_code varchar(64) NULL DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			order_id bigint(20) unsigned NULL DEFAULT NULL,
			points_awarded int(11) NOT NULL DEFAULT 0,
			notes longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY referrer_user_id (referrer_user_id),
			KEY referred_user_id (referred_user_id),
			KEY referral_code (referral_code),
			KEY status (status)
		) {$charset};";
		dbDelta( $sql );

		$campaigns = self::loyalty_campaigns_table();
		$sql       = "CREATE TABLE {$campaigns} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			campaign_type varchar(32) NOT NULL,
			points_amount int(11) NOT NULL DEFAULT 0,
			multiplier decimal(4,2) NOT NULL DEFAULT 1.00,
			reward_id bigint(20) unsigned NULL DEFAULT NULL,
			user_ids longtext NULL,
			message longtext NULL,
			status varchar(32) NOT NULL DEFAULT 'draft',
			starts_at datetime NULL DEFAULT NULL,
			ends_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_type (campaign_type),
			KEY status (status),
			KEY starts_at (starts_at)
		) {$charset};";
		dbDelta( $sql );

		$user_achievements = self::user_achievements_table();
		$sql               = "CREATE TABLE {$user_achievements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			achievement_id bigint(20) unsigned NOT NULL,
			achievement_slug varchar(64) NOT NULL,
			progress_value int(11) NOT NULL DEFAULT 0,
			unlocked_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_achievement (user_id, achievement_slug),
			KEY user_id (user_id),
			KEY achievement_id (achievement_id)
		) {$charset};";
		dbDelta( $sql );

		$diary = self::diary_table();
		$sql   = "CREATE TABLE {$diary} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			local_id varchar(64) NOT NULL,
			entry_type varchar(32) NOT NULL DEFAULT 'session',
			payload longtext NOT NULL,
			visibility varchar(16) NOT NULL DEFAULT 'private',
			client_updated_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_local (user_id, local_id),
			KEY user_id (user_id),
			KEY entry_type (entry_type),
			KEY updated_at (updated_at)
		) {$charset};";
		dbDelta( $sql );

		$team_posts = self::team_posts_table();
		$sql        = "CREATE TABLE {$team_posts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			author_user_id bigint(20) unsigned NULL DEFAULT NULL,
			title varchar(255) NOT NULL,
			body longtext NOT NULL,
			post_type varchar(32) NOT NULL DEFAULT 'announcement',
			audience_roles longtext NULL,
			is_published tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_type (post_type),
			KEY is_published (is_published),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$idempotency = self::loyalty_idempotency_table();
		$sql         = "CREATE TABLE {$idempotency} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			idempotency_key varchar(128) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			action_type varchar(32) NOT NULL,
			result_payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_user_action (idempotency_key, user_id, action_type),
			KEY user_id (user_id)
		) {$charset};";
		dbDelta( $sql );

		$ai_logs = self::ai_logs_table();
		$sql     = "CREATE TABLE {$ai_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL DEFAULT NULL,
			endpoint varchar(64) NOT NULL,
			request_summary varchar(255) NULL DEFAULT NULL,
			response_status varchar(16) NOT NULL DEFAULT 'ok',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY endpoint (endpoint),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		update_option( 'mbmc_db_version', self::DB_VERSION );

		if ( class_exists( 'MBMC_Loyalty_Config' ) ) {
			MBMC_Loyalty_Config::seed_defaults();
		}
	}

	/**
	 * Run install when plugin version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'mbmc_db_version', '' );

		if ( self::DB_VERSION !== $installed ) {
			self::install();
		}
	}

	/**
	 * Count rows in a table.
	 *
	 * @param string $table Full table name.
	 * @return int
	 */
	public static function count_rows( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Find device row by device_id.
	 *
	 * @param string $device_id Device identifier.
	 * @return object|null
	 */
	public static function get_device_by_device_id( $device_id ) {
		global $wpdb;

		$table = self::devices_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE device_id = %s LIMIT 1",
				$device_id
			)
		);
	}

	/**
	 * Insert or update a device record.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function upsert_device( array $data ) {
		global $wpdb;

		$table    = self::devices_table();
		$existing = self::get_device_by_device_id( $data['device_id'] );
		$now      = current_time( 'mysql', true );

		$row = array(
			'device_id'                => $data['device_id'],
			'push_token'               => $data['push_token'],
			'platform'                 => $data['platform'],
			'app_version'              => $data['app_version'] ?? null,
			'notification_preferences' => $data['notification_preferences'] ?? null,
			'last_seen_at'             => $now,
			'updated_at'               => $now,
		);

		if ( ! empty( $data['user_id'] ) ) {
			$row['user_id'] = (int) $data['user_id'];
		}

		if ( ! empty( $data['customer_id'] ) ) {
			$row['customer_id'] = (int) $data['customer_id'];
		}

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$table,
				$row,
				array( 'device_id' => $data['device_id'] )
			);

			return false !== $updated ? (int) $existing->id : false;
		}

		$row['created_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $row );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Link an existing device row to a WordPress / Woo customer.
	 *
	 * @param string $device_id   Device identifier.
	 * @param int    $user_id     WordPress user ID.
	 * @param int    $customer_id WooCommerce customer ID.
	 * @return bool
	 */
	public static function link_device_to_user( $device_id, $user_id, $customer_id ) {
		global $wpdb;

		$device_id   = mbmc_sanitize_device_id( $device_id );
		$user_id     = absint( $user_id );
		$customer_id = absint( $customer_id );

		if ( '' === $device_id || $user_id <= 0 ) {
			return false;
		}

		$table = self::devices_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'user_id'      => $user_id,
				'customer_id'  => $customer_id > 0 ? $customer_id : $user_id,
				'updated_at'   => $now,
				'last_seen_at' => $now,
			),
			array( 'device_id' => $device_id ),
			array( '%d', '%d', '%s', '%s' ),
			array( '%s' )
		);

		return false !== $updated;
	}

	/**
	 * Delete device by device_id.
	 *
	 * @param string $device_id Device identifier.
	 * @return bool
	 */
	public static function delete_device( $device_id ) {
		global $wpdb;

		$table = self::devices_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$table,
			array( 'device_id' => $device_id ),
			array( '%s' )
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Update preferences for an existing device.
	 *
	 * @param string $device_id Device identifier.
	 * @param string $preferences_json JSON preferences.
	 * @return bool
	 */
	public static function update_preferences( $device_id, $preferences_json ) {
		global $wpdb;

		$table = self::devices_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'notification_preferences' => $preferences_json,
				'last_seen_at'             => $now,
				'updated_at'               => $now,
			),
			array( 'device_id' => $device_id ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);

		return false !== $updated;
	}

	/**
	 * List recent devices for admin.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function list_devices( $limit = 50 ) {
		global $wpdb;

		$table = self::devices_table();
		$limit = max( 1, min( 200, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Find registered devices for a WooCommerce customer ID.
	 *
	 * @param int $customer_id WooCommerce customer user ID.
	 * @param int $limit       Max rows.
	 * @return array<int, object>
	 */
	public static function get_devices_by_customer_id( $customer_id, $limit = 10 ) {
		global $wpdb;

		$customer_id = absint( $customer_id );

		if ( $customer_id <= 0 ) {
			return array();
		}

		$table = self::devices_table();
		$limit = max( 1, min( 100, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d OR user_id = %d ORDER BY updated_at DESC LIMIT %d",
				$customer_id,
				$customer_id,
				$limit
			)
		);
	}

	/**
	 * Insert feedback row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public static function insert_feedback( array $data ) {
		global $wpdb;

		$table = self::feedback_table();
		$now   = current_time( 'mysql', true );

		$row = array(
			'user_id'       => ! empty( $data['user_id'] ) ? (int) $data['user_id'] : null,
			'device_id'     => $data['device_id'] ?? null,
			'feedback_type' => $data['feedback_type'] ?? 'app',
			'title'         => $data['title'] ?? null,
			'message'       => $data['message'],
			'priority'      => $data['priority'] ?? 'normal',
			'app_version'   => $data['app_version'] ?? null,
			'platform'      => $data['platform'] ?? null,
			'device_info'   => $data['device_info'] ?? null,
			'status'        => 'new',
			'created_at'    => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $row );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * List recent feedback for admin.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function list_feedback( $limit = 50 ) {
		global $wpdb;

		$table = self::feedback_table();
		$limit = max( 1, min( 200, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Insert team catch report.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public static function insert_team_catch_report( array $data ) {
		global $wpdb;

		$table = self::team_catch_table();
		$now   = current_time( 'mysql', true );

		$row = array(
			'user_id'            => ! empty( $data['user_id'] ) ? (int) $data['user_id'] : null,
			'fishery_id'         => ! empty( $data['fishery_id'] ) ? (int) $data['fishery_id'] : null,
			'team_role'          => $data['team_role'] ?? 'customer',
			'angler_name'        => $data['angler_name'] ?? null,
			'fish_weight'        => $data['fish_weight'] ?? null,
			'venue'              => $data['venue'],
			'bait_used'          => $data['bait_used'],
			'rig_used'           => $data['rig_used'] ?? null,
			'notes'              => $data['notes'] ?? null,
			'photo_url'          => $data['photo_url'] ?? null,
			'permission_to_use'  => ! empty( $data['permission_to_use'] ) ? 1 : 0,
			'social_caption'     => $data['social_caption'],
			'status'             => $data['status'] ?? 'submitted',
			'created_at'         => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $row );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * List recent team catch reports for admin.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function list_team_catch_reports( $limit = 50 ) {
		global $wpdb;

		$table = self::team_catch_table();
		$limit = max( 1, min( 200, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Insert fishery record.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public static function insert_fishery( array $data ) {
		global $wpdb;

		$table = self::fisheries_table();
		$now   = current_time( 'mysql', true );
		$row   = array(
			'fishery_name'    => $data['fishery_name'],
			'lake_name'       => $data['lake_name'] ?? null,
			'postcode'        => $data['postcode'] ?? null,
			'county'          => $data['county'] ?? null,
			'latitude'        => isset( $data['latitude'] ) ? (float) $data['latitude'] : null,
			'longitude'       => isset( $data['longitude'] ) ? (float) $data['longitude'] : null,
			'description'     => $data['description'] ?? null,
			'photos'          => $data['photos'] ?? null,
			'created_by_user' => ! empty( $data['created_by_user'] ) ? (int) $data['created_by_user'] : null,
			'approved'        => ! empty( $data['approved'] ) ? 1 : 0,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $row );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Find likely duplicate fishery.
	 *
	 * @param string     $fishery_name Fishery name.
	 * @param string     $postcode     Postcode.
	 * @param float|null $latitude     Latitude.
	 * @param float|null $longitude    Longitude.
	 * @return object|null
	 */
	public static function find_duplicate_fishery( $fishery_name, $postcode = '', $latitude = null, $longitude = null ) {
		$fishery_name = mbmc_sanitize_short_text( (string) $fishery_name, 191 );
		$postcode     = mbmc_sanitize_short_text( (string) $postcode, 32 );
		if ( '' === $fishery_name ) {
			return null;
		}

		$candidates = self::search_fisheries(
			array(
				'query'        => $fishery_name,
				'include_all'  => true,
				'limit'        => 30,
			)
		);

		$normalized_name = strtolower( preg_replace( '/\s+/', ' ', $fishery_name ) );
		$normalized_postcode = strtolower( preg_replace( '/\s+/', '', $postcode ) );

		foreach ( $candidates as $candidate ) {
			$candidate_name = strtolower( preg_replace( '/\s+/', ' ', (string) $candidate->fishery_name ) );
			similar_text( $normalized_name, $candidate_name, $similarity );

			if ( $similarity >= 88 ) {
				return $candidate;
			}

			if ( '' !== $normalized_postcode && ! empty( $candidate->postcode ) ) {
				$candidate_postcode = strtolower( preg_replace( '/\s+/', '', (string) $candidate->postcode ) );
				if ( $normalized_postcode === $candidate_postcode ) {
					return $candidate;
				}
			}

			if (
				null !== $latitude && null !== $longitude &&
				null !== $candidate->latitude && null !== $candidate->longitude
			) {
				$distance_km = self::distance_km(
					(float) $latitude,
					(float) $longitude,
					(float) $candidate->latitude,
					(float) $candidate->longitude
				);
				if ( $distance_km <= 1.2 ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Search fisheries.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function search_fisheries( array $args = array() ) {
		global $wpdb;

		$table       = self::fisheries_table();
		$query       = mbmc_sanitize_short_text( (string) ( $args['query'] ?? '' ), 191 );
		$postcode    = mbmc_sanitize_short_text( (string) ( $args['postcode'] ?? '' ), 32 );
		$include_all = ! empty( $args['include_all'] );
		$limit       = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );

		$where_parts = array();
		$values      = array();

		if ( ! $include_all ) {
			$where_parts[] = 'approved = 1';
		}
		if ( '' !== $query ) {
			$like = '%' . $wpdb->esc_like( $query ) . '%';
			$where_parts[] = '(fishery_name LIKE %s OR lake_name LIKE %s OR county LIKE %s OR postcode LIKE %s)';
			array_push( $values, $like, $like, $like, $like );
		}
		if ( '' !== $postcode ) {
			$where_parts[] = 'postcode = %s';
			$values[]      = $postcode;
		}

		$where_sql = ! empty( $where_parts ) ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';
		$sql       = "SELECT * FROM {$table} {$where_sql} ORDER BY approved DESC, updated_at DESC LIMIT %d";
		$values[]  = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * List nearby fisheries sorted by distance.
	 *
	 * @param float $latitude Latitude.
	 * @param float $longitude Longitude.
	 * @param int   $limit Result limit.
	 * @return array<int, object>
	 */
	public static function list_nearby_fisheries( $latitude, $longitude, $limit = 20 ) {
		global $wpdb;

		$table = self::fisheries_table();
		$limit = max( 1, min( 100, (int) $limit ) );
		$lat   = (float) $latitude;
		$lng   = (float) $longitude;

		$sql = "SELECT *,
			(6371 * ACOS(
				COS(RADIANS(%f)) * COS(RADIANS(latitude)) *
				COS(RADIANS(longitude) - RADIANS(%f)) +
				SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
			)) AS distance_km
			FROM {$table}
			WHERE approved = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL
			ORDER BY distance_km ASC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( $sql, $lat, $lng, $lat, $limit ) );
	}

	/**
	 * List fisheries for moderation.
	 *
	 * @param bool $approved Whether approved.
	 * @param int  $limit Max rows.
	 * @return array<int, object>
	 */
	public static function list_fisheries_for_moderation( $approved = false, $limit = 200 ) {
		global $wpdb;

		$table = self::fisheries_table();
		$limit = max( 1, min( 500, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE approved = %d ORDER BY created_at DESC LIMIT %d",
				$approved ? 1 : 0,
				$limit
			)
		);
	}

	/**
	 * Count fisheries in the database.
	 *
	 * @param bool|null $approved When true/false, filter by approval; null counts all.
	 * @return int
	 */
	public static function count_fisheries( $approved = null ) {
		global $wpdb;

		$table = self::fisheries_table();

		if ( null === $approved ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE approved = %d",
				$approved ? 1 : 0
			)
		);
	}

	/**
	 * Set fishery approval status.
	 *
	 * @param int    $id Fishery ID.
	 * @param bool   $approved Status.
	 * @param string $rejection_reason Optional rejection reason.
	 * @return bool
	 */
	public static function set_fishery_approval( $id, $approved, $rejection_reason = '' ) {
		global $wpdb;

		$table = self::fisheries_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'approved'         => $approved ? 1 : 0,
				'rejection_reason' => $approved ? null : mbmc_sanitize_short_text( (string) $rejection_reason, 255 ),
				'updated_at'       => $now,
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Fetch fishery by ID.
	 *
	 * @param int $id Fishery ID.
	 * @return object|null
	 */
	public static function get_fishery_by_id( $id ) {
		global $wpdb;

		$table = self::fisheries_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $id
			)
		);
	}

	/**
	 * Insert or update a community catch publish request.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public static function upsert_community_catch( array $data ) {
		global $wpdb;

		$table          = self::community_catches_table();
		$user_id        = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$local_catch_id = isset( $data['local_catch_id'] ) ? mbmc_sanitize_short_text( (string) $data['local_catch_id'], 64 ) : '';

		if ( $user_id <= 0 || '' === $local_catch_id ) {
			return false;
		}

		$existing = self::get_community_catch_by_local_id( $user_id, $local_catch_id );
		$now      = current_time( 'mysql', true );

		$row = array(
			'user_id'        => $user_id,
			'local_catch_id' => $local_catch_id,
			'angler_name'    => mbmc_sanitize_short_text( (string) ( $data['angler_name'] ?? '' ), 255 ),
			'species'        => mbmc_sanitize_short_text( (string) ( $data['species'] ?? '' ), 128 ),
			'weight_display' => mbmc_sanitize_short_text( (string) ( $data['weight_display'] ?? '' ), 64 ),
			'venue'          => mbmc_sanitize_short_text( (string) ( $data['venue'] ?? '' ), 255 ),
			'peg'            => mbmc_sanitize_short_text( (string) ( $data['peg'] ?? '' ), 64 ),
			'bait_used'      => mbmc_sanitize_short_text( (string) ( $data['bait_used'] ?? '' ), 255 ),
			'rig_used'       => mbmc_sanitize_short_text( (string) ( $data['rig_used'] ?? '' ), 255 ),
			'notes'          => mbmc_sanitize_long_text( (string) ( $data['notes'] ?? '' ) ),
			'photo_url'      => ! empty( $data['photo_url'] ) ? mbmc_sanitize_url_field( (string) $data['photo_url'] ) : null,
			'caught_at'      => self::normalize_mysql_datetime( $data['caught_at'] ?? $now ),
			'status'         => mbmc_sanitize_short_text( (string) ( $data['status'] ?? 'pending' ), 32 ),
			'updated_at'     => $now,
		);

		if ( $existing ) {
			if ( 'published' === $existing->status ) {
				return (int) $existing->id;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$table,
				$row,
				array( 'id' => (int) $existing->id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return false !== $updated ? (int) $existing->id : false;
		}

		$row['created_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Fetch a community catch by user and local catch id.
	 *
	 * @param int    $user_id User ID.
	 * @param string $local_catch_id Local catch id from the app.
	 * @return object|null
	 */
	public static function get_community_catch_by_local_id( $user_id, $local_catch_id ) {
		global $wpdb;

		$table = self::community_catches_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND local_catch_id = %s LIMIT 1",
				(int) $user_id,
				mbmc_sanitize_short_text( (string) $local_catch_id, 64 )
			)
		);
	}

	/**
	 * Fetch a community catch by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get_community_catch_by_id( $id ) {
		global $wpdb;

		$table = self::community_catches_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $id
			)
		);
	}

	/**
	 * List published community catches for the home feed.
	 *
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public static function list_published_community_catches( $limit = 12 ) {
		global $wpdb;

		$table = self::community_catches_table();
		$limit = max( 1, min( 40, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY published_at DESC, caught_at DESC LIMIT %d",
				'published',
				$limit
			)
		);
	}

	/**
	 * List community catches awaiting moderation.
	 *
	 * @param string $status Status filter.
	 * @param int    $limit Max rows.
	 * @return object[]
	 */
	public static function list_community_catches_by_status( $status, $limit = 150 ) {
		global $wpdb;

		$table  = self::community_catches_table();
		$limit  = max( 1, min( 500, (int) $limit ) );
		$status = mbmc_sanitize_short_text( (string) $status, 32 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
				$status,
				$limit
			)
		);
	}

	/**
	 * Approve or reject a community catch.
	 *
	 * @param int    $id Row ID.
	 * @param string $status New status.
	 * @param string $rejection_reason Optional rejection reason.
	 * @return bool
	 */
	public static function set_community_catch_status( $id, $status, $rejection_reason = '' ) {
		global $wpdb;

		$table  = self::community_catches_table();
		$now    = current_time( 'mysql', true );
		$status = mbmc_sanitize_short_text( (string) $status, 32 );

		$data = array(
			'status'           => $status,
			'rejection_reason' => 'rejected' === $status
				? mbmc_sanitize_short_text( (string) $rejection_reason, 255 )
				: null,
			'updated_at'       => $now,
		);

		if ( 'published' === $status ) {
			$data['published_at'] = $now;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Normalize datetime input to MySQL UTC format.
	 *
	 * @param mixed $value Raw datetime.
	 * @return string
	 */
	private static function normalize_mysql_datetime( $value ) {
		$raw = trim( (string) $value );
		if ( '' === $raw ) {
			return current_time( 'mysql', true );
		}

		$timestamp = strtotime( $raw );
		if ( false === $timestamp ) {
			return current_time( 'mysql', true );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Haversine distance in kilometers.
	 *
	 * @param float $lat1 Lat 1.
	 * @param float $lng1 Lng 1.
	 * @param float $lat2 Lat 2.
	 * @param float $lng2 Lng 2.
	 * @return float
	 */
	private static function distance_km( $lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6371;
		$d_lat        = deg2rad( $lat2 - $lat1 );
		$d_lng        = deg2rad( $lng2 - $lng1 );
		$a            = sin( $d_lat / 2 ) * sin( $d_lat / 2 )
			+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
			* sin( $d_lng / 2 ) * sin( $d_lng / 2 );
		$c            = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}

	/**
	 * Insert notification log row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public static function insert_notification_log( array $data ) {
		global $wpdb;

		$table = self::notification_logs_table();
		$now   = current_time( 'mysql', true );

		$row = array(
			'device_id'         => $data['device_id'] ?? null,
			'push_token'        => $data['push_token'] ?? null,
			'notification_type' => $data['notification_type'] ?? 'unknown',
			'title'             => $data['title'] ?? '',
			'message'           => $data['message'] ?? '',
			'payload'           => $data['payload'] ?? null,
			'provider'          => $data['provider'] ?? 'expo',
			'status'            => $data['status'] ?? 'queued',
			'provider_response' => $data['provider_response'] ?? null,
			'created_at'        => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $row );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * List recent notification logs for admin.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, object>
	 */
	public static function list_notification_logs( $limit = 25 ) {
		global $wpdb;

		$table = self::notification_logs_table();
		$limit = max( 1, min( 200, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Insert loyalty transaction row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public static function insert_loyalty_transaction( $data ) {
		global $wpdb;

		$table = self::loyalty_table();
		$row   = array(
			'user_id'     => absint( $data['user_id'] ?? 0 ),
			'type'        => (string) ( $data['type'] ?? 'earn' ),
			'points'      => (int) ( $data['points'] ?? 0 ),
			'source'      => (string) ( $data['source'] ?? 'admin' ),
			'source_id'   => (string) ( $data['source_id'] ?? '' ),
			'status'      => (string) ( $data['status'] ?? 'approved' ),
			'title'       => (string) ( $data['title'] ?? '' ),
			'notes'       => $data['notes'] ?? null,
			'created_at'  => (string) ( $data['created_at'] ?? current_time( 'mysql', true ) ),
			'expires_at'  => $data['expires_at'] ?? null,
			'redeemed_at' => $data['redeemed_at'] ?? null,
		);

		if ( $row['user_id'] <= 0 || '' === $row['title'] ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $row );

		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Check duplicate source award.
	 *
	 * @param int    $user_id   User id.
	 * @param string $source    Source.
	 * @param string $source_id Source id.
	 * @return bool
	 */
	public static function loyalty_source_exists( $user_id, $source, $source_id ) {
		global $wpdb;

		$table = self::loyalty_table();
		$user  = absint( $user_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND source = %s AND source_id = %s AND status NOT IN ('rejected') LIMIT 1",
				$user,
				$source,
				$source_id
			)
		);

		return ! empty( $id );
	}

	/**
	 * Mark expired earn rows.
	 *
	 * @param int $user_id User id (0 = all users).
	 * @return int
	 */
	public static function expire_loyalty_points( $user_id = 0 ) {
		global $wpdb;

		$table = self::loyalty_table();
		$now   = current_time( 'mysql', true );
		$user  = absint( $user_id );

		if ( $user > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'expired' WHERE user_id = %d AND type = 'earn' AND status = 'approved' AND expires_at IS NOT NULL AND expires_at <= %s",
					$user,
					$now
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired' WHERE type = 'earn' AND status = 'approved' AND expires_at IS NOT NULL AND expires_at <= %s",
				$now
			)
		);
	}

	/**
	 * Sum available loyalty balance.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public static function get_loyalty_balance( $user_id ) {
		global $wpdb;

		$table = self::loyalty_table();
		$user  = absint( $user_id );
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND status IN ('approved', 'redeemed') AND (expires_at IS NULL OR expires_at > %s OR type = 'redeem')",
				$user,
				$now
			)
		);

		return (int) $sum;
	}

	/**
	 * Pending earn points.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public static function get_loyalty_pending_points( $user_id ) {
		global $wpdb;

		$table = self::loyalty_table();
		$user  = absint( $user_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND type = 'earn' AND status = 'pending'",
				$user
			)
		);

		return (int) $sum;
	}

	/**
	 * Lifetime approved earn total.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public static function get_loyalty_lifetime_earned( $user_id ) {
		global $wpdb;

		$table = self::loyalty_table();
		$user  = absint( $user_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND type = 'earn' AND status NOT IN ('rejected')",
				$user
			)
		);

		return (int) $sum;
	}

	/**
	 * Points expiring within N days.
	 *
	 * @param int $user_id User id.
	 * @param int $days    Days window.
	 * @return array{points: int, date: string|null}
	 */
	public static function get_loyalty_expiring_soon( $user_id, $days = 30 ) {
		global $wpdb;

		$table  = self::loyalty_table();
		$user   = absint( $user_id );
		$now    = current_time( 'mysql', true );
		$future = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' +' . absint( $days ) . ' days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$points = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND type = 'earn' AND status = 'approved' AND expires_at IS NOT NULL AND expires_at > %s AND expires_at <= %s",
				$user,
				$now,
				$future
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(expires_at) FROM {$table} WHERE user_id = %d AND type = 'earn' AND status = 'approved' AND expires_at IS NOT NULL AND expires_at > %s AND expires_at <= %s",
				$user,
				$now,
				$future
			)
		);

		return array(
			'points' => $points,
			'date'   => $date ? mbmc_format_datetime_for_api( $date ) : null,
		);
	}

	/**
	 * List loyalty transactions for user.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Max rows.
	 * @return array<int, object>
	 */
	public static function list_loyalty_transactions( $user_id, $limit = 50 ) {
		global $wpdb;

		$table = self::loyalty_table();
		$user  = absint( $user_id );
		$limit = max( 1, min( 100, (int) $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
				$user,
				$limit
			)
		);
	}

	// --- Loyalty config, catalog, referrals, campaigns, analytics ---

	public static function set_loyalty_config( $key, $value ) {
		global $wpdb;
		$table = self::loyalty_config_table();
		$key   = sanitize_key( (string) $key );
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT config_key FROM {$table} WHERE config_key = %s", $key ) );
		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return false !== $wpdb->update( $table, array( 'config_value' => (string) $value, 'updated_at' => $now ), array( 'config_key' => $key ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->insert( $table, array( 'config_key' => $key, 'config_value' => (string) $value, 'updated_at' => $now ) );
	}

	public static function loyalty_config_exists( $key ) {
		global $wpdb;
		$table = self::loyalty_config_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return ! empty( $wpdb->get_var( $wpdb->prepare( "SELECT config_key FROM {$table} WHERE config_key = %s", sanitize_key( (string) $key ) ) ) );
	}

	public static function get_loyalty_config_map() {
		global $wpdb;
		$table = self::loyalty_config_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT config_key, config_value FROM {$table}" );
		$map  = array();
		foreach ( $rows as $row ) {
			$map[ (string) $row->config_key ] = (string) $row->config_value;
		}
		return $map;
	}

	public static function count_loyalty_rewards() {
		global $wpdb;
		$table = self::loyalty_rewards_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function count_loyalty_achievements() {
		global $wpdb;
		$table = self::loyalty_achievements_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function insert_loyalty_reward( $data ) {
		global $wpdb;
		$table = self::loyalty_rewards_table();
		$now   = current_time( 'mysql', true );
		$row   = array(
			'slug'         => sanitize_title( (string) ( $data['slug'] ?? uniqid( 'reward_', true ) ) ),
			'title'        => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'description'  => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'image_url'    => esc_url_raw( (string) ( $data['image_url'] ?? '' ) ) ?: null,
			'points_cost'  => (int) ( $data['points_cost'] ?? 0 ),
			'stock_qty'    => isset( $data['stock_qty'] ) ? (int) $data['stock_qty'] : null,
			'category'     => sanitize_key( (string) ( $data['category'] ?? 'general' ) ),
			'tier_minimum' => ! empty( $data['tier_minimum'] ) ? sanitize_key( (string) $data['tier_minimum'] ) : null,
			'is_active'    => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'starts_at'    => ! empty( $data['starts_at'] ) ? self::normalize_mysql_datetime( $data['starts_at'] ) : null,
			'ends_at'      => ! empty( $data['ends_at'] ) ? self::normalize_mysql_datetime( $data['ends_at'] ) : null,
			'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $table, $row );
		return false !== $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update_loyalty_reward( $id, $data ) {
		global $wpdb;
		$table = self::loyalty_rewards_table();
		$row   = array( 'updated_at' => current_time( 'mysql', true ) );
		foreach ( array( 'title', 'description', 'image_url', 'category', 'tier_minimum' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$row[ $field ] = 'description' === $field ? wp_kses_post( (string) $data[ $field ] ) : sanitize_text_field( (string) $data[ $field ] );
			}
		}
		foreach ( array( 'points_cost', 'stock_qty', 'sort_order', 'is_active' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$row[ $field ] = (int) $data[ $field ];
			}
		}
		if ( isset( $data['starts_at'] ) ) {
			$row['starts_at'] = $data['starts_at'] ? self::normalize_mysql_datetime( $data['starts_at'] ) : null;
		}
		if ( isset( $data['ends_at'] ) ) {
			$row['ends_at'] = $data['ends_at'] ? self::normalize_mysql_datetime( $data['ends_at'] ) : null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( $table, $row, array( 'id' => (int) $id ) );
	}

	public static function delete_loyalty_reward( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( self::loyalty_rewards_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public static function get_loyalty_reward( $id ) {
		global $wpdb;
		$table = self::loyalty_rewards_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	public static function list_loyalty_rewards( $active_only = false ) {
		global $wpdb;
		$table = self::loyalty_rewards_table();
		$sql   = "SELECT * FROM {$table}";
		if ( $active_only ) {
			$now = current_time( 'mysql', true );
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= %s) AND (ends_at IS NULL OR ends_at >= %s) ORDER BY sort_order ASC, points_cost ASC",
				$now,
				$now
			);
		} else {
			$sql .= ' ORDER BY sort_order ASC, points_cost ASC';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $sql );
	}

	public static function insert_loyalty_achievement( $data ) {
		global $wpdb;
		$table = self::loyalty_achievements_table();
		$now   = current_time( 'mysql', true );
		$row   = array(
			'slug'            => sanitize_title( (string) ( $data['slug'] ?? uniqid( 'ach_', true ) ) ),
			'title'           => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'description'     => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'category'        => sanitize_key( (string) ( $data['category'] ?? 'loyalty' ) ),
			'icon'            => sanitize_text_field( (string) ( $data['icon'] ?? 'ribbon-outline' ) ),
			'icon_url'        => esc_url_raw( (string) ( $data['icon_url'] ?? '' ) ) ?: null,
			'criteria_type'   => sanitize_key( (string) ( $data['criteria_type'] ?? 'manual' ) ),
			'criteria_value'  => (int) ( $data['criteria_value'] ?? 1 ),
			'criteria_meta'   => isset( $data['criteria_meta'] ) ? wp_json_encode( $data['criteria_meta'] ) : null,
			'bonus_points'    => (int) ( $data['bonus_points'] ?? 0 ),
			'is_active'       => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'is_visible'      => isset( $data['is_visible'] ) ? (int) (bool) $data['is_visible'] : 1,
			'sort_order'      => (int) ( $data['sort_order'] ?? 0 ),
			'created_at'      => $now,
			'updated_at'      => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $table, $row );
		return false !== $ok ? (int) $wpdb->insert_id : false;
	}

	public static function update_loyalty_achievement( $id, $data ) {
		global $wpdb;
		$table = self::loyalty_achievements_table();
		$row   = array( 'updated_at' => current_time( 'mysql', true ) );
		$map   = array( 'title', 'description', 'category', 'icon', 'icon_url', 'criteria_type' );
		foreach ( $map as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$row[ $field ] = 'description' === $field ? wp_kses_post( (string) $data[ $field ] ) : sanitize_text_field( (string) $data[ $field ] );
			}
		}
		foreach ( array( 'criteria_value', 'bonus_points', 'sort_order', 'is_active', 'is_visible' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$row[ $field ] = (int) $data[ $field ];
			}
		}
		if ( isset( $data['criteria_meta'] ) ) {
			$row['criteria_meta'] = wp_json_encode( $data['criteria_meta'] );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( $table, $row, array( 'id' => (int) $id ) );
	}

	public static function delete_loyalty_achievement( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( self::loyalty_achievements_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public static function get_loyalty_achievement( $id ) {
		global $wpdb;
		$table = self::loyalty_achievements_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	public static function list_loyalty_achievements( $active_only = false ) {
		global $wpdb;
		$table = self::loyalty_achievements_table();
		$sql   = $active_only
			? "SELECT * FROM {$table} WHERE is_active = 1 AND is_visible = 1 ORDER BY sort_order ASC"
			: "SELECT * FROM {$table} ORDER BY sort_order ASC";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $sql );
	}

	public static function list_loyalty_referrals( $status = '', $limit = 100 ) {
		global $wpdb;
		$table = self::loyalty_referrals_table();
		$limit = max( 1, min( 200, (int) $limit ) );
		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d", sanitize_key( $status ), $limit ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ) );
	}

	public static function update_loyalty_referral( $id, $data ) {
		global $wpdb;
		$row = array_merge( array( 'updated_at' => current_time( 'mysql', true ) ), $data );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( self::loyalty_referrals_table(), $row, array( 'id' => (int) $id ) );
	}

	public static function insert_loyalty_campaign( $data ) {
		global $wpdb;
		$table = self::loyalty_campaigns_table();
		$now   = current_time( 'mysql', true );
		$row   = array(
			'title'          => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'campaign_type'  => sanitize_key( (string) ( $data['campaign_type'] ?? 'bonus_all' ) ),
			'points_amount'  => (int) ( $data['points_amount'] ?? 0 ),
			'multiplier'     => (float) ( $data['multiplier'] ?? 1 ),
			'reward_id'      => ! empty( $data['reward_id'] ) ? (int) $data['reward_id'] : null,
			'user_ids'       => isset( $data['user_ids'] ) ? wp_json_encode( $data['user_ids'] ) : null,
			'message'        => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ),
			'status'         => sanitize_key( (string) ( $data['status'] ?? 'draft' ) ),
			'starts_at'      => ! empty( $data['starts_at'] ) ? self::normalize_mysql_datetime( $data['starts_at'] ) : null,
			'ends_at'        => ! empty( $data['ends_at'] ) ? self::normalize_mysql_datetime( $data['ends_at'] ) : null,
			'created_at'     => $now,
			'updated_at'     => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $table, $row );
		return false !== $ok ? (int) $wpdb->insert_id : false;
	}

	public static function list_loyalty_campaigns( $limit = 50 ) {
		global $wpdb;
		$table = self::loyalty_campaigns_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", max( 1, (int) $limit ) ) );
	}

	public static function list_loyalty_user_balances( $limit = 100 ) {
		global $wpdb;
		$table = self::loyalty_table();
		$limit = max( 1, min( 500, (int) $limit ) );
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COALESCE(SUM(CASE WHEN status IN ('approved','redeemed') AND (expires_at IS NULL OR expires_at > %s OR type = 'redeem') THEN points ELSE 0 END), 0) AS balance, COALESCE(SUM(CASE WHEN type='earn' AND status NOT IN ('rejected') THEN GREATEST(points,0) ELSE 0 END), 0) AS lifetime FROM {$table} GROUP BY user_id HAVING balance != 0 OR lifetime != 0 ORDER BY balance DESC LIMIT %d",
				$now,
				$limit
			)
		);
	}

	public static function search_loyalty_users( $query, $limit = 25 ) {
		$query = trim( (string) $query );
		if ( '' === $query ) {
			return array();
		}
		$args = array(
			'number'         => max( 1, min( 50, (int) $limit ) ),
			'search'         => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'fields'         => array( 'ID', 'display_name', 'user_email' ),
		);
		if ( is_numeric( $query ) ) {
			$user = get_user_by( 'id', (int) $query );
			return $user ? array( $user ) : array();
		}
		return get_users( $args );
	}

	public static function reject_loyalty_source_transactions( $user_id, $source, $source_id ) {
		global $wpdb;
		$table = self::loyalty_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'rejected' WHERE user_id = %d AND source = %s AND source_id = %s AND status IN ('pending','approved')",
				absint( $user_id ),
				sanitize_key( $source ),
				sanitize_text_field( (string) $source_id )
			)
		);
	}

	public static function set_community_catch_featured( $id, $featured ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			self::community_catches_table(),
			array( 'featured' => $featured ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function get_loyalty_analytics() {
		global $wpdb;
		$table = self::loyalty_table();
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$issued = (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE type='earn' AND status NOT IN ('rejected')" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$redeemed = (int) abs( (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE type='redeem' AND status='redeemed'" ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$expired = (int) $wpdb->get_var( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE status='expired'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$liability = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points),0) FROM {$table} WHERE type='earn' AND status='approved' AND (expires_at IS NULL OR expires_at > %s)", $now ) );
		$community = self::community_catches_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$catch_reports = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$community}" );
		$referrals = self::loyalty_referrals_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$referral_conversions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$referrals} WHERE status='approved'" );
		return compact( 'issued', 'redeemed', 'expired', 'liability', 'catch_reports', 'referral_conversions' );
	}

	// --- User achievements, diary, team posts, idempotency, AI logs ---

	public static function get_user_achievement_unlock( $user_id, $achievement_slug ) {
		global $wpdb;
		$table = self::user_achievements_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND achievement_slug = %s LIMIT 1",
				absint( $user_id ),
				sanitize_title( (string) $achievement_slug )
			)
		);
	}

	public static function list_user_achievements( $user_id ) {
		global $wpdb;
		$table = self::user_achievements_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY unlocked_at DESC, updated_at DESC",
				absint( $user_id )
			)
		);
	}

	public static function upsert_user_achievement( $user_id, $achievement_id, $achievement_slug, $progress_value, $unlocked = false ) {
		global $wpdb;
		$table  = self::user_achievements_table();
		$user   = absint( $user_id );
		$slug   = sanitize_title( (string) $achievement_slug );
		$now    = current_time( 'mysql', true );
		$exists = self::get_user_achievement_unlock( $user, $slug );

		$row = array(
			'user_id'          => $user,
			'achievement_id'   => absint( $achievement_id ),
			'achievement_slug' => $slug,
			'progress_value'   => (int) $progress_value,
			'updated_at'       => $now,
		);

		if ( $unlocked ) {
			$row['unlocked_at'] = $now;
		}

		if ( $exists ) {
			if ( ! empty( $exists->unlocked_at ) ) {
				unset( $row['unlocked_at'] );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false !== $wpdb->update( $table, $row, array( 'id' => (int) $exists->id ) );
		}

		$row['created_at'] = $now;
		if ( $unlocked ) {
			$row['unlocked_at'] = $now;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->insert( $table, $row );
	}

	public static function get_diary_entry( $user_id, $local_id ) {
		global $wpdb;
		$table = self::diary_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND local_id = %s AND deleted_at IS NULL LIMIT 1",
				absint( $user_id ),
				mbmc_sanitize_short_text( (string) $local_id, 64 )
			)
		);
	}

	public static function get_diary_entry_by_id( $user_id, $entry_id ) {
		global $wpdb;
		$table = self::diary_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND id = %d AND deleted_at IS NULL LIMIT 1",
				absint( $user_id ),
				absint( $entry_id )
			)
		);
	}

	public static function list_diary_entries( $user_id, $since = null, $limit = 100 ) {
		global $wpdb;
		$table = self::diary_table();
		$user  = absint( $user_id );
		$limit = max( 1, min( 200, (int) $limit ) );

		if ( $since ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND deleted_at IS NULL AND updated_at >= %s ORDER BY updated_at DESC LIMIT %d",
					$user,
					self::normalize_mysql_datetime( $since ),
					$limit
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT %d",
				$user,
				$limit
			)
		);
	}

	public static function upsert_diary_entry( $user_id, $local_id, $entry_type, $payload, $visibility = 'private', $client_updated_at = null ) {
		global $wpdb;
		$table    = self::diary_table();
		$user     = absint( $user_id );
		$local_id = mbmc_sanitize_short_text( (string) $local_id, 64 );
		$now      = current_time( 'mysql', true );
		$existing = self::get_diary_entry( $user, $local_id );

		if ( $existing && $client_updated_at ) {
			$client_ts = strtotime( (string) $client_updated_at );
			$server_ts = strtotime( (string) $existing->client_updated_at );
			if ( false !== $client_ts && false !== $server_ts && $client_ts < $server_ts ) {
				return (int) $existing->id;
			}
		}

		$row = array(
			'user_id'           => $user,
			'local_id'          => $local_id,
			'entry_type'        => sanitize_key( (string) $entry_type ),
			'payload'           => wp_json_encode( $payload ),
			'visibility'        => sanitize_key( (string) $visibility ),
			'client_updated_at' => $client_updated_at ? self::normalize_mysql_datetime( $client_updated_at ) : $now,
			'updated_at'        => $now,
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update( $table, $row, array( 'id' => (int) $existing->id ) );
			return false !== $ok ? (int) $existing->id : false;
		}

		$row['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $table, $row );
		return false !== $ok ? (int) $wpdb->insert_id : false;
	}

	public static function soft_delete_diary_entry( $user_id, $entry_id ) {
		global $wpdb;
		$table = self::diary_table();
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$table,
			array( 'deleted_at' => $now, 'updated_at' => $now ),
			array( 'id' => absint( $entry_id ), 'user_id' => absint( $user_id ) ),
			array( '%s', '%s' ),
			array( '%d', '%d' )
		);
	}

	public static function list_team_posts( $limit = 50, $published_only = true ) {
		global $wpdb;
		$table = self::team_posts_table();
		$limit = max( 1, min( 100, (int) $limit ) );
		$sql   = $published_only
			? $wpdb->prepare( "SELECT * FROM {$table} WHERE is_published = 1 ORDER BY created_at DESC LIMIT %d", $limit )
			: $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $sql );
	}

	public static function insert_team_post( $data ) {
		global $wpdb;
		$table = self::team_posts_table();
		$now   = current_time( 'mysql', true );
		$row   = array(
			'author_user_id' => ! empty( $data['author_user_id'] ) ? absint( $data['author_user_id'] ) : null,
			'title'          => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'body'           => wp_kses_post( (string) ( $data['body'] ?? '' ) ),
			'post_type'      => sanitize_key( (string) ( $data['post_type'] ?? 'announcement' ) ),
			'audience_roles' => isset( $data['audience_roles'] ) ? wp_json_encode( $data['audience_roles'] ) : null,
			'is_published'   => isset( $data['is_published'] ) ? (int) (bool) $data['is_published'] : 1,
			'created_at'     => $now,
			'updated_at'     => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $table, $row );
		return false !== $ok ? (int) $wpdb->insert_id : false;
	}

	public static function get_loyalty_idempotency( $user_id, $action_type, $idempotency_key ) {
		global $wpdb;
		$table = self::loyalty_idempotency_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND action_type = %s AND idempotency_key = %s LIMIT 1",
				absint( $user_id ),
				sanitize_key( (string) $action_type ),
				sanitize_text_field( (string) $idempotency_key )
			)
		);
	}

	public static function store_loyalty_idempotency( $user_id, $action_type, $idempotency_key, $result_payload ) {
		global $wpdb;
		$table = self::loyalty_idempotency_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->insert(
			$table,
			array(
				'user_id'         => absint( $user_id ),
				'action_type'     => sanitize_key( (string) $action_type ),
				'idempotency_key' => sanitize_text_field( (string) $idempotency_key ),
				'result_payload'  => wp_json_encode( $result_payload ),
				'created_at'      => current_time( 'mysql', true ),
			)
		);
	}

	public static function decrement_loyalty_reward_stock( $reward_id ) {
		global $wpdb;
		$table  = self::loyalty_rewards_table();
		$reward = self::get_loyalty_reward( $reward_id );
		if ( ! $reward || null === $reward->stock_qty ) {
			return true;
		}
		if ( (int) $reward->stock_qty <= 0 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$table,
			array(
				'stock_qty'  => max( 0, (int) $reward->stock_qty - 1 ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $reward_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function insert_ai_log( $endpoint, $user_id, $request_summary, $response_status = 'ok' ) {
		global $wpdb;
		$table = self::ai_logs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->insert(
			$table,
			array(
				'user_id'          => $user_id > 0 ? absint( $user_id ) : null,
				'endpoint'         => sanitize_key( (string) $endpoint ),
				'request_summary'  => mbmc_sanitize_short_text( (string) $request_summary, 255 ),
				'response_status'  => sanitize_key( (string) $response_status ),
				'created_at'       => current_time( 'mysql', true ),
			)
		);
	}

	public static function list_ai_logs( $limit = 50 ) {
		global $wpdb;
		$table = self::ai_logs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				max( 1, min( 200, (int) $limit ) )
			)
		);
	}

	public static function count_user_orders( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		return (int) wc_get_customer_order_count( absint( $user_id ) );
	}

	public static function count_user_approved_catches( $user_id ) {
		global $wpdb;
		$table = self::community_catches_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = %s",
				absint( $user_id ),
				'published'
			)
		);
	}
}
