<?php
/**
 * Modul katalógu cookies.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Cookies;

use HCC\Abstract_Module;
use HCC\Modules\Categories\Categories_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul katalógu cookies.
 */
class Cookies_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 6;

	/**
	 * Repozitár cookies.
	 *
	 * @var Cookies_Repository|null
	 */
	private ?Cookies_Repository $repository = null;

	/**
	 * Seeder predvolených cookies.
	 *
	 * @var Cookies_Seeder|null
	 */
	private ?Cookies_Seeder $seeder = null;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'cookies';
	}

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Katalóg cookies', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Zaregistruje hooky modulu.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'hcc_activated', array( $this, 'on_activated' ), 20 );
	}

	/**
	 * Založí predvolené cookies pri aktivácii.
	 *
	 * Hook beží s prioritou 20, aby po `hcc_activated` na priorite 10
	 * vytvoril kategórie modul Categories — bez kategórií by cookies
	 * nemali kam zaradiť.
	 *
	 * @return void
	 */
	public function on_activated(): void {
		$categories_module = $this->get_module( 'categories' );

		if ( ! $categories_module instanceof Categories_Module ) {
			return;
		}

		$this->get_seeder()->seed();
	}

	/**
	 * Vráti repozitár cookies.
	 *
	 * @return Cookies_Repository
	 */
	public function get_repository(): Cookies_Repository {
		if ( null === $this->repository ) {
			$this->repository = new Cookies_Repository();
		}

		return $this->repository;
	}

	/**
	 * Vráti seeder.
	 *
	 * @return Cookies_Seeder
	 */
	public function get_seeder(): Cookies_Seeder {
		if ( null === $this->seeder ) {
			$categories_module = $this->get_module( 'categories' );

			$categories = $categories_module instanceof Categories_Module
				? $categories_module->get_repository()
				: new \HCC\Modules\Categories\Categories_Repository();

			$this->seeder = new Cookies_Seeder( $this->get_repository(), $categories );
		}

		return $this->seeder;
	}
}
