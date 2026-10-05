<?php
/**
 * Verzia pluginu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jediný zdroj pravdy o verzii pluginu.
 *
 * `Options` potrebuje verziu už pri načítaní schémy — teda ešte predtým,
 * než sa načíta hlavný súbor pluginu s globálnymi konštantami. Preto
 * tu nie je konštanta `HCC_VERSION`, ale konštanta v triede.
 */
final class Version {

	/**
	 * Aktuálna verzia pluginu (semver).
	 */
	public const NUMBER = '0.1.0';

	/**
	 * Minimálna podporovaná verzia PHP.
	 */
	public const MIN_PHP = '8.1';

	/**
	 * Text domain pluginu.
	 */
	public const TEXT_DOMAIN = 'hybrid-cookies-conset-r-plus';

	/**
	 * Vráti verziu ako pole pre WordPress hlavičku.
	 *
	 * @return array{version:string,min_php:string}
	 */
	public static function as_array(): array {
		return array(
			'version' => self::NUMBER,
			'min_php' => self::MIN_PHP,
		);
	}
}
