<?php
/**
 * Settings schema: tabs, fields, types, defaults and visibility rules.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\ProviderRegistry;
/**
 * Settings schema: tabs, fields, types, defaults and visibility rules.
 */
final class Schema {

	/**
	 * Settings schema grouped by tab.
	 *
	 * @return array
	 */
	public static function all() {
		$schema = array(
			'general'   => array(
				'label'  => __( 'عمومی', 'yadambiar' ),
				'fields' => array(
					'enabled_types'           => array(
						'type'    => 'multicheck',
						'label'   => __( 'انواع اعلان فعال', 'yadambiar' ),
						'options' => array(
							'stock'        => __( 'موجود شدن', 'yadambiar' ),
							'price_drop'   => __( 'کاهش قیمت (قیمت هدف)', 'yadambiar' ),
							'price_rise'   => __( 'افزایش قیمت', 'yadambiar' ),
							'price_change' => __( 'هر تغییر قیمت', 'yadambiar' ),
							'min_qty'      => __( 'حداقل موجودی (قانون ترکیبی)', 'yadambiar' ),
						),
						'default' => array( 'stock', 'price_drop', 'price_rise', 'price_change', 'min_qty' ),
					),
					'contact_mode'            => array(
						'type'    => 'select',
						'label'   => __( 'اطلاعات تماس لازم', 'yadambiar' ),
						'options' => array(
							'either' => __( 'موبایل یا ایمیل (حداقل یکی)', 'yadambiar' ),
							'phone'  => __( 'فقط موبایل', 'yadambiar' ),
							'email'  => __( 'فقط ایمیل', 'yadambiar' ),
							'both'   => __( 'هر دو الزامی', 'yadambiar' ),
						),
						'default' => 'either',
					),
					'require_login'           => array(
						'type'    => 'checkbox',
						'label'   => __( 'فقط کاربران عضو بتوانند درخواست ثبت کنند', 'yadambiar' ),
						'default' => 0,
					),
					'position'                => array(
						'type'    => 'select',
						'label'   => __( 'محل نمایش در صفحه محصول', 'yadambiar' ),
						'options' => array(
							'after_cart'  => __( 'بعد از دکمه افزودن به سبد', 'yadambiar' ),
							'after_price' => __( 'بعد از قیمت', 'yadambiar' ),
							'after_meta'  => __( 'بعد از دسته‌بندی‌ها', 'yadambiar' ),
							'shortcode'   => __( 'فقط با شورت‌کد [yadambiar]', 'yadambiar' ),
						),
						'default' => 'after_cart',
					),
					'button_text_stock'       => array(
						'type'    => 'text',
						'label'   => __( 'متن دکمه موجود شدن', 'yadambiar' ),
						'default' => '🔔 یادم بیار وقتی موجود شد',
					),
					'button_text_price'       => array(
						'type'    => 'text',
						'label'   => __( 'متن دکمه هشدار قیمت', 'yadambiar' ),
						'default' => 'یادم بیار وقتی ارزان شد',
					),
					'show_price_alert'        => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش هشدار قیمت برای محصولات موجود', 'yadambiar' ),
						'default' => 1,
					),
					'annotate_variations'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'علامت‌گذاری گزینه‌های ناموجود در لیست تنوع‌ها (مثلاً «۴۲ — ناموجود 🔔»)', 'yadambiar' ),
						'default' => 1,
					),
					'show_waiting_count'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش تعداد منتظران (اثبات اجتماعی)', 'yadambiar' ),
						'default' => 1,
					),
					'waiting_count_min'       => array(
						'type'    => 'number',
						'label'   => __( 'حداقل تعداد منتظر برای نمایش', 'yadambiar' ),
						'default' => 3,
					),
					'backorder_as_in_stock'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'وضعیت «پیش‌خرید» موجود حساب شود', 'yadambiar' ),
						'default' => 0,
					),
					'price_requires_stock'    => array(
						'type'    => 'checkbox',
						'label'   => __( 'هشدار قیمت فقط وقتی کالا موجود است ارسال شود', 'yadambiar' ),
						'default' => 1,
					),
					'expiry_days'             => array(
						'type'    => 'number',
						'label'   => __( 'انقضای درخواست‌ها (روز، ۰ = بدون انقضا)', 'yadambiar' ),
						'default' => 180,
					),
					'success_message'         => array(
						'type'    => 'text',
						'label'   => __( 'پیام موفقیت', 'yadambiar' ),
						'default' => 'درخواست شما ثبت شد، هنگام موجود شدن اطلاع می‌دهیم.',
					),
					'privacy_text'            => array(
						'type'    => 'text',
						'label'   => __( 'متن حریم خصوصی زیر فرم', 'yadambiar' ),
						'default' => 'اطلاعات شما فقط برای ارسال همین اعلان استفاده می‌شود.',
					),
				),
			),
			'channels'  => array(
				'label'  => __( 'کانال‌های ارسال', 'yadambiar' ),
				'fields' => array(
					'channels_enabled'   => array(
						'type'    => 'multicheck',
						'label'   => __( 'کانال‌های فعال', 'yadambiar' ),
						'options' => Catalog::channels(),
						'default' => array( 'sms', 'email', 'onsite' ),
					),
					'user_selects_channel' => array(
						'type'    => 'checkbox',
						'label'   => __( 'کاربر بتواند کانال دریافت را انتخاب کند', 'yadambiar' ),
						'default' => 1,
					),
					'floating_bell'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش زنگوله شناور اعلان‌های داخل سایت', 'yadambiar' ),
						'default' => 1,
					),
					'_sms_heading'       => array(
						'type'  => 'heading',
						'label' => __( 'پیامک', 'yadambiar' ),
					),
					'_sms_conn_group'    => array(
						'type'  => 'group',
						'label' => __( 'سامانه و اتصال', 'yadambiar' ),
						'desc'  => __( 'سامانه پیامک، اطلاعات ورود و شماره خط ارسال', 'yadambiar' ),
						'icon'  => 'phone',
						'ready' => array( 'sms_api_key', 'sms_username', 'sms_password', 'sms_sender' ),
					),
					'sms_gateway'        => array(
						'type'    => 'select',
						'label'   => __( 'سامانه پیامک', 'yadambiar' ),
						'desc'    => __( 'پنل‌های «سازگار» روی پلتفرم مشترک (ملی پیامک / IPPanel) یا وب‌سرویس HTTP کار می‌کنند؛ پلتفرم و آدرس API را از بخش «وب‌سرویس» پنل خود بردارید.', 'yadambiar' ),
						'options' => ProviderRegistry::options(),
						'default' => 'kavenegar',
					),
					'sms_platform'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( array(), true ) ),
						'type'    => 'select',
						'label'   => __( 'پلتفرم وب‌سرویس پنل', 'yadambiar' ),
						'desc'    => __( 'در مستندات وب‌سرویس پنل خود ببینید: اگر آدرس‌ها شبیه /api/SendSMS/ است «ملی پیامک»، اگر شبیه /api/v1/sms/ است «IPPanel»، و در غیر این صورت «وب‌سرویس سفارشی».', 'yadambiar' ),
						'options' => array(
							'payamak_panel' => __( 'سازگار با ملی پیامک (Payamak-Panel)', 'yadambiar' ),
							'ippanel'       => __( 'سازگار با IPPanel', 'yadambiar' ),
							'webhook'       => __( 'وب‌سرویس سفارشی (HTTP)', 'yadambiar' ),
						),
						'default' => 'payamak_panel',
					),
					'sms_base_url'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( array( 'payamak_panel', 'ippanel' ), true ) ),
						'type'    => 'text',
						'label'   => __( 'آدرس API پنل', 'yadambiar' ),
						'desc'    => __( 'برای پنل‌های سازگار الزامی است (مثلاً https://rest.example.ir). برای ملی پیامک و IPPanel خالی بگذارید.', 'yadambiar' ),
					),
					'sms_api_key'        => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::KEY_DRIVERS, true ) ),
						'type'  => 'password',
						'label' => __( 'API Key / توکن', 'yadambiar' ),
					),
					'sms_username'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::USER_DRIVERS, true ) ),
						'type'  => 'text',
						'label' => __( 'نام کاربری پنل', 'yadambiar' ),
					),
					'sms_password'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::USER_DRIVERS, true ) ),
						'type'  => 'password',
						'label' => __( 'رمز عبور پنل', 'yadambiar' ),
					),
					'sms_sender'         => array(
						'show_if' => array( 'sms_gateway' => array_values( array_diff( array_keys( ProviderRegistry::all() ), array( 'webhook' ) ) ) ),
						'type'  => 'text',
						'label' => __( 'شماره فرستنده (خط)', 'yadambiar' ),
					),
					'_sms_mode_group'    => array(
						'type'   => 'group',
						'label'  => __( 'نوع ارسال و پترن‌ها', 'yadambiar' ),
						'desc'   => __( 'ارسال متنی از قالب پیام یا خدماتی با کد پترن', 'yadambiar' ),
						'icon'   => 'list',
						'status' => 'sms_mode',
					),
					'sms_mode'           => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::PATTERN_DRIVERS, true ) ),
						'type'    => 'select',
						'label'   => __( 'نوع ارسال', 'yadambiar' ),
						'options' => array(
							'text'    => __( 'متنی (از قالب پیام)', 'yadambiar' ),
							'pattern' => __( 'خدماتی / پترن', 'yadambiar' ),
						),
						'default' => 'text',
					),
					'sms_pattern_items'  => array(
						'type'    => 'sms_patterns',
						'label'   => __( 'پترن‌های پیامک', 'yadambiar' ),
						'default' => array(),
					),
					'_sms_hook_group'    => array(
						'type'  => 'group',
						'label' => __( 'وب‌سرویس سفارشی', 'yadambiar' ),
						'desc'  => __( 'آدرس و بدنه درخواست HTTP برای سامانه دلخواه', 'yadambiar' ),
						'icon'  => 'gear',
						'ready' => array( 'sms_webhook_url' ),
					),
					'sms_webhook_url'    => array(
						'show_if' => array( array( 'sms_gateway' => array( 'webhook' ) ), array( 'sms_gateway' => ProviderRegistry::keys_for( array(), true ), 'sms_platform' => array( 'webhook' ) ) ),
						'type'  => 'text',
						'label' => __( 'آدرس وب‌سرویس سفارشی', 'yadambiar' ),
						'desc'  => __( 'متغیرها: {to} {message}', 'yadambiar' ),
					),
					'sms_webhook_body'   => array(
						'show_if' => array( array( 'sms_gateway' => array( 'webhook' ) ), array( 'sms_gateway' => ProviderRegistry::keys_for( array(), true ), 'sms_platform' => array( 'webhook' ) ) ),
						'type'    => 'textarea',
						'label'   => __( 'بدنه درخواست سفارشی (JSON، خالی = GET)', 'yadambiar' ),
						'default' => '{"to":"{to}","text":"{message}"}',
					),
					'_msg_heading'       => array(
						'type'  => 'heading',
						'label' => __( 'پیام‌رسان‌ها (تلگرام، بله)', 'yadambiar' ),
						'desc'  => __( 'کاربر با یک کلیک ربات را استارت می‌کند و حسابش متصل می‌شود. پس از وارد کردن توکن، دکمه «ثبت وب‌هوک ربات‌ها» را بزنید (سایت باید HTTPS باشد).', 'yadambiar' ),
					),
					'_tg_group'          => array(
						'type'  => 'group',
						'label' => __( 'تلگرام', 'yadambiar' ),
						'desc'  => __( 'پیام شخصی به مشتری و اعلام در کانال تلگرام', 'yadambiar' ),
						'icon'  => 'send',
						'ready' => array( 'telegram_token', 'telegram_bot' ),
					),
					'telegram_token'     => array(
						'type'  => 'password',
						'label' => __( 'توکن ربات تلگرام', 'yadambiar' ),
						'desc'  => __( 'از @BotFather', 'yadambiar' ),
					),
					'telegram_bot'       => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری ربات تلگرام (بدون @)', 'yadambiar' ),
					),
					'telegram_api_base'  => array(
						'type'    => 'text',
						'label'   => __( 'آدرس API تلگرام', 'yadambiar' ),
						'default' => 'https://api.telegram.org',
						'desc'    => __( 'اگر سرور شما در ایران است و به تلگرام دسترسی ندارد، آدرس یک پراکسی معکوس Bot API را وارد کنید.', 'yadambiar' ),
					),
					'telegram_channel'   => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال تلگرام', 'yadambiar' ),
						'desc'  => __( 'مثل ‎@myshop یا ‎-100123…؛ ربات باید ادمین کانال باشد.', 'yadambiar' ),
					),
					'_safir_group'       => array(
						'type'  => 'group',
						'label' => __( 'بله — پیام به شماره موبایل (سفیر)', 'yadambiar' ),
						'desc'  => __( 'پیام مستقیم به شماره مشتری در بله؛ مشتری لازم نیست ربات را Start کند', 'yadambiar' ),
						'icon'  => 'phone',
						'ready' => array( 'bale_safir_key' ),
					),
					'bale_safir_key'     => array(
						'type'  => 'password',
						'label' => __( 'کلید API سفیر (api-access-key)', 'yadambiar' ),
						'desc'  => __( 'در پنل کسب‌وکار بله (business.bale.ai) یک «سفیر» بسازید و کلید دسترسی API آن را اینجا وارد کنید. ارسال سفیر طبق تعرفه بله از اعتبار پنل کسر می‌شود.', 'yadambiar' ),
					),
					'bale_safir_bot_id'  => array(
						'type'  => 'text',
						'label' => __( 'شناسه عددی بازو (bot_id)', 'yadambiar' ),
						'desc'  => __( 'شناسه بازویی که سفیر به نام آن پیام می‌فرستد. خالی = عدد ابتدای توکن ربات بله (قبل از «:»).', 'yadambiar' ),
					),
					'_bale_group'        => array(
						'type'  => 'group',
						'label' => __( 'بله — ربات و کانال', 'yadambiar' ),
						'desc'  => __( 'اعلام در کانال بله؛ و پیام شخصی به مشتریانی که ربات را Start کرده‌اند (وقتی سفیر تنظیم نشده)', 'yadambiar' ),
						'icon'  => 'chat',
						'ready' => array( 'bale_token', 'bale_bot' ),
					),
					'bale_token'         => array(
						'type'  => 'password',
						'label' => __( 'توکن ربات بله', 'yadambiar' ),
						'desc'  => __( 'از @botfather در بله', 'yadambiar' ),
					),
					'bale_bot'           => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری ربات بله (بدون @)', 'yadambiar' ),
					),
					'bale_channel'       => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال بله', 'yadambiar' ),
					),
					'_bc_group'          => array(
						'type'      => 'group',
						'label'     => __( 'اعلام در کانال فروشگاه', 'yadambiar' ),
						'desc'      => __( 'انتشار خودکار موجود شدن کالاهای پرتقاضا در کانال‌ها', 'yadambiar' ),
						'icon'      => 'bell',
						'ready'     => array( 'broadcast_networks' ),
						'on_label'  => __( 'فعال', 'yadambiar' ),
						'off_label' => __( 'غیرفعال', 'yadambiar' ),
					),
					'broadcast_networks' => array(
						'type'    => 'multicheck',
						'style'   => 'cards',
						'label'   => __( 'کانال‌های اعلام', 'yadambiar' ),
						'desc'    => __( 'وقتی کالای پرتقاضا موجود شود، در کانال‌های انتخاب‌شده پست می‌شود؛ برای هر کالا حداکثر یک بار در ۲۴ ساعت.', 'yadambiar' ),
						'options' => array(
							'telegram' => __( 'کانال تلگرام', 'yadambiar' ),
							'bale'     => __( 'کانال بله', 'yadambiar' ),
						),
						'icons'   => array(
							'telegram' => 'send',
							'bale'     => 'chat',
						),
						// Each card shows the channel id it will post to (from these settings).
						'hints'   => array(
							'telegram' => 'telegram_channel',
							'bale'     => 'bale_channel',
						),
						'default' => array(),
					),
					'broadcast_min_waiting' => array(
						'type'    => 'number',
						'label'   => __( 'حداقل تعداد منتظر', 'yadambiar' ),
						'suffix'  => __( 'نفر', 'yadambiar' ),
						'desc'    => __( 'فقط کالاهایی اعلام می‌شوند که دست‌کم این تعداد منتظر داشته باشند.', 'yadambiar' ),
						'default' => 3,
					),
					'_push_heading'      => array(
						'type'  => 'heading',
						'label' => __( 'پوش نوتیفیکیشن', 'yadambiar' ),
						'desc'  => __( 'کلیدهای VAPID به‌صورت خودکار ساخته می‌شوند. سایت باید HTTPS باشد.', 'yadambiar' ),
					),
					'push_icon'          => array(
						'type'  => 'media',
						'label' => __( 'آیکون اعلان', 'yadambiar' ),
						'desc'  => __( 'از کتابخانه رسانه انتخاب یا آپلود کنید (همه فرمت‌های تصویری وردپرس). پیشنهاد: تصویر مربع حداقل ۱۹۲×۱۹۲. خالی = آیکون سایت.', 'yadambiar' ),
					),
				),
			),
			'appearance' => array(
				'label'  => __( 'ظاهر و رنگ‌ها', 'yadambiar' ),
				'fields' => array(
					'_look_heading'      => array(
						'type'  => 'heading',
						'label' => __( 'رنگ‌بندی پاپ‌آپ، دکمه‌ها و کارت‌ها', 'yadambiar' ),
						'desc'  => __( 'این رنگ‌ها روی همه‌ی بخش‌های سمت مشتری اعمال می‌شود. در ویجت‌های المنتور هم می‌توان برای هر ویجت جداگانه تغییرشان داد.', 'yadambiar' ),
					),
					'color_accent'       => array( 'type' => 'color', 'label' => __( 'رنگ اصلی (دکمه‌ها، تیک‌ها، تأکید)', 'yadambiar' ), 'default' => '#f4511e' ),
					'color_button_text'  => array( 'type' => 'color', 'label' => __( 'رنگ متن دکمه‌ها', 'yadambiar' ), 'default' => '#ffffff' ),
					'color_ink'          => array( 'type' => 'color', 'label' => __( 'رنگ متن اصلی', 'yadambiar' ), 'default' => '#1f2140' ),
					'color_muted'        => array( 'type' => 'color', 'label' => __( 'رنگ متن کم‌رنگ', 'yadambiar' ), 'default' => '#6b6f7b' ),
					'color_surface'      => array( 'type' => 'color', 'label' => __( 'پس‌زمینه‌ی پاپ‌آپ و کارت‌ها', 'yadambiar' ), 'default' => '#ffffff' ),
					'color_soft'         => array( 'type' => 'color', 'label' => __( 'پس‌زمینه‌ی ملایم', 'yadambiar' ), 'default' => '#f7f7f9' ),
					'color_line'         => array( 'type' => 'color', 'label' => __( 'رنگ حاشیه‌ها', 'yadambiar' ), 'default' => '#e6e7eb' ),
					'radius'             => array( 'type' => 'number', 'label' => __( 'گردی گوشه‌ها (پیکسل)', 'yadambiar' ), 'default' => 14 ),
				),
			),
			'templates' => array(
				'label'  => __( 'قالب پیام‌ها', 'yadambiar' ),
				'desc'   => __( 'متغیرها: {customer_name} {product_name} {variation} {price} {old_price} {regular_price} {target_price} {stock} {link} {site_name} {minutes} {exclusive_note} {manage_link} {code} {coupon} {coupon_amount} {coupon_expiry} {coupon_note} {alt_name} {alt_price} {alt_link} {alternatives_html} {waiting}', 'yadambiar' ),
				'fields' => array(),
			),
			'growth'    => array(
				'label'  => __( 'رشد فروش', 'yadambiar' ),
				'fields' => array(
					'_coupon_heading'        => array(
						'type'  => 'heading',
						'label' => __( 'کد تخفیف خودکار', 'yadambiar' ),
						'desc'  => __( 'برای هر منتظر یک کد یکبار مصرف، محدود به همان محصول و با تاریخ انقضا ساخته و در پیام قرار داده می‌شود.', 'yadambiar' ),
					),
					'coupon_enabled'         => array(
						'type'    => 'checkbox',
						'label'   => __( 'ساخت کد تخفیف اختصاصی در پیام', 'yadambiar' ),
						'default' => 0,
					),
					'coupon_events'          => array(
						'type'    => 'multicheck',
						'label'   => __( 'برای کدام اعلان‌ها', 'yadambiar' ),
						'options' => array(
							'back_in_stock' => __( 'موجود شدن', 'yadambiar' ),
							'low_stock'     => __( 'موجود شدن تعداد محدود', 'yadambiar' ),
							'combo'         => __( 'قانون ترکیبی', 'yadambiar' ),
							'price_drop'    => __( 'کاهش قیمت', 'yadambiar' ),
						),
						'default' => array( 'back_in_stock', 'combo' ),
					),
					'coupon_type'            => array(
						'type'    => 'select',
						'label'   => __( 'نوع تخفیف', 'yadambiar' ),
						'options' => array(
							'percent'       => __( 'درصدی', 'yadambiar' ),
							'fixed_product' => __( 'مبلغ ثابت', 'yadambiar' ),
						),
						'default' => 'percent',
					),
					'coupon_amount'          => array(
						'type'    => 'number',
						'label'   => __( 'مقدار تخفیف (درصد یا مبلغ)', 'yadambiar' ),
						'default' => 5,
					),
					'coupon_hours'           => array(
						'type'    => 'number',
						'label'   => __( 'اعتبار کد (ساعت)', 'yadambiar' ),
						'default' => 48,
					),
					'coupon_restrict_email'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'کد فقط با ایمیل همان مشتری قابل استفاده باشد (در صورت داشتن ایمیل)', 'yadambiar' ),
						'default' => 0,
					),
					'coupon_individual'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'با کدهای تخفیف دیگر ترکیب نشود', 'yadambiar' ),
						'default' => 1,
					),
					'coupon_auto_apply'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'اعمال خودکار کد وقتی مشتری از لینک پیام وارد شود', 'yadambiar' ),
						'default' => 1,
					),
					'_alt_heading'           => array(
						'type'  => 'heading',
						'label' => __( 'پیشنهاد محصول جایگزین', 'yadambiar' ),
					),
					'alt_enabled'            => array(
						'type'    => 'checkbox',
						'label'   => __( 'ارسال پیشنهاد جایگزین برای منتظرانی که مدت زیادی منتظر مانده‌اند', 'yadambiar' ),
						'default' => 0,
					),
					'alt_after_days'         => array(
						'type'    => 'number',
						'label'   => __( 'پس از چند روز انتظار', 'yadambiar' ),
						'default' => 14,
					),
					'alt_count'              => array(
						'type'    => 'number',
						'label'   => __( 'تعداد پیشنهاد در پیام ارسالی', 'yadambiar' ),
						'default' => 3,
					),
					'alt_page_count'         => array(
						'type'    => 'number',
						'label'   => __( 'تعداد محصولات مشابه در صفحه محصول (۱ تا ۱۲)', 'yadambiar' ),
						'default' => 4,
					),
					'alt_price_range'        => array(
						'type'    => 'number',
						'label'   => __( 'بازه قیمت مشابه (± درصد)', 'yadambiar' ),
						'default' => 30,
					),
					'alt_show_on_page'       => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش «جایگزین‌های موجود» در صفحه محصول ناموجود', 'yadambiar' ),
						'default' => 1,
					),
					'_ph_heading'            => array(
						'type'  => 'heading',
						'label' => __( 'تاریخچه قیمت', 'yadambiar' ),
					),
					'price_history_enabled'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'ثبت تاریخچه قیمت و نمایش نمودار', 'yadambiar' ),
						'default' => 1,
					),
					'price_history_display'  => array(
						'type'    => 'select',
						'label'   => __( 'محل نمایش نمودار', 'yadambiar' ),
						'options' => array(
							'tab'       => __( 'تب «تاریخچه قیمت» در صفحه محصول', 'yadambiar' ),
							'widget'    => __( 'زیر دکمه یادم بیار', 'yadambiar' ),
							'shortcode' => __( 'فقط شورت‌کد / المنتور', 'yadambiar' ),
						),
						'default' => 'tab',
					),
					'price_history_days'     => array(
						'type'    => 'number',
						'label'   => __( 'بازه نمودار (روز)', 'yadambiar' ),
						'default' => 90,
					),
					'_fc_heading'            => array(
						'type'  => 'heading',
						'label' => __( 'پیش‌بینی تقاضا', 'yadambiar' ),
					),
					'forecast_lead_days'     => array(
						'type'    => 'number',
						'label'   => __( 'زمان تحویل تامین‌کننده (روز)', 'yadambiar' ),
						'default' => 14,
					),
					'forecast_cover_days'    => array(
						'type'    => 'number',
						'label'   => __( 'موجودی برای چند روز فروش سفارش داده شود', 'yadambiar' ),
						'default' => 30,
					),
					'forecast_default_conv'  => array(
						'type'    => 'number',
						'label'   => __( 'نرخ تبدیل پیش‌فرض منتظر به خریدار (درصد، وقتی داده کافی نیست)', 'yadambiar' ),
						'default' => 30,
					),
					'forecast_service_level' => array(
						'type'    => 'select',
						'label'   => __( 'سطح اطمینان موجودی اطمینان', 'yadambiar' ),
						'options' => array(
							'80' => '80%',
							'90' => '90%',
							'95' => '95%',
							'98' => '98%',
						),
						'default' => '90',
					),
				),
			),
			'advanced'  => array(
				'label'  => __( 'پیشرفته', 'yadambiar' ),
				'fields' => array(
					'low_stock_threshold'   => array(
						'type'    => 'number',
						'label'   => __( 'آستانه «تعداد محدود» (پیام ویژه وقتی موجودی کمتر یا مساوی این عدد است)', 'yadambiar' ),
						'default' => 5,
					),
					'waves_enabled'         => array(
						'type'    => 'checkbox',
						'label'   => __( 'ارسال موجی (به‌ترتیب ثبت، متناسب با موجودی)', 'yadambiar' ),
						'desc'    => __( 'مثلاً با موجودی ۲ و ضریب ۳، ابتدا ۶ نفر اول مطلع می‌شوند؛ اگر کالا هنوز موجود بود موج بعدی ارسال می‌شود.', 'yadambiar' ),
						'default' => 0,
					),
					'waves_multiplier'      => array(
						'type'    => 'number',
						'label'   => __( 'ضریب موج', 'yadambiar' ),
						'default' => 3,
					),
					'waves_interval'        => array(
						'type'    => 'number',
						'label'   => __( 'فاصله موج‌ها (دقیقه)', 'yadambiar' ),
						'default' => 60,
					),
					'exclusive_enabled'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'رزرو زودتر از دیگران (فرصت خرید اختصاصی برای منتظران)', 'yadambiar' ),
						'default' => 0,
					),
					'exclusive_minutes'     => array(
						'type'    => 'number',
						'label'   => __( 'مدت فرصت اختصاصی (دقیقه)', 'yadambiar' ),
						'default' => 30,
					),
					'verify_contact'        => array(
						'type'    => 'select',
						'label'   => __( 'تایید شماره/ایمیل با کد یکبار مصرف', 'yadambiar' ),
						'options' => array(
							'none'  => __( 'غیرفعال', 'yadambiar' ),
							'guest' => __( 'فقط مهمان‌ها', 'yadambiar' ),
							'all'   => __( 'همه کاربران', 'yadambiar' ),
						),
						'default' => 'none',
					),
					'strict_iran_mobile'    => array(
						'type'    => 'checkbox',
						'label'   => __( 'اعتبارسنجی شماره موبایل ایران (09xxxxxxxxx)', 'yadambiar' ),
						'default' => 1,
					),
					'rate_limit'            => array(
						'type'    => 'number',
						'label'   => __( 'حداکثر درخواست از هر IP در ساعت', 'yadambiar' ),
						'default' => 15,
					),
					'max_per_contact'       => array(
						'type'    => 'number',
						'label'   => __( 'حداکثر درخواست فعال برای هر شماره/ایمیل', 'yadambiar' ),
						'default' => 50,
					),
					'batch_size'            => array(
						'type'    => 'number',
						'label'   => __( 'تعداد ارسال در هر نوبت پردازش', 'yadambiar' ),
						'default' => 50,
					),
					'duplicate_window'      => array(
						'type'    => 'number',
						'label'   => __( 'جلوگیری از اعلان تکراری به یک نفر (ساعت)', 'yadambiar' ),
						'desc'    => __( 'اگر یک نفر چند درخواست برای همان کالا داشته باشد (یا دوباره ثبت کند)، همان اعلان در این بازه دوباره برایش ارسال نمی‌شود. ۰ = غیرفعال.', 'yadambiar' ),
						'default' => 72,
					),
					'price_change_cooldown' => array(
						'type'    => 'number',
						'label'   => __( 'حداقل فاصله دو اعلان «تغییر قیمت» برای یک درخواست (ساعت)', 'yadambiar' ),
						'default' => 24,
					),
					'conversion_days'       => array(
						'type'    => 'number',
						'label'   => __( 'بازه نسبت‌دادن خرید به اعلان (روز)', 'yadambiar' ),
						'default' => 14,
					),
					'webhook_url'           => array(
						'type'  => 'text',
						'label' => __( 'وب‌هوک خروجی (n8n / Zapier / CRM)', 'yadambiar' ),
						'desc'  => __( 'پس از هر ثبت درخواست و هر ارسال اعلان، یک POST با JSON به این آدرس ارسال می‌شود.', 'yadambiar' ),
					),
					'webhook_secret'        => array(
						'type'  => 'password',
						'label' => __( 'کلید امضای وب‌هوک', 'yadambiar' ),
						'desc'  => __( 'اختیاری. هر درخواست هدر X-YadamBiar-Signature (sha256=HMAC بدنه با این کلید) دارد تا گیرنده اصالت آن را بسنجد.', 'yadambiar' ),
					),
					'log_retention_days'    => array(
						'type'    => 'number',
						'label'   => __( 'نگهداری لاگ ارسال (روز)', 'yadambiar' ),
						'default' => 90,
					),
					'delete_on_uninstall'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'حذف همه داده‌ها هنگام حذف افزونه', 'yadambiar' ),
						'default' => 0,
					),
				),
			),
		);

		foreach ( MessageTemplates::all() as $event => $tpl ) {
			$events = Catalog::events();
			$schema['templates']['fields'][ '_tpl_' . $event ] = array(
				'type'  => 'heading',
				'label' => $events[ $event ],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_sms' ]     = array(
				'type'    => 'textarea',
				'label'   => __( 'متن کوتاه (پیامک / پیام‌رسان / پوش / داخل سایت)', 'yadambiar' ),
				'default' => $tpl['sms'],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_subject' ] = array(
				'type'    => 'text',
				'label'   => __( 'عنوان (ایمیل / اعلان)', 'yadambiar' ),
				'default' => $tpl['subject'],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_body' ]    = array(
				'type'    => 'html',
				'label'   => __( 'متن ایمیل (HTML)', 'yadambiar' ),
				'default' => $tpl['body'],
			);
		}

		return apply_filters( 'yadambiar_settings_schema', $schema );
	}

	/**
	 * Flattened defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array();
		foreach ( self::all() as $tab ) {
			foreach ( $tab['fields'] as $key => $field ) {
				if ( in_array( $field['type'], array( 'heading', 'group' ), true ) ) {
					continue;
				}
				$defaults[ $key ] = isset( $field['default'] ) ? $field['default'] : ( 'multicheck' === $field['type'] ? array() : '' );
			}
		}
		return $defaults;
	}
}
