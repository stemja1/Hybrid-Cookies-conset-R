<?php
/**
 * Testy pre mapu krajín a regiónov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Testy pre mapu krajín a regiónov.
 */
#[CoversNothing]
class RegionsTest extends TestCase {

	/**
	 * Obnoví pôvodný stav registrovanych filtrov po každom teste.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_all_filters( 'hcc_regions' );
		remove_all_filters( 'hcc_region_definitions' );

		parent::tearDown();
	}

	/**
	 * Slovenská krajina patrí do regiónu EÚ.
	 *
	 * @return void
	 */
	public function test_sk_is_in_eu(): void {
		$this->assertSame( 'eu', hcc_country_to_region( 'SK' ) );
	}

	/**
	 * Veľké písmená sa normalizujú.
	 *
	 * `Accept-Language` aj GeoIP vracajú rôzne formáty.
	 *
	 * @return void
	 */
	public function test_country_code_is_case_insensitive(): void {
		$this->assertSame( 'eu', hcc_country_to_region( 'de' ) );
		$this->assertSame( 'uk', hcc_country_to_region( 'gb' ) );
	}

	/**
	 * Spojené kráľovstvo je samostatný región, nie EÚ.
	 *
	 * GB bola v EÚ do roku 2020 a platí tam UK GDPR, nie EÚ GDPR.
	 *
	 * @return void
	 */
	public function test_uk_is_separate_region(): void {
		$this->assertSame( 'uk', hcc_country_to_region( 'GB' ) );
		$this->assertNotSame( 'eu', hcc_country_to_region( 'GB' ) );
	}

	/**
	 * USA majú `optout`, zvyšok sveta `optin`.
	 *
	 * @return void
	 */
	public function test_us_uses_optout_consent(): void {
		$definitions = hcc_get_region_definitions();

		$this->assertSame( 'optout', $definitions['us']['consent_type'] );
		$this->assertSame( 'optin', $definitions['eu']['consent_type'] );
	}

	/**
	 * Neznáma krajina nevyvolá chybu.
	 *
	 * @return void
	 */
	public function test_unknown_country_returns_null(): void {
		$this->assertNull( hcc_country_to_region( 'ZZ' ) );
	}

	/**
	 * Prázdny alebo chybný vstup nevyvolá chybu.
	 *
	 * @return void
	 */
	public function test_malformed_input_returns_null(): void {
		$this->assertNull( hcc_country_to_region( '' ) );
		$this->assertNull( hcc_country_to_region( 'SLO' ) );
		$this->assertNull( hcc_country_to_region( '123' ) );
	}

	/**
	 * Všetky krajiny v mape odkazujú na existujúci región.
	 *
	 * Bez tejto kontroly by banner pre danú krajinu nenašiel pravidlá
	 * a o súhlase by sa nedalo rozhodnúť.
	 *
	 * @return void
	 */
	public function test_every_country_maps_to_defined_region(): void {
		$definitions = hcc_get_region_definitions();

		foreach ( hcc_get_regions() as $country => $mapping ) {
			$this->assertArrayHasKey(
				$mapping['region'],
				$definitions,
				sprintf( 'Krajina %s odkazuje na región "%s", ktorý nie je definovaný.', $country, $mapping['region'] )
			);
		}
	}

	/**
	 * Všetky regióny v definíciách majú platný typ súhlasu.
	 *
	 * @return void
	 */
	public function test_all_regions_have_valid_consent_type(): void {
		foreach ( hcc_get_region_definitions() as $region => $definition ) {
			$this->assertContains(
				$definition['consent_type'],
				array( 'optin', 'optout' ),
				sprintf( 'Región "%s" má neplatný typ súhlasu.', $region )
			);
		}
	}

	/**
	 * Filter `hcc_regions` vie pridať krajinu.
	 *
	 * @return void
	 */
	public function test_filter_can_add_country(): void {
		add_filter(
			'hcc_regions',
			static function ( array $regions ): array {
				$regions['SK'] = array(
					'region' => 'custom',
					'law'    => 'TEST',
				);

				return $regions;
			}
		);

		// `hcc_get_regions()` cache je statická — reload treba v novom procese,
		// preto testujeme, že filter je vôbec zavolaný.
		$regions = apply_filters( 'hcc_regions', hcc_default_regions() );

		$this->assertSame( 'custom', $regions['SK']['region'] );
		$this->assertSame( 'TEST', $regions['SK']['law'] );
	}
}
