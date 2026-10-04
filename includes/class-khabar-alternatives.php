<?php
/**
 * Alternative product suggestions for customers who have been waiting a long time.
 *
 * Candidates: up-sells, cross-sells and products of the same categories that are in stock.
 * Scoring:   + the customer's wanted attribute values (e.g. size 42) available in stock
 *            + price closeness to the awaited product (within ± range)
 *            + popularity (total sales), + merchant-chosen up-sell/cross-sell
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Alternatives {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'khabar_daily', array( __CLASS__, 'schedule' ), 20 );
		add_action( 'khabar_send_alternatives', array( __CLASS__, 'run' ) );
	}

	/**
	 * Queue the daily run (keeps the daily cron light).
	 */
	public static function schedule() {
		if ( Khabar_Settings::get( 'alt_enabled' ) ) {
			Khabar_Utils::queue( 'khabar_send_alternatives', array(), 120 );
		}
	}

	/**
	 * Human values of wanted attributes, e.g. ['pa_size' => '42'].
	 *
	 * @param array $attributes attribute_x => slug.
	 * @return array taxonomy/name => lowercase display value
	 */
	private static function wanted_values( $attributes ) {
		$out = array();
		foreach ( (array) $attributes as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$tax = str_replace( 'attribute_', '', $key );
			if ( taxonomy_exists( $tax ) ) {
				$term  = get_term_by( 'slug', $value, $tax );
				$value = $term ? $term->name : $value;
			}
			$out[ $tax ] = mb_strtolower( (string) $value );
		}
		return $out;
	}

	/**
	 * In-stock variation of a candidate that offers all wanted values (or null).
	 *
	 * @param WC_Product $candidate Candidate (variable).
	 * @param array      $wanted    Wanted values.
	 * @return WC_Product|null
	 */
	private static function matching_variation( $candidate, $wanted ) {
		foreach ( $candidate->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child || ! $child->is_in_stock() || ! $child->is_purchasable() ) {
				continue;
			}
			$have = self::wanted_values( $child->get_variation_attributes() );
			$ok   = true;
			foreach ( $wanted as $tax => $value ) {
				// Only compare attributes that the candidate actually has.
				if ( isset( $have[ $tax ] ) && '' !== $have[ $tax ] && $have[ $tax ] !== $value ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * Find alternatives.
	 *
	 * @param WC_Product $product    Awaited product (parent).
	 * @param array      $attributes Wanted attributes (attribute_x => slug).
	 * @param float|null $ref_price  Reference price.
	 * @param int        $limit      Limit.
	 * @return array[] product, target, score
	 */
	public static function find( $product, $attributes = array(), $ref_price = null, $limit = 3 ) {
		if ( null === $ref_price ) {
			$ref_price = '' === $product->get_price() ? null : (float) $product->get_price();
		}
		$range   = max( 1, (int) Khabar_Settings::get( 'alt_price_range', 30 ) ) / 100;
		$curated = array_unique( array_merge( $product->get_upsell_ids(), $product->get_cross_sell_ids() ) );
		$cats    = wc_get_product_term_ids( $product->get_id(), 'product_cat' );

		$ids = $curated;
		if ( $cats ) {
			$slugs = array();
			foreach ( $cats as $cat_id ) {
				$term = get_term( $cat_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) && 'uncategorized' !== $term->slug ) {
					$slugs[] = $term->slug;
				}
			}
			if ( $slugs ) {
				$ids = array_merge(
					$ids,
					wc_get_products(
						array(
							'status'       => 'publish',
							'category'     => $slugs,
							'stock_status' => 'instock',
							'visibility'   => 'catalog',
							'exclude'      => array( $product->get_id() ),
							'limit'        => 60,
							'return'       => 'ids',
						)
					)
				);
			}
		}
		$ids = array_diff( array_unique( array_map( 'intval', $ids ) ), array( $product->get_id() ) );
		$ids = apply_filters( 'khabar_alternative_candidate_ids', $ids, $product );

		$wanted = self::wanted_values( $attributes );
		$scored = array();
		foreach ( $ids as $id ) {
			$candidate = wc_get_product( $id );
			if ( ! $candidate || 'publish' !== $candidate->get_status() || ! $candidate->is_in_stock() || ! $candidate->is_visible() ) {
				continue;
			}
			$target = $candidate;
			$score  = 0.0;
			if ( $wanted && $candidate->is_type( 'variable' ) ) {
				$variation = self::matching_variation( $candidate, $wanted );
				if ( ! $variation ) {
					continue; // Does not come in the customer's size/color.
				}
				$target = $variation;
				// Bonus only when the candidate really shares the wanted attributes (e.g. has a size 42).
				if ( array_intersect_key( $wanted, self::wanted_values( $variation->get_variation_attributes() ) ) ) {
					$score += 3;
				}
			} elseif ( ! $candidate->is_purchasable() && ! $candidate->is_type( 'variable' ) ) {
				continue;
			}

			$price = '' === $target->get_price() ? null : (float) $target->get_price();
			if ( null !== $ref_price && $ref_price > 0 && null !== $price ) {
				$diff = abs( $price - $ref_price ) / $ref_price;
				if ( $diff > $range && ! in_array( $id, $curated, true ) ) {
					continue;
				}
				$score += 2 * max( 0, 1 - $diff / $range );
			}
			if ( in_array( $id, $curated, true ) ) {
				$score += 2;
			}
			$score += min( 2, log10( 1 + (int) $candidate->get_total_sales() ) / 2 );

			$scored[] = array(
				'product' => $candidate,
				'target'  => $target,
				'score'   => round( $score, 3 ),
			);
		}
		usort(
			$scored,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		return array_slice( $scored, 0, max( 1, $limit ) );
	}

	/**
	 * Is the awaited item still unavailable for this subscription.
	 *
	 * @param object     $sub     Subscription.
	 * @param WC_Product $product Parent product.
	 * @return bool
	 */
	private static function still_unavailable( $sub, $product ) {
		if ( $sub->variation_id ) {
			$v = wc_get_product( $sub->variation_id );
			return $v && ! $v->is_in_stock();
		}
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && $child->is_in_stock() && Khabar_Rules::attributes_match( $sub->attributes, $child->get_variation_attributes() ) ) {
					return false;
				}
			}
			return true;
		}
		return ! $product->is_in_stock();
	}

	/**
	 * Daily job: send suggestions to long-waiting customers (each subscription at most once).
	 *
	 * @param int $limit Max subscriptions per run.
	 * @return int Sent count.
	 */
	public static function run( $limit = 100 ) {
		global $wpdb;
		if ( ! Khabar_Settings::get( 'alt_enabled' ) ) {
			return 0;
		}
		$table  = Khabar_Install::table( 'subscriptions' );
		$before = Khabar_Utils::now( -max( 1, (int) Khabar_Settings::get( 'alt_after_days', 14 ) ) * DAY_IN_SECONDS );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'active' AND in_stock = 1 AND alt_sent_at IS NULL AND created_at <= %s ORDER BY updated_at ASC LIMIT %d", $before, $limit ) ); // phpcs:ignore
		$sent   = 0;
		$count  = max( 1, (int) Khabar_Settings::get( 'alt_count', 3 ) );

		foreach ( $rows as $row ) {
			$sub     = Khabar_Subscriptions::hydrate( $row );
			$product = wc_get_product( $sub->product_id );
			if ( ! $product || ! self::still_unavailable( $sub, $product ) ) {
				Khabar_Subscriptions::update( $sub->id, array() ); // Touch updated_at so others get their turn.
				continue;
			}
			$awaited = $sub->variation_id ? wc_get_product( $sub->variation_id ) : $product;
			$wanted  = $sub->variation_id && $awaited ? $awaited->get_variation_attributes() : $sub->attributes;
			$alts    = self::find( $product, $wanted, '' === $awaited->get_price() ? null : (float) $awaited->get_price(), $count );
			if ( ! $alts ) {
				Khabar_Subscriptions::update( $sub->id, array() );
				continue;
			}
			if ( self::send( $sub, $awaited, $alts ) ) {
				++$sent;
			}
			Khabar_Subscriptions::update( $sub->id, array( 'alt_sent_at' => Khabar_Utils::now() ) );
		}
		return $sent;
	}

	/**
	 * Send the suggestion message.
	 *
	 * @param object     $sub     Subscription.
	 * @param WC_Product $awaited Awaited product / variation.
	 * @param array[]    $alts    Alternatives.
	 * @return bool
	 */
	public static function send( $sub, $awaited, $alts ) {
		$snap  = Khabar_Rules::snapshot( $awaited );
		$vars  = Khabar_Dispatcher::vars( $sub, $awaited, $snap );
		$first = $alts[0]['target'];
		$html  = '<table role="presentation" cellpadding="8" style="width:100%;border-collapse:collapse">';
		foreach ( $alts as $alt ) {
			$link  = Khabar_Tracking::link( $sub, $alt['target']->get_id(), 'alt' );
			$img   = wp_get_attachment_image_url( $alt['target']->get_image_id() ? $alt['target']->get_image_id() : $alt['product']->get_image_id(), 'thumbnail' );
			$html .= '<tr style="border-bottom:1px solid #eee">'
				. '<td style="width:72px">' . ( $img ? '<img src="' . esc_url( $img ) . '" width="64" height="64" style="border-radius:8px;object-fit:cover" alt="">' : '' ) . '</td>'
				. '<td><a href="' . esc_url( $link ) . '" style="font-weight:bold">' . esc_html( $alt['target']->get_name() ) . '</a><br>' . esc_html( Khabar_Utils::price_text( $alt['target']->get_price() ) ) . '</td></tr>';
		}
		$html .= '</table>';

		$vars['alt_name']          = $first->get_name();
		$vars['alt_price']         = Khabar_Utils::price_text( $first->get_price() );
		$vars['alt_link']          = Khabar_Tracking::link( $sub, $first->get_id(), 'alt' );
		$vars['alternatives_html'] = $html;
		$vars['link']              = $vars['alt_link'];

		$message = Khabar_Channels::build_message( 'alternatives', $vars );
		$results = Khabar_Channels::send_to_subscription( $sub, 'alternatives', $message );
		do_action( 'khabar_alternatives_sent', $sub, $alts, $results );
		return in_array( true, $results, true );
	}

	/**
	 * "Available alternatives" block for an out-of-stock product page.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function render_block( $product ) {
		if ( ! Khabar_Settings::get( 'alt_show_on_page', 1 ) || $product->is_in_stock() ) {
			return '';
		}
		$alts = self::find( $product, array(), null, max( 1, (int) Khabar_Settings::get( 'alt_count', 3 ) ) );
		if ( ! $alts ) {
			return '';
		}
		ob_start();
		?>
		<div class="khabar-alts">
			<p class="khabar-alts-title"><?php esc_html_e( 'تا موجود شدن، این محصولات مشابه موجودند:', 'khabar' ); ?></p>
			<ul>
				<?php foreach ( $alts as $alt ) : ?>
					<?php $url = $alt['target']->is_type( 'variation' ) ? add_query_arg( $alt['target']->get_variation_attributes(), $alt['product']->get_permalink() ) : $alt['product']->get_permalink(); ?>
					<li>
						<a href="<?php echo esc_url( $url ); ?>">
							<?php echo wp_kses_post( $alt['product']->get_image( array( 64, 64 ) ) ); ?>
							<span class="khabar-alts-name"><?php echo esc_html( $alt['target']->get_name() ); ?></span>
							<span class="khabar-alts-price"><?php echo wp_kses_post( $alt['target']->get_price_html() ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}
}
