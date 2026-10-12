<?php
/**
 * Markup helpers for admin-post forms.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Ui;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;

/**
 * Opens/closes POST forms that target an admin-post action with its nonce.
 */
final class Form {

	/**
	 * Print the opening tag, the action field and the nonce.
	 *
	 * @param string $action Action name without prefix.
	 * @param string $class  CSS class.
	 */
	public static function open( $action, $class = '' ) {
		printf( '<form method="post" action="%s"%s>', esc_url( admin_url( 'admin-post.php' ) ), $class ? ' class="' . esc_attr( $class ) . '"' : '' );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( AdminUrl::ACTION_PREFIX . $action ) );
		wp_nonce_field( AdminUrl::ACTION_PREFIX . $action );
	}

	/**
	 * A form holding a single submit button.
	 *
	 * @param string $action Action name without prefix.
	 * @param string $label  Button text.
	 * @param string $class  Button classes.
	 */
	public static function button( $action, $label, $class = 'button' ) {
		self::open( $action );
		printf( '<button type="submit" class="%s">%s</button>', esc_attr( $class ), esc_html( $label ) );
		self::close();
	}

	/**
	 * Close the form.
	 */
	public static function close() {
		echo '</form>';
	}
}
