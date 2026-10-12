<?php
/**
 * "یادآوری‌های من" panel.
 *
 * Available: $subs, $notes, $channels, $messengers, $token.
 *
 * @package YadamBiar
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="yadambiar-panel" data-token="<?php echo esc_attr( $token ); ?>">
	<h3><?php esc_html_e( 'محصولاتی که منتظرشان هستم', 'yadam-biar' ); ?></h3>
	<?php if ( ! empty( $messengers ) ) : ?>
		<div class="yadambiar-connect" data-networks="<?php echo esc_attr( wp_json_encode( $messengers ) ); ?>" hidden></div>
	<?php endif; ?>
	<?php if ( ! $subs ) : ?>
		<p class="yadambiar-empty"><?php esc_html_e( 'هنوز درخواستی ثبت نکرده‌اید.', 'yadam-biar' ); ?></p>
	<?php else : ?>
		<ul class="yadambiar-sub-list">
			<?php
			foreach ( $subs as $yadambiar_sub ) :
				$yadambiar_p    = wc_get_product( $yadambiar_sub->variation_id ? $yadambiar_sub->variation_id : $yadambiar_sub->product_id );
				$yadambiar_name = $yadambiar_p ? $yadambiar_p->get_name() : __( 'محصول حذف شده', 'yadam-biar' );
				$yadambiar_st   = \YadamBiar\Subscriptions\SubscriptionRepository::statuses();
				?>
				<li class="yadambiar-sub yadambiar-status-<?php echo esc_attr( $yadambiar_sub->status ); ?>" data-id="<?php echo esc_attr( $yadambiar_sub->id ); ?>">
					<div class="yadambiar-sub-main">
						<?php echo $yadambiar_p ? wp_kses_post( $yadambiar_p->get_image( array( 56, 56 ) ) ) : ''; ?>
						<div>
							<a class="yadambiar-sub-title" href="<?php echo esc_url( $yadambiar_p ? $yadambiar_p->get_permalink() : '#' ); ?>"><?php echo esc_html( $yadambiar_name ); ?></a>
							<?php if ( $yadambiar_sub->attributes ) : ?>
								<div class="yadambiar-sub-attrs"><?php echo esc_html( \YadamBiar\Support\Utils::attributes_label( $yadambiar_sub->attributes ) ); ?></div>
							<?php endif; ?>
							<div class="yadambiar-sub-cond"><?php echo esc_html( \YadamBiar\Subscriptions\SubscriptionRepository::conditions_label( $yadambiar_sub ) ); ?></div>
							<?php if ( $yadambiar_p ) : ?>
								<div class="yadambiar-sub-now">
									<?php
									/* translators: 1: price 2: stock */
									printf( esc_html__( 'الان: %1$s — %2$s', 'yadam-biar' ), esc_html( \YadamBiar\Support\Utils::price_text( $yadambiar_p->get_price() ) ), $yadambiar_p->is_in_stock() ? esc_html__( 'موجود', 'yadam-biar' ) : esc_html__( 'ناموجود', 'yadam-biar' ) );
									?>
								</div>
							<?php endif; ?>
						</div>
						<span class="yadambiar-badge"><?php echo esc_html( isset( $yadambiar_st[ $yadambiar_sub->status ] ) ? $yadambiar_st[ $yadambiar_sub->status ] : $yadambiar_sub->status ); ?></span>
					</div>
					<div class="yadambiar-sub-actions">
						<button type="button" class="yadambiar-link yadambiar-edit"><?php esc_html_e( 'ویرایش', 'yadam-biar' ); ?></button>
						<button type="button" class="yadambiar-link yadambiar-delete"><?php esc_html_e( 'حذف', 'yadam-biar' ); ?></button>
					</div>
					<form class="yadambiar-edit-form" hidden>
						<?php if ( null !== $yadambiar_sub->price_below ) : ?>
							<label><span><?php esc_html_e( 'قیمت هدف (کمتر از)', 'yadam-biar' ); ?></span><input type="text" inputmode="numeric" name="price_below" value="<?php echo esc_attr( wc_format_decimal( $yadambiar_sub->price_below, wc_get_price_decimals() ) ); ?>"></label>
						<?php endif; ?>
						<?php if ( null !== $yadambiar_sub->price_above ) : ?>
							<label><span><?php esc_html_e( 'قیمت (بیشتر از)', 'yadam-biar' ); ?></span><input type="text" inputmode="numeric" name="price_above" value="<?php echo esc_attr( wc_format_decimal( $yadambiar_sub->price_above, wc_get_price_decimals() ) ); ?>"></label>
						<?php endif; ?>
						<?php if ( $yadambiar_sub->min_qty ) : ?>
							<label><span><?php esc_html_e( 'حداقل موجودی', 'yadam-biar' ); ?></span><input type="text" inputmode="numeric" name="min_qty" value="<?php echo esc_attr( $yadambiar_sub->min_qty ); ?>"></label>
						<?php endif; ?>
						<label><span><?php esc_html_e( 'موبایل', 'yadam-biar' ); ?></span><input type="tel" dir="ltr" name="phone" value="<?php echo esc_attr( $yadambiar_sub->phone ); ?>"></label>
						<label><span><?php esc_html_e( 'ایمیل', 'yadam-biar' ); ?></span><input type="email" dir="ltr" name="email" value="<?php echo esc_attr( $yadambiar_sub->email ); ?>"></label>
						<?php if ( \YadamBiar\Settings\Settings::get( 'user_selects_channel' ) && count( $channels ) > 1 ) : ?>
							<div class="yadambiar-channels">
								<?php foreach ( $channels as $yadambiar_ch => $yadambiar_label ) : ?>
									<label><input type="checkbox" name="channels[]" value="<?php echo esc_attr( $yadambiar_ch ); ?>" <?php checked( ! $yadambiar_sub->channel_list || in_array( $yadambiar_ch, $yadambiar_sub->channel_list, true ) ); ?>> <?php echo esc_html( $yadambiar_label ); ?></label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<p class="yadambiar-msg" hidden></p>
						<button type="submit" class="button"><?php esc_html_e( 'ذخیره تغییرات', 'yadam-biar' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $notes ) : ?>
		<h3><?php esc_html_e( 'اعلان‌های اخیر', 'yadam-biar' ); ?></h3>
		<ul class="yadambiar-notes">
			<?php foreach ( $notes as $yadambiar_note ) : ?>
				<li class="<?php echo $yadambiar_note->is_read ? '' : 'yadambiar-unread'; ?>">
					<a href="<?php echo esc_url( $yadambiar_note->url ); ?>"><strong><?php echo esc_html( $yadambiar_note->title ); ?></strong></a>
					<span><?php /* translators: %s time */ printf( esc_html__( '%s پیش', 'yadam-biar' ), esc_html( human_time_diff( strtotime( $yadambiar_note->created_at . ' UTC' ) ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
