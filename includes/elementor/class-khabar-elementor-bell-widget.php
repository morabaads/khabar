<?php
/**
 * Elementor widget: on-site notification bell.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Elementor_Bell_Widget extends Khabar_Elementor_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'khabar-bell';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'زنگوله اعلان‌ها', 'khabar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-notification';
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'ظاهر', 'khabar' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'bg', array( 'label' => __( 'پس‌زمینه', 'khabar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-bell-btn' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'badge', array( 'label' => __( 'رنگ شمارنده', 'khabar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-bell-count' => 'background: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		$html = Khabar_Frontend::bell_shortcode();
		if ( ! $html ) {
			$this->placeholder( __( 'کانال «اعلان داخل سایت» در تنظیمات غیرفعال است.', 'khabar' ) );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
