<?php
/**
 * An SMS provider API.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Contracts;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Sms\SmsMessage;

interface SmsGateway {

	/**
	 * Send one message (pattern when the message carries one and the gateway supports it).
	 *
	 * @param SmsMessage $message Message.
	 * @return true|\WP_Error
	 */
	public function send( SmsMessage $message );
}
