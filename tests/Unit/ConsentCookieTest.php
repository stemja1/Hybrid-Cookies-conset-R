<?php
/**
 * Testy pre cookie súhlasu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Tests\Unit;

use HCC\Modules\Consent\Consent_Cookie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Testy validácie a čítania payloadu súhlasu.
 */
#[CoversClass( Consent_Cookie::class )]
class ConsentCookieTest extends TestCase {

	/**
	 * Cookie handler.
	 *
	 * @var Consent_Cookie
	 */
	private Consent_Cookie $cookie;

	/**
	 * Predvolené nastavenie.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->cookie = new Consent_Cookie();
		unset( $_COOKIE[ Consent_Cookie::NAME ] );
	}

	/**
	 * Očistí cookie po teste.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_COOKIE[ Consent_Cookie::NAME ] );

		parent::tearDown();
	}

	/**
	 * Bez cookie nie je súhlas.
	 *
	 * @return void
	 */
	public function test_read_returns_null_without_cookie(): void {
		$this->assertNull( $this->cookie->read() );
	}

	/**
	 * Bez súhlasu je súhlasná iba kategória `necessary`.
	 *
	 * GDPR: súhlas musí byť udelený *pred* spracovaním. Bez uloženého
	 * súhlasu sa nesmie načítať nič okrem nevyhnutného.
	 *
	 * @return void
	 */
	public function test_has_consent_without_consent_only_allows_necessary(): void {
		$this->assertTrue( $this->cookie->has_consent( 'necessary' ) );
		$this->assertFalse( $this->cookie->has_consent( 'marketing' ) );
		$this->assertFalse( $this->cookie->has_consent( 'statistics' ) );
	}

	/**
	 * `necessary` je vždy súhlasná, aj keď je zo súhlasu vypnutá.
	 *
	 * Bez `necessary` by stránka nemala fungovať — je to podmienka
	 * právneho záklona, nie voľba návštevníka.
	 *
	 * @return void
	 */
	public function test_validate_always_includes_necessary(): void {
		$result = $this->cookie->validate(
			array(
				'categories' => array( 'marketing' ),
				'given'      => true,
			)
		);

		$this->assertNotNull( $result );
		$this->assertContains( 'necessary', $result['categories'] );
	}

