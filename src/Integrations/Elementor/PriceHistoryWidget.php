<?php
/**
 * Elementor widget: price history chart.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Commerce\PriceHistory;
use YadamBiar\Settings\Settings;

use Elementor\Controls_Manager;

class PriceHistoryWidget extends BaseWidget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'yadambiar-price-history';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'تاریخچه قیمت', 'yadam-biar' );
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
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'yadam-biar' ) ) );
		$this->product_controls();
		$this->add_control(
			'days',
			array(
				'label'   => __( 'بازه (روز)', 'yadam-biar' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 7,
				'max'     => 365,
				'default' => (int) Settings::get( 'price_history_days', 90 ),
			)
		);
		$this->add_control(
			'dark',
			array(
				'label'        => __( 'حالت تیره', 'yadam-biar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			array(
				'label' => __( 'نمودار', 'yadam-biar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'series', array( 'label' => __( 'رنگ خط', 'yadam-biar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-ph' => '--yadambiar-ph-series: {{VALUE}};' ) ) );
		$this->add_control( 'surface', array( 'label' => __( 'پس‌زمینه', 'yadam-biar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-ph' => '--yadambiar-ph-surface: {{VALUE}};' ) ) );
		$this->add_control( 'text', array( 'label' => __( 'رنگ متن', 'yadam-biar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-ph' => '--yadambiar-ph-text: {{VALUE}};' ) ) );
		$this->add_control( 'grid', array( 'label' => __( 'رنگ خطوط راهنما و حاشیه', 'yadam-biar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-ph' => '--yadambiar-ph-grid: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'radius',
			array(
				'label'      => __( 'گردی گوشه', 'yadam-biar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .yadambiar-ph' => 'border-radius: {{SIZE}}{{UNIT}};' ),
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
			$this->placeholder( __( 'تاریخچه قیمت: در صفحه محصول نمایش داده می‌شود یا شناسه محصول را وارد کنید.', 'yadam-biar' ) );
			return;
		}
		$s    = $this->get_settings_for_display();
		$html = PriceHistory::render( $product, isset( $s['days'] ) ? (int) $s['days'] : 0 );
		if ( ! empty( $s['dark'] ) ) {
			$html = preg_replace( '/class="yadambiar-ph"/', 'class="yadambiar-ph yadambiar-dark"', $html, 1 );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
