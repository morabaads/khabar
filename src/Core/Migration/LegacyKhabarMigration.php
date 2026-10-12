<?php
/**
 * One-time import of data from the previous plugin name ("Khabar" / خبرم کن).
 *
 * Runs on activation before the tables are created, so existing requests, logs,
 * notifications, push devices, price history, messenger links and settings carry over.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Core\Migration;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Settings\Settings;
use YadamBiar\Support\Text;

/**
 * Imports khabar_* tables, options and meta into yadambiar_*.
 */
final class LegacyKhabarMigration {

	const DONE_OPTION = 'yadambiar_legacy_migrated';
	const OLD_PREFIX  = 'khabar_';
	const NEW_PREFIX  = 'yadambiar_';
	const TABLES      = array( 'subscriptions', 'log', 'notifications', 'push', 'price_history', 'messenger' );

	/**
	 * Run once (no-op when there is nothing to import or it already ran).
	 */
	public static function run() {
		if ( get_option( self::DONE_OPTION ) || false === get_option( self::OLD_PREFIX . 'settings', false ) ) {
			return;
		}
		self::deactivate_old_plugin();
		self::rename_tables();
		self::copy_settings();
		self::copy_options();
		self::rename_meta();
		self::clear_old_schedules();
		update_option( self::DONE_OPTION, time(), false );
	}

	/**
	 * Both plugins active would send every notification twice.
	 */
	private static function deactivate_old_plugin() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array( 'khabar/khabar.php' ) as $old ) {
			if ( is_plugin_active( $old ) ) {
				deactivate_plugins( $old, true );
			}
		}
	}

	/**
	 * RENAME TABLE keeps every row (and is atomic) when the new table does not exist yet.
	 */
	private static function rename_tables() {
		global $wpdb;
		foreach ( self::TABLES as $name ) {
			$old = $wpdb->prefix . self::OLD_PREFIX . $name;
			$new = $wpdb->prefix . self::NEW_PREFIX . $name;
			$has_old = $old === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$has_new = $new === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $new ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $has_old && ! $has_new ) {
				$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			}
		}
	}

	/**
	 * Settings: drop removed channels (Eitaa, WhatsApp) and convert the old textarea SMS patterns.
	 */
	private static function copy_settings() {
		$old = get_option( self::OLD_PREFIX . 'settings', array() );
		if ( ! is_array( $old ) || false !== get_option( Settings::OPTION, false ) ) {
			return;
		}
		unset( $old['eitaa_token'], $old['eitaa_channel'] );
		foreach ( array_keys( $old ) as $key ) {
			if ( 0 === strpos( $key, 'whatsapp_' ) ) {
				unset( $old[ $key ] );
			}
		}
		foreach ( array( 'channels_enabled', 'broadcast_networks' ) as $list ) {
			if ( isset( $old[ $list ] ) ) {
				$old[ $list ] = array_values( array_diff( (array) $old[ $list ], array( 'whatsapp', 'eitaa' ) ) );
			}
		}
		if ( empty( $old['sms_pattern_items'] ) && ! empty( $old['sms_patterns'] ) ) {
			$old['sms_pattern_items'] = self::legacy_patterns( (string) $old['sms_patterns'], isset( $old['sms_pattern_vars'] ) ? (string) $old['sms_pattern_vars'] : '' );
		}
		unset( $old['sms_patterns'], $old['sms_pattern_vars'] );
		add_option( Settings::OPTION, $old );
		Settings::flush();
	}

	/**
	 * "event=code" lines + shared "name=value" lines → per-event pattern items.
	 *
	 * @param string $codes Codes text.
	 * @param string $vars  Variables text.
	 * @return array
	 */
	private static function legacy_patterns( $codes, $vars ) {
		$list = array();
		foreach ( Text::parse_lines( $vars ) as $name => $value ) {
			$list[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}
		$items = array();
		foreach ( Text::parse_lines( $codes ) as $event => $code ) {
			if ( '' !== $code ) {
				$items[ sanitize_key( $event ) ] = array(
					'event' => sanitize_key( $event ),
					'code'  => sanitize_text_field( $code ),
					'vars'  => $list,
				);
			}
		}
		return $items;
	}

	/**
	 * Other options (push keys, "my alerts" page).
	 */
	private static function copy_options() {
		foreach ( array( 'vapid', 'manage_page_id' ) as $name ) {
			$value = get_option( self::OLD_PREFIX . $name, null );
			if ( null !== $value && false === get_option( self::NEW_PREFIX . $name, false ) ) {
				add_option( self::NEW_PREFIX . $name, $value, '', false );
			}
		}
		$page = (int) get_option( self::NEW_PREFIX . 'manage_page_id' );
		$post = $page ? get_post( $page ) : null;
		if ( $post && false !== strpos( $post->post_content, '[khabar_my_alerts]' ) ) {
			wp_update_post(
				array(
					'ID'           => $page,
					'post_content' => str_replace( '[khabar_my_alerts]', '[yadambiar_my_alerts]', $post->post_content ),
				)
			);
		}
	}

	/**
	 * Product / coupon / order meta keys (_khabar_* → _yadambiar_*), classic and HPOS order tables.
	 */
	private static function rename_meta() {
		global $wpdb;
		$like  = $wpdb->esc_like( '_' . self::OLD_PREFIX ) . '%';
		$start = strlen( '_' . self::OLD_PREFIX ) + 1;
		$tables = array( $wpdb->postmeta );
		$hpos   = $wpdb->prefix . 'wc_orders_meta';
		if ( $hpos === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $hpos ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$tables[] = $hpos;
		}
		foreach ( $tables as $table ) {
			// $table is one of WordPress's own table names (never user input).
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET meta_key = CONCAT(%s, SUBSTRING(meta_key, %d)) WHERE meta_key LIKE %s", '_' . self::NEW_PREFIX, $start, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Stop the old plugin's cron / queued jobs (they would call classes that no longer exist).
	 */
	private static function clear_old_schedules() {
		wp_clear_scheduled_hook( 'khabar_daily' );
		wp_clear_scheduled_hook( 'khabar_check_product' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( array( 'khabar_check_product', 'khabar_sweep', 'khabar_send_alternatives' ) as $hook ) {
				as_unschedule_all_actions( $hook );
			}
		}
	}
}
