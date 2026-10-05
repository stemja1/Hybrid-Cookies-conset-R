<?php
/**
 * Bootstrap autoloadu pre kontexty mimo `plugins_loaded` (aktivácia, deaktivácia).
 *
 * WordPress volá `register_activation_hook` ešte pred načítaním nášho hlavného
 * `plugins_loaded` hooku, takže sa nemôžeme spoľahnúť na už zaregistrovaný
 * autoloader z `hcc_boot()`. Tento súbor ho načíta explicitne.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HCC_PLUGIN_DIR' ) ) {
	define( 'HCC_PLUGIN_DIR', dirname( __DIR__ ) );
}

if ( ! defined( 'HCC_PLUGIN_URL' ) ) {
	define( 'HCC_PLUGIN_URL', plugin_dir_url( HCC_PLUGIN_DIR . '/hybrid-cookies-conset-r-plus.php' ) );
}

if ( ! defined( 'HCC_PLUGIN_BASENAME' ) ) {
	define( 'HCC_PLUGIN_BASENAME', plugin_basename( HCC_PLUGIN_DIR . '/hybrid-cookies-conset-r-plus.php' ) );
}

if ( ! defined( 'HCC_VERSION' ) ) {
	define( 'HCC_VERSION', '0.1.0' );
}

$hcc_composer_autoload = HCC_PLUGIN_DIR . '/vendor/autoload.php';

if ( is_readable( $hcc_composer_autoload ) ) {
	require_once $hcc_composer_autoload;
} else {
	require_once __DIR__ . '/../autoload-fallback.php';
}

// `config/regions.php` nie je súčasťou Composer autoloadu — aktivácia
// potrebuje mapu regiónov ešte pred `Plugin::boot()`.
if ( ! function_exists( 'hcc_get_regions' ) ) {
	require_once HCC_PLUGIN_DIR . '/config/regions.php';
}
