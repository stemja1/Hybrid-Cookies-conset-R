<?php
/**
 * Deaktivácia pluginu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deaktivátor pluginu.
 */
class Deactivator {

	/**
	 * Spustí sa pri deaktivácii pluginu.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Tabuľky ponecháme — doklady o súhlase musia prežiť deaktiváciu.
		self::clear_transients();

		/**
		 * Action po deaktivácii.
		 */
		do_action( 'hcc_deactivated' );
	}

	/**
	 * Vymaže transienty pluginu.
	 *
	 * @return void
	 */
	public static function clear_transients(): void {
		$transients = array(
			'hcc_activation_redirect',
			'hcc_script_providers',
			'hcc_rate_limit',
			'hcc_default_banner',
		);

		foreach ( $transients as $transient ) {
			delete_transient( $transient );
		}

		if ( wp_next_scheduled( 'hcc_daily_cleanup' ) ) {
			wp_clear_scheduled_hook( 'hcc_daily_cleanup' );
		}
	}
}
