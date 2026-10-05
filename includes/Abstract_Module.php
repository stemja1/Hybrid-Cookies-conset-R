<?php
/**
 * Základná trieda pre všetky moduly pluginu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstraktný modul.
 *
 * Každý modul je samostatná jednotka so svojou zodpovednosťou. Modul
 * deklaruje slug, cez ktorý ho ostatné časti pluginu vyhľadávajú, a
 * registruje vlastné hooky. Plugin ich načíta a zoradí podľa priority,
 * takže pridanie nového modulu neznamená zásah do jadra.
 */
abstract class Abstract_Module {

	/**
	 * Priorita modulu. Nižšie číslo = skoršie načítanie.
	 *
	 * @var int
	 */
	protected int $priority = 10;

	/**
	 * Či je modul aktívny.
	 *
	 * @var bool
	 */
	protected bool $enabled = true;

	/**
	 * Inštancia modulu.
	 *
	 * @var Module_Registry|null
	 */
	private ?Module_Registry $registry = null;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * Zavolá sa raz pri načítaní pluginu.
	 *
	 * Modul tu má zaregistrovať svoje hooky. Nevolá sa pri každom
	 * requeste — iba raz pri `plugins_loaded`.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Priradí modul do registry.
	 *
	 * @param Module_Registry $registry Registry modulov.
	 * @return void
	 */
	public function set_registry( Module_Registry $registry ): void {
		$this->registry = $registry;
	}

	/**
	 * Vráti registry modulov.
	 *
	 * @return Module_Registry|null
	 */
	public function get_registry(): ?Module_Registry {
		return $this->registry;
	}

	/**
	 * Pripojí ďalší modul podľa slugu.
	 *
	 * @param string $slug Slug modulu.
	 * @return Abstract_Module|null
	 */
	public function get_module( string $slug ): ?Abstract_Module {
		return $this->registry?->get( $slug );
	}

	/**
	 * Vráti prioritetu modulu.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Vráti, či je modul aktívny.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Prepne modul zapnutý/vypnutý.
	 *
	 * @param bool $enabled Nový stav.
	 * @return void
	 */
	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}
}
