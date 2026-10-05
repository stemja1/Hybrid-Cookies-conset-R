<?php
/**
 * Seedovanie predvoleného katalógu cookies.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Cookies;

use HCC\Modules\Categories\Categories_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nahrá predvolené cookies z `config/default-cookies.json`.
 *
 * Súbor je mapa `názov cookie => poskytovateľ / kategória / doba platnosti`.
 * Učiteľný text účelu sa generuje z kategórie — každá kategória má vlastný
 * vysvetľujúci popis, ktorý je už uložený v tabuľke kategórií.
 */
class Cookies_Seeder {

	/**
	 * Cesta k JSON súboru.
	 */
	const FILE = 'config/default-cookies.json';

	/**
	 * Repozitár cookies.
	 *
	 * @var Cookies_Repository
	 */
	private Cookies_Repository $repository;

	/**
	 * Repozitár kategórií.
	 *
	 * @var Categories_Repository
	 */
	private Categories_Repository $categories;

	/**
	 * Konštruktór.
	 *
	 * @param Cookies_Repository    $repository Repozitár cookies.
	 * @param Categories_Repository $categories Repozitár kategórií.
	 */
	public function __construct( Cookies_Repository $repository, Categories_Repository $categories ) {
		$this->repository = $repository;
		$this->categories = $categories;
	}

	/**
	 * Načíta predvolené cookies z JSON.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function load_defaults(): array {
		$path = HCC_PLUGIN_DIR . '/' . self::FILE;

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Vloží predvolené cookies, ktoré v katalógi ešte nie sú.
	 *
	 * @return int Počet vložených cookies.
	 */
	public function seed(): int {
		// Slugy kategórií musia existovať, inak by sme nemali kam cookies zaradiť.
		$category_ids = array();

		foreach ( $this->categories->all() as $category ) {
			$category_ids[ $category['slug'] ] = (int) $category['id'];
		}

		if ( ! $category_ids ) {
			return 0;
		}

		$inserted = 0;

		foreach ( $this->load_defaults() as $name => $definition ) {
			// Existujúce záznamy neduplikujeme — používateľ mohol popis upraviť.
			if ( $this->repository->get_by_name( (string) $name ) ) {
				continue;
			}

			$category_slug = (string) ( $definition['category'] ?? 'necessary' );
			$category_id   = $category_ids[ $category_slug ] ?? $category_ids['necessary'] ?? 0;

			if ( $category_id <= 0 ) {
				continue;
			}

			$created = $this->repository->create(
				array(
					'category_id'    => $category_id,
					'name'           => (string) $name,
					'provider'       => (string) ( $definition['provider'] ?? '' ),
					'domain_pattern' => (string) ( $definition['domain_pattern'] ?? '' ),
					'duration'       => (string) ( $definition['duration'] ?? '' ),
					'purpose'        => $this->build_purpose( $category_slug ),
					'is_discovered'  => 0,
				)
			);

			if ( ! is_wp_error( $created ) ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	/**
	 * Vygeneruje text účelu cookie podľa jej kategórie.
	 *
	 * @param string $category_slug Slug kategórie.
	 * @return string
	 */
	private function build_purpose( string $category_slug ): string {
		$category = $this->categories->get_by_slug( $category_slug );

		if ( $category ) {
			return (string) $category->description;
		}

		return __( 'Účel nie je zatiaľ zdokumentovaný.', 'hybrid-cookies-conset-r-plus' );
	}
}
