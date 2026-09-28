<?php
/**
 * REST API: loyalty points summary, config, rewards and transaction history.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Loyalty
 */
class MBMC_REST_Loyalty {

	const REST_NAMESPACE = 'madbaits/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/transactions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_transactions' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/rewards',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_rewards' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/achievements',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_achievements' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/redeem',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'redeem_reward' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/loyalty/checkout-apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkout_apply' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);
	}

	/**
	 * GET /loyalty/config — dynamic rules + copy for app (no auth beyond app token).
	 *
	 * @return WP_REST_Response
	 */
	public function get_config() {
		return rest_ensure_response(
			array(
				'ok'    => true,
				'rules' => MBMC_Loyalty_Config::rules_for_api(),
			)
		);
	}

	/**
	 * GET /loyalty/rewards — active rewards catalog.
	 *
	 * @return WP_REST_Response
	 */
	public function list_rewards() {
		$rows = MBMC_DB::list_loyalty_rewards( true );
		return rest_ensure_response(
			array(
				'ok'      => true,
				'rewards' => array_map( array( 'MBMC_Loyalty', 'serialize_reward' ), $rows ),
			)
		);
	}

	/**
	 * GET /loyalty/achievements — active visible achievements.
	 *
	 * @return WP_REST_Response
	 */
	public function list_achievements() {
		$rows = MBMC_DB::list_loyalty_achievements( true );
		return rest_ensure_response(
			array(
				'ok'           => true,
				'achievements' => array_map( array( 'MBMC_Loyalty', 'serialize_achievement' ), $rows ),
			)
		);
	}

	/**
	 * GET /loyalty/summary
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_summary( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to view rewards.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$balance  = MBMC_Loyalty::get_available_balance( $user_id );
		$pending  = MBMC_DB::get_loyalty_pending_points( $user_id );
		$lifetime = MBMC_DB::get_loyalty_lifetime_earned( $user_id );
		$expiring = MBMC_DB::get_loyalty_expiring_soon( $user_id, 30 );

		return rest_ensure_response(
			array(
				'ok'                 => true,
				'availablePoints'    => $balance,
				'pendingPoints'      => $pending,
				'lifetimeEarned'     => $lifetime,
				'expiringSoonPoints' => (int) $expiring['points'],
				'expiringSoonDate'   => $expiring['date'],
			)
		);
	}

	/**
	 * GET /loyalty/transactions
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_transactions( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to view rewards history.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$limit = min( 100, max( 1, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
		$rows  = MBMC_DB::list_loyalty_transactions( $user_id, $limit );

		return rest_ensure_response(
			array(
				'ok'           => true,
				'transactions' => array_map( array( 'MBMC_Loyalty', 'serialize_transaction' ), $rows ),
			)
		);
	}

	/**
	 * POST /loyalty/redeem
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function redeem_reward( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'mbmc_unauthorized', __( 'Sign in to redeem rewards.', 'mad-baits-mobile-connector' ), array( 'status' => 401 ) );
		}

		$reward_id = absint( $request->get_param( 'rewardId' ) );
		$slug      = sanitize_title( (string) $request->get_param( 'rewardSlug' ) );

		if ( $reward_id <= 0 && '' !== $slug ) {
			$rewards = MBMC_DB::list_loyalty_rewards( true );
			foreach ( $rewards as $reward ) {
				if ( $reward->slug === $slug ) {
					$reward_id = (int) $reward->id;
					break;
				}
			}
		}

		if ( $reward_id <= 0 ) {
			return new WP_Error( 'mbmc_invalid_reward', __( 'rewardId or rewardSlug is required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$idempotency_key = sanitize_text_field( (string) ( $request->get_param( 'idempotencyKey' ) ?: '' ) );
		$result = MBMC_Loyalty::redeem_reward( $user_id, $reward_id, $idempotency_key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * POST /loyalty/checkout-apply — preview points discount for checkout.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function checkout_apply( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'mbmc_unauthorized', __( 'Sign in to apply points at checkout.', 'mad-baits-mobile-connector' ), array( 'status' => 401 ) );
		}

		$line_items = $request->get_param( 'lineItems' );
		if ( ! is_array( $line_items ) || empty( $line_items ) ) {
			return new WP_Error( 'mbmc_invalid_line_items', __( 'lineItems are required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$eligible = MBMC_Loyalty_Eligibility::get_line_items_eligible_subtotal( $line_items );
		if ( is_wp_error( $eligible ) ) {
			return $eligible;
		}

		$has_coupon     = rest_sanitize_boolean( $request->get_param( 'hasCoupon' ) );
		$points_to_apply = absint( $request->get_param( 'pointsToApply' ) );
		$allowance       = MBMC_Loyalty_Eligibility::checkout_points_allowance( $user_id, $eligible, $has_coupon );

		if ( ! $allowance['canApply'] ) {
			return rest_ensure_response(
				array(
					'ok'               => false,
					'canApply'         => false,
					'reason'           => $allowance['reason'],
					'eligibleSubtotal' => round( (float) $eligible, 2 ),
					'maxPoints'        => 0,
					'pointsApplied'    => 0,
					'discountGbp'      => 0,
				)
			);
		}

		$applied = min( $points_to_apply, $allowance['maxPoints'] );
		$discount = MBMC_Loyalty_Eligibility::points_to_gbp_discount( $applied );

		return rest_ensure_response(
			array(
				'ok'               => true,
				'canApply'         => true,
				'eligibleSubtotal' => round( (float) $eligible, 2 ),
				'maxPoints'        => $allowance['maxPoints'],
				'pointsApplied'    => $applied,
				'discountGbp'      => $discount,
				'availablePoints'  => MBMC_Loyalty::get_available_balance( $user_id ),
				'checkoutPointsPerGbp' => MBMC_Loyalty_Config::get_int( 'checkout_points_per_gbp' ),
			)
		);
	}
}
