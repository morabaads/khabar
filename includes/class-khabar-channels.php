<?php
/**
 * Channel registry, message builder and send log.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Channels {

	/**
	 * Build the message for an event.
	 *
	 * @param string $event Event.
	 * @param array  $vars  Vars.
	 * @return array text, subject, html, url
	 */
	public static function build_message( $event, $vars ) {
		$text_vars                   = $vars;
		$text_vars['exclusive_note'] = '';
		return array(
			'text'    => trim( Khabar_Utils::render( Khabar_Settings::get( 'tpl_' . $event . '_sms', '' ), $text_vars ) ),
			'subject' => wp_strip_all_tags( Khabar_Utils::render( Khabar_Settings::get( 'tpl_' . $event . '_subject', '' ), $text_vars ) ),
			'html'    => Khabar_Utils::render( Khabar_Settings::get( 'tpl_' . $event . '_body', '' ), $vars ),
			'url'     => isset( $vars['link'] ) ? $vars['link'] : '',
			'vars'    => $vars,
		);
	}

	/**
	 * Channels to use for a subscription.
	 *
	 * @param object $sub Subscription.
	 * @return string[]
	 */
	public static function resolve( $sub ) {
		$enabled = (array) Khabar_Settings::get( 'channels_enabled', array() );
		$chosen  = $sub->channel_list ? array_intersect( $sub->channel_list, $enabled ) : $enabled;
		$out     = array();
		foreach ( $chosen as $channel ) {
			if ( in_array( $channel, array( 'sms', 'whatsapp' ), true ) && ! $sub->phone ) {
				continue;
			}
			if ( 'email' === $channel && ! $sub->email ) {
				continue;
			}
			if ( in_array( $channel, array( 'onsite', 'push' ), true ) && ! self::owner_of( $sub ) ) {
				continue;
			}
			// Push only reaches owners that granted browser permission.
			if ( 'push' === $channel && ! Khabar_Push::has_subscription( self::owner_of( $sub ) ) ) {
				continue;
			}
			$out[] = $channel;
		}
		// Never leave a subscriber without a channel when a contact is available.
		if ( ! $out ) {
			if ( $sub->phone && in_array( 'sms', $enabled, true ) ) {
				$out[] = 'sms';
			} elseif ( $sub->email ) {
				$out[] = 'email';
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Owner key for onsite / push.
	 *
	 * @param object $sub Subscription.
	 * @return string
	 */
	public static function owner_of( $sub ) {
		return $sub->user_id ? 'u:' . $sub->user_id : (string) $sub->owner_key;
	}

	/**
	 * Send a message to a subscription on its channels.
	 *
	 * @param object $sub     Subscription.
	 * @param string $event   Event.
	 * @param array  $message Message.
	 * @return array channel => bool
	 */
	public static function send_to_subscription( $sub, $event, $message ) {
		$results = array();
		$onsite  = false;
		foreach ( self::resolve( $sub ) as $channel ) {
			$recipient = '';
			switch ( $channel ) {
				case 'email':
					$recipient = $sub->email;
					$result    = Khabar_Channel_Email::send( $sub->email, $message['subject'], $message['html'] );
					break;
				case 'sms':
					$recipient = $sub->phone;
					$result    = Khabar_Channel_Sms::send( $sub->phone, $message['text'], $event, $message['vars'] );
					break;
				case 'whatsapp':
					$recipient = $sub->phone;
					$result    = Khabar_Channel_Whatsapp::send( $sub->phone, $message['text'] );
					break;
				case 'onsite':
				case 'push':
					$recipient = self::owner_of( $sub );
					if ( ! $onsite ) {
						$onsite = Khabar_Channel_Onsite::create( $recipient, $sub->id, $message['subject'], $message['text'], $message['url'] );
					}
					$result = 'push' === $channel ? Khabar_Push::notify_owner( $recipient ) : ( $onsite ? true : new WP_Error( 'onsite', 'db' ) );
					break;
				default:
					$result = apply_filters( 'khabar_send_channel_' . $channel, new WP_Error( 'khabar_channel', 'Unknown channel' ), $sub, $event, $message );
			}
			$results[ $channel ] = true === $result;
			self::log( $sub, $event, $channel, $recipient, $message['text'], $result );
		}
		return $results;
	}

	/**
	 * Write to the send log.
	 *
	 * @param object             $sub       Subscription.
	 * @param string             $event     Event.
	 * @param string             $channel   Channel.
	 * @param string             $recipient Recipient.
	 * @param string             $text      Text.
	 * @param true|WP_Error|bool $result    Result.
	 */
	public static function log( $sub, $event, $channel, $recipient, $text, $result ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Khabar_Install::table( 'log' ),
			array(
				'subscription_id' => $sub ? $sub->id : 0,
				'product_id'      => $sub ? $sub->product_id : 0,
				'variation_id'    => $sub ? (int) $sub->notified_target : 0,
				'event'           => $event,
				'channel'         => $channel,
				'recipient'       => (string) $recipient,
				'status'          => true === $result ? 'sent' : 'failed',
				'message'         => $text,
				'error'           => is_wp_error( $result ) ? $result->get_error_message() : ( true === $result ? '' : 'failed' ),
				'created_at'      => Khabar_Utils::now(),
			)
		);
	}

	/**
	 * Send a one time code to a phone or email.
	 *
	 * @param string $contact Phone or email.
	 * @param string $code    Code.
	 * @return true|WP_Error
	 */
	public static function send_code( $contact, $code ) {
		$vars    = array(
			'code'      => $code,
			'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		$message = self::build_message( 'otp', $vars );
		if ( is_email( $contact ) ) {
			return Khabar_Channel_Email::send( $contact, $message['subject'], $message['html'] );
		}
		return Khabar_Channel_Sms::send( $contact, $message['text'], 'otp', $vars );
	}

	/**
	 * Retry a failed log entry.
	 *
	 * @param int $log_id Log id.
	 * @return true|WP_Error
	 */
	public static function retry( $log_id ) {
		global $wpdb;
		$table = Khabar_Install::table( 'log' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ) ); // phpcs:ignore
		if ( ! $row ) {
			return new WP_Error( 'khabar', __( 'یافت نشد', 'khabar' ) );
		}
		switch ( $row->channel ) {
			case 'sms':
				$result = Khabar_Channel_Sms::send( $row->recipient, $row->message, 'text', array() );
				break;
			case 'whatsapp':
				$result = Khabar_Channel_Whatsapp::send( $row->recipient, $row->message );
				break;
			case 'email':
				$result = Khabar_Channel_Email::send( $row->recipient, get_bloginfo( 'name' ), wpautop( esc_html( $row->message ) ) );
				break;
			case 'push':
				$result = Khabar_Push::notify_owner( $row->recipient );
				break;
			default:
				$result = new WP_Error( 'khabar', __( 'این کانال قابل ارسال مجدد نیست.', 'khabar' ) );
		}
		$wpdb->update( // phpcs:ignore
			$table,
			array(
				'status' => true === $result ? 'sent' : 'failed',
				'error'  => is_wp_error( $result ) ? $result->get_error_message() : '',
			),
			array( 'id' => $row->id )
		);
		return $result;
	}
}
