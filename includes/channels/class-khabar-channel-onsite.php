<?php
/**
 * On-site notifications (bell) – also the payload store for push notifications.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Channel_Onsite {

	/**
	 * Create a notification.
	 *
	 * @param string $owner_key Owner.
	 * @param int    $sub_id    Subscription id.
	 * @param string $title     Title.
	 * @param string $message   Text.
	 * @param string $url       URL.
	 * @return int|false
	 */
	public static function create( $owner_key, $sub_id, $title, $message, $url ) {
		global $wpdb;
		if ( ! $owner_key ) {
			return false;
		}
		$ok = $wpdb->insert( // phpcs:ignore
			Khabar_Install::table( 'notifications' ),
			array(
				'owner_key'       => $owner_key,
				'subscription_id' => (int) $sub_id,
				'title'           => mb_substr( $title, 0, 255 ),
				'message'         => $message,
				'url'             => $url,
				'created_at'      => Khabar_Utils::now(),
			)
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * List notifications.
	 *
	 * @param string $owner_key Owner.
	 * @param int    $limit     Limit.
	 * @return array
	 */
	public static function latest( $owner_key, $limit = 15 ) {
		global $wpdb;
		if ( ! $owner_key ) {
			return array();
		}
		$table = Khabar_Install::table( 'notifications' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, title, message, url, is_read, created_at FROM {$table} WHERE owner_key = %s ORDER BY id DESC LIMIT %d", $owner_key, $limit ) ); // phpcs:ignore
	}

	/**
	 * Unread count.
	 *
	 * @param string $owner_key Owner.
	 * @return int
	 */
	public static function unread( $owner_key ) {
		global $wpdb;
		if ( ! $owner_key ) {
			return 0;
		}
		$table = Khabar_Install::table( 'notifications' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE owner_key = %s AND is_read = 0", $owner_key ) ); // phpcs:ignore
	}

	/**
	 * Mark all read.
	 *
	 * @param string $owner_key Owner.
	 */
	public static function mark_read( $owner_key ) {
		global $wpdb;
		if ( $owner_key ) {
			$wpdb->update( Khabar_Install::table( 'notifications' ), array( 'is_read' => 1 ), array( 'owner_key' => $owner_key ) ); // phpcs:ignore
		}
	}
}
