<?php
/**
 * Plugin Name:       یادم بیار – اعلان موجودی و قیمت ووکامرس
 * Plugin URI:        https://github.com/morabaads/yadambiar
 * Description:       اعلان هوشمند موجود شدن دقیق تنوع محصول، کاهش/افزایش قیمت و قوانین ترکیبی؛ با پیامک، ایمیل، اعلان داخل سایت، پوش نوتیفیکیشن، تلگرام و بله.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yadam Biar
 * Text Domain:       yadambiar
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:   9.9
 * License:           GPL-2.0-or-later
 *
 * @package YadamBiar
 */

defined( 'ABSPATH' ) || exit;

define( 'YADAMBIAR_VERSION', '2.0.0' );
define( 'YADAMBIAR_DB_VERSION', '2.0.0' );
define( 'YADAMBIAR_FILE', __FILE__ );
define( 'YADAMBIAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'YADAMBIAR_URL', plugin_dir_url( __FILE__ ) );

require_once YADAMBIAR_DIR . 'src/Core/Autoloader.php';
\YadamBiar\Core\Autoloader::register( YADAMBIAR_DIR . 'src' );

register_activation_hook( __FILE__, array( \YadamBiar\Core\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \YadamBiar\Core\Installer::class, 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( \YadamBiar\Core\Plugin::class, 'instance' ), 20 );
