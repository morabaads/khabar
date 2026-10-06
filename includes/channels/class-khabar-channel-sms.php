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

		$driver  = Khabar_Sms_Providers::driver( (string) Khabar_Settings::get( 'sms_gateway', 'kavenegar' ) );
		$pattern = '';
		$params  = array();
		// Drivers without a pattern API always send the text message.
		if ( 'pattern' === Khabar_Settings::get( 'sms_mode' ) && 'text' !== $event && in_array( $driver, Khabar_Sms_Providers::PATTERN_DRIVERS, true ) ) {
			$items = Khabar_Settings::pattern_items();
			$item  = isset( $items[ $event ] ) ? $items[ $event ] : ( 'low_stock' === $event && isset( $items['back_in_stock'] ) ? $items['back_in_stock'] : null );
			if ( $item ) {
				$pattern = (string) $item['code'];
				foreach ( (array) $item['vars'] as $var ) {
					$value = trim( wp_strip_all_tags( Khabar_Utils::render( $var['value'], $vars ) ) );
					if ( '' !== $value ) {
						$params[ $var['name'] ] = $value;
					}
				}
			}
		}

		switch ( $driver ) {
			case 'kavenegar':
				return self::kavenegar( $to, $text, $pattern, $params );
			case 'payamak_panel':
				return self::melipayamak( $to, $text, $pattern, $params );
			case 'ippanel':
				return self::ippanel( $to, $text, $pattern, $params );
			case 'smsir':
				return self::smsir( $to, $text, $pattern, $params );
			case 'ghasedak':
				return self::ghasedak( $to, $text, $pattern, $params );
			case 'iranpayamak':
				return self::iranpayamak( $to, $text, $pattern, $params );
			case 'asanak':
				return self::asanak( $to, $text );
			case 'raygansms':
				return self::raygansms( $to, $text );
			case 'sabanovin':
				return self::sabanovin( $to, $text );
			case 'payamresan':
				return self::payamresan( $to, $text );
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
			// Prefer the provider's own error text over the raw JSON.
			$msg = is_array( $json ) ? ( $json['message'] ?? $json['Message'] ?? $json['return']['message'] ?? $json['StrRetStatus'] ?? null ) : null;
			return new WP_Error( 'khabar_sms_http', 'HTTP ' . $code . ': ' . ( is_string( $msg ) && '' !== $msg ? $msg : mb_substr( $body, 0, 300 ) ) );
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
				self::base( 'https://rest.payamak-panel.com' ) . '/api/SendSMS/BaseServiceNumber',
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
				self::base( 'https://rest.payamak-panel.com' ) . '/api/SendSMS/SendSMS',
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
				self::base( 'https://api2.ippanel.com' ) . '/api/v1/sms/pattern/normal/send',
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
				self::base( 'https://api2.ippanel.com' ) . '/api/v1/sms/send/webservice/single',
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
			// SMS.ir wants the line as a JSON number (e.g. 30007732000000), not a string.
			$line = preg_replace( '/\D/', '', Khabar_Utils::latin_digits( (string) Khabar_Settings::get( 'sms_sender' ) ) );
			if ( '' === $line ) {
				return new WP_Error( 'khabar_sms', __( 'SMS.ir: «شماره فرستنده (خط)» را در تنظیمات پیامک وارد کنید (مثلاً 30007732000000).', 'khabar' ) );
			}
			$res = self::request(
				'https://api.sms.ir/v1/send/bulk',
				array(
					'headers' => $headers,
					'body'    => wp_json_encode(
						array(
							'lineNumber'  => (int) $line,
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
	 * API host: the admin's own panel address (white-label resellers) or the platform default.
	 *
	 * @param string $default Default host.
	 * @return string
	 */
	private static function base( $default ) {
		$url = trim( (string) Khabar_Settings::get( 'sms_base_url', '' ) );
		if ( $url && wp_http_validate_url( $url ) ) {
			// Accept a full endpoint pasted from the panel docs: keep only scheme://host[:port][/prefix-before-/api].
			$url = preg_replace( '#/api(/.*)?$#i', '', untrailingslashit( $url ) );
			return untrailingslashit( $url );
		}
		return $default;
	}

	/**
	 * Ghasedak (ghasedak.me, REST v1).
	 */
	private static function ghasedak( $to, $text, $pattern, $params ) {
		$headers = array(
			'ApiKey'       => Khabar_Settings::get( 'sms_api_key' ),
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		if ( $pattern ) {
			$inputs = array();
			foreach ( $params as $name => $value ) {
				$inputs[] = array( 'param' => (string) $name, 'value' => (string) $value );
			}
			$body = array(
				'receptors'    => array( array( 'mobile' => $to, 'clientReferenceId' => (string) time() ) ),
				'templateName' => $pattern,
				'inputs'       => $inputs,
			);
			$url  = 'https://gateway.ghasedak.me/rest/api/v1/WebService/SendOtpSMS';
		} else {
			$body = array(
				'lineNumber' => Khabar_Settings::get( 'sms_sender' ),
				'message'    => $text,
				'receptor'   => $to,
			);
			$url  = 'https://gateway.ghasedak.me/rest/api/v1/WebService/SendSingleSMS';
		}
		$res = self::request( $url, array( 'headers' => $headers, 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ! empty( $res['isSuccess'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['message'] ) ? $res['message'] : 'Ghasedak error' );
	}

	/**
	 * Iran Payamak (api.iranpayamak.com).
	 */
	private static function iranpayamak( $to, $text, $pattern, $params ) {
		$headers = array(
			'Api-Key'      => Khabar_Settings::get( 'sms_api_key' ),
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		if ( $pattern ) {
			$body = array(
				'code'          => $pattern,
				'attributes'    => (object) $params,
				'recipient'     => $to,
				'line_number'   => Khabar_Settings::get( 'sms_sender' ),
				'number_format' => 'english',
			);
			$url  = 'https://api.iranpayamak.com/ws/v1/sms/pattern';
		} else {
			$body = array(
				'text'          => $text,
				'line_number'   => Khabar_Settings::get( 'sms_sender' ),
				'recipients'    => array( $to ),
				'number_format' => 'english',
			);
			$url  = 'https://api.iranpayamak.com/ws/v1/sms/simple';
		}
		$res = self::request( $url, array( 'headers' => $headers, 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status'] ) && 'success' === $res['status'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['message'] ) ? wp_json_encode( $res['message'], JSON_UNESCAPED_UNICODE ) : 'Iran Payamak error' );
	}

	/**
	 * Asanak (v1rest).
	 */
	private static function asanak( $to, $text ) {
		$res = self::request(
			'https://panel.asanak.ir/webservice/v1rest/sendsms',
			array(
				'body' => array(
					'username'    => Khabar_Settings::get( 'sms_username' ),
					'password'    => Khabar_Settings::get( 'sms_password' ),
					'source'      => Khabar_Settings::get( 'sms_sender' ),
					'destination' => $to,
					'message'     => $text,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		// Success: a list of message ids; failure: an object / text describing the error.
		$list = isset( $res['raw'] ) ? array() : $res;
		return ( $list && array_keys( $list ) === range( 0, count( $list ) - 1 ) && is_numeric( reset( $list ) ) ) ? true : new WP_Error( 'khabar_sms', 'Asanak: ' . mb_substr( wp_json_encode( $res, JSON_UNESCAPED_UNICODE ), 0, 300 ) );
	}

	/**
	 * Raygan SMS (smspanel.trez.ir JSON API, basic auth).
	 */
	private static function raygansms( $to, $text ) {
		$res = self::request(
			'http://smspanel.trez.ir/api/smsAPI/SendMessage',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( Khabar_Settings::get( 'sms_username' ) . ':' . Khabar_Settings::get( 'sms_password' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				),
				'body'    => wp_json_encode(
					array(
						'PhoneNumber'         => Khabar_Settings::get( 'sms_sender' ),
						'Message'             => $text,
						'Mobiles'             => array( $to ),
						'UserGroupID'         => uniqid( 'khabar', false ),
						'SendDateInTimeStamp' => time(),
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['Code'] ) && 0 === (int) $res['Code'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['Message'] ) ? $res['Message'] : 'Raygan SMS error' );
	}

	/**
	 * Saba Novin (v1 REST, key in path).
	 */
	private static function sabanovin( $to, $text ) {
		$url = 'https://api.sabanovin.com/v1/' . rawurlencode( (string) Khabar_Settings::get( 'sms_api_key' ) ) . '/sms/send.json';
		$url = add_query_arg(
			array(
				'gateway' => rawurlencode( (string) Khabar_Settings::get( 'sms_sender' ) ),
				'to'      => rawurlencode( $to ),
				'text'    => rawurlencode( $text ),
			),
			$url
		);
		$res = self::request( $url, array( 'method' => 'GET' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return ( isset( $res['status']['code'] ) && 200 === (int) $res['status']['code'] ) ? true : new WP_Error( 'khabar_sms', isset( $res['status']['message'] ) ? $res['status']['message'] : 'Saba Novin error' );
	}

	/**
	 * Payam Resan (APISend.aspx).
	 */
	private static function payamresan( $to, $text ) {
		$url = add_query_arg(
			array(
				'Username' => rawurlencode( (string) Khabar_Settings::get( 'sms_username' ) ),
				'Password' => rawurlencode( (string) Khabar_Settings::get( 'sms_password' ) ),
				'From'     => rawurlencode( (string) Khabar_Settings::get( 'sms_sender' ) ),
				'To'       => rawurlencode( $to ),
				'Text'     => rawurlencode( $text ),
			),
			'http://www.payam-resan.com/APISend.aspx'
		);
		$res = self::request( $url, array( 'method' => 'GET' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		// The service answers with a numeric message id (> 0) on success, or a negative / textual error code.
		$raw = isset( $res['raw'] ) ? trim( (string) $res['raw'] ) : trim( (string) wp_json_encode( $res ) );
		return ( is_numeric( $raw ) && (float) $raw > 0 ) ? true : new WP_Error( 'khabar_sms', 'Payam Resan: ' . mb_substr( wp_strip_all_tags( $raw ), 0, 200 ) );
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
