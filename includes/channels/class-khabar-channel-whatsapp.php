<?php
/**
 * WhatsApp channel.
 *
 * Providers:
 *  - cloud:     Meta WhatsApp Cloud API (official; the API host can be replaced by a reverse proxy
 *               for servers that cannot reach graph.facebook.com, e.g. hosts in Iran).
 *  - greenapi:  Green API (personal number linked by QR code).
 *  - ultramsg:  UltraMsg (personal number linked by QR code).
 *  - waha:      WAHA, self-hosted WhatsApp HTTP API (QR code).
 *  - evolution: Evolution API v2, self-hosted (QR code).
 *  - webhook:   Any HTTP gateway with a configurable JSON body and headers.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Channel_Whatsapp {

	/**
	 * Default API hosts (used when the "API server" setting is empty).
	 */
	const DEFAULT_BASE = array(
		'cloud'    => 'https://graph.facebook.com',
		'greenapi' => 'https://api.green-api.com',
		'ultramsg' => 'https://api.ultramsg.com',
	);

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
		$provider = (string) Khabar_Settings::get( 'whatsapp_provider', 'cloud' );
		$method   = 'send_' . $provider;
		if ( ! method_exists( __CLASS__, $method ) ) {
			return new WP_Error( 'khabar_wa', __( 'سرویس‌دهنده واتساپ تنظیم نشده است.', 'khabar' ) );
		}
		return call_user_func( array( __CLASS__, $method ), $to, $text );
	}

	/**
	 * API host of the current provider (setting, or the provider's default).
	 *
	 * @param string $provider Provider.
	 * @return string Without trailing slash, '' when none.
	 */
	private static function base( $provider ) {
		$base = trim( (string) Khabar_Settings::get( 'whatsapp_base_url', '' ) );
		if ( '' === $base && isset( self::DEFAULT_BASE[ $provider ] ) ) {
			$base = self::DEFAULT_BASE[ $provider ];
		}
		return untrailingslashit( $base );
	}

	/**
	 * POST JSON and turn the answer into true / WP_Error.
	 *
	 * @param string        $url     URL.
	 * @param array|string  $body    Body (array is JSON-encoded).
	 * @param array         $headers Extra headers.
	 * @param callable|null $ok      Extra success check on the decoded body.
	 * @return true|WP_Error
	 */
	private static function post( $url, $body, $headers = array(), $ok = null ) {
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => is_array( $body ) ? wp_json_encode( $body ) : $body,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'khabar_wa', 'HTTP ' . $code . ': ' . mb_substr( $raw, 0, 300 ) );
		}
		if ( $ok && ! $ok( json_decode( $raw, true ) ) ) {
			return new WP_Error( 'khabar_wa', mb_substr( $raw, 0, 300 ) );
		}
		return true;
	}

	/**
	 * Missing-setting error.
	 *
	 * @return WP_Error
	 */
	private static function incomplete() {
		return new WP_Error( 'khabar_wa', __( 'اطلاعات اتصال واتساپ کامل نیست.', 'khabar' ) );
	}

	/**
	 * Meta WhatsApp Cloud API.
	 *
	 * @param string $to   International number.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	private static function send_cloud( $to, $text ) {
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
		return self::post( self::base( 'cloud' ) . '/v20.0/' . rawurlencode( $phone_id ) . '/messages', $payload, array( 'Authorization' => 'Bearer ' . $token ) );
	}

	/**
	 * Green API: {apiUrl}/waInstance{id}/sendMessage/{token}.
	 *
	 * @param string $to   International number.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	private static function send_greenapi( $to, $text ) {
		$id    = trim( (string) Khabar_Settings::get( 'whatsapp_instance' ) );
		$token = trim( (string) Khabar_Settings::get( 'whatsapp_api_key' ) );
		if ( '' === $id || '' === $token ) {
			return self::incomplete();
		}
		return self::post(
			self::base( 'greenapi' ) . '/waInstance' . rawurlencode( $id ) . '/sendMessage/' . rawurlencode( $token ),
			array(
				'chatId'  => $to . '@c.us',
				'message' => $text,
			),
			array(),
			function ( $json ) {
				return is_array( $json ) && ! empty( $json['idMessage'] );
			}
		);
	}

	/**
	 * UltraMsg: {base}/{instance}/messages/chat.
	 *
	 * @param string $to   International number.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	private static function send_ultramsg( $to, $text ) {
		$instance = trim( (string) Khabar_Settings::get( 'whatsapp_instance' ) );
		$token    = trim( (string) Khabar_Settings::get( 'whatsapp_api_key' ) );
		if ( '' === $instance || '' === $token ) {
			return self::incomplete();
		}
		return self::post(
			self::base( 'ultramsg' ) . '/' . rawurlencode( $instance ) . '/messages/chat',
			array(
				'token' => $token,
				'to'    => '+' . $to,
				'body'  => $text,
			),
			array(),
			function ( $json ) {
				// UltraMsg answers 200 with {"error": …} for problems such as a disconnected instance.
				return is_array( $json ) && empty( $json['error'] );
			}
		);
	}

	/**
	 * WAHA (self-hosted): {base}/api/sendText.
	 *
	 * @param string $to   International number.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	private static function send_waha( $to, $text ) {
		$base = self::base( 'waha' );
		if ( '' === $base ) {
			return self::incomplete();
		}
		$session = trim( (string) Khabar_Settings::get( 'whatsapp_instance' ) );
		$key     = trim( (string) Khabar_Settings::get( 'whatsapp_api_key' ) );
		return self::post(
			$base . '/api/sendText',
			array(
				'session' => '' !== $session ? $session : 'default',
				'chatId'  => $to . '@c.us',
				'text'    => $text,
			),
			'' !== $key ? array( 'X-Api-Key' => $key ) : array()
		);
	}

	/**
	 * Evolution API v2 (self-hosted): {base}/message/sendText/{instance}.
	 *
	 * @param string $to   International number.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	private static function send_evolution( $to, $text ) {
		$base     = self::base( 'evolution' );
		$instance = trim( (string) Khabar_Settings::get( 'whatsapp_instance' ) );
		$key      = trim( (string) Khabar_Settings::get( 'whatsapp_api_key' ) );
		if ( '' === $base || '' === $instance || '' === $key ) {
			return self::incomplete();
		}
		return self::post(
			$base . '/message/sendText/' . rawurlencode( $instance ),
			array(
				'number' => $to,
				'text'   => $text,
			),
			array( 'apikey' => $key )
		);
	}

	/**
	 * Custom HTTP gateway: JSON body template with {to} {chat_id} {message} and optional headers.
	 *
	 * @param string $to   International number.
	 * @param string $text Text.
	 * @return true|WP_Error
	 */
	private static function send_webhook( $to, $text ) {
		$url = Khabar_Settings::get( 'whatsapp_webhook_url' );
		if ( ! $url ) {
			return new WP_Error( 'khabar_wa', __( 'وب‌سرویس واتساپ تنظیم نشده است.', 'khabar' ) );
		}
		$json = function ( $v ) {
			return substr( wp_json_encode( (string) $v, JSON_UNESCAPED_UNICODE ), 1, -1 ); // Escaped for use inside a JSON string.
		};
		$tpl  = trim( (string) Khabar_Settings::get( 'whatsapp_webhook_body', '' ) );
		$body = strtr(
			'' !== $tpl ? $tpl : '{"to":"{to}","message":"{message}"}',
			array(
				'{to}'      => $json( $to ),
				'{chat_id}' => $json( $to . '@c.us' ),
				'{message}' => $json( $text ),
			)
		);
		$headers = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) Khabar_Settings::get( 'whatsapp_webhook_headers', '' ) ) as $line ) {
			$pos = strpos( $line, ':' );
			if ( false !== $pos && '' !== trim( substr( $line, 0, $pos ) ) ) {
				$headers[ trim( substr( $line, 0, $pos ) ) ] = trim( substr( $line, $pos + 1 ) );
			}
		}
		return self::post( $url, $body, $headers );
	}
}
