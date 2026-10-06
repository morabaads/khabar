<?php
/**
 * Registry of Iranian SMS panels and the API driver each one talks to.
 *
 * "Native" panels have a dedicated driver built on the provider's documented API.
 * "Compatible" panels are white-label resellers: they run on a shared platform
 * (Payamak-Panel / IPPanel engine) or expose a plain HTTP web service, so the admin
 * picks the platform and pastes the API address shown in their own panel.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Sms_Providers {

	/**
	 * Drivers that support pattern (service-line) sending.
	 */
	const PATTERN_DRIVERS = array( 'kavenegar', 'smsir', 'payamak_panel', 'ippanel', 'ghasedak', 'iranpayamak' );

	/**
	 * Drivers authenticated with an API key / token.
	 */
	const KEY_DRIVERS = array( 'kavenegar', 'smsir', 'ippanel', 'ghasedak', 'iranpayamak', 'sabanovin' );

	/**
	 * Drivers authenticated with username + password.
	 */
	const USER_DRIVERS = array( 'payamak_panel', 'asanak', 'raygansms', 'payamresan' );

	/**
	 * All providers: key => [ label, driver ].
	 *
	 * @return array
	 */
	public static function all() {
		$native = array(
			'kavenegar'   => array( __( 'کاوه‌نگار', 'khabar' ), 'kavenegar' ),
			'smsir'       => array( __( 'SMS.ir', 'khabar' ), 'smsir' ),
			'melipayamak' => array( __( 'ملی پیامک', 'khabar' ), 'payamak_panel' ),
			'ippanel'     => array( __( 'IPPanel', 'khabar' ), 'ippanel' ),
			'farazsms'    => array( __( 'فراز اس‌ام‌اس', 'khabar' ), 'ippanel' ),
			'iranpayamak' => array( __( 'ایران پیامک', 'khabar' ), 'iranpayamak' ),
			'ghasedak'    => array( __( 'قاصدک', 'khabar' ), 'ghasedak' ),
			'asanak'      => array( __( 'آسانک', 'khabar' ), 'asanak' ),
			'raygansms'   => array( __( 'رایگان اس‌ام‌اس', 'khabar' ), 'raygansms' ),
			'sabanovin'   => array( __( 'صبا نوین', 'khabar' ), 'sabanovin' ),
			'payamresan'  => array( __( 'پیام رسان', 'khabar' ), 'payamresan' ),
			'farapayamak' => array( __( 'فراپیامک', 'khabar' ), 'payamak_panel' ),
			'rayansms'    => array( __( 'رایان اس‌ام‌اس', 'khabar' ), 'payamak_panel' ),
		);
		$compat = array(
			'modirpayamak'  => __( 'مدیر پیامک', 'khabar' ),
			'maxsms'        => __( 'مکس اس‌ام‌اس', 'khabar' ),
			'najva'         => __( 'نجوا', 'khabar' ),
			'atiyeh'        => __( 'آتیه داده‌پرداز', 'khabar' ),
			'sepidsms'      => __( 'سپید اس‌ام‌اس', 'khabar' ),
			'iransms'       => __( 'ایران اس‌ام‌اس', 'khabar' ),
			'afzanpayamak'  => __( 'افزان پیامک نوین', 'khabar' ),
			'bartarpayamak' => __( 'برتر پیامک', 'khabar' ),
			'parsgreen'     => __( 'پارس گرین', 'khabar' ),
			'novinsms'      => __( 'سامانه پیام کوتاه نوین', 'khabar' ),
			'karapayamak'   => __( 'کارا پیامک', 'khabar' ),
			'parssms'       => __( 'پارس اس‌ام‌اس', 'khabar' ),
			'novinpayamak'  => __( 'نوین پیامک', 'khabar' ),
			'behinpayam'    => __( 'بهین پیام', 'khabar' ),
			'payamgostar'   => __( 'پیام گستر', 'khabar' ),
			'soheilpayamak' => __( 'سهیل پیامک', 'khabar' ),
			'payamakfori'   => __( 'پیامک فوری', 'khabar' ),
			'hamyarpayamak' => __( 'همیار پیامک', 'khabar' ),
			'toseepayamak'  => __( 'توسعه پیامک', 'khabar' ),
			'radpayamak'    => __( 'راد پیامک', 'khabar' ),
			'asanpayam'     => __( 'آسان پیام', 'khabar' ),
		);
		$out = $native;
		foreach ( $compat as $key => $label ) {
			$out[ $key ] = array( $label, 'compat' );
		}
		$out['webhook'] = array( __( 'وب‌سرویس سفارشی (HTTP)', 'khabar' ), 'webhook' );
		return apply_filters( 'khabar_sms_providers', $out );
	}

	/**
	 * Select options for the settings page.
	 *
	 * @return array
	 */
	public static function options() {
		$out = array();
		foreach ( self::all() as $key => $p ) {
			$out[ $key ] = 'compat' === $p[1] ? $p[0] . ' ' . __( '(سازگار)', 'khabar' ) : $p[0];
		}
		return $out;
	}

	/**
	 * Driver configured for a provider ("compat" resolves to the chosen platform).
	 *
	 * @param string $provider Provider key.
	 * @return string
	 */
	public static function driver( $provider ) {
		$all    = self::all();
		$driver = isset( $all[ $provider ] ) ? $all[ $provider ][1] : 'webhook';
		if ( 'compat' === $driver ) {
			$platform = (string) Khabar_Settings::get( 'sms_platform', 'payamak_panel' );
			$driver   = in_array( $platform, array( 'payamak_panel', 'ippanel', 'webhook' ), true ) ? $platform : 'payamak_panel';
		}
		return $driver;
	}

	/**
	 * Provider keys whose (static) driver is in a list – used for settings visibility rules.
	 *
	 * @param string[] $drivers      Drivers.
	 * @param bool     $with_compat  Include compatible panels.
	 * @return string[]
	 */
	public static function keys_for( $drivers, $with_compat = false ) {
		$out = array();
		foreach ( self::all() as $key => $p ) {
			if ( in_array( $p[1], $drivers, true ) || ( $with_compat && 'compat' === $p[1] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}
}
