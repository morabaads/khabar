<?php
/**
 * Price history: records every price change of simple products and variations and
 * renders an accessible step chart (current / lowest / highest + "best time to buy" hint).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Core\Installer;
use YadamBiar\Frontend\ProductWidget;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Clock;

class PriceHistory {

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
	public static function init() {
		if ( ! Settings::get( 'price_history_enabled', 1 ) ) {
			return;
		}
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'on_save' ), 30 );
		add_action( 'woocommerce_after_product_variation_object_save', array( __CLASS__, 'on_save' ), 30 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 30, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 30, 3 );
		add_action( 'shutdown', array( __CLASS__, 'flush_meta' ), 5 );
		add_action( 'yadambiar_daily', array( __CLASS__, 'cleanup' ) );
		add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tab' ), 25 );
		add_shortcode( 'yadambiar_price_history', array( __CLASS__, 'shortcode' ) );
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
		global $wpdb;
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
		$wpdb->insert( // phpcs:ignore
			Installer::table( 'price_history' ),
			array(
				'product_id'    => $product->get_id(),
				'price'         => $price,
				'regular_price' => '' === $regular ? null : (float) $regular,
				'recorded_at'   => Clock::now(),
			)
		);
		update_post_meta( $product->get_id(), self::LAST_META, $price );
		return true;
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
			self::record( $product );
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
	 * Add the product tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		global $product;
		if ( 'tab' === Settings::get( 'price_history_display', 'tab' ) && $product instanceof \WC_Product && '' !== $product->get_price() ) {
			$tabs['yadambiar_price_history'] = array(
				'title'    => __( 'تاریخچه قیمت', 'yadam-biar' ),
				'priority' => 35,
				'callback' => function () use ( $product ) {
					echo self::render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
				},
			);
		}
		return $tabs;
	}

	/**
	 * [yadambiar_price_history product_id="" days=""]
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		global $product;
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'days'       => 0,
			),
			$atts,
			'yadambiar_price_history'
		);
		$p    = $atts['product_id'] ? wc_get_product( absint( $atts['product_id'] ) ) : $product;
		return $p instanceof \WC_Product ? self::render( $p, (int) $atts['days'] ) : '';
	}

	/**
	 * Chart markup (drawn by assets/js/frontend.js).
	 *
	 * @param WC_Product $product Product.
	 * @param int        $days    Window (0 = setting).
	 * @return string
	 */
	public static function render( $product, $days = 0 ) {
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		if ( ! $product ) {
			return '';
		}
		$days   = $days > 0 ? $days : max( 7, (int) Settings::get( 'price_history_days', 90 ) );
		$series = array();
		$def    = 0;
		if ( $product->is_type( 'variable' ) ) {
			$cheapest = null;
			foreach ( array_slice( $product->get_children(), 0, 50 ) as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! $child || '' === $child->get_price() ) {
					continue;
				}
				$series[ $child_id ] = array(
					'label'  => wc_get_formatted_variation( $child, true, false, false ),
					'points' => self::series( $child, $days ),
				);
				if ( null === $cheapest || (float) $child->get_price() < $cheapest ) {
					$cheapest = (float) $child->get_price();
					$def      = $child_id;
				}
			}
		} elseif ( '' !== $product->get_price() ) {
			$def            = $product->get_id();
			$series[ $def ] = array(
				'label'  => '',
				'points' => self::series( $product, $days ),
			);
		}
		if ( ! $series ) {
			return '';
		}
		ProductWidget::enqueue();

		$config = array(
			'days'     => $days,
			'default'  => $def,
			'series'   => $series,
			'currency' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			'decimals' => wc_get_price_decimals(),
		);
		ob_start();
		?>
		<div class="yadambiar-ph" data-product="<?php echo esc_attr( $product->get_id() ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="yadambiar-ph-head">
				<h3 class="yadambiar-ph-title">
					<?php
					/* translators: %s days */
					printf( esc_html__( 'تاریخچه قیمت %s روز اخیر', 'yadam-biar' ), esc_html( number_format_i18n( $days ) ) );
					?>
					<span class="yadambiar-ph-variation"></span>
				</h3>
				<p class="yadambiar-ph-insight" hidden></p>
			</div>
			<div class="yadambiar-ph-stats"></div>
			<div class="yadambiar-ph-plot" dir="ltr"></div>
			<details class="yadambiar-ph-table-wrap">
				<summary><?php esc_html_e( 'جدول تغییرات قیمت', 'yadam-biar' ); ?></summary>
				<table class="yadambiar-ph-table"><thead><tr><th><?php esc_html_e( 'تاریخ', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'قیمت', 'yadam-biar' ); ?></th></tr></thead><tbody></tbody></table>
			</details>
		</div>
		<?php
		return ob_get_clean();
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
