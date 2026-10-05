<?php
/**
 * Shared base for Khabar Elementor widgets.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

abstract class Khabar_Elementor_Base extends \Elementor\Widget_Base {

	/**
	 * Category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'khabar', 'woocommerce-elements' );
	}

	/**
	 * Script dependencies.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'khabar' );
	}

	/**
	 * Style dependencies.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'khabar' );
	}

	/**
	 * Keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'khabar', 'خبرم کن', 'stock', 'notify', 'woocommerce', 'price' );
	}

	/**
	 * Product source controls.
	 */
	protected function product_controls() {
		$this->add_control(
			'source',
			array(
				'label'   => __( 'محصول', 'khabar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current' => __( 'محصول همین صفحه', 'khabar' ),
					'custom'  => __( 'انتخاب با شناسه', 'khabar' ),
				),
			)
		);
		$this->add_control(
			'product_id',
			array(
				'label'     => __( 'شناسه محصول', 'khabar' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 1,
				'condition' => array( 'source' => 'custom' ),
			)
		);
	}

	/**
	 * Resolve the product.
	 *
	 * @return WC_Product|null
	 */
	protected function product() {
		$settings = $this->get_settings_for_display();
		if ( isset( $settings['source'] ) && 'custom' === $settings['source'] && ! empty( $settings['product_id'] ) ) {
			$product = wc_get_product( absint( $settings['product_id'] ) );
		} else {
			global $product;
			$product = $product instanceof WC_Product ? $product : wc_get_product( get_the_ID() );
		}
		return $product instanceof WC_Product ? $product : null;
	}

	/**
	 * Popup / card colour controls writing CSS variables (applied through a style attribute so they
	 * also reach the popup, which is moved to <body> and therefore outside the widget wrapper).
	 *
	 * @param string $prefix Control id prefix.
	 */
	protected function color_var_controls( $prefix = 'v_' ) {
		$fields = array(
			'accent'    => __( 'رنگ اصلی', 'khabar' ),
			'on_accent' => __( 'رنگ متن روی دکمه', 'khabar' ),
			'ink'       => __( 'رنگ متن اصلی', 'khabar' ),
			'muted'     => __( 'رنگ متن کم‌رنگ', 'khabar' ),
			'bg'        => __( 'پس‌زمینه', 'khabar' ),
			'soft'      => __( 'پس‌زمینه ملایم', 'khabar' ),
			'line'      => __( 'رنگ حاشیه‌ها', 'khabar' ),
		);
		foreach ( $fields as $key => $label ) {
			$this->add_control( $prefix . $key, array( 'label' => $label, 'type' => Controls_Manager::COLOR ) );
		}
		$this->add_control(
			$prefix . 'radius',
			array(
				'label'      => __( 'گردی گوشه‌ها', 'khabar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			)
		);
	}

	/**
	 * Build the "--khabar-*:value;" string from colour_var_controls() settings.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $prefix   Prefix.
	 * @return string
	 */
	protected function color_vars( $settings, $prefix = 'v_' ) {
		$map = array(
			'accent'    => '--khabar-accent',
			'on_accent' => '--khabar-on-accent',
			'ink'       => '--khabar-ink',
			'muted'     => '--khabar-muted',
			'bg'        => '--khabar-bg',
			'soft'      => '--khabar-soft',
			'line'      => '--khabar-line',
		);
		$out = '';
		foreach ( $map as $key => $var ) {
			$val = isset( $settings[ $prefix . $key ] ) ? trim( (string) $settings[ $prefix . $key ] ) : '';
			if ( '' !== $val && preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%\/]+\)|[a-z]+)$/i', $val ) ) {
				$out .= $var . ':' . $val . ';';
			}
		}
		if ( ! empty( $settings[ $prefix . 'radius' ]['size'] ) || ( isset( $settings[ $prefix . 'radius' ]['size'] ) && '0' === (string) $settings[ $prefix . 'radius' ]['size'] ) ) {
			$out .= '--khabar-radius:' . absint( $settings[ $prefix . 'radius' ]['size'] ) . 'px;';
		}
		return $out;
	}

	/**
	 * Editor-only hint when there is no product context.
	 *
	 * @param string $text Text.
	 */
	protected function placeholder( $text ) {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			echo '<div class="khabar-el-placeholder" style="padding:16px;border:1px dashed #c3c4c7;border-radius:8px;text-align:center;direction:rtl">' . esc_html( $text ) . '</div>';
		}
	}
}
