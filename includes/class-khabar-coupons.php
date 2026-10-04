<?php
/**
 * Personal one-time discount coupons inside notification messages.
 *
 * Each coupon is single use, restricted to the notified product/variation, expires,
 * is optionally bound to the customer's email, is applied automatically when the
 * customer arrives from the message link and attributes the sale precisely.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Coupons {

	const META    = '_khabar_subscription';
	const SESSION = 'khabar_coupon';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'auto_apply' ), 20 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'auto_apply' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'auto_apply' ) );
		add_action( 'khabar_daily', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Should a coupon be issued for this event.
	 *
	 * @param object $sub   Subscription.
	 * @param string $event Event.
	 * @return bool
	 */
	public static function applies( $sub, $event ) {
		return Khabar_Settings::get( 'coupon_enabled' )
			&& (int) Khabar_Settings::get( 'coupon_amount' ) > 0
			&& Khabar_Settings::has( 'coupon_events', $event )
			&& ! Khabar_Rules::is_recurring( $sub )
			&& wc_coupons_enabled();
	}

	/**
	 * Create the coupon.
	 *
	 * @param object     $sub    Subscription.
	 * @param WC_Product $target Product / variation.
	 * @return string Coupon code or ''.
	 */
	public static function create( $sub, $target ) {
		$type   = 'fixed_product' === Khabar_Settings::get( 'coupon_type' ) ? 'fixed_product' : 'percent';
		$amount = (float) Khabar_Settings::get( 'coupon_amount' );
		if ( 'percent' === $type ) {
			$amount = min( 100, $amount );
		}
		$hours = max( 1, (int) Khabar_Settings::get( 'coupon_hours', 48 ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$code = 'KHB-' . strtoupper( wp_generate_password( 8, false, false ) );
			if ( ! wc_get_coupon_id_by_code( $code ) ) {
				break;
			}
		}

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		/* translators: 1: subscription id 2: product name */
		$coupon->set_description( sprintf( __( 'خبرم کن — درخواست #%1$d — %2$s', 'khabar' ), $sub->id, $target->get_name() ) );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_product_ids( array( $target->get_id() ) );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_limit_usage_to_x_items( 1 );
		$coupon->set_individual_use( (bool) Khabar_Settings::get( 'coupon_individual', 1 ) );
		$coupon->set_date_expires( time() + $hours * HOUR_IN_SECONDS );
		if ( Khabar_Settings::get( 'coupon_restrict_email' ) && $sub->email ) {
			$coupon->set_email_restrictions( array( $sub->email ) );
		}
		$coupon->update_meta_data( self::META, $sub->id );
		$id = $coupon->save();
		if ( ! $id ) {
			return '';
		}
		Khabar_Subscriptions::update( $sub->id, array( 'coupon_code' => $code ) );
		return $code;
	}

	/**
	 * Message variables for a coupon.
	 *
	 * @param string $code Code.
	 * @return array
	 */
	public static function vars( $code ) {
		if ( ! $code ) {
			return array(
				'coupon'        => '',
				'coupon_amount' => '',
				'coupon_expiry' => '',
				'coupon_note'   => '',
			);
		}
		$hours  = max( 1, (int) Khabar_Settings::get( 'coupon_hours', 48 ) );
		$amount = 'percent' === Khabar_Settings::get( 'coupon_type' )
			? number_format_i18n( min( 100, (float) Khabar_Settings::get( 'coupon_amount' ) ) ) . '٪'
			: Khabar_Utils::price_text( Khabar_Settings::get( 'coupon_amount' ) );
		/* translators: %d hours */
		$expiry = sprintf( _n( '%d ساعت', '%d ساعت', $hours, 'khabar' ), $hours );
		return array(
			'coupon'        => $code,
			'coupon_amount' => $amount,
			'coupon_expiry' => $expiry,
			/* translators: 1: amount 2: code 3: expiry */
			'coupon_note'   => sprintf( __( '🎁 کد تخفیف %1$s مخصوص شما: %2$s (اعتبار %3$s)', 'khabar' ), $amount, $code, $expiry ),
		);
	}

	/**
	 * Remember a coupon for automatic application (called on message link click).
	 *
	 * @param object $sub Subscription.
	 */
	public static function remember( $sub ) {
		if ( ! $sub->coupon_code || ! Khabar_Settings::get( 'coupon_auto_apply', 1 ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( self::SESSION, $sub->coupon_code );
	}

	/**
	 * Apply the remembered coupon when the cart contains the product.
	 */
	public static function auto_apply() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}
		$code = WC()->session->get( self::SESSION );
		if ( ! $code || WC()->cart->has_discount( $code ) ) {
			return;
		}
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() || ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() ) || $coupon->get_usage_count() >= max( 1, $coupon->get_usage_limit() ) ) {
			WC()->session->set( self::SESSION, null );
			return;
		}
		$ids = $coupon->get_product_ids();
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( array_intersect( $ids, array( (int) $item['product_id'], (int) $item['variation_id'] ) ) ) {
				if ( WC()->cart->apply_coupon( $code ) ) {
					WC()->session->set( self::SESSION, null );
				}
				return;
			}
		}
	}

	/**
	 * Subscription ids whose coupon was used in an order.
	 *
	 * @param WC_Order $order Order.
	 * @return int[]
	 */
	public static function subscriptions_in_order( $order ) {
		$ids = array();
		foreach ( $order->get_coupon_codes() as $code ) {
			if ( 0 !== stripos( $code, 'khb-' ) ) {
				continue;
			}
			$coupon = new WC_Coupon( $code );
			$sid    = (int) $coupon->get_meta( self::META );
			if ( $sid ) {
				$ids[] = $sid;
			}
		}
		return $ids;
	}

	/**
	 * Delete unused coupons that expired more than a week ago.
	 */
	public static function cleanup() {
		$ids = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => self::META,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'date_expires',
						'value'   => time() - WEEK_IN_SECONDS,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
					array(
						'key'   => 'usage_count',
						'value' => '0',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
}
