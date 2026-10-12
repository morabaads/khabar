<?php
/**
 * REST: the customer's own requests ("my alerts"): list, edit, cancel.
 * Access: the logged-in owner, or a guest holding the management token sent to them.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\RestRoutes;
use YadamBiar\Frontend\Account;
use YadamBiar\Http\RestRouter;
use YadamBiar\Subscriptions\SubscriptionPresenter;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Subscriptions\SubscriptionUpdater;
use YadamBiar\Support\Queue;

/**
 * My requests endpoints.
 */
final class SubscriptionsController implements RestRoutes {

	/**
	 * Routes.
	 *
	 * @param string $namespace Namespace.
	 */
	public function routes( $namespace ) {
		$token = array( 'token' => RestRouter::string_arg() );
		register_rest_route(
			$namespace,
			'/subscriptions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'has_owner' ),
				'args'                => $token,
			)
		);
		register_rest_route(
			$namespace,
			'/subscriptions/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'owns' ),
					'args'                => $token + array( 'id' => RestRouter::id_arg( true ) ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'cancel' ),
					'permission_callback' => array( $this, 'owns' ),
					'args'                => $token + array( 'id' => RestRouter::id_arg( true ) ),
				),
			)
		);
	}

	/**
	 * Owner of the request (logged-in user or management token).
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return array
	 */
	private function owner( \WP_REST_Request $req ) {
		return Account::current_owner( (string) $req->get_param( 'token' ) );
	}

	/**
	 * Permission: someone identifiable.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return bool|\WP_Error
	 */
	public function has_owner( \WP_REST_Request $req ) {
		return $this->owner( $req ) ? true : RestRouter::error( __( 'دسترسی ندارید.', 'yadambiar' ), 401 );
	}

	/**
	 * Permission: the request belongs to the caller.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return bool|\WP_Error
	 */
	public function owns( \WP_REST_Request $req ) {
		$sub = SubscriptionRepository::get( (int) $req['id'] );
		return SubscriptionRepository::owned_by( $sub, $this->owner( $req ) ) ? true : RestRouter::error( __( 'دسترسی ندارید.', 'yadambiar' ), 403 );
	}

	/**
	 * List.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $req ) {
		return rest_ensure_response( array_map( array( SubscriptionPresenter::class, 'export' ), SubscriptionRepository::for_owner( $this->owner( $req ) ) ) );
	}

	/**
	 * Edit target price / minimum stock / contact / channels.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $req ) {
		$sub  = SubscriptionRepository::get( (int) $req['id'] );
		$data = ( new SubscriptionUpdater( $req->get_params() ) )->changes( $sub );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( $data ) {
			SubscriptionRepository::update( $sub->id, $data );
			Queue::schedule( 'yadambiar_check_product', array( (int) $sub->product_id ), 10 );
		}
		return rest_ensure_response(
			array(
				'success'      => true,
				'subscription' => SubscriptionPresenter::export( SubscriptionRepository::get( $sub->id ) ),
			)
		);
	}

	/**
	 * Cancel.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response
	 */
	public function cancel( \WP_REST_Request $req ) {
		SubscriptionRepository::update( (int) $req['id'], array( 'status' => 'cancelled' ) );
		return rest_ensure_response( array( 'success' => true ) );
	}
}
