<?php
/**
 * Customer panel: "خبرم کن‌های من" (Flow 4) – My Account endpoint + shortcode for guests.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Account {

	const ENDPOINT = 'khabar-alerts';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );
		add_shortcode( 'khabar_my_alerts', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_login', array( __CLASS__, 'claim_guest' ), 10, 2 );
	}

	/**
	 * Endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		if ( get_option( 'khabar_flush_rewrite' ) ) {
			delete_option( 'khabar_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * WC query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Menu item.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function menu( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new[ self::ENDPOINT ] = __( 'خبرم کن‌های من', 'khabar' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'خبرم کن‌های من', 'khabar' );
		}
		return $new;
	}

	/**
	 * Endpoint title.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'خبرم کن‌های من', 'khabar' );
	}

	/**
	 * Management URL for a subscription (used in messages).
	 *
	 * @param object $sub Subscription.
	 * @return string
	 */
	public static function manage_url( $sub ) {
		$page = (int) get_option( 'khabar_manage_page_id' );
		$base = $page ? get_permalink( $page ) : home_url( '/' );
		return add_query_arg( 'khabar_token', $sub->token, $base );
	}

	/**
	 * Resolve the current owner (logged in user, management token, or guest cookie).
	 *
	 * @param string $token Optional management token.
	 * @return array
	 */
	public static function current_owner( $token = '' ) {
		$owner = array();
		if ( $token ) {
			$sub = Khabar_Subscriptions::get_by_token( $token );
			if ( $sub ) {
				$owner['email'] = $sub->email;
				$owner['phone'] = $sub->phone;
				if ( $sub->owner_key ) {
					$owner['owner_key'] = $sub->owner_key;
				}
			}
		}
		$uid = get_current_user_id();
		if ( $uid ) {
			$owner['user_id']  = $uid;
			$owner['email']    = isset( $owner['email'] ) && $owner['email'] ? $owner['email'] : wp_get_current_user()->user_email;
			$owner['owner_key'] = isset( $owner['owner_key'] ) ? $owner['owner_key'] : 'u:' . $uid;
		} elseif ( ! isset( $owner['owner_key'] ) ) {
			$key = Khabar_Utils::owner_key();
			if ( $key ) {
				$owner['owner_key'] = $key;
			}
		}
		return array_filter( $owner );
	}

	/**
	 * When a guest logs in, attach the subscriptions made from this browser.
	 *
	 * @param string  $login Login.
	 * @param WP_User $user  User.
	 */
	public static function claim_guest( $login, $user ) {
		global $wpdb;
		if ( empty( $_COOKIE[ Khabar_Utils::GUEST_COOKIE ] ) ) {
			return;
		}
		$key = 'g:' . preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_COOKIE[ Khabar_Utils::GUEST_COOKIE ] ) );
		$wpdb->update( Khabar_Install::table( 'subscriptions' ), array( 'user_id' => $user->ID ), array( 'owner_key' => $key, 'user_id' => 0 ) ); // phpcs:ignore
		$wpdb->update( Khabar_Install::table( 'notifications' ), array( 'owner_key' => 'u:' . $user->ID ), array( 'owner_key' => $key ) ); // phpcs:ignore
		$wpdb->update( Khabar_Install::table( 'push' ), array( 'owner_key' => 'u:' . $user->ID ), array( 'owner_key' => $key ) ); // phpcs:ignore
	}

	/**
	 * My Account endpoint.
	 */
	public static function render_endpoint() {
		echo self::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Shortcode output.
	 *
	 * @return string
	 */
	public static function shortcode() {
		$token = isset( $_GET['khabar_token'] ) ? sanitize_text_field( wp_unslash( $_GET['khabar_token'] ) ) : ''; // phpcs:ignore
		$owner = self::current_owner( $token );
		Khabar_Frontend::enqueue();

		ob_start();
		if ( ! $owner ) {
			echo '<div class="khabar-panel"><p>' . esc_html__( 'برای مشاهده درخواست‌ها وارد حساب کاربری شوید یا از لینک ارسال‌شده در پیام استفاده کنید.', 'khabar' ) . '</p></div>';
			return ob_get_clean();
		}
		$subs     = Khabar_Subscriptions::for_owner( $owner );
		$notes    = Khabar_Channel_Onsite::latest( isset( $owner['owner_key'] ) ? $owner['owner_key'] : '', 10 );
		$channels = array_intersect_key( Khabar_Settings::channels(), array_flip( (array) Khabar_Settings::get( 'channels_enabled' ) ) );
		include Khabar_Utils::template( 'my-alerts.php' );
		return ob_get_clean();
	}
}
