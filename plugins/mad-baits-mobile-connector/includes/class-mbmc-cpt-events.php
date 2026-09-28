<?php
/**
 * Mad Baits Events custom post type and admin meta.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_CPT_Events
 */
class MBMC_CPT_Events {

	/**
	 * Register custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Mad Baits Events', 'mad-baits-mobile-connector' ),
			'singular_name'         => __( 'Event', 'mad-baits-mobile-connector' ),
			'menu_name'             => __( 'Mad Baits Events', 'mad-baits-mobile-connector' ),
			'name_admin_bar'        => __( 'Event', 'mad-baits-mobile-connector' ),
			'add_new'               => __( 'Add New', 'mad-baits-mobile-connector' ),
			'add_new_item'          => __( 'Add New Event', 'mad-baits-mobile-connector' ),
			'new_item'              => __( 'New Event', 'mad-baits-mobile-connector' ),
			'edit_item'             => __( 'Edit Event', 'mad-baits-mobile-connector' ),
			'view_item'             => __( 'View Event', 'mad-baits-mobile-connector' ),
			'all_items'             => __( 'All Events', 'mad-baits-mobile-connector' ),
			'search_items'          => __( 'Search Events', 'mad-baits-mobile-connector' ),
			'not_found'             => __( 'No events found.', 'mad-baits-mobile-connector' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'mad-baits-mobile-connector' ),
			'featured_image'        => __( 'Event Image', 'mad-baits-mobile-connector' ),
			'set_featured_image'    => __( 'Set event image', 'mad-baits-mobile-connector' ),
			'remove_featured_image' => __( 'Remove event image', 'mad-baits-mobile-connector' ),
			'use_featured_image'    => __( 'Use as event image', 'mad-baits-mobile-connector' ),
		);

		$can_manage_events = mbmc_user_can_manage_mobile_app();

		register_post_type(
			'madbaits_event',
			array(
				'labels'              => $labels,
				'public'              => true,
				'show_ui'             => $can_manage_events,
				'show_in_menu'        => $can_manage_events ? 'mbmc-mobile-app' : false,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'events' ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'exclude_from_search' => false,
			)
		);
	}

	/**
	 * Register post meta for REST visibility.
	 *
	 * @return void
	 */
	public function register_meta() {
		$string_meta = array(
			'event_start_date',
			'event_end_date',
			'event_location',
			'event_type',
			'event_image_url',
			'event_cta_label',
			'event_cta_url',
		);

		foreach ( $string_meta as $key ) {
			register_post_meta(
				'madbaits_event',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}

		register_post_meta(
			'madbaits_event',
			'event_is_featured',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
			)
		);
	}

