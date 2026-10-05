<?php
/**
 * Plugin Name:       خبرم کن (Khabar) – اعلان موجودی و قیمت ووکامرس
 * Plugin URI:        https://github.com/morabaads/khabar
 * Description:       اعلان هوشمند موجود شدن دقیق تنوع محصول، کاهش/افزایش قیمت و قوانین ترکیبی؛ با پیامک، ایمیل، اعلان داخل سایت، پوش نوتیفیکیشن و واتساپ.
 * Version:           1.1.8
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Khabar
 * Text Domain:       khabar
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:   9.9
 * License:           GPL-2.0-or-later
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

define( 'KHABAR_VERSION', '1.1.8' );
define( 'KHABAR_DB_VERSION', '1.1.0' );
define( 'KHABAR_FILE', __FILE__ );
define( 'KHABAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHABAR_URL', plugin_dir_url( __FILE__ ) );

require_once KHABAR_DIR . 'includes/class-khabar-autoloader.php';
Khabar_Autoloader::register();

register_activation_hook( __FILE__, array( 'Khabar_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Khabar_Install', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Main plugin instance.
 *
 * @return Khabar_Plugin
 */
function khabar() {
	return Khabar_Plugin::instance();
}

add_action( 'plugins_loaded', 'khabar', 20 );
