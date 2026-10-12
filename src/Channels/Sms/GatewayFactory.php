<?php
/**
 * Maps a driver key to its gateway class (filterable, so add-ons can plug in providers).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\Gateways\AsanakGateway;
use YadamBiar\Channels\Sms\Gateways\GhasedakGateway;
use YadamBiar\Channels\Sms\Gateways\IppanelGateway;
use YadamBiar\Channels\Sms\Gateways\IranPayamakGateway;
use YadamBiar\Channels\Sms\Gateways\KavenegarGateway;
use YadamBiar\Channels\Sms\Gateways\PayamResanGateway;
use YadamBiar\Channels\Sms\Gateways\PayamakPanelGateway;
use YadamBiar\Channels\Sms\Gateways\RayganGateway;
use YadamBiar\Channels\Sms\Gateways\SabaNovinGateway;
use YadamBiar\Channels\Sms\Gateways\SmsIrGateway;
use YadamBiar\Channels\Sms\Gateways\WebhookGateway;
use YadamBiar\Contracts\SmsGateway;
use YadamBiar\Support\Http\JsonClient;

/**
 * Gateway factory.
 */
final class GatewayFactory {

	/**
	 * Driver => gateway class.
	 *
	 * @return array
	 */
	public static function classes() {
		return (array) apply_filters(
			'yadambiar_sms_gateways',
			array(
				'kavenegar'     => KavenegarGateway::class,
				'smsir'         => SmsIrGateway::class,
				'payamak_panel' => PayamakPanelGateway::class,
				'ippanel'       => IppanelGateway::class,
				'ghasedak'      => GhasedakGateway::class,
				'iranpayamak'   => IranPayamakGateway::class,
				'asanak'        => AsanakGateway::class,
				'raygansms'     => RayganGateway::class,
				'sabanovin'     => SabaNovinGateway::class,
				'payamresan'    => PayamResanGateway::class,
				'webhook'       => WebhookGateway::class,
			)
		);
	}

	/**
	 * Gateway of a driver.
	 *
	 * @param string     $driver Driver key.
	 * @param array      $config SMS settings.
	 * @param JsonClient $http   HTTP client.
	 * @return SmsGateway|null
	 */
	public static function make( $driver, array $config, JsonClient $http ) {
		$classes = self::classes();
		if ( empty( $classes[ $driver ] ) || ! class_exists( $classes[ $driver ] ) ) {
			return null;
		}
		$gateway = new $classes[ $driver ]( $config, $http );
		return $gateway instanceof SmsGateway ? $gateway : null;
	}
}
