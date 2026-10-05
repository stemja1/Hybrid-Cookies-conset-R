<?php
/**
 * Modul blokovania skriptov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Blocker;

use HCC\Abstract_Module;
use HCC\Options;
use HCC\Version;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul blocker.
 *
 * Vypíše konfiguráciu a `blocker.js` do `<head>` ešte pred tým, než
 * WordPress načíta akýkoľvek skript tretej strany. Načítanie cez
 * `wp_head` s prioritou 0 znamená, že blocker je prvý.
 */
class Blocker_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 10;

	/**
	 * Handle enqueueovaného skriptu.
	 */
	const HANDLE = 'hcc-blocker';

	/**
	 * Katalóg poskytovateľov.
	 *
	 * @var Script_Catalog|null
	 */
	private ?Script_Catalog $catalog = null;

	/**
	 * Konfigurácia pre frontend.
	 *
	 * @var Blocker_Config|null
	 */
	private ?Blocker_Config $config = null;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'blocker';
	}

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Blokovanie skriptov', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Zaregistruje hooky modulu.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priorita 0 = pred všetkými ostatnými hookmi v `wp_head`.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 0 );
		add_action( 'wp_head', array( $this, 'print_inline_config' ), 0 );
		add_action( 'hcc_options_updated', array( $this, 'flush_catalog' ) );
	}

	/**
	 * Zaregistruje skript bez vypísania do päty.
	 *
	 * `wp_register_script()` + `wp_add_inline_script()` a potom
	 * `wp_print_script()` v `wp_head` zaručí, že kód beží pred
	 * skriptmi tretích strán.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		if ( is_admin() || ! $this->should_block() ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			HCC_PLUGIN_URL . 'modules/Blocker/js/blocker.js',
			array(),
			Version::NUMBER,
			false
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.hccBlockerConfig = ' . wp_json_encode( $this->get_config()->build( $this->get_granted() ) ) . ';',
			'before'
		);
	}

	/**
	 * Vypíše blocker skript do `<head>`.
	 *
	 * @return void
	 */
	public function print_inline_config(): void {
		if ( is_admin() || ! $this->should_block() ) {
			return;
		}

		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			$this->register_assets();
		}

		wp_print_script( self::HANDLE );
	}

	/**
	 * Vráti, či sa má vôbec blokovať.
	 *
	 * @return bool
	 */
	private function should_block(): bool {
		return (bool) Options::get( 'blocker_enabled' );
	}

	/**
	 * Vráti aktuálne súhlasné kategórie.
	 *
	 * @return array<int,string>
	 */
	private function get_granted(): array {
		$consent_module = $this->get_module( 'consent' );

		if ( ! $consent_module instanceof Consent_Module ) {
			return array( 'necessary' );
		}

		return $consent_module->get_cookie()->granted_categories();
	}

	/**
	 * Vymaže cache katalógu po zmene nastavení.
	 *
	 * @return void
	 */
	public function flush_catalog(): void {
		$this->get_catalog()->flush();
	}

	/**
	 * Vráti katalóg poskytovateľov.
	 *
	 * @return Script_Catalog
	 */
	public function get_catalog(): Script_Catalog {
		if ( null === $this->catalog ) {
			$this->catalog = new Script_Catalog();
		}

		return $this->catalog;
	}

	/**
	 * Vráti konfiguráciu frontendu.
	 *
	 * @return Blocker_Config
	 */
	public function get_config(): Blocker_Config {
		if ( null === $this->config ) {
			$this->config = new Blocker_Config();
		}

		return $this->config;
	}
}
