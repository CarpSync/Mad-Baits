<?php
/**
 * REST API: achievements catalog, user progress, evaluation.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Achievements
 */
class MBMC_REST_Achievements {

	const REST_NAMESPACE = 'madbaits/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/achievements',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_achievements' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/achievements/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_my_achievements' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/achievements/evaluate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'evaluate_achievements' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);
	}

	/**
	 * GET /achievements
	 *
	 * @return WP_REST_Response
	 */
	public function list_achievements() {
		$rows = MBMC_DB::list_loyalty_achievements( true );
		return rest_ensure_response(
			array(
				'ok'           => true,
				'achievements' => array_map(
					static function ( $row ) {
						return MBMC_Achievements::serialize_for_api( $row );
					},
					$rows
				),
			)
		);
	}

	/**
	 * GET /achievements/me
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_my_achievements( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to view achievements.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$definitions = MBMC_DB::list_loyalty_achievements( true );
		$unlocks     = MBMC_DB::list_user_achievements( $user_id );
		$unlock_map  = array();
		foreach ( $unlocks as $unlock ) {
			$unlock_map[ (string) $unlock->achievement_slug ] = $unlock;
		}

		$items = array();
		foreach ( $definitions as $def ) {
			$slug = (string) $def->slug;
			$items[] = MBMC_Achievements::serialize_for_api( $def, $unlock_map[ $slug ] ?? null );
		}

		return rest_ensure_response(
			array(
				'ok'           => true,
				'achievements' => $items,
			)
		);
	}

	/**
	 * POST /achievements/evaluate
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function evaluate_achievements( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_achievements_evaluate', 60, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'mbmc_rate_limited',
				__( 'Too many requests. Please try again later.', 'mad-baits-mobile-connector' ),
				array( 'status' => 429 )
			);
		}

		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to evaluate achievements.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$event   = sanitize_key( (string) ( $request->get_param( 'event' ) ?: 'app_opened' ) );
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$new_unlocks = MBMC_Achievements::evaluate_for_user( $user_id, $event, $context );

		return rest_ensure_response(
			array(
				'ok'          => true,
				'newUnlocks'  => $new_unlocks,
				'evaluatedAt' => wp_date( 'c' ),
			)
		);
	}
}
