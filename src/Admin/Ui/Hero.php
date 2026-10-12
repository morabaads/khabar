<?php
/**
 * Banner at the top of the admin app (brand, section tabs, active requests pill).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Ui;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\Icon;

/**
 * Admin hero banner.
 */
final class Hero {

	/**
	 * Print the banner.
	 *
	 * @param string $subtitle Subtitle.
	 * @param string $nav      Navigation markup (already escaped).
	 */
	public static function render( $subtitle, $nav = '' ) {
		$active = SubscriptionRepository::active_count();
		?>
		<header class="yadambiar-hero">
			<div class="yadambiar-hero-brand">
				<span class="yadambiar-hero-logo"><?php echo Icon::svg( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<div>
					<h1><?php esc_html_e( 'یادم بیار', 'yadambiar' ); ?></h1>
					<p><?php echo esc_html( $subtitle ); ?></p>
				</div>
			</div>
			<?php echo $nav; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<a class="yadambiar-hero-ghost" href="<?php echo esc_url( AdminUrl::view( 'requests', array( 'status' => 'active' ) ) ); ?>">
				<span class="yadambiar-hero-dot"></span>
				<?php /* translators: %s number */ printf( esc_html__( '%s درخواست فعال', 'yadambiar' ), '<b>' . esc_html( number_format_i18n( $active ) ) . '</b>' ); ?>
			</a>
		</header>
		<hr class="wp-header-end">
		<?php
	}
}
