<?php
/**
 * Evaluates the show_if rules of settings rows (server side twin of the admin JS).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\SettingsForm;

defined( 'ABSPATH' ) || exit;

/**
 * show_if evaluator.
 */
final class Visibility {

	const MAX_DEPTH = 5;

	/**
	 * Fields of the tab.
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Current settings.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Constructor.
	 *
	 * @param array $fields Fields of the tab.
	 * @param array $values Current settings.
	 */
	public function __construct( array $fields, array $values ) {
		$this->fields = $fields;
		$this->values = $values;
	}

	/**
	 * Is a row shown for the current values (its rules, and those of the fields they depend on)?
	 *
	 * @param string $key   Field key.
	 * @param int    $depth Recursion guard.
	 * @return bool
	 */
	public function is_visible( $key, $depth = 0 ) {
		if ( empty( $this->fields[ $key ]['show_if'] ) || $depth > self::MAX_DEPTH ) {
			return true;
		}
		$rules = $this->fields[ $key ]['show_if'];
		$sets  = isset( $rules[0] ) ? $rules : array( $rules );
		foreach ( $sets as $set ) {
			$ok = true;
			foreach ( $set as $dep => $allowed ) {
				$value = isset( $this->values[ $dep ] ) ? (string) $this->values[ $dep ] : '';
				if ( isset( $this->fields[ $dep ] ) && ( ! in_array( $value, (array) $allowed, true ) || ! $this->is_visible( $dep, $depth + 1 ) ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * show_if rules as a data attribute.
	 *
	 * @param array $field Field.
	 * @return string
	 */
	public static function attribute( array $field ) {
		return empty( $field['show_if'] ) ? '' : ' data-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"';
	}
}
