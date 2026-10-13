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
use YadamBiar\Support\Icon;
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
	 * [yadambiar_bell]: bell button with the unread count; opens the notifications popup.
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @param bool         $floating Rendered as the floating bell.
	 * @return string
	 */
	public static function bell_shortcode( $atts = array(), $floating = false ) {
		if ( ! Settings::has( 'channels_enabled', 'onsite' ) ) {
			return '';
		}
		Assets::enqueue();
		$unread = OnsiteChannel::unread( Visitor::owner_key() );
		$label  = $unread
			/* translators: %s number of unread notifications */
			? sprintf( __( '%s اعلان جدید', 'yadambiar' ), number_format_i18n( $unread ) )
			: __( 'اعلان‌ها', 'yadambiar' );
		ob_start();
		?>
		<div class="yadambiar-bell<?php echo $unread ? ' has-unread' : ''; ?>" data-yadambiar-bell<?php echo $floating ? ' data-floating="1"' : ''; ?>>
			<button type="button" class="yadambiar-bell-btn" aria-haspopup="dialog" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php echo Icon::svg( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( $unread ) : ?>
					<span class="yadambiar-bell-count"><?php echo esc_html( number_format_i18n( $unread ) ); ?></span>
				<?php endif; ?>
			</button>
			<?php if ( $floating && $unread ) : ?>
				<span class="yadambiar-bell-teaser" aria-hidden="true"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Floating bell: only while the visitor has unread notifications (it goes away once they are viewed).
	 */
	public static function floating_bell() {
		if ( ! Settings::get( 'floating_bell' ) || ! Settings::has( 'channels_enabled', 'onsite' ) ) {
			return;
		}
		$owner = Visitor::owner_key();
		if ( ! $owner || ! OnsiteChannel::unread( $owner ) ) {
			return;
		}
		echo '<div class="yadambiar-floating">' . self::bell_shortcode( array(), true ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
