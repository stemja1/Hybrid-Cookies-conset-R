<?php
/**
 * Tabuľky databázy.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Názvy tabuliek a SQL schéma.
 *
 * Centrálne miesto pre názvy tabuliek — všetky ostatné triedy používajú
 * `Tables::get( 'cookies' )` namiesto skladania stringu s prefixom.
 */
class Tables {

	/**
	 * Prefix tabuliek pluginu (bez prefixu WordPressu).
	 */
	const PREFIX = 'hcc_';

	/**
	 * Názvy tabuliek bez prefixu WordPressu.
	 *
	 * @var array<string,string>
	 */
	private const NAMES = array(
		'categories'   => 'categories',
		'cookies'      => 'cookies',
		'banners'      => 'banners',
		'consent_logs' => 'consent_logs',
	);

	/**
	 * Vráti plný názov tabuľky s prefixom WordPressu.
	 *
	 * @param string $key Kľúč tabuľky.
	 * @return string
	 */
	public static function get( string $key ): string {
		global $wpdb;

		$name = self::NAMES[ $key ] ?? '';

		return '' === $name ? '' : $wpdb->prefix . self::PREFIX . $name;
	}

	/**
	 * Vráti všetky tabuľky pluginu.
	 *
	 * @return array<int,string>
	 */
	public static function all(): array {
		return array_values( array_map( array( self::class, 'get' ), array_keys( self::NAMES ) ) );
	}

	/**
	 * Vráti SQL príkazy na vytvorenie tabuliek pre `dbDelta()`.
	 *
	 * @return array<string,string>
	 */
	public static function get_schema(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$categories   = self::get( 'categories' );
		$cookies      = self::get( 'cookies' );
		$banners      = self::get( 'banners' );
		$consent_logs = self::get( 'consent_logs' );

		$schema = array();

		$schema[] = "CREATE TABLE {$categories} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(64) NOT NULL,
			name VARCHAR(190) NOT NULL,
			description TEXT NOT NULL,
			is_necessary TINYINT(1) NOT NULL DEFAULT 0,
			sell_personal_data TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$cookies} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) NOT NULL,
			provider VARCHAR(190) NOT NULL DEFAULT '',
			domain_pattern VARCHAR(190) NOT NULL DEFAULT '',
			duration VARCHAR(64) NOT NULL DEFAULT '',
			purpose TEXT NOT NULL,
			is_discovered TINYINT(1) NOT NULL DEFAULT 0,
			last_seen DATETIME NULL,
			PRIMARY KEY  (id),
			KEY category_id (category_id),
			KEY provider (provider),
			KEY is_discovered (is_discovered)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$banners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(190) NOT NULL DEFAULT '',
			region VARCHAR(8) NOT NULL DEFAULT 'all',
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			config LONGTEXT NOT NULL,
			version INT NOT NULL DEFAULT 1,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY region (region),
			KEY is_default (is_default)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$consent_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_uuid CHAR(36) NOT NULL,
			banner_version INT NOT NULL DEFAULT 1,
			categories TEXT NOT NULL,
			action ENUM('accept_all','reject_all','custom') NOT NULL DEFAULT 'custom',
			region VARCHAR(8) NOT NULL DEFAULT 'all',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			user_agent_hash CHAR(64) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY consent_uuid (consent_uuid),
			KEY created_at (created_at),
			KEY action (action)
		) {$charset_collate};";

		return $schema;
	}
}
