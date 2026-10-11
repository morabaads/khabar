<?php
/**
 * Messenger channels: Telegram & Bale (personal messages through a bot the customer
 * starts with a one-time deep link) and channel broadcasting for Telegram and Bale.
 *
 * Linking flow:
 *   1. The visitor clicks "connect" → we create a one-time code bound to their owner key.
 *   2. They open https://t.me/<bot>?start=<code> (or ble.ir) and press Start.
 *   3. The bot webhook receives "/start <code>" → chat_id is stored for that owner.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Messenger {

	const CODE_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Network definitions.
	 *
	 * @return array
	 */
	public static function networks() {
		$tg_base = untrailingslashit( Khabar_Settings::get( 'telegram_api_base', 'https://api.telegram.org' ) );
		return array(
			'telegram' => array(
				'label'    => __( 'تلگرام', 'khabar' ),
				'token'    => (string) Khabar_Settings::get( 'telegram_token' ),
				'bot'      => ltrim( (string) Khabar_Settings::get( 'telegram_bot' ), '@' ),
				'api'      => ( $tg_base ? $tg_base : 'https://api.telegram.org' ) . '/bot%s/%s',
				'link'     => 'https://t.me/%s?start=%s',
				'personal' => true,
				'channel'  => self::normalize_chat( Khabar_Settings::get( 'telegram_channel' ) ),
			),
			'bale'     => array(
				'label'    => __( 'بله', 'khabar' ),
				'token'    => (string) Khabar_Settings::get( 'bale_token' ),
				'bot'      => ltrim( (string) Khabar_Settings::get( 'bale_bot' ), '@' ),
				'api'      => 'https://tapi.bale.ai/bot%s/%s',
				'link'     => 'https://ble.ir/%s?start=%s',
				'personal' => true,
				'channel'  => self::normalize_chat( Khabar_Settings::get( 'bale_channel' ) ),
			),
		);
	}

	/**
	 * Network config.
	 *
	 * @param string $network Network.
	 * @return array|null
	 */
	public static function network( $network ) {
		$all = self::networks();
		return isset( $all[ $network ] ) ? $all[ $network ] : null;
	}

	/**
	 * Is personal messaging configured for a network.
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function personal_ready( $network ) {
		$n = self::network( $network );
		return $n && $n['personal'] && $n['token'] && $n['bot'] && Khabar_Settings::has( 'channels_enabled', $network );
	}

	/* ------------------------------------------------------------------ */
	/* Bale Safir: messages straight to a phone number (no Start needed)    */
	/* ------------------------------------------------------------------ */

	const SAFIR_URL = 'https://safir.bale.ai/api/v3/send_message';

	/**
	 * Bot ("بازو") id for Safir: the setting, or the number before ":" in the bot token.
	 *
	 * @return int
	 */
	public static function safir_bot_id() {
		$id = preg_replace( '/\D/', '', (string) Khabar_Settings::get( 'bale_safir_bot_id', '' ) );
		if ( '' === $id && preg_match( '/^(\d+):/', trim( (string) Khabar_Settings::get( 'bale_token', '' ) ), $m ) ) {
			$id = $m[1];
		}
		return (int) $id;
	}

	/**
	 * Can Bale messages go to phone numbers (Safir configured and Bale enabled)?
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function phone_ready( $network ) {
		return 'bale' === $network && Khabar_Settings::has( 'channels_enabled', 'bale' ) && '' !== trim( (string) Khabar_Settings::get( 'bale_safir_key', '' ) ) && self::safir_bot_id() > 0;
	}

	/**
	 * Offer this messenger to customers at all (by phone through Safir, or by linking the bot).
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function available( $network ) {
		return self::phone_ready( $network ) || self::personal_ready( $network );
	}

	/**
	 * Does the customer have to link the bot (press Start) to get personal messages?
	 *
	 * @param string $network Network.
	 * @return bool
	 */
	public static function needs_link( $network ) {
		return self::personal_ready( $network ) && ! self::phone_ready( $network );
	}

	/**
	 * Can a subscription be reached on this messenger?
	 *
	 * @param string $network Network.
	 * @param object $sub     Subscription.
	 * @return bool
	 */
	public static function can_reach( $network, $sub ) {
		if ( self::phone_ready( $network ) && $sub->phone ) {
			return true;
		}
		return (bool) self::chat_id( Khabar_Channels::owner_of( $sub ), $network );
	}

	/**
	 * Deliver a notification to a subscription: by phone number through Safir when possible,
	 * otherwise to the chat the customer linked.
	 *
	 * @param string $network Network.
	 * @param object $sub     Subscription.
	 * @param array  $message Built message (subject, text, url).
	 * @return array [ recipient, true|WP_Error ]
	 */
	public static function deliver( $network, $sub, $message ) {
		if ( self::phone_ready( $network ) && $sub->phone ) {
			return array( $sub->phone, self::send_safir( $sub->phone, $message ) );
		}
		$owner = Khabar_Channels::owner_of( $sub );
		return array( self::chat_id( $owner, $network ), self::send_to_owner( $network, $owner, $message ) );
	}

	/**
	 * Send through Bale Safir (https://docs.bale.ai/safir) to a phone number.
	 *
	 * @param string $phone   Mobile (any local/international form).
	 * @param array  $message subject, text, url.
	 * @return true|WP_Error
	 */
	public static function send_safir( $phone, $message ) {
		$key    = trim( (string) Khabar_Settings::get( 'bale_safir_key', '' ) );
		$bot_id = self::safir_bot_id();
		if ( '' === $key || ! $bot_id ) {
			return new WP_Error( 'khabar_safir', __( 'کلید API یا شناسه بازوی سفیر بله تنظیم نشده است.', 'khabar' ) );
		}
		$to = Khabar_Utils::international_phone( Khabar_Utils::normalize_phone( $phone ), '98' );
		if ( ! $to ) {
			return new WP_Error( 'khabar_safir', __( 'شماره موبایل نامعتبر', 'khabar' ) );
		}
		$text = ! empty( $message['subject'] ) ? '🔔 ' . $message['subject'] . "\n\n" . $message['text'] : $message['text'];
		$body = array( 'text' => $text );
		$url  = isset( $message['url'] ) ? (string) $message['url'] : '';
		if ( $url && 0 === strpos( $url, 'https://' ) ) {
			// The button carries the link; keep the text clean.
			$body['text']         = trim( str_replace( $url, '', $text ) );
			$body['reply_markup'] = array(
				'inline_keyboard' => array(
					array(
						array(
							'text' => __( '🛒 مشاهده و خرید', 'khabar' ),
							'url'  => $url,
						),
					),
				),
			);
		}
		$res = wp_remote_post(
			self::SAFIR_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'api-access-key' => $key,
					'Content-Type'   => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'request_id'   => wp_generate_uuid4(),
						'bot_id'       => $bot_id,
						'phone_number' => $to,
						'message_data' => array( 'message' => $body ),
					)
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		$err  = is_array( $data ) && ! empty( $data['error_data'] ) ? $data['error_data'] : null;
		if ( is_array( $err ) && isset( $err[0] ) ) {
			$err = $err[0];
		}
		if ( $code >= 200 && $code < 300 && ! $err ) {
			return true;
		}
		$known = array(
			'2'  => __( 'خطای داخلی سرور سفیر', 'khabar' ),
			'3'  => __( 'محدودیت سرعت ارسال سفیر', 'khabar' ),
			'4'  => __( 'داده ارسالی نامعتبر است', 'khabar' ),
			'8'  => __( 'شماره موبایل نامعتبر است', 'khabar' ),
			'17' => __( 'این شماره در بله حساب کاربری ندارد', 'khabar' ),
			'20' => __( 'اعتبار سفیر کافی نیست (شارژ پنل کسب‌وکار بله)', 'khabar' ),
			'21' => __( 'به سقف تعداد مخاطبان سفیر رسیده‌اید', 'khabar' ),
		);
		$ecode = is_array( $err ) ? (string) ( isset( $err['code'] ) ? $err['code'] : ( isset( $err['error'] ) ? $err['error'] : '' ) ) : '';
		$edesc = is_array( $err ) ? (string) ( isset( $err['description'] ) ? $err['description'] : ( isset( $err['message'] ) ? $err['message'] : '' ) ) : '';
		$text  = isset( $known[ $ecode ] ) ? $known[ $ecode ] : ( $edesc ? $edesc : 'HTTP ' . $code . ': ' . mb_substr( $raw, 0, 200 ) );
		return new WP_Error( 'khabar_safir', __( 'سفیر بله: ', 'khabar' ) . $text . ( $ecode ? ' (' . $ecode . ')' : '' ) );
	}

	/**
	 * Secret used to authenticate webhook calls.
	 *
	 * @param string $network Network.
	 * @return string
	 */
	public static function secret( $network ) {
		return substr( hash_hmac( 'sha256', 'khabar-bot-' . $network, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Webhook URL for a network.
	 *
	 * @param string $network Network.
	 * @return string
	 */
	public static function webhook_url( $network ) {
		return add_query_arg( 'secret', self::secret( $network ), rest_url( 'khabar/v1/bot/' . $network ) );
	}

	/**
	 * Normalize a chat / channel id: numeric ids stay as they are; a public username (with or without
	 * "@", or a pasted t.me / ble.ir link) becomes "@username", the form the Bot APIs expect.
	 *
	 * @param string $id Raw id.
	 * @return string
	 */
	public static function normalize_chat( $id ) {
		$id = trim( (string) $id );
		$id = preg_replace( '#^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me|ble\.ir|web\.bale\.ai)/+#i', '', $id );
		$id = trim( $id, "/ \t" );
		if ( '' === $id || preg_match( '/^-?\d+$/', $id ) ) {
			return $id;
		}
		return '@' . ltrim( $id, '@' );
	}

	/**
	 * Health check of a bot, straight from the messenger's API: token, bot username, webhook
	 * registration and its last delivery error, linked customers and channel access.
	 *
	 * @param string $network telegram|bale.
	 * @return array[] [ level (ok|warn|error), text ]
	 */
	public static function check( $network ) {
		global $wpdb;
		$n   = self::network( $network );
		$out = array();
		$safir = self::phone_ready( $network );
		if ( $safir ) {
			/* translators: %d bot id */
			$out[] = array( 'ok', sprintf( __( 'سفیر تنظیم شده (بازو %d): اعلان شخصی مستقیم به شماره موبایل مشتری در بله ارسال می‌شود و Start لازم نیست. صحت کلید را با «ارسال آزمایشی ← بله (شماره موبایل)» بسنجید.', 'khabar' ), self::safir_bot_id() ) );
		} elseif ( 'bale' === $network && Khabar_Settings::get( 'bale_safir_key' ) && ! self::safir_bot_id() ) {
			$out[] = array( 'error', __( 'کلید سفیر وارد شده ولی شناسه بازو (bot_id) مشخص نیست؛ آن را وارد کنید یا توکن ربات را بگذارید.', 'khabar' ) );
		} elseif ( 'bale' === $network ) {
			$out[] = array( 'warn', __( 'سفیر تنظیم نشده؛ پیام شخصی فقط به مشتریانی می‌رسد که ربات را Start کرده‌اند. برای ارسال مستقیم به شماره موبایل، کلید سفیر را وارد کنید.', 'khabar' ) );
		}
		if ( ! $n || ! $n['token'] ) {
			$out[] = $safir
				? array( 'warn', __( 'توکن ربات وارد نشده؛ فقط برای اعلام در کانال بله لازم است.', 'khabar' ) )
				: array( 'error', __( 'توکن ربات وارد نشده است.', 'khabar' ) );
			return $out;
		}
		$me = self::api( $network, 'getMe', array() );
		if ( is_wp_error( $me ) ) {
			/* translators: %s error */
			return array( array( 'error', sprintf( __( 'ربات پاسخ نداد (توکن اشتباه است یا سایت به سرور پیام‌رسان دسترسی ندارد): %s', 'khabar' ), $me->get_error_message() ) ) );
		}
		$username = isset( $me['username'] ) ? (string) $me['username'] : '';
		/* translators: %s bot username */
		$out[] = array( 'ok', sprintf( __( 'توکن درست است؛ ربات ‎@%s پاسخ داد.', 'khabar' ), $username ) );
		if ( ! $n['bot'] && ! $safir ) {
			$out[] = array( 'error', __( 'نام کاربری ربات وارد نشده؛ دکمه «دریافت در پیام‌رسان» به مشتری نمایش داده نمی‌شود.', 'khabar' ) );
		} elseif ( $username && 0 !== strcasecmp( $username, $n['bot'] ) ) {
			/* translators: 1: username in settings 2: username of the token's bot */
			$out[] = array( 'error', sprintf( __( 'نام کاربری ربات در تنظیمات (%1$s) با ربات این توکن (%2$s) یکی نیست؛ مشتری به ربات اشتباه فرستاده می‌شود.', 'khabar' ), $n['bot'], $username ) );
		}
		if ( ! Khabar_Settings::has( 'channels_enabled', $network ) ) {
			$out[] = array( 'error', __( 'این پیام‌رسان در «کانال‌های فعال» (بالای همین صفحه) تیک نخورده؛ پیام شخصی ارسال نمی‌شود.', 'khabar' ) );
		}
		if ( 0 !== strpos( rest_url(), 'https://' ) ) {
			$out[] = array( 'error', __( 'آدرس سایت HTTPS نیست؛ پیام‌رسان‌ها وب‌هوک بدون HTTPS را قبول نمی‌کنند.', 'khabar' ) );
		}

		$info = self::api( $network, 'getWebhookInfo', array() );
		if ( is_wp_error( $info ) ) {
			/* translators: %s error */
			$out[] = array( 'warn', sprintf( __( 'وضعیت وب‌هوک دریافت نشد: %s', 'khabar' ), $info->get_error_message() ) );
		} else {
			$url = isset( $info['url'] ) ? (string) $info['url'] : '';
			if ( '' === $url ) {
				$out[] = array( 'error', __( 'وب‌هوک ثبت نشده است؛ دکمه «ثبت وب‌هوک ربات‌ها» را بزنید. بدون آن، Start مشتری به سایت نمی‌رسد و حسابش متصل نمی‌شود.', 'khabar' ) );
			} elseif ( $url !== self::webhook_url( $network ) ) {
				$out[] = array( 'error', __( 'وب‌هوک ربات به آدرس دیگری ثبت شده (مثلاً سایت یا افزونه دیگری)؛ دکمه «ثبت وب‌هوک ربات‌ها» را بزنید.', 'khabar' ) );
			} else {
				$out[] = array( 'ok', __( 'وب‌هوک درست ثبت شده است.', 'khabar' ) );
			}
			if ( ! empty( $info['last_error_message'] ) ) {
				$when  = ! empty( $info['last_error_date'] ) ? wp_date( 'Y/m/d H:i', (int) $info['last_error_date'] ) : '';
				/* translators: 1: date 2: error */
				$out[] = array( 'error', sprintf( __( 'پیام‌رسان آخرین بار نتوانست پیام کاربر را به سایت برساند (%1$s): %2$s — معمولاً فایروال، افزونه امنیتی یا مسدود بودن REST API سایت.', 'khabar' ), $when, $info['last_error_message'] ) );
			}
			if ( ! empty( $info['pending_update_count'] ) ) {
				/* translators: %d count */
				$out[] = array( 'warn', sprintf( __( '%d پیام کاربر در صف ربات مانده و هنوز به سایت نرسیده.', 'khabar' ), (int) $info['pending_update_count'] ) );
			}
		}

		$linked = $safir ? -1 : (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Khabar_Install::table( 'messenger' ) . ' WHERE network = %s', $network ) ); // phpcs:ignore
		$out[]  = $safir ? array( 'ok', __( 'با سفیر نیازی به اتصال (Start) مشتریان نیست.', 'khabar' ) ) : ( $linked
			/* translators: %d customers */
			? array( 'ok', sprintf( __( '%d مشتری حسابش را به ربات متصل کرده است.', 'khabar' ), $linked ) )
			: array( 'warn', __( 'هنوز هیچ مشتری‌ای متصل نشده. پیام شخصی فقط به مشتریانی می‌رسد که بعد از ثبت درخواست روی «دریافت در پیام‌رسان» زده و در ربات Start را زده باشند.', 'khabar' ) ) );

		if ( $n['channel'] ) {
			$chat = self::api( $network, 'getChat', array( 'chat_id' => $n['channel'] ) );
			if ( is_wp_error( $chat ) ) {
				/* translators: 1: channel 2: error */
				$out[] = array( 'error', sprintf( __( 'کانال %1$s در دسترس ربات نیست: %2$s — شناسه را بررسی کنید و ربات را ادمین کانال کنید.', 'khabar' ), $n['channel'], $chat->get_error_message() ) );
			} else {
				$member = ! empty( $me['id'] ) ? self::api( $network, 'getChatMember', array( 'chat_id' => $n['channel'], 'user_id' => $me['id'] ) ) : null;
				$status = is_array( $member ) && isset( $member['status'] ) ? $member['status'] : '';
				if ( $status && ! in_array( $status, array( 'administrator', 'creator' ), true ) ) {
					/* translators: %s channel */
					$out[] = array( 'error', sprintf( __( 'ربات در کانال %s ادمین نیست؛ برای انتشار پست باید ادمین با اجازه ارسال پیام باشد.', 'khabar' ), $n['channel'] ) );
				} else {
					/* translators: 1: channel 2: title */
					$out[] = array( 'ok', sprintf( __( 'کانال %1$s («%2$s») در دسترس ربات است.', 'khabar' ), $n['channel'], isset( $chat['title'] ) ? $chat['title'] : '' ) );
				}
			}
		}
		return $out;
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			'khabar/v1',
			'/bot/(?P<network>telegram|bale)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'khabar/v1',
			'/messenger/link',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_link' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'khabar/v1',
			'/messenger/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Call a bot API method.
	 *
	 * @param string $network Network.
	 * @param string $method  Method (sendMessage, setWebhook…).
	 * @param array  $params  Params.
	 * @return array|WP_Error Decoded "result".
	 */
	public static function api( $network, $method, $params ) {
		$n = self::network( $network );
		if ( ! $n || ! $n['token'] ) {
			return new WP_Error( 'khabar_msg', __( 'توکن ربات تنظیم نشده است.', 'khabar' ) );
		}
		$res = wp_remote_post(
			sprintf( $n['api'], $n['token'], $method ),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $params ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		// Telegram/Bale: {"ok":true,"result":…}.
		if ( $code >= 300 || ( is_array( $body ) && isset( $body['ok'] ) && ! $body['ok'] ) ) {
			$desc = is_array( $body ) && isset( $body['description'] ) ? $body['description'] : 'HTTP ' . $code;
			return new WP_Error( 'khabar_msg', $n['label'] . ': ' . $desc );
		}
		return is_array( $body ) && isset( $body['result'] ) ? (array) $body['result'] : (array) $body;
	}

	/* ------------------------------------------------------------------ */
	/* Linking                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Create a one-time start code for an owner.
	 *
	 * @param string $owner_key Owner.
	 * @return string
	 */
	public static function create_code( $owner_key ) {
		$code = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( 'khabar_link_' . $code, $owner_key, self::CODE_TTL );
		return $code;
	}

	/**
	 * Deep link to start the bot.
	 *
	 * @param string $network   Network.
	 * @param string $owner_key Owner.
	 * @return string
	 */
	public static function deep_link( $network, $owner_key ) {
		$n = self::network( $network );
		if ( ! $n || ! $n['link'] || ! $n['bot'] || ! $owner_key ) {
			return '';
		}
		return sprintf( $n['link'], rawurlencode( $n['bot'] ), self::create_code( $owner_key ) );
	}

	/**
	 * Linked chat id.
	 *
	 * @param string $owner_key Owner.
	 * @param string $network   Network.
	 * @return string
	 */
	public static function chat_id( $owner_key, $network ) {
		global $wpdb;
		if ( ! $owner_key ) {
			return '';
		}
		$table = Khabar_Install::table( 'messenger' );
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT chat_id FROM {$table} WHERE owner_key = %s AND network = %s", $owner_key, $network ) ); // phpcs:ignore
	}

	/**
	 * Store a link.
	 *
	 * @param string $owner_key Owner.
	 * @param string $network   Network.
	 * @param string $chat_id   Chat id.
	 * @param string $username  Username.
	 */
	public static function link( $owner_key, $network, $chat_id, $username = '' ) {
		global $wpdb;
		$table = Khabar_Install::table( 'messenger' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE owner_key = %s AND network = %s", $owner_key, $network ) ); // phpcs:ignore
		$wpdb->insert( // phpcs:ignore
			$table,
			array(
				'owner_key'  => $owner_key,
				'network'    => $network,
				'chat_id'    => (string) $chat_id,
				'username'   => mb_substr( (string) $username, 0, 100 ),
				'created_at' => Khabar_Utils::now(),
			)
		);
	}

	/**
	 * Remove links of a chat (when the user sends /stop or blocks the bot).
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat id.
	 */
	public static function unlink_chat( $network, $chat_id ) {
		global $wpdb;
		$wpdb->delete( Khabar_Install::table( 'messenger' ), array( 'network' => $network, 'chat_id' => (string) $chat_id ) ); // phpcs:ignore
	}

	/**
	 * Move links from a guest key to a user key (on login).
	 *
	 * @param string $from Old owner key.
	 * @param string $to   New owner key.
	 */
	public static function move_owner( $from, $to ) {
		global $wpdb;
		$table = Khabar_Install::table( 'messenger' );
		foreach ( array_keys( self::networks() ) as $network ) {
			if ( self::chat_id( $to, $network ) ) {
				$wpdb->delete( $table, array( 'owner_key' => $from, 'network' => $network ) ); // phpcs:ignore
			}
		}
		$wpdb->update( $table, array( 'owner_key' => $to ), array( 'owner_key' => $from ) ); // phpcs:ignore
	}

	/**
	 * Bot webhook: handles /start <code>, /stop and /list.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function webhook( WP_REST_Request $req ) {
		$network = $req['network'];
		$secret  = (string) $req->get_param( 'secret' );
		$header  = (string) $req->get_header( 'x_telegram_bot_api_secret_token' );
		$expect  = self::secret( $network );
		if ( ! hash_equals( $expect, $secret ) && ! hash_equals( $expect, $header ) ) {
			return new WP_Error( 'khabar_forbidden', 'forbidden', array( 'status' => 403 ) );
		}

		$update  = $req->get_json_params();
		$message = isset( $update['message'] ) ? $update['message'] : array();
		$chat_id = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text    = isset( $message['text'] ) ? trim( (string) $message['text'] ) : '';
		if ( ! $chat_id || '' === $text ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( preg_match( '#^/start(?:@\w+)?\s+([a-z0-9]{10,64})$#i', $text, $m ) ) {
			$code  = strtolower( $m[1] );
			$owner = get_transient( 'khabar_link_' . $code );
			if ( $owner ) {
				delete_transient( 'khabar_link_' . $code );
				$username = isset( $message['from']['username'] ) ? $message['from']['username'] : '';
				self::link( $owner, $network, $chat_id, $username );
				/* translators: %s site name */
				self::send( $network, $chat_id, sprintf( __( '✅ حساب شما به «خبرم کن» %s متصل شد. از این پس اعلان‌ها را همین‌جا دریافت می‌کنید.', 'khabar' ), $site ) );
			} else {
				self::send( $network, $chat_id, __( 'این لینک منقضی شده است. لطفاً از سایت دوباره روی «اتصال» بزنید.', 'khabar' ) );
			}
		} elseif ( preg_match( '#^/stop#i', $text ) ) {
			self::unlink_chat( $network, $chat_id );
			self::send( $network, $chat_id, __( 'اتصال قطع شد و دیگر پیامی دریافت نمی‌کنید.', 'khabar' ) );
		} elseif ( preg_match( '#^/(list|start)#i', $text ) ) {
			self::send( $network, $chat_id, self::list_text( $network, $chat_id ) );
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Text listing the chat's active requests.
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat id.
	 * @return string
	 */
	private static function list_text( $network, $chat_id ) {
		global $wpdb;
		$table = Khabar_Install::table( 'messenger' );
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT owner_key FROM {$table} WHERE network = %s AND chat_id = %s", $network, $chat_id ) ); // phpcs:ignore
		if ( ! $owner ) {
			return __( 'برای دریافت اعلان‌ها، در صفحه محصول روی «خبرم کن» بزنید و گزینه اتصال را انتخاب کنید.', 'khabar' );
		}
		$query = 0 === strpos( $owner, 'u:' ) ? array( 'user_id' => (int) substr( $owner, 2 ) ) : array( 'owner_key' => $owner );
		$lines = array();
		foreach ( Khabar_Subscriptions::for_owner( $query ) as $sub ) {
			if ( 'active' !== $sub->status ) {
				continue;
			}
			$p       = wc_get_product( $sub->variation_id ? $sub->variation_id : $sub->product_id );
			$lines[] = '• ' . ( $p ? $p->get_name() : '#' . $sub->product_id ) . ' — ' . Khabar_Subscriptions::conditions_label( $sub );
		}
		return $lines ? __( 'درخواست‌های فعال شما:', 'khabar' ) . "\n" . implode( "\n", array_slice( $lines, 0, 20 ) ) : __( 'درخواست فعالی ندارید.', 'khabar' );
	}

	/* ------------------------------------------------------------------ */
	/* Sending                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Send a message (optionally with a URL button).
	 *
	 * @param string $network Network.
	 * @param string $chat_id Chat / channel id.
	 * @param string $text    Text.
	 * @param string $url     Button URL.
	 * @param string $button  Button label.
	 * @return true|WP_Error
	 */
	public static function send( $network, $chat_id, $text, $url = '', $button = '' ) {
		if ( ! $chat_id ) {
			return new WP_Error( 'khabar_msg', __( 'حساب پیام‌رسان متصل نیست.', 'khabar' ) );
		}
		$params = array(
			'chat_id'                  => $chat_id,
			'text'                     => $text,
			'disable_web_page_preview' => false,
		);
		if ( $url && 0 === strpos( $url, 'https://' ) ) {
			$params['reply_markup'] = array(
				'inline_keyboard' => array(
					array(
						array(
							'text' => $button ? $button : __( '🛒 مشاهده و خرید', 'khabar' ),
							'url'  => $url,
						),
					),
				),
			);
		} elseif ( $url ) {
			$params['text'] .= "\n" . $url;
		}
		$res = self::api( $network, 'sendMessage', $params );
		if ( is_wp_error( $res ) ) {
			// The user blocked the bot → forget the link.
			if ( false !== stripos( $res->get_error_message(), 'blocked' ) ) {
				self::unlink_chat( $network, $chat_id );
			}
			return $res;
		}
		return true;
	}

	/**
	 * Send a notification to a subscription's linked chat.
	 *
	 * @param string $network   Network.
	 * @param string $owner_key Owner.
	 * @param array  $message   Built message.
	 * @return true|WP_Error
	 */
	public static function send_to_owner( $network, $owner_key, $message ) {
		$text = $message['subject'] ? '🔔 ' . $message['subject'] . "\n\n" . $message['text'] : $message['text'];
		// The button carries the link; drop the raw link line from the text to keep it clean.
		if ( $message['url'] && 0 === strpos( $message['url'], 'https://' ) ) {
			$text = trim( str_replace( $message['url'], '', $text ) );
		}
		return self::send( $network, self::chat_id( $owner_key, $network ), $text, $message['url'] );
	}

	/**
	 * Announce a restock of a high-demand product in the store's channels (once per restock).
	 *
	 * @param WC_Product $target  Product / variation.
	 * @param array      $snap    Snapshot.
	 * @param int        $waiting Number of people who were waiting.
	 * @return array network => true|WP_Error
	 */
	public static function broadcast_restock( $target, $snap, $waiting ) {
		$networks = (array) Khabar_Settings::get( 'broadcast_networks', array() );
		if ( ! $networks || $waiting < max( 1, (int) Khabar_Settings::get( 'broadcast_min_waiting', 3 ) ) ) {
			return array();
		}
		$last = (int) get_post_meta( $target->get_id(), '_khabar_broadcast_at', true );
		if ( $last && time() - $last < DAY_IN_SECONDS ) {
			return array();
		}
		update_post_meta( $target->get_id(), '_khabar_broadcast_at', time() );

		$parent = $target->is_type( 'variation' ) ? wc_get_product( $target->get_parent_id() ) : $target;
		$link   = $target->is_type( 'variation' ) ? add_query_arg( $target->get_variation_attributes(), get_permalink( $target->get_parent_id() ) ) : get_permalink( $target->get_id() );
		$link   = add_query_arg( array( 'utm_source' => 'khabar', 'utm_medium' => 'channel' ), $link );
		$vars   = array(
			'product_name' => $parent ? $parent->get_name() : $target->get_name(),
			'variation'    => $target->is_type( 'variation' ) ? '(' . wc_get_formatted_variation( $target, true, false, false ) . ')' : '',
			'price'        => Khabar_Utils::price_text( $snap['price'] ),
			'stock'        => null !== $snap['qty'] ? $snap['qty'] : '',
			'waiting'      => number_format_i18n( $waiting ),
			'link'         => '',
			'site_name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		$text   = trim( Khabar_Utils::render( Khabar_Settings::get( 'tpl_broadcast_sms', '' ), $vars ) );
		$out    = array();
		foreach ( $networks as $network ) {
			$n = self::network( $network );
			if ( ! $n || ! $n['token'] || ! $n['channel'] ) {
				continue;
			}
			$out[ $network ] = self::send( $network, $n['channel'], $text, $link );
			Khabar_Channels::log( null, 'broadcast', $network, $n['channel'], $text, $out[ $network ] );
		}
		return $out;
	}

	/**
	 * Register webhooks with Telegram / Bale.
	 *
	 * @return array network => true|WP_Error
	 */
	public static function register_webhooks() {
		$out = array();
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			$n = self::network( $network );
			if ( ! $n['token'] ) {
				continue;
			}
			$params = array(
				'url'             => self::webhook_url( $network ),
				'allowed_updates' => array( 'message' ),
			);
			if ( 'telegram' === $network ) {
				$params['secret_token'] = self::secret( $network );
			}
			$res             = self::api( $network, 'setWebhook', $params );
			$out[ $network ] = is_wp_error( $res ) ? $res : true;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* REST for the frontend                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Create a deep link for the current visitor.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_link( WP_REST_Request $req ) {
		$network = sanitize_key( (string) $req->get_param( 'network' ) );
		if ( ! self::personal_ready( $network ) || ! Khabar_Rest::rate_ok( 'msglink', 20 ) ) {
			return new WP_Error( 'khabar_error', __( 'این پیام‌رسان فعال نیست.', 'khabar' ), array( 'status' => 400 ) );
		}
		$owner = Khabar_Account::current_owner( (string) $req->get_param( 'token' ) );
		$key   = isset( $owner['user_id'] ) ? 'u:' . $owner['user_id'] : ( isset( $owner['owner_key'] ) ? $owner['owner_key'] : Khabar_Utils::owner_key( true ) );
		return rest_ensure_response(
			array(
				'url'       => self::deep_link( $network, $key ),
				'connected' => (bool) self::chat_id( $key, $network ),
			)
		);
	}

	/**
	 * Which networks the current visitor has linked.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_status( WP_REST_Request $req ) {
		$owner = Khabar_Account::current_owner( (string) $req->get_param( 'token' ) );
		$key   = isset( $owner['user_id'] ) ? 'u:' . $owner['user_id'] : ( isset( $owner['owner_key'] ) ? $owner['owner_key'] : '' );
		$out   = array();
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			if ( self::personal_ready( $network ) ) {
				$out[ $network ] = (bool) self::chat_id( $key, $network );
			}
		}
		return rest_ensure_response( $out );
	}
}
