<?php
/**
 * Requests list table.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Khabar_Requests_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'khabar_request',
				'plural'   => 'khabar_requests',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Build WHERE from request filters.
	 *
	 * @return string
	 */
	public static function where() {
		global $wpdb;
		$where = array( '1=1' );
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_REQUEST['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', sanitize_key( $_REQUEST['status'] ) );
		}
		if ( ! empty( $_REQUEST['type'] ) ) {
			$where[] = $wpdb->prepare( 'type = %s', sanitize_key( $_REQUEST['type'] ) );
		}
		if ( ! empty( $_REQUEST['product_id'] ) ) {
			$where[] = $wpdb->prepare( 'product_id = %d', absint( $_REQUEST['product_id'] ) );
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$s       = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			$phone   = Khabar_Utils::normalize_phone( $s );
			$like    = '%' . $wpdb->esc_like( $s ) . '%';
			$where[] = $wpdb->prepare( '(email LIKE %s OR phone LIKE %s OR phone = %s OR name LIKE %s OR product_id = %d)', $like, $like, $phone, $like, absint( $s ) );
		}
		// phpcs:enable
		return implode( ' AND ', $where );
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'product'    => __( 'محصول', 'khabar' ),
			'conditions' => __( 'شرایط', 'khabar' ),
			'contact'    => __( 'مشتری', 'khabar' ),
			'channels'   => __( 'کانال', 'khabar' ),
			'status'     => __( 'وضعیت', 'khabar' ),
			'created_at' => __( 'تاریخ ثبت', 'khabar' ),
			'notified'   => __( 'آخرین اعلان', 'khabar' ),
		);
	}

	/**
	 * Sortable.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'id', true ),
			'status'     => array( 'status', false ),
			'product'    => array( 'product_id', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'activate' => __( 'فعال‌سازی مجدد', 'khabar' ),
			'cancel'   => __( 'لغو', 'khabar' ),
			'delete'   => __( 'حذف', 'khabar' ),
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		global $wpdb;
		$table   = Khabar_Install::table( 'subscriptions' );
		$counts  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : ''; // phpcs:ignore
		$base    = admin_url( 'admin.php?page=khabar&view=requests' );
		$total   = array_sum( wp_list_pluck( $counts, 'c' ) );
		$views   = array( 'all' => sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>', esc_url( $base ), $current ? '' : 'current', esc_html__( 'همه', 'khabar' ), number_format_i18n( $total ) ) );
		foreach ( Khabar_Subscriptions::statuses() as $key => $label ) {
			if ( empty( $counts[ $key ] ) ) {
				continue;
			}
			$views[ $key ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>', esc_url( add_query_arg( 'status', $key, $base ) ), $current === $key ? 'current' : '', esc_html( $label ), number_format_i18n( $counts[ $key ]->c ) );
		}
		return $views;
	}

	/**
	 * Type filter.
	 *
	 * @param string $which Position.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$type = isset( $_REQUEST['type'] ) ? sanitize_key( $_REQUEST['type'] ) : ''; // phpcs:ignore
		echo '<div class="alignleft actions"><select name="type"><option value="">' . esc_html__( 'همه انواع', 'khabar' ) . '</option>';
		foreach ( Khabar_Subscriptions::types() as $key => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $type, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( __( 'فیلتر', 'khabar' ), '', 'filter_action', false );
		$export = wp_nonce_url( add_query_arg( array( 'action' => 'khabar_export' ) + array_map( 'sanitize_text_field', wp_unslash( $_GET ) ), admin_url( 'admin-post.php' ) ), 'khabar_export' ); // phpcs:ignore
		echo ' <a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'خروجی CSV', 'khabar' ) . '</a></div>';
	}

	/**
	 * Prepare.
	 */
	public function prepare_items() {
		global $wpdb;
		$table    = Khabar_Install::table( 'subscriptions' );
		$per_page = 30;
		$paged    = $this->get_pagenum();
		$where    = self::where();
		// phpcs:disable WordPress.Security.NonceVerification
		$orderby = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array( 'id', 'status', 'product_id' ), true ) ? sanitize_key( $_REQUEST['orderby'] ) : 'id';
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_key( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable
		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore
		$rows        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page ) ); // phpcs:ignore
		$this->items = array_map( array( 'Khabar_Subscriptions', 'hydrate' ), (array) $rows );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="ids[]" value="' . esc_attr( $item->id ) . '" />';
	}

	/**
	 * Product column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_product( $item ) {
		$p    = wc_get_product( $item->variation_id ? $item->variation_id : $item->product_id );
		$name = $p ? $p->get_name() : '#' . $item->product_id;
		$out  = '<strong><a href="' . esc_url( get_edit_post_link( $item->product_id ) ) . '">' . esc_html( $name ) . '</a></strong>';
		if ( $item->attributes ) {
			$out .= '<br><small>' . esc_html( Khabar_Utils::attributes_label( $item->attributes ) ) . '</small>';
		}
		if ( $p ) {
			$out .= '<br><small class="khabar-muted">' . esc_html( Khabar_Utils::price_text( $p->get_price() ) ) . ' — ' . ( $p->is_in_stock() ? esc_html__( 'موجود', 'khabar' ) : esc_html__( 'ناموجود', 'khabar' ) ) . '</small>';
		}
		$actions = array(
			'filter' => '<a href="' . esc_url( admin_url( 'admin.php?page=khabar&view=requests&product_id=' . $item->product_id ) ) . '">' . esc_html__( 'درخواست‌های این محصول', 'khabar' ) . '</a>',
		);
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Default column.
	 *
	 * @param object $item   Row.
	 * @param string $column Column.
	 * @return string
	 */
	protected function column_default( $item, $column ) {
		switch ( $column ) {
			case 'conditions':
				$types = Khabar_Subscriptions::types();
				return '<span class="khabar-type khabar-type-' . esc_attr( $item->type ) . '">' . esc_html( $types[ $item->type ] ?? $item->type ) . '</span><br><small>' . esc_html( Khabar_Subscriptions::conditions_label( $item ) ) . '</small>';
			case 'contact':
				$parts = array_filter( array( esc_html( $item->name ), $item->phone ? '<span dir="ltr">' . esc_html( $item->phone ) . '</span>' : '', esc_html( $item->email ) ) );
				if ( $item->user_id ) {
					$parts[] = '<a href="' . esc_url( get_edit_user_link( $item->user_id ) ) . '">' . esc_html__( 'کاربر', 'khabar' ) . ' #' . (int) $item->user_id . '</a>';
				}
				return implode( '<br>', $parts );
			case 'channels':
				$labels = Khabar_Settings::channels();
				return esc_html( implode( '، ', array_map( function ( $c ) use ( $labels ) { return $labels[ $c ] ?? $c; }, $item->channel_list ) ) ) ?: '—'; // phpcs:ignore
			case 'status':
				$st  = Khabar_Subscriptions::statuses();
				$out = '<span class="khabar-status khabar-status-' . esc_attr( $item->status ) . '">' . esc_html( $st[ $item->status ] ?? $item->status ) . '</span>';
				if ( $item->clicked_at ) {
					$out .= '<br><small>' . esc_html__( 'کلیک کرد', 'khabar' ) . '</small>';
				}
				if ( $item->order_id ) {
					$out .= '<br><small><a href="' . esc_url( admin_url( 'post.php?post=' . $item->order_id . '&action=edit' ) ) . '">' . esc_html__( 'سفارش', 'khabar' ) . ' #' . (int) $item->order_id . '</a></small>';
				}
				return $out;
			case 'created_at':
				return esc_html( Khabar_Admin::date( $item->created_at ) );
			case 'notified':
				return $item->last_notified_at ? esc_html( Khabar_Admin::date( $item->last_notified_at ) ) . ( $item->notified_count > 1 ? ' <small>(×' . (int) $item->notified_count . ')</small>' : '' ) : '—';
		}
		return '';
	}

	/**
	 * Empty message.
	 */
	public function no_items() {
		esc_html_e( 'درخواستی یافت نشد.', 'khabar' );
	}
}
