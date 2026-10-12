<?php
/**
 * Small HTTP client for JSON APIs: timeouts, error mapping and the last decoded response for diagnostics.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support\Http;

defined( 'ABSPATH' ) || exit;

/**
 * JSON HTTP client.
 */
final class JsonClient {

	const TIMEOUT  = 15;
	const SNIPPET  = 300;
	const ERR_KEYS = array( 'message', 'Message', 'description', 'StrRetStatus' );

	/**
	 * Decoded body of the last call.
	 *
	 * @var array|null
	 */
	private $last;

	/**
	 * Send a request.
	 *
	 * @param string $url  URL.
	 * @param array  $args wp_remote_request() args; "safe" => true blocks local/private hosts (URLs typed by the admin).
	 * @return array|\WP_Error Decoded JSON, or array( 'raw' => body ) for non-JSON answers.
	 */
	public function request( $url, array $args = array() ) {
		$safe = ! empty( $args['safe'] );
		unset( $args['safe'] );
		$args = wp_parse_args(
			$args,
			array(
				'timeout'     => self::TIMEOUT,
				'method'      => 'POST',
				'redirection' => 2,
			)
		);
		$this->last = null;
		$response   = $safe ? wp_safe_remote_request( $url, $args ) : wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code       = (int) wp_remote_retrieve_response_code( $response );
		$body       = (string) wp_remote_retrieve_body( $response );
		$json       = json_decode( $body, true );
		$this->last = is_array( $json ) ? $json : array( 'raw' => mb_substr( $body, 0, 500 ) );
		if ( $code >= 400 ) {
			$msg = is_array( $json ) ? self::error_text( $json ) : '';
			return new \WP_Error( 'yadambiar_http', 'HTTP ' . $code . ': ' . ( '' !== $msg ? $msg : mb_substr( wp_strip_all_tags( $body ), 0, self::SNIPPET ) ), array( 'status' => $code ) );
		}
		return is_array( $json ) ? $json : array( 'raw' => $body );
	}

	/**
	 * Decoded body of the last call.
	 *
	 * @return array|null
	 */
	public function last_response() {
		return $this->last;
	}

	/**
	 * The provider's own error text, when it sends one.
	 *
	 * @param array $json Decoded body.
	 * @return string
	 */
	private static function error_text( array $json ) {
		foreach ( self::ERR_KEYS as $key ) {
			if ( isset( $json[ $key ] ) && is_string( $json[ $key ] ) && '' !== $json[ $key ] ) {
				return $json[ $key ];
			}
		}
		return isset( $json['return']['message'] ) && is_string( $json['return']['message'] ) ? $json['return']['message'] : '';
	}
}
