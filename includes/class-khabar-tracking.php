<?php
/**
 * Click and conversion tracking (which notifications produced a sale).
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Tracking {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_click' ), 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'track_order' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'track_order' ) );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'track_order' ) );
	}

	/**
	 * Tracking link: short to keep SMS small.
	 *
	 * @param object $sub       Subscription.
	 * @param int    $target_id Product/variation id.
	 * @return string
	 */
	public static function link( $sub, $target_id ) {
		return add_query_arg(
			array(
				'kgo' => $sub->token,
				't'   => (int) $target_id,
			),
			home_url( '/' )
		);
	}

	/**
	 * Redirect click to the product, record it and grant reservation access.
	 */
	public static function handle_click() {
		if ( empty( $_GET['kgo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$sub = Khabar_Subscriptions::get_by_token( sanitize_text_field( wp_unslash( $_GET['kgo'] ) ) ); // phpcs:ignore
		if ( ! $sub ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$target_id = isset( $_GET['t'] ) ? absint( $_GET['t'] ) : 0; // phpcs:ignore
		$target    = $target_id ? wc_get_product( $target_id ) : null;
		if ( ! $target ) {
			$target = wc_get_product( $sub->product_id );
		}

		if ( ! $sub->clicked_at ) {
			Khabar_Subscriptions::update( $sub->id, array( 'clicked_at' => Khabar_Utils::now() ) );
		}
		Khabar_Reservation::grant( $sub->token );
		do_action( 'khabar_clicked', $sub );

		$url = home_url( '/' );
		if ( $target ) {
			if ( $target->is_type( 'variation' ) ) {
				$url = add_query_arg( $target->get_variation_attributes(), get_permalink( $target->get_parent_id() ) );
			} else {
				$url = get_permalink( $target->get_id() );
			}
		}
		$url = add_query_arg(
			array(
				'utm_source'   => 'khabar',
				'utm_medium'   => 'notification',
				'utm_campaign' => $sub->type,
			),
			$url
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Attribute an order to notified subscriptions.
	 *
	 * @param int $order_id Order id.
	 */
	public static function track_order( $order_id ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_khabar_tracked' ) ) {
			return;
		}
		$order->update_meta_data( '_khabar_tracked', 1 );
		$order->save_meta_data();

		$email = strtolower( (string) $order->get_billing_email() );
		$phone = Khabar_Utils::normalize_phone( $order->get_billing_phone() );
		$uid   = (int) $order->get_customer_id();
		$since = Khabar_Utils::now( -1 * max( 1, (int) Khabar_Settings::get( 'conversion_days', 14 ) ) * DAY_IN_SECONDS );
		$table = Khabar_Install::table( 'subscriptions' );

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$pid  = (int) $item->get_product_id();
			$vid  = (int) $item->get_variation_id();
			$rows = $wpdb->get_results( // phpcs:ignore
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE product_id = %d AND status = 'notified' AND last_notified_at >= %s AND ( notified_target IN (%d, %d) OR variation_id = 0 ) AND ( (user_id > 0 AND user_id = %d) OR (email <> '' AND email = %s) OR (phone <> '' AND phone = %s) )", // phpcs:ignore
					$pid,
					$since,
					$pid,
					$vid,
					$uid,
					$email,
					$phone
				)
			);
			foreach ( $rows as $row ) {
				Khabar_Subscriptions::update(
					$row->id,
					array(
						'status'      => 'purchased',
						'order_id'    => $order->get_id(),
						'order_value' => (float) $item->get_total(),
					)
				);
				do_action( 'khabar_converted', (int) $row->id, $order );
			}
		}
	}
}
