<?php
/**
 * REST API: team catch report submissions.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Team
 */
class MBMC_REST_Team {

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
			'/team',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_team_hub' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/team/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_team_me' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/team/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_team_posts' ),
					'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_team_post' ),
					'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/team/catch-report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_catch_report' ),
				'permission_callback' => 'mbmc_jwt_auth_permission_callback',
				'args'                => $this->get_args(),
			)
		);
	}

	/**
	 * Require team membership or return 403.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{user: WP_User, profile: array}|WP_Error
	 */
	private function require_team_member( WP_REST_Request $request ) {
		$user_id = mbmc_get_user_id_from_jwt( $request );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to access Team features.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in to access Team features.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$profile = mbmc_get_user_team_profile( $user );
		if ( empty( $profile['isTeamMember'] ) ) {
			return new WP_Error(
				'mbmc_team_access_denied',
				__( 'Team access required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 403 )
			);
		}

		return array(
			'user'    => $user,
			'profile' => $profile,
		);
	}

	/**
	 * GET /team — hub content for team members.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_team_hub( WP_REST_Request $request ) {
		$auth = $this->require_team_member( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$role = (string) $auth['profile']['teamRole'];
		return rest_ensure_response(
			array(
				'ok'            => true,
				'teamRole'      => $role,
				'teamStatus'    => (string) $auth['profile']['teamStatus'],
				'announcements' => $this->map_posts_for_role( 'announcement', $role ),
				'campaignTasks' => $this->map_posts_for_role( 'campaign_task', $role ),
				'productTests'  => $this->map_posts_for_role( 'product_test', $role ),
			)
		);
	}

	/**
	 * GET /team/me — profile + unread counts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_team_me( WP_REST_Request $request ) {
		$auth = $this->require_team_member( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$role = (string) $auth['profile']['teamRole'];
		$announcements = $this->map_posts_for_role( 'announcement', $role );

		return rest_ensure_response(
			array_merge(
				$auth['profile'],
				array(
					'ok'                     => true,
					'unreadAnnouncements'    => count( $announcements ),
					'pendingCampaignTasks'   => count(
						array_filter(
							$this->map_posts_for_role( 'campaign_task', $role ),
							static function ( $task ) {
								return 'pending' === ( $task['status'] ?? '' );
							}
						)
					),
				)
			)
		);
	}

	/**
	 * GET /team/posts
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_team_posts( WP_REST_Request $request ) {
		$auth = $this->require_team_member( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$type = sanitize_key( (string) ( $request->get_param( 'type' ) ?: 'announcement' ) );
		$role = (string) $auth['profile']['teamRole'];

		return rest_ensure_response(
			array(
				'ok'    => true,
				'posts' => $this->map_posts_for_role( $type, $role ),
			)
		);
	}

	/**
	 * POST /team/posts — team members can submit content requests (admin publishes).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_team_post( WP_REST_Request $request ) {
		$auth = $this->require_team_member( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$title = mbmc_sanitize_short_text( (string) $request->get_param( 'title' ), 255 );
		$body  = mbmc_sanitize_long_text( (string) $request->get_param( 'body' ) );
		if ( '' === $title || '' === $body ) {
			return new WP_Error( 'mbmc_invalid_post', __( 'title and body are required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$post_type = sanitize_key( (string) ( $request->get_param( 'postType' ) ?: 'announcement' ) );
		$meta      = $request->get_param( 'meta' );
		$payload   = is_array( $meta ) ? wp_json_encode( $meta ) : wp_json_encode( array() );

		$id = MBMC_DB::insert_team_post(
			array(
				'author_user_id' => (int) $auth['user']->ID,
				'title'          => $title,
				'body'           => $body . "\n\n<!--meta:" . $payload . '-->',
				'post_type'      => $post_type,
				'audience_roles' => array( (string) $auth['profile']['teamRole'] ),
				'is_published'   => 0,
			)
		);

		if ( ! $id ) {
			return new WP_Error( 'mbmc_post_failed', __( 'Could not save team post.', 'mad-baits-mobile-connector' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'ok'     => true,
				'id'     => (string) $id,
				'status' => 'submitted',
			)
		);
	}

	/**
	 * Map team posts to mobile payloads filtered by role.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $role      Team role.
	 * @return array<int, array<string, mixed>>
	 */
	private function map_posts_for_role( $post_type, $role ) {
		$rows   = MBMC_DB::list_team_posts( 100, true );
		$mapped = array();

		foreach ( $rows as $row ) {
			if ( sanitize_key( (string) $row->post_type ) !== sanitize_key( $post_type ) ) {
				continue;
			}

			$audience = json_decode( (string) $row->audience_roles, true );
			if ( is_array( $audience ) && ! empty( $audience ) && ! in_array( $role, $audience, true ) && ! in_array( 'all', $audience, true ) ) {
				continue;
			}

			$meta = $this->parse_post_meta( (string) $row->body );
			$body = preg_replace( '/\n\n<!--meta:.*-->/s', '', (string) $row->body );

			if ( 'announcement' === $post_type ) {
				$mapped[] = array(
					'id'        => (string) $row->id,
					'title'     => (string) $row->title,
					'body'      => trim( (string) $body ),
					'category'  => (string) ( $meta['category'] ?? 'general' ),
					'createdAt' => mbmc_format_datetime_for_api( $row->created_at ),
					'read'      => false,
					'priority'  => (string) ( $meta['priority'] ?? 'normal' ),
				);
			} elseif ( 'campaign_task' === $post_type ) {
				$mapped[] = array(
					'id'          => (string) $row->id,
					'title'       => (string) $row->title,
					'description' => trim( (string) $body ),
					'status'      => (string) ( $meta['status'] ?? 'pending' ),
					'dueDate'     => (string) ( $meta['dueDate'] ?? mbmc_format_datetime_for_api( $row->created_at ) ),
					'rewardLabel' => isset( $meta['rewardLabel'] ) ? (string) $meta['rewardLabel'] : null,
					'category'    => (string) ( $meta['category'] ?? 'general' ),
				);
			} else {
				$mapped[] = array(
					'id'                => (string) $row->id,
					'productName'       => (string) $row->title,
					'sku'               => isset( $meta['sku'] ) ? (string) $meta['sku'] : null,
					'status'            => (string) ( $meta['status'] ?? 'active' ),
					'testingNotes'      => (string) ( $meta['testingNotes'] ?? '' ),
					'rating'            => isset( $meta['rating'] ) ? (int) $meta['rating'] : null,
					'catchResultNotes'  => (string) ( $meta['catchResultNotes'] ?? '' ),
					'linkedCatchId'     => isset( $meta['linkedCatchId'] ) ? (string) $meta['linkedCatchId'] : null,
					'feedbackSubmitted' => ! empty( $meta['feedbackSubmitted'] ),
					'startedAt'         => mbmc_format_datetime_for_api( $row->created_at ),
				);
			}
		}

		return $mapped;
	}

	/**
	 * @param string $body Post body with embedded meta comment.
	 * @return array<string, mixed>
	 */
	private function parse_post_meta( $body ) {
		if ( preg_match( '/<!--meta:(.*?)-->/s', $body, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	/**
	 * POST /team/catch-report
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_catch_report( WP_REST_Request $request ) {
		$venue = mbmc_sanitize_short_text( (string) $request->get_param( 'venue' ), 255 );
		$bait  = mbmc_sanitize_short_text(
			(string) ( $request->get_param( 'baitUsed' ) ?: $request->get_param( 'productUsed' ) ),
			255
		);
		$caption = mbmc_sanitize_long_text( (string) $request->get_param( 'socialCaption' ), 2000 );

		if ( '' === $venue ) {
			return new WP_Error(
				'mbmc_invalid_venue',
				__( 'venue is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $bait ) {
			return new WP_Error(
				'mbmc_invalid_bait',
				__( 'baitUsed (or productUsed) is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $caption ) {
			return new WP_Error(
				'mbmc_invalid_caption',
				__( 'socialCaption is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$species       = mbmc_sanitize_short_text( (string) $request->get_param( 'species' ), 128 );
		$weight        = mbmc_sanitize_short_text(
			(string) ( $request->get_param( 'fishWeight' ) ?: $request->get_param( 'weightDisplay' ) ),
			64
		);
		$notes         = mbmc_sanitize_long_text( (string) $request->get_param( 'notes' ) );
		$angler_name   = mbmc_sanitize_short_text( (string) $request->get_param( 'anglerName' ), 255 );
		$rig_used      = mbmc_sanitize_short_text( (string) $request->get_param( 'rigUsed' ), 255 );
		$photo_url     = mbmc_sanitize_url_field( (string) $request->get_param( 'photoUrl' ) );
		$user_id       = mbmc_get_user_id_from_jwt( $request );

		if ( $user_id <= 0 ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in with your Mad Baits Team account to submit catch reports.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'mbmc_unauthorized',
				__( 'Sign in with your Mad Baits Team account to submit catch reports.', 'mad-baits-mobile-connector' ),
				array( 'status' => 401 )
			);
		}

		$team_profile = mbmc_get_user_team_profile( $user );
		if ( empty( $team_profile['isTeamMember'] ) ) {
			return new WP_Error(
				'mbmc_team_access_denied',
				__( 'Team catch reports require a Mad Baits Team invite account.', 'mad-baits-mobile-connector' ),
				array( 'status' => 403 )
			);
		}

		$team_role = (string) $team_profile['teamRole'];
		$fishery_id    = absint( $request->get_param( 'fisheryId' ) );
		$postcode      = mbmc_sanitize_short_text( (string) $request->get_param( 'postcode' ), 32 );
		$county        = mbmc_sanitize_short_text( (string) $request->get_param( 'county' ), 128 );
		$latitude      = $request->get_param( 'latitude' );
		$longitude     = $request->get_param( 'longitude' );
		$permission    = rest_sanitize_boolean( $request->get_param( 'permissionToUse' ) )
			|| rest_sanitize_boolean( $request->get_param( 'photoPermission' ) );

		if ( $fishery_id <= 0 ) {
			$duplicate = MBMC_DB::find_duplicate_fishery(
				$venue,
				$postcode,
				is_numeric( $latitude ) ? (float) $latitude : null,
				is_numeric( $longitude ) ? (float) $longitude : null
			);
			if ( $duplicate ) {
				$fishery_id = (int) $duplicate->id;
			} else {
				$created = MBMC_DB::insert_fishery(
					array(
						'fishery_name'    => $venue,
						'lake_name'       => $venue,
						'postcode'        => $postcode ?: null,
						'county'          => $county ?: null,
						'latitude'        => is_numeric( $latitude ) ? (float) $latitude : null,
						'longitude'       => is_numeric( $longitude ) ? (float) $longitude : null,
						'description'     => null,
						'photos'          => null,
						'created_by_user' => $user_id > 0 ? $user_id : null,
						'approved'        => false,
					)
				);
				if ( false !== $created ) {
					$fishery_id = (int) $created;
				}
			}
		}

		if ( '' === $angler_name && '' !== $species ) {
			$angler_name = $species;
		}

		$combined_notes = $notes;

		if ( '' !== $species && false === strpos( $notes, $species ) ) {
			$prefix = sprintf( 'Species: %s', $species );
			$combined_notes = '' !== $notes ? $prefix . "\n" . $notes : $prefix;
		}

		$result = MBMC_DB::insert_team_catch_report(
			array(
				'user_id'           => $user_id,
				'fishery_id'        => $fishery_id > 0 ? $fishery_id : null,
				'team_role'         => $team_role,
				'angler_name'       => $angler_name ?: null,
				'fish_weight'       => $weight ?: null,
				'venue'             => $venue,
				'bait_used'         => $bait,
				'rig_used'          => $rig_used ?: null,
				'notes'             => $combined_notes ?: null,
				'photo_url'         => '' !== $photo_url ? $photo_url : null,
				'permission_to_use' => $permission,
				'social_caption'    => $caption,
				'status'            => 'submitted',
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'mbmc_catch_report_failed',
				__( 'Could not save catch report.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'id'        => (string) $result,
				'status'    => 'submitted',
				'fisheryId' => $fishery_id > 0 ? (string) $fishery_id : null,
				'teamRole'  => $team_role,
				'source'    => 'wordpress',
				'generatedAt' => wp_date( 'c' ),
			)
		);
	}

	/**
	 * Endpoint args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_args() {
		return array(
			'venue'            => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'baitUsed'         => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'productUsed'      => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'socialCaption'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_long_text',
			),
			'teamRole'         => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_team_role',
			),
			'anglerName'       => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'fishWeight'       => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'weightDisplay'    => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'species'          => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'rigUsed'          => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'notes'            => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_long_text',
			),
			'photoUrl'         => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_url_field',
			),
			'permissionToUse'  => array(
				'required'          => false,
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'photoPermission'  => array(
				'required'          => false,
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'fisheryId'        => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'postcode'         => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'county'           => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'latitude'         => array(
				'required'          => false,
				'type'              => 'number',
			),
			'longitude'        => array(
				'required'          => false,
				'type'              => 'number',
			),
		);
	}
}
