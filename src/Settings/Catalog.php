<?php
/**
 * Catalog of notification events and delivery channels.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Catalog of notification events and delivery channels.
 */
final class Catalog {

	/**
	 * Notification events.
	 *
	 * @return array
	 */
	public static function events() {
		return array(
			'back_in_stock' => __( 'موجود شدن', 'yadambiar' ),
			'low_stock'     => __( 'موجود شدن تعداد محدود', 'yadambiar' ),
			'price_drop'    => __( 'کاهش قیمت', 'yadambiar' ),
			'price_rise'    => __( 'افزایش قیمت', 'yadambiar' ),
			'price_change'  => __( 'تغییر قیمت', 'yadambiar' ),
			'combo'         => __( 'قانون ترکیبی', 'yadambiar' ),
			'alternatives'  => __( 'پیشنهاد محصول جایگزین', 'yadambiar' ),
			'broadcast'     => __( 'اعلام موجود شدن در کانال', 'yadambiar' ),
			'otp'           => __( 'کد تایید', 'yadambiar' ),
		);
	}

	/**
	 * Available channels.
	 *
	 * @return array
	 */
	public static function channels() {
		return array(
			'sms'      => __( 'پیامک', 'yadambiar' ),
			'email'    => __( 'ایمیل', 'yadambiar' ),
			'onsite'   => __( 'اعلان داخل سایت', 'yadambiar' ),
			'push'     => __( 'پوش نوتیفیکیشن', 'yadambiar' ),
			'telegram' => __( 'تلگرام', 'yadambiar' ),
			'bale'     => __( 'بله', 'yadambiar' ),
		);
	}
}
