<?php
/**
 * Announces the restock of a high-demand product in the store's Telegram / Bale channels.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Notifications\DeliveryLog;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Money;
use YadamBiar\Support\Text;
/**
 * Announces the restock of a high-demand product in the store's Telegram / Bale channels.
 */
final class ChannelBroadcaster {

	/**
	 * Announce a restock of a high-demand product in the store's channels (once per restock).
	 *
	 * @param WC_Product $target  Product / variation.
	 * @param array      $snap    Snapshot.
	 * @param int        $waiting Number of people who were waiting.
	 * @return array network => true|WP_Error
	 */
	public static function broadcast_restock( $target, $snap, $waiting ) {
		$networks = (array) Settings::get( 'broadcast_networks', array() );
		if ( ! $networks || $waiting < max( 1, (int) Settings::get( 'broadcast_min_waiting', 3 ) ) ) {
			return array();
		}
		$last = (int) get_post_meta( $target->get_id(), '_yadambiar_broadcast_at', true );
		if ( $last && time() - $last < DAY_IN_SECONDS ) {
			return array();
		}
		update_post_meta( $target->get_id(), '_yadambiar_broadcast_at', time() );

		$parent = $target->is_type( 'variation' ) ? wc_get_product( $target->get_parent_id() ) : $target;
		$link   = $target->is_type( 'variation' ) ? add_query_arg( $target->get_variation_attributes(), get_permalink( $target->get_parent_id() ) ) : get_permalink( $target->get_id() );
		$link   = add_query_arg( array( 'utm_source' => 'yadambiar', 'utm_medium' => 'channel' ), $link );
		$vars   = array(
			'product_name' => $parent ? $parent->get_name() : $target->get_name(),
			'variation'    => $target->is_type( 'variation' ) ? '(' . wc_get_formatted_variation( $target, true, false, false ) . ')' : '',
			'price'        => Money::price_text( $snap['price'] ),
			'stock'        => null !== $snap['qty'] ? $snap['qty'] : '',
			'waiting'      => number_format_i18n( $waiting ),
			'link'         => '',
			'site_name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		$text   = trim( Text::render( Settings::get( 'tpl_broadcast_sms', '' ), $vars ) );
		$out    = array();
		foreach ( $networks as $network ) {
			$n = MessengerConfig::network( $network );
			if ( ! $n || ! $n['token'] || ! $n['channel'] ) {
				continue;
			}
			$out[ $network ] = BotApi::send( $network, $n['channel'], $text, $link );
			DeliveryLog::record( null, 'broadcast', $network, $n['channel'], $text, $out[ $network ] );
		}
		return $out;
	}
}
