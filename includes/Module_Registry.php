<?php
/**
 * Registry modulov — načítanie, zoradenie a prístup k modulom pluginu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Objekt, ktorý drží všetky moduly pluginu.
 */
class Module_Registry {

	/**
	 * Načítané moduly podľa slugu.
	 *
	 * @var array<string,Abstract_Module>
	 */
	private array $modules = array();

	/**
	 * Moduly, ktoré sa nenačítali.
	 *
	 * @var array<string,string>
	 */
	private array $failed = array();

	/**
	 * Zoznam tried modulov, ktoré sa majú načítať.
	 *
	 * Kľúč je slug, hodnota plná názov triedy. Poradie v poli je
	 * zanedbané — moduly sa zoradia podľa priority.
	 *
	 * @return array<string,string>
	 */
	private function get_module_map(): array {
		$map = array(
			'categories' => Modules\Categories\Categories_Module::class,
			'cookies'    => Modules\Cookies\Cookies_Module::class,
			'blocker'    => Modules\Blocker\Blocker_Module::class,
			'consent'    => Modules\Consent\Consent_Module::class,
			'banner'     => Modules\Banner\Banner_Module::class,
		);

		/**
		 * Filter umožňuje pridať alebo odobrať moduly.
		 *
		 * @param array<string,string> $map Slug => názov triedy.
		 */
		return (array) apply_filters( 'hcc_module_map', $map );
	}

	/**
	 * Načíta všetky moduly a zoradí ich podľa priority.
	 *
	 * @return void
	 */
	public function load(): void {
		foreach ( $this->get_module_map() as $slug => $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				$this->failed[ $slug ] = sprintf(
					/* translators: %s: názov triedy modulu. */
					__( 'Trieda modulu %s neexistuje.', 'hybrid-cookies-conset-r-plus' ),
					$class_name
				);

				continue;
			}

			$module = new $class_name();

			if ( ! $module instanceof Abstract_Module ) {
				$this->failed[ $slug ] = sprintf(
					/* translators: %s: názov triedy modulu. */
					__( 'Trieda modulu %s nededičí z Abstract_Module.', 'hybrid-cookies-conset-r-plus' ),
					$class_name
				);

				continue;
			}

			$module->set_registry( $this );

			$this->modules[ $module->get_slug() ] = $module;
		}

		uasort(
			$this->modules,
			static function ( Abstract_Module $a, Abstract_Module $b ): int {
				return $a->get_priority() <=> $b->get_priority();
			}
		);
	}

	/**
	 * Zavolá `register()` na každom aktívnom module.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		/**
		 * Filter umožňuje modul preskočiť.
		 *
		 * @param bool                $skip  Či modul preskočiť.
		 * @param string              $slug  Slug modulu.
		 * @param Abstract_Module     $module Modul.
		 */
		foreach ( $this->modules as $slug => $module ) {
			if ( ! $module->is_enabled() ) {
				continue;
			}

			if ( apply_filters( 'hcc_skip_module', false, $slug, $module ) ) {
				continue;
			}

			$module->register();
		}
	}

	/**
	 * Vráti modul podľa slugu.
	 *
	 * @param string $slug Slug modulu.
	 * @return Abstract_Module|null
	 */
	public function get( string $slug ): ?Abstract_Module {
		return $this->modules[ $slug ] ?? null;
	}

	/**
	 * Vráti všetky načítané moduly.
	 *
	 * @return array<string,Abstract_Module>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Vráti moduly, ktoré sa nepodarilo načítať.
	 *
	 * @return array<string,string>
	 */
	public function get_failed(): array {
		return $this->failed;
	}

	/**
	 * Vráti sludy modulov.
	 *
	 * @return array<int,string>
	 */
	public function get_slugs(): array {
		return array_keys( $this->modules );
	}
}
