<?php
/**
 * Evaluates waiting subscriptions of a product and sends notifications (Flow 2, 3 & 6).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Channels\Messenger\ChannelBroadcaster;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Settings;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Clock;
use YadamBiar\Support\Queue;

class Dispatcher implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'yadambiar_check_product', array( __CLASS__, 'check_product' ), 10, 1 );
	}

	/**
	 * Check one (parent) product and notify everyone whose conditions are now met.
	 *
	 * @param int $product_id Parent product id.
	 * @return int Number of subscriptions notified.
	 */
	public static function check_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! ProductLock::acquire( $product_id ) ) {
			// Another run is processing this product right now; look again shortly so nothing is missed.
			Queue::schedule( 'yadambiar_check_product', array( $product_id ), 60 );
			return 0;
		}
		Reservation::$bypass = true;
		try {
			return self::do_check_product( $product_id );
		} finally {
			Reservation::$bypass = false;
			ProductLock::release( $product_id );
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
		$subs    = SubscriptionRepository::active_for_product( $product_id );
		if ( ! $subs ) {
			return 0;
		}
		if ( ! $product || 'trash' === $product->get_status() ) {
			return 0;
		}

		$context  = new EvaluationContext( $product );
		$batch    = max( 1, (int) Settings::get( 'batch_size', 50 ) );
		$waves    = new WaveLimiter();
		$sent     = 0;
		$more     = false;
		$restocked      = array();
		$reached        = array(); // event|target|contact => true: one message per person per item.
		$waiting_before = SubscriptionRepository::waiting_count( $product_id, null, true );

		foreach ( $subs as $sub ) {
			if ( $context->is_expired( $sub ) ) {
				SubscriptionRepository::update( $sub->id, array( 'status' => 'expired' ) );
				continue;
			}

			$opts  = $context->options_for( $sub );
			$match = null;
			$event = false;
			foreach ( $context->candidates( $sub ) as $candidate ) {
				$event = RuleEngine::evaluate( $sub, $context->snapshot( $candidate ), $opts );
				if ( $event ) {
					$match = $candidate;
					break;
				}
			}
			if ( ! $match ) {
				continue;
			}

			$snap       = $context->snapshot( $match );
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
			foreach ( DuplicateGuard::contact_keys( $sub ) as $contact ) {
				$keys[] = $event . '|' . $match->get_id() . '|' . $contact;
			}
			$dup_run = $keys && array_intersect_key( $reached, array_flip( $keys ) );
			// Across runs too: the same person (another request of theirs, a re-subscription, a re-activated
			// request) does not get the same alert for the same item again within the duplicate window.
			$dup_old = ! $dup_run && ! RuleEngine::is_recurring( $sub ) && DuplicateGuard::recently_notified( $sub, $event, $match->get_id() );
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
							'last_notified_at' => Clock::now(),
							'notified_target'  => $match->get_id(),
						)
					);
				}
				continue;
			}

			if ( Notifier::notify( $sub, $event, $match, $snap, $stock_type ) ) {
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
			ChannelBroadcaster::broadcast_restock( $info[0], $info[1], $waiting_before );
		}

		if ( $more ) {
			Queue::schedule( 'yadambiar_check_product', array( (int) $product_id ), 30 );
		} elseif ( $waves->deferred() ) {
			Queue::schedule( 'yadambiar_check_product', array( (int) $product_id ), $waves->deferred() );
		}

		wp_cache_delete( 'yadambiar_wc_' . $product_id . '_0', 'yadambiar' );
		return $sent;
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
