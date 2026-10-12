<?php
/**
 * Public representation of a request (REST, outgoing webhooks, personal data export).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Subscriptions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Support\ProductAttributes;
/**
 * Subscription presenter.
 */
final class SubscriptionPresenter {

	/**
	 * Public fields of a request.
	 *
	 * @param object|null $sub Subscription.
	 * @return array
	 */
	public static function export( $sub ) {
		if ( ! $sub ) {
			return array();
		}
		$product  = wc_get_product( $sub->variation_id ? $sub->variation_id : $sub->product_id );
		$statuses = SubscriptionRepository::statuses();
		return array(
			'id'           => $sub->id,
			'product_id'   => $sub->product_id,
			'variation_id' => $sub->variation_id,
			'product'      => $product ? $product->get_name() : '',
			'url'          => $product ? $product->get_permalink() : '',
			'attributes'   => ProductAttributes::attributes_label( $sub->attributes ),
			'type'         => $sub->type,
			'conditions'   => SubscriptionRepository::conditions_label( $sub ),
			'price_below'  => $sub->price_below,
			'price_above'  => $sub->price_above,
			'min_qty'      => $sub->min_qty,
			'status'       => $sub->status,
			'status_label' => isset( $statuses[ $sub->status ] ) ? $statuses[ $sub->status ] : $sub->status,
			'email'        => $sub->email,
			'phone'        => $sub->phone,
			'channels'     => $sub->channel_list,
			'created_at'   => $sub->created_at,
		);
	
	}
}
