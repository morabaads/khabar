<?php
/**
 * One-time codes that confirm a guest's contact before their requests become active.
 *
 * Limits: 5 codes per IP per hour, and per contact 60 seconds between codes and 5 per hour;
 * 5 wrong guesses lock the code. Codes are stored hashed for 10 minutes.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Subscriptions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Http\RateLimiter;
use YadamBiar\Support\Failure;
use YadamBiar\Support\Phone;
use YadamBiar\Support\Text;

/**
 * OTP issue / verify.
 */
final class OtpService {

	const TTL           = 10 * MINUTE_IN_SECONDS;
	const MAX_TRIES     = 5;
	const RESEND_DELAY  = 60;
	const MAX_PER_HOUR  = 5;
	const IP_PER_HOUR   = 5;
	const CODE_MIN      = 100000;
	const CODE_MAX      = 999999;

	/**
	 * Canonical contact: email, or the normalized mobile.
	 *
	 * @param string $contact Raw contact.
	 * @return string '' when invalid.
	 */
	public static function normalize_contact( $contact ) {
		$contact = trim( (string) $contact );
		return is_email( $contact ) ? strtolower( sanitize_email( $contact ) ) : Phone::normalize_phone( $contact );
	}

	/**
	 * Send a new code for pending requests.
	 *
	 * @param string $contact Phone or email.
	 * @param int[]  $ids     Pending subscription ids.
	 * @return true|\WP_Error
	 */
	public static function send( $contact, array $ids ) {
		if ( ! RateLimiter::allow( 'otp', self::IP_PER_HOUR ) ) {
			return Failure::of( __( 'تعداد درخواست کد زیاد است.', 'yadambiar' ), 429 );
		}
		$contact = self::normalize_contact( $contact );
		$limit   = self::key( 'otpc', $contact );
		$meta    = get_transient( $limit );
		$meta    = is_array( $meta ) ? $meta : array(
			'n'    => 0,
			'last' => 0,
		);
		if ( time() - (int) $meta['last'] < self::RESEND_DELAY || (int) $meta['n'] >= self::MAX_PER_HOUR ) {
			return Failure::of( __( 'کد اخیراً ارسال شده است؛ کمی بعد دوباره تلاش کنید.', 'yadambiar' ), 429 );
		}
		set_transient(
			$limit,
			array(
				'n'    => (int) $meta['n'] + 1,
				'last' => time(),
			),
			HOUR_IN_SECONDS
		);

		$key   = self::key( 'otp', $contact );
		$state = get_transient( $key );
		$code  = (string) wp_rand( self::CODE_MIN, self::CODE_MAX );
		set_transient(
			$key,
			array(
				'hash'  => wp_hash_password( $code ),
				'ids'   => array_values( array_unique( array_map( 'absint', array_merge( is_array( $state ) ? $state['ids'] : array(), $ids ) ) ) ),
				// Re-sending a code must not reset the guess counter.
				'tries' => is_array( $state ) ? (int) $state['tries'] : 0,
			),
			self::TTL
		);
		return ChannelManager::send_code( $contact, $code );
	}

	/**
	 * Check a code; on success the code is used up.
	 *
	 * @param string $contact Phone or email.
	 * @param string $code    Code (Persian digits accepted).
	 * @return int[]|\WP_Error Ids of the requests waiting for this code.
	 */
	public static function verify( $contact, $code ) {
		$contact = self::normalize_contact( $contact );
		$code    = preg_replace( '/\D/', '', Text::latin_digits( (string) $code ) );
		$key     = self::key( 'otp', $contact );
		$state   = $contact ? get_transient( $key ) : false;
		if ( ! is_array( $state ) ) {
			return Failure::of( __( 'کد منقضی شده است؛ دوباره درخواست دهید.', 'yadambiar' ) );
		}
		if ( (int) $state['tries'] >= self::MAX_TRIES ) {
			return Failure::of( __( 'تعداد تلاش‌ها بیش از حد مجاز است.', 'yadambiar' ), 429 );
		}
		if ( '' === $code || ! wp_check_password( $code, $state['hash'] ) ) {
			++$state['tries'];
			set_transient( $key, $state, self::TTL );
			return Failure::of( __( 'کد وارد شده صحیح نیست.', 'yadambiar' ) );
		}
		delete_transient( $key );
		return array_map( 'absint', (array) $state['ids'] );
	}

	/**
	 * Transient key of a contact.
	 *
	 * @param string $kind    otp|otpc.
	 * @param string $contact Canonical contact.
	 * @return string
	 */
	private static function key( $kind, $contact ) {
		return 'yadambiar_' . $kind . '_' . md5( strtolower( $contact ) );
	}
}
