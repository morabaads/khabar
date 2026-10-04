<?php
/**
 * Product edit screen: waiting customers box + products list column.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Product_Metabox {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
	}

	/**
	 * Register box.
	 */
	public static function add() {
		add_meta_box( 'khabar-waiting', __( 'خبرم کن — منتظران', 'khabar' ), array( __CLASS__, 'render' ), 'product', 'side', 'default' );
	}

	/**
	 * Render box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		$rows  = Khabar_Reports::variation_demand( $post->ID );
		$total = Khabar_Subscriptions::waiting_count( $post->ID );
		echo '<p><strong style="font-size:20px">' . esc_html( number_format_i18n( $total ) ) . '</strong> ' . esc_html__( 'نفر منتظر', 'khabar' ) . '</p>';
		if ( $rows ) {
			echo '<ul class="khabar-box-list">';
			foreach ( array_slice( $rows, 0, 12 ) as $row ) {
				$v     = $row->variation_id ? wc_get_product( $row->variation_id ) : null;
				$label = $v ? wc_get_formatted_variation( $v, true, false ) : ( $row->attributes ? Khabar_Utils::attributes_label( json_decode( $row->attributes, true ) ) : __( 'کل محصول', 'khabar' ) );
				echo '<li><span>' . esc_html( $label ) . '</span><b>' . esc_html( number_format_i18n( $row->c ) ) . '</b></li>';
			}
			echo '</ul>';
			$run = wp_nonce_url( admin_url( 'admin-post.php?action=khabar_run_product&product_id=' . $post->ID ), 'khabar_run_product' );
			echo '<p><a class="button" href="' . esc_url( $run ) . '">' . esc_html__( 'بررسی و ارسال اعلان اکنون', 'khabar' ) . '</a></p>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=khabar-reports&product_id=' . $post->ID ) ) . '">' . esc_html__( 'گزارش کامل', 'khabar' ) . '</a> | <a href="' . esc_url( admin_url( 'admin.php?page=khabar-requests&product_id=' . $post->ID ) ) . '">' . esc_html__( 'درخواست‌ها', 'khabar' ) . '</a></p>';
		}
		echo '<p class="description">' . esc_html__( 'با افزایش موجودی یا تغییر قیمت، اعلان‌ها به‌صورت خودکار ارسال می‌شوند.', 'khabar' ) . '</p>';
	}

	/**
	 * Column.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function column( $cols ) {
		$cols['khabar'] = '🔔';
		return $cols;
	}

	/**
	 * Column value.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post id.
	 */
	public static function column_value( $col, $post_id ) {
		if ( 'khabar' !== $col ) {
			return;
		}
		$count = Khabar_Subscriptions::waiting_count( $post_id );
		echo $count ? '<a href="' . esc_url( admin_url( 'admin.php?page=khabar-requests&product_id=' . $post_id ) ) . '" title="' . esc_attr__( 'منتظران', 'khabar' ) . '">' . esc_html( number_format_i18n( $count ) ) . '</a>' : '<span class="khabar-muted">–</span>';
	}
}
