<?php
/**
 * Telegram / Bale Bot API client: method calls, messages with a link button, webhook registration.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

/**
 * Telegram / Bale Bot API client: method calls, messages with a link button, webhook registration.
 */
final class BotApi {

	/**
	 * Call a bot API method.
	 *
	 * @param string $network Network.
	 * @param string $method  Method (sendMessage, setWebhook…).
	 * @param array  $params  Params.
	 * @return array|WP_Error Decoded "result".
	 */
	public static function api( $network, $method, $params ) {
		$n = MessengerConfig::network( $network );
		if ( ! $n || ! $n['token'] ) {
			return new \WP_Error( 'yadambiar_msg', __( 'توکن ربات تنظیم نشده است.', 'yadambiar' ) );
		}
		$res = wp_remote_post(
			sprintf( $n['api'], $n['token'], $method ),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $params ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		// Telegram/Bale: {"ok":true,"result":…}.
		if ( $code >= 300 || ( is_array( $body ) && isset( $body['ok'] ) && ! $body['ok'] ) ) {
			$desc = is_array( $body ) && isset( $body['description'] ) ? $body['description'] : 'HTTP ' . $code;
			return new \WP_Error( 'yadambiar_msg', $n['label'] . ': ' . $desc );
		}
		return is_array( $body ) && isset( $body['result'] ) ? (array) $body['result'] : (array) $body;
	}

	/**
	 * Send a message (optionally with a URL button).
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat / channel id.
	 * @param string $text    Text.
	 * @param string $url     Button URL.
	 * @param string $button  Button label.
	 * @return true|WP_Error
	 */
	public static function send( $network, $chat_id, $text, $url = '', $button = '' ) {
		if ( ! $chat_id ) {
			return new \WP_Error( 'yadambiar_msg', __( 'حساب پیام‌رسان متصل نیست.', 'yadambiar' ) );
		}
		$params = array(
			'chat_id'                  => $chat_id,
			'text'                     => $text,
			'disable_web_page_preview' => false,
		);
		if ( self::is_button_url( $url ) ) {
			$params['reply_markup'] = self::button( $url, $button );
		} elseif ( $url ) {
			$params['text'] .= "\n" . $url;
		}
		$res = self::api( $network, 'sendMessage', $params );
		if ( is_wp_error( $res ) ) {
			// The user blocked the bot → forget the link.
			if ( false !== stripos( $res->get_error_message(), 'blocked' ) ) {
				LinkRepository::unlink_chat( $network, $chat_id );
			}
			return $res;
		}
		return true;
	}

	/**
	 * Secret used to authenticate webhook calls.
	 *
	 * @param string $network Network.
	 * @return string
	 */
	public static function secret( $network ) {
		return substr( hash_hmac( 'sha256', 'yadambiar-bot-' . $network, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Webhook URL for a network.
	 *
	 * @param string $network Network.
	 * @return string
	 */
	public static function webhook_url( $network ) {
		return add_query_arg( 'secret', self::secret( $network ), rest_url( 'yadambiar/v1/bot/' . $network ) );
	}

	/**
	 * Register webhooks with Telegram / Bale.
	 *
	 * @return array network => true|WP_Error
	 */
	public static function register_webhooks() {
		$out = array();
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			$n = MessengerConfig::network( $network );
			if ( ! $n['token'] ) {
				continue;
			}
			$params = array(
				'url'             => self::webhook_url( $network ),
				'allowed_updates' => array( 'message' ),
			);
			if ( 'telegram' === $network ) {
				$params['secret_token'] = self::secret( $network );
			}
			$res             = self::api( $network, 'setWebhook', $params );
			$out[ $network ] = is_wp_error( $res ) ? $res : true;
		}
		return $out;
	}

	/**
	 * Can a URL be an inline button (the messengers accept https only)?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_button_url( $url ) {
		return is_string( $url ) && 0 === strpos( $url, 'https://' );
	}

	/**
	 * Inline keyboard with one link button.
	 *
	 * @param string $url   URL.
	 * @param string $label Label.
	 * @return array
	 */
	public static function button( $url, $label = '' ) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text' => $label ? $label : __( '🛒 مشاهده و خرید', 'yadambiar' ),
						'url'  => $url,
					),
				),
			),
		);
	}
}
