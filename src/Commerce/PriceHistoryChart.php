<?php
/**
 * Accessible step chart of a product's price (current / lowest / highest and a best-time-to-buy hint).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Frontend\Assets;
use YadamBiar\Settings\Settings;
/**
 * Accessible step chart of a product's price (current / lowest / highest and a best-time-to-buy hint).
 */
final class PriceHistoryChart {

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
				'title'    => __( 'تاریخچه قیمت', 'yadambiar' ),
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
					'points' => PriceHistoryRepository::series( $child, $days ),
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
				'points' => PriceHistoryRepository::series( $product, $days ),
			);
		}
		if ( ! $series ) {
			return '';
		}
		Assets::enqueue();

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
					printf( esc_html__( 'تاریخچه قیمت %s روز اخیر', 'yadambiar' ), esc_html( number_format_i18n( $days ) ) );
					?>
					<span class="yadambiar-ph-variation"></span>
				</h3>
				<p class="yadambiar-ph-insight" hidden></p>
			</div>
			<div class="yadambiar-ph-stats"></div>
			<div class="yadambiar-ph-plot" dir="ltr"></div>
			<details class="yadambiar-ph-table-wrap">
				<summary><?php esc_html_e( 'جدول تغییرات قیمت', 'yadambiar' ); ?></summary>
				<table class="yadambiar-ph-table"><thead><tr><th><?php esc_html_e( 'تاریخ', 'yadambiar' ); ?></th><th><?php esc_html_e( 'قیمت', 'yadambiar' ); ?></th></tr></thead><tbody></tbody></table>
			</details>
		</div>
		<?php
		return ob_get_clean();
	}
}
