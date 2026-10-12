<?php
/**
 * Renders one settings row per field type.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\SettingsForm;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\PushChannel;
use YadamBiar\Channels\Sms\PatternRepository;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Icon;

/**
 * Settings row renderer.
 */
final class FieldRenderer {

	/**
	 * Print a row.
	 *
	 * @param string $key     Key.
	 * @param array  $field   Field.
	 * @param mixed  $value   Value.
	 * @param mixed  $default Default.
	 */
	public function render( $key, array $field, $value, $default ) {
		$name = 'yadambiar[' . $key . ']';
		$id   = 'yadambiar-' . $key;
		$attrs  = Visibility::attribute( $field );
		$attrs .= 'sms_patterns' === $field['type'] ? ' class="yadambiar-row-wide"' : '';
		if ( 'sms_patterns' === $field['type'] ) {
			echo '<tr' . $attrs . '><td colspan="2">';
		} else {
			echo '<tr' . $attrs . '><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		}
		switch ( $field['type'] ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', esc_attr( $id ), esc_attr( $name ), checked( (int) $value, 1, false ), esc_html__( 'فعال', 'yadambiar' ) );
				break;
			case 'multicheck':
				if ( isset( $field['style'] ) && 'cards' === $field['style'] ) {
					$this->multicheck_cards( $name, $field, (array) $value );
					break;
				}
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<label class="yadambiar-check"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>', esc_attr( $name ), esc_attr( $opt ), checked( in_array( $opt, (array) $value, true ), true, false ), esc_html( $label ) );
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'media':
				$preview = $value ? PushChannel::icon_url( $value, 96 ) : '';
				printf(
					'<div class="yadambiar-media" data-title="%1$s" data-button="%2$s"><span class="yadambiar-media-preview%3$s" data-empty="%11$s">%4$s</span><div class="yadambiar-media-main"><input type="text" class="regular-text" dir="ltr" id="%5$s" name="%6$s" value="%7$s" placeholder="https://…"><div class="yadambiar-media-actions"><button type="button" class="button yadambiar-media-pick">%8$s</button><button type="button" class="button-link yadambiar-media-clear"%9$s>%10$s</button></div></div></div>',
					esc_attr( $field['label'] ),
					esc_attr__( 'استفاده به‌عنوان آیکون', 'yadambiar' ),
					$preview ? '' : ' is-empty',
					$preview ? '<img src="' . esc_url( $preview ) . '" alt="">' : Icon::svg( 'bell' ), // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( 'انتخاب از کتابخانه رسانه', 'yadambiar' ),
					$value ? '' : ' hidden',
					esc_html__( 'حذف', 'yadambiar' ),
					esc_attr( Icon::svg( 'bell' ) )
				);
				break;
			case 'number':
				$input = sprintf( '<input type="number" min="%s"%s class="small-text" id="%s" name="%s" value="%s">', esc_attr( isset( $field['min'] ) ? $field['min'] : 0 ), isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				if ( ! empty( $field['suffix'] ) ) {
					$input = '<span class="yadambiar-num-wrap">' . $input . '<span class="yadambiar-num-suffix">' . esc_html( $field['suffix'] ) . '</span></span>';
				}
				echo $input; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'textarea':
			case 'html':
				printf( '<textarea id="%s" name="%s" rows="%d" class="large-text" %s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), 'html' === $field['type'] ? 5 : 3, 'html' === $field['type'] ? 'dir="auto"' : ( ! empty( $field['dir'] ) ? 'dir="' . esc_attr( $field['dir'] ) . '"' : '' ), esc_textarea( $value ) );
				if ( $default && $default !== $value ) {
					printf( ' <button type="button" class="button-link yadambiar-reset" data-target="%s" data-default="%s">%s</button>', esc_attr( $id ), esc_attr( $default ), esc_html__( 'بازگردانی پیش‌فرض', 'yadambiar' ) );
				}
				break;
			case 'sms_patterns':
				( new PatternRepeater() )->render( $name, PatternRepository::pattern_items() );
				break;
			case 'color':
				printf( '<input type="text" class="yadambiar-color" id="%s" name="%s" value="%s" data-default-color="%s" dir="ltr">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $default ) );
				break;
			case 'password':
				printf( '<input type="password" autocomplete="new-password" class="regular-text" dir="ltr" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="text" class="regular-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}
		if ( ! empty( $field['desc_by'] ) ) {
			// Hint that depends on another setting (e.g. per provider): {setting: {value: text}}; JS swaps it live.
			$dep  = key( $field['desc_by'] );
			$map  = current( $field['desc_by'] );
			$cur  = (string) Settings::get( $dep );
			$text = isset( $map[ $cur ] ) ? $map[ $cur ] : ( ! empty( $field['desc'] ) ? $field['desc'] : '' );
			printf( '<p class="description" data-desc-by="%s" data-desc-default="%s">%s</p>', esc_attr( wp_json_encode( $field['desc_by'] ) ), esc_attr( ! empty( $field['desc'] ) ? $field['desc'] : '' ), esc_html( $text ) );
		} elseif ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Multicheck as selectable cards (icon, label and an optional hint read from another setting).
	 *
	 * @param string $name  Input name.
	 * @param array  $field Field: options, icons, hints (option => setting key).
	 * @param array  $value Checked options.
	 */
	private function multicheck_cards( $name, array $field, array $value ) {
		echo '<div class="yadambiar-cards">';
		foreach ( $field['options'] as $opt => $label ) {
			$hint_key = isset( $field['hints'][ $opt ] ) ? $field['hints'][ $opt ] : '';
			$hint     = $hint_key ? trim( (string) Settings::get( $hint_key ) ) : '';
			printf(
				'<label class="yadambiar-cardopt"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s><span class="yadambiar-cardopt-box"><span class="yadambiar-cardopt-icon">%4$s</span><span class="yadambiar-cardopt-text"><b>%5$s</b>%6$s</span><span class="yadambiar-cardopt-tick" aria-hidden="true"></span></span></label>',
				esc_attr( $name ),
				esc_attr( $opt ),
				checked( in_array( $opt, $value, true ), true, false ),
				Icon::svg( isset( $field['icons'][ $opt ] ) ? $field['icons'][ $opt ] : 'bell' ), // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html( $label ),
				$hint_key ? sprintf(
					'<small class="yadambiar-cardopt-hint%1$s" data-hint-from="%2$s" data-empty="%3$s" dir="auto">%4$s</small>',
					$hint ? '' : ' is-missing',
					esc_attr( $hint_key ),
					esc_attr__( 'شناسه کانال وارد نشده', 'yadambiar' ),
					esc_html( $hint ? $hint : __( 'شناسه کانال وارد نشده', 'yadambiar' ) )
				) : ''
			);
		}
		echo '</div>';
	}
}
