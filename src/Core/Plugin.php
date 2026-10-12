<?php
/**
 * Plugin bootstrap (composition root).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Core;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Actions\ChannelActions;
use YadamBiar\Admin\Actions\DeliveryActions;
use YadamBiar\Admin\Actions\ExportActions;
use YadamBiar\Admin\Actions\SettingsActions;
use YadamBiar\Admin\AdminApp;
use YadamBiar\Admin\AdminUrl;
use YadamBiar\Admin\ProductMetabox;
use YadamBiar\Channels\Push\ServiceWorker;
use YadamBiar\Commerce\Alternatives;
use YadamBiar\Commerce\Coupons;
use YadamBiar\Commerce\PriceHistory;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Commerce\Tracking;
use YadamBiar\Compat\LegacyShortcodes;
use YadamBiar\Contracts\Module;
use YadamBiar\Frontend\Account;
use YadamBiar\Frontend\Assets;
use YadamBiar\Frontend\Bell;
use YadamBiar\Frontend\ProductWidget;
use YadamBiar\Http\RestRouter;
use YadamBiar\Integrations\Elementor\ElementorIntegration;
use YadamBiar\Notifications\Dispatcher;
use YadamBiar\Notifications\StockWatcher;

/**
 * Composition root: decides which modules run and boots them.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Booted modules.
	 *
	 * @var Module[]
	 */
	private $modules = array();

	/**
	 * Get instance (boots on first call).
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Modules that run everywhere.
	 *
	 * @return string[] Class names implementing Module.
	 */
	private function modules() {
		$modules = array(
			StockWatcher::class,
			Dispatcher::class,
			Reservation::class,
			RestRouter::class,
			Assets::class,
			ProductWidget::class,
			Bell::class,
			Account::class,
			Tracking::class,
			ServiceWorker::class,
			Privacy::class,
			Cron::class,
			Coupons::class,
			Alternatives::class,
			PriceHistory::class,
			ElementorIntegration::class,
			LegacyShortcodes::class,
		);
		if ( is_admin() ) {
			array_push( $modules, AdminApp::class, ProductMetabox::class, SettingsActions::class, DeliveryActions::class, ExportActions::class, ChannelActions::class );
		}
		return (array) apply_filters( 'yadambiar_modules', $modules );
	}

	/**
	 * Boot.
	 */
	private function boot() {
		load_plugin_textdomain( 'yadambiar', false, dirname( plugin_basename( YADAMBIAR_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
			return;
		}

		Installer::maybe_upgrade();

		foreach ( $this->modules() as $class ) {
			$module = new $class();
			if ( $module instanceof Module ) {
				$module->register();
				$this->modules[ $class ] = $module;
			}
		}

		add_filter( 'plugin_action_links_' . plugin_basename( YADAMBIAR_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * A booted module instance.
	 *
	 * @param string $class Class name.
	 * @return Module|null
	 */
	public function module( $class ) {
		return isset( $this->modules[ $class ] ) ? $this->modules[ $class ] : null;
	}

	/**
	 * Notice when WooCommerce is not active.
	 */
	public function missing_wc_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه «یادم بیار» برای کار به ووکامرس نیاز دارد.', 'yadambiar' ) . '</p></div>';
	}

	/**
	 * Settings link on plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( AdminUrl::view( 'settings' ) ) . '">' . esc_html__( 'تنظیمات', 'yadambiar' ) . '</a>' );
		return $links;
	}
}
