<?php
/**
 * URLs of the admin app: section views and nonce-protected admin-post actions.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds admin URLs in one place.
 */
final class AdminUrl {

	const PAGE          = 'yadambiar';
	const ACTION_PREFIX = 'yadambiar_';

	/**
	 * URL of a section of the single-page app.
	 *
	 * @param string $view Section slug.
	 * @param array  $args Extra query args (already sanitized values).
	 * @return string Raw URL (escape on output).
	 */
	public static function view( $view = 'dashboard', array $args = array() ) {
		$query = array( 'page' => self::PAGE );
		if ( 'dashboard' !== $view ) {
			$query['view'] = $view;
		}
		return add_query_arg( array_map( 'rawurlencode', array_merge( $query, $args ) ), admin_url( 'admin.php' ) );
	}

	/**
	 * Settings tab URL.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public static function settings( $tab = 'general' ) {
		return self::view( 'settings', array( 'tab' => $tab ) );
	}

	/**
	 * Nonce-protected admin-post URL (GET actions such as exports and "send now").
	 *
	 * @param string $action Action name without prefix.
	 * @param array  $args   Extra query args.
	 * @return string
	 */
	public static function action( $action, array $args = array() ) {
		$args['action'] = self::ACTION_PREFIX . $action;
		return wp_nonce_url( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin-post.php' ) ), self::ACTION_PREFIX . $action );
	}
}
