<?php
/**
 * Product page widget.
 *
 * Available: $product, $s, $types, $variable, $in_stock, $any_oos, $waiting, $total,
 * $show_count, $prefill, $channels, $messengers, $attributes, $price.
 *
 * Override by copying to yourtheme/khabar/product-widget.php.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

$khabar_uid        = isset( $khabar_uid ) ? $khabar_uid : 'khabar-' . $product->get_id();
$khabar_vars       = isset( $khabar_vars ) ? $khabar_vars : '';
$khabar_has_stock  = in_array( 'stock', $types, true );
$khabar_has_price  = array_intersect( array( 'price_drop', 'price_rise', 'price_change' ), $types );
$khabar_contact    = $s['contact_mode'];
$khabar_sel        = $s['user_selects_channel'] && count( $channels ) > 1;
?>
<div class="khabar" id="<?php echo esc_attr( $khabar_uid ); ?>"<?php echo ! empty( $khabar_vars ) ? ' style="' . esc_attr( $khabar_vars ) . '"' : ''; ?>
	data-product="<?php echo esc_attr( $product->get_id() ); ?>"
	data-variable="<?php echo $variable ? '1' : '0'; ?>"
	data-instock="<?php echo $in_stock ? '1' : '0'; ?>"
	data-price="<?php echo esc_attr( $price ); ?>"
	data-waiting="<?php echo esc_attr( wp_json_encode( (object) $waiting ) ); ?>"
	data-show-count="<?php echo $show_count ? '1' : '0'; ?>"
	data-count-min="<?php echo esc_attr( (int) $s['waiting_count_min'] ); ?>">

	<?php if ( $khabar_has_stock ) : ?>
	<div class="khabar-cta khabar-cta-stock" <?php echo ( ! $in_stock && ! $variable ) || ( $variable && ! $in_stock ) ? '' : 'hidden'; ?>>
		<button type="button" class="button khabar-open" data-mode="stock"><?php echo esc_html( $s['button_text_stock'] ); ?></button>
		<p class="khabar-waiting" <?php echo $show_count ? '' : 'hidden'; ?>>
			<?php
			/* translators: %s number of people */
			printf( esc_html__( '%s نفر منتظر این کالا هستند', 'khabar' ), '<span>' . esc_html( number_format_i18n( $total ) ) . '</span>' );
			?>
		</p>
	</div>
	<?php endif; ?>

	<?php if ( $khabar_has_price && $s['show_price_alert'] ) : ?>
	<div class="khabar-cta khabar-cta-price" <?php echo $in_stock ? '' : 'hidden'; ?>>
		<button type="button" class="khabar-price-btn khabar-open" data-mode="price"><span class="khabar-price-ic"><?php echo Khabar_Frontend::icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span><?php echo esc_html( $s['button_text_price'] ); ?></span></button>
	</div>
	<?php endif; ?>

	<div class="khabar-modal"<?php echo ! empty( $khabar_vars ) ? ' style="' . esc_attr( $khabar_vars ) . '"' : ''; ?> role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $khabar_uid ); ?>-title" hidden>
		<div class="khabar-modal-box">
			<span class="khabar-grab" aria-hidden="true"></span>
			<header class="khabar-head">
				<span class="khabar-thumb"><?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></span>
				<div class="khabar-head-text">
					<h3 id="<?php echo esc_attr( $khabar_uid ); ?>-title"><?php esc_html_e( 'خبرم کن', 'khabar' ); ?></h3>
					<p class="khabar-product-name"><strong><?php echo esc_html( $product->get_name() ); ?></strong> <span class="khabar-variation-label"></span></p>
				</div>
				<button type="button" class="khabar-close" aria-label="<?php esc_attr_e( 'بستن', 'khabar' ); ?>"><?php echo Khabar_Frontend::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</header>

			<form class="khabar-form" novalidate autocomplete="off">
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
				<input type="hidden" name="variation_id" value="0">
				<div class="khabar-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

				<?php if ( $attributes ) : ?>
				<fieldset class="khabar-attrs">
					<legend><span><?php esc_html_e( 'ویژگی‌های مورد انتظار شما', 'khabar' ); ?></span></legend>
					<?php foreach ( $attributes as $khabar_key => $khabar_attr ) : ?>
						<label>
							<span><?php echo esc_html( $khabar_attr['label'] ); ?></span>
							<select name="attributes[<?php echo esc_attr( $khabar_key ); ?>]" data-attr="<?php echo esc_attr( $khabar_key ); ?>">
								<option value=""><?php esc_html_e( 'هر کدام', 'khabar' ); ?></option>
								<?php foreach ( $khabar_attr['options'] as $khabar_slug => $khabar_name ) : ?>
									<option value="<?php echo esc_attr( $khabar_slug ); ?>"><?php echo esc_html( $khabar_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>

				<fieldset class="khabar-conditions">
					<legend><?php echo Khabar_Frontend::icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'چه زمانی خبرتان کنیم؟', 'khabar' ); ?></span></legend>
					<?php if ( $khabar_has_stock ) : ?>
						<label class="khabar-cond"><input type="checkbox" name="in_stock" value="1"><span class="khabar-cond-text"><?php esc_html_e( 'وقتی موجود شد', 'khabar' ); ?></span><span class="khabar-cond-ic"><?php echo Khabar_Frontend::icon( 'box' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
					<?php if ( in_array( 'price_drop', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" data-toggle="price_below"><span class="khabar-cond-text"><?php esc_html_e( 'وقتی قیمت رسید به کمتر از', 'khabar' ); ?></span>
							<input type="text" inputmode="numeric" name="price_below" class="khabar-num" placeholder="<?php echo esc_attr( $price ? wc_format_localized_price( round( $price * 0.9, wc_get_price_decimals() ) ) : '' ); ?>" disabled><span class="khabar-cur"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						<span class="khabar-cond-ic"><?php echo Khabar_Frontend::icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'price_rise', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" data-toggle="price_above"><span class="khabar-cond-text"><?php esc_html_e( 'وقتی قیمت بیشتر شد از', 'khabar' ); ?></span>
							<span class="khabar-cur"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="text" inputmode="numeric" name="price_above" class="khabar-num" disabled>
						<span class="khabar-cond-ic"><?php echo Khabar_Frontend::icon( 'up' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'price_change', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" name="price_change" value="1"><span class="khabar-cond-text"><?php esc_html_e( 'هر بار قیمت تغییر کرد', 'khabar' ); ?></span><span class="khabar-cond-ic"><?php echo Khabar_Frontend::icon( 'chart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
					<?php if ( in_array( 'min_qty', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" data-toggle="min_qty"><span class="khabar-cond-text"><?php esc_html_e( 'موجودی حداقل', 'khabar' ); ?></span>
							<span class="khabar-cur"><?php esc_html_e( 'عدد شد', 'khabar' ); ?></span><input type="text" inputmode="numeric" name="min_qty" class="khabar-num khabar-num-sm" disabled>
						<span class="khabar-cond-ic"><?php echo Khabar_Frontend::icon( 'cube' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</label>
					<?php endif; ?>
					<div class="khabar-mode" hidden>
						<label><input type="radio" name="mode" value="all" checked><span><?php esc_html_e( 'وقتی همه شرایط با هم برقرار شد', 'khabar' ); ?></span></label>
						<label><input type="radio" name="mode" value="separate"><span><?php esc_html_e( 'برای هر شرط جداگانه', 'khabar' ); ?></span></label>
					</div>
				</fieldset>

				<?php if ( $khabar_sel ) : ?>
				<fieldset class="khabar-channels">
					<legend><?php echo Khabar_Frontend::icon( 'send' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'از چه راهی خبرتان کنیم؟', 'khabar' ); ?></span></legend>
					<?php foreach ( $channels as $khabar_ch => $khabar_label ) : ?>
						<label><input type="checkbox" name="channels[]" value="<?php echo esc_attr( $khabar_ch ); ?>" <?php checked( in_array( $khabar_ch, array( 'sms', 'email', 'onsite', 'telegram', 'bale' ), true ) ); ?>><?php echo Khabar_Frontend::icon( Khabar_Frontend::channel_icon( $khabar_ch ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $khabar_label ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>
				<fieldset class="khabar-contact">
					<label><span class="khabar-lbl"><?php esc_html_e( 'نام (اختیاری)', 'khabar' ); ?></span><span class="khabar-field"><input type="text" name="name" value="<?php echo esc_attr( $prefill['name'] ); ?>" autocomplete="name"><?php echo Khabar_Frontend::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php if ( $khabar_sel || 'email' !== $khabar_contact ) : ?>
						<label class="khabar-f-phone" data-for="sms whatsapp"><span class="khabar-lbl"><?php esc_html_e( 'شماره موبایل', 'khabar' ); ?></span><span class="khabar-field"><input type="tel" name="phone" dir="ltr" value="<?php echo esc_attr( $prefill['phone'] ); ?>" placeholder="09xxxxxxxxx" autocomplete="tel" <?php echo in_array( $khabar_contact, array( 'phone', 'both' ), true ) ? 'required' : ''; ?>><?php echo Khabar_Frontend::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
					<?php if ( $khabar_sel || 'phone' !== $khabar_contact ) : ?>
						<label class="khabar-f-email" data-for="email"><span class="khabar-lbl"><?php esc_html_e( 'ایمیل', 'khabar' ); ?></span><span class="khabar-field"><input type="email" name="email" dir="ltr" value="<?php echo esc_attr( $prefill['email'] ); ?>" autocomplete="email" <?php echo in_array( $khabar_contact, array( 'email', 'both' ), true ) ? 'required' : ''; ?>><?php echo Khabar_Frontend::icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
				</fieldset>


				<p class="khabar-msg" role="alert" hidden></p>
				<button type="submit" class="button alt khabar-submit"><span><?php esc_html_e( 'ثبت درخواست', 'khabar' ); ?></span><?php echo Khabar_Frontend::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</form>

			<form class="khabar-otp" hidden novalidate>
				<p class="khabar-otp-text"></p>
				<input type="hidden" name="contact">
				<label><span><?php esc_html_e( 'کد تایید', 'khabar' ); ?></span><input type="text" name="code" inputmode="numeric" dir="ltr" autocomplete="one-time-code" maxlength="6"></label>
				<p class="khabar-msg" role="alert" hidden></p>
				<button type="submit" class="button alt"><?php esc_html_e( 'تایید', 'khabar' ); ?></button>
			</form>

			<div class="khabar-done" hidden>
				<div class="khabar-done-badge" aria-hidden="true">
					<svg viewBox="0 0 52 52" width="52" height="52" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path class="khabar-done-check" d="M14 27l8 8 16-17"/></svg>
				</div>
				<h4 class="khabar-done-title"><?php esc_html_e( 'عالی، ثبت شد!', 'khabar' ); ?></h4>
				<p class="khabar-done-text"></p>
				<?php if ( $messengers ) : ?>
					<div class="khabar-connect" data-networks="<?php echo esc_attr( wp_json_encode( $messengers ) ); ?>" hidden></div>
				<?php endif; ?>
				<div class="khabar-done-actions">
					<?php if ( is_user_logged_in() ) : ?>
						<a class="khabar-btn khabar-btn-primary" href="<?php echo esc_url( wc_get_account_endpoint_url( Khabar_Account::ENDPOINT ) ); ?>"><?php esc_html_e( 'مشاهده خبرم کن‌های من', 'khabar' ); ?></a>
					<?php endif; ?>
					<button type="button" class="khabar-btn <?php echo is_user_logged_in() ? 'khabar-btn-ghost' : 'khabar-btn-primary'; ?> khabar-close-done"><?php esc_html_e( 'متوجه شدم', 'khabar' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
