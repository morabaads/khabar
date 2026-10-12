<?php
/**
 * A way to deliver a notification to a customer's request.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Contracts;

defined( 'ABSPATH' ) || exit;

interface NotificationChannel {

	/**
	 * Channel key (sms, email, onsite, push, telegram, bale…).
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Can this request be reached on this channel (contact present, chat linked, permission given)?
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function can_reach( $sub );

	/**
	 * Deliver a built message.
	 *
	 * @param object $sub     Subscription.
	 * @param string $event   Event key.
	 * @param array  $message text, subject, html, url, vars.
	 * @return array [ recipient, true|\WP_Error ]
	 */
	public function deliver( $sub, $event, array $message );
}
