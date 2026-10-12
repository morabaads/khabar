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
			'kavenegar'   => array( __( 'کاوه‌نگار', 'yadam-biar' ), 'kavenegar' ),
			'smsir'       => array( __( 'SMS.ir', 'yadam-biar' ), 'smsir' ),
			'melipayamak' => array( __( 'ملی پیامک', 'yadam-biar' ), 'payamak_panel' ),
			'ippanel'     => array( __( 'IPPanel', 'yadam-biar' ), 'ippanel' ),
			'farazsms'    => array( __( 'فراز اس‌ام‌اس', 'yadam-biar' ), 'ippanel' ),
			'iranpayamak' => array( __( 'ایران پیامک', 'yadam-biar' ), 'iranpayamak' ),
			'ghasedak'    => array( __( 'قاصدک', 'yadam-biar' ), 'ghasedak' ),
			'asanak'      => array( __( 'آسانک', 'yadam-biar' ), 'asanak' ),
			'raygansms'   => array( __( 'رایگان اس‌ام‌اس', 'yadam-biar' ), 'raygansms' ),
			'sabanovin'   => array( __( 'صبا نوین', 'yadam-biar' ), 'sabanovin' ),
			'payamresan'  => array( __( 'پیام رسان', 'yadam-biar' ), 'payamresan' ),
			'farapayamak' => array( __( 'فراپیامک', 'yadam-biar' ), 'payamak_panel' ),
			'rayansms'    => array( __( 'رایان اس‌ام‌اس', 'yadam-biar' ), 'payamak_panel' ),
		);
		$compat = array(
			'modirpayamak'  => __( 'مدیر پیامک', 'yadam-biar' ),
			'maxsms'        => __( 'مکس اس‌ام‌اس', 'yadam-biar' ),
			'najva'         => __( 'نجوا', 'yadam-biar' ),
			'atiyeh'        => __( 'آتیه داده‌پرداز', 'yadam-biar' ),
			'sepidsms'      => __( 'سپید اس‌ام‌اس', 'yadam-biar' ),
			'iransms'       => __( 'ایران اس‌ام‌اس', 'yadam-biar' ),
			'afzanpayamak'  => __( 'افزان پیامک نوین', 'yadam-biar' ),
			'bartarpayamak' => __( 'برتر پیامک', 'yadam-biar' ),
			'parsgreen'     => __( 'پارس گرین', 'yadam-biar' ),
			'novinsms'      => __( 'سامانه پیام کوتاه نوین', 'yadam-biar' ),
			'karapayamak'   => __( 'کارا پیامک', 'yadam-biar' ),
			'parssms'       => __( 'پارس اس‌ام‌اس', 'yadam-biar' ),
			'novinpayamak'  => __( 'نوین پیامک', 'yadam-biar' ),
			'behinpayam'    => __( 'بهین پیام', 'yadam-biar' ),
			'payamgostar'   => __( 'پیام گستر', 'yadam-biar' ),
			'soheilpayamak' => __( 'سهیل پیامک', 'yadam-biar' ),
			'payamakfori'   => __( 'پیامک فوری', 'yadam-biar' ),
			'hamyarpayamak' => __( 'همیار پیامک', 'yadam-biar' ),
			'toseepayamak'  => __( 'توسعه پیامک', 'yadam-biar' ),
			'radpayamak'    => __( 'راد پیامک', 'yadam-biar' ),
			'asanpayam'     => __( 'آسان پیام', 'yadam-biar' ),
		);
		$out = $native;
		foreach ( $compat as $key => $label ) {
			$out[ $key ] = array( $label, 'compat' );
		}
		$out['webhook'] = array( __( 'وب‌سرویس سفارشی (HTTP)', 'yadam-biar' ), 'webhook' );
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
			$out[ $key ] = 'compat' === $p[1] ? $p[0] . ' ' . __( '(سازگار)', 'yadam-biar' ) : $p[0];
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
