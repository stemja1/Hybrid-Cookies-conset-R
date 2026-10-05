<?php
/**
 * Katalóg cookies – CRUD nad tabuľkou cookies a import predvolených záznamov.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Katalóg cookies.
 */
class HCC_Cookie_Catalog {

	/**
	 * Registruje háky.
	 *
	 * @return void
	 */
	public function register() {
		// TODO: Hooky pre automatickú detekciu cookies cez skenovanie HTML.
	}

	/**
	 * Načíta všetky cookies z katalógu.
	 *
	 * @param array<string,mixed> $args Argumenty WP_Query (napr. category, number).
	 * @return array<int,object>
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$table   = HCC_Activator::cookies_table();
		$where   = '';
		$limit   = '';
		$params  = array();

		if ( ! empty( $args['category'] ) ) {
			$where    = 'WHERE category = %s';
			$params[] = $args['category'];
		}

		if ( ! empty( $args['number'] ) ) {
			$limit   = 'LIMIT %d';
			$params[] = (int) $args['number'];
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY category ASC, name ASC {$limit}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where/$limit sú dáta z whitelistu.
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Načíta jeden záznam podľa ID.
	 *
	 * @param int $id ID cookies.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = HCC_Activator::cookies_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Vytvorí alebo upraví záznam.
	 *
	 * @param array<string,mixed> $data Dáta cookies.
	 * @return int|WP_Error ID záznamu alebo chyba.
	 */
	public static function save( $data ) {
		global $wpdb;

		$defaults = array(
			'name'          => '',
			'slug'          => '',
			'description'   => '',
			'provider'      => '',
			'category'      => 'necessary',
			'duration_days' => 0,
			'is_default'    => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		$table = HCC_Activator::cookies_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->insert( $table, $data );
	}

	/**
	 * Zmaže záznam.
	 *
	 * @param int $id ID cookies.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->delete( HCC_Activator::cookies_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Importuje predvolený katalóg cookies (GA, Meta Pixel, YouTube …).
	 *
	 * @return void
	 */
	public static function import_defaults() {
		if ( (int) get_option( 'hcc_defaults_imported', 0 ) ) {
			return;
		}

		$defaults = self::default_cookies();

		foreach ( $defaults as $cookie ) {
			self::save( $cookie );
		}

		update_option( 'hcc_defaults_imported', 1 );
	}

	/**
	 * Predvolený zoznam cookies.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function default_cookies() {
		// TODO: Rozšíriť podľa reálnych skriptov na stránkach.
		return array(
			array(
				'name'          => 'CookieConsent',
				'slug'          => 'cookie-consent',
				'description'   => 'Ukladá rozhodnutie návštevníka o súhlase s cookies.',
				'provider'      => 'První strana',
				'category'      => 'necessary',
				'duration_days' => 180,
				'is_default'    => 1,
			),
			array(
				'name'          => '_ga',
				'slug'          => 'google-analytics',
				'description'   => 'Google Analytics – meranie návštevnosti.',
				'provider'      => 'Google',
				'category'      => 'statistics',
				'duration_days' => 730,
				'is_default'    => 1,
			),
			array(
				'name'          => '_fbp',
				'slug'          => 'facebook-pixel',
				'description'   => 'Meta Pixel – sledovanie konverzií a reklám.',
				'provider'      => 'Meta',
				'category'      => 'marketing',
				'duration_days' => 90,
				'is_default'    => 1,
			),
			array(
				'name'          => 'yt-remote-desktop-id',
				'slug'          => 'youtube-embed',
				'description'   => 'Vložené video YouTube nastavuje pripojenie k serverom YouTube.',
				'provider'      => 'Google',
				'category'      => 'marketing',
				'duration_days' => 180,
				'is_default'    => 1,
			),
		);
	}
}