<?php
/**
 * A group of REST routes under the plugin namespace.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Contracts;

defined( 'ABSPATH' ) || exit;

interface RestRoutes {

	/**
	 * Register the routes (called on rest_api_init).
	 *
	 * @param string $namespace REST namespace.
	 */
	public function routes( $namespace );
}