	/**
	 * Register admin meta box.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'mbmc_event_details',
			__( 'Event Details', 'mad-baits-mobile-connector' ),
			array( $this, 'render_meta_box' ),
			'madbaits_event',
			'normal',
			'high'
		);
	}

	/**
	 * Render event details meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'mbmc_save_event_meta', 'mbmc_event_meta_nonce' );

		$start_date   = get_post_meta( $post->ID, 'event_start_date', true );
		$end_date     = get_post_meta( $post->ID, 'event_end_date', true );
		$location     = get_post_meta( $post->ID, 'event_location', true );
		$event_type   = get_post_meta( $post->ID, 'event_type', true );
		$image_url    = get_post_meta( $post->ID, 'event_image_url', true );
		$cta_label    = get_post_meta( $post->ID, 'event_cta_label', true );
		$cta_url      = get_post_meta( $post->ID, 'event_cta_url', true );
		$is_featured  = (bool) get_post_meta( $post->ID, 'event_is_featured', true );
		$event_types  = mbmc_get_event_types();
		$start_local  = $this->datetime_to_local_input( $start_date );
		$end_local    = $this->datetime_to_local_input( $end_date );

		if ( '' === $event_type ) {
			$event_type = 'bbq';
		}

		if ( '' === $cta_label ) {
			$cta_label = __( 'View Event', 'mad-baits-mobile-connector' );
		}
		?>
		<div class="mbmc-meta-grid">
			<p class="mbmc-field">
				<label for="mbmc_event_start_date"><strong><?php esc_html_e( 'Start date & time', 'mad-baits-mobile-connector' ); ?></strong></label>
				<input type="datetime-local" id="mbmc_event_start_date" name="event_start_date" value="<?php echo esc_attr( $start_local ); ?>" />
				<span class="description"><?php esc_html_e( 'Leave empty if the date is not yet confirmed (app will show TBC).', 'mad-baits-mobile-connector' ); ?></span>
			</p>

			<p class="mbmc-field">
				<label for="mbmc_event_end_date"><strong><?php esc_html_e( 'End date & time', 'mad-baits-mobile-connector' ); ?></strong></label>
				<input type="datetime-local" id="mbmc_event_end_date" name="event_end_date" value="<?php echo esc_attr( $end_local ); ?>" />
			</p>

			<p class="mbmc-field">
				<label for="mbmc_event_location"><strong><?php esc_html_e( 'Location', 'mad-baits-mobile-connector' ); ?></strong></label>
				<input type="text" class="widefat" id="mbmc_event_location" name="event_location" value="<?php echo esc_attr( $location ); ?>" placeholder="<?php esc_attr_e( 'e.g. Mad Baits HQ', 'mad-baits-mobile-connector' ); ?>" />
			</p>

			<p class="mbmc-field">
				<label for="mbmc_event_type"><strong><?php esc_html_e( 'Event type', 'mad-baits-mobile-connector' ); ?></strong></label>
				<select id="mbmc_event_type" name="event_type">
					<?php foreach ( $event_types as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $event_type, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="mbmc-field">
				<label for="mbmc_event_image_url"><strong><?php esc_html_e( 'Image URL (optional)', 'mad-baits-mobile-connector' ); ?></strong></label>
				<input type="url" class="widefat" id="mbmc_event_image_url" name="event_image_url" value="<?php echo esc_url( $image_url ); ?>" placeholder="https://madbaits.com/wp-content/uploads/..." />
				<span class="description"><?php esc_html_e( 'Overrides featured image in the mobile API if set. Featured image is used when empty.', 'mad-baits-mobile-connector' ); ?></span>
			</p>

			<p class="mbmc-field">
				<label for="mbmc_event_cta_label"><strong><?php esc_html_e( 'CTA label', 'mad-baits-mobile-connector' ); ?></strong></label>
				<input type="text" class="widefat" id="mbmc_event_cta_label" name="event_cta_label" value="<?php echo esc_attr( $cta_label ); ?>" />
			</p>

			<p class="mbmc-field">
				<label for="mbmc_event_cta_url"><strong><?php esc_html_e( 'CTA URL', 'mad-baits-mobile-connector' ); ?></strong></label>
				<input type="url" class="widefat" id="mbmc_event_cta_url" name="event_cta_url" value="<?php echo esc_url( $cta_url ); ?>" placeholder="https://madbaits.com/events/..." />
				<span class="description"><?php esc_html_e( 'Defaults to the event permalink if empty.', 'mad-baits-mobile-connector' ); ?></span>
			</p>

			<p class="mbmc-field mbmc-field-checkbox">
				<label for="mbmc_event_is_featured">
					<input type="checkbox" id="mbmc_event_is_featured" name="event_is_featured" value="1" <?php checked( $is_featured ); ?> />
					<?php esc_html_e( 'Featured event (shown on mobile Home)', 'mad-baits-mobile-connector' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Save event meta securely.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_event_meta( $post_id, $post ) {
		unset( $post );

		if ( ! isset( $_POST['mbmc_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mbmc_event_meta_nonce'] ) ), 'mbmc_save_event_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$start = isset( $_POST['event_start_date'] ) ? mbmc_sanitize_datetime( wp_unslash( $_POST['event_start_date'] ) ) : '';
		$end   = isset( $_POST['event_end_date'] ) ? mbmc_sanitize_datetime( wp_unslash( $_POST['event_end_date'] ) ) : '';

		update_post_meta( $post_id, 'event_start_date', $start );
		update_post_meta( $post_id, 'event_end_date', $end );

		$location = isset( $_POST['event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['event_location'] ) ) : '';
		update_post_meta( $post_id, 'event_location', $location );

		$allowed_types = array_keys( mbmc_get_event_types() );
		$event_type    = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : 'app_event';

		if ( ! in_array( $event_type, $allowed_types, true ) ) {
			$event_type = 'app_event';
		}

		update_post_meta( $post_id, 'event_type', $event_type );

		$image_url = isset( $_POST['event_image_url'] ) ? mbmc_sanitize_url_field( wp_unslash( $_POST['event_image_url'] ) ) : '';
		update_post_meta( $post_id, 'event_image_url', $image_url );

		$cta_label = isset( $_POST['event_cta_label'] ) ? sanitize_text_field( wp_unslash( $_POST['event_cta_label'] ) ) : '';
		update_post_meta( $post_id, 'event_cta_label', $cta_label );

		$cta_url = isset( $_POST['event_cta_url'] ) ? mbmc_sanitize_url_field( wp_unslash( $_POST['event_cta_url'] ) ) : '';
		update_post_meta( $post_id, 'event_cta_url', $cta_url );

		$is_featured = isset( $_POST['event_is_featured'] ) && '1' === $_POST['event_is_featured'];
		update_post_meta( $post_id, 'event_is_featured', $is_featured ? 1 : 0 );
	}

	/**
	 * Admin list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function admin_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['mbmc_event_type'] = __( 'Type', 'mad-baits-mobile-connector' );
				$new['mbmc_event_date'] = __( 'Start', 'mad-baits-mobile-connector' );
				$new['mbmc_featured']   = __( 'Featured', 'mad-baits-mobile-connector' );
			}
		}

		return $new;
	}

	/**
	 * Render custom admin column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function admin_column_content( $column, $post_id ) {
		if ( 'mbmc_event_type' === $column ) {
			$slug = get_post_meta( $post_id, 'event_type', true );
			echo esc_html( mbmc_format_event_type_for_api( $slug ) );
			return;
		}

		if ( 'mbmc_event_date' === $column ) {
			$start = get_post_meta( $post_id, 'event_start_date', true );

			if ( '' === $start ) {
				echo esc_html__( 'TBC', 'mad-baits-mobile-connector' );
				return;
			}

			echo esc_html( wp_date( 'j M Y H:i', strtotime( $start ) ) );
			return;
		}

		if ( 'mbmc_featured' === $column ) {
			$featured = (bool) get_post_meta( $post_id, 'event_is_featured', true );
			echo $featured ? '★' : '—';
		}
	}

	/**
	 * Convert stored datetime to HTML datetime-local value.
	 *
	 * @param string $stored Stored datetime.
	 * @return string
	 */
	private function datetime_to_local_input( $stored ) {
		if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
			return '';
		}

		$timestamp = strtotime( $stored );

		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( 'Y-m-d\TH:i', $timestamp );
	}
}
