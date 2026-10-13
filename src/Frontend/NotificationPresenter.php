<?php
/**
 * Shapes a bell notification for the visitor: clean text (the link becomes a button),
 * relative time and the product image.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Frontend;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Subscriptions\SubscriptionRepository;

/**
 * Bell item presenter.
 */
final class NotificationPresenter {

	/**
	 * Product thumbnails already looked up: product id => url.
	 *
	 * @var string[]
	 */
	private static $images = array();

	/**
	 * Public fields of a notification row.
	 *
	 * @param object $row Row (id, subscription_id, title, message, url, is_read, created_at).
	 * @return array
	 */
	public static function present( $row ) {
		$url     = (string) $row->url;
		$message = (string) $row->message;
		if ( '' !== $url ) {
			$message = str_replace( $url, '', $message ); // The link is shown as a button.
		}
		$message = trim( preg_replace( "/\n{3,}/", "\n\n", $message ) );
		$time    = strtotime( $row->created_at . ' UTC' );
		return array(
			'id'      => (int) $row->id,
			'title'   => (string) $row->title,
			'message' => $message,
			'url'     => esc_url_raw( $url ),
			'unread'  => ! (int) $row->is_read,
			'time'    => self::ago( $time ),
			'image'   => self::image( (int) $row->subscription_id ),
		);
	}

	/**
	 * "5 minutes ago" style time.
	 *
	 * @param int|false $time Timestamp.
	 * @return string
	 */
	private static function ago( $time ) {
		if ( ! $time ) {
			return '';
		}
		if ( time() - $time < MINUTE_IN_SECONDS ) {
			return __( 'همین الان', 'yadambiar' );
		}
		/* translators: %s human time difference, e.g. "5 minutes" */
		return sprintf( __( '%s پیش', 'yadambiar' ), human_time_diff( $time ) );
	}

	/**
	 * Thumbnail of the product a request waits for.
	 *
	 * @param int $sub_id Subscription id.
	 * @return string
	 */
	private static function image( $sub_id ) {
		$sub = $sub_id ? SubscriptionRepository::get( $sub_id ) : null;
		if ( ! $sub ) {
			return '';
		}
		$pid = (int) ( $sub->variation_id ? $sub->variation_id : $sub->product_id );
		if ( ! isset( self::$images[ $pid ] ) ) {
			$product  = wc_get_product( $pid );
			$image_id = $product ? $product->get_image_id() : 0;
			if ( ! $image_id && $product && $product->get_parent_id() ) {
				$parent   = wc_get_product( $product->get_parent_id() );
				$image_id = $parent ? $parent->get_image_id() : 0;
			}
			self::$images[ $pid ] = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
		}
		return self::$images[ $pid ];
	}
}
