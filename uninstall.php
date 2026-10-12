<?php
/**
 * Uninstall «یادم بیار»: removes the plugin's data only when the admin opted in
 * (Settings → Advanced → "delete data on uninstall"). Multisite: every site is cleaned.
 *
 * Coupons already issued and order history stay untouched (they belong to the store).
 *
 * @package YadamBiar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's data from the current site.
 */
function yadambiar_uninstall_site() {
	global $wpdb;
	$settings = get_option( 'yadambiar_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	foreach ( array( 'subscriptions', 'log', 'notifications', 'push', 'price_history', 'messenger' ) as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . 'yadambiar_' . $table ) . '`' ); // phpcs:ignore WordPress.DB
	}

	$page = (int) get_option( 'yadambiar_manage_page_id' );
	if ( $page ) {
		wp_delete_post( $page, true );
	}

	$options = array( 'yadambiar_settings', 'yadambiar_db_version', 'yadambiar_version', 'yadambiar_vapid', 'yadambiar_manage_page_id', 'yadambiar_flush_rewrite', 'yadambiar_legacy_migrated' );
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Run locks and transients (rate limits, codes, flash notices, caches).
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'yadambiar\\_lock\\_%' OR option_name LIKE '\\_transient\\_yadambiar\\_%' OR option_name LIKE '\\_transient\\_timeout\\_yadambiar\\_%'" ); // phpcs:ignore WordPress.DB

	// Product meta written by the plugin (waves, exclusive windows, last seen price, broadcasts).
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_yadambiar_wave','_yadambiar_exclusive_until','_yadambiar_last_price','_yadambiar_broadcast_at')" ); // phpcs:ignore WordPress.DB

	wp_clear_scheduled_hook( 'yadambiar_daily' );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'yadambiar' );
	}
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'yadambiar' );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $yadambiar_site ) {
		switch_to_blog( $yadambiar_site );
		yadambiar_uninstall_site();
		restore_current_blog();
	}
} else {
	yadambiar_uninstall_site();
}
