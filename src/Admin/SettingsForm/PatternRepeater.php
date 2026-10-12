<?php
/**
 * Repeater UI for per-event SMS patterns.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\SettingsForm;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\ProviderRegistry;
use YadamBiar\Settings\Catalog;

/**
 * SMS pattern repeater.
 */
final class PatternRepeater {

	/**
	 * Print the repeater.
	 *
	 * @param string $name  Field name prefix.
	 * @param array  $items Saved items.
	 */
	public function render( $name, array $items ) {
		$events = Catalog::events();
		$chips  = array(
			'customer_name' => __( 'نام مشتری', 'yadambiar' ),
			'product_name'  => __( 'نام محصول', 'yadambiar' ),
			'variation'     => __( 'تنوع', 'yadambiar' ),
			'price'         => __( 'قیمت', 'yadambiar' ),
			'old_price'     => __( 'قیمت قبلی', 'yadambiar' ),
			'target_price'  => __( 'قیمت هدف', 'yadambiar' ),
			'stock'         => __( 'موجودی', 'yadambiar' ),
			'link'          => __( 'لینک', 'yadambiar' ),
			'site_name'     => __( 'نام سایت', 'yadambiar' ),
			'code'          => __( 'کد تایید', 'yadambiar' ),
			'coupon'        => __( 'کد تخفیف', 'yadambiar' ),
		);
		$hints = array(
			'kavenegar'   => __( 'کاوه‌نگار: «کد پترن» همان نام قالب در پنل است. نام متغیرها را token، token2، token3 (و token10، token20) بگذارید؛ مقدار آن‌ها فاصله نمی‌پذیرد و خودکار اصلاح می‌شود.', 'yadambiar' ),
			'payamak_panel' => __( 'ملی پیامک و پنل‌های سازگار: «کد پترن» همان bodyId است. مقدارها به ترتیب نوشتن و با ; ارسال می‌شوند؛ نام متغیر فقط برای شما است.', 'yadambiar' ),
			'ippanel'     => __( 'فراز / IPPanel: «کد پترن» را وارد کنید و نام متغیرها را دقیقاً مثل نام‌های تعریف‌شده در پترن بنویسید.', 'yadambiar' ),
			'smsir'       => __( 'SMS.ir: «کد پترن» شناسه‌ی عددی قالب است و نام پارامترها دقیقاً همان نام‌های قالب است (مثلاً Code).', 'yadambiar' ),
			'ghasedak'    => __( 'قاصدک: «کد پترن» نام قالب (templateName) در پنل است و نام متغیرها همان param های قالب.', 'yadambiar' ),
			'iranpayamak' => __( 'ایران پیامک: «کد پترن» کد الگو است و نام متغیرها همان نام‌های تعریف‌شده در الگو.', 'yadambiar' ),
			'webhook'     => __( 'وب‌سرویس سفارشی از پترن استفاده نمی‌کند؛ از «قالب پیام» متنی استفاده می‌شود.', 'yadambiar' ),
		);
		$row = function ( $i, $item ) use ( $name, $events, $chips ) {
			$base = $name . '[' . $i . ']';
			ob_start();
			?>
			<div class="yadambiar-pat-row">
				<div class="yadambiar-pat-head">
					<label class="yadambiar-pat-f">
						<span><?php esc_html_e( 'نوع رویداد', 'yadambiar' ); ?></span>
						<select name="<?php echo esc_attr( $base ); ?>[event]" class="yadambiar-pat-event">
							<?php foreach ( $events as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $item['event'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="yadambiar-pat-f yadambiar-pat-code">
						<span><?php esc_html_e( 'کد / نام پترن', 'yadambiar' ); ?></span>
						<input type="text" dir="ltr" name="<?php echo esc_attr( $base ); ?>[code]" value="<?php echo esc_attr( $item['code'] ); ?>" placeholder="123456">
					</label>
					<button type="button" class="yadambiar-pat-del" title="<?php esc_attr_e( 'حذف این پترن', 'yadambiar' ); ?>" aria-label="<?php esc_attr_e( 'حذف این پترن', 'yadambiar' ); ?>">×</button>
				</div>
				<div class="yadambiar-pat-vars" data-base="<?php echo esc_attr( $base ); ?>">
					<div class="yadambiar-pat-vhead"><strong><?php esc_html_e( 'متغیرهای این پترن', 'yadambiar' ); ?></strong><small><?php esc_html_e( 'نام متغیر در سامانه پیامک ← مقداری که ارسال می‌شود', 'yadambiar' ); ?></small></div>
					<div class="yadambiar-pat-vlist">
						<?php foreach ( array_values( (array) $item['vars'] ) as $j => $var ) : ?>
							<div class="yadambiar-pat-var">
								<input type="text" dir="ltr" class="yadambiar-pat-vname" name="<?php echo esc_attr( $base ); ?>[vars][<?php echo (int) $j; ?>][name]" value="<?php echo esc_attr( $var['name'] ); ?>" placeholder="token">
								<span class="yadambiar-pat-arrow">←</span>
								<input type="text" dir="auto" class="yadambiar-pat-vval" name="<?php echo esc_attr( $base ); ?>[vars][<?php echo (int) $j; ?>][value]" value="<?php echo esc_attr( $var['value'] ); ?>" placeholder="{product_name}">
								<button type="button" class="yadambiar-pat-vdel" aria-label="<?php esc_attr_e( 'حذف متغیر', 'yadambiar' ); ?>">×</button>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="yadambiar-pat-chips" aria-label="<?php esc_attr_e( 'درج متغیر', 'yadambiar' ); ?>">
						<?php foreach ( $chips as $key => $label ) : ?>
							<button type="button" class="yadambiar-chip" data-token="{<?php echo esc_attr( $key ); ?>}" title="{<?php echo esc_attr( $key ); ?>}"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button yadambiar-pat-addvar">+ <?php esc_html_e( 'افزودن متغیر', 'yadambiar' ); ?></button>
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
				<strong><?php esc_html_e( 'چطور کار می‌کند؟', 'yadambiar' ); ?></strong>
				<ol>
					<li><?php esc_html_e( 'برای هر رویداد یک پترن بسازید: «افزودن پترن» را بزنید و نوع رویداد (مثلاً موجود شدن، کاهش قیمت، کد تایید) را انتخاب کنید.', 'yadambiar' ); ?></li>
					<li><?php esc_html_e( 'کد پترن تاییدشده‌ی همان رویداد را از سامانه‌ی پیامک وارد کنید.', 'yadambiar' ); ?></li>
					<li><?php esc_html_e( 'متغیرهای پترن را مشخص کنید. روی دکمه‌های رنگی (نام محصول، قیمت، لینک…) بزنید تا در مقدار انتخاب‌شده درج شود.', 'yadambiar' ); ?></li>
				</ol>
				<p class="yadambiar-pat-gw" hidden></p>
				<p class="yadambiar-pat-note"><?php esc_html_e( 'رویدادی که پترن ندارد با پیامک متنی (قالب پیام) ارسال می‌شود.', 'yadambiar' ); ?></p>
			</div>
			<div class="yadambiar-pat-list">
				<?php
				$i = 0;
				foreach ( $items as $item ) {
					echo $row( $i++, $item ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
			<p class="yadambiar-pat-empty" <?php echo $items ? 'hidden' : ''; ?>><?php esc_html_e( 'هنوز پترنی ثبت نشده است.', 'yadambiar' ); ?></p>
			<button type="button" class="button button-primary yadambiar-pat-add">+ <?php esc_html_e( 'افزودن پترن', 'yadambiar' ); ?></button>
			<template class="yadambiar-pat-tpl"><?php echo $row( '__i__', array( 'event' => 'back_in_stock', 'code' => '', 'vars' => array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		</div>
		<?php
	}
}
