<?php
/**
 * Settings schema, defaults and accessors.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\ProviderRegistry;

class Settings {

	const OPTION = 'yadambiar_settings';

	/**
	 * Cached values.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Notification events.
	 *
	 * @return array
	 */
	public static function events() {
		return array(
			'back_in_stock' => __( 'موجود شدن', 'yadam-biar' ),
			'low_stock'     => __( 'موجود شدن تعداد محدود', 'yadam-biar' ),
			'price_drop'    => __( 'کاهش قیمت', 'yadam-biar' ),
			'price_rise'    => __( 'افزایش قیمت', 'yadam-biar' ),
			'price_change'  => __( 'تغییر قیمت', 'yadam-biar' ),
			'combo'         => __( 'قانون ترکیبی', 'yadam-biar' ),
			'alternatives'  => __( 'پیشنهاد محصول جایگزین', 'yadam-biar' ),
			'broadcast'     => __( 'اعلام موجود شدن در کانال', 'yadam-biar' ),
			'otp'           => __( 'کد تایید', 'yadam-biar' ),
		);
	}

	/**
	 * Available channels.
	 *
	 * @return array
	 */
	public static function channels() {
		return array(
			'sms'      => __( 'پیامک', 'yadam-biar' ),
			'email'    => __( 'ایمیل', 'yadam-biar' ),
			'onsite'   => __( 'اعلان داخل سایت', 'yadam-biar' ),
			'push'     => __( 'پوش نوتیفیکیشن', 'yadam-biar' ),
			'telegram' => __( 'تلگرام', 'yadam-biar' ),
			'bale'     => __( 'بله', 'yadam-biar' ),
		);
	}

	/**
	 * Default message templates.
	 *
	 * @return array
	 */
	private static function default_templates() {
		return array(
			'back_in_stock' => array(
				'sms'     => "{customer_name} عزیز، {product_name} {variation} موجود شد.\nقیمت: {price}\n{link}\n{site_name}",
				'subject' => '{product_name} موجود شد!',
				'body'    => "<p>{customer_name} عزیز سلام،</p><p>محصولی که منتظرش بودید یعنی <strong>{product_name}</strong> {variation} هم‌اکنون موجود است.</p><p>قیمت: {price}</p><p><a href=\"{link}\">مشاهده و خرید محصول</a></p>{exclusive_note}",
			),
			'low_stock'     => array(
				'sms'     => "فقط {stock} عدد از {product_name} {variation} موجود شده است! عجله کنید.\n{link}\n{site_name}",
				'subject' => 'فقط {stock} عدد از {product_name} موجود شد',
				'body'    => "<p>{customer_name} عزیز،</p><p>فقط <strong>{stock} عدد</strong> از {product_name} {variation} موجود شده است.</p><p>قیمت: {price}</p><p><a href=\"{link}\">همین حالا خرید کنید</a></p>{exclusive_note}",
			),
			'price_drop'    => array(
				'sms'     => "قیمت {product_name} {variation} به {price} کاهش یافت (هدف شما: {target_price}).\n{link}\n{site_name}",
				'subject' => 'قیمت {product_name} کاهش یافت',
				'body'    => "<p>{customer_name} عزیز،</p><p>قیمت <strong>{product_name}</strong> {variation} به <strong>{price}</strong> رسید.</p><p>قیمت هدف شما: {target_price}</p><p><a href=\"{link}\">مشاهده محصول</a></p>",
			),
			'price_rise'    => array(
				'sms'     => "قیمت {product_name} {variation} از {target_price} بالاتر رفت و اکنون {price} است.\n{link}\n{site_name}",
				'subject' => 'افزایش قیمت {product_name}',
				'body'    => "<p>{customer_name} عزیز،</p><p>قیمت <strong>{product_name}</strong> {variation} به <strong>{price}</strong> افزایش یافت.</p><p><a href=\"{link}\">مشاهده محصول</a></p>",
			),
			'price_change'  => array(
				'sms'     => "قیمت {product_name} {variation} تغییر کرد: {old_price} ← {price}\n{link}\n{site_name}",
				'subject' => 'تغییر قیمت {product_name}',
				'body'    => "<p>{customer_name} عزیز،</p><p>قیمت <strong>{product_name}</strong> {variation} از {old_price} به <strong>{price}</strong> تغییر کرد.</p><p><a href=\"{link}\">مشاهده محصول</a></p>",
			),
			'combo'         => array(
				'sms'     => "شرایط مورد نظر شما برای {product_name} {variation} برقرار شد. قیمت: {price} | موجودی: {stock}\n{link}\n{site_name}",
				'subject' => 'شرایط شما برای {product_name} برقرار شد',
				'body'    => "<p>{customer_name} عزیز،</p><p>همه شرایطی که برای <strong>{product_name}</strong> {variation} تعیین کرده بودید برقرار شد.</p><ul><li>قیمت: {price}</li><li>موجودی: {stock}</li></ul><p><a href=\"{link}\">مشاهده و خرید</a></p>{exclusive_note}",
			),
			'alternatives'  => array(
				'sms'     => "{customer_name} عزیز، {product_name} {variation} هنوز موجود نشده است. پیشنهاد مشابه موجود:\n{alt_name} — {alt_price}\n{alt_link}\nهمچنان منتظر موجود شدن هستیم و خبرتان می‌کنیم.\n{site_name}",
				'subject' => 'تا موجود شدن {product_name}، این‌ها را ببینید',
				'body'    => "<p>{customer_name} عزیز،</p><p><strong>{product_name}</strong> {variation} هنوز موجود نشده است. تا آن زمان این محصولات مشابه موجود هستند:</p>{alternatives_html}<p>درخواست شما همچنان فعال است و به محض موجود شدن خبرتان می‌کنیم.</p>",
			),
			'broadcast'     => array(
				'sms'     => "🔔 {product_name} {variation} دوباره موجود شد!\nقیمت: {price}\n{waiting} نفر منتظرش بودند.\n{link}",
				'subject' => '{product_name} موجود شد',
				'body'    => '',
			),
			'otp'           => array(
				'sms'     => "کد تایید یادم بیار: {code}\n{site_name}",
				'subject' => 'کد تایید {site_name}',
				'body'    => '<p>کد تایید شما: <strong style="font-size:20px">{code}</strong></p>',
			),
		);
	}

	/**
	 * Settings schema grouped by tab.
	 *
	 * @return array
	 */
	public static function schema() {
		$schema = array(
			'general'   => array(
				'label'  => __( 'عمومی', 'yadam-biar' ),
				'fields' => array(
					'enabled_types'           => array(
						'type'    => 'multicheck',
						'label'   => __( 'انواع اعلان فعال', 'yadam-biar' ),
						'options' => array(
							'stock'        => __( 'موجود شدن', 'yadam-biar' ),
							'price_drop'   => __( 'کاهش قیمت (قیمت هدف)', 'yadam-biar' ),
							'price_rise'   => __( 'افزایش قیمت', 'yadam-biar' ),
							'price_change' => __( 'هر تغییر قیمت', 'yadam-biar' ),
							'min_qty'      => __( 'حداقل موجودی (قانون ترکیبی)', 'yadam-biar' ),
						),
						'default' => array( 'stock', 'price_drop', 'price_rise', 'price_change', 'min_qty' ),
					),
					'contact_mode'            => array(
						'type'    => 'select',
						'label'   => __( 'اطلاعات تماس لازم', 'yadam-biar' ),
						'options' => array(
							'either' => __( 'موبایل یا ایمیل (حداقل یکی)', 'yadam-biar' ),
							'phone'  => __( 'فقط موبایل', 'yadam-biar' ),
							'email'  => __( 'فقط ایمیل', 'yadam-biar' ),
							'both'   => __( 'هر دو الزامی', 'yadam-biar' ),
						),
						'default' => 'either',
					),
					'require_login'           => array(
						'type'    => 'checkbox',
						'label'   => __( 'فقط کاربران عضو بتوانند درخواست ثبت کنند', 'yadam-biar' ),
						'default' => 0,
					),
					'position'                => array(
						'type'    => 'select',
						'label'   => __( 'محل نمایش در صفحه محصول', 'yadam-biar' ),
						'options' => array(
							'after_cart'  => __( 'بعد از دکمه افزودن به سبد', 'yadam-biar' ),
							'after_price' => __( 'بعد از قیمت', 'yadam-biar' ),
							'after_meta'  => __( 'بعد از دسته‌بندی‌ها', 'yadam-biar' ),
							'shortcode'   => __( 'فقط با شورت‌کد [yadambiar]', 'yadam-biar' ),
						),
						'default' => 'after_cart',
					),
					'button_text_stock'       => array(
						'type'    => 'text',
						'label'   => __( 'متن دکمه موجود شدن', 'yadam-biar' ),
						'default' => '🔔 یادم بیار وقتی موجود شد',
					),
					'button_text_price'       => array(
						'type'    => 'text',
						'label'   => __( 'متن دکمه هشدار قیمت', 'yadam-biar' ),
						'default' => 'یادم بیار وقتی ارزان شد',
					),
					'show_price_alert'        => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش هشدار قیمت برای محصولات موجود', 'yadam-biar' ),
						'default' => 1,
					),
					'annotate_variations'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'علامت‌گذاری گزینه‌های ناموجود در لیست تنوع‌ها (مثلاً «۴۲ — ناموجود 🔔»)', 'yadam-biar' ),
						'default' => 1,
					),
					'show_waiting_count'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش تعداد منتظران (اثبات اجتماعی)', 'yadam-biar' ),
						'default' => 1,
					),
					'waiting_count_min'       => array(
						'type'    => 'number',
						'label'   => __( 'حداقل تعداد منتظر برای نمایش', 'yadam-biar' ),
						'default' => 3,
					),
					'backorder_as_in_stock'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'وضعیت «پیش‌خرید» موجود حساب شود', 'yadam-biar' ),
						'default' => 0,
					),
					'price_requires_stock'    => array(
						'type'    => 'checkbox',
						'label'   => __( 'هشدار قیمت فقط وقتی کالا موجود است ارسال شود', 'yadam-biar' ),
						'default' => 1,
					),
					'expiry_days'             => array(
						'type'    => 'number',
						'label'   => __( 'انقضای درخواست‌ها (روز، ۰ = بدون انقضا)', 'yadam-biar' ),
						'default' => 180,
					),
					'success_message'         => array(
						'type'    => 'text',
						'label'   => __( 'پیام موفقیت', 'yadam-biar' ),
						'default' => 'درخواست شما ثبت شد، هنگام موجود شدن اطلاع می‌دهیم.',
					),
					'privacy_text'            => array(
						'type'    => 'text',
						'label'   => __( 'متن حریم خصوصی زیر فرم', 'yadam-biar' ),
						'default' => 'اطلاعات شما فقط برای ارسال همین اعلان استفاده می‌شود.',
					),
				),
			),
			'channels'  => array(
				'label'  => __( 'کانال‌های ارسال', 'yadam-biar' ),
				'fields' => array(
					'channels_enabled'   => array(
						'type'    => 'multicheck',
						'label'   => __( 'کانال‌های فعال', 'yadam-biar' ),
						'options' => self::channels(),
						'default' => array( 'sms', 'email', 'onsite' ),
					),
					'user_selects_channel' => array(
						'type'    => 'checkbox',
						'label'   => __( 'کاربر بتواند کانال دریافت را انتخاب کند', 'yadam-biar' ),
						'default' => 1,
					),
					'floating_bell'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش زنگوله شناور اعلان‌های داخل سایت', 'yadam-biar' ),
						'default' => 1,
					),
					'_sms_heading'       => array(
						'type'  => 'heading',
						'label' => __( 'پیامک', 'yadam-biar' ),
					),
					'_sms_conn_group'    => array(
						'type'  => 'group',
						'label' => __( 'سامانه و اتصال', 'yadam-biar' ),
						'desc'  => __( 'سامانه پیامک، اطلاعات ورود و شماره خط ارسال', 'yadam-biar' ),
						'icon'  => 'phone',
						'ready' => array( 'sms_api_key', 'sms_username', 'sms_password', 'sms_sender' ),
					),
					'sms_gateway'        => array(
						'type'    => 'select',
						'label'   => __( 'سامانه پیامک', 'yadam-biar' ),
						'desc'    => __( 'پنل‌های «سازگار» روی پلتفرم مشترک (ملی پیامک / IPPanel) یا وب‌سرویس HTTP کار می‌کنند؛ پلتفرم و آدرس API را از بخش «وب‌سرویس» پنل خود بردارید.', 'yadam-biar' ),
						'options' => ProviderRegistry::options(),
						'default' => 'kavenegar',
					),
					'sms_platform'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( array(), true ) ),
						'type'    => 'select',
						'label'   => __( 'پلتفرم وب‌سرویس پنل', 'yadam-biar' ),
						'desc'    => __( 'در مستندات وب‌سرویس پنل خود ببینید: اگر آدرس‌ها شبیه /api/SendSMS/ است «ملی پیامک»، اگر شبیه /api/v1/sms/ است «IPPanel»، و در غیر این صورت «وب‌سرویس سفارشی».', 'yadam-biar' ),
						'options' => array(
							'payamak_panel' => __( 'سازگار با ملی پیامک (Payamak-Panel)', 'yadam-biar' ),
							'ippanel'       => __( 'سازگار با IPPanel', 'yadam-biar' ),
							'webhook'       => __( 'وب‌سرویس سفارشی (HTTP)', 'yadam-biar' ),
						),
						'default' => 'payamak_panel',
					),
					'sms_base_url'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( array( 'payamak_panel', 'ippanel' ), true ) ),
						'type'    => 'text',
						'label'   => __( 'آدرس API پنل', 'yadam-biar' ),
						'desc'    => __( 'برای پنل‌های سازگار الزامی است (مثلاً https://rest.example.ir). برای ملی پیامک و IPPanel خالی بگذارید.', 'yadam-biar' ),
					),
					'sms_api_key'        => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::KEY_DRIVERS, true ) ),
						'type'  => 'password',
						'label' => __( 'API Key / توکن', 'yadam-biar' ),
					),
					'sms_username'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::USER_DRIVERS, true ) ),
						'type'  => 'text',
						'label' => __( 'نام کاربری پنل', 'yadam-biar' ),
					),
					'sms_password'       => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::USER_DRIVERS, true ) ),
						'type'  => 'password',
						'label' => __( 'رمز عبور پنل', 'yadam-biar' ),
					),
					'sms_sender'         => array(
						'show_if' => array( 'sms_gateway' => array_values( array_diff( array_keys( ProviderRegistry::all() ), array( 'webhook' ) ) ) ),
						'type'  => 'text',
						'label' => __( 'شماره فرستنده (خط)', 'yadam-biar' ),
					),
					'_sms_mode_group'    => array(
						'type'   => 'group',
						'label'  => __( 'نوع ارسال و پترن‌ها', 'yadam-biar' ),
						'desc'   => __( 'ارسال متنی از قالب پیام یا خدماتی با کد پترن', 'yadam-biar' ),
						'icon'   => 'list',
						'status' => 'sms_mode',
					),
					'sms_mode'           => array(
						'show_if' => array( 'sms_gateway' => ProviderRegistry::keys_for( ProviderRegistry::PATTERN_DRIVERS, true ) ),
						'type'    => 'select',
						'label'   => __( 'نوع ارسال', 'yadam-biar' ),
						'options' => array(
							'text'    => __( 'متنی (از قالب پیام)', 'yadam-biar' ),
							'pattern' => __( 'خدماتی / پترن', 'yadam-biar' ),
						),
						'default' => 'text',
					),
					'sms_pattern_items'  => array(
						'type'    => 'sms_patterns',
						'label'   => __( 'پترن‌های پیامک', 'yadam-biar' ),
						'default' => array(),
					),
					'_sms_hook_group'    => array(
						'type'  => 'group',
						'label' => __( 'وب‌سرویس سفارشی', 'yadam-biar' ),
						'desc'  => __( 'آدرس و بدنه درخواست HTTP برای سامانه دلخواه', 'yadam-biar' ),
						'icon'  => 'gear',
						'ready' => array( 'sms_webhook_url' ),
					),
					'sms_webhook_url'    => array(
						'show_if' => array( array( 'sms_gateway' => array( 'webhook' ) ), array( 'sms_gateway' => ProviderRegistry::keys_for( array(), true ), 'sms_platform' => array( 'webhook' ) ) ),
						'type'  => 'text',
						'label' => __( 'آدرس وب‌سرویس سفارشی', 'yadam-biar' ),
						'desc'  => __( 'متغیرها: {to} {message}', 'yadam-biar' ),
					),
					'sms_webhook_body'   => array(
						'show_if' => array( array( 'sms_gateway' => array( 'webhook' ) ), array( 'sms_gateway' => ProviderRegistry::keys_for( array(), true ), 'sms_platform' => array( 'webhook' ) ) ),
						'type'    => 'textarea',
						'label'   => __( 'بدنه درخواست سفارشی (JSON، خالی = GET)', 'yadam-biar' ),
						'default' => '{"to":"{to}","text":"{message}"}',
					),
					'_msg_heading'       => array(
						'type'  => 'heading',
						'label' => __( 'پیام‌رسان‌ها (تلگرام، بله)', 'yadam-biar' ),
						'desc'  => __( 'کاربر با یک کلیک ربات را استارت می‌کند و حسابش متصل می‌شود. پس از وارد کردن توکن، دکمه «ثبت وب‌هوک ربات‌ها» را بزنید (سایت باید HTTPS باشد).', 'yadam-biar' ),
					),
					'_tg_group'          => array(
						'type'  => 'group',
						'label' => __( 'تلگرام', 'yadam-biar' ),
						'desc'  => __( 'پیام شخصی به مشتری و اعلام در کانال تلگرام', 'yadam-biar' ),
						'icon'  => 'send',
						'ready' => array( 'telegram_token', 'telegram_bot' ),
					),
					'telegram_token'     => array(
						'type'  => 'password',
						'label' => __( 'توکن ربات تلگرام', 'yadam-biar' ),
						'desc'  => __( 'از @BotFather', 'yadam-biar' ),
					),
					'telegram_bot'       => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری ربات تلگرام (بدون @)', 'yadam-biar' ),
					),
					'telegram_api_base'  => array(
						'type'    => 'text',
						'label'   => __( 'آدرس API تلگرام', 'yadam-biar' ),
						'default' => 'https://api.telegram.org',
						'desc'    => __( 'اگر سرور شما در ایران است و به تلگرام دسترسی ندارد، آدرس یک پراکسی معکوس Bot API را وارد کنید.', 'yadam-biar' ),
					),
					'telegram_channel'   => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال تلگرام', 'yadam-biar' ),
						'desc'  => __( 'مثل ‎@myshop یا ‎-100123…؛ ربات باید ادمین کانال باشد.', 'yadam-biar' ),
					),
					'_safir_group'       => array(
						'type'  => 'group',
						'label' => __( 'بله — پیام به شماره موبایل (سفیر)', 'yadam-biar' ),
						'desc'  => __( 'پیام مستقیم به شماره مشتری در بله؛ مشتری لازم نیست ربات را Start کند', 'yadam-biar' ),
						'icon'  => 'phone',
						'ready' => array( 'bale_safir_key' ),
					),
					'bale_safir_key'     => array(
						'type'  => 'password',
						'label' => __( 'کلید API سفیر (api-access-key)', 'yadam-biar' ),
						'desc'  => __( 'در پنل کسب‌وکار بله (business.bale.ai) یک «سفیر» بسازید و کلید دسترسی API آن را اینجا وارد کنید. ارسال سفیر طبق تعرفه بله از اعتبار پنل کسر می‌شود.', 'yadam-biar' ),
					),
					'bale_safir_bot_id'  => array(
						'type'  => 'text',
						'label' => __( 'شناسه عددی بازو (bot_id)', 'yadam-biar' ),
						'desc'  => __( 'شناسه بازویی که سفیر به نام آن پیام می‌فرستد. خالی = عدد ابتدای توکن ربات بله (قبل از «:»).', 'yadam-biar' ),
					),
					'_bale_group'        => array(
						'type'  => 'group',
						'label' => __( 'بله — ربات و کانال', 'yadam-biar' ),
						'desc'  => __( 'اعلام در کانال بله؛ و پیام شخصی به مشتریانی که ربات را Start کرده‌اند (وقتی سفیر تنظیم نشده)', 'yadam-biar' ),
						'icon'  => 'chat',
						'ready' => array( 'bale_token', 'bale_bot' ),
					),
					'bale_token'         => array(
						'type'  => 'password',
						'label' => __( 'توکن ربات بله', 'yadam-biar' ),
						'desc'  => __( 'از @botfather در بله', 'yadam-biar' ),
					),
					'bale_bot'           => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری ربات بله (بدون @)', 'yadam-biar' ),
					),
					'bale_channel'       => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال بله', 'yadam-biar' ),
					),
					'_bc_group'          => array(
						'type'      => 'group',
						'label'     => __( 'اعلام در کانال فروشگاه', 'yadam-biar' ),
						'desc'      => __( 'انتشار خودکار موجود شدن کالاهای پرتقاضا در کانال‌ها', 'yadam-biar' ),
						'icon'      => 'bell',
						'ready'     => array( 'broadcast_networks' ),
						'on_label'  => __( 'فعال', 'yadam-biar' ),
						'off_label' => __( 'غیرفعال', 'yadam-biar' ),
					),
					'broadcast_networks' => array(
						'type'    => 'multicheck',
						'style'   => 'cards',
						'label'   => __( 'کانال‌های اعلام', 'yadam-biar' ),
						'desc'    => __( 'وقتی کالای پرتقاضا موجود شود، در کانال‌های انتخاب‌شده پست می‌شود؛ برای هر کالا حداکثر یک بار در ۲۴ ساعت.', 'yadam-biar' ),
						'options' => array(
							'telegram' => __( 'کانال تلگرام', 'yadam-biar' ),
							'bale'     => __( 'کانال بله', 'yadam-biar' ),
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
						'label'   => __( 'حداقل تعداد منتظر', 'yadam-biar' ),
						'suffix'  => __( 'نفر', 'yadam-biar' ),
						'desc'    => __( 'فقط کالاهایی اعلام می‌شوند که دست‌کم این تعداد منتظر داشته باشند.', 'yadam-biar' ),
						'default' => 3,
					),
					'_push_heading'      => array(
						'type'  => 'heading',
						'label' => __( 'پوش نوتیفیکیشن', 'yadam-biar' ),
						'desc'  => __( 'کلیدهای VAPID به‌صورت خودکار ساخته می‌شوند. سایت باید HTTPS باشد.', 'yadam-biar' ),
					),
					'push_icon'          => array(
						'type'  => 'media',
						'label' => __( 'آیکون اعلان', 'yadam-biar' ),
						'desc'  => __( 'از کتابخانه رسانه انتخاب یا آپلود کنید (همه فرمت‌های تصویری وردپرس). پیشنهاد: تصویر مربع حداقل ۱۹۲×۱۹۲. خالی = آیکون سایت.', 'yadam-biar' ),
					),
				),
			),
			'appearance' => array(
				'label'  => __( 'ظاهر و رنگ‌ها', 'yadam-biar' ),
				'fields' => array(
					'_look_heading'      => array(
						'type'  => 'heading',
						'label' => __( 'رنگ‌بندی پاپ‌آپ، دکمه‌ها و کارت‌ها', 'yadam-biar' ),
						'desc'  => __( 'این رنگ‌ها روی همه‌ی بخش‌های سمت مشتری اعمال می‌شود. در ویجت‌های المنتور هم می‌توان برای هر ویجت جداگانه تغییرشان داد.', 'yadam-biar' ),
					),
					'color_accent'       => array( 'type' => 'color', 'label' => __( 'رنگ اصلی (دکمه‌ها، تیک‌ها، تأکید)', 'yadam-biar' ), 'default' => '#f4511e' ),
					'color_button_text'  => array( 'type' => 'color', 'label' => __( 'رنگ متن دکمه‌ها', 'yadam-biar' ), 'default' => '#ffffff' ),
					'color_ink'          => array( 'type' => 'color', 'label' => __( 'رنگ متن اصلی', 'yadam-biar' ), 'default' => '#1f2140' ),
					'color_muted'        => array( 'type' => 'color', 'label' => __( 'رنگ متن کم‌رنگ', 'yadam-biar' ), 'default' => '#6b6f7b' ),
					'color_surface'      => array( 'type' => 'color', 'label' => __( 'پس‌زمینه‌ی پاپ‌آپ و کارت‌ها', 'yadam-biar' ), 'default' => '#ffffff' ),
					'color_soft'         => array( 'type' => 'color', 'label' => __( 'پس‌زمینه‌ی ملایم', 'yadam-biar' ), 'default' => '#f7f7f9' ),
					'color_line'         => array( 'type' => 'color', 'label' => __( 'رنگ حاشیه‌ها', 'yadam-biar' ), 'default' => '#e6e7eb' ),
					'radius'             => array( 'type' => 'number', 'label' => __( 'گردی گوشه‌ها (پیکسل)', 'yadam-biar' ), 'default' => 14 ),
				),
			),
			'templates' => array(
				'label'  => __( 'قالب پیام‌ها', 'yadam-biar' ),
				'desc'   => __( 'متغیرها: {customer_name} {product_name} {variation} {price} {old_price} {regular_price} {target_price} {stock} {link} {site_name} {minutes} {exclusive_note} {manage_link} {code} {coupon} {coupon_amount} {coupon_expiry} {coupon_note} {alt_name} {alt_price} {alt_link} {alternatives_html} {waiting}', 'yadam-biar' ),
				'fields' => array(),
			),
			'growth'    => array(
				'label'  => __( 'رشد فروش', 'yadam-biar' ),
				'fields' => array(
					'_coupon_heading'        => array(
						'type'  => 'heading',
						'label' => __( 'کد تخفیف خودکار', 'yadam-biar' ),
						'desc'  => __( 'برای هر منتظر یک کد یکبار مصرف، محدود به همان محصول و با تاریخ انقضا ساخته و در پیام قرار داده می‌شود.', 'yadam-biar' ),
					),
					'coupon_enabled'         => array(
						'type'    => 'checkbox',
						'label'   => __( 'ساخت کد تخفیف اختصاصی در پیام', 'yadam-biar' ),
						'default' => 0,
					),
					'coupon_events'          => array(
						'type'    => 'multicheck',
						'label'   => __( 'برای کدام اعلان‌ها', 'yadam-biar' ),
						'options' => array(
							'back_in_stock' => __( 'موجود شدن', 'yadam-biar' ),
							'low_stock'     => __( 'موجود شدن تعداد محدود', 'yadam-biar' ),
							'combo'         => __( 'قانون ترکیبی', 'yadam-biar' ),
							'price_drop'    => __( 'کاهش قیمت', 'yadam-biar' ),
						),
						'default' => array( 'back_in_stock', 'combo' ),
					),
					'coupon_type'            => array(
						'type'    => 'select',
						'label'   => __( 'نوع تخفیف', 'yadam-biar' ),
						'options' => array(
							'percent'       => __( 'درصدی', 'yadam-biar' ),
							'fixed_product' => __( 'مبلغ ثابت', 'yadam-biar' ),
						),
						'default' => 'percent',
					),
					'coupon_amount'          => array(
						'type'    => 'number',
						'label'   => __( 'مقدار تخفیف (درصد یا مبلغ)', 'yadam-biar' ),
						'default' => 5,
					),
					'coupon_hours'           => array(
						'type'    => 'number',
						'label'   => __( 'اعتبار کد (ساعت)', 'yadam-biar' ),
						'default' => 48,
					),
					'coupon_restrict_email'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'کد فقط با ایمیل همان مشتری قابل استفاده باشد (در صورت داشتن ایمیل)', 'yadam-biar' ),
						'default' => 0,
					),
					'coupon_individual'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'با کدهای تخفیف دیگر ترکیب نشود', 'yadam-biar' ),
						'default' => 1,
					),
					'coupon_auto_apply'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'اعمال خودکار کد وقتی مشتری از لینک پیام وارد شود', 'yadam-biar' ),
						'default' => 1,
					),
					'_alt_heading'           => array(
						'type'  => 'heading',
						'label' => __( 'پیشنهاد محصول جایگزین', 'yadam-biar' ),
					),
					'alt_enabled'            => array(
						'type'    => 'checkbox',
						'label'   => __( 'ارسال پیشنهاد جایگزین برای منتظرانی که مدت زیادی منتظر مانده‌اند', 'yadam-biar' ),
						'default' => 0,
					),
					'alt_after_days'         => array(
						'type'    => 'number',
						'label'   => __( 'پس از چند روز انتظار', 'yadam-biar' ),
						'default' => 14,
					),
					'alt_count'              => array(
						'type'    => 'number',
						'label'   => __( 'تعداد پیشنهاد در پیام ارسالی', 'yadam-biar' ),
						'default' => 3,
					),
					'alt_page_count'         => array(
						'type'    => 'number',
						'label'   => __( 'تعداد محصولات مشابه در صفحه محصول (۱ تا ۱۲)', 'yadam-biar' ),
						'default' => 4,
					),
					'alt_price_range'        => array(
						'type'    => 'number',
						'label'   => __( 'بازه قیمت مشابه (± درصد)', 'yadam-biar' ),
						'default' => 30,
					),
					'alt_show_on_page'       => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش «جایگزین‌های موجود» در صفحه محصول ناموجود', 'yadam-biar' ),
						'default' => 1,
					),
					'_ph_heading'            => array(
						'type'  => 'heading',
						'label' => __( 'تاریخچه قیمت', 'yadam-biar' ),
					),
					'price_history_enabled'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'ثبت تاریخچه قیمت و نمایش نمودار', 'yadam-biar' ),
						'default' => 1,
					),
					'price_history_display'  => array(
						'type'    => 'select',
						'label'   => __( 'محل نمایش نمودار', 'yadam-biar' ),
						'options' => array(
							'tab'       => __( 'تب «تاریخچه قیمت» در صفحه محصول', 'yadam-biar' ),
							'widget'    => __( 'زیر دکمه یادم بیار', 'yadam-biar' ),
							'shortcode' => __( 'فقط شورت‌کد / المنتور', 'yadam-biar' ),
						),
						'default' => 'tab',
					),
					'price_history_days'     => array(
						'type'    => 'number',
						'label'   => __( 'بازه نمودار (روز)', 'yadam-biar' ),
						'default' => 90,
					),
					'_fc_heading'            => array(
						'type'  => 'heading',
						'label' => __( 'پیش‌بینی تقاضا', 'yadam-biar' ),
					),
					'forecast_lead_days'     => array(
						'type'    => 'number',
						'label'   => __( 'زمان تحویل تامین‌کننده (روز)', 'yadam-biar' ),
						'default' => 14,
					),
					'forecast_cover_days'    => array(
						'type'    => 'number',
						'label'   => __( 'موجودی برای چند روز فروش سفارش داده شود', 'yadam-biar' ),
						'default' => 30,
					),
					'forecast_default_conv'  => array(
						'type'    => 'number',
						'label'   => __( 'نرخ تبدیل پیش‌فرض منتظر به خریدار (درصد، وقتی داده کافی نیست)', 'yadam-biar' ),
						'default' => 30,
					),
					'forecast_service_level' => array(
						'type'    => 'select',
						'label'   => __( 'سطح اطمینان موجودی اطمینان', 'yadam-biar' ),
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
				'label'  => __( 'پیشرفته', 'yadam-biar' ),
				'fields' => array(
					'low_stock_threshold'   => array(
						'type'    => 'number',
						'label'   => __( 'آستانه «تعداد محدود» (پیام ویژه وقتی موجودی کمتر یا مساوی این عدد است)', 'yadam-biar' ),
						'default' => 5,
					),
					'waves_enabled'         => array(
						'type'    => 'checkbox',
						'label'   => __( 'ارسال موجی (به‌ترتیب ثبت، متناسب با موجودی)', 'yadam-biar' ),
						'desc'    => __( 'مثلاً با موجودی ۲ و ضریب ۳، ابتدا ۶ نفر اول مطلع می‌شوند؛ اگر کالا هنوز موجود بود موج بعدی ارسال می‌شود.', 'yadam-biar' ),
						'default' => 0,
					),
					'waves_multiplier'      => array(
						'type'    => 'number',
						'label'   => __( 'ضریب موج', 'yadam-biar' ),
						'default' => 3,
					),
					'waves_interval'        => array(
						'type'    => 'number',
						'label'   => __( 'فاصله موج‌ها (دقیقه)', 'yadam-biar' ),
						'default' => 60,
					),
					'exclusive_enabled'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'رزرو زودتر از دیگران (فرصت خرید اختصاصی برای منتظران)', 'yadam-biar' ),
						'default' => 0,
					),
					'exclusive_minutes'     => array(
						'type'    => 'number',
						'label'   => __( 'مدت فرصت اختصاصی (دقیقه)', 'yadam-biar' ),
						'default' => 30,
					),
					'verify_contact'        => array(
						'type'    => 'select',
						'label'   => __( 'تایید شماره/ایمیل با کد یکبار مصرف', 'yadam-biar' ),
						'options' => array(
							'none'  => __( 'غیرفعال', 'yadam-biar' ),
							'guest' => __( 'فقط مهمان‌ها', 'yadam-biar' ),
							'all'   => __( 'همه کاربران', 'yadam-biar' ),
						),
						'default' => 'none',
					),
					'strict_iran_mobile'    => array(
						'type'    => 'checkbox',
						'label'   => __( 'اعتبارسنجی شماره موبایل ایران (09xxxxxxxxx)', 'yadam-biar' ),
						'default' => 1,
					),
					'rate_limit'            => array(
						'type'    => 'number',
						'label'   => __( 'حداکثر درخواست از هر IP در ساعت', 'yadam-biar' ),
						'default' => 15,
					),
					'max_per_contact'       => array(
						'type'    => 'number',
						'label'   => __( 'حداکثر درخواست فعال برای هر شماره/ایمیل', 'yadam-biar' ),
						'default' => 50,
					),
					'batch_size'            => array(
						'type'    => 'number',
						'label'   => __( 'تعداد ارسال در هر نوبت پردازش', 'yadam-biar' ),
						'default' => 50,
					),
					'duplicate_window'      => array(
						'type'    => 'number',
						'label'   => __( 'جلوگیری از اعلان تکراری به یک نفر (ساعت)', 'yadam-biar' ),
						'desc'    => __( 'اگر یک نفر چند درخواست برای همان کالا داشته باشد (یا دوباره ثبت کند)، همان اعلان در این بازه دوباره برایش ارسال نمی‌شود. ۰ = غیرفعال.', 'yadam-biar' ),
						'default' => 72,
					),
					'price_change_cooldown' => array(
						'type'    => 'number',
						'label'   => __( 'حداقل فاصله دو اعلان «تغییر قیمت» برای یک درخواست (ساعت)', 'yadam-biar' ),
						'default' => 24,
					),
					'conversion_days'       => array(
						'type'    => 'number',
						'label'   => __( 'بازه نسبت‌دادن خرید به اعلان (روز)', 'yadam-biar' ),
						'default' => 14,
					),
					'webhook_url'           => array(
						'type'  => 'text',
						'label' => __( 'وب‌هوک خروجی (n8n / Zapier / CRM)', 'yadam-biar' ),
						'desc'  => __( 'پس از هر ثبت درخواست و هر ارسال اعلان، یک POST با JSON به این آدرس ارسال می‌شود.', 'yadam-biar' ),
					),
					'log_retention_days'    => array(
						'type'    => 'number',
						'label'   => __( 'نگهداری لاگ ارسال (روز)', 'yadam-biar' ),
						'default' => 90,
					),
					'delete_on_uninstall'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'حذف همه داده‌ها هنگام حذف افزونه', 'yadam-biar' ),
						'default' => 0,
					),
				),
			),
		);

		foreach ( self::default_templates() as $event => $tpl ) {
			$events = self::events();
			$schema['templates']['fields'][ '_tpl_' . $event ] = array(
				'type'  => 'heading',
				'label' => $events[ $event ],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_sms' ]     = array(
				'type'    => 'textarea',
				'label'   => __( 'متن کوتاه (پیامک / پیام‌رسان / پوش / داخل سایت)', 'yadam-biar' ),
				'default' => $tpl['sms'],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_subject' ] = array(
				'type'    => 'text',
				'label'   => __( 'عنوان (ایمیل / اعلان)', 'yadam-biar' ),
				'default' => $tpl['subject'],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_body' ]    = array(
				'type'    => 'html',
				'label'   => __( 'متن ایمیل (HTML)', 'yadam-biar' ),
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
		foreach ( self::schema() as $tab ) {
			foreach ( $tab['fields'] as $key => $field ) {
				if ( in_array( $field['type'], array( 'heading', 'group' ), true ) ) {
					continue;
				}
				$defaults[ $key ] = isset( $field['default'] ) ? $field['default'] : ( 'multicheck' === $field['type'] ? array() : '' );
			}
		}
		return $defaults;
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Is the setting value in a multicheck list.
	 *
	 * @param string $key   Key.
	 * @param string $value Value.
	 * @return bool
	 */
	public static function has( $key, $value ) {
		$list = (array) self::get( $key, array() );
		return in_array( $value, $list, true );
	}

	/**
	 * Save settings (sanitized against the schema).
	 *
	 * @param array $input Raw input.
	 * @param string $tab  Tab being saved.
	 */
	public static function save( $input, $tab ) {
		$schema  = self::schema();
		$current = self::all();
		if ( empty( $schema[ $tab ] ) ) {
			return;
		}
		foreach ( $schema[ $tab ]['fields'] as $key => $field ) {
			$raw = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : null;
			switch ( $field['type'] ) {
				case 'heading':
				case 'group':
					continue 2;
				case 'checkbox':
					$current[ $key ] = $raw ? 1 : 0;
					break;
				case 'multicheck':
					$current[ $key ] = array_values( array_intersect( array_map( 'strval', (array) $raw ), array_keys( $field['options'] ) ) );
					break;
				case 'number':
					$current[ $key ] = max( 0, (int) $raw );
					break;
				case 'sms_patterns':
					$current[ $key ]            = self::sanitize_pattern_items( $raw );
					$current['sms_patterns']    = ''; // Legacy textarea values are superseded.
					$current['sms_pattern_vars'] = '';
					break;
				case 'color':
					$hex             = sanitize_hex_color( (string) $raw );
					$current[ $key ] = $hex ? $hex : $field['default'];
					break;
				case 'select':
					$current[ $key ] = isset( $field['options'][ $raw ] ) ? $raw : $field['default'];
					break;
				case 'textarea':
					$current[ $key ] = sanitize_textarea_field( (string) $raw );
					break;
				case 'html':
					$current[ $key ] = wp_kses_post( (string) $raw );
					break;
				case 'media':
					$current[ $key ] = esc_url_raw( trim( (string) $raw ) );
					break;
				default:
					$current[ $key ] = sanitize_text_field( (string) $raw );
			}
		}
		// Eitaa (1.5.6) and WhatsApp (1.7.1) were removed: drop their leftover settings.
		unset( $current['eitaa_token'], $current['eitaa_channel'] );
		foreach ( array_keys( $current ) as $k ) {
			if ( 0 === strpos( $k, 'whatsapp_' ) ) {
				unset( $current[ $k ] );
			}
		}
		update_option( self::OPTION, $current );
		self::$cache = null;
	}

	/**
	 * Reset cache (tests / after save).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Parse "key=value" lines into an array.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function parse_lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$pos = strpos( $line, '=' );
			if ( false === $pos ) {
				continue;
			}
			$k = trim( substr( $line, 0, $pos ) );
			if ( '' !== $k ) {
				$out[ $k ] = trim( substr( $line, $pos + 1 ) );
			}
		}
		return $out;
	}

	/**
	 * Pattern items: [ event => [ 'event', 'code', 'vars' => [ [name, value], … ] ] ].
	 * Falls back to the pre-1.1.9 textarea settings so nothing is lost on upgrade.
	 *
	 * @return array
	 */
	public static function pattern_items() {
		$items = self::get( 'sms_pattern_items', array() );
		if ( is_array( $items ) && $items ) {
			return $items;
		}
		$codes = self::parse_lines( self::get( 'sms_patterns', '' ) );
		$vars  = array();
		foreach ( self::parse_lines( self::get( 'sms_pattern_vars', '' ) ) as $name => $value ) {
			$vars[] = array( 'name' => $name, 'value' => $value );
		}
		$out = array();
		foreach ( $codes as $event => $code ) {
			if ( '' !== $code ) {
				$out[ $event ] = array( 'event' => $event, 'code' => $code, 'vars' => $vars );
			}
		}
		return $out;
	}

	/**
	 * Sanitize the posted pattern repeater (one pattern per event).
	 *
	 * @param mixed $raw Posted rows.
	 * @return array
	 */
	public static function sanitize_pattern_items( $raw ) {
		$events = self::events();
		$out    = array();
		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$event = isset( $row['event'] ) ? sanitize_key( $row['event'] ) : '';
			$code  = isset( $row['code'] ) ? sanitize_text_field( $row['code'] ) : '';
			if ( ! isset( $events[ $event ] ) || '' === $code ) {
				continue;
			}
			$vars = array();
			foreach ( isset( $row['vars'] ) ? (array) $row['vars'] : array() as $v ) {
				$name  = isset( $v['name'] ) ? sanitize_text_field( $v['name'] ) : '';
				$value = isset( $v['value'] ) ? sanitize_text_field( $v['value'] ) : '';
				if ( '' !== $name && '' !== $value ) {
					$vars[] = array( 'name' => $name, 'value' => $value );
				}
			}
			$out[ $event ] = array( 'event' => $event, 'code' => $code, 'vars' => $vars );
		}
		return $out;
	}

	/**
	 * CSS custom properties for the appearance settings.
	 *
	 * @return string e.g. "--yadambiar-accent:#f4511e;…"
	 */
	public static function css_vars() {
		$map = array(
			'color_accent'      => '--yadambiar-accent',
			'color_button_text' => '--yadambiar-on-accent',
			'color_ink'         => '--yadambiar-ink',
			'color_muted'       => '--yadambiar-muted',
			'color_surface'     => '--yadambiar-bg',
			'color_soft'        => '--yadambiar-soft',
			'color_line'        => '--yadambiar-line',
		);
		$out = '';
		foreach ( $map as $key => $var ) {
			$hex = sanitize_hex_color( (string) self::get( $key, '' ) );
			if ( $hex ) {
				$out .= $var . ':' . $hex . ';';
			}
		}
		$r = (int) self::get( 'radius', 14 );
		if ( $r >= 0 && $r <= 40 ) {
			$out .= '--yadambiar-radius:' . $r . 'px;';
		}
		return $out;
	}
}
