<?php
/**
 * One-shot admin notices that survive a redirect (post/redirect/get).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Flash notice storage per user.
 */
final class Flash {

	const TYPES = array( 'success', 'error', 'warning', 'info' );

	/**
	 * Store a notice and redirect.
	 *
	 * @param string $url     Target URL (same site only).
	 * @param string $message Text.
	 * @param string $type    success|error|warning|info.
	 */
	public static function redirect( $url, $message, $type = 'success' ) {
		set_transient( self::key(), array( (string) $message, in_array( $type, self::TYPES, true ) ? $type : 'info' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( $url ? $url : AdminUrl::view() );
		exit;
	}

	/**
	 * Print (and forget) the pending notice.
	 */
	public static function render() {
		$notice = get_transient( self::key() );
		if ( ! is_array( $notice ) || 2 !== count( $notice ) ) {
			return;
		}
		delete_transient( self::key() );
		printf( '<div class="notice notice-%s yadambiar-notice"><p>%s</p></div>', esc_attr( $notice[1] ), esc_html( $notice[0] ) );
	}

	/**
	 * Transient key of the current user.
	 *
	 * @return string
	 */
	private static function key() {
		return 'yadambiar_notice_' . get_current_user_id();
	}
}
