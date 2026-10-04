<?php
/**
 * Elementor widget: price history chart.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class Khabar_Elementor_Price_History_Widget extends Khabar_Elementor_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'khabar-price-history';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'تاریخچه قیمت', 'khabar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-skill-bar';
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'khabar' ) ) );
		$this->product_controls();
		$this->add_control(
			'days',
			array(
				'label'   => __( 'بازه (روز)', 'khabar' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 7,
				'max'     => 365,
				'default' => (int) Khabar_Settings::get( 'price_history_days', 90 ),
			)
		);
		$this->add_control(
			'dark',
			array(
				'label'        => __( 'حالت تیره', 'khabar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'نمودار', 'khabar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'series', array( 'label' => __( 'رنگ خط', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-ph' => '--khabar-ph-series: {{VALUE}};' ) ) );
		$this->add_control( 'surface', array( 'label' => __( 'پس‌زمینه', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-ph' => '--khabar-ph-surface: {{VALUE}};' ) ) );
		$this->add_control( 'text', array( 'label' => __( 'رنگ متن', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-ph' => '--khabar-ph-text: {{VALUE}};' ) ) );
		$this->add_control( 'grid', array( 'label' => __( 'رنگ خطوط راهنما و حاشیه', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-ph' => '--khabar-ph-grid: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'radius',
			array(
				'label'      => __( 'گردی گوشه', 'khabar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .khabar-ph' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		$product = $this->product();
		if ( ! $product ) {
			$this->placeholder( __( 'تاریخچه قیمت: در صفحه محصول نمایش داده می‌شود یا شناسه محصول را وارد کنید.', 'khabar' ) );
			return;
		}
		$s    = $this->get_settings_for_display();
		$html = Khabar_Price_History::render( $product, isset( $s['days'] ) ? (int) $s['days'] : 0 );
		if ( ! empty( $s['dark'] ) ) {
			$html = preg_replace( '/class="khabar-ph"/', 'class="khabar-ph khabar-dark"', $html, 1 );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
