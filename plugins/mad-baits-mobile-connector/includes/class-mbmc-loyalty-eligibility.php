<?php
/**
 * Loyalty eligibility — which products/orders earn or spend points.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Loyalty_Eligibility
 */
class MBMC_Loyalty_Eligibility {

	/**
	 * Whether a WooCommerce product is eligible for loyalty earn/redeem.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function is_product_eligible( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		if ( MBMC_Loyalty_Config::get_bool( 'exclude_sale' ) && $product->is_on_sale() ) {
			return false;
		}

		$excluded = self::get_excluded_category_slugs();
		$slugs    = self::get_product_category_slugs( $product );

		foreach ( $slugs as $slug ) {
			if ( in_array( $slug, $excluded, true ) ) {
				return false;
			}
		}

		if ( MBMC_Loyalty_Config::get_bool( 'exclude_bundles' ) && self::product_is_bundle( $product ) ) {
			return false;
		}

		if ( MBMC_Loyalty_Config::get_bool( 'exclude_deals' ) && self::product_is_deal( $product, $slugs ) ) {
			return false;
		}

		if ( MBMC_Loyalty_Config::get_bool( 'exclude_tackle' ) && self::product_is_tackle( $slugs ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sum eligible line-item totals (excludes shipping) for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return float GBP eligible subtotal.
	 */
	public static function get_order_eligible_subtotal( WC_Order $order ) {
		$total = 0.0;

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( ! self::is_product_eligible( $product ) ) {
				continue;
			}

			$total += (float) $item->get_total();
		}

		return max( 0, $total );
	}

	/**
	 * Eligible subtotal from cart line items before order creation.
	 *
	 * @param array<int, array<string, mixed>> $line_items App line items.
	 * @return float|WP_Error
	 */
	public static function get_line_items_eligible_subtotal( array $line_items ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error(
				'mbmc_checkout_unavailable',
				__( 'Checkout is currently unavailable.', 'mad-baits-mobile-connector' ),
				array( 'status' => 503 )
			);
		}

		$total = 0.0;

		foreach ( $line_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$product_id   = isset( $raw_item['productId'] ) ? absint( $raw_item['productId'] ) : 0;
			$variation_id = isset( $raw_item['variationId'] ) ? absint( $raw_item['variationId'] ) : 0;
			$quantity     = isset( $raw_item['quantity'] ) ? max( 1, absint( $raw_item['quantity'] ) ) : 1;
			$target_id    = $variation_id > 0 ? $variation_id : $product_id;

			if ( $target_id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $target_id );
			if ( ! $product instanceof WC_Product || ! self::is_product_eligible( $product ) ) {
				continue;
			}

			$total += (float) $product->get_price() * $quantity;
		}

		return max( 0, $total );
	}

	/**
	 * Max points applicable at checkout for eligible subtotal.
	 *
	 * @param int   $user_id           User id.
	 * @param float $eligible_subtotal Eligible GBP subtotal.
	 * @param bool  $has_coupon        Whether a coupon is applied.
	 * @return array{canApply: bool, maxPoints: int, maxDiscountGbp: float, reason: string|null}
	 */
	public static function checkout_points_allowance( $user_id, $eligible_subtotal, $has_coupon = false ) {
		$eligible_subtotal = max( 0, (float) $eligible_subtotal );
		$user_id           = absint( $user_id );
		$min_gbp           = MBMC_Loyalty_Config::get_float( 'min_redemption_gbp' );
		$max_percent       = MBMC_Loyalty_Config::get_int( 'max_redemption_percent' );
		$rate              = max( 1, MBMC_Loyalty_Config::get_int( 'checkout_points_per_gbp' ) );

		if ( $has_coupon && MBMC_Loyalty_Config::get_bool( 'cannot_combine_coupons' ) ) {
			return array(
				'canApply'       => false,
				'maxPoints'      => 0,
				'maxDiscountGbp' => 0,
				'reason'         => __( 'Points cannot be combined with coupon codes.', 'mad-baits-mobile-connector' ),
			);
		}

		if ( $eligible_subtotal < $min_gbp ) {
			return array(
				'canApply'       => false,
				'maxPoints'      => 0,
				'maxDiscountGbp' => 0,
				'reason'         => sprintf(
					/* translators: %s minimum GBP */
					__( 'Minimum eligible order value is £%s to use points.', 'mad-baits-mobile-connector' ),
					number_format( $min_gbp, 2 )
				),
			);
		}

		$balance = $user_id > 0 ? MBMC_Loyalty::get_available_balance( $user_id ) : 0;
		$max_gbp = $eligible_subtotal * ( $max_percent / 100 );
		$max_pts = (int) floor( $max_gbp * $rate );
		$max_pts = min( $max_pts, $balance );

		if ( $max_pts <= 0 ) {
			return array(
				'canApply'       => false,
				'maxPoints'      => 0,
				'maxDiscountGbp' => 0,
				'reason'         => $balance <= 0
					? __( 'No points available.', 'mad-baits-mobile-connector' )
					: __( 'No points applicable for this order.', 'mad-baits-mobile-connector' ),
			);
		}

		return array(
			'canApply'       => true,
			'maxPoints'      => $max_pts,
			'maxDiscountGbp' => round( $max_pts / $rate, 2 ),
			'reason'         => null,
		);
	}

	/**
	 * Convert points to GBP discount using checkout rate.
	 *
	 * @param int $points Points to apply.
	 * @return float
	 */
	public static function points_to_gbp_discount( $points ) {
		$rate = max( 1, MBMC_Loyalty_Config::get_int( 'checkout_points_per_gbp' ) );
		return round( max( 0, (int) $points ) / $rate, 2 );
	}

	/**
	 * @return string[]
	 */
	private static function get_excluded_category_slugs() {
		$raw = MBMC_Loyalty_Config::get( 'excluded_categories', '[]' );
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? array_map( 'sanitize_title', $decoded ) : array();
	}

	/**
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	private static function get_product_category_slugs( WC_Product $product ) {
		$ids   = wc_get_product_cat_ids( $product->get_id() );
		$slugs = array();
		foreach ( $ids as $term_id ) {
			$term = get_term( (int) $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$slugs[] = sanitize_title( $term->slug );
			}
		}
		return $slugs;
	}

	/**
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private static function product_is_bundle( WC_Product $product ) {
		if ( $product->is_type( 'bundle' ) || $product->is_type( 'composite' ) ) {
			return true;
		}
		$type = sanitize_key( (string) $product->get_type() );
		return in_array( $type, array( 'bundle', 'composite', 'grouped' ), true );
	}

	/**
	 * @param WC_Product $product Product.
	 * @param string[]   $slugs   Category slugs.
	 * @return bool
	 */
	private static function product_is_deal( WC_Product $product, array $slugs ) {
		foreach ( array( 'deal', 'deals', 'discounted', 'sale', 'bundle' ) as $needle ) {
			if ( in_array( $needle, $slugs, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string[] $slugs Category slugs.
	 * @return bool
	 */
	private static function product_is_tackle( array $slugs ) {
		foreach ( array( 'tackle', 'terminal-tackle', 'hooks', 'lines' ) as $needle ) {
			if ( in_array( $needle, $slugs, true ) ) {
				return true;
			}
		}
		return false;
	}
}
