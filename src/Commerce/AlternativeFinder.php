<?php
/**
 * Finds in-stock alternatives truly related to an awaited product (category, attributes, title, price).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Commerce;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
use YadamBiar\Support\Text;
/**
 * Finds in-stock alternatives truly related to an awaited product (category, attributes, title, price).
 */
final class AlternativeFinder {

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
		$title = mb_strtolower( Text::latin_digits( wp_strip_all_tags( (string) $title ) ) );
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
		$range   = max( 1, (int) Settings::get( 'alt_price_range', 30 ) ) / 100;
		$curated = array_map( 'intval', array_unique( array_merge( $product->get_upsell_ids(), $product->get_cross_sell_ids() ) ) );
		$ctx     = self::context( $product );

		$ids = array_diff( array_unique( array_map( 'intval', self::candidate_ids( $product, $ctx ) ) ), array( $product->get_id() ) );
		$ids = apply_filters( 'yadambiar_alternative_candidate_ids', $ids, $product );

		// Minimum relevance (without price / popularity) for non-curated candidates.
		$min_rel = (float) apply_filters( 'yadambiar_alternative_min_relevance', 2.5, $product );
		$floor   = (float) apply_filters( 'yadambiar_alternative_floor_relevance', 1.0, $product );
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
}
