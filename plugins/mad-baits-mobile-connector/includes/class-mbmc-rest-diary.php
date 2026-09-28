<?php
/**
 * REST API: personal diary cloud sync.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Diary
 */
class MBMC_REST_Diary {

	const REST_NAMESPACE = 'madbaits/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/diary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_entries' ),
					'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upsert_entry' ),
					'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/diary/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_entry' ),
					'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_entry' ),
					'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				),
			)
		);
	}

	/**
	 * GET /diary
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_entries( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'mbmc_unauthorized', __( 'Sign in to sync your diary.', 'mad-baits-mobile-connector' ), array( 'status' => 401 ) );
		}

		$since = $request->get_param( 'since' );
		$limit = min( 200, max( 1, absint( $request->get_param( 'limit' ) ?: 100 ) ) );
		$rows  = MBMC_DB::list_diary_entries( $user_id, $since ? (string) $since : null, $limit );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'entries' => array_map( array( $this, 'serialize_entry' ), $rows ),
			)
		);
	}

	/**
	 * POST /diary
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upsert_entry( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'mbmc_unauthorized', __( 'Sign in to save diary entries.', 'mad-baits-mobile-connector' ), array( 'status' => 401 ) );
		}

		$local_id = mbmc_sanitize_short_text( (string) $request->get_param( 'localId' ), 64 );
		$payload  = $request->get_param( 'payload' );
		if ( '' === $local_id || ! is_array( $payload ) ) {
			return new WP_Error( 'mbmc_invalid_diary', __( 'localId and payload are required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$entry_type = sanitize_key( (string) ( $request->get_param( 'entryType' ) ?: 'session' ) );
		$visibility = sanitize_key( (string) ( $request->get_param( 'visibility' ) ?: 'private' ) );
		$client_updated_at = $request->get_param( 'clientUpdatedAt' );

		$id = MBMC_DB::upsert_diary_entry( $user_id, $local_id, $entry_type, $payload, $visibility, $client_updated_at );
		if ( ! $id ) {
			return new WP_Error( 'mbmc_diary_save_failed', __( 'Could not save diary entry.', 'mad-baits-mobile-connector' ), array( 'status' => 500 ) );
		}

		$row = MBMC_DB::get_diary_entry_by_id( $user_id, $id );
		return rest_ensure_response(
			array(
				'ok'    => true,
				'entry' => $row ? $this->serialize_entry( $row ) : null,
			)
		);
	}

	/**
	 * PUT /diary/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_entry( WP_REST_Request $request ) {
		$user_id  = mbmc_get_user_id_from_jwt( $request );
		$entry_id = absint( $request->get_param( 'id' ) );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'mbmc_unauthorized', __( 'Sign in to update diary entries.', 'mad-baits-mobile-connector' ), array( 'status' => 401 ) );
		}

		$row = MBMC_DB::get_diary_entry_by_id( $user_id, $entry_id );
		if ( ! $row ) {
			return new WP_Error( 'mbmc_not_found', __( 'Diary entry not found.', 'mad-baits-mobile-connector' ), array( 'status' => 404 ) );
		}

		$payload = $request->get_param( 'payload' );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'mbmc_invalid_diary', __( 'payload is required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$visibility = sanitize_key( (string) ( $request->get_param( 'visibility' ) ?: $row->visibility ) );
		$id = MBMC_DB::upsert_diary_entry(
			$user_id,
			(string) $row->local_id,
			(string) $row->entry_type,
			$payload,
			$visibility,
			$request->get_param( 'clientUpdatedAt' )
		);

		$updated = MBMC_DB::get_diary_entry_by_id( $user_id, $entry_id );
		return rest_ensure_response(
			array(
				'ok'    => true,
				'entry' => $updated ? $this->serialize_entry( $updated ) : null,
			)
		);
	}

	/**
	 * DELETE /diary/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_entry( WP_REST_Request $request ) {
		$user_id  = mbmc_get_user_id_from_jwt( $request );
		$entry_id = absint( $request->get_param( 'id' ) );
		if ( $user_id <= 0 ) {
			return new WP_Error( 'mbmc_unauthorized', __( 'Sign in to delete diary entries.', 'mad-baits-mobile-connector' ), array( 'status' => 401 ) );
		}

		if ( ! MBMC_DB::soft_delete_diary_entry( $user_id, $entry_id ) ) {
			return new WP_Error( 'mbmc_not_found', __( 'Diary entry not found.', 'mad-baits-mobile-connector' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'ok' => true, 'deleted' => true ) );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	private function serialize_entry( $row ) {
		$payload = json_decode( (string) $row->payload, true );
		return array(
			'id'              => (string) $row->id,
			'localId'         => (string) $row->local_id,
			'entryType'       => (string) $row->entry_type,
			'visibility'      => (string) $row->visibility,
			'payload'         => is_array( $payload ) ? $payload : array(),
			'clientUpdatedAt' => ! empty( $row->client_updated_at ) ? mbmc_format_datetime_for_api( $row->client_updated_at ) : null,
			'createdAt'       => mbmc_format_datetime_for_api( $row->created_at ),
			'updatedAt'       => mbmc_format_datetime_for_api( $row->updated_at ),
		);
	}
}
