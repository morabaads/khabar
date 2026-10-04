<?php
/**
 * GDPR personal data export / erase.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Privacy {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'خبرم کن', 'khabar' ), wpautop( __( 'وقتی برای اطلاع از موجود شدن یا تغییر قیمت یک محصول درخواست ثبت می‌کنید، نام، شماره موبایل، ایمیل و IP شما ذخیره می‌شود تا اعلان مربوطه برایتان ارسال شود. این اطلاعات پس از انقضای درخواست حذف یا غیرفعال می‌شود.', 'khabar' ) ) );
		}
	}

	/**
	 * Register exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['khabar'] = array(
			'exporter_friendly_name' => __( 'درخواست‌های خبرم کن', 'khabar' ),
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
		$erasers['khabar'] = array(
			'eraser_friendly_name' => __( 'درخواست‌های خبرم کن', 'khabar' ),
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
			$phone            = Khabar_Utils::normalize_phone( get_user_meta( $user->ID, 'billing_phone', true ) );
			if ( $phone ) {
				$owner['phone'] = $phone;
			}
		}
		return $owner;
	}

	/**
	 * Export.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		$items = array();
		foreach ( Khabar_Subscriptions::for_owner( self::owner( $email ), true ) as $sub ) {
			$data    = Khabar_Rest::export( $sub );
			$items[] = array(
				'group_id'    => 'khabar',
				'group_label' => __( 'درخواست‌های خبرم کن', 'khabar' ),
				'item_id'     => 'khabar-' . $sub->id,
				'data'        => array(
					array( 'name' => __( 'محصول', 'khabar' ), 'value' => $data['product'] ),
					array( 'name' => __( 'شرایط', 'khabar' ), 'value' => $data['conditions'] ),
					array( 'name' => __( 'موبایل', 'khabar' ), 'value' => $sub->phone ),
					array( 'name' => __( 'ایمیل', 'khabar' ), 'value' => $sub->email ),
					array( 'name' => __( 'IP', 'khabar' ), 'value' => $sub->ip ),
					array( 'name' => __( 'تاریخ', 'khabar' ), 'value' => $sub->created_at ),
				),
			);
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
		$subs = Khabar_Subscriptions::for_owner( self::owner( $email ), true );
		Khabar_Subscriptions::delete( wp_list_pluck( $subs, 'id' ) );
		return array(
			'items_removed'  => count( $subs ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
