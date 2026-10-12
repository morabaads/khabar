<?php
/**
 * Elementor widget: notify button + expectation form.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Frontend\ProductWidget;
use YadamBiar\Settings\Settings;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Elementor widget: notify button + expectation form.
 */
final class NotifyWidget extends BaseWidget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'yadambiar-notify';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'دکمه یادم بیار', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-bell';
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'yadambiar' ) ) );
		$this->product_controls();
		$this->add_control(
			'button_text_stock',
			array(
				'label'       => __( 'متن دکمه موجود شدن', 'yadambiar' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => Settings::get( 'button_text_stock' ),
			)
		);
		$this->add_control(
			'button_text_price',
			array(
				'label'       => __( 'متن دکمه هشدار قیمت', 'yadambiar' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => Settings::get( 'button_text_price' ),
			)
		);
		$this->add_control(
			'show_price_alert',
			array(
				'label'        => __( 'نمایش هشدار قیمت برای کالای موجود', 'yadambiar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => Settings::get( 'show_price_alert' ) ? 'yes' : '',
			)
		);
		$this->add_control(
			'show_extras',
			array(
				'label'        => __( 'نمایش جایگزین‌ها و نمودار (طبق تنظیمات افزونه)', 'yadambiar' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'چینش', 'yadambiar' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'right'  => array( 'title' => __( 'راست', 'yadambiar' ), 'icon' => 'eicon-text-align-right' ),
					'center' => array( 'title' => __( 'وسط', 'yadambiar' ), 'icon' => 'eicon-text-align-center' ),
					'left'   => array( 'title' => __( 'چپ', 'yadambiar' ), 'icon' => 'eicon-text-align-left' ),
				),
				'selectors' => array( '{{WRAPPER}} .yadambiar-cta' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		// Button style.
		$this->start_controls_section(
			'style_button',
			array(
				'label' => __( 'دکمه', 'yadambiar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$btn = '{{WRAPPER}} .yadambiar-cta-stock .yadambiar-open';
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'btn_typo', 'selector' => $btn ) );
		$this->add_responsive_control(
			'btn_width',
			array(
				'label'      => __( 'عرض', 'yadambiar' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 800 ) ),
				'selectors'  => array( $btn => 'width: {{SIZE}}{{UNIT}}; max-width: none;' ),
			)
		);
		$this->start_controls_tabs( 'btn_tabs' );
		$this->start_controls_tab( 'btn_normal', array( 'label' => __( 'عادی', 'yadambiar' ) ) );
		$this->add_control( 'btn_color', array( 'label' => __( 'رنگ متن', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $btn => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'btn_bg', array( 'label' => __( 'پس‌زمینه', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $btn => 'background: {{VALUE}};' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'btn_hover', array( 'label' => __( 'هاور', 'yadambiar' ) ) );
		$this->add_control( 'btn_color_h', array( 'label' => __( 'رنگ متن', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $btn . ':hover' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'btn_bg_h', array( 'label' => __( 'پس‌زمینه', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $btn . ':hover' => 'background: {{VALUE}}; filter: none;' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'btn_border', 'selector' => $btn, 'separator' => 'before' ) );
		$this->add_responsive_control(
			'btn_radius',
			array(
				'label'      => __( 'گردی گوشه', 'yadambiar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array( $btn => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'btn_padding',
			array(
				'label'      => __( 'فاصله داخلی', 'yadambiar' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $btn => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'btn_shadow', 'selector' => $btn ) );
		$this->end_controls_section();

		// Price link + form accent.
		$this->start_controls_section(
			'style_misc',
			array(
				'label' => __( 'لینک قیمت و فرم', 'yadambiar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'link_color', array( 'label' => __( 'رنگ لینک هشدار قیمت', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-cta-price .yadambiar-price-btn' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'link_typo', 'selector' => '{{WRAPPER}} .yadambiar-cta-price .yadambiar-price-btn' ) );
		$this->add_control( 'accent', array( 'label' => __( 'رنگ اصلی فرم', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar' => '--yadambiar-accent: {{VALUE}};' ) ) );
		$this->add_control( 'count_color', array( 'label' => __( 'رنگ «نفر منتظر»', 'yadambiar' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .yadambiar-waiting' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		// Popup + card colours (CSS variables).
		$this->start_controls_section(
			'style_popup',
			array(
				'label' => __( 'رنگ‌بندی پاپ‌آپ', 'yadambiar' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'popup_note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__( 'خالی بگذارید تا رنگ‌های تنظیمات افزونه (ظاهر و رنگ‌ها) استفاده شود.', 'yadambiar' ), 'content_classes' => 'elementor-descriptor' ) );
		$this->color_var_controls( 'v_' );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		$product = $this->product();
		if ( ! $product ) {
			$this->placeholder( __( 'یادم بیار: در صفحه محصول نمایش داده می‌شود یا شناسه محصول را وارد کنید.', 'yadambiar' ) );
			return;
		}
		$s = $this->get_settings_for_display();
		echo ProductWidget::render( // phpcs:ignore WordPress.Security.EscapeOutput
			$product,
			array(
				'force'             => true,
				'uid'               => 'yadambiar-el-' . $this->get_id(),
				'button_text_stock' => isset( $s['button_text_stock'] ) ? $s['button_text_stock'] : '',
				'button_text_price' => isset( $s['button_text_price'] ) ? $s['button_text_price'] : '',
				'show_price_alert'  => ! empty( $s['show_price_alert'] ) ? 1 : 0,
				'hide_extras'       => empty( $s['show_extras'] ),
				'vars'              => $this->color_vars( $s, 'v_' ),
			)
		);
	}
}
