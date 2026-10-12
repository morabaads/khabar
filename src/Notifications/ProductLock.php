<?php
/**
 * Per-product run lock (atomic across requests): a save-time check and a queued check never send twice.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Per-product run lock (atomic across requests): a save-time check and a queued check never send twice.
 */
final class ProductLock {

	/**
	 * Take the per-product run lock. An admin save runs the check at shutdown while Action
	 * Scheduler may run an already queued check in parallel; without the lock both send.
	 * Uses INSERT IGNORE on the options table so the claim is atomic across requests.
	 *
	 * @param int $product_id Product id.
	 * @return bool
	 */
	public static function acquire( $product_id ) {
		global $wpdb;
		$name = 'yadambiar_lock_' . $product_id;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $name, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) ( time() + 5 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Release the per-product run lock.
	 *
	 * @param int $product_id Product id.
	 */
	public static function release( $product_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'yadambiar_lock_' . $product_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
