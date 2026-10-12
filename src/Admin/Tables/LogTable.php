<?php
/**
 * Send log list table.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Tables;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminApp;
use YadamBiar\Core\Installer;
use YadamBiar\Settings\Settings;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LogTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'yadambiar_log',
				'plural'   => 'yadambiar_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'created_at' => __( 'زمان', 'yadam-biar' ),
			'event'      => __( 'رویداد', 'yadam-biar' ),
			'product'    => __( 'محصول', 'yadam-biar' ),
			'channel'    => __( 'کانال', 'yadam-biar' ),
			'recipient'  => __( 'گیرنده', 'yadam-biar' ),
			'status'     => __( 'وضعیت', 'yadam-biar' ),
			'message'    => __( 'پیام', 'yadam-biar' ),
		);
	}

	/**
	 * Views.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : ''; // phpcs:ignore
		$base    = admin_url( 'admin.php?page=yadambiar&view=logs' );
		return array(
			'all'    => '<a href="' . esc_url( $base ) . '" class="' . ( $current ? '' : 'current' ) . '">' . esc_html__( 'همه', 'yadam-biar' ) . '</a>',
			'sent'   => '<a href="' . esc_url( add_query_arg( 'status', 'sent', $base ) ) . '" class="' . ( 'sent' === $current ? 'current' : '' ) . '">' . esc_html__( 'موفق', 'yadam-biar' ) . '</a>',
			'failed' => '<a href="' . esc_url( add_query_arg( 'status', 'failed', $base ) ) . '" class="' . ( 'failed' === $current ? 'current' : '' ) . '">' . esc_html__( 'ناموفق', 'yadam-biar' ) . '</a>',
		);
	}

	/**
	 * Prepare.
	 */
	public function prepare_items() {
		global $wpdb;
		$table    = Installer::table( 'log' );
		$per_page = 50;
		$paged    = $this->get_pagenum();
		$where    = '1=1';
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_REQUEST['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', sanitize_key( $_REQUEST['status'] ) );
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$like   = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) . '%';
			$where .= $wpdb->prepare( ' AND (recipient LIKE %s OR message LIKE %s)', $like, $like );
		}
		// phpcs:enable
		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore
		$this->items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page ) ); // phpcs:ignore
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Column output.
	 *
	 * @param object $item   Row.
	 * @param string $column Column.
	 * @return string
	 */
	protected function column_default( $item, $column ) {
		switch ( $column ) {
			case 'created_at':
				return esc_html( AdminApp::date( $item->created_at ) );
			case 'event':
				$events = Settings::events();
				return esc_html( $events[ $item->event ] ?? $item->event );
			case 'product':
				$p = wc_get_product( $item->variation_id ? $item->variation_id : $item->product_id );
				return $p ? '<a href="' . esc_url( get_edit_post_link( $item->product_id ) ) . '">' . esc_html( $p->get_name() ) . '</a>' : '—';
			case 'channel':
				if ( 'all' === $item->channel ) {
					return '—';
				}
				$ch = Settings::channels();
				return esc_html( $ch[ $item->channel ] ?? $item->channel );
			case 'recipient':
				// Request number: shows which request each message came from.
				$req = $item->subscription_id ? '<br><a class="yadambiar-muted" href="' . esc_url( admin_url( 'admin.php?page=yadambiar&view=requests&s=' . rawurlencode( $item->recipient ) ) ) . '">' . esc_html__( 'درخواست', 'yadam-biar' ) . ' #' . (int) $item->subscription_id . '</a>' : '';
				return '<span dir="ltr">' . esc_html( $item->recipient ) . '</span>' . $req;
			case 'status':
				if ( 'sent' === $item->status ) {
					return '<span class="yadambiar-status yadambiar-status-notified">' . esc_html__( 'ارسال شد', 'yadam-biar' ) . '</span>';
				}
				if ( 'skipped' === $item->status ) {
					return '<span class="yadambiar-status yadambiar-status-active">' . esc_html__( 'ارسال نشد (تکراری)', 'yadam-biar' ) . '</span><br><small>' . esc_html( $item->error ) . '</small>';
				}
				$retry = wp_nonce_url( admin_url( 'admin-post.php?action=yadambiar_retry&log=' . $item->id ), 'yadambiar_retry' );
				return '<span class="yadambiar-status yadambiar-status-cancelled">' . esc_html__( 'ناموفق', 'yadam-biar' ) . '</span><br><small>' . esc_html( $item->error ) . '</small><br><a href="' . esc_url( $retry ) . '">' . esc_html__( 'ارسال مجدد', 'yadam-biar' ) . '</a>';
			case 'message':
				return '<div class="yadambiar-log-msg">' . nl2br( esc_html( $item->message ) ) . '</div>';
		}
		return '';
	}

	/**
	 * Empty message.
	 */
	public function no_items() {
		esc_html_e( 'هنوز اعلانی ارسال نشده است.', 'yadam-biar' );
	}
}
