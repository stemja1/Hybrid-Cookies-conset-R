<?php
/**
 * Seedovanie predvolených kategórií.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Categories;

use HCC\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nahrá predvolené kategórie z `config/default-categories.json`.
 *
 * Súbor obsahuje názvy a popisy v oboch jazykoch naraz (`en_US`, `sk_SK`),
 * takže pri aktivácii pluginu funguje aj keď WP nie je nastavený na
 * slovenčinu — názvy sa uložia pre aktuálnu locale.
 */
class Categories_Seeder {

	/**
	 * Cesta k JSON súboru.
	 */
	const FILE = 'config/default-categories.json';

	/**
	 * Repozitár kategórií.
	 *
	 * @var Categories_Repository
	 */
	private Categories_Repository $repository;

	/**
	 * Konštruktór.
	 *
	 * @param Categories_Repository $repository Repozitár kategórií.
	 */
	public function __construct( Categories_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Načíta predvolené kategórie z JSON.
	 *
	 * @return array<string,array<string,mixed>>
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
	 * Vloží predvolené kategórie, ak v tabuľke ešte nie sú.
	 *
	 * Existujúce kategórie sa prepíšuť nedajú — ak používateľ upravil názvy,
	 * reštart pluginu ich musí zachovať.
	 *
	 * @return int Počet vložených kategórií.
	 */
	public function seed(): int {
		// Ak už katalóg obsahuje záznamy, nesiahame doň.
		if ( $this->repository->all() ) {
			return 0;
		}

		$locale   = $this->get_locale();
		$inserted = 0;

		foreach ( $this->load_defaults() as $slug => $definition ) {
			$created = $this->repository->create(
				array(
					'slug'               => (string) $slug,
					'name'               => $this->translate( $definition['name'] ?? '', $locale ),
					'description'        => $this->translate( $definition['description'] ?? '', $locale ),
					'is_necessary'       => (int) ( $definition['is_necessary'] ?? 0 ),
					'sell_personal_data' => (int) ( $definition['sell_personal_data'] ?? 0 ),
					'sort_order'         => (int) ( $definition['sort_order'] ?? 0 ),
				)
			);

			if ( ! is_wp_error( $created ) ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	/**
	 * Vyberie preklad pre danú locale.
	 *
	 * @param mixed  $value Hodnota so štruktúrou `{locale: string}` alebo obyčajný string.
	 * @param string $locale Aktuálna locale.
	 * @return string
	 */
	private function translate( $value, string $locale ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		// Presná zhoda (napr. `sk_SK`).
		if ( isset( $value[ $locale ] ) ) {
			return (string) $value[ $locale ];
		}

		// Jazyková zhoda bez krajiny (napr. `sk` pre `sk_SK`).
		$language = strtolower( substr( $locale, 0, 2 ) );

		foreach ( $value as $key => $text ) {
			if ( strtolower( substr( (string) $key, 0, 2 ) ) === $language ) {
				return (string) $text;
			}
		}

		// Fallback na angličtinu.
		return (string) ( $value[ I18n::FALLBACK_LOCALE ] ?? reset( $value ) );
	}

	/**
	 * Vráti locale, pre ktorú ukladáme názvy.
	 *
	 * @return string
	 */
	private function get_locale(): string {
		$locale = get_locale();

		if ( in_array( $locale, I18n::SUPPORTED_LOCALES, true ) ) {
			return $locale;
		}

		$language = strtolower( substr( $locale, 0, 2 ) );

		foreach ( I18n::SUPPORTED_LOCALES as $supported ) {
			if ( strtolower( substr( $supported, 0, 2 ) ) === $language ) {
				return $supported;
			}
		}

		return I18n::FALLBACK_LOCALE;
	}
}
