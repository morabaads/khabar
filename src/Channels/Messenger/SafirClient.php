<?php
/**
 * Bale Safir: messages straight to a phone number (no Start needed).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
use YadamBiar\Support\Phone;
/**
 * Bale Safir: messages straight to a phone number (no Start needed).
 */
final class SafirClient {

	const API = 'https://safir.bale.ai/api/v3/send_message';

	/**
	 * Send through Bale Safir (https://docs.bale.ai/safir) to a phone number.
	 *
	 * @param string $phone   Mobile (any local/international form).
	 * @param array  $message subject, text, url.
	 * @return true|WP_Error
	 */
	public static function send( $phone, $message ) {
		$key    = trim( (string) Settings::get( 'bale_safir_key', '' ) );
		$bot_id = MessengerConfig::safir_bot_id();
		if ( '' === $key || ! $bot_id ) {
			return new \WP_Error( 'yadambiar_safir', __( 'کلید API یا شناسه بازوی سفیر بله تنظیم نشده است.', 'yadambiar' ) );
		}
		$to = Phone::international_phone( Phone::normalize_phone( $phone ), '98' );
		if ( ! $to ) {
			return new \WP_Error( 'yadambiar_safir', __( 'شماره موبایل نامعتبر', 'yadambiar' ) );
		}
		list( $text, $url ) = MessengerChannel::compose( $message );
		$body               = array( 'text' => $text );
		if ( BotApi::is_button_url( $url ) ) {
			$body['reply_markup'] = BotApi::button( $url );
		}
		$res = wp_remote_post(
			self::API,
			array(
				'timeout' => 15,
				'headers' => array(
					'api-access-key' => $key,
					'Content-Type'   => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'request_id'   => wp_generate_uuid4(),
						'bot_id'       => $bot_id,
						'phone_number' => $to,
						'message_data' => array( 'message' => $body ),
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		$err  = is_array( $data ) && ! empty( $data['error_data'] ) ? $data['error_data'] : null;
		if ( is_array( $err ) && isset( $err[0] ) ) {
			$err = $err[0];
		}
		if ( $code >= 200 && $code < 300 && ! $err ) {
			return true;
		}
		$known = array(
			'2'  => __( 'خطای داخلی سرور سفیر', 'yadambiar' ),
			'3'  => __( 'محدودیت سرعت ارسال سفیر', 'yadambiar' ),
			'4'  => __( 'داده ارسالی نامعتبر است', 'yadambiar' ),
			'8'  => __( 'شماره موبایل نامعتبر است', 'yadambiar' ),
			'17' => __( 'این شماره در بله حساب کاربری ندارد', 'yadambiar' ),
			'20' => __( 'اعتبار سفیر کافی نیست (شارژ پنل کسب‌وکار بله)', 'yadambiar' ),
			'21' => __( 'به سقف تعداد مخاطبان سفیر رسیده‌اید', 'yadambiar' ),
		);
		$ecode = is_array( $err ) ? (string) ( isset( $err['code'] ) ? $err['code'] : ( isset( $err['error'] ) ? $err['error'] : '' ) ) : '';
		$edesc = is_array( $err ) ? (string) ( isset( $err['description'] ) ? $err['description'] : ( isset( $err['message'] ) ? $err['message'] : '' ) ) : '';
		$text  = isset( $known[ $ecode ] ) ? $known[ $ecode ] : ( $edesc ? $edesc : 'HTTP ' . $code . ': ' . mb_substr( $raw, 0, 200 ) );
		return new \WP_Error( 'yadambiar_safir', __( 'سفیر بله: ', 'yadambiar' ) . $text . ( $ecode ? ' (' . $ecode . ')' : '' ) );
	}
}
