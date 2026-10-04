<?php
/**
 * Simple class autoloader: Khabar_Foo_Bar => class-khabar-foo-bar.php
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Autoloader {

	/**
	 * Register autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Class name.
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, 'Khabar_' ) ) {
			return;
		}
		$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		foreach ( array( 'includes/', 'includes/channels/', 'includes/admin/' ) as $dir ) {
			$path = KHABAR_DIR . $dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
