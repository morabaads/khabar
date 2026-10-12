<?php
/**
 * Everything needed to evaluate the requests of one product: its published variations,
 * cached snapshots, the rule options from the settings and the clock.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Notifications;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;

/**
 * Per-product evaluation state.
 */
final class EvaluationContext {

	/**
	 * Parent product.
	 *
	 * @var \WC_Product
	 */
	private $product;

	/**
	 * Published variations keyed by id.
	 *
	 * @var \WC_Product[]
	 */
	private $children = array();

	/**
	 * Snapshots keyed by product id.
	 *
	 * @var array
	 */
	private $snapshots = array();

	/**
	 * Rule options shared by every request.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Minimum seconds between two "any price change" alerts.
	 *
	 * @var int
	 */
	private $cooldown;

	/**
	 * Evaluation time.
	 *
	 * @var int
	 */
	private $now;

	/**
	 * Constructor.
	 *
	 * @param \WC_Product $product Parent product.
	 */
	public function __construct( \WC_Product $product ) {
		$this->product  = $product;
		$this->now      = time();
		$this->cooldown = (int) Settings::get( 'price_change_cooldown', 24 ) * HOUR_IN_SECONDS;
		$this->options  = array(
			'price_requires_stock' => (bool) Settings::get( 'price_requires_stock', 1 ),
			'low_stock_threshold'  => (int) Settings::get( 'low_stock_threshold', 0 ),
		);
		if ( $product->is_type( 'variable' ) ) {
			$ids = array_map( 'absint', $product->get_children() );
			_prime_post_caches( $ids ); // One query for all variations instead of one per variation.
			foreach ( $ids as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && 'publish' === $child->get_status() ) {
					$this->children[ $child_id ] = $child;
				}
			}
		}
	}

	/**
	 * Evaluation time.
	 *
	 * @return int
	 */
	public function now() {
		return $this->now;
	}

	/**
	 * Products (the product or matching variations) a request can be satisfied by.
	 *
	 * @param object $sub Subscription.
	 * @return \WC_Product[]
	 */
	public function candidates( $sub ) {
		if ( $sub->variation_id ) {
			return isset( $this->children[ $sub->variation_id ] ) ? array( $this->children[ $sub->variation_id ] ) : array();
		}
		if ( ! $this->children ) {
			return array( $this->product );
		}
		$out = array();
		foreach ( $this->children as $child ) {
			if ( RuleEngine::attributes_match( $sub->attributes, $child->get_variation_attributes() ) ) {
				$out[] = $child;
			}
		}
		return $out;
	}

	/**
	 * Cached snapshot of a candidate.
	 *
	 * @param \WC_Product $product Product / variation.
	 * @return array
	 */
	public function snapshot( \WC_Product $product ) {
		$id = $product->get_id();
		if ( ! isset( $this->snapshots[ $id ] ) ) {
			$this->snapshots[ $id ] = RuleEngine::snapshot( $product );
		}
		return $this->snapshots[ $id ];
	}

	/**
	 * Rule options for one request (adds its own price-change cooldown state).
	 *
	 * @param object $sub Subscription.
	 * @return array
	 */
	public function options_for( $sub ) {
		return $this->options + array(
			'cooldown_ok' => ! $sub->last_notified_at || ( strtotime( $sub->last_notified_at . ' UTC' ) + $this->cooldown ) <= $this->now,
		);
	}

	/**
	 * Has a request passed its expiry date?
	 *
	 * @param object $sub Subscription.
	 * @return bool
	 */
	public function is_expired( $sub ) {
		return $sub->expires_at && strtotime( $sub->expires_at . ' UTC' ) < $this->now;
	}
}
