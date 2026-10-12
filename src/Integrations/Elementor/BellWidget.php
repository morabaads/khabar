<?php
/**
 * Elementor widget: on-site notification bell.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Frontend\Bell;

class BellWidget extends BaseWidget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'yadambiar-bell';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'زنگوله اعلان‌ها', 'yadambiar' );
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
				'label' => __( 'ظاهر', 'yadambiar' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'bg', array( 'label' => __( 'پس‌زمینه', 'yadambiar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-bell-btn' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'badge', array( 'label' => __( 'رنگ شمارنده', 'yadambiar' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-bell-count' => 'background: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		$html = Bell::bell_shortcode();
		if ( ! $html ) {
			$this->placeholder( __( 'کانال «اعلان داخل سایت» در تنظیمات غیرفعال است.', 'yadambiar' ) );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
