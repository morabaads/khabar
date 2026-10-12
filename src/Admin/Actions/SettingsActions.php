<?php
/**
 * Admin action: save a settings tab.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Actions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Settings\Schema;
use YadamBiar\Settings\Settings;

/**
 * Settings form handler.
 */
final class SettingsActions extends ActionGroup {

	/**
	 * Actions.
	 *
	 * @return array
	 */
	protected function actions() {
		return array( 'save_settings' => 'save' );
	}

	/**
	 * Save the submitted tab (values are validated per field type by Settings::save()).
	 */
	public function save() {
		$tab = $this->key_arg( 'tab', 'general' );
		$tab = isset( Schema::all()[ $tab ] ) ? $tab : 'general';
		$raw = isset( $_POST['yadambiar'] ) && is_array( $_POST['yadambiar'] ) ? $_POST['yadambiar'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification -- sanitized per field in Settings::save().
		Settings::save( $raw, $tab );
		if ( Settings::has( 'channels_enabled', 'push' ) ) {
			PushChannel::keys(); // Make sure the VAPID key pair exists.
		}
		$this->done( AdminUrl::settings( $tab ), __( 'تنظیمات ذخیره شد.', 'yadambiar' ) );
	}
}
