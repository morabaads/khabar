<?php
/**
 * Human labels for WooCommerce variation attributes.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Human labels for WooCommerce variation attributes.
 */
final class ProductAttributes {

	/**
	 * Human label for a set of attributes.
	 *
	 * @param array $attributes attribute_pa_color => value.
	 * @return string
	 */
	public static function attributes_label( $attributes ) {
		$parts = array();
		foreach ( (array) $attributes as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			$taxonomy = str_replace( 'attribute_', '', $key );
			$label    = wc_attribute_label( $taxonomy );
			if ( taxonomy_exists( $taxonomy ) ) {
				$term  = get_term_by( 'slug', $value, $taxonomy );
				$value = $term ? $term->name : $value;
			}
			$parts[] = $label . ': ' . $value;
		}
		return implode( '، ', $parts );
	}

	/**
	 * Product name; for a variation "Parent — attributes".
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function full_name( $product ) {
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			return ( $parent ? $parent->get_name() : '' ) . ' — ' . wc_get_formatted_variation( $product, true, false, false );
		}
		return $product->get_name();
	}
}
