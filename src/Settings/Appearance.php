<?php
/**
 * Appearance settings exposed as CSS custom properties.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Appearance settings exposed as CSS custom properties.
 */
final class Appearance {

	/**
	 * CSS custom properties for the appearance settings.
	 *
	 * @return string e.g. "--yadambiar-accent:#f4511e;…"
	 */
	public static function css_vars() {
		$map = array(
			'color_accent'      => '--yadambiar-accent',
			'color_button_text' => '--yadambiar-on-accent',
			'color_ink'         => '--yadambiar-ink',
			'color_muted'       => '--yadambiar-muted',
			'color_surface'     => '--yadambiar-bg',
			'color_soft'        => '--yadambiar-soft',
			'color_line'        => '--yadambiar-line',
		);
		$out = '';
		foreach ( $map as $key => $var ) {
			$hex = sanitize_hex_color( (string) Settings::get( $key, '' ) );
			if ( $hex ) {
				$out .= $var . ':' . $hex . ';';
			}
		}
		$r = (int) Settings::get( 'radius', 14 );
		if ( $r >= 0 && $r <= 40 ) {
			$out .= '--yadambiar-radius:' . $r . 'px;';
		}
		return $out;
	}
}
