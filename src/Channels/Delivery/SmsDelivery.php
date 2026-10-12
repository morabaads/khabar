<?php
/**
 * SMS delivery (pattern or text, per settings).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Delivery;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsChannel;
use YadamBiar\Contracts\NotificationChannel;

/**
 * SMS adapter.
 */
final class SmsDelivery implements NotificationChannel {

	/**
	 * Key.
	 *
	 * @return string
	 */
	public function key() {
		return 'sms';
	}

	/**
	 * Reachable with a mobile number.
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function can_reach( $sub ) {
		return (bool) $sub->phone;
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
		return array( $sub->phone, SmsChannel::send( $sub->phone, $message['text'], $event, $message['vars'] ) );
	}
}
