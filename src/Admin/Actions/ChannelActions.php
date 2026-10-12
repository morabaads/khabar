<?php
/**
 * Admin actions of the channels tab: test send, SMS account/delivery checks, messenger bots.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Actions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Admin\UserState;
use YadamBiar\Channels\EmailChannel;
use YadamBiar\Channels\Messenger\BotApi;
use YadamBiar\Channels\Messenger\BotHealthCheck;
use YadamBiar\Channels\Messenger\MessengerConfig;
use YadamBiar\Channels\Messenger\SafirClient;
use YadamBiar\Channels\Sms\PatternRepository;
use YadamBiar\Channels\Sms\SmsChannel;
use YadamBiar\Settings\Catalog;
use YadamBiar\Settings\Settings;

/**
 * Channel tools handlers.
 */
final class ChannelActions extends ActionGroup {

	const TEST_CHANNELS = array( 'sms', 'email', 'telegram', 'bale', 'bale_safir' );
	const NETWORKS      = array( 'telegram', 'bale' );

	/**
	 * Actions.
	 *
	 * @return array
	 */
	protected function actions() {
		return array(
			'test'         => 'test_send',
			'sms_account'  => 'sms_account',
			'sms_status'   => 'sms_status',
			'check_bots'   => 'check_bots',
			'set_webhooks' => 'set_webhooks',
		);
	}

	/**
	 * Where every action of this group returns.
	 *
	 * @return string
	 */
	private function back_url() {
		return AdminUrl::settings( 'channels' );
	}

	/**
	 * Send a test message through one channel.
	 */
	public function test_send() {
		$channel = $this->key_arg( 'channel', 'sms' );
		$channel = in_array( $channel, self::TEST_CHANNELS, true ) ? $channel : 'sms';
		$to      = $this->text_arg( 'to' );
		if ( '' === $to ) {
			$this->done( $this->back_url(), __( 'گیرنده را وارد کنید.', 'yadambiar' ), 'error' );
		}
		/* translators: %s site */
		$text = sprintf( __( 'پیام آزمایشی یادم بیار از %s', 'yadambiar' ), get_bloginfo( 'name' ) );
		switch ( $channel ) {
			case 'email':
				$result = is_email( $to ) ? EmailChannel::send( $to, $text, '<p>' . esc_html( $text ) . '</p>' ) : new \WP_Error( 'yadambiar_email', __( 'ایمیل معتبر نیست.', 'yadambiar' ) );
				break;
			case 'bale_safir':
				$result = SafirClient::send( $to, array( 'subject' => '', 'text' => $text, 'url' => home_url( '/' ) ) );
				break;
			case 'telegram':
			case 'bale':
				$result = BotApi::send( $channel, MessengerConfig::normalize_chat( $to ), $text );
				break;
			default:
				$result = $this->test_sms( $to, $text );
		}
		if ( is_wp_error( $result ) ) {
			$this->done( $this->back_url(), $result->get_error_message(), 'error' );
		}
		$this->done( $this->back_url(), 'sms' === $channel ? __( 'سامانه پیامک درخواست را پذیرفت. وضعیت تحویل را در کادر «آخرین ارسال آزمایشی» بررسی کنید.', 'yadambiar' ) : __( 'پیام آزمایشی ارسال شد.', 'yadambiar' ) );
	}

	/**
	 * Test SMS (plain text or the OTP pattern); the outcome is kept for the diagnostics box.
	 *
	 * @param string $to   Phone.
	 * @param string $text Text.
	 * @return true|\WP_Error
	 */
	private function test_sms( $to, $text ) {
		$kind = 'otp' === $this->key_arg( 'sms_kind' ) ? 'otp' : 'text';
		if ( 'otp' === $kind ) {
			$items = PatternRepository::pattern_items();
			if ( empty( $items['otp'] ) ) {
				$this->done( $this->back_url(), __( 'برای تست پترن، ابتدا در بخش «پترن‌های پیامک» یک پترن برای رویداد «کد تایید» ثبت و ذخیره کنید.', 'yadambiar' ), 'error' );
			}
			$result = SmsChannel::send( $to, '', 'otp', array( 'code' => '12345', 'site_name' => get_bloginfo( 'name' ) ), true );
		} else {
			$result = SmsChannel::send( $to, $text, 'text' );
		}
		UserState::set(
			UserState::SMS_TEST,
			array(
				'to'       => $to,
				'kind'     => $kind,
				'time'     => time(),
				'ok'       => true === $result,
				'error'    => is_wp_error( $result ) ? $result->get_error_message() : '',
				'id'       => true === $result ? SmsChannel::last_message_id() : '',
				'response' => SmsChannel::last_response(),
			),
			DAY_IN_SECONDS
		);
		return $result;
	}

	/**
	 * SMS account check (credit / lines).
	 */
	public function sms_account() {
		$info = SmsChannel::account_info();
		if ( is_wp_error( $info ) ) {
			$this->done( $this->back_url(), $info->get_error_message(), 'error' );
		}
		$parts = array();
		foreach ( $info as $label => $value ) {
			$parts[] = $label . ': ' . $value;
		}
		$this->done( $this->back_url(), __( 'اتصال برقرار است.', 'yadambiar' ) . ' ' . implode( ' — ', $parts ) );
	}

	/**
	 * Delivery status of the last test SMS.
	 */
	public function sms_status() {
		$test = UserState::get( UserState::SMS_TEST );
		if ( empty( $test['id'] ) ) {
			$this->done( $this->back_url(), __( 'شناسه پیامکی برای بررسی وجود ندارد.', 'yadambiar' ), 'error' );
		}
		$status = SmsChannel::delivery_status( $test['id'] );
		if ( is_wp_error( $status ) ) {
			$this->done( $this->back_url(), $status->get_error_message(), 'error' );
		}
		$this->done( $this->back_url(), __( 'وضعیت تحویل:', 'yadambiar' ) . ' ' . $status );
	}

	/**
	 * Check the bots against the messengers' APIs; the result is shown in the bots box.
	 */
	public function check_bots() {
		$out = array();
		foreach ( self::NETWORKS as $network ) {
			if ( Settings::get( $network . '_token' ) || Settings::has( 'channels_enabled', $network ) ) {
				$out[ $network ] = BotHealthCheck::check( $network );
			}
		}
		UserState::set( UserState::BOT_CHECK, $out, 10 * MINUTE_IN_SECONDS );
		if ( ! $out ) {
			$this->done( $this->back_url(), __( 'هیچ رباتی تنظیم نشده است.', 'yadambiar' ), 'error' );
		}
		$this->done( $this->back_url(), __( 'بررسی ربات‌ها انجام شد؛ نتیجه در بخش «ربات‌های پیام‌رسان» است.', 'yadambiar' ) );
	}

	/**
	 * Register the Telegram / Bale webhooks.
	 */
	public function set_webhooks() {
		$results = BotApi::register_webhooks();
		if ( ! $results ) {
			$this->done( $this->back_url(), __( 'هیچ توکن رباتی وارد نشده است.', 'yadambiar' ), 'error' );
		}
		$labels = Catalog::channels();
		$msgs   = array();
		$error  = false;
		foreach ( $results as $network => $result ) {
			$error  = $error || is_wp_error( $result );
			$msgs[] = $labels[ $network ] . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : __( 'وب‌هوک ثبت شد', 'yadambiar' ) );
		}
		$this->done( $this->back_url(), implode( ' | ', $msgs ), $error ? 'error' : 'success' );
	}
}
