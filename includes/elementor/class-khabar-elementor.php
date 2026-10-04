<?php
/**
 * Elementor integration: "خبرم کن" widget category and widgets.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Elementor {

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
			'khabar',
			array(
				'title' => __( 'خبرم کن', 'khabar' ),
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
			'Khabar_Elementor_Notify_Widget',
			'Khabar_Elementor_Price_History_Widget',
			'Khabar_Elementor_My_Alerts_Widget',
			'Khabar_Elementor_Bell_Widget',
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
		Khabar_Frontend::enqueue();
	}

	/**
	 * Re-initialise widgets when Elementor (re)renders them in the editor.
	 */
	public static function editor_hooks() {
		if ( ! wp_script_is( 'khabar', 'registered' ) ) {
			Khabar_Frontend::register();
		}
		$names = array( 'khabar-notify', 'khabar-price-history', 'khabar-my-alerts', 'khabar-bell' );
		$js    = 'jQuery(window).on("elementor/frontend/init",function(){' . implode(
			'',
			array_map(
				function ( $n ) {
					return 'elementorFrontend.hooks.addAction("frontend/element_ready/' . $n . '.default",function($s){window.Khabar&&window.Khabar.init($s);});';
				},
				$names
			)
		) . '});';
		wp_add_inline_script( 'elementor-frontend', $js );
	}
}
