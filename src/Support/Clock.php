<?php
/**
 * GMT time helper (MySQL format).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * GMT time helper (MySQL format).
 */
final class Clock {

	/**
	 * Current GMT mysql time.
	 *
	 * @param int $offset Seconds offset.
	 * @return string
	 */
	public static function now( $offset = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() + $offset );
	}
}
