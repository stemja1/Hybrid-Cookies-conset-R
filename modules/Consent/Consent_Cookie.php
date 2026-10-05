<?php
/**
 * Správa cookie `hcc_consent`.
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
 * Cookie súhlasu.
 *
 * Payload je base64(JSON) — čitateľný v JS bez dešifrovania, zároveň
 * neprezrádza nič, čo by sa dalo zneužiť. Nastavenia cookie sú
 * `HttpOnly = false`, pretože frontend JS musí vedieť, či už bol
 * súhlas udelený, ešte predtým než načíta REST.
 */
class Consent_Cookie {

	/**
	 * Názov cookie.
	 */
	const NAME = 'hcc_consent';

	/**
	 * Až do tejto hodnoty (YYYY-MM-DD) sa platnosť nevypočítava.
	 */
	const SESSION = 'session';

	/**
	 * Zvaliduje payload súhlasu.
	 *
	 * @param mixed $payload Payload z cookie alebo requestu.
	 * @return array{categories:array<int,string>,given:bool,timestamp:int,banner_version:int,uuid:string}|null
	 */
	public function validate( $payload ): ?array {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$categories = isset( $payload['categories'] ) && is_array( $payload['categories'] )
			? array_values( array_unique( array_map( 'sanitize_key', $payload['categories'] ) ) )
			: array();

		// `necessary` je vždy súhlasný, inak by stránka nemala fungovať.
		$categories[] = 'necessary';
		$categories   = array_values( array_unique( $categories ) );

		return array(
			'categories'     => $categories,
			'given'          => ! empty( $payload['given'] ),
			'timestamp'      => isset( $payload['t'] ) ? max( 0, (int) $payload['t'] ) : 0,
			'banner_version' => isset( $payload['bv'] ) ? max( 1, (int) $payload['bv'] ) : 1,
			'uuid'           => isset( $payload['uuid'] ) && is_string( $payload['uuid'] )
				? substr( $payload['uuid'], 0, 36 )
				: '',
		);
	}

	/**
	 * Prečíta súhlas z cookie.
	 *
	 * @return array{categories:array<int,string>,given:bool,timestamp:int,banner_version:int,uuid:string}|null
	 */
	public function read(): ?array {
		if ( empty( $_COOKIE[ self::NAME ] ) ) {
			return null;
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::NAME ] ) );

		return $this->validate( $this->decode( $raw ) );
	}

	/**
	 * Zapíše súhlas do cookie.
	 *
	 * @param array<string,mixed> $payload Validovaný payload.
	 * @return void
	 */
	public function write( array $payload ): void {
		$value = $this->encode( $payload );
		$days  = (int) $this->get_expiry_days();

		if ( headers_sent() ) {
			// V REST odpovedi sú headers poslané — fallback na JS.
			$_COOKIE[ self::NAME ] = $value;

			return;
		}

		setcookie(
			self::NAME,
			$value,
			array(
				'expires'  => $days > 0 ? time() + ( $days * DAY_IN_SECONDS ) : 0,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::NAME ] = $value;
	}

	/**
	 * Vymaže cookie (odvolanie súhlasu).
	 *
	 * @return void
	 */
	public function delete(): void {
		if ( ! headers_sent() ) {
			setcookie(
				self::NAME,
				'',
				array(
					'expires'  => time() - 3600,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		}

		unset( $_COOKIE[ self::NAME ] );
	}

	/**
	 * Overí, či má súhlas pre danú kategóriu.
	 *
	 * Bez uloženého súhlasu je súhlasná iba kategória `necessary` —
	 * GDPR to vyžaduje a zároveň bráni tomu, aby sa skript načítal
	 * vtedy, keď návštevník ešte nič nespravil.
	 *
	 * @param string $category Kategória cookies.
	 * @return bool
	 */
	public function has_consent( string $category ): bool {
		if ( 'necessary' === $category ) {
			return true;
		}

		$consent = $this->read();

		if ( ! $consent || ! $consent['given'] ) {
			return false;
		}

		return in_array( $category, $consent['categories'], true );
	}

	/**
	 * Vráti kategórie, ktoré sú aktuálne súhlasné.
	 *
	 * @return array<int,string>
	 */
	public function granted_categories(): array {
		$consent = $this->read();

		if ( ! $consent || ! $consent['given'] ) {
			return array( 'necessary' );
		}

		return $consent['categories'];
	}

	/**
	 * Zistí, či bol súhlas udelaný pre aktuálnu verziu bannera.
	 *
	 * Ak používateľ zmení texty bannera, starý súhlas už neplatí —
	 * bez toho by návštevník súhlasil s niečím iným, než vidí.
	 *
	 * @param int $current_version Aktuálna verzia bannera.
	 * @return bool
	 */
	public function is_current( int $current_version ): bool {
		$consent = $this->read();

		if ( ! $consent || ! $consent['given'] ) {
			return false;
		}

		return $consent['banner_version'] >= $current_version;
	}

	/**
	 * Zakoduje payload do hodnoty cookie.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	private function encode( array $payload ): string {
		return rawurlencode( base64_encode( (string) wp_json_encode( $payload ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Dekóduje hodnotu cookie.
	 *
	 * @param string $raw Hodnota z cookie.
	 * @return mixed
	 */
	private function decode( string $raw ) {
		$json = base64_decode( rawurldecode( $raw ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $json ) {
			return null;
		}

		return json_decode( (string) $json, true );
	}

	/**
	 * Vráti dobu platnosti súhlasu v dňoch.
	 *
	 * @return int
	 */
	private function get_expiry_days(): int {
		$days = (int) Options::get( 'consent_expiry_days' );

		/**
		 * Filter mení dobu platnosti súhlasu v dňoch.
		 *
		 * @param int $days Počet dní.
		 */
		return (int) apply_filters( 'hcc_consent_expiry_days', $days );
	}
}
