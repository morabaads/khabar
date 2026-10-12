<?php
/**
 * On-site notifications bell: [yadambiar_bell] and the floating bell for visitors with notifications.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Frontend;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Channels\OnsiteChannel;
use YadamBiar\Contracts\Module;
use YadamBiar\Settings\Settings;
use YadamBiar\Support\Visitor;
/**
 * On-site notifications bell: [yadambiar_bell] and the floating bell for visitors with notifications.
 */
final class Bell implements Module {

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'yadambiar_bell', array( __CLASS__, 'bell_shortcode' ) );
		add_action( 'wp_footer', array( __CLASS__, 'floating_bell' ) );
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
		Assets::enqueue();
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
