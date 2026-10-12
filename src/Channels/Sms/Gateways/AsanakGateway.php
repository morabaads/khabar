<?php
/**
 * Asanak (v1rest, text only).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Asanak.
 */
final class AsanakGateway extends AbstractGateway {

	const API = 'https://panel.asanak.ir/webservice/v1rest/sendsms';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		$res = $this->http->request(
			self::API,
			array(
				'body' => array(
					'username'    => $this->setting( 'sms_username' ),
					'password'    => $this->setting( 'sms_password' ),
					'source'      => $this->setting( 'sms_sender' ),
					'destination' => $message->to,
					'message'     => $message->text,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		// Success: a list of message ids; failure: an object / text describing the error.
		$list = isset( $res['raw'] ) ? array() : $res;
		$ok   = $list && array_keys( $list ) === range( 0, count( $list ) - 1 ) && is_numeric( reset( $list ) );
		return $ok ? true : $this->error( 'Asanak: ' . mb_substr( wp_json_encode( $res, JSON_UNESCAPED_UNICODE ), 0, 300 ) );
	}
}
