<?php
/**
 * Testy pre REST odpovede — validácia argumentov a mapovanie na repository.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Testy pre argumenty REST endpointov.
 */
#[CoversNothing]
class RestArgsTest extends TestCase {

	/**
	 * Overí, že argumenty pre cookies sú typované.
	 *
	 * Bez `sanitize_callback` by sa do repository dostal hocijaký
	 * vstup — `per_page` by mohol byť záporný a spraviť SQL problém.
	 *
	 * @return void
	 */
	public function test_cookies_args_are_typed(): void {
		$args = $this->get_args( 'cookies_args' );

		foreach ( array( 'category_id', 'search', 'provider', 'page', 'per_page', 'orderby', 'order' ) as $key ) {
			$this->assertArrayHasKey( $key, $args, sprintf( 'Chýba argument "%s".', $key ) );
			$this->assertArrayHasKey( 'type', $args[ $key ] );
			$this->assertArrayHasKey( 'default', $args[ $key ] );
		}
	}

	/**
	 * `per_page` má rozumné minimum.
	 *
	 * @return void
	 */
	public function test_per_page_has_default(): void {
		$args = $this->get_args( 'cookies_args' );

		$this->assertSame( 20, $args['per_page']['default'] );
		$this->assertSame( 1, $args['page']['default'] );
	}

	/**
	 * Argumenty pre logy sú typované.
	 *
	 * @return void
	 */
	public function test_consent_logs_args_are_typed(): void {
		$args = $this->get_args( 'consent_logs_args' );

		foreach ( array( 'action', 'region', 'before', 'after', 'page', 'per_page' ) as $key ) {
			$this->assertArrayHasKey( $key, $args );
			$this->assertArrayHasKey( 'type', $args[ $key ] );
		}
	}

	/**
	 * Kategórie nepotrebujú filtre.
	 *
	 * @return void
	 */
	public function test_categories_args_are_empty(): void {
		$this->assertSame( array(), $this->get_args( 'categories_args' ) );
	}

	/**
	 * Načíta argumenty z kontroléra.
	 *
	 * @param string $method Názov metódy.
	 * @return array<string,mixed>
	 */
	private function get_args( string $method ): array {
		$controller = new \HCC\Admin\Rest_Controller();

		$result = $controller->{$method}();

		$this->assertIsArray( $result );

		return $result;
	}
}
