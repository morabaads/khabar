<?php
/**
 * REST: create requests from the notify form and confirm them with a one-time code.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\RestRoutes;
use YadamBiar\Http\RestRouter;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\OtpService;
use YadamBiar\Subscriptions\SubscribeService;
use YadamBiar\Subscriptions\SubscriptionPresenter;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\OutgoingWebhook;
use YadamBiar\Support\Queue;

/**
 * Subscribe + verify endpoints (public: guests may subscribe; protected by honeypot,
 * rate limits, contact limits and optional verification).
 */
final class SubscribeController implements RestRoutes {

	/**
	 * Routes.
	 *
	 * @param string $namespace Namespace.
	 */
	public function routes( $namespace ) {
		register_rest_route(
			$namespace,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'subscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'   => RestRouter::id_arg( true ),
					'variation_id' => RestRouter::id_arg(),
					'name'         => RestRouter::string_arg(),
					'email'        => RestRouter::string_arg(),
					'phone'        => RestRouter::string_arg(),
					'mode'         => RestRouter::string_arg(),
					'website'      => RestRouter::string_arg(),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'verify' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'contact' => array(
						'type'     => 'string',
						'required' => true,
					),
					'code'    => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Create request(s).
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function subscribe( \WP_REST_Request $req ) {
		if ( '' !== (string) $req->get_param( 'website' ) ) { // Honeypot: pretend success to bots.
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => Settings::get( 'success_message' ),
				)
			);
		}
		$result = ( new SubscribeService( $req->get_params() ) )->run();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Verify the code and activate the pending requests.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify( \WP_REST_Request $req ) {
		$ids = OtpService::verify( (string) $req->get_param( 'contact' ), (string) $req->get_param( 'code' ) );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		$pid = 0;
		foreach ( $ids as $id ) {
			$sub = SubscriptionRepository::get( $id );
			if ( $sub && 'pending' === $sub->status ) {
				SubscriptionRepository::update( $id, array( 'status' => 'active' ) );
				OutgoingWebhook::send( 'subscribed', array( 'subscription' => SubscriptionPresenter::export( $sub ) ) );
				$pid = (int) $sub->product_id;
			}
		}
		if ( $pid ) {
			Queue::schedule( 'yadambiar_check_product', array( $pid ), 10 );
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => Settings::get( 'success_message' ),
			)
		);
	}
}
