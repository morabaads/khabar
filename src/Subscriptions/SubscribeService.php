<?php
/**
 * Creates customer requests from the notify form: target (variation / wanted attributes),
 * conditions, contact rules, verification and the follow-up check.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Subscriptions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Messenger\MessengerConfig;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Channels\Push\PushSubscriptionRepository;
use YadamBiar\Http\RateLimiter;
use YadamBiar\Notifications\RuleEngine;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Failure;
use YadamBiar\Support\Money;
use YadamBiar\Support\OutgoingWebhook;
use YadamBiar\Support\Phone;
use YadamBiar\Support\Queue;
use YadamBiar\Support\Text;
use YadamBiar\Support\Visitor;
/**
 * Subscribe use case.
 */
final class SubscribeService {

	const ARRAY_FIELDS = array( 'attributes', 'channels', 'push' );

	/**
	 * Raw form input.
	 *
	 * @var array
	 */
	private $input;

	/**
	 * Constructor.
	 *
	 * @param array $input Form fields (product_id, variation_id, attributes, conditions, contact, channels, push…).
	 */
	public function __construct( array $input ) {
		$this->input = $input;
	}

	/**
	 * A raw input value.
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
	 * Validate and store the request(s).
	 *
	 * @return array|\WP_Error Response data.
	 */
	public function run() {
		$s = Settings::all();

		if ( ! RateLimiter::allow( 'sub', (int) $s['rate_limit'] ) ) {
			return Failure::of( __( 'تعداد درخواست‌های شما زیاد است. کمی بعد دوباره تلاش کنید.', 'yadambiar' ), 429 );
		}
		$uid = get_current_user_id();
		if ( $s['require_login'] && ! $uid ) {
			return Failure::of( __( 'برای ثبت درخواست ابتدا وارد حساب کاربری شوید.', 'yadambiar' ), 401 );
		}

		$product = wc_get_product( absint( $this->param( 'product_id' ) ) );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return Failure::of( __( 'محصول یافت نشد.', 'yadambiar' ), 404 );
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		// Target variation / wanted attributes (Feature 1 & 2).
		$variation_id = absint( $this->param( 'variation_id' ) );
		$attributes   = array();
		$valid_attrs  = $product->is_type( 'variable' ) ? $product->get_variation_attributes() : array();
		foreach ( (array) $this->param( 'attributes' ) as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = self::attribute_value( $valid_attrs, sanitize_title( $key ), (string) $value );
			if ( null !== $value ) {
				$attributes[ $value[0] ] = $value[1];
			}
		}
		$target = $product;
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_parent_id() !== $product->get_id() ) {
				return Failure::of( __( 'تنوع انتخابی معتبر نیست.', 'yadambiar' ) );
			}
			$target     = $variation;
			$attributes = array();
		} elseif ( $product->is_type( 'variable' ) && $attributes ) {
			// When the wanted attributes resolve to exactly one variation, store it directly.
			$matches = array();
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && RuleEngine::attributes_match( $attributes, $child->get_variation_attributes() ) ) {
					$matches[] = $child;
				}
			}
			if ( 1 === count( $matches ) && count( $attributes ) === count( $valid_attrs ) ) {
				$target       = $matches[0];
				$variation_id = $target->get_id();
				$attributes   = array();
			}
		}

		// Conditions (Feature 4, 5, 6, 15).
		$types        = (array) $s['enabled_types'];
		$in_stock     = rest_sanitize_boolean( $this->param( 'in_stock' ) ) && in_array( 'stock', $types, true );
		$price_below  = in_array( 'price_drop', $types, true ) ? Money::parse_price( $this->param( 'price_below' ) ) : null;
		$price_above  = in_array( 'price_rise', $types, true ) ? Money::parse_price( $this->param( 'price_above' ) ) : null;
		$price_change = rest_sanitize_boolean( $this->param( 'price_change' ) ) && in_array( 'price_change', $types, true );
		$min_qty      = in_array( 'min_qty', $types, true ) ? absint( Text::latin_digits( (string) $this->param( 'min_qty' ) ) ) : 0;
		$separate     = 'separate' === $this->param( 'mode' );

		if ( ! $in_stock && null === $price_below && null === $price_above && ! $price_change && ! $min_qty ) {
			return Failure::of( __( 'حداقل یک شرط اعلان را انتخاب کنید.', 'yadambiar' ) );
		}
		if ( null !== $price_below && null !== $price_above && $price_below < $price_above && ! $separate ) {
			return Failure::of( __( 'محدوده قیمت تعیین‌شده منطقی نیست.', 'yadambiar' ) );
		}

		$current = '' === $target->get_price() ? null : (float) $target->get_price();
		if ( null !== $price_below && null !== $current && $price_below >= $current && ! $in_stock && ! $min_qty && null === $price_above ) {
			return Failure::of( __( 'قیمت هدف باید کمتر از قیمت فعلی باشد.', 'yadambiar' ) );
		}
		$only_stock = $in_stock && null === $price_below && null === $price_above && ! $price_change && ! $min_qty;
		if ( $only_stock && ! $target->is_type( 'variable' ) && 'instock' === $target->get_stock_status() ) {
			return Failure::of( __( 'این محصول هم‌اکنون موجود است.', 'yadambiar' ) );
		}

		// Contact (Feature 9).
		$user    = $uid ? wp_get_current_user() : null;
		$email   = sanitize_email( (string) $this->param( 'email' ) );
		$phone   = Phone::normalize_phone( (string) $this->param( 'phone' ) );
		$raw_ph  = trim( (string) $this->param( 'phone' ) );
		if ( $user && ! $email && ! $phone ) {
			$email = $user->user_email;
			$phone = Phone::normalize_phone( get_user_meta( $uid, 'billing_phone', true ) );
		}
		if ( '' !== $raw_ph && ! $phone ) {
			return Failure::of( __( 'شماره موبایل معتبر نیست.', 'yadambiar' ) );
		}
		if ( '' !== (string) $this->param( 'email' ) && ! is_email( $email ) ) {
			return Failure::of( __( 'ایمیل معتبر نیست.', 'yadambiar' ) );
		}
		$mode     = $s['contact_mode'];
		$enabled  = (array) $s['channels_enabled'];
		$channels = $s['user_selects_channel'] ? array_values( array_intersect( array_map( 'sanitize_key', (array) $this->param( 'channels' ) ), $enabled ) ) : array();
		// Bale through Safir is sent to the phone number, like SMS.
		$need_ph  = (bool) array_intersect( $channels, MessengerConfig::phone_ready( 'bale' ) ? array( 'sms', 'bale' ) : array( 'sms' ) );
		$need_em  = in_array( 'email', $channels, true );
		if ( $channels && ( $need_ph || $need_em ) ) {
			// The ticked channels decide which contact is required.
			if ( $need_ph && ! $phone ) {
				return Failure::of( __( 'شماره موبایل را وارد کنید.', 'yadambiar' ) );
			}
			if ( $need_em && ! $email ) {
				return Failure::of( __( 'ایمیل را وارد کنید.', 'yadambiar' ) );
			}
		} elseif ( ( 'phone' === $mode && ! $phone ) || ( 'email' === $mode && ! $email ) || ( 'both' === $mode && ( ! $phone || ! $email ) ) || ( 'either' === $mode && ! $phone && ! $email ) ) {
			$labels = array(
				'phone'  => __( 'شماره موبایل را وارد کنید.', 'yadambiar' ),
				'email'  => __( 'ایمیل را وارد کنید.', 'yadambiar' ),
				'both'   => __( 'شماره موبایل و ایمیل را وارد کنید.', 'yadambiar' ),
				'either' => __( 'شماره موبایل یا ایمیل را وارد کنید.', 'yadambiar' ),
			);
			return Failure::of( $labels[ $mode ] );
		}
		if ( (int) $s['max_per_contact'] && SubscriptionRepository::count_for_contact( $email, $phone ) >= (int) $s['max_per_contact'] ) {
			return Failure::of( __( 'تعداد درخواست‌های فعال شما به حداکثر رسیده است.', 'yadambiar' ) );
		}

		$verify = $s['verify_contact'];
		$needs  = ( 'all' === $verify ) || ( 'guest' === $verify && ! $uid );
		if ( $needs && $user && ( ! $phone || $phone === Phone::normalize_phone( get_user_meta( $uid, 'billing_phone', true ) ) ) && ( ! $email || $email === $user->user_email ) ) {
			$needs = false; // Account contact info is trusted.
		}

		$base = array(
			'product_id'   => $product->get_id(),
			'variation_id' => $variation_id,
			'attributes'   => $attributes,
			'user_id'      => $uid,
			'owner_key'    => Visitor::owner_key( true ),
			'name'         => sanitize_text_field( (string) $this->param( 'name' ) ),
			'email'        => $email,
			'phone'        => $phone,
			'channels'     => $channels,
			'status'       => $needs ? 'pending' : 'active',
			'base_price'   => $current,
		);
		if ( ! $base['name'] && $user ) {
			$base['name'] = $user->first_name ? $user->first_name : $user->display_name;
		}

		$sets = array();
		if ( $separate ) {
			if ( $in_stock || $min_qty ) {
				$sets[] = array( 'in_stock' => 1, 'min_qty' => $min_qty );
			}
			if ( null !== $price_below ) {
				$sets[] = array( 'price_below' => $price_below );
			}
			if ( null !== $price_above ) {
				$sets[] = array( 'price_above' => $price_above );
			}
			if ( $price_change ) {
				$sets[] = array( 'price_change' => 1 );
			}
		} else {
			$sets[] = array(
				'in_stock'     => $in_stock || $min_qty ? 1 : 0,
				'price_below'  => $price_below,
				'price_above'  => $price_above,
				'price_change' => $price_change ? 1 : 0,
				'min_qty'      => $min_qty,
			);
		}

		$ids = array();
		foreach ( $sets as $set ) {
			$id = SubscriptionRepository::create( array_merge( $base, $set ) );
			if ( is_wp_error( $id ) ) {
				return Failure::of( $id->get_error_message(), 500 );
			}
			$ids[] = $id;
		}

		// Optional push subscription sent together with the form.
		$push = $this->param( 'push' );
		if ( is_array( $push ) && PushChannel::enabled() ) {
			PushSubscriptionRepository::save_subscription( $base['owner_key'], $push );
		}

		if ( $needs ) {
			$contact = $phone ? $phone : $email;
			$sent    = OtpService::send( $contact, $ids );
			if ( is_wp_error( $sent ) ) {
				return $sent;
			}
			return (
				array(
					'success'   => true,
					'need_code' => true,
					'contact'   => $contact,
					/* translators: %s masked contact */
					'message'   => sprintf( __( 'کد تایید به %s ارسال شد.', 'yadambiar' ), Text::mask( $contact ) ),
				)
			);
		}

		foreach ( $ids as $id ) {
			OutgoingWebhook::send( 'subscribed', array( 'subscription' => SubscriptionPresenter::export( SubscriptionRepository::get( $id ) ) ) );
		}
		// Conditions may already be satisfied (e.g. price already below target) – check soon.
		Queue::schedule( 'yadambiar_check_product', array( (int) $product->get_id() ), 10 );

		return (
			array(
				'success' => true,
				'ids'     => $ids,
				'message' => $s['success_message'],
				'waiting' => SubscriptionRepository::waiting_count( $product->get_id() ),
			)
		);
	
	}

	/**
	 * Validate a wanted attribute against the product's variation attributes.
	 * Values are matched against the real options (term slugs may be URL-encoded for Persian terms).
	 *
	 * @param array  $valid Product variation attributes (name => options).
	 * @param string $key   Requested key (with or without "attribute_").
	 * @param string $value Requested value.
	 * @return array|null [attribute_key, option] or null.
	 */
	private static function attribute_value( $valid, $key, $value ) {
		$key = 0 === strpos( $key, 'attribute_' ) ? substr( $key, 10 ) : $key;
		if ( '' === trim( $value ) ) {
			return null;
		}
		foreach ( $valid as $name => $options ) {
			if ( sanitize_title( $name ) !== $key ) {
				continue;
			}
			foreach ( (array) $options as $option ) {
				$option = (string) $option;
				if ( $option === $value || strtolower( $option ) === strtolower( $value ) || sanitize_title( $value ) === $option || strtolower( rawurlencode( $value ) ) === strtolower( $option ) ) {
					return array( 'attribute_' . $key, $option );
				}
			}
		}
		return null;
	
	}
}
