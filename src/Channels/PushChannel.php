<?php
/**
 * Web Push (VAPID, payload-less). The service worker fetches the latest on-site
 * notification of its owner when a push arrives, so no payload encryption is needed.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Push\PushSubscriptionRepository;
use YadamBiar\Channels\Push\VapidKeys;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Media;

final class PushChannel {

	/**
	 * Is push enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Settings::has( 'channels_enabled', 'push' ) && VapidKeys::keys();
	}

	/**
	 * Ping all browsers of an owner.
	 *
	 * @param string $owner_key Owner.
	 * @return true|WP_Error
	 */
	public static function notify_owner( $owner_key ) {
		$keys = VapidKeys::keys();
		if ( ! $keys ) {
			return new \WP_Error( 'yadambiar_push', __( 'کلید VAPID موجود نیست (OpenSSL).', 'yadambiar' ) );
		}
		$rows = PushSubscriptionRepository::endpoints( $owner_key );
		if ( ! $rows ) {
			return new \WP_Error( 'yadambiar_push', __( 'کاربر اجازه پوش نداده است.', 'yadambiar' ) );
		}
		$ok = false;
		foreach ( $rows as $row ) {
			$jwt = VapidKeys::jwt( $row->endpoint, $keys );
			if ( ! $jwt ) {
				continue;
			}
			$res  = wp_safe_remote_post(
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
				PushSubscriptionRepository::delete( $row->id ); // Expired / unsubscribed browser.
			}
			if ( $code >= 200 && $code < 300 ) {
				$ok = true;
			}
		}
		return $ok ? true : new \WP_Error( 'yadambiar_push', __( 'ارسال پوش ناموفق بود.', 'yadambiar' ) );
	}

	/**
	 * Latest not-yet-pushed notification for a device.
	 *
	 * @param string $endpoint Endpoint.
	 * @return array|null
	 */
	public static function latest_for_endpoint( $endpoint ) {
		$owner = PushSubscriptionRepository::owner_of_endpoint( $endpoint );
		$row   = $owner ? OnsiteChannel::next_for_push( $owner ) : null;
		if ( ! $row ) {
			return null;
		}
		return array(
			'title' => $row->title,
			'body'  => $row->message,
			'url'   => $row->url,
			'icon'  => Settings::get( 'push_icon' ) ? Media::sized_url( Settings::get( 'push_icon' ) ) : get_site_icon_url( 192 ),
		);
	}

}
