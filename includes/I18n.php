<?php
/**
 * Načítanie prekladov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * I18n modul.
 *
 * Plugin má zámerne iba dva jazyky — angličtinu a slovenčinu. Filter
 * `hcc_locale` je otvorený, aby sa dal prekladať aj iný jazyk bez
 * zásahu do kódu.
 */
class I18n {

	/**
	 * Jazyky, ktoré plugin podporuje.
	 *
	 * @var array<int,string>
	 */
	public const SUPPORTED_LOCALES = array( 'en_US', 'sk_SK' );

	/**
	 * Registruje hooky.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		add_filter( 'plugin_locale', array( $this, 'filter_locale' ) );
	}

	/**
	 * Načíta prekladový súbor pluginu.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'hybrid-cookies-conset-r-plus',
			false,
			dirname( HCC_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Prepne locale na `sk_SK`, ak je site nastavené na slovenskú
	 * mutáciu (`sk`), ktorú WordPress nepozná.
	 *
	 * @param string $locale Aktuálna locale.
	 * @return string
	 */
	public function filter_locale( string $locale ): string {
		if ( 0 !== strpos( $locale, 'sk' ) ) {
			return $locale;
		}

		/**
		 * Filter umožňuje vynútiť inú mutáciu slovenského jazyka.
		 *
		 * @param string $locale Aktuálna locale.
		 */
		return (string) apply_filters( 'hcc_locale', 'sk_SK', $locale );
	}
}
