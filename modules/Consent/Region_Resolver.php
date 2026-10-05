<?php
/**
 * Určenie regiónu návštevníka.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Consent;

use HCC\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Region resolver.
 *
 * Priorita zdrojov:
 *
 * 1. Manuálne nastavenie v bannri (návštevník si sám zvolil inú krajinu)
 * 2. `region_mode = manual` — pevne nastavené regióny zo settings
 * 3. `Accept-Language` hlavička
 * 4. GeoIP (ak je dostupný cez filter, inak sa preskočí)
 *
 * Bez GeoIP databázy plugin nikdy neodosiela IP adresu na tretiu stranu —
 * predvolene sa spolieha len na jazyk prehliadača, čo je aproximácia,
 * nie presná detekcia.
 */
class Region_Resolver {

	/**
	 * Predvolený región, keď sa nedá určiť.
	 */
	const FALLBACK = 'generic';

	/**
	 * Cache na úrovni requestu.
	 *
	 * @var string|null
	 */
	private static ?string $resolved = null;

	/**
	 * Krajina, ktorú si návštevník vybral ručne.
	 *
	 * @var string
	 */
	private string $manual_country = '';

	/**
	 * Nastaví ručne zvolenú krajinu.
	 *
	 * @param string $country Dvojmiestný kód krajiny.
	 * @return void
	 */
	public function set_manual_country( string $country ): void {
		$country = strtoupper( trim( $country ) );

		$this->manual_country = 2 === strlen( $country ) ? $country : '';
		self::$resolved       = null;
	}

	/**
	 * Určí región návštevníka.
	 *
	 * @return string
	 */
	public function resolve(): string {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		$mode = (string) Options::get( 'region_mode' );

		// `off` znamená, že používateľ regionálnu logiku vypol.
		if ( 'off' === $mode ) {
			self::$resolved = self::FALLBACK;

			return self::$resolved;
		}

		$country = $this->detect_country( $mode );
		$region  = '' === $country ? null : hcc_country_to_region( $country );

		/**
		 * Filter umožňuje prepísať určený región.
		 *
		 * @param string|null $region  Určený región alebo null.
		 * @param string      $country Krajina, z ktorej bol región odvodený.
		 * @param string      $mode    Režim detekcie (`auto`, `manual`, `off`).
		 */
		$region = apply_filters( 'hcc_region_resolved', $region, $country, $mode );

		self::$resolved = is_string( $region ) && isset( hcc_get_region_definitions()[ $region ] )
			? $region
			: self::FALLBACK;

		/**
		 * Action po určení regiónu.
		 *
		 * @param string      $resolved_region Efektívny región.
		 * @param string|null $region          Región pred filtrom.
		 * @param string      $country         Krajina.
		 */
		do_action( 'hcc_region_resolved', self::$resolved, $region, $country );

		return self::$resolved;
	}

	/**
	 * Vráti pravidlá regiónu.
	 *
	 * @return array{consent_type:string,law:string,label:string}
	 */
	public function get_rules(): array {
		$definitions = hcc_get_region_definitions();
		$region      = $this->resolve();

		return $definitions[ $region ] ?? $definitions[ self::FALLBACK ];
	}

	/**
	 * Vráti, či región používa model opt-out.
	 *
	 * V opt-out režime (napr. USA podľa CCPA) návštevník nemusí súhlas
	 * udeliť — stačí, že neurobí žiadnu voliteľnú činnosť.
	 *
	 * @return bool
	 */
	public function is_optout(): bool {
		return 'optout' === $this->get_rules()['consent_type'];
	}

	/**
	 * Vráti zákon platný pre návštevníka.
	 *
	 * @return string
	 */
	public function get_law(): string {
		return (string) $this->get_rules()['law'];
	}

	/**
	 * Určí krajinu podľa režimu.
	 *
	 * @param string $mode Režim detekcie.
	 * @return string Dvojmiestný kód krajiny alebo prázdny string.
	 */
	private function detect_country( string $mode ): string {
		// 1. Ručná voľba návštevníka má najvyššiu prioritu.
		if ( '' !== $this->manual_country ) {
			return $this->manual_country;
		}

		// 2. Pevne nastavené regióny zo settings.
		if ( 'manual' === $mode ) {
			$regions = (array) Options::get( 'regions' );
			$first   = sanitize_key( (string) ( $regions[0] ?? '' ) );

			return '' === $first ? '' : $this->region_to_country( $first );
		}

		// 3. GeoIP cez filter — plugin sám žiadne API nevolá.
		$geoip_country = (string) apply_filters( 'hcc_geoip_country', '' );
		$geoip_country = strtoupper( trim( $geoip_country ) );

		if ( 2 === strlen( $geoip_country ) ) {
			return $geoip_country;
		}

		// 4. Accept-Language ako posledná možnosť.
		return $this->country_from_language_header();
	}

	/**
	 * Preloží hlavičku `Accept-Language` na kód krajiny.
	 *
	 * Berie prvú položku, lebo poradie v hlavičke zodpovedá preferenciám
	 * používateľa. Hodnoty ako `sk-SK;q=0.9` sa normalizujú na `SK`.
	 *
	 * @return string
	 */
	private function country_from_language_header(): string {
		if ( empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return '';
		}

		$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );

		foreach ( explode( ',', $header ) as $part ) {
			$tag = trim( (string) explode( ';', $part )[0] );

			if ( '' === $tag ) {
				continue;
			}

			$parts = explode( '-', $tag );

			// Bez kódu krajiny (`sk`) nie je čo overiť.
			if ( ! isset( $parts[1] ) ) {
				continue;
			}

			$country = strtoupper( $parts[1] );

			if ( 2 === strlen( $country ) && hcc_country_to_region( $country ) ) {
				return $country;
			}
		}

		return '';
	}

	/**
	 * Nájde reprezentatívnu krajinu pre región.
	 *
	 * Používa sa, keď je režim `manual` — používateľ vyberie región EÚ
	 * a potrebujeme vedieť, ktorý zákon platí.
	 *
	 * @param string $region Slug regiónu.
	 * @return string
	 */
	private function region_to_country( string $region ): string {
		$region = sanitize_key( $region );

		/**
		 * Filter umožňuje namapovať región na krajinu.
		 *
		 * @param string $country Krajina.
		 * @param string $region  Región.
		 */
		$country = (string) apply_filters( 'hcc_region_to_country', '', $region );

		if ( 2 === strlen( $country ) ) {
			return strtoupper( $country );
		}

		foreach ( hcc_get_regions() as $code => $mapping ) {
			if ( $mapping['region'] === $region ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Zmaže cache regiónu.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$resolved = null;
	}
}
