<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CF7VB
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all rows from custom tables (WordPress Coding Standards allow TRUNCATE on uninstall).
 */
function cf7vb_uninstall_truncate_tables() {
	global $wpdb;

	$cf7vb_tables = array(
		$wpdb->prefix . 'cf7vb_entries',
		$wpdb->prefix . 'cf7vb_analytics',
		$wpdb->prefix . 'cf7vb_drafts',
	);

	foreach ( $cf7vb_tables as $cf7vb_table ) {
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $cf7vb_table ) );
	}
}

cf7vb_uninstall_truncate_tables();

require_once __DIR__ . '/includes/class-cf7vb-activator.php';

CF7VB_Activator::uninstall();
