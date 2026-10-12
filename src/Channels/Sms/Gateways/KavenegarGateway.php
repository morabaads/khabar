<?php
/**
 * Kavenegar (REST, key in path).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;
use YadamBiar\Contracts\SmsDiagnostics;

/**
 * Kavenegar.
 */
final class KavenegarGateway extends AbstractGateway implements SmsDiagnostics {

	const API = 'https://api.kavenegar.com/v1/';

	/**
	 * Tokens that may not contain spaces.
	 */
	const NO_SPACE_TOKENS = array( 'token', 'token2', 'token3' );

	/**
	 * Endpoint URL.
	 *
	 * @param string $path Path after the key.
	 * @return string
	 */
	private function url( $path ) {
		return self::API . rawurlencode( $this->setting( 'sms_api_key' ) ) . '/' . $path;
	}

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		if ( $message->is_pattern() ) {
			$body = array(
				'receptor' => $message->to,
				'template' => $message->pattern,
			);
			foreach ( $message->params as $name => $value ) {
				$body[ $name ] = in_array( $name, self::NO_SPACE_TOKENS, true ) ? preg_replace( '/\s+/u', "\u{200C}", $value ) : $value;
			}
			$res = $this->http->request( $this->url( 'verify/lookup.json' ), array( 'body' => $body ) );
		} else {
			$res = $this->http->request(
				$this->url( 'sms/send.json' ),
				array(
					'body' => array(
						'receptor' => $message->to,
						'sender'   => $this->setting( 'sms_sender' ),
						'message'  => $message->text,
					),
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['return']['status'] ) && 200 === (int) $res['return']['status'] ) ? true : $this->failure( $res['return']['message'] ?? '', 'Kavenegar error' );
	}

	/**
	 * Credit.
	 *
	 * @return array|\WP_Error
	 */
	public function account_info() {
		$res = $this->http->request( $this->url( 'account/info.json' ), array( 'method' => 'GET' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( __( 'اعتبار (ریال)', 'yadambiar' ) => isset( $res['entries']['remaincredit'] ) ? (string) $res['entries']['remaincredit'] : '—' );
	}

	/**
	 * Delivery status.
	 *
	 * @param string $id Message id.
	 * @return string|\WP_Error
	 */
	public function delivery_status( $id ) {
		$res = $this->http->request( $this->url( 'sms/status.json?messageid=' . rawurlencode( $id ) ), array( 'method' => 'GET' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return isset( $res['entries'][0]['statustext'] ) ? $res['entries'][0]['statustext'] . ' (status=' . (int) $res['entries'][0]['status'] . ')' : __( 'نامشخص', 'yadambiar' );
	}

	/**
	 * Message id.
	 *
	 * @param array $response Response.
	 * @return string
	 */
	public function message_id( array $response ) {
		return isset( $response['entries'][0]['messageid'] ) ? (string) $response['entries'][0]['messageid'] : '';
	}
}
