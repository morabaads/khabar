<?php
/**
 * The on-site notification of a send, created once and shared by the bell and push
 * (the service worker shows the latest bell item after a push ping).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Delivery;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Channels\OnsiteChannel;

/**
 * Per-send bell item.
 */
final class Inbox {

	/**
	 * Created items in this request: send key => notification id|false.
	 *
	 * @var array
	 */
	private static $created = array();

	/**
	 * Make sure the bell item for this send exists.
	 *
	 * @param object $sub     Subscription.
	 * @param array  $message Message.
	 * @return int|false Notification id.
	 */
	public static function ensure( $sub, array $message ) {
		$key = (int) $sub->id . '|' . md5( $message['subject'] . "\0" . $message['text'] );
		if ( ! array_key_exists( $key, self::$created ) ) {
			self::$created[ $key ] = OnsiteChannel::create( ChannelManager::owner_of( $sub ), $sub->id, $message['subject'], $message['text'], $message['url'] );
		}
		return self::$created[ $key ];
	}
}
