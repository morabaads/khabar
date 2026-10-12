<?php
/**
 * Text helpers: digit normalisation, {placeholder} rendering and masking.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Text helpers: digit normalisation, {placeholder} rendering and masking.
 */
final class Text {

	/**
	 * Convert Persian / Arabic digits to latin.
	 *
	 * @param string $str Input.
	 * @return string
	 */
	public static function latin_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $fa, $en, (string) $str );
	}

	/**
	 * Fill {placeholders}.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Vars.
	 * @return string
	 */
	public static function render( $template, $vars ) {
		$map = array();
		foreach ( $vars as $k => $v ) {
			$map[ '{' . $k . '}' ] = (string) $v;
		}
		$out = strtr( (string) $template, $map );
		// Collapse double spaces left by empty placeholders.
		return preg_replace( '/[ \t]{2,}/', ' ', $out );
	}

	/**
	 * Mask a recipient for display.
	 *
	 * @param string $value Phone or email.
	 * @return string
	 */
	public static function mask( $value ) {
		if ( false !== strpos( $value, '@' ) ) {
			list( $user, $domain ) = explode( '@', $value, 2 );
			return substr( $user, 0, 2 ) . str_repeat( '*', max( 1, strlen( $user ) - 2 ) ) . '@' . $domain;
		}
		$len = strlen( $value );
		return $len > 7 ? substr( $value, 0, 4 ) . str_repeat( '*', $len - 7 ) . substr( $value, -3 ) : $value;
	}

	/**
	 * Parse "key=value" lines into an array.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function parse_lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$pos = strpos( $line, '=' );
			if ( false === $pos ) {
				continue;
			}
			$k = trim( substr( $line, 0, $pos ) );
			if ( '' !== $k ) {
				$out[ $k ] = trim( substr( $line, $pos + 1 ) );
			}
		}
		return $out;
	}
}
