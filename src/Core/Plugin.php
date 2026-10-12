<?php
/**
 * Plugin bootstrap.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Core;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminApp;
use YadamBiar\Admin\Pages\ForecastPage;
use YadamBiar\Admin\ProductMetabox;
use YadamBiar\Channels\Messenger\MessengerChannel;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Commerce\Alternatives;
use YadamBiar\Commerce\Coupons;
use YadamBiar\Commerce\PriceHistory;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Commerce\Tracking;
use YadamBiar\Frontend\Account;
use YadamBiar\Frontend\ProductWidget;
use YadamBiar\Http\RestController;
use YadamBiar\Integrations\Elementor\ElementorIntegration;
use YadamBiar\Notifications\Dispatcher;
use YadamBiar\Notifications\StockWatcher;

final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Plugin
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
		load_plugin_textdomain( 'yadam-biar', false, dirname( plugin_basename( YADAMBIAR_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
			return;
		}

		Installer::maybe_upgrade();

		StockWatcher::init();
		Dispatcher::init();
		Reservation::init();
		RestController::init();
		ProductWidget::init();
		Account::init();
		Tracking::init();
		PushChannel::init();
		Privacy::init();
		Cron::init();
		MessengerChannel::init();
		Coupons::init();
		Alternatives::init();
		PriceHistory::init();
		ElementorIntegration::init();

		if ( is_admin() ) {
			AdminApp::init();
			ProductMetabox::init();
			ForecastPage::init();
		}

		add_filter( 'plugin_action_links_' . plugin_basename( YADAMBIAR_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Notice when WooCommerce is not active.
	 */
	public function missing_wc_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه «یادم بیار» برای کار به ووکامرس نیاز دارد.', 'yadam-biar' ) . '</p></div>';
	}

	/**
	 * Settings link on plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=yadambiar&view=settings' ) ) . '">' . esc_html__( 'تنظیمات', 'yadam-biar' ) . '</a>' );
		return $links;
	}
}
