<?php
/**
 * Custom HTTP gateway configured by the admin ({to} / {message} placeholders).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Generic webhook gateway.
 */
final class WebhookGateway extends AbstractGateway {

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		$url = $this->setting( 'sms_webhook_url' );
		if ( ! $url ) {
			return $this->error( __( 'آدرس وب‌سرویس پیامک تنظیم نشده است.', 'yadambiar' ) );
		}
		$url  = strtr(
			$url,
			array(
				'{to}'      => rawurlencode( $message->to ),
				'{message}' => rawurlencode( $message->text ),
			)
		);
		$body = $this->setting( 'sms_webhook_body' );
		$args = array(
			'method'  => 'GET',
			'timeout' => 15,
		);
		if ( '' !== $body ) {
			$args = array(
				'method'  => 'POST',
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => strtr(
					$body,
					array(
						'{to}'      => trim( wp_json_encode( $message->to ), '"' ),
						'{message}' => trim( wp_json_encode( $message->text, JSON_UNESCAPED_UNICODE ), '"' ),
					)
				),
			);
		}
		// Admin-typed URL: refuse local / private network targets.
		$res = wp_safe_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return ( $code >= 200 && $code < 300 ) ? true : $this->error( 'HTTP ' . $code );
	}
}
