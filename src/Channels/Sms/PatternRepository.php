<?php
/**
 * SMS pattern (service-line template) definitions per event.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Catalog;
use YadamBiar\Settings\Settings;
/**
 * SMS pattern (service-line template) definitions per event.
 */
final class PatternRepository {

	/**
	 * Pattern items: [ event => [ 'event', 'code', 'vars' => [ [name, value], … ] ] ].
	 *
	 * @return array
	 */
	public static function pattern_items() {
		$items = Settings::get( 'sms_pattern_items', array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Sanitize the posted pattern repeater (one pattern per event).
	 *
	 * @param mixed $raw Posted rows.
	 * @return array
	 */
	public static function sanitize_pattern_items( $raw ) {
		$events = Catalog::events();
		$out    = array();
		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$event = isset( $row['event'] ) ? sanitize_key( $row['event'] ) : '';
			$code  = isset( $row['code'] ) ? sanitize_text_field( $row['code'] ) : '';
			if ( ! isset( $events[ $event ] ) || '' === $code ) {
				continue;
			}
			$vars = array();
			foreach ( isset( $row['vars'] ) ? (array) $row['vars'] : array() as $v ) {
				$name  = isset( $v['name'] ) ? sanitize_text_field( $v['name'] ) : '';
				$value = isset( $v['value'] ) ? sanitize_text_field( $v['value'] ) : '';
				if ( '' !== $name && '' !== $value ) {
					$vars[] = array( 'name' => $name, 'value' => $value );
				}
			}
			$out[ $event ] = array( 'event' => $event, 'code' => $code, 'vars' => $vars );
		}
		return $out;
	}
}
