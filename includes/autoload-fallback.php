<?php
/**
 * Fallback autoloader pre prípad, že na serveri nie je `vendor/`.
 *
 * Plugin nemá žiadnu runtime závislosť (žiadny balík z Packagist nie je
 * potrebný na beh pluginu), preto by sa mal dať nainštalovať aj cez FTP
 * bez spustenia `composer install`. Tento súbor mapuje PSR-4 namespace
 * `HCC\` na adresár `includes/` a `HCC\Modules\` na `modules/`.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jednoduchý PSR-4 autoloader.
 *
 * @param string $class_name Celý názov triedy.
 * @return void
 */
function hcc_fallback_autoload( string $class_name ): void {
	static $prefixes = array(
		'HCC\\Modules\\' => '/modules/',
		'HCC\\Admin\\'   => '/admin/',
		'HCC\\'          => '/includes/',
	);

	foreach ( $prefixes as $prefix => $directory ) {
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			continue;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = HCC_PLUGIN_DIR . $directory . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;

			return;
		}
	}
}

spl_autoload_register( 'hcc_fallback_autoload' );
