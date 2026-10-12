<?php
/**
 * REST: on-site notifications of the current visitor (bell).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\OnsiteChannel;
use YadamBiar\Contracts\RestRoutes;
use YadamBiar\Support\Visitor;

/**
 * Bell endpoints (public: scoped to the visitor's own key).
 */
final class NotificationsController implements RestRoutes {

	/**
	 * Routes.
	 *
	 * @param string $namespace Namespace.
	 */
	public function routes( $namespace ) {
		register_rest_route(
			$namespace,
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$namespace,
			'/notifications/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_read' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Unread count and latest items.
	 *
	 * @return \WP_REST_Response
	 */
	public function index() {
		$owner = Visitor::owner_key();
		return rest_ensure_response(
			array(
				'unread' => $owner ? OnsiteChannel::unread( $owner ) : 0,
				'items'  => $owner ? OnsiteChannel::latest( $owner ) : array(),
			)
		);
	}

	/**
	 * Mark all read.
	 *
	 * @return \WP_REST_Response
	 */
	public function mark_read() {
		$owner = Visitor::owner_key();
		if ( $owner ) {
			OnsiteChannel::mark_read( $owner );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}
}
