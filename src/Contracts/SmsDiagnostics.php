<?php
/**
 * Optional gateway features used by the admin diagnostics box.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Contracts;

defined( 'ABSPATH' ) || exit;

interface SmsDiagnostics {

	/**
	 * Credit and sender lines.
	 *
	 * @return array|\WP_Error label => value.
	 */
	public function account_info();

	/**
	 * Human readable delivery status of a message.
	 *
	 * @param string $id Message id.
	 * @return string|\WP_Error
	 */
	public function delivery_status( $id );

	/**
	 * Message id in a successful send response.
	 *
	 * @param array $response Decoded response.
	 * @return string
	 */
	public function message_id( array $response );
}
