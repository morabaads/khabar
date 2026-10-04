<?php
/**
 * Daily maintenance: expiry, cleanup and a safety sweep for changes made outside WooCommerce CRUD.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Cron {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'khabar_daily', array( __CLASS__, 'daily' ) );
		if ( ! wp_next_scheduled( 'khabar_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'khabar_daily' );
		}
	}

	/**
	 * Daily job.
	 */
	public static function daily() {
		global $wpdb;
		$subs  = Khabar_Install::table( 'subscriptions' );
		$log   = Khabar_Install::table( 'log' );
		$notes = Khabar_Install::table( 'notifications' );
		$now   = Khabar_Utils::now();

		$wpdb->query( $wpdb->prepare( "UPDATE {$subs} SET status = 'expired', updated_at = %s WHERE status IN ('active','pending') AND expires_at IS NOT NULL AND expires_at < %s", $now, $now ) ); // phpcs:ignore
		// Unverified requests are dropped after a day.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$subs} WHERE status = 'pending' AND created_at < %s", Khabar_Utils::now( -DAY_IN_SECONDS ) ) ); // phpcs:ignore

		$days = (int) Khabar_Settings::get( 'log_retention_days', 90 );
		if ( $days > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$log} WHERE created_at < %s", Khabar_Utils::now( -$days * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$notes} WHERE created_at < %s", Khabar_Utils::now( -90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore

		// Safety sweep, spread over time.
		$ids = $wpdb->get_col( "SELECT DISTINCT product_id FROM {$subs} WHERE status = 'active'" ); // phpcs:ignore
		foreach ( $ids as $i => $pid ) {
			Khabar_Utils::queue( 'khabar_check_product', array( (int) $pid ), 60 + $i * 5 );
		}
	}
}
