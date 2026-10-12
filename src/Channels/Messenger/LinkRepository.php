<?php
/**
 * Links between a customer (owner key) and their bot chat, and the one-time start codes.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Support\Clock;
/**
 * Links between a customer (owner key) and their bot chat, and the one-time start codes.
 */
final class LinkRepository {

	const CODE_TTL    = 30 * MINUTE_IN_SECONDS;
	const CODE_PREFIX = 'yadambiar_link_';

	/**
	 * Create a one-time start code for an owner.
	 *
	 * @param string $owner_key Owner.
	 * @return string
	 */
	public static function create_code( $owner_key ) {
		$code = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( self::CODE_PREFIX . $code, $owner_key, self::CODE_TTL );
		return $code;
	}

	/**
	 * Deep link to start the bot.
	 *
	 * @param string $network   Network.
	 * @param string $owner_key Owner.
	 * @return string
	 */
	public static function deep_link( $network, $owner_key ) {
		$n = MessengerConfig::network( $network );
		if ( ! $n || ! $n['link'] || ! $n['bot'] || ! $owner_key ) {
			return '';
		}
		return sprintf( $n['link'], rawurlencode( $n['bot'] ), self::create_code( $owner_key ) );
	}

	/**
	 * Linked chat id.
	 *
	 * @param string $owner_key Owner.
	 * @param string $network   Network.
	 * @return string
	 */
	public static function chat_id( $owner_key, $network ) {
		global $wpdb;
		if ( ! $owner_key ) {
			return '';
		}
		$table = Installer::table( 'messenger' );
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT chat_id FROM {$table} WHERE owner_key = %s AND network = %s", $owner_key, $network ) ); // phpcs:ignore
	}

	/**
	 * Store a link.
	 *
	 * @param string $owner_key Owner.
	 * @param string $network   Network.
	 * @param string $chat_id   Chat id.
	 * @param string $username  Username.
	 */
	public static function link( $owner_key, $network, $chat_id, $username = '' ) {
		global $wpdb;
		$table = Installer::table( 'messenger' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE owner_key = %s AND network = %s", $owner_key, $network ) ); // phpcs:ignore
		$wpdb->insert( // phpcs:ignore
			$table,
			array(
				'owner_key'  => $owner_key,
				'network'    => $network,
				'chat_id'    => (string) $chat_id,
				'username'   => mb_substr( (string) $username, 0, 100 ),
				'created_at' => Clock::now(),
			)
		);
	}

	/**
	 * Remove links of a chat (when the user sends /stop or blocks the bot).
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat id.
	 */
	public static function unlink_chat( $network, $chat_id ) {
		global $wpdb;
		$wpdb->delete( Installer::table( 'messenger' ), array( 'network' => $network, 'chat_id' => (string) $chat_id ) ); // phpcs:ignore
	}

	/**
	 * Move links from a guest key to a user key (on login).
	 *
	 * @param string $from Old owner key.
	 * @param string $to   New owner key.
	 */
	public static function move_owner( $from, $to ) {
		global $wpdb;
		$table = Installer::table( 'messenger' );
		foreach ( array_keys( MessengerConfig::networks() ) as $network ) {
			if ( self::chat_id( $to, $network ) ) {
				$wpdb->delete( $table, array( 'owner_key' => $from, 'network' => $network ) ); // phpcs:ignore
			}
		}
		$wpdb->update( $table, array( 'owner_key' => $to ), array( 'owner_key' => $from ) ); // phpcs:ignore
	}

	/**
	 * Owner of a start code; the code works once.
	 *
	 * @param string $code Code from "/start <code>".
	 * @return string Owner key, or '' when unknown / expired.
	 */
	public static function consume_code( $code ) {
		$code = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $code ) );
		if ( '' === $code ) {
			return '';
		}
		$owner = get_transient( self::CODE_PREFIX . $code );
		delete_transient( self::CODE_PREFIX . $code );
		return is_string( $owner ) ? $owner : '';
	}

	/**
	 * Owner linked to a chat.
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat id.
	 * @return string
	 */
	public static function owner_of_chat( $network, $chat_id ) {
		global $wpdb;
		$table = Installer::table( 'messenger' );
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT owner_key FROM {$table} WHERE network = %s AND chat_id = %s", $network, (string) $chat_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Number of customers linked to a network's bot.
	 *
	 * @param string $network Network.
	 * @return int
	 */
	public static function count( $network ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Installer::table( 'messenger' ) . ' WHERE network = %s', $network ) ); // phpcs:ignore WordPress.DB
	}
}
