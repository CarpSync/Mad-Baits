<?php
/**
 * REST API: community catch publish requests and home feed.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Catches
 */
class MBMC_REST_Catches {

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
			'/catches/published',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_published_catches' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/catches/publish-request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_publish_request' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/catches/publish-request/(?P<localCatchId>[a-zA-Z0-9_\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_publish_status' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/catches/(?P<id>\d+)/moderate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'moderate_catch' ),
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
	 * GET /catches/published
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_published_catches( WP_REST_Request $request ) {
		$limit = max( 1, min( 40, absint( $request->get_param( 'limit' ) ?: 12 ) ) );
		$rows  = MBMC_DB::list_published_community_catches( $limit );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'catches' => array_map( array( $this, 'serialize_community_catch' ), $rows ),
			)
		);
	}

	/**
	 * POST /catches/publish-request
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_publish_request( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to share your catch on the home feed.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$local_catch_id = mbmc_sanitize_short_text( (string) $request->get_param( 'localCatchId' ), 64 );
		$species        = mbmc_sanitize_short_text( (string) $request->get_param( 'species' ), 128 );
		$venue          = mbmc_sanitize_short_text( (string) $request->get_param( 'venue' ), 255 );

		if ( '' === $local_catch_id ) {
			return new WP_Error(
				'mbmc_invalid_local_catch_id',
				__( 'localCatchId is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $species ) {
			return new WP_Error(
				'mbmc_invalid_species',
				__( 'species is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $venue ) {
			return new WP_Error(
				'mbmc_invalid_venue',
				__( 'venue is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$photo_url = mbmc_sanitize_url_field( (string) $request->get_param( 'photoUrl' ) );
		if ( '' === $photo_url ) {
			$photo_base64 = (string) $request->get_param( 'photoBase64' );
			$photo_mime   = (string) ( $request->get_param( 'photoMimeType' ) ?: 'image/jpeg' );
			if ( '' !== trim( $photo_base64 ) ) {
				$uploaded = mbmc_upload_base64_image( $photo_base64, $photo_mime, 'mbmc-community-catch' );
				if ( false !== $uploaded ) {
					$photo_url = $uploaded;
				}
			}
		}

		$angler_name = mbmc_sanitize_short_text( (string) $request->get_param( 'anglerName' ), 255 );
		if ( '' === $angler_name ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user instanceof WP_User ) {
				$angler_name = $user->display_name;
			}
		}

		$result_id = MBMC_DB::upsert_community_catch(
			array(
				'user_id'        => $user_id,
				'local_catch_id' => $local_catch_id,
				'angler_name'    => $angler_name ?: 'Mad Baits angler',
				'species'        => $species,
				'weight_display' => mbmc_sanitize_short_text( (string) $request->get_param( 'weightDisplay' ), 64 ),
				'venue'          => $venue,
				'peg'            => mbmc_sanitize_short_text( (string) $request->get_param( 'peg' ), 64 ),
				'bait_used'      => mbmc_sanitize_short_text( (string) $request->get_param( 'baitUsed' ), 255 ),
				'rig_used'       => mbmc_sanitize_short_text( (string) $request->get_param( 'rigUsed' ), 255 ),
				'notes'          => mbmc_sanitize_long_text( (string) $request->get_param( 'notes' ) ),
				'photo_url'      => '' !== $photo_url ? $photo_url : null,
				'caught_at'      => (string) $request->get_param( 'caughtAt' ),
				'status'         => 'pending',
			)
		);

		if ( false === $result_id ) {
			return new WP_Error(
				'mbmc_publish_request_failed',
				__( 'Could not save your publish request.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		$row = MBMC_DB::get_community_catch_by_id( (int) $result_id );

		return rest_ensure_response(
			array(
				'ok'              => true,
				'id'              => (string) $result_id,
				'status'          => $row ? (string) $row->status : 'pending',
				'rejectionReason' => $row && ! empty( $row->rejection_reason ) ? (string) $row->rejection_reason : null,
			)
		);
	}

	/**
	 * GET /catches/publish-request/{localCatchId}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_publish_status( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to check publish status.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$local_catch_id = mbmc_sanitize_short_text( (string) $request->get_param( 'localCatchId' ), 64 );
		$row            = MBMC_DB::get_community_catch_by_local_id( $user_id, $local_catch_id );

		if ( ! $row ) {
			return new WP_Error(
				'mbmc_not_found',
				__( 'No publish request found for this catch.', 'mad-baits-mobile-connector' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'ok'               => true,
				'status'           => (string) $row->status,
				'communityCatchId' => (string) $row->id,
				'rejectionReason'  => ! empty( $row->rejection_reason ) ? (string) $row->rejection_reason : null,
			)
		);
	}

	/**
	 * POST /catches/{id}/moderate
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function moderate_catch( WP_REST_Request $request ) {
		$id     = absint( $request->get_param( 'id' ) );
		$action = mbmc_sanitize_short_text( (string) $request->get_param( 'action' ), 16 );

		if ( $id <= 0 ) {
			return new WP_Error(
				'mbmc_invalid_id',
				__( 'Invalid catch id.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( 'approve' === $action ) {
			$updated = MBMC_DB::set_community_catch_status( $id, 'published' );
			if ( $updated ) {
				$row = MBMC_DB::get_community_catch_by_id( $id );
				if ( $row && ! empty( $row->user_id ) ) {
					$featured = rest_sanitize_boolean( $request->get_param( 'featured' ) );
					MBMC_Loyalty::award_catch_report( (int) $row->user_id, $id, $featured );
				}
			}
		} elseif ( 'reject' === $action ) {
			$reason  = mbmc_sanitize_short_text( (string) $request->get_param( 'reason' ), 255 );
			$updated = MBMC_DB::set_community_catch_status( $id, 'rejected', $reason ?: 'Rejected by moderator' );
		} else {
			return new WP_Error(
				'mbmc_invalid_action',
				__( 'action must be approve or reject.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $updated ) {
			return new WP_Error(
				'mbmc_moderation_failed',
				__( 'Could not update catch status.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		$row = MBMC_DB::get_community_catch_by_id( $id );

		return rest_ensure_response(
			array(
				'ok'     => true,
				'id'     => (string) $id,
				'status' => $row ? (string) $row->status : 'pending',
			)
		);
	}

	/**
	 * Serialize a community catch row for the mobile app.
	 *
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	private function serialize_community_catch( $row ) {
		return array(
			'id'            => (string) $row->id,
			'localCatchId'  => (string) $row->local_catch_id,
			'anglerName'    => (string) $row->angler_name,
			'species'       => (string) $row->species,
			'weightDisplay' => (string) $row->weight_display,
			'venue'         => (string) $row->venue,
			'baitUsed'      => ! empty( $row->bait_used ) ? (string) $row->bait_used : null,
			'photoUrl'      => ! empty( $row->photo_url ) ? (string) $row->photo_url : null,
			'caughtAt'      => mbmc_format_datetime_for_api( $row->caught_at ),
			'publishedAt'   => ! empty( $row->published_at ) ? mbmc_format_datetime_for_api( $row->published_at ) : null,
		);
	}
}
