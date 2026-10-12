<?php
/**
 * Admin actions: CSV exports (requests list, purchase order from the forecast).
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Actions;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\Export\CsvWriter;
use YadamBiar\Admin\Pages\ForecastPage;
use YadamBiar\Admin\Tables\RequestsTable;
use YadamBiar\Commerce\Forecast;
use YadamBiar\Core\Installer;
use YadamBiar\Subscriptions\SubscriptionRepository;
use YadamBiar\Support\ProductAttributes;

/**
 * CSV export handlers.
 */
final class ExportActions extends ActionGroup {

	const BATCH    = 1000;
	const MAX_ROWS = 50000;

	/**
	 * Actions.
	 *
	 * @return array
	 */
	protected function actions() {
		return array(
			'export'       => 'requests',
			'forecast_csv' => 'purchase_order',
		);
	}

	/**
	 * Requests matching the list filters, streamed in batches (bounded memory).
	 */
	public function requests() {
		global $wpdb;
		$table = Installer::table( 'subscriptions' );
		$where = SubscriptionRepository::filter_where( RequestsTable::filters() );
		$csv   = new CsvWriter( 'yadambiar-requests', array( 'ID', 'Product', 'Variation', 'Attributes', 'Type', 'Conditions', 'Name', 'Phone', 'Email', 'Channels', 'Status', 'Created', 'Notified', 'Clicked', 'Order', 'Order value' ) );
		$last  = PHP_INT_MAX;
		$done  = 0;
		while ( $done < self::MAX_ROWS ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} AND id < %d ORDER BY id DESC LIMIT %d", $last, self::BATCH ) ); // phpcs:ignore WordPress.DB
			if ( ! $rows ) {
				break;
			}
			_prime_post_caches( array_unique( array_map( 'absint', wp_list_pluck( $rows, 'product_id' ) ) ), false, false );
			foreach ( $rows as $row ) {
				$sub = SubscriptionRepository::hydrate( $row );
				$p   = wc_get_product( $sub->product_id );
				$csv->row(
					array(
						(int) $sub->id,
						$p ? $p->get_name() : (int) $sub->product_id,
						(int) $sub->variation_id,
						ProductAttributes::attributes_label( $sub->attributes ),
						$sub->type,
						SubscriptionRepository::conditions_label( $sub ),
						$sub->name,
						$sub->phone,
						$sub->email,
						$sub->channels,
						$sub->status,
						$sub->created_at,
						$sub->last_notified_at,
						$sub->clicked_at,
						$sub->order_id ? (int) $sub->order_id : '',
						$sub->order_value,
					)
				);
				$last = (int) $sub->id;
			}
			$done += count( $rows );
			if ( count( $rows ) < self::BATCH ) {
				break;
			}
		}
		$csv->finish();
	}

	/**
	 * Suggested purchase order from the demand forecast.
	 */
	public function purchase_order() {
		$data = Forecast::build( ForecastPage::request_params() );
		$csv  = new CsvWriter( 'yadambiar-purchase-order', array( 'SKU', 'Product', 'Suggested qty', 'Current stock', 'Waiting', 'Conversion %', 'Forecast demand', 'Safety stock', 'Unit price', 'Value' ) );
		foreach ( $data['rows'] as $r ) {
			if ( $r['calc']['order'] <= 0 ) {
				continue;
			}
			$csv->row(
				array(
					$r['sku'],
					ProductAttributes::full_name( $r['product'] ),
					$r['calc']['order'],
					null === $r['stock'] ? '' : $r['stock'],
					$r['waiting'],
					round( $r['conv'] * 100, 1 ),
					$r['calc']['demand'],
					$r['calc']['safety'],
					$r['price'],
					$r['revenue'],
				)
			);
		}
		$csv->finish();
	}
}
