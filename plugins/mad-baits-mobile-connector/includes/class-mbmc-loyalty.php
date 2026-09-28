<?php
/**
 * Loyalty points ledger — earn, expire, redeem with duplicate prevention.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Loyalty
 */
class MBMC_Loyalty {

	/**
	 * Award points if not already awarded for source + source_id.
	 *
	 * @param int    $user_id   WordPress user id.
	 * @param int    $points    Points (positive earn, negative redeem).
	 * @param string $source    Source key.
	 * @param string $source_id Unique reference within source.
	 * @param string $title     Display title.
	 * @param string $status    pending|approved|rejected|expired|redeemed.
	 * @param string $notes     Optional notes.
	 * @param bool   $allow_duplicate Allow duplicate source awards.
	 * @return int|false Transaction id or false on duplicate / failure.
	 */
	public static function award_points( $user_id, $points, $source, $source_id, $title, $status = 'approved', $notes = '', $allow_duplicate = false ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || 0 === (int) $points ) {
			return false;
		}

		if ( ! $allow_duplicate && self::has_award( $user_id, $source, $source_id ) ) {
			return false;
		}

		$now      = current_time( 'mysql', true );
		$expires  = null;
		$type     = $points > 0 ? 'earn' : 'redeem';
		$redeemed = null;
		$months   = class_exists( 'MBMC_Loyalty_Config' ) ? MBMC_Loyalty_Config::get_int( 'expiry_months' ) : 12;

