<?php
/**
 * CRUD nad tabuľkou katalógu cookies.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Cookies;

use HCC\Tables;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repozitár cookies.
 */
class Cookies_Repository {

	/**
	 * Načíta cookies.
	 *
	 * @param array<string,mixed> $args Argumenty: category_id, search, provider, is_discovered, per_page, page, orderby, order.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public function all( array $args = array() ): array {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return array(
				'rows'  => array(),
				'total' => 0,
			);
		}

		$defaults = array(
			'category_id'   => 0,
			'search'        => '',
			'provider'      => '',
			'is_discovered' => null,
			'per_page'      => 20,
			'page'          => 1,
			'orderby'       => 'name',
			'order'         => 'ASC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$params = array();

		if ( $args['category_id'] > 0 ) {
			$where[]  = 'category_id = %d';
			$params[] = (int) $args['category_id'];
		}

		if ( '' !== $args['search'] ) {
			$where[]  = '(name LIKE %s OR provider LIKE %s OR domain_pattern LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $args['provider'] ) {
			$where[]  = 'provider = %s';
			$params[] = $args['provider'];
		}

		if ( null !== $args['is_discovered'] ) {
			$where[]  = 'is_discovered = %d';
			$params[] = (int) $args['is_discovered'];
		}

		$where_sql = implode( ' AND ', $where );

		// `orderby` a `order` nesmú byť používateľský vstup — whitelist.
		$allowed_orderby = array( 'id', 'name', 'provider', 'domain_pattern', 'last_seen', 'category_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$rows_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( $per_page, $offset ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A );

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Načíta jeden záznam.
	 *
	 * @param int $id ID cookie.
	 * @return object|null
	 */
	public function get( int $id ): ?object {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Načíta cookies podľa názvu cookie.
	 *
	 * @param string $name Názov cookie.
	 * @return object|null
	 */
	public function get_by_name( string $name ): ?object {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE name = %s", $name ) );
	}

	/**
	 * Načíta cookies zoskupené podľa kategórie.
	 *
	 * @return array<int,object> Záznamy s pridaným poľom `category_slug`.
	 */
	public function grouped_by_category(): array {
		global $wpdb;

		$cookies_table    = Tables::get( 'cookies' );
		$categories_table = Tables::get( 'categories' );

		if ( ! $cookies_table || ! $categories_table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT co.*, cat.slug AS category_slug, cat.name AS category_name
			FROM {$cookies_table} co
			INNER JOIN {$categories_table} cat ON cat.id = co.category_id
			ORDER BY cat.sort_order ASC, co.name ASC",
			ARRAY_A
		);

		$grouped = array();

		foreach ( (array) $rows as $row ) {
			$grouped[ $row['category_slug'] ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Načíta zoznam poskytovateľov pre filter.
	 *
	 * @return array<int,string>
	 */
	public function providers(): array {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col( "SELECT DISTINCT provider FROM {$table} WHERE provider != '' ORDER BY provider ASC" );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Vytvorí záznam.
	 *
	 * @param array<string,mixed> $data Dáta cookie.
	 * @return int|WP_Error
	 */
	public function create( array $data ) {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka cookies neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$clean = $this->sanitize( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $clean );

		if ( false === $result ) {
			return new WP_Error( 'hcc_insert_failed', __( 'Cookie sa nepodarilo uložiť.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Aktualizuje záznam.
	 *
	 * @param int                 $id   ID cookie.
	 * @param array<string,mixed> $data Dáta cookie.
	 * @return true|WP_Error
	 */
	public function update( int $id, array $data ) {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka cookies neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		if ( ! $this->get( $id ) ) {
			return new WP_Error( 'hcc_not_found', __( 'Cookie neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$clean = $this->sanitize( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $table, $clean, array( 'id' => $id ) );

		if ( false === $result ) {
			return new WP_Error( 'hcc_update_failed', __( 'Cookie sa nepodarilo aktualizovať.', 'hybrid-cookies-conset-r-plus' ) );
		}

		/**
		 * Action po zmene katalógu cookies.
		 *
		 * @param int   $id   ID cookie.
		 * @param array $clean Nové dáta.
		 */
		do_action( 'hcc_cookie_updated', $id, $clean );

		return true;
	}

	/**
	 * Zmaže záznam.
	 *
	 * @param int $id ID cookie.
	 * @return true|WP_Error
	 */
	public function delete( int $id ) {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka cookies neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'hcc_delete_failed', __( 'Cookie sa nepodarilo zmazať.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return true;
	}

	/**
	 * Hromadne priradí cookies ku kategórii.
	 *
	 * @param array<int,int> $ids         ID cookies.
	 * @param int            $category_id ID kategórie.
	 * @return int Počet upravených záznamov.
	 */
	public function assign_category( array $ids, int $category_id ): int {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table || ! $ids ) {
			return 0;
		}

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( $category_id ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET category_id = %d WHERE id IN ({$placeholders})",
				$params
			)
		);
	}

	/**
	 * Označí, že cookies boli práve zistené.
	 *
	 * @param int $id ID cookie.
	 * @return void
	 */
	public function touch_seen( int $id ): void {
		global $wpdb;

		$table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'last_seen' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Vyčistí a validuje dáta cookie.
	 *
	 * @param array<string,mixed> $data Vstupné dáta.
	 * @return array<string,mixed>|WP_Error
	 */
	private function sanitize( array $data ): array|WP_Error {
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'hcc_missing_name', __( 'Názov cookie je povinný.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$category_id = isset( $data['category_id'] ) ? (int) $data['category_id'] : 0;

		if ( $category_id <= 0 ) {
			return new WP_Error( 'hcc_missing_category', __( 'Je potrebné vybrať kategóriu.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return array(
			'category_id'    => $category_id,
			'name'           => $name,
			'provider'       => isset( $data['provider'] ) ? sanitize_text_field( (string) $data['provider'] ) : '',
			'domain_pattern' => isset( $data['domain_pattern'] ) ? sanitize_text_field( (string) $data['domain_pattern'] ) : '',
			'duration'       => isset( $data['duration'] ) ? sanitize_text_field( (string) $data['duration'] ) : '',
			'purpose'        => isset( $data['purpose'] ) ? sanitize_textarea_field( (string) $data['purpose'] ) : '',
			'is_discovered'  => ! empty( $data['is_discovered'] ) ? 1 : 0,
			'last_seen'      => isset( $data['last_seen'] ) && $data['last_seen']
				? sanitize_text_field( (string) $data['last_seen'] )
				: null,
		);
	}
}
