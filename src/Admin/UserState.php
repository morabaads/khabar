<?php
/**
 * Short-lived per-admin state between a POST action and the page it redirects to
 * (last test SMS, bot check results).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user transients.
 */
final class UserState {

	const SMS_TEST  = 'sms_test';
	const BOT_CHECK = 'botcheck';

	/**
	 * Store a value.
	 *
	 * @param string $name  Name.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Seconds.
	 */
	public static function set( $name, $value, $ttl ) {
		set_transient( self::key( $name ), $value, $ttl );
	}

	/**
	 * Read a value.
	 *
	 * @param string $name Name.
	 * @return mixed|null
	 */
	public static function get( $name ) {
		$value = get_transient( self::key( $name ) );
		return false === $value ? null : $value;
	}

	/**
	 * Read and forget a value.
	 *
	 * @param string $name Name.
	 * @return mixed|null
	 */
	public static function pull( $name ) {
		$value = self::get( $name );
		delete_transient( self::key( $name ) );
		return $value;
	}

	/**
	 * Transient key.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function key( $name ) {
		return 'yadambiar_' . $name . '_' . get_current_user_id();
	}
}
