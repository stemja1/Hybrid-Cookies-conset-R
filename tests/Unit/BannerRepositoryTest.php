<?php
/**
 * Testy pre sanitizáciu konfigurácie bannera.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use HCC\Modules\Banner\Banner_Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Testy validácie farieb, textov a layoutu.
 */
#[CoversClass( Banner_Repository::class )]
class BannerRepositoryTest extends TestCase {

	/**
	 * Repozitár bannerov.
	 *
	 * @var Banner_Repository
	 */
	private Banner_Repository $repository;

	/**
	 * Predvolené nastavenie.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->repository = new Banner_Repository();
	}

	/**
	 * Predvolená konfigurácia má všetky kľúče.
	 *
	 * @return void
	 */
	public function test_default_config_has_all_keys(): void {
		$config = Banner_Repository::default_config();

		foreach ( array( 'layout', 'position', 'modal', 'show_details', 'show_reject_all', 'colors', 'texts' ) as $key ) {
			$this->assertArrayHasKey( $key, $config, sprintf( 'Chýba kľúč "%s".', $key ) );
		}
	}

	/**
	 * Neplatný layout sa nahradí predvoleným.
	 *
	 * Layout riadi, ktorá šablóna sa načíta — neplatná hodnota by
	 * spôsobila 500 po `include`.
	 *
	 * @return void
	 */
	public function test_invalid_layout_falls_back(): void {
		$config = $this->sanitize(
			array(
				'config' => array( 'layout' => '../../etc/passwd' ),
			)
		);

		$this->assertSame( 'default', $config['layout'] );
	}

	/**
	 * Platné layouty prejdú.
	 *
	 * @return void
	 */
	public function test_valid_layouts_are_preserved(): void {
		foreach ( array( 'default', 'bar', 'box' ) as $layout ) {
			$config = $this->sanitize( array( 'config' => array( 'layout' => $layout ) ) );

			$this->assertSame( $layout, $config['layout'] );
		}
	}

	/**
	 * Neplatná pozícia sa nahradí predvolenou.
	 *
	 * @return void
	 */
	public function test_invalid_position_falls_back(): void {
		$config = $this->sanitize( array( 'config' => array( 'position' => 'left' ) ) );

		$this->assertSame( 'bottom', $config['position'] );
	}

	/**
	 * Farby musia byť hex.
	 *
	 * Farby idú do inline `style` atribútu. Keby prešli ľubovoľné
	 * hodnoty, cez banner by sa dala vložiť deklarácia CSS, napr.
	 * `background: red; position: fixed; z-index: 99999`.
	 *
	 * @return void
	 */
	public function test_non_hex_colors_are_rejected(): void {
		$config = $this->sanitize(
			array(
				'config' => array(
					'colors' => array(
						'background' => 'red; position: fixed; top: 0; left: 0; width: 100vw; height: 100vw; z-index: 99999',
					),
				),
			)
		);

		$this->assertSame( '#ffffff', $config['colors']['background'] );
	}

	/**
	 * Platné hex farby prejdú.
	 *
	 * @return void
	 */
	public function test_hex_colors_are_preserved(): void {
		foreach ( array( '#fff', '#2271b1', '#2271b1cc' ) as $hex ) {
			$config = $this->sanitize(
				array(
					'config' => array( 'colors' => array( 'accent' => $hex ) ),
				)
			);

			$this->assertSame( $hex, $config['colors']['accent'] );
		}
	}

	/**
	 * URL v texte sa sanitizuje.
	 *
	 * @return void
	 */
	public function test_texts_are_sanitized(): void {
		$config = $this->sanitize(
			array(
				'config' => array(
					'texts' => array(
						'title' => '<script>alert(1)</script>Názov',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script>', $config['texts']['title'] );
	}

	/**
	 * Chýbajúce kľúče sa doplnia z predvolených.
	 *
	 * @return void
	 */
	public function test_missing_keys_are_filled_from_defaults(): void {
		$config = $this->sanitize( array( 'config' => array( 'layout' => 'bar' ) ) );

		$this->assertSame( 'bar', $config['layout'] );
		$this->assertArrayHasKey( 'colors', $config );
		$this->assertArrayHasKey( 'texts', $config );
		$this->assertArrayHasKey( 'position', $config );
	}

	/**
	 * Prázdny config sa nahradí predvoleným.
	 *
	 * @return void
	 */
	public function test_empty_config_uses_defaults(): void {
		$config = $this->sanitize( array( 'config' => array() ) );

		$this->assertSame( 'default', $config['layout'] );
		$this->assertSame( Banner_Repository::default_config()['colors'], $config['colors'] );
	}

	/**
	 * Slug regiónu sa sanitizuje.
	 *
	 * @return void
	 */
	public function test_region_is_sanitized(): void {
		$row = $this->sanitize_row( array( 'region' => 'EU<script>' ) );

		$this->assertSame( 'euscript', $row['region'] );
	}

	/**
	 * Prázdny región sa nahradí `default`.
	 *
	 * @return void
	 */
	public function test_empty_region_becomes_default(): void {
		$row = $this->sanitize_row( array() );

		$this->assertSame( 'default', $row['region'] );
	}

	/**
	 * Závislá volanie na `sanitize()`.
	 *
	 * @param array<string,mixed> $data Vstup.
	 * @return array<string,mixed>
	 */
	private function sanitize( array $data ): array {
		return $this->sanitize_row( $data )['config'] ?? array();
	}

	/**
	 * Zavolá privátnu metódu `sanitize()` cez reflection.
	 *
	 * Testujeme validáciu bez databázy, takže `sanitize()` je jediná
	 * dostupná cesta.
	 *
	 * @param array<string,mixed> $data Vstupné dáta.
	 * @return array<string,mixed>
	 */
	private function sanitize_row( array $data ): array {
		$method = new ReflectionMethod( $this->repository, 'sanitize' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->repository, $data );

		$this->assertIsArray( $result );

		// Konfigurácia je v DB ako JSON — dekodujeme, aby sa dali
		// kontrolovať hodnoty.
		if ( isset( $result['config'] ) && is_string( $result['config'] ) ) {
			$result['config'] = json_decode( $result['config'], true );
		}

		return $result;
	}
}
