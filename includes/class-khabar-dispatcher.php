<?php
/**
 * Evaluates waiting subscriptions of a product and sends notifications (Flow 2, 3 & 6).
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Dispatcher {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'khabar_check_product', array( __CLASS__, 'check_product' ), 10, 1 );
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
				if ( Khabar_Rules::attributes_match( $sub->attributes, $child->get_variation_attributes() ) ) {
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
		Khabar_Reservation::$bypass = true;
		try {
			return self::do_check_product( $product_id );
		} finally {
			Khabar_Reservation::$bypass = false;
		}
	}

	/**
	 * Implementation of check_product (runs with the reservation filter bypassed).
	 *
	 * @param int $product_id Parent product id.
	 * @return int
	 */
	private static function do_check_product( $product_id ) {
		$product = wc_get_product( $product_id );
		$subs    = Khabar_Subscriptions::active_for_product( $product_id );
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
		$batch    = max( 1, (int) Khabar_Settings::get( 'batch_size', 50 ) );
		$cooldown = (int) Khabar_Settings::get( 'price_change_cooldown', 24 ) * HOUR_IN_SECONDS;
		$opts     = array(
			'price_requires_stock' => (bool) Khabar_Settings::get( 'price_requires_stock', 1 ),
			'low_stock_threshold'  => (int) Khabar_Settings::get( 'low_stock_threshold', 0 ),
		);
		$waves    = new Khabar_Waves();
		$sent     = 0;
		$more     = false;
		$restocked      = array();
		$waiting_before = Khabar_Subscriptions::waiting_count( $product_id, null, true );
		$now      = time();

		foreach ( $subs as $sub ) {
			if ( $sub->expires_at && strtotime( $sub->expires_at . ' UTC' ) < $now ) {
				Khabar_Subscriptions::update( $sub->id, array( 'status' => 'expired' ) );
				continue;
			}

			$opts['cooldown_ok'] = ! $sub->last_notified_at || ( strtotime( $sub->last_notified_at . ' UTC' ) + $cooldown ) <= $now;

			$match = null;
			$event = false;
			foreach ( self::candidates( $sub, $product, $children ) as $candidate ) {
				$cid = $candidate->get_id();
				if ( ! isset( $snaps[ $cid ] ) ) {
					$snaps[ $cid ] = Khabar_Rules::snapshot( $candidate );
				}
				$event = Khabar_Rules::evaluate( $sub, $snaps[ $cid ], $opts );
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

			if ( self::notify( $sub, $event, $match, $snap, $stock_type ) ) {
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
			Khabar_Messenger::broadcast_restock( $info[0], $info[1], $waiting_before );
		}

		if ( $more ) {
			Khabar_Utils::queue( 'khabar_check_product', array( (int) $product_id ), 30 );
		} elseif ( $waves->deferred() ) {
			Khabar_Utils::queue( 'khabar_check_product', array( (int) $product_id ), $waves->deferred() );
		}

		wp_cache_delete( 'khabar_wc_' . $product_id . '_0', 'khabar' );
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
		$variation = $target->is_type( 'variation' ) ? wc_get_formatted_variation( $target, true, false, false ) : Khabar_Utils::attributes_label( $sub->attributes );
		$minutes   = (int) Khabar_Settings::get( 'exclusive_minutes', 30 );
		$target_p  = null !== $sub->price_below ? $sub->price_below : $sub->price_above;

		return apply_filters(
			'khabar_message_vars',
			array(
				'customer_name'  => $sub->name ? $sub->name : __( 'مشتری', 'khabar' ),
				'product_name'   => $parent ? $parent->get_name() : $target->get_name(),
				'variation'      => $variation ? '(' . $variation . ')' : '',
				'price'          => Khabar_Utils::price_text( $snap['price'] ),
				'old_price'      => Khabar_Utils::price_text( $sub->base_price ),
				'regular_price'  => Khabar_Utils::price_text( $target->get_regular_price() ),
				'target_price'   => Khabar_Utils::price_text( $target_p ),
				'stock'          => null !== $snap['qty'] ? $snap['qty'] : __( 'موجود', 'khabar' ),
				'link'           => Khabar_Tracking::link( $sub, $target->get_id() ),
				'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'minutes'        => $minutes,
				/* translators: %d minutes */
				'exclusive_note' => $exclusive ? '<p><strong>' . sprintf( __( 'شما %d دقیقه فرصت خرید اختصاصی دارید؛ پس از آن محصول برای همه قابل خرید می‌شود.', 'khabar' ), $minutes ) . '</strong></p>' : '',
				'manage_link'    => Khabar_Account::manage_url( $sub ),
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
		$exclusive = $stock_type && Khabar_Reservation::open_window( $target );
		$coupon    = Khabar_Coupons::applies( $sub, $event ) ? Khabar_Coupons::create( $sub, $target ) : '';
		$vars      = array_merge( self::vars( $sub, $target, $snap, $exclusive ), Khabar_Coupons::vars( $coupon ) );
		if ( $exclusive ) {
			/* translators: %d minutes */
			$vars['exclusive_note_text'] = sprintf( __( '%d دقیقه فرصت خرید اختصاصی دارید.', 'khabar' ), $vars['minutes'] );
		}

		$message = Khabar_Channels::build_message( $event, $vars );
		if ( $exclusive ) {
			$message['text'] .= "\n" . $vars['exclusive_note_text'];
		}
		// Templates written before coupons existed get the coupon appended automatically.
		if ( $coupon && false === strpos( (string) Khabar_Settings::get( 'tpl_' . $event . '_sms' ), '{coupon' ) ) {
			$message['text'] .= "\n" . $vars['coupon_note'];
		}
		if ( $coupon && false === strpos( (string) Khabar_Settings::get( 'tpl_' . $event . '_body' ), '{coupon' ) ) {
			$message['html'] .= '<p style="background:#fff7e6;padding:12px;border-radius:8px"><strong>' . esc_html( $vars['coupon_note'] ) . '</strong></p>';
		}

		$sub->notified_target = $target->get_id(); // Logged with each send.
		$results              = Khabar_Channels::send_to_subscription( $sub, $event, $message );
		$ok      = in_array( true, $results, true );

		if ( $ok ) {
			$update = array(
				'notified_count'   => $sub->notified_count + 1,
				'last_notified_at' => Khabar_Utils::now(),
				'notified_target'  => $target->get_id(),
			);
			if ( Khabar_Rules::is_recurring( $sub ) ) {
				$update['base_price'] = $snap['price'];
			} else {
				$update['status'] = 'notified';
			}
			if ( $exclusive ) {
				$update['reserved_until'] = Khabar_Utils::now( $vars['minutes'] * MINUTE_IN_SECONDS );
			}
			Khabar_Subscriptions::update( $sub->id, $update );

			Khabar_Utils::webhook(
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
			do_action( 'khabar_notified', $sub, $event, $target, $results );
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
		$table   = Khabar_Install::table( 'subscriptions' );
		$subs    = array_map( array( 'Khabar_Subscriptions', 'hydrate' ), (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d AND status IN ('active','pending') ORDER BY id ASC LIMIT 200", $product_id ) ) ); // phpcs:ignore
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
		Khabar_Reservation::$bypass = true;
		$opts     = array(
			'price_requires_stock' => (bool) Khabar_Settings::get( 'price_requires_stock', 1 ),
			'low_stock_threshold'  => (int) Khabar_Settings::get( 'low_stock_threshold', 0 ),
		);
		$cooldown = (int) Khabar_Settings::get( 'price_change_cooldown', 24 ) * HOUR_IN_SECONDS;
		foreach ( $subs as $sub ) {
			$row = array( 'sub' => $sub, 'ok' => false, 'reason' => '', 'target' => '', 'channels' => array(), 'warnings' => array() );
			if ( 'pending' === $sub->status ) {
				$row['reason'] = __( 'مشتری هنوز کد تایید (OTP) را وارد نکرده؛ درخواست فعال نشده است', 'khabar' );
			} elseif ( $sub->expires_at && strtotime( $sub->expires_at . ' UTC' ) < time() ) {
				$row['reason'] = __( 'درخواست منقضی شده است', 'khabar' );
			} else {
				$opts['cooldown_ok'] = ! $sub->last_notified_at || ( strtotime( $sub->last_notified_at . ' UTC' ) + $cooldown ) <= time();
				$cands               = self::candidates( $sub, $product, $children );
				if ( ! $cands ) {
					$row['reason'] = __( 'تنوع درخواستی وجود ندارد یا منتشر نشده است', 'khabar' );
				}
				foreach ( $cands as $cand ) {
					$x = Khabar_Rules::explain( $sub, Khabar_Rules::snapshot( $cand ), $opts );
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
			$row['channels'] = Khabar_Channels::resolve( $sub );
			foreach ( $sub->channel_list as $ch ) {
				if ( in_array( $ch, $row['channels'], true ) ) {
					continue;
				}
				if ( in_array( $ch, array( 'sms', 'whatsapp' ), true ) && ! $sub->phone ) {
					$row['warnings'][] = __( 'پیامک/واتساپ انتخاب شده ولی شماره موبایل ثبت نشده', 'khabar' );
				} elseif ( 'email' === $ch && ! $sub->email ) {
					$row['warnings'][] = __( 'ایمیل انتخاب شده ولی آدرس ایمیل ثبت نشده', 'khabar' );
				} elseif ( in_array( $ch, array( 'telegram', 'bale' ), true ) ) {
					/* translators: %s network */
					$row['warnings'][] = sprintf( __( '%s انتخاب شده ولی مشتری ربات را Start نکرده (حساب متصل نیست)', 'khabar' ), 'telegram' === $ch ? __( 'تلگرام', 'khabar' ) : __( 'بله', 'khabar' ) );
				} elseif ( ! Khabar_Settings::has( 'channels_enabled', $ch ) ) {
					/* translators: %s channel */
					$row['warnings'][] = sprintf( __( 'کانال «%s» در تنظیمات غیرفعال است', 'khabar' ), $ch );
				}
			}
			if ( $row['ok'] && ! $row['channels'] ) {
				$row['warnings'][] = __( 'شرایط برقرار است ولی هیچ کانالی برای ارسال به این مشتری در دسترس نیست', 'khabar' );
			}
			$out[] = $row;
		}
		Khabar_Reservation::$bypass = false;
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
			$out[] = array( 'warn', __( 'WP-Cron در wp-config غیرفعال است؛ اگر کرون سرور تنظیم نشده باشد، صف بررسی موجودی هرگز اجرا نمی‌شود.', 'khabar' ) );
		}
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$late = as_get_scheduled_actions( array( 'hook' => 'khabar_check_product', 'status' => 'pending', 'date' => gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS ), 'date_compare' => '<=', 'per_page' => 50 ), 'ids' );
			if ( $late ) {
				/* translators: %d count */
				$out[] = array( 'error', sprintf( __( '%d بررسی در صف Action Scheduler عقب افتاده است؛ صف اجرا نمی‌شود (ووکامرس ← وضعیت ← اقدامات زمان‌بندی‌شده را ببینید یا کرون سرور را تنظیم کنید).', 'khabar' ), count( $late ) ) );
			} else {
				$out[] = array( 'ok', __( 'صف بررسی (Action Scheduler) عقب‌افتادگی ندارد.', 'khabar' ) );
			}
			$failed = as_get_scheduled_actions( array( 'hook' => 'khabar_check_product', 'status' => 'failed', 'per_page' => 5 ), 'ids' );
			if ( $failed ) {
				$out[] = array( 'error', __( 'بعضی بررسی‌ها با خطا متوقف شده‌اند؛ جزئیات در ووکامرس ← وضعیت ← اقدامات زمان‌بندی‌شده (گروه khabar).', 'khabar' ) );
			}
		}
		$enabled = (array) Khabar_Settings::get( 'channels_enabled', array() );
		if ( in_array( 'sms', $enabled, true ) ) {
			$driver = Khabar_Sms_Providers::driver( (string) Khabar_Settings::get( 'sms_gateway', '' ) );
			$cred   = in_array( $driver, Khabar_Sms_Providers::USER_DRIVERS, true ) ? Khabar_Settings::get( 'sms_username' ) : Khabar_Settings::get( 'sms_api_key' );
			if ( 'webhook' !== $driver && ! $cred ) {
				$out[] = array( 'error', __( 'پیامک فعال است ولی اطلاعات ورود سامانه پیامک وارد نشده.', 'khabar' ) );
			}
		}
		$nets = (array) Khabar_Settings::get( 'broadcast_networks', array() );
		if ( $nets && $product_id ) {
			$min     = max( 1, (int) Khabar_Settings::get( 'broadcast_min_waiting', 3 ) );
			$waiting = Khabar_Subscriptions::waiting_count( $product_id, null, true );
			if ( $waiting < $min ) {
				/* translators: 1: min 2: waiting */
				$out[] = array( 'warn', sprintf( __( 'اعلام در کانال (تلگرام/بله/ایتا) فقط وقتی انجام می‌شود که دست‌کم %1$s نفر منتظر باشند و اعلان به آن‌ها ارسال شود؛ این محصول الان %2$s منتظر دارد. «حداقل تعداد منتظر» را در تنظیمات کمتر کنید.', 'khabar' ), number_format_i18n( $min ), number_format_i18n( $waiting ) ) );
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
			if ( $sent < max( 1, (int) Khabar_Settings::get( 'batch_size', 50 ) ) ) {
				break;
			}
		}
		return $total;
	}
}
