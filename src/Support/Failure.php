<?php
/**
 * Domain errors carrying an HTTP status (shown to the visitor as is).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * WP_Error factory.
 */
final class Failure {

	/**
	 * Error with a status.
	 *
	 * @param string $message Message for the visitor.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	public static function of( $message, $status = 400 ) {
		return new \WP_Error( 'yadambiar_error', $message, array( 'status' => (int) $status ) );
	}
}
