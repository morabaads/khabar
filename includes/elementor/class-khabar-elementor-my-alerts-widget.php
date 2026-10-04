<?php
/**
 * Elementor widget: "my alerts" panel.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Elementor_My_Alerts_Widget extends Khabar_Elementor_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'khabar-my-alerts';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'خبرم کن‌های من', 'khabar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-bullet-list';
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
		$this->add_control( 'card_bg', array( 'label' => __( 'پس‌زمینه کارت‌ها', 'khabar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-sub' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'accent', array( 'label' => __( 'رنگ اصلی', 'khabar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-panel' => '--khabar-accent: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		echo Khabar_Account::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
