<?php
/**
 * Stored price points: series for a chart, the lowest price in a window, retention cleanup.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Support\Clock;
/**
 * Stored price points: series for a chart, the lowest price in a window, retention cleanup.
 */
final class PriceHistoryRepository {

	/**
	 * Store a price point.
	 *
	 * @param int        $product_id Product / variation id.
	 * @param float      $price      Active price.
	 * @param float|null $regular    Regular price.
	 */
	public static function add( $product_id, $price, $regular ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Installer::table( 'price_history' ),
			array(
				'product_id'    => (int) $product_id,
				'price'         => (float) $price,
				'regular_price' => $regular,
				'recorded_at'   => Clock::now(),
			)
		);
	}

	/**
	 * Series for one product/variation: [[timestamp_ms, price], …] covering the window,
	 * including the price in effect at the window start and a final point "now".
	 *
	 * @param WC_Product $product Product / variation.
	 * @param int        $days    Window.
	 * @return array
	 */
	public static function series( $product, $days ) {
		global $wpdb;
		$table = Installer::table( 'price_history' );
		$id    = $product->get_id();
		$start = Clock::now( -$days * DAY_IN_SECONDS );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT price, recorded_at FROM {$table} WHERE product_id = %d AND recorded_at >= %s ORDER BY recorded_at ASC, id ASC LIMIT 1000", $id, $start ) ); // phpcs:ignore
		$prev = $wpdb->get_var( $wpdb->prepare( "SELECT price FROM {$table} WHERE product_id = %d AND recorded_at < %s ORDER BY recorded_at DESC, id DESC LIMIT 1", $id, $start ) ); // phpcs:ignore

		if ( ! $rows && null === $prev ) {
			// No history yet: start tracking from now.
			PriceHistory::record( $product );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT price, recorded_at FROM {$table} WHERE product_id = %d ORDER BY id ASC LIMIT 1", $id ) ); // phpcs:ignore
		}

		$points = array();
		if ( null !== $prev ) {
			$points[] = array( ( time() - $days * DAY_IN_SECONDS ) * 1000, (float) $prev );
		}
		foreach ( (array) $rows as $row ) {
			$points[] = array( strtotime( $row->recorded_at . ' UTC' ) * 1000, (float) $row->price );
		}
		$current = $product->get_price();
		if ( '' !== $current && $points ) {
			$points[] = array( time() * 1000, (float) $current );
		}
		return $points;
	}

	/**
	 * Lowest price of a product in the last N days (used in messages / API).
	 *
	 * @param WC_Product $product Product.
	 * @param int        $days    Days.
	 * @return float|null
	 */
	public static function lowest( $product, $days = 90 ) {
		$points = self::series( $product, $days );
		return $points ? min( wp_list_pluck( $points, 1 ) ) : null;
	}

	/**
	 * Delete rows older than ~13 months (keeps the latest per product as the baseline).
	 */
	public static function cleanup() {
		global $wpdb;
		$table = Installer::table( 'price_history' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE recorded_at < %s AND id NOT IN (SELECT id FROM (SELECT MAX(id) AS id FROM {$table} GROUP BY product_id) keep)", Clock::now( -400 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
	}
}
