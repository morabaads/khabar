<?php
/**
 * Elementor widget: "my alerts" panel.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Frontend\Account;

class MyAlertsWidget extends BaseWidget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'yadambiar-my-alerts';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'یادآوری‌های من', 'yadambiar' );
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
				'label' => __( 'ظاهر', 'yadambiar' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'card_bg', array( 'label' => __( 'پس‌زمینه کارت‌ها', 'yadambiar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-sub' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'accent', array( 'label' => __( 'رنگ اصلی', 'yadambiar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-panel' => '--yadambiar-accent: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		echo Account::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
