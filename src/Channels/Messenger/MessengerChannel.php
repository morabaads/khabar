<?php
/**
 * Messenger channels (Telegram & Bale): reaching a subscription and delivering a notification.
 *
 * Personal messages go by phone number through Bale Safir when configured, otherwise to the chat
 * the customer linked by starting the bot with a one-time deep link:
 *   1. The visitor clicks "connect" → a one-time code bound to their owner key is created.
 *   2. They open https://t.me/<bot>?start=<code> (or ble.ir) and press Start.
 *   3. The bot webhook receives "/start <code>" → the chat id is stored for that owner.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;

/**
 * Messenger delivery.
 */
final class MessengerChannel {

	/**
	 * Can a subscription be reached on this messenger?
	 *
	 * @param string $network Network.
	 * @param object $sub     Subscription.
	 * @return bool
	 */
	public static function can_reach( $network, $sub ) {
		if ( MessengerConfig::phone_ready( $network ) && $sub->phone ) {
			return true;
		}
		return (bool) LinkRepository::chat_id( ChannelManager::owner_of( $sub ), $network );
	}

	/**
	 * Deliver a notification to a subscription.
	 *
	 * @param string $network Network.
	 * @param object $sub     Subscription.
	 * @param array  $message Built message (subject, text, url).
	 * @return array [ recipient, true|WP_Error ]
	 */
	public static function deliver( $network, $sub, $message ) {
		if ( MessengerConfig::phone_ready( $network ) && $sub->phone ) {
			return array( $sub->phone, SafirClient::send( $sub->phone, $message ) );
		}
		$owner = ChannelManager::owner_of( $sub );
		return array( LinkRepository::chat_id( $owner, $network ), self::send_to_owner( $network, $owner, $message ) );
	}

	/**
	 * Send a notification to an owner's linked chat.
	 *
	 * @param string $network   Network.
	 * @param string $owner_key Owner.
	 * @param array  $message   Built message.
	 * @return true|\WP_Error
	 */
	public static function send_to_owner( $network, $owner_key, $message ) {
		list( $text, $url ) = self::compose( $message );
		return BotApi::send( $network, LinkRepository::chat_id( $owner_key, $network ), $text, $url );
	}

	/**
	 * Chat text of a notification: "🔔 subject" on top; when the link becomes a button, the raw
	 * link line is dropped from the text.
	 *
	 * @param array $message subject, text, url.
	 * @return array [ text, url ]
	 */
	public static function compose( $message ) {
		$subject = isset( $message['subject'] ) ? (string) $message['subject'] : '';
		$text    = isset( $message['text'] ) ? (string) $message['text'] : '';
		$url     = isset( $message['url'] ) ? (string) $message['url'] : '';
		$text    = '' !== $subject ? '🔔 ' . $subject . "\n\n" . $text : $text;
		if ( BotApi::is_button_url( $url ) ) {
			$text = trim( str_replace( $url, '', $text ) );
		}
		return array( $text, $url );
	}
}
