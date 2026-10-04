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
$khabar_has_stock  = in_array( 'stock', $types, true );
$khabar_has_price  = array_intersect( array( 'price_drop', 'price_rise', 'price_change' ), $types );
$khabar_contact    = $s['contact_mode'];
?>
<div class="khabar" id="<?php echo esc_attr( $khabar_uid ); ?>"
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
		<button type="button" class="khabar-link khabar-open" data-mode="price">📉 <?php echo esc_html( $s['button_text_price'] ); ?></button>
	</div>
	<?php endif; ?>

	<div class="khabar-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $khabar_uid ); ?>-title" hidden>
		<div class="khabar-modal-box">
			<button type="button" class="khabar-close" aria-label="<?php esc_attr_e( 'بستن', 'khabar' ); ?>">×</button>
			<h3 id="<?php echo esc_attr( $khabar_uid ); ?>-title"><?php esc_html_e( 'خبرم کن', 'khabar' ); ?></h3>
			<p class="khabar-product-name"><strong><?php echo esc_html( $product->get_name() ); ?></strong> <span class="khabar-variation-label"></span></p>

			<form class="khabar-form" novalidate>
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
				<input type="hidden" name="variation_id" value="0">
				<div class="khabar-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

				<?php if ( $attributes ) : ?>
				<fieldset class="khabar-attrs">
					<legend><?php esc_html_e( 'ویژگی‌های مورد انتظار شما', 'khabar' ); ?></legend>
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
					<legend><?php esc_html_e( 'چه زمانی خبرتان کنیم؟', 'khabar' ); ?></legend>
					<?php if ( $khabar_has_stock ) : ?>
						<label class="khabar-cond"><input type="checkbox" name="in_stock" value="1"> <?php esc_html_e( 'وقتی موجود شد', 'khabar' ); ?></label>
					<?php endif; ?>
					<?php if ( in_array( 'price_drop', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" data-toggle="price_below"> <?php esc_html_e( 'وقتی قیمت رسید به کمتر از', 'khabar' ); ?>
							<input type="text" inputmode="numeric" name="price_below" class="khabar-num" placeholder="<?php echo esc_attr( $price ? wc_format_localized_price( round( $price * 0.9, wc_get_price_decimals() ) ) : '' ); ?>" disabled> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'price_rise', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" data-toggle="price_above"> <?php esc_html_e( 'وقتی قیمت بیشتر شد از', 'khabar' ); ?>
							<input type="text" inputmode="numeric" name="price_above" class="khabar-num" disabled> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'price_change', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" name="price_change" value="1"> <?php esc_html_e( 'هر بار قیمت تغییر کرد', 'khabar' ); ?></label>
					<?php endif; ?>
					<?php if ( in_array( 'min_qty', $types, true ) ) : ?>
						<label class="khabar-cond"><input type="checkbox" data-toggle="min_qty"> <?php esc_html_e( 'موجودی حداقل', 'khabar' ); ?>
							<input type="text" inputmode="numeric" name="min_qty" class="khabar-num khabar-num-sm" disabled> <?php esc_html_e( 'عدد شد', 'khabar' ); ?>
						</label>
					<?php endif; ?>
					<div class="khabar-mode" hidden>
						<label><input type="radio" name="mode" value="all" checked> <?php esc_html_e( 'وقتی همه شرایط با هم برقرار شد', 'khabar' ); ?></label>
						<label><input type="radio" name="mode" value="separate"> <?php esc_html_e( 'برای هر شرط جداگانه', 'khabar' ); ?></label>
					</div>
				</fieldset>

				<fieldset class="khabar-contact">
					<label><span><?php esc_html_e( 'نام (اختیاری)', 'khabar' ); ?></span><input type="text" name="name" value="<?php echo esc_attr( $prefill['name'] ); ?>" autocomplete="name"></label>
					<?php if ( 'email' !== $khabar_contact ) : ?>
						<label><span><?php esc_html_e( 'شماره موبایل', 'khabar' ); ?></span><input type="tel" name="phone" dir="ltr" value="<?php echo esc_attr( $prefill['phone'] ); ?>" placeholder="09xxxxxxxxx" autocomplete="tel" <?php echo in_array( $khabar_contact, array( 'phone', 'both' ), true ) ? 'required' : ''; ?>></label>
					<?php endif; ?>
					<?php if ( 'phone' !== $khabar_contact ) : ?>
						<label><span><?php esc_html_e( 'ایمیل', 'khabar' ); ?></span><input type="email" name="email" dir="ltr" value="<?php echo esc_attr( $prefill['email'] ); ?>" autocomplete="email" <?php echo in_array( $khabar_contact, array( 'email', 'both' ), true ) ? 'required' : ''; ?>></label>
					<?php endif; ?>
				</fieldset>

				<?php if ( $s['user_selects_channel'] && count( $channels ) > 1 ) : ?>
				<fieldset class="khabar-channels">
					<legend><?php esc_html_e( 'از چه راهی خبرتان کنیم؟', 'khabar' ); ?></legend>
					<?php foreach ( $channels as $khabar_ch => $khabar_label ) : ?>
						<label><input type="checkbox" name="channels[]" value="<?php echo esc_attr( $khabar_ch ); ?>" <?php checked( in_array( $khabar_ch, array( 'sms', 'email', 'onsite', 'telegram', 'bale' ), true ) ); ?>> <?php echo esc_html( $khabar_label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>

				<p class="khabar-msg" role="alert" hidden></p>
				<button type="submit" class="button alt khabar-submit"><?php esc_html_e( 'ثبت درخواست', 'khabar' ); ?></button>
				<?php if ( $s['privacy_text'] ) : ?>
					<p class="khabar-privacy"><?php echo esc_html( $s['privacy_text'] ); ?></p>
				<?php endif; ?>
			</form>

			<form class="khabar-otp" hidden novalidate>
				<p class="khabar-otp-text"></p>
				<input type="hidden" name="contact">
				<label><span><?php esc_html_e( 'کد تایید', 'khabar' ); ?></span><input type="text" name="code" inputmode="numeric" dir="ltr" autocomplete="one-time-code" maxlength="6"></label>
				<p class="khabar-msg" role="alert" hidden></p>
				<button type="submit" class="button alt"><?php esc_html_e( 'تایید', 'khabar' ); ?></button>
			</form>

			<div class="khabar-done" hidden>
				<div class="khabar-done-icon">✅</div>
				<p class="khabar-done-text"></p>
				<?php if ( $messengers ) : ?>
					<div class="khabar-connect" data-networks="<?php echo esc_attr( wp_json_encode( $messengers ) ); ?>" hidden></div>
				<?php endif; ?>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( Khabar_Account::ENDPOINT ) ); ?>"><?php esc_html_e( 'مشاهده خبرم کن‌های من', 'khabar' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
