<?php
/**
 * Database-driven loyalty rules and copy — no hardcoded point values in business logic.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Loyalty_Config
 */
class MBMC_Loyalty_Config {

	/**
	 * Default rule values (seeded once; editable in admin).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_rules() {
		return array(
			'purchase_per_gbp'           => 1,
			'catch_report_points'        => 10,
			'featured_catch_points'      => 25,
			'review_points'              => 10,
			'referral_points'            => 100,
			'referral_min_order_gbp'     => 25,
			'expiry_months'              => 12,
			'min_redemption_gbp'         => 25,
			'max_redemption_percent'     => 20,
			'checkout_points_per_gbp'    => 100,
			'eligible_categories'        => wp_json_encode( array( 'boilies', 'hookbaits', 'liquids', 'clothing' ) ),
			'excluded_categories'        => wp_json_encode( array( 'sale', 'bundle', 'discounted', 'tackle', 'gift-card', 'shipping' ) ),
			'exclude_sale'                 => 1,
			'exclude_bundles'              => 1,
			'exclude_deals'                => 1,
			'exclude_tackle'               => 1,
			'cannot_combine_coupons'       => 1,
			'customer_intro'               => 'Earn points on bait orders, catch reports and achievements. Points last 12 months and can be redeemed against eligible Mad Baits rewards.',
			'restrictions_summary'         => 'Points cannot be used on sale items, bundle deals, tackle, delivery, gift cards or combined with discount codes.',
			'validity_note'                => 'Points are valid for 12 months from the date earned.',
			'no_cash_value_note'           => 'Points have no cash value and are non-transferable.',
		);
	}

	/**
	 * Get a single config value.
	 *
	 * @param string $key     Config key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$rules = self::get_all_rules();
		if ( array_key_exists( $key, $rules ) ) {
			return $rules[ $key ];
		}
		$defaults = self::default_rules();
		return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;
	}

	/**
	 * Get int rule.
	 *
	 * @param string $key Config key.
	 * @return int
	 */
	public static function get_int( $key ) {
		return (int) self::get( $key, 0 );
	}

	/**
	 * Get float rule.
	 *
	 * @param string $key Config key.
	 * @return float
	 */
	public static function get_float( $key ) {
		return (float) self::get( $key, 0 );
	}

	/**
	 * Get bool rule.
	 *
	 * @param string $key Config key.
	 * @return bool
	 */
	public static function get_bool( $key ) {
		return (bool) self::get( $key, false );
	}

