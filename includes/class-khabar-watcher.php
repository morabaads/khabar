<?php
/**
 * Watches WooCommerce for stock / price changes (Flow 2 & 14) and queues checks.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Watcher {

	/**
	 * Parent product ids to check at shutdown.
	 *
	 * @var int[]
	 */
	private static $pending = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_id' ), 20, 1 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_id' ), 20, 1 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_product' ), 20, 1 );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_product' ), 20, 1 );
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'on_product' ), 20, 1 );
		add_action( 'woocommerce_after_product_variation_object_save', array( __CLASS__, 'on_product' ), 20, 1 );
		// Importers / direct meta writes bypassing the CRUD layer.
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 20, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 20, 3 );
		add_action( 'shutdown', array( __CLASS__, 'flush' ) );
	}

	/**
	 * By product id.
	 *
	 * @param int $id Product or variation id.
	 */
	public static function on_id( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}
		$parent = wp_get_post_parent_id( $id );
		$type   = get_post_type( $id );
		$pid    = ( 'product_variation' === $type && $parent ) ? $parent : $id;
		self::$pending[ $pid ] = $pid;
	}

	/**
	 * By product object.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function on_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$pid = $product->get_parent_id() && $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		if ( $pid ) {
			self::$pending[ $pid ] = (int) $pid;
		}
	}

	/**
	 * Meta change.
	 *
	 * @param int    $meta_id   Meta id.
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Key.
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key ) {
		if ( in_array( $meta_key, array( '_price', '_stock_status', '_stock' ), true ) ) {
			self::on_id( $object_id );
		}
	}

	/**
	 * Queue checks for products that actually have waiting customers.
	 */
	public static function flush() {
		if ( ! self::$pending ) {
			return;
		}
		global $wpdb;
		$ids   = array_map( 'intval', self::$pending );
		$table = Khabar_Install::table( 'subscriptions' );
		$have  = $wpdb->get_col( "SELECT DISTINCT product_id FROM {$table} WHERE status = 'active' AND product_id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
		// When an admin edits stock/price in the dashboard, notify right away instead of waiting for
		// WP-Cron / Action Scheduler (which never runs on sites with cron disabled or no traffic).
		$sync = is_admin() && ! wp_doing_cron() && current_user_can( 'edit_products' ) && apply_filters( 'khabar_sync_check_on_save', true );
		foreach ( $have as $pid ) {
			$waiting = $sync ? Khabar_Subscriptions::waiting_count( (int) $pid, null, true ) : 0;
			if ( $sync && $waiting <= 20 ) {
				Khabar_Dispatcher::check_product( (int) $pid );
			} else {
				Khabar_Utils::queue( 'khabar_check_product', array( (int) $pid ) );
			}
		}
		self::$pending = array();
	}

	/**
	 * Ids queued (tests).
	 *
	 * @return int[]
	 */
	public static function pending() {
		return self::$pending;
	}
}
