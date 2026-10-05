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

		// Moduly musia byť načítané, aby sa zaregistrovali aktivačné hooky.
		self::boot_modules_for_activation();

		/**
		 * Action po úspešnej aktivácii.
		 */
		do_action( 'hcc_activated' );
	}

	/**
	 * Načíta moduly a zaregistruje ich hooky bez spustenia celého pluginu.
	 *
	 * Používa sa pri aktivácii, keď `Plugin::boot()` ešte nebežal — moduly
	 * potrebuujú zaregistrovať svoje aktivačné hooky (napr. seedovanie
	 * kategórií), ale `hcc_loaded` action by spustil aj banner a blocker.
	 *
	 * @return void
	 */
	public static function boot_modules_for_activation(): void {
		$registry = new Module_Registry();
		$registry->load();

		$registry->register_hooks();
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
