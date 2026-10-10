<?php
/**
 * Admin page: demand forecast & purchase-order suggestions.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Forecast_Page {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_khabar_forecast_csv', array( __CLASS__, 'csv' ) );
	}

	/**
	 * What-if parameters from the query string.
	 *
	 * @return array
	 */
	private static function params() {
		$p = array();
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['lead'] ) && '' !== $_GET['lead'] ) {
			$p['lead'] = min( 365, absint( $_GET['lead'] ) );
		}
		if ( isset( $_GET['cover'] ) && '' !== $_GET['cover'] ) {
			$p['cover'] = min( 365, absint( $_GET['cover'] ) );
		}
		if ( isset( $_GET['level'] ) && in_array( absint( $_GET['level'] ), array( 80, 90, 95, 98 ), true ) ) {
			$p['level'] = absint( $_GET['level'] );
		}
		// phpcs:enable
		return $p;
	}

	/**
	 * Product label.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private static function label( $product ) {
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			return ( $parent ? $parent->get_name() : '' ) . ' — ' . wc_get_formatted_variation( $product, true, false, false );
		}
		return $product->get_name();
	}

	/**
	 * Page.
	 */
	public static function page() {
		$data   = Khabar_Forecast::build( self::params() );
		$p      = $data['params'];
		$rows   = $data['rows'];
		$units  = array_sum( wp_list_pluck( wp_list_pluck( $rows, 'calc' ), 'order' ) );
		$rev    = array_sum( wp_list_pluck( $rows, 'revenue' ) );
		$lost   = array_sum( wp_list_pluck( $rows, 'lost' ) );
		$conf   = array(
			'high'   => __( 'بالا', 'khabar' ),
			'medium' => __( 'متوسط', 'khabar' ),
			'low'    => __( 'پایین', 'khabar' ),
		);
		$csv    = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'khabar_forecast_csv' ), self::params() ), admin_url( 'admin-post.php' ) ), 'khabar_forecast_csv' );
		?>
		<div class="khabar-view">

			<form method="get" class="khabar-report-filter">
				<input type="hidden" name="page" value="khabar"><input type="hidden" name="view" value="forecast">
				<label><?php esc_html_e( 'زمان تحویل (روز)', 'khabar' ); ?> <input type="number" min="0" class="small-text" name="lead" value="<?php echo esc_attr( $p['lead'] ); ?>"></label>
				<label><?php esc_html_e( 'پوشش موجودی (روز)', 'khabar' ); ?> <input type="number" min="0" class="small-text" name="cover" value="<?php echo esc_attr( $p['cover'] ); ?>"></label>
				<label><?php esc_html_e( 'سطح اطمینان', 'khabar' ); ?>
					<select name="level"><?php foreach ( array( 80, 90, 95, 98 ) as $l ) : ?><option value="<?php echo esc_attr( $l ); ?>" <?php selected( (int) $p['level'], $l ); ?>><?php echo esc_html( $l ); ?>%</option><?php endforeach; ?></select>
				</label>
				<?php submit_button( __( 'محاسبه', 'khabar' ), 'secondary', '', false ); ?>
				<a class="button button-primary" href="<?php echo esc_url( $csv ); ?>"><?php esc_html_e( 'خروجی سفارش خرید (CSV)', 'khabar' ); ?></a>
			</form>

			<div class="khabar-kpis">
				<div class="khabar-kpi"><span><?php esc_html_e( 'کالاهای نیازمند سفارش', 'khabar' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( array_filter( $rows, function ( $r ) { return $r['calc']['order'] > 0; } ) ) ) ); ?></strong><small><?php esc_html_e( 'دارای منتظر', 'khabar' ); ?></small></div>
				<div class="khabar-kpi"><span><?php esc_html_e( 'تعداد پیشنهادی کل', 'khabar' ); ?></span><strong><?php echo esc_html( number_format_i18n( $units ) ); ?></strong><small><?php esc_html_e( 'واحد', 'khabar' ); ?></small></div>
				<div class="khabar-kpi"><span><?php esc_html_e( 'فروش مورد انتظار', 'khabar' ); ?></span><strong><?php echo esc_html( Khabar_Utils::price_text( $rev ) ); ?></strong><small><?php esc_html_e( 'ارزش سفارش پیشنهادی', 'khabar' ); ?></small></div>
				<div class="khabar-kpi"><span><?php esc_html_e( 'فروش معطل منتظران', 'khabar' ); ?></span><strong><?php echo esc_html( Khabar_Utils::price_text( $lost ) ); ?></strong><small><?php esc_html_e( 'با تامین فوری قابل جذب', 'khabar' ); ?></small></div>
				<div class="khabar-kpi"><span><?php esc_html_e( 'نرخ تبدیل فروشگاه', 'khabar' ); ?></span><strong><?php echo esc_html( number_format_i18n( $p['store_conv'] * 100, 1 ) ); ?>٪</strong><small><?php esc_html_e( 'منتظر ← خریدار (هموارشده)', 'khabar' ); ?></small></div>
			</div>

			<div class="khabar-card">
				<?php if ( ! $rows ) : ?>
					<p><?php esc_html_e( 'کالایی با منتظر موجود شدن وجود ندارد.', 'khabar' ); ?></p>
				<?php else : ?>
				<div class="khabar-table-scroll"><table class="widefat striped khabar-forecast-table">
					<thead><tr>
						<th><?php esc_html_e( 'کالا', 'khabar' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'موجودی', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'منتظر', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'منتظر جدید/روز', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'نرخ تبدیل', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'فروش ۳۰ روز', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'تقاضای پیش‌بینی', 'khabar' ); ?></th>
						<th class="num"><?php esc_html_e( 'موجودی اطمینان', 'khabar' ); ?></th>
						<th class="num"><strong><?php esc_html_e( 'سفارش پیشنهادی', 'khabar' ); ?></strong></th>
						<th class="num"><?php esc_html_e( 'ارزش', 'khabar' ); ?></th>
						<th><?php esc_html_e( 'اطمینان', 'khabar' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : $c = $r['calc']; ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $r['product']->get_parent_id() ? $r['product']->get_parent_id() : $r['product']->get_id() ) ); ?>"><?php echo esc_html( self::label( $r['product'] ) ); ?></a>
								<br><small class="khabar-muted"><?php /* translators: %s days */ printf( esc_html__( 'قدیمی‌ترین انتظار: %s روز', 'khabar' ), esc_html( number_format_i18n( $r['days_out'] ) ) ); ?></small></td>
							<td dir="ltr"><?php echo esc_html( $r['sku'] ? $r['sku'] : '—' ); ?></td>
							<td class="num"><?php echo null === $r['stock'] ? '—' : esc_html( number_format_i18n( $r['stock'] ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['waiting'], 1 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['new_per_day'], 2 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['conv'] * 100, 1 ) ); ?>٪</td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['sales_30'], 1 ) ); ?></td>
							<td class="num" title="<?php echo esc_attr( sprintf( /* translators: 1 waitlist 2 new 3 organic */ __( 'منتظران: %1$s + منتظران جدید: %2$s + فروش عادی: %3$s', 'khabar' ), $c['waitlist'], $c['new'], $c['organic'] ) ); ?>"><?php echo esc_html( number_format_i18n( $c['demand'], 1 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $c['safety'] ) ); ?></td>
							<td class="num"><strong><?php echo esc_html( number_format_i18n( $c['order'] ) ); ?></strong></td>
							<td class="num"><?php echo esc_html( Khabar_Utils::price_text( $r['revenue'] ) ); ?></td>
							<td><?php echo esc_html( $conf[ $r['confidence'] ] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php endif; ?>
			</div>

			<div class="khabar-card">
				<h2><?php esc_html_e( 'روش محاسبه', 'khabar' ); ?></h2>
				<ul class="khabar-method">
					<li><?php esc_html_e( 'نرخ تبدیل: درصد منتظرانی که پس از اعلان خرید کرده‌اند؛ برای محصولات با داده کم به سمت نرخ کل فروشگاه (و آن هم به سمت مقدار پیش‌فرض تنظیمات) هموار می‌شود.', 'khabar' ); ?></li>
					<li><?php esc_html_e( 'تقاضا = منتظران × نرخ تبدیل + منتظران جدید در دوره تحویل و پوشش × نرخ تبدیل + فروش روزانه عادی × روزهای پوشش.', 'khabar' ); ?></li>
					<li><?php esc_html_e( 'موجودی اطمینان = z × √تقاضا (مدل پواسون) بر اساس سطح اطمینان انتخابی.', 'khabar' ); ?></li>
					<li><?php esc_html_e( 'سفارش پیشنهادی = تقاضا + موجودی اطمینان − موجودی فعلی. درخواست‌هایی که تنوع دقیق ندارند بین تنوع‌های ناموجود منطبق تقسیم می‌شوند.', 'khabar' ); ?></li>
					<li><?php esc_html_e( 'فروش عادی از جدول تحلیل ووکامرس (۹۰ روز اخیر) خوانده می‌شود؛ چون کالا در بخشی از این مدت ناموجود بوده، این عدد محافظه‌کارانه است.', 'khabar' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Purchase order CSV.
	 */
	public static function csv() {
		if ( ! current_user_can( Khabar_Admin::CAP ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'khabar' ) );
		}
		check_admin_referer( 'khabar_forecast_csv' );
		$data = Khabar_Forecast::build( self::params() );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=khabar-purchase-order-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'SKU', 'Product', 'Suggested qty', 'Current stock', 'Waiting', 'Conversion %', 'Forecast demand', 'Safety stock', 'Unit price', 'Value' ) );
		foreach ( $data['rows'] as $r ) {
			if ( $r['calc']['order'] <= 0 ) {
				continue;
			}
			fputcsv(
				$out,
				array(
					$r['sku'],
					self::label( $r['product'] ),
					$r['calc']['order'],
					null === $r['stock'] ? '' : $r['stock'],
					$r['waiting'],
					round( $r['conv'] * 100, 1 ),
					$r['calc']['demand'],
					$r['calc']['safety'],
					$r['price'],
					$r['revenue'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
