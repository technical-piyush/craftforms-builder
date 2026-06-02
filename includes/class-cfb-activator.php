<?php
/**
 * Activation: database tables.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activation, database tables, and upgrades.
 */
final class CFB_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		update_option( 'cfb_db_version', CFB_DB_VERSION );
	}

	/**
	 * Run on plugins_loaded for upgrades.
	 */
	public static function maybe_upgrade() {
		self::migrate_legacy_prefix();

		if ( get_option( 'cfb_db_version' ) === CFB_DB_VERSION ) {
			return;
		}
		self::create_tables();
		update_option( 'cfb_db_version', CFB_DB_VERSION );
	}

	/**
	 * Migrate legacy cf7vb options and post meta from pre-rename installs.
	 *
	 * Custom tables are recreated via dbDelta() in maybe_upgrade(); rename old
	 * wp_*cf7vb_* tables manually if you need to preserve row data from a dev build.
	 */
	private static function migrate_legacy_prefix() {
		if ( ! get_option( 'cf7vb_db_version' ) ) {
			return;
		}

		$legacy_version = get_option( 'cf7vb_db_version' );
		if ( false !== $legacy_version && false === get_option( 'cfb_db_version' ) ) {
			update_option( 'cfb_db_version', $legacy_version );
		}
		delete_option( 'cf7vb_db_version' );

		self::migrate_legacy_post_meta();

		$legacy_keys = get_option( 'cf7vb_rate_limit_keys' );
		if ( false !== $legacy_keys ) {
			update_option( 'cfb_rate_limit_keys', $legacy_keys );
			delete_option( 'cf7vb_rate_limit_keys' );
		}
	}

	/**
	 * Move legacy schema post meta to the current meta key using core APIs.
	 */
	private static function migrate_legacy_post_meta() {
		$form_ids = get_posts(
			array(
				'post_type'              => 'wpcf7_contact_form',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $form_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! metadata_exists( 'post', $post_id, '_cf7vb_schema' ) ) {
				continue;
			}

			$value = get_post_meta( $post_id, '_cf7vb_schema', true );
			update_post_meta( $post_id, '_cfb_schema', $value );
			delete_post_meta( $post_id, '_cf7vb_schema' );
		}
	}

	/**
	 * Create or update custom database tables via dbDelta.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$entries = $wpdb->prefix . 'cfb_entries';
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

		$events = $wpdb->prefix . 'cfb_analytics';
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

		$drafts = $wpdb->prefix . 'cfb_drafts';
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
		delete_option( 'cfb_db_version' );
		delete_post_meta_by_key( '_cfb_schema' );
		self::delete_rate_limit_transients();
	}

	/**
	 * Delete rate-limit transients using the key registry (no direct DB query).
	 */
	private static function delete_rate_limit_transients() {
		$cfb_keys = get_option( 'cfb_rate_limit_keys', array() );

		if ( ! is_array( $cfb_keys ) ) {
			delete_option( 'cfb_rate_limit_keys' );
			return;
		}

		foreach ( $cfb_keys as $cfb_key ) {
			if ( is_string( $cfb_key ) && '' !== $cfb_key ) {
				delete_transient( $cfb_key );
			}
		}

		delete_option( 'cfb_rate_limit_keys' );
	}
}
