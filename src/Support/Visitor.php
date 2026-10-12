<?php
/**
 * Current visitor: client IP, guest owner key and cookies.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Current visitor: client IP, guest owner key and cookies.
 */
final class Visitor {

	const GUEST_COOKIE  = 'yadambiar_gk';

	const ACCESS_COOKIE = 'yadambiar_access';

	/**
	 * Client IP.
	 *
	 * @return string
	 */
	public static function ip() {
		// Forwarded headers are client-controlled; only REMOTE_ADDR is trusted unless a filter says otherwise
		// (e.g. behind a known reverse proxy / CDN: add_filter( 'yadambiar_client_ip', ... )).
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'yadambiar_client_ip', $ip );
	}

	/**
	 * Owner key of the current visitor (user or cookie based guest key).
	 *
	 * @param bool $create Create guest cookie if missing.
	 * @return string
	 */
	public static function owner_key( $create = false ) {
		$uid = get_current_user_id();
		if ( $uid ) {
			return 'u:' . $uid;
		}
		$key = isset( $_COOKIE[ self::GUEST_COOKIE ] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_COOKIE[ self::GUEST_COOKIE ] ) ) : '';
		if ( strlen( $key ) === 32 ) {
			return 'g:' . $key;
		}
		if ( ! $create ) {
			return '';
		}
		$key = wp_generate_password( 32, false, false );
		self::set_cookie( self::GUEST_COOKIE, $key, YEAR_IN_SECONDS );
		$_COOKIE[ self::GUEST_COOKIE ] = $key;
		return 'g:' . $key;
	}

	/**
	 * Set a cookie safely.
	 *
	 * @param string $name   Name.
	 * @param string $value  Value.
	 * @param int    $expire Seconds.
	 */
	public static function set_cookie( $name, $value, $expire ) {
		if ( headers_sent() ) {
			return;
		}
		setcookie( $name, $value, time() + $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}
}
