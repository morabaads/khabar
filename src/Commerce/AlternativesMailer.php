<?php
/**
 * Daily: suggests in-stock alternatives to customers who have waited long (each request at most once).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Notifications\Notifier;
use YadamBiar\Notifications\RuleEngine;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Clock;
use YadamBiar\Support\Money;
/**
 * Daily: suggests in-stock alternatives to customers who have waited long (each request at most once).
 */
final class AlternativesMailer {

	/**
	 * Is the awaited item still unavailable for this subscription.
	 *
	 * @param object     $sub     Subscription.
	 * @param WC_Product $product Parent product.
	 * @return bool
	 */
	private static function still_unavailable( $sub, $product ) {
		if ( $sub->variation_id ) {
			$v = wc_get_product( $sub->variation_id );
			return $v && ! $v->is_in_stock();
		}
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && $child->is_in_stock() && RuleEngine::attributes_match( $sub->attributes, $child->get_variation_attributes() ) ) {
					return false;
				}
			}
			return true;
		}
		return ! $product->is_in_stock();
	}

	/**
	 * Daily job: send suggestions to long-waiting customers (each subscription at most once).
	 *
	 * @param int $limit Max subscriptions per run.
	 * @return int Sent count.
	 */
	public static function run( $limit = 100 ) {
		if ( ! Settings::get( 'alt_enabled' ) ) {
			return 0;
		}
		$before = Clock::now( -max( 1, (int) Settings::get( 'alt_after_days', 14 ) ) * DAY_IN_SECONDS );
		$subs   = SubscriptionRepository::awaiting_alternatives( $before, $limit );
		$sent   = 0;
		$count  = max( 1, (int) Settings::get( 'alt_count', 3 ) );

		foreach ( $subs as $sub ) {
			$product = wc_get_product( $sub->product_id );
			if ( ! $product || ! self::still_unavailable( $sub, $product ) ) {
				SubscriptionRepository::update( $sub->id, array() ); // Touch updated_at so others get their turn.
				continue;
			}
			$awaited = $sub->variation_id ? wc_get_product( $sub->variation_id ) : $product;
			if ( ! $awaited ) {
				continue;
			}
			$wanted  = $sub->variation_id && $awaited ? $awaited->get_variation_attributes() : $sub->attributes;
			$alts    = AlternativeFinder::find( $product, $wanted, '' === $awaited->get_price() ? null : (float) $awaited->get_price(), $count );
			if ( ! $alts ) {
				SubscriptionRepository::update( $sub->id, array() );
				continue;
			}
			if ( self::send( $sub, $awaited, $alts ) ) {
				++$sent;
				SubscriptionRepository::update( $sub->id, array( 'alt_sent_at' => Clock::now() ) );
			} else {
				SubscriptionRepository::update( $sub->id, array() ); // Retry on a later run.
			}
		}
		return $sent;
	}

	/**
	 * Send the suggestion message.
	 *
	 * @param object     $sub     Subscription.
	 * @param WC_Product $awaited Awaited product / variation.
	 * @param array[]    $alts    Alternatives.
	 * @return bool
	 */
	public static function send( $sub, $awaited, $alts ) {
		$snap  = RuleEngine::snapshot( $awaited );
		$vars  = Notifier::vars( $sub, $awaited, $snap );
		$first = $alts[0]['target'];
		$html  = '<table role="presentation" cellpadding="8" style="width:100%;border-collapse:collapse">';
		foreach ( $alts as $alt ) {
			$link  = Tracking::link( $sub, $alt['target']->get_id(), 'alt' );
			$img   = wp_get_attachment_image_url( $alt['target']->get_image_id() ? $alt['target']->get_image_id() : $alt['product']->get_image_id(), 'thumbnail' );
			$html .= '<tr style="border-bottom:1px solid #eee">'
				. '<td style="width:72px">' . ( $img ? '<img src="' . esc_url( $img ) . '" width="64" height="64" style="border-radius:8px;object-fit:cover" alt="">' : '' ) . '</td>'
				. '<td><a href="' . esc_url( $link ) . '" style="font-weight:bold">' . esc_html( $alt['target']->get_name() ) . '</a><br>' . esc_html( Money::price_text( $alt['target']->get_price() ) ) . '</td></tr>';
		}
		$html .= '</table>';

		$vars['alt_name']          = $first->get_name();
		$vars['alt_price']         = Money::price_text( $first->get_price() );
		$vars['alt_link']          = Tracking::link( $sub, $first->get_id(), 'alt' );
		$vars['alternatives_html'] = $html;
		$vars['link']              = $vars['alt_link'];

		$message = ChannelManager::build_message( 'alternatives', $vars );
		$results = ChannelManager::send_to_subscription( $sub, 'alternatives', $message );
		do_action( 'yadambiar_alternatives_sent', $sub, $alts, $results );
		return in_array( true, $results, true );
	}
}
