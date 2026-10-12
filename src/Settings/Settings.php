<?php
/**
 * Settings repository (stored option, cached per request).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings repository: read / write the plugin option.
 */
final class Settings {

	const OPTION = 'yadambiar_settings';

	/**
	 * Cached values.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), Schema::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Is the setting value in a multicheck list.
	 *
	 * @param string $key   Key.
	 * @param string $value Value.
	 * @return bool
	 */
	public static function has( $key, $value ) {
		$list = (array) self::get( $key, array() );
		return in_array( $value, $list, true );
	}

	/**
	 * Save one settings tab (each value sanitized by its schema field).
	 *
	 * @param array  $input Raw posted values (slashed).
	 * @param string $tab   Tab being saved.
	 * @return bool Saved.
	 */
	public static function save( array $input, $tab ) {
		$schema = Schema::all();
		if ( empty( $schema[ $tab ] ) ) {
			return false;
		}
		$current = self::all();
		foreach ( $schema[ $tab ]['fields'] as $key => $field ) {
			if ( ! FieldSanitizer::stores_value( $field ) ) {
				continue;
			}
			$raw             = array_key_exists( $key, $input ) ? wp_unslash( $input[ $key ] ) : null;
			$current[ $key ] = FieldSanitizer::sanitize( $field, $raw );
		}
		update_option( self::OPTION, $current );
		self::$cache = null;
		return true;
	}

	/**
	 * Reset cache (tests / after save).
	 */
	public static function flush() {
		self::$cache = null;
	}
}
