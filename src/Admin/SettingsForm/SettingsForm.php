<?php
/**
 * The settings form of one tab: cards (headings), collapsible groups and rows.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\SettingsForm;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Ui\Form;
use YadamBiar\Support\Icon;

/**
 * Settings tab form.
 *
 * Layout: a heading opens a card; a group inside a card is a collapsible panel; plain fields are table rows.
 */
final class SettingsForm {

	const CLOSE_TABLE   = 0;
	const CLOSE_GROUP   = 1;
	const CLOSE_SECTION = 2;

	/**
	 * Tab key.
	 *
	 * @var string
	 */
	private $tab;

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
	 * Defaults.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * show_if evaluator.
	 *
	 * @var Visibility
	 */
	private $visibility;

	/**
	 * Row renderer.
	 *
	 * @var FieldRenderer
	 */
	private $rows;

	/**
	 * Open containers while printing.
	 *
	 * @var array section|group|table => bool
	 */
	private $open = array(
		'section' => false,
		'group'   => false,
		'table'   => false,
	);

	/**
	 * Constructor.
	 *
	 * @param string $tab      Tab key.
	 * @param array  $fields   Fields of the tab.
	 * @param array  $values   Current settings.
	 * @param array  $defaults Defaults.
	 */
	public function __construct( $tab, array $fields, array $values, array $defaults ) {
		$this->tab        = $tab;
		$this->fields     = $fields;
		$this->values     = $values;
		$this->defaults   = $defaults;
		$this->visibility = new Visibility( $fields, $values );
		$this->rows       = new FieldRenderer();
	}

	/**
	 * Print the form.
	 */
	public function render() {
		Form::open( 'save_settings' );
		printf( '<input type="hidden" name="tab" value="%s">', esc_attr( $this->tab ) );
		foreach ( $this->fields as $key => $field ) {
			if ( 'heading' === $field['type'] ) {
				$this->close( self::CLOSE_SECTION );
				echo '<section class="yadambiar-set-card"><header><h2>' . esc_html( $field['label'] ) . '</h2>' . ( ! empty( $field['desc'] ) ? '<p class="description">' . esc_html( $field['desc'] ) . '</p>' : '' ) . '</header>';
				$this->open['section'] = true;
				continue;
			}
			if ( ! $this->open['section'] ) {
				echo '<section class="yadambiar-set-card">';
				$this->open['section'] = true;
			}
			if ( 'group' === $field['type'] ) {
				$this->close( self::CLOSE_GROUP );
				$this->open_group( $key, $field );
				$this->open['group'] = true;
				continue;
			}
			if ( ! $this->open['table'] ) {
				echo '<table class="form-table" role="presentation">';
				$this->open['table'] = true;
			}
			$this->rows->render( $key, $field, $this->values[ $key ] ?? '', $this->defaults[ $key ] ?? '' );
		}
		$this->close( self::CLOSE_SECTION );
		echo '<div class="yadambiar-savebar">';
		submit_button( __( 'ذخیره تنظیمات', 'yadambiar' ) );
		echo '</div>';
		Form::close();
	}

	/**
	 * Close the open containers up to a level.
	 *
	 * @param int $level CLOSE_* constant.
	 */
	private function close( $level ) {
		if ( $this->open['table'] ) {
			echo '</table>';
			$this->open['table'] = false;
		}
		if ( $level >= self::CLOSE_GROUP && $this->open['group'] ) {
			echo '</div></details>';
			$this->open['group'] = false;
		}
		if ( $level >= self::CLOSE_SECTION && $this->open['section'] ) {
			echo '</section>';
			$this->open['section'] = false;
		}
	}

	/**
	 * Has a setting a non-empty value?
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function is_filled( $key ) {
		return isset( $this->values[ $key ] ) && array() !== $this->values[ $key ] && '' !== $this->values[ $key ];
	}

	/**
	 * Open a collapsible settings group with its status badge.
	 *
	 * Group keys: label, desc, icon, show_if, and either ready (fields that must be filled; only rows
	 * shown for the current gateway/provider count) with on_label/off_label, or status (a select whose
	 * chosen option is shown as the badge).
	 *
	 * @param string $key   Group key.
	 * @param array  $field Group definition.
	 */
	private function open_group( $key, array $field ) {
		// Rows of this group: everything up to the next group or heading.
		$members = array();
		$inside  = false;
		foreach ( $this->fields as $k => $f ) {
			if ( $k === $key ) {
				$inside = true;
				continue;
			}
			if ( $inside && in_array( $f['type'], array( 'group', 'heading' ), true ) ) {
				break;
			}
			if ( $inside ) {
				$members[] = $k;
			}
		}
		$shown = $this->visibility->is_visible( $key ) && array_filter( $members, array( $this->visibility, 'is_visible' ) );

		$labels = array(
			'on'      => isset( $field['on_label'] ) ? $field['on_label'] : __( 'تنظیم شده', 'yadambiar' ),
			'partial' => __( 'ناقص', 'yadambiar' ),
			'off'     => isset( $field['off_label'] ) ? $field['off_label'] : __( 'تنظیم نشده', 'yadambiar' ),
		);
		if ( ! empty( $field['status'] ) ) {
			$sel   = $this->fields[ $field['status'] ];
			$val   = isset( $this->values[ $field['status'] ] ) ? $this->values[ $field['status'] ] : '';
			$state = 'info';
			$text  = isset( $sel['options'][ $val ] ) ? $sel['options'][ $val ] : '';
		} else {
			$ready  = array_values( array_filter( isset( $field['ready'] ) ? (array) $field['ready'] : array(), array( $this->visibility, 'is_visible' ) ) );
			$filled = count( array_filter( $ready, array( $this, 'is_filled' ) ) );
			// No required field applies to the chosen gateway/provider: show no badge rather than a wrong one.
			$state = ! $ready ? 'none' : ( ! $filled ? 'off' : ( count( $ready ) === $filled ? 'on' : 'partial' ) );
			$text  = isset( $labels[ $state ] ) ? $labels[ $state ] : '';
		}
		printf(
			'<details class="yadambiar-acc" id="yadambiar-acc-%1$s" data-ready="%2$s" data-labels="%3$s" data-status="%4$s"%5$s%6$s><summary><span class="yadambiar-acc-icon">%7$s</span><span class="yadambiar-acc-title"><b>%8$s</b>%9$s</span><span class="yadambiar-acc-badge is-%10$s">%11$s</span><span class="yadambiar-acc-chev" aria-hidden="true"></span></summary><div class="yadambiar-acc-body">',
			esc_attr( trim( $key, '_' ) ),
			esc_attr( wp_json_encode( isset( $field['ready'] ) ? (array) $field['ready'] : array() ) ),
			esc_attr( wp_json_encode( $labels ) ),
			esc_attr( isset( $field['status'] ) ? $field['status'] : '' ),
			Visibility::attribute( $field ),
			$shown ? '' : ' style="display:none"',
			Icon::svg( isset( $field['icon'] ) ? $field['icon'] : 'gear' ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $field['label'] ),
			! empty( $field['desc'] ) ? '<small>' . esc_html( $field['desc'] ) . '</small>' : '',
			esc_attr( $state ),
			esc_html( $text )
		);
	}
}
