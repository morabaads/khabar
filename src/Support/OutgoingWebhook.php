<?php
/**
 * Outgoing webhook notifier (n8n / Zapier / CRM).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
/**
 * Outgoing webhook notifier (n8n / Zapier / CRM).
 */
final class OutgoingWebhook {

	/**
	 * Fire outgoing webhook (non-blocking).
	 *
	 * @param string $event Event.
	 * @param array  $data  Payload.
	 */
	public static function send( $event, $data ) {
		$url = Settings::get( 'webhook_url' );
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
}
