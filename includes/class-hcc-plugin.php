<?php
/**
 * Hlavná trieda pluginu – zoradí háky a načíta moduly.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton orchestrátor pluginu.
 */
final class HCC_Plugin {

	/**
	 * Singleton inštancia.
	 *
	 * @var HCC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Moduly pluginu.
	 *
	 * @var array<string,object>
	 */
	private $modules = array();

	/**
	 * Vráti singleton inštanciu.
	 *
	 * @return HCC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registruje moduly a ich háky.
	 *
	 * @return void
	 */
	public function run() {
		$this->modules = array(
			'catalog' => new HCC_Cookie_Catalog(),
			'consent' => new HCC_Consent(),
			'admin'    => new HCC_Admin(),
			'settings' => new HCC_Settings(),
			'banner'  => new HCC_Banner(),
			'rest'    => new HCC_REST_Controller(),
		);

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Načíta preklady.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'hybrid-cookies-conset-r-plus',
			false,
			dirname( HCC_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Vráti modul podľa kľúča.
	 *
	 * @param string $key Kľúč modulu.
	 * @return object|null
	 */
	public function get_module( $key ) {
		return isset( $this->modules[ $key ] ) ? $this->modules[ $key ] : null;
	}
}