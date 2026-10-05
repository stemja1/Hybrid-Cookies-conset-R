<?php
/**
 * Vyčistenie pri odstránení pluginu.
 *
 * Dôležité: tabuľky sa mažú IBA vtedy, keď si to používateľ výslovne
 * nastavil. Log súhlasov je doklad o splnení GDPR, takže jeho zmazanie
 * bez súhlasu majiteľa webu by bolo protiprávne.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'HCC_VERSION' ) ) {
	define( 'HCC_VERSION', '0.1.0' );
}

$hcc_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $hcc_autoload ) ) {
	require_once $hcc_autoload;
} else {
	require_once __DIR__ . '/includes/autoload-fallback.php';
}

if ( ! \HCC\Options::get( 'delete_data_on_uninstall' ) ) {
	// Používateľ si nechal dáta zachovať — končíme.
	return;
}

foreach ( \HCC\Tables::all() as $hcc_table ) {
	// Názov tabuľky pochádza z `Tables::all()`, nie od používateľa.
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $hcc_table . '`' );
}

\HCC\Options::delete_all();
\HCC\Capabilities::remove();

delete_option( 'hcc_version' );
delete_transient( 'hcc_default_banner' );
