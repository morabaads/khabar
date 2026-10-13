<?php
/**
 * On-site notifications (bell) – also the payload store for push notifications.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Support\Clock;

/**
 * On-site notifications (bell) – also the payload store for push notifications.
 */
final class OnsiteChannel {

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
			Installer::table( 'notifications' ),
			array(
				'owner_key'       => $owner_key,
				'subscription_id' => (int) $sub_id,
				'title'           => mb_substr( $title, 0, 255 ),
				'message'         => $message,
				'url'             => $url,
				'created_at'      => Clock::now(),
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
		$table = Installer::table( 'notifications' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, subscription_id, title, message, url, is_read, created_at FROM {$table} WHERE owner_key = %s ORDER BY id DESC LIMIT %d", $owner_key, $limit ) ); // phpcs:ignore
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
		$table = Installer::table( 'notifications' );
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
			$wpdb->update( Installer::table( 'notifications' ), array( 'is_read' => 1 ), array( 'owner_key' => $owner_key ) ); // phpcs:ignore
		}
	}

	/**
	 * The item a push ping shows: the owner's newest not-yet-pushed item of the last day
	 * (marked as pushed).
	 *
	 * @param string $owner_key Owner.
	 * @return object|null
	 */
	public static function next_for_push( $owner_key ) {
		global $wpdb;
		$table = Installer::table( 'notifications' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE owner_key = %s AND created_at > %s ORDER BY pushed ASC, id DESC LIMIT 1", $owner_key, Clock::now( -DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
		if ( $row ) {
			$wpdb->update( $table, array( 'pushed' => 1 ), array( 'id' => $row->id ) ); // phpcs:ignore WordPress.DB
		}
		return $row;
	}
}
