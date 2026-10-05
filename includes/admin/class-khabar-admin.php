<?php
/**
 * Admin pages (Flow 5): dashboard, requests, reports, log, settings.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Admin {

	const CAP = 'manage_woocommerce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_khabar_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_khabar_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_khabar_retry', array( __CLASS__, 'retry' ) );
		add_action( 'admin_post_khabar_run_product', array( __CLASS__, 'run_product' ) );
		add_action( 'admin_post_khabar_test', array( __CLASS__, 'test_send' ) );
		add_action( 'admin_post_khabar_set_webhooks', array( __CLASS__, 'set_webhooks' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Local date.
	 *
	 * @param string $gmt GMT mysql date.
	 * @return string
	 */
	public static function date( $gmt ) {
		return $gmt ? wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $gmt . ' UTC' ) ) : '';
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		global $wpdb;
		$table  = Khabar_Install::table( 'subscriptions' );
		$active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ); // phpcs:ignore
		$bubble = $active ? ' <span class="awaiting-mod">' . number_format_i18n( $active ) . '</span>' : '';

		add_menu_page( __( 'خبرم کن', 'khabar' ), __( 'خبرم کن', 'khabar' ) . $bubble, self::CAP, 'khabar', array( __CLASS__, 'page_dashboard' ), 'dashicons-bell', 56 );
		add_submenu_page( 'khabar', __( 'داشبورد', 'khabar' ), __( 'داشبورد', 'khabar' ), self::CAP, 'khabar', array( __CLASS__, 'page_dashboard' ) );
		$hook = add_submenu_page( 'khabar', __( 'درخواست‌ها', 'khabar' ), __( 'درخواست‌ها', 'khabar' ), self::CAP, 'khabar-requests', array( __CLASS__, 'page_requests' ) );
		add_action( 'load-' . $hook, array( __CLASS__, 'handle_bulk' ) );
		add_submenu_page( 'khabar', __( 'گزارش‌ها', 'khabar' ), __( 'گزارش‌ها', 'khabar' ), self::CAP, 'khabar-reports', array( __CLASS__, 'page_reports' ) );
		add_submenu_page( 'khabar', __( 'لاگ ارسال', 'khabar' ), __( 'لاگ ارسال', 'khabar' ), self::CAP, 'khabar-logs', array( __CLASS__, 'page_logs' ) );
		add_submenu_page( 'khabar', __( 'تنظیمات', 'khabar' ), __( 'تنظیمات', 'khabar' ), self::CAP, 'khabar-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'khabar' ) && ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'khabar-admin', KHABAR_URL . 'assets/css/admin.css', array(), KHABAR_VERSION );
		wp_enqueue_script( 'khabar-admin', KHABAR_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), KHABAR_VERSION, true );
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $url    URL.
	 * @param string $notice Notice text.
	 * @param string $type   success|error.
	 */
	private static function back( $url, $notice, $type = 'success' ) {
		set_transient( 'khabar_notice_' . get_current_user_id(), array( $notice, $type ), 60 );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Flash notices.
	 */
	public static function notices() {
		$key    = 'khabar_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice[1] ), esc_html( $notice[0] ) );
		}
	}

	/**
	 * Check capability + nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'khabar' ) );
		}
		check_admin_referer( $action );
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Dashboard page.
	 */
	public static function page_dashboard() {
		$k        = Khabar_Reports::kpis( 30 );
		$top      = Khabar_Reports::top_products( 10 );
		$restock  = Khabar_Reports::restock_priority( 8 );
		$channels = Khabar_Reports::channel_stats( 30 );
		$labels   = Khabar_Settings::channels();
		?>
		<div class="wrap khabar-admin">
			<h1><?php esc_html_e( 'داشبورد خبرم کن', 'khabar' ); ?></h1>

			<div class="khabar-kpis">
				<?php
				$cards = array(
					array( __( 'درخواست فعال', 'khabar' ), number_format_i18n( $k['active'] ), sprintf( /* translators: %s products */ __( 'برای %s محصول', 'khabar' ), number_format_i18n( $k['products'] ) ) ),
					array( __( 'مشتری منتظر', 'khabar' ), number_format_i18n( $k['people'] ), __( 'افراد یکتا', 'khabar' ) ),
					array( __( 'اعلان ارسال شده', 'khabar' ), number_format_i18n( $k['sent'] ), sprintf( /* translators: %s failed */ __( '%s ناموفق — ۳۰ روز اخیر', 'khabar' ), number_format_i18n( $k['failed'] ) ) ),
					array( __( 'نرخ کلیک', 'khabar' ), number_format_i18n( $k['click_rate'], 1 ) . '٪', sprintf( /* translators: %s clicks */ __( '%s کلیک', 'khabar' ), number_format_i18n( $k['clicked'] ) ) ),
					array( __( 'خرید از اعلان', 'khabar' ), number_format_i18n( $k['conversions'] ), sprintf( /* translators: %s rate */ __( 'نرخ تبدیل %s٪', 'khabar' ), number_format_i18n( $k['conv_rate'], 1 ) ) ),
					array( __( 'درآمد از اعلان‌ها', 'khabar' ), Khabar_Utils::price_text( $k['revenue'] ), __( '۳۰ روز اخیر', 'khabar' ) ),
				);
				foreach ( $cards as $card ) {
					printf( '<div class="khabar-kpi"><span>%s</span><strong>%s</strong><small>%s</small></div>', esc_html( $card[0] ), esc_html( $card[1] ), esc_html( $card[2] ) );
				}
				?>
			</div>

			<div class="khabar-grid">
				<div class="khabar-card">
					<h2><?php esc_html_e( 'محصولات تحت انتظار', 'khabar' ); ?></h2>
					<?php if ( ! $top ) : ?>
						<p><?php esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'khabar' ); ?></p>
					<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'محصول', 'khabar' ); ?></th><th><?php esc_html_e( 'منتظران', 'khabar' ); ?></th><th><?php esc_html_e( 'موجودی', 'khabar' ); ?></th><th><?php esc_html_e( 'قیمت', 'khabar' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						foreach ( $top as $row ) :
							$p = wc_get_product( $row->product_id );
							if ( ! $p ) {
								continue;
							}
							$run = wp_nonce_url( admin_url( 'admin-post.php?action=khabar_run_product&product_id=' . $row->product_id ), 'khabar_run_product' );
							?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=khabar-reports&product_id=' . $row->product_id ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a>
									<br><small><?php printf( /* translators: 1: stock requests 2: price requests */ esc_html__( '%1$s موجودی / %2$s قیمت', 'khabar' ), esc_html( number_format_i18n( $row->stock_requests ) ), esc_html( number_format_i18n( $row->price_requests ) ) ); ?></small></td>
								<td><strong><?php echo esc_html( number_format_i18n( $row->people ) ); ?></strong></td>
								<td><?php echo $p->is_in_stock() ? '<span class="khabar-status khabar-status-notified">' . esc_html__( 'موجود', 'khabar' ) . '</span>' : '<span class="khabar-status khabar-status-cancelled">' . esc_html__( 'ناموجود', 'khabar' ) . '</span>'; ?></td>
								<td><?php echo wp_kses_post( $p->get_price_html() ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( $run ); ?>"><?php esc_html_e( 'بررسی و ارسال', 'khabar' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<div class="khabar-card">
					<h2><?php esc_html_e( 'اولویت تامین موجودی', 'khabar' ); ?></h2>
					<p class="description"><?php esc_html_e( 'کالاهای ناموجود با بیشترین تقاضا و درآمد بالقوه در صورت تامین.', 'khabar' ); ?></p>
					<?php if ( ! $restock ) : ?>
						<p>—</p>
					<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'کالا', 'khabar' ); ?></th><th><?php esc_html_e( 'منتظر', 'khabar' ); ?></th><th><?php esc_html_e( 'درآمد بالقوه', 'khabar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $restock as $r ) : ?>
							<tr><td><a href="<?php echo esc_url( get_edit_post_link( $r['product']->get_parent_id() ? $r['product']->get_parent_id() : $r['product']->get_id() ) ); ?>"><?php echo esc_html( $r['product']->get_name() ); ?></a></td><td><?php echo esc_html( number_format_i18n( $r['count'] ) ); ?></td><td><?php echo esc_html( Khabar_Utils::price_text( $r['potential'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<h2><?php esc_html_e( 'وضعیت کانال‌ها (۳۰ روز)', 'khabar' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'کانال', 'khabar' ); ?></th><th><?php esc_html_e( 'موفق', 'khabar' ); ?></th><th><?php esc_html_e( 'ناموفق', 'khabar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $labels as $key => $label ) : ?>
							<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $channels[ $key ]['sent'] ?? 0 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $channels[ $key ]['failed'] ?? 0 ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Requests                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Bulk actions handler.
	 */
	public static function handle_bulk() {
		$table  = new Khabar_Requests_Table();
		$action = $table->current_action();
		if ( ! $action || empty( $_REQUEST['ids'] ) ) { // phpcs:ignore
			return;
		}
		check_admin_referer( 'bulk-khabar_requests' );
		$ids = array_map( 'absint', (array) $_REQUEST['ids'] ); // phpcs:ignore
		switch ( $action ) {
			case 'delete':
				Khabar_Subscriptions::delete( $ids );
				break;
			case 'cancel':
			case 'activate':
				foreach ( $ids as $id ) {
					Khabar_Subscriptions::update( $id, array( 'status' => 'cancel' === $action ? 'cancelled' : 'active' ) );
				}
				break;
		}
		self::back( remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ), wp_get_referer() ), __( 'انجام شد.', 'khabar' ) );
	}

	/**
	 * Requests page.
	 */
	public static function page_requests() {
		$table = new Khabar_Requests_Table();
		$table->prepare_items();
		?>
		<div class="wrap khabar-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'درخواست‌ها', 'khabar' ); ?></h1>
			<hr class="wp-header-end">
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="khabar-requests">
				<?php if ( ! empty( $_GET['status'] ) ) : // phpcs:ignore ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( sanitize_key( $_GET['status'] ) ); // phpcs:ignore ?>">
				<?php endif; ?>
				<?php
				$table->search_box( __( 'جستجو (موبایل، ایمیل، نام، شناسه محصول)', 'khabar' ), 'khabar' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * CSV export.
	 */
	public static function export_csv() {
		global $wpdb;
		self::guard( 'khabar_export' );
		$table = Khabar_Install::table( 'subscriptions' );
		$where = Khabar_Requests_Table::where();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 50000" ); // phpcs:ignore

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=khabar-requests-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
		fputcsv( $out, array( 'ID', 'Product', 'Variation', 'Attributes', 'Type', 'Conditions', 'Name', 'Phone', 'Email', 'Channels', 'Status', 'Created', 'Notified', 'Clicked', 'Order', 'Order value' ) );
		foreach ( $rows as $row ) {
			$sub = Khabar_Subscriptions::hydrate( $row );
			$p   = wc_get_product( $sub->product_id );
			fputcsv(
				$out,
				array(
					$sub->id,
					$p ? $p->get_name() : $sub->product_id,
					$sub->variation_id,
					Khabar_Utils::attributes_label( $sub->attributes ),
					$sub->type,
					Khabar_Subscriptions::conditions_label( $sub ),
					$sub->name,
					$sub->phone,
					$sub->email,
					$sub->channels,
					$sub->status,
					$sub->created_at,
					$sub->last_notified_at,
					$sub->clicked_at,
					$sub->order_id ? $sub->order_id : '',
					$sub->order_value,
				)
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}

	/**
	 * Run a product check now.
	 */
	public static function run_product() {
		self::guard( 'khabar_run_product' );
		$pid  = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$sent = $pid ? Khabar_Dispatcher::run_now( $pid ) : 0;
		/* translators: %s count */
		self::back( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=khabar' ), sprintf( __( '%s مشتری مطلع شدند. (فقط درخواست‌هایی که شرایطشان برقرار است ارسال می‌شوند.)', 'khabar' ), number_format_i18n( $sent ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Reports                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Reports page.
	 */
	public static function page_reports() {
		$pid     = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore
		$product = $pid ? wc_get_product( $pid ) : null;
		$demand  = Khabar_Reports::attribute_demand( $pid );
		$funnel  = Khabar_Reports::type_funnel();
		$types   = Khabar_Subscriptions::types();
		$top     = Khabar_Reports::top_products( 30 );
		?>
		<div class="wrap khabar-admin">
			<h1><?php esc_html_e( 'گزارش رفتار مشتریان', 'khabar' ); ?></h1>

			<form method="get" class="khabar-report-filter">
				<input type="hidden" name="page" value="khabar-reports">
				<select name="product_id">
					<option value=""><?php esc_html_e( 'کل فروشگاه', 'khabar' ); ?></option>
					<?php foreach ( $top as $row ) : $p = wc_get_product( $row->product_id ); if ( ! $p ) { continue; } ?>
						<option value="<?php echo esc_attr( $row->product_id ); ?>" <?php selected( $pid, (int) $row->product_id ); ?>><?php echo esc_html( $p->get_name() . ' (' . number_format_i18n( $row->people ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'نمایش', 'khabar' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( $product ) : ?>
				<div class="khabar-card">
					<h2><?php echo esc_html( $product->get_name() ); ?></h2>
					<p>
						<?php
						/* translators: %s people */
						printf( esc_html__( 'تعداد منتظر: %s نفر', 'khabar' ), '<strong>' . esc_html( number_format_i18n( Khabar_Subscriptions::waiting_count( $pid ) ) ) . '</strong>' );
						?>
					</p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'تنوع / ویژگی‌های درخواستی', 'khabar' ); ?></th><th><?php esc_html_e( 'درخواست', 'khabar' ); ?></th><th><?php esc_html_e( 'موجودی فعلی', 'khabar' ); ?></th></tr></thead>
						<tbody>
						<?php
						foreach ( Khabar_Reports::variation_demand( $pid ) as $row ) :
							$v     = $row->variation_id ? wc_get_product( $row->variation_id ) : null;
							$label = $v ? wc_get_formatted_variation( $v, true, true ) : ( $row->attributes ? Khabar_Utils::attributes_label( json_decode( $row->attributes, true ) ) : __( 'کل محصول / هر تنوعی', 'khabar' ) );
							?>
							<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $row->c ) ); ?></td><td><?php echo $v ? esc_html( $v->is_in_stock() ? ( $v->managing_stock() ? number_format_i18n( $v->get_stock_quantity() ) : __( 'موجود', 'khabar' ) ) : __( 'ناموجود', 'khabar' ) ) : '—'; ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="khabar-grid">
				<div class="khabar-card">
					<h2><?php echo $product ? esc_html__( 'بیشترین ویژگی‌های درخواست‌شده این محصول', 'khabar' ) : esc_html__( 'بیشترین ویژگی‌های درخواست‌شده در فروشگاه', 'khabar' ); ?></h2>
					<?php if ( ! $demand ) : ?>
						<p><?php esc_html_e( 'داده‌ای موجود نیست.', 'khabar' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $demand as $attr => $values ) : ?>
						<h3><?php echo esc_html( $attr ); ?></h3>
						<table class="widefat striped khabar-compact">
							<tbody>
							<?php foreach ( array_slice( $values, 0, 10, true ) as $value => $count ) : ?>
								<tr><td><?php echo esc_html( $value ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
				</div>

				<div class="khabar-card">
					<h2><?php esc_html_e( 'قیف هر نوع اعلان', 'khabar' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'نوع', 'khabar' ); ?></th><th><?php esc_html_e( 'در انتظار', 'khabar' ); ?></th><th><?php esc_html_e( 'اطلاع داده شد', 'khabar' ); ?></th><th><?php esc_html_e( 'خرید', 'khabar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $types as $key => $label ) : $f = $funnel[ $key ] ?? array(); ?>
							<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $f['active'] ?? 0 ) ); ?></td><td><?php echo esc_html( number_format_i18n( ( $f['notified'] ?? 0 ) + ( $f['purchased'] ?? 0 ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( $f['purchased'] ?? 0 ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Logs                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Log page.
	 */
	public static function page_logs() {
		$table = new Khabar_Log_Table();
		$table->prepare_items();
		?>
		<div class="wrap khabar-admin">
			<h1><?php esc_html_e( 'لاگ ارسال اعلان‌ها', 'khabar' ); ?></h1>
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="khabar-logs">
				<?php
				$table->search_box( __( 'جستجو', 'khabar' ), 'khabar-log' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Retry a failed send.
	 */
	public static function retry() {
		self::guard( 'khabar_retry' );
		$result = Khabar_Channels::retry( isset( $_GET['log'] ) ? absint( $_GET['log'] ) : 0 );
		$back   = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=khabar-logs' );
		if ( is_wp_error( $result ) ) {
			self::back( $back, $result->get_error_message(), 'error' );
		}
		self::back( $back, __( 'ارسال شد.', 'khabar' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Settings page.
	 */
	public static function page_settings() {
		$schema   = Khabar_Settings::schema();
		$tab      = isset( $_GET['tab'] ) && isset( $schema[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore
		$values   = Khabar_Settings::all();
		$defaults = Khabar_Settings::defaults();
		?>
		<div class="wrap khabar-admin">
			<h1><?php esc_html_e( 'تنظیمات خبرم کن', 'khabar' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $schema as $key => $section ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=khabar-settings&tab=' . $key ) ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( ! empty( $schema[ $tab ]['desc'] ) ) : ?>
				<p class="description khabar-tab-desc"><?php echo esc_html( $schema[ $tab ]['desc'] ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="khabar_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'khabar_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<?php
					foreach ( $schema[ $tab ]['fields'] as $key => $field ) {
						self::field( $key, $field, $values[ $key ] ?? '', $defaults[ $key ] ?? '' );
					}
					?>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( 'channels' === $tab ) : ?>
				<div class="khabar-card">
					<h2><?php esc_html_e( 'ارسال آزمایشی', 'khabar' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="khabar-inline-form">
						<input type="hidden" name="action" value="khabar_test">
						<?php wp_nonce_field( 'khabar_test' ); ?>
						<select name="channel">
							<option value="sms"><?php esc_html_e( 'پیامک', 'khabar' ); ?></option>
							<option value="email"><?php esc_html_e( 'ایمیل', 'khabar' ); ?></option>
							<option value="whatsapp"><?php esc_html_e( 'واتساپ', 'khabar' ); ?></option>
							<option value="telegram"><?php esc_html_e( 'تلگرام (chat id یا @channel)', 'khabar' ); ?></option>
							<option value="bale"><?php esc_html_e( 'بله (chat id یا @channel)', 'khabar' ); ?></option>
							<option value="eitaa"><?php esc_html_e( 'ایتا (شناسه کانال)', 'khabar' ); ?></option>
						</select>
						<input type="text" name="to" dir="ltr" placeholder="09xxxxxxxxx / email" required>
						<?php submit_button( __( 'ارسال تست', 'khabar' ), 'secondary', '', false ); ?>
					</form>
					<h2><?php esc_html_e( 'ربات‌های پیام‌رسان', 'khabar' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="khabar_set_webhooks">
						<?php wp_nonce_field( 'khabar_set_webhooks' ); ?>
						<p class="description"><?php esc_html_e( 'پس از ذخیره توکن‌ها این دکمه را بزنید تا تلگرام/بله پیام‌های کاربران (Start) را به سایت شما بفرستند.', 'khabar' ); ?></p>
						<?php foreach ( array( 'telegram', 'bale' ) as $khabar_net ) : ?>
							<p><code dir="ltr" style="font-size:11px"><?php echo esc_html( preg_replace( '/secret=[^&]+/', 'secret=…', Khabar_Messenger::webhook_url( $khabar_net ) ) ); ?></code></p>
						<?php endforeach; ?>
						<?php submit_button( __( 'ثبت وب‌هوک ربات‌ها', 'khabar' ), 'secondary', '', false ); ?>
					</form>
					<?php $keys = Khabar_Push::keys(); ?>
					<p class="description">
						<?php esc_html_e( 'کلید عمومی VAPID:', 'khabar' ); ?>
						<code dir="ltr"><?php echo esc_html( $keys ? $keys['public'] : __( 'OpenSSL در دسترس نیست', 'khabar' ) ); ?></code>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( 'general' === $tab ) : ?>
				<div class="khabar-card">
					<h2><?php esc_html_e( 'شورت‌کدها', 'khabar' ); ?></h2>
					<ul>
						<li><code>[khabar]</code> / <code>[khabar product_id="123"]</code> — <?php esc_html_e( 'دکمه و فرم خبرم کن', 'khabar' ); ?></li>
						<li><code>[khabar_my_alerts]</code> — <?php esc_html_e( 'پنل «خبرم کن‌های من» (برای مهمان‌ها با لینک ارسالی)', 'khabar' ); ?></li>
						<li><code>[khabar_bell]</code> — <?php esc_html_e( 'زنگوله اعلان‌های داخل سایت', 'khabar' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one settings field.
	 *
	 * @param string $key     Key.
	 * @param array  $field   Field.
	 * @param mixed  $value   Value.
	 * @param mixed  $default Default.
	 */
	private static function field( $key, $field, $value, $default ) {
		$name = 'khabar[' . $key . ']';
		$id   = 'khabar-' . $key;
		if ( 'heading' === $field['type'] ) {
			echo '<tr><th colspan="2"><h2 class="khabar-heading">' . esc_html( $field['label'] ) . '</h2>' . ( ! empty( $field['desc'] ) ? '<p class="description">' . esc_html( $field['desc'] ) . '</p>' : '' ) . '</th></tr>';
			return;
		}
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		switch ( $field['type'] ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', esc_attr( $id ), esc_attr( $name ), checked( (int) $value, 1, false ), esc_html__( 'فعال', 'khabar' ) );
				break;
			case 'multicheck':
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<label class="khabar-check"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>', esc_attr( $name ), esc_attr( $opt ), checked( in_array( $opt, (array) $value, true ), true, false ), esc_html( $label ) );
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'number':
				printf( '<input type="number" min="0" class="small-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			case 'textarea':
			case 'html':
				printf( '<textarea id="%s" name="%s" rows="%d" class="large-text" %s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), 'html' === $field['type'] ? 5 : 3, 'html' === $field['type'] ? 'dir="auto"' : '', esc_textarea( $value ) );
				if ( $default && $default !== $value ) {
					printf( ' <button type="button" class="button-link khabar-reset" data-target="%s" data-default="%s">%s</button>', esc_attr( $id ), esc_attr( $default ), esc_html__( 'بازگردانی پیش‌فرض', 'khabar' ) );
				}
				break;
			case 'color':
				printf( '<input type="text" class="khabar-color" id="%s" name="%s" value="%s" data-default-color="%s" dir="ltr">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $default ) );
				break;
			case 'password':
				printf( '<input type="password" autocomplete="new-password" class="regular-text" dir="ltr" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="text" class="regular-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}
		if ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Save settings.
	 */
	public static function save_settings() {
		self::guard( 'khabar_save_settings' );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'general';
		Khabar_Settings::save( isset( $_POST['khabar'] ) ? (array) $_POST['khabar'] : array(), $tab ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( Khabar_Settings::has( 'channels_enabled', 'push' ) ) {
			Khabar_Push::keys();
		}
		self::back( admin_url( 'admin.php?page=khabar-settings&tab=' . $tab ), __( 'تنظیمات ذخیره شد.', 'khabar' ) );
	}

	/**
	 * Register Telegram / Bale webhooks.
	 */
	public static function set_webhooks() {
		self::guard( 'khabar_set_webhooks' );
		$back    = admin_url( 'admin.php?page=khabar-settings&tab=channels' );
		$results = Khabar_Messenger::register_webhooks();
		if ( ! $results ) {
			self::back( $back, __( 'هیچ توکن رباتی وارد نشده است.', 'khabar' ), 'error' );
		}
		$msgs  = array();
		$error = false;
		foreach ( $results as $network => $result ) {
			$label  = Khabar_Settings::channels()[ $network ];
			$error  = $error || is_wp_error( $result );
			$msgs[] = $label . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : __( 'وب‌هوک ثبت شد', 'khabar' ) );
		}
		self::back( $back, implode( ' | ', $msgs ), $error ? 'error' : 'success' );
	}

	/**
	 * Test send.
	 */
	public static function test_send() {
		self::guard( 'khabar_test' );
		$channel = isset( $_POST['channel'] ) ? sanitize_key( $_POST['channel'] ) : 'sms';
		$to      = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		/* translators: %s site */
		$text = sprintf( __( 'پیام آزمایشی خبرم کن از %s', 'khabar' ), get_bloginfo( 'name' ) );
		switch ( $channel ) {
			case 'email':
				$result = Khabar_Channel_Email::send( $to, $text, '<p>' . esc_html( $text ) . '</p>' );
				break;
			case 'whatsapp':
				$result = Khabar_Channel_Whatsapp::send( $to, $text );
				break;
			case 'telegram':
			case 'bale':
			case 'eitaa':
				$result = Khabar_Messenger::send( $channel, $to, $text );
				break;
			default:
				$result = Khabar_Channel_Sms::send( $to, $text, 'text' );
		}
		$back = admin_url( 'admin.php?page=khabar-settings&tab=channels' );
		if ( is_wp_error( $result ) ) {
			self::back( $back, $result->get_error_message(), 'error' );
		}
		self::back( $back, __( 'پیام آزمایشی ارسال شد.', 'khabar' ) );
	}
}
