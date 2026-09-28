<?php
/**
 * WordPress admin pages for the Mobile Connector.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Admin
 */
class MBMC_Admin {

	/**
	 * Top-level menu slug.
	 */
	const MENU_SLUG = 'mbmc-mobile-app';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'block_unauthorized_admin_access' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_mbmc_send_test_push', array( $this, 'handle_admin_test_push' ) );
		add_action( 'admin_post_mbmc_moderate_fishery', array( $this, 'handle_admin_fishery_moderation' ) );
		add_action( 'admin_post_mbmc_seed_fisheries_directory', array( $this, 'handle_admin_seed_fisheries_directory' ) );
		add_action( 'admin_post_mbmc_moderate_community_catch', array( $this, 'handle_admin_community_catch_moderation' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_push_notice' ) );
	}

	/**
	 * Block direct URL access to Mobile App admin pages for unauthorized users.
	 *
	 * @return void
	 */
	public function block_unauthorized_admin_access() {
		if ( mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		global $pagenow;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$blocked_pages = array(
			self::MENU_SLUG,
			'mbmc-devices',
			'mbmc-feedback',
			'mbmc-team-catch',
			'mbmc-fisheries',
			'mbmc-community-catches',
			'mbmc-loyalty',
			'mbmc-settings',
		);

		if ( in_array( $page, $blocked_pages, true ) ) {
			wp_die( esc_html__( 'You do not have permission to access Mobile App admin.', 'mad-baits-mobile-connector' ), 403 );
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

		if ( 'madbaits_event' === $post_type && in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Mobile App events.', 'mad-baits-mobile-connector' ), 403 );
		}

		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

			if ( $post_id > 0 && 'madbaits_event' === get_post_type( $post_id ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Mobile App events.', 'mad-baits-mobile-connector' ), 403 );
			}
		}

		if ( isset( $_POST['option_page'] ) && 'mbmc_settings' === sanitize_key( wp_unslash( $_POST['option_page'] ) ) ) {
			wp_die( esc_html__( 'You do not have permission to change Mobile App settings.', 'mad-baits-mobile-connector' ), 403 );
		}
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menus() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		add_menu_page(
			__( 'Mobile App', 'mad-baits-mobile-connector' ),
			__( 'Mobile App', 'mad-baits-mobile-connector' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-smartphone',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Mobile App Dashboard', 'mad-baits-mobile-connector' ),
			__( 'Dashboard', 'mad-baits-mobile-connector' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Registered Devices', 'mad-baits-mobile-connector' ),
			__( 'Devices', 'mad-baits-mobile-connector' ),
			'manage_options',
			'mbmc-devices',
			array( $this, 'render_devices' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'App Feedback', 'mad-baits-mobile-connector' ),
			__( 'Feedback', 'mad-baits-mobile-connector' ),
			'manage_options',
			'mbmc-feedback',
			array( $this, 'render_feedback' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Team Catch Reports', 'mad-baits-mobile-connector' ),
			__( 'Team Catch Reports', 'mad-baits-mobile-connector' ),
			'manage_options',
			'mbmc-team-catch',
			array( $this, 'render_team_catch_reports' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Fisheries Moderation', 'mad-baits-mobile-connector' ),
			__( 'Fisheries', 'mad-baits-mobile-connector' ),
			'manage_options',
			'mbmc-fisheries',
			array( $this, 'render_fisheries' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Community Catches', 'mad-baits-mobile-connector' ),
			__( 'Community Catches', 'mad-baits-mobile-connector' ),
			'manage_options',
			'mbmc-community-catches',
			array( $this, 'render_community_catches' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Mad Baits Events', 'mad-baits-mobile-connector' ),
			__( 'Events', 'mad-baits-mobile-connector' ),
			'manage_options',
			'edit.php?post_type=madbaits_event'
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Mobile App Settings', 'mad-baits-mobile-connector' ),
			__( 'Settings', 'mad-baits-mobile-connector' ),
			'manage_options',
			'mbmc-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'mbmc_settings',
			mbmc_get_app_token_option_key(),
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_app_token' ),
				'default'           => '',
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_push_sending_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'mbmc_sanitize_checkbox_option',
				'default'           => false,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_push_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_push_provider_option',
				'default'           => 'expo',
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_push_test_mode_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'mbmc_sanitize_checkbox_option',
				'default'           => true,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_push_max_batch',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'mbmc_sanitize_push_max_batch_option',
				'default'           => 10,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_order_notifications_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'mbmc_sanitize_checkbox_option',
				'default'           => false,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_order_dispatched_notifications_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'mbmc_sanitize_checkbox_option',
				'default'           => false,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_order_tracking_notifications_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'mbmc_sanitize_checkbox_option',
				'default'           => false,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_order_require_push_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'mbmc_sanitize_checkbox_option',
				'default'           => true,
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_google_places_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_app_token' ),
				'default'           => '',
			)
		);

		register_setting(
			'mbmc_settings',
			'mbmc_openai_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_app_token' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize app token on save.
	 *
	 * @param string $value Raw token.
	 * @return string
	 */
	public function sanitize_app_token( $value ) {
		return substr( sanitize_text_field( (string) $value ), 0, 128 );
	}

	/**
	 * Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$device_count   = MBMC_DB::count_rows( MBMC_DB::devices_table() );
		$feedback_count = MBMC_DB::count_rows( MBMC_DB::feedback_table() );
		$team_count     = MBMC_DB::count_rows( MBMC_DB::team_catch_table() );
		$fisheries_pending = count( MBMC_DB::list_fisheries_for_moderation( false, 500 ) );
		$community_pending = count( MBMC_DB::list_community_catches_by_status( 'pending', 500 ) );
		$log_count      = MBMC_DB::count_rows( MBMC_DB::notification_logs_table() );
		$event_count    = wp_count_posts( 'madbaits_event' );
		$published      = isset( $event_count->publish ) ? (int) $event_count->publish : 0;
		$token_set      = mbmc_is_app_token_configured();
		$push_enabled   = mbmc_is_push_sending_enabled();
		$test_mode      = mbmc_is_push_test_mode_enabled();
		$order_enabled  = mbmc_is_order_notifications_enabled();

		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Mad Baits Mobile App Dashboard', 'mad-baits-mobile-connector' ); ?></h1>

			<?php $this->render_token_notice( $token_set ); ?>

			<div class="mbmc-stat-grid">
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $device_count ) ); ?></h2>
					<p><?php esc_html_e( 'Registered devices', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-devices' ) ); ?>"><?php esc_html_e( 'View devices', 'mad-baits-mobile-connector' ); ?></a>
				</div>
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $feedback_count ) ); ?></h2>
					<p><?php esc_html_e( 'Feedback submissions', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-feedback' ) ); ?>"><?php esc_html_e( 'View feedback', 'mad-baits-mobile-connector' ); ?></a>
				</div>
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $team_count ) ); ?></h2>
					<p><?php esc_html_e( 'Team catch reports', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-team-catch' ) ); ?>"><?php esc_html_e( 'View reports', 'mad-baits-mobile-connector' ); ?></a>
				</div>
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $fisheries_pending ) ); ?></h2>
					<p><?php esc_html_e( 'Pending fisheries', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-fisheries' ) ); ?>"><?php esc_html_e( 'Moderate fisheries', 'mad-baits-mobile-connector' ); ?></a>
				</div>
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $community_pending ) ); ?></h2>
					<p><?php esc_html_e( 'Pending community catches', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-community-catches' ) ); ?>"><?php esc_html_e( 'Moderate catches', 'mad-baits-mobile-connector' ); ?></a>
				</div>
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $published ) ); ?></h2>
					<p><?php esc_html_e( 'Published events', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=madbaits_event' ) ); ?>"><?php esc_html_e( 'Manage events', 'mad-baits-mobile-connector' ); ?></a>
				</div>
				<div class="mbmc-stat-card">
					<h2><?php echo esc_html( number_format_i18n( $log_count ) ); ?></h2>
					<p><?php esc_html_e( 'Notification logs', 'mad-baits-mobile-connector' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-settings#mbmc-push-logs' ) ); ?>"><?php esc_html_e( 'View logs', 'mad-baits-mobile-connector' ); ?></a>
				</div>
			</div>

			<h2><?php esc_html_e( 'Push sending', 'mad-baits-mobile-connector' ); ?></h2>
			<table class="widefat striped mbmc-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Push sending enabled', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<?php if ( $push_enabled ) : ?>
								<span class="mbmc-badge mbmc-badge-ok"><?php esc_html_e( 'On', 'mad-baits-mobile-connector' ); ?></span>
							<?php else : ?>
								<span class="mbmc-badge mbmc-badge-warn"><?php esc_html_e( 'Off (default)', 'mad-baits-mobile-connector' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test mode', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<?php if ( $test_mode ) : ?>
								<span class="mbmc-badge mbmc-badge-ok"><?php esc_html_e( 'On — test_* types only', 'mad-baits-mobile-connector' ); ?></span>
							<?php else : ?>
								<span class="mbmc-badge mbmc-badge-warn"><?php esc_html_e( 'Off', 'mad-baits-mobile-connector' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Provider', 'mad-baits-mobile-connector' ); ?></th>
						<td><code><?php echo esc_html( mbmc_get_push_provider() ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order notifications', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<?php if ( $order_enabled ) : ?>
								<span class="mbmc-badge mbmc-badge-ok"><?php esc_html_e( 'Enabled', 'mad-baits-mobile-connector' ); ?></span>
							<?php else : ?>
								<span class="mbmc-badge mbmc-badge-warn"><?php esc_html_e( 'Off (default)', 'mad-baits-mobile-connector' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mbmc-settings#mbmc-push-test' ) ); ?>" class="button">
					<?php esc_html_e( 'Send test notification', 'mad-baits-mobile-connector' ); ?>
				</a>
			</p>

			<h2><?php esc_html_e( 'API status', 'mad-baits-mobile-connector' ); ?></h2>
			<table class="widefat striped mbmc-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Endpoint group', 'mad-baits-mobile-connector' ); ?></th>
						<th><?php esc_html_e( 'Status', 'mad-baits-mobile-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>GET /madbaits/v1/events</code></td>
						<td><span class="mbmc-badge mbmc-badge-ok"><?php esc_html_e( 'Public (always on)', 'mad-baits-mobile-connector' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Device, feedback, team, auth, account, orders', 'mad-baits-mobile-connector' ); ?></td>
						<td>
							<?php if ( $token_set ) : ?>
								<span class="mbmc-badge mbmc-badge-ok"><?php esc_html_e( 'Enabled (token configured)', 'mad-baits-mobile-connector' ); ?></span>
							<?php else : ?>
								<span class="mbmc-badge mbmc-badge-warn"><?php esc_html_e( 'Disabled until App Token is set', 'mad-baits-mobile-connector' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Devices list page.
	 *
	 * @return void
	 */
	public function render_devices() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$rows = MBMC_DB::list_devices( 100 );

		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Registered Devices', 'mad-baits-mobile-connector' ); ?></h1>
			<?php $this->render_token_notice( mbmc_is_app_token_configured() ); ?>
			<?php $this->render_table(
				array(
					__( 'Device ID', 'mad-baits-mobile-connector' ),
					__( 'Platform', 'mad-baits-mobile-connector' ),
					__( 'App version', 'mad-baits-mobile-connector' ),
					__( 'Last seen', 'mad-baits-mobile-connector' ),
					__( 'Updated', 'mad-baits-mobile-connector' ),
				),
				$rows,
				function ( $row ) {
					return array(
						esc_html( $row->device_id ),
						esc_html( $row->platform ),
						esc_html( $row->app_version ? $row->app_version : '—' ),
						esc_html( $row->last_seen_at ? $row->last_seen_at : '—' ),
						esc_html( $row->updated_at ),
					);
				}
			); ?>
		</div>
		<?php
	}

	/**
	 * Feedback list page.
	 *
	 * @return void
	 */
	public function render_feedback() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$rows = MBMC_DB::list_feedback( 100 );

		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'App Feedback', 'mad-baits-mobile-connector' ); ?></h1>
			<?php $this->render_table(
				array(
					__( 'ID', 'mad-baits-mobile-connector' ),
					__( 'Type', 'mad-baits-mobile-connector' ),
					__( 'Title', 'mad-baits-mobile-connector' ),
					__( 'Message', 'mad-baits-mobile-connector' ),
					__( 'Priority', 'mad-baits-mobile-connector' ),
					__( 'Status', 'mad-baits-mobile-connector' ),
					__( 'Created', 'mad-baits-mobile-connector' ),
				),
				$rows,
				function ( $row ) {
					$message = wp_trim_words( wp_strip_all_tags( (string) $row->message ), 12, '…' );

					return array(
						esc_html( (string) $row->id ),
						esc_html( $row->feedback_type ),
						esc_html( $row->title ? $row->title : '—' ),
						esc_html( $message ),
						esc_html( $row->priority ),
						esc_html( $row->status ),
						esc_html( $row->created_at ),
					);
				}
			); ?>
		</div>
		<?php
	}

	/**
	 * Team catch reports list page.
	 *
	 * @return void
	 */
	public function render_team_catch_reports() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$rows = MBMC_DB::list_team_catch_reports( 100 );

		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Team Catch Reports', 'mad-baits-mobile-connector' ); ?></h1>
			<?php $this->render_table(
				array(
					__( 'ID', 'mad-baits-mobile-connector' ),
					__( 'Role', 'mad-baits-mobile-connector' ),
					__( 'Venue', 'mad-baits-mobile-connector' ),
					__( 'Bait', 'mad-baits-mobile-connector' ),
					__( 'Weight', 'mad-baits-mobile-connector' ),
					__( 'Permission', 'mad-baits-mobile-connector' ),
					__( 'Status', 'mad-baits-mobile-connector' ),
					__( 'Created', 'mad-baits-mobile-connector' ),
				),
				$rows,
				function ( $row ) {
					return array(
						esc_html( (string) $row->id ),
						esc_html( $row->team_role ),
						esc_html( $row->venue ),
						esc_html( $row->bait_used ),
						esc_html( $row->fish_weight ? $row->fish_weight : '—' ),
						esc_html( (int) $row->permission_to_use ? __( 'Yes', 'mad-baits-mobile-connector' ) : __( 'No', 'mad-baits-mobile-connector' ) ),
						esc_html( $row->status ),
						esc_html( $row->created_at ),
					);
				}
			); ?>
		</div>
		<?php
	}

	/**
	 * Fisheries moderation page.
	 *
	 * @return void
	 */
	public function render_fisheries() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$pending       = MBMC_DB::list_fisheries_for_moderation( false, 150 );
		$approved      = MBMC_DB::list_fisheries_for_moderation( true, 100 );
		$approved_total = MBMC_DB::count_fisheries( true );
		$seed_total    = MBMC_Fisheries_Seed::count_seed_records();
		$seeded_at     = get_option( MBMC_Fisheries_Seed::OPTION_IMPORTED_AT, '' );
		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Fisheries Moderation', 'mad-baits-mobile-connector' ); ?></h1>
			<p><?php esc_html_e( 'Approve or reject user-submitted fisheries from catches and Add Fishery flow.', 'mad-baits-mobile-connector' ); ?></p>

			<div class="mbmc-stat-card" style="max-width:720px;margin-bottom:20px;">
				<strong><?php echo esc_html( number_format_i18n( $approved_total ) ); ?></strong>
				<?php esc_html_e( ' approved fisheries in database', 'mad-baits-mobile-connector' ); ?>
				<?php if ( $seed_total > 0 ) : ?>
					<br />
					<?php
					printf(
						/* translators: 1: seed file count */
						esc_html__( 'Bundled directory seed file: %1$s venues ready to import.', 'mad-baits-mobile-connector' ),
						esc_html( number_format_i18n( $seed_total ) )
					);
					?>
				<?php endif; ?>
				<?php if ( $seeded_at ) : ?>
					<br />
					<?php
					printf(
						/* translators: %s: datetime */
						esc_html__( 'Last directory import: %s', 'mad-baits-mobile-connector' ),
						esc_html( $seeded_at )
					);
					?>
				<?php endif; ?>
				<?php if ( $seed_total > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
						<input type="hidden" name="action" value="mbmc_seed_fisheries_directory" />
						<?php wp_nonce_field( 'mbmc_seed_fisheries_directory' ); ?>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Import bundled fisheries directory', 'mad-baits-mobile-connector' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'Pending fisheries', 'mad-baits-mobile-connector' ); ?></h2>
			<?php if ( empty( $pending ) ) : ?>
				<p><?php esc_html_e( 'No pending fisheries.', 'mad-baits-mobile-connector' ); ?></p>
			<?php else : ?>
				<table class="widefat striped mbmc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Lake', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Postcode', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'County', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Created', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'mad-baits-mobile-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending as $fishery ) : ?>
							<tr>
								<td><?php echo esc_html( $fishery->fishery_name ); ?></td>
								<td><?php echo esc_html( $fishery->lake_name ?: '—' ); ?></td>
								<td><?php echo esc_html( $fishery->postcode ?: '—' ); ?></td>
								<td><?php echo esc_html( $fishery->county ?: '—' ); ?></td>
								<td><?php echo esc_html( $fishery->created_at ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
										<input type="hidden" name="action" value="mbmc_moderate_fishery" />
										<input type="hidden" name="fishery_id" value="<?php echo esc_attr( (string) $fishery->id ); ?>" />
										<input type="hidden" name="approved" value="1" />
										<?php wp_nonce_field( 'mbmc_moderate_fishery' ); ?>
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'mad-baits-mobile-connector' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-left:8px;">
										<input type="hidden" name="action" value="mbmc_moderate_fishery" />
										<input type="hidden" name="fishery_id" value="<?php echo esc_attr( (string) $fishery->id ); ?>" />
										<input type="hidden" name="approved" value="0" />
										<?php wp_nonce_field( 'mbmc_moderate_fishery' ); ?>
										<button type="submit" class="button"><?php esc_html_e( 'Reject', 'mad-baits-mobile-connector' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:24px;"><?php esc_html_e( 'Recently approved', 'mad-baits-mobile-connector' ); ?></h2>
			<?php
			$this->render_table(
				array(
					__( 'Name', 'mad-baits-mobile-connector' ),
					__( 'Lake', 'mad-baits-mobile-connector' ),
					__( 'Postcode', 'mad-baits-mobile-connector' ),
					__( 'County', 'mad-baits-mobile-connector' ),
					__( 'Updated', 'mad-baits-mobile-connector' ),
				),
				$approved,
				function ( $row ) {
					return array(
						esc_html( $row->fishery_name ),
						esc_html( $row->lake_name ?: '—' ),
						esc_html( $row->postcode ?: '—' ),
						esc_html( $row->county ?: '—' ),
						esc_html( $row->updated_at ),
					);
				}
			);
			?>
		</div>
		<?php
	}

	/**
	 * Settings page — Mad Baits App Token.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$push_enabled = mbmc_is_push_sending_enabled();
		$test_mode    = mbmc_is_push_test_mode_enabled();
		$provider     = mbmc_get_push_provider();
		$max_batch    = mbmc_get_push_max_batch();
		$order_enabled    = mbmc_is_order_notifications_enabled();
		$dispatched_enabled = mbmc_is_order_dispatched_notifications_enabled();
		$tracking_enabled   = mbmc_is_order_tracking_notifications_enabled();
		$require_push       = mbmc_is_order_require_push_enabled();
		$logs         = MBMC_DB::list_notification_logs( 15 );
		$google_places_key = (string) get_option( 'mbmc_google_places_api_key', '' );
		$openai_api_key    = (string) get_option( 'mbmc_openai_api_key', '' );

		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Mobile App Settings', 'mad-baits-mobile-connector' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'mbmc_settings' ); ?>

				<h2><?php esc_html_e( 'API security', 'mad-baits-mobile-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="mbmc_app_token"><?php esc_html_e( 'Mad Baits App Token', 'mad-baits-mobile-connector' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="mbmc_app_token"
								name="<?php echo esc_attr( mbmc_get_app_token_option_key() ); ?>"
								value="<?php echo esc_attr( mbmc_get_app_token() ); ?>"
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php esc_html_e( 'Required for all mobile POST/GET endpoints except GET /events. Sent as X-Madbaits-App-Token.', 'mad-baits-mobile-connector' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="mbmc_google_places_api_key"><?php esc_html_e( 'Google Places API Key', 'mad-baits-mobile-connector' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="mbmc_google_places_api_key"
								name="mbmc_google_places_api_key"
								value="<?php echo esc_attr( $google_places_key ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description"><?php esc_html_e( 'Optional fallback for fishery search when no local fishery matches.', 'mad-baits-mobile-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="mbmc_openai_api_key"><?php esc_html_e( 'OpenAI API Key (Ask Mark voice)', 'mad-baits-mobile-connector' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="mbmc_openai_api_key"
								name="mbmc_openai_api_key"
								value="<?php echo esc_attr( $openai_api_key ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description"><?php esc_html_e( 'Server-side only — powers /ai/speech-to-text and /ai/text-to-speech.', 'mad-baits-mobile-connector' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Push notifications', 'mad-baits-mobile-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Push Sending Enabled', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<input type="hidden" name="mbmc_push_sending_enabled" value="0" />
							<label for="mbmc_push_sending_enabled">
								<input
									type="checkbox"
									id="mbmc_push_sending_enabled"
									name="mbmc_push_sending_enabled"
									value="1"
									<?php checked( $push_enabled ); ?>
								/>
								<?php esc_html_e( 'Allow outbound push sends (off by default for production safety)', 'mad-baits-mobile-connector' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="mbmc_push_provider"><?php esc_html_e( 'Push Provider', 'mad-baits-mobile-connector' ); ?></label>
						</th>
						<td>
							<select id="mbmc_push_provider" name="mbmc_push_provider">
								<option value="expo" <?php selected( $provider, 'expo' ); ?>><?php esc_html_e( 'Expo Push API', 'mad-baits-mobile-connector' ); ?></option>
								<option value="firebase_future" <?php selected( $provider, 'firebase_future' ); ?>><?php esc_html_e( 'Firebase (future)', 'mad-baits-mobile-connector' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Mode Enabled', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<input type="hidden" name="mbmc_push_test_mode_enabled" value="0" />
							<label for="mbmc_push_test_mode_enabled">
								<input
									type="checkbox"
									id="mbmc_push_test_mode_enabled"
									name="mbmc_push_test_mode_enabled"
									value="1"
									<?php checked( $test_mode ); ?>
								/>
								<?php esc_html_e( 'Only allow notification types starting with test_ (recommended until go-live)', 'mad-baits-mobile-connector' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="mbmc_push_max_batch"><?php esc_html_e( 'Max Sends Per Batch', 'mad-baits-mobile-connector' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="mbmc_push_max_batch"
								name="mbmc_push_max_batch"
								value="<?php echo esc_attr( (string) $max_batch ); ?>"
								min="1"
								max="100"
								class="small-text"
							/>
							<p class="description"><?php esc_html_e( 'Limits devices notified per order event.', 'mad-baits-mobile-connector' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Order notifications', 'mad-baits-mobile-connector' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'WooCommerce order status hooks send push notifications to registered customer devices. All defaults are off until tested on staging.', 'mad-baits-mobile-connector' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable order notifications', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<input type="hidden" name="mbmc_order_notifications_enabled" value="0" />
							<label for="mbmc_order_notifications_enabled">
								<input
									type="checkbox"
									id="mbmc_order_notifications_enabled"
									name="mbmc_order_notifications_enabled"
									value="1"
									<?php checked( $order_enabled ); ?>
								/>
								<?php esc_html_e( 'Listen to WooCommerce order status changes', 'mad-baits-mobile-connector' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable dispatched notifications', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<input type="hidden" name="mbmc_order_dispatched_notifications_enabled" value="0" />
							<label for="mbmc_order_dispatched_notifications_enabled">
								<input
									type="checkbox"
									id="mbmc_order_dispatched_notifications_enabled"
									name="mbmc_order_dispatched_notifications_enabled"
									value="1"
									<?php checked( $dispatched_enabled ); ?>
								/>
								<?php esc_html_e( 'Send order_dispatched for dispatched / wc-dispatched statuses', 'mad-baits-mobile-connector' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable tracking notifications', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<input type="hidden" name="mbmc_order_tracking_notifications_enabled" value="0" />
							<label for="mbmc_order_tracking_notifications_enabled">
								<input
									type="checkbox"
									id="mbmc_order_tracking_notifications_enabled"
									name="mbmc_order_tracking_notifications_enabled"
									value="1"
									<?php checked( $tracking_enabled ); ?>
								/>
								<?php esc_html_e( 'Send tracking_added when common tracking meta is saved', 'mad-baits-mobile-connector' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require push sending enabled', 'mad-baits-mobile-connector' ); ?></th>
						<td>
							<input type="hidden" name="mbmc_order_require_push_enabled" value="0" />
							<label for="mbmc_order_require_push_enabled">
								<input
									type="checkbox"
									id="mbmc_order_require_push_enabled"
									name="mbmc_order_require_push_enabled"
									value="1"
									<?php checked( $require_push ); ?>
								/>
								<?php esc_html_e( 'Order notifications only send when Push Sending Enabled is on (recommended)', 'mad-baits-mobile-connector' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2 id="mbmc-push-test"><?php esc_html_e( 'Send test notification', 'mad-baits-mobile-connector' ); ?></h2>
			<?php if ( ! $push_enabled ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Push sending is disabled. Enable “Push Sending Enabled” above before sending tests.', 'mad-baits-mobile-connector' ); ?></p>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mbmc-test-push-form">
				<input type="hidden" name="action" value="mbmc_send_test_push" />
				<?php wp_nonce_field( 'mbmc_send_test_push' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mbmc_test_push_token"><?php esc_html_e( 'Push token', 'mad-baits-mobile-connector' ); ?></label></th>
						<td>
							<input type="text" id="mbmc_test_push_token" name="mbmc_test_push_token" class="large-text" placeholder="ExponentPushToken[...]" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mbmc_test_title"><?php esc_html_e( 'Title', 'mad-baits-mobile-connector' ); ?></label></th>
						<td><input type="text" id="mbmc_test_title" name="mbmc_test_title" class="regular-text" value="<?php echo esc_attr__( 'Mad Baits test', 'mad-baits-mobile-connector' ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="mbmc_test_message"><?php esc_html_e( 'Message', 'mad-baits-mobile-connector' ); ?></label></th>
						<td><textarea id="mbmc_test_message" name="mbmc_test_message" class="large-text" rows="3" required><?php echo esc_textarea( __( 'This is a test push from WordPress admin.', 'mad-baits-mobile-connector' ) ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Send test notification', 'mad-baits-mobile-connector' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2 id="mbmc-push-logs"><?php esc_html_e( 'Recent notification logs', 'mad-baits-mobile-connector' ); ?></h2>
			<?php
			$this->render_table(
				array(
					__( 'ID', 'mad-baits-mobile-connector' ),
					__( 'Type', 'mad-baits-mobile-connector' ),
					__( 'Title', 'mad-baits-mobile-connector' ),
					__( 'Status', 'mad-baits-mobile-connector' ),
					__( 'Provider', 'mad-baits-mobile-connector' ),
					__( 'Created', 'mad-baits-mobile-connector' ),
				),
				$logs,
				function ( $row ) {
					return array(
						esc_html( (string) $row->id ),
						esc_html( $row->notification_type ),
						esc_html( $row->title ),
						esc_html( $row->status ),
						esc_html( $row->provider ),
						esc_html( $row->created_at ),
					);
				}
			);
			?>

			<h2><?php esc_html_e( 'Header', 'mad-baits-mobile-connector' ); ?></h2>
			<pre class="mbmc-code">X-Madbaits-App-Token: &lt;your-token&gt;</pre>
		</div>
		<?php
	}

	/**
	 * Handle fisheries moderation action from admin.
	 *
	 * @return void
	 */
	public function handle_admin_fishery_moderation() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mad-baits-mobile-connector' ) );
		}

		check_admin_referer( 'mbmc_moderate_fishery' );

		$fishery_id = isset( $_POST['fishery_id'] ) ? absint( $_POST['fishery_id'] ) : 0;
		$approved   = isset( $_POST['approved'] ) ? rest_sanitize_boolean( $_POST['approved'] ) : false;

		if ( $fishery_id > 0 ) {
			MBMC_DB::set_fishery_approval( $fishery_id, $approved, $approved ? '' : 'Rejected by moderator' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mbmc-fisheries' ) );
		exit;
	}

	/**
	 * Import bundled fisheries directory JSON into the database.
	 *
	 * @return void
	 */
	public function handle_admin_seed_fisheries_directory() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mad-baits-mobile-connector' ) );
		}

		check_admin_referer( 'mbmc_seed_fisheries_directory' );

		$result = MBMC_Fisheries_Seed::import_directory();
		$notice = isset( $result['message'] ) ? (string) $result['message'] : __( 'Fisheries import finished.', 'mad-baits-mobile-connector' );

		set_transient(
			'mbmc_admin_push_notice',
			array(
				'type'    => ! empty( $result['ok'] ) ? 'success' : 'error',
				'message' => $notice,
			),
			30
		);

		wp_safe_redirect( admin_url( 'admin.php?page=mbmc-fisheries' ) );
		exit;
	}

	/**
	 * Community catches moderation page.
	 *
	 * @return void
	 */
	public function render_community_catches() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$pending   = MBMC_DB::list_community_catches_by_status( 'pending', 150 );
		$published = MBMC_DB::list_community_catches_by_status( 'published', 100 );
		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Community Catches', 'mad-baits-mobile-connector' ); ?></h1>
			<p><?php esc_html_e( 'Approve angler catches before they appear on the home screen for all app users.', 'mad-baits-mobile-connector' ); ?></p>
			<h2><?php esc_html_e( 'Pending catches', 'mad-baits-mobile-connector' ); ?></h2>
			<?php if ( empty( $pending ) ) : ?>
				<p><?php esc_html_e( 'No pending community catches.', 'mad-baits-mobile-connector' ); ?></p>
			<?php else : ?>
				<table class="widefat striped mbmc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Angler', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Species', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Weight', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Venue', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Bait', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Photo', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Submitted', 'mad-baits-mobile-connector' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'mad-baits-mobile-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending as $catch_row ) : ?>
							<tr>
								<td><?php echo esc_html( $catch_row->angler_name ); ?></td>
								<td><?php echo esc_html( $catch_row->species ); ?></td>
								<td><?php echo esc_html( $catch_row->weight_display ?: '—' ); ?></td>
								<td><?php echo esc_html( $catch_row->venue ); ?></td>
								<td><?php echo esc_html( $catch_row->bait_used ?: '—' ); ?></td>
								<td>
									<?php if ( ! empty( $catch_row->photo_url ) ) : ?>
										<a href="<?php echo esc_url( $catch_row->photo_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'mad-baits-mobile-connector' ); ?></a>
									<?php else : ?>
										<?php esc_html_e( '—', 'mad-baits-mobile-connector' ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $catch_row->created_at ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
										<input type="hidden" name="action" value="mbmc_moderate_community_catch" />
										<input type="hidden" name="catch_id" value="<?php echo esc_attr( (string) $catch_row->id ); ?>" />
										<input type="hidden" name="approved" value="1" />
										<label style="margin-right:8px;"><input type="checkbox" name="featured" value="1" /> <?php esc_html_e( 'Featured', 'mad-baits-mobile-connector' ); ?></label>
										<?php wp_nonce_field( 'mbmc_moderate_community_catch' ); ?>
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'mad-baits-mobile-connector' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-left:8px;">
										<input type="hidden" name="action" value="mbmc_moderate_community_catch" />
										<input type="hidden" name="catch_id" value="<?php echo esc_attr( (string) $catch_row->id ); ?>" />
										<input type="hidden" name="approved" value="0" />
										<?php wp_nonce_field( 'mbmc_moderate_community_catch' ); ?>
										<button type="submit" class="button"><?php esc_html_e( 'Reject', 'mad-baits-mobile-connector' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:24px;"><?php esc_html_e( 'Recently published', 'mad-baits-mobile-connector' ); ?></h2>
			<?php
			$this->render_table(
				array(
					__( 'Angler', 'mad-baits-mobile-connector' ),
					__( 'Species', 'mad-baits-mobile-connector' ),
					__( 'Weight', 'mad-baits-mobile-connector' ),
					__( 'Venue', 'mad-baits-mobile-connector' ),
					__( 'Published', 'mad-baits-mobile-connector' ),
				),
				$published,
				function ( $row ) {
					return array(
						esc_html( $row->angler_name ),
						esc_html( $row->species ),
						esc_html( $row->weight_display ?: '—' ),
						esc_html( $row->venue ),
						esc_html( $row->published_at ?: $row->updated_at ),
					);
				}
			);
			?>
		</div>
		<?php
	}

	/**
	 * Handle community catch moderation action from admin.
	 *
	 * @return void
	 */
	public function handle_admin_community_catch_moderation() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mad-baits-mobile-connector' ) );
		}

		check_admin_referer( 'mbmc_moderate_community_catch' );

		$catch_id = isset( $_POST['catch_id'] ) ? absint( $_POST['catch_id'] ) : 0;
		$approved = isset( $_POST['approved'] ) ? rest_sanitize_boolean( $_POST['approved'] ) : false;

		if ( $catch_id > 0 ) {
			if ( $approved ) {
				$featured = isset( $_POST['featured'] ) && rest_sanitize_boolean( $_POST['featured'] );
				MBMC_DB::set_community_catch_status( $catch_id, 'published' );
				MBMC_DB::set_community_catch_featured( $catch_id, $featured );
				$row = MBMC_DB::get_community_catch_by_id( $catch_id );
				if ( $row && ! empty( $row->user_id ) ) {
					MBMC_Loyalty::award_catch_report( (int) $row->user_id, $catch_id, $featured );
				}
			} else {
				$row = MBMC_DB::get_community_catch_by_id( $catch_id );
				if ( $row && ! empty( $row->user_id ) ) {
					MBMC_Loyalty::revoke_catch_report_points( (int) $row->user_id, $catch_id );
				}
				MBMC_DB::set_community_catch_status( $catch_id, 'rejected', 'Rejected by moderator' );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mbmc-community-catches' ) );
		exit;
	}

	/**
	 * Handle admin test push form submission.
	 *
	 * @return void
	 */
	public function handle_admin_test_push() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mad-baits-mobile-connector' ) );
		}

		check_admin_referer( 'mbmc_send_test_push' );

		$result = MBMC_Notification_Service::send_test_notification(
			array(
				'pushToken' => isset( $_POST['mbmc_test_push_token'] ) ? sanitize_text_field( wp_unslash( $_POST['mbmc_test_push_token'] ) ) : '',
				'type'      => 'test_admin',
				'title'     => isset( $_POST['mbmc_test_title'] ) ? sanitize_text_field( wp_unslash( $_POST['mbmc_test_title'] ) ) : '',
				'message'   => isset( $_POST['mbmc_test_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mbmc_test_message'] ) ) : '',
			)
		);

		set_transient( 'mbmc_admin_push_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=mbmc-settings&mbmc_push_result=1#mbmc-push-test' ) );
		exit;
	}

	/**
	 * Show result notice after admin test push.
	 *
	 * @return void
	 */
	public function render_admin_push_notice() {
		if ( ! isset( $_GET['mbmc_push_result'] ) || ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$result = get_transient( 'mbmc_admin_push_result_' . get_current_user_id() );
		delete_transient( 'mbmc_admin_push_result_' . get_current_user_id() );

		if ( ! is_array( $result ) ) {
			return;
		}

		$class   = ! empty( $result['success'] ) ? 'notice-success' : 'notice-warning';
		$message = isset( $result['message'] ) ? (string) $result['message'] : '';

		printf(
			'<div class="notice %1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
			esc_attr( $class ),
			esc_html__( 'Test push:', 'mad-baits-mobile-connector' ),
			esc_html( $message )
		);
	}

	/**
	 * Render token configuration notice.
	 *
	 * @param bool $token_set Whether token is configured.
	 * @return void
	 */
	private function render_token_notice( $token_set ) {
		if ( $token_set ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: settings page URL */
					wp_kses_post( __( '<strong>Mad Baits App Token</strong> is not configured. Protected API endpoints are disabled until you set a token under <a href="%s">Settings</a>. GET /events remains public.', 'mad-baits-mobile-connector' ) ),
					esc_url( admin_url( 'admin.php?page=mbmc-settings' ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a simple admin table.
	 *
	 * @param string[]             $headers  Column headers.
	 * @param array<int, object>   $rows     Data rows.
	 * @param callable             $mapper   Maps row to cell strings.
	 * @return void
	 */
	private function render_table( array $headers, array $rows, callable $mapper ) {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No records yet.', 'mad-baits-mobile-connector' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped mbmc-table"><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $mapper( $row ) as $cell ) {
				echo '<td>' . $cell . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
