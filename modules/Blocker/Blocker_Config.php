<?php
/**
 * Konfigurácia prehodu do frontendu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Blocker;

use HCC\Modules\Consent\Consent_Cookie;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zostaví konfiguráciu, ktorú `blocker.js` potrebuje.
 *
 * Konfigurácia sa vypisuje ako JSON v `<head>` pred akýmkoľvek skriptom
 * tretej strany — bez nej by blocker vedel pracovať len so súhlasom,
 * ktorý ešte nebol zapísaný.
 */
class Blocker_Config {

	/**
	 * Katalóg poskytovateľov.
	 *
	 * @var Script_Catalog|null
	 */
	private ?Script_Catalog $catalog = null;

	/**
	 * Vráti konfiguráciu pre JS.
	 *
	 * @param array<int,string> $granted Kategórie, ktoré sú súhlasné.
	 * @return array<string,mixed>
	 */
	public function build( array $granted ): array {
		$config = array(
			'cookie'      => Consent_Cookie::NAME,
			'granted'     => array_values( $granted ),
			'categories'  => $this->get_enabled_categories(),
			'patterns'    => $this->get_flat_patterns(),
			'consentUrl'  => esc_url_raw( rest_url( 'hcc/v1/consent' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'debug'       => (bool) apply_filters( 'hcc_blocker_debug', false ),
			'blockedList' => array(),
		);

		/**
		 * Filter umožňuje upraviť konfiguráciu frontendu.
		 *
		 * @param array<string,mixed> $config Konfigurácia.
		 */
		return (array) apply_filters( 'hcc_blocker_config', $config );
	}

	/**
	 * Vráti zoznam kategórií, ktoré sa majú blokovať.
	 *
	 * @return array<int,string>
	 */
	private function get_enabled_categories(): array {
		return array( 'functional', 'statistics', 'marketing' );
	}

	/**
	 * Prevedie katalóg na plochú mapu `pattern -> kategória`.
	 *
	 * JS nepotrebuje providera ani zoznam cookies — stačí mu vedieť,
	 * či pattern patrí do kategórie. Plochá štruktúra je aj rýchlejšia
	 * pri lookup-e na každom vloženom uzle.
	 *
	 * @return array<string,string>
	 */
	private function get_flat_patterns(): array {
		$patterns = array();

		foreach ( $this->get_catalog()->all() as $pattern => $definition ) {
			$patterns[ (string) $pattern ] = $definition['category'];
		}

		return $patterns;
	}

	/**
	 * Vráti katalóg poskytovateľov.
	 *
	 * @return Script_Catalog
	 */
	private function get_catalog(): Script_Catalog {
		if ( null === $this->catalog ) {
			$this->catalog = new Script_Catalog();
		}

		return $this->catalog;
	}
}
