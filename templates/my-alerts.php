<?php
/**
 * "خبرم کن‌های من" panel.
 *
 * Available: $subs, $notes, $channels, $messengers, $token.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="khabar-panel" data-token="<?php echo esc_attr( $token ); ?>">
	<h3><?php esc_html_e( 'محصولاتی که منتظرشان هستم', 'khabar' ); ?></h3>
	<?php if ( ! empty( $messengers ) ) : ?>
		<div class="khabar-connect" data-networks="<?php echo esc_attr( wp_json_encode( $messengers ) ); ?>" hidden></div>
	<?php endif; ?>
	<?php if ( ! $subs ) : ?>
		<p class="khabar-empty"><?php esc_html_e( 'هنوز درخواستی ثبت نکرده‌اید.', 'khabar' ); ?></p>
	<?php else : ?>
		<ul class="khabar-sub-list">
			<?php
			foreach ( $subs as $khabar_sub ) :
				$khabar_p    = wc_get_product( $khabar_sub->variation_id ? $khabar_sub->variation_id : $khabar_sub->product_id );
				$khabar_name = $khabar_p ? $khabar_p->get_name() : __( 'محصول حذف شده', 'khabar' );
				$khabar_st   = Khabar_Subscriptions::statuses();
				?>
				<li class="khabar-sub khabar-status-<?php echo esc_attr( $khabar_sub->status ); ?>" data-id="<?php echo esc_attr( $khabar_sub->id ); ?>">
					<div class="khabar-sub-main">
						<?php echo $khabar_p ? wp_kses_post( $khabar_p->get_image( array( 56, 56 ) ) ) : ''; ?>
						<div>
							<a class="khabar-sub-title" href="<?php echo esc_url( $khabar_p ? $khabar_p->get_permalink() : '#' ); ?>"><?php echo esc_html( $khabar_name ); ?></a>
							<?php if ( $khabar_sub->attributes ) : ?>
								<div class="khabar-sub-attrs"><?php echo esc_html( Khabar_Utils::attributes_label( $khabar_sub->attributes ) ); ?></div>
							<?php endif; ?>
							<div class="khabar-sub-cond"><?php echo esc_html( Khabar_Subscriptions::conditions_label( $khabar_sub ) ); ?></div>
							<?php if ( $khabar_p ) : ?>
								<div class="khabar-sub-now">
									<?php
									/* translators: 1: price 2: stock */
									printf( esc_html__( 'الان: %1$s — %2$s', 'khabar' ), esc_html( Khabar_Utils::price_text( $khabar_p->get_price() ) ), $khabar_p->is_in_stock() ? esc_html__( 'موجود', 'khabar' ) : esc_html__( 'ناموجود', 'khabar' ) );
									?>
								</div>
							<?php endif; ?>
						</div>
						<span class="khabar-badge"><?php echo esc_html( isset( $khabar_st[ $khabar_sub->status ] ) ? $khabar_st[ $khabar_sub->status ] : $khabar_sub->status ); ?></span>
					</div>
					<div class="khabar-sub-actions">
						<button type="button" class="khabar-link khabar-edit"><?php esc_html_e( 'ویرایش', 'khabar' ); ?></button>
						<button type="button" class="khabar-link khabar-delete"><?php esc_html_e( 'حذف', 'khabar' ); ?></button>
					</div>
					<form class="khabar-edit-form" hidden>
						<?php if ( null !== $khabar_sub->price_below ) : ?>
							<label><span><?php esc_html_e( 'قیمت هدف (کمتر از)', 'khabar' ); ?></span><input type="text" inputmode="numeric" name="price_below" value="<?php echo esc_attr( wc_format_decimal( $khabar_sub->price_below, wc_get_price_decimals() ) ); ?>"></label>
						<?php endif; ?>
						<?php if ( null !== $khabar_sub->price_above ) : ?>
							<label><span><?php esc_html_e( 'قیمت (بیشتر از)', 'khabar' ); ?></span><input type="text" inputmode="numeric" name="price_above" value="<?php echo esc_attr( wc_format_decimal( $khabar_sub->price_above, wc_get_price_decimals() ) ); ?>"></label>
						<?php endif; ?>
						<?php if ( $khabar_sub->min_qty ) : ?>
							<label><span><?php esc_html_e( 'حداقل موجودی', 'khabar' ); ?></span><input type="text" inputmode="numeric" name="min_qty" value="<?php echo esc_attr( $khabar_sub->min_qty ); ?>"></label>
						<?php endif; ?>
						<label><span><?php esc_html_e( 'موبایل', 'khabar' ); ?></span><input type="tel" dir="ltr" name="phone" value="<?php echo esc_attr( $khabar_sub->phone ); ?>"></label>
						<label><span><?php esc_html_e( 'ایمیل', 'khabar' ); ?></span><input type="email" dir="ltr" name="email" value="<?php echo esc_attr( $khabar_sub->email ); ?>"></label>
						<?php if ( Khabar_Settings::get( 'user_selects_channel' ) && count( $channels ) > 1 ) : ?>
							<div class="khabar-channels">
								<?php foreach ( $channels as $khabar_ch => $khabar_label ) : ?>
									<label><input type="checkbox" name="channels[]" value="<?php echo esc_attr( $khabar_ch ); ?>" <?php checked( ! $khabar_sub->channel_list || in_array( $khabar_ch, $khabar_sub->channel_list, true ) ); ?>> <?php echo esc_html( $khabar_label ); ?></label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<p class="khabar-msg" hidden></p>
						<button type="submit" class="button"><?php esc_html_e( 'ذخیره تغییرات', 'khabar' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $notes ) : ?>
		<h3><?php esc_html_e( 'اعلان‌های اخیر', 'khabar' ); ?></h3>
		<ul class="khabar-notes">
			<?php foreach ( $notes as $khabar_note ) : ?>
				<li class="<?php echo $khabar_note->is_read ? '' : 'khabar-unread'; ?>">
					<a href="<?php echo esc_url( $khabar_note->url ); ?>"><strong><?php echo esc_html( $khabar_note->title ); ?></strong></a>
					<span><?php /* translators: %s time */ printf( esc_html__( '%s پیش', 'khabar' ), esc_html( human_time_diff( strtotime( $khabar_note->created_at . ' UTC' ) ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
