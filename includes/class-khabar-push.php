<?php
/**
 * Web Push (VAPID, payload-less). The service worker fetches the latest on-site
 * notification of its owner when a push arrives, so no payload encryption is needed.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Push {

	const OPTION = 'khabar_vapid';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'serve_worker' ), 1 );
	}

	/**
	 * Is push enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Khabar_Settings::has( 'channels_enabled', 'push' ) && self::keys();
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

	/**
	 * Store a browser subscription.
	 *
	 * @param string $owner_key Owner.
	 * @param array  $sub       endpoint, keys.p256dh, keys.auth.
	 * @return bool
	 */
	public static function save_subscription( $owner_key, $sub ) {
		global $wpdb;
		$endpoint = isset( $sub['endpoint'] ) ? esc_url_raw( $sub['endpoint'] ) : '';
		if ( ! $owner_key || ! $endpoint || 0 !== strpos( $endpoint, 'https://' ) ) {
			return false;
		}
		$table = Khabar_Install::table( 'push' );
		$hash  = hash( 'sha256', $endpoint );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE endpoint_hash = %s", $hash ) ); // phpcs:ignore
		return (bool) $wpdb->insert( // phpcs:ignore
			$table,
			array(
				'owner_key'     => $owner_key,
				'endpoint'      => $endpoint,
				'endpoint_hash' => $hash,
				'p256dh'        => isset( $sub['keys']['p256dh'] ) ? sanitize_text_field( $sub['keys']['p256dh'] ) : '',
				'auth'          => isset( $sub['keys']['auth'] ) ? sanitize_text_field( $sub['keys']['auth'] ) : '',
				'created_at'    => Khabar_Utils::now(),
			)
		);
	}

	/**
	 * Does the owner have at least one browser subscription.
	 *
	 * @param string $owner_key Owner.
	 * @return bool
	 */
	public static function has_subscription( $owner_key ) {
		global $wpdb;
		if ( ! $owner_key ) {
			return false;
		}
		$table = Khabar_Install::table( 'push' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE owner_key = %s LIMIT 1", $owner_key ) ); // phpcs:ignore
	}

	/**
	 * Ping all browsers of an owner.
	 *
	 * @param string $owner_key Owner.
	 * @return true|WP_Error
	 */
	public static function notify_owner( $owner_key ) {
		global $wpdb;
		$keys = self::keys();
		if ( ! $keys ) {
			return new WP_Error( 'khabar_push', __( 'کلید VAPID موجود نیست (OpenSSL).', 'khabar' ) );
		}
		$table = Khabar_Install::table( 'push' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, endpoint FROM {$table} WHERE owner_key = %s", $owner_key ) ); // phpcs:ignore
		if ( ! $rows ) {
			return new WP_Error( 'khabar_push', __( 'کاربر اجازه پوش نداده است.', 'khabar' ) );
		}
		$ok = false;
		foreach ( $rows as $row ) {
			$jwt = self::jwt( $row->endpoint, $keys );
			if ( ! $jwt ) {
				continue;
			}
			$res  = wp_remote_post(
				$row->endpoint,
				array(
					'timeout' => 10,
					'headers' => array(
						'TTL'            => '86400',
						'Urgency'        => 'high',
						'Content-Length' => '0',
						'Authorization'  => 'vapid t=' . $jwt . ', k=' . $keys['public'],
					),
					'body'    => '',
				)
			);
			$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
			if ( in_array( $code, array( 404, 410 ), true ) ) {
				$wpdb->delete( $table, array( 'id' => $row->id ) ); // phpcs:ignore
			}
			if ( $code >= 200 && $code < 300 ) {
				$ok = true;
			}
		}
		return $ok ? true : new WP_Error( 'khabar_push', __( 'ارسال پوش ناموفق بود.', 'khabar' ) );
	}

	/**
	 * Owner of a stored endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @return string
	 */
	public static function owner_of_endpoint( $endpoint ) {
		global $wpdb;
		$table = Khabar_Install::table( 'push' );
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT owner_key FROM {$table} WHERE endpoint_hash = %s", hash( 'sha256', (string) $endpoint ) ) ); // phpcs:ignore
	}

	/**
	 * Latest not-yet-pushed notification for a device.
	 *
	 * @param string $endpoint Endpoint.
	 * @return array|null
	 */
	public static function latest_for_endpoint( $endpoint ) {
		global $wpdb;
		$owner = self::owner_of_endpoint( $endpoint );
		if ( ! $owner ) {
			return null;
		}
		$table = Khabar_Install::table( 'notifications' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE owner_key = %s AND created_at > %s ORDER BY pushed ASC, id DESC LIMIT 1", $owner, Khabar_Utils::now( -DAY_IN_SECONDS ) ) ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		$wpdb->update( $table, array( 'pushed' => 1 ), array( 'id' => $row->id ) ); // phpcs:ignore
		return array(
			'title' => $row->title,
			'body'  => $row->message,
			'url'   => $row->url,
			'icon'  => Khabar_Settings::get( 'push_icon' ) ? Khabar_Settings::get( 'push_icon' ) : get_site_icon_url( 192 ),
		);
	}

	/**
	 * Serve the service worker from the site root so its scope covers the whole site.
	 */
	public static function serve_worker() {
		if ( empty( $_GET['khabar_sw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache' );
		$latest = esc_url_raw( rest_url( 'khabar/v1/push/latest' ) );
		echo "/* Khabar service worker */\n";
		echo 'var KHABAR_LATEST = ' . wp_json_encode( $latest ) . ";\n";
		echo file_get_contents( KHABAR_DIR . 'assets/js/sw.js' ); // phpcs:ignore
		exit;
	}
}
