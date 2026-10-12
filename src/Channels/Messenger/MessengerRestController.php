<?php
/**
 * REST routes of the messengers: bot webhook, deep link for the visitor, link status.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\RestRoutes;
use YadamBiar\Frontend\Account;
use YadamBiar\Http\RateLimiter;
use YadamBiar\Support\Visitor;
/**
 * REST routes of the messengers: bot webhook, deep link for the visitor, link status.
 */
final class MessengerRestController implements RestRoutes {

	const LINKS_PER_HOUR = 20;

	/**
	 * REST routes.
	 *
	 * @param string $namespace Namespace.
	 */
	public function routes( $namespace ) {
		$network = array(
			'type'     => 'string',
			'enum'     => array_keys( MessengerConfig::networks() ),
			'required' => true,
		);
		register_rest_route(
			$namespace,
			'/bot/(?P<network>telegram|bale)',
			array(
				'methods'             => 'POST',
				'callback'            => array( BotWebhook::class, 'handle' ),
				'permission_callback' => array( BotWebhook::class, 'authorize' ),
			)
		);
		register_rest_route(
			$namespace,
			'/messenger/link',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_link' ),
				'permission_callback' => '__return_true', // Public: visitors link their own key; rate limited.
				'args'                => array(
					'network' => $network,
					'token'   => array( 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/messenger/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_status' ),
				'permission_callback' => '__return_true', // Public: answers only for the visitor's own key.
				'args'                => array(
					'token' => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * Create a deep link for the current visitor.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_link( \WP_REST_Request $req ) {
		$network = sanitize_key( (string) $req->get_param( 'network' ) );
		if ( ! MessengerConfig::personal_ready( $network ) || ! RateLimiter::allow( 'msglink', self::LINKS_PER_HOUR ) ) {
			return new \WP_Error( 'yadambiar_error', __( 'این پیام‌رسان فعال نیست.', 'yadambiar' ), array( 'status' => 400 ) );
		}
		$owner = Account::current_owner( (string) $req->get_param( 'token' ) );
		$key   = isset( $owner['user_id'] ) ? 'u:' . $owner['user_id'] : ( isset( $owner['owner_key'] ) ? $owner['owner_key'] : Visitor::owner_key( true ) );
		return rest_ensure_response(
			array(
				'url'       => LinkRepository::deep_link( $network, $key ),
				'connected' => (bool) LinkRepository::chat_id( $key, $network ),
			)
		);
	}

	/**
	 * Which networks the current visitor has linked.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_status( \WP_REST_Request $req ) {
		$owner = Account::current_owner( (string) $req->get_param( 'token' ) );
		$key   = isset( $owner['user_id'] ) ? 'u:' . $owner['user_id'] : ( isset( $owner['owner_key'] ) ? $owner['owner_key'] : '' );
		$out   = array();
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			if ( MessengerConfig::personal_ready( $network ) ) {
				$out[ $network ] = (bool) LinkRepository::chat_id( $key, $network );
			}
		}
		return rest_ensure_response( $out );
	}
}
