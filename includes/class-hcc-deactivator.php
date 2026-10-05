<?php
/**
 * Deaktivácia pluginu.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deaktivátor pluginu.
 */
class HCC_Deactivator {

	/**
	 * Spustí sa pri deaktivácii pluginu.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// TODO: Vymazať transienty a naplánované úlohy.
		// Údaje v databáze NEODSTRÁNIME – o tom rozhodne uninstall.php.
		delete_transient( 'hcc_flush_cache' );
	}
}