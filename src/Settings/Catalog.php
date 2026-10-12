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
}
