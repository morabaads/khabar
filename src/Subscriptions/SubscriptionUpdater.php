<?php
/**
 * Validates what a customer may change on their own request (target price, minimum stock,
 * contact, channels) and re-arms a notified request when its target changes.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Subscriptions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
use YadamBiar\Support\Failure;
use YadamBiar\Support\Money;
use YadamBiar\Support\Phone;
use YadamBiar\Support\Text;
/**
 * Customer-side request edits.
 */
final class SubscriptionUpdater {

	const ARRAY_FIELDS = array( 'channels' );

	/**
	 * Raw input.
	 *
	 * @var array
	 */
	private $input;

	/**
	 * Constructor.
	 *
	 * @param array $input Fields sent by the customer.
	 */
	public function __construct( array $input ) {
		$this->input = $input;
	}

	/**
	 * A raw input value (null when not sent).
	 *
	 * @param string $key Key.
	 * @return mixed|null
	 */
	private function param( $key ) {
		$value = isset( $this->input[ $key ] ) ? $this->input[ $key ] : null;
		if ( is_array( $value ) || is_object( $value ) ) {
			return in_array( $key, self::ARRAY_FIELDS, true ) ? (array) $value : null; // Scalars only, except list fields.
		}
		return $value;
	}

	/**
	 * Validated changes for a request.
	 *
	 * @param object $sub Subscription.
	 * @return array|\WP_Error Column => value.
	 */
	public function changes( $sub ) {
		$data = array();
		foreach ( array( 'price_below', 'price_above' ) as $field ) {
			if ( null !== $this->param( $field ) && null !== $sub->$field ) {
				$value = Money::parse_price( $this->param( $field ) );
				if ( ! $value ) {
					return Failure::of( __( 'قیمت معتبر نیست.', 'yadambiar' ) );
				}
				$data[ $field ] = $value;
			}
		}
		if ( null !== $this->param( 'min_qty' ) && $sub->min_qty ) {
			$data['min_qty'] = max( 1, absint( Text::latin_digits( (string) $this->param( 'min_qty' ) ) ) );
		}
		if ( null !== $this->param( 'phone' ) ) {
			$raw   = trim( (string) $this->param( 'phone' ) );
			$phone = Phone::normalize_phone( $raw );
			if ( '' !== $raw && ! $phone ) {
				return Failure::of( __( 'شماره موبایل معتبر نیست.', 'yadambiar' ) );
			}
			$data['phone'] = $phone;
		}
		if ( null !== $this->param( 'email' ) ) {
			$email = sanitize_email( (string) $this->param( 'email' ) );
			if ( '' !== (string) $this->param( 'email' ) && ! is_email( $email ) ) {
				return Failure::of( __( 'ایمیل معتبر نیست.', 'yadambiar' ) );
			}
			$data['email'] = $email;
		}
		if ( isset( $data['phone'] ) || isset( $data['email'] ) ) {
			$phone = isset( $data['phone'] ) ? $data['phone'] : $sub->phone;
			$email = isset( $data['email'] ) ? $data['email'] : $sub->email;
			if ( ! $phone && ! $email ) {
				return Failure::of( __( 'حداقل یک راه ارتباطی لازم است.', 'yadambiar' ) );
			}
		}
		if ( null !== $this->param( 'channels' ) ) {
			$data['channels'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $this->param( 'channels' ) ), (array) Settings::get( 'channels_enabled' ) ) );
		}
		if ( 'notified' === $sub->status && ( isset( $data['price_below'] ) || isset( $data['price_above'] ) || isset( $data['min_qty'] ) ) ) {
			$data['status'] = 'active'; // Re-arm with the new target.
		}
		return $data;
	}
}
