<?php
/**
 * Admin actions that send now: check a product's waiting list, retry a failed send.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Actions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Channels\ChannelManager;
use YadamBiar\Notifications\Dispatcher;

/**
 * Manual delivery handlers.
 */
final class DeliveryActions extends ActionGroup {

	/**
	 * Actions.
	 *
	 * @return array
	 */
	protected function actions() {
		return array(
			'run_product' => 'run_product',
			'retry'       => 'retry',
		);
	}

	/**
	 * Check a product now and notify the customers whose conditions hold.
	 */
	public function run_product() {
		$pid = $this->int_arg( 'product_id' );
		if ( ! $pid || ! wc_get_product( $pid ) ) {
			$this->done( AdminUrl::view(), __( 'محصول پیدا نشد.', 'yadambiar' ), 'error' );
		}
		$sent = Dispatcher::run_now( $pid );
		if ( ! $sent ) {
			$this->done( AdminUrl::view( 'diagnose', array( 'product_id' => $pid ) ), __( 'اعلانی ارسال نشد؛ دلیل هر درخواست را در جدول زیر ببینید.', 'yadambiar' ), 'error' );
		}
		/* translators: %s count */
		$this->done( $this->referer( AdminUrl::view() ), sprintf( __( '%s مشتری مطلع شدند. (فقط درخواست‌هایی که شرایطشان برقرار است ارسال می‌شوند.)', 'yadambiar' ), number_format_i18n( $sent ) ) );
	}

	/**
	 * Retry a failed send from the log.
	 */
	public function retry() {
		$result = ChannelManager::retry( $this->int_arg( 'log' ) );
		$back   = $this->referer( AdminUrl::view( 'logs' ) );
		if ( is_wp_error( $result ) ) {
			$this->done( $back, $result->get_error_message(), 'error' );
		}
		$this->done( $back, __( 'ارسال شد.', 'yadambiar' ) );
	}
}
