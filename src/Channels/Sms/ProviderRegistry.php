<?php
/**
 * Registry of Iranian SMS panels and the API driver each one talks to.
 *
 * "Native" panels have a dedicated driver built on the provider's documented API.
 * "Compatible" panels are white-label resellers: they run on a shared platform
 * (Payamak-Panel / IPPanel engine) or expose a plain HTTP web service, so the admin
 * picks the platform and pastes the API address shown in their own panel.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;

class ProviderRegistry {

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
			'kavenegar'   => array( __( 'کاوه‌نگار', 'yadambiar' ), 'kavenegar' ),
			'smsir'       => array( __( 'SMS.ir', 'yadambiar' ), 'smsir' ),
			'melipayamak' => array( __( 'ملی پیامک', 'yadambiar' ), 'payamak_panel' ),
			'ippanel'     => array( __( 'IPPanel', 'yadambiar' ), 'ippanel' ),
			'farazsms'    => array( __( 'فراز اس‌ام‌اس', 'yadambiar' ), 'ippanel' ),
			'iranpayamak' => array( __( 'ایران پیامک', 'yadambiar' ), 'iranpayamak' ),
			'ghasedak'    => array( __( 'قاصدک', 'yadambiar' ), 'ghasedak' ),
			'asanak'      => array( __( 'آسانک', 'yadambiar' ), 'asanak' ),
			'raygansms'   => array( __( 'رایگان اس‌ام‌اس', 'yadambiar' ), 'raygansms' ),
			'sabanovin'   => array( __( 'صبا نوین', 'yadambiar' ), 'sabanovin' ),
			'payamresan'  => array( __( 'پیام رسان', 'yadambiar' ), 'payamresan' ),
			'farapayamak' => array( __( 'فراپیامک', 'yadambiar' ), 'payamak_panel' ),
			'rayansms'    => array( __( 'رایان اس‌ام‌اس', 'yadambiar' ), 'payamak_panel' ),
		);
		$compat = array(
			'modirpayamak'  => __( 'مدیر پیامک', 'yadambiar' ),
			'maxsms'        => __( 'مکس اس‌ام‌اس', 'yadambiar' ),
			'najva'         => __( 'نجوا', 'yadambiar' ),
			'atiyeh'        => __( 'آتیه داده‌پرداز', 'yadambiar' ),
			'sepidsms'      => __( 'سپید اس‌ام‌اس', 'yadambiar' ),
			'iransms'       => __( 'ایران اس‌ام‌اس', 'yadambiar' ),
			'afzanpayamak'  => __( 'افزان پیامک نوین', 'yadambiar' ),
			'bartarpayamak' => __( 'برتر پیامک', 'yadambiar' ),
			'parsgreen'     => __( 'پارس گرین', 'yadambiar' ),
			'novinsms'      => __( 'سامانه پیام کوتاه نوین', 'yadambiar' ),
			'karapayamak'   => __( 'کارا پیامک', 'yadambiar' ),
			'parssms'       => __( 'پارس اس‌ام‌اس', 'yadambiar' ),
			'novinpayamak'  => __( 'نوین پیامک', 'yadambiar' ),
			'behinpayam'    => __( 'بهین پیام', 'yadambiar' ),
			'payamgostar'   => __( 'پیام گستر', 'yadambiar' ),
			'soheilpayamak' => __( 'سهیل پیامک', 'yadambiar' ),
			'payamakfori'   => __( 'پیامک فوری', 'yadambiar' ),
			'hamyarpayamak' => __( 'همیار پیامک', 'yadambiar' ),
			'toseepayamak'  => __( 'توسعه پیامک', 'yadambiar' ),
			'radpayamak'    => __( 'راد پیامک', 'yadambiar' ),
			'asanpayam'     => __( 'آسان پیام', 'yadambiar' ),
		);
		$out = $native;
		foreach ( $compat as $key => $label ) {
			$out[ $key ] = array( $label, 'compat' );
		}
		$out['webhook'] = array( __( 'وب‌سرویس سفارشی (HTTP)', 'yadambiar' ), 'webhook' );
		return apply_filters( 'yadambiar_sms_providers', $out );
	}

	/**
	 * Select options for the settings page.
	 *
	 * @return array
	 */
	public static function options() {
		$out = array();
		foreach ( self::all() as $key => $p ) {
			$out[ $key ] = 'compat' === $p[1] ? $p[0] . ' ' . __( '(سازگار)', 'yadambiar' ) : $p[0];
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
			$platform = (string) Settings::get( 'sms_platform', 'payamak_panel' );
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
