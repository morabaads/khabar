<?php
/**
 * Report queries for the admin dashboard (Feature 13, Flow 5).
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Reports {

	/**
	 * Headline numbers.
	 *
	 * @param int $days Window.
	 * @return array
	 */
	public static function kpis( $days = 30 ) {
		global $wpdb;
		$subs  = Khabar_Install::table( 'subscriptions' );
		$log   = Khabar_Install::table( 'log' );
		$since = Khabar_Utils::now( -$days * DAY_IN_SECONDS );

		$notified = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$subs} WHERE last_notified_at >= %s", $since ) ); // phpcs:ignore
		$clicked  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$subs} WHERE clicked_at >= %s", $since ) ); // phpcs:ignore
		$conv     = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS c, COALESCE(SUM(order_value),0) AS v FROM {$subs} WHERE status = 'purchased' AND updated_at >= %s", $since ) ); // phpcs:ignore

		return array(
			'active'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$subs} WHERE status = 'active'" ), // phpcs:ignore
			'people'      => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT CONCAT(user_id,'|',email,'|',phone)) FROM {$subs} WHERE status = 'active'" ), // phpcs:ignore
			'products'    => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$subs} WHERE status = 'active'" ), // phpcs:ignore
			'notified'    => $notified,
			'sent'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log} WHERE status = 'sent' AND created_at >= %s", $since ) ), // phpcs:ignore
			'failed'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$log} WHERE status = 'failed' AND created_at >= %s", $since ) ), // phpcs:ignore
			'clicked'     => $clicked,
			'click_rate'  => $notified ? round( 100 * $clicked / $notified, 1 ) : 0,
			'conversions' => (int) $conv->c,
			'conv_rate'   => $notified ? round( 100 * $conv->c / $notified, 1 ) : 0,
			'revenue'     => (float) $conv->v,
		);
	}

	/**
	 * Most waited products.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function top_products( $limit = 10 ) {
		global $wpdb;
		$subs = Khabar_Install::table( 'subscriptions' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT product_id, COUNT(*) AS requests, COUNT(DISTINCT CONCAT(user_id,'|',email,'|',phone)) AS people, SUM(type = 'stock' OR in_stock = 1) AS stock_requests, SUM(type IN ('price_drop','price_rise','price_change')) AS price_requests FROM {$subs} WHERE status = 'active' GROUP BY product_id ORDER BY people DESC LIMIT %d", $limit ) ); // phpcs:ignore
	}

	/**
	 * Attribute demand: which sizes / colors are requested the most.
	 *
	 * @param int $product_id 0 = whole store.
	 * @return array attribute label => [value label => count] sorted desc.
	 */
	public static function attribute_demand( $product_id = 0 ) {
		global $wpdb;
		$subs  = Khabar_Install::table( 'subscriptions' );
		$where = "status = 'active' AND (variation_id > 0 OR attributes <> '')";
		if ( $product_id ) {
			$where .= $wpdb->prepare( ' AND product_id = %d', $product_id );
		}
		$rows   = $wpdb->get_results( "SELECT variation_id, attributes FROM {$subs} WHERE {$where} LIMIT 20000" ); // phpcs:ignore
		$cache  = array();
		$counts = array();
		foreach ( (array) $rows as $row ) {
			if ( $row->variation_id ) {
				if ( ! isset( $cache[ $row->variation_id ] ) ) {
					$v                            = wc_get_product( $row->variation_id );
					$cache[ $row->variation_id ] = $v ? $v->get_variation_attributes() : array();
				}
				$attrs = $cache[ $row->variation_id ];
			} else {
				$attrs = json_decode( $row->attributes, true );
			}
			foreach ( (array) $attrs as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$tax   = str_replace( 'attribute_', '', $key );
				$label = wc_attribute_label( $tax );
				if ( taxonomy_exists( $tax ) ) {
					$term  = get_term_by( 'slug', $value, $tax );
					$value = $term ? $term->name : $value;
				}
				if ( ! isset( $counts[ $label ][ $value ] ) ) {
					$counts[ $label ][ $value ] = 0;
				}
				++$counts[ $label ][ $value ];
			}
		}
		foreach ( $counts as &$values ) {
			arsort( $values );
		}
		return $counts;
	}

	/**
	 * Waiting count per variation of a product.
	 *
	 * @param int $product_id Product.
	 * @return array
	 */
	public static function variation_demand( $product_id ) {
		global $wpdb;
		$subs = Khabar_Install::table( 'subscriptions' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT variation_id, attributes, COUNT(*) AS c FROM {$subs} WHERE product_id = %d AND status = 'active' GROUP BY variation_id, attributes ORDER BY c DESC", $product_id ) ); // phpcs:ignore
	}

	/**
	 * Send stats per channel.
	 *
	 * @param int $days Window.
	 * @return array channel => [sent, failed]
	 */
	public static function channel_stats( $days = 30 ) {
		global $wpdb;
		$log  = Khabar_Install::table( 'log' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT channel, status, COUNT(*) AS c FROM {$log} WHERE created_at >= %s GROUP BY channel, status", Khabar_Utils::now( -$days * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			if ( ! isset( $out[ $r->channel ] ) ) {
				$out[ $r->channel ] = array( 'sent' => 0, 'failed' => 0 );
			}
			$out[ $r->channel ][ 'sent' === $r->status ? 'sent' : 'failed' ] += (int) $r->c;
		}
		return $out;
	}

	/**
	 * Funnel per subscription type.
	 *
	 * @return array type => [status => count]
	 */
	public static function type_funnel() {
		global $wpdb;
		$subs = Khabar_Install::table( 'subscriptions' );
		$rows = $wpdb->get_results( "SELECT type, status, COUNT(*) AS c FROM {$subs} GROUP BY type, status" ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r->type ][ $r->status ] = (int) $r->c;
		}
		return $out;
	}

	/**
	 * Restock priority: out-of-stock items ordered by demand with potential revenue.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function restock_priority( $limit = 15 ) {
		global $wpdb;
		$subs = Khabar_Install::table( 'subscriptions' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, variation_id, COUNT(*) AS c FROM {$subs} WHERE status = 'active' AND in_stock = 1 GROUP BY product_id, variation_id ORDER BY c DESC LIMIT %d", $limit * 3 ) ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$p = wc_get_product( $r->variation_id ? $r->variation_id : $r->product_id );
			if ( ! $p || $p->is_in_stock() ) {
				continue;
			}
			$out[] = array(
				'product'   => $p,
				'count'     => (int) $r->c,
				'potential' => (float) $p->get_price() * (int) $r->c,
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
}
