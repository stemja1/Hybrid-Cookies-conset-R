<?php
/**
 * Plugin Name:       Hybrid Cookies conset R+
 * Plugin URI:        https://github.com/stemja1/hybrid-cookies-conset-r-plus
 * Description:       Consent banner (cookie banner) a centrálny katalóg cookies pre WordPress vrátane blokovania skriptov pred súhlasom.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Joy
 * Author URI:        https://github.com/stemja1
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hybrid-cookies-conset-r-plus
 * Domain Path:       /languages
 *
 * @package HybridCookies
 */

// Exit, ak je súbor volaný priamo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HCC_VERSION', '0.1.0' );
define( 'HCC_PLUGIN_FILE', __FILE__ );
define( 'HCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HCC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Načíta všetky triedy pluginu.
 *
 * @return void
 */
function hcc_bootstrap() {
	$classes = array(
		'includes/class-hcc-plugin.php',
		'includes/class-hcc-activator.php',
		'includes/class-hcc-deactivator.php',
		'includes/class-hcc-cookie-catalog.php',
		'includes/class-hcc-consent.php',
		'includes/admin/class-hcc-admin.php',
		'includes/admin/class-hcc-settings.php',
		'includes/admin/class-hcc-cookie-list-table.php',
		'includes/admin/class-hcc-banner.php',
		'includes/api/class-hcc-rest-controller.php',
		'includes/helpers/class-hcc-helpers.php',
	);

	foreach ( $classes as $class_file ) {
		$path = HCC_PLUGIN_DIR . $class_file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	HCC_Plugin::instance()->run();
}

add_action( 'plugins_loaded', 'hcc_bootstrap' );

/**
 * Aktivácia pluginu.
 *
 * @return void
 */
function hcc_activate() {
	require_once HCC_PLUGIN_DIR . 'includes/helpers/class-hcc-helpers.php';
	require_once HCC_PLUGIN_DIR . 'includes/class-hcc-cookie-catalog.php';
	require_once HCC_PLUGIN_DIR . 'includes/class-hcc-activator.php';
	HCC_Activator::activate();
}
register_activation_hook( __FILE__, 'hcc_activate' );

/**
 * Deaktivácia pluginu.
 *
 * @return void
 */
function hcc_deactivate() {
	require_once HCC_PLUGIN_DIR . 'includes/class-hcc-deactivator.php';
	HCC_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'hcc_deactivate' );