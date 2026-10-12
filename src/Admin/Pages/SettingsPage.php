<?php
/**
 * Admin section: Settings tabs, the save form and per-tab tools.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Admin\SettingsForm\ChannelTools;
use YadamBiar\Admin\SettingsForm\SettingsForm;
use YadamBiar\Settings\Schema;
use YadamBiar\Settings\Settings;
/**
 * Settings tabs, the save form and per-tab tools.
 */
final class SettingsPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'settings';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'تنظیمات', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'gear';
	}

	/**
	 * Render.
	 */
	public function render() {
		$schema = Schema::all();
		$tab    = $this->query_key( 'tab' );
		$tab    = isset( $schema[ $tab ] ) ? $tab : 'general';
		?>
		<div class="yadambiar-view">
			<nav class="nav-tab-wrapper yadambiar-tabs">
				<?php foreach ( $schema as $key => $section ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( AdminUrl::settings( $key ) ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( ! empty( $schema[ $tab ]['desc'] ) ) : ?>
				<p class="description yadambiar-tab-desc"><?php echo esc_html( $schema[ $tab ]['desc'] ); ?></p>
			<?php endif; ?>

			<?php
			( new SettingsForm( $tab, $schema[ $tab ]['fields'], Settings::all(), Schema::defaults() ) )->render();
			if ( 'channels' === $tab ) {
				( new ChannelTools() )->render();
			}
			if ( 'general' === $tab ) {
				$this->shortcodes();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Shortcode reference.
	 */
	private function shortcodes() {
		?>
		<div class="yadambiar-card">
			<h2><?php esc_html_e( 'شورت‌کدها', 'yadambiar' ); ?></h2>
			<ul>
				<li><code>[yadambiar]</code> / <code>[yadambiar product_id="123"]</code> — <?php esc_html_e( 'دکمه و فرم یادم بیار', 'yadambiar' ); ?></li>
				<li><code>[yadambiar_my_alerts]</code> — <?php esc_html_e( 'پنل «یادآوری‌های من» (برای مهمان‌ها با لینک ارسالی)', 'yadambiar' ); ?></li>
				<li><code>[yadambiar_bell]</code> — <?php esc_html_e( 'زنگوله اعلان‌های داخل سایت', 'yadambiar' ); ?></li>
				<li><code>[yadambiar_price_history]</code> — <?php esc_html_e( 'نمودار تاریخچه قیمت محصول', 'yadambiar' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
