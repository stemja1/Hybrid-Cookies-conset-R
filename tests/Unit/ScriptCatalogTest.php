<?php
/**
 * Testy pre katalóg poskytovateľov skriptov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use HCC\Modules\Blocker\Script_Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Testy normalizácie a vyhľadávania v katalógu poskytovateľov.
 */
#[CoversClass( Script_Catalog::class )]
class ScriptCatalogTest extends TestCase {

	/**
	 * Katalóg na testovanie.
	 *
	 * @var Script_Catalog
	 */
	private Script_Catalog $catalog;

	/**
	 * Predvolené nastavenie.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->catalog = new Script_Catalog();
	}

	/**
	 * Nahrá poskytovateľov priamo do transientu, aby testy nečítali disk.
	 *
	 * @param array<string,array<string,mixed>> $providers Poskytovatelia.
	 * @return void
	 */
	private function seed_cache( array $providers ): void {
		set_transient( Script_Catalog::CACHE_KEY, $providers, 300 );
	}

	/**
	 * Prázdny katalóg bez údajov nekreslí nič.
	 *
	 * @return void
	 */
	public function test_match_returns_null_for_empty_source(): void {
		$this->assertNull( $this->catalog->match( '' ) );
	}

	/**
	 * Nenájdený provider vráti null.
	 *
	 * @return void
	 */
	public function test_match_returns_null_for_unknown_domain(): void {
		$this->seed_cache(
			array(
				'google-analytics.com' => array(
					'provider' => 'Google Analytics',
					'category' => 'statistics',
					'cookies'  => array(),
				),
			)
		);

		$this->assertNull( $this->catalog->match( 'https://cdn.mojskript.sk/app.js' ) );
	}

	/**
	 * Najdlhší zhodný pattern vyhráva.
	 *
	 * `googletagmanager.com/ns.html` musí poraziť `googletagmanager.com`,
	 * inak by GTM iframe dostal inú kategóriu než GTM skript.
	 *
	 * @return void
	 */
	public function test_longest_pattern_wins(): void {
		$this->seed_cache(
			array(
				'googletagmanager.com'         => array(
					'provider' => 'Google Tag Manager',
					'category' => 'marketing',
					'cookies'  => array(),
				),
				'googletagmanager.com/ns.html' => array(
					'provider' => 'Google Tag Manager iframe',
					'category' => 'necessary',
					'cookies'  => array(),
				),
			)
		);

		$match = $this->catalog->match( 'https://www.googletagmanager.com/ns.html?id=GTM-ABC' );

		$this->assertNotNull( $match );
		$this->assertSame( 'googletagmanager.com/ns.html', $match['pattern'] );
		$this->assertSame( 'necessary', $match['category'] );
	}

	/**
	 * Zhoda nájde providera aj keď je pattern vnorený v URL.
	 *
	 * @return void
	 */
	public function test_match_finds_nested_pattern(): void {
		$this->seed_cache(
			array(
				'connect.facebook.net' => array(
					'provider' => 'Meta',
					'category' => 'marketing',
					'cookies'  => array( 'fr' ),
				),
			)
		);

		$match = $this->catalog->match( 'https://connect.facebook.net/en_US/fbevents.js' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Meta', $match['provider'] );
		$this->assertSame( array( 'fr' ), $match['cookies'] );
	}

	/**
	 * Filter `hcc_script_providers` vie doplniť poskytovateľa.
	 *
	 * @return void
	 */
	public function test_filter_can_add_provider(): void {
		add_filter(
			'hcc_script_providers',
			static function ( array $providers ): array {
				$providers['cdn.mojskript.sk'] = array(
					'provider' => 'Môj skript',
					'category' => 'functional',
					'cookies'  => array(),
				);

				return $providers;
			}
		);

		$this->catalog->flush();

		$match = $this->catalog->match( 'https://cdn.mojskript.sk/widget.js' );

		remove_all_filters( 'hcc_script_providers' );

		$this->assertNotNull( $match );
		$this->assertSame( 'Môj skript', $match['provider'] );
		$this->assertSame( 'functional', $match['category'] );
	}

	/**
	 * Neznáma kategória sa normalizuje na `marketing`.
	 *
	 * Takáto kategória by inak spôsobila, že by sa cookie nikdy
	 * neodblokovala, lebo banner by ju nikdy nenabudol ako „prijatú".
	 *
	 * @return void
	 */
	public function test_unknown_category_falls_back_to_marketing(): void {
		add_filter(
			'hcc_script_providers',
			static function ( array $providers ): array {
				$providers['skript.sk'] = array(
					'provider' => 'Neznámy',
					'category' => 'neexistujuca_kategoria',
					'cookies'  => array(),
				);

				return $providers;
			}
		);

		$this->catalog->flush();
		$all = $this->catalog->all();

		remove_all_filters( 'hcc_script_providers' );
		$this->catalog->flush();

		$this->assertArrayHasKey( 'skript.sk', $all );
		$this->assertSame( 'marketing', $all['skript.sk']['category'] );
	}

	/**
	 * Prázdny pattern sa zahodí.
	 *
	 * Pattern `''` by sa zhodol s každou URL a zablokoval celý web.
	 *
	 * @return void
	 */
	public function test_empty_pattern_is_discarded(): void {
		add_filter(
			'hcc_script_providers',
			static function ( array $providers ): array {
				$providers[''] = array(
					'provider' => 'Nič',
					'category' => 'marketing',
					'cookies'  => array(),
				);

				return $providers;
			}
		);

		$this->catalog->flush();
		$all = $this->catalog->all();

		remove_all_filters( 'hcc_script_providers' );
		$this->catalog->flush();

		$this->assertArrayNotHasKey( '', $all );
	}

	/**
	 * Kategórie v katalógu sú bez duplikátov.
	 *
	 * @return void
	 */
	public function test_categories_are_unique(): void {
		$this->seed_cache(
			array(
				'a.example.com' => array(
					'provider' => 'A',
					'category' => 'statistics',
					'cookies'  => array(),
				),
				'b.example.com' => array(
					'provider' => 'B',
					'category' => 'statistics',
					'cookies'  => array(),
				),
				'c.example.com' => array(
					'provider' => 'C',
					'category' => 'marketing',
					'cookies'  => array(),
				),
			)
		);

		$categories = $this->catalog->categories();

		sort( $categories );

		$this->assertSame( array( 'marketing', 'statistics' ), $categories );
	}
}
