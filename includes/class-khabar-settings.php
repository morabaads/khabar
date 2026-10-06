<?php
/**
 * Settings schema, defaults and accessors.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Settings {

	const OPTION = 'khabar_settings';

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
			'back_in_stock' => __( 'موجود شدن', 'khabar' ),
			'low_stock'     => __( 'موجود شدن تعداد محدود', 'khabar' ),
			'price_drop'    => __( 'کاهش قیمت', 'khabar' ),
			'price_rise'    => __( 'افزایش قیمت', 'khabar' ),
			'price_change'  => __( 'تغییر قیمت', 'khabar' ),
			'combo'         => __( 'قانون ترکیبی', 'khabar' ),
			'alternatives'  => __( 'پیشنهاد محصول جایگزین', 'khabar' ),
			'broadcast'     => __( 'اعلام موجود شدن در کانال', 'khabar' ),
			'otp'           => __( 'کد تایید', 'khabar' ),
		);
	}

	/**
	 * Available channels.
	 *
	 * @return array
	 */
	public static function channels() {
		return array(
			'sms'      => __( 'پیامک', 'khabar' ),
			'email'    => __( 'ایمیل', 'khabar' ),
			'onsite'   => __( 'اعلان داخل سایت', 'khabar' ),
			'push'     => __( 'پوش نوتیفیکیشن', 'khabar' ),
			'whatsapp' => __( 'واتساپ', 'khabar' ),
			'telegram' => __( 'تلگرام', 'khabar' ),
			'bale'     => __( 'بله', 'khabar' ),
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
				'sms'     => "کد تایید خبرم کن: {code}\n{site_name}",
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
				'label'  => __( 'عمومی', 'khabar' ),
				'fields' => array(
					'enabled_types'           => array(
						'type'    => 'multicheck',
						'label'   => __( 'انواع اعلان فعال', 'khabar' ),
						'options' => array(
							'stock'        => __( 'موجود شدن', 'khabar' ),
							'price_drop'   => __( 'کاهش قیمت (قیمت هدف)', 'khabar' ),
							'price_rise'   => __( 'افزایش قیمت', 'khabar' ),
							'price_change' => __( 'هر تغییر قیمت', 'khabar' ),
							'min_qty'      => __( 'حداقل موجودی (قانون ترکیبی)', 'khabar' ),
						),
						'default' => array( 'stock', 'price_drop', 'price_rise', 'price_change', 'min_qty' ),
					),
					'contact_mode'            => array(
						'type'    => 'select',
						'label'   => __( 'اطلاعات تماس لازم', 'khabar' ),
						'options' => array(
							'either' => __( 'موبایل یا ایمیل (حداقل یکی)', 'khabar' ),
							'phone'  => __( 'فقط موبایل', 'khabar' ),
							'email'  => __( 'فقط ایمیل', 'khabar' ),
							'both'   => __( 'هر دو الزامی', 'khabar' ),
						),
						'default' => 'either',
					),
					'require_login'           => array(
						'type'    => 'checkbox',
						'label'   => __( 'فقط کاربران عضو بتوانند درخواست ثبت کنند', 'khabar' ),
						'default' => 0,
					),
					'position'                => array(
						'type'    => 'select',
						'label'   => __( 'محل نمایش در صفحه محصول', 'khabar' ),
						'options' => array(
							'after_cart'  => __( 'بعد از دکمه افزودن به سبد', 'khabar' ),
							'after_price' => __( 'بعد از قیمت', 'khabar' ),
							'after_meta'  => __( 'بعد از دسته‌بندی‌ها', 'khabar' ),
							'shortcode'   => __( 'فقط با شورت‌کد [khabar]', 'khabar' ),
						),
						'default' => 'after_cart',
					),
					'button_text_stock'       => array(
						'type'    => 'text',
						'label'   => __( 'متن دکمه موجود شدن', 'khabar' ),
						'default' => '🔔 خبرم کن وقتی موجود شد',
					),
					'button_text_price'       => array(
						'type'    => 'text',
						'label'   => __( 'متن دکمه هشدار قیمت', 'khabar' ),
						'default' => 'خبرم کن وقتی ارزان شد',
					),
					'show_price_alert'        => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش هشدار قیمت برای محصولات موجود', 'khabar' ),
						'default' => 1,
					),
					'annotate_variations'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'علامت‌گذاری گزینه‌های ناموجود در لیست تنوع‌ها (مثلاً «۴۲ — ناموجود 🔔»)', 'khabar' ),
						'default' => 1,
					),
					'show_waiting_count'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش تعداد منتظران (اثبات اجتماعی)', 'khabar' ),
						'default' => 1,
					),
					'waiting_count_min'       => array(
						'type'    => 'number',
						'label'   => __( 'حداقل تعداد منتظر برای نمایش', 'khabar' ),
						'default' => 3,
					),
					'backorder_as_in_stock'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'وضعیت «پیش‌خرید» موجود حساب شود', 'khabar' ),
						'default' => 0,
					),
					'price_requires_stock'    => array(
						'type'    => 'checkbox',
						'label'   => __( 'هشدار قیمت فقط وقتی کالا موجود است ارسال شود', 'khabar' ),
						'default' => 1,
					),
					'expiry_days'             => array(
						'type'    => 'number',
						'label'   => __( 'انقضای درخواست‌ها (روز، ۰ = بدون انقضا)', 'khabar' ),
						'default' => 180,
					),
					'success_message'         => array(
						'type'    => 'text',
						'label'   => __( 'پیام موفقیت', 'khabar' ),
						'default' => 'درخواست شما ثبت شد، هنگام موجود شدن اطلاع می‌دهیم.',
					),
					'privacy_text'            => array(
						'type'    => 'text',
						'label'   => __( 'متن حریم خصوصی زیر فرم', 'khabar' ),
						'default' => 'اطلاعات شما فقط برای ارسال همین اعلان استفاده می‌شود.',
					),
				),
			),
			'channels'  => array(
				'label'  => __( 'کانال‌های ارسال', 'khabar' ),
				'fields' => array(
					'channels_enabled'   => array(
						'type'    => 'multicheck',
						'label'   => __( 'کانال‌های فعال', 'khabar' ),
						'options' => self::channels(),
						'default' => array( 'sms', 'email', 'onsite' ),
					),
					'user_selects_channel' => array(
						'type'    => 'checkbox',
						'label'   => __( 'کاربر بتواند کانال دریافت را انتخاب کند', 'khabar' ),
						'default' => 1,
					),
					'floating_bell'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش زنگوله شناور اعلان‌های داخل سایت', 'khabar' ),
						'default' => 1,
					),
					'_sms_heading'       => array(
						'type'  => 'heading',
						'label' => __( 'پیامک', 'khabar' ),
					),
					'sms_gateway'        => array(
						'type'    => 'select',
						'label'   => __( 'سامانه پیامک', 'khabar' ),
						'desc'    => __( 'پنل‌های «سازگار» روی پلتفرم مشترک (ملی پیامک / IPPanel) یا وب‌سرویس HTTP کار می‌کنند؛ پلتفرم و آدرس API را از بخش «وب‌سرویس» پنل خود بردارید.', 'khabar' ),
						'options' => Khabar_Sms_Providers::options(),
						'default' => 'kavenegar',
					),
					'sms_platform'       => array(
						'show_if' => array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( array(), true ) ),
						'type'    => 'select',
						'label'   => __( 'پلتفرم وب‌سرویس پنل', 'khabar' ),
						'desc'    => __( 'در مستندات وب‌سرویس پنل خود ببینید: اگر آدرس‌ها شبیه /api/SendSMS/ است «ملی پیامک»، اگر شبیه /api/v1/sms/ است «IPPanel»، و در غیر این صورت «وب‌سرویس سفارشی».', 'khabar' ),
						'options' => array(
							'payamak_panel' => __( 'سازگار با ملی پیامک (Payamak-Panel)', 'khabar' ),
							'ippanel'       => __( 'سازگار با IPPanel', 'khabar' ),
							'webhook'       => __( 'وب‌سرویس سفارشی (HTTP)', 'khabar' ),
						),
						'default' => 'payamak_panel',
					),
					'sms_base_url'       => array(
						'show_if' => array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( array( 'payamak_panel', 'ippanel' ), true ) ),
						'type'    => 'text',
						'label'   => __( 'آدرس API پنل', 'khabar' ),
						'desc'    => __( 'برای پنل‌های سازگار الزامی است (مثلاً https://rest.example.ir). برای ملی پیامک و IPPanel خالی بگذارید.', 'khabar' ),
					),
					'sms_api_key'        => array(
						'show_if' => array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( Khabar_Sms_Providers::KEY_DRIVERS, true ) ),
						'type'  => 'password',
						'label' => __( 'API Key / توکن', 'khabar' ),
					),
					'sms_username'       => array(
						'show_if' => array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( Khabar_Sms_Providers::USER_DRIVERS, true ) ),
						'type'  => 'text',
						'label' => __( 'نام کاربری پنل', 'khabar' ),
					),
					'sms_password'       => array(
						'show_if' => array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( Khabar_Sms_Providers::USER_DRIVERS, true ) ),
						'type'  => 'password',
						'label' => __( 'رمز عبور پنل', 'khabar' ),
					),
					'sms_sender'         => array(
						'show_if' => array( 'sms_gateway' => array_values( array_diff( array_keys( Khabar_Sms_Providers::all() ), array( 'webhook' ) ) ) ),
						'type'  => 'text',
						'label' => __( 'شماره فرستنده (خط)', 'khabar' ),
					),
					'sms_mode'           => array(
						'show_if' => array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( Khabar_Sms_Providers::PATTERN_DRIVERS, true ) ),
						'type'    => 'select',
						'label'   => __( 'نوع ارسال', 'khabar' ),
						'options' => array(
							'text'    => __( 'متنی (از قالب پیام)', 'khabar' ),
							'pattern' => __( 'خدماتی / پترن', 'khabar' ),
						),
						'default' => 'text',
					),
					'sms_pattern_items'  => array(
						'type'    => 'sms_patterns',
						'label'   => __( 'پترن‌های پیامک', 'khabar' ),
						'default' => array(),
					),
					'sms_webhook_url'    => array(
						'show_if' => array( array( 'sms_gateway' => array( 'webhook' ) ), array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( array(), true ), 'sms_platform' => array( 'webhook' ) ) ),
						'type'  => 'text',
						'label' => __( 'آدرس وب‌سرویس سفارشی', 'khabar' ),
						'desc'  => __( 'متغیرها: {to} {message}', 'khabar' ),
					),
					'sms_webhook_body'   => array(
						'show_if' => array( array( 'sms_gateway' => array( 'webhook' ) ), array( 'sms_gateway' => Khabar_Sms_Providers::keys_for( array(), true ), 'sms_platform' => array( 'webhook' ) ) ),
						'type'    => 'textarea',
						'label'   => __( 'بدنه درخواست سفارشی (JSON، خالی = GET)', 'khabar' ),
						'default' => '{"to":"{to}","text":"{message}"}',
					),
					'_wa_heading'        => array(
						'type'  => 'heading',
						'label' => __( 'واتساپ', 'khabar' ),
					),
					'whatsapp_provider'  => array(
						'type'    => 'select',
						'label'   => __( 'سرویس‌دهنده', 'khabar' ),
						'options' => array(
							'cloud'   => __( 'WhatsApp Cloud API (Meta)', 'khabar' ),
							'webhook' => __( 'وب‌سرویس سفارشی', 'khabar' ),
						),
						'default' => 'cloud',
					),
					'whatsapp_token'     => array(
						'show_if' => array( 'whatsapp_provider' => array( 'cloud' ) ),
						'type'  => 'password',
						'label' => __( 'Access Token', 'khabar' ),
					),
					'whatsapp_phone_id'  => array(
						'show_if' => array( 'whatsapp_provider' => array( 'cloud' ) ),
						'type'  => 'text',
						'label' => __( 'Phone Number ID', 'khabar' ),
					),
					'whatsapp_template'  => array(
						'show_if' => array( 'whatsapp_provider' => array( 'cloud' ) ),
						'type'  => 'text',
						'label' => __( 'نام Template تایید شده', 'khabar' ),
						'desc'  => __( 'بدنه تمپلیت باید یک متغیر {{1}} داشته باشد؛ متن پیام در آن قرار می‌گیرد. خالی = ارسال متن ساده.', 'khabar' ),
					),
					'whatsapp_lang'      => array(
						'show_if' => array( 'whatsapp_provider' => array( 'cloud' ) ),
						'type'    => 'text',
						'label'   => __( 'زبان Template', 'khabar' ),
						'default' => 'fa',
					),
					'whatsapp_country'   => array(
						'type'    => 'text',
						'label'   => __( 'پیش‌شماره کشور', 'khabar' ),
						'default' => '98',
					),
					'whatsapp_webhook_url' => array(
						'show_if' => array( 'whatsapp_provider' => array( 'webhook' ) ),
						'type'  => 'text',
						'label' => __( 'آدرس وب‌سرویس سفارشی واتساپ', 'khabar' ),
					),
					'_msg_heading'       => array(
						'type'  => 'heading',
						'label' => __( 'پیام‌رسان‌ها (تلگرام، بله، ایتا)', 'khabar' ),
						'desc'  => __( 'کاربر با یک کلیک ربات را استارت می‌کند و حسابش متصل می‌شود. پس از وارد کردن توکن، دکمه «ثبت وب‌هوک ربات‌ها» را بزنید (سایت باید HTTPS باشد).', 'khabar' ),
					),
					'telegram_token'     => array(
						'type'  => 'password',
						'label' => __( 'توکن ربات تلگرام', 'khabar' ),
						'desc'  => __( 'از @BotFather', 'khabar' ),
					),
					'telegram_bot'       => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری ربات تلگرام (بدون @)', 'khabar' ),
					),
					'telegram_api_base'  => array(
						'type'    => 'text',
						'label'   => __( 'آدرس API تلگرام', 'khabar' ),
						'default' => 'https://api.telegram.org',
						'desc'    => __( 'اگر سرور شما در ایران است و به تلگرام دسترسی ندارد، آدرس یک پراکسی معکوس Bot API را وارد کنید.', 'khabar' ),
					),
					'bale_token'         => array(
						'type'  => 'password',
						'label' => __( 'توکن ربات بله', 'khabar' ),
						'desc'  => __( 'از @botfather در بله', 'khabar' ),
					),
					'bale_bot'           => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری ربات بله (بدون @)', 'khabar' ),
					),
					'eitaa_token'        => array(
						'type'  => 'password',
						'label' => __( 'توکن ایتایار', 'khabar' ),
						'desc'  => __( 'API ایتایار فقط امکان ارسال به کانال/گروه را دارد؛ ایتا برای اعلام عمومی در کانال استفاده می‌شود.', 'khabar' ),
					),
					'broadcast_networks' => array(
						'type'    => 'multicheck',
						'label'   => __( 'اعلام موجود شدن کالاهای پرتقاضا در کانال فروشگاه', 'khabar' ),
						'options' => array(
							'telegram' => __( 'کانال تلگرام', 'khabar' ),
							'bale'     => __( 'کانال بله', 'khabar' ),
							'eitaa'    => __( 'کانال ایتا', 'khabar' ),
						),
						'default' => array(),
					),
					'telegram_channel'   => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال تلگرام', 'khabar' ),
						'desc'  => __( 'مثل ‎@myshop یا ‎-100123…؛ ربات باید ادمین کانال باشد.', 'khabar' ),
					),
					'bale_channel'       => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال بله', 'khabar' ),
					),
					'eitaa_channel'      => array(
						'type'  => 'text',
						'label' => __( 'شناسه کانال ایتا', 'khabar' ),
					),
					'broadcast_min_waiting' => array(
						'type'    => 'number',
						'label'   => __( 'حداقل تعداد منتظر برای اعلام در کانال', 'khabar' ),
						'default' => 3,
					),
					'_push_heading'      => array(
						'type'  => 'heading',
						'label' => __( 'پوش نوتیفیکیشن', 'khabar' ),
						'desc'  => __( 'کلیدهای VAPID به‌صورت خودکار ساخته می‌شوند. سایت باید HTTPS باشد.', 'khabar' ),
					),
					'push_icon'          => array(
						'type'  => 'text',
						'label' => __( 'آدرس آیکون اعلان', 'khabar' ),
					),
				),
			),
			'appearance' => array(
				'label'  => __( 'ظاهر و رنگ‌ها', 'khabar' ),
				'fields' => array(
					'_look_heading'      => array(
						'type'  => 'heading',
						'label' => __( 'رنگ‌بندی پاپ‌آپ، دکمه‌ها و کارت‌ها', 'khabar' ),
						'desc'  => __( 'این رنگ‌ها روی همه‌ی بخش‌های سمت مشتری اعمال می‌شود. در ویجت‌های المنتور هم می‌توان برای هر ویجت جداگانه تغییرشان داد.', 'khabar' ),
					),
					'color_accent'       => array( 'type' => 'color', 'label' => __( 'رنگ اصلی (دکمه‌ها، تیک‌ها، تأکید)', 'khabar' ), 'default' => '#f4511e' ),
					'color_button_text'  => array( 'type' => 'color', 'label' => __( 'رنگ متن دکمه‌ها', 'khabar' ), 'default' => '#ffffff' ),
					'color_ink'          => array( 'type' => 'color', 'label' => __( 'رنگ متن اصلی', 'khabar' ), 'default' => '#1f2140' ),
					'color_muted'        => array( 'type' => 'color', 'label' => __( 'رنگ متن کم‌رنگ', 'khabar' ), 'default' => '#6b6f7b' ),
					'color_surface'      => array( 'type' => 'color', 'label' => __( 'پس‌زمینه‌ی پاپ‌آپ و کارت‌ها', 'khabar' ), 'default' => '#ffffff' ),
					'color_soft'         => array( 'type' => 'color', 'label' => __( 'پس‌زمینه‌ی ملایم', 'khabar' ), 'default' => '#f7f7f9' ),
					'color_line'         => array( 'type' => 'color', 'label' => __( 'رنگ حاشیه‌ها', 'khabar' ), 'default' => '#e6e7eb' ),
					'radius'             => array( 'type' => 'number', 'label' => __( 'گردی گوشه‌ها (پیکسل)', 'khabar' ), 'default' => 14 ),
				),
			),
			'templates' => array(
				'label'  => __( 'قالب پیام‌ها', 'khabar' ),
				'desc'   => __( 'متغیرها: {customer_name} {product_name} {variation} {price} {old_price} {regular_price} {target_price} {stock} {link} {site_name} {minutes} {exclusive_note} {manage_link} {code} {coupon} {coupon_amount} {coupon_expiry} {coupon_note} {alt_name} {alt_price} {alt_link} {alternatives_html} {waiting}', 'khabar' ),
				'fields' => array(),
			),
			'growth'    => array(
				'label'  => __( 'رشد فروش', 'khabar' ),
				'fields' => array(
					'_coupon_heading'        => array(
						'type'  => 'heading',
						'label' => __( 'کد تخفیف خودکار', 'khabar' ),
						'desc'  => __( 'برای هر منتظر یک کد یکبار مصرف، محدود به همان محصول و با تاریخ انقضا ساخته و در پیام قرار داده می‌شود.', 'khabar' ),
					),
					'coupon_enabled'         => array(
						'type'    => 'checkbox',
						'label'   => __( 'ساخت کد تخفیف اختصاصی در پیام', 'khabar' ),
						'default' => 0,
					),
					'coupon_events'          => array(
						'type'    => 'multicheck',
						'label'   => __( 'برای کدام اعلان‌ها', 'khabar' ),
						'options' => array(
							'back_in_stock' => __( 'موجود شدن', 'khabar' ),
							'low_stock'     => __( 'موجود شدن تعداد محدود', 'khabar' ),
							'combo'         => __( 'قانون ترکیبی', 'khabar' ),
							'price_drop'    => __( 'کاهش قیمت', 'khabar' ),
						),
						'default' => array( 'back_in_stock', 'combo' ),
					),
					'coupon_type'            => array(
						'type'    => 'select',
						'label'   => __( 'نوع تخفیف', 'khabar' ),
						'options' => array(
							'percent'       => __( 'درصدی', 'khabar' ),
							'fixed_product' => __( 'مبلغ ثابت', 'khabar' ),
						),
						'default' => 'percent',
					),
					'coupon_amount'          => array(
						'type'    => 'number',
						'label'   => __( 'مقدار تخفیف (درصد یا مبلغ)', 'khabar' ),
						'default' => 5,
					),
					'coupon_hours'           => array(
						'type'    => 'number',
						'label'   => __( 'اعتبار کد (ساعت)', 'khabar' ),
						'default' => 48,
					),
					'coupon_restrict_email'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'کد فقط با ایمیل همان مشتری قابل استفاده باشد (در صورت داشتن ایمیل)', 'khabar' ),
						'default' => 0,
					),
					'coupon_individual'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'با کدهای تخفیف دیگر ترکیب نشود', 'khabar' ),
						'default' => 1,
					),
					'coupon_auto_apply'      => array(
						'type'    => 'checkbox',
						'label'   => __( 'اعمال خودکار کد وقتی مشتری از لینک پیام وارد شود', 'khabar' ),
						'default' => 1,
					),
					'_alt_heading'           => array(
						'type'  => 'heading',
						'label' => __( 'پیشنهاد محصول جایگزین', 'khabar' ),
					),
					'alt_enabled'            => array(
						'type'    => 'checkbox',
						'label'   => __( 'ارسال پیشنهاد جایگزین برای منتظرانی که مدت زیادی منتظر مانده‌اند', 'khabar' ),
						'default' => 0,
					),
					'alt_after_days'         => array(
						'type'    => 'number',
						'label'   => __( 'پس از چند روز انتظار', 'khabar' ),
						'default' => 14,
					),
					'alt_count'              => array(
						'type'    => 'number',
						'label'   => __( 'تعداد پیشنهاد در پیام ارسالی', 'khabar' ),
						'default' => 3,
					),
					'alt_page_count'         => array(
						'type'    => 'number',
						'label'   => __( 'تعداد محصولات مشابه در صفحه محصول (۱ تا ۱۲)', 'khabar' ),
						'default' => 4,
					),
					'alt_price_range'        => array(
						'type'    => 'number',
						'label'   => __( 'بازه قیمت مشابه (± درصد)', 'khabar' ),
						'default' => 30,
					),
					'alt_show_on_page'       => array(
						'type'    => 'checkbox',
						'label'   => __( 'نمایش «جایگزین‌های موجود» در صفحه محصول ناموجود', 'khabar' ),
						'default' => 1,
					),
					'_ph_heading'            => array(
						'type'  => 'heading',
						'label' => __( 'تاریخچه قیمت', 'khabar' ),
					),
					'price_history_enabled'  => array(
						'type'    => 'checkbox',
						'label'   => __( 'ثبت تاریخچه قیمت و نمایش نمودار', 'khabar' ),
						'default' => 1,
					),
					'price_history_display'  => array(
						'type'    => 'select',
						'label'   => __( 'محل نمایش نمودار', 'khabar' ),
						'options' => array(
							'tab'       => __( 'تب «تاریخچه قیمت» در صفحه محصول', 'khabar' ),
							'widget'    => __( 'زیر دکمه خبرم کن', 'khabar' ),
							'shortcode' => __( 'فقط شورت‌کد / المنتور', 'khabar' ),
						),
						'default' => 'tab',
					),
					'price_history_days'     => array(
						'type'    => 'number',
						'label'   => __( 'بازه نمودار (روز)', 'khabar' ),
						'default' => 90,
					),
					'_fc_heading'            => array(
						'type'  => 'heading',
						'label' => __( 'پیش‌بینی تقاضا', 'khabar' ),
					),
					'forecast_lead_days'     => array(
						'type'    => 'number',
						'label'   => __( 'زمان تحویل تامین‌کننده (روز)', 'khabar' ),
						'default' => 14,
					),
					'forecast_cover_days'    => array(
						'type'    => 'number',
						'label'   => __( 'موجودی برای چند روز فروش سفارش داده شود', 'khabar' ),
						'default' => 30,
					),
					'forecast_default_conv'  => array(
						'type'    => 'number',
						'label'   => __( 'نرخ تبدیل پیش‌فرض منتظر به خریدار (درصد، وقتی داده کافی نیست)', 'khabar' ),
						'default' => 30,
					),
					'forecast_service_level' => array(
						'type'    => 'select',
						'label'   => __( 'سطح اطمینان موجودی اطمینان', 'khabar' ),
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
				'label'  => __( 'پیشرفته', 'khabar' ),
				'fields' => array(
					'low_stock_threshold'   => array(
						'type'    => 'number',
						'label'   => __( 'آستانه «تعداد محدود» (پیام ویژه وقتی موجودی کمتر یا مساوی این عدد است)', 'khabar' ),
						'default' => 5,
					),
					'waves_enabled'         => array(
						'type'    => 'checkbox',
						'label'   => __( 'ارسال موجی (به‌ترتیب ثبت، متناسب با موجودی)', 'khabar' ),
						'desc'    => __( 'مثلاً با موجودی ۲ و ضریب ۳، ابتدا ۶ نفر اول مطلع می‌شوند؛ اگر کالا هنوز موجود بود موج بعدی ارسال می‌شود.', 'khabar' ),
						'default' => 0,
					),
					'waves_multiplier'      => array(
						'type'    => 'number',
						'label'   => __( 'ضریب موج', 'khabar' ),
						'default' => 3,
					),
					'waves_interval'        => array(
						'type'    => 'number',
						'label'   => __( 'فاصله موج‌ها (دقیقه)', 'khabar' ),
						'default' => 60,
					),
					'exclusive_enabled'     => array(
						'type'    => 'checkbox',
						'label'   => __( 'رزرو زودتر از دیگران (فرصت خرید اختصاصی برای منتظران)', 'khabar' ),
						'default' => 0,
					),
					'exclusive_minutes'     => array(
						'type'    => 'number',
						'label'   => __( 'مدت فرصت اختصاصی (دقیقه)', 'khabar' ),
						'default' => 30,
					),
					'verify_contact'        => array(
						'type'    => 'select',
						'label'   => __( 'تایید شماره/ایمیل با کد یکبار مصرف', 'khabar' ),
						'options' => array(
							'none'  => __( 'غیرفعال', 'khabar' ),
							'guest' => __( 'فقط مهمان‌ها', 'khabar' ),
							'all'   => __( 'همه کاربران', 'khabar' ),
						),
						'default' => 'none',
					),
					'strict_iran_mobile'    => array(
						'type'    => 'checkbox',
						'label'   => __( 'اعتبارسنجی شماره موبایل ایران (09xxxxxxxxx)', 'khabar' ),
						'default' => 1,
					),
					'rate_limit'            => array(
						'type'    => 'number',
						'label'   => __( 'حداکثر درخواست از هر IP در ساعت', 'khabar' ),
						'default' => 15,
					),
					'max_per_contact'       => array(
						'type'    => 'number',
						'label'   => __( 'حداکثر درخواست فعال برای هر شماره/ایمیل', 'khabar' ),
						'default' => 50,
					),
					'batch_size'            => array(
						'type'    => 'number',
						'label'   => __( 'تعداد ارسال در هر نوبت پردازش', 'khabar' ),
						'default' => 50,
					),
					'price_change_cooldown' => array(
						'type'    => 'number',
						'label'   => __( 'حداقل فاصله دو اعلان «تغییر قیمت» برای یک درخواست (ساعت)', 'khabar' ),
						'default' => 24,
					),
					'conversion_days'       => array(
						'type'    => 'number',
						'label'   => __( 'بازه نسبت‌دادن خرید به اعلان (روز)', 'khabar' ),
						'default' => 14,
					),
					'webhook_url'           => array(
						'type'  => 'text',
						'label' => __( 'وب‌هوک خروجی (n8n / Zapier / CRM)', 'khabar' ),
						'desc'  => __( 'پس از هر ثبت درخواست و هر ارسال اعلان، یک POST با JSON به این آدرس ارسال می‌شود.', 'khabar' ),
					),
					'log_retention_days'    => array(
						'type'    => 'number',
						'label'   => __( 'نگهداری لاگ ارسال (روز)', 'khabar' ),
						'default' => 90,
					),
					'delete_on_uninstall'   => array(
						'type'    => 'checkbox',
						'label'   => __( 'حذف همه داده‌ها هنگام حذف افزونه', 'khabar' ),
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
				'label'   => __( 'متن کوتاه (پیامک / واتساپ / پوش / داخل سایت)', 'khabar' ),
				'default' => $tpl['sms'],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_subject' ] = array(
				'type'    => 'text',
				'label'   => __( 'عنوان (ایمیل / اعلان)', 'khabar' ),
				'default' => $tpl['subject'],
			);
			$schema['templates']['fields'][ 'tpl_' . $event . '_body' ]    = array(
				'type'    => 'html',
				'label'   => __( 'متن ایمیل (HTML)', 'khabar' ),
				'default' => $tpl['body'],
			);
		}

		return apply_filters( 'khabar_settings_schema', $schema );
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
				if ( 'heading' === $field['type'] ) {
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
				default:
					$current[ $key ] = sanitize_text_field( (string) $raw );
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
	 * @return string e.g. "--khabar-accent:#f4511e;…"
	 */
	public static function css_vars() {
		$map = array(
			'color_accent'      => '--khabar-accent',
			'color_button_text' => '--khabar-on-accent',
			'color_ink'         => '--khabar-ink',
			'color_muted'       => '--khabar-muted',
			'color_surface'     => '--khabar-bg',
			'color_soft'        => '--khabar-soft',
			'color_line'        => '--khabar-line',
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
			$out .= '--khabar-radius:' . $r . 'px;';
		}
		return $out;
	}
}
