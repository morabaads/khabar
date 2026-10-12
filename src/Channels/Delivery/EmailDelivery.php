<?php
/**
 * Email delivery (WooCommerce mailer template).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Delivery;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\EmailChannel;
use YadamBiar\Contracts\NotificationChannel;

/**
 * Email adapter.
 */
final class EmailDelivery implements NotificationChannel {

	/**
	 * Key.
	 *
	 * @return string
	 */
	public function key() {
		return 'email';
	}

	/**
	 * Reachable with an email address.
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function can_reach( $sub ) {
		return (bool) $sub->email;
	}

	/**
	 * Deliver.
	 *
	 * @param object $sub     Subscription.
	 * @param string $event   Event.
	 * @param array  $message Message.
	 * @return array
	 */
	public function deliver( $sub, $event, array $message ) {
		return array( $sub->email, EmailChannel::send( $sub->email, $message['subject'], $message['html'] ) );
	}
}
