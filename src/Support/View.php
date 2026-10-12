<?php
/**
 * Template locator (theme overridable).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Template locator (theme overridable).
 */
final class View {

	/**
	 * Template path, overridable from the theme at yourtheme/yadambiar/{name}.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function locate( $name ) {
		$theme = locate_template( 'yadambiar/' . $name );
		return apply_filters( 'yadambiar_template', $theme ? $theme : YADAMBIAR_DIR . 'templates/' . $name, $name );
	}
}
