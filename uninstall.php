<?php
/**
 * Uninstall: remove data only when the admin opted in.
 *
 * @package YadamBiar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$yadambiar_settings = get_option( 'yadambiar_settings', array() );
if ( empty( $yadambiar_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'subscriptions', 'log', 'notifications', 'push', 'price_history', 'messenger' ) as $yadambiar_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'yadambiar_' . $yadambiar_table ); // phpcs:ignore
}
$yadambiar_page = (int) get_option( 'yadambiar_manage_page_id' );
if ( $yadambiar_page ) {
	wp_delete_post( $yadambiar_page, true );
}
foreach ( array( 'yadambiar_settings', 'yadambiar_db_version', 'yadambiar_vapid', 'yadambiar_manage_page_id', 'yadambiar_flush_rewrite', 'yadambiar_version' ) as $yadambiar_option ) {
	delete_option( $yadambiar_option );
}
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_yadambiar_wave','_yadambiar_exclusive_until','_yadambiar_last_price','_yadambiar_broadcast_at')" ); // phpcs:ignore
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yadambiar\\_lock\\_%'" ); // phpcs:ignore
wp_clear_scheduled_hook( 'yadambiar_daily' );
