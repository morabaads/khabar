<?php
/**
 * Admin pages (Flow 5): dashboard, requests, reports, log, settings.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Pages\ForecastPage;
use YadamBiar\Admin\Tables\LogTable;
use YadamBiar\Admin\Tables\RequestsTable;
use YadamBiar\Channels\ChannelManager;
use YadamBiar\Channels\EmailChannel;
use YadamBiar\Channels\Messenger\MessengerChannel;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Channels\Sms\ProviderRegistry;
use YadamBiar\Channels\Sms\SmsChannel;
use YadamBiar\Core\Installer;
use YadamBiar\Frontend\ProductWidget;
use YadamBiar\Notifications\Dispatcher;
use YadamBiar\Reports\ReportRepository;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Money;
use YadamBiar\Support\ProductAttributes;

class AdminApp {

	const CAP = 'manage_woocommerce';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_action( 'admin_init', array( __CLASS__, 'legacy_redirect' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'menu_assets' ) );
		add_action( 'admin_post_yadambiar_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_yadambiar_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_yadambiar_retry', array( __CLASS__, 'retry' ) );
		add_action( 'admin_post_yadambiar_run_product', array( __CLASS__, 'run_product' ) );
		add_action( 'admin_post_yadambiar_test', array( __CLASS__, 'test_send' ) );
		add_action( 'admin_post_yadambiar_sms_account', array( __CLASS__, 'sms_account' ) );
		add_action( 'admin_post_yadambiar_sms_status', array( __CLASS__, 'sms_status' ) );
		add_action( 'admin_post_yadambiar_set_webhooks', array( __CLASS__, 'set_webhooks' ) );
		add_action( 'admin_post_yadambiar_check_bots', array( __CLASS__, 'check_bots' ) );
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
	 * Views of the single-page admin app: slug => [label, callback].
	 *
	 * @return array
	 */
	public static function views() {
		return array(
			'dashboard' => array( __( 'داشبورد', 'yadam-biar' ), array( __CLASS__, 'page_dashboard' ) ),
			'requests'  => array( __( 'درخواست‌ها', 'yadam-biar' ), array( __CLASS__, 'page_requests' ) ),
			'reports'   => array( __( 'گزارش‌ها', 'yadam-biar' ), array( __CLASS__, 'page_reports' ) ),
			'logs'      => array( __( 'لاگ ارسال', 'yadam-biar' ), array( __CLASS__, 'page_logs' ) ),
			'forecast'  => array( __( 'پیش‌بینی تقاضا', 'yadam-biar' ), array( ForecastPage::class, 'page' ) ),
			'settings'  => array( __( 'تنظیمات', 'yadam-biar' ), array( __CLASS__, 'page_settings' ) ),
			'help'      => array( __( 'راهنما', 'yadam-biar' ), array( __CLASS__, 'page_help' ) ),
			'diagnose'  => array( __( 'عیب‌یابی ارسال', 'yadam-biar' ), array( __CLASS__, 'page_diagnose' ), 'hidden' ),
		);
	}

	/**
	 * Current view slug.
	 *
	 * @return string
	 */
	public static function current_view() {
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification
		return isset( self::views()[ $view ] ) ? $view : 'dashboard';
	}

	/**
	 * Menu: one entry, the sections live inside the page (loaded with AJAX).
	 */
	public static function menu() {
		global $wpdb;
		$table  = Installer::table( 'subscriptions' );
		$active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ); // phpcs:ignore
		$bubble = $active ? ' <span class="awaiting-mod">' . number_format_i18n( $active ) . '</span>' : '';

		$label = '<span class="yadambiar-mn"><b>' . esc_html__( 'یادم بیار', 'yadam-biar' ) . $bubble . '</b><small>' . esc_html__( 'اعلان موجودی و قیمت', 'yadam-biar' ) . '</small></span>';
		$hook  = add_menu_page( __( 'یادم بیار', 'yadam-biar' ), $label, self::CAP, 'yadam-biar', array( __CLASS__, 'page_app' ), 'dashicons-bell', 56 );
		add_action( 'load-' . $hook, array( __CLASS__, 'load_app' ) );

		// Sidebar shortcuts into the single-page app (each opens its section).
		foreach ( self::views() as $slug => $view ) {
			if ( ! empty( $view[2] ) ) {
				continue;
			}
			$target = 'dashboard' === $slug ? 'yadam-biar' : 'admin.php?page=yadambiar&view=' . $slug;
			add_submenu_page( 'yadam-biar', $view[0], $view[0], self::CAP, $target, 'dashboard' === $slug ? array( __CLASS__, 'page_app' ) : '' );
		}
	}

	/**
	 * Highlight the sidebar shortcut of the open section.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $file ) {
		if ( isset( $_GET['page'] ) && 'yadam-biar' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$view = self::current_view();
			return 'dashboard' === $view ? 'yadam-biar' : 'admin.php?page=yadambiar&view=' . $view;
		}
		return $file;
	}

	/**
	 * Keep the app page clean: drop notices other plugins/WordPress print (reviews, promos, nags).
	 * Yadam Biar's own flash notices are printed inside the app, not through these hooks.
	 */
	public static function silence_foreign_notices() {
		foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
			remove_all_actions( $hook );
		}
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag', 10 );
	}

	/**
	 * Old per-section URLs (page=yadambiar-settings …) redirect to the single page.
	 */
	public static function legacy_redirect() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, 'yadambiar-' ) ) {
			return;
		}
		$args         = array_map( 'sanitize_text_field', wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$args['view'] = substr( $page, 7 );
		$args['page'] = 'yadam-biar';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Runs before any output on the app page: bulk actions and, for AJAX navigation, the bare fragment.
	 */
	public static function load_app() {
		self::silence_foreign_notices();
		if ( 'requests' === self::current_view() ) {
			self::handle_bulk();
		}
		if ( isset( $_SERVER['HTTP_X_YADAMBIAR_AJAX'] ) ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-YadamBiar-Fragment: 1' );
			header( 'X-YadamBiar-View: ' . self::current_view() );
			self::notices( true );
			self::render_view();
			exit;
		}
	}

	/**
	 * Render the active view.
	 */
	private static function render_view() {
		$views = self::views();
		call_user_func( $views[ self::current_view() ][1] );
	}

	/**
	 * Single-page app shell: hero, section tabs and the (AJAX-swapped) body.
	 */
	public static function page_app() {
		$view  = self::current_view();
		$views = self::views();
		?>
		<div class="wrap yadambiar-admin" id="yadambiar-app">
			<?php
			$icons = array( 'dashboard' => 'grid', 'requests' => 'list', 'reports' => 'chart', 'logs' => 'send', 'forecast' => 'trend', 'settings' => 'gear', 'help' => 'help' );
			ob_start();
			?>
			<nav class="yadambiar-tabs yadambiar-nav" aria-label="<?php esc_attr_e( 'بخش‌های یادم بیار', 'yadam-biar' ); ?>">
				<?php foreach ( $views as $slug => $v ) : if ( ! empty( $v[2] ) ) { continue; } ?>
					<a class="nav-tab <?php echo $slug === $view ? 'nav-tab-active' : ''; ?>" data-view="<?php echo esc_attr( $slug ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=yadambiar&view=' . $slug ) ); ?>"><?php echo ProductWidget::icon( isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $v[0] ); ?></span></a>
				<?php endforeach; ?>
			</nav>
			<?php
			self::header( __( 'اتاق یادم بیار', 'yadam-biar' ), __( 'اعلان موجودی و قیمت', 'yadam-biar' ), ob_get_clean() );
			?>
			<div id="yadambiar-body" aria-live="polite">
				<?php
				self::notices( true );
				self::render_view();
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'yadam-biar' ) && ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'yadambiar-admin', YADAMBIAR_URL . 'assets/css/admin.css', array(), YADAMBIAR_VERSION );
		$accent = sanitize_hex_color( (string) Settings::get( 'color_accent', '' ) );
		wp_add_inline_style( 'yadambiar-admin', '.yadambiar-admin{--ka:' . ( $accent ? $accent : '#f4511e' ) . ';}' );
		if ( 'toplevel_page_yadambiar' === $hook ) {
			wp_enqueue_media(); // Media library picker for image settings (sections load via AJAX, so always on the app page).
		}
		wp_enqueue_script( 'yadambiar-admin', YADAMBIAR_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), YADAMBIAR_VERSION, true );
	}

	/**
	 * Font for the plugin's sidebar menu (loaded on every admin page).
	 */
	public static function menu_assets() {
		wp_enqueue_style( 'yadambiar-admin-menu', YADAMBIAR_URL . 'assets/css/admin-menu.css', array(), YADAMBIAR_VERSION );
	}

	/**
	 * Redirect back with a notice.
	 *
	 * @param string $url    URL.
	 * @param string $notice Notice text.
	 * @param string $type   success|error.
	 */
	private static function back( $url, $notice, $type = 'success' ) {
		set_transient( 'yadambiar_notice_' . get_current_user_id(), array( $notice, $type ), 60 );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Flash notices.
	 */
	public static function notices( $in_app = false ) {
		if ( ! $in_app && isset( $_GET['page'] ) && 'yadam-biar' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$key    = 'yadambiar_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%s yadambiar-notice"><p>%s</p></div>', esc_attr( $notice[1] ), esc_html( $notice[0] ) );
		}
	}

	/**
	 * Shared page header (banner with title and channel status pills).
	 *
	 * @param string $title    Title.
	 * @param string $subtitle Subtitle.
	 */
	public static function header( $title, $subtitle = '', $nav = '' ) {
		global $wpdb;
		$active = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Installer::table( 'subscriptions' ) . " WHERE status = 'active'" ); // phpcs:ignore
		?>
		<header class="yadambiar-hero">
			<div class="yadambiar-hero-brand">
				<span class="yadambiar-hero-logo"><?php echo ProductWidget::icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<div>
					<h1><?php esc_html_e( 'یادم بیار', 'yadam-biar' ); ?></h1>
					<p><?php echo esc_html( $subtitle ? $subtitle : $title ); ?></p>
				</div>
			</div>
			<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<a class="yadambiar-hero-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=yadambiar&view=requests&status=active' ) ); ?>">
				<span class="yadambiar-hero-dot"></span>
				<?php /* translators: %s number */ printf( esc_html__( '%s درخواست فعال', 'yadam-biar' ), '<b>' . esc_html( number_format_i18n( $active ) ) . '</b>' ); ?>
			</a>
		</header>
		<hr class="wp-header-end">
		<?php
	}

	/**
	 * Check capability + nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadam-biar' ) );
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
		$k        = ReportRepository::kpis( 30 );
		$top      = ReportRepository::top_products( 10 );
		$restock  = ReportRepository::restock_priority( 8 );
		$channels = ReportRepository::channel_stats( 30 );
		$labels   = Settings::channels();
		?>
		<div class="yadambiar-view">

			<div class="yadambiar-kpis">
				<?php
				$cards = array(
					array( __( 'درخواست فعال', 'yadam-biar' ), number_format_i18n( $k['active'] ), sprintf( /* translators: %s products */ __( 'برای %s محصول', 'yadam-biar' ), number_format_i18n( $k['products'] ) ) ),
					array( __( 'مشتری منتظر', 'yadam-biar' ), number_format_i18n( $k['people'] ), __( 'افراد یکتا', 'yadam-biar' ) ),
					array( __( 'اعلان ارسال شده', 'yadam-biar' ), number_format_i18n( $k['sent'] ), sprintf( /* translators: %s failed */ __( '%s ناموفق — ۳۰ روز اخیر', 'yadam-biar' ), number_format_i18n( $k['failed'] ) ) ),
					array( __( 'نرخ کلیک', 'yadam-biar' ), number_format_i18n( $k['click_rate'], 1 ) . '٪', sprintf( /* translators: %s clicks */ __( '%s کلیک', 'yadam-biar' ), number_format_i18n( $k['clicked'] ) ) ),
					array( __( 'خرید از اعلان', 'yadam-biar' ), number_format_i18n( $k['conversions'] ), sprintf( /* translators: %s rate */ __( 'نرخ تبدیل %s٪', 'yadam-biar' ), number_format_i18n( $k['conv_rate'], 1 ) ) ),
					array( __( 'درآمد از اعلان‌ها', 'yadam-biar' ), Money::price_text( $k['revenue'] ), __( '۳۰ روز اخیر', 'yadam-biar' ) ),
				);
				foreach ( $cards as $card ) {
					printf( '<div class="yadambiar-kpi"><span>%s</span><strong>%s</strong><small>%s</small></div>', esc_html( $card[0] ), esc_html( $card[1] ), esc_html( $card[2] ) ); // Icons come from CSS (nth-child).
				}
				?>
			</div>

			<div class="yadambiar-grid">
				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'محصولات تحت انتظار', 'yadam-biar' ); ?></h2>
					<?php if ( ! $top ) : ?>
						<p><?php esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'yadam-biar' ); ?></p>
					<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'محصول', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'منتظران', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'موجودی', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'قیمت', 'yadam-biar' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						foreach ( $top as $row ) :
							$p = wc_get_product( $row->product_id );
							if ( ! $p ) {
								continue;
							}
							$run = wp_nonce_url( admin_url( 'admin-post.php?action=yadambiar_run_product&product_id=' . $row->product_id ), 'yadambiar_run_product' );
							?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=yadambiar&view=reports&product_id=' . $row->product_id ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a>
									<br><small><?php printf( /* translators: 1: stock requests 2: price requests */ esc_html__( '%1$s موجودی / %2$s قیمت', 'yadam-biar' ), esc_html( number_format_i18n( $row->stock_requests ) ), esc_html( number_format_i18n( $row->price_requests ) ) ); ?></small></td>
								<td><strong><?php echo esc_html( number_format_i18n( $row->people ) ); ?></strong></td>
								<td><?php echo $p->is_in_stock() ? '<span class="yadambiar-status yadambiar-status-notified">' . esc_html__( 'موجود', 'yadam-biar' ) . '</span>' : '<span class="yadambiar-status yadambiar-status-cancelled">' . esc_html__( 'ناموجود', 'yadam-biar' ) . '</span>'; ?></td>
								<td><?php echo wp_kses_post( $p->get_price_html() ); ?></td>
								<td style="white-space:nowrap"><a class="button button-small" href="<?php echo esc_url( $run ); ?>"><?php esc_html_e( 'بررسی و ارسال', 'yadam-biar' ); ?></a> <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=yadambiar&view=diagnose&product_id=' . $row->product_id ) ); ?>"><?php esc_html_e( 'عیب‌یابی', 'yadam-biar' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'اولویت تامین موجودی', 'yadam-biar' ); ?></h2>
					<p class="description"><?php esc_html_e( 'کالاهای ناموجود با بیشترین تقاضا و درآمد بالقوه در صورت تامین.', 'yadam-biar' ); ?></p>
					<?php if ( ! $restock ) : ?>
						<p>—</p>
					<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'کالا', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'منتظر', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'درآمد بالقوه', 'yadam-biar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $restock as $r ) : ?>
							<tr><td><a href="<?php echo esc_url( get_edit_post_link( $r['product']->get_parent_id() ? $r['product']->get_parent_id() : $r['product']->get_id() ) ); ?>"><?php echo esc_html( $r['product']->get_name() ); ?></a></td><td><?php echo esc_html( number_format_i18n( $r['count'] ) ); ?></td><td><?php echo esc_html( Money::price_text( $r['potential'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<h2><?php esc_html_e( 'وضعیت کانال‌ها (۳۰ روز)', 'yadam-biar' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'کانال', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'موفق', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'ناموفق', 'yadam-biar' ); ?></th></tr></thead>
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
		$table  = new RequestsTable();
		$action = $table->current_action();
		if ( ! $action || empty( $_REQUEST['ids'] ) ) { // phpcs:ignore
			return;
		}
		check_admin_referer( 'bulk-yadambiar_requests' );
		$ids = array_map( 'absint', (array) $_REQUEST['ids'] ); // phpcs:ignore
		switch ( $action ) {
			case 'delete':
				SubscriptionRepository::delete( $ids );
				break;
			case 'cancel':
			case 'activate':
				foreach ( $ids as $id ) {
					SubscriptionRepository::update( $id, array( 'status' => 'cancel' === $action ? 'cancelled' : 'active' ) );
				}
				break;
		}
		self::back( remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ), wp_get_referer() ), __( 'انجام شد.', 'yadam-biar' ) );
	}

	/**
	 * Requests page.
	 */
	public static function page_requests() {
		$table = new RequestsTable();
		$table->prepare_items();
		?>
		<div class="yadambiar-view">
			<hr class="wp-header-end">
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="yadam-biar"><input type="hidden" name="view" value="requests">
				<?php if ( ! empty( $_GET['status'] ) ) : // phpcs:ignore ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( sanitize_key( $_GET['status'] ) ); // phpcs:ignore ?>">
				<?php endif; ?>
				<?php
				$table->search_box( __( 'جستجو (موبایل، ایمیل، نام، شناسه محصول)', 'yadam-biar' ), 'yadam-biar' );
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
		self::guard( 'yadambiar_export' );
		$table = Installer::table( 'subscriptions' );
		$where = RequestsTable::where();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 50000" ); // phpcs:ignore

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=yadambiar-requests-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
		fputcsv( $out, array( 'ID', 'Product', 'Variation', 'Attributes', 'Type', 'Conditions', 'Name', 'Phone', 'Email', 'Channels', 'Status', 'Created', 'Notified', 'Clicked', 'Order', 'Order value' ) );
		foreach ( $rows as $row ) {
			$sub = SubscriptionRepository::hydrate( $row );
			$p   = wc_get_product( $sub->product_id );
			fputcsv(
				$out,
				array(
					$sub->id,
					$p ? $p->get_name() : $sub->product_id,
					$sub->variation_id,
					ProductAttributes::attributes_label( $sub->attributes ),
					$sub->type,
					SubscriptionRepository::conditions_label( $sub ),
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
		self::guard( 'yadambiar_run_product' );
		$pid  = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$sent = $pid ? Dispatcher::run_now( $pid ) : 0;
		if ( $pid && ! $sent ) {
			self::back( admin_url( 'admin.php?page=yadambiar&view=diagnose&product_id=' . $pid ), __( 'اعلانی ارسال نشد؛ دلیل هر درخواست را در جدول زیر ببینید.', 'yadam-biar' ), 'error' );
		}
		/* translators: %s count */
		self::back( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=yadambiar' ), sprintf( __( '%s مشتری مطلع شدند. (فقط درخواست‌هایی که شرایطشان برقرار است ارسال می‌شوند.)', 'yadam-biar' ), number_format_i18n( $sent ) ) );
	}

	/**
	 * Diagnostics: why a product's waiting customers were (not) notified.
	 */
	public static function page_diagnose() {
		global $wpdb;
		$pid   = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$table = Installer::table( 'subscriptions' );
		$ids   = $wpdb->get_col( "SELECT product_id FROM {$table} WHERE status IN ('active','pending') GROUP BY product_id ORDER BY COUNT(*) DESC LIMIT 100" ); // phpcs:ignore
		if ( ! $pid && $ids ) {
			$pid = (int) $ids[0];
		}
		$rows    = $pid ? Dispatcher::diagnose( $pid ) : array();
		$health  = Dispatcher::health( $pid );
		$product = $pid ? wc_get_product( $pid ) : null;
		?>
		<div class="yadambiar-view">
			<form method="get" class="yadambiar-report-filter">
				<input type="hidden" name="page" value="yadam-biar"><input type="hidden" name="view" value="diagnose">
				<label><?php esc_html_e( 'محصول', 'yadam-biar' ); ?>
					<select name="product_id" style="width:320px">
						<?php foreach ( $ids as $id ) : $p = wc_get_product( $id ); if ( ! $p ) { continue; } ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $pid, (int) $id ); ?>><?php echo esc_html( $p->get_name() ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php submit_button( __( 'بررسی', 'yadam-biar' ), 'secondary', '', false ); ?>
				<?php if ( $pid ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=yadambiar_run_product&product_id=' . $pid ), 'yadambiar_run_product' ) ); ?>"><?php esc_html_e( 'بررسی و ارسال اکنون', 'yadam-biar' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="yadambiar-card">
				<h2><?php esc_html_e( 'وضعیت سیستم', 'yadam-biar' ); ?></h2>
				<ul class="yadambiar-health">
					<?php foreach ( $health as $h ) : ?>
						<li class="is-<?php echo esc_attr( $h[0] ); ?>"><?php echo esc_html( $h[1] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="yadambiar-card">
				<h2><?php echo esc_html( $product ? sprintf( /* translators: %s product */ __( 'درخواست‌های «%s»', 'yadam-biar' ), $product->get_name() ) : __( 'درخواستی برای بررسی وجود ندارد', 'yadam-biar' ) ); ?></h2>
				<?php if ( $rows ) : ?>
					<div class="yadambiar-table-scroll"><table class="widefat yadambiar-forecast-table yadambiar-diag-table">
						<thead><tr><th><?php esc_html_e( 'مشتری', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'شرایط', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'نتیجه بررسی', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'کانال‌های قابل ارسال', 'yadam-biar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $r ) : $sub = $r['sub']; ?>
							<tr>
								<td><strong><?php echo esc_html( $sub->name ? $sub->name : '#' . $sub->id ); ?></strong><br><small class="yadambiar-muted" dir="ltr"><?php echo esc_html( trim( $sub->phone . ' ' . $sub->email ) ); ?></small></td>
								<td><?php echo esc_html( SubscriptionRepository::conditions_label( $sub ) ); ?><?php echo $sub->attributes ? '<br><small class="yadambiar-muted">' . esc_html( ProductAttributes::attributes_label( $sub->attributes ) ) . '</small>' : ''; ?></td>
								<td><span class="kf-conf <?php echo $r['ok'] ? 'is-high' : 'is-medium'; ?>"><?php echo esc_html( $r['ok'] ? __( 'آماده ارسال', 'yadam-biar' ) : __( 'ارسال نمی‌شود', 'yadam-biar' ) ); ?></span><br><small><?php echo esc_html( $r['reason'] ); ?></small></td>
								<td><?php echo $r['channels'] ? esc_html( implode( '، ', array_map( function ( $c ) { $all = Settings::channels(); return isset( $all[ $c ] ) ? $all[ $c ] : $c; }, $r['channels'] ) ) ) : '<span class="kf-dash">—</span>'; ?>
									<?php foreach ( $r['warnings'] as $w ) : ?><br><small class="yadambiar-warn">⚠ <?php echo esc_html( $w ); ?></small><?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php else : ?>
					<p><?php esc_html_e( 'این محصول درخواست فعالی ندارد.', 'yadam-biar' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
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
		$demand  = ReportRepository::attribute_demand( $pid );
		$funnel  = ReportRepository::type_funnel();
		$types   = SubscriptionRepository::types();
		$top     = ReportRepository::top_products( 30 );
		?>
		<div class="yadambiar-view">

			<form method="get" class="yadambiar-report-filter">
				<input type="hidden" name="page" value="yadam-biar"><input type="hidden" name="view" value="reports">
				<select name="product_id">
					<option value=""><?php esc_html_e( 'کل فروشگاه', 'yadam-biar' ); ?></option>
					<?php foreach ( $top as $row ) : $p = wc_get_product( $row->product_id ); if ( ! $p ) { continue; } ?>
						<option value="<?php echo esc_attr( $row->product_id ); ?>" <?php selected( $pid, (int) $row->product_id ); ?>><?php echo esc_html( $p->get_name() . ' (' . number_format_i18n( $row->people ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'نمایش', 'yadam-biar' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( $product ) : ?>
				<div class="yadambiar-card">
					<h2><?php echo esc_html( $product->get_name() ); ?></h2>
					<p>
						<?php
						/* translators: %s people */
						printf( esc_html__( 'تعداد منتظر: %s نفر', 'yadam-biar' ), '<strong>' . esc_html( number_format_i18n( SubscriptionRepository::waiting_count( $pid ) ) ) . '</strong>' );
						?>
					</p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'تنوع / ویژگی‌های درخواستی', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'درخواست', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'موجودی فعلی', 'yadam-biar' ); ?></th></tr></thead>
						<tbody>
						<?php
						foreach ( ReportRepository::variation_demand( $pid ) as $row ) :
							$v     = $row->variation_id ? wc_get_product( $row->variation_id ) : null;
							$label = $v ? wc_get_formatted_variation( $v, true, true ) : ( $row->attributes ? ProductAttributes::attributes_label( json_decode( $row->attributes, true ) ) : __( 'کل محصول / هر تنوعی', 'yadam-biar' ) );
							?>
							<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $row->c ) ); ?></td><td><?php echo $v ? esc_html( $v->is_in_stock() ? ( $v->managing_stock() ? number_format_i18n( $v->get_stock_quantity() ) : __( 'موجود', 'yadam-biar' ) ) : __( 'ناموجود', 'yadam-biar' ) ) : '—'; ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="yadambiar-grid">
				<div class="yadambiar-card">
					<h2><?php echo $product ? esc_html__( 'بیشترین ویژگی‌های درخواست‌شده این محصول', 'yadam-biar' ) : esc_html__( 'بیشترین ویژگی‌های درخواست‌شده در فروشگاه', 'yadam-biar' ); ?></h2>
					<?php if ( ! $demand ) : ?>
						<p><?php esc_html_e( 'داده‌ای موجود نیست.', 'yadam-biar' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $demand as $attr => $values ) : ?>
						<h3><?php echo esc_html( $attr ); ?></h3>
						<table class="widefat striped yadambiar-compact">
							<tbody>
							<?php foreach ( array_slice( $values, 0, 10, true ) as $value => $count ) : ?>
								<tr><td><?php echo esc_html( $value ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
				</div>

				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'قیف هر نوع اعلان', 'yadam-biar' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'نوع', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'در انتظار', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'اطلاع داده شد', 'yadam-biar' ); ?></th><th><?php esc_html_e( 'خرید', 'yadam-biar' ); ?></th></tr></thead>
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
		$table = new LogTable();
		$table->prepare_items();
		?>
		<div class="yadambiar-view">
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="yadam-biar"><input type="hidden" name="view" value="logs">
				<?php
				$table->search_box( __( 'جستجو', 'yadam-biar' ), 'yadambiar-log' );
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
		self::guard( 'yadambiar_retry' );
		$result = ChannelManager::retry( isset( $_GET['log'] ) ? absint( $_GET['log'] ) : 0 );
		$back   = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=yadambiar&view=logs' );
		if ( is_wp_error( $result ) ) {
			self::back( $back, $result->get_error_message(), 'error' );
		}
		self::back( $back, __( 'ارسال شد.', 'yadam-biar' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Settings page.
	 */
	public static function page_settings() {
		$schema   = Settings::schema();
		$tab      = isset( $_GET['tab'] ) && isset( $schema[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore
		$values   = Settings::all();
		$defaults = Settings::defaults();
		?>
		<div class="yadambiar-view">
			<nav class="nav-tab-wrapper yadambiar-tabs">
				<?php foreach ( $schema as $key => $section ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=yadambiar&view=settings&tab=' . $key ) ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( ! empty( $schema[ $tab ]['desc'] ) ) : ?>
				<p class="description yadambiar-tab-desc"><?php echo esc_html( $schema[ $tab ]['desc'] ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="yadambiar_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'yadambiar_save_settings' ); ?>
				<?php
				// Sections (heading) hold rows; a group inside a section is a collapsible panel.
				$yadambiar_sec   = false;
				$yadambiar_grp   = false;
				$yadambiar_tbl   = false;
				$yadambiar_close = function ( $level ) use ( &$yadambiar_sec, &$yadambiar_grp, &$yadambiar_tbl ) {
					if ( $yadambiar_tbl ) {
						echo '</table>';
						$yadambiar_tbl = false;
					}
					if ( $level >= 1 && $yadambiar_grp ) {
						echo '</div></details>';
						$yadambiar_grp = false;
					}
					if ( $level >= 2 && $yadambiar_sec ) {
						echo '</section>';
						$yadambiar_sec = false;
					}
				};
				foreach ( $schema[ $tab ]['fields'] as $key => $field ) {
					if ( 'heading' === $field['type'] ) {
						$yadambiar_close( 2 );
						echo '<section class="yadambiar-set-card"><header><h2>' . esc_html( $field['label'] ) . '</h2>' . ( ! empty( $field['desc'] ) ? '<p class="description">' . esc_html( $field['desc'] ) . '</p>' : '' ) . '</header>';
						$yadambiar_sec = true;
						continue;
					}
					if ( ! $yadambiar_sec ) {
						echo '<section class="yadambiar-set-card">';
						$yadambiar_sec = true;
					}
					if ( 'group' === $field['type'] ) {
						$yadambiar_close( 1 );
						self::group_open( $key, $field, $values, $schema[ $tab ]['fields'] );
						$yadambiar_grp = true;
						continue;
					}
					if ( ! $yadambiar_tbl ) {
						echo '<table class="form-table" role="presentation">';
						$yadambiar_tbl = true;
					}
					self::field( $key, $field, $values[ $key ] ?? '', $defaults[ $key ] ?? '' );
				}
				$yadambiar_close( 2 );
				?>
				<div class="yadambiar-savebar">
				<?php submit_button( __( 'ذخیره تنظیمات', 'yadam-biar' ) ); ?>
				</div>
			</form>

			<?php if ( 'channels' === $tab ) : ?>
				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'ارسال آزمایشی', 'yadam-biar' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yadambiar-inline-form">
						<input type="hidden" name="action" value="yadambiar_test">
						<?php wp_nonce_field( 'yadambiar_test' ); ?>
						<select name="channel">
							<option value="sms"><?php esc_html_e( 'پیامک', 'yadam-biar' ); ?></option>
							<option value="email"><?php esc_html_e( 'ایمیل', 'yadam-biar' ); ?></option>
							<option value="telegram"><?php esc_html_e( 'تلگرام (chat id یا @channel)', 'yadam-biar' ); ?></option>
							<option value="bale_safir"><?php esc_html_e( 'بله — شماره موبایل (سفیر)', 'yadam-biar' ); ?></option>
							<option value="bale"><?php esc_html_e( 'بله (chat id یا @channel)', 'yadam-biar' ); ?></option>
						</select>
						<select name="sms_kind" title="<?php esc_attr_e( 'نوع پیامک آزمایشی', 'yadam-biar' ); ?>">
							<option value="text"><?php esc_html_e( 'پیامک متنی', 'yadam-biar' ); ?></option>
							<option value="otp"><?php esc_html_e( 'پترن کد تایید (خط خدماتی)', 'yadam-biar' ); ?></option>
						</select>
						<input type="text" name="to" dir="ltr" placeholder="09xxxxxxxxx / email" required>
						<?php submit_button( __( 'ارسال تست', 'yadam-biar' ), 'secondary', '', false ); ?>
					</form>
					<?php self::sms_test_box(); ?>
					<?php $keys = PushChannel::keys(); ?>
					<div class="yadambiar-bots">
						<div class="yadambiar-bots-head">
							<div>
								<h2><?php esc_html_e( 'ربات‌های پیام‌رسان', 'yadam-biar' ); ?></h2>
								<p class="description"><?php esc_html_e( 'بعد از ذخیره توکن‌ها، وب‌هوک را ثبت کنید تا Start کاربران به سایت برسد.', 'yadam-biar' ); ?></p>
							</div>
							<div class="yadambiar-bots-actions">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="yadambiar_check_bots">
									<?php wp_nonce_field( 'yadambiar_check_bots' ); ?>
									<?php submit_button( __( 'بررسی ربات‌ها', 'yadam-biar' ), 'secondary small', '', false ); ?>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="yadambiar_set_webhooks">
									<?php wp_nonce_field( 'yadambiar_set_webhooks' ); ?>
									<?php submit_button( __( 'ثبت وب‌هوک ربات‌ها', 'yadam-biar' ), 'secondary small', '', false ); ?>
								</form>
							</div>
						</div>
						<ul class="yadambiar-kv">
							<?php
							foreach ( array( 'telegram' => __( 'تلگرام', 'yadam-biar' ), 'bale' => __( 'بله', 'yadam-biar' ) ) as $yadambiar_net => $yadambiar_label ) :
								$yadambiar_has = (bool) Settings::get( $yadambiar_net . '_token' );
								?>
								<li>
									<span class="yadambiar-kv-label"><?php echo esc_html( $yadambiar_label ); ?></span>
									<code dir="ltr" title="<?php esc_attr_e( 'آدرس وب‌هوک (کلید مخفی پنهان شده)', 'yadam-biar' ); ?>"><?php echo esc_html( rawurldecode( preg_replace( '#^https?://[^/]+#', '', rest_url( 'yadam-biar/v1/bot/' . $yadambiar_net ) ) ) ); // Never shows the secret. ?></code>
									<span class="yadambiar-kv-state<?php echo $yadambiar_has ? ' is-ok' : ''; ?>"><?php echo $yadambiar_has ? esc_html__( 'توکن ثبت شده', 'yadam-biar' ) : esc_html__( 'بدون توکن', 'yadam-biar' ); ?></span>
								</li>
							<?php endforeach; ?>
							<li>
								<span class="yadambiar-kv-label"><?php esc_html_e( 'کلید VAPID', 'yadam-biar' ); ?></span>
								<code dir="ltr" class="yadambiar-kv-copyable"><?php echo esc_html( $keys ? $keys['public'] : __( 'OpenSSL در دسترس نیست', 'yadam-biar' ) ); ?></code>
								<?php if ( $keys ) : ?>
									<button type="button" class="button-link yadambiar-copy" data-copy="<?php echo esc_attr( $keys['public'] ); ?>" data-done="<?php esc_attr_e( 'کپی شد', 'yadam-biar' ); ?>"><?php esc_html_e( 'کپی', 'yadam-biar' ); ?></button>
								<?php endif; ?>
							</li>
						</ul>
						<?php
						$yadambiar_check = get_transient( 'yadambiar_botcheck_' . get_current_user_id() );
						if ( is_array( $yadambiar_check ) && $yadambiar_check ) :
							delete_transient( 'yadambiar_botcheck_' . get_current_user_id() );
							foreach ( $yadambiar_check as $yadambiar_net => $yadambiar_rows ) :
								?>
								<h3 class="yadambiar-bots-check-title"><?php echo esc_html( Settings::channels()[ $yadambiar_net ] ); ?></h3>
								<ul class="yadambiar-health">
									<?php foreach ( $yadambiar_rows as $yadambiar_row ) : ?>
										<li class="is-<?php echo esc_attr( $yadambiar_row[0] ); ?>"><?php echo esc_html( $yadambiar_row[1] ); ?></li>
									<?php endforeach; ?>
								</ul>
								<?php
							endforeach;
						endif;
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( 'general' === $tab ) : ?>
				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'شورت‌کدها', 'yadam-biar' ); ?></h2>
					<ul>
						<li><code>[yadambiar]</code> / <code>[yadambiar product_id="123"]</code> — <?php esc_html_e( 'دکمه و فرم یادم بیار', 'yadam-biar' ); ?></li>
						<li><code>[yadambiar_my_alerts]</code> — <?php esc_html_e( 'پنل «یادآوری‌های من» (برای مهمان‌ها با لینک ارسالی)', 'yadam-biar' ); ?></li>
						<li><code>[yadambiar_bell]</code> — <?php esc_html_e( 'زنگوله اعلان‌های داخل سایت', 'yadam-biar' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Repeater UI for per-event SMS patterns.
	 *
	 * @param string $name  Field name prefix.
	 * @param array  $items Saved items.
	 */
	private static function pattern_field( $name, $items ) {
		$events = Settings::events();
		$chips  = array(
			'customer_name' => __( 'نام مشتری', 'yadam-biar' ),
			'product_name'  => __( 'نام محصول', 'yadam-biar' ),
			'variation'     => __( 'تنوع', 'yadam-biar' ),
			'price'         => __( 'قیمت', 'yadam-biar' ),
			'old_price'     => __( 'قیمت قبلی', 'yadam-biar' ),
			'target_price'  => __( 'قیمت هدف', 'yadam-biar' ),
			'stock'         => __( 'موجودی', 'yadam-biar' ),
			'link'          => __( 'لینک', 'yadam-biar' ),
			'site_name'     => __( 'نام سایت', 'yadam-biar' ),
			'code'          => __( 'کد تایید', 'yadam-biar' ),
			'coupon'        => __( 'کد تخفیف', 'yadam-biar' ),
		);
		$hints = array(
			'kavenegar'   => __( 'کاوه‌نگار: «کد پترن» همان نام قالب در پنل است. نام متغیرها را token، token2، token3 (و token10، token20) بگذارید؛ مقدار آن‌ها فاصله نمی‌پذیرد و خودکار اصلاح می‌شود.', 'yadam-biar' ),
			'payamak_panel' => __( 'ملی پیامک و پنل‌های سازگار: «کد پترن» همان bodyId است. مقدارها به ترتیب نوشتن و با ; ارسال می‌شوند؛ نام متغیر فقط برای شما است.', 'yadam-biar' ),
			'ippanel'     => __( 'فراز / IPPanel: «کد پترن» را وارد کنید و نام متغیرها را دقیقاً مثل نام‌های تعریف‌شده در پترن بنویسید.', 'yadam-biar' ),
			'smsir'       => __( 'SMS.ir: «کد پترن» شناسه‌ی عددی قالب است و نام پارامترها دقیقاً همان نام‌های قالب است (مثلاً Code).', 'yadam-biar' ),
			'ghasedak'    => __( 'قاصدک: «کد پترن» نام قالب (templateName) در پنل است و نام متغیرها همان param های قالب.', 'yadam-biar' ),
			'iranpayamak' => __( 'ایران پیامک: «کد پترن» کد الگو است و نام متغیرها همان نام‌های تعریف‌شده در الگو.', 'yadam-biar' ),
			'webhook'     => __( 'وب‌سرویس سفارشی از پترن استفاده نمی‌کند؛ از «قالب پیام» متنی استفاده می‌شود.', 'yadam-biar' ),
		);
		$row = function ( $i, $item ) use ( $name, $events, $chips ) {
			$base = $name . '[' . $i . ']';
			ob_start();
			?>
			<div class="yadambiar-pat-row">
				<div class="yadambiar-pat-head">
					<label class="yadambiar-pat-f">
						<span><?php esc_html_e( 'نوع رویداد', 'yadam-biar' ); ?></span>
						<select name="<?php echo esc_attr( $base ); ?>[event]" class="yadambiar-pat-event">
							<?php foreach ( $events as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $item['event'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="yadambiar-pat-f yadambiar-pat-code">
						<span><?php esc_html_e( 'کد / نام پترن', 'yadam-biar' ); ?></span>
						<input type="text" dir="ltr" name="<?php echo esc_attr( $base ); ?>[code]" value="<?php echo esc_attr( $item['code'] ); ?>" placeholder="123456">
					</label>
					<button type="button" class="yadambiar-pat-del" title="<?php esc_attr_e( 'حذف این پترن', 'yadam-biar' ); ?>" aria-label="<?php esc_attr_e( 'حذف این پترن', 'yadam-biar' ); ?>">×</button>
				</div>
				<div class="yadambiar-pat-vars" data-base="<?php echo esc_attr( $base ); ?>">
					<div class="yadambiar-pat-vhead"><strong><?php esc_html_e( 'متغیرهای این پترن', 'yadam-biar' ); ?></strong><small><?php esc_html_e( 'نام متغیر در سامانه پیامک ← مقداری که ارسال می‌شود', 'yadam-biar' ); ?></small></div>
					<div class="yadambiar-pat-vlist">
						<?php foreach ( array_values( (array) $item['vars'] ) as $j => $var ) : ?>
							<div class="yadambiar-pat-var">
								<input type="text" dir="ltr" class="yadambiar-pat-vname" name="<?php echo esc_attr( $base ); ?>[vars][<?php echo (int) $j; ?>][name]" value="<?php echo esc_attr( $var['name'] ); ?>" placeholder="token">
								<span class="yadambiar-pat-arrow">←</span>
								<input type="text" dir="auto" class="yadambiar-pat-vval" name="<?php echo esc_attr( $base ); ?>[vars][<?php echo (int) $j; ?>][value]" value="<?php echo esc_attr( $var['value'] ); ?>" placeholder="{product_name}">
								<button type="button" class="yadambiar-pat-vdel" aria-label="<?php esc_attr_e( 'حذف متغیر', 'yadam-biar' ); ?>">×</button>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="yadambiar-pat-chips" aria-label="<?php esc_attr_e( 'درج متغیر', 'yadam-biar' ); ?>">
						<?php foreach ( $chips as $key => $label ) : ?>
							<button type="button" class="yadambiar-chip" data-token="{<?php echo esc_attr( $key ); ?>}" title="{<?php echo esc_attr( $key ); ?>}"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button yadambiar-pat-addvar">+ <?php esc_html_e( 'افزودن متغیر', 'yadam-biar' ); ?></button>
				</div>
			</div>
			<?php
			return ob_get_clean();
		};
		?>
		<?php
		$drivers = array();
		foreach ( ProviderRegistry::all() as $pkey => $p ) {
			$drivers[ $pkey ] = $p[1];
		}
		?>
		<div class="yadambiar-pat" data-next="<?php echo (int) count( $items ) + 100; ?>" data-hints="<?php echo esc_attr( wp_json_encode( $hints ) ); ?>" data-drivers="<?php echo esc_attr( wp_json_encode( $drivers ) ); ?>" data-pattern-drivers="<?php echo esc_attr( wp_json_encode( ProviderRegistry::PATTERN_DRIVERS ) ); ?>">
			<div class="yadambiar-pat-info">
				<strong><?php esc_html_e( 'چطور کار می‌کند؟', 'yadam-biar' ); ?></strong>
				<ol>
					<li><?php esc_html_e( 'برای هر رویداد یک پترن بسازید: «افزودن پترن» را بزنید و نوع رویداد (مثلاً موجود شدن، کاهش قیمت، کد تایید) را انتخاب کنید.', 'yadam-biar' ); ?></li>
					<li><?php esc_html_e( 'کد پترن تاییدشده‌ی همان رویداد را از سامانه‌ی پیامک وارد کنید.', 'yadam-biar' ); ?></li>
					<li><?php esc_html_e( 'متغیرهای پترن را مشخص کنید. روی دکمه‌های رنگی (نام محصول، قیمت، لینک…) بزنید تا در مقدار انتخاب‌شده درج شود.', 'yadam-biar' ); ?></li>
				</ol>
				<p class="yadambiar-pat-gw" hidden></p>
				<p class="yadambiar-pat-note"><?php esc_html_e( 'رویدادی که پترن ندارد با پیامک متنی (قالب پیام) ارسال می‌شود.', 'yadam-biar' ); ?></p>
			</div>
			<div class="yadambiar-pat-list">
				<?php
				$i = 0;
				foreach ( $items as $item ) {
					echo $row( $i++, $item ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
			<p class="yadambiar-pat-empty" <?php echo $items ? 'hidden' : ''; ?>><?php esc_html_e( 'هنوز پترنی ثبت نشده است.', 'yadam-biar' ); ?></p>
			<button type="button" class="button button-primary yadambiar-pat-add">+ <?php esc_html_e( 'افزودن پترن', 'yadam-biar' ); ?></button>
			<template class="yadambiar-pat-tpl"><?php echo $row( '__i__', array( 'event' => 'back_in_stock', 'code' => '', 'vars' => array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		</div>
		<?php
	}

	/**
	 * Is a settings row shown for the current values (its show_if rules, and those of the fields they depend on)?
	 *
	 * @param string $key    Field key.
	 * @param array  $fields Fields of the tab.
	 * @param array  $values Current settings.
	 * @param int    $depth  Recursion guard.
	 * @return bool
	 */
	private static function field_visible( $key, $fields, $values, $depth = 0 ) {
		if ( empty( $fields[ $key ]['show_if'] ) || $depth > 5 ) {
			return true;
		}
		$rules = $fields[ $key ]['show_if'];
		$sets  = isset( $rules[0] ) ? $rules : array( $rules );
		foreach ( $sets as $set ) {
			$ok = true;
			foreach ( $set as $dep => $allowed ) {
				$value = isset( $values[ $dep ] ) ? (string) $values[ $dep ] : '';
				if ( isset( $fields[ $dep ] ) && ( ! in_array( $value, (array) $allowed, true ) || ! self::field_visible( $dep, $fields, $values, $depth + 1 ) ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Open a collapsible settings group with its status badge.
	 *
	 * Group keys: label, desc, icon, show_if, and either ready (fields that must be filled; only rows
	 * shown for the current gateway/provider count) with on_label/off_label, or status (a select whose
	 * chosen option is shown as the badge).
	 *
	 * @param string $key    Group key.
	 * @param array  $field  Group definition.
	 * @param array  $values Current settings.
	 * @param array  $fields All fields of the tab.
	 */
	private static function group_open( $key, $field, $values, $fields ) {
		// Rows of this group: everything up to the next group or heading.
		$members = array();
		$inside  = false;
		foreach ( $fields as $k => $f ) {
			if ( $k === $key ) {
				$inside = true;
				continue;
			}
			if ( $inside && in_array( $f['type'], array( 'group', 'heading' ), true ) ) {
				break;
			}
			if ( $inside ) {
				$members[] = $k;
			}
		}
		$shown = self::field_visible( $key, array( $key => $field ) + $fields, $values );
		$shown = $shown && array_filter(
			$members,
			function ( $k ) use ( $fields, $values ) {
				return self::field_visible( $k, $fields, $values );
			}
		);

		$labels = array(
			'on'      => isset( $field['on_label'] ) ? $field['on_label'] : __( 'تنظیم شده', 'yadam-biar' ),
			'partial' => __( 'ناقص', 'yadam-biar' ),
			'off'     => isset( $field['off_label'] ) ? $field['off_label'] : __( 'تنظیم نشده', 'yadam-biar' ),
		);
		if ( ! empty( $field['status'] ) ) {
			$sel   = $fields[ $field['status'] ];
			$val   = isset( $values[ $field['status'] ] ) ? $values[ $field['status'] ] : '';
			$state = 'info';
			$text  = isset( $sel['options'][ $val ] ) ? $sel['options'][ $val ] : '';
		} else {
			$ready = array_values(
				array_filter(
					isset( $field['ready'] ) ? (array) $field['ready'] : array(),
					function ( $k ) use ( $fields, $values ) {
						return self::field_visible( $k, $fields, $values );
					}
				)
			);
			$filled = count(
				array_filter(
					$ready,
					function ( $k ) use ( $values ) {
						return isset( $values[ $k ] ) && array() !== $values[ $k ] && '' !== $values[ $k ];
					}
				)
			);
			// No required field applies to the chosen gateway/provider: show no badge rather than a wrong one.
			$state = ! $ready ? 'none' : ( ! $filled ? 'off' : ( count( $ready ) === $filled ? 'on' : 'partial' ) );
			$text  = isset( $labels[ $state ] ) ? $labels[ $state ] : '';
		}
		printf(
			'<details class="yadambiar-acc" id="yadambiar-acc-%1$s" data-ready="%2$s" data-labels="%3$s" data-status="%4$s"%5$s%6$s><summary><span class="yadambiar-acc-icon">%7$s</span><span class="yadambiar-acc-title"><b>%8$s</b>%9$s</span><span class="yadambiar-acc-badge is-%10$s">%11$s</span><span class="yadambiar-acc-chev" aria-hidden="true"></span></summary><div class="yadambiar-acc-body">',
			esc_attr( trim( $key, '_' ) ),
			esc_attr( wp_json_encode( isset( $field['ready'] ) ? (array) $field['ready'] : array() ) ),
			esc_attr( wp_json_encode( $labels ) ),
			esc_attr( isset( $field['status'] ) ? $field['status'] : '' ),
			! empty( $field['show_if'] ) ? ' data-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : '',
			$shown ? '' : ' style="display:none"',
			ProductWidget::icon( isset( $field['icon'] ) ? $field['icon'] : 'gear' ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $field['label'] ),
			! empty( $field['desc'] ) ? '<small>' . esc_html( $field['desc'] ) . '</small>' : '',
			esc_attr( $state ),
			esc_html( $text )
		);
	}

	/**
	 * Multicheck as selectable cards (icon, label and an optional hint read from another setting).
	 *
	 * @param string $name  Input name.
	 * @param array  $field Field: options, icons, hints (option => setting key).
	 * @param array  $value Checked options.
	 */
	private static function multicheck_cards( $name, $field, $value ) {
		echo '<div class="yadambiar-cards">';
		foreach ( $field['options'] as $opt => $label ) {
			$hint_key = isset( $field['hints'][ $opt ] ) ? $field['hints'][ $opt ] : '';
			$hint     = $hint_key ? trim( (string) Settings::get( $hint_key ) ) : '';
			printf(
				'<label class="yadambiar-cardopt"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s><span class="yadambiar-cardopt-box"><span class="yadambiar-cardopt-icon">%4$s</span><span class="yadambiar-cardopt-text"><b>%5$s</b>%6$s</span><span class="yadambiar-cardopt-tick" aria-hidden="true"></span></span></label>',
				esc_attr( $name ),
				esc_attr( $opt ),
				checked( in_array( $opt, $value, true ), true, false ),
				ProductWidget::icon( isset( $field['icons'][ $opt ] ) ? $field['icons'][ $opt ] : 'bell' ), // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html( $label ),
				$hint_key ? sprintf(
					'<small class="yadambiar-cardopt-hint%1$s" data-hint-from="%2$s" data-empty="%3$s" dir="auto">%4$s</small>',
					$hint ? '' : ' is-missing',
					esc_attr( $hint_key ),
					esc_attr__( 'شناسه کانال وارد نشده', 'yadam-biar' ),
					esc_html( $hint ? $hint : __( 'شناسه کانال وارد نشده', 'yadam-biar' ) )
				) : ''
			);
		}
		echo '</div>';
	}

	/**
	 * Help: a short, plain guide to each part of the plugin with links to the matching settings.
	 */
	public static function page_help() {
		$set = function ( $tab ) {
			return admin_url( 'admin.php?page=yadambiar&view=settings&tab=' . $tab );
		};
		$steps = array(
			array( __( 'کانال ارسال را وصل کنید', 'yadam-biar' ), __( 'تنظیمات ← کانال‌های ارسال: سامانه پیامک را تنظیم و «کانال‌های فعال» را تیک بزنید.', 'yadam-biar' ) ),
			array( __( 'ارسال آزمایشی بگیرید', 'yadam-biar' ), __( 'پایین همان صفحه، یک پیام تست به شماره خودتان بفرستید.', 'yadam-biar' ) ),
			array( __( 'دکمه را در صفحه محصول ببینید', 'yadam-biar' ), __( 'روی کالای ناموجود، دکمه «یادم بیار» خودکار نمایش داده می‌شود.', 'yadam-biar' ) ),
			array( __( 'بقیه‌اش خودکار است', 'yadam-biar' ), __( 'وقتی موجودی یا قیمت تغییر کند، به منتظران خبر داده می‌شود.', 'yadam-biar' ) ),
		);
		$topics = array(
			array(
				'icon'  => 'bell',
				'title' => __( 'یادم بیار چطور کار می‌کند؟', 'yadam-biar' ),
				'items' => array(
					__( 'مشتری روی «یادم بیار» می‌زند، تنوع (سایز/رنگ) و شرط را انتخاب می‌کند و موبایل یا ایمیلش را می‌دهد.', 'yadam-biar' ),
					__( 'شرط‌ها: موجود شدن، کمتر شدن قیمت از عدد دلخواه، بیشتر شدن قیمت، هر تغییر قیمت، یا حداقل موجودی.', 'yadam-biar' ),
					__( 'با تغییر موجودی یا قیمت (دستی، ایمپورت یا هر روش دیگر)، درخواست‌ها بررسی و پیام ارسال می‌شود.', 'yadam-biar' ),
					__( 'به هر درخواست فقط یک بار خبر داده می‌شود؛ «هر تغییر قیمت» تا وقتی لغو نشود ادامه دارد.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'تنظیمات عمومی', 'yadam-biar' ), $set( 'general' ) ) ),
			),
			array(
				'icon'  => 'send',
				'title' => __( 'کانال‌های ارسال', 'yadam-biar' ),
				'items' => array(
					__( 'پیامک: سامانه و کلید را وارد کنید. برای خط خدماتی، «نوع ارسال» را روی پترن بگذارید و کد پترن هر رویداد را اضافه کنید.', 'yadam-biar' ),
					__( 'ایمیل و اعلان داخل سایت: بدون تنظیم کار می‌کنند.', 'yadam-biar' ),
					__( 'پوش: سایت باید HTTPS باشد؛ فقط آیکون را انتخاب کنید.', 'yadam-biar' ),
					__( 'بله: با «سفیر» پیام مستقیم به شماره موبایل مشتری می‌رود. بدون سفیر، مشتری باید ربات را Start کند.', 'yadam-biar' ),
					__( 'تلگرام: توکن و نام ربات را بدهید و «ثبت وب‌هوک ربات‌ها» را بزنید؛ مشتری با دکمه «دریافت در تلگرام» وصل می‌شود.', 'yadam-biar' ),
					__( 'اعلام در کانال: موجود شدن کالاهای پرتقاضا در کانال تلگرام/بله پست می‌شود (ربات باید ادمین کانال باشد).', 'yadam-biar' ),
				),
				'links' => array( array( __( 'کانال‌های ارسال', 'yadam-biar' ), $set( 'channels' ) ) ),
			),
			array(
				'icon'  => 'chat',
				'title' => __( 'قالب پیام‌ها', 'yadam-biar' ),
				'items' => array(
					__( 'برای هر رویداد یک متن کوتاه (پیامک و پیام‌رسان)، یک عنوان و یک متن ایمیل دارید.', 'yadam-biar' ),
					__( 'متغیرها مثل {product_name}، {price} و {link} موقع ارسال با مقدار واقعی جایگزین می‌شوند.', 'yadam-biar' ),
					__( 'هر وقت خواستید با «بازگردانی پیش‌فرض» به متن اولیه برگردید.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'قالب پیام‌ها', 'yadam-biar' ), $set( 'templates' ) ) ),
			),
			array(
				'icon'  => 'trend',
				'title' => __( 'رشد فروش', 'yadam-biar' ),
				'items' => array(
					__( 'کد تخفیف خودکار: برای هر منتظر یک کد یکبار مصرف و محدود به همان کالا ساخته و در پیام گذاشته می‌شود.', 'yadam-biar' ),
					__( 'محصول جایگزین: اگر کسی مدت زیادی منتظر مانده، محصولات مشابهِ موجود به او پیشنهاد می‌شود.', 'yadam-biar' ),
					__( 'تاریخچه قیمت: نمودار تغییر قیمت در صفحه محصول.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'رشد فروش', 'yadam-biar' ), $set( 'growth' ) ) ),
			),
			array(
				'icon'  => 'grid',
				'title' => __( 'داشبورد، درخواست‌ها و گزارش‌ها', 'yadam-biar' ),
				'items' => array(
					__( 'داشبورد: تعداد منتظران، پیام‌های ارسالی، کلیک، خرید و درآمد حاصل از اعلان‌ها.', 'yadam-biar' ),
					__( 'درخواست‌ها: همه درخواست‌ها با جستجو، فیلتر، لغو/حذف گروهی و خروجی اکسل.', 'yadam-biar' ),
					__( 'گزارش‌ها: پرتقاضاترین کالاها و سایز/رنگ‌ها.', 'yadam-biar' ),
					__( 'لاگ ارسال: نتیجه هر پیام؛ پیام ناموفق را می‌توانید دوباره بفرستید.', 'yadam-biar' ),
				),
				'links' => array(
					array( __( 'داشبورد', 'yadam-biar' ), admin_url( 'admin.php?page=yadambiar' ) ),
					array( __( 'لاگ ارسال', 'yadam-biar' ), admin_url( 'admin.php?page=yadambiar&view=logs' ) ),
				),
			),
			array(
				'icon'  => 'box',
				'title' => __( 'پیش‌بینی تقاضا', 'yadam-biar' ),
				'items' => array(
					__( 'برای هر کالای ناموجود می‌گوید چند عدد سفارش بدهید، بر اساس تعداد منتظران و فروش قبلی.', 'yadam-biar' ),
					__( 'زمان تحویل تامین‌کننده و تعداد روزهای پوشش را تغییر دهید تا عدد پیشنهادی به‌روز شود.', 'yadam-biar' ),
					__( 'خروجی CSV آن را مستقیم برای تامین‌کننده بفرستید.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'پیش‌بینی تقاضا', 'yadam-biar' ), admin_url( 'admin.php?page=yadambiar&view=forecast' ) ) ),
			),
			array(
				'icon'  => 'gear',
				'title' => __( 'پیشرفته (اختیاری)', 'yadam-biar' ),
				'items' => array(
					__( 'ارسال موجی: وقتی موجودی کم است، اول به همان تعداد نفر اول خبر داده می‌شود، نه به همه.', 'yadam-biar' ),
					__( 'رزرو زودتر از دیگران: منتظران چند دقیقه فرصت خرید اختصاصی دارند.', 'yadam-biar' ),
					__( 'تایید با کد: شماره/ایمیل مهمان با کد یکبار مصرف تایید می‌شود تا شماره دیگران ثبت نشود.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'پیشرفته', 'yadam-biar' ), $set( 'advanced' ) ) ),
			),
			array(
				'icon'  => 'cube',
				'title' => __( 'نمایش در سایت', 'yadam-biar' ),
				'items' => array(
					__( 'جای دکمه در صفحه محصول را از «تنظیمات عمومی ← محل نمایش» عوض کنید. رنگ‌ها در «ظاهر و رنگ‌ها» است.', 'yadam-biar' ),
					__( 'شورت‌کدها: [yadambiar] دکمه، [yadambiar_my_alerts] پنل «یادآوری‌های من»، [yadambiar_bell] زنگوله اعلان‌ها، [yadambiar_price_history] نمودار قیمت.', 'yadam-biar' ),
					__( 'در المنتور، ویجت‌ها در دسته «یادم بیار» هستند. اگر صفحه محصول را با المنتور ساخته‌اید، محل نمایش را «فقط با شورت‌کد» بگذارید.', 'yadam-biar' ),
					__( 'مشتری درخواست‌هایش را در «حساب کاربری ← یادآوری‌های من» می‌بیند و ویرایش می‌کند.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'ظاهر و رنگ‌ها', 'yadam-biar' ), $set( 'appearance' ) ) ),
			),
			array(
				'icon'  => 'help',
				'title' => __( 'پیام نرسید؟', 'yadam-biar' ),
				'items' => array(
					__( 'لاگ ارسال را ببینید؛ علت خطا کنار هر پیام ناموفق نوشته شده است.', 'yadam-biar' ),
					__( 'ارسال آزمایشی بگیرید تا مطمئن شوید سامانه پیامک یا ربات درست تنظیم شده است.', 'yadam-biar' ),
					__( 'برای تلگرام و بله، دکمه «بررسی ربات‌ها» را بزنید.', 'yadam-biar' ),
					__( 'در داشبورد کنار هر محصول دکمه «عیب‌یابی» را بزنید تا علت ارسال نشدن هر درخواست را ببینید.', 'yadam-biar' ),
				),
				'links' => array( array( __( 'لاگ ارسال', 'yadam-biar' ), admin_url( 'admin.php?page=yadambiar&view=logs' ) ) ),
			),
		);
		?>
		<div class="yadambiar-view yadambiar-help">
			<section class="yadambiar-set-card">
				<header><h2><?php esc_html_e( 'شروع سریع', 'yadam-biar' ); ?></h2></header>
				<ol class="yadambiar-steps">
					<?php foreach ( $steps as $i => $step ) : ?>
						<li><span class="yadambiar-step-n"><?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></span><div><b><?php echo esc_html( $step[0] ); ?></b><small><?php echo esc_html( $step[1] ); ?></small></div></li>
					<?php endforeach; ?>
				</ol>
			</section>
			<section class="yadambiar-set-card">
				<header><h2><?php esc_html_e( 'بخش‌ها', 'yadam-biar' ); ?></h2></header>
				<?php foreach ( $topics as $i => $topic ) : ?>
					<details class="yadambiar-acc" id="yadambiar-acc-help-<?php echo esc_attr( $i ); ?>">
						<summary><span class="yadambiar-acc-icon"><?php echo ProductWidget::icon( $topic['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="yadambiar-acc-title"><b><?php echo esc_html( $topic['title'] ); ?></b></span><span class="yadambiar-acc-chev" aria-hidden="true"></span></summary>
						<div class="yadambiar-acc-body">
							<ul class="yadambiar-help-list">
								<?php foreach ( $topic['items'] as $item ) : ?>
									<li><?php echo esc_html( $item ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p class="yadambiar-help-links">
								<?php foreach ( $topic['links'] as $link ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $link[1] ); ?>"><?php echo esc_html( $link[0] ); ?> ←</a>
								<?php endforeach; ?>
							</p>
						</div>
					</details>
				<?php endforeach; ?>
			</section>
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
		$name = 'yadambiar[' . $key . ']';
		$id   = 'yadambiar-' . $key;
		if ( 'heading' === $field['type'] ) {
			echo '<tr><th colspan="2"><h2 class="yadambiar-heading">' . esc_html( $field['label'] ) . '</h2>' . ( ! empty( $field['desc'] ) ? '<p class="description">' . esc_html( $field['desc'] ) . '</p>' : '' ) . '</th></tr>';
			return;
		}
		$attrs = ! empty( $field['show_if'] ) ? ' data-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"' : '';
		$attrs .= 'sms_patterns' === $field['type'] ? ' class="yadambiar-row-wide"' : '';
		if ( 'sms_patterns' === $field['type'] ) {
			echo '<tr' . $attrs . '><td colspan="2">';
		} else {
			echo '<tr' . $attrs . '><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		}
		switch ( $field['type'] ) {
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', esc_attr( $id ), esc_attr( $name ), checked( (int) $value, 1, false ), esc_html__( 'فعال', 'yadam-biar' ) );
				break;
			case 'multicheck':
				if ( isset( $field['style'] ) && 'cards' === $field['style'] ) {
					self::multicheck_cards( $name, $field, (array) $value );
					break;
				}
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<label class="yadambiar-check"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>', esc_attr( $name ), esc_attr( $opt ), checked( in_array( $opt, (array) $value, true ), true, false ), esc_html( $label ) );
				}
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $opt => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'media':
				$preview = $value ? PushChannel::icon_url( $value, 96 ) : '';
				printf(
					'<div class="yadambiar-media" data-title="%1$s" data-button="%2$s"><span class="yadambiar-media-preview%3$s" data-empty="%11$s">%4$s</span><div class="yadambiar-media-main"><input type="text" class="regular-text" dir="ltr" id="%5$s" name="%6$s" value="%7$s" placeholder="https://…"><div class="yadambiar-media-actions"><button type="button" class="button yadambiar-media-pick">%8$s</button><button type="button" class="button-link yadambiar-media-clear"%9$s>%10$s</button></div></div></div>',
					esc_attr( $field['label'] ),
					esc_attr__( 'استفاده به‌عنوان آیکون', 'yadam-biar' ),
					$preview ? '' : ' is-empty',
					$preview ? '<img src="' . esc_url( $preview ) . '" alt="">' : ProductWidget::icon( 'bell' ), // phpcs:ignore WordPress.Security.EscapeOutput
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( 'انتخاب از کتابخانه رسانه', 'yadam-biar' ),
					$value ? '' : ' hidden',
					esc_html__( 'حذف', 'yadam-biar' ),
					esc_attr( ProductWidget::icon( 'bell' ) )
				);
				break;
			case 'number':
				$input = sprintf( '<input type="number" min="0" class="small-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				if ( ! empty( $field['suffix'] ) ) {
					$input = '<span class="yadambiar-num-wrap">' . $input . '<span class="yadambiar-num-suffix">' . esc_html( $field['suffix'] ) . '</span></span>';
				}
				echo $input; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'textarea':
			case 'html':
				printf( '<textarea id="%s" name="%s" rows="%d" class="large-text" %s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), 'html' === $field['type'] ? 5 : 3, 'html' === $field['type'] ? 'dir="auto"' : ( ! empty( $field['dir'] ) ? 'dir="' . esc_attr( $field['dir'] ) . '"' : '' ), esc_textarea( $value ) );
				if ( $default && $default !== $value ) {
					printf( ' <button type="button" class="button-link yadambiar-reset" data-target="%s" data-default="%s">%s</button>', esc_attr( $id ), esc_attr( $default ), esc_html__( 'بازگردانی پیش‌فرض', 'yadam-biar' ) );
				}
				break;
			case 'sms_patterns':
				self::pattern_field( $name, Settings::pattern_items() );
				break;
			case 'color':
				printf( '<input type="text" class="yadambiar-color" id="%s" name="%s" value="%s" data-default-color="%s" dir="ltr">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $default ) );
				break;
			case 'password':
				printf( '<input type="password" autocomplete="new-password" class="regular-text" dir="ltr" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			default:
				printf( '<input type="text" class="regular-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}
		if ( ! empty( $field['desc_by'] ) ) {
			// Hint that depends on another setting (e.g. per provider): {setting: {value: text}}; JS swaps it live.
			$dep  = key( $field['desc_by'] );
			$map  = current( $field['desc_by'] );
			$cur  = (string) Settings::get( $dep );
			$text = isset( $map[ $cur ] ) ? $map[ $cur ] : ( ! empty( $field['desc'] ) ? $field['desc'] : '' );
			printf( '<p class="description" data-desc-by="%s" data-desc-default="%s">%s</p>', esc_attr( wp_json_encode( $field['desc_by'] ) ), esc_attr( ! empty( $field['desc'] ) ? $field['desc'] : '' ), esc_html( $text ) );
		} elseif ( ! empty( $field['desc'] ) ) {
			echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Save settings.
	 */
	public static function save_settings() {
		self::guard( 'yadambiar_save_settings' );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'general';
		Settings::save( isset( $_POST['yadam-biar'] ) ? (array) $_POST['yadam-biar'] : array(), $tab ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( Settings::has( 'channels_enabled', 'push' ) ) {
			PushChannel::keys();
		}
		self::back( admin_url( 'admin.php?page=yadambiar&view=settings&tab=' . $tab ), __( 'تنظیمات ذخیره شد.', 'yadam-biar' ) );
	}

	/**
	 * Check the bots against the messengers' APIs and show the result in the bots box.
	 */
	public static function check_bots() {
		self::guard( 'yadambiar_check_bots' );
		$out = array();
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			if ( Settings::get( $network . '_token' ) || Settings::has( 'channels_enabled', $network ) ) {
				$out[ $network ] = MessengerChannel::check( $network );
			}
		}
		set_transient( 'yadambiar_botcheck_' . get_current_user_id(), $out, 10 * MINUTE_IN_SECONDS );
		self::back( admin_url( 'admin.php?page=yadambiar&view=settings&tab=channels' ), $out ? __( 'بررسی ربات‌ها انجام شد؛ نتیجه در بخش «ربات‌های پیام‌رسان» است.', 'yadam-biar' ) : __( 'هیچ رباتی تنظیم نشده است.', 'yadam-biar' ), $out ? 'success' : 'error' );
	}

	/**
	 * Register Telegram / Bale webhooks.
	 */
	public static function set_webhooks() {
		self::guard( 'yadambiar_set_webhooks' );
		$back    = admin_url( 'admin.php?page=yadambiar&view=settings&tab=channels' );
		$results = MessengerChannel::register_webhooks();
		if ( ! $results ) {
			self::back( $back, __( 'هیچ توکن رباتی وارد نشده است.', 'yadam-biar' ), 'error' );
		}
		$msgs  = array();
		$error = false;
		foreach ( $results as $network => $result ) {
			$label  = Settings::channels()[ $network ];
			$error  = $error || is_wp_error( $result );
			$msgs[] = $label . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : __( 'وب‌هوک ثبت شد', 'yadam-biar' ) );
		}
		self::back( $back, implode( ' | ', $msgs ), $error ? 'error' : 'success' );
	}

	/**
	 * Test send.
	 */
	public static function test_send() {
		self::guard( 'yadambiar_test' );
		$channel = isset( $_POST['channel'] ) ? sanitize_key( $_POST['channel'] ) : 'sms';
		$to      = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		/* translators: %s site */
		$text = sprintf( __( 'پیام آزمایشی یادم بیار از %s', 'yadam-biar' ), get_bloginfo( 'name' ) );
		switch ( $channel ) {
			case 'email':
				$result = EmailChannel::send( $to, $text, '<p>' . esc_html( $text ) . '</p>' );
				break;
			case 'bale_safir':
				$result = MessengerChannel::send_safir( $to, array( 'subject' => '', 'text' => $text, 'url' => home_url( '/' ) ) );
				break;
			case 'telegram':
			case 'bale':
				$result = MessengerChannel::send( $channel, MessengerChannel::normalize_chat( $to ), $text );
				break;
			default:
				$kind = isset( $_POST['sms_kind'] ) && 'otp' === $_POST['sms_kind'] ? 'otp' : 'text';
				if ( 'otp' === $kind ) {
					$items = Settings::pattern_items();
					if ( empty( $items['otp'] ) ) {
						self::back( admin_url( 'admin.php?page=yadambiar&view=settings&tab=channels' ), __( 'برای تست پترن، ابتدا در بخش «پترن‌های پیامک» یک پترن برای رویداد «کد تایید» ثبت و ذخیره کنید.', 'yadam-biar' ), 'error' );
					}
					$result = SmsChannel::send( $to, '', 'otp', array( 'code' => '12345', 'site_name' => get_bloginfo( 'name' ) ), true );
				} else {
					$result = SmsChannel::send( $to, $text, 'text' );
				}
				set_transient(
					'yadambiar_sms_test_' . get_current_user_id(),
					array(
						'to'       => $to,
						'kind'     => $kind,
						'time'     => time(),
						'ok'       => true === $result,
						'error'    => is_wp_error( $result ) ? $result->get_error_message() : '',
						'id'       => true === $result ? SmsChannel::last_message_id() : '',
						'response' => SmsChannel::$last_response,
					),
					DAY_IN_SECONDS
				);
		}
		$back = admin_url( 'admin.php?page=yadambiar&view=settings&tab=channels' );
		if ( is_wp_error( $result ) ) {
			self::back( $back, $result->get_error_message(), 'error' );
		}
		self::back( $back, 'sms' === $channel ? __( 'سامانه پیامک درخواست را پذیرفت. وضعیت تحویل را در کادر «آخرین ارسال آزمایشی» بررسی کنید.', 'yadam-biar' ) : __( 'پیام آزمایشی ارسال شد.', 'yadam-biar' ) );
	}

	/**
	 * Diagnostics box: last test SMS, delivery check and account check.
	 */
	private static function sms_test_box() {
		$test = get_transient( 'yadambiar_sms_test_' . get_current_user_id() );
		?>
		<div class="yadambiar-diag">
			<div class="yadambiar-diag-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="yadambiar_sms_account">
					<?php wp_nonce_field( 'yadambiar_sms_account' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'بررسی اتصال، اعتبار و خطوط', 'yadam-biar' ); ?></button>
				</form>
				<?php if ( ! empty( $test['id'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="yadambiar_sms_status">
						<?php wp_nonce_field( 'yadambiar_sms_status' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'بررسی وضعیت تحویل آخرین پیامک', 'yadam-biar' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<?php if ( $test ) : ?>
				<div class="yadambiar-diag-last <?php echo $test['ok'] ? 'is-ok' : 'is-err'; ?>">
					<strong><?php esc_html_e( 'آخرین ارسال آزمایشی', 'yadam-biar' ); ?></strong>
					<span><?php echo esc_html( $test['to'] . ' · ' . ( 'otp' === $test['kind'] ? __( 'پترن', 'yadam-biar' ) : __( 'متنی', 'yadam-biar' ) ) . ' · ' . wp_date( 'H:i:s', $test['time'] ) ); ?></span>
					<span><?php echo esc_html( $test['ok'] ? __( 'پذیرفته شد', 'yadam-biar' ) . ( $test['id'] ? ' — ' . __( 'شناسه:', 'yadam-biar' ) . ' ' . $test['id'] : '' ) : $test['error'] ); ?></span>
					<?php if ( ! empty( $test['response'] ) ) : ?>
						<details><summary><?php esc_html_e( 'پاسخ کامل سامانه پیامک', 'yadam-biar' ); ?></summary><code dir="ltr"><?php echo esc_html( wp_json_encode( $test['response'], JSON_UNESCAPED_UNICODE ) ); ?></code></details>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'اگر سامانه پیامک درخواست را پذیرفت ولی پیامک نرسید، معمولاً شماره در لیست سیاه مخابرات است (پیامک متنی از خط تبلیغاتی نمی‌گیرد)، خط به حساب تعلق ندارد یا کلید API از نوع «آزمایشی/sandbox» است. «پترن کد تایید» از خط خدماتی و بدون محدودیت لیست سیاه ارسال می‌شود.', 'yadam-biar' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Admin action: SMS account check (credit / lines).
	 */
	public static function sms_account() {
		self::guard( 'yadambiar_sms_account' );
		$back = admin_url( 'admin.php?page=yadambiar&view=settings&tab=channels' );
		$info = SmsChannel::account_info();
		if ( is_wp_error( $info ) ) {
			self::back( $back, $info->get_error_message(), 'error' );
		}
		$parts = array();
		foreach ( $info as $label => $value ) {
			$parts[] = $label . ': ' . $value;
		}
		self::back( $back, __( 'اتصال برقرار است.', 'yadam-biar' ) . ' ' . implode( ' — ', $parts ) );
	}

	/**
	 * Admin action: delivery status of the last test SMS.
	 */
	public static function sms_status() {
		self::guard( 'yadambiar_sms_status' );
		$back = admin_url( 'admin.php?page=yadambiar&view=settings&tab=channels' );
		$test = get_transient( 'yadambiar_sms_test_' . get_current_user_id() );
		if ( empty( $test['id'] ) ) {
			self::back( $back, __( 'شناسه پیامکی برای بررسی وجود ندارد.', 'yadam-biar' ), 'error' );
		}
		$status = SmsChannel::delivery_status( $test['id'] );
		if ( is_wp_error( $status ) ) {
			self::back( $back, $status->get_error_message(), 'error' );
		}
		self::back( $back, __( 'وضعیت تحویل:', 'yadam-biar' ) . ' ' . $status );
	}
}
