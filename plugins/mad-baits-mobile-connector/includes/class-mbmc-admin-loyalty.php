<?php
/**
 * Loyalty & Rewards admin — points, rewards, achievements, referrals, rules, campaigns.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Admin_Loyalty
 */
class MBMC_Admin_Loyalty {

	const PAGE_SLUG = 'mbmc-loyalty';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_post_mbmc_loyalty_action', array( $this, 'handle_post' ) );
	}

	/**
	 * Register submenu under Mobile App.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		add_submenu_page(
			MBMC_Admin::MENU_SLUG,
			__( 'Loyalty & Rewards', 'mad-baits-mobile-connector' ),
			__( 'Loyalty & Rewards', 'mad-baits-mobile-connector' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Current tab slug.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$allowed = array( 'dashboard', 'points', 'rewards', 'achievements', 'referrals', 'rules', 'campaigns', 'catches' );
		return in_array( $tab, $allowed, true ) ? $tab : 'dashboard';
	}

	/**
	 * Tab URL helper.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function tab_url( $tab ) {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab );
	}

	/**
	 * Handle admin POST actions.
	 *
	 * @return void
	 */
	public function handle_post() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mad-baits-mobile-connector' ) );
		}

		check_admin_referer( 'mbmc_loyalty_action' );
		$action = isset( $_POST['mbmc_loyalty_action'] ) ? sanitize_key( wp_unslash( $_POST['mbmc_loyalty_action'] ) ) : '';
		$tab    = isset( $_POST['redirect_tab'] ) ? sanitize_key( wp_unslash( $_POST['redirect_tab'] ) ) : 'dashboard';

		switch ( $action ) {
			case 'adjust_points':
				$user_id = absint( $_POST['user_id'] ?? 0 );
				$points  = (int) ( $_POST['points'] ?? 0 );
				$notes   = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
				if ( $user_id > 0 && 0 !== $points ) {
					MBMC_Loyalty::admin_adjust_points( $user_id, $points, $notes );
				}
				break;
			case 'reset_points':
				$user_id = absint( $_POST['user_id'] ?? 0 );
				$notes   = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
				if ( $user_id > 0 ) {
					MBMC_Loyalty::admin_reset_user_points( $user_id, $notes );
				}
				break;
			case 'save_rules':
				$values = array();
				foreach ( array_keys( MBMC_Loyalty_Config::default_rules() ) as $key ) {
					if ( isset( $_POST[ 'rule_' . $key ] ) ) {
						$values[ $key ] = wp_unslash( $_POST[ 'rule_' . $key ] );
					}
				}
				foreach ( array( 'exclude_sale', 'exclude_bundles', 'exclude_deals', 'exclude_tackle', 'cannot_combine_coupons' ) as $bool_key ) {
					$values[ $bool_key ] = isset( $_POST[ 'rule_' . $bool_key ] ) ? 1 : 0;
				}
				MBMC_Loyalty_Config::save_rules( $values );
				break;
			case 'save_reward':
				$id = absint( $_POST['reward_id'] ?? 0 );
				$data = array(
					'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
					'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
					'image_url'    => esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) ),
					'points_cost'  => absint( $_POST['points_cost'] ?? 0 ),
					'stock_qty'    => '' === ( $_POST['stock_qty'] ?? '' ) ? null : absint( $_POST['stock_qty'] ),
					'category'     => sanitize_key( wp_unslash( $_POST['category'] ?? 'general' ) ),
					'tier_minimum' => sanitize_key( wp_unslash( $_POST['tier_minimum'] ?? '' ) ),
					'is_active'    => isset( $_POST['is_active'] ) ? 1 : 0,
					'starts_at'    => sanitize_text_field( wp_unslash( $_POST['starts_at'] ?? '' ) ),
					'ends_at'      => sanitize_text_field( wp_unslash( $_POST['ends_at'] ?? '' ) ),
					'sort_order'   => absint( $_POST['sort_order'] ?? 0 ),
				);
				if ( $id > 0 ) {
					MBMC_DB::update_loyalty_reward( $id, $data );
				} else {
					$data['slug'] = sanitize_title( wp_unslash( $_POST['slug'] ?? $data['title'] ) );
					MBMC_DB::insert_loyalty_reward( $data );
				}
				$tab = 'rewards';
				break;
			case 'delete_reward':
				MBMC_DB::delete_loyalty_reward( absint( $_POST['reward_id'] ?? 0 ) );
				$tab = 'rewards';
				break;
			case 'save_achievement':
				$id = absint( $_POST['achievement_id'] ?? 0 );
				$data = array(
					'title'          => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
					'description'    => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
					'category'       => sanitize_key( wp_unslash( $_POST['category'] ?? 'loyalty' ) ),
					'icon'           => sanitize_text_field( wp_unslash( $_POST['icon'] ?? 'ribbon-outline' ) ),
					'icon_url'       => esc_url_raw( wp_unslash( $_POST['icon_url'] ?? '' ) ),
					'criteria_type'  => sanitize_key( wp_unslash( $_POST['criteria_type'] ?? 'manual' ) ),
					'criteria_value' => absint( $_POST['criteria_value'] ?? 1 ),
					'bonus_points'   => absint( $_POST['bonus_points'] ?? 0 ),
					'is_active'      => isset( $_POST['is_active'] ) ? 1 : 0,
					'is_visible'     => isset( $_POST['is_visible'] ) ? 1 : 0,
					'sort_order'     => absint( $_POST['sort_order'] ?? 0 ),
				);
				if ( $id > 0 ) {
					MBMC_DB::update_loyalty_achievement( $id, $data );
				} else {
					$data['slug'] = sanitize_title( wp_unslash( $_POST['slug'] ?? $data['title'] ) );
					MBMC_DB::insert_loyalty_achievement( $data );
				}
				$tab = 'achievements';
				break;
			case 'delete_achievement':
				MBMC_DB::delete_loyalty_achievement( absint( $_POST['achievement_id'] ?? 0 ) );
				$tab = 'achievements';
				break;
			case 'referral_approve':
				$ref_id = absint( $_POST['referral_id'] ?? 0 );
				$override = isset( $_POST['override_points'] ) && '' !== $_POST['override_points']
					? (int) $_POST['override_points']
					: null;
				$rows = MBMC_DB::list_loyalty_referrals( '', 500 );
				foreach ( $rows as $row ) {
					if ( (int) $row->id !== $ref_id ) {
						continue;
					}
					$points = MBMC_Loyalty::award_referral(
						(int) $row->referrer_user_id,
						(int) $row->referred_user_id,
						(int) $row->order_id,
						$override
					);
					MBMC_DB::update_loyalty_referral(
						$ref_id,
						array(
							'status'         => 'approved',
							'points_awarded' => $override ?? MBMC_Loyalty_Config::get_int( 'referral_points' ),
						)
					);
					break;
				}
				$tab = 'referrals';
				break;
			case 'referral_reject':
				MBMC_DB::update_loyalty_referral(
					absint( $_POST['referral_id'] ?? 0 ),
					array( 'status' => 'rejected' )
				);
				$tab = 'referrals';
				break;
			case 'save_campaign':
				$campaign_id = MBMC_DB::insert_loyalty_campaign(
					array(
						'title'         => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
						'campaign_type' => sanitize_key( wp_unslash( $_POST['campaign_type'] ?? 'bonus_all' ) ),
						'points_amount' => absint( $_POST['points_amount'] ?? 0 ),
						'multiplier'    => (float) ( $_POST['multiplier'] ?? 1 ),
						'user_ids'      => array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['user_ids'] ?? '' ) ) ) ) ),
						'message'       => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
						'status'        => 'active',
						'starts_at'     => sanitize_text_field( wp_unslash( $_POST['starts_at'] ?? '' ) ),
						'ends_at'       => sanitize_text_field( wp_unslash( $_POST['ends_at'] ?? '' ) ),
					)
				);
				if ( $campaign_id && isset( $_POST['run_now'] ) ) {
					$campaign = (object) array_merge(
						(array) MBMC_DB::list_loyalty_campaigns( 1 )[0] ?? array(),
						array( 'id' => $campaign_id )
					);
					MBMC_Loyalty::run_campaign( $campaign );
				}
				$tab = 'campaigns';
				break;
			case 'moderate_catch':
				$catch_id = absint( $_POST['catch_id'] ?? 0 );
				$approved = isset( $_POST['approved'] );
				$featured = isset( $_POST['featured'] );
				if ( $catch_id > 0 ) {
					if ( $approved ) {
						MBMC_DB::set_community_catch_status( $catch_id, 'published' );
						MBMC_DB::set_community_catch_featured( $catch_id, $featured );
						$row = MBMC_DB::get_community_catch_by_id( $catch_id );
						if ( $row && ! empty( $row->user_id ) ) {
							MBMC_Loyalty::award_catch_report( (int) $row->user_id, $catch_id, $featured );
						if ( class_exists( 'MBMC_Achievements' ) ) {
							MBMC_Achievements::evaluate_for_user( (int) $row->user_id, 'catch_approved', array( 'catchId' => $catch_id ) );
						}
						}
					} else {
						$row = MBMC_DB::get_community_catch_by_id( $catch_id );
						if ( $row && ! empty( $row->user_id ) ) {
							MBMC_Loyalty::revoke_catch_report_points( (int) $row->user_id, $catch_id );
						}
						MBMC_DB::set_community_catch_status( $catch_id, 'rejected', sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'Rejected' ) ) );
					}
				}
				$tab = 'catches';
				break;
			case 'unpublish_catch':
				$catch_id = absint( $_POST['catch_id'] ?? 0 );
				$row      = MBMC_DB::get_community_catch_by_id( $catch_id );
				if ( $row && ! empty( $row->user_id ) ) {
					MBMC_Loyalty::revoke_catch_report_points( (int) $row->user_id, $catch_id );
				}
				MBMC_DB::set_community_catch_status( $catch_id, 'rejected', 'Removed by moderator' );
				$tab = 'catches';
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab . '&updated=1' ) );
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap mbmc-admin-wrap">
			<h1><?php esc_html_e( 'Loyalty & Rewards', 'mad-baits-mobile-connector' ); ?></h1>
			<p><?php esc_html_e( 'Manage points, rewards, achievements, referrals and loyalty rules. All values are stored in the database and served to the app via API.', 'mad-baits-mobile-connector' ); ?></p>
			<?php if ( ! empty( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'mad-baits-mobile-connector' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'dashboard'    => __( 'Analytics', 'mad-baits-mobile-connector' ),
					'points'       => __( 'Points', 'mad-baits-mobile-connector' ),
					'rewards'      => __( 'Rewards', 'mad-baits-mobile-connector' ),
					'achievements' => __( 'Achievements', 'mad-baits-mobile-connector' ),
					'referrals'    => __( 'Referrals', 'mad-baits-mobile-connector' ),
					'rules'        => __( 'Rules', 'mad-baits-mobile-connector' ),
					'campaigns'    => __( 'Campaigns', 'mad-baits-mobile-connector' ),
					'catches'      => __( 'Catch reports', 'mad-baits-mobile-connector' ),
				);
				foreach ( $tabs as $slug => $label ) {
					$active = $tab === $slug ? ' nav-tab-active' : '';
					echo '<a href="' . esc_url( $this->tab_url( $slug ) ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
				}
				?>
			</h2>

			<?php
			switch ( $tab ) {
				case 'points':
					$this->render_points_tab();
					break;
				case 'rewards':
					$this->render_rewards_tab();
					break;
				case 'achievements':
					$this->render_achievements_tab();
					break;
				case 'referrals':
					$this->render_referrals_tab();
					break;
				case 'rules':
					$this->render_rules_tab();
					break;
				case 'campaigns':
					$this->render_campaigns_tab();
					break;
				case 'catches':
					$this->render_catches_tab();
					break;
				default:
					$this->render_dashboard_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Analytics dashboard tab.
	 *
	 * @return void
	 */
	private function render_dashboard_tab() {
		$stats = MBMC_DB::get_loyalty_analytics();
		$top   = MBMC_DB::list_loyalty_user_balances( 10 );
		?>
		<div class="mbmc-stat-grid" style="margin-top:16px;">
			<div class="mbmc-stat-card"><strong><?php echo esc_html( number_format_i18n( $stats['issued'] ) ); ?></strong><br /><?php esc_html_e( 'Points issued', 'mad-baits-mobile-connector' ); ?></div>
			<div class="mbmc-stat-card"><strong><?php echo esc_html( number_format_i18n( $stats['redeemed'] ) ); ?></strong><br /><?php esc_html_e( 'Points redeemed', 'mad-baits-mobile-connector' ); ?></div>
			<div class="mbmc-stat-card"><strong><?php echo esc_html( number_format_i18n( $stats['expired'] ) ); ?></strong><br /><?php esc_html_e( 'Points expired', 'mad-baits-mobile-connector' ); ?></div>
			<div class="mbmc-stat-card"><strong><?php echo esc_html( number_format_i18n( $stats['liability'] ) ); ?></strong><br /><?php esc_html_e( 'Active liability', 'mad-baits-mobile-connector' ); ?></div>
			<div class="mbmc-stat-card"><strong><?php echo esc_html( number_format_i18n( $stats['catch_reports'] ) ); ?></strong><br /><?php esc_html_e( 'Catch reports', 'mad-baits-mobile-connector' ); ?></div>
			<div class="mbmc-stat-card"><strong><?php echo esc_html( number_format_i18n( $stats['referral_conversions'] ) ); ?></strong><br /><?php esc_html_e( 'Referral conversions', 'mad-baits-mobile-connector' ); ?></div>
		</div>
		<h2><?php esc_html_e( 'Most active members', 'mad-baits-mobile-connector' ); ?></h2>
		<table class="widefat striped mbmc-table">
			<thead><tr><th><?php esc_html_e( 'User', 'mad-baits-mobile-connector' ); ?></th><th><?php esc_html_e( 'Balance', 'mad-baits-mobile-connector' ); ?></th><th><?php esc_html_e( 'Lifetime', 'mad-baits-mobile-connector' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $top as $row ) : $user = get_user_by( 'id', (int) $row->user_id ); ?>
				<tr>
					<td><?php echo esc_html( $user ? $user->display_name . ' (' . $user->user_email . ')' : '#' . $row->user_id ); ?></td>
					<td><?php echo esc_html( (string) $row->balance ); ?></td>
					<td><?php echo esc_html( (string) $row->lifetime ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Points management tab.
	 *
	 * @return void
	 */
	private function render_points_tab() {
		$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		?>
		<form method="get" style="margin:16px 0;">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<input type="hidden" name="tab" value="points" />
			<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email or user ID', 'mad-baits-mobile-connector' ); ?>" />
			<button class="button"><?php esc_html_e( 'Search', 'mad-baits-mobile-connector' ); ?></button>
		</form>
		<?php
		if ( $search ) {
			$users = MBMC_DB::search_loyalty_users( $search );
			?>
			<table class="widefat striped mbmc-table"><thead><tr><th>User</th><th>Balance</th><th></th></tr></thead><tbody>
			<?php foreach ( $users as $user ) : ?>
				<tr>
					<td><?php echo esc_html( $user->display_name . ' · ' . $user->user_email . ' · #' . $user->ID ); ?></td>
					<td><?php echo esc_html( (string) MBMC_Loyalty::get_available_balance( (int) $user->ID ) ); ?></td>
					<td><a class="button" href="<?php echo esc_url( $this->tab_url( 'points' ) . '&user_id=' . (int) $user->ID ); ?>"><?php esc_html_e( 'Manage', 'mad-baits-mobile-connector' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php
		}

		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$balance = MBMC_Loyalty::get_available_balance( $user_id );
				$history = MBMC_DB::list_loyalty_transactions( $user_id, 100 );
				?>
				<h2><?php echo esc_html( sprintf( __( 'Points for %s', 'mad-baits-mobile-connector' ), $user->display_name ) ); ?></h2>
				<p><strong><?php esc_html_e( 'Available:', 'mad-baits-mobile-connector' ); ?></strong> <?php echo esc_html( (string) $balance ); ?>
				· <strong><?php esc_html_e( 'Pending:', 'mad-baits-mobile-connector' ); ?></strong> <?php echo esc_html( (string) MBMC_DB::get_loyalty_pending_points( $user_id ) ); ?>
				· <strong><?php esc_html_e( 'Lifetime:', 'mad-baits-mobile-connector' ); ?></strong> <?php echo esc_html( (string) MBMC_DB::get_loyalty_lifetime_earned( $user_id ) ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
					<input type="hidden" name="action" value="mbmc_loyalty_action" />
					<input type="hidden" name="mbmc_loyalty_action" value="adjust_points" />
					<input type="hidden" name="redirect_tab" value="points" />
					<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
					<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
					<input type="number" name="points" placeholder="<?php esc_attr_e( 'Points (+/-)', 'mad-baits-mobile-connector' ); ?>" required />
					<input type="text" name="notes" placeholder="<?php esc_attr_e( 'Notes', 'mad-baits-mobile-connector' ); ?>" style="width:240px;" />
					<button class="button button-primary"><?php esc_html_e( 'Adjust points', 'mad-baits-mobile-connector' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:24px;" onsubmit="return confirm('Reset all points for this user?');">
					<input type="hidden" name="action" value="mbmc_loyalty_action" />
					<input type="hidden" name="mbmc_loyalty_action" value="reset_points" />
					<input type="hidden" name="redirect_tab" value="points" />
					<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
					<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
					<input type="text" name="notes" placeholder="<?php esc_attr_e( 'Reset reason', 'mad-baits-mobile-connector' ); ?>" style="width:240px;" />
					<button class="button"><?php esc_html_e( 'Reset balance', 'mad-baits-mobile-connector' ); ?></button>
				</form>

				<table class="widefat striped mbmc-table">
					<thead><tr><th>Date</th><th>Title</th><th>Points</th><th>Status</th><th>Expires</th><th>Source</th></tr></thead>
					<tbody>
					<?php foreach ( $history as $tx ) : ?>
						<tr>
							<td><?php echo esc_html( $tx->created_at ); ?></td>
							<td><?php echo esc_html( $tx->title ); ?></td>
							<td><?php echo esc_html( (string) $tx->points ); ?></td>
							<td><span class="mbmc-badge"><?php echo esc_html( $tx->status ); ?></span></td>
							<td><?php echo esc_html( $tx->expires_at ?: '—' ); ?></td>
							<td><?php echo esc_html( $tx->source . ( $tx->source_id ? ':' . $tx->source_id : '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			}
		}
	}

	/**
	 * Rewards CRUD tab.
	 *
	 * @return void
	 */
	private function render_rewards_tab() {
		$edit_id = isset( $_GET['edit_reward'] ) ? absint( $_GET['edit_reward'] ) : 0;
		$edit    = $edit_id ? MBMC_DB::get_loyalty_reward( $edit_id ) : null;
		$rewards = MBMC_DB::list_loyalty_rewards( false );
		?>
		<h2><?php echo $edit ? esc_html__( 'Edit reward', 'mad-baits-mobile-connector' ) : esc_html__( 'Create reward', 'mad-baits-mobile-connector' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;margin-bottom:24px;">
			<input type="hidden" name="action" value="mbmc_loyalty_action" />
			<input type="hidden" name="mbmc_loyalty_action" value="save_reward" />
			<input type="hidden" name="redirect_tab" value="rewards" />
			<input type="hidden" name="reward_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
			<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
			<table class="form-table"><tbody>
				<?php if ( ! $edit ) : ?><tr><th>Slug</th><td><input name="slug" class="regular-text" /></td></tr><?php endif; ?>
				<tr><th>Title</th><td><input name="title" class="regular-text" value="<?php echo esc_attr( $edit->title ?? '' ); ?>" required /></td></tr>
				<tr><th>Description</th><td><textarea name="description" rows="3" class="large-text"><?php echo esc_textarea( $edit->description ?? '' ); ?></textarea></td></tr>
				<tr><th>Image URL</th><td><input name="image_url" class="regular-text" value="<?php echo esc_attr( $edit->image_url ?? '' ); ?>" /></td></tr>
				<tr><th>Points cost</th><td><input name="points_cost" type="number" value="<?php echo esc_attr( (string) ( $edit->points_cost ?? 0 ) ); ?>" /></td></tr>
				<tr><th>Stock qty</th><td><input name="stock_qty" type="number" value="<?php echo esc_attr( isset( $edit->stock_qty ) ? (string) $edit->stock_qty : '' ); ?>" placeholder="Unlimited" /></td></tr>
				<tr><th>Category</th><td><input name="category" value="<?php echo esc_attr( $edit->category ?? 'general' ); ?>" /></td></tr>
				<tr><th>Tier minimum</th><td><input name="tier_minimum" value="<?php echo esc_attr( $edit->tier_minimum ?? '' ); ?>" placeholder="bronze|silver|gold|team_mad_baits" /></td></tr>
				<tr><th>Sort order</th><td><input name="sort_order" type="number" value="<?php echo esc_attr( (string) ( $edit->sort_order ?? 0 ) ); ?>" /></td></tr>
				<tr><th>Starts / Ends</th><td><input name="starts_at" type="datetime-local" value="<?php echo esc_attr( $edit->starts_at ?? '' ); ?>" /> — <input name="ends_at" type="datetime-local" value="<?php echo esc_attr( $edit->ends_at ?? '' ); ?>" /></td></tr>
				<tr><th>Active</th><td><label><input type="checkbox" name="is_active" <?php checked( ! $edit || ! empty( $edit->is_active ) ); ?> /> Active</label></td></tr>
			</tbody></table>
			<?php submit_button( $edit ? 'Update reward' : 'Create reward' ); ?>
		</form>
		<table class="widefat striped mbmc-table">
			<thead><tr><th>Title</th><th>Cost</th><th>Category</th><th>Active</th><th>Actions</th></tr></thead>
			<tbody>
			<?php foreach ( $rewards as $reward ) : ?>
				<tr>
					<td><?php echo esc_html( $reward->title ); ?></td>
					<td><?php echo esc_html( (string) $reward->points_cost ); ?></td>
					<td><?php echo esc_html( $reward->category ); ?></td>
					<td><?php echo ! empty( $reward->is_active ) ? 'Yes' : 'No'; ?></td>
					<td>
						<a href="<?php echo esc_url( $this->tab_url( 'rewards' ) . '&edit_reward=' . (int) $reward->id ); ?>">Edit</a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Delete?');">
							<input type="hidden" name="action" value="mbmc_loyalty_action" />
							<input type="hidden" name="mbmc_loyalty_action" value="delete_reward" />
							<input type="hidden" name="redirect_tab" value="rewards" />
							<input type="hidden" name="reward_id" value="<?php echo esc_attr( (string) $reward->id ); ?>" />
							<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
							<button class="button-link-delete">Delete</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Achievements CRUD tab.
	 *
	 * @return void
	 */
	private function render_achievements_tab() {
		$edit_id = isset( $_GET['edit_achievement'] ) ? absint( $_GET['edit_achievement'] ) : 0;
		$edit    = $edit_id ? MBMC_DB::get_loyalty_achievement( $edit_id ) : null;
		$items   = MBMC_DB::list_loyalty_achievements( false );
		?>
		<h2><?php echo $edit ? esc_html__( 'Edit achievement', 'mad-baits-mobile-connector' ) : esc_html__( 'Create achievement', 'mad-baits-mobile-connector' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;margin-bottom:24px;">
			<input type="hidden" name="action" value="mbmc_loyalty_action" />
			<input type="hidden" name="mbmc_loyalty_action" value="save_achievement" />
			<input type="hidden" name="redirect_tab" value="achievements" />
			<input type="hidden" name="achievement_id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
			<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
			<table class="form-table"><tbody>
				<?php if ( ! $edit ) : ?><tr><th>Slug</th><td><input name="slug" class="regular-text" /></td></tr><?php endif; ?>
				<tr><th>Title</th><td><input name="title" class="regular-text" value="<?php echo esc_attr( $edit->title ?? '' ); ?>" required /></td></tr>
				<tr><th>Description</th><td><textarea name="description" rows="3" class="large-text"><?php echo esc_textarea( $edit->description ?? '' ); ?></textarea></td></tr>
				<tr><th>Category</th><td><input name="category" value="<?php echo esc_attr( $edit->category ?? 'loyalty' ); ?>" /></td></tr>
				<tr><th>Icon</th><td><input name="icon" value="<?php echo esc_attr( $edit->icon ?? 'ribbon-outline' ); ?>" /></td></tr>
				<tr><th>Icon URL</th><td><input name="icon_url" class="regular-text" value="<?php echo esc_attr( $edit->icon_url ?? '' ); ?>" /></td></tr>
				<tr><th>Criteria type</th><td><input name="criteria_type" value="<?php echo esc_attr( $edit->criteria_type ?? 'manual' ); ?>" placeholder="app_orders, max_weight_lb, approved_catch_reports..." /></td></tr>
				<tr><th>Criteria value</th><td><input name="criteria_value" type="number" value="<?php echo esc_attr( (string) ( $edit->criteria_value ?? 1 ) ); ?>" /></td></tr>
				<tr><th>Bonus points</th><td><input name="bonus_points" type="number" value="<?php echo esc_attr( (string) ( $edit->bonus_points ?? 0 ) ); ?>" /></td></tr>
				<tr><th>Sort</th><td><input name="sort_order" type="number" value="<?php echo esc_attr( (string) ( $edit->sort_order ?? 0 ) ); ?>" /></td></tr>
				<tr><th>Flags</th><td><label><input type="checkbox" name="is_active" <?php checked( ! $edit || ! empty( $edit->is_active ) ); ?> /> Active</label> &nbsp; <label><input type="checkbox" name="is_visible" <?php checked( ! $edit || ! empty( $edit->is_visible ) ); ?> /> Visible in app</label></td></tr>
			</tbody></table>
			<?php submit_button( $edit ? 'Update achievement' : 'Create achievement' ); ?>
		</form>
		<table class="widefat striped mbmc-table">
			<thead><tr><th>Title</th><th>Criteria</th><th>Points</th><th>Active</th><th>Actions</th></tr></thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item->title ); ?></td>
					<td><?php echo esc_html( $item->criteria_type . ' ≥ ' . $item->criteria_value ); ?></td>
					<td><?php echo esc_html( (string) $item->bonus_points ); ?></td>
					<td><?php echo ! empty( $item->is_active ) ? 'Yes' : 'No'; ?></td>
					<td><a href="<?php echo esc_url( $this->tab_url( 'achievements' ) . '&edit_achievement=' . (int) $item->id ); ?>">Edit</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Referrals tab.
	 *
	 * @return void
	 */
	private function render_referrals_tab() {
		$rows = MBMC_DB::list_loyalty_referrals( '', 200 );
		?>
		<table class="widefat striped mbmc-table">
			<thead><tr><th>Referrer</th><th>Referred</th><th>Status</th><th>Order</th><th>Points</th><th>Actions</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) :
				$ref = get_user_by( 'id', (int) $row->referrer_user_id );
				$rec = $row->referred_user_id ? get_user_by( 'id', (int) $row->referred_user_id ) : null;
				?>
				<tr>
					<td><?php echo esc_html( $ref ? $ref->display_name : '#' . $row->referrer_user_id ); ?></td>
					<td><?php echo esc_html( $rec ? $rec->display_name : ( $row->referred_email ?: '—' ) ); ?></td>
					<td><?php echo esc_html( $row->status ); ?></td>
					<td><?php echo esc_html( $row->order_id ? (string) $row->order_id : '—' ); ?></td>
					<td><?php echo esc_html( (string) $row->points_awarded ); ?></td>
					<td>
						<?php if ( 'pending' === $row->status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="mbmc_loyalty_action" />
							<input type="hidden" name="mbmc_loyalty_action" value="referral_approve" />
							<input type="hidden" name="redirect_tab" value="referrals" />
							<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $row->id ); ?>" />
							<input type="number" name="override_points" placeholder="Override pts" style="width:90px;" />
							<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
							<button class="button button-primary">Approve</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="mbmc_loyalty_action" />
							<input type="hidden" name="mbmc_loyalty_action" value="referral_reject" />
							<input type="hidden" name="redirect_tab" value="referrals" />
							<input type="hidden" name="referral_id" value="<?php echo esc_attr( (string) $row->id ); ?>" />
							<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
							<button class="button">Reject</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Rules tab.
	 *
	 * @return void
	 */
	private function render_rules_tab() {
		$rules = MBMC_Loyalty_Config::get_all_rules();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:820px;margin-top:16px;">
			<input type="hidden" name="action" value="mbmc_loyalty_action" />
			<input type="hidden" name="mbmc_loyalty_action" value="save_rules" />
			<input type="hidden" name="redirect_tab" value="rules" />
			<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
			<h2><?php esc_html_e( 'Earning rates', 'mad-baits-mobile-connector' ); ?></h2>
			<table class="form-table"><tbody>
				<?php
				$numeric = array( 'purchase_per_gbp', 'catch_report_points', 'featured_catch_points', 'review_points', 'referral_points', 'referral_min_order_gbp', 'expiry_months', 'min_redemption_gbp', 'max_redemption_percent', 'checkout_points_per_gbp' );
				foreach ( $numeric as $key ) :
					?>
					<tr><th><?php echo esc_html( str_replace( '_', ' ', $key ) ); ?></th><td><input name="rule_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $rules[ $key ] ); ?>" /></td></tr>
				<?php endforeach; ?>
				<tr><th>eligible_categories (JSON)</th><td><textarea name="rule_eligible_categories" rows="2" class="large-text"><?php echo esc_textarea( (string) $rules['eligible_categories'] ); ?></textarea></td></tr>
				<tr><th>excluded_categories (JSON)</th><td><textarea name="rule_excluded_categories" rows="2" class="large-text"><?php echo esc_textarea( (string) $rules['excluded_categories'] ); ?></textarea></td></tr>
				<?php foreach ( array( 'exclude_sale', 'exclude_bundles', 'exclude_deals', 'exclude_tackle', 'cannot_combine_coupons' ) as $bool_key ) : ?>
					<tr><th><?php echo esc_html( $bool_key ); ?></th><td><label><input type="checkbox" name="rule_<?php echo esc_attr( $bool_key ); ?>" <?php checked( ! empty( $rules[ $bool_key ] ) ); ?> /> Enabled</label></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<h2><?php esc_html_e( 'Customer-facing copy', 'mad-baits-mobile-connector' ); ?></h2>
			<table class="form-table"><tbody>
				<?php foreach ( array( 'customer_intro', 'restrictions_summary', 'validity_note', 'no_cash_value_note' ) as $text_key ) : ?>
					<tr><th><?php echo esc_html( $text_key ); ?></th><td><textarea name="rule_<?php echo esc_attr( $text_key ); ?>" rows="2" class="large-text"><?php echo esc_textarea( (string) $rules[ $text_key ] ); ?></textarea></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<?php submit_button( 'Save rules' ); ?>
		</form>
		<?php
	}

	/**
	 * Campaigns tab.
	 *
	 * @return void
	 */
	private function render_campaigns_tab() {
		$campaigns = MBMC_DB::list_loyalty_campaigns( 50 );
		?>
		<h2><?php esc_html_e( 'Create campaign', 'mad-baits-mobile-connector' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;margin-bottom:24px;">
			<input type="hidden" name="action" value="mbmc_loyalty_action" />
			<input type="hidden" name="mbmc_loyalty_action" value="save_campaign" />
			<input type="hidden" name="redirect_tab" value="campaigns" />
			<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
			<table class="form-table"><tbody>
				<tr><th>Title</th><td><input name="title" class="regular-text" required /></td></tr>
				<tr><th>Type</th><td><select name="campaign_type"><option value="bonus_all">Bonus all users</option><option value="bonus_selected">Bonus selected users</option><option value="double_points">Double points event</option></select></td></tr>
				<tr><th>Points</th><td><input name="points_amount" type="number" /></td></tr>
				<tr><th>Multiplier</th><td><input name="multiplier" type="number" step="0.1" value="1" /></td></tr>
				<tr><th>User IDs</th><td><input name="user_ids" class="regular-text" placeholder="1,2,3 for selected users" /></td></tr>
				<tr><th>Message</th><td><textarea name="message" rows="2" class="large-text"></textarea></td></tr>
				<tr><th>Schedule</th><td><input name="starts_at" type="datetime-local" /> — <input name="ends_at" type="datetime-local" /></td></tr>
				<tr><th>Run now</th><td><label><input type="checkbox" name="run_now" /> Award immediately on save</label></td></tr>
			</tbody></table>
			<?php submit_button( 'Create campaign' ); ?>
		</form>
		<h2><?php esc_html_e( 'Recent campaigns', 'mad-baits-mobile-connector' ); ?></h2>
		<table class="widefat striped mbmc-table">
			<thead><tr><th>Title</th><th>Type</th><th>Points</th><th>Status</th><th>Created</th></tr></thead>
			<tbody>
			<?php foreach ( $campaigns as $c ) : ?>
				<tr><td><?php echo esc_html( $c->title ); ?></td><td><?php echo esc_html( $c->campaign_type ); ?></td><td><?php echo esc_html( (string) $c->points_amount ); ?></td><td><?php echo esc_html( $c->status ); ?></td><td><?php echo esc_html( $c->created_at ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Catch report moderation with loyalty integration.
	 *
	 * @return void
	 */
	private function render_catches_tab() {
		$pending   = MBMC_DB::list_community_catches_by_status( 'pending', 100 );
		$published = MBMC_DB::list_community_catches_by_status( 'published', 50 );
		?>
		<h2><?php esc_html_e( 'Pending catch reports', 'mad-baits-mobile-connector' ); ?></h2>
		<table class="widefat striped mbmc-table">
			<thead><tr><th>Angler</th><th>Species</th><th>Venue</th><th>Submitted</th><th>Actions</th></tr></thead>
			<tbody>
			<?php foreach ( $pending as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->angler_name ); ?></td>
					<td><?php echo esc_html( $row->species . ( $row->weight_display ? ' · ' . $row->weight_display : '' ) ); ?></td>
					<td><?php echo esc_html( $row->venue ); ?></td>
					<td><?php echo esc_html( $row->created_at ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="mbmc_loyalty_action" />
							<input type="hidden" name="mbmc_loyalty_action" value="moderate_catch" />
							<input type="hidden" name="redirect_tab" value="catches" />
							<input type="hidden" name="catch_id" value="<?php echo esc_attr( (string) $row->id ); ?>" />
							<input type="hidden" name="approved" value="1" />
							<label><input type="checkbox" name="featured" /> Featured</label>
							<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
							<button class="button button-primary">Approve + points</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="mbmc_loyalty_action" />
							<input type="hidden" name="mbmc_loyalty_action" value="moderate_catch" />
							<input type="hidden" name="redirect_tab" value="catches" />
							<input type="hidden" name="catch_id" value="<?php echo esc_attr( (string) $row->id ); ?>" />
							<input type="text" name="reason" placeholder="Reason" />
							<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
							<button class="button">Reject</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2><?php esc_html_e( 'Published — remove & revoke points', 'mad-baits-mobile-connector' ); ?></h2>
		<table class="widefat striped mbmc-table">
			<thead><tr><th>Angler</th><th>Species</th><th>Featured</th><th>Published</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $published as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->angler_name ); ?></td>
					<td><?php echo esc_html( $row->species ); ?></td>
					<td><?php echo ! empty( $row->featured ) ? 'Yes' : 'No'; ?></td>
					<td><?php echo esc_html( $row->published_at ?: $row->updated_at ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remove and revoke points?');">
							<input type="hidden" name="action" value="mbmc_loyalty_action" />
							<input type="hidden" name="mbmc_loyalty_action" value="unpublish_catch" />
							<input type="hidden" name="redirect_tab" value="catches" />
							<input type="hidden" name="catch_id" value="<?php echo esc_attr( (string) $row->id ); ?>" />
							<?php wp_nonce_field( 'mbmc_loyalty_action' ); ?>
							<button class="button">Remove</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
