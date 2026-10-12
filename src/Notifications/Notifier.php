<?php
/**
 * Sends one notification: claims the request, builds the message (coupon, exclusive window) and records the outcome.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Commerce\Coupons;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Commerce\Tracking;
use YadamBiar\Frontend\Account;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Clock;
use YadamBiar\Support\Money;
use YadamBiar\Support\OutgoingWebhook;
use YadamBiar\Support\ProductAttributes;
/**
 * Sends one notification: claims the request, builds the message (coupon, exclusive window) and records the outcome.
 */
final class Notifier {

	/**
	 * Build message variables.
	 *
	 * @param object     $sub     Subscription.
	 * @param WC_Product $target  Product / variation that satisfied the rule.
	 * @param array      $snap    Snapshot.
	 * @param bool       $exclusive Exclusive window opened.
	 * @return array
	 */
	public static function vars( $sub, $target, $snap, $exclusive = false ) {
		$parent    = $target->is_type( 'variation' ) ? wc_get_product( $target->get_parent_id() ) : $target;
		$variation = $target->is_type( 'variation' ) ? wc_get_formatted_variation( $target, true, false, false ) : ProductAttributes::attributes_label( $sub->attributes );
		$minutes   = (int) Settings::get( 'exclusive_minutes', 30 );
		$target_p  = null !== $sub->price_below ? $sub->price_below : $sub->price_above;

		return apply_filters(
			'yadambiar_message_vars',
			array(
				'customer_name'  => $sub->name ? $sub->name : __( 'مشتری', 'yadambiar' ),
				'product_name'   => $parent ? $parent->get_name() : $target->get_name(),
				'variation'      => $variation ? '(' . $variation . ')' : '',
				'price'          => null === $snap['price'] ? __( 'استعلام در سایت', 'yadambiar' ) : Money::price_text( $snap['price'] ),
				'old_price'      => Money::price_text( $sub->base_price ),
				'regular_price'  => Money::price_text( $target->get_regular_price() ),
				'target_price'   => Money::price_text( $target_p ),
				'stock'          => null !== $snap['qty'] ? $snap['qty'] : __( 'موجود', 'yadambiar' ),
				'link'           => Tracking::link( $sub, $target->get_id() ),
				'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'minutes'        => $minutes,
				/* translators: %d minutes */
				'exclusive_note' => $exclusive ? '<p><strong>' . sprintf( __( 'شما %d دقیقه فرصت خرید اختصاصی دارید؛ پس از آن محصول برای همه قابل خرید می‌شود.', 'yadambiar' ), $minutes ) . '</strong></p>' : '',
				'manage_link'    => Account::manage_url( $sub ),
				'code'           => '',
			),
			$sub,
			$target
		);
	}

	/**
	 * Notify a subscriber on all of their channels.
	 *
	 * @param object     $sub        Subscription.
	 * @param string     $event      Event.
	 * @param WC_Product $target     Target product.
	 * @param array      $snap       Snapshot.
	 * @param bool       $stock_type Is a stock-arrival notification.
	 * @return bool At least one channel succeeded.
	 */
	public static function notify( $sub, $event, $target, $snap, $stock_type = false ) {
		// Claim the subscription first: if another run already took it, do not send again.
		$recurring = RuleEngine::is_recurring( $sub );
		if ( ! SubscriptionRepository::claim( $sub, $recurring ) ) {
			return false;
		}

		$exclusive = $stock_type && Reservation::open_window( $target );
		$coupon    = Coupons::applies( $sub, $event ) ? Coupons::create( $sub, $target ) : '';
		$vars      = array_merge( self::vars( $sub, $target, $snap, $exclusive ), Coupons::vars( $coupon ) );
		if ( $exclusive ) {
			/* translators: %d minutes */
			$vars['exclusive_note_text'] = sprintf( __( '%d دقیقه فرصت خرید اختصاصی دارید.', 'yadambiar' ), $vars['minutes'] );
		}

		$message = ChannelManager::build_message( $event, $vars );
		if ( $exclusive ) {
			$message['text'] .= "\n" . $vars['exclusive_note_text'];
		}
		// Templates written before coupons existed get the coupon appended automatically.
		if ( $coupon && false === strpos( (string) Settings::get( 'tpl_' . $event . '_sms' ), '{coupon' ) ) {
			$message['text'] .= "\n" . $vars['coupon_note'];
		}
		if ( $coupon && false === strpos( (string) Settings::get( 'tpl_' . $event . '_body' ), '{coupon' ) ) {
			$message['html'] .= '<p style="background:#fff7e6;padding:12px;border-radius:8px"><strong>' . esc_html( $vars['coupon_note'] ) . '</strong></p>';
		}

		$sub->notified_target = $target->get_id(); // Logged with each send.
		$results              = ChannelManager::send_to_subscription( $sub, $event, $message );
		$ok      = in_array( true, $results, true );

		if ( ! $ok ) {
			SubscriptionRepository::release( $sub, $recurring ); // Nothing went out: keep waiting.
		}

		if ( $ok ) {
			$update = array(
				'notified_count'   => $sub->notified_count + 1,
				'last_notified_at' => Clock::now(),
				'notified_target'  => $target->get_id(),
			);
			if ( $recurring ) {
				$update['base_price'] = $snap['price'];
			} else {
				$update['status'] = 'notified';
			}
			if ( $exclusive ) {
				$update['reserved_until'] = Clock::now( $vars['minutes'] * MINUTE_IN_SECONDS );
			}
			SubscriptionRepository::update( $sub->id, $update );

			OutgoingWebhook::send(
				'notified',
				array(
					'subscription_id' => $sub->id,
					'event'           => $event,
					'product_id'      => $sub->product_id,
					'target_id'       => $target->get_id(),
					'price'           => $snap['price'],
					'stock'           => $snap['qty'],
					'channels'        => array_keys( array_filter( $results ) ),
				)
			);
			do_action( 'yadambiar_notified', $sub, $event, $target, $results );
		}

		return $ok;
	}
}
