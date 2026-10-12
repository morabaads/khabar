<?php
/**
 * Routes notifications to channels: builds the message, picks the channels a request can be
 * reached on, delivers through each channel adapter and logs every attempt.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Channels;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Delivery\EmailDelivery;
use YadamBiar\Channels\Delivery\MessengerDelivery;
use YadamBiar\Channels\Delivery\OnsiteDelivery;
use YadamBiar\Channels\Delivery\PushDelivery;
use YadamBiar\Channels\Delivery\SmsDelivery;
use YadamBiar\Channels\Sms\SmsChannel;
use YadamBiar\Contracts\NotificationChannel;
use YadamBiar\Notifications\DeliveryLog;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Text;

/**
 * Channel router.
 */
final class ChannelManager {

	/**
	 * Adapters by key.
	 *
	 * @var NotificationChannel[]|null
	 */
	private static $channels;

	/**
	 * Channel adapters (filterable with "yadambiar_notification_channels").
	 *
	 * @return NotificationChannel[] key => adapter.
	 */
	public static function channels() {
		if ( null === self::$channels ) {
			$list           = apply_filters(
				'yadambiar_notification_channels',
				array( new SmsDelivery(), new EmailDelivery(), new OnsiteDelivery(), new PushDelivery(), new MessengerDelivery( 'telegram' ), new MessengerDelivery( 'bale' ) )
			);
			self::$channels = array();
			foreach ( $list as $channel ) {
				if ( $channel instanceof NotificationChannel ) {
					self::$channels[ $channel->key() ] = $channel;
				}
			}
		}
		return self::$channels;
	}

	/**
	 * Build the message for an event from its templates.
	 *
	 * @param string $event Event.
	 * @param array  $vars  Vars.
	 * @return array text, subject, html, url, vars
	 */
	public static function build_message( $event, $vars ) {
		$text_vars                   = $vars;
		$text_vars['exclusive_note'] = '';
		return array(
			'text'    => trim( Text::render( Settings::get( 'tpl_' . $event . '_sms', '' ), $text_vars ) ),
			'subject' => wp_strip_all_tags( Text::render( Settings::get( 'tpl_' . $event . '_subject', '' ), $text_vars ) ),
			'html'    => Text::render( Settings::get( 'tpl_' . $event . '_body', '' ), $vars ),
			'url'     => isset( $vars['link'] ) ? $vars['link'] : '',
			'vars'    => $vars,
		);
	}

	/**
	 * Channels a request will be notified on: its chosen (or all enabled) channels it can be reached on;
	 * never empty while a phone (SMS enabled) or email exists.
	 *
	 * @param object $sub Subscription.
	 * @return string[]
	 */
	public static function resolve( $sub ) {
		$enabled  = (array) Settings::get( 'channels_enabled', array() );
		$chosen   = $sub->channel_list ? array_intersect( $sub->channel_list, $enabled ) : $enabled;
		$channels = self::channels();
		$out      = array();
		foreach ( $chosen as $key ) {
			if ( isset( $channels[ $key ] ) ? $channels[ $key ]->can_reach( $sub ) : has_filter( 'yadambiar_send_channel_' . $key ) ) {
				$out[] = $key; // Unknown keys only for add-ons serving the "yadambiar_send_channel_{key}" filter (not removed channels).
			}
		}
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
	 * Owner key used by the bell, push and messenger links.
	 *
	 * @param object $sub Subscription.
	 * @return string
	 */
	public static function owner_of( $sub ) {
		return $sub->user_id ? 'u:' . $sub->user_id : (string) $sub->owner_key;
	}

	/**
	 * Send a message to a request on all of its channels.
	 *
	 * @param object $sub     Subscription.
	 * @param string $event   Event.
	 * @param array  $message Message.
	 * @return array channel => bool
	 */
	public static function send_to_subscription( $sub, $event, $message ) {
		$channels = self::channels();
		$results  = array();
		foreach ( self::resolve( $sub ) as $key ) {
			if ( isset( $channels[ $key ] ) ) {
				list( $recipient, $result ) = $channels[ $key ]->deliver( $sub, $event, $message );
			} else {
				$recipient = '';
				$result    = apply_filters( 'yadambiar_send_channel_' . $key, new \WP_Error( 'yadambiar_channel', 'Unknown channel' ), $sub, $event, $message );
			}
			$results[ $key ] = true === $result;
			DeliveryLog::record( $sub, $event, $key, $recipient, $message['text'], $result );
		}
		return $results;
	}

	/**
	 * Send a one-time code to a phone or email.
	 *
	 * @param string $contact Phone or email.
	 * @param string $code    Code.
	 * @return true|\WP_Error
	 */
	public static function send_code( $contact, $code ) {
		$vars    = array(
			'code'      => $code,
			'site_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		$message = self::build_message( 'otp', $vars );
		if ( is_email( $contact ) ) {
			return EmailChannel::send( $contact, $message['subject'], $message['html'] );
		}
		return SmsChannel::send( $contact, $message['text'], 'otp', $vars );
	}

	/**
	 * Retry a failed log entry (SMS, email, push).
	 *
	 * @param int $log_id Log id.
	 * @return true|\WP_Error
	 */
	public static function retry( $log_id ) {
		$row = DeliveryLog::get( $log_id );
		if ( ! $row ) {
			return new \WP_Error( 'yadambiar', __( 'یافت نشد', 'yadambiar' ) );
		}
		switch ( $row->channel ) {
			case 'sms':
				$result = SmsChannel::send( $row->recipient, $row->message, 'text', array() );
				break;
			case 'email':
				$result = EmailChannel::send( $row->recipient, get_bloginfo( 'name' ), wpautop( esc_html( $row->message ) ) );
				break;
			case 'push':
				$result = PushChannel::notify_owner( $row->recipient );
				break;
			default:
				return new \WP_Error( 'yadambiar', __( 'این کانال قابل ارسال مجدد نیست.', 'yadambiar' ) );
		}
		DeliveryLog::update_result( $row->id, $result );
		return $result;
	}
}
