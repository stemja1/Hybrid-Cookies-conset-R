<?php
/**
 * Aktivácia pluginu — databázová schéma a predvolené hodnoty.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aktivátor pluginu.
 */
class Activator {

	/**
	 * Spustí sa pri aktivácii pluginu.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();

		Options::seed_defaults();
		Capabilities::add();

		Options::update( 'version', HCC_VERSION );

		set_transient( 'hcc_activation_redirect', true, 60 );

		/**
		 * Action po úspešnej aktivácii.
		 */
		do_action( 'hcc_activated' );
	}

	/**
	 * Vytvorí tabuľky cez `dbDelta()`.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$previous_suppress = $wpdb->suppress_errors();

		// Necháme dbDelta updatovať existujúce tabuľky.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Tables::get_schema() as $sql ) {
			dbDelta( $sql );
		}

		$wpdb->suppress_errors( $previous_suppress );
	}
}
