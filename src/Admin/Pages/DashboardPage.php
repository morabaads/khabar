<?php
/**
 * Admin section: KPIs, most-awaited products, restock priority and channel health.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Reports\ReportRepository;
use YadamBiar\Settings\Catalog;
use YadamBiar\Support\Money;
/**
 * KPIs, most-awaited products, restock priority and channel health.
 */
final class DashboardPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'dashboard';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'داشبورد', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'grid';
	}

	/**
	 * Render.
	 */
	public function render() {
		$k        = ReportRepository::kpis( 30 );
		$top      = ReportRepository::top_products( 10 );
		$restock  = ReportRepository::restock_priority( 8 );
		$channels = ReportRepository::channel_stats( 30 );
		$labels   = Catalog::channels();
		?>
		<div class="yadambiar-view">

			<div class="yadambiar-kpis">
				<?php
				$cards = array(
					array( __( 'درخواست فعال', 'yadambiar' ), number_format_i18n( $k['active'] ), sprintf( /* translators: %s products */ __( 'برای %s محصول', 'yadambiar' ), number_format_i18n( $k['products'] ) ) ),
					array( __( 'مشتری منتظر', 'yadambiar' ), number_format_i18n( $k['people'] ), __( 'افراد یکتا', 'yadambiar' ) ),
					array( __( 'اعلان ارسال شده', 'yadambiar' ), number_format_i18n( $k['sent'] ), sprintf( /* translators: %s failed */ __( '%s ناموفق — ۳۰ روز اخیر', 'yadambiar' ), number_format_i18n( $k['failed'] ) ) ),
					array( __( 'نرخ کلیک', 'yadambiar' ), number_format_i18n( $k['click_rate'], 1 ) . '٪', sprintf( /* translators: %s clicks */ __( '%s کلیک', 'yadambiar' ), number_format_i18n( $k['clicked'] ) ) ),
					array( __( 'خرید از اعلان', 'yadambiar' ), number_format_i18n( $k['conversions'] ), sprintf( /* translators: %s rate */ __( 'نرخ تبدیل %s٪', 'yadambiar' ), number_format_i18n( $k['conv_rate'], 1 ) ) ),
					array( __( 'درآمد از اعلان‌ها', 'yadambiar' ), Money::price_text( $k['revenue'] ), __( '۳۰ روز اخیر', 'yadambiar' ) ),
				);
				foreach ( $cards as $card ) {
					printf( '<div class="yadambiar-kpi"><span>%s</span><strong>%s</strong><small>%s</small></div>', esc_html( $card[0] ), esc_html( $card[1] ), esc_html( $card[2] ) ); // Icons come from CSS (nth-child).
				}
				?>
			</div>

			<div class="yadambiar-grid">
				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'محصولات تحت انتظار', 'yadambiar' ); ?></h2>
					<?php if ( ! $top ) : ?>
						<p><?php esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'yadambiar' ); ?></p>
					<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'محصول', 'yadambiar' ); ?></th><th><?php esc_html_e( 'منتظران', 'yadambiar' ); ?></th><th><?php esc_html_e( 'موجودی', 'yadambiar' ); ?></th><th><?php esc_html_e( 'قیمت', 'yadambiar' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						foreach ( $top as $row ) :
							$p = wc_get_product( $row->product_id );
							if ( ! $p ) {
								continue;
							}
							$run = AdminUrl::action( 'run_product', array( 'product_id' => $row->product_id ) );
							?>
							<tr>
								<td><a href="<?php echo esc_url( AdminUrl::view( 'reports', array( 'product_id' => $row->product_id ) ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a>
									<br><small><?php printf( /* translators: 1: stock requests 2: price requests */ esc_html__( '%1$s موجودی / %2$s قیمت', 'yadambiar' ), esc_html( number_format_i18n( $row->stock_requests ) ), esc_html( number_format_i18n( $row->price_requests ) ) ); ?></small></td>
								<td><strong><?php echo esc_html( number_format_i18n( $row->people ) ); ?></strong></td>
								<td><?php echo $p->is_in_stock() ? '<span class="yadambiar-status yadambiar-status-notified">' . esc_html__( 'موجود', 'yadambiar' ) . '</span>' : '<span class="yadambiar-status yadambiar-status-cancelled">' . esc_html__( 'ناموجود', 'yadambiar' ) . '</span>'; ?></td>
								<td><?php echo wp_kses_post( $p->get_price_html() ); ?></td>
								<td style="white-space:nowrap"><a class="button button-small" href="<?php echo esc_url( $run ); ?>"><?php esc_html_e( 'بررسی و ارسال', 'yadambiar' ); ?></a> <a class="button button-small" href="<?php echo esc_url( AdminUrl::view( 'diagnose', array( 'product_id' => $row->product_id ) ) ); ?>"><?php esc_html_e( 'عیب‌یابی', 'yadambiar' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<div class="yadambiar-card">
					<h2><?php esc_html_e( 'اولویت تامین موجودی', 'yadambiar' ); ?></h2>
					<p class="description"><?php esc_html_e( 'کالاهای ناموجود با بیشترین تقاضا و درآمد بالقوه در صورت تامین.', 'yadambiar' ); ?></p>
					<?php if ( ! $restock ) : ?>
						<p>—</p>
					<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'کالا', 'yadambiar' ); ?></th><th><?php esc_html_e( 'منتظر', 'yadambiar' ); ?></th><th><?php esc_html_e( 'درآمد بالقوه', 'yadambiar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $restock as $r ) : ?>
							<tr><td><a href="<?php echo esc_url( get_edit_post_link( $r['product']->get_parent_id() ? $r['product']->get_parent_id() : $r['product']->get_id() ) ); ?>"><?php echo esc_html( $r['product']->get_name() ); ?></a></td><td><?php echo esc_html( number_format_i18n( $r['count'] ) ); ?></td><td><?php echo esc_html( Money::price_text( $r['potential'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>

					<h2><?php esc_html_e( 'وضعیت کانال‌ها (۳۰ روز)', 'yadambiar' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'کانال', 'yadambiar' ); ?></th><th><?php esc_html_e( 'موفق', 'yadambiar' ); ?></th><th><?php esc_html_e( 'ناموفق', 'yadambiar' ); ?></th></tr></thead>
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
}
