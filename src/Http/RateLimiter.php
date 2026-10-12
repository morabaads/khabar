<?php
/**
 * Per-IP hourly request limits for the public endpoints.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Http;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Access;
use YadamBiar\Support\Visitor;

/**
 * Fixed-window rate limiter on transients.
 */
final class RateLimiter {

	/**
	 * Count a hit and tell whether it is within the limit.
	 *
	 * @param string $bucket Bucket name.
	 * @param int    $limit  Hits per hour (0 = unlimited).
	 * @return bool Allowed.
	 */
	public static function allow( $bucket, $limit ) {
		if ( $limit <= 0 || Access::allowed() ) {
			return true;
		}
		$key   = 'yadambiar_rl_' . sanitize_key( $bucket ) . '_' . md5( Visitor::ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}
}