	/**
	 * Kategórie sa deduplikujú.
	 *
	 * @return void
	 */
	public function test_validate_deduplicates_categories(): void {
		$result = $this->cookie->validate(
			array(
				'categories' => array( 'marketing', 'statistics', 'marketing' ),
				'given'      => true,
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame(
			array( 'marketing', 'statistics', 'necessary' ),
			$result['categories']
		);
	}

	/**
	 * Kategórie sa sanitizujú.
	 *
	 * @return void
	 */
	public function test_validate_sanitizes_categories(): void {
		$result = $this->cookie->validate(
			array(
				'categories' => array( 'Marketing', '  statistics  ', 'invalid!!' ),
				'given'      => true,
			)
		);

		$this->assertNotNull( $result );
		$this->assertContains( 'marketing', $result['categories'] );
		$this->assertContains( 'statistics', $result['categories'] );
		$this->assertNotContains( 'invalid!!', $result['categories'] );
	}

	/**
	 * Neplatný payload vráti null.
	 *
	 * @return void
	 */
	public function test_validate_rejects_non_array(): void {
		$this->assertNull( $this->cookie->validate( 'string' ) );
		$this->assertNull( $this->cookie->validate( 123 ) );
		$this->assertNull( $this->cookie->validate( null ) );
	}

	/**
	 * Prázdne kategórie sa nahradia kategóriou `necessary`.
	 *
	 * @return void
	 */
	public function test_validate_handles_missing_categories(): void {
		$result = $this->cookie->validate( array( 'given' => true ) );

		$this->assertNotNull( $result );
		$this->assertSame( array( 'necessary' ), $result['categories'] );
	}

	/**
	 * Poškodená hodnota cookie sa ignoruje.
	 *
	 * @return void
	 */
	public function test_read_handles_corrupted_cookie(): void {
		$_COOKIE[ Consent_Cookie::NAME ] = 'this-is-not-valid-base64!!';

		$this->assertNull( $this->cookie->read() );
	}

	/**
	 * Poškodená cookie neznamená súhlas v žiadnej kategórii.
	 *
	 * @return void
	 */
	public function test_corrupted_cookie_does_not_grant_categories(): void {
		$_COOKIE[ Consent_Cookie::NAME ] = '!!!';

		$this->assertFalse( $this->cookie->has_consent( 'marketing' ) );
		$this->assertTrue( $this->cookie->has_consent( 'necessary' ) );
	}

	/**
	 * Zaokrúhlený čas sa sanitizuje na celé číslo.
	 *
	 * @return void
	 */
	public function test_validate_casts_timestamp(): void {
		$result = $this->cookie->validate(
			array(
				'categories' => array(),
				'given'      => true,
				't'          => '1234567890abc',
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( 1234567890, $result['timestamp'] );
	}

	/**
	 * Záporný čas sa vyrovná na nulu.
	 *
	 * @return void
	 */
	public function test_validate_clamps_negative_timestamp(): void {
		$result = $this->cookie->validate(
			array(
				'categories' => array(),
				'given'      => true,
				't'          => -500,
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( 0, $result['timestamp'] );
	}

	/**
	 * Verzia bannera je aspoň 1.
	 *
	 * @return void
	 */
	public function test_validate_clamps_banner_version(): void {
		$result = $this->cookie->validate(
			array(
				'categories' => array(),
				'given'      => true,
				'bv'         => 0,
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( 1, $result['banner_version'] );
	}

	/**
	 * Súhlas pre staršiu verziu bannera neplatí.
	 *
	 * Ak sa zmenia texty bannera, návštevník musí súhlasiť znova —
	 * inak by súhlasil s niečím iným, než čo vidí.
	 *
	 * @return void
	 */
	public function test_is_current_returns_false_for_older_banner_version(): void {
		$_COOKIE[ Consent_Cookie::NAME ] = $this->make_cookie(
			array(
				'categories' => array( 'necessary', 'marketing' ),
				'given'      => true,
				't'          => time(),
				'bv'         => 1,
			)
		);

		$this->assertFalse( $this->cookie->is_current( 2 ) );
		$this->assertTrue( $this->cookie->is_current( 1 ) );
	}

	/**
	 * Súhlas pre novšiu verziu bannera platí.
	 *
	 * @return void
	 */
	public function test_is_current_returns_true_for_newer_banner_version(): void {
		$_COOKIE[ Consent_Cookie::NAME ] = $this->make_cookie(
			array(
				'categories' => array( 'necessary' ),
				'given'      => true,
				't'          => time(),
				'bv'         => 5,
			)
		);

		$this->assertTrue( $this->cookie->is_current( 3 ) );
	}

	/**
	 * Bez súhlasu nie je banner aktuálny.
	 *
	 * @return void
	 */
	public function test_is_current_returns_false_without_consent(): void {
		$this->assertFalse( $this->cookie->is_current( 1 ) );
	}

	/**
	 * `given = false` neznamená platný súhlas, aj keď sú kategórie prítomné.
	 *
	 * @return void
	 */
	public function test_unconfirmed_payload_does_not_grant_categories(): void {
		$_COOKIE[ Consent_Cookie::NAME ] = $this->make_cookie(
			array(
				'categories' => array( 'necessary', 'marketing' ),
				'given'      => false,
				't'          => time(),
				'bv'         => 1,
			)
		);

		$this->assertFalse( $this->cookie->has_consent( 'marketing' ) );
		$this->assertSame( array( 'necessary' ), $this->cookie->granted_categories() );
	}

	/**
	 * Vyrobí hodnotu cookie z payloadu.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	private function make_cookie( array $payload ): string {
		return rawurlencode( base64_encode( (string) json_encode( $payload ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
