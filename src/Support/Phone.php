<?php
/**
 * Mobile number normalisation and formatting.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
/**
 * Mobile number normalisation and formatting.
 */
final class Phone {

	/**
	 * Normalize a mobile number. Returns '' when invalid.
	 *
	 * @param string    $phone  Raw phone.
	 * @param bool|null $strict Iranian validation.
	 * @return string
	 */
	public static function normalize_phone( $phone, $strict = null ) {
		if ( null === $strict ) {
			$strict = (bool) Settings::get( 'strict_iran_mobile', 1 );
		}
		$phone = preg_replace( '/[^0-9+]/', '', Text::latin_digits( $phone ) );
		if ( '' === $phone ) {
			return '';
		}
		if ( $strict ) {
			$phone = preg_replace( '/^(\+98|0098|98)/', '0', $phone );
			if ( preg_match( '/^9\d{9}$/', $phone ) ) {
				$phone = '0' . $phone;
			}
			return preg_match( '/^09\d{9}$/', $phone ) ? $phone : '';
		}
		return preg_match( '/^\+?\d{7,15}$/', $phone ) ? $phone : '';
	}

	/**
	 * Convert local Iranian mobile to international (no plus) for messaging APIs.
	 *
	 * @param string $phone   Phone.
	 * @param string $country Country code.
	 * @return string
	 */
	public static function international_phone( $phone, $country = '98' ) {
		$phone = ltrim( (string) $phone, '+' );
		if ( 0 === strpos( $phone, '00' ) ) {
			return substr( $phone, 2 );
		}
		if ( 0 === strpos( $phone, '0' ) ) {
			return $country . substr( $phone, 1 );
		}
		return $phone;
	}
}
