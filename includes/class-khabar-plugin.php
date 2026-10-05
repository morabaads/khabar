<?php
/**
 * Plugin bootstrap.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

final class Khabar_Plugin {

	/**
	 * Singleton.
	 *
	 * @var Khabar_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Khabar_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		load_plugin_textdomain( 'khabar', false, dirname( plugin_basename( KHABAR_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
			return;
		}

		Khabar_Install::maybe_upgrade();

		Khabar_Watcher::init();
		Khabar_Dispatcher::init();
		Khabar_Reservation::init();
		Khabar_Rest::init();
		Khabar_Frontend::init();
		Khabar_Account::init();
		Khabar_Tracking::init();
		Khabar_Push::init();
		Khabar_Privacy::init();
		Khabar_Cron::init();
		Khabar_Messenger::init();
		Khabar_Coupons::init();
		Khabar_Alternatives::init();
		Khabar_Price_History::init();
		Khabar_Elementor::init();

		if ( is_admin() ) {
			Khabar_Admin::init();
			Khabar_Product_Metabox::init();
			Khabar_Forecast_Page::init();
		}

		add_filter( 'plugin_action_links_' . plugin_basename( KHABAR_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Notice when WooCommerce is not active.
	 */
	public function missing_wc_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه «خبرم کن» برای کار به ووکامرس نیاز دارد.', 'khabar' ) . '</p></div>';
	}

	/**
	 * Settings link on plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=khabar&view=settings' ) ) . '">' . esc_html__( 'تنظیمات', 'khabar' ) . '</a>' );
		return $links;
	}
}
