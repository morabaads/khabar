<?php
/**
 * VAPID key pair (P-256) and the signed JWT that authorizes a push to an endpoint.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Push;

defined( 'ABSPATH' ) || exit;

/**
 * VAPID key pair (P-256) and the signed JWT that authorizes a push to an endpoint.
 */
final class VapidKeys {

	const OPTION = 'yadambiar_vapid';

	/**
	 * Get or generate VAPID keys.
	 *
	 * @return array|null public (base64url raw point), pem (private key)
	 */
	public static function keys() {
		$keys = get_option( self::OPTION );
		if ( is_array( $keys ) && ! empty( $keys['public'] ) && ! empty( $keys['pem'] ) ) {
			return $keys;
		}
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			return null;
		}
		$key = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		if ( ! $key ) {
			return null;
		}
		$details = openssl_pkey_get_details( $key );
		openssl_pkey_export( $key, $pem );
		$x    = str_pad( $details['ec']['x'], 32, "\0", STR_PAD_LEFT );
		$y    = str_pad( $details['ec']['y'], 32, "\0", STR_PAD_LEFT );
		$keys = array(
			'public' => self::b64u( "\x04" . $x . $y ),
			'pem'    => $pem,
		);
		update_option( self::OPTION, $keys, false );
		return $keys;
	}

	/**
	 * Base64url helpers.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	public static function b64u( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Convert a DER ECDSA signature to raw r||s (64 bytes).
	 *
	 * @param string $der DER.
	 * @return string
	 */
	public static function der_to_raw( $der ) {
		$offset = 2;
		if ( ord( $der[1] ) & 0x80 ) {
			$offset += ord( $der[1] ) & 0x7f;
		}
		$parts = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$len     = ord( $der[ $offset + 1 ] );
			$int     = substr( $der, $offset + 2, $len );
			$int     = ltrim( $int, "\0" );
			$parts[] = str_pad( $int, 32, "\0", STR_PAD_LEFT );
			$offset += 2 + $len;
		}
		return $parts[0] . $parts[1];
	}

	/**
	 * VAPID JWT for an endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $keys     Keys.
	 * @return string|null
	 */
	public static function jwt( $endpoint, $keys ) {
		$parts    = wp_parse_url( $endpoint );
		$audience = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$header   = self::b64u( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$claims   = self::b64u(
			wp_json_encode(
				array(
					'aud' => $audience,
					'exp' => time() + 12 * HOUR_IN_SECONDS,
					'sub' => 'mailto:' . get_option( 'admin_email' ),
				)
			)
		);
		$input = $header . '.' . $claims;
		if ( ! openssl_sign( $input, $der, $keys['pem'], OPENSSL_ALGO_SHA256 ) ) {
			return null;
		}
		return $input . '.' . self::b64u( self::der_to_raw( $der ) );
	}
}
