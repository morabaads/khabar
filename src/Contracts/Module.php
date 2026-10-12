<?php
/**
 * A self-contained feature that wires itself into WordPress.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Modules only register hooks in register(); all work happens in the hooked callbacks.
 */
interface Module {

	/**
	 * Register hooks (actions, filters, shortcodes, routes).
	 */
	public function register();
}
