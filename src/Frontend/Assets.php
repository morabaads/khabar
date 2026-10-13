<?php
/**
 * Storefront assets: styles, script and its localized settings, loaded only where a widget renders.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Frontend;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\PushChannel;
use YadamBiar\Channels\Push\VapidKeys;
use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Appearance;
use YadamBiar\Settings\Settings;
/**
 * Storefront assets: styles, script and its localized settings, loaded only where a widget renders.
 */
final class Assets implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Script settings were attached (once per request).
	 *
	 * @var bool
	 */
	private static $prepared = false;

	/**
	 * Register the handles (cheap: runs on every front-end page).
	 */
	public static function register_assets() {
		wp_register_style( 'yadambiar', YADAMBIAR_URL . 'assets/css/frontend.css', array(), YADAMBIAR_VERSION );
		wp_register_script( 'yadambiar', YADAMBIAR_URL . 'assets/js/frontend.js', array( 'jquery' ), YADAMBIAR_VERSION, true );
	}

	/**
	 * Load the assets where a widget renders; colors and script settings are computed only then.
	 */
	public static function enqueue() {
		if ( ! wp_script_is( 'yadambiar', 'registered' ) ) {
			self::register_assets();
		}
		if ( ! self::$prepared ) {
			self::$prepared = true;
			wp_add_inline_style( 'yadambiar', ':root{' . Appearance::css_vars() . '}' );
			wp_localize_script( 'yadambiar', 'YadamBiarData', self::script_data() );
		}
		wp_enqueue_style( 'yadambiar' );
		wp_enqueue_script( 'yadambiar' );
	}

	/**
	 * Settings and strings for frontend.js.
	 *
	 * @return array
	 */
	private static function script_data() {
		$keys = PushChannel::enabled() ? VapidKeys::keys() : null;
		return array(
			'rest'      => esc_url_raw( rest_url( 'yadambiar/v1/' ) ),
			'nonce'     => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'annotate'  => (bool) Settings::get( 'annotate_variations' ),
			'pushKey'   => $keys ? $keys['public'] : '',
			'sw'        => add_query_arg( 'yadambiar_sw', 1, home_url( '/' ) ),
			'decimals'  => wc_get_price_decimals(),
			'i18n'      => array(
				'outOfStock' => __( 'ناموجود 🔔', 'yadambiar' ),
				'error'      => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'yadambiar' ),
				'confirmDel' => __( 'این درخواست حذف شود؟', 'yadambiar' ),
				'saved'      => __( 'ذخیره شد.', 'yadambiar' ),
				'pushDenied' => __( 'اجازه نمایش اعلان داده نشد.', 'yadambiar' ),
				'chooseOne'  => __( 'حداقل یک شرط را انتخاب کنید.', 'yadambiar' ),
				'any'        => __( 'هر کدام', 'yadambiar' ),
				'noNotes'    => __( 'اعلانی ندارید.', 'yadambiar' ),
				'notesTitle'  => __( 'اعلان‌های شما', 'yadambiar' ),
				/* translators: %s number of new notifications */
				'notesNew'    => __( '%s اعلان جدید', 'yadambiar' ),
				'notesAll'    => __( 'همه اعلان‌ها را دیده‌اید', 'yadambiar' ),
				'new'         => __( 'جدید', 'yadambiar' ),
				'viewProduct' => __( 'مشاهده محصول', 'yadambiar' ),
				'close'       => __( 'بستن', 'yadambiar' ),
				'connect'    => __( 'دریافت در %s', 'yadambiar' ),
				'connected'  => __( '%s متصل است ✓', 'yadambiar' ),
				'connectTip' => __( 'روی دکمه بزنید و در ربات «Start» را بزنید تا اعلان‌ها را آنجا هم بگیرید.', 'yadambiar' ),
				'networks'   => array(
					'telegram' => __( 'تلگرام', 'yadambiar' ),
					'bale'     => __( 'بله', 'yadambiar' ),
				),
			),
		);
	}
}
