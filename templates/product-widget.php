<?php
/**
 * Product page widget.
 *
 * Available: $product, $s, $types, $variable, $in_stock, $any_oos, $waiting, $total,
 * $show_count, $prefill, $channels, $messengers, $attributes, $price.
 *
 * Override by copying to yourtheme/yadambiar/product-widget.php.
 *
 * @package YadamBiar
 */

defined( 'ABSPATH' ) || exit;

$yadambiar_uid        = isset( $yadambiar_uid ) ? $yadambiar_uid : 'yadambiar-' . $product->get_id();
$yadambiar_vars       = isset( $yadambiar_vars ) ? $yadambiar_vars : '';
$yadambiar_has_stock  = in_array( 'stock', $types, true );
// "When back in stock" only makes sense if something is out of stock right now.
$yadambiar_stock_opt  = $yadambiar_has_stock && ( $variable ? $any_oos : ! $in_stock );
$yadambiar_has_price  = array_intersect( array( 'price_drop', 'price_rise', 'price_change' ), $types );
$yadambiar_contact    = $s['contact_mode'];
$yadambiar_sel        = $s['user_selects_channel'] && count( $channels ) > 1;
?>
<div class="yadambiar" id="<?php echo esc_attr( $yadambiar_uid ); ?>"<?php echo ! empty( $yadambiar_vars ) ? ' style="' . esc_attr( $yadambiar_vars ) . '"' : ''; ?>
	data-product="<?php echo esc_attr( $product->get_id() ); ?>"
	data-variable="<?php echo $variable ? '1' : '0'; ?>"
	data-instock="<?php echo $in_stock ? '1' : '0'; ?>"
	data-price="<?php echo esc_attr( $price ); ?>"
	data-waiting="<?php echo esc_attr( wp_json_encode( (object) $waiting ) ); ?>"
	data-show-count="<?php echo $show_count ? '1' : '0'; ?>"
	data-count-min="<?php echo esc_attr( (int) $s['waiting_count_min'] ); ?>">

	<?php if ( $yadambiar_has_stock ) : ?>
	<div class="yadambiar-cta yadambiar-cta-stock" <?php echo ( ! $in_stock && ! $variable ) || ( $variable && ! $in_stock ) ? '' : 'hidden'; ?>>
		<button type="button" class="button yadambiar-open" data-mode="stock"><?php echo esc_html( $s['button_text_stock'] ); ?></button>
		<p class="yadambiar-waiting" <?php echo $show_count ? '' : 'hidden'; ?>>
			<?php
			/* translators: %s number of people */
			printf( esc_html__( '%s نفر منتظر این کالا هستند', 'yadambiar' ), '<span>' . esc_html( number_format_i18n( $total ) ) . '</span>' );
			?>
		</p>
	</div>
	<?php endif; ?>

	<?php if ( $yadambiar_has_price && $s['show_price_alert'] ) : ?>
	<div class="yadambiar-cta yadambiar-cta-price" <?php echo $in_stock ? '' : 'hidden'; ?>>
		<button type="button" class="yadambiar-price-btn yadambiar-open" data-mode="price"><span class="yadambiar-price-ic"><?php echo \YadamBiar\Support\Icon::svg( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span><?php echo esc_html( $s['button_text_price'] ); ?></span></button>
	</div>
	<?php endif; ?>

	<div class="yadambiar-modal"<?php echo ! empty( $yadambiar_vars ) ? ' style="' . esc_attr( $yadambiar_vars ) . '"' : ''; ?> role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $yadambiar_uid ); ?>-title" hidden>
		<div class="yadambiar-modal-box">
			<span class="yadambiar-grab" aria-hidden="true"></span>
			<header class="yadambiar-head">
				<span class="yadambiar-thumb"><?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></span>
				<div class="yadambiar-head-text">
					<h3 id="<?php echo esc_attr( $yadambiar_uid ); ?>-title"><?php esc_html_e( 'یادم بیار', 'yadambiar' ); ?></h3>
					<p class="yadambiar-product-name"><strong><?php echo esc_html( $product->get_name() ); ?></strong> <span class="yadambiar-variation-label"></span></p>
				</div>
				<button type="button" class="yadambiar-close" aria-label="<?php esc_attr_e( 'بستن', 'yadambiar' ); ?>"><?php echo \YadamBiar\Support\Icon::svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</header>

			<form class="yadambiar-form" novalidate autocomplete="off">
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
				<input type="hidden" name="variation_id" value="0">
				<div class="yadambiar-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

				<?php if ( $attributes ) : ?>
				<fieldset class="yadambiar-attrs">
					<legend><span><?php esc_html_e( 'ویژگی‌های مورد انتظار شما', 'yadambiar' ); ?></span></legend>
					<?php foreach ( $attributes as $yadambiar_key => $yadambiar_attr ) : ?>
						<label>
							<span><?php echo esc_html( $yadambiar_attr['label'] ); ?></span>
							<select name="attributes[<?php echo esc_attr( $yadambiar_key ); ?>]" data-attr="<?php echo esc_attr( $yadambiar_key ); ?>">
								<option value=""><?php esc_html_e( 'هر کدام', 'yadambiar' ); ?></option>
								<?php foreach ( $yadambiar_attr['options'] as $yadambiar_slug => $yadambiar_name ) : ?>
									<option value="<?php echo esc_attr( $yadambiar_slug ); ?>"><?php echo esc_html( $yadambiar_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>

				<fieldset class="yadambiar-conditions">
					<legend><?php echo \YadamBiar\Support\Icon::svg( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'چه زمانی خبرتان کنیم؟', 'yadambiar' ); ?></span></legend>
					<?php if ( $yadambiar_stock_opt ) : ?>
						<label class="yadambiar-cond"><input type="checkbox" name="in_stock" value="1"><span class="yadambiar-cond-text"><?php esc_html_e( 'وقتی موجود شد', 'yadambiar' ); ?></span><span class="yadambiar-cond-ic"><?php echo \YadamBiar\Support\Icon::svg( 'box' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
					<?php if ( in_array( 'price_drop', $types, true ) ) : ?>
						<label class="yadambiar-cond"><input type="checkbox" data-toggle="price_below"><span class="yadambiar-cond-text"><?php esc_html_e( 'وقتی قیمت رسید به کمتر از', 'yadambiar' ); ?></span>
							<input type="text" inputmode="numeric" name="price_below" class="yadambiar-num" placeholder="<?php echo esc_attr( $price ? wc_format_localized_price( round( $price * 0.9, wc_get_price_decimals() ) ) : '' ); ?>" disabled><span class="yadambiar-cur"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
						<span class="yadambiar-cond-ic"><?php echo \YadamBiar\Support\Icon::svg( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'price_rise', $types, true ) ) : ?>
						<label class="yadambiar-cond"><input type="checkbox" data-toggle="price_above"><span class="yadambiar-cond-text"><?php esc_html_e( 'وقتی قیمت بیشتر شد از', 'yadambiar' ); ?></span>
							<span class="yadambiar-cur"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="text" inputmode="numeric" name="price_above" class="yadambiar-num" disabled>
						<span class="yadambiar-cond-ic"><?php echo \YadamBiar\Support\Icon::svg( 'up' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</label>
					<?php endif; ?>
					<?php if ( in_array( 'price_change', $types, true ) ) : ?>
						<label class="yadambiar-cond"><input type="checkbox" name="price_change" value="1"><span class="yadambiar-cond-text"><?php esc_html_e( 'هر بار قیمت تغییر کرد', 'yadambiar' ); ?></span><span class="yadambiar-cond-ic"><?php echo \YadamBiar\Support\Icon::svg( 'chart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
					<?php if ( in_array( 'min_qty', $types, true ) ) : ?>
						<label class="yadambiar-cond"><input type="checkbox" data-toggle="min_qty"><span class="yadambiar-cond-text"><?php esc_html_e( 'موجودی حداقل', 'yadambiar' ); ?></span>
							<span class="yadambiar-cur"><?php esc_html_e( 'عدد شد', 'yadambiar' ); ?></span><input type="text" inputmode="numeric" name="min_qty" class="yadambiar-num yadambiar-num-sm" disabled>
						<span class="yadambiar-cond-ic"><?php echo \YadamBiar\Support\Icon::svg( 'cube' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</label>
					<?php endif; ?>
					<div class="yadambiar-mode" hidden>
						<label><input type="radio" name="mode" value="all" checked><span><?php esc_html_e( 'وقتی همه شرایط با هم برقرار شد', 'yadambiar' ); ?></span></label>
						<label><input type="radio" name="mode" value="separate"><span><?php esc_html_e( 'برای هر شرط جداگانه', 'yadambiar' ); ?></span></label>
					</div>
				</fieldset>

				<?php if ( $yadambiar_sel ) : ?>
				<fieldset class="yadambiar-channels">
					<legend><?php echo \YadamBiar\Support\Icon::svg( 'send' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'از چه راهی خبرتان کنیم؟', 'yadambiar' ); ?></span></legend>
					<?php foreach ( $channels as $yadambiar_ch => $yadambiar_label ) : ?>
						<label><input type="checkbox" name="channels[]" value="<?php echo esc_attr( $yadambiar_ch ); ?>" <?php checked( in_array( $yadambiar_ch, array( 'sms', 'email', 'onsite', 'telegram', 'bale' ), true ) ); ?>><?php echo \YadamBiar\Support\Icon::svg( \YadamBiar\Frontend\ProductWidget::channel_icon( $yadambiar_ch ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $yadambiar_label ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>
				<fieldset class="yadambiar-contact">
					<label><span class="yadambiar-lbl"><?php esc_html_e( 'نام (اختیاری)', 'yadambiar' ); ?></span><span class="yadambiar-field"><input type="text" name="name" value="<?php echo esc_attr( $prefill['name'] ); ?>" autocomplete="name"><?php echo \YadamBiar\Support\Icon::svg( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php if ( $yadambiar_sel || 'email' !== $yadambiar_contact ) : ?>
						<label class="yadambiar-f-phone" data-for="sms<?php echo \YadamBiar\Channels\Messenger\MessengerChannel::phone_ready( 'bale' ) ? ' bale' : ''; ?>"><span class="yadambiar-lbl"><?php esc_html_e( 'شماره موبایل', 'yadambiar' ); ?></span><span class="yadambiar-field"><input type="tel" name="phone" dir="ltr" value="<?php echo esc_attr( $prefill['phone'] ); ?>" placeholder="09xxxxxxxxx" autocomplete="tel" <?php echo in_array( $yadambiar_contact, array( 'phone', 'both' ), true ) ? 'required' : ''; ?>><?php echo \YadamBiar\Support\Icon::svg( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
					<?php if ( $yadambiar_sel || 'phone' !== $yadambiar_contact ) : ?>
						<label class="yadambiar-f-email" data-for="email"><span class="yadambiar-lbl"><?php esc_html_e( 'ایمیل', 'yadambiar' ); ?></span><span class="yadambiar-field"><input type="email" name="email" dir="ltr" value="<?php echo esc_attr( $prefill['email'] ); ?>" autocomplete="email" <?php echo in_array( $yadambiar_contact, array( 'email', 'both' ), true ) ? 'required' : ''; ?>><?php echo \YadamBiar\Support\Icon::svg( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></label>
					<?php endif; ?>
				</fieldset>


				<p class="yadambiar-msg" role="alert" hidden></p>
				<button type="submit" class="button alt yadambiar-submit"><span><?php esc_html_e( 'ثبت درخواست', 'yadambiar' ); ?></span><?php echo \YadamBiar\Support\Icon::svg( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</form>

			<form class="yadambiar-otp" hidden novalidate>
				<p class="yadambiar-otp-text"></p>
				<input type="hidden" name="contact">
				<label><span><?php esc_html_e( 'کد تایید', 'yadambiar' ); ?></span><input type="text" name="code" inputmode="numeric" dir="ltr" autocomplete="one-time-code" maxlength="6"></label>
				<p class="yadambiar-msg" role="alert" hidden></p>
				<button type="submit" class="button alt"><?php esc_html_e( 'تایید', 'yadambiar' ); ?></button>
			</form>

			<div class="yadambiar-done" hidden>
				<div class="yadambiar-done-badge" aria-hidden="true">
					<svg viewBox="0 0 52 52" width="52" height="52" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path class="yadambiar-done-check" d="M14 27l8 8 16-17"/></svg>
				</div>
				<h4 class="yadambiar-done-title"><?php esc_html_e( 'عالی، ثبت شد!', 'yadambiar' ); ?></h4>
				<p class="yadambiar-done-text"></p>
				<?php if ( $messengers ) : ?>
					<div class="yadambiar-connect" data-networks="<?php echo esc_attr( wp_json_encode( $messengers ) ); ?>" hidden></div>
				<?php endif; ?>
				<div class="yadambiar-done-actions">
					<?php if ( is_user_logged_in() ) : ?>
						<a class="yadambiar-btn yadambiar-btn-primary" href="<?php echo esc_url( wc_get_account_endpoint_url( \YadamBiar\Frontend\Account::ENDPOINT ) ); ?>"><?php esc_html_e( 'مشاهده یادآوری‌های من', 'yadambiar' ); ?></a>
					<?php endif; ?>
					<button type="button" class="yadambiar-btn <?php echo is_user_logged_in() ? 'yadambiar-btn-ghost' : 'yadambiar-btn-primary'; ?> yadambiar-close-done"><?php esc_html_e( 'متوجه شدم', 'yadambiar' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
