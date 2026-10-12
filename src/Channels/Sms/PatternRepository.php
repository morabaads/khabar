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
use YadamBiar\Support\Text;
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

	/**
	 * Pattern code and filled-in values for an event (low stock falls back to the back-in-stock pattern).
	 *
	 * @param string $event Event key.
	 * @param array  $vars  Message vars.
	 * @return array|null array( code, params ) or null when the event has no pattern.
	 */
	public static function resolve( $event, array $vars ) {
		$items = self::pattern_items();
		$item  = isset( $items[ $event ] ) ? $items[ $event ] : ( 'low_stock' === $event && isset( $items['back_in_stock'] ) ? $items['back_in_stock'] : null );
		if ( ! $item || '' === (string) $item['code'] ) {
			return null;
		}
		$params = array();
		foreach ( (array) $item['vars'] as $var ) {
			$value = trim( wp_strip_all_tags( Text::render( $var['value'], $vars ) ) );
			if ( '' !== $value ) {
				$params[ $var['name'] ] = $value;
			}
		}
		return array( (string) $item['code'], $params );
	}
}
