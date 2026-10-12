<?php
/**
 * Payamak-panel platform (Melipayamak and white-label resellers, REST).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Payamak panel.
 */
final class PayamakPanelGateway extends AbstractGateway {

	const HOST = 'https://rest.payamak-panel.com';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		$auth = array(
			'username' => $this->setting( 'sms_username' ),
			'password' => $this->setting( 'sms_password' ),
		);
		if ( $message->is_pattern() ) {
			// Values are positional, joined with ";" (the variable names are only for the admin).
			$path = '/api/SendSMS/BaseServiceNumber';
			$body = array(
				'text'   => implode( ';', array_values( $message->params ) ),
				'to'     => $message->to,
				'bodyId' => $message->pattern,
			);
		} else {
			$path = '/api/SendSMS/SendSMS';
			$body = array(
				'to'      => $message->to,
				'from'    => $this->setting( 'sms_sender' ),
				'text'    => $message->text,
				'isflash' => 'false',
			);
		}
		$res = $this->http->request( $this->base_url( self::HOST ) . $path, array( 'body' => $auth + $body, 'safe' => true ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['RetStatus'] ) && 1 === (int) $res['RetStatus'] ) ? true : $this->failure( $res['StrRetStatus'] ?? '', 'Melipayamak error' );
	}
}
