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

	const LEGACY_GUEST_COOKIE = 'khabar_gk';

	/**
	 * Alphanumeric value of a cookie ('' when missing).
	 *
	 * @param string $name Cookie name.
	 * @return string
	 */
	public static function cookie( $name ) {
		return isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ? (string) preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_COOKIE[ $name ] ) ) : '';
	}

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
		$key = self::cookie( self::GUEST_COOKIE );
		if ( 32 !== strlen( $key ) ) {
			// Guests of the previous plugin version keep their requests (same key, new cookie name).
			$key = self::cookie( self::LEGACY_GUEST_COOKIE );
			if ( 32 === strlen( $key ) && $create ) {
				self::set_cookie( self::GUEST_COOKIE, $key, YEAR_IN_SECONDS );
			}
		}
		if ( 32 === strlen( $key ) ) {
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
		// HttpOnly + SameSite=Lax: scripts cannot read it and other sites cannot send requests with it.
		setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + $expire,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
