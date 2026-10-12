<?php
/**
 * Admin section: Why the waiting customers of a product were (not) notified.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Core\Installer;
use YadamBiar\Notifications\Dispatcher;
use YadamBiar\Settings\Catalog;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\ProductAttributes;
/**
 * Why the waiting customers of a product were (not) notified.
 */
final class DiagnosePage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'diagnose';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'عیب‌یابی ارسال', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'help';
	}

	/**
	 * Render.
	 */
	public function render() {
		global $wpdb;
		$pid   = $this->query_int( 'product_id' );
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
				<?php $this->view_fields(); ?>
				<label><?php esc_html_e( 'محصول', 'yadambiar' ); ?>
					<select name="product_id" style="width:320px">
						<?php foreach ( $ids as $id ) : $p = wc_get_product( $id ); if ( ! $p ) { continue; } ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $pid, (int) $id ); ?>><?php echo esc_html( $p->get_name() ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php submit_button( __( 'بررسی', 'yadambiar' ), 'secondary', '', false ); ?>
				<?php if ( $pid ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( AdminUrl::action( 'run_product', array( 'product_id' => $pid ) ) ); ?>"><?php esc_html_e( 'بررسی و ارسال اکنون', 'yadambiar' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="yadambiar-card">
				<h2><?php esc_html_e( 'وضعیت سیستم', 'yadambiar' ); ?></h2>
				<ul class="yadambiar-health">
					<?php foreach ( $health as $h ) : ?>
						<li class="is-<?php echo esc_attr( $h[0] ); ?>"><?php echo esc_html( $h[1] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="yadambiar-card">
				<h2><?php echo esc_html( $product ? sprintf( /* translators: %s product */ __( 'درخواست‌های «%s»', 'yadambiar' ), $product->get_name() ) : __( 'درخواستی برای بررسی وجود ندارد', 'yadambiar' ) ); ?></h2>
				<?php if ( $rows ) : ?>
					<div class="yadambiar-table-scroll"><table class="widefat yadambiar-forecast-table yadambiar-diag-table">
						<thead><tr><th><?php esc_html_e( 'مشتری', 'yadambiar' ); ?></th><th><?php esc_html_e( 'شرایط', 'yadambiar' ); ?></th><th><?php esc_html_e( 'نتیجه بررسی', 'yadambiar' ); ?></th><th><?php esc_html_e( 'کانال‌های قابل ارسال', 'yadambiar' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $r ) : $sub = $r['sub']; ?>
							<tr>
								<td><strong><?php echo esc_html( $sub->name ? $sub->name : '#' . $sub->id ); ?></strong><br><small class="yadambiar-muted" dir="ltr"><?php echo esc_html( trim( $sub->phone . ' ' . $sub->email ) ); ?></small></td>
								<td><?php echo esc_html( SubscriptionRepository::conditions_label( $sub ) ); ?><?php echo $sub->attributes ? '<br><small class="yadambiar-muted">' . esc_html( ProductAttributes::attributes_label( $sub->attributes ) ) . '</small>' : ''; ?></td>
								<td><span class="kf-conf <?php echo $r['ok'] ? 'is-high' : 'is-medium'; ?>"><?php echo esc_html( $r['ok'] ? __( 'آماده ارسال', 'yadambiar' ) : __( 'ارسال نمی‌شود', 'yadambiar' ) ); ?></span><br><small><?php echo esc_html( $r['reason'] ); ?></small></td>
								<td><?php echo $r['channels'] ? esc_html( implode( '، ', array_map( function ( $c ) { $all = Catalog::channels(); return isset( $all[ $c ] ) ? $all[ $c ] : $c; }, $r['channels'] ) ) ) : '<span class="kf-dash">—</span>'; ?>
									<?php foreach ( $r['warnings'] as $w ) : ?><br><small class="yadambiar-warn">⚠ <?php echo esc_html( $w ); ?></small><?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php else : ?>
					<p><?php esc_html_e( 'این محصول درخواست فعالی ندارد.', 'yadambiar' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
