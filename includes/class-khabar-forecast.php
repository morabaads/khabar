<?php
/**
 * Demand forecast for purchase orders.
 *
 * For every out-of-stock / low item with waiting customers:
 *
 *   conv        = smoothed conversion "notified → purchased"
 *                 product rate shrunk towards the store rate, store rate towards the default:
 *                 (purchased + k·prior) / (notified + k)
 *   waitlist    = waiting × conv                        (buy right after restock)
 *   new_waiters = new_per_day × (lead + cover) × conv   (people who keep joining)
 *   organic     = daily_sales × cover                    (normal sales after restock)
 *   demand      = waitlist + new_waiters + organic
 *   safety      = z(service level) × √demand              (Poisson demand variability)
 *   suggested   = ceil( demand + safety − max(0, stock) )
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Forecast {

	const K_PRODUCT = 10;
	const K_STORE   = 20;

	/**
	 * Z value for a service level.
	 *
	 * @param int $level 80/90/95/98.
	 * @return float
	 */
	public static function z( $level ) {
		$map = array(
			80 => 0.842,
			90 => 1.282,
			95 => 1.645,
			98 => 2.054,
		);
		return isset( $map[ (int) $level ] ) ? $map[ (int) $level ] : 1.282;
	}

	/**
	 * Bayesian-smoothed rate.
	 *
	 * @param float $success Successes.
	 * @param float $trials  Trials.
	 * @param float $prior   Prior rate (0..1).
	 * @param float $k       Prior weight.
	 * @return float
	 */
	public static function smooth( $success, $trials, $prior, $k ) {
		return ( $success + $k * $prior ) / ( $trials + $k );
	}

	/**
	 * Core calculation (pure).
	 *
	 * @param array $in waiting, new_per_day, conv, daily_sales, stock.
	 * @param array $p  lead, cover, z.
	 * @return array
	 */
	public static function calculate( $in, $p ) {
		$in = array_merge(
			array(
				'waiting'     => 0,
				'new_per_day' => 0,
				'conv'        => 0.3,
				'daily_sales' => 0,
				'stock'       => 0,
			),
			$in
		);
		$waitlist = $in['waiting'] * $in['conv'];
		$new      = $in['new_per_day'] * ( $p['lead'] + $p['cover'] ) * $in['conv'];
		$organic  = $in['daily_sales'] * $p['cover'];
		$demand   = $waitlist + $new + $organic;
		$safety   = $demand > 0 ? $p['z'] * sqrt( $demand ) : 0;
		$order    = max( 0, (int) ceil( $demand + $safety - max( 0, (float) $in['stock'] ) - 1e-9 ) );
		return array(
			'waitlist' => round( $waitlist, 2 ),
			'new'      => round( $new, 2 ),
			'organic'  => round( $organic, 2 ),
			'demand'   => round( $demand, 2 ),
			'safety'   => (int) ceil( $safety ),
			'order'    => $order,
		);
	}

	/**
	 * Units sold per item in the last N days (from WooCommerce analytics lookup table).
	 *
	 * @param int $days Days.
	 * @return array item_id => units
	 */
	public static function sales( $days = 90 ) {
		global $wpdb;
		$lookup = $wpdb->prefix . 'wc_order_product_lookup';
		$stats  = $wpdb->prefix . 'wc_order_stats';
		$since  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->suppress_errors( true );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT CASE WHEN l.variation_id > 0 THEN l.variation_id ELSE l.product_id END AS item, SUM(l.product_qty) AS qty FROM {$lookup} l INNER JOIN {$stats} s ON s.order_id = l.order_id WHERE l.date_created >= %s AND s.status IN ('wc-completed','wc-processing','wc-on-hold') GROUP BY item", $since ) ); // phpcs:ignore
		$wpdb->suppress_errors( false );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->item ] = (float) $r->qty;
		}
		return apply_filters( 'khabar_forecast_sales', $out, $days );
	}

	/**
	 * Build the forecast table.
	 *
	 * @param array $p lead, cover, level, default_conv (0..1).
	 * @return array rows sorted by urgency + 'params'.
	 */
	public static function build( $p = array() ) {
		global $wpdb;
		$p = array_merge(
			array(
				'lead'         => (int) Khabar_Settings::get( 'forecast_lead_days', 14 ),
				'cover'        => (int) Khabar_Settings::get( 'forecast_cover_days', 30 ),
				'level'        => (int) Khabar_Settings::get( 'forecast_service_level', 90 ),
				'default_conv' => max( 1, (int) Khabar_Settings::get( 'forecast_default_conv', 30 ) ) / 100,
			),
			$p
		);
		$p['z'] = self::z( $p['level'] );
		$table  = Khabar_Install::table( 'subscriptions' );

		// Conversion history.
		$hist  = $wpdb->get_results( "SELECT product_id, SUM(status = 'purchased') AS bought, COUNT(*) AS notified FROM {$table} WHERE last_notified_at IS NOT NULL AND in_stock = 1 GROUP BY product_id", OBJECT_K ); // phpcs:ignore
		$all_b = array_sum( wp_list_pluck( $hist, 'bought' ) );
		$all_n = array_sum( wp_list_pluck( $hist, 'notified' ) );
		$store = self::smooth( $all_b, $all_n, $p['default_conv'], self::K_STORE );

		// Waiting demand distributed over concrete items.
		$subs     = $wpdb->get_results( "SELECT product_id, variation_id, attributes, created_at FROM {$table} WHERE status = 'active' AND in_stock = 1 LIMIT 50000" ); // phpcs:ignore
		$children = array();
		$items    = array();
		$recent   = Khabar_Utils::now( -14 * DAY_IN_SECONDS );
		foreach ( (array) $subs as $row ) {
			$pid = (int) $row->product_id;
			if ( $row->variation_id ) {
				$targets = array( (int) $row->variation_id );
			} else {
				if ( ! isset( $children[ $pid ] ) ) {
					$product          = wc_get_product( $pid );
					$children[ $pid ] = array();
					if ( $product && $product->is_type( 'variable' ) ) {
						foreach ( $product->get_children() as $cid ) {
							$c = wc_get_product( $cid );
							if ( $c ) {
								$children[ $pid ][ $cid ] = $c;
							}
						}
					}
				}
				if ( $children[ $pid ] ) {
					$wanted  = $row->attributes ? (array) json_decode( $row->attributes, true ) : array();
					$match   = array();
					$oos     = array();
					foreach ( $children[ $pid ] as $cid => $c ) {
						if ( Khabar_Rules::attributes_match( $wanted, $c->get_variation_attributes() ) ) {
							$match[] = $cid;
							if ( ! $c->is_in_stock() ) {
								$oos[] = $cid;
							}
						}
					}
					$targets = $oos ? $oos : $match;
				} else {
					$targets = array( $pid );
				}
			}
			if ( ! $targets ) {
				continue;
			}
			$w = 1 / count( $targets );
			foreach ( $targets as $tid ) {
				if ( ! isset( $items[ $tid ] ) ) {
					$items[ $tid ] = array(
						'parent'  => $pid,
						'waiting' => 0,
						'new14'   => 0,
						'oldest'  => $row->created_at,
					);
				}
				$items[ $tid ]['waiting'] += $w;
				if ( $row->created_at >= $recent ) {
					$items[ $tid ]['new14'] += $w;
				}
				if ( $row->created_at < $items[ $tid ]['oldest'] ) {
					$items[ $tid ]['oldest'] = $row->created_at;
				}
			}
		}

		$sales = self::sales( 90 );
		$rows  = array();
		foreach ( $items as $tid => $it ) {
			$product = wc_get_product( $tid );
			if ( ! $product ) {
				continue;
			}
			$h      = isset( $hist[ $it['parent'] ] ) ? $hist[ $it['parent'] ] : null;
			$n      = $h ? (int) $h->notified : 0;
			$conv   = self::smooth( $h ? (int) $h->bought : 0, $n, $store, self::K_PRODUCT );
			$stock  = $product->managing_stock() ? (float) $product->get_stock_quantity() : ( $product->is_in_stock() ? null : 0 );
			$daily  = ( isset( $sales[ $tid ] ) ? $sales[ $tid ] : 0 ) / 90;
			$calc   = self::calculate(
				array(
					'waiting'     => $it['waiting'],
					'new_per_day' => $it['new14'] / 14,
					'conv'        => $conv,
					'daily_sales' => $daily,
					'stock'       => null === $stock ? 0 : $stock,
				),
				$p
			);
			$price  = '' === $product->get_price() ? 0 : (float) $product->get_price();
			$rows[] = array(
				'product'     => $product,
				'sku'         => $product->get_sku(),
				'stock'       => $stock,
				'waiting'     => round( $it['waiting'], 1 ),
				'new_per_day' => round( $it['new14'] / 14, 2 ),
				'conv'        => $conv,
				'sales_30'    => round( $daily * 30, 1 ),
				'days_out'    => max( 0, (int) floor( ( time() - strtotime( $it['oldest'] . ' UTC' ) ) / DAY_IN_SECONDS ) ),
				'confidence'  => $n >= 30 ? 'high' : ( $n >= 10 ? 'medium' : 'low' ),
				'price'       => $price,
				'revenue'     => $calc['order'] * $price,
				'lost'        => $calc['waitlist'] * $price,
				'calc'        => $calc,
			);
		}
		usort(
			$rows,
			function ( $a, $b ) {
				return $b['lost'] <=> $a['lost'];
			}
		);
		return array(
			'rows'   => $rows,
			'params' => $p + array( 'store_conv' => $store ),
		);
	}
}
