<?php
/**
 * Server-side achievement evaluation and unlocks.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Achievements
 */
class MBMC_Achievements {

	/**
	 * Evaluate all active achievements for a user after an event.
	 *
	 * @param int    $user_id User id.
	 * @param string $event   Event slug.
	 * @param array  $context Optional context.
	 * @return array<int, array<string, mixed>> Newly unlocked achievements.
	 */
	public static function evaluate_for_user( $user_id, $event, $context = array() ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$definitions = MBMC_DB::list_loyalty_achievements( true );
		$new_unlocks = array();

		foreach ( $definitions as $def ) {
			$slug     = (string) $def->slug;
			$existing = MBMC_DB::get_user_achievement_unlock( $user_id, $slug );
			if ( $existing && ! empty( $existing->unlocked_at ) ) {
				continue;
			}

			$progress = self::compute_progress( $user_id, $def, $event, $context );
			$target   = max( 1, (int) $def->criteria_value );
			$unlocked = $progress >= $target;

			MBMC_DB::upsert_user_achievement( $user_id, (int) $def->id, $slug, $progress, $unlocked );

			if ( ! $unlocked ) {
				continue;
			}

			$bonus = (int) $def->bonus_points;
			if ( $bonus > 0 ) {
				MBMC_Loyalty::award_points(
					$user_id,
					$bonus,
					'achievement',
					$slug,
					sprintf(
						/* translators: %s achievement title */
						__( 'Achievement unlocked · %s', 'mad-baits-mobile-connector' ),
						(string) $def->title
					)
				);
			}

			$payload = array(
				'slug'       => $slug,
				'title'      => (string) $def->title,
				'bonusPoints'=> $bonus,
				'unlockedAt' => mbmc_format_datetime_for_api( current_time( 'mysql', true ) ),
			);
			$new_unlocks[] = $payload;

			if ( class_exists( 'MBMC_Notification_Service' ) ) {
				MBMC_Notification_Service::dispatch_loyalty_notification(
					$user_id,
					'achievement_unlocked',
					__( 'Achievement unlocked!', 'mad-baits-mobile-connector' ),
					(string) $def->title,
					array( 'achievementSlug' => $slug )
				);
			}
		}

		return $new_unlocks;
	}

	/**
	 * Serialize achievement definition + user state for API.
	 *
	 * @param object      $def    Achievement row.
	 * @param object|null $unlock User unlock row.
	 * @return array<string, mixed>
	 */
	public static function serialize_for_api( $def, $unlock = null ) {
		$base = MBMC_Loyalty::serialize_achievement( $def );
		$progress = $unlock ? (int) $unlock->progress_value : 0;
		return array_merge(
			$base,
			array(
				'progress'   => $progress,
				'target'     => (int) $def->criteria_value,
				'unlocked'   => $unlock && ! empty( $unlock->unlocked_at ),
				'unlockedAt' => ( $unlock && ! empty( $unlock->unlocked_at ) )
					? mbmc_format_datetime_for_api( $unlock->unlocked_at )
					: null,
			)
		);
	}

	/**
	 * @param int    $user_id User id.
	 * @param object $def     Achievement definition.
	 * @param string $event   Trigger event.
	 * @param array  $context Context.
	 * @return int
	 */
	private static function compute_progress( $user_id, $def, $event, $context ) {
		$type = sanitize_key( (string) $def->criteria_type );

		switch ( $type ) {
			case 'approved_catch_reports':
				return MBMC_DB::count_user_approved_catches( $user_id );
			case 'app_orders':
				return MBMC_DB::count_user_orders( $user_id );
			case 'max_weight_lb':
				return isset( $context['maxWeightLb'] ) ? (int) floor( (float) $context['maxWeightLb'] ) : 0;
			case 'app_streak_days':
				return isset( $context['appStreakDays'] ) ? (int) $context['appStreakDays'] : 0;
			case 'bait_used':
				return isset( $context['baitUsedCount'] ) ? (int) $context['baitUsedCount'] : 0;
			case 'manual':
				return 0;
			default:
				return 0;
		}
	}
}
