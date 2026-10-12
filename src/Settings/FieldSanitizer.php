<?php
/**
 * Sanitizes one posted settings value according to its schema field definition.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\PatternRepository;

/**
 * Type-driven sanitizer: every field type has exactly one sanitizing rule.
 */
final class FieldSanitizer {

	/**
	 * Field types that hold no value (layout only).
	 */
	const LAYOUT_TYPES = array( 'heading', 'group' );

	/**
	 * Field types whose posted value is an array.
	 */
	const ARRAY_TYPES = array( 'multicheck', 'sms_patterns' );

	/**
	 * Does this field store a value?
	 *
	 * @param array $field Field definition.
	 * @return bool
	 */
	public static function stores_value( array $field ) {
		return ! in_array( $field['type'], self::LAYOUT_TYPES, true );
	}

	/**
	 * Sanitize a raw (already unslashed) value.
	 *
	 * @param array $field Field definition (type, options, default, min, max).
	 * @param mixed $raw   Posted value or null when missing.
	 * @return mixed
	 */
	public static function sanitize( array $field, $raw ) {
		$type    = $field['type'];
		$default = isset( $field['default'] ) ? $field['default'] : '';
		// Never let an array reach a scalar sanitizer (or a scalar an array one).
		if ( is_array( $raw ) !== in_array( $type, self::ARRAY_TYPES, true ) && null !== $raw ) {
			$raw = in_array( $type, self::ARRAY_TYPES, true ) ? array() : '';
		}
		switch ( $type ) {
			case 'checkbox':
				return $raw ? 1 : 0;
			case 'multicheck':
				$allowed = array_map( 'strval', array_keys( isset( $field['options'] ) ? $field['options'] : array() ) );
				return array_values( array_intersect( array_map( 'strval', (array) $raw ), $allowed ) );
			case 'number':
				return self::number( $field, $raw );
			case 'sms_patterns':
				return PatternRepository::sanitize_pattern_items( $raw );
			case 'color':
				$hex = sanitize_hex_color( (string) $raw );
				return $hex ? $hex : $default;
			case 'select':
				$raw = (string) $raw;
				return isset( $field['options'][ $raw ] ) ? $raw : $default;
			case 'textarea':
				return sanitize_textarea_field( (string) $raw );
			case 'html':
				return wp_kses_post( (string) $raw );
			case 'media':
			case 'url':
				return esc_url_raw( trim( (string) $raw ), array( 'http', 'https' ) );
			case 'password':
				// Secrets: keep every printable character, drop whitespace/control characters only.
				return preg_replace( '/[\x00-\x20\x7F]/', '', (string) $raw );
			default:
				return sanitize_text_field( (string) $raw );
		}
	}

	/**
	 * Integer within the field's optional min / max (default min 0).
	 *
	 * @param array $field Field.
	 * @param mixed $raw   Raw value.
	 * @return int
	 */
	private static function number( array $field, $raw ) {
		$value = (int) $raw;
		$min   = isset( $field['min'] ) ? (int) $field['min'] : 0;
		$value = max( $min, $value );
		if ( isset( $field['max'] ) ) {
			$value = min( (int) $field['max'], $value );
		}
		return $value;
	}
}
