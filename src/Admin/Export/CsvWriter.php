<?php
/**
 * Streams a CSV download (UTF-8 with BOM for Excel), safe against spreadsheet formula injection.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Export;

defined( 'ABSPATH' ) || exit;

/**
 * CSV download stream.
 */
final class CsvWriter {

	/**
	 * Output handle.
	 *
	 * @var resource
	 */
	private $out;

	/**
	 * Send the download headers and the header row.
	 *
	 * @param string $name   File name prefix (".csv" and today's date are appended).
	 * @param array  $header Column titles.
	 */
	public function __construct( $name, array $header ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $name . '-' . gmdate( 'Y-m-d' ) . '.csv' ) );
		header( 'X-Content-Type-Options: nosniff' );
		$this->out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $this->out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->row( $header );
	}

	/**
	 * Write a row.
	 *
	 * @param array $cells Cells.
	 */
	public function row( array $cells ) {
		fputcsv( $this->out, array_map( array( __CLASS__, 'cell' ), $cells ) );
	}

	/**
	 * Finish the download and stop the request.
	 */
	public function finish() {
		fclose( $this->out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Neutralize values a spreadsheet would run as a formula (customer names, notes…).
	 *
	 * @param mixed $value Cell.
	 * @return string|int|float
	 */
	public static function cell( $value ) {
		if ( is_int( $value ) || is_float( $value ) || null === $value ) {
			return null === $value ? '' : $value;
		}
		$value = (string) $value;
		if ( '' !== $value && ! is_numeric( $value ) && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
