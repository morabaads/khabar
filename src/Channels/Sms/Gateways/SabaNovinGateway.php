<?php
/**
 * Saba Novin (v1 REST, key in path, text only).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Saba Novin.
 */
final class SabaNovinGateway extends AbstractGateway {

	const API = 'https://api.sabanovin.com/v1/';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		$url = add_query_arg(
			array(
				'gateway' => rawurlencode( $this->setting( 'sms_sender' ) ),
				'to'      => rawurlencode( $message->to ),
				'text'    => rawurlencode( $message->text ),
			),
			self::API . rawurlencode( $this->setting( 'sms_api_key' ) ) . '/sms/send.json'
		);
		$res = $this->http->request( $url, array( 'method' => 'GET' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status']['code'] ) && 200 === (int) $res['status']['code'] ) ? true : $this->failure( $res['status']['message'] ?? '', 'Saba Novin error' );
	}
}
