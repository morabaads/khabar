<?php
/**
 * Tools under the channels tab: test send, SMS diagnostics and messenger bots.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\SettingsForm;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Ui\Form;
use YadamBiar\Admin\UserState;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Settings\Catalog;
use YadamBiar\Settings\Settings;

/**
 * Channels tab tools.
 */
final class ChannelTools {

	/**
	 * Print the tools card.
	 */
	public function render() {
		?>
		<div class="yadambiar-card">
			<h2><?php esc_html_e( 'ارسال آزمایشی', 'yadambiar' ); ?></h2>
			<?php Form::open( 'test', 'yadambiar-inline-form' ); ?>
				<select name="channel">
					<option value="sms"><?php esc_html_e( 'پیامک', 'yadambiar' ); ?></option>
					<option value="email"><?php esc_html_e( 'ایمیل', 'yadambiar' ); ?></option>
					<option value="telegram"><?php esc_html_e( 'تلگرام (chat id یا @channel)', 'yadambiar' ); ?></option>
					<option value="bale_safir"><?php esc_html_e( 'بله — شماره موبایل (سفیر)', 'yadambiar' ); ?></option>
					<option value="bale"><?php esc_html_e( 'بله (chat id یا @channel)', 'yadambiar' ); ?></option>
				</select>
				<select name="sms_kind" title="<?php esc_attr_e( 'نوع پیامک آزمایشی', 'yadambiar' ); ?>">
					<option value="text"><?php esc_html_e( 'پیامک متنی', 'yadambiar' ); ?></option>
					<option value="otp"><?php esc_html_e( 'پترن کد تایید (خط خدماتی)', 'yadambiar' ); ?></option>
				</select>
				<input type="text" name="to" dir="ltr" placeholder="09xxxxxxxxx / email" required>
				<?php submit_button( __( 'ارسال تست', 'yadambiar' ), 'secondary', '', false ); ?>
			<?php Form::close(); ?>
			<?php $this->sms_diagnostics(); ?>
			<?php $keys = PushChannel::keys(); ?>
			<div class="yadambiar-bots">
				<div class="yadambiar-bots-head">
					<div>
						<h2><?php esc_html_e( 'ربات‌های پیام‌رسان', 'yadambiar' ); ?></h2>
						<p class="description"><?php esc_html_e( 'بعد از ذخیره توکن‌ها، وب‌هوک را ثبت کنید تا Start کاربران به سایت برسد.', 'yadambiar' ); ?></p>
					</div>
					<div class="yadambiar-bots-actions">
						<?php Form::button( 'check_bots', __( 'بررسی ربات‌ها', 'yadambiar' ), 'button button-small' ); ?>
						<?php Form::button( 'set_webhooks', __( 'ثبت وب‌هوک ربات‌ها', 'yadambiar' ), 'button button-small' ); ?>
					</div>
				</div>
				<ul class="yadambiar-kv">
					<?php
					foreach ( array( 'telegram' => __( 'تلگرام', 'yadambiar' ), 'bale' => __( 'بله', 'yadambiar' ) ) as $net => $label ) :
						$has = (bool) Settings::get( $net . '_token' );
						?>
						<li>
							<span class="yadambiar-kv-label"><?php echo esc_html( $label ); ?></span>
							<code dir="ltr" title="<?php esc_attr_e( 'آدرس وب‌هوک (کلید مخفی پنهان شده)', 'yadambiar' ); ?>"><?php echo esc_html( rawurldecode( preg_replace( '#^https?://[^/]+#', '', rest_url( 'yadambiar/v1/bot/' . $net ) ) ) ); // Never shows the secret. ?></code>
							<span class="yadambiar-kv-state<?php echo $has ? ' is-ok' : ''; ?>"><?php echo $has ? esc_html__( 'توکن ثبت شده', 'yadambiar' ) : esc_html__( 'بدون توکن', 'yadambiar' ); ?></span>
						</li>
					<?php endforeach; ?>
					<li>
						<span class="yadambiar-kv-label"><?php esc_html_e( 'کلید VAPID', 'yadambiar' ); ?></span>
						<code dir="ltr" class="yadambiar-kv-copyable"><?php echo esc_html( $keys ? $keys['public'] : __( 'OpenSSL در دسترس نیست', 'yadambiar' ) ); ?></code>
						<?php if ( $keys ) : ?>
							<button type="button" class="button-link yadambiar-copy" data-copy="<?php echo esc_attr( $keys['public'] ); ?>" data-done="<?php esc_attr_e( 'کپی شد', 'yadambiar' ); ?>"><?php esc_html_e( 'کپی', 'yadambiar' ); ?></button>
						<?php endif; ?>
					</li>
				</ul>
				<?php
				$check = (array) UserState::pull( UserState::BOT_CHECK );
				if ( $check ) :
					foreach ( $check as $net => $rows ) :
						?>
						<h3 class="yadambiar-bots-check-title"><?php echo esc_html( Catalog::channels()[ $net ] ); ?></h3>
						<ul class="yadambiar-health">
							<?php foreach ( $rows as $row ) : ?>
								<li class="is-<?php echo esc_attr( $row[0] ); ?>"><?php echo esc_html( $row[1] ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php
					endforeach;
				endif;
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Last test SMS, delivery check and account check.
	 */
	private function sms_diagnostics() {
		$test = UserState::get( UserState::SMS_TEST );
		?>
		<div class="yadambiar-diag">
			<div class="yadambiar-diag-actions">
				<?php Form::button( 'sms_account', __( 'بررسی اتصال، اعتبار و خطوط', 'yadambiar' ) ); ?>
				<?php if ( ! empty( $test['id'] ) ) : ?>
					<?php Form::button( 'sms_status', __( 'بررسی وضعیت تحویل آخرین پیامک', 'yadambiar' ), 'button button-primary' ); ?>
				<?php endif; ?>
			</div>
			<?php if ( $test ) : ?>
				<div class="yadambiar-diag-last <?php echo $test['ok'] ? 'is-ok' : 'is-err'; ?>">
					<strong><?php esc_html_e( 'آخرین ارسال آزمایشی', 'yadambiar' ); ?></strong>
					<span><?php echo esc_html( $test['to'] . ' · ' . ( 'otp' === $test['kind'] ? __( 'پترن', 'yadambiar' ) : __( 'متنی', 'yadambiar' ) ) . ' · ' . wp_date( 'H:i:s', $test['time'] ) ); ?></span>
					<span><?php echo esc_html( $test['ok'] ? __( 'پذیرفته شد', 'yadambiar' ) . ( $test['id'] ? ' — ' . __( 'شناسه:', 'yadambiar' ) . ' ' . $test['id'] : '' ) : $test['error'] ); ?></span>
					<?php if ( ! empty( $test['response'] ) ) : ?>
						<details><summary><?php esc_html_e( 'پاسخ کامل سامانه پیامک', 'yadambiar' ); ?></summary><code dir="ltr"><?php echo esc_html( wp_json_encode( $test['response'], JSON_UNESCAPED_UNICODE ) ); ?></code></details>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'اگر سامانه پیامک درخواست را پذیرفت ولی پیامک نرسید، معمولاً شماره در لیست سیاه مخابرات است (پیامک متنی از خط تبلیغاتی نمی‌گیرد)، خط به حساب تعلق ندارد یا کلید API از نوع «آزمایشی/sandbox» است. «پترن کد تایید» از خط خدماتی و بدون محدودیت لیست سیاه ارسال می‌شود.', 'yadambiar' ); ?></p>
		</div>
		<?php
	}
}
