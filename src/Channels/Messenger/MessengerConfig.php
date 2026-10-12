<?php
/**
 * Messenger settings: networks (Telegram, Bale), what is configured and how customers are reached.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
/**
 * Messenger settings: networks (Telegram, Bale), what is configured and how customers are reached.
 */
final class MessengerConfig {

	/**
	 * Network definitions.
	 *
	 * @return array
	 */
	public static function networks() {
		$tg_base = untrailingslashit( Settings::get( 'telegram_api_base', 'https://api.telegram.org' ) );
		return array(
			'telegram' => array(
				'label'    => __( 'تلگرام', 'yadambiar' ),
				'token'    => (string) Settings::get( 'telegram_token' ),
				'bot'      => ltrim( (string) Settings::get( 'telegram_bot' ), '@' ),
				'api'      => ( $tg_base ? $tg_base : 'https://api.telegram.org' ) . '/bot%s/%s',
				'link'     => 'https://t.me/%s?start=%s',
				'personal' => true,
				'channel'  => self::normalize_chat( Settings::get( 'telegram_channel' ) ),
			),
			'bale'     => array(
				'label'    => __( 'بله', 'yadambiar' ),
				'token'    => (string) Settings::get( 'bale_token' ),
				'bot'      => ltrim( (string) Settings::get( 'bale_bot' ), '@' ),
				'api'      => 'https://tapi.bale.ai/bot%s/%s',
				'link'     => 'https://ble.ir/%s?start=%s',
				'personal' => true,
				'channel'  => self::normalize_chat( Settings::get( 'bale_channel' ) ),
			),
		);
	}

	/**
	 * Network config.
	 *
	 * @param string $network Network.
	 * @return array|null
	 */
	public static function network( $network ) {
		$all = self::networks();
		return isset( $all[ $network ] ) ? $all[ $network ] : null;
	}

	/**
	 * Is personal messaging configured for a network.
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function personal_ready( $network ) {
		$n = self::network( $network );
		return $n && $n['personal'] && $n['token'] && $n['bot'] && Settings::has( 'channels_enabled', $network );
	}

	/**
	 * Bot ("بازو") id for Safir: the setting, or the number before ":" in the bot token.
	 *
	 * @return int
	 */
	public static function safir_bot_id() {
		$id = preg_replace( '/\D/', '', (string) Settings::get( 'bale_safir_bot_id', '' ) );
		if ( '' === $id && preg_match( '/^(\d+):/', trim( (string) Settings::get( 'bale_token', '' ) ), $m ) ) {
			$id = $m[1];
		}
		return (int) $id;
	}

	/**
	 * Can Bale messages go to phone numbers (Safir configured and Bale enabled)?
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function phone_ready( $network ) {
		return 'bale' === $network && Settings::has( 'channels_enabled', 'bale' ) && '' !== trim( (string) Settings::get( 'bale_safir_key', '' ) ) && self::safir_bot_id() > 0;
	}

	/**
	 * Offer this messenger to customers at all (by phone through Safir, or by linking the bot).
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function available( $network ) {
		return self::phone_ready( $network ) || self::personal_ready( $network );
	}

	/**
	 * Does the customer have to link the bot (press Start) to get personal messages?
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function needs_link( $network ) {
		return self::personal_ready( $network ) && ! self::phone_ready( $network );
	}

	/**
	 * Normalize a chat / channel id: numeric ids stay as they are; a public username (with or without
	 * "@", or a pasted t.me / ble.ir link) becomes "@username", the form the Bot APIs expect.
	 *
	 * @param string $id Raw id.
	 * @return string
	 */
	public static function normalize_chat( $id ) {
		$id = trim( (string) $id );
		$id = preg_replace( '#^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me|ble\.ir|web\.bale\.ai)/+#i', '', $id );
		$id = trim( $id, "/ \t" );
		if ( '' === $id || preg_match( '/^-?\d+$/', $id ) ) {
			return $id;
		}
		return '@' . ltrim( $id, '@' );
	}
}
