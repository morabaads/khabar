<?php
/**
 * Serves the service worker from the site root so its scope covers the whole site.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Push;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\Module;
/**
 * Serves the service worker from the site root so its scope covers the whole site.
 */
final class ServiceWorker implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'serve_worker' ), 1 );
	}

	/**
	 * Serve the service worker from the site root so its scope covers the whole site.
	 */
	public static function serve_worker() {
		if ( empty( $_GET['yadambiar_sw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache' );
		$latest = esc_url_raw( rest_url( 'yadambiar/v1/push/latest' ) );
		echo "/* Yadam Biar service worker */\n";
		echo 'var YADAMBIAR_LATEST = ' . wp_json_encode( $latest ) . ";\n";
		echo file_get_contents( YADAMBIAR_DIR . 'assets/js/sw.js' ); // phpcs:ignore
		exit;
	}
}
