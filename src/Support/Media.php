<?php
/**
 * Media helpers.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Media helpers.
 */
final class Media {

	/**
	 * Notification icon URL. A media-library image is served at the requested size, so formats that
	 * browsers cannot show (e.g. HEIC) or very large originals go out as WordPress's generated size.
	 *
	 * @param string $url  Configured URL.
	 * @param int    $size Edge in pixels.
	 * @return string
	 */
	public static function sized_url( $url, $size = 192 ) {
		$key = 'yadambiar_icon_' . md5( $url . '|' . $size );
		$out = get_transient( $key );
		if ( false === $out ) {
			$id  = attachment_url_to_postid( $url );
			$img = $id ? wp_get_attachment_image_url( $id, array( $size, $size ) ) : '';
			$out = $img ? $img : $url;
			set_transient( $key, $out, DAY_IN_SECONDS );
		}
		return $out;
	}
}
