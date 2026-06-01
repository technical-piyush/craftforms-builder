<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CraftFormsBuilder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all rows from custom tables (WordPress Coding Standards allow TRUNCATE on uninstall).
 */
function cfb_uninstall_truncate_tables() {
	global $wpdb;

	$cfb_tables = array(
		$wpdb->prefix . 'cfb_entries',
		$wpdb->prefix . 'cfb_analytics',
		$wpdb->prefix . 'cfb_drafts',
	);

	foreach ( $cfb_tables as $cfb_table ) {
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $cfb_table ) );
	}
}

cfb_uninstall_truncate_tables();

require_once __DIR__ . '/includes/class-cfb-activator.php';

CFB_Activator::uninstall();