		if ( 'earn' === $type && 'approved' === $status ) {
			$expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' +' . max( 1, $months ) . ' months' ) );
		}
		if ( 'redeem' === $type ) {
			$redeemed = $now;
		}

		return MBMC_DB::insert_loyalty_transaction(
			array(
				'user_id'     => $user_id,
				'type'        => $type,
				'points'      => (int) $points,
				'source'      => sanitize_key( $source ),
				'source_id'   => sanitize_text_field( (string) $source_id ),
				'status'      => sanitize_key( $status ),
				'title'       => sanitize_text_field( $title ),
				'notes'       => sanitize_textarea_field( (string) $notes ),
				'created_at'  => $now,
				'expires_at'  => $expires,
				'redeemed_at' => $redeemed,
			)
		);
	}

	/**
	 * Manual admin adjustment (always allows duplicate via unique source_id timestamp).
	 *
	 * @param int    $user_id User id.
	 * @param int    $points  Points (+ earn, - deduct).
	 * @param string $notes   Admin notes.
	 * @return int|false
	 */
	public static function admin_adjust_points( $user_id, $points, $notes = '' ) {
		$title = $points > 0
			? __( 'Admin bonus points', 'mad-baits-mobile-connector' )
			: __( 'Admin points deduction', 'mad-baits-mobile-connector' );

		return self::award_points(
			$user_id,
			$points,
			'admin',
			'admin_' . time() . '_' . wp_rand( 1000, 9999 ),
			$title,
			'approved',
			$notes,
			true
		);
	}

	/**
	 * Reset user balance by expiring active earns and recording adjustment.
	 *
	 * @param int    $user_id User id.
	 * @param string $notes   Reason.
	 * @return void
	 */
	public static function admin_reset_user_points( $user_id, $notes = '' ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}
		MBMC_DB::expire_loyalty_points( $user_id );
		global $wpdb;
		$table = MBMC_DB::loyalty_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'expired' WHERE user_id = %d AND type = 'earn' AND status = 'approved'",
				$user_id
			)
		);
		$balance = MBMC_DB::get_loyalty_balance( $user_id );
		if ( 0 === $balance ) {
			return;
		}
		self::admin_adjust_points(
			$user_id,
			-$balance,
			$notes
		);
	}

	/**
	 * Whether user already has a non-rejected award for source + source_id.
	 *
	 * @param int    $user_id   User id.
	 * @param string $source    Source.
	 * @param string $source_id Source id.
	 * @return bool
	 */
	public static function has_award( $user_id, $source, $source_id ) {
		return MBMC_DB::loyalty_source_exists( absint( $user_id ), sanitize_key( $source ), sanitize_text_field( (string) $source_id ) );
	}

	public static function expire_stale_points( $user_id = 0 ) {
		return MBMC_DB::expire_loyalty_points( absint( $user_id ) );
	}

	public static function get_available_balance( $user_id ) {
		self::expire_stale_points( $user_id );
		return MBMC_DB::get_loyalty_balance( absint( $user_id ) );
	}

	/**
	 * Award catch report points on moderation approve.
	 *
	 * @param int  $user_id            User id.
	 * @param int  $community_catch_id Catch id.
	 * @param bool $featured           Featured bonus.
	 * @return void
	 */
	public static function award_catch_report( $user_id, $community_catch_id, $featured = false ) {
		$base     = class_exists( 'MBMC_Loyalty_Config' ) ? MBMC_Loyalty_Config::get_int( 'catch_report_points' ) : 10;
		$featured_pts = class_exists( 'MBMC_Loyalty_Config' ) ? MBMC_Loyalty_Config::get_int( 'featured_catch_points' ) : 25;

		self::award_points(
			$user_id,
			$base,
			'catch_report',
			(string) $community_catch_id,
			__( 'Catch report approved', 'mad-baits-mobile-connector' )
		);

		if ( $featured ) {
			self::award_points(
				$user_id,
				$featured_pts,
				'catch_report_featured',
				(string) $community_catch_id,
				__( 'Featured catch report bonus', 'mad-baits-mobile-connector' )
			);
		}
	}

	/**
	 * Revoke catch report points when unpublished/rejected.
	 *
	 * @param int $user_id            User id.
	 * @param int $community_catch_id Catch id.
	 * @return void
	 */
	public static function revoke_catch_report_points( $user_id, $community_catch_id ) {
		MBMC_DB::reject_loyalty_source_transactions( $user_id, 'catch_report', (string) $community_catch_id );
		MBMC_DB::reject_loyalty_source_transactions( $user_id, 'catch_report_featured', (string) $community_catch_id );
	}

	public static function award_order_points( $user_id, $order_id, $eligible_gbp ) {
		$rate   = class_exists( 'MBMC_Loyalty_Config' ) ? MBMC_Loyalty_Config::get_int( 'purchase_per_gbp' ) : 1;
		$points = (int) floor( max( 0, (float) $eligible_gbp ) * max( 0, $rate ) );
		if ( $points <= 0 ) {
			return false;
		}

		return self::award_points(
			$user_id,
			$points,
			'purchase',
			(string) $order_id,
			sprintf(
				/* translators: %s order total */
				__( 'Eligible order · £%s spent', 'mad-baits-mobile-connector' ),
				number_format( (float) $eligible_gbp, 2 )
			)
		);
	}

	public static function award_referral( $referrer_user_id, $referred_user_id, $order_id, $override_points = null ) {
		$points = null !== $override_points
			? (int) $override_points
			: ( class_exists( 'MBMC_Loyalty_Config' ) ? MBMC_Loyalty_Config::get_int( 'referral_points' ) : 100 );

		return self::award_points(
			$referrer_user_id,
			$points,
			'referral',
			$referred_user_id . ':' . $order_id,
			__( 'Successful referral', 'mad-baits-mobile-connector' )
		);
	}

	/**
	 * Redeem a catalog reward — deduct points server-side.
	 *
	 * @param int    $user_id          User id.
	 * @param int    $reward_id        Reward id.
	 * @param string $idempotency_key  Idempotency key.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function redeem_reward( $user_id, $reward_id, $idempotency_key = '' ) {
		$user_id   = absint( $user_id );
		$reward_id = absint( $reward_id );

		if ( $user_id <= 0 || $reward_id <= 0 ) {
			return new WP_Error( 'mbmc_invalid_reward', __( 'Invalid reward.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		if ( '' !== $idempotency_key ) {
			$cached = MBMC_DB::get_loyalty_idempotency( $user_id, 'redeem', $idempotency_key );
			if ( $cached && ! empty( $cached->result_payload ) ) {
				$decoded = json_decode( (string) $cached->result_payload, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		$reward = MBMC_DB::get_loyalty_reward( $reward_id );
		if ( ! $reward || ! (int) $reward->is_active ) {
			return new WP_Error( 'mbmc_reward_unavailable', __( 'This reward is not available.', 'mad-baits-mobile-connector' ), array( 'status' => 404 ) );
		}

		if ( null !== $reward->stock_qty && (int) $reward->stock_qty <= 0 ) {
			return new WP_Error( 'mbmc_reward_out_of_stock', __( 'This reward is out of stock.', 'mad-baits-mobile-connector' ), array( 'status' => 409 ) );
		}

		$cost    = (int) $reward->points_cost;
		$balance = self::get_available_balance( $user_id );
		if ( $balance < $cost ) {
			return new WP_Error( 'mbmc_insufficient_points', __( 'Not enough points to redeem this reward.', 'mad-baits-mobile-connector' ), array( 'status' => 402 ) );
		}

		$source_id = 'reward_' . $reward_id . '_' . time();
		$tx_id = self::award_points(
			$user_id,
			-$cost,
			'reward_redemption',
			$source_id,
			sprintf(
				/* translators: %s reward title */
				__( 'Redeemed · %s', 'mad-baits-mobile-connector' ),
				(string) $reward->title
			),
			'redeemed',
			'',
			true
		);

		if ( ! $tx_id ) {
			return new WP_Error( 'mbmc_redeem_failed', __( 'Could not redeem this reward.', 'mad-baits-mobile-connector' ), array( 'status' => 500 ) );
		}

		MBMC_DB::decrement_loyalty_reward_stock( $reward_id );

		$result = array(
			'ok'             => true,
			'rewardId'       => (string) $reward_id,
			'rewardSlug'     => (string) $reward->slug,
			'pointsDeducted' => $cost,
			'transactionId'  => (string) $tx_id,
			'availablePoints'=> self::get_available_balance( $user_id ),
		);

		if ( '' !== $idempotency_key ) {
			MBMC_DB::store_loyalty_idempotency( $user_id, 'redeem', $idempotency_key, $result );
		}

		if ( class_exists( 'MBMC_Notification_Service' ) ) {
			MBMC_Notification_Service::dispatch_loyalty_notification(
				$user_id,
				'reward_redeemed',
				__( 'Reward redeemed!', 'mad-baits-mobile-connector' ),
				(string) $reward->title,
				array( 'rewardId' => (string) $reward_id )
			);
		}

		return $result;
	}

	/**
	 * Run a bonus points campaign.
	 *
	 * @param object $campaign Campaign row.
	 * @return int Users awarded.
	 */
	public static function run_campaign( $campaign ) {
		$count   = 0;
		$user_ids = array();

		if ( ! empty( $campaign->user_ids ) ) {
			$decoded = json_decode( (string) $campaign->user_ids, true );
			if ( is_array( $decoded ) ) {
				$user_ids = array_map( 'absint', $decoded );
			}
		}

		if ( 'bonus_all' === $campaign->campaign_type ) {
			$users = get_users( array( 'fields' => array( 'ID' ) ) );
			foreach ( $users as $user ) {
				$user_ids[] = (int) $user->ID;
			}
		}

		$user_ids = array_values( array_unique( array_filter( $user_ids ) ) );
		$points   = (int) $campaign->points_amount;
		$mult     = max( 1, (float) $campaign->multiplier );

		foreach ( $user_ids as $uid ) {
			$award = 'double_points' === $campaign->campaign_type
				? (int) round( $points * $mult )
				: $points;
			if ( $award <= 0 ) {
				continue;
			}
			$ok = self::award_points(
				$uid,
				$award,
				'admin',
				'campaign_' . (int) $campaign->id . '_' . $uid,
				(string) $campaign->title,
				'approved',
				(string) $campaign->message,
				true
			);
			if ( $ok ) {
				++$count;
			}
		}

		return $count;
	}

	public static function serialize_transaction( $row ) {
		return array(
			'id'         => (string) $row->id,
			'userId'     => (string) $row->user_id,
			'type'       => (string) $row->type,
			'points'     => (int) $row->points,
			'source'     => (string) $row->source,
			'sourceId'   => ! empty( $row->source_id ) ? (string) $row->source_id : null,
			'status'     => (string) $row->status,
			'title'      => (string) $row->title,
			'createdAt'  => mbmc_format_datetime_for_api( $row->created_at ),
			'expiresAt'  => ! empty( $row->expires_at ) ? mbmc_format_datetime_for_api( $row->expires_at ) : null,
			'redeemedAt' => ! empty( $row->redeemed_at ) ? mbmc_format_datetime_for_api( $row->redeemed_at ) : null,
			'notes'      => ! empty( $row->notes ) ? (string) $row->notes : null,
		);
	}

	public static function serialize_reward( $row ) {
		return array(
			'id'          => (string) $row->id,
			'slug'        => (string) $row->slug,
			'title'       => (string) $row->title,
			'description' => (string) $row->description,
			'imageUrl'    => ! empty( $row->image_url ) ? (string) $row->image_url : null,
			'pointsCost'  => (int) $row->points_cost,
			'stockQty'    => isset( $row->stock_qty ) ? (int) $row->stock_qty : null,
			'category'    => (string) $row->category,
			'tierMinimum' => ! empty( $row->tier_minimum ) ? (string) $row->tier_minimum : null,
			'appOnly'     => true,
		);
	}

	public static function serialize_achievement( $row ) {
		return array(
			'id'            => (string) $row->id,
			'slug'          => (string) $row->slug,
			'title'         => (string) $row->title,
			'description'   => (string) $row->description,
			'category'      => (string) $row->category,
			'icon'          => (string) $row->icon,
			'iconUrl'       => ! empty( $row->icon_url ) ? (string) $row->icon_url : null,
			'criteriaType'  => (string) $row->criteria_type,
			'criteriaValue' => (int) $row->criteria_value,
			'bonusPoints'   => (int) $row->bonus_points,
		);
	}
}
