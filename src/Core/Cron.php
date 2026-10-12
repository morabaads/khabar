<?php
/**
 * Daily maintenance: expiry, cleanup and a safety sweep for changes made outside WooCommerce CRUD.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Core;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Clock;
use YadamBiar\Support\Queue;

/**
 * Daily maintenance: expiry, cleanup and a safety sweep for changes made outside WooCommerce CRUD.
 */
final class Cron implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'yadambiar_daily', array( __CLASS__, 'daily' ) );
		if ( ! wp_next_scheduled( 'yadambiar_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'yadambiar_daily' );
		}
	}

	/**
	 * Daily job.
	 */
	public static function daily() {
		global $wpdb;
		$subs  = Installer::table( 'subscriptions' );
		$log   = Installer::table( 'log' );
		$notes = Installer::table( 'notifications' );
		$now   = Clock::now();

		$wpdb->query( $wpdb->prepare( "UPDATE {$subs} SET status = 'expired', updated_at = %s WHERE status IN ('active','pending') AND expires_at IS NOT NULL AND expires_at < %s", $now, $now ) ); // phpcs:ignore
		// Unverified requests are dropped after a day.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$subs} WHERE status = 'pending' AND created_at < %s", Clock::now( -DAY_IN_SECONDS ) ) ); // phpcs:ignore

		$days = (int) Settings::get( 'log_retention_days', 90 );
		if ( $days > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$log} WHERE created_at < %s", Clock::now( -$days * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$notes} WHERE created_at < %s", Clock::now( -90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore

		self::sweep();
	}

	/**
	 * Safety sweep: queue a check of every product with waiting customers, spread over time.
	 *
	 * @param int $start Seconds before the first check.
	 */
	public static function sweep( $start = 60 ) {
		global $wpdb;
		$subs = Installer::table( 'subscriptions' );
		$ids  = $wpdb->get_col( "SELECT DISTINCT product_id FROM {$subs} WHERE status = 'active'" ); // phpcs:ignore
		foreach ( $ids as $i => $pid ) {
			Queue::schedule( 'yadambiar_check_product', array( (int) $pid ), $start + $i * 5 );
		}
	}
}
