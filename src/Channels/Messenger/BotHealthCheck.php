<?php
/**
 * Health check of a bot straight from the messenger API (token, webhook, linked customers, channel access).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Messenger;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
/**
 * Health check of a bot straight from the messenger API (token, webhook, linked customers, channel access).
 */
final class BotHealthCheck {

	/**
	 * Health check of a bot, straight from the messenger's API: token, bot username, webhook
	 * registration and its last delivery error, linked customers and channel access.
	 *
	 * @param string $network telegram|bale.
	 * @return array[] [ level (ok|warn|error), text ]
	 */
	public static function check( $network ) {
		$n   = MessengerConfig::network( $network );
		$out = array();
		$safir = MessengerConfig::phone_ready( $network );
		if ( $safir ) {
			/* translators: %d bot id */
			$out[] = array( 'ok', sprintf( __( 'سفیر تنظیم شده (بازو %d): اعلان شخصی مستقیم به شماره موبایل مشتری در بله ارسال می‌شود و Start لازم نیست. صحت کلید را با «ارسال آزمایشی ← بله (شماره موبایل)» بسنجید.', 'yadambiar' ), MessengerConfig::safir_bot_id() ) );
		} elseif ( 'bale' === $network && Settings::get( 'bale_safir_key' ) && ! MessengerConfig::safir_bot_id() ) {
			$out[] = array( 'error', __( 'کلید سفیر وارد شده ولی شناسه بازو (bot_id) مشخص نیست؛ آن را وارد کنید یا توکن ربات را بگذارید.', 'yadambiar' ) );
		} elseif ( 'bale' === $network ) {
			$out[] = array( 'warn', __( 'سفیر تنظیم نشده؛ پیام شخصی فقط به مشتریانی می‌رسد که ربات را Start کرده‌اند. برای ارسال مستقیم به شماره موبایل، کلید سفیر را وارد کنید.', 'yadambiar' ) );
		}
		if ( ! $n || ! $n['token'] ) {
			$out[] = $safir
				? array( 'warn', __( 'توکن ربات وارد نشده؛ فقط برای اعلام در کانال بله لازم است.', 'yadambiar' ) )
				: array( 'error', __( 'توکن ربات وارد نشده است.', 'yadambiar' ) );
			return $out;
		}
		$me = BotApi::api( $network, 'getMe', array() );
		if ( is_wp_error( $me ) ) {
			/* translators: %s error */
			return array( array( 'error', sprintf( __( 'ربات پاسخ نداد (توکن اشتباه است یا سایت به سرور پیام‌رسان دسترسی ندارد): %s', 'yadambiar' ), $me->get_error_message() ) ) );
		}
		$username = isset( $me['username'] ) ? (string) $me['username'] : '';
		/* translators: %s bot username */
		$out[] = array( 'ok', sprintf( __( 'توکن درست است؛ ربات ‎@%s پاسخ داد.', 'yadambiar' ), $username ) );
		if ( ! $n['bot'] && ! $safir ) {
			$out[] = array( 'error', __( 'نام کاربری ربات وارد نشده؛ دکمه «دریافت در پیام‌رسان» به مشتری نمایش داده نمی‌شود.', 'yadambiar' ) );
		} elseif ( $username && 0 !== strcasecmp( $username, $n['bot'] ) ) {
			/* translators: 1: username in settings 2: username of the token's bot */
			$out[] = array( 'error', sprintf( __( 'نام کاربری ربات در تنظیمات (%1$s) با ربات این توکن (%2$s) یکی نیست؛ مشتری به ربات اشتباه فرستاده می‌شود.', 'yadambiar' ), $n['bot'], $username ) );
		}
		if ( ! Settings::has( 'channels_enabled', $network ) ) {
			$out[] = array( 'error', __( 'این پیام‌رسان در «کانال‌های فعال» (بالای همین صفحه) تیک نخورده؛ پیام شخصی ارسال نمی‌شود.', 'yadambiar' ) );
		}
		if ( 0 !== strpos( rest_url(), 'https://' ) ) {
			$out[] = array( 'error', __( 'آدرس سایت HTTPS نیست؛ پیام‌رسان‌ها وب‌هوک بدون HTTPS را قبول نمی‌کنند.', 'yadambiar' ) );
		}

		$info = BotApi::api( $network, 'getWebhookInfo', array() );
		if ( is_wp_error( $info ) ) {
			/* translators: %s error */
			$out[] = array( 'warn', sprintf( __( 'وضعیت وب‌هوک دریافت نشد: %s', 'yadambiar' ), $info->get_error_message() ) );
		} else {
			$url = isset( $info['url'] ) ? (string) $info['url'] : '';
			if ( '' === $url ) {
				$out[] = array( 'error', __( 'وب‌هوک ثبت نشده است؛ دکمه «ثبت وب‌هوک ربات‌ها» را بزنید. بدون آن، Start مشتری به سایت نمی‌رسد و حسابش متصل نمی‌شود.', 'yadambiar' ) );
			} elseif ( $url !== BotApi::webhook_url( $network ) ) {
				$out[] = array( 'error', __( 'وب‌هوک ربات به آدرس دیگری ثبت شده (مثلاً سایت یا افزونه دیگری)؛ دکمه «ثبت وب‌هوک ربات‌ها» را بزنید.', 'yadambiar' ) );
			} else {
				$out[] = array( 'ok', __( 'وب‌هوک درست ثبت شده است.', 'yadambiar' ) );
			}
			if ( ! empty( $info['last_error_message'] ) ) {
				$when  = ! empty( $info['last_error_date'] ) ? wp_date( 'Y/m/d H:i', (int) $info['last_error_date'] ) : '';
				/* translators: 1: date 2: error */
				$out[] = array( 'error', sprintf( __( 'پیام‌رسان آخرین بار نتوانست پیام کاربر را به سایت برساند (%1$s): %2$s — معمولاً فایروال، افزونه امنیتی یا مسدود بودن REST API سایت.', 'yadambiar' ), $when, $info['last_error_message'] ) );
			}
			if ( ! empty( $info['pending_update_count'] ) ) {
				/* translators: %d count */
				$out[] = array( 'warn', sprintf( __( '%d پیام کاربر در صف ربات مانده و هنوز به سایت نرسیده.', 'yadambiar' ), (int) $info['pending_update_count'] ) );
			}
		}

		$linked = $safir ? -1 : LinkRepository::count( $network );
		$out[]  = $safir ? array( 'ok', __( 'با سفیر نیازی به اتصال (Start) مشتریان نیست.', 'yadambiar' ) ) : ( $linked
			/* translators: %d customers */
			? array( 'ok', sprintf( __( '%d مشتری حسابش را به ربات متصل کرده است.', 'yadambiar' ), $linked ) )
			: array( 'warn', __( 'هنوز هیچ مشتری‌ای متصل نشده. پیام شخصی فقط به مشتریانی می‌رسد که بعد از ثبت درخواست روی «دریافت در پیام‌رسان» زده و در ربات Start را زده باشند.', 'yadambiar' ) ) );

		if ( $n['channel'] ) {
			$chat = BotApi::api( $network, 'getChat', array( 'chat_id' => $n['channel'] ) );
			if ( is_wp_error( $chat ) ) {
				/* translators: 1: channel 2: error */
				$out[] = array( 'error', sprintf( __( 'کانال %1$s در دسترس ربات نیست: %2$s — شناسه را بررسی کنید و ربات را ادمین کانال کنید.', 'yadambiar' ), $n['channel'], $chat->get_error_message() ) );
			} else {
				$member = ! empty( $me['id'] ) ? BotApi::api( $network, 'getChatMember', array( 'chat_id' => $n['channel'], 'user_id' => $me['id'] ) ) : null;
				$status = is_array( $member ) && isset( $member['status'] ) ? $member['status'] : '';
				if ( $status && ! in_array( $status, array( 'administrator', 'creator' ), true ) ) {
					/* translators: %s channel */
					$out[] = array( 'error', sprintf( __( 'ربات در کانال %s ادمین نیست؛ برای انتشار پست باید ادمین با اجازه ارسال پیام باشد.', 'yadambiar' ), $n['channel'] ) );
				} else {
					/* translators: 1: channel 2: title */
					$out[] = array( 'ok', sprintf( __( 'کانال %1$s («%2$s») در دسترس ربات است.', 'yadambiar' ), $n['channel'], isset( $chat['title'] ) ? $chat['title'] : '' ) );
				}
			}
		}
		return $out;
	}
}