	/**
	 * All rules merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all_rules() {
		$stored = MBMC_DB::get_loyalty_config_map();
		return array_merge( self::default_rules(), $stored );
	}

	/**
	 * Save multiple rules from admin form.
	 *
	 * @param array<string, mixed> $values Values.
	 * @return void
	 */
	public static function save_rules( $values ) {
		$allowed = array_keys( self::default_rules() );
		foreach ( $values as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}
			MBMC_DB::set_loyalty_config( $key, (string) $value );
		}
	}

	/**
	 * Rules payload for mobile app API.
	 *
	 * @return array<string, mixed>
	 */
	public static function rules_for_api() {
		$rules = self::get_all_rules();
		return array(
			'purchasePerGbp'          => self::get_int( 'purchase_per_gbp' ),
			'catchReportPoints'       => self::get_int( 'catch_report_points' ),
			'featuredCatchPoints'     => self::get_int( 'featured_catch_points' ),
			'reviewPoints'            => self::get_int( 'review_points' ),
			'referralPoints'          => self::get_int( 'referral_points' ),
			'referralMinOrderGbp'     => self::get_float( 'referral_min_order_gbp' ),
			'expiryMonths'            => self::get_int( 'expiry_months' ),
			'minRedemptionGbp'        => self::get_float( 'min_redemption_gbp' ),
			'maxRedemptionPercent'    => self::get_int( 'max_redemption_percent' ),
			'checkoutPointsPerGbp'    => self::get_int( 'checkout_points_per_gbp' ),
			'eligibleCategories'      => json_decode( (string) $rules['eligible_categories'], true ) ?: array(),
			'excludedCategories'      => json_decode( (string) $rules['excluded_categories'], true ) ?: array(),
			'excludeSale'             => self::get_bool( 'exclude_sale' ),
			'excludeBundles'          => self::get_bool( 'exclude_bundles' ),
			'excludeDeals'            => self::get_bool( 'exclude_deals' ),
			'excludeTackle'           => self::get_bool( 'exclude_tackle' ),
			'cannotCombineCoupons'    => self::get_bool( 'cannot_combine_coupons' ),
			'customerIntro'           => (string) $rules['customer_intro'],
			'restrictionsSummary'     => (string) $rules['restrictions_summary'],
			'validityNote'            => (string) $rules['validity_note'],
			'noCashValueNote'         => (string) $rules['no_cash_value_note'],
		);
	}

	/**
	 * Seed default rules, rewards and achievements on install.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		foreach ( self::default_rules() as $key => $value ) {
			if ( ! MBMC_DB::loyalty_config_exists( $key ) ) {
				MBMC_DB::set_loyalty_config( $key, (string) $value );
			}
		}

		if ( 0 === MBMC_DB::count_loyalty_rewards() ) {
			$rewards = array(
				array( 'slug' => 'reward_sticker_pack', 'title' => 'Sticker pack', 'description' => 'Mad Baits vinyl sticker set.', 'points_cost' => 250, 'category' => 'merch', 'sort_order' => 10 ),
				array( 'slug' => 'reward_hookbait_pot', 'title' => 'Free pot of hookbaits', 'description' => 'Redeem for a free pot from the hookbait range.', 'points_cost' => 500, 'category' => 'hookbaits', 'sort_order' => 20 ),
				array( 'slug' => 'reward_bucket', 'title' => 'Free bucket', 'description' => 'Mad Baits bucket for mixing bait.', 'points_cost' => 1000, 'category' => 'merch', 'sort_order' => 30 ),
				array( 'slug' => 'reward_delivery_voucher', 'title' => 'Free delivery voucher', 'description' => 'One free UK delivery on eligible orders over £25.', 'points_cost' => 1500, 'category' => 'voucher', 'sort_order' => 40 ),
				array( 'slug' => 'reward_hoodie', 'title' => 'Mad Baits hoodie', 'description' => 'Classic Mad Baits hoodie reward.', 'points_cost' => 2500, 'category' => 'clothing', 'sort_order' => 50 ),
				array( 'slug' => 'reward_bait_10kg', 'title' => '10kg bait package', 'description' => '10kg eligible boilie package.', 'points_cost' => 5000, 'category' => 'boilies', 'sort_order' => 60 ),
			);
			foreach ( $rewards as $reward ) {
				MBMC_DB::insert_loyalty_reward( $reward );
			}
		}

		if ( 0 === MBMC_DB::count_loyalty_achievements() ) {
			$achievements = array(
				array( 'slug' => 'first_catch_report', 'title' => 'First Catch Report', 'description' => 'Submit your first approved catch report.', 'category' => 'community', 'icon' => 'document-text-outline', 'criteria_type' => 'approved_catch_reports', 'criteria_value' => 1, 'bonus_points' => 10, 'sort_order' => 10 ),
				array( 'slug' => 'first_20lb_carp', 'title' => 'First 20lb Carp', 'description' => 'Log a carp weighing 20lb or more.', 'category' => 'pbs', 'icon' => 'fish-outline', 'criteria_type' => 'max_weight_lb', 'criteria_value' => 20, 'bonus_points' => 10, 'sort_order' => 20 ),
				array( 'slug' => 'first_30lb_carp', 'title' => 'First 30lb Carp', 'description' => 'Log a carp weighing 30lb or more.', 'category' => 'pbs', 'icon' => 'fish', 'criteria_type' => 'max_weight_lb', 'criteria_value' => 30, 'bonus_points' => 20, 'sort_order' => 30 ),
				array( 'slug' => 'first_50lb_carp', 'title' => 'First 50lb Carp', 'description' => 'Log a carp weighing 50lb or more.', 'category' => 'pbs', 'icon' => 'trophy', 'criteria_type' => 'max_weight_lb', 'criteria_value' => 50, 'bonus_points' => 50, 'sort_order' => 40 ),
				array( 'slug' => 'first_app_order', 'title' => 'First App Order', 'description' => 'Place your first order through the app.', 'category' => 'shop', 'icon' => 'bag-check-outline', 'criteria_type' => 'app_orders', 'criteria_value' => 1, 'bonus_points' => 25, 'sort_order' => 50 ),
				array( 'slug' => 'app_streak_7', 'title' => '7-Day App Streak', 'description' => 'Open the app seven days in a row.', 'category' => 'loyalty', 'icon' => 'flame-outline', 'criteria_type' => 'app_streak_days', 'criteria_value' => 7, 'bonus_points' => 10, 'sort_order' => 60 ),
				array( 'slug' => 'app_streak_30', 'title' => '30-Day App Streak', 'description' => 'Open the app thirty days in a row.', 'category' => 'loyalty', 'icon' => 'flame', 'criteria_type' => 'app_streak_days', 'criteria_value' => 30, 'bonus_points' => 50, 'sort_order' => 70 ),
			);
			foreach ( $achievements as $achievement ) {
				MBMC_DB::insert_loyalty_achievement( $achievement );
			}
		}
	}
}
