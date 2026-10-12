<?php
/**
 * Price history: records every price change of simple products and variations and
 * renders an accessible step chart (current / lowest / highest + "best time to buy" hint).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Settings;

/**
 * Price history: records every price change of simple products and variations and.
 */
final class PriceHistory implements Module {

	const LAST_META = '_yadambiar_last_price';

	/**
	 * Product ids whose price meta changed outside the CRUD layer.
	 *
	 * @var int[]
	 */
	private static $meta_changed = array();

	/**
	 * Hooks.
	 */
	public function register() {
		if ( ! Settings::get( 'price_history_enabled', 1 ) ) {
			return;
		}
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'on_save' ), 30 );
		add_action( 'woocommerce_after_product_variation_object_save', array( __CLASS__, 'on_save' ), 30 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 30, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 30, 3 );
		add_action( 'shutdown', array( __CLASS__, 'flush_meta' ), 5 );
		add_action( 'yadambiar_daily', array( PriceHistoryRepository::class, 'cleanup' ) );
		add_filter( 'woocommerce_product_tabs', array( PriceHistoryChart::class, 'tab' ), 25 );
		add_shortcode( 'yadambiar_price_history', array( PriceHistoryChart::class, 'shortcode' ) );
	}

	/**
	 * CRUD save.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function on_save( $product ) {
		if ( $product instanceof \WC_Product ) {
			self::record( $product );
		}
	}

	/**
	 * Direct meta writes (importers).
	 *
	 * @param int    $meta_id   Meta id.
	 * @param int    $object_id Object.
	 * @param string $key       Key.
	 */
	public static function on_meta( $meta_id, $object_id, $key ) {
		if ( '_price' === $key ) {
			self::$meta_changed[ (int) $object_id ] = (int) $object_id;
		}
	}

	/**
	 * Record meta-driven changes at the end of the request.
	 */
	public static function flush_meta() {
		foreach ( self::$meta_changed as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				self::record( $product );
			}
		}
		self::$meta_changed = array();
	}

	/**
	 * Record the current price when it differs from the last recorded one.
	 *
	 * @param WC_Product $product Product (variable parents are skipped – their variations are recorded).
	 * @return bool Recorded.
	 */
	public static function record( $product ) {
		if ( $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return false;
		}
		$price = $product->get_price( 'edit' );
		if ( '' === $price || null === $price ) {
			return false;
		}
		$price = (float) $price;
		$last  = get_post_meta( $product->get_id(), self::LAST_META, true );
		if ( '' !== $last && abs( (float) $last - $price ) < 0.0001 ) {
			return false;
		}
		$regular = $product->get_regular_price( 'edit' );
		PriceHistoryRepository::add( $product->get_id(), $price, '' === $regular ? null : (float) $regular );
		update_post_meta( $product->get_id(), self::LAST_META, $price );
		return true;
	}

}
