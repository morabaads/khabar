<?php
/**
 * WhatsApp channel (Meta WhatsApp Cloud API or a custom HTTP provider).
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Channel_Whatsapp {

	/**
	 * Send.
	 *
	 * @param string $to   Phone.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	public static function send( $to, $text ) {
		$to = Khabar_Utils::international_phone( Khabar_Utils::normalize_phone( $to ), Khabar_Settings::get( 'whatsapp_country', '98' ) );
		if ( ! $to ) {
			return new WP_Error( 'khabar_wa', __( 'شماره نامعتبر', 'khabar' ) );
		}

		if ( 'webhook' === Khabar_Settings::get( 'whatsapp_provider' ) ) {
			$url = Khabar_Settings::get( 'whatsapp_webhook_url' );
			if ( ! $url ) {
				return new WP_Error( 'khabar_wa', __( 'وب‌سرویس واتساپ تنظیم نشده است.', 'khabar' ) );
			}
			$res = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'to'      => $to,
							'message' => $text,
						)
					),
				)
			);
		} else {
			$token    = Khabar_Settings::get( 'whatsapp_token' );
			$phone_id = Khabar_Settings::get( 'whatsapp_phone_id' );
			if ( ! $token || ! $phone_id ) {
				return new WP_Error( 'khabar_wa', __( 'اطلاعات WhatsApp Cloud API تنظیم نشده است.', 'khabar' ) );
			}
			$template = Khabar_Settings::get( 'whatsapp_template' );
			$payload  = array(
				'messaging_product' => 'whatsapp',
				'to'                => $to,
			);
			if ( $template ) {
				// Template parameters may not contain new lines.
				$payload['type']     = 'template';
				$payload['template'] = array(
					'name'       => $template,
					'language'   => array( 'code' => Khabar_Settings::get( 'whatsapp_lang', 'fa' ) ),
					'components' => array(
						array(
							'type'       => 'body',
							'parameters' => array(
								array(
									'type' => 'text',
									'text' => preg_replace( '/\s*\n+\s*/u', ' | ', $text ),
								),
							),
						),
					),
				);
			} else {
				$payload['type'] = 'text';
				$payload['text'] = array( 'body' => $text );
			}
			$res = wp_remote_post(
				'https://graph.facebook.com/v20.0/' . rawurlencode( $phone_id ) . '/messages',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
				)
			);
		}

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		return ( $code >= 200 && $code < 300 ) ? true : new WP_Error( 'khabar_wa', 'HTTP ' . $code . ': ' . mb_substr( wp_remote_retrieve_body( $res ), 0, 300 ) );
	}
}
