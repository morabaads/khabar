<?php
/**
 * Elementor integration: "یادم بیار" widget category and widgets.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Frontend\ProductWidget;

class ElementorIntegration {

	/**
	 * Hooks (only when Elementor is active).
	 */
	public static function init() {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register' ) );
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
			'yadam-biar',
			array(
				'title' => __( 'یادم بیار', 'yadam-biar' ),
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
	public static function register( $manager ) {
		foreach ( self::widgets() as $class ) {
			$manager->register( new $class() );
		}
	}

	/**
	 * Assets inside the editor preview iframe.
	 */
	public static function preview_assets() {
		ProductWidget::enqueue();
	}

	/**
	 * Re-initialise widgets when Elementor (re)renders them in the editor.
	 */
	public static function editor_hooks() {
		if ( ! wp_script_is( 'yadam-biar', 'registered' ) ) {
			ProductWidget::register();
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
