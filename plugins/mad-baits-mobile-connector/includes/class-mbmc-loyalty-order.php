<?php
/**
 * Award loyalty points on WooCommerce order status changes.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Loyalty_Order
 */
class MBMC_Loyalty_Order {

	const ORDER_META_POINTS       = '_mbmc_loyalty_points_awarded';
	const ORDER_META_ELIGIBLE_GBP = '_mbmc_loyalty_eligible_gbp';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_award_on_status' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_award_on_status' ), 20, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_order_metabox' ) );
		add_action( 'admin_post_mbmc_recalculate_order_points', array( __CLASS__, 'handle_admin_recalculate' ) );
	}

	/**
	 * Award points when order reaches processing or completed.
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order object.
	 * @return void
	 */
	public static function maybe_award_on_status( $order_id, $order = null ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order_id = absint( $order_id );
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::award_for_order( $order, false );
	}

	/**
	 * Calculate and award purchase points for an order.
	 *
	 * @param WC_Order $order    Order.
	 * @param bool     $force    Force recalculate (admin).
	 * @return array{awarded: bool, points: int, eligibleGbp: float}
	 */
	public static function award_for_order( WC_Order $order, $force = false ) {
		$result = array(
			'awarded'     => false,
			'points'      => 0,
			'eligibleGbp' => 0.0,
		);

		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return $result;
		}

		$order_id = (int) $order->get_id();
		$existing = (int) $order->get_meta( self::ORDER_META_POINTS );

		if ( ! $force && $existing > 0 ) {
			$result['points']      = $existing;
			$result['eligibleGbp'] = (float) $order->get_meta( self::ORDER_META_ELIGIBLE_GBP );
			return $result;
		}

		if ( ! $force && MBMC_Loyalty::has_award( $user_id, 'purchase', (string) $order_id ) ) {
			$stored = (int) $order->get_meta( self::ORDER_META_POINTS );
			if ( $stored > 0 ) {
				$result['points'] = $stored;
				return $result;
			}
		}

		if ( $force && $existing > 0 ) {
			MBMC_DB::reject_loyalty_source_transactions( $user_id, 'purchase', (string) $order_id );
		}

		$eligible = MBMC_Loyalty_Eligibility::get_order_eligible_subtotal( $order );
		$result['eligibleGbp'] = $eligible;

		if ( $eligible <= 0 ) {
			$order->update_meta_data( self::ORDER_META_ELIGIBLE_GBP, '0' );
			$order->update_meta_data( self::ORDER_META_POINTS, '0' );
			$order->save();
			return $result;
		}

		$tx_id = MBMC_Loyalty::award_order_points( $user_id, $order_id, $eligible );
		if ( ! $tx_id ) {
			return $result;
		}

		$rate   = MBMC_Loyalty_Config::get_int( 'purchase_per_gbp' );
		$points = (int) floor( $eligible * max( 0, $rate ) );

		$order->update_meta_data( self::ORDER_META_ELIGIBLE_GBP, (string) $eligible );
		$order->update_meta_data( self::ORDER_META_POINTS, (string) $points );
		$order->add_order_note(
			sprintf(
				/* translators: 1: points, 2: eligible GBP */
				__( 'Mad Baits loyalty: %1$d points awarded on £%2$s eligible spend.', 'mad-baits-mobile-connector' ),
				$points,
				number_format( $eligible, 2 )
			)
		);
		$order->save();

		$result['awarded'] = true;
		$result['points']  = $points;

		if ( class_exists( 'MBMC_Notification_Service' ) ) {
			MBMC_Notification_Service::dispatch_loyalty_notification(
				$user_id,
				'points_awarded',
				sprintf( __( 'You earned %d loyalty points', 'mad-baits-mobile-connector' ), $points ),
				sprintf( __( 'Order #%s — £%s eligible spend.', 'mad-baits-mobile-connector' ), $order->get_order_number(), number_format( $eligible, 2 ) ),
				array( 'orderId' => (string) $order_id, 'points' => (string) $points )
			);
		}

		if ( class_exists( 'MBMC_Achievements' ) ) {
			MBMC_Achievements::evaluate_for_user( $user_id, 'order_completed', array( 'orderId' => $order_id ) );
		}

		return $result;
	}

	/**
	 * Register WooCommerce order metabox.
	 *
	 * @return void
	 */
	public static function register_order_metabox() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			return;
		}

		add_meta_box(
			'mbmc_loyalty_order_points',
			__( 'Mad Baits Loyalty', 'mad-baits-mobile-connector' ),
			array( __CLASS__, 'render_order_metabox' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Render order loyalty metabox.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public static function render_order_metabox( $post ) {
		$order = wc_get_order( $post->ID );
		if ( ! $order instanceof WC_Order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'mad-baits-mobile-connector' ) . '</p>';
			return;
		}

		$points    = (int) $order->get_meta( self::ORDER_META_POINTS );
		$eligible  = (float) $order->get_meta( self::ORDER_META_ELIGIBLE_GBP );
		$preview   = MBMC_Loyalty_Eligibility::get_order_eligible_subtotal( $order );
		$has_award = MBMC_Loyalty::has_award( (int) $order->get_customer_id(), 'purchase', (string) $order->get_id() );

		echo '<p><strong>' . esc_html__( 'Points awarded', 'mad-baits-mobile-connector' ) . ':</strong> ' . esc_html( (string) $points ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Eligible spend recorded', 'mad-baits-mobile-connector' ) . ':</strong> £' . esc_html( number_format( $eligible, 2 ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Eligible spend (recalc)', 'mad-baits-mobile-connector' ) . ':</strong> £' . esc_html( number_format( $preview, 2 ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Ledger entry', 'mad-baits-mobile-connector' ) . ':</strong> ' . ( $has_award ? esc_html__( 'Yes', 'mad-baits-mobile-connector' ) : esc_html__( 'No', 'mad-baits-mobile-connector' ) ) . '</p>';

		wp_nonce_field( 'mbmc_recalculate_order_points', 'mbmc_loyalty_order_nonce' );
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '" />';
		submit_button( __( 'Recalculate & award points', 'mad-baits-mobile-connector' ), 'secondary', 'mbmc_recalculate_points', false );
	}

	/**
	 * Admin: recalculate and award order points.
	 *
	 * @return void
	 */
	public static function handle_admin_recalculate() {
		if ( ! mbmc_user_can_manage_mobile_app() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mad-baits-mobile-connector' ) );
		}

		check_admin_referer( 'mbmc_recalculate_order_points', 'mbmc_loyalty_order_nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		self::award_for_order( $order, true );

		wp_safe_redirect( add_query_arg( 'mbmc_loyalty_recalculated', '1', get_edit_post_link( $order_id, 'raw' ) ) );
		exit;
	}

	/**
	 * Apply loyalty points discount to a pending order.
	 *
	 * @param WC_Order $order  Order.
	 * @param int      $user_id User id.
	 * @param int      $points  Points to apply.
	 * @return true|WP_Error
	 */
	public static function apply_checkout_points_discount( WC_Order $order, $user_id, $points ) {
		$user_id = absint( $user_id );
		$points  = absint( $points );

		if ( $user_id <= 0 || $points <= 0 ) {
			return true;
		}

		$eligible = MBMC_Loyalty_Eligibility::get_order_eligible_subtotal( $order );
		$allowance = MBMC_Loyalty_Eligibility::checkout_points_allowance( $user_id, $eligible, count( $order->get_coupon_codes() ) > 0 );

		if ( ! $allowance['canApply'] || $points > $allowance['maxPoints'] ) {
			return new WP_Error(
				'mbmc_loyalty_points_invalid',
				$allowance['reason'] ?? __( 'Points cannot be applied to this order.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$discount_gbp = MBMC_Loyalty_Eligibility::points_to_gbp_discount( $points );
		if ( $discount_gbp <= 0 ) {
			return new WP_Error(
				'mbmc_loyalty_points_invalid',
				__( 'Invalid points amount.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$fee = new WC_Order_Item_Fee();
		$fee->set_name( __( 'Loyalty points discount', 'mad-baits-mobile-connector' ) );
		$fee->set_amount( -$discount_gbp );
		$fee->set_total( -$discount_gbp );
		$order->add_item( $fee );

		$order->update_meta_data( '_mbmc_loyalty_points_applied', (string) $points );
		$order->update_meta_data( '_mbmc_loyalty_discount_gbp', (string) $discount_gbp );
		$order->update_meta_data( '_mbmc_loyalty_points_user_id', (string) $user_id );

		return true;
	}

	/**
	 * Finalize points deduction after successful payment.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function finalize_checkout_points_redemption( WC_Order $order ) {
		$points  = absint( $order->get_meta( '_mbmc_loyalty_points_applied' ) );
		$user_id = absint( $order->get_meta( '_mbmc_loyalty_points_user_id' ) );

		if ( $points <= 0 || $user_id <= 0 ) {
			return;
		}

		if ( $order->get_meta( '_mbmc_loyalty_points_redeemed' ) ) {
			return;
		}

		$source_id = 'checkout_' . $order->get_id();
		if ( MBMC_Loyalty::has_award( $user_id, 'checkout_redemption', $source_id ) ) {
			$order->update_meta_data( '_mbmc_loyalty_points_redeemed', '1' );
			$order->save();
			return;
		}

		MBMC_Loyalty::award_points(
			$user_id,
			-$points,
			'checkout_redemption',
			$source_id,
			sprintf(
				/* translators: %s order number */
				__( 'Points used at checkout · Order %s', 'mad-baits-mobile-connector' ),
				$order->get_order_number()
			),
			'redeemed'
		);

		$order->update_meta_data( '_mbmc_loyalty_points_redeemed', '1' );
		$order->save();

		if ( class_exists( 'MBMC_Notification_Service' ) ) {
			MBMC_Notification_Service::dispatch_loyalty_notification(
				$user_id,
				'points_redeemed',
				__( 'Points applied at checkout', 'mad-baits-mobile-connector' ),
				sprintf( __( '%d points used on order #%s.', 'mad-baits-mobile-connector' ), $points, $order->get_order_number() ),
				array( 'orderId' => (string) $order->get_id(), 'points' => (string) $points )
			);
		}
	}
}
