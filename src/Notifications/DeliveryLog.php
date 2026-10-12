<?php
/**
 * The send log: one row per channel attempt (sent, failed or skipped as a duplicate).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Support\Clock;

/**
 * Delivery log repository.
 */
final class DeliveryLog {

	const SENT    = 'sent';
	const FAILED  = 'failed';
	const SKIPPED = 'skipped';

	/**
	 * Record an attempt.
	 *
	 * @param object|null          $sub       Subscription (null for broadcasts).
	 * @param string               $event     Event.
	 * @param string               $channel   Channel.
	 * @param string               $recipient Recipient.
	 * @param string               $text      Text.
	 * @param true|\WP_Error|string $result   true, WP_Error, or 'skipped'.
	 */
	public static function record( $sub, $event, $channel, $recipient, $text, $result ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Installer::table( 'log' ),
			array(
				'subscription_id' => $sub ? (int) $sub->id : 0,
				'product_id'      => $sub ? (int) $sub->product_id : 0,
				'variation_id'    => $sub && isset( $sub->notified_target ) ? (int) $sub->notified_target : 0,
				'event'           => (string) $event,
				'channel'         => (string) $channel,
				'recipient'       => (string) $recipient,
				'status'          => self::status_of( $result ),
				'message'         => (string) $text,
				'error'           => self::error_of( $result ),
				'created_at'      => Clock::now(),
			)
		);
	}

	/**
	 * One row.
	 *
	 * @param int $id Log id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Installer::table( 'log' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Store the outcome of a retry.
	 *
	 * @param int            $id     Log id.
	 * @param true|\WP_Error $result Result.
	 */
	public static function update_result( $id, $result ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Installer::table( 'log' ),
			array(
				'status' => self::status_of( $result ),
				'error'  => self::error_of( $result ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Status column of a result.
	 *
	 * @param mixed $result Result.
	 * @return string
	 */
	private static function status_of( $result ) {
		if ( true === $result ) {
			return self::SENT;
		}
		return self::SKIPPED === $result ? self::SKIPPED : self::FAILED;
	}

	/**
	 * Error column of a result.
	 *
	 * @param mixed $result Result.
	 * @return string
	 */
	private static function error_of( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( true === $result ) {
			return '';
		}
		return self::SKIPPED === $result ? __( 'تکراری: همین اعلان اخیراً برای این شخص ارسال شده بود', 'yadambiar' ) : 'failed';
	}
}
