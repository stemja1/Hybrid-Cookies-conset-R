<?php
/**
 * Plugin Name:       Hybrid Cookies conset R+
 * Plugin URI:        https://github.com/stemja1/Hybrid-Cookies-conset-R
 * Description:       Consent banner (cookie banner), katalóg cookies a blokovanie trackovacích skriptov pred udeleným súhlasom. GDPR/CCPA.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            stemja1
 * Author URI:        https://github.com/stemja1
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hybrid-cookies-conset-r-plus
 * Domain Path:       /languages
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

// Blokujeme priamy pristup.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Version.php';

// Konštanty pre kompatibilitu s inými kódmi, ktoré očakávajú
// `HCC_VERSION`. Zdroj pravdy je `HCC\Version`.
define( 'HCC_VERSION', HCC\Version::NUMBER );
define( 'HCC_MIN_PHP', HCC\Version::MIN_PHP );
define( 'HCC_PLUGIN_FILE', __FILE__ );
define( 'HCC_PLUGIN_DIR', __DIR__ );
define( 'HCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HCC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Overi minimalnu verziu PHP a PHP extensionov.
 *
 * Bez tohto kontrolu by plugin po aktivacii spadol s fatal errorom,
 * ktory je pre pouzivatela necitatelny.
 *
 * @return void
 */
function hcc_check_requirements(): void {
	$problems = array();

	if ( version_compare( PHP_VERSION, HCC_MIN_PHP, '<' ) ) {
		$problems[] = sprintf(
			/* translators: 1: pozadovana minimalna verzia PHP, 2: aktualna verzia PHP. */
			__( 'Plugin vyžaduje PHP %1$s alebo novšie. Aktuálna verzia je %2$s.', 'hybrid-cookies-conset-r-plus' ),
			HCC_MIN_PHP,
			PHP_VERSION
		);
	}

	foreach ( array( 'json', 'mbstring' ) as $extension ) {
		if ( ! extension_loaded( $extension ) ) {
			$problems[] = sprintf(
				/* translators: %s: nazov PHP extensionu. */
				__( 'Plugin vyžaduje PHP extension %s.', 'hybrid-cookies-conset-r-plus' ),
				$extension
			);
		}
	}

	if ( $problems ) {
		add_action(
			'admin_notices',
			static function () use ( $problems ): void {
				echo '<div class="notice notice-error"><p><strong>';
				esc_html_e( 'Hybrid Cookies conset R+ sa nenačítal:', 'hybrid-cookies-conset-r-plus' );
				echo '</strong></p><ul style="list-style:disc;margin-left:20px">';

				foreach ( $problems as $problem ) {
					echo '<li>' . esc_html( $problem ) . '</li>';
				}

				echo '</ul></div>';
			}
		);

		return;
	}

	hcc_boot();
}

/**
 * Načíta autoloader a spustí plugin.
 *
 * Composer autoloader je voliteľný — plugin nemá žiadnu runtime závislosť,
 * takže funguje aj keď na serveri `vendor/` nie je. Fallback autoloader
 * mapuje PSR-4 namespace `HCC\` na `includes/` a `modules/`.
 *
 * @return void
 */
function hcc_boot(): void {
	$composer_autoload = HCC_PLUGIN_DIR . '/vendor/autoload.php';

	if ( is_readable( $composer_autoload ) ) {
		require_once $composer_autoload;
	} else {
		require_once HCC_PLUGIN_DIR . '/includes/autoload-fallback.php';
	}

	HCC\Plugin::instance()->boot();
}

/**
 * Spustí sa pri aktivácii pluginu.
 *
 * @return void
 */
function hcc_activate(): void {
	require_once HCC_PLUGIN_DIR . '/includes/helpers/autoload.php';

	HCC\Activator::activate();
}
register_activation_hook( __FILE__, 'hcc_activate' );

/**
 * Spustí sa pri deaktivácii pluginu.
 *
 * @return void
 */
function hcc_deactivate(): void {
	require_once HCC_PLUGIN_DIR . '/includes/helpers/autoload.php';

	HCC\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'hcc_deactivate' );

hcc_check_requirements();
