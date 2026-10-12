<?php
/**
 * Product edit screen: waiting customers box + products list column.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\Module;
use YadamBiar\Reports\ReportRepository;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\ProductAttributes;

class ProductMetabox implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
	}

	/**
	 * Register box.
	 */
	public static function add() {
		add_meta_box( 'yadambiar-waiting', __( 'یادم بیار — منتظران', 'yadambiar' ), array( __CLASS__, 'render' ), 'product', 'side', 'default' );
	}

	/**
	 * Render box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		$rows  = ReportRepository::variation_demand( $post->ID );
		$total = SubscriptionRepository::waiting_count( $post->ID );
		echo '<p><strong style="font-size:20px">' . esc_html( number_format_i18n( $total ) ) . '</strong> ' . esc_html__( 'نفر منتظر', 'yadambiar' ) . '</p>';
		if ( $rows ) {
			echo '<ul class="yadambiar-box-list">';
			foreach ( array_slice( $rows, 0, 12 ) as $row ) {
				$v     = $row->variation_id ? wc_get_product( $row->variation_id ) : null;
				$label = $v ? wc_get_formatted_variation( $v, true, false ) : ( $row->attributes ? ProductAttributes::attributes_label( json_decode( $row->attributes, true ) ) : __( 'کل محصول', 'yadambiar' ) );
				echo '<li><span>' . esc_html( $label ) . '</span><b>' . esc_html( number_format_i18n( $row->c ) ) . '</b></li>';
			}
			echo '</ul>';
			$run = AdminUrl::action( 'run_product', array( 'product_id' => $post->ID ) );
			echo '<p><a class="button" href="' . esc_url( $run ) . '">' . esc_html__( 'بررسی و ارسال اعلان اکنون', 'yadambiar' ) . '</a></p>';
			echo '<p><a href="' . esc_url( AdminUrl::view( 'reports', array( 'product_id' => $post->ID ) ) ) . '">' . esc_html__( 'گزارش کامل', 'yadambiar' ) . '</a> | <a href="' . esc_url( AdminUrl::view( 'requests', array( 'product_id' => $post->ID ) ) ) . '">' . esc_html__( 'درخواست‌ها', 'yadambiar' ) . '</a></p>';
		}
		echo '<p class="description">' . esc_html__( 'با افزایش موجودی یا تغییر قیمت، اعلان‌ها به‌صورت خودکار ارسال می‌شوند.', 'yadambiar' ) . '</p>';
	}

	/**
	 * Column.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function column( $cols ) {
		$cols['yadambiar'] = '🔔';
		return $cols;
	}

	/**
	 * Column value.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post id.
	 */
	public static function column_value( $col, $post_id ) {
		if ( 'yadambiar' !== $col ) {
			return;
		}
		$count = SubscriptionRepository::waiting_count( $post_id );
		echo $count ? '<a href="' . esc_url( AdminUrl::view( 'requests', array( 'product_id' => $post_id ) ) ) . '" title="' . esc_attr__( 'منتظران', 'yadambiar' ) . '">' . esc_html( number_format_i18n( $count ) ) . '</a>' : '<span class="yadambiar-muted">–</span>';
	}
}
