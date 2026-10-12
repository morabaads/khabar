<?php
/**
 * Elementor integration: "یادم بیار" widget category and widgets.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\Module;
use YadamBiar\Frontend\Assets;

class ElementorIntegration implements Module {

	/**
	 * Hooks (only when Elementor is active).
	 */
	public function register() {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'preview_assets' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( __CLASS__, 'editor_hooks' ) );
	}

	/**
	 * Widget category.
	 *
	 * @param \Elementor\Elements_Manager $manager Manager.
	 */
	public static function category( $manager ) {
		$manager->add_category(
			'yadambiar',
			array(
				'title' => __( 'یادم بیار', 'yadambiar' ),
				'icon'  => 'eicon-bell',
			)
		);
	}

	/**
	 * Widget classes.
	 *
	 * @return string[]
	 */
	public static function widgets() {
		return array(
			NotifyWidget::class,
			AlternativesWidget::class,
			PriceHistoryWidget::class,
			MyAlertsWidget::class,
			BellWidget::class,
		);
	}

	/**
	 * Register widgets.
	 *
	 * @param \Elementor\Widgets_Manager $manager Manager.
	 */
	public static function register_widgets( $manager ) {
		foreach ( self::widgets() as $class ) {
			$manager->register( new $class() );
		}
	}

	/**
	 * Assets inside the editor preview iframe.
	 */
	public static function preview_assets() {
		Assets::enqueue();
	}

	/**
	 * Re-initialise widgets when Elementor (re)renders them in the editor.
	 */
	public static function editor_hooks() {
		if ( ! wp_script_is( 'yadambiar', 'registered' ) ) {
			Assets::register_assets();
		}
		$names = array( 'yadambiar-notify', 'yadambiar-price-history', 'yadambiar-my-alerts', 'yadambiar-bell' );
		$js    = 'jQuery(window).on("elementor/frontend/init",function(){' . implode(
			'',
			array_map(
				function ( $n ) {
					return 'elementorFrontend.hooks.addAction("frontend/element_ready/' . $n . '.default",function($s){window.YadamBiar&&window.YadamBiar.init($s);});';
				},
				$names
			)
		) . '});';
		wp_add_inline_script( 'elementor-frontend', $js );
	}
}
