<?php
/**
 * Defaults shared by the admin app sections.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\AdminPage;

/**
 * Base admin section.
 */
abstract class Page implements AdminPage {

	/**
	 * Icon name.
	 *
	 * @return string
	 */
	public function icon() {
		return 'grid';
	}

	/**
	 * Listed in navigation.
	 *
	 * @return bool
	 */
	public function in_menu() {
		return true;
	}

	/**
	 * Nothing to do before output by default.
	 */
	public function load() {}

	/**
	 * Integer query arg.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	protected function query_int( $key ) {
		return isset( $_GET[ $key ] ) ? absint( $_GET[ $key ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Key-like query arg.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	protected function query_key( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Hidden inputs that keep a GET filter form inside this section.
	 */
	protected function view_fields() {
		printf( '<input type="hidden" name="page" value="yadambiar"><input type="hidden" name="view" value="%s">', esc_attr( $this->slug() ) );
	}
}
