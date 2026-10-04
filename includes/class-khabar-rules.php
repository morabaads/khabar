<?php
/**
 * Rule engine: decides whether a subscription is satisfied by a product/variation.
 *
 * Pure logic (operates on a "snapshot" array) so it can be unit tested without WordPress.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Rules {

	/**
	 * Build a snapshot of a product state.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return array
	 */
	public static function snapshot( $product ) {
		$status = $product->get_stock_status();
		$in     = 'instock' === $status || ( 'onbackorder' === $status && Khabar_Settings::get( 'backorder_as_in_stock' ) );
		return array(
			'id'         => $product->get_id(),
			'in_stock'   => $in && $product->is_purchasable(),
			'manage'     => $product->managing_stock(),
			'qty'        => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
			'price'      => '' === $product->get_price() ? null : (float) $product->get_price(),
			'attributes' => $product->is_type( 'variation' ) ? $product->get_variation_attributes() : array(),
		);
	}

	/**
	 * Do variation attributes satisfy the requested attributes?
	 * A variation attribute with an empty value means "any" and matches anything.
	 *
	 * @param array $wanted    attribute_x => value (subset).
	 * @param array $available attribute_x => value of the variation.
	 * @return bool
	 */
	public static function attributes_match( $wanted, $available ) {
		foreach ( (array) $wanted as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			if ( ! array_key_exists( $key, $available ) ) {
				return false;
			}
			$have = (string) $available[ $key ];
			if ( '' !== $have && strtolower( $have ) !== strtolower( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Evaluate a subscription against a snapshot. All configured conditions are ANDed.
	 *
	 * @param object $sub   Subscription (hydrated).
	 * @param array  $snap  Snapshot.
	 * @param array  $opts  price_requires_stock (bool), cooldown_ok (bool).
	 * @return string|false Event name or false.
	 */
	public static function evaluate( $sub, $snap, $opts = array() ) {
		$opts = array_merge(
			array(
				'price_requires_stock' => true,
				'cooldown_ok'          => true,
				'low_stock_threshold'  => 0,
			),
			$opts
		);

		$price      = $snap['price'];
		$has_price  = null !== $price;
		$price_cond = null !== $sub->price_below || null !== $sub->price_above || $sub->price_change;

		if ( $sub->in_stock || $sub->min_qty ) {
			if ( ! $snap['in_stock'] ) {
				return false;
			}
		} elseif ( $price_cond && $opts['price_requires_stock'] && ! $snap['in_stock'] ) {
			return false;
		}

		if ( $sub->min_qty && $snap['manage'] && (int) $snap['qty'] < (int) $sub->min_qty ) {
			return false;
		}
		if ( null !== $sub->price_below && ( ! $has_price || $price > (float) $sub->price_below ) ) {
			return false;
		}
		if ( null !== $sub->price_above && ( ! $has_price || $price < (float) $sub->price_above ) ) {
			return false;
		}
		if ( $sub->price_change ) {
			if ( ! $has_price || null === $sub->base_price || abs( $price - (float) $sub->base_price ) < 0.0001 ) {
				return false;
			}
			if ( ! $opts['cooldown_ok'] ) {
				return false;
			}
		}

		if ( ! $sub->in_stock && ! $price_cond && ! $sub->min_qty ) {
			return false; // Nothing to evaluate.
		}

		switch ( $sub->type ) {
			case 'stock':
				$low = (int) $opts['low_stock_threshold'];
				return ( $low && $snap['manage'] && null !== $snap['qty'] && $snap['qty'] > 0 && $snap['qty'] <= $low ) ? 'low_stock' : 'back_in_stock';
			case 'price_drop':
			case 'price_rise':
			case 'price_change':
				return $sub->type;
			default:
				return 'combo';
		}
	}

	/**
	 * Whether a subscription is recurring (stays active after a notification).
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public static function is_recurring( $sub ) {
		return 'price_change' === $sub->type;
	}
}
