<?php
/**
 * Background jobs through Action Scheduler (WP-Cron fallback).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Background jobs through Action Scheduler (WP-Cron fallback).
 */
final class Queue {

	/**
	 * Queue an async/scheduled action using Action Scheduler when present.
	 *
	 * @param string $hook  Hook.
	 * @param array  $args  Args.
	 * @param int    $delay Delay seconds.
	 */
	public static function schedule( $hook, $args = array(), $delay = 0 ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, 'yadambiar' ) ) {
				return;
			}
			as_schedule_single_action( time() + $delay, $hook, $args, 'yadambiar' );
			return;
		}
		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + $delay, $hook, $args );
		}
	}
}
