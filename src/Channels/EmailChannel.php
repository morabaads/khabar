<?php
/**
 * Email channel (uses the WooCommerce email wrapper).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels;

defined( 'ABSPATH' ) || exit;

class EmailChannel {

	/**
	 * Send an email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    HTML body.
	 * @return true|WP_Error
	 */
	public static function send( $to, $subject, $html ) {
		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'yadambiar_email', __( 'ایمیل نامعتبر', 'yadam-biar' ) );
		}
		$mailer = function_exists( 'WC' ) ? WC()->mailer() : null;
		$body   = '<div dir="rtl" style="text-align:right">' . $html . '</div>';
		if ( $mailer ) {
			$body = $mailer->wrap_message( $subject, $body );
			$ok   = $mailer->send( $to, $subject, $body, "Content-Type: text/html\r\n", array() );
		} else {
			$ok = wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
		return $ok ? true : new \WP_Error( 'yadambiar_email', __( 'ارسال ایمیل ناموفق بود.', 'yadam-biar' ) );
	}
}
