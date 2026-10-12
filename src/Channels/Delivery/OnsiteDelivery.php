<?php
/**
 * On-site (bell) delivery.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Delivery;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Contracts\NotificationChannel;

/**
 * Bell adapter.
 */
final class OnsiteDelivery implements NotificationChannel {

	/**
	 * Key.
	 *
	 * @return string
	 */
	public function key() {
		return 'onsite';
	}

	/**
	 * Reachable when the request has an owner (user or browser).
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function can_reach( $sub ) {
		return '' !== ChannelManager::owner_of( $sub );
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
		$id = Inbox::ensure( $sub, $message );
		return array( ChannelManager::owner_of( $sub ), $id ? true : new \WP_Error( 'yadambiar_onsite', 'db' ) );
	}
}
