<?php
/**
 * Raygan SMS (smspanel.trez.ir JSON API, basic auth, text only).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

/**
 * Raygan SMS.
 */
final class RayganGateway extends AbstractGateway {

	const API = 'http://smspanel.trez.ir/api/smsAPI/SendMessage';

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		$res = $this->post_json(
			self::API,
			array(
				'PhoneNumber'         => $this->setting( 'sms_sender' ),
				'Message'             => $message->text,
				'Mobiles'             => array( $message->to ),
				'UserGroupID'         => uniqid( 'yadambiar', false ),
				'SendDateInTimeStamp' => time(),
			),
			array( 'Authorization' => 'Basic ' . base64_encode( $this->setting( 'sms_username' ) . ':' . $this->setting( 'sms_password' ) ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['Code'] ) && 0 === (int) $res['Code'] ) ? true : $this->failure( $res['Message'] ?? '', 'Raygan SMS error' );
	}
}
