<?php
/**
 * IPPanel platform (FarazSMS and resellers).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * IPPanel.
 */
final class IppanelGateway extends AbstractGateway {

	const HOST = 'https://api2.ippanel.com';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		if ( $message->is_pattern() ) {
			$path = '/api/v1/sms/pattern/normal/send';
			$body = array(
				'code'      => $message->pattern,
				'sender'    => $this->setting( 'sms_sender' ),
				'recipient' => $message->to,
				'variable'  => (object) $message->params,
			);
		} else {
			$path = '/api/v1/sms/send/webservice/single';
			$body = array(
				'recipient' => array( $message->to ),
				'sender'    => $this->setting( 'sms_sender' ),
				'message'   => $message->text,
			);
		}
		$res = $this->http->request(
			$this->base_url( self::HOST ) . $path,
			array(
				'headers' => array(
					'apikey'       => $this->setting( 'sms_api_key' ),
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'safe'    => true,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status'] ) && 'OK' === strtoupper( (string) $res['status'] ) ) ? true : $this->failure( $res['message'] ?? '', 'IPPanel error' );
	}
}
