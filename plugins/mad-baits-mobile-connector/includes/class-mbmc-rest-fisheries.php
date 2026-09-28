<?php
/**
 * REST API: fisheries search, add, moderation.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Fisheries
 */
class MBMC_REST_Fisheries {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'madbaits/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/fisheries/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_fisheries' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/fisheries/nearby',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'nearby_fisheries' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/fisheries',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_fishery' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/fisheries/(?P<id>\d+)/moderate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'moderate_fishery' ),
				'permission_callback' => array( $this, 'can_moderate' ),
			)
		);
	}

	/**
	 * Permission callback for moderation routes.
	 *
	 * @return bool
	 */
	public function can_moderate() {
		return mbmc_user_can_manage_mobile_app();
	}

	/**
	 * GET /fisheries/search
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function search_fisheries( WP_REST_Request $request ) {
		$query    = mbmc_sanitize_short_text( (string) $request->get_param( 'query' ), 191 );
		$postcode = mbmc_sanitize_short_text( (string) $request->get_param( 'postcode' ), 32 );
		$limit    = max( 1, min( 40, absint( $request->get_param( 'limit' ) ?: 12 ) ) );

		$rows = MBMC_DB::search_fisheries(
			array(
				'query'    => $query,
				'postcode' => $postcode,
				'limit'    => $limit,
			)
		);

		$source = 'wordpress';
		if ( empty( $rows ) && '' !== $query ) {
			$fallback = $this->search_google_places( $query, $limit );
			if ( ! empty( $fallback ) ) {
				$rows   = $fallback;
				$source = 'google_places';
			}
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'source'    => $source,
				'results'   => array_map( array( $this, 'serialize_fishery' ), $rows ),
				'generatedAt' => wp_date( 'c' ),
			)
		);
	}

	/**
	 * GET /fisheries/nearby
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function nearby_fisheries( WP_REST_Request $request ) {
		$latitude  = $request->get_param( 'latitude' );
		$longitude = $request->get_param( 'longitude' );
		$limit     = max( 1, min( 40, absint( $request->get_param( 'limit' ) ?: 12 ) ) );

		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return new WP_Error(
				'mbmc_invalid_coordinates',
				__( 'latitude and longitude are required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$rows = MBMC_DB::list_nearby_fisheries( (float) $latitude, (float) $longitude, $limit );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'source'  => 'wordpress',
				'results' => array_map( array( $this, 'serialize_fishery' ), $rows ),
			)
		);
	}

	/**
	 * POST /fisheries
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_fishery( WP_REST_Request $request ) {
		$fishery_name = mbmc_sanitize_short_text( (string) $request->get_param( 'fishery_name' ), 191 );
		$lake_name    = mbmc_sanitize_short_text( (string) $request->get_param( 'lake_name' ), 191 );
		$postcode     = mbmc_sanitize_short_text( (string) $request->get_param( 'postcode' ), 32 );
		$county       = mbmc_sanitize_short_text( (string) $request->get_param( 'county' ), 128 );
		$description  = mbmc_sanitize_long_text( (string) $request->get_param( 'description' ) );
		$photos       = $request->get_param( 'photos' );
		$latitude     = $request->get_param( 'latitude' );
		$longitude    = $request->get_param( 'longitude' );

		if ( '' === $fishery_name ) {
			return new WP_Error(
				'mbmc_invalid_fishery_name',
				__( 'fishery_name is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$lat_value = is_numeric( $latitude ) ? (float) $latitude : null;
		$lng_value = is_numeric( $longitude ) ? (float) $longitude : null;

		$duplicate = MBMC_DB::find_duplicate_fishery(
			$fishery_name,
			$postcode,
			$lat_value,
			$lng_value
		);

		if ( $duplicate ) {
			return rest_ensure_response(
				array(
					'ok'         => true,
					'duplicate'  => true,
					'fishery'    => $this->serialize_fishery( $duplicate ),
					'message'    => 'Fishery already exists.',
				)
			);
		}

		$user_id = mbmc_get_user_id_from_jwt( $request );
		$result  = MBMC_DB::insert_fishery(
			array(
				'fishery_name'    => $fishery_name,
				'lake_name'       => $lake_name ?: null,
				'postcode'        => $postcode ?: null,
				'county'          => $county ?: null,
				'latitude'        => $lat_value,
				'longitude'       => $lng_value,
				'description'     => $description ?: null,
				'photos'          => is_array( $photos ) ? wp_json_encode( $photos ) : null,
				'created_by_user' => $user_id > 0 ? $user_id : null,
				'approved'        => false,
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'mbmc_fishery_create_failed',
				__( 'Could not create fishery.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		$created = MBMC_DB::get_fishery_by_id( (int) $result );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'pending' => true,
				'fishery' => $created ? $this->serialize_fishery( $created ) : null,
			)
		);
	}

	/**
	 * POST /fisheries/{id}/moderate
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function moderate_fishery( WP_REST_Request $request ) {
		$id       = absint( $request['id'] );
		$approved = rest_sanitize_boolean( $request->get_param( 'approved' ) );
		$reason   = mbmc_sanitize_short_text( (string) $request->get_param( 'rejection_reason' ), 255 );

		if ( $id <= 0 ) {
			return new WP_Error(
				'mbmc_invalid_fishery_id',
				__( 'Invalid fishery id.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$updated = MBMC_DB::set_fishery_approval( $id, $approved, $reason );
		if ( ! $updated ) {
			return new WP_Error(
				'mbmc_fishery_moderation_failed',
				__( 'Could not update fishery.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		$fishery = MBMC_DB::get_fishery_by_id( $id );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'fishery' => $fishery ? $this->serialize_fishery( $fishery ) : null,
			)
		);
	}

	/**
	 * Convert DB row to API payload.
	 *
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	private function serialize_fishery( $row ) {
		$photos = array();
		if ( isset( $row->photos ) && is_string( $row->photos ) && '' !== $row->photos ) {
			$decoded = json_decode( $row->photos, true );
			if ( is_array( $decoded ) ) {
				$photos = $decoded;
			}
		}

		return array(
			'id'              => isset( $row->id ) ? (int) $row->id : 0,
			'fishery_name'    => (string) ( $row->fishery_name ?? $row->name ?? '' ),
			'lake_name'       => isset( $row->lake_name ) ? (string) $row->lake_name : '',
			'postcode'        => isset( $row->postcode ) ? (string) $row->postcode : '',
			'county'          => isset( $row->county ) ? (string) $row->county : '',
			'latitude'        => isset( $row->latitude ) && null !== $row->latitude ? (float) $row->latitude : null,
			'longitude'       => isset( $row->longitude ) && null !== $row->longitude ? (float) $row->longitude : null,
			'description'     => isset( $row->description ) ? (string) $row->description : '',
			'photos'          => $photos,
			'created_by_user' => isset( $row->created_by_user ) ? (int) $row->created_by_user : 0,
			'approved'        => isset( $row->approved ) ? (bool) $row->approved : true,
			'distance_km'     => isset( $row->distance_km ) ? (float) $row->distance_km : null,
		);
	}

	/**
	 * Google Places fallback search.
	 *
	 * @param string $query Search text.
	 * @param int    $limit Max rows.
	 * @return array<int, object>
	 */
	private function search_google_places( $query, $limit ) {
		$api_key = trim( (string) get_option( 'mbmc_google_places_api_key', '' ) );
		if ( '' === $api_key ) {
			return array();
		}

		$url      = add_query_arg(
			array(
				'query' => rawurlencode( $query . ' fishery uk' ),
				'key'   => $api_key,
			),
			'https://maps.googleapis.com/maps/api/place/textsearch/json'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $body->results ) || ! is_array( $body->results ) ) {
			return array();
		}

		$results = array();
		foreach ( array_slice( $body->results, 0, $limit ) as $place ) {
			$row               = new stdClass();
			$row->id           = 0;
			$row->fishery_name = isset( $place->name ) ? (string) $place->name : '';
			$row->lake_name    = '';
			$row->postcode     = '';
			$row->county       = '';
			$row->latitude     = isset( $place->geometry->location->lat ) ? (float) $place->geometry->location->lat : null;
			$row->longitude    = isset( $place->geometry->location->lng ) ? (float) $place->geometry->location->lng : null;
			$row->description  = isset( $place->formatted_address ) ? (string) $place->formatted_address : '';
			$row->photos       = '';
			$row->created_by_user = 0;
			$row->approved     = true;
			$results[]         = $row;
		}

		return $results;
	}
}
