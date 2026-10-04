<?php
/**
 * SMS channel with Iranian gateways (Kavenegar, Melipayamak, IPPanel/FarazSMS, SMS.ir) and a generic HTTP gateway.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Channel_Sms {

	/**
	 * Send an SMS.
	 *
	 * @param string $to    Mobile.
	 * @param string $text  Text (text mode).
	 * @param string $event Event (pattern lookup). Use 'text' to force text mode.
	 * @param array  $vars  Message vars (pattern mode).
	 * @return true|WP_Error
	 */
	public static function send( $to, $text, $event = 'text', $vars = array() ) {
		$to = Khabar_Utils::normalize_phone( $to );
		if ( ! $to ) {
			return new WP_Error( 'khabar_sms', __( 'شماره موبایل نامعتبر', 'khabar' ) );
		}

		$pre = apply_filters( 'khabar_pre_send_sms', null, $to, $text, $event, $vars );
		if ( null !== $pre ) {
			return $pre;
		}

		$gateway = Khabar_Settings::get( 'sms_gateway', 'kavenegar' );
		$pattern = '';
		$params  = array();
		if ( 'pattern' === Khabar_Settings::get( 'sms_mode' ) && 'text' !== $event ) {
			$patterns = Khabar_Settings::parse_lines( Khabar_Settings::get( 'sms_patterns' ) );
			$pattern  = isset( $patterns[ $event ] ) ? $patterns[ $event ] : '';
			if ( '' === $pattern && 'low_stock' === $event && ! empty( $patterns['back_in_stock'] ) ) {
				$pattern = $patterns['back_in_stock'];
			}
			foreach ( Khabar_Settings::parse_lines( Khabar_Settings::get( 'sms_pattern_vars' ) ) as $name => $tpl ) {
				$value = trim( wp_strip_all_tags( Khabar_Utils::render( $tpl, $vars ) ) );
				if ( '' !== $value ) {
					$params[ $name ] = $value;
				}
			}
		}

		switch ( $gateway ) {
			case 'kavenegar':
				return self::kavenegar( $to, $text, $pattern, $params );
			case 'melipayamak':
				return self::melipayamak( $to, $text, $pattern, $params );
			case 'ippanel':
				return self::ippanel( $to, $text, $pattern, $params );
			case 'smsir':
				return self::smsir( $to, $text, $pattern, $params );
			case 'webhook':
				return self::webhook( $to, $text );
		}
		return new WP_Error( 'khabar_sms', __( 'سامانه پیامک تنظیم نشده است.', 'khabar' ) );
	}

	/**
	 * HTTP helper returning decoded JSON or WP_Error.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args.
	 * @return array|WP_Error
	 */
	private static function request( $url, $args ) {
		$args     = wp_parse_args(
			$args,
			array(
				'timeout' => 15,
				'method'  => 'POST',
			)
		);
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( $code >= 400 ) {
			return new WP_Error( 'khabar_sms_http', 'HTTP ' . $code . ': ' . mb_substr( $body, 0, 300 ) );
		}
		return is_array( $json ) ? $json : array( 'raw' => $body );
	}

	/**
	 * Kavenegar.
	 */
	private static function kavenegar( $to, $text, $pattern, $params ) {
		$key = Khabar_Settings::get( 'sms_api_key' );
		if ( $pattern ) {
			$body = array(
				'receptor' => $to,
				'template' => $pattern,
			);
			foreach ( $params as $name => $value ) {
				// token, token2, token3 may not contain spaces.
				$body[ $name ] = in_array( $name, array( 'token', 'token2', 'token3' ), true ) ? preg_replace( '/\s+/u', "\u{200C}", $value ) : $value;
			}
			$res = self::request( 'https://api.kavenegar.com/v1/' . rawurlencode( $key ) . '/verify/lookup.json', array( 'body' => $body ) );
		} else {
			$res = self::request(
				'https://api.kavenegar.com/v1/' . rawurlencode( $key ) . '/sms/send.json',
				array(
					'body' => array(
						'receptor' => $to,
						'sender'   => Khabar_Settings::get( 'sms_sender' ),
						'message'  => $text,
					),
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['return']['status'] ) && 200 === (int) $res['return']['status'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['return']['message'] ) ? $res['return']['message'] : 'Kavenegar error' );
	}

	/**
	 * Melipayamak (REST).
	 */
	private static function melipayamak( $to, $text, $pattern, $params ) {
		$auth = array(
			'username' => Khabar_Settings::get( 'sms_username' ),
			'password' => Khabar_Settings::get( 'sms_password' ),
		);
		if ( $pattern ) {
			$res = self::request(
				'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
				array(
					'body' => $auth + array(
						'text'   => implode( ';', array_values( $params ) ),
						'to'     => $to,
						'bodyId' => $pattern,
					),
				)
			);
		} else {
			$res = self::request(
				'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
				array(
					'body' => $auth + array(
						'to'      => $to,
						'from'    => Khabar_Settings::get( 'sms_sender' ),
						'text'    => $text,
						'isflash' => 'false',
					),
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['RetStatus'] ) && 1 === (int) $res['RetStatus'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['StrRetStatus'] ) ? $res['StrRetStatus'] : 'Melipayamak error' );
	}

	/**
	 * IPPanel / FarazSMS.
	 */
	private static function ippanel( $to, $text, $pattern, $params ) {
		$headers = array(
			'apikey'       => Khabar_Settings::get( 'sms_api_key' ),
			'Content-Type' => 'application/json',
		);
		if ( $pattern ) {
			$res = self::request(
				'https://api2.ippanel.com/api/v1/sms/pattern/normal/send',
				array(
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'code'      => $pattern,
							'sender'    => Khabar_Settings::get( 'sms_sender' ),
							'recipient' => $to,
							'variable'  => (object) $params,
						)
					),
				)
			);
		} else {
			$res = self::request(
				'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
				array(
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'recipient' => array( $to ),
							'sender'    => Khabar_Settings::get( 'sms_sender' ),
							'message'   => $text,
						)
					),
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status'] ) && 'OK' === strtoupper( (string) $res['status'] ) ) ? true : new WP_Error( 'khabar_sms', isset( $res['message'] ) ? wp_json_encode( $res['message'] ) : 'IPPanel error' );
	}

	/**
	 * SMS.ir (v1).
	 */
	private static function smsir( $to, $text, $pattern, $params ) {
		$headers = array(
			'X-API-KEY'    => Khabar_Settings::get( 'sms_api_key' ),
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		if ( $pattern ) {
			$parameters = array();
			foreach ( $params as $name => $value ) {
				$parameters[] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
			$res = self::request(
				'https://api.sms.ir/v1/send/verify',
				array(
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'mobile'     => $to,
							'templateId' => (int) $pattern,
							'parameters' => $parameters,
						)
					),
				)
			);
		} else {
			$res = self::request(
				'https://api.sms.ir/v1/send/bulk',
				array(
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'lineNumber'  => Khabar_Settings::get( 'sms_sender' ),
							'messageText' => $text,
							'mobiles'     => array( $to ),
						)
					),
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status'] ) && 1 === (int) $res['status'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['message'] ) ? $res['message'] : 'SMS.ir error' );
	}

	/**
	 * Generic HTTP gateway.
	 */
	private static function webhook( $to, $text ) {
		$url = Khabar_Settings::get( 'sms_webhook_url' );
		if ( ! $url ) {
			return new WP_Error( 'khabar_sms', __( 'آدرس وب‌سرویس پیامک تنظیم نشده است.', 'khabar' ) );
		}
		$url  = strtr(
			$url,
			array(
				'{to}'      => rawurlencode( $to ),
				'{message}' => rawurlencode( $text ),
			)
		);
		$body = trim( (string) Khabar_Settings::get( 'sms_webhook_body' ) );
		if ( '' === $body ) {
			$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		} else {
			$body = strtr(
				$body,
				array(
					'{to}'      => trim( wp_json_encode( $to ), '"' ),
					'{message}' => trim( wp_json_encode( $text, JSON_UNESCAPED_UNICODE ), '"' ),
				)
			);
			$res  = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => $body,
				)
			);
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		return ( $code >= 200 && $code < 300 ) ? true : new WP_Error( 'khabar_sms', 'HTTP ' . $code );
	}
}
