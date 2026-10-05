<?php
/**
 * Spustí sa pri odstránení pluginu.
 *
 * @package HybridCookies
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Odstráni tabuľky a nastavenia pluginu.
 */
function hcc_uninstall() {
	global $wpdb;

	$option = get_option( 'hcc_delete_on_uninstall', '0' );

	// Bez explicitného súhlasu necháme dáta vo WordPresse.
	if ( '1' !== (string) $option ) {
		return;
	}

	$tables = array(
		$wpdb->prefix . 'hcc_cookies',
		$wpdb->prefix . 'hcc_consent_log',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	$options = array(
		'hcc_enabled',
		'hcc_banner_position',
		'hcc_modal',
		'hcc_show_on_login',
		'hcc_show_on_mobile',
		'hcc_consent_expiry_days',
		'hcc_geo_targeting',
		'hcc_reject_all',
		'hcc_categories_enabled',
		'hcc_delete_on_uninstall',
		'hcc_script_mode',
		'hcc_block_patterns',
		'hcc_custom_css',
		'hcc_version',
		'hcc_defaults_imported',
	);

	foreach ( $options as $name ) {
		delete_option( $name );
	}
}
hcc_uninstall();