<?php
/**
 * Aktivácia pluginu – vytvorí tabuľky a nastaví predvolené hodnoty.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aktivátor pluginu.
 */
class HCC_Activator {

	/**
	 * Názov tabuľky s katalógom cookies.
	 *
	 * @return string
	 */
	public static function cookies_table() {
		global $wpdb;

		return $wpdb->prefix . 'hcc_cookies';
	}

	/**
	 * Názov tabuľky s logom súhlasov.
	 *
	 * @return string
	 */
	public static function consent_table() {
		global $wpdb;

		return $wpdb->prefix . 'hcc_consent_log';
	}

	/**
	 * Spustí sa pri aktivácii pluginu.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		HCC_Helpers::set_default_options();
		HCC_Cookie_Catalog::import_defaults();

		update_option( 'hcc_version', HCC_VERSION );
	}

	/**
	 * Vytvorí tabuľky databázy.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$cookies_table   = self::cookies_table();
		$consent_table   = self::consent_table();

		// TODO: Skontrolovať schému stĺpcov podľa finálneho dátového modelu.
		$sql_cookies = "CREATE TABLE {$cookies_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			provider VARCHAR(191) NOT NULL DEFAULT '',
			category VARCHAR(32) NOT NULL DEFAULT 'necessary',
			duration_days INT NOT NULL DEFAULT 0,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY category (category)
		) {$charset_collate};";

		$sql_consent = "CREATE TABLE {$consent_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visitor_hash VARCHAR(64) NOT NULL,
			categories TEXT NULL,
			consent_given TINYINT(1) NOT NULL DEFAULT 0,
			ip_anonymized VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY visitor_hash (visitor_hash),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_cookies );
		dbDelta( $sql_consent );
	}
}