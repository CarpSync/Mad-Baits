<?php
/**
 * Event admin meta boxes.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Admin meta UI for events.
 */
class Mad_Events_Meta {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
		add_action('save_post_mad_event', array(__CLASS__, 'save_event_meta'), 10, 2);
		add_filter('manage_mad_event_posts_columns', array(__CLASS__, 'columns'));
		add_action('manage_mad_event_posts_custom_column', array(__CLASS__, 'column_content'), 10, 2);
	}

	/**
	 * Register meta boxes.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box('mad_event_details', __('Event Details', 'mad-baits'), array(__CLASS__, 'render_details_box'), 'mad_event', 'normal', 'high');
		add_meta_box('mad_event_location', __('Location', 'mad-baits'), array(__CLASS__, 'render_location_box'), 'mad_event', 'normal', 'default');
		add_meta_box('mad_event_engagement', __('Engagement & Commerce', 'mad-baits'), array(__CLASS__, 'render_engagement_box'), 'mad_event', 'normal', 'default');
		add_meta_box('mad_event_rsvps', __('RSVPs', 'mad-baits'), array(__CLASS__, 'render_rsvp_box'), 'mad_event', 'side', 'default');
	}

	/**
	 * Get meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field Field key.
	 * @return string
	 */
	private static function get($post_id, $field) {
		$keys = Mad_Events_Data::meta_keys();
		$key  = isset($keys[ $field ]) ? $keys[ $field ] : '';
		return $key ? (string) get_post_meta($post_id, $key, true) : '';
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_details_box($post) {
		wp_nonce_field('mad_event_meta_save', 'mad_event_meta_nonce');
		?>
		<table class="form-table mad-event-admin-table">
			<tr>
				<th><label for="mad_event_start_date"><?php esc_html_e('Start date', 'mad-baits'); ?></label></th>
				<td><input type="date" id="mad_event_start_date" name="mad_event_start_date" value="<?php echo esc_attr(self::get($post->ID, 'start_date')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_start_time"><?php esc_html_e('Start time', 'mad-baits'); ?></label></th>
				<td><input type="time" id="mad_event_start_time" name="mad_event_start_time" value="<?php echo esc_attr(self::get($post->ID, 'start_time')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_end_date"><?php esc_html_e('End date', 'mad-baits'); ?></label></th>
				<td><input type="date" id="mad_event_end_date" name="mad_event_end_date" value="<?php echo esc_attr(self::get($post->ID, 'end_date')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_end_time"><?php esc_html_e('End time', 'mad-baits'); ?></label></th>
				<td><input type="time" id="mad_event_end_time" name="mad_event_end_time" value="<?php echo esc_attr(self::get($post->ID, 'end_time')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_status"><?php esc_html_e('Status', 'mad-baits'); ?></label></th>
				<td>
					<select id="mad_event_status" name="mad_event_status">
						<?php foreach (array('upcoming', 'past', 'cancelled') as $status) : ?>
							<option value="<?php echo esc_attr($status); ?>" <?php selected(self::get($post->ID, 'status'), $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="mad_event_short_description"><?php esc_html_e('Short description', 'mad-baits'); ?></label></th>
				<td><textarea id="mad_event_short_description" name="mad_event_short_description" rows="3" class="large-text"><?php echo esc_textarea(self::get($post->ID, 'short_description')); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="mad_event_full_description"><?php esc_html_e('Full event details', 'mad-baits'); ?></label></th>
				<td><textarea id="mad_event_full_description" name="mad_event_full_description" rows="6" class="large-text"><?php echo esc_textarea(self::get($post->ID, 'full_description')); ?></textarea>
					<p class="description"><?php esc_html_e('Shown on the event page. Main editor content is also displayed.', 'mad-baits'); ?></p></td>
			</tr>
			<tr>
				<th><label for="mad_event_highlights"><?php esc_html_e('Highlights / tags', 'mad-baits'); ?></label></th>
				<td><textarea id="mad_event_highlights" name="mad_event_highlights" rows="4" class="large-text" placeholder="BBQ&#10;Free Food&#10;Bait Deals"><?php echo esc_textarea(self::get($post->ID, 'highlights')); ?></textarea>
					<p class="description"><?php esc_html_e('One highlight per line.', 'mad-baits'); ?></p></td>
			</tr>
			<tr>
				<th><label for="mad_event_gallery_ids"><?php esc_html_e('Gallery image IDs', 'mad-baits'); ?></label></th>
				<td><input type="text" class="large-text" id="mad_event_gallery_ids" name="mad_event_gallery_ids" value="<?php echo esc_attr(self::get($post->ID, 'gallery_ids')); ?>" placeholder="123,456" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_location_box($post) {
		?>
		<table class="form-table mad-event-admin-table">
			<tr>
				<th><label for="mad_event_location_name"><?php esc_html_e('Venue name', 'mad-baits'); ?></label></th>
				<td><input type="text" class="large-text" id="mad_event_location_name" name="mad_event_location_name" value="<?php echo esc_attr(self::get($post->ID, 'location_name')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_address"><?php esc_html_e('Address', 'mad-baits'); ?></label></th>
				<td><textarea id="mad_event_address" name="mad_event_address" rows="3" class="large-text"><?php echo esc_textarea(self::get($post->ID, 'address')); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="mad_event_map_link"><?php esc_html_e('Google Maps link', 'mad-baits'); ?></label></th>
				<td><input type="url" class="large-text" id="mad_event_map_link" name="mad_event_map_link" value="<?php echo esc_url(self::get($post->ID, 'map_link')); ?>" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_engagement_box($post) {
		?>
		<table class="form-table mad-event-admin-table">
			<tr>
				<th><?php esc_html_e('Featured event', 'mad-baits'); ?></th>
				<td><label><input type="checkbox" name="mad_event_featured" value="1" <?php checked(self::get($post->ID, 'featured'), '1'); ?> /> <?php esc_html_e('Show as featured on Events hub', 'mad-baits'); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e('RSVP enabled', 'mad-baits'); ?></th>
				<td><label><input type="checkbox" name="mad_event_rsvp_enabled" value="1" <?php checked(self::get($post->ID, 'rsvp_enabled'), '1'); ?> /> <?php esc_html_e('Allow register interest', 'mad-baits'); ?></label></td>
			</tr>
			<tr>
				<th><label for="mad_event_max_attendees"><?php esc_html_e('Max attendees', 'mad-baits'); ?></label></th>
				<td><input type="number" min="0" id="mad_event_max_attendees" name="mad_event_max_attendees" value="<?php echo esc_attr(self::get($post->ID, 'max_attendees')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_featured_products"><?php esc_html_e('Featured product IDs', 'mad-baits'); ?></label></th>
				<td><input type="text" class="large-text" id="mad_event_featured_products" name="mad_event_featured_products" value="<?php echo esc_attr(self::get($post->ID, 'featured_products')); ?>" placeholder="101,102,103" />
					<p class="description"><?php esc_html_e('Comma-separated WooCommerce product IDs.', 'mad-baits'); ?></p></td>
			</tr>
			<tr>
				<th><label for="mad_event_cta_label"><?php esc_html_e('CTA label', 'mad-baits'); ?></label></th>
				<td><input type="text" id="mad_event_cta_label" name="mad_event_cta_label" value="<?php echo esc_attr(self::get($post->ID, 'cta_label')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_cta_url"><?php esc_html_e('CTA URL', 'mad-baits'); ?></label></th>
				<td><input type="url" class="large-text" id="mad_event_cta_url" name="mad_event_cta_url" value="<?php echo esc_url(self::get($post->ID, 'cta_url')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="mad_event_push_message"><?php esc_html_e('Push notification message', 'mad-baits'); ?></label></th>
				<td><textarea id="mad_event_push_message" name="mad_event_push_message" rows="3" class="large-text"><?php echo esc_textarea(self::get($post->ID, 'push_message')); ?></textarea></td>
			</tr>
		</table>
		<?php
		Mad_Events_Push::render_admin_push_controls($post);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_rsvp_box($post) {
		$rsvps = Mad_Events_RSVP::get_for_event((int) $post->ID, 20);
		$count = Mad_Events_RSVP::count_for_event((int) $post->ID);
		echo '<p><strong>' . esc_html(sprintf(/* translators: %d RSVP count */ _n('%d RSVP', '%d RSVPs', $count, 'mad-baits'), $count)) . '</strong></p>';
		if (empty($rsvps)) {
			echo '<p>' . esc_html__('No RSVPs yet.', 'mad-baits') . '</p>';
			return;
		}
		echo '<ul class="mad-event-rsvp-admin-list">';
		foreach ($rsvps as $rsvp) {
			echo '<li><strong>' . esc_html($rsvp['name']) . '</strong><br />' . esc_html($rsvp['email']);
			if (! empty($rsvp['attendees'])) {
				echo '<br />' . esc_html(sprintf(__('Attending: %d', 'mad-baits'), (int) $rsvp['attendees']));
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Save event meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function save_event_meta($post_id, $post) {
		if (! isset($_POST['mad_event_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mad_event_meta_nonce'])), 'mad_event_meta_save')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$keys = Mad_Events_Data::meta_keys();
		$map  = array(
			'start_date'        => 'mad_event_start_date',
			'start_time'        => 'mad_event_start_time',
			'end_date'          => 'mad_event_end_date',
			'end_time'          => 'mad_event_end_time',
			'location_name'     => 'mad_event_location_name',
			'address'           => 'mad_event_address',
			'map_link'          => 'mad_event_map_link',
			'short_description' => 'mad_event_short_description',
			'full_description'  => 'mad_event_full_description',
			'gallery_ids'       => 'mad_event_gallery_ids',
			'cta_label'         => 'mad_event_cta_label',
			'cta_url'           => 'mad_event_cta_url',
			'max_attendees'     => 'mad_event_max_attendees',
			'featured_products' => 'mad_event_featured_products',
			'push_message'      => 'mad_event_push_message',
			'status'            => 'mad_event_status',
			'highlights'        => 'mad_event_highlights',
		);

		foreach ($map as $field => $input) {
			if (! isset($keys[ $field ])) {
				continue;
			}
			$value = isset($_POST[ $input ]) ? wp_unslash($_POST[ $input ]) : '';
			if (in_array($field, array('short_description', 'full_description', 'address', 'push_message', 'highlights'), true)) {
				$value = sanitize_textarea_field((string) $value);
			} elseif ('map_link' === $field || 'cta_url' === $field) {
				$value = esc_url_raw((string) $value);
			} elseif (in_array($field, array('start_date', 'end_date'), true)) {
				$value = Mad_Events_Data::sanitize_date((string) $value);
			} elseif (in_array($field, array('start_time', 'end_time'), true)) {
				$value = Mad_Events_Data::sanitize_time((string) $value);
			} elseif ('status' === $field) {
				$value = sanitize_key((string) $value);
			} else {
				$value = sanitize_text_field((string) $value);
			}
			update_post_meta($post_id, $keys[ $field ], $value);
		}

		update_post_meta($post_id, $keys['featured'], isset($_POST['mad_event_featured']) ? '1' : '0');
		update_post_meta($post_id, $keys['rsvp_enabled'], isset($_POST['mad_event_rsvp_enabled']) ? '1' : '0');
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns($columns) {
		$new = array();
		foreach ($columns as $key => $label) {
			$new[ $key ] = $label;
			if ('title' === $key) {
				$new['mad_event_date'] = __('Date', 'mad-baits');
				$new['mad_event_status'] = __('Status', 'mad-baits');
			}
		}
		return $new;
	}

	/**
	 * @param string $column Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function column_content($column, $post_id) {
		$event = Mad_Events_Data::get_event($post_id);
		if (! $event) {
			return;
		}
		if ('mad_event_date' === $column) {
			echo esc_html((string) $event['date_label']);
		}
		if ('mad_event_status' === $column) {
			echo esc_html(ucfirst((string) Mad_Events_Data::effective_status($event)));
		}
	}
}
