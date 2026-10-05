<?php
/**
 * CRUD nad tabuľkou konfigurácií bannera.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Banner;

use HCC\Tables;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repozitár bannerov.
 *
 * Podporuje viacero bannerov naraz (napr. iný text pre EÚ a pre USA).
 * `version` sa inkrementuje pri každej zmene konfigurácie — banner porovná
 * `version` so súhlasom v cookie a pri nezrovnaní vypíše banner znova.
 */
class Banner_Repository {

	/**
	 * Predvolená konfigurácia bannera.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_config(): array {
		return array(
			'layout'          => 'default',
			'position'        => 'bottom',
			'modal'           => false,
			'show_details'    => true,
			'show_reject_all' => true,
			'colors'          => array(
				'background' => '#ffffff',
				'text'       => '#1e1e1e',
				'accent'     => '#2271b1',
				'border'     => '#e0e0e0',
			),
			'texts'           => array(
				'title'       => __( 'Používame cookies', 'hybrid-cookies-conset-r-plus' ),
				'description' => __( 'Používame cookies na zlepšenie webu. Môžete súhlasiť s celou sadou alebo len s vybranými kategóriami.', 'hybrid-cookies-conset-r-plus' ),
				'accept_all'  => __( 'Prijať všetko', 'hybrid-cookies-conset-r-plus' ),
				'reject_all'  => __( 'Odmietnuť', 'hybrid-cookies-conset-r-plus' ),
				'save'        => __( 'Uložiť voľby', 'hybrid-cookies-conset-r-plus' ),
				'settings'    => __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ),
			),
		);
	}

	/**
	 * Načíta všetky bannery.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function all(): array {
		global $wpdb;

		$table = Tables::get( 'banners' );

		if ( ! $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY is_default DESC, region ASC", ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['config'] = $this->decode_config( (string) $row['config'] );
		}

		return $rows;
	}

	/**
	 * Načíta banner pre daný región.
	 *
	 * Priorita: presná zhoda regiónu → predvolený banner → `all`.
	 *
	 * @param string $region Slug regiónu.
	 * @return array<string,mixed>|null
	 */
	public function for_region( string $region ): ?array {
		$region   = sanitize_key( $region );
		$banners  = $this->all();
		$fallback = null;

		foreach ( $banners as $banner ) {
			if ( 'default' === $banner['region'] ) {
				$fallback = $banner;
				continue;
			}

			if ( $region === $banner['region'] ) {
				return $banner;
			}
		}

		return $fallback;
	}

