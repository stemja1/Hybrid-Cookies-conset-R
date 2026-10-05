<?php
/**
 * Zápis súhlasu do lokálnej tabuľky.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Consent;

use HCC\Options;
use HCC\Tables;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recorder súhlasov.
 *
 * Privacy by design: IP adresa ani user agent sa nikdy neukladajú
 * v čitateľnej forme. Ukladá sa iba SHA-256 hash so `wp_salt()` —
 * z neho sa nedá IP získať späť, ale dá sa overiť, že ide o
 * ten istý návštevník.
 */
class Consent_Recorder {

	/**
	 * Tabuľka logov.
	 */
	const TABLE = 'consent_logs';

	/**
	 * Zaznamená súhlas.
	 *
	 * @param array<string,mixed> $payload Validovaný payload súhlasu.
	 * @return string|WP_Error UUID záznamu alebo chyba.
	 */
	public function record( array $payload ) {
		global $wpdb;

		$table = Tables::get( self::TABLE );

		if ( ! $table ) {
			return new WP_Error(
				'hcc_no_table',
				__( 'Tabuľka logov súhlasov neexistuje.', 'hybrid-cookies-conset-r-plus' )
			);
		}

		$uuid = ! empty( $payload['uuid'] ) ? (string) $payload['uuid'] : wp_generate_uuid4();
		$row  = array(
			'consent_uuid'    => $uuid,
			'banner_version'  => max( 1, (int) ( $payload['banner_version'] ?? 1 ) ),
			'categories'      => (string) wp_json_encode( (array) ( $payload['categories'] ?? array() ) ),
			'action'          => $this->normalize_action( (string) ( $payload['action'] ?? 'custom' ) ),
			'region'          => substr( (string) ( $payload['region'] ?? 'all' ), 0, 8 ),
			'ip_hash'         => $this->hash_ip( $this->get_client_ip() ),
			'user_agent_hash' => $this->hash_user_agent( $this->get_user_agent() ),
			'created_at'      => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $row );

		if ( false === $result ) {
			return new WP_Error(
				'hcc_insert_failed',
				__( 'Súhlas sa nepodarilo zaznamenať.', 'hybrid-cookies-conset-r-plus' )
			);
		}

		/**
		 * Action po zapísaní súhlasu.
		 *
		 * @param string $uuid  UUID záznamu.
		 * @param array  $row   Riadok vložený do tabuľky (bez soli, takže
		 *                       sa z neho nedá odvodiť IP).
		 * @param array  $payload Pôvodný payload súhlasu.
		 */
		do_action( 'hcc_consent_recorded', $uuid, $row, $payload );

		return $uuid;
	}

	/**
	 * Načíta posledné záznamy.
	 *
	 * @param array<string,mixed> $args Argumenty: action, region, before, after, per_page, page.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public function recent( array $args = array() ): array {
		global $wpdb;

		$table = Tables::get( self::TABLE );

		if ( ! $table ) {
			return array(
				'rows'  => array(),
				'total' => 0,
			);
		}

		$defaults = array(
			'action'   => '',
			'region'   => '',
			'before'   => '',
			'after'    => '',
			'per_page' => 20,
			'page'     => 1,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['action'] ) {
			$where[]  = 'action = %s';
			$params[] = $this->normalize_action( (string) $args['action'] );
		}

		if ( '' !== $args['region'] ) {
			$where[]  = 'region = %s';
			$params[] = substr( (string) $args['region'], 0, 8 );
		}

		if ( '' !== $args['before'] ) {
			$where[]  = 'created_at < %s';
			$params[] = sanitize_text_field( (string) $args['before'] );
		}

		if ( '' !== $args['after'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = sanitize_text_field( (string) $args['after'] );
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		$total = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: (int) $wpdb->get_var( $count_sql );

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$rows_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( $per_page, $offset ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A );

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Zmaže záznamy staršie ako nastavená retencia.
	 *
	 * @return int Počet zmazaných záznamov.
	 */
	public function cleanup(): int {
		global $wpdb;

		$table = Tables::get( self::TABLE );

		if ( ! $table ) {
			return 0;
		}

		$days = (int) Options::get( 'log_retention_days' );

		if ( $days <= 0 ) {
			return 0;
		}

		$before = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $before ) );
	}

	/**
	 * Načíta súhrn súhlasov po kategóriách.
	 *
	 * @return array<string,array{count:int,percentage:float}>
	 */
	public function summary(): array {
		global $wpdb;

		$table = Tables::get( self::TABLE );

		if ( ! $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT categories, COUNT(*) AS total FROM {$table} GROUP BY categories", ARRAY_A );

		$counts = array();
		$total  = 0;

		foreach ( (array) $rows as $row ) {
			$total += (int) $row['total'];

			$decoded = json_decode( (string) $row['categories'], true );

			foreach ( (array) $decoded as $category ) {
				$category = sanitize_key( (string) $category );

				if ( ! isset( $counts[ $category ] ) ) {
					$counts[ $category ] = 0;
				}

				++$counts[ $category ];
			}
		}

		$summary = array();

		foreach ( $counts as $category => $count ) {
			$summary[ $category ] = array(
				'count'      => $count,
				'percentage' => $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0.0,
			);
		}

		return $summary;
	}

	/**
	 * Overí, či je akcia platná.
	 *
	 * @param string $action Akcia.
	 * @return string
	 */
	private function normalize_action( string $action ): string {
		$allowed = array( 'accept_all', 'reject_all', 'custom' );

		return in_array( $action, $allowed, true ) ? $action : 'custom';
	}

	/**
	 * Zahashuje IP adresu so saltom.
	 *
	 * @param string $ip IP adresa.
	 * @return string
	 */
	private function hash_ip( string $ip ): string {
		if ( '' === $ip ) {
			return '';
		}

		return hash( 'sha256', $ip . wp_salt( 'nonce' ) );
	}

	/**
	 * Zahashuje user agent.
	 *
	 * @param string $user_agent User agent.
	 * @return string|null
	 */
	private function hash_user_agent( string $user_agent ): ?string {
		if ( '' === $user_agent ) {
			return null;
		}

		return hash( 'sha256', $user_agent . wp_salt( 'nonce' ) );
	}

	/**
	 * Zistí IP adresu návštevníka.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$raw   = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$first = trim( (string) explode( ',', $raw )[0] );

			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}

		return '';
	}

	/**
	 * Načíta user agent.
	 *
	 * @return string
	 */
	private function get_user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 );
	}
}
