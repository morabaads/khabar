<?php
/**
 * Payam Resan (APISend.aspx, text only).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Payam Resan.
 */
final class PayamResanGateway extends AbstractGateway {

	const API = 'http://www.payam-resan.com/APISend.aspx';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		$url = add_query_arg(
			array(
				'Username' => rawurlencode( $this->setting( 'sms_username' ) ),
				'Password' => rawurlencode( $this->setting( 'sms_password' ) ),
				'From'     => rawurlencode( $this->setting( 'sms_sender' ) ),
				'To'       => rawurlencode( $message->to ),
				'Text'     => rawurlencode( $message->text ),
			),
			self::API
		);
		$res = $this->http->request( $url, array( 'method' => 'GET' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		// A numeric message id (> 0) on success, or a negative / textual error code.
		$raw = isset( $res['raw'] ) ? trim( (string) $res['raw'] ) : trim( (string) wp_json_encode( $res ) );
		return ( is_numeric( $raw ) && (float) $raw > 0 ) ? true : $this->error( 'Payam Resan: ' . mb_substr( wp_strip_all_tags( $raw ), 0, 200 ) );
	}
}
