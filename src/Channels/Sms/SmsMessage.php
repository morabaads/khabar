<?php
/**
 * One SMS to send: recipient, plain text and (optionally) the provider pattern with its values.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable SMS value object.
 */
final class SmsMessage {

	/**
	 * Normalized mobile (09xxxxxxxxx).
	 *
	 * @var string
	 */
	public $to;

	/**
	 * Text (text mode).
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Provider pattern code; empty for a text message.
	 *
	 * @var string
	 */
	public $pattern;

	/**
	 * Pattern values: provider variable name => value.
	 *
	 * @var array
	 */
	public $params;

	/**
	 * Constructor.
	 *
	 * @param string $to      Normalized mobile.
	 * @param string $text    Text.
	 * @param string $pattern Pattern code.
	 * @param array  $params  Pattern values.
	 */
	public function __construct( $to, $text, $pattern = '', array $params = array() ) {
		$this->to      = (string) $to;
		$this->text    = (string) $text;
		$this->pattern = (string) $pattern;
		$this->params  = $params;
	}

	/**
	 * Pattern (service line) message?
	 *
	 * @return bool
	 */
	public function is_pattern() {
		return '' !== $this->pattern;
	}
}
