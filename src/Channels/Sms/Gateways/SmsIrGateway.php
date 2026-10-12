<?php
/**
 * SMS.ir (v1 REST).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;
use YadamBiar\Contracts\SmsDiagnostics;
use YadamBiar\Support\Text;

/**
 * SMS.ir.
 */
final class SmsIrGateway extends AbstractGateway implements SmsDiagnostics {

	const API = 'https://api.sms.ir/v1/';

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'X-API-KEY' => $this->setting( 'sms_api_key' ) );
	}

	/**
	 * GET request.
	 *
	 * @param string $path Path.
	 * @return array|\WP_Error
	 */
	private function get( $path ) {
		return $this->http->request(
			self::API . $path,
			array(
				'method'  => 'GET',
				'headers' => $this->headers() + array( 'Accept' => 'application/json' ),
			)
		);
	}

	/**
	 * Send.
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message ) {
		if ( $message->is_pattern() ) {
			$parameters = array();
			foreach ( $message->params as $name => $value ) {
				$parameters[] = array(
					'name'  => (string) $name,
					'value' => (string) $value,
				);
			}
			$res = $this->post_json(
				self::API . 'send/verify',
				array(
					'mobile'     => $message->to,
					'templateId' => (int) $message->pattern,
					'parameters' => $parameters,
				),
				$this->headers()
			);
		} else {
			// The line must be a JSON number (e.g. 30007732000000), not a string.
			$line = preg_replace( '/\D/', '', Text::latin_digits( $this->setting( 'sms_sender' ) ) );
			if ( '' === $line ) {
				return $this->error( __( 'SMS.ir: «شماره فرستنده (خط)» را در تنظیمات پیامک وارد کنید (مثلاً 30007732000000).', 'yadambiar' ) );
			}
			$res = $this->post_json(
				self::API . 'send/bulk',
				array(
					'lineNumber'  => (int) $line,
					'messageText' => $message->text,
					'mobiles'     => array( $message->to ),
				),
				$this->headers()
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status'] ) && 1 === (int) $res['status'] ) ? true : $this->failure( $res['message'] ?? '', 'SMS.ir error' );
	}

	/**
	 * Credit and lines.
	 *
	 * @return array|\WP_Error
	 */
	public function account_info() {
		$credit = $this->get( 'credit' );
		if ( is_wp_error( $credit ) ) {
			return $credit;
		}
		$lines = $this->get( 'line' );
		return array(
			__( 'اعتبار', 'yadambiar' )         => isset( $credit['data'] ) && is_scalar( $credit['data'] ) ? (string) $credit['data'] : '—',
			__( 'خطوط فعال حساب', 'yadambiar' ) => ( ! is_wp_error( $lines ) && ! empty( $lines['data'] ) ) ? implode( '، ', array_map( 'strval', array_filter( (array) $lines['data'], 'is_scalar' ) ) ) : '—',
		);
	}

	/**
	 * Delivery status.
	 *
	 * @param string $id Message id.
	 * @return string|\WP_Error
	 */
	public function delivery_status( $id ) {
		$res = $this->get( 'send/' . rawurlencode( $id ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$state  = isset( $res['data']['deliveryState'] ) ? (int) $res['data']['deliveryState'] : 0;
		$labels = array(
			1 => __( 'به گوشی رسیده است', 'yadambiar' ),
			2 => __( 'به گوشی نرسیده است', 'yadambiar' ),
			3 => __( 'در حال پردازش در مخابرات', 'yadambiar' ),
			4 => __( 'به مخابرات نرسیده است', 'yadambiar' ),
			5 => __( 'به اپراتور رسیده است', 'yadambiar' ),
			6 => __( 'ناموفق', 'yadambiar' ),
			7 => __( 'شماره در لیست سیاه مخابرات است (پیامک تبلیغاتی دریافت نمی‌کند) — از ارسال پترن استفاده کنید', 'yadambiar' ),
		);
		return ( isset( $labels[ $state ] ) ? $labels[ $state ] : __( 'نامشخص', 'yadambiar' ) ) . ' (deliveryState=' . $state . ')';
	}

	/**
	 * Message id.
	 *
	 * @param array $response Response.
	 * @return string
	 */
	public function message_id( array $response ) {
		if ( isset( $response['data']['messageIds'][0] ) ) {
			return (string) $response['data']['messageIds'][0];
		}
		return isset( $response['data']['messageId'] ) ? (string) $response['data']['messageId'] : '';
	}
}
