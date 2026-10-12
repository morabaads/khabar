<?php
/**
 * SMS channel: picks the configured gateway, chooses pattern or text, and keeps the last
 * provider response for the admin diagnostics.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels\Sms;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\SmsDiagnostics;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Http\JsonClient;
use YadamBiar\Support\Phone;

/**
 * SMS facade.
 */
final class SmsChannel {

	/**
	 * HTTP client shared by the gateways (holds the last response).
	 *
	 * @var JsonClient|null
	 */
	private static $http;

	/**
	 * Send an SMS.
	 *
	 * @param string $to            Mobile.
	 * @param string $text          Text (text mode).
	 * @param string $event         Event (pattern lookup). Use 'text' to force text mode.
	 * @param array  $vars          Message vars (pattern mode).
	 * @param bool   $force_pattern Use the event's pattern even when the send mode is "text" (admin test).
	 * @return true|\WP_Error
	 */
	public static function send( $to, $text, $event = 'text', $vars = array(), $force_pattern = false ) {
		self::$http = new JsonClient();
		$to         = Phone::normalize_phone( $to );
		if ( ! $to ) {
			return new \WP_Error( 'yadambiar_sms', __( 'شماره موبایل نامعتبر', 'yadambiar' ) );
		}

		$pre = apply_filters( 'yadambiar_pre_send_sms', null, $to, $text, $event, $vars );
		if ( null !== $pre ) {
			return $pre;
		}

		$driver  = self::driver();
		$gateway = self::gateway( $driver );
		if ( ! $gateway ) {
			return new \WP_Error( 'yadambiar_sms', __( 'سامانه پیامک تنظیم نشده است.', 'yadambiar' ) );
		}
		$pattern = array( '', array() );
		// Drivers without a pattern API always send the text message.
		if ( ( $force_pattern || 'pattern' === Settings::get( 'sms_mode' ) ) && 'text' !== $event && in_array( $driver, ProviderRegistry::PATTERN_DRIVERS, true ) ) {
			$resolved = PatternRepository::resolve( $event, (array) $vars );
			$pattern  = $resolved ? $resolved : $pattern;
		}
		return $gateway->send( new SmsMessage( $to, $text, $pattern[0], $pattern[1] ) );
	}

	/**
	 * Decoded provider response of the last call (diagnostics).
	 *
	 * @return array|null
	 */
	public static function last_response() {
		return self::$http ? self::$http->last_response() : null;
	}

	/**
	 * Message id of the last successful send (gateways with delivery reports).
	 *
	 * @return string
	 */
	public static function last_message_id() {
		$gateway  = self::gateway( self::driver() );
		$response = self::last_response();
		return $gateway instanceof SmsDiagnostics && is_array( $response ) ? $gateway->message_id( $response ) : '';
	}

	/**
	 * Account check: credit and sender lines.
	 *
	 * @return array|\WP_Error label => value
	 */
	public static function account_info() {
		$gateway = self::diagnostics();
		return is_wp_error( $gateway ) ? $gateway : $gateway->account_info();
	}

	/**
	 * Delivery status of a message.
	 *
	 * @param string $id Message id.
	 * @return string|\WP_Error
	 */
	public static function delivery_status( $id ) {
		$gateway = self::diagnostics();
		return is_wp_error( $gateway ) ? $gateway : $gateway->delivery_status( (string) $id );
	}

	/**
	 * Configured driver.
	 *
	 * @return string
	 */
	private static function driver() {
		return ProviderRegistry::driver( (string) Settings::get( 'sms_gateway', 'kavenegar' ) );
	}

	/**
	 * Gateway of a driver with the current settings.
	 *
	 * @param string $driver Driver.
	 * @return SmsGateway|null
	 */
	private static function gateway( $driver ) {
		if ( ! self::$http ) {
			self::$http = new JsonClient();
		}
		return GatewayFactory::make( $driver, Settings::all(), self::$http );
	}

	/**
	 * Gateway with diagnostics, or an error naming the providers that have them.
	 *
	 * @return SmsDiagnostics|\WP_Error
	 */
	private static function diagnostics() {
		self::$http = new JsonClient();
		$gateway    = self::gateway( self::driver() );
		return $gateway instanceof SmsDiagnostics ? $gateway : new \WP_Error( 'yadambiar_sms', __( 'بررسی حساب و وضعیت تحویل فقط برای SMS.ir و کاوه‌نگار در دسترس است.', 'yadambiar' ) );
	}
}
