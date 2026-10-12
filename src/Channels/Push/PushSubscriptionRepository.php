<?php
/**
 * Browser push subscriptions per owner; only real push services are accepted as endpoints (no SSRF).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Push;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Support\Clock;
/**
 * Browser push subscriptions per owner; only real push services are accepted as endpoints (no SSRF).
 */
final class PushSubscriptionRepository {

	/**
	 * Is the endpoint a known push service (prevents using the server as an SSRF proxy)?
	 *
	 * @param string $endpoint Endpoint URL.
	 * @return bool
	 */
	public static function endpoint_allowed( $endpoint ) {
		$parts = wp_parse_url( $endpoint );
		if ( empty( $parts['host'] ) || 'https' !== ( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ) || ! empty( $parts['user'] ) || ( ! empty( $parts['port'] ) && 443 !== (int) $parts['port'] ) ) {
			return false;
		}
		$host    = strtolower( $parts['host'] );
		$allowed = apply_filters(
			'yadambiar_push_hosts',
			array( 'fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com' )
		);
		foreach ( $allowed as $domain ) {
			if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
				return true;
			}
		}
		return false;
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
		if ( ! $owner_key || ! $endpoint || ! self::endpoint_allowed( $endpoint ) ) {
			return false;
		}
		$table = Installer::table( 'push' );
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
				'created_at'    => Clock::now(),
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
		$table = Installer::table( 'push' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE owner_key = %s LIMIT 1", $owner_key ) ); // phpcs:ignore
	}

	/**
	 * Owner of a stored endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @return string
	 */
	public static function owner_of_endpoint( $endpoint ) {
		global $wpdb;
		$table = Installer::table( 'push' );
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT owner_key FROM {$table} WHERE endpoint_hash = %s", hash( 'sha256', (string) $endpoint ) ) ); // phpcs:ignore
	}

	/**
	 * Browser endpoints of an owner.
	 *
	 * @param string $owner_key Owner.
	 * @return object[] id, endpoint
	 */
	public static function endpoints( $owner_key ) {
		global $wpdb;
		$table = Installer::table( 'push' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, endpoint FROM {$table} WHERE owner_key = %s", $owner_key ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Forget a subscription.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Installer::table( 'push' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}
}
