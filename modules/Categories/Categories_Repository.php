<?php
/**
 * CRUD nad tabuľkou kategórií.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Categories;

use HCC\Tables;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repozitár kategórií súhlasu.
 */
class Categories_Repository {

	/**
	 * Kategórie, ktoré nemožno zmazať (sú nevyhnutné pre GDPR).
	 *
	 * @var array<int,string>
	 */
	private const PROTECTED_SLUGS = array( 'necessary' );

	/**
	 * Načíta všetky kategórie.
	 *
	 * @return array<int,object>
	 */
	public function all(): array {
		global $wpdb;

		$table = Tables::get( 'categories' );

		if ( ! $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC",
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Načíta kategórie ako slug => názov.
	 *
	 * @return array<string,string>
	 */
	public function names(): array {
		$result = array();

		foreach ( $this->all() as $category ) {
			$result[ $category['slug'] ] = $category['name'];
		}

		return $result;
	}

	/**
	 * Načíta jeden záznam.
	 *
	 * @param int $id ID kategórie.
	 * @return object|null
	 */
	public function get( int $id ): ?object {
		global $wpdb;

		$table = Tables::get( 'categories' );

		if ( ! $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Načíta kategóriu podľa slugu.
	 *
	 * @param string $slug Slug kategórie.
	 * @return object|null
	 */
	public function get_by_slug( string $slug ): ?object {
		global $wpdb;

		$table = Tables::get( 'categories' );

		if ( ! $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );
	}

	/**
	 * Vytvorí kategóriu.
	 *
	 * @param array<string,mixed> $data Dáta kategórie.
	 * @return int|WP_Error
	 */
	public function create( array $data ) {
		global $wpdb;

		$table = Tables::get( 'categories' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka kategórií neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$clean = $this->sanitize( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( $this->get_by_slug( $clean['slug'] ) ) {
			return new WP_Error(
				'hcc_duplicate_slug',
				__( 'Kategória s týmto slugom už existuje.', 'hybrid-cookies-conset-r-plus' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $clean );

		if ( false === $result ) {
			return new WP_Error( 'hcc_insert_failed', __( 'Kategóriu sa nepodarilo uložiť.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Aktualizuje kategóriu.
	 *
	 * Nevyhnutnú kategóriu nemožno premeniť na dobrovoľnú.
	 *
	 * @param int                 $id   ID kategórie.
	 * @param array<string,mixed> $data Dáta kategórie.
	 * @return true|WP_Error
	 */
	public function update( int $id, array $data ) {
		global $wpdb;

		$table = Tables::get( 'categories' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka kategórií neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$existing = $this->get( $id );

		if ( ! $existing ) {
			return new WP_Error( 'hcc_not_found', __( 'Kategória neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$clean = $this->sanitize( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		if ( in_array( $existing->slug, self::PROTECTED_SLUGS, true ) && ! $clean['is_necessary'] ) {
			return new WP_Error(
				'hcc_protected_category',
				__( 'Nevyhnutnú kategóriu nemožno premeniť na dobrovoľnú.', 'hybrid-cookies-conset-r-plus' )
			);
		}

		if ( $clean['slug'] !== $existing->slug && $this->get_by_slug( $clean['slug'] ) ) {
			return new WP_Error(
				'hcc_duplicate_slug',
				__( 'Kategória s týmto slugom už existuje.', 'hybrid-cookies-conset-r-plus' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $table, $clean, array( 'id' => $id ) );

		if ( false === $result ) {
			return new WP_Error( 'hcc_update_failed', __( 'Kategóriu sa nepodarilo aktualizovať.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return true;
	}

	/**
	 * Zmaže kategóriu.
	 *
	 * Nevyhnutné kategórie a kategórie, ktoré majú priradené cookies,
	 * sa mazať nedajú — najprv treba presunúť cookies inam.
	 *
	 * @param int $id ID kategórie.
	 * @return true|WP_Error
	 */
	public function delete( int $id ) {
		global $wpdb;

		$table         = Tables::get( 'categories' );
		$cookies_table = Tables::get( 'cookies' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka kategórií neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$existing = $this->get( $id );

		if ( ! $existing ) {
			return new WP_Error( 'hcc_not_found', __( 'Kategória neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		if ( in_array( $existing->slug, self::PROTECTED_SLUGS, true ) ) {
			return new WP_Error(
				'hcc_protected_category',
				__( 'Nevyhnutnú kategóriu nie je možné zmazať.', 'hybrid-cookies-conset-r-plus' )
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$in_use = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$cookies_table} WHERE category_id = %d",
				$id
			)
		);

		if ( $in_use > 0 ) {
			return new WP_Error(
				'hcc_category_in_use',
				sprintf(
					/* translators: %d: počet cookies v kategórii. */
					__( 'Kategória obsahuje %d cookies. Najprv ich priraďte inej kategórii.', 'hybrid-cookies-conset-r-plus' ),
					$in_use
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'hcc_delete_failed', __( 'Kategóriu sa nepodarilo zmazať.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return true;
	}

	/**
	 * Vyčistí a validuje dáta kategórie.
	 *
	 * @param array<string,mixed> $data Vstupné dáta.
	 * @return array<string,mixed>|WP_Error
	 */
	private function sanitize( array $data ): array|WP_Error {
		$slug = isset( $data['slug'] ) ? sanitize_key( $data['slug'] ) : '';

		if ( '' === $slug ) {
			return new WP_Error( 'hcc_missing_slug', __( 'Slug kategórie je povinný.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'hcc_missing_name', __( 'Názov kategórie je povinný.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return array(
			'slug'               => $slug,
			'name'               => $name,
			'description'        => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
			'is_necessary'       => ! empty( $data['is_necessary'] ) ? 1 : 0,
			'sell_personal_data' => ! empty( $data['sell_personal_data'] ) ? 1 : 0,
			'sort_order'         => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
		);
	}
}
