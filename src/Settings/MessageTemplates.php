<?php
/**
 * Default message templates per event.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Default message templates per event.
 */
final class MessageTemplates {

	/**
	 * Default message templates.
	 *
	 * @return array
	 */
	public static function all() {
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
}
