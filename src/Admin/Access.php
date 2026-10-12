<?php
/**
 * Who may use the admin app, and the capability + nonce gate for admin actions.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Capability and nonce checks for the admin side.
 */
final class Access {

	const CAP = 'manage_woocommerce';

	/**
	 * May the current user manage the plugin?
	 *
	 * @return bool
	 */
	public static function allowed() {
		return current_user_can( self::CAP );
	}

	/**
	 * Stop the request unless the user has the capability and the nonce is valid.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	public static function guard( $nonce_action ) {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadambiar' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}
}
