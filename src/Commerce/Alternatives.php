<?php
/**
 * Alternative product suggestions for customers who have been waiting a long time.
 *
 * Candidates: up-sells, cross-sells and products of the same categories that are in stock.
 * Scoring:   + the customer's wanted attribute values (e.g. size 42) available in stock
 *            + price closeness to the awaited product (within ± range)
 *            + popularity (total sales), + merchant-chosen up-sell/cross-sell
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Queue;

/**
 * Alternative product suggestions for customers who have been waiting a long time.
 */
final class Alternatives implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'yadambiar_daily', array( __CLASS__, 'schedule' ), 20 );
		add_action( 'yadambiar_send_alternatives', array( AlternativesMailer::class, 'run' ) );
	}

	/**
	 * Queue the daily run (keeps the daily cron light).
	 */
	public static function schedule() {
		if ( Settings::get( 'alt_enabled' ) ) {
			Queue::schedule( 'yadambiar_send_alternatives', array(), 120 );
		}
	}

	/**
	 * "Available alternatives" block for an out-of-stock product page.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function render_block( $product, $args = array() ) {
		$always = ! empty( $args['always'] );
		if ( ! $always && ( ! Settings::get( 'alt_show_on_page', 1 ) || $product->is_in_stock() ) ) {
			return '';
		}
		$count = ! empty( $args['count'] ) ? (int) $args['count'] : (int) Settings::get( 'alt_page_count', 4 );
		$title = ! empty( $args['title'] ) ? (string) $args['title'] : __( 'تا موجود شدن، این محصولات مشابه موجودند', 'yadambiar' );
		$alts  = AlternativeFinder::find( $product, array(), null, min( 12, max( 1, $count ) ) );
		if ( ! $alts ) {
			return '';
		}
		ob_start();
		?>
		<div class="yadambiar-alts"<?php echo ! empty( $args['vars'] ) ? ' style="' . esc_attr( $args['vars'] ) . '"' : ''; ?>>
			<p class="yadambiar-alts-title"><?php echo esc_html( $title ); ?></p>
			<ul class="yadambiar-alts-list">
				<?php foreach ( $alts as $alt ) : ?>
					<?php $url = $alt['target']->is_type( 'variation' ) ? add_query_arg( $alt['target']->get_variation_attributes(), $alt['product']->get_permalink() ) : $alt['product']->get_permalink(); ?>
					<li>
						<a class="yadambiar-alt" href="<?php echo esc_url( $url ); ?>">
							<span class="yadambiar-alts-img"><?php echo wp_kses_post( $alt['product']->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></span>
							<span class="yadambiar-alts-name"><?php echo esc_html( $alt['target']->get_name() ); ?></span>
							<span class="yadambiar-alts-price"><?php echo wp_kses_post( $alt['target']->get_price_html() ); ?></span>
							<span class="yadambiar-alts-cta"><?php esc_html_e( 'مشاهده', 'yadambiar' ); ?> ←</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}
}
