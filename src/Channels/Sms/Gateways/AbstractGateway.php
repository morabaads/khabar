<?php
/**
 * Shared plumbing of the SMS gateways: settings access, HTTP client, errors.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms\Gateways;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\SmsGateway;
use YadamBiar\Support\Http\JsonClient;

/**
 * Base gateway.
 */
abstract class AbstractGateway implements SmsGateway {

	/**
	 * SMS settings (sms_api_key, sms_username, sms_password, sms_sender, sms_base_url …).
	 *
	 * @var array
	 */
	private $config;

	/**
	 * HTTP client.
	 *
	 * @var JsonClient
	 */
	protected $http;

	/**
	 * Constructor.
	 *
	 * @param array      $config Settings.
	 * @param JsonClient $http   HTTP client.
	 */
	public function __construct( array $config, JsonClient $http ) {
		$this->config = $config;
		$this->http   = $http;
	}

	/**
	 * A setting as a trimmed string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	protected function setting( $key ) {
		return isset( $this->config[ $key ] ) && is_scalar( $this->config[ $key ] ) ? trim( (string) $this->config[ $key ] ) : '';
	}

	/**
	 * Gateway error.
	 *
	 * @param string $message Text.
	 * @return \WP_Error
	 */
	protected function error( $message ) {
		return new \WP_Error( 'yadambiar_sms', $message );
	}

	/**
	 * Provider error text from a response field, or a fallback.
	 *
	 * @param mixed  $value    Field value.
	 * @param string $fallback Fallback text.
	 * @return \WP_Error
	 */
	protected function failure( $value, $fallback ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
		}
		return $this->error( is_string( $value ) && '' !== $value ? $value : $fallback );
	}

	/**
	 * JSON POST.
	 *
	 * @param string $url     URL.
	 * @param array  $body    Body.
	 * @param array  $headers Headers.
	 * @return array|\WP_Error
	 */
	protected function post_json( $url, array $body, array $headers = array() ) {
		return $this->http->request(
			$url,
			array(
				'headers' => $headers + array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * API host: the admin's own panel address (white-label resellers) or the platform default.
	 *
	 * @param string $default Default host.
	 * @return string
	 */
	protected function base_url( $default ) {
		$url = $this->setting( 'sms_base_url' );
		if ( $url && wp_http_validate_url( $url ) ) {
			// Accept a full endpoint pasted from the panel docs: keep scheme://host[:port][/prefix-before-/api].
			return untrailingslashit( preg_replace( '#/api(/.*)?$#i', '', untrailingslashit( $url ) ) );
		}
		return $default;
	}
}
