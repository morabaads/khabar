<?php
/**
 * Admin section: List of customer requests with filters, bulk actions and CSV export.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Access;
use YadamBiar\Admin\Flash;
use YadamBiar\Admin\Tables\RequestsTable;
use YadamBiar\Subscriptions\SubscriptionRepository;
/**
 * List of customer requests with filters, bulk actions and CSV export.
 */
final class RequestsPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'requests';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'درخواست‌ها', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'list';
	}

	/**
	 * Bulk actions of the list (delete / cancel / activate), before any output.
	 */
	public function load() {
		$table  = new RequestsTable();
		$action = $table->current_action();
		if ( ! in_array( $action, array( 'delete', 'cancel', 'activate' ), true ) || empty( $_REQUEST['ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		Access::guard( 'bulk-yadambiar_requests' );
		$ids = array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['ids'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'delete' === $action ) {
			SubscriptionRepository::delete( $ids );
		} else {
			foreach ( $ids as $id ) {
				SubscriptionRepository::update( $id, array( 'status' => 'cancel' === $action ? 'cancelled' : 'active' ) );
			}
		}
		Flash::redirect( remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce', '_wp_http_referer' ), wp_get_referer() ), __( 'انجام شد.', 'yadambiar' ) );
	}

	/**
	 * Render.
	 */
	public function render() {
		$table = new RequestsTable();
		$table->prepare_items();
		?>
		<div class="yadambiar-view">
			<hr class="wp-header-end">
			<?php $table->views(); ?>
			<form method="get">
				<?php $this->view_fields(); ?>
				<?php if ( $this->query_key( 'status' ) ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $this->query_key( 'status' ) ); ?>">
				<?php endif; ?>
				<?php
				$table->search_box( __( 'جستجو (موبایل، ایمیل، نام، شناسه محصول)', 'yadambiar' ), 'yadambiar' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
