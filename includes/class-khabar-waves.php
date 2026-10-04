<?php
/**
 * Wave based sending: with limited stock notify the earliest subscribers first
 * (stock × multiplier), then the next wave after an interval if stock remains.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Waves {

	const META = '_khabar_wave';

	/**
	 * Wave state per product id.
	 *
	 * @var array
	 */
	private $state = array();

	/**
	 * Products whose state changed.
	 *
	 * @var WC_Product[]
	 */
	private $dirty = array();

	/**
	 * Seconds until next wave, 0 = none deferred.
	 *
	 * @var int
	 */
	private $deferred = 0;

	/**
	 * Is enabled.
	 *
	 * @return bool
	 */
	private function enabled() {
		return (bool) Khabar_Settings::get( 'waves_enabled' );
	}

	/**
	 * Load wave state for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private function &state( $product ) {
		$id = $product->get_id();
		if ( ! isset( $this->state[ $id ] ) ) {
			$saved               = get_post_meta( $id, self::META, true );
			$this->state[ $id ]  = is_array( $saved ) ? $saved : array(
				'started' => 0,
				'count'   => 0,
			);
		}
		return $this->state[ $id ];
	}

	/**
	 * May we notify one more person for this product now?
	 *
	 * @param WC_Product $product Product.
	 * @param array      $snap    Snapshot.
	 * @return bool
	 */
	public function allow( $product, $snap ) {
		if ( ! $this->enabled() || ! $snap['manage'] || null === $snap['qty'] || $snap['qty'] <= 0 ) {
			return true;
		}
		$interval = max( 1, (int) Khabar_Settings::get( 'waves_interval', 60 ) ) * MINUTE_IN_SECONDS;
		$cap      = (int) ceil( $snap['qty'] * max( 1, (float) Khabar_Settings::get( 'waves_multiplier', 3 ) ) );
		$state    = &$this->state( $product );
		$now      = time();

		if ( $now - (int) $state['started'] >= $interval ) {
			$state = array(
				'started' => $now,
				'count'   => 0,
			);
			$this->dirty[ $product->get_id() ] = $product;
		}
		if ( $state['count'] < $cap ) {
			return true;
		}
		$wait           = $interval - ( $now - (int) $state['started'] ) + 5;
		$this->deferred = $this->deferred ? min( $this->deferred, $wait ) : $wait;
		return false;
	}

	/**
	 * Record one notification.
	 *
	 * @param WC_Product $product Product.
	 */
	public function consume( $product ) {
		if ( ! $this->enabled() ) {
			return;
		}
		$state = &$this->state( $product );
		++$state['count'];
		$this->dirty[ $product->get_id() ] = $product;
	}

	/**
	 * Persist.
	 */
	public function save() {
		foreach ( $this->dirty as $id => $product ) {
			update_post_meta( $id, self::META, $this->state[ $id ] );
		}
		$this->dirty = array();
	}

	/**
	 * Seconds to the next wave (0 when nothing deferred).
	 *
	 * @return int
	 */
	public function deferred() {
		return $this->deferred;
	}
}
