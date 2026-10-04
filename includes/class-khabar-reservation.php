<?php
/**
 * Early access ("رزرو زودتر از دیگران"): after a restock, waiting customers get an
 * exclusive purchase window. Others cannot buy the product/variation until it ends.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Reservation {

	const META = '_khabar_exclusive_until';

	/**
	 * Disable restriction (used while evaluating rules).
	 *
	 * @var bool
	 */
	public static $bypass = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_purchasable' ), 99, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( __CLASS__, 'filter_purchasable' ), 99, 2 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'notice' ), 29 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_data' ), 20, 3 );
	}

	/**
	 * Open a window for a product when a stock notification goes out.
	 *
	 * @param WC_Product $product Product / variation.
	 * @return bool Window is (now) open.
	 */
	public static function open_window( $product ) {
		if ( ! Khabar_Settings::get( 'exclusive_enabled' ) ) {
			return false;
		}
		$until = (int) get_post_meta( $product->get_id(), self::META, true );
		if ( $until > time() ) {
			return true;
		}
		$minutes = max( 1, (int) Khabar_Settings::get( 'exclusive_minutes', 30 ) );
		update_post_meta( $product->get_id(), self::META, time() + $minutes * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Seconds left of an exclusive window.
	 *
	 * @param int $product_id Product id.
	 * @return int
	 */
	public static function remaining( $product_id ) {
		if ( ! Khabar_Settings::get( 'exclusive_enabled' ) ) {
			return 0;
		}
		$until = (int) get_post_meta( $product_id, self::META, true );
		return max( 0, $until - time() );
	}

	/**
	 * Does the current visitor have early access to a product?
	 *
	 * @param int $product_id Product / variation id.
	 * @return bool
	 */
	public static function has_access( $product_id ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		$now   = Khabar_Utils::now();

		// Token cookie set when the visitor opened the link from the notification.
		$tokens = self::access_tokens();
		if ( $tokens ) {
			$placeholders = implode( ',', array_fill( 0, count( $tokens ), '%s' ) );
			$args         = array_merge( $tokens, array( $product_id, $now ) );
			$found        = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE token IN ({$placeholders}) AND notified_target = %d AND reserved_until > %s LIMIT 1", $args ) ); // phpcs:ignore
			if ( $found ) {
				return true;
			}
		}
		$uid = get_current_user_id();
		if ( $uid ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND notified_target = %d AND reserved_until > %s LIMIT 1", $uid, $product_id, $now ) ); // phpcs:ignore
		}
		return false;
	}

	/**
	 * Access tokens from cookie.
	 *
	 * @return string[]
	 */
	public static function access_tokens() {
		if ( empty( $_COOKIE[ Khabar_Utils::ACCESS_COOKIE ] ) ) {
			return array();
		}
		$raw = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE[ Khabar_Utils::ACCESS_COOKIE ] ) ) );
		return array_slice( array_filter( array_map( 'sanitize_key', $raw ) ), 0, 10 );
	}

	/**
	 * Remember a token in the access cookie.
	 *
	 * @param string $token Token.
	 */
	public static function grant( $token ) {
		$tokens   = self::access_tokens();
		$tokens[] = strtolower( $token );
		$tokens   = array_slice( array_unique( $tokens ), -10 );
		Khabar_Utils::set_cookie( Khabar_Utils::ACCESS_COOKIE, implode( '.', $tokens ), DAY_IN_SECONDS );
		$_COOKIE[ Khabar_Utils::ACCESS_COOKIE ] = implode( '.', $tokens );
	}

	/**
	 * Block purchase for the public during the exclusive window.
	 *
	 * @param bool       $purchasable Purchasable.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public static function filter_purchasable( $purchasable, $product ) {
		if ( ! $purchasable || self::$bypass || ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() ) {
			return $purchasable;
		}
		if ( self::remaining( $product->get_id() ) <= 0 ) {
			return $purchasable;
		}
		return self::has_access( $product->get_id() );
	}

	/**
	 * Notice on the product page.
	 */
	public static function notice() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$ids = array( $product->get_id() );
		if ( $product->is_type( 'variable' ) ) {
			$ids = array_merge( $ids, $product->get_children() );
		}
		foreach ( $ids as $id ) {
			$left = self::remaining( $id );
			if ( $left > 0 ) {
				$mins = (int) ceil( $left / 60 );
				$msg  = self::has_access( $id )
					/* translators: %d minutes */
					? sprintf( __( '🎉 این کالا برای شما رزرو شده است. %d دقیقه فرصت خرید اختصاصی دارید.', 'khabar' ), $mins )
					/* translators: %d minutes */
					: sprintf( __( 'این کالا تازه موجود شده و تا %d دقیقه دیگر فقط برای مشتریان منتظر قابل خرید است.', 'khabar' ), $mins );
				echo '<div class="khabar-exclusive-note">' . esc_html( $msg ) . '</div>';
				return;
			}
		}
	}

	/**
	 * Show a reservation message for a blocked variation.
	 *
	 * @param array                $data      Data.
	 * @param WC_Product           $product   Parent.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public static function variation_data( $data, $product, $variation ) {
		$left = self::remaining( $variation->get_id() );
		if ( $left > 0 && ! self::has_access( $variation->get_id() ) ) {
			/* translators: %d minutes */
			$data['availability_html'] .= '<p class="khabar-exclusive-note">' . esc_html( sprintf( __( 'تا %d دقیقه دیگر فقط برای مشتریان منتظر قابل خرید است.', 'khabar' ), ceil( $left / 60 ) ) ) . '</p>';
		}
		return $data;
	}
}
