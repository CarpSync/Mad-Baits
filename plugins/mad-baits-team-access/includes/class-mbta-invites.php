<?php
/**
 * Invite codes CRUD.
 *
 * @package MadBaitsTeamAccess
 */

defined('ABSPATH') || exit;

class MBTA_Invites {
	/**
	 * @return void
	 */
	public static function init() {
		// Reserved for future cron expiry cleanup.
	}

	/**
	 * @param string $code Invite code.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_code($code) {
		global $wpdb;
		$code = self::normalize_code($code);
		if ('' === $code) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE code = %s LIMIT 1', $code),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}

	/**
	 * @param int $id Invite ID.
	 * @return array<string, mixed>|null
	 */
	public static function get($id) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1', absint($id)),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all() {
		global $wpdb;
		$rows = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC', ARRAY_A);
		return is_array($rows) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return int|false
	 */
	public static function create($data) {
		global $wpdb;
		$role = sanitize_key((string) ($data['role'] ?? ''));
		if (! isset(MBTA_Roles::get_role_labels()[ $role ])) {
			return false;
		}

		$raw_code = isset($data['code']) ? trim((string) $data['code']) : '';
		$code     = '' !== $raw_code ? self::normalize_code($raw_code) : self::generate_code();
		if ('' === $code || self::get_by_code($code)) {
			return false;
		}

		self::ensure_table_schema();

		$now = current_time('mysql', true);
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'code'         => $code,
				'role'         => $role,
				'expires_at'   => self::sanitize_datetime($data['expires_at'] ?? ''),
				'usage_limit'  => isset($data['usage_limit']) && '' !== (string) $data['usage_limit'] ? absint($data['usage_limit']) : null,
				'used_count'   => 0,
				'status'       => sanitize_key((string) ($data['status'] ?? 'active')),
				'notes'        => sanitize_textarea_field((string) ($data['notes'] ?? '')),
				'invite_email' => self::sanitize_email($data['invite_email'] ?? ''),
				'created_by'   => get_current_user_id() ?: null,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param int                  $id   ID.
	 * @param array<string, mixed> $data Data.
	 * @return bool
	 */
	public static function update($id, $data) {
		global $wpdb;
		$fields = array('updated_at' => current_time('mysql', true));
		$format = array('%s');

		foreach (array('role', 'status', 'notes') as $key) {
			if (array_key_exists($key, $data)) {
				$fields[ $key ] = 'notes' === $key
					? sanitize_textarea_field((string) $data[ $key ])
					: sanitize_key((string) $data[ $key ]);
				$format[] = '%s';
			}
		}
		if (array_key_exists('invite_email', $data)) {
			$fields['invite_email'] = self::sanitize_email($data['invite_email']);
			$format[] = '%s';
		}
		if (array_key_exists('expires_at', $data)) {
			$fields['expires_at'] = self::sanitize_datetime($data['expires_at']);
			$format[] = '%s';
		}
		if (array_key_exists('usage_limit', $data)) {
			$fields['usage_limit'] = '' === (string) $data['usage_limit'] ? null : absint($data['usage_limit']);
			$format[] = '%d';
		}

		return false !== $wpdb->update(self::table(), $fields, array('id' => absint($id)), $format, array('%d'));
	}

	/**
	 * @param int $id Invite ID.
	 * @return bool
	 */
	public static function increment_usage($id) {
		global $wpdb;
		$id = absint($id);
		return false !== $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET used_count = used_count + 1, updated_at = %s WHERE id = %d',
				current_time('mysql', true),
				$id
			)
		);
	}

	/**
	 * @param array<string, mixed> $invite Invite row.
	 * @return string
	 */
	public static function validate_invite($invite) {
		if (! is_array($invite) || empty($invite['code'])) {
			return __('Invalid invite code.', 'mad-baits-team-access');
		}
		if ('active' !== sanitize_key((string) ($invite['status'] ?? ''))) {
			return __('This invite is no longer active.', 'mad-baits-team-access');
		}
		if (! empty($invite['expires_at'])) {
			$expires = strtotime((string) $invite['expires_at'] . ' UTC');
			if ($expires && time() > $expires) {
				return __('This invite has expired.', 'mad-baits-team-access');
			}
		}
		$limit = isset($invite['usage_limit']) ? absint($invite['usage_limit']) : 0;
		$used  = isset($invite['used_count']) ? absint($invite['used_count']) : 0;
		if ($limit > 0 && $used >= $limit) {
			return __('This invite has reached its usage limit.', 'mad-baits-team-access');
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $invite Invite.
	 * @return string
	 */
	public static function get_invite_url($invite) {
		$code = is_array($invite) ? (string) ($invite['code'] ?? '') : '';
		return add_query_arg('code', rawurlencode($code), home_url('/team-invite/'));
	}

	/**
	 * @return string
	 */
	public static function generate_code() {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$code = '';
			for ($i = 0; $i < 12; $i++) {
				$code .= $alphabet[ random_int(0, strlen($alphabet) - 1) ];
			}
		} while (self::get_by_code($code));
		return $code;
	}

	/**
	 * @param string $code Code.
	 * @return string
	 */
	public static function normalize_code($code) {
		return strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $code));
	}

	/**
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	/**
	 * @param mixed $value Email.
	 * @return string|null
	 */
	public static function sanitize_email($value) {
		$email = sanitize_email((string) $value);
		return is_email($email) ? $email : null;
	}

	/**
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function sanitize_datetime($value) {
		$value = trim((string) $value);
		if ('' === $value) {
			return null;
		}
		$ts = strtotime($value);
		return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
	}

	/**
	 * @return string
	 */
	private static function table() {
		return MBTA_DB::invites_table();
	}

	/**
	 * Ensure invites table exists and has invite_email column (older installs).
	 *
	 * @return void
	 */
	public static function ensure_table_schema() {
		if (class_exists('MBTA_DB')) {
			MBTA_DB::create_tables();
		}
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return string Error code or empty string.
	 */
	public static function get_create_error_code($data) {
		$role = sanitize_key((string) ($data['role'] ?? ''));
		if (! isset(MBTA_Roles::get_role_labels()[ $role ])) {
			return 'invalid_role';
		}

		$raw_code = isset($data['code']) ? trim((string) $data['code']) : '';
		if ('' !== $raw_code) {
			$code = self::normalize_code($raw_code);
			if ('' === $code) {
				return 'invalid_code';
			}
			if (self::get_by_code($code)) {
				return 'duplicate_code';
			}
		}

		return '';
	}
}