	/**
	 * Načíta jeden banner podľa ID.
	 *
	 * @param int $id ID bannera.
	 * @return array<string,mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		$table = Tables::get( 'banners' );

		if ( ! $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$row['config'] = $this->decode_config( (string) $row['config'] );

		return $row;
	}

	/**
	 * Vytvorí banner.
	 *
	 * @param array<string,mixed> $data Dáta: title, region, is_default, config.
	 * @return int|WP_Error
	 */
	public function create( array $data ) {
		global $wpdb;

		$table = Tables::get( 'banners' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka bannerov neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$clean = $this->sanitize( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $clean );

		if ( false === $result ) {
			return new WP_Error( 'hcc_insert_failed', __( 'Banner sa nepodarilo uložiť.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Aktualizuje banner a inkrementuje verziu.
	 *
	 * @param int                 $id   ID bannera.
	 * @param array<string,mixed> $data Dáta: title, region, is_default, config.
	 * @return true|WP_Error
	 */
	public function update( int $id, array $data ) {
		global $wpdb;

		$table = Tables::get( 'banners' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka bannerov neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$existing = $this->get( $id );

		if ( ! $existing ) {
			return new WP_Error( 'hcc_not_found', __( 'Banner neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$clean = $this->sanitize( $data );

		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// Nová verzia = iné texty. Starý súhlas na ne neplatí.
		$clean['version']    = (int) $existing['version'] + 1;
		$clean['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $table, $clean, array( 'id' => $id ) );

		if ( false === $result ) {
			return new WP_Error( 'hcc_update_failed', __( 'Banner sa nepodarilo aktualizovať.', 'hybrid-cookies-conset-r-plus' ) );
		}

		/**
		 * Action po uložení bannera.
		 *
		 * @param int $id           ID bannera.
		 * @param int $new_version  Nová verzia.
		 */
		do_action( 'hcc_banner_saved', $id, (int) $clean['version'] );

		return true;
	}

	/**
	 * Zmaže banner.
	 *
	 * @param int $id ID bannera.
	 * @return true|WP_Error
	 */
	public function delete( int $id ) {
		global $wpdb;

		$table = Tables::get( 'banners' );

		if ( ! $table ) {
			return new WP_Error( 'hcc_no_table', __( 'Tabuľka bannerov neexistuje.', 'hybrid-cookies-conset-r-plus' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			return new WP_Error( 'hcc_delete_failed', __( 'Banner sa nepodarilo zmazať.', 'hybrid-cookies-conset-r-plus' ) );
		}

		return true;
	}

	/**
	 * Vytvorí predvolený banner, ak žiadny neexistuje.
	 *
	 * @return int ID vytvoreného bannera, 0 ak banner už existuje.
	 */
	public function seed_default(): int {
		if ( $this->all() ) {
			return 0;
		}

		$id = $this->create(
			array(
				'title'      => __( 'Predvolený banner', 'hybrid-cookies-conset-r-plus' ),
				'region'     => 'default',
				'is_default' => 1,
				'config'     => self::default_config(),
			)
		);

		return is_wp_error( $id ) ? 0 : $id;
	}

	/**
	 * Vyčistí a validuje dáta bannera.
	 *
	 * @param array<string,mixed> $data Vstupné dáta.
	 * @return array<string,mixed>|WP_Error
	 */
	private function sanitize( array $data ): array|WP_Error {
		$config = isset( $data['config'] ) && is_array( $data['config'] )
			? $data['config']
			: self::default_config();

		$merged = $this->decode_config( (string) wp_json_encode( $config ) );

		$layout = sanitize_key( (string) $merged['layout'] );

		if ( ! in_array( $layout, array( 'default', 'bar', 'box' ), true ) ) {
			$layout = 'default';
		}

		$position = sanitize_key( (string) $merged['position'] );

		if ( ! in_array( $position, array( 'bottom', 'top' ), true ) ) {
			$position = 'bottom';
		}

		$region = isset( $data['region'] ) ? sanitize_key( (string) $data['region'] ) : 'default';

		return array(
			'title'      => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'region'     => $region,
			'is_default' => ! empty( $data['is_default'] ) ? 1 : 0,
			'config'     => (string) wp_json_encode(
				array(
					'layout'          => $layout,
					'position'        => $position,
					'modal'           => ! empty( $merged['modal'] ),
					'show_details'    => ! empty( $merged['show_details'] ),
					'show_reject_all' => ! empty( $merged['show_reject_all'] ),
					'colors'          => $this->sanitize_colors( $merged['colors'] ),
					'texts'           => $this->sanitize_texts( $merged['texts'] ),
				)
			),
		);
	}

	/**
	 * Vyčistí farby.
	 *
	 * Farby idú do inline `style`, takže musia byť overené — inak by sa
	 * cez banner dala vložiť ľubovoľná deklarácia CSS.
	 *
	 * @param array<string,mixed> $colors Farby.
	 * @return array<string,string>
	 */
	private function sanitize_colors( $colors ): array {
		$defaults = self::default_config()['colors'];
		$result   = array();

		foreach ( $defaults as $key => $default ) {
			$value = isset( $colors[ $key ] ) ? (string) $colors[ $key ] : '';

			// Len hex farby — nič iné nemá čo v inline style hľadať.
			if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
				$result[ $key ] = $value;
				continue;
			}

			$result[ $key ] = $default;
		}

		return $result;
	}

	/**
	 * Vyčistí texty bannera.
	 *
	 * @param array<string,mixed> $texts Texty.
	 * @return array<string,string>
	 */
	private function sanitize_texts( $texts ): array {
		$defaults = self::default_config()['texts'];
		$result   = array();

		foreach ( $defaults as $key => $default ) {
			$result[ $key ] = isset( $texts[ $key ] )
				? sanitize_text_field( (string) $texts[ $key ] )
				: $default;
		}

		return $result;
	}

	/**
	 * Dekóduje JSON konfiguráciu a doplní chýbajúce kľúče.
	 *
	 * @param string $json JSON reťazec.
	 * @return array<string,mixed>
	 */
	private function decode_config( string $json ): array {
		$decoded = json_decode( $json, true );

		return is_array( $decoded )
			? array_replace_recursive( self::default_config(), $decoded )
			: self::default_config();
	}
}
