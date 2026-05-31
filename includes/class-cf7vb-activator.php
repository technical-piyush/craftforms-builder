<?php
/**
 * Activation: database tables.
 *
 * @package CF7VB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CF7VB_Activator {

	public static function activate() {
		self::create_tables();
		update_option( 'cf7vb_db_version', CF7VB_DB_VERSION );
	}

	/**
	 * Run on plugins_loaded for upgrades.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'cf7vb_db_version' ) === CF7VB_DB_VERSION ) {
			return;
		}
		self::create_tables();
		update_option( 'cf7vb_db_version', CF7VB_DB_VERSION );
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$entries = $wpdb->prefix . 'cf7vb_entries';
		$sql1    = "CREATE TABLE {$entries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'unread',
			data longtext NOT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			referrer text DEFAULT NULL,
			device varchar(32) DEFAULT NULL,
			draft_key varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY draft_key (draft_key),
			KEY created_at (created_at)
		) {$charset};";

		$events = $wpdb->prefix . 'cf7vb_analytics';
		$sql2   = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(32) NOT NULL,
			event_data longtext,
			session_key varchar(64) DEFAULT NULL,
			device varchar(32) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY event_type (event_type),
			KEY session_key (session_key),
			KEY created_at (created_at)
		) {$charset};";

		$drafts = $wpdb->prefix . 'cf7vb_drafts';
		$sql3   = "CREATE TABLE {$drafts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			draft_key varchar(64) NOT NULL,
			data longtext NOT NULL,
			email varchar(190) DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY draft_key (draft_key),
			KEY form_id (form_id),
			KEY expires_at (expires_at)
		) {$charset};";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	/**
	 * Remove plugin options, meta, and transients on uninstall.
	 *
	 * Custom table rows are truncated in uninstall.php; empty tables may remain until removed manually.
	 */
	public static function uninstall() {
		delete_option( 'cf7vb_db_version' );
		delete_post_meta_by_key( '_cf7vb_schema' );
		self::delete_rate_limit_transients();
	}

	/**
	 * Delete rate-limit transients using the key registry (no direct DB query).
	 */
	private static function delete_rate_limit_transients() {
		$cf7vb_keys = get_option( 'cf7vb_rate_limit_keys', array() );

		if ( ! is_array( $cf7vb_keys ) ) {
			delete_option( 'cf7vb_rate_limit_keys' );
			return;
		}

		foreach ( $cf7vb_keys as $cf7vb_key ) {
			if ( is_string( $cf7vb_key ) && '' !== $cf7vb_key ) {
				delete_transient( $cf7vb_key );
			}
		}

		delete_option( 'cf7vb_rate_limit_keys' );
	}
}
