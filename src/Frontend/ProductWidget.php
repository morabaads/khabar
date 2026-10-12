<?php
/**
 * Product page UI: smart "notify me" button, expectation form, price alerts (Flow 1 & 3).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Frontend;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\Messenger\MessengerChannel;
use YadamBiar\Channels\OnsiteChannel;
use YadamBiar\Channels\PushChannel;
use YadamBiar\Commerce\Alternatives;
use YadamBiar\Commerce\PriceHistory;
use YadamBiar\Commerce\Reservation;
use YadamBiar\Contracts\Module;
use YadamBiar\Core\Installer;
use YadamBiar\Settings\Appearance;
use YadamBiar\Settings\Catalog;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\View;
use YadamBiar\Support\Visitor;

class ProductWidget implements Module {

	/**
	 * Rendered product ids.
	 *
	 * @var int[]
	 */
	private static $rendered = array();

	/**
	 * Hooks.
	 */
	public function register() {
		$positions = array(
			'after_price' => 15,
			'after_cart'  => 35,
			'after_meta'  => 45,
		);
		$pos       = Settings::get( 'position', 'after_cart' );
		if ( isset( $positions[ $pos ] ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_current' ), $positions[ $pos ] );
		}
		// Block themes do not run the classic summary hooks: attach to the equivalent blocks.
		add_filter( 'render_block', array( __CLASS__, 'render_block' ), 10, 3 );
		add_shortcode( 'yadambiar', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'yadambiar_bell', array( __CLASS__, 'bell_shortcode' ) );
		add_action( 'wp_footer', array( __CLASS__, 'floating_bell' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register assets.
	 */
	public static function register_assets() {
		wp_register_style( 'yadambiar', YADAMBIAR_URL . 'assets/css/frontend.css', array(), YADAMBIAR_VERSION );
		wp_add_inline_style( 'yadambiar', ':root{' . Appearance::css_vars() . '}' );
		wp_register_script( 'yadambiar', YADAMBIAR_URL . 'assets/js/frontend.js', array( 'jquery' ), YADAMBIAR_VERSION, true );
		$keys = PushChannel::enabled() ? PushChannel::keys() : null;
		wp_localize_script(
			'yadambiar',
			'YadamBiarData',
			array(
				'rest'      => esc_url_raw( rest_url( 'yadambiar/v1/' ) ),
				'nonce'     => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'annotate'  => (bool) Settings::get( 'annotate_variations' ),
				'pushKey'   => $keys ? $keys['public'] : '',
				'sw'        => add_query_arg( 'yadambiar_sw', 1, home_url( '/' ) ),
				'decimals'  => wc_get_price_decimals(),
				'i18n'      => array(
					'outOfStock' => __( 'ناموجود 🔔', 'yadambiar' ),
					'error'      => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'yadambiar' ),
					'confirmDel' => __( 'این درخواست حذف شود؟', 'yadambiar' ),
					'saved'      => __( 'ذخیره شد.', 'yadambiar' ),
					'pushDenied' => __( 'اجازه نمایش اعلان داده نشد.', 'yadambiar' ),
					'chooseOne'  => __( 'حداقل یک شرط را انتخاب کنید.', 'yadambiar' ),
					'any'        => __( 'هر کدام', 'yadambiar' ),
					'noNotes'    => __( 'اعلانی ندارید.', 'yadambiar' ),
					'connect'    => __( 'دریافت در %s', 'yadambiar' ),
					'connected'  => __( '%s متصل است ✓', 'yadambiar' ),
					'connectTip' => __( 'روی دکمه بزنید و در ربات «Start» را بزنید تا اعلان‌ها را آنجا هم بگیرید.', 'yadambiar' ),
					'networks'   => array(
						'telegram' => __( 'تلگرام', 'yadambiar' ),
						'bale'     => __( 'بله', 'yadambiar' ),
					),
				),
			)
		);
	}

	/**
	 * Enqueue assets (called when a widget renders).
	 */
	public static function enqueue() {
		if ( ! wp_script_is( 'yadambiar', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'yadambiar' );
		wp_enqueue_script( 'yadambiar' );
	}

	/**
	 * Icon name for a notification channel.
	 *
	 * @param string $channel Channel key.
	 * @return string
	 */
	public static function channel_icon( $channel ) {
		$map = array(
			'sms'      => 'chat',
			'email'    => 'mail',
			'telegram' => 'send',
			'bale'     => 'send',
		);
		return isset( $map[ $channel ] ) ? $map[ $channel ] : 'bell';
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
		if ( $product instanceof \WC_Product ) {
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
		$pos = Settings::get( 'position', 'after_cart' );
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
		Reservation::notice();
		return $content . ob_get_clean() . self::render( $product );
	}

	/**
	 * [yadambiar product_id="123"]
	 *
	 * @param array $atts Atts.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		global $product;
		$atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'yadambiar' );
		$p    = $atts['product_id'] ? wc_get_product( absint( $atts['product_id'] ) ) : $product;
		return $p instanceof \WC_Product ? self::render( $p ) : '';
	}

	/**
	 * Waiting count per variation.
	 *
	 * @param int $product_id Parent id.
	 * @return array variation_id => count
	 */
	private static function waiting_by_variation( $product_id ) {
		global $wpdb;
		$table = Installer::table( 'subscriptions' );
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
		if ( ! $product->is_type( array( 'simple', 'variable' ) ) && ! apply_filters( 'yadambiar_supports_product', false, $product ) ) {
			return '';
		}
		self::$rendered[ $product->get_id() ] = true;
		self::enqueue();

		$s        = array_merge( Settings::all(), array_filter( (array) $args, function ( $v ) { return null !== $v && '' !== $v; } ) );
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
		$channels   = array_intersect_key( Catalog::channels(), array_flip( (array) $s['channels_enabled'] ) );
		if ( isset( $channels['push'] ) && ! PushChannel::enabled() ) {
			unset( $channels['push'] );
		}
		foreach ( array( 'telegram', 'bale' ) as $network ) {
			if ( isset( $channels[ $network ] ) && ! MessengerChannel::available( $network ) ) {
				unset( $channels[ $network ] );
			}
		}
		// "Connect" buttons only where the customer must start the bot (not for Bale via Safir).
		$messengers = array_values( array_filter( array_intersect( array( 'telegram', 'bale' ), array_keys( $channels ) ), array( MessengerChannel::class, 'needs_link' ) ) );
		$attributes = $variable ? self::attribute_options( $product ) : array();
		$price      = '' === $product->get_price() ? '' : (float) $product->get_price();

		$yadambiar_vars = ! empty( $args['vars'] ) ? (string) $args['vars'] : '';
		$yadambiar_uid = ! empty( $args['uid'] ) ? sanitize_html_class( $args['uid'] ) : 'yadambiar-' . $product->get_id();
		ob_start();
		include View::locate( 'product-widget.php' );
		$html = ob_get_clean();
		if ( empty( $args['hide_extras'] ) ) {
			$html .= Alternatives::render_block( $product );
			if ( Settings::get( 'price_history_enabled', 1 ) && 'widget' === Settings::get( 'price_history_display' ) ) {
				$html .= PriceHistory::render( $product );
			}
		}
		return $html;
	}

	/**
	 * [yadambiar_bell]
	 *
	 * @return string
	 */
	public static function bell_shortcode() {
		if ( ! Settings::has( 'channels_enabled', 'onsite' ) ) {
			return '';
		}
		self::enqueue();
		$owner  = Visitor::owner_key();
		$unread = OnsiteChannel::unread( $owner );
		ob_start();
		?>
		<div class="yadambiar-bell" data-yadambiar-bell>
			<button type="button" class="yadambiar-bell-btn" aria-label="<?php esc_attr_e( 'اعلان‌ها', 'yadambiar' ); ?>">🔔<?php if ( $unread ) : ?><span class="yadambiar-bell-count"><?php echo esc_html( number_format_i18n( $unread ) ); ?></span><?php endif; ?></button>
			<div class="yadambiar-bell-list" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Floating bell for visitors who have notifications.
	 */
	public static function floating_bell() {
		if ( ! Settings::get( 'floating_bell' ) || ! Settings::has( 'channels_enabled', 'onsite' ) ) {
			return;
		}
		$owner = Visitor::owner_key();
		if ( ! $owner || ! OnsiteChannel::latest( $owner, 1 ) ) {
			return;
		}
		echo '<div class="yadambiar-floating">' . self::bell_shortcode() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
