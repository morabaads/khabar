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
						'options' => array(
							'kavenegar'   => __( 'کاوه‌نگار', 'khabar' ),
							'melipayamak' => __( 'ملی پیامک', 'khabar' ),
							'ippanel'     => __( 'IPPanel / فراز اس‌ام‌اس', 'khabar' ),
							'smsir'       => __( 'SMS.ir', 'khabar' ),
							'webhook'     => __( 'وب‌سرویس سفارشی (HTTP)', 'khabar' ),
						),
						'default' => 'kavenegar',
					),
					'sms_api_key'        => array(
						'type'  => 'password',
						'label' => __( 'API Key', 'khabar' ),
					),
					'sms_username'       => array(
						'type'  => 'text',
						'label' => __( 'نام کاربری (ملی پیامک)', 'khabar' ),
					),
					'sms_password'       => array(
						'type'  => 'password',
						'label' => __( 'رمز عبور (ملی پیامک)', 'khabar' ),
					),
					'sms_sender'         => array(
						'type'  => 'text',
						'label' => __( 'شماره فرستنده', 'khabar' ),
					),
					'sms_mode'           => array(
						'type'    => 'select',
						'label'   => __( 'نوع ارسال', 'khabar' ),
						'options' => array(
							'text'    => __( 'متنی (از قالب پیام)', 'khabar' ),
							'pattern' => __( 'خدماتی / پترن', 'khabar' ),
						),
						'default' => 'text',
					),
					'sms_patterns'       => array(
						'type'  => 'textarea',
						'label' => __( 'کد پترن هر رویداد', 'khabar' ),
						'desc'  => __( 'هر خط: رویداد=کد پترن. رویدادها: back_in_stock, low_stock, price_drop, price_rise, price_change, combo, otp', 'khabar' ),
						'default' => "back_in_stock=\nlow_stock=\nprice_drop=\nprice_rise=\nprice_change=\ncombo=\notp=",
					),
					'sms_pattern_vars'   => array(
						'type'    => 'textarea',
						'label'   => __( 'متغیرهای پترن', 'khabar' ),
						'desc'    => __( 'هر خط: نام‌متغیر=مقدار. مثال برای کاوه‌نگار: token=… token2=… / در پیامک کد تایید {code} را بفرستید.', 'khabar' ),
						'default' => "product={product_name}\nvariation={variation}\nprice={price}\nlink={link}\ncode={code}",
					),
					'sms_webhook_url'    => array(
						'type'  => 'text',
						'label' => __( 'آدرس وب‌سرویس سفارشی', 'khabar' ),
						'desc'  => __( 'متغیرها: {to} {message}', 'khabar' ),
					),
					'sms_webhook_body'   => array(
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
						'type'  => 'password',
						'label' => __( 'Access Token', 'khabar' ),
					),
					'whatsapp_phone_id'  => array(
						'type'  => 'text',
						'label' => __( 'Phone Number ID', 'khabar' ),
					),
					'whatsapp_template'  => array(
						'type'  => 'text',
						'label' => __( 'نام Template تایید شده', 'khabar' ),
						'desc'  => __( 'بدنه تمپلیت باید یک متغیر {{1}} داشته باشد؛ متن پیام در آن قرار می‌گیرد. خالی = ارسال متن ساده.', 'khabar' ),
					),
					'whatsapp_lang'      => array(
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
						'type'  => 'text',
						'label' => __( 'آدرس وب‌سرویس سفارشی واتساپ', 'khabar' ),
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
			'templates' => array(
				'label'  => __( 'قالب پیام‌ها', 'khabar' ),
				'desc'   => __( 'متغیرها: {customer_name} {product_name} {variation} {price} {old_price} {regular_price} {target_price} {stock} {link} {site_name} {minutes} {exclusive_note} {manage_link} {code}', 'khabar' ),
				'fields' => array(),
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
}
