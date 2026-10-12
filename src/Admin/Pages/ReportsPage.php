<?php
/**
 * Admin section: Demand per product / attribute and the funnel of each alert type.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Reports\ReportRepository;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\ProductAttributes;
/**
 * Demand per product / attribute and the funnel of each alert type.
 */
final class ReportsPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'reports';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'گزارش‌ها', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'chart';
	}

	/**
	 * Render.
	 */
	public function render() {
		$pid     = $this->query_int( 'product_id' );
		$product = $pid ? wc_get_product( $pid ) : null;
		$demand  = ReportRepository::attribute_demand( $pid );
		$funnel  = ReportRepository::type_funnel();
		$types   = SubscriptionRepository::types();
		$top     = ReportRepository::top_products( 30 );
		?>
		<div class="yadambiar-view">

			<form method="get" class="yadambiar-report-filter">
				<?php $this->view_fields(); ?>
				<select name="product_id">
					<option value=""><?php esc_html_e( 'کل فروشگاه', 'yadambiar' ); ?></option>
					<?php foreach ( $top as $row ) : $p = wc_get_product( $row->product_id ); if ( ! $p ) { continue; } ?>
						<option value="<?php echo esc_attr( $row->product_id ); ?>" <?php selected( $pid, (int) $row->product_id ); ?>><?php echo esc_html( $p->get_name() . ' (' . number_format_i18n( $row->people ) . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'نمایش', 'yadambiar' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( $product ) : ?>
				<div class="yadambiar-card">
					<h2><?php echo esc_html( $product->get_name() ); ?></h2>
					<p>
						<?php
						/* translators: %s people */
						printf( esc_html__( 'تعداد منتظر: %s نفر', 'yadambiar' ), '<strong>' . esc_html( number_format_i18n( SubscriptionRepository::waiting_count( $pid ) ) ) . '</strong>' );
						?>
					</p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'تنوع / ویژگی‌های درخواستی', 'yadambiar' ); ?></th><th><?php esc_html_e( 'درخواست', 'yadambiar' ); ?></th><th><?php esc_html_e( 'موجودی فعلی', 'yadambiar' ); ?></th></tr></thead>
						<tbody>
						<?php
						foreach ( ReportRepository::variation_demand( $pid ) as $row ) :
							$v     = $row->variation_id ? wc_get_product( $row->variation_id ) : null;
							$label = $v ? wc_get_formatted_variation( $v, true, true ) : ( $row->attributes ? ProductAttributes::attributes_label( json_decode( $row->attributes, true ) ) : __( 'کل محصول / هر تنوعی', 'yadambiar' ) );
							?>
							<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( number_format_i18n( $row->c ) ); ?></td><td><?php echo $v ? esc_html( $v->is_in_stock() ? ( $v->managing_stock() ? number_format_i18n( $v->get_stock_quantity() ) : __( 'موجود', 'yadambiar' ) ) : __( 'ناموجود', 'yadambiar' ) ) : '—'; ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="yadambiar-grid">
				<div class="yadambiar-card">
					<h2><?php echo $product ? esc_html__( 'بیشترین ویژگی‌های درخواست‌شده این محصول', 'yadambiar' ) : esc_html__( 'بیشترین ویژگی‌های درخواست‌شده در فروشگاه', 'yadambiar' ); ?></h2>
					<?php if ( ! $demand ) : ?>
						<p><?php esc_html_e( 'داده‌ای موجود نیست.', 'yadambiar' ); ?></p>
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
					<h2><?php esc_html_e( 'قیف هر نوع اعلان', 'yadambiar' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'نوع', 'yadambiar' ); ?></th><th><?php esc_html_e( 'در انتظار', 'yadambiar' ); ?></th><th><?php esc_html_e( 'اطلاع داده شد', 'yadambiar' ); ?></th><th><?php esc_html_e( 'خرید', 'yadambiar' ); ?></th></tr></thead>
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
}
