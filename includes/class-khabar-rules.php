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
			'status'     => $status,
			'stocked'    => $in,
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
	 * Human explanation of why a subscription is / is not satisfied (diagnostics).
	 *
	 * @param object $sub  Subscription.
	 * @param array  $snap Snapshot.
	 * @param array  $opts Options as for evaluate().
	 * @return array ok (bool), reason (string)
	 */
	public static function explain( $sub, $snap, $opts = array() ) {
		$opts  = array_merge( array( 'price_requires_stock' => true, 'cooldown_ok' => true, 'low_stock_threshold' => 0 ), $opts );
		$event = self::evaluate( $sub, $snap, $opts );
		if ( $event ) {
			return array( 'ok' => true, 'reason' => __( 'شرایط برقرار است و باید اعلان ارسال شود', 'khabar' ) );
		}
		$price      = $snap['price'];
		$price_cond = null !== $sub->price_below || null !== $sub->price_above || $sub->price_change;
		$not_stock  = ! $snap['in_stock'];
		$why_stock  = ! empty( $snap['stocked'] ) && $not_stock ? __( 'کالا موجود است ولی قابل خرید نیست (قیمت ندارد یا منتشر نشده)', 'khabar' ) : __( 'کالا هنوز ناموجود است', 'khabar' );
		if ( ( $sub->in_stock || $sub->min_qty ) && $not_stock ) {
			return array( 'ok' => false, 'reason' => $why_stock );
		}
		if ( ! ( $sub->in_stock || $sub->min_qty ) && $price_cond && $opts['price_requires_stock'] && $not_stock ) {
			return array( 'ok' => false, 'reason' => __( 'شرط قیمت فقط برای کالای موجود بررسی می‌شود و کالا ناموجود است', 'khabar' ) );
		}
		if ( $sub->min_qty && $snap['manage'] && (int) $snap['qty'] < (int) $sub->min_qty ) {
			/* translators: 1: stock 2: wanted */
			return array( 'ok' => false, 'reason' => sprintf( __( 'موجودی (%1$s) کمتر از حداقل درخواستی (%2$s) است', 'khabar' ), (int) $snap['qty'], (int) $sub->min_qty ) );
		}
		if ( null !== $sub->price_below && ( null === $price || $price > (float) $sub->price_below ) ) {
			/* translators: 1: current 2: target */
			return array( 'ok' => false, 'reason' => sprintf( __( 'قیمت فعلی (%1$s) هنوز به قیمت هدف (%2$s) نرسیده است', 'khabar' ), Khabar_Utils::price_text( $price ), Khabar_Utils::price_text( $sub->price_below ) ) );
		}
		if ( null !== $sub->price_above && ( null === $price || $price < (float) $sub->price_above ) ) {
			/* translators: 1: current 2: threshold */
			return array( 'ok' => false, 'reason' => sprintf( __( 'قیمت فعلی (%1$s) هنوز از %2$s بیشتر نشده است', 'khabar' ), Khabar_Utils::price_text( $price ), Khabar_Utils::price_text( $sub->price_above ) ) );
		}
		if ( $sub->price_change ) {
			if ( ! $opts['cooldown_ok'] ) {
				return array( 'ok' => false, 'reason' => __( 'در فاصله‌ی زمانی بین دو اعلان تغییر قیمت است', 'khabar' ) );
			}
			return array( 'ok' => false, 'reason' => __( 'قیمت از زمان ثبت درخواست تغییری نکرده است', 'khabar' ) );
		}
		return array( 'ok' => false, 'reason' => __( 'هیچ شرطی برقرار نیست', 'khabar' ) );
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
