<?php
/**
 * REST API: Mad Baits events endpoint.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Events
 */
class MBMC_REST_Events {

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
			'/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'featured' => array(
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 50,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * GET /madbaits/v1/events
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events( WP_REST_Request $request ) {
		$featured_only = (bool) $request->get_param( 'featured' );
		$per_page      = (int) $request->get_param( 'per_page' );
		$page          = (int) $request->get_param( 'page' );
		if ( $page < 1 ) {
			$page = 1;
		}

		$post_types = array( 'madbaits_event', 'mad_event' );
		if ( function_exists( 'post_type_exists' ) ) {
			$post_types = array_values(
				array_filter(
					$post_types,
					static function ( $type ) {
						return post_type_exists( $type );
					}
				)
			);
		}
		if ( empty( $post_types ) ) {
			$post_types = array( 'madbaits_event', 'mad_event' );
		}

		$query_args = array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( $featured_only ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => 'event_is_featured',
					'value' => '1',
				),
			);
		}

		$query = new WP_Query( $query_args );

		$events = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$events[] = $this->map_event( $post );
			}
		}

		// Posts without start dates sort last but remain published.
		usort(
			$events,
			static function ( $a, $b ) {
				$a_time = isset( $a['startDate'] ) && $a['startDate'] ? strtotime( $a['startDate'] ) : PHP_INT_MAX;
				$b_time = isset( $b['startDate'] ) && $b['startDate'] ? strtotime( $b['startDate'] ) : PHP_INT_MAX;

				if ( $a_time === $b_time ) {
					return strcmp( $a['title'], $b['title'] );
				}

				return $a_time <=> $b_time;
			}
		);

		$response = array(
			'events'      => $events,
			'source'      => 'wordpress',
			'generatedAt' => wp_date( 'c' ),
			'pagination'  => array(
				'page'       => $page,
				'perPage'    => $per_page,
				'total'      => (int) $query->found_posts,
				'totalPages' => (int) $query->max_num_pages,
				'hasMore'    => $page < (int) $query->max_num_pages,
			),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Map WP post to mobile app event shape.
	 *
	 * @param WP_Post $post Event post.
	 * @return array<string, mixed>
	 */
	private function map_event( WP_Post $post ) {
		$start_raw     = get_post_meta( $post->ID, 'event_start_date', true );
		$end_raw       = get_post_meta( $post->ID, 'event_end_date', true );
		$location      = get_post_meta( $post->ID, 'event_location', true );
		$type_slug     = get_post_meta( $post->ID, 'event_type', true );
		$image_url     = get_post_meta( $post->ID, 'event_image_url', true );
		$cta_label     = get_post_meta( $post->ID, 'event_cta_label', true );
		$cta_url       = get_post_meta( $post->ID, 'event_cta_url', true );
		$is_featured   = (bool) get_post_meta( $post->ID, 'event_is_featured', true );
		$permalink     = get_permalink( $post );
		$description   = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' );
		$start_date    = mbmc_format_datetime_for_api( $start_raw );
		$end_date      = mbmc_format_datetime_for_api( $end_raw );

		if ( '' === $image_url && has_post_thumbnail( $post ) ) {
			$thumb = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'large' );
			if ( is_string( $thumb ) ) {
				$image_url = $thumb;
			}
		}

		if ( '' === $cta_label ) {
			$cta_label = __( 'View Event', 'mad-baits-mobile-connector' );
		}

		if ( '' === $cta_url && is_string( $permalink ) ) {
			$cta_url = $permalink;
		}

		return array(
			'id'             => (string) $post->ID,
			'title'          => sanitize_text_field( get_the_title( $post ) ),
			'description'    => sanitize_textarea_field( $description ),
			'startDate'      => $start_date,
			'endDate'        => $end_date,
			'dateConfirmed'  => null !== $start_date,
			'location'       => '' !== $location ? sanitize_text_field( $location ) : null,
			'eventType'      => mbmc_format_event_type_for_api( $type_slug ),
			'imageUrl'       => '' !== $image_url ? esc_url_raw( $image_url ) : null,
			'sourceUrl'      => is_string( $permalink ) ? esc_url_raw( $permalink ) : null,
			'isFeatured'     => $is_featured,
			'bookingRequired'=> false,
			'ctaLabel'       => sanitize_text_field( $cta_label ),
			'ctaUrl'         => '' !== $cta_url ? esc_url_raw( $cta_url ) : null,
		);
	}
}
