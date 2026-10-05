<?php
/**
 * Subscription repository.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Subscriptions {

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'pending'   => __( 'در انتظار تایید', 'khabar' ),
			'active'    => __( 'در انتظار', 'khabar' ),
			'notified'  => __( 'اطلاع داده شد', 'khabar' ),
			'purchased' => __( 'خرید شد', 'khabar' ),
			'cancelled' => __( 'لغو شده', 'khabar' ),
			'expired'   => __( 'منقضی', 'khabar' ),
		);
	}

	/**
	 * Type labels.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'stock'        => __( 'موجود شدن', 'khabar' ),
			'price_drop'   => __( 'کاهش قیمت', 'khabar' ),
			'price_rise'   => __( 'افزایش قیمت', 'khabar' ),
			'price_change' => __( 'تغییر قیمت', 'khabar' ),
			'combo'        => __( 'قانون ترکیبی', 'khabar' ),
		);
	}

	/**
	 * Derive the type from conditions.
	 *
	 * @param array $c Conditions.
	 * @return string
	 */
	public static function derive_type( $c ) {
		$set = array_filter(
			array(
				'stock'        => ! empty( $c['in_stock'] ),
				'price_drop'   => isset( $c['price_below'] ) && null !== $c['price_below'],
				'price_rise'   => isset( $c['price_above'] ) && null !== $c['price_above'],
				'price_change' => ! empty( $c['price_change'] ),
				'min_qty'      => ! empty( $c['min_qty'] ),
			)
		);
		// A single simple condition keeps its own type; anything combined (or a quantity threshold) is a rule.
		if ( 1 === count( $set ) && ! isset( $set['min_qty'] ) ) {
			return key( $set );
		}
		return 'combo';
	}

	/**
	 * Human readable conditions.
	 *
	 * @param object $sub Row.
	 * @return string
	 */
	public static function conditions_label( $sub ) {
		$parts = array();
		if ( $sub->in_stock ) {
			$parts[] = __( 'موجود شدن', 'khabar' );
		}
		if ( null !== $sub->price_below ) {
			/* translators: %s price */
			$parts[] = sprintf( __( 'قیمت ≤ %s', 'khabar' ), Khabar_Utils::price_text( $sub->price_below ) );
		}
		if ( null !== $sub->price_above ) {
			/* translators: %s price */
			$parts[] = sprintf( __( 'قیمت ≥ %s', 'khabar' ), Khabar_Utils::price_text( $sub->price_above ) );
		}
		if ( $sub->price_change ) {
			$parts[] = __( 'هر تغییر قیمت', 'khabar' );
		}
		if ( $sub->min_qty ) {
			/* translators: %d qty */
			$parts[] = sprintf( __( 'موجودی ≥ %d عدد', 'khabar' ), $sub->min_qty );
		}
		return implode( ' + ', $parts );
	}

	/**
	 * Normalize a row (decode JSON, cast numbers).
	 *
	 * @param object|null $row Row.
	 * @return object|null
	 */
	public static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row->id           = (int) $row->id;
		$row->product_id   = (int) $row->product_id;
		$row->variation_id = (int) $row->variation_id;
		$row->user_id      = (int) $row->user_id;
		$row->in_stock     = (int) $row->in_stock;
		$row->price_change = (int) $row->price_change;
		$row->min_qty      = null === $row->min_qty ? null : (int) $row->min_qty;
		$row->price_below  = null === $row->price_below ? null : (float) $row->price_below;
		$row->price_above  = null === $row->price_above ? null : (float) $row->price_above;
		$row->base_price   = null === $row->base_price ? null : (float) $row->base_price;
		$attrs             = $row->attributes ? json_decode( $row->attributes, true ) : array();
		$row->attributes   = is_array( $attrs ) ? $attrs : array();
		$row->channel_list = array_filter( explode( ',', (string) $row->channels ) );
		return $row;
	}

	/**
	 * Get by id.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get by token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		$token = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token ) );
		if ( strlen( $token ) < 20 ) {
			return null;
		}
		$table = Khabar_Install::table( 'subscriptions' );
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create (or merge into an existing identical) subscription.
	 *
	 * @param array $data Data.
	 * @return int|WP_Error Subscription id.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );

		$data = wp_parse_args(
			$data,
			array(
				'product_id'   => 0,
				'variation_id' => 0,
				'attributes'   => array(),
				'in_stock'     => 0,
				'price_below'  => null,
				'price_above'  => null,
				'min_qty'      => null,
				'price_change' => 0,
				'user_id'      => 0,
				'owner_key'    => '',
				'name'         => '',
				'email'        => '',
				'phone'        => '',
				'channels'     => array(),
				'status'       => 'active',
				'base_price'   => null,
			)
		);

		ksort( $data['attributes'] );
		$attributes = $data['attributes'] ? wp_json_encode( $data['attributes'], JSON_UNESCAPED_UNICODE ) : '';
		$type       = self::derive_type( $data );

		// Duplicate guard: same contact + same target + same conditions => refresh the existing row.
		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND variation_id = %d AND attributes = %s AND email = %s AND phone = %s AND user_id = %d AND type = %s AND status IN ('active','pending')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$data['product_id'],
				$data['variation_id'],
				$attributes,
				$data['email'],
				$data['phone'],
				$data['user_id'],
				$type
			)
		);

		$days = (int) Khabar_Settings::get( 'expiry_days', 0 );
		$row  = array(
			'product_id'   => (int) $data['product_id'],
			'variation_id' => (int) $data['variation_id'],
			'attributes'   => $attributes,
			'type'         => $type,
			'in_stock'     => $data['in_stock'] ? 1 : 0,
			'price_below'  => $data['price_below'],
			'price_above'  => $data['price_above'],
			'min_qty'      => $data['min_qty'] ? (int) $data['min_qty'] : null,
			'price_change' => $data['price_change'] ? 1 : 0,
			'user_id'      => (int) $data['user_id'],
			'owner_key'    => (string) $data['owner_key'],
			'name'         => mb_substr( (string) $data['name'], 0, 100 ),
			'email'        => (string) $data['email'],
			'phone'        => (string) $data['phone'],
			'channels'     => implode( ',', (array) $data['channels'] ),
			'status'       => $data['status'],
			'base_price'   => $data['base_price'],
			'updated_at'   => Khabar_Utils::now(),
			'expires_at'   => $days ? Khabar_Utils::now( $days * DAY_IN_SECONDS ) : null,
		);

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => $existing->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $existing->id;
		}

		$row['token']      = strtolower( wp_generate_password( 40, false, false ) );
		$row['ip']         = Khabar_Utils::ip();
		$row['created_at'] = Khabar_Utils::now();

		if ( false === $wpdb->insert( $table, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'khabar_db', __( 'خطا در ذخیره درخواست.', 'khabar' ) );
		}
		$id = (int) $wpdb->insert_id;
		wp_cache_delete( 'khabar_wc_' . (int) $data['product_id'] . '_0', 'khabar' );
		wp_cache_delete( 'khabar_wc_' . (int) $data['product_id'] . '_' . (int) $data['variation_id'], 'khabar' );
		do_action( 'khabar_subscription_created', $id, $row );
		return $id;
	}

	/**
	 * Update a row.
	 *
	 * @param int   $id   ID.
	 * @param array $data Columns.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		if ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
			$data['attributes'] = $data['attributes'] ? wp_json_encode( $data['attributes'], JSON_UNESCAPED_UNICODE ) : '';
		}
		if ( isset( $data['channels'] ) && is_array( $data['channels'] ) ) {
			$data['channels'] = implode( ',', $data['channels'] );
		}
		$data['updated_at'] = Khabar_Utils::now();
		return false !== $wpdb->update( Khabar_Install::table( 'subscriptions' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Delete rows.
	 *
	 * @param int|int[] $ids IDs.
	 */
	public static function delete( $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( ! $ids ) {
			return;
		}
		$table = Khabar_Install::table( 'subscriptions' );
		$wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
	}

	/**
	 * Active subscriptions for a (parent) product, oldest first (FIFO fairness).
	 *
	 * @param int $product_id Parent product id.
	 * @return object[]
	 */
	public static function active_for_product( $product_id ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d AND status = 'active' ORDER BY id ASC", $product_id ) ); // phpcs:ignore
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Subscriptions belonging to an owner.
	 *
	 * @param array $owner user_id | owner_key | email | phone.
	 * @param bool  $include_closed Include cancelled/expired.
	 * @return object[]
	 */
	public static function for_owner( $owner, $include_closed = false ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		$where = array();
		$args  = array();
		if ( ! empty( $owner['sub_id'] ) ) {
			$where[] = 'id = %d';
			$args[]  = (int) $owner['sub_id'];
		}
		if ( ! empty( $owner['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$args[]  = (int) $owner['user_id'];
		}
		if ( ! empty( $owner['owner_key'] ) ) {
			$where[] = 'owner_key = %s';
			$args[]  = $owner['owner_key'];
		}
		if ( ! empty( $owner['email'] ) ) {
			$where[] = 'email = %s';
			$args[]  = $owner['email'];
		}
		if ( ! empty( $owner['phone'] ) ) {
			$where[] = 'phone = %s';
			$args[]  = $owner['phone'];
		}
		if ( ! $where ) {
			return array();
		}
		$status = $include_closed ? '' : " AND status NOT IN ('cancelled','expired')";
		$sql    = "SELECT * FROM {$table} WHERE (" . implode( ' OR ', $where ) . "){$status} ORDER BY id DESC LIMIT 200";
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Does the given owner own the subscription.
	 *
	 * @param object $sub   Row.
	 * @param array  $owner Owner descriptor.
	 * @return bool
	 */
	public static function owned_by( $sub, $owner ) {
		if ( ! $sub ) {
			return false;
		}
		if ( ! empty( $owner['sub_id'] ) && (int) $owner['sub_id'] === $sub->id ) {
			return true;
		}
		if ( ! empty( $owner['user_id'] ) && (int) $owner['user_id'] === $sub->user_id ) {
			return true;
		}
		if ( ! empty( $owner['owner_key'] ) && $owner['owner_key'] === $sub->owner_key ) {
			return true;
		}
		if ( ! empty( $owner['email'] ) && $owner['email'] === $sub->email ) {
			return true;
		}
		if ( ! empty( $owner['phone'] ) && $owner['phone'] === $sub->phone ) {
			return true;
		}
		return false;
	}

	/**
	 * Number of distinct waiting people for a product / variation.
	 *
	 * @param int      $product_id   Parent id.
	 * @param int|null $variation_id Variation.
	 * @return int
	 */
	public static function waiting_count( $product_id, $variation_id = null, $fresh = false ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		$key   = 'khabar_wc_' . $product_id . '_' . (int) $variation_id;
		$count = $fresh ? false : wp_cache_get( $key, 'khabar' );
		if ( false !== $count ) {
			return (int) $count;
		}
		$sql = "SELECT COUNT(DISTINCT CONCAT(user_id,'|',email,'|',phone)) FROM {$table} WHERE product_id = %d AND status = 'active'";
		if ( null !== $variation_id ) {
			$sql .= $wpdb->prepare( ' AND variation_id = %d', $variation_id );
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $product_id ) ); // phpcs:ignore
		wp_cache_set( $key, $count, 'khabar', 300 );
		return $count;
	}

	/**
	 * Count active subscriptions for a contact (abuse guard).
	 *
	 * @param string $email Email.
	 * @param string $phone Phone.
	 * @return int
	 */
	public static function count_for_contact( $email, $phone ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status IN ('active','pending') AND ( (email <> '' AND email = %s) OR (phone <> '' AND phone = %s) )", $email, $phone ) ); // phpcs:ignore
	}
}
