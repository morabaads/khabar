<?php
/**
 * REST API (khabar/v1).
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Rest {

	const NS = 'khabar/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function routes() {
		$public = '__return_true';
		register_rest_route( self::NS, '/subscribe', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'subscribe' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/verify', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'verify' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/subscriptions', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'list_subscriptions' ), 'permission_callback' => $public ) );
		register_rest_route(
			self::NS,
			'/subscriptions/(?P<id>\d+)',
			array(
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'update_subscription' ), 'permission_callback' => $public ),
				array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_subscription' ), 'permission_callback' => $public ),
			)
		);
		register_rest_route( self::NS, '/notifications', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'notifications' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/notifications/read', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'notifications_read' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/push/subscribe', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'push_subscribe' ), 'permission_callback' => $public ) );
		register_rest_route( self::NS, '/push/latest', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'push_latest' ), 'permission_callback' => $public ) );
	}

	/**
	 * Error helper.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private static function error( $message, $status = 400 ) {
		return new WP_Error( 'khabar_error', $message, array( 'status' => $status ) );
	}

	/**
	 * Simple per-IP rate limiting.
	 *
	 * @param string $bucket Bucket.
	 * @param int    $limit  Limit per hour.
	 * @return bool Allowed.
	 */
	public static function rate_ok( $bucket, $limit ) {
		if ( $limit <= 0 || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		$key   = 'khabar_rl_' . $bucket . '_' . md5( Khabar_Utils::ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Create subscription(s) – Flow 1 & 3.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function subscribe( WP_REST_Request $req ) {
		$s = Khabar_Settings::all();

		if ( '' !== (string) $req->get_param( 'website' ) ) { // Honeypot.
			return rest_ensure_response( array( 'success' => true, 'message' => $s['success_message'] ) );
		}
		if ( ! self::rate_ok( 'sub', (int) $s['rate_limit'] ) ) {
			return self::error( __( 'تعداد درخواست‌های شما زیاد است. کمی بعد دوباره تلاش کنید.', 'khabar' ), 429 );
		}
		$uid = get_current_user_id();
		if ( $s['require_login'] && ! $uid ) {
			return self::error( __( 'برای ثبت درخواست ابتدا وارد حساب کاربری شوید.', 'khabar' ), 401 );
		}

		$product = wc_get_product( absint( $req->get_param( 'product_id' ) ) );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return self::error( __( 'محصول یافت نشد.', 'khabar' ), 404 );
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		// Target variation / wanted attributes (Feature 1 & 2).
		$variation_id = absint( $req->get_param( 'variation_id' ) );
		$attributes   = array();
		$valid_attrs  = $product->is_type( 'variable' ) ? $product->get_variation_attributes() : array();
		foreach ( (array) $req->get_param( 'attributes' ) as $key => $value ) {
			$value = self::valid_attribute_value( $valid_attrs, sanitize_title( $key ), wp_unslash( (string) $value ) );
			if ( null !== $value ) {
				$attributes[ $value[0] ] = $value[1];
			}
		}
		$target = $product;
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_parent_id() !== $product->get_id() ) {
				return self::error( __( 'تنوع انتخابی معتبر نیست.', 'khabar' ) );
			}
			$target     = $variation;
			$attributes = array();
		} elseif ( $product->is_type( 'variable' ) && $attributes ) {
			// When the wanted attributes resolve to exactly one variation, store it directly.
			$matches = array();
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && Khabar_Rules::attributes_match( $attributes, $child->get_variation_attributes() ) ) {
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
		$in_stock     = rest_sanitize_boolean( $req->get_param( 'in_stock' ) ) && in_array( 'stock', $types, true );
		$price_below  = in_array( 'price_drop', $types, true ) ? Khabar_Utils::parse_price( $req->get_param( 'price_below' ) ) : null;
		$price_above  = in_array( 'price_rise', $types, true ) ? Khabar_Utils::parse_price( $req->get_param( 'price_above' ) ) : null;
		$price_change = rest_sanitize_boolean( $req->get_param( 'price_change' ) ) && in_array( 'price_change', $types, true );
		$min_qty      = in_array( 'min_qty', $types, true ) ? absint( Khabar_Utils::latin_digits( (string) $req->get_param( 'min_qty' ) ) ) : 0;
		$separate     = 'separate' === $req->get_param( 'mode' );

		if ( ! $in_stock && null === $price_below && null === $price_above && ! $price_change && ! $min_qty ) {
			return self::error( __( 'حداقل یک شرط اعلان را انتخاب کنید.', 'khabar' ) );
		}
		if ( null !== $price_below && null !== $price_above && $price_below < $price_above && ! $separate ) {
			return self::error( __( 'محدوده قیمت تعیین‌شده منطقی نیست.', 'khabar' ) );
		}

		$current = '' === $target->get_price() ? null : (float) $target->get_price();
		if ( null !== $price_below && null !== $current && $price_below >= $current && ! $in_stock && ! $min_qty && null === $price_above ) {
			return self::error( __( 'قیمت هدف باید کمتر از قیمت فعلی باشد.', 'khabar' ) );
		}
		$only_stock = $in_stock && null === $price_below && null === $price_above && ! $price_change && ! $min_qty;
		if ( $only_stock && ! $target->is_type( 'variable' ) && 'instock' === $target->get_stock_status() ) {
			return self::error( __( 'این محصول هم‌اکنون موجود است.', 'khabar' ) );
		}

		// Contact (Feature 9).
		$user    = $uid ? wp_get_current_user() : null;
		$email   = sanitize_email( (string) $req->get_param( 'email' ) );
		$phone   = Khabar_Utils::normalize_phone( (string) $req->get_param( 'phone' ) );
		$raw_ph  = trim( (string) $req->get_param( 'phone' ) );
		if ( $user && ! $email && ! $phone ) {
			$email = $user->user_email;
			$phone = Khabar_Utils::normalize_phone( get_user_meta( $uid, 'billing_phone', true ) );
		}
		if ( '' !== $raw_ph && ! $phone ) {
			return self::error( __( 'شماره موبایل معتبر نیست.', 'khabar' ) );
		}
		if ( '' !== (string) $req->get_param( 'email' ) && ! is_email( $email ) ) {
			return self::error( __( 'ایمیل معتبر نیست.', 'khabar' ) );
		}
		$mode     = $s['contact_mode'];
		$enabled  = (array) $s['channels_enabled'];
		$channels = $s['user_selects_channel'] ? array_values( array_intersect( array_map( 'sanitize_key', (array) $req->get_param( 'channels' ) ), $enabled ) ) : array();
		$need_ph  = (bool) array_intersect( $channels, array( 'sms', 'whatsapp' ) );
		$need_em  = in_array( 'email', $channels, true );
		if ( $channels && ( $need_ph || $need_em ) ) {
			// The ticked channels decide which contact is required.
			if ( $need_ph && ! $phone ) {
				return self::error( __( 'شماره موبایل را وارد کنید.', 'khabar' ) );
			}
			if ( $need_em && ! $email ) {
				return self::error( __( 'ایمیل را وارد کنید.', 'khabar' ) );
			}
		} elseif ( ( 'phone' === $mode && ! $phone ) || ( 'email' === $mode && ! $email ) || ( 'both' === $mode && ( ! $phone || ! $email ) ) || ( 'either' === $mode && ! $phone && ! $email ) ) {
			$labels = array(
				'phone'  => __( 'شماره موبایل را وارد کنید.', 'khabar' ),
				'email'  => __( 'ایمیل را وارد کنید.', 'khabar' ),
				'both'   => __( 'شماره موبایل و ایمیل را وارد کنید.', 'khabar' ),
				'either' => __( 'شماره موبایل یا ایمیل را وارد کنید.', 'khabar' ),
			);
			return self::error( $labels[ $mode ] );
		}
		if ( (int) $s['max_per_contact'] && Khabar_Subscriptions::count_for_contact( $email, $phone ) >= (int) $s['max_per_contact'] ) {
			return self::error( __( 'تعداد درخواست‌های فعال شما به حداکثر رسیده است.', 'khabar' ) );
		}


		$verify = $s['verify_contact'];
		$needs  = ( 'all' === $verify ) || ( 'guest' === $verify && ! $uid );
		if ( $needs && $user && ( ! $phone || $phone === Khabar_Utils::normalize_phone( get_user_meta( $uid, 'billing_phone', true ) ) ) && ( ! $email || $email === $user->user_email ) ) {
			$needs = false; // Account contact info is trusted.
		}

		$base = array(
			'product_id'   => $product->get_id(),
			'variation_id' => $variation_id,
			'attributes'   => $attributes,
			'user_id'      => $uid,
			'owner_key'    => Khabar_Utils::owner_key( true ),
			'name'         => sanitize_text_field( (string) $req->get_param( 'name' ) ),
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
			$id = Khabar_Subscriptions::create( array_merge( $base, $set ) );
			if ( is_wp_error( $id ) ) {
				return self::error( $id->get_error_message(), 500 );
			}
			$ids[] = $id;
		}

		// Optional push subscription sent together with the form.
		$push = $req->get_param( 'push' );
		if ( is_array( $push ) && Khabar_Push::enabled() ) {
			Khabar_Push::save_subscription( $base['owner_key'], $push );
		}

		if ( $needs ) {
			$contact = $phone ? $phone : $email;
			$sent    = self::send_code( $contact, $ids );
			if ( is_wp_error( $sent ) ) {
				return self::error( $sent->get_error_message(), 500 );
			}
			return rest_ensure_response(
				array(
					'success'   => true,
					'need_code' => true,
					'contact'   => $contact,
					/* translators: %s masked contact */
					'message'   => sprintf( __( 'کد تایید به %s ارسال شد.', 'khabar' ), Khabar_Utils::mask( $contact ) ),
				)
			);
		}

		foreach ( $ids as $id ) {
			Khabar_Utils::webhook( 'subscribed', array( 'subscription' => self::export( Khabar_Subscriptions::get( $id ) ) ) );
		}
		// Conditions may already be satisfied (e.g. price already below target) – check soon.
		Khabar_Utils::queue( 'khabar_check_product', array( (int) $product->get_id() ), 10 );

		return rest_ensure_response(
			array(
				'success' => true,
				'ids'     => $ids,
				'message' => $s['success_message'],
				'waiting' => Khabar_Subscriptions::waiting_count( $product->get_id() ),
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
	private static function valid_attribute_value( $valid, $key, $value ) {
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

	/**
	 * Send and store a one time code.
	 *
	 * @param string $contact Contact.
	 * @param int[]  $ids     Subscription ids.
	 * @return true|WP_Error
	 */
	private static function send_code( $contact, $ids ) {
		if ( ! self::rate_ok( 'otp', 5 ) ) {
			return new WP_Error( 'khabar', __( 'تعداد درخواست کد زیاد است.', 'khabar' ) );
		}
		// Per-contact limits (independent of the client IP): 60s between codes, 5 codes per hour.
		$ckey = 'khabar_otpc_' . md5( strtolower( $contact ) );
		$meta = get_transient( $ckey );
		$meta = is_array( $meta ) ? $meta : array( 'n' => 0, 'last' => 0 );
		if ( time() - (int) $meta['last'] < 60 || $meta['n'] >= 5 ) {
			return new WP_Error( 'khabar', __( 'کد اخیراً ارسال شده است؛ کمی بعد دوباره تلاش کنید.', 'khabar' ) );
		}
		set_transient( $ckey, array( 'n' => $meta['n'] + 1, 'last' => time() ), HOUR_IN_SECONDS );

		$key   = 'khabar_otp_' . md5( strtolower( $contact ) );
		$state = get_transient( $key );
		$ids   = array_unique( array_merge( is_array( $state ) ? $state['ids'] : array(), $ids ) );
		$code  = (string) wp_rand( 100000, 999999 );
		set_transient(
			$key,
			array(
				'hash'  => wp_hash_password( $code ),
				'ids'   => $ids,
				// Re-sending a code must not reset the guess counter.
				'tries' => is_array( $state ) ? (int) $state['tries'] : 0,
			),
			10 * MINUTE_IN_SECONDS
		);
		return Khabar_Channels::send_code( $contact, $code );
	}

	/**
	 * Verify code and activate pending subscriptions.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function verify( WP_REST_Request $req ) {
		$contact = (string) $req->get_param( 'contact' );
		$contact = is_email( $contact ) ? sanitize_email( $contact ) : Khabar_Utils::normalize_phone( $contact );
		$code    = preg_replace( '/\D/', '', Khabar_Utils::latin_digits( (string) $req->get_param( 'code' ) ) );
		$key     = 'khabar_otp_' . md5( strtolower( $contact ) );
		$state   = get_transient( $key );
		if ( ! $contact || ! is_array( $state ) ) {
			return self::error( __( 'کد منقضی شده است؛ دوباره درخواست دهید.', 'khabar' ) );
		}
		if ( $state['tries'] >= 5 ) {
			return self::error( __( 'تعداد تلاش‌ها بیش از حد مجاز است.', 'khabar' ) );
		}
		if ( ! wp_check_password( $code, $state['hash'] ) ) {
			++$state['tries'];
			set_transient( $key, $state, 10 * MINUTE_IN_SECONDS );
			return self::error( __( 'کد وارد شده صحیح نیست.', 'khabar' ) );
		}
		delete_transient( $key );
		$pid = 0;
		foreach ( $state['ids'] as $id ) {
			$sub = Khabar_Subscriptions::get( $id );
			if ( $sub && 'pending' === $sub->status ) {
				Khabar_Subscriptions::update( $id, array( 'status' => 'active' ) );
				Khabar_Utils::webhook( 'subscribed', array( 'subscription' => self::export( $sub ) ) );
				$pid = $sub->product_id;
			}
		}
		if ( $pid ) {
			Khabar_Utils::queue( 'khabar_check_product', array( (int) $pid ), 10 );
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => Khabar_Settings::get( 'success_message' ),
			)
		);
	}

	/**
	 * Owner from request (logged in user or management token).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return array
	 */
	private static function owner( WP_REST_Request $req ) {
		return Khabar_Account::current_owner( (string) $req->get_param( 'token' ) );
	}

	/**
	 * Public representation.
	 *
	 * @param object $sub Subscription.
	 * @return array
	 */
	public static function export( $sub ) {
		if ( ! $sub ) {
			return array();
		}
		$product  = wc_get_product( $sub->variation_id ? $sub->variation_id : $sub->product_id );
		$statuses = Khabar_Subscriptions::statuses();
		return array(
			'id'           => $sub->id,
			'product_id'   => $sub->product_id,
			'variation_id' => $sub->variation_id,
			'product'      => $product ? $product->get_name() : '',
			'url'          => $product ? $product->get_permalink() : '',
			'attributes'   => Khabar_Utils::attributes_label( $sub->attributes ),
			'type'         => $sub->type,
			'conditions'   => Khabar_Subscriptions::conditions_label( $sub ),
			'price_below'  => $sub->price_below,
			'price_above'  => $sub->price_above,
			'min_qty'      => $sub->min_qty,
			'status'       => $sub->status,
			'status_label' => isset( $statuses[ $sub->status ] ) ? $statuses[ $sub->status ] : $sub->status,
			'email'        => $sub->email,
			'phone'        => $sub->phone,
			'channels'     => $sub->channel_list,
			'created_at'   => $sub->created_at,
		);
	}

	/**
	 * List my subscriptions.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_subscriptions( WP_REST_Request $req ) {
		$owner = self::owner( $req );
		if ( ! $owner ) {
			return self::error( __( 'دسترسی ندارید.', 'khabar' ), 401 );
		}
		return rest_ensure_response( array_map( array( __CLASS__, 'export' ), Khabar_Subscriptions::for_owner( $owner ) ) );
	}

	/**
	 * Update target price / contact / channels (Flow 4).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_subscription( WP_REST_Request $req ) {
		$sub   = Khabar_Subscriptions::get( (int) $req['id'] );
		$owner = self::owner( $req );
		if ( ! Khabar_Subscriptions::owned_by( $sub, $owner ) ) {
			return self::error( __( 'دسترسی ندارید.', 'khabar' ), 403 );
		}
		$data = array();
		foreach ( array( 'price_below', 'price_above' ) as $field ) {
			if ( null !== $req->get_param( $field ) && null !== $sub->$field ) {
				$value = Khabar_Utils::parse_price( $req->get_param( $field ) );
				if ( ! $value ) {
					return self::error( __( 'قیمت معتبر نیست.', 'khabar' ) );
				}
				$data[ $field ] = $value;
			}
		}
		if ( null !== $req->get_param( 'min_qty' ) && $sub->min_qty ) {
			$data['min_qty'] = max( 1, absint( Khabar_Utils::latin_digits( (string) $req->get_param( 'min_qty' ) ) ) );
		}
		if ( null !== $req->get_param( 'phone' ) ) {
			$raw   = trim( (string) $req->get_param( 'phone' ) );
			$phone = Khabar_Utils::normalize_phone( $raw );
			if ( '' !== $raw && ! $phone ) {
				return self::error( __( 'شماره موبایل معتبر نیست.', 'khabar' ) );
			}
			$data['phone'] = $phone;
		}
		if ( null !== $req->get_param( 'email' ) ) {
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			if ( '' !== (string) $req->get_param( 'email' ) && ! is_email( $email ) ) {
				return self::error( __( 'ایمیل معتبر نیست.', 'khabar' ) );
			}
			$data['email'] = $email;
		}
		if ( isset( $data['phone'] ) || isset( $data['email'] ) ) {
			$phone = isset( $data['phone'] ) ? $data['phone'] : $sub->phone;
			$email = isset( $data['email'] ) ? $data['email'] : $sub->email;
			if ( ! $phone && ! $email ) {
				return self::error( __( 'حداقل یک راه ارتباطی لازم است.', 'khabar' ) );
			}
		}
		if ( null !== $req->get_param( 'channels' ) ) {
			$data['channels'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $req->get_param( 'channels' ) ), (array) Khabar_Settings::get( 'channels_enabled' ) ) );
		}
		if ( 'notified' === $sub->status && ( isset( $data['price_below'] ) || isset( $data['price_above'] ) || isset( $data['min_qty'] ) ) ) {
			$data['status'] = 'active'; // Re-arm with the new target.
		}
		if ( $data ) {
			Khabar_Subscriptions::update( $sub->id, $data );
			Khabar_Utils::queue( 'khabar_check_product', array( (int) $sub->product_id ), 10 );
		}
		return rest_ensure_response( array( 'success' => true, 'subscription' => self::export( Khabar_Subscriptions::get( $sub->id ) ) ) );
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_subscription( WP_REST_Request $req ) {
		$sub = Khabar_Subscriptions::get( (int) $req['id'] );
		if ( ! Khabar_Subscriptions::owned_by( $sub, self::owner( $req ) ) ) {
			return self::error( __( 'دسترسی ندارید.', 'khabar' ), 403 );
		}
		Khabar_Subscriptions::update( $sub->id, array( 'status' => 'cancelled' ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * On-site notifications.
	 *
	 * @return WP_REST_Response
	 */
	public static function notifications() {
		$owner = Khabar_Utils::owner_key();
		return rest_ensure_response(
			array(
				'unread' => Khabar_Channel_Onsite::unread( $owner ),
				'items'  => Khabar_Channel_Onsite::latest( $owner ),
			)
		);
	}

	/**
	 * Mark read.
	 *
	 * @return WP_REST_Response
	 */
	public static function notifications_read() {
		Khabar_Channel_Onsite::mark_read( Khabar_Utils::owner_key() );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Save browser push subscription.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function push_subscribe( WP_REST_Request $req ) {
		if ( ! Khabar_Push::enabled() || ! self::rate_ok( 'push', 20 ) ) {
			return self::error( __( 'پوش فعال نیست.', 'khabar' ) );
		}
		$ok = Khabar_Push::save_subscription( Khabar_Utils::owner_key( true ), (array) $req->get_param( 'subscription' ) );
		return $ok ? rest_ensure_response( array( 'success' => true ) ) : self::error( __( 'اشتراک نامعتبر است.', 'khabar' ) );
	}

	/**
	 * Payload for the service worker.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function push_latest( WP_REST_Request $req ) {
		$data = Khabar_Push::latest_for_endpoint( (string) $req->get_param( 'endpoint' ) );
		return rest_ensure_response( $data ? $data : (object) array() );
	}
}
