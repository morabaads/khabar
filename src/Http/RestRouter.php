<?php
/**
 * Registers every REST route group of the plugin under yadambiar/v1.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Http;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Messenger\MessengerRestController;
use YadamBiar\Contracts\Module;
use YadamBiar\Contracts\RestRoutes;
use YadamBiar\Http\Controllers\NotificationsController;
use YadamBiar\Http\Controllers\PushController;
use YadamBiar\Http\Controllers\SubscribeController;
use YadamBiar\Http\Controllers\SubscriptionsController;
use YadamBiar\Support\Failure;

/**
 * REST router.
 */
final class RestRouter implements Module {

	const NAMESPACE_V1 = 'yadambiar/v1';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Route groups (filterable with "yadambiar_rest_controllers").
	 *
	 * @return RestRoutes[]
	 */
	private function controllers() {
		return (array) apply_filters(
			'yadambiar_rest_controllers',
			array(
				new SubscribeController(),
				new SubscriptionsController(),
				new NotificationsController(),
				new PushController(),
				new MessengerRestController(),
			)
		);
	}

	/**
	 * Register all routes.
	 */
	public function routes() {
		foreach ( $this->controllers() as $controller ) {
			if ( $controller instanceof RestRoutes ) {
				$controller->routes( self::NAMESPACE_V1 );
			}
		}
	}

	/**
	 * Error response.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	public static function error( $message, $status = 400 ) {
		return Failure::of( $message, $status );
	}

	/**
	 * Schema of an optional string arg.
	 *
	 * @return array
	 */
	public static function string_arg() {
		return array( 'type' => 'string' );
	}

	/**
	 * Schema of a positive id arg.
	 *
	 * @param bool $required Required.
	 * @return array
	 */
	public static function id_arg( $required = false ) {
		return array(
			'type'     => 'integer',
			'minimum'  => $required ? 1 : 0,
			'required' => $required,
		);
	}
}
