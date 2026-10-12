<?php
/**
 * Shared base for Yadam Biar Elementor widgets.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

abstract class BaseWidget extends \Elementor\Widget_Base {

	/**
	 * Category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'yadam-biar', 'woocommerce-elements' );
	}

	/**
	 * Script dependencies.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'yadam-biar' );
	}

	/**
	 * Style dependencies.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'yadam-biar' );
	}

	/**
	 * Keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'yadam-biar', 'یادم بیار', 'stock', 'notify', 'woocommerce', 'price' );
	}

	/**
	 * Product source controls.
	 */
	protected function product_controls() {
		$this->add_control(
			'source',
			array(
				'label'   => __( 'محصول', 'yadam-biar' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current' => __( 'محصول همین صفحه', 'yadam-biar' ),
					'custom'  => __( 'انتخاب با شناسه', 'yadam-biar' ),
				),
			)
		);
		$this->add_control(
			'product_id',
			array(
				'label'     => __( 'شناسه محصول', 'yadam-biar' ),
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
			$product = $product instanceof \WC_Product ? $product : wc_get_product( get_the_ID() );
		}
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Popup / card colour controls writing CSS variables (applied through a style attribute so they
	 * also reach the popup, which is moved to <body> and therefore outside the widget wrapper).
	 *
	 * @param string $prefix Control id prefix.
	 */
	protected function color_var_controls( $prefix = 'v_' ) {
		$fields = array(
			'accent'    => __( 'رنگ اصلی', 'yadam-biar' ),
			'on_accent' => __( 'رنگ متن روی دکمه', 'yadam-biar' ),
			'ink'       => __( 'رنگ متن اصلی', 'yadam-biar' ),
			'muted'     => __( 'رنگ متن کم‌رنگ', 'yadam-biar' ),
			'bg'        => __( 'پس‌زمینه', 'yadam-biar' ),
			'soft'      => __( 'پس‌زمینه ملایم', 'yadam-biar' ),
			'line'      => __( 'رنگ حاشیه‌ها', 'yadam-biar' ),
		);
		foreach ( $fields as $key => $label ) {
			$this->add_control( $prefix . $key, array( 'label' => $label, 'type' => Controls_Manager::COLOR ) );
		}
		$this->add_control(
			$prefix . 'radius',
			array(
				'label'      => __( 'گردی گوشه‌ها', 'yadam-biar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			)
		);
	}

	/**
	 * Build the "--yadambiar-*:value;" string from colour_var_controls() settings.
	 *
	 * @param array  $settings Widget settings.
	 * @param string $prefix   Prefix.
	 * @return string
	 */
	protected function color_vars( $settings, $prefix = 'v_' ) {
		$map = array(
			'accent'    => '--yadambiar-accent',
			'on_accent' => '--yadambiar-on-accent',
			'ink'       => '--yadambiar-ink',
			'muted'     => '--yadambiar-muted',
			'bg'        => '--yadambiar-bg',
			'soft'      => '--yadambiar-soft',
			'line'      => '--yadambiar-line',
		);
		$out = '';
		foreach ( $map as $key => $var ) {
			$val = isset( $settings[ $prefix . $key ] ) ? trim( (string) $settings[ $prefix . $key ] ) : '';
			if ( '' !== $val && preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%\/]+\)|[a-z]+)$/i', $val ) ) {
				$out .= $var . ':' . $val . ';';
			}
		}
		if ( ! empty( $settings[ $prefix . 'radius' ]['size'] ) || ( isset( $settings[ $prefix . 'radius' ]['size'] ) && '0' === (string) $settings[ $prefix . 'radius' ]['size'] ) ) {
			$out .= '--yadambiar-radius:' . absint( $settings[ $prefix . 'radius' ]['size'] ) . 'px;';
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
			echo '<div class="yadambiar-el-placeholder" style="padding:16px;border:1px dashed #c3c4c7;border-radius:8px;text-align:center;direction:rtl">' . esc_html( $text ) . '</div>';
		}
	}
}
