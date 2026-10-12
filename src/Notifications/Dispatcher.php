<?php
/**
 * Evaluates waiting subscriptions of a product and sends notifications (Flow 2, 3 & 6).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Channels\Messenger\MessengerChannel;
use YadamBiar\Channels\Sms\ProviderRegistry;
use YadamBiar\Commerce\Coupons;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Commerce\Tracking;
use YadamBiar\Core\Installer;
use YadamBiar\Frontend\Account;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Utils;

class Dispatcher {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'yadambiar_check_product', array( __CLASS__, 'check_product' ), 10, 1 );
	}

	/**
	 * Candidate products (variations) for a subscription.
	 *
	 * @param object       $sub      Subscription.
	 * @param WC_Product   $product  Parent product.
	 * @param WC_Product[] $children Variations keyed by id.
	 * @return WC_Product[]
	 */
	private static function candidates( $sub, $product, $children ) {
		if ( $sub->variation_id ) {
			return isset( $children[ $sub->variation_id ] ) ? array( $children[ $sub->variation_id ] ) : array();
		}
		if ( $children ) {
			$out = array();
			foreach ( $children as $child ) {
				if ( RuleEngine::attributes_match( $sub->attributes, $child->get_variation_attributes() ) ) {
					$out[] = $child;
				}
			}
			return $out;
		}
		return array( $product );
	}

	/**
	 * Check one (parent) product and notify everyone whose conditions are now met.
	 *
	 * @param int $product_id Parent product id.
	 * @return int Number of subscriptions notified.
	 */
	public static function check_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! self::lock( $product_id ) ) {
			// Another run is processing this product right now; look again shortly so nothing is missed.
			Utils::queue( 'yadambiar_check_product', array( $product_id ), 60 );
			return 0;
		}
		Reservation::$bypass = true;
		try {
			return self::do_check_product( $product_id );
		} finally {
			Reservation::$bypass = false;
			self::unlock( $product_id );
		}
	}

	/**
	 * Take the per-product run lock. An admin save runs the check at shutdown while Action
	 * Scheduler may run an already queued check in parallel; without the lock both send.
	 * Uses INSERT IGNORE on the options table so the claim is atomic across requests.
	 *
	 * @param int $product_id Product id.
	 * @return bool
	 */
	private static function lock( $product_id ) {
		global $wpdb;
		$name = 'yadambiar_lock_' . $product_id;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $name, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) ( time() + 5 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Release the per-product run lock.
	 *
	 * @param int $product_id Product id.
	 */
	private static function unlock( $product_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'yadambiar_lock_' . $product_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Has this person (by user, email or phone, on any of their requests) already received this alert
	 * for this item within the duplicate window?
	 *
	 * @param object $sub       Subscription.
	 * @param string $event     Event.
	 * @param int    $target_id Product / variation id.
	 * @return bool
	 */
	public static function recently_notified( $sub, $event, $target_id ) {
		global $wpdb;
		$hours = (int) Settings::get( 'duplicate_window', 72 );
		if ( $hours <= 0 ) {
			return false;
		}
		// back_in_stock / low_stock are the same news ("it's available").
		$events = in_array( $event, array( 'back_in_stock', 'low_stock' ), true ) ? array( 'back_in_stock', 'low_stock' ) : array( $event );
		$who    = array();
		$args   = array();
		if ( $sub->user_id ) {
			$who[]  = 's.user_id = %d';
			$args[] = (int) $sub->user_id;
		}
		if ( $sub->email ) {
			$who[]  = 's.email = %s';
			$args[] = $sub->email;
		}
		if ( $sub->phone ) {
			$who[]  = 's.phone = %s';
			$args[] = $sub->phone;
		}
		if ( ! $who ) {
			return false;
		}
		$log  = Installer::table( 'log' );
		$subs = Installer::table( 'subscriptions' );
		$sql  = "SELECT l.id FROM {$log} l INNER JOIN {$subs} s ON s.id = l.subscription_id WHERE l.status = 'sent' AND l.subscription_id <> %d AND l.variation_id = %d AND l.created_at >= %s AND l.event IN (" . implode( ',', array_fill( 0, count( $events ), '%s' ) ) . ') AND (' . implode( ' OR ', $who ) . ') LIMIT 1';
		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( array( (int) $sub->id, (int) $target_id, Utils::now( -$hours * HOUR_IN_SECONDS ) ), $events, $args ) ) ); // phpcs:ignore
	}

	/**
	 * Identities of a subscriber (user, email, phone) used to send one message per person.
	 *
	 * @param object $sub Subscription.
	 * @return string[]
	 */
	private static function contact_keys( $sub ) {
		return array_filter(
			array(
				$sub->user_id ? 'u:' . $sub->user_id : '',
				$sub->email ? 'e:' . strtolower( $sub->email ) : '',
				$sub->phone ? 'p:' . $sub->phone : '',
			)
		);
	}

	/**
	 * Implementation of check_product (runs with the reservation filter bypassed).
	 *
	 * @param int $product_id Parent product id.
	 * @return int
	 */
	private static function do_check_product( $product_id ) {
		$product = wc_get_product( $product_id );
		$subs    = SubscriptionRepository::active_for_product( $product_id );
		if ( ! $subs ) {
			return 0;
		}
		if ( ! $product || 'trash' === $product->get_status() ) {
			return 0;
		}


		$children = array();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && 'publish' === $child->get_status() ) {
					$children[ $child_id ] = $child;
				}
			}
		}

		$snaps    = array();
		$batch    = max( 1, (int) Settings::get( 'batch_size', 50 ) );
		$cooldown = (int) Settings::get( 'price_change_cooldown', 24 ) * HOUR_IN_SECONDS;
		$opts     = array(
			'price_requires_stock' => (bool) Settings::get( 'price_requires_stock', 1 ),
			'low_stock_threshold'  => (int) Settings::get( 'low_stock_threshold', 0 ),
		);
		$waves    = new WaveLimiter();
		$sent     = 0;
		$more     = false;
		$restocked      = array();
		$reached        = array(); // event|target|contact => true: one message per person per item.
		$waiting_before = SubscriptionRepository::waiting_count( $product_id, null, true );
		$now      = time();

		foreach ( $subs as $sub ) {
			if ( $sub->expires_at && strtotime( $sub->expires_at . ' UTC' ) < $now ) {
				SubscriptionRepository::update( $sub->id, array( 'status' => 'expired' ) );
				continue;
			}

			$opts['cooldown_ok'] = ! $sub->last_notified_at || ( strtotime( $sub->last_notified_at . ' UTC' ) + $cooldown ) <= $now;

			$match = null;
			$event = false;
			foreach ( self::candidates( $sub, $product, $children ) as $candidate ) {
				$cid = $candidate->get_id();
				if ( ! isset( $snaps[ $cid ] ) ) {
					$snaps[ $cid ] = RuleEngine::snapshot( $candidate );
				}
				$event = RuleEngine::evaluate( $sub, $snaps[ $cid ], $opts );
				if ( $event ) {
					$match = $candidate;
					break;
				}
			}
			if ( ! $match ) {
				continue;
			}

			$snap       = $snaps[ $match->get_id() ];
			$stock_type = in_array( $event, array( 'back_in_stock', 'low_stock' ), true ) || ( 'combo' === $event && ( $sub->in_stock || $sub->min_qty ) );

			if ( $stock_type && ! $waves->allow( $match, $snap ) ) {
				continue;
			}
			if ( $sent >= $batch ) {
				$more = true;
				break;
			}

			// The same person waiting twice for this item (e.g. as guest and as customer) gets one message.
			$keys = array();
			foreach ( self::contact_keys( $sub ) as $contact ) {
				$keys[] = $event . '|' . $match->get_id() . '|' . $contact;
			}
			$dup_run = $keys && array_intersect_key( $reached, array_flip( $keys ) );
			// Across runs too: the same person (another request of theirs, a re-subscription, a re-activated
			// request) does not get the same alert for the same item again within the duplicate window.
			$dup_old = ! $dup_run && ! RuleEngine::is_recurring( $sub ) && self::recently_notified( $sub, $event, $match->get_id() );
			if ( $dup_old ) {
				$sub->notified_target = $match->get_id();
				ChannelManager::log( $sub, $event, 'all', $sub->email ? $sub->email : $sub->phone, '', 'skipped' );
			}
			if ( $dup_run || $dup_old ) {
				if ( ! RuleEngine::is_recurring( $sub ) ) {
					SubscriptionRepository::update(
						$sub->id,
						array(
							'status'           => 'notified',
							'last_notified_at' => Utils::now(),
							'notified_target'  => $match->get_id(),
						)
					);
				}
				continue;
			}

			if ( self::notify( $sub, $event, $match, $snap, $stock_type ) ) {
				$reached += array_fill_keys( $keys, true );
				++$sent;
				if ( $stock_type ) {
					$waves->consume( $match );
					$restocked[ $match->get_id() ] = array( $match, $snap );
				}
			}
		}

		$waves->save();

		// Announce restocks of high-demand items in the store's messenger channels.
		foreach ( $restocked as $tid => $info ) {
			MessengerChannel::broadcast_restock( $info[0], $info[1], $waiting_before );
		}

		if ( $more ) {
			Utils::queue( 'yadambiar_check_product', array( (int) $product_id ), 30 );
		} elseif ( $waves->deferred() ) {
			Utils::queue( 'yadambiar_check_product', array( (int) $product_id ), $waves->deferred() );
		}

		wp_cache_delete( 'yadambiar_wc_' . $product_id . '_0', 'yadam-biar' );
		return $sent;
	}

	/**
	 * Build message variables.
	 *
	 * @param object     $sub     Subscription.
	 * @param WC_Product $target  Product / variation that satisfied the rule.
	 * @param array      $snap    Snapshot.
	 * @param bool       $exclusive Exclusive window opened.
	 * @return array
	 */
	public static function vars( $sub, $target, $snap, $exclusive = false ) {
		$parent    = $target->is_type( 'variation' ) ? wc_get_product( $target->get_parent_id() ) : $target;
		$variation = $target->is_type( 'variation' ) ? wc_get_formatted_variation( $target, true, false, false ) : Utils::attributes_label( $sub->attributes );
		$minutes   = (int) Settings::get( 'exclusive_minutes', 30 );
		$target_p  = null !== $sub->price_below ? $sub->price_below : $sub->price_above;

		return apply_filters(
			'yadambiar_message_vars',
			array(
				'customer_name'  => $sub->name ? $sub->name : __( 'مشتری', 'yadam-biar' ),
				'product_name'   => $parent ? $parent->get_name() : $target->get_name(),
				'variation'      => $variation ? '(' . $variation . ')' : '',
				'price'          => null === $snap['price'] ? __( 'استعلام در سایت', 'yadam-biar' ) : Utils::price_text( $snap['price'] ),
				'old_price'      => Utils::price_text( $sub->base_price ),
				'regular_price'  => Utils::price_text( $target->get_regular_price() ),
				'target_price'   => Utils::price_text( $target_p ),
				'stock'          => null !== $snap['qty'] ? $snap['qty'] : __( 'موجود', 'yadam-biar' ),
				'link'           => Tracking::link( $sub, $target->get_id() ),
				'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'minutes'        => $minutes,
				/* translators: %d minutes */
				'exclusive_note' => $exclusive ? '<p><strong>' . sprintf( __( 'شما %d دقیقه فرصت خرید اختصاصی دارید؛ پس از آن محصول برای همه قابل خرید می‌شود.', 'yadam-biar' ), $minutes ) . '</strong></p>' : '',
				'manage_link'    => Account::manage_url( $sub ),
				'code'           => '',
			),
			$sub,
			$target
		);
	}

	/**
	 * Notify a subscriber on all of their channels.
	 *
	 * @param object     $sub        Subscription.
	 * @param string     $event      Event.
	 * @param WC_Product $target     Target product.
	 * @param array      $snap       Snapshot.
	 * @param bool       $stock_type Is a stock-arrival notification.
	 * @return bool At least one channel succeeded.
	 */
	public static function notify( $sub, $event, $target, $snap, $stock_type = false ) {
		// Claim the subscription first: if another run already took it, do not send again.
		$recurring = RuleEngine::is_recurring( $sub );
		if ( ! SubscriptionRepository::claim( $sub, $recurring ) ) {
			return false;
		}

		$exclusive = $stock_type && Reservation::open_window( $target );
		$coupon    = Coupons::applies( $sub, $event ) ? Coupons::create( $sub, $target ) : '';
		$vars      = array_merge( self::vars( $sub, $target, $snap, $exclusive ), Coupons::vars( $coupon ) );
		if ( $exclusive ) {
			/* translators: %d minutes */
			$vars['exclusive_note_text'] = sprintf( __( '%d دقیقه فرصت خرید اختصاصی دارید.', 'yadam-biar' ), $vars['minutes'] );
		}

		$message = ChannelManager::build_message( $event, $vars );
		if ( $exclusive ) {
			$message['text'] .= "\n" . $vars['exclusive_note_text'];
		}
		// Templates written before coupons existed get the coupon appended automatically.
		if ( $coupon && false === strpos( (string) Settings::get( 'tpl_' . $event . '_sms' ), '{coupon' ) ) {
			$message['text'] .= "\n" . $vars['coupon_note'];
		}
		if ( $coupon && false === strpos( (string) Settings::get( 'tpl_' . $event . '_body' ), '{coupon' ) ) {
			$message['html'] .= '<p style="background:#fff7e6;padding:12px;border-radius:8px"><strong>' . esc_html( $vars['coupon_note'] ) . '</strong></p>';
		}

		$sub->notified_target = $target->get_id(); // Logged with each send.
		$results              = ChannelManager::send_to_subscription( $sub, $event, $message );
		$ok      = in_array( true, $results, true );

		if ( ! $ok ) {
			SubscriptionRepository::release( $sub, $recurring ); // Nothing went out: keep waiting.
		}

		if ( $ok ) {
			$update = array(
				'notified_count'   => $sub->notified_count + 1,
				'last_notified_at' => Utils::now(),
				'notified_target'  => $target->get_id(),
			);
			if ( $recurring ) {
				$update['base_price'] = $snap['price'];
			} else {
				$update['status'] = 'notified';
			}
			if ( $exclusive ) {
				$update['reserved_until'] = Utils::now( $vars['minutes'] * MINUTE_IN_SECONDS );
			}
			SubscriptionRepository::update( $sub->id, $update );

			Utils::webhook(
				'notified',
				array(
					'subscription_id' => $sub->id,
					'event'           => $event,
					'product_id'      => $sub->product_id,
					'target_id'       => $target->get_id(),
					'price'           => $snap['price'],
					'stock'           => $snap['qty'],
					'channels'        => array_keys( array_filter( $results ) ),
				)
			);
			do_action( 'yadambiar_notified', $sub, $event, $target, $results );
		}

		return $ok;
	}

	/**
	 * Explain, per waiting subscription of a product, whether it should be notified now and through which channels.
	 *
	 * @param int $product_id Parent product id.
	 * @return array
	 */
	public static function diagnose( $product_id ) {
		global $wpdb;
		$product = wc_get_product( $product_id );
		$table   = Installer::table( 'subscriptions' );
		$subs    = array_map( array( SubscriptionRepository::class, 'hydrate' ), (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d AND status IN ('active','pending') ORDER BY id ASC LIMIT 200", $product_id ) ) ); // phpcs:ignore
		$out     = array();
		if ( ! $product ) {
			return $out;
		}
		$children = array();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && 'publish' === $child->get_status() ) {
					$children[ $child_id ] = $child;
				}
			}
		}
		Reservation::$bypass = true;
		$opts     = array(
			'price_requires_stock' => (bool) Settings::get( 'price_requires_stock', 1 ),
			'low_stock_threshold'  => (int) Settings::get( 'low_stock_threshold', 0 ),
		);
		$cooldown = (int) Settings::get( 'price_change_cooldown', 24 ) * HOUR_IN_SECONDS;
		foreach ( $subs as $sub ) {
			$row = array( 'sub' => $sub, 'ok' => false, 'reason' => '', 'target' => '', 'channels' => array(), 'warnings' => array() );
			if ( 'pending' === $sub->status ) {
				$row['reason'] = __( 'مشتری هنوز کد تایید (OTP) را وارد نکرده؛ درخواست فعال نشده است', 'yadam-biar' );
			} elseif ( $sub->expires_at && strtotime( $sub->expires_at . ' UTC' ) < time() ) {
				$row['reason'] = __( 'درخواست منقضی شده است', 'yadam-biar' );
			} else {
				$opts['cooldown_ok'] = ! $sub->last_notified_at || ( strtotime( $sub->last_notified_at . ' UTC' ) + $cooldown ) <= time();
				$cands               = self::candidates( $sub, $product, $children );
				if ( ! $cands ) {
					$row['reason'] = __( 'تنوع درخواستی وجود ندارد یا منتشر نشده است', 'yadam-biar' );
				}
				foreach ( $cands as $cand ) {
					$x = RuleEngine::explain( $sub, RuleEngine::snapshot( $cand ), $opts );
					if ( $x['ok'] || '' === $row['reason'] ) {
						$row['ok']     = $x['ok'];
						$row['reason'] = $x['reason'];
						$row['target'] = $cand->get_name();
					}
					if ( $x['ok'] ) {
						break;
					}
				}
			}
			$row['channels'] = ChannelManager::resolve( $sub );
			foreach ( $sub->channel_list as $ch ) {
				if ( in_array( $ch, $row['channels'], true ) ) {
					continue;
				}
				if ( 'sms' === $ch && ! $sub->phone ) {
					$row['warnings'][] = __( 'پیامک انتخاب شده ولی شماره موبایل ثبت نشده', 'yadam-biar' );
				} elseif ( 'email' === $ch && ! $sub->email ) {
					$row['warnings'][] = __( 'ایمیل انتخاب شده ولی آدرس ایمیل ثبت نشده', 'yadam-biar' );
				} elseif ( in_array( $ch, array( 'telegram', 'bale' ), true ) && MessengerChannel::phone_ready( $ch ) && ! $sub->phone ) {
					$row['warnings'][] = __( 'بله انتخاب شده ولی شماره موبایل ثبت نشده (سفیر به شماره موبایل ارسال می‌کند)', 'yadam-biar' );
				} elseif ( in_array( $ch, array( 'telegram', 'bale' ), true ) ) {
					/* translators: %s network */
					$row['warnings'][] = sprintf( __( '%s انتخاب شده ولی مشتری ربات را Start نکرده (حساب متصل نیست)', 'yadam-biar' ), 'telegram' === $ch ? __( 'تلگرام', 'yadam-biar' ) : __( 'بله', 'yadam-biar' ) );
				} elseif ( ! Settings::has( 'channels_enabled', $ch ) ) {
					/* translators: %s channel */
					$row['warnings'][] = sprintf( __( 'کانال «%s» در تنظیمات غیرفعال است', 'yadam-biar' ), $ch );
				}
			}
			if ( $row['ok'] && ! $row['channels'] ) {
				$row['warnings'][] = __( 'شرایط برقرار است ولی هیچ کانالی برای ارسال به این مشتری در دسترس نیست', 'yadam-biar' );
			}
			$out[] = $row;
		}
		Reservation::$bypass = false;
		return $out;
	}

	/**
	 * System checks that commonly stop notifications.
	 *
	 * @param int $product_id Product (for the broadcast threshold check).
	 * @return array[] [ level (ok|warn|error), text ]
	 */
	public static function health( $product_id = 0 ) {
		$out = array();
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$out[] = array( 'warn', __( 'WP-Cron در wp-config غیرفعال است؛ اگر کرون سرور تنظیم نشده باشد، صف بررسی موجودی هرگز اجرا نمی‌شود.', 'yadam-biar' ) );
		}
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$late = as_get_scheduled_actions( array( 'hook' => 'yadambiar_check_product', 'status' => 'pending', 'date' => gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS ), 'date_compare' => '<=', 'per_page' => 50 ), 'ids' );
			if ( $late ) {
				/* translators: %d count */
				$out[] = array( 'error', sprintf( __( '%d بررسی در صف Action Scheduler عقب افتاده است؛ صف اجرا نمی‌شود (ووکامرس ← وضعیت ← اقدامات زمان‌بندی‌شده را ببینید یا کرون سرور را تنظیم کنید).', 'yadam-biar' ), count( $late ) ) );
			} else {
				$out[] = array( 'ok', __( 'صف بررسی (Action Scheduler) عقب‌افتادگی ندارد.', 'yadam-biar' ) );
			}
			$failed = as_get_scheduled_actions( array( 'hook' => 'yadambiar_check_product', 'status' => 'failed', 'per_page' => 5 ), 'ids' );
			if ( $failed ) {
				$out[] = array( 'error', __( 'بعضی بررسی‌ها با خطا متوقف شده‌اند؛ جزئیات در ووکامرس ← وضعیت ← اقدامات زمان‌بندی‌شده (گروه yadambiar).', 'yadam-biar' ) );
			}
		}
		$enabled = (array) Settings::get( 'channels_enabled', array() );
		if ( in_array( 'sms', $enabled, true ) ) {
			$driver = ProviderRegistry::driver( (string) Settings::get( 'sms_gateway', '' ) );
			$cred   = in_array( $driver, ProviderRegistry::USER_DRIVERS, true ) ? Settings::get( 'sms_username' ) : Settings::get( 'sms_api_key' );
			if ( 'webhook' !== $driver && ! $cred ) {
				$out[] = array( 'error', __( 'پیامک فعال است ولی اطلاعات ورود سامانه پیامک وارد نشده.', 'yadam-biar' ) );
			}
		}
		$nets = (array) Settings::get( 'broadcast_networks', array() );
		if ( $nets && $product_id ) {
			$min     = max( 1, (int) Settings::get( 'broadcast_min_waiting', 3 ) );
			$waiting = SubscriptionRepository::waiting_count( $product_id, null, true );
			if ( $waiting < $min ) {
				/* translators: 1: min 2: waiting */
				$out[] = array( 'warn', sprintf( __( 'اعلام در کانال (تلگرام/بله) فقط وقتی انجام می‌شود که دست‌کم %1$s نفر منتظر باشند و اعلان به آن‌ها ارسال شود؛ این محصول الان %2$s منتظر دارد. «حداقل تعداد منتظر» را در تنظیمات کمتر کنید.', 'yadam-biar' ), number_format_i18n( $min ), number_format_i18n( $waiting ) ) );
			}
		}
		return $out;
	}

	/**
	 * Manually notify waiting customers of a product (admin action) – re-evaluates rules.
	 *
	 * @param int $product_id Product id.
	 * @return int
	 */
	public static function run_now( $product_id ) {
		$total = 0;
		// Run several batches synchronously so admins get immediate feedback.
		for ( $i = 0; $i < 10; $i++ ) {
			$sent   = self::check_product( $product_id );
			$total += $sent;
			if ( $sent < max( 1, (int) Settings::get( 'batch_size', 50 ) ) ) {
				break;
			}
		}
		return $total;
	}
}
