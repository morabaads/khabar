<?php
/**
 * Product page UI: smart "notify me" button, expectation form, price alerts (Flow 1 & 3).
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Frontend {

	/**
	 * Rendered product ids.
	 *
	 * @var int[]
	 */
	private static $rendered = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		$positions = array(
			'after_price' => 15,
			'after_cart'  => 35,
			'after_meta'  => 45,
		);
		$pos       = Khabar_Settings::get( 'position', 'after_cart' );
		if ( isset( $positions[ $pos ] ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_current' ), $positions[ $pos ] );
		}
		// Block themes do not run the classic summary hooks: attach to the equivalent blocks.
		add_filter( 'render_block', array( __CLASS__, 'render_block' ), 10, 3 );
		add_shortcode( 'khabar', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'khabar_bell', array( __CLASS__, 'bell_shortcode' ) );
		add_action( 'wp_footer', array( __CLASS__, 'floating_bell' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register assets.
	 */
	public static function register() {
		wp_register_style( 'khabar', KHABAR_URL . 'assets/css/frontend.css', array(), KHABAR_VERSION );
		wp_register_script( 'khabar', KHABAR_URL . 'assets/js/frontend.js', array( 'jquery' ), KHABAR_VERSION, true );
		$keys = Khabar_Push::enabled() ? Khabar_Push::keys() : null;
		wp_localize_script(
			'khabar',
			'KhabarData',
			array(
				'rest'      => esc_url_raw( rest_url( 'khabar/v1/' ) ),
				'nonce'     => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'annotate'  => (bool) Khabar_Settings::get( 'annotate_variations' ),
				'pushKey'   => $keys ? $keys['public'] : '',
				'sw'        => add_query_arg( 'khabar_sw', 1, home_url( '/' ) ),
				'decimals'  => wc_get_price_decimals(),
				'i18n'      => array(
					'outOfStock' => __( 'ناموجود 🔔', 'khabar' ),
					'error'      => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'khabar' ),
					'confirmDel' => __( 'این درخواست حذف شود؟', 'khabar' ),
					'saved'      => __( 'ذخیره شد.', 'khabar' ),
					'pushDenied' => __( 'اجازه نمایش اعلان داده نشد.', 'khabar' ),
					'chooseOne'  => __( 'حداقل یک شرط را انتخاب کنید.', 'khabar' ),
					'any'        => __( 'هر کدام', 'khabar' ),
					'noNotes'    => __( 'اعلانی ندارید.', 'khabar' ),
					'connect'    => __( 'دریافت در %s', 'khabar' ),
					'connected'  => __( '%s متصل است ✓', 'khabar' ),
					'connectTip' => __( 'روی دکمه بزنید و در ربات «Start» را بزنید تا اعلان‌ها را آنجا هم بگیرید.', 'khabar' ),
					'networks'   => array(
						'telegram' => __( 'تلگرام', 'khabar' ),
						'bale'     => __( 'بله', 'khabar' ),
					),
				),
			)
		);
	}

	/**
	 * Enqueue assets (called when a widget renders).
	 */
	public static function enqueue() {
		if ( ! wp_script_is( 'khabar', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'khabar' );
		wp_enqueue_script( 'khabar' );
	}

	/**
	 * Render for the global product.
	 */
	public static function render_current() {
		global $product;
		// WooCommerce's block-template compatibility layer replays classic hooks from inside
		// render_block at approximate positions; our own render_block handler places it precisely.
		if ( doing_filter( 'render_block' ) && wp_is_block_theme() ) {
			return;
		}
		if ( $product instanceof WC_Product ) {
			echo self::render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Append the widget after the matching WooCommerce block on single product pages.
	 *
	 * @param string   $content  Block HTML.
	 * @param array    $block    Parsed block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function render_block( $content, $block, $instance = null ) {
		$map = array(
			'after_cart'  => array( 'woocommerce/add-to-cart-form', 'woocommerce/add-to-cart-with-options' ),
			'after_price' => array( 'woocommerce/product-price' ),
			'after_meta'  => array( 'woocommerce/product-meta' ),
		);
		$pos = Khabar_Settings::get( 'position', 'after_cart' );
		if ( empty( $block['blockName'] ) || ! isset( $map[ $pos ] ) || ! in_array( $block['blockName'], $map[ $pos ], true ) || ! is_singular( 'product' ) ) {
			return $content;
		}
		$id = ( $instance && ! empty( $instance->context['postId'] ) ) ? (int) $instance->context['postId'] : get_the_ID();
		if ( (int) get_queried_object_id() !== $id ) {
			return $content; // Related products / loops on the same page.
		}
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return $content;
		}
		ob_start();
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		Khabar_Reservation::notice();
		return $content . ob_get_clean() . self::render( $product );
	}

	/**
	 * [khabar product_id="123"]
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		global $product;
		$atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'khabar' );
		$p    = $atts['product_id'] ? wc_get_product( absint( $atts['product_id'] ) ) : $product;
		return $p instanceof WC_Product ? self::render( $p ) : '';
	}

	/**
	 * Waiting count per variation.
	 *
	 * @param int $product_id Parent id.
	 * @return array variation_id => count
	 */
	private static function waiting_by_variation( $product_id ) {
		global $wpdb;
		$table = Khabar_Install::table( 'subscriptions' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT variation_id, COUNT(*) AS c FROM {$table} WHERE product_id = %d AND status = 'active' GROUP BY variation_id", $product_id ) ); // phpcs:ignore
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->variation_id ] = (int) $row->c;
		}
		return $out;
	}

	/**
	 * Attribute options for the expectation form.
	 *
	 * @param WC_Product_Variable $product Product.
	 * @return array key => [label, options[slug => name]]
	 */
	private static function attribute_options( $product ) {
		$out = array();
		foreach ( $product->get_variation_attributes() as $name => $values ) {
			$options = array();
			if ( taxonomy_exists( $name ) ) {
				foreach ( wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'all' ) ) as $term ) {
					if ( in_array( $term->slug, $values, true ) ) {
						$options[ $term->slug ] = $term->name;
					}
				}
			} else {
				foreach ( $values as $v ) {
					$options[ $v ] = $v;
				}
			}
			$out[ 'attribute_' . sanitize_title( $name ) ] = array(
				'label'   => wc_attribute_label( $name, $product ),
				'options' => $options,
			);
		}
		return $out;
	}

	/**
	 * Render the widget.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function render( $product, $args = array() ) {
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		if ( ! $product || ( isset( self::$rendered[ $product->get_id() ] ) && empty( $args['force'] ) ) ) {
			return '';
		}
		if ( ! $product->is_type( array( 'simple', 'variable' ) ) && ! apply_filters( 'khabar_supports_product', false, $product ) ) {
			return '';
		}
		self::$rendered[ $product->get_id() ] = true;
		self::enqueue();

		$s        = array_merge( Khabar_Settings::all(), array_filter( (array) $args, function ( $v ) { return null !== $v && '' !== $v; } ) );
		$types    = (array) $s['enabled_types'];
		$variable = $product->is_type( 'variable' );
		$in_stock = $variable ? true : $product->is_in_stock();
		$any_oos  = false;
		if ( $variable ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && ! $child->is_in_stock() ) {
					$any_oos = true;
					break;
				}
			}
			$in_stock = $product->is_in_stock();
		}

		$waiting    = self::waiting_by_variation( $product->get_id() );
		$total      = array_sum( $waiting );
		$show_count = $s['show_waiting_count'] && $total >= (int) $s['waiting_count_min'];
		$user       = wp_get_current_user();
		$prefill    = array(
			'name'  => $user->ID ? ( $user->first_name ? $user->first_name : $user->display_name ) : '',
			'email' => $user->ID ? $user->user_email : '',
			'phone' => $user->ID ? get_user_meta( $user->ID, 'billing_phone', true ) : '',
		);
		$channels   = array_intersect_key( Khabar_Settings::channels(), array_flip( (array) $s['channels_enabled'] ) );
		if ( isset( $channels['push'] ) && ! Khabar_Push::enabled() ) {
			unset( $channels['push'] );
		}
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			if ( isset( $channels[ $network ] ) && ! Khabar_Messenger::personal_ready( $network ) ) {
				unset( $channels[ $network ] );
			}
		}
		$messengers = array_values( array_intersect( array( 'telegram', 'bale' ), array_keys( $channels ) ) );
		$attributes = $variable ? self::attribute_options( $product ) : array();
		$price      = '' === $product->get_price() ? '' : (float) $product->get_price();

		$khabar_uid = ! empty( $args['uid'] ) ? sanitize_html_class( $args['uid'] ) : 'khabar-' . $product->get_id();
		ob_start();
		include Khabar_Utils::template( 'product-widget.php' );
		$html = ob_get_clean();
		if ( empty( $args['hide_extras'] ) ) {
			$html .= Khabar_Alternatives::render_block( $product );
			if ( Khabar_Settings::get( 'price_history_enabled', 1 ) && 'widget' === Khabar_Settings::get( 'price_history_display' ) ) {
				$html .= Khabar_Price_History::render( $product );
			}
		}
		return $html;
	}

	/**
	 * [khabar_bell]
	 *
	 * @return string
	 */
	public static function bell_shortcode() {
		if ( ! Khabar_Settings::has( 'channels_enabled', 'onsite' ) ) {
			return '';
		}
		self::enqueue();
		$owner  = Khabar_Utils::owner_key();
		$unread = Khabar_Channel_Onsite::unread( $owner );
		ob_start();
		?>
		<div class="khabar-bell" data-khabar-bell>
			<button type="button" class="khabar-bell-btn" aria-label="<?php esc_attr_e( 'اعلان‌ها', 'khabar' ); ?>">🔔<?php if ( $unread ) : ?><span class="khabar-bell-count"><?php echo esc_html( number_format_i18n( $unread ) ); ?></span><?php endif; ?></button>
			<div class="khabar-bell-list" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Floating bell for visitors who have notifications.
	 */
	public static function floating_bell() {
		if ( ! Khabar_Settings::get( 'floating_bell' ) || ! Khabar_Settings::has( 'channels_enabled', 'onsite' ) ) {
			return;
		}
		$owner = Khabar_Utils::owner_key();
		if ( ! $owner || ! Khabar_Channel_Onsite::latest( $owner, 1 ) ) {
			return;
		}
		echo '<div class="khabar-floating">' . self::bell_shortcode() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
