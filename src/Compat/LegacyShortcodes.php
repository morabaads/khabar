<?php
/**
 * Shortcodes of the previous plugin name keep working on existing pages.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Compat;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Commerce\PriceHistory;
use YadamBiar\Contracts\Module;
use YadamBiar\Frontend\Account;
use YadamBiar\Frontend\ProductWidget;

/**
 * [khabar], [khabar_my_alerts], [khabar_bell], [khabar_price_history] → current implementations.
 */
final class LegacyShortcodes implements Module {

	/**
	 * Register aliases (only when no other plugin owns the tag).
	 */
	public function register() {
		add_action( 'init', array( $this, 'add' ), 20 );
	}

	/**
	 * Add the aliases.
	 */
	public function add() {
		$aliases = array(
			'khabar'               => array( ProductWidget::class, 'shortcode' ),
			'khabar_bell'          => array( ProductWidget::class, 'bell_shortcode' ),
			'khabar_my_alerts'     => array( Account::class, 'shortcode' ),
			'khabar_price_history' => array( PriceHistory::class, 'shortcode' ),
		);
		foreach ( $aliases as $tag => $callback ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, $callback );
			}
		}
	}
}
