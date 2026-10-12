<?php
/**
 * Admin section: Delivery log with retry for failed sends.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Tables\LogTable;
/**
 * Delivery log with retry for failed sends.
 */
final class LogsPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'logs';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'لاگ ارسال', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'send';
	}

	/**
	 * Render.
	 */
	public function render() {
		$table = new LogTable();
		$table->prepare_items();
		?>
		<div class="yadambiar-view">
			<?php $table->views(); ?>
			<form method="get">
				<?php $this->view_fields(); ?>
				<?php
				$table->search_box( __( 'جستجو', 'yadambiar' ), 'yadambiar-log' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
