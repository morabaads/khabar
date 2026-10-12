<?php
/**
 * GDPR personal data export / erase.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Core;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\ChannelManager;
use YadamBiar\Contracts\Module;
use YadamBiar\Subscriptions\SubscriptionPresenter;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Phone;

/**
 * GDPR personal data export / erase.
 */
final class Privacy implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'یادم بیار', 'yadambiar' ), wpautop( __( 'وقتی برای اطلاع از موجود شدن یا تغییر قیمت یک محصول درخواست ثبت می‌کنید، نام، شماره موبایل، ایمیل و IP شما ذخیره می‌شود تا اعلان مربوطه برایتان ارسال شود. این اطلاعات پس از انقضای درخواست حذف یا غیرفعال می‌شود.', 'yadambiar' ) ) );
		}
	}

	/**
	 * Register exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['yadambiar'] = array(
			'exporter_friendly_name' => __( 'درخواست‌های یادم بیار', 'yadambiar' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['yadambiar'] = array(
			'eraser_friendly_name' => __( 'درخواست‌های یادم بیار', 'yadambiar' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Owner for an email.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	private static function owner( $email ) {
		$user  = get_user_by( 'email', $email );
		$owner = array( 'email' => $email );
		if ( $user ) {
			$owner['user_id'] = $user->ID;
			$phone            = Phone::normalize_phone( get_user_meta( $user->ID, 'billing_phone', true ) );
			if ( $phone ) {
				$owner['phone'] = $phone;
			}
		}
		return $owner;
	}

	/**
	 * Owner keys (user / guest) of the subscriptions of an email.
	 *
	 * @param string $email Email.
	 * @return string[]
	 */
	private static function owner_keys( $email ) {
		$keys = array();
		foreach ( SubscriptionRepository::for_owner( self::owner( $email ), true ) as $sub ) {
			$key = ChannelManager::owner_of( $sub );
			if ( $key ) {
				$keys[ $key ] = $key;
			}
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$keys[ 'u:' . $user->ID ] = 'u:' . $user->ID;
		}
		return array_values( $keys );
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		$items = array();
		foreach ( SubscriptionRepository::for_owner( self::owner( $email ), true ) as $sub ) {
			$data    = SubscriptionPresenter::export( $sub );
			$items[] = array(
				'group_id'    => 'yadambiar',
				'group_label' => __( 'درخواست‌های یادم بیار', 'yadambiar' ),
				'item_id'     => 'yadambiar-' . $sub->id,
				'data'        => array(
					array( 'name' => __( 'محصول', 'yadambiar' ), 'value' => $data['product'] ),
					array( 'name' => __( 'شرایط', 'yadambiar' ), 'value' => $data['conditions'] ),
					array( 'name' => __( 'موبایل', 'yadambiar' ), 'value' => $sub->phone ),
					array( 'name' => __( 'ایمیل', 'yadambiar' ), 'value' => $sub->email ),
					array( 'name' => __( 'IP', 'yadambiar' ), 'value' => $sub->ip ),
					array( 'name' => __( 'تاریخ', 'yadambiar' ), 'value' => $sub->created_at ),
				),
			);
		}
		global $wpdb;
		$keys = self::owner_keys( $email );
		if ( $keys ) {
			$in    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			$notes = Installer::table( 'notifications' );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, created_at FROM {$notes} WHERE owner_key IN ({$in})", $keys ) ); // phpcs:ignore
			foreach ( (array) $rows as $row ) {
				$items[] = array(
					'group_id'    => 'yadambiar-notifications',
					'group_label' => __( 'اعلان‌های یادم بیار', 'yadambiar' ),
					'item_id'     => 'yadambiar-note-' . $row->id,
					'data'        => array(
						array( 'name' => __( 'عنوان', 'yadambiar' ), 'value' => $row->title ),
						array( 'name' => __( 'تاریخ', 'yadambiar' ), 'value' => $row->created_at ),
					),
				);
			}
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		global $wpdb;
		$subs = SubscriptionRepository::for_owner( self::owner( $email ), true );
		$keys = self::owner_keys( $email );
		$ids  = array_map( 'intval', wp_list_pluck( $subs, 'id' ) );
		if ( $ids ) {
			$wpdb->query( 'DELETE FROM ' . Installer::table( 'log' ) . ' WHERE subscription_id IN (' . implode( ',', $ids ) . ')' ); // phpcs:ignore
		}
		if ( $keys ) {
			$in = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			foreach ( array( 'notifications', 'push', 'messenger' ) as $name ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Installer::table( $name ) . " WHERE owner_key IN ({$in})", $keys ) ); // phpcs:ignore
			}
		}
		SubscriptionRepository::delete( wp_list_pluck( $subs, 'id' ) );
		return array(
			'items_removed'  => count( $subs ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
