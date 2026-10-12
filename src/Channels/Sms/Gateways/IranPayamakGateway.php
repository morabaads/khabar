<?php
/**
 * Iran Payamak (api.iranpayamak.com).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Iran Payamak.
 */
final class IranPayamakGateway extends AbstractGateway {

	const API = 'https://api.iranpayamak.com/ws/v1/sms/';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		if ( $message->is_pattern() ) {
			$path = 'pattern';
			$body = array(
				'code'          => $message->pattern,
				'attributes'    => (object) $message->params,
				'recipient'     => $message->to,
				'line_number'   => $this->setting( 'sms_sender' ),
				'number_format' => 'english',
			);
		} else {
			$path = 'simple';
			$body = array(
				'text'          => $message->text,
				'line_number'   => $this->setting( 'sms_sender' ),
				'recipients'    => array( $message->to ),
				'number_format' => 'english',
			);
		}
		$res = $this->post_json( self::API . $path, $body, array( 'Api-Key' => $this->setting( 'sms_api_key' ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status'] ) && 'success' === $res['status'] ) ? true : $this->failure( $res['message'] ?? '', 'Iran Payamak error' );
	}
}
