<?php
/**
 * Ghasedak (ghasedak.me, REST v1).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Ghasedak.
 */
final class GhasedakGateway extends AbstractGateway {

	const API = 'https://gateway.ghasedak.me/rest/api/v1/WebService/';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		if ( $message->is_pattern() ) {
			$inputs = array();
			foreach ( $message->params as $name => $value ) {
				$inputs[] = array(
					'param' => (string) $name,
					'value' => (string) $value,
				);
			}
			$path = 'SendOtpSMS';
			$body = array(
				'receptors'    => array(
					array(
						'mobile'            => $message->to,
						'clientReferenceId' => (string) time(),
					),
				),
				'templateName' => $message->pattern,
				'inputs'       => $inputs,
			);
		} else {
			$path = 'SendSingleSMS';
			$body = array(
				'lineNumber' => $this->setting( 'sms_sender' ),
				'message'    => $message->text,
				'receptor'   => $message->to,
			);
		}
		$res = $this->post_json( self::API . $path, $body, array( 'ApiKey' => $this->setting( 'sms_api_key' ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ! empty( $res['isSuccess'] ) ? true : $this->failure( $res['message'] ?? '', 'Ghasedak error' );
	}
}
