<?php
/**
 * Helpers.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Utils {

	const GUEST_COOKIE  = 'khabar_gk';
	const ACCESS_COOKIE = 'khabar_access';

	/**
	 * Convert Persian / Arabic digits to latin.
	 *
	 * @param string $str Input.
	 * @return string
	 */
	public static function latin_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $fa, $en, (string) $str );
	}

	/**
	 * Normalize a mobile number. Returns '' when invalid.
	 *
	 * @param string    $phone  Raw phone.
	 * @param bool|null $strict Iranian validation.
	 * @return string
	 */
	public static function normalize_phone( $phone, $strict = null ) {
		if ( null === $strict ) {
			$strict = (bool) Khabar_Settings::get( 'strict_iran_mobile', 1 );
		}
		$phone = preg_replace( '/[^0-9+]/', '', self::latin_digits( $phone ) );
		if ( '' === $phone ) {
			return '';
		}
		if ( $strict ) {
			$phone = preg_replace( '/^(\+98|0098|98)/', '0', $phone );
			if ( preg_match( '/^9\d{9}$/', $phone ) ) {
				$phone = '0' . $phone;
			}
			return preg_match( '/^09\d{9}$/', $phone ) ? $phone : '';
		}
		return preg_match( '/^\+?\d{7,15}$/', $phone ) ? $phone : '';
	}

	/**
	 * Convert local Iranian mobile to international (no plus) for APIs such as WhatsApp.
	 *
	 * @param string $phone   Phone.
	 * @param string $country Country code.
	 * @return string
	 */
	public static function international_phone( $phone, $country = '98' ) {
		$phone = ltrim( (string) $phone, '+' );
		if ( 0 === strpos( $phone, '00' ) ) {
			return substr( $phone, 2 );
		}
		if ( 0 === strpos( $phone, '0' ) ) {
			return $country . substr( $phone, 1 );
		}
		return $phone;
	}

	/**
	 * Mask a recipient for display.
	 *
	 * @param string $value Phone or email.
	 * @return string
	 */
	public static function mask( $value ) {
		if ( false !== strpos( $value, '@' ) ) {
			list( $user, $domain ) = explode( '@', $value, 2 );
			return substr( $user, 0, 2 ) . str_repeat( '*', max( 1, strlen( $user ) - 2 ) ) . '@' . $domain;
		}
		$len = strlen( $value );
		return $len > 7 ? substr( $value, 0, 4 ) . str_repeat( '*', $len - 7 ) . substr( $value, -3 ) : $value;
	}

	/**
	 * Current GMT mysql time.
	 *
	 * @param int $offset Seconds offset.
	 * @return string
	 */
	public static function now( $offset = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() + $offset );
	}

	/**
	 * Client IP.
	 *
	 * @return string
	 */
	public static function ip() {
		// Forwarded headers are client-controlled; only REMOTE_ADDR is trusted unless a filter says otherwise
		// (e.g. behind a known reverse proxy / CDN: add_filter( 'khabar_client_ip', ... )).
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'khabar_client_ip', $ip );
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

	/**
	 * Plain-text price for messages.
	 *
	 * @param float|string $price Price.
	 * @return string
	 */
	public static function price_text( $price ) {
		if ( '' === $price || null === $price ) {
			return '';
		}
		$text = trim( html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ) );
		// Stores using "left/right" (no space) currency positions glue the symbol to the digits; keep them readable.
		$text = preg_replace( '/([\d\x{06F0}-\x{06F9}])(\p{L})/u', '$1 $2', $text );
		return preg_replace( '/(\p{L})([\d\x{06F0}-\x{06F9}])/u', '$1 $2', $text );
	}

	/**
	 * Parse a user supplied price (may contain separators and Persian digits).
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	public static function parse_price( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = self::latin_digits( (string) $value );
		$value = str_replace( array( ',', '٬', '،', ' ' ), '', $value );
		$value = preg_replace( '/[^0-9.]/', '', $value );
		return '' === $value ? null : (float) $value;
	}

	/**
	 * Human label for a set of attributes.
	 *
	 * @param array $attributes attribute_pa_color => value.
	 * @return string
	 */
	public static function attributes_label( $attributes ) {
		$parts = array();
		foreach ( (array) $attributes as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$taxonomy = str_replace( 'attribute_', '', $key );
			$label    = wc_attribute_label( $taxonomy );
			if ( taxonomy_exists( $taxonomy ) ) {
				$term  = get_term_by( 'slug', $value, $taxonomy );
				$value = $term ? $term->name : $value;
			}
			$parts[] = $label . ': ' . $value;
		}
		return implode( '، ', $parts );
	}

	/**
	 * Fill {placeholders}.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Vars.
	 * @return string
	 */
	public static function render( $template, $vars ) {
		$map = array();
		foreach ( $vars as $k => $v ) {
			$map[ '{' . $k . '}' ] = (string) $v;
		}
		$out = strtr( (string) $template, $map );
		// Collapse double spaces left by empty placeholders.
		return preg_replace( '/[ \t]{2,}/', ' ', $out );
	}

	/**
	 * Template path, overridable from the theme at yourtheme/khabar/{name}.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function template( $name ) {
		$theme = locate_template( 'khabar/' . $name );
		return apply_filters( 'khabar_template', $theme ? $theme : KHABAR_DIR . 'templates/' . $name, $name );
	}

	/**
	 * Fire outgoing webhook (non-blocking).
	 *
	 * @param string $event Event.
	 * @param array  $data  Payload.
	 */
	public static function webhook( $event, $data ) {
		$url = Khabar_Settings::get( 'webhook_url' );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return;
		}
		wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'event' => $event,
						'site'  => home_url(),
						'time'  => gmdate( 'c' ),
						'data'  => $data,
					)
				),
			)
		);
	}

	/**
	 * Queue an async/scheduled action using Action Scheduler when present.
	 *
	 * @param string $hook  Hook.
	 * @param array  $args  Args.
	 * @param int    $delay Delay seconds.
	 */
	public static function queue( $hook, $args = array(), $delay = 0 ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, 'khabar' ) ) {
				return;
			}
			as_schedule_single_action( time() + $delay, $hook, $args, 'khabar' );
			return;
		}
		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + $delay, $hook, $args );
		}
	}
}
