<?php
/**
 * PSR-4 autoloader for the YadamBiar\ namespace (maps to src/).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Loads YadamBiar\Foo\Bar from src/Foo/Bar.php.
 */
final class Autoloader {

	const PREFIX = 'YadamBiar\\';

	/**
	 * Base directory (with trailing slash).
	 *
	 * @var string
	 */
	private static $base = '';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Absolute path of src/.
	 */
	public static function register( $base_dir ) {
		self::$base = rtrim( $base_dir, '/\\' ) . '/';
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( $class ) {
		if ( 0 !== strncmp( $class, self::PREFIX, strlen( self::PREFIX ) ) ) {
			return;
		}
		$relative = substr( $class, strlen( self::PREFIX ) );
		// Only letters, digits and namespace separators: never builds a path from anything else.
		if ( ! preg_match( '/^[A-Za-z0-9\\\\]+$/', $relative ) ) {
			return;
		}
		$file = self::$base . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
