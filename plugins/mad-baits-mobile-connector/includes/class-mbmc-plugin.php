<?php
/**
 * Main plugin bootstrap.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Plugin
 */
class MBMC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MBMC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * CPT handler.
	 *
	 * @var MBMC_CPT_Events
	 */
	public $cpt_events;

	/**
	 * REST events handler.
	 *
	 * @var MBMC_REST_Events
	 */
	public $rest_events;

	/**
	 * REST devices handler.
	 *
	 * @var MBMC_REST_Devices
	 */
	public $rest_devices;

	/**
	 * REST feedback handler.
	 *
	 * @var MBMC_REST_Feedback
	 */
	public $rest_feedback;

	/**
	 * REST team handler.
	 *
	 * @var MBMC_REST_Team
	 */
	public $rest_team;

	/**
	 * REST fisheries handler.
	 *
	 * @var MBMC_REST_Fisheries
	 */
	public $rest_fisheries;

	/**
	 * REST community catches handler.
	 *
	 * @var MBMC_REST_Catches
	 */
	public $rest_catches;

	/**
	 * REST auth handler.
	 *
	 * @var MBMC_REST_Auth
	 */
	public $rest_auth;

	/**
	 * REST account handler.
	 *
	 * @var MBMC_REST_Account
	 */
	public $rest_account;

	/**
	 * REST notifications handler.
	 *
	 * @var MBMC_REST_Notifications
	 */
	public $rest_notifications;

	/**
	 * REST AI handler.
	 *
	 * @var MBMC_REST_AI
	 */
	public $rest_ai;

	/**
	 * REST loyalty handler.
	 *
	 * @var MBMC_REST_Loyalty
	 */
	public $rest_loyalty;

	/**
	 * Admin loyalty handler.
	 *
	 * @var MBMC_Admin_Loyalty
	 */
	public $admin_loyalty;

	/**
	 * REST diary handler.
	 *
	 * @var MBMC_REST_Diary
	 */
	public $rest_diary;

	/**
	 * REST achievements handler.
	 *
	 * @var MBMC_REST_Achievements
	 */
	public $rest_achievements;

	/**
	 * Admin handler.
	 *
	 * @var MBMC_Admin
	 */
	public $admin;

	/**
	 * Get singleton.
	 *
	 * @return MBMC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->cpt_events    = new MBMC_CPT_Events();
		$this->rest_events   = new MBMC_REST_Events();
		$this->rest_devices  = new MBMC_REST_Devices();
		$this->rest_feedback = new MBMC_REST_Feedback();
		$this->rest_team     = new MBMC_REST_Team();
		$this->rest_diary    = new MBMC_REST_Diary();
		$this->rest_achievements = new MBMC_REST_Achievements();
		$this->rest_fisheries = new MBMC_REST_Fisheries();
		$this->rest_catches   = new MBMC_REST_Catches();
		$this->rest_auth     = new MBMC_REST_Auth();
		$this->rest_account       = new MBMC_REST_Account();
		$this->rest_notifications = new MBMC_REST_Notifications();
		$this->rest_ai            = new MBMC_REST_AI();
		$this->rest_loyalty       = new MBMC_REST_Loyalty();
		$this->admin_loyalty      = new MBMC_Admin_Loyalty();
		$this->admin              = new MBMC_Admin();

		add_action( 'init', array( $this->cpt_events, 'register_post_type' ) );
		add_action( 'init', array( $this->cpt_events, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this->cpt_events, 'register_meta_boxes' ) );
		add_action( 'save_post_madbaits_event', array( $this->cpt_events, 'save_event_meta' ), 10, 2 );
		add_filter( 'manage_madbaits_event_posts_columns', array( $this->cpt_events, 'admin_columns' ) );
		add_action( 'manage_madbaits_event_posts_custom_column', array( $this->cpt_events, 'admin_column_content' ), 10, 2 );

		add_action( 'rest_api_init', array( $this->rest_events, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_devices, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_feedback, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_team, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_diary, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_achievements, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_fisheries, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_catches, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_auth, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_account, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_notifications, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_ai, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->rest_loyalty, 'register_routes' ) );

		$this->admin->register_hooks();
		$this->admin_loyalty->register_hooks();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_db_upgrade_notice' ) );

		MBMC_WooCommerce::init();
	}

	/**
	 * Warn admins when schema is behind the plugin (e.g. after rsync deploy without activation).
	 *
	 * @return void
	 */
	public function maybe_show_db_upgrade_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$installed = get_option( 'mbmc_db_version', '' );
		if ( MBMC_DB::DB_VERSION === $installed ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Mad Baits Mobile Connector:', 'mad-baits-mobile-connector' ),
			esc_html(
				sprintf(
					/* translators: 1: installed schema version, 2: required schema version */
					__( 'Database schema update required (installed %1$s, needs %2$s). Tables will upgrade automatically on this page load.', 'mad-baits-mobile-connector' ),
					$installed ? $installed : __( 'none', 'mad-baits-mobile-connector' ),
					MBMC_DB::DB_VERSION
				)
			)
		);
	}

	/**
	 * Load admin styles on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post_type;

		$plugin_pages = array(
			'toplevel_page_mbmc-mobile-app',
			'mobile-app_page_mbmc-devices',
			'mobile-app_page_mbmc-feedback',
			'mobile-app_page_mbmc-team-catch',
			'mobile-app_page_mbmc-fisheries',
			'mobile-app_page_mbmc-community-catches',
			'mobile-app_page_mbmc-loyalty',
			'mobile-app_page_mbmc-settings',
		);

		$is_event_screen = in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true )
			&& 'madbaits_event' === $post_type;

		if ( ! in_array( $hook, $plugin_pages, true ) && ! $is_event_screen ) {
			return;
		}

		wp_enqueue_style(
			'mbmc-admin',
			MBMC_PLUGIN_URL . 'assets/admin.css',
			array(),
			MBMC_VERSION
		);
	}
}
