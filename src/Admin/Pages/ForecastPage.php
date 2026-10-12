<?php
/**
 * Admin page: demand forecast & purchase-order suggestions.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Commerce\Forecast;
use YadamBiar\Support\Money;
use YadamBiar\Support\ProductAttributes;

/**
 * Demand forecast & purchase-order suggestions.
 */
final class ForecastPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'forecast';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'پیش‌بینی تقاضا', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'trend';
	}

	/**
	 * What-if parameters from the query string.
	 *
	 * @return array
	 */
	public static function request_params() {
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
	 * Render.
	 */
	public function render() {
		$data   = Forecast::build( self::request_params() );
		$p      = $data['params'];
		$rows   = $data['rows'];
		$units  = array_sum( wp_list_pluck( wp_list_pluck( $rows, 'calc' ), 'order' ) );
		$rev    = array_sum( wp_list_pluck( $rows, 'revenue' ) );
		$lost   = array_sum( wp_list_pluck( $rows, 'lost' ) );
		$conf   = array(
			'high'   => __( 'بالا', 'yadambiar' ),
			'medium' => __( 'متوسط', 'yadambiar' ),
			'low'    => __( 'پایین', 'yadambiar' ),
		);
		$csv    = AdminUrl::action( 'forecast_csv', self::request_params() );
		?>
		<div class="yadambiar-view">

			<form method="get" class="yadambiar-report-filter yadambiar-fc-filter">
				<?php $this->view_fields(); ?>
				<label><?php esc_html_e( 'زمان تحویل (روز)', 'yadambiar' ); ?> <input type="number" min="0" class="small-text" name="lead" value="<?php echo esc_attr( $p['lead'] ); ?>"></label>
				<label><?php esc_html_e( 'پوشش موجودی (روز)', 'yadambiar' ); ?> <input type="number" min="0" class="small-text" name="cover" value="<?php echo esc_attr( $p['cover'] ); ?>"></label>
				<label><?php esc_html_e( 'سطح اطمینان', 'yadambiar' ); ?>
					<select name="level"><?php foreach ( array( 80, 90, 95, 98 ) as $l ) : ?><option value="<?php echo esc_attr( $l ); ?>" <?php selected( (int) $p['level'], $l ); ?>><?php echo esc_html( $l ); ?>%</option><?php endforeach; ?></select>
				</label>
				<?php submit_button( __( 'محاسبه', 'yadambiar' ), 'secondary', '', false ); ?>
				<a class="button button-primary" href="<?php echo esc_url( $csv ); ?>"><?php esc_html_e( 'خروجی سفارش خرید (CSV)', 'yadambiar' ); ?></a>
			</form>

			<div class="yadambiar-kpis">
				<div class="yadambiar-kpi"><span><?php esc_html_e( 'کالاهای نیازمند سفارش', 'yadambiar' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( array_filter( $rows, function ( $r ) { return $r['calc']['order'] > 0; } ) ) ) ); ?></strong><small><?php esc_html_e( 'دارای منتظر', 'yadambiar' ); ?></small></div>
				<div class="yadambiar-kpi"><span><?php esc_html_e( 'تعداد پیشنهادی کل', 'yadambiar' ); ?></span><strong><?php echo esc_html( number_format_i18n( $units ) ); ?></strong><small><?php esc_html_e( 'واحد', 'yadambiar' ); ?></small></div>
				<div class="yadambiar-kpi"><span><?php esc_html_e( 'فروش مورد انتظار', 'yadambiar' ); ?></span><strong><?php echo esc_html( Money::price_text( $rev ) ); ?></strong><small><?php esc_html_e( 'ارزش سفارش پیشنهادی', 'yadambiar' ); ?></small></div>
				<div class="yadambiar-kpi"><span><?php esc_html_e( 'فروش معطل منتظران', 'yadambiar' ); ?></span><strong><?php echo esc_html( Money::price_text( $lost ) ); ?></strong><small><?php esc_html_e( 'با تامین فوری قابل جذب', 'yadambiar' ); ?></small></div>
				<div class="yadambiar-kpi"><span><?php esc_html_e( 'نرخ تبدیل فروشگاه', 'yadambiar' ); ?></span><strong><?php echo esc_html( number_format_i18n( $p['store_conv'] * 100, 1 ) ); ?>٪</strong><small><?php esc_html_e( 'منتظر ← خریدار (هموارشده)', 'yadambiar' ); ?></small></div>
			</div>

			<div class="yadambiar-card">
				<?php if ( ! $rows ) : ?>
					<p><?php esc_html_e( 'کالایی با منتظر موجود شدن وجود ندارد.', 'yadambiar' ); ?></p>
				<?php else : ?>
				<div class="yadambiar-table-scroll"><table class="widefat striped yadambiar-forecast-table">
					<thead><tr>
						<th><?php esc_html_e( 'کالا', 'yadambiar' ); ?></th>
						<th><?php esc_html_e( 'SKU', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'موجودی', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'منتظر', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'منتظر جدید/روز', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'نرخ تبدیل', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'فروش ۳۰ روز', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'تقاضای پیش‌بینی', 'yadambiar' ); ?></th>
						<th class="num"><?php esc_html_e( 'موجودی اطمینان', 'yadambiar' ); ?></th>
						<th class="num"><strong><?php esc_html_e( 'سفارش پیشنهادی', 'yadambiar' ); ?></strong></th>
						<th class="num"><?php esc_html_e( 'ارزش', 'yadambiar' ); ?></th>
						<th><?php esc_html_e( 'اطمینان', 'yadambiar' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : $c = $r['calc']; ?>
						<tr>
							<td class="kf-product-td"><div class="kf-product">
								<?php $kf_img = $r['product']->get_image_id() ? $r['product']->get_image_id() : ( $r['product']->get_parent_id() ? get_post_thumbnail_id( $r['product']->get_parent_id() ) : 0 ); ?>
								<span class="kf-thumb"><?php echo $kf_img ? wp_get_attachment_image( $kf_img, array( 44, 44 ) ) : ''; ?></span>
								<span class="kf-name">
									<a href="<?php echo esc_url( get_edit_post_link( $r['product']->get_parent_id() ? $r['product']->get_parent_id() : $r['product']->get_id() ) ); ?>"><?php echo esc_html( ProductAttributes::full_name( $r['product'] ) ); ?></a>
									<small><?php /* translators: %s days */ printf( esc_html__( 'قدیمی‌ترین انتظار: %s روز', 'yadambiar' ), esc_html( number_format_i18n( $r['days_out'] ) ) ); ?></small>
								</span>
							</div></td>
							<td><?php echo $r['sku'] ? '<code class="kf-sku">' . esc_html( $r['sku'] ) . '</code>' : '<span class="kf-dash">—</span>'; ?></td>
							<td class="num"><?php echo null === $r['stock'] ? '<span class="kf-dash">—</span>' : '<span class="kf-stock' . ( $r['stock'] <= 0 ? ' is-out' : '' ) . '">' . esc_html( number_format_i18n( $r['stock'] ) ) . '</span>'; ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['waiting'], 1 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['new_per_day'], 2 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['conv'] * 100, 1 ) ); ?>٪</td>
							<td class="num"><?php echo esc_html( number_format_i18n( $r['sales_30'], 1 ) ); ?></td>
							<td class="num" title="<?php echo esc_attr( sprintf( /* translators: 1 waitlist 2 new 3 organic */ __( 'منتظران: %1$s + منتظران جدید: %2$s + فروش عادی: %3$s', 'yadambiar' ), $c['waitlist'], $c['new'], $c['organic'] ) ); ?>"><?php echo esc_html( number_format_i18n( $c['demand'], 1 ) ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( $c['safety'] ) ); ?></td>
							<td class="num"><span class="kf-order<?php echo $c['order'] > 0 ? '' : ' is-zero'; ?>"><?php echo esc_html( number_format_i18n( $c['order'] ) ); ?></span></td>
							<td class="num kf-value"><?php echo esc_html( Money::price_text( $r['revenue'] ) ); ?></td>
							<td><span class="kf-conf is-<?php echo esc_attr( $r['confidence'] ); ?>"><?php echo esc_html( $conf[ $r['confidence'] ] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php endif; ?>
			</div>

			<div class="yadambiar-card">
				<h2><?php esc_html_e( 'روش محاسبه', 'yadambiar' ); ?></h2>
				<ul class="yadambiar-method">
					<li><?php esc_html_e( 'نرخ تبدیل: درصد منتظرانی که پس از اعلان خرید کرده‌اند؛ برای محصولات با داده کم به سمت نرخ کل فروشگاه (و آن هم به سمت مقدار پیش‌فرض تنظیمات) هموار می‌شود.', 'yadambiar' ); ?></li>
					<li><?php esc_html_e( 'تقاضا = منتظران × نرخ تبدیل + منتظران جدید در دوره تحویل و پوشش × نرخ تبدیل + فروش روزانه عادی × روزهای پوشش.', 'yadambiar' ); ?></li>
					<li><?php esc_html_e( 'موجودی اطمینان = z × √تقاضا (مدل پواسون) بر اساس سطح اطمینان انتخابی.', 'yadambiar' ); ?></li>
					<li><?php esc_html_e( 'سفارش پیشنهادی = تقاضا + موجودی اطمینان − موجودی فعلی. درخواست‌هایی که تنوع دقیق ندارند بین تنوع‌های ناموجود منطبق تقسیم می‌شوند.', 'yadambiar' ); ?></li>
					<li><?php esc_html_e( 'فروش عادی از جدول تحلیل ووکامرس (۹۰ روز اخیر) خوانده می‌شود؛ چون کالا در بخشی از این مدت ناموجود بوده، این عدد محافظه‌کارانه است.', 'yadambiar' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
