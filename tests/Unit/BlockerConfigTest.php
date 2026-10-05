<?php
/**
 * Testy pre konfiguráciu frontendu blockera.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use HCC\Modules\Blocker\Blocker_Config;
use HCC\Modules\Blocker\Script_Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Testy zostavovania konfigurácie, ktorú `blocker.js` číta.
 */
#[CoversClass( Blocker_Config::class )]
class BlockerConfigTest extends TestCase {

	/**
	 * Konfigurácia.
	 *
	 * @var Blocker_Config
	 */
	private Blocker_Config $config;

	/**
	 * Predvolené nastavenie.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config = new Blocker_Config();
	}

	/**
	 * Očistí filtery a cache po teste.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_all_filters( 'hcc_script_providers' );
		remove_all_filters( 'hcc_blocker_config' );
		remove_all_filters( 'hcc_blocker_debug' );

		( new Script_Catalog() )->flush();

		parent::tearDown();
	}

	/**
	 * Konfigurácia obsahuje všetky kľúče, ktoré JS číta.
	 *
	 * @return void
	 */
	public function test_config_contains_required_keys(): void {
		$config = $this->config->build( array( 'necessary' ) );

		foreach ( array( 'cookie', 'granted', 'categories', 'patterns', 'consentUrl', 'nonce' ) as $key ) {
			$this->assertArrayHasKey( $key, $config, sprintf( 'Chýba kľúč "%s".', $key ) );
		}
	}

	/**
	 * Patterns sú plochá mapa `pattern -> kategória`.
	 *
	 * Plochá štruktúra je rýchlejšia na lookup než vnorené objekty
	 * a nepotrebuje providera, ktorý JS nikdy nepoužije.
	 *
	 * @return void
	 */
	public function test_patterns_are_flat_map_of_pattern_to_category(): void {
		$config = $this->config->build( array( 'necessary' ) );

		$this->assertArrayHasKey( 'google-analytics.com', $config['patterns'] );
		$this->assertSame( 'statistics', $config['patterns']['google-analytics.com'] );
		$this->assertSame( 'marketing', $config['patterns']['facebook.net'] );
	}

	/**
	 * Patterns neobsahujú providera ani zoznam cookies.
	 *
	 * @return void
	 */
	public function test_patterns_do_not_leak_internals(): void {
		$config = $this->config->build( array( 'necessary' ) );

		foreach ( $config['patterns'] as $definition ) {
			$this->assertIsString( $definition );
		}
	}

	/**
	 * Filter `hcc_script_providers` sa prejaví do patterns.
	 *
	 * @return void
	 */
	public function test_filter_provider_appears_in_patterns(): void {
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

		( new Script_Catalog() )->flush();

		$config = $this->config->build( array( 'necessary' ) );

		$this->assertSame( 'functional', $config['patterns']['cdn.mojskript.sk'] );
	}

	/**
	 * `necessary` nie je v zozname blokovaných kategórií.
	 *
	 * Bloker by nemal nikdy blokovať nevyhnutné — bez nich stránka
	 * nemá fungovať.
	 *
	 * @return void
	 */
	public function test_necessary_is_not_blockable(): void {
		$config = $this->config->build( array( 'necessary' ) );

		$this->assertNotContains( 'necessary', $config['categories'] );
	}

	/**
	 * Súhlasné kategórie sa prepíšu do konfigurácie.
	 *
	 * @return void
	 */
	public function test_granted_categories_are_passed_through(): void {
		$config = $this->config->build( array( 'necessary', 'statistics' ) );

		$this->assertSame( array( 'necessary', 'statistics' ), $config['granted'] );
	}

	/**
	 * Filter `hcc_blocker_debug` zapína debug výstup.
	 *
	 * @return void
	 */
	public function test_debug_flag_defaults_to_false(): void {
		$this->assertFalse( $this->config->build( array( 'necessary' ) )['debug'] );

		add_filter( 'hcc_blocker_debug', '__return_true' );

		$this->assertTrue( $this->config->build( array( 'necessary' ) )['debug'] );
	}

	/**
	 * Filter `hcc_blocker_config` umožňuje prepísať celú konfiguráciu.
	 *
	 * @return void
	 */
	public function test_config_filter_can_override(): void {
		add_filter(
			'hcc_blocker_config',
			static function ( array $config ): array {
				$config['patterns'] = array();

				return $config;
			}
		);

		$config = $this->config->build( array( 'necessary' ) );

		$this->assertSame( array(), $config['patterns'] );
	}
}
