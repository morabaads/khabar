<?php
/**
 * Bot webhook: /start <code> links the chat, /stop unlinks it, /list shows the active requests.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Subscriptions\SubscriptionRepository;
/**
 * Bot webhook: /start <code> links the chat, /stop unlinks it, /list shows the active requests.
 */
final class BotWebhook {

	/**
	 * Only the messenger knows the webhook secret (query arg, or Telegram's secret header).
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return bool|\WP_Error
	 */
	public static function authorize( \WP_REST_Request $req ) {
		$expect = BotApi::secret( (string) $req['network'] );
		$secret = (string) $req->get_param( 'secret' );
		$header = (string) $req->get_header( 'x_telegram_bot_api_secret_token' );
		if ( hash_equals( $expect, $secret ) || hash_equals( $expect, $header ) ) {
			return true;
		}
		return new \WP_Error( 'yadambiar_forbidden', 'forbidden', array( 'status' => 403 ) );
	}

	/**
	 * Handle an update: /start <code>, /stop and /list.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response
	 */
	public static function handle( \WP_REST_Request $req ) {
		$network = (string) $req['network'];
		$update  = $req->get_json_params();
		$message = isset( $update['message'] ) ? $update['message'] : array();
		$chat_id = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text    = isset( $message['text'] ) ? trim( (string) $message['text'] ) : '';
		if ( ! $chat_id || '' === $text ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( preg_match( '#^/start(?:@\w+)?\s+([a-z0-9]{10,64})$#i', $text, $m ) ) {
			$owner = LinkRepository::consume_code( $m[1] );
			if ( $owner ) {
				$username = isset( $message['from']['username'] ) ? $message['from']['username'] : '';
				LinkRepository::link( $owner, $network, $chat_id, $username );
				/* translators: %s site name */
				BotApi::send( $network, $chat_id, sprintf( __( '✅ حساب شما به «یادم بیار» %s متصل شد. از این پس اعلان‌ها را همین‌جا دریافت می‌کنید.', 'yadambiar' ), $site ) );
			} else {
				BotApi::send( $network, $chat_id, __( 'این لینک منقضی شده است. لطفاً از سایت دوباره روی «اتصال» بزنید.', 'yadambiar' ) );
			}
		} elseif ( preg_match( '#^/stop#i', $text ) ) {
			LinkRepository::unlink_chat( $network, $chat_id );
			BotApi::send( $network, $chat_id, __( 'اتصال قطع شد و دیگر پیامی دریافت نمی‌کنید.', 'yadambiar' ) );
		} elseif ( preg_match( '#^/(list|start)#i', $text ) ) {
			BotApi::send( $network, $chat_id, self::list_text( $network, $chat_id ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Text listing the chat's active requests.
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat id.
	 * @return string
	 */
	private static function list_text( $network, $chat_id ) {
		$owner = LinkRepository::owner_of_chat( $network, $chat_id );
		if ( ! $owner ) {
			return __( 'برای دریافت اعلان‌ها، در صفحه محصول روی «یادم بیار» بزنید و گزینه اتصال را انتخاب کنید.', 'yadambiar' );
		}
		$query = 0 === strpos( $owner, 'u:' ) ? array( 'user_id' => (int) substr( $owner, 2 ) ) : array( 'owner_key' => $owner );
		$lines = array();
		foreach ( SubscriptionRepository::for_owner( $query ) as $sub ) {
			if ( 'active' !== $sub->status ) {
				continue;
			}
			$p       = wc_get_product( $sub->variation_id ? $sub->variation_id : $sub->product_id );
			$lines[] = '• ' . ( $p ? $p->get_name() : '#' . $sub->product_id ) . ' — ' . SubscriptionRepository::conditions_label( $sub );
		}
		return $lines ? __( 'درخواست‌های فعال شما:', 'yadambiar' ) . "\n" . implode( "\n", array_slice( $lines, 0, 20 ) ) : __( 'درخواست فعالی ندارید.', 'yadambiar' );
	}
}
