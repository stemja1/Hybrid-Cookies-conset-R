<?php
/**
 * Testy pre určovanie regiónu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use HCC\Modules\Consent\Region_Resolver;
use HCC\Options;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Testy priority zdrojov pri určení regiónu.
 */
#[CoversClass( Region_Resolver::class )]
class RegionResolverTest extends TestCase {

	/**
	 * Predvolené nastavenie.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Region_Resolver::flush();
		Options::flush_schema_cache();
		$GLOBALS['hcc_test_options'] = array();

		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'sk-SK,sk;q=0.9,en;q=0.8';
	}

	/**
	 * Očistí stav po teste.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_all_filters( 'hcc_region_resolved' );
		remove_all_filters( 'hcc_geoip_country' );
		remove_all_filters( 'hcc_region_to_country' );

		unset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

		Region_Resolver::flush();
		Options::flush_schema_cache();
		$GLOBALS['hcc_test_options'] = array();

		parent::tearDown();
	}

	/**
	 * `Accept-Language` určí región.
	 *
	 * @return void
	 */
	public function test_detects_region_from_language_header(): void {
		$resolver = new Region_Resolver();

		$this->assertSame( 'eu', $resolver->resolve() );
	}

	/**
	 * Hlavička bez kódu krajiny sa použiť nedá.
	 *
	 * `Accept-Language: sk` hovorí iba, že používateľ hovorí po slovensky.
	 * To neznamená, že je v EÚ — jazyk a bydlisko sú nezávislé.
	 *
	 * @return void
	 */
	public function test_language_without_country_falls_back(): void {
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'sk,cs;q=0.9';
		Region_Resolver::flush();

		$this->assertSame( 'generic', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * Bez hlavičky sa použije fallback.
	 *
	 * @return void
	 */
	public function test_no_header_falls_back(): void {
		unset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

		$this->assertSame( 'generic', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * Ručne zvolená krajina má najvyššiu prioritu.
	 *
	 * Návštevník si môže zvoliť inú krajinu — jeho voľba je explicitná
	 * a nesmie byť prehlasovaná hlavičkou prehliadača.
	 *
	 * @return void
	 */
	public function test_manual_country_overrides_language_header(): void {
		$resolver = new Region_Resolver();
		$resolver->set_manual_country( 'US' );

		$this->assertSame( 'us', $resolver->resolve() );
	}

	/**
	 * Neplatná ručná krajina sa ignoruje.
	 *
	 * @return void
	 */
	public function test_invalid_manual_country_is_ignored(): void {
		$resolver = new Region_Resolver();
		$resolver->set_manual_country( 'XXX' );

		$this->assertSame( 'eu', $resolver->resolve() );
	}

	/**
	 * GeoIP cez filter má prednos pred `Accept-Language`.
	 *
	 * @return void
	 */
	public function test_geoip_filter_overrides_language_header(): void {
		add_filter( 'hcc_geoip_country', static fn(): string => 'CH' );

		$this->assertSame( 'ch', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * Prázdny GeoIP filter sa preskočí.
	 *
	 * @return void
	 */
	public function test_empty_geoip_filter_is_skipped(): void {
		add_filter( 'hcc_geoip_country', static fn(): string => '' );

		$this->assertSame( 'eu', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * Filter `hcc_region_resolved` vie prepísať región.
	 *
	 * @return void
	 */
	public function test_filter_can_override_region(): void {
		add_filter(
			'hcc_region_resolved',
			static fn() => 'us'
		);

		$this->assertSame( 'us', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * Filter môže vrátiť aj neznámy región.
	 *
	 * Takýto región musí skončiť ako `generic` — inak by sa pri
	 * `get_rules()` dostal k nedefinovanému zákazu.
	 *
	 * @return void
	 */
	public function test_filter_returning_unknown_region_falls_back(): void {
		add_filter(
			'hcc_region_resolved',
			static fn() => 'mars'
		);

		$this->assertSame( 'generic', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * Režim `off` vypne regionálnu logiku.
	 *
	 * @return void
	 */
	public function test_region_mode_off_returns_generic(): void {
		Options::update( 'region_mode', 'off' );
		$resolver = new Region_Resolver();

		$this->assertSame( 'generic', $resolver->resolve() );
	}

	/**
	 * Režim `manual` použije nastavené regióny.
	 *
	 * @return void
	 */
	public function test_region_mode_manual_uses_configured_region(): void {
		Options::update( 'region_mode', 'manual' );
		Options::update( 'regions', array( 'us' ) );

		$this->assertSame( 'us', ( new Region_Resolver() )->resolve() );
	}

	/**
	 * USA používajú opt-out.
	 *
	 * @return void
	 */
	public function test_us_is_optout(): void {
		$resolver = new Region_Resolver();
		$resolver->set_manual_country( 'US' );

		$this->assertTrue( $resolver->is_optout() );
	}

	/**
	 * EÚ používa opt-in.
	 *
	 * @return void
	 */
	public function test_eu_is_optin(): void {
		$this->assertFalse( ( new Region_Resolver() )->is_optout() );
	}

	/**
	 * Vráti zákon platný pre región.
	 *
	 * @return void
	 */
	public function test_returns_law_for_region(): void {
		$this->assertSame( 'GDPR', ( new Region_Resolver() )->get_law() );
	}

	/**
	 * Výsledok sa v rámci requestu cacheuje.
	 *
	 * @return void
	 */
	public function test_result_is_cached_within_request(): void {
		$resolver = new Region_Resolver();

		$first = $resolver->resolve();

		// Zmena hlavičky po prvom volaní nesmie zmeniť výsledok.
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';

		$this->assertSame( $first, $resolver->resolve() );
	}
}
