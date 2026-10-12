<?php
/**
 * Price formatting and parsing.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Price formatting and parsing.
 */
final class Money {

	/**
	 * Plain-text price for messages.
	 *
	 * @param float|string $price Price.
	 * @return string
	 */
	public static function price_text( $price ) {
		if ( '' === $price || null === $price ) {
			return '';
		}
		$text = trim( html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ) );
		// Stores using "left/right" (no space) currency positions glue the symbol to the digits; keep them readable.
		$text = preg_replace( '/([\d\x{06F0}-\x{06F9}])(\p{L})/u', '$1 $2', $text );
		return preg_replace( '/(\p{L})([\d\x{06F0}-\x{06F9}])/u', '$1 $2', $text );
	}

	/**
	 * Parse a user supplied price (may contain separators and Persian digits).
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	public static function parse_price( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = Text::latin_digits( (string) $value );
		$value = str_replace( array( ',', '٬', '،', ' ' ), '', $value );
		$value = preg_replace( '/[^0-9.]/', '', $value );
		return '' === $value ? null : (float) $value;
	}
}
