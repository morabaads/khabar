<?php
/**
 * Browser push delivery (VAPID ping; the payload is the bell item).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Delivery;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Channels\Push\PushSubscriptionRepository;
use YadamBiar\Contracts\NotificationChannel;

/**
 * Push adapter.
 */
final class PushDelivery implements NotificationChannel {

	/**
	 * Key.
	 *
	 * @return string
	 */
	public function key() {
		return 'push';
	}

	/**
	 * Reachable when the owner granted browser permission.
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function can_reach( $sub ) {
		$owner = ChannelManager::owner_of( $sub );
		return '' !== $owner && PushSubscriptionRepository::has_subscription( $owner );
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
		Inbox::ensure( $sub, $message );
		$owner = ChannelManager::owner_of( $sub );
		return array( $owner, PushChannel::notify_owner( $owner ) );
	}
}
