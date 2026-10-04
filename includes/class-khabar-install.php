<?php
/**
 * Installation: tables, pages, cron.
 *
 * @package Khabar
 */

defined( 'ABSPATH' ) || exit;

class Khabar_Install {

	/**
	 * Activation.
	 */
	public static function activate() {
		self::create_tables();
		self::create_pages();
		if ( false === get_option( Khabar_Settings::OPTION ) ) {
			add_option( Khabar_Settings::OPTION, Khabar_Settings::defaults() );
		}
		if ( ! wp_next_scheduled( 'khabar_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'khabar_daily' );
		}
		update_option( 'khabar_db_version', KHABAR_DB_VERSION );
		update_option( 'khabar_flush_rewrite', 1 );
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'khabar_daily' );
		wp_clear_scheduled_hook( 'khabar_check_product' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'khabar_check_product' );
			as_unschedule_all_actions( 'khabar_sweep' );
			as_unschedule_all_actions( 'khabar_send_alternatives' );
		}
		flush_rewrite_rules();
	}

	/**
	 * Upgrade DB when version changes.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'khabar_db_version' ) !== KHABAR_DB_VERSION ) {
			self::activate();
		}
	}

	/**
	 * Table names.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'khabar_' . $name;
	}

	/**
	 * Create DB tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$subs   = self::table( 'subscriptions' );
		$log    = self::table( 'log' );
		$notes  = self::table( 'notifications' );
		$push   = self::table( 'push' );
		$prices = self::table( 'price_history' );
		$links  = self::table( 'messenger' );

		dbDelta(
			"CREATE TABLE {$subs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attributes text NULL,
			type varchar(20) NOT NULL DEFAULT 'stock',
			in_stock tinyint(1) NOT NULL DEFAULT 0,
			price_below decimal(19,4) NULL,
			price_above decimal(19,4) NULL,
			min_qty int(11) NULL,
			price_change tinyint(1) NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_key varchar(64) NOT NULL DEFAULT '',
			name varchar(100) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			channels varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			base_price decimal(19,4) NULL,
			notified_count int(11) NOT NULL DEFAULT 0,
			last_notified_at datetime NULL,
			notified_target bigint(20) unsigned NOT NULL DEFAULT 0,
			reserved_until datetime NULL,
			clicked_at datetime NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_value decimal(19,4) NULL,
			coupon_code varchar(40) NOT NULL DEFAULT '',
			alt_sent_at datetime NULL,
			token varchar(64) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NULL,
			PRIMARY KEY  (id),
			KEY product_status (product_id,status),
			KEY variation_id (variation_id),
			KEY user_id (user_id),
			KEY owner_key (owner_key),
			KEY email (email),
			KEY phone (phone),
			KEY token (token),
			KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event varchar(30) NOT NULL DEFAULT '',
			channel varchar(20) NOT NULL DEFAULT '',
			recipient varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'sent',
			message text NULL,
			error text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY subscription_id (subscription_id),
			KEY product_id (product_id),
			KEY status (status),
			KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_key varchar(64) NOT NULL DEFAULT '',
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			message text NULL,
			url text NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			pushed tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY owner_read (owner_key,is_read)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$push} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_key varchar(64) NOT NULL DEFAULT '',
			endpoint text NOT NULL,
			endpoint_hash char(64) NOT NULL DEFAULT '',
			p256dh varchar(255) NOT NULL DEFAULT '',
			auth varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_hash (endpoint_hash),
			KEY owner_key (owner_key)
			) {$charset};"
		);

		self::create_tables_v11( $prices, $links, $charset );
	}

	/**
	 * Tables added in 1.1.
	 *
	 * @param string $prices  Price history table.
	 * @param string $links   Messenger links table.
	 * @param string $charset Charset.
	 */
	private static function create_tables_v11( $prices, $links, $charset ) {
		dbDelta(
			"CREATE TABLE {$prices} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			price decimal(19,4) NOT NULL DEFAULT 0,
			regular_price decimal(19,4) NULL,
			recorded_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_time (product_id,recorded_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$links} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_key varchar(64) NOT NULL DEFAULT '',
			network varchar(20) NOT NULL DEFAULT '',
			chat_id varchar(64) NOT NULL DEFAULT '',
			username varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_network (owner_key,network),
			KEY chat (network,chat_id)
			) {$charset};"
		);
	}

	/**
	 * Create the "my alerts" page used by guests (and as a fallback).
	 */
	public static function create_pages() {
		$page_id = (int) get_option( 'khabar_manage_page_id' );
		if ( $page_id && get_post( $page_id ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'خبرم کن‌های من', 'khabar' ),
				'post_name'    => 'my-alerts',
				'post_content' => '<!-- wp:shortcode -->[khabar_my_alerts]<!-- /wp:shortcode -->',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'khabar_manage_page_id', $page_id );
		}
	}
}
