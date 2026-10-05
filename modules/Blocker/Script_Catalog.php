<?php
/**
 * Katalóg poskytovateľov skriptov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Blocker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centrálny register poskytovateľov skriptov a ich kategórií.
 *
 * Zdroj údaj je `data/known-cookies.json`. Filter `hcc_script_providers`
 * umožňuje doplniť alebo premapovať položky bez zásahu do JSON súboru.
 */
class Script_Catalog {

	/**
	 * Cesta k JSON súboru.
	 */
	const FILE = 'modules/Blocker/data/known-cookies.json';

	/**
	 * Kľúč transientu.
	 */
	const CACHE_KEY = 'hcc_script_providers';

	/**
	 * Dĺžka platnosti transientu v sekundách (12 hodín).
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Vráti poskytovateľov z JSON súboru.
	 *
	 * @return array<string,array{provider:string,category:string,cookies:array<int,string>}>
	 */
	public function load_from_file(): array {
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
	 * Vráti poskytovateľov s aplikovaným filtrom a transientom.
	 *
	 * Transient sa invaliduje, keď sa filter zmení — WordPress pri
	 * zmene hooku nemení nič v transiente, preto používame aj
	 * kontrolu počtu hookov a `has_filter()`.
	 *
	 * @param bool $force Vynútiť načítanie bez transientu.
	 * @return array<string,array{provider:string,category:string,cookies:array<int,string>}>
	 */
	public function all( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$providers = $this->normalize( $this->load_from_file() );

		/**
		 * Filter pridáva alebo mení poskytovateľov skriptov.
		 *
		 * Klúč je substring URL (napr. `google-analytics.com`),
		 * hodnota je pole s kľúčmi `provider`, `category` a `cookies`.
		 *
		 * @param array<string,array{provider:string,category:string,cookies:array<int,string>}> $providers Poskytovatelia.
		 */
		$filtered = (array) apply_filters( 'hcc_script_providers', $providers );

		$result = $this->normalize( $filtered );

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Nájde poskytovateľa podľa URL skriptu.
	 *
	 * Hľadá sa najdlhší zhodný pattern, aby `googletagmanager.com/ns.html`
	 * vyhral pred `googletagmanager.com`.
	 *
	 * @param string $src URL skriptu.
	 * @return array{provider:string,category:string,cookies:array<int,string>,pattern:string}|null
	 */
	public function match( string $src ): ?array {
		if ( '' === $src ) {
			return null;
		}

		$best        = null;
		$best_length = 0;

		foreach ( $this->all() as $pattern => $definition ) {
			if ( ! str_contains( $src, $pattern ) ) {
				continue;
			}

			$length = strlen( $pattern );

			if ( $length > $best_length ) {
				$best_length = $length;
				$best        = array(
					'pattern'  => (string) $pattern,
					'provider' => $definition['provider'],
					'category' => $definition['category'],
					'cookies'  => $definition['cookies'],
				);
			}
		}

		/**
		 * Filter umožňuje prepísať výsledok vyhľadávania.
		 *
		 * @param array|null $match Zhoda poskytovateľa.
		 * @param string     $src   URL skriptu.
		 */
		return apply_filters( 'hcc_match_provider', $best, $src );
	}

	/**
	 * Vráti kategórie prítomné v katalógu.
	 *
	 * @return array<int,string>
	 */
	public function categories(): array {
		$categories = array();

		foreach ( $this->all() as $definition ) {
			$categories[ $definition['category'] ] = true;
		}

		return array_keys( $categories );
	}

	/**
	 * Vymaže transient.
	 *
	 * Volá sa pri zmene nastavení, aby sa katalóg prečítal znova.
	 *
	 * @return void
	 */
	public function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Skontroluje a vyčistí štruktúru poskytovateľa.
	 *
	 * @param array<string,mixed> $providers Vstupné poskytovatelia.
	 * @return array<string,array{provider:string,category:string,cookies:array<int,string>}>
	 */
	private function normalize( array $providers ): array {
		$allowed_categories = array( 'necessary', 'functional', 'statistics', 'marketing' );
		$result             = array();

		foreach ( $providers as $pattern => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$pattern = trim( (string) $pattern );

			// Pattern bez obsahu by blokoval všetko.
			if ( '' === $pattern ) {
				continue;
			}

			$category = (string) ( $definition['category'] ?? 'marketing' );

			// Neznáma kategória by spôsobila, že by sa cookie nikdy neodblokovala.
			if ( ! in_array( $category, $allowed_categories, true ) ) {
				$category = 'marketing';
			}

			$cookies = isset( $definition['cookies'] ) && is_array( $definition['cookies'] )
				? array_values( array_map( 'strval', $definition['cookies'] ) )
				: array();

			$result[ $pattern ] = array(
				'provider' => (string) ( $definition['provider'] ?? $pattern ),
				'category' => $category,
				'cookies'  => $cookies,
			);
		}

		return $result;
	}
}
