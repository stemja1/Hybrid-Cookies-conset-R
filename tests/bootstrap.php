<?php
/**
 * Bootstrap pre PHPUnit.
 *
 * Načíta WordPress testovacie prostredie ak je dostupné, inak použije
 * minimálne stuby WordPress funkcií. Cieľom je, aby čisté triedy
 * (`Script_Catalog`, `Options`, `Regions`) bolo možné testovať aj bez
 * databázy.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

define( 'HCC_TESTS_DIR', __DIR__ );
define( 'HCC_PLUGIN_DIR', dirname( __DIR__ ) );

require_once __DIR__ . '/stubs.php';

// Načítanie tried pluginu cez Composer autoloader (PSR-4).
$hcc_composer_autoload = HCC_PLUGIN_DIR . '/vendor/autoload.php';

if ( is_readable( $hcc_composer_autoload ) ) {
	require_once $hcc_composer_autoload;
} else {
	require_once HCC_PLUGIN_DIR . '/includes/autoload-fallback.php';
}

// `config/regions.php` je mimo PSR-4 (procedurálne funkcie), v produkcii
// ho načíta `Plugin::boot()`. V teste ho potrebujeme hneď.
require_once HCC_PLUGIN_DIR . '/config/regions.php';
