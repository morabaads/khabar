<?php
/**
 * Requests list table.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Tables;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Core\Installer;
use YadamBiar\Settings\Catalog;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Clock;
use YadamBiar\Support\Money;
use YadamBiar\Support\ProductAttributes;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class RequestsTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'yadambiar_request',
				'plural'   => 'yadambiar_requests',
				'ajax'     => false,
			)
		);
	}

	/**
	 * List filters from the request (validated against the known statuses and types).
	 *
	 * @return array status, type, product_id, s — only the ones set.
	 */
	public static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification -- read-only list filters.
		$filters = array(
			'status'     => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '',
			'type'       => isset( $_REQUEST['type'] ) ? sanitize_key( wp_unslash( $_REQUEST['type'] ) ) : '',
			'product_id' => isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0,
			's'          => isset( $_REQUEST['s'] ) && is_string( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
		);
		// phpcs:enable
		if ( ! isset( SubscriptionRepository::statuses()[ $filters['status'] ] ) ) {
			$filters['status'] = '';
		}
		if ( ! isset( SubscriptionRepository::types()[ $filters['type'] ] ) ) {
			$filters['type'] = '';
		}
		return array_filter( $filters );
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'product'    => __( 'محصول', 'yadambiar' ),
			'conditions' => __( 'شرایط', 'yadambiar' ),
			'contact'    => __( 'مشتری', 'yadambiar' ),
			'channels'   => __( 'کانال', 'yadambiar' ),
			'status'     => __( 'وضعیت', 'yadambiar' ),
			'created_at' => __( 'تاریخ ثبت', 'yadambiar' ),
			'notified'   => __( 'آخرین اعلان', 'yadambiar' ),
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
			'activate' => __( 'فعال‌سازی مجدد', 'yadambiar' ),
			'cancel'   => __( 'لغو', 'yadambiar' ),
			'delete'   => __( 'حذف', 'yadambiar' ),
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array
	 */
	protected function get_views() {
		global $wpdb;
		$table   = Installer::table( 'subscriptions' );
		$counts  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : ''; // phpcs:ignore
		$base    = AdminUrl::view( 'requests' );
		$total   = array_sum( wp_list_pluck( $counts, 'c' ) );
		$views   = array( 'all' => sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>', esc_url( $base ), $current ? '' : 'current', esc_html__( 'همه', 'yadambiar' ), number_format_i18n( $total ) ) );
		foreach ( SubscriptionRepository::statuses() as $key => $label ) {
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
		echo '<div class="alignleft actions"><select name="type"><option value="">' . esc_html__( 'همه انواع', 'yadambiar' ) . '</option>';
		foreach ( SubscriptionRepository::types() as $key => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $type, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( __( 'فیلتر', 'yadambiar' ), '', 'filter_action', false );
		$export = AdminUrl::action( 'export', self::filters() );
		echo ' <a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'خروجی CSV', 'yadambiar' ) . '</a></div>';
	}

	/**
	 * Prepare.
	 */
	public function prepare_items() {
		global $wpdb;
		$table    = Installer::table( 'subscriptions' );
		$per_page = 30;
		$paged    = $this->get_pagenum();
		$where    = SubscriptionRepository::filter_where( self::filters() );
		// phpcs:disable WordPress.Security.NonceVerification
		$orderby = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array( 'id', 'status', 'product_id' ), true ) ? sanitize_key( $_REQUEST['orderby'] ) : 'id';
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_key( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		// phpcs:enable
		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore
		$rows        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $per_page, ( $paged - 1 ) * $per_page ) ); // phpcs:ignore
		$this->items = array_map( array( SubscriptionRepository::class, 'hydrate' ), (array) $rows );
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
			$out .= '<br><small>' . esc_html( ProductAttributes::attributes_label( $item->attributes ) ) . '</small>';
		}
		if ( $p ) {
			$out .= '<br><small class="yadambiar-muted">' . esc_html( Money::price_text( $p->get_price() ) ) . ' — ' . ( $p->is_in_stock() ? esc_html__( 'موجود', 'yadambiar' ) : esc_html__( 'ناموجود', 'yadambiar' ) ) . '</small>';
		}
		$actions = array(
			'filter' => '<a href="' . esc_url( AdminUrl::view( 'requests', array( 'product_id' => $item->product_id ) ) ) . '">' . esc_html__( 'درخواست‌های این محصول', 'yadambiar' ) . '</a>',
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
				$types = SubscriptionRepository::types();
				return '<span class="yadambiar-type yadambiar-type-' . esc_attr( $item->type ) . '">' . esc_html( $types[ $item->type ] ?? $item->type ) . '</span><br><small>' . esc_html( SubscriptionRepository::conditions_label( $item ) ) . '</small>';
			case 'contact':
				$parts = array_filter( array( esc_html( $item->name ), $item->phone ? '<span dir="ltr">' . esc_html( $item->phone ) . '</span>' : '', esc_html( $item->email ) ) );
				if ( $item->user_id ) {
					$parts[] = '<a href="' . esc_url( get_edit_user_link( $item->user_id ) ) . '">' . esc_html__( 'کاربر', 'yadambiar' ) . ' #' . (int) $item->user_id . '</a>';
				}
				return implode( '<br>', $parts );
			case 'channels':
				$labels = Catalog::channels();
				return esc_html( implode( '، ', array_map( function ( $c ) use ( $labels ) { return $labels[ $c ] ?? $c; }, $item->channel_list ) ) ) ?: '—'; // phpcs:ignore
			case 'status':
				$st  = SubscriptionRepository::statuses();
				$out = '<span class="yadambiar-status yadambiar-status-' . esc_attr( $item->status ) . '">' . esc_html( $st[ $item->status ] ?? $item->status ) . '</span>';
				if ( $item->clicked_at ) {
					$out .= '<br><small>' . esc_html__( 'کلیک کرد', 'yadambiar' ) . '</small>';
				}
				if ( $item->order_id ) {
					$out .= '<br><small><a href="' . esc_url( admin_url( 'post.php?post=' . $item->order_id . '&action=edit' ) ) . '">' . esc_html__( 'سفارش', 'yadambiar' ) . ' #' . (int) $item->order_id . '</a></small>';
				}
				return $out;
			case 'created_at':
				return esc_html( Clock::local( $item->created_at ) );
			case 'notified':
				return $item->last_notified_at ? esc_html( Clock::local( $item->last_notified_at ) ) . ( $item->notified_count > 1 ? ' <small>(×' . (int) $item->notified_count . ')</small>' : '' ) : '—';
		}
		return '';
	}

	/**
	 * Empty message.
	 */
	public function no_items() {
		esc_html_e( 'درخواستی یافت نشد.', 'yadambiar' );
	}
}
