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
	 * Words of a product title that carry meaning (model codes, types, brands).
	 *
	 * @param string $title Title.
	 * @return string[]
	 */
	private static function title_tokens( $title ) {
		$title = mb_strtolower( Khabar_Utils::latin_digits( wp_strip_all_tags( (string) $title ) ) );
		$title = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $title );
		$stop  = array( 'مدل', 'با', 'و', 'برای', 'از', 'در', 'به', 'تا', 'کیفیت', 'the', 'for', 'and', 'with', 'of', 'new', 'جدید', 'اصل', 'اورجینال', 'اصلی' );
		$words = array();
		foreach ( preg_split( '/\s+/u', trim( $title ) ) as $w ) {
			if ( mb_strlen( $w ) >= 2 && ! in_array( $w, $stop, true ) ) {
				$words[ $w ] = $w;
			}
		}
		return array_values( $words );
	}

	/**
	 * Context describing the awaited product (categories, tags, brand, title) used for relevance.
	 *
	 * @param WC_Product $product Awaited product.
	 * @return array
	 */
	private static function context( $product ) {
		$all  = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
		$leaf = array();
		foreach ( $all as $id ) {
			$children = get_term_children( $id, 'product_cat' );
			if ( ! array_intersect( $children ? $children : array(), $all ) ) {
				$term = get_term( $id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) && 'uncategorized' !== $term->slug ) {
					$leaf[] = (int) $id;
				}
			}
		}
		$ancestors = array();
		foreach ( $all as $id ) {
			foreach ( get_ancestors( $id, 'product_cat' ) as $a ) {
				$ancestors[] = (int) $a;
			}
		}
		$brands = array();
		foreach ( array( 'product_brand', 'pa_brand', 'pa_برند' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$ids = wp_get_post_terms( $product->get_id(), $tax, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $ids ) ) {
					$brands[ $tax ] = array_map( 'intval', $ids );
				}
			}
		}
		$tags = wc_get_product_term_ids( $product->get_id(), 'product_tag' );
		return array(
			'all'       => array_map( 'intval', $all ),
			'leaf'      => $leaf,
			'ancestors' => array_values( array_unique( $ancestors ) ),
			'tags'      => array_map( 'intval', $tags ),
			'brands'    => $brands,
			'tokens'    => self::title_tokens( $product->get_name() ),
		);
	}

	/**
	 * How related is a candidate to the awaited product (0 = unrelated)?
	 * Same most-specific category, shared brand/tags and similar title words weigh most;
	 * price and popularity only rank already related products.
	 *
	 * @param array      $ctx       Awaited product context.
	 * @param WC_Product $candidate Candidate.
	 * @return float
	 */
	private static function relevance( $ctx, $candidate ) {
		$score = 0.0;
		$cats  = wc_get_product_term_ids( $candidate->get_id(), 'product_cat' );
		if ( array_intersect( $ctx['leaf'], $cats ) ) {
			$score += 4;
		} elseif ( array_intersect( $ctx['all'], $cats ) ) {
			$score += 2.5; // Shares a (non-leaf) category.
		} elseif ( array_intersect( $ctx['ancestors'], $cats ) || array_intersect( $ctx['leaf'], self::ancestors_of( $cats ) ) ) {
			$score += 1; // Parent / sibling area only.
		}
		foreach ( $ctx['brands'] as $tax => $ids ) {
			if ( $ids && array_intersect( $ids, array_map( 'intval', (array) wp_get_post_terms( $candidate->get_id(), $tax, array( 'fields' => 'ids' ) ) ) ) ) {
				$score += 1.5;
			}
		}
		if ( $ctx['tags'] ) {
			$shared = count( array_intersect( $ctx['tags'], array_map( 'intval', wc_get_product_term_ids( $candidate->get_id(), 'product_tag' ) ) ) );
			$score += min( 2, $shared * 0.75 );
		}
		if ( $ctx['tokens'] ) {
			$other = self::title_tokens( $candidate->get_name() );
						$inter = count( array_intersect( $ctx['tokens'], $other ) );
			$score += ( $other && $ctx['tokens'] ) ? 4 * ( $inter / sqrt( count( $ctx['tokens'] ) * count( $other ) ) ) : 0;
		}
		return $score;
	}

	/**
	 * Ancestors of a list of term ids.
	 *
	 * @param int[] $ids Term ids.
	 * @return int[]
	 */
	private static function ancestors_of( $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			foreach ( get_ancestors( $id, 'product_cat' ) as $a ) {
				$out[] = (int) $a;
			}
		}
		return $out;
	}

	/**
	 * Candidate product ids: curated up-/cross-sells, then the most specific category, tags and, only if
	 * those are too few, the parent categories.
	 *
	 * @param WC_Product $product Awaited product.
	 * @param array      $ctx     Context.
	 * @return int[]
	 */
	private static function candidate_ids( $product, $ctx ) {
		$ids  = array_merge( $product->get_upsell_ids(), $product->get_cross_sell_ids() );
		$base = array(
			'status'       => 'publish',
			'stock_status' => 'instock',
			'visibility'   => 'catalog',
			'exclude'      => array( $product->get_id() ),
			'limit'        => 60,
			'orderby'      => 'popularity',
			'return'       => 'ids',
		);
		$slugs = function ( $term_ids ) {
			$out = array();
			foreach ( $term_ids as $tid ) {
				$term = get_term( $tid, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) && 'uncategorized' !== $term->slug ) {
					$out[] = $term->slug;
				}
			}
			return $out;
		};
		$leaf_slugs = $slugs( $ctx['leaf'] ? $ctx['leaf'] : $ctx['all'] );
		if ( $leaf_slugs ) {
			$ids = array_merge( $ids, wc_get_products( array_merge( $base, array( 'category' => $leaf_slugs ) ) ) );
		}
		if ( $ctx['tags'] ) {
			$tag_slugs = array();
			foreach ( $ctx['tags'] as $tid ) {
				$term = get_term( $tid, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$tag_slugs[] = $term->slug;
				}
			}
			if ( $tag_slugs ) {
				$ids = array_merge( $ids, wc_get_products( array_merge( $base, array( 'tag' => $tag_slugs, 'limit' => 30 ) ) ) );
			}
		}
		if ( count( array_unique( $ids ) ) < 12 && $ctx['ancestors'] ) {
			$parent_slugs = $slugs( $ctx['ancestors'] );
			if ( $parent_slugs ) {
				$ids = array_merge( $ids, wc_get_products( array_merge( $base, array( 'category' => $parent_slugs, 'limit' => 40 ) ) ) );
			}
		}
		return $ids;
	}

	/**
	 * Find alternatives that are really related to the awaited product.
	 *
	 * Relevance (category / brand / tags / title similarity) decides whether a product qualifies;
	 * the wanted attribute values (size, color), price closeness and popularity then rank them.
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
		$curated = array_map( 'intval', array_unique( array_merge( $product->get_upsell_ids(), $product->get_cross_sell_ids() ) ) );
		$ctx     = self::context( $product );

		$ids = array_diff( array_unique( array_map( 'intval', self::candidate_ids( $product, $ctx ) ) ), array( $product->get_id() ) );
		$ids = apply_filters( 'khabar_alternative_candidate_ids', $ids, $product );

		// Minimum relevance (without price / popularity) for non-curated candidates.
		$min_rel = (float) apply_filters( 'khabar_alternative_min_relevance', 2.5, $product );
		$floor   = (float) apply_filters( 'khabar_alternative_floor_relevance', 1.0, $product );
		$wanted  = self::wanted_values( $attributes );
		$scored  = array();
		foreach ( $ids as $id ) {
			$candidate = wc_get_product( $id );
			if ( ! $candidate || 'publish' !== $candidate->get_status() || ! $candidate->is_in_stock() || ! $candidate->is_visible() ) {
				continue;
			}
			$is_curated = in_array( $id, $curated, true );
			$relevance  = self::relevance( $ctx, $candidate );
			if ( ! $is_curated && $relevance < $floor ) {
				continue; // Not related at all.
			}
			$target = $candidate;
			$score  = $relevance + ( $is_curated ? 3 : 0 );
			if ( $wanted && $candidate->is_type( 'variable' ) ) {
				$variation = self::matching_variation( $candidate, $wanted );
				if ( ! $variation ) {
					continue; // Does not come in the customer's size/color.
				}
				$target = $variation;
				if ( array_intersect_key( $wanted, self::wanted_values( $variation->get_variation_attributes() ) ) ) {
					$score += 2;
				}
			} elseif ( ! $candidate->is_purchasable() && ! $candidate->is_type( 'variable' ) ) {
				continue;
			}

			$price = '' === $target->get_price() ? null : (float) $target->get_price();
			if ( null !== $ref_price && $ref_price > 0 && null !== $price ) {
				$diff = abs( $price - $ref_price ) / $ref_price;
				if ( $diff > $range && ! $is_curated ) {
					continue;
				}
				$score += 2 * max( 0, 1 - $diff / $range );
			}
			$score += min( 1, log10( 1 + (int) $candidate->get_total_sales() ) / 3 );

			$scored[] = array(
				'product'   => $candidate,
				'target'    => $target,
				'score'     => round( $score, 3 ),
				'relevance' => round( $relevance, 3 ),
			);
		}
		usort(
			$scored,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);
		// Strongly related products first; weaker (same area only) ones just fill the remaining slots.
		$limit  = max( 1, $limit );
		$strong = array_values(
			array_filter(
				$scored,
				function ( $row ) use ( $min_rel, $curated ) {
					return $row['relevance'] >= $min_rel || in_array( $row['product']->get_id(), $curated, true );
				}
			)
		);
		if ( count( $strong ) >= $limit ) {
			return array_slice( $strong, 0, $limit );
		}
		$weak = array_values( array_udiff( $scored, $strong, function ( $a, $b ) { return $a['product']->get_id() <=> $b['product']->get_id(); } ) );
		return array_slice( array_merge( $strong, $weak ), 0, $limit );
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
			if ( ! $awaited ) {
				continue;
			}
			$wanted  = $sub->variation_id && $awaited ? $awaited->get_variation_attributes() : $sub->attributes;
			$alts    = self::find( $product, $wanted, '' === $awaited->get_price() ? null : (float) $awaited->get_price(), $count );
			if ( ! $alts ) {
				Khabar_Subscriptions::update( $sub->id, array() );
				continue;
			}
			if ( self::send( $sub, $awaited, $alts ) ) {
				++$sent;
				Khabar_Subscriptions::update( $sub->id, array( 'alt_sent_at' => Khabar_Utils::now() ) );
			} else {
				Khabar_Subscriptions::update( $sub->id, array() ); // Retry on a later run.
			}
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
	public static function render_block( $product, $args = array() ) {
		$always = ! empty( $args['always'] );
		if ( ! $always && ( ! Khabar_Settings::get( 'alt_show_on_page', 1 ) || $product->is_in_stock() ) ) {
			return '';
		}
		$count = ! empty( $args['count'] ) ? (int) $args['count'] : (int) Khabar_Settings::get( 'alt_page_count', 4 );
		$title = ! empty( $args['title'] ) ? (string) $args['title'] : __( 'تا موجود شدن، این محصولات مشابه موجودند', 'khabar' );
		$alts  = self::find( $product, array(), null, min( 12, max( 1, $count ) ) );
		if ( ! $alts ) {
			return '';
		}
		ob_start();
		?>
		<div class="khabar-alts"<?php echo ! empty( $args['vars'] ) ? ' style="' . esc_attr( $args['vars'] ) . '"' : ''; ?>>
			<p class="khabar-alts-title"><?php echo esc_html( $title ); ?></p>
			<ul class="khabar-alts-list">
				<?php foreach ( $alts as $alt ) : ?>
					<?php $url = $alt['target']->is_type( 'variation' ) ? add_query_arg( $alt['target']->get_variation_attributes(), $alt['product']->get_permalink() ) : $alt['product']->get_permalink(); ?>
					<li>
						<a class="khabar-alt" href="<?php echo esc_url( $url ); ?>">
							<span class="khabar-alts-img"><?php echo wp_kses_post( $alt['product']->get_image( 'woocommerce_gallery_thumbnail' ) ); ?></span>
							<span class="khabar-alts-name"><?php echo esc_html( $alt['target']->get_name() ); ?></span>
							<span class="khabar-alts-price"><?php echo wp_kses_post( $alt['target']->get_price_html() ); ?></span>
							<span class="khabar-alts-cta"><?php esc_html_e( 'مشاهده', 'khabar' ); ?> ←</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}
}
