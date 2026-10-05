<?php
/**
 * Mapa krajín, regiónov a právnych rámcov.
 *
 * Štruktúra je dvojstupňová: krajina (`SK`) odkazuje na región (`eu`),
 * región má typ súhlasu (`optin` / `optout`) a zákon.
 *
 * Filter `hcc_regions` pridáva alebo mení krajiny,
 * filter `hcc_region_definitions` pridáva alebo mení regióny.
 *
 * Poznámka: `apply_filters()` sa volá až pri prístupe cez `hcc_get_regions()`,
 * nie na úrovni súboru — WordPress vtedy ešte nemusí mať načítané pluginy.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Predvolená mapa krajín.
 *
 * @return array<string,array{region:string,law:string}>
 */
function hcc_default_regions(): array {
	$eu = 'eu';

	return array(
		// --- EÚ a EHP (GDPR, opt-in) ---
		'AT' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'BE' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'BG' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'HR' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'CY' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'CZ' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'DE' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'DK' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'EE' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'ES' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'FI' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'FR' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'GR' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'HU' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'IE' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'IT' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'LT' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'LU' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'PL' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'PT' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'RO' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'SE' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'SI' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),
		'SK' => array(
			'region' => $eu,
			'law'    => 'GDPR',
		),

		// --- Spojené kráľovstvo (UK GDPR + PECR, opt-in) ---
		'GB' => array(
			'region' => 'uk',
			'law'    => 'UK_GDPR',
		),

		// --- Švajčiarsko (FADP, opt-in odporúčaný) ---
		'CH' => array(
			'region' => 'ch',
			'law'    => 'FADP',
		),

		// --- USA (štátové zákony, opt-out) ---
		'US' => array(
			'region' => 'us',
			'law'    => 'CCPA',
		),

		// --- Brazília (LGPD, opt-in) ---
		'BR' => array(
			'region' => 'br',
			'law'    => 'LGPD',
		),

		// --- Kanada (PIPEDA, opt-in) ---
		'CA' => array(
			'region' => 'ca',
			'law'    => 'PIPEDA',
		),

		// --- Japonsko (APPI, opt-in) ---
		'JP' => array(
			'region' => 'jp',
			'law'    => 'APPI',
		),

		// --- Austrália (Privacy Act, opt-in) ---
		'AU' => array(
			'region' => 'au',
			'law'    => 'PRIVACY_ACT',
		),
	);
}

/**
 * Predvolené regióny.
 *
 * @return array<string,array{consent_type:string,law:string,label:string}>
 */
function hcc_default_region_definitions(): array {
	return array(
		'eu'      => array(
			'consent_type' => 'optin',
			'law'          => 'GDPR',
			'label'        => __( 'Európska únia', 'hybrid-cookies-conset-r-plus' ),
		),
		'uk'      => array(
			'consent_type' => 'optin',
			'law'          => 'UK_GDPR',
			'label'        => __( 'Spojené kráľovstvo', 'hybrid-cookies-conset-r-plus' ),
		),
		'ch'      => array(
			'consent_type' => 'optin',
			'law'          => 'FADP',
			'label'        => __( 'Švajčiarsko', 'hybrid-cookies-conset-r-plus' ),
		),
		'us'      => array(
			'consent_type' => 'optout',
			'law'          => 'CCPA',
			'label'        => __( 'Spojené štáty', 'hybrid-cookies-conset-r-plus' ),
		),
		'br'      => array(
			'consent_type' => 'optin',
			'law'          => 'LGPD',
			'label'        => __( 'Brazília', 'hybrid-cookies-conset-r-plus' ),
		),
		'ca'      => array(
			'consent_type' => 'optin',
			'law'          => 'PIPEDA',
			'label'        => __( 'Kanada', 'hybrid-cookies-conset-r-plus' ),
		),
		'jp'      => array(
			'consent_type' => 'optin',
			'law'          => 'APPI',
			'label'        => __( 'Japonsko', 'hybrid-cookies-conset-r-plus' ),
		),
		'au'      => array(
			'consent_type' => 'optin',
			'law'          => 'PRIVACY_ACT',
			'label'        => __( 'Austrália', 'hybrid-cookies-conset-r-plus' ),
		),
		'generic' => array(
			'consent_type' => 'optin',
			'law'          => 'GENERIC',
			'label'        => __( 'Ostatné krajiny', 'hybrid-cookies-conset-r-plus' ),
		),
	);
}

/**
 * Vráti mapu krajín s aplikovaným filtrom.
 *
 * @return array<string,array{region:string,law:string}>
 */
function hcc_get_regions(): array {
	static $regions = null;

	if ( null === $regions ) {
		/**
		 * Filter pridáva alebo mení krajiny v mape.
		 *
		 * @param array<string,array{region:string,law:string}> $map Krajina => región a zákon.
		 */
		$regions = (array) apply_filters( 'hcc_regions', hcc_default_regions() );
	}

	return $regions;
}

/**
 * Vráti regióny s aplikovaným filtrom.
 *
 * @return array<string,array{consent_type:string,law:string,label:string}>
 */
function hcc_get_region_definitions(): array {
	static $definitions = null;

	if ( null === $definitions ) {
		/**
		 * Filter pridáva alebo mení regióny.
		 *
		 * @param array<string,array{consent_type:string,law:string,label:string}> $definitions Regióny.
		 */
		$definitions = (array) apply_filters( 'hcc_region_definitions', hcc_default_region_definitions() );
	}

	return $definitions;
}

/**
 * Vráti región pre danú krajinu.
 *
 * @param string $country Dvojmiestny kód krajiny (ISO 3166-1 alpha-2).
 * @return string|null
 */
function hcc_country_to_region( string $country ): ?string {
	$country = strtoupper( trim( $country ) );

	if ( 2 !== strlen( $country ) ) {
		return null;
	}

	$regions = hcc_get_regions();

	return $regions[ $country ]['region'] ?? null;
}
