<?php
/**
 * One message per person: the same alert for the same item is not repeated across a person's requests.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Clock;
/**
 * One message per person: the same alert for the same item is not repeated across a person's requests.
 */
final class DuplicateGuard {

	/**
	 * Has this person (by user, email or phone, on any of their requests) already received this alert
	 * for this item within the duplicate window?
	 *
	 * @param object $sub       Subscription.
	 * @param string $event     Event.
	 * @param int    $target_id Product / variation id.
	 * @return bool
	 */
	public static function recently_notified( $sub, $event, $target_id ) {
		global $wpdb;
		$hours = (int) Settings::get( 'duplicate_window', 72 );
		if ( $hours <= 0 ) {
			return false;
		}
		// back_in_stock / low_stock are the same news ("it's available").
		$events = in_array( $event, array( 'back_in_stock', 'low_stock' ), true ) ? array( 'back_in_stock', 'low_stock' ) : array( $event );
		$who    = array();
		$args   = array();
		if ( $sub->user_id ) {
			$who[]  = 's.user_id = %d';
			$args[] = (int) $sub->user_id;
		}
		if ( $sub->email ) {
			$who[]  = 's.email = %s';
			$args[] = $sub->email;
		}
		if ( $sub->phone ) {
			$who[]  = 's.phone = %s';
			$args[] = $sub->phone;
		}
		if ( ! $who ) {
			return false;
		}
		$log  = Installer::table( 'log' );
		$subs = Installer::table( 'subscriptions' );
		$sql  = "SELECT l.id FROM {$log} l INNER JOIN {$subs} s ON s.id = l.subscription_id WHERE l.status = 'sent' AND l.subscription_id <> %d AND l.variation_id = %d AND l.created_at >= %s AND l.event IN (" . implode( ',', array_fill( 0, count( $events ), '%s' ) ) . ') AND (' . implode( ' OR ', $who ) . ') LIMIT 1';
		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( array( (int) $sub->id, (int) $target_id, Clock::now( -$hours * HOUR_IN_SECONDS ) ), $events, $args ) ) ); // phpcs:ignore
	}

	/**
	 * Identities of a subscriber (user, email, phone) used to send one message per person.
	 *
	 * @param object $sub Subscription.
	 * @return string[]
	 */
	public static function contact_keys( $sub ) {
		return array_filter(
			array(
				$sub->user_id ? 'u:' . $sub->user_id : '',
				$sub->email ? 'e:' . strtolower( $sub->email ) : '',
				$sub->phone ? 'p:' . $sub->phone : '',
			)
		);
	}
}
