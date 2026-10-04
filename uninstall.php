<?php
/**
 * Uninstall: remove data only when the admin opted in.
 *
 * @package Khabar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$khabar_settings = get_option( 'khabar_settings', array() );
if ( empty( $khabar_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'subscriptions', 'log', 'notifications', 'push' ) as $khabar_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'khabar_' . $khabar_table ); // phpcs:ignore
}
$khabar_page = (int) get_option( 'khabar_manage_page_id' );
if ( $khabar_page ) {
	wp_delete_post( $khabar_page, true );
}
foreach ( array( 'khabar_settings', 'khabar_db_version', 'khabar_vapid', 'khabar_manage_page_id', 'khabar_flush_rewrite' ) as $khabar_option ) {
	delete_option( $khabar_option );
}
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_khabar_wave','_khabar_exclusive_until')" ); // phpcs:ignore
wp_clear_scheduled_hook( 'khabar_daily' );
