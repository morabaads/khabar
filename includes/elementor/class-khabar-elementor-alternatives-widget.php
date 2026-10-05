<?php
/**
 * Elementor widget: similar / alternative products block.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class Khabar_Elementor_Alternatives_Widget extends Khabar_Elementor_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'khabar-alternatives';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'محصولات مشابه (خبرم کن)', 'khabar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-products';
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'khabar' ) ) );
		$this->product_controls();
		$this->add_control( 'title', array( 'label' => __( 'عنوان', 'khabar' ), 'type' => Controls_Manager::TEXT, 'placeholder' => __( 'تا موجود شدن، این محصولات مشابه موجودند', 'khabar' ) ) );
		$this->add_control( 'count', array( 'label' => __( 'تعداد محصولات', 'khabar' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 12, 'default' => (int) Khabar_Settings::get( 'alt_page_count', 4 ) ) );
		$this->add_control(
			'always',
			array(
				'label'        => __( 'نمایش حتی برای محصول موجود', 'khabar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'در حالت عادی فقط برای محصول ناموجود نمایش داده می‌شود.', 'khabar' ),
			)
		);
		$this->add_responsive_control(
			'card_w',
			array(
				'label'      => __( 'عرض هر کارت', 'khabar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 110, 'max' => 320 ) ),
				'selectors'  => array( '{{WRAPPER}} .khabar-alts' => '--khabar-alt-w: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'img_h',
			array(
				'label'      => __( 'ارتفاع تصویر', 'khabar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 60, 'max' => 300 ) ),
				'selectors'  => array( '{{WRAPPER}} .khabar-alts-img' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'style_box', array( 'label' => __( 'کادر و کارت‌ها', 'khabar' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'box_bg', array( 'label' => __( 'پس‌زمینه کادر', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alts' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'card_bg', array( 'label' => __( 'پس‌زمینه کارت', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alt' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'card_hover', array( 'label' => __( 'رنگ حاشیه در هاور', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alt:hover, {{WRAPPER}} .khabar-alt:focus-visible' => 'border-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .khabar-alt' ) );
		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'گردی گوشه کارت', 'khabar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( '{{WRAPPER}} .khabar-alt' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .khabar-alt' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_text', array( 'label' => __( 'متن‌ها', 'khabar' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'title_color', array( 'label' => __( 'رنگ عنوان بخش', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alts-title' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'title_typo', 'selector' => '{{WRAPPER}} .khabar-alts-title' ) );
		$this->add_control( 'name_color', array( 'label' => __( 'رنگ نام محصول', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alts-name' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'name_typo', 'selector' => '{{WRAPPER}} .khabar-alts-name' ) );
		$this->add_control( 'price_color', array( 'label' => __( 'رنگ قیمت', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alts-price' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'price_typo', 'selector' => '{{WRAPPER}} .khabar-alts-price' ) );
		$this->add_control( 'accent', array( 'label' => __( 'رنگ نوار کنار عنوان', 'khabar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .khabar-alts-title::before' => 'background: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		$product = $this->product();
		if ( ! $product ) {
			$this->placeholder( __( 'محصولات مشابه: در صفحه محصول نمایش داده می‌شود یا شناسه محصول را وارد کنید.', 'khabar' ) );
			return;
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		$s    = $this->get_settings_for_display();
		$edit = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$html = Khabar_Alternatives::render_block(
			$product,
			array(
				'always' => ! empty( $s['always'] ) || $edit,
				'count'  => isset( $s['count'] ) ? (int) $s['count'] : 0,
				'title'  => isset( $s['title'] ) ? $s['title'] : '',
			)
		);
		if ( '' === $html ) {
			$this->placeholder( __( 'محصول مشابهی برای نمایش پیدا نشد (یا محصول موجود است).', 'khabar' ) );
			return;
		}
		Khabar_Frontend::enqueue();
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
