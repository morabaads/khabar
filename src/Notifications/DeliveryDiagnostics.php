<?php
/**
 * Admin diagnostics: why each waiting request of a product would (not) be notified, and system checks.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Channels\Messenger\MessengerConfig;
use YadamBiar\Channels\Sms\ProviderRegistry;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
/**
 * Admin diagnostics: why each waiting request of a product would (not) be notified, and system checks.
 */
final class DeliveryDiagnostics {

	/**
	 * Explain, per waiting subscription of a product, whether it should be notified now and through which channels.
	 *
	 * @param int $product_id Parent product id.
	 * @return array
	 */
	public static function diagnose( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}
		Reservation::$bypass = true;
		try {
			return self::explain_all( new EvaluationContext( $product ), SubscriptionRepository::open_for_product( $product_id ) );
		} finally {
			Reservation::$bypass = false;
		}
	}

	/**
	 * Rows of diagnose().
	 *
	 * @param EvaluationContext $context Context.
	 * @param object[]          $subs    Requests.
	 * @return array
	 */
	private static function explain_all( EvaluationContext $context, array $subs ) {
		$out = array();
		foreach ( $subs as $sub ) {
			$row = array( 'sub' => $sub, 'ok' => false, 'reason' => '', 'target' => '', 'channels' => array(), 'warnings' => array() );
			if ( 'pending' === $sub->status ) {
				$row['reason'] = __( 'مشتری هنوز کد تایید (OTP) را وارد نکرده؛ درخواست فعال نشده است', 'yadambiar' );
			} elseif ( $context->is_expired( $sub ) ) {
				$row['reason'] = __( 'درخواست منقضی شده است', 'yadambiar' );
			} else {
				$opts  = $context->options_for( $sub );
				$cands = $context->candidates( $sub );
				if ( ! $cands ) {
					$row['reason'] = __( 'تنوع درخواستی وجود ندارد یا منتشر نشده است', 'yadambiar' );
				}
				foreach ( $cands as $cand ) {
					$x = RuleEngine::explain( $sub, $context->snapshot( $cand ), $opts );
					if ( $x['ok'] || '' === $row['reason'] ) {
						$row['ok']     = $x['ok'];
						$row['reason'] = $x['reason'];
						$row['target'] = $cand->get_name();
					}
					if ( $x['ok'] ) {
						break;
					}
				}
			}
			$row['channels'] = ChannelManager::resolve( $sub );
			foreach ( $sub->channel_list as $ch ) {
				if ( in_array( $ch, $row['channels'], true ) ) {
					continue;
				}
				if ( 'sms' === $ch && ! $sub->phone ) {
					$row['warnings'][] = __( 'پیامک انتخاب شده ولی شماره موبایل ثبت نشده', 'yadambiar' );
				} elseif ( 'email' === $ch && ! $sub->email ) {
					$row['warnings'][] = __( 'ایمیل انتخاب شده ولی آدرس ایمیل ثبت نشده', 'yadambiar' );
				} elseif ( in_array( $ch, array( 'telegram', 'bale' ), true ) && MessengerConfig::phone_ready( $ch ) && ! $sub->phone ) {
					$row['warnings'][] = __( 'بله انتخاب شده ولی شماره موبایل ثبت نشده (سفیر به شماره موبایل ارسال می‌کند)', 'yadambiar' );
				} elseif ( in_array( $ch, array( 'telegram', 'bale' ), true ) ) {
					/* translators: %s network */
					$row['warnings'][] = sprintf( __( '%s انتخاب شده ولی مشتری ربات را Start نکرده (حساب متصل نیست)', 'yadambiar' ), 'telegram' === $ch ? __( 'تلگرام', 'yadambiar' ) : __( 'بله', 'yadambiar' ) );
				} elseif ( ! Settings::has( 'channels_enabled', $ch ) ) {
					/* translators: %s channel */
					$row['warnings'][] = sprintf( __( 'کانال «%s» در تنظیمات غیرفعال است', 'yadambiar' ), $ch );
				}
			}
			if ( $row['ok'] && ! $row['channels'] ) {
				$row['warnings'][] = __( 'شرایط برقرار است ولی هیچ کانالی برای ارسال به این مشتری در دسترس نیست', 'yadambiar' );
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * System checks that commonly stop notifications.
	 *
	 * @param int $product_id Product (for the broadcast threshold check).
	 * @return array[] [ level (ok|warn|error), text ]
	 */
	public static function health( $product_id = 0 ) {
		$out = array();
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$out[] = array( 'warn', __( 'WP-Cron در wp-config غیرفعال است؛ اگر کرون سرور تنظیم نشده باشد، صف بررسی موجودی هرگز اجرا نمی‌شود.', 'yadambiar' ) );
		}
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$late = as_get_scheduled_actions( array( 'hook' => 'yadambiar_check_product', 'status' => 'pending', 'date' => gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS ), 'date_compare' => '<=', 'per_page' => 50 ), 'ids' );
			if ( $late ) {
				/* translators: %d count */
				$out[] = array( 'error', sprintf( __( '%d بررسی در صف Action Scheduler عقب افتاده است؛ صف اجرا نمی‌شود (ووکامرس ← وضعیت ← اقدامات زمان‌بندی‌شده را ببینید یا کرون سرور را تنظیم کنید).', 'yadambiar' ), count( $late ) ) );
			} else {
				$out[] = array( 'ok', __( 'صف بررسی (Action Scheduler) عقب‌افتادگی ندارد.', 'yadambiar' ) );
			}
			$failed = as_get_scheduled_actions( array( 'hook' => 'yadambiar_check_product', 'status' => 'failed', 'per_page' => 5 ), 'ids' );
			if ( $failed ) {
				$out[] = array( 'error', __( 'بعضی بررسی‌ها با خطا متوقف شده‌اند؛ جزئیات در ووکامرس ← وضعیت ← اقدامات زمان‌بندی‌شده (گروه yadambiar).', 'yadambiar' ) );
			}
		}
		$enabled = (array) Settings::get( 'channels_enabled', array() );
		if ( in_array( 'sms', $enabled, true ) ) {
			$driver = ProviderRegistry::driver( (string) Settings::get( 'sms_gateway', '' ) );
			$cred   = in_array( $driver, ProviderRegistry::USER_DRIVERS, true ) ? Settings::get( 'sms_username' ) : Settings::get( 'sms_api_key' );
			if ( 'webhook' !== $driver && ! $cred ) {
				$out[] = array( 'error', __( 'پیامک فعال است ولی اطلاعات ورود سامانه پیامک وارد نشده.', 'yadambiar' ) );
			}
		}
		$nets = (array) Settings::get( 'broadcast_networks', array() );
		if ( $nets && $product_id ) {
			$min     = max( 1, (int) Settings::get( 'broadcast_min_waiting', 3 ) );
			$waiting = SubscriptionRepository::waiting_count( $product_id, null, true );
			if ( $waiting < $min ) {
				/* translators: 1: min 2: waiting */
				$out[] = array( 'warn', sprintf( __( 'اعلام در کانال (تلگرام/بله) فقط وقتی انجام می‌شود که دست‌کم %1$s نفر منتظر باشند و اعلان به آن‌ها ارسال شود؛ این محصول الان %2$s منتظر دارد. «حداقل تعداد منتظر» را در تنظیمات کمتر کنید.', 'yadambiar' ), number_format_i18n( $min ), number_format_i18n( $waiting ) ) );
			}
		}
		return $out;
	}
}
