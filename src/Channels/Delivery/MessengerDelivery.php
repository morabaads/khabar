<?php
/**
 * Telegram / Bale delivery (Safir by phone, or the linked chat).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Delivery;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Messenger\MessengerChannel;
use YadamBiar\Contracts\NotificationChannel;

/**
 * Messenger adapter.
 */
final class MessengerDelivery implements NotificationChannel {

	/**
	 * Network.
	 *
	 * @var string
	 */
	private $network;

	/**
	 * Constructor.
	 *
	 * @param string $network telegram|bale.
	 */
	public function __construct( $network ) {
		$this->network = $network;
	}

	/**
	 * Key.
	 *
	 * @return string
	 */
	public function key() {
		return $this->network;
	}

	/**
	 * Reachable by phone (Safir) or a linked chat.
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function can_reach( $sub ) {
		return MessengerChannel::can_reach( $this->network, $sub );
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
		return MessengerChannel::deliver( $this->network, $sub, $message );
	}
}
