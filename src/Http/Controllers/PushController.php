<?php
/**
 * REST: browser push subscriptions and the payload the service worker shows.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\PushChannel;
use YadamBiar\Channels\Push\PushSubscriptionRepository;
use YadamBiar\Contracts\RestRoutes;
use YadamBiar\Http\RateLimiter;
use YadamBiar\Http\RestRouter;
use YadamBiar\Support\Visitor;

/**
 * Push endpoints.
 */
final class PushController implements RestRoutes {

	const SUBSCRIBE_PER_HOUR = 20;

	/**
	 * Routes.
	 *
	 * @param string $namespace Namespace.
	 */
	public function routes( $namespace ) {
		register_rest_route(
			$namespace,
			'/push/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'subscription' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/push/latest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'latest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'endpoint' => array(
						'type'     => 'string',
						'format'   => 'uri',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Save the visitor's push subscription.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function subscribe( \WP_REST_Request $req ) {
		if ( ! PushChannel::enabled() || ! RateLimiter::allow( 'push', self::SUBSCRIBE_PER_HOUR ) ) {
			return RestRouter::error( __( 'پوش فعال نیست.', 'yadambiar' ) );
		}
		$ok = PushSubscriptionRepository::save_subscription( Visitor::owner_key( true ), (array) $req->get_param( 'subscription' ) );
		return $ok ? rest_ensure_response( array( 'success' => true ) ) : RestRouter::error( __( 'اشتراک نامعتبر است.', 'yadambiar' ) );
	}

	/**
	 * Latest notification for a push endpoint (the service worker asks after a ping).
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response
	 */
	public function latest( \WP_REST_Request $req ) {
		$data = PushChannel::latest_for_endpoint( (string) $req->get_param( 'endpoint' ) );
		return rest_ensure_response( $data ? $data : (object) array() );
	}
}
