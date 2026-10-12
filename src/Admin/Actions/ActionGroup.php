<?php
/**
 * Base of the admin-post handlers: every action is gated by capability + nonce in one place.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Actions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Access;
use YadamBiar\Admin\AdminUrl;
use YadamBiar\Admin\Flash;
use YadamBiar\Contracts\Module;

/**
 * A group of related admin-post actions.
 */
abstract class ActionGroup implements Module {

	/**
	 * Actions of this group.
	 *
	 * @return array Action name (without prefix) => method name.
	 */
	abstract protected function actions();

	/**
	 * Hook every action behind the capability + nonce guard (nonce action = full action name).
	 */
	public function register() {
		foreach ( $this->actions() as $action => $method ) {
			$hook = AdminUrl::ACTION_PREFIX . $action;
			add_action(
				'admin_post_' . $hook,
				function () use ( $hook, $method ) {
					Access::guard( $hook );
					$this->$method();
				}
			);
		}
	}

	/**
	 * Redirect with a flash notice.
	 *
	 * @param string $url     URL.
	 * @param string $message Text.
	 * @param string $type    success|error.
	 */
	protected function done( $url, $message, $type = 'success' ) {
		Flash::redirect( $url, $message, $type );
	}

	/**
	 * Referring admin URL, or a fallback.
	 *
	 * @param string $fallback URL.
	 * @return string
	 */
	protected function referer( $fallback ) {
		$referer = wp_get_referer();
		return $referer ? $referer : $fallback;
	}

	/**
	 * Integer request arg.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	protected function int_arg( $key ) {
		return isset( $_REQUEST[ $key ] ) ? absint( $_REQUEST[ $key ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked by the guard.
	}

	/**
	 * Key-like request arg.
	 *
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	protected function key_arg( $key, $default = '' ) {
		return isset( $_REQUEST[ $key ] ) && is_string( $_REQUEST[ $key ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Single-line text request arg.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	protected function text_arg( $key ) {
		return isset( $_REQUEST[ $key ] ) && is_string( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}
}
