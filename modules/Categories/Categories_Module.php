<?php
/**
 * Modul kategórií súhlasu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Categories;

use HCC\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul kategórií.
 *
 * Kategórie (nevyhnutné / funkčné / štatistické / marketingové) sú základ
 * GDPR súhlasu. Modul vlastní repozitár a seeder predvolených hodnôt.
 */
class Categories_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 5;

	/**
	 * Repozitár kategórií.
	 *
	 * @var Categories_Repository|null
	 */
	private ?Categories_Repository $repository = null;

	/**
	 * Seeder predvolených kategórií.
	 *
	 * @var Categories_Seeder|null
	 */
	private ?Categories_Seeder $seeder = null;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'categories';
	}

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Kategórie súhlasu', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Zaregistruje hooky modulu.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'hcc_activated', array( $this, 'on_activated' ) );
	}

	/**
	 * Založí predvolené kategórie pri aktivácii pluginu.
	 *
	 * @return void
	 */
	public function on_activated(): void {
		$this->get_seeder()->seed();
	}

	/**
	 * Vráti repozitár kategórií.
	 *
	 * @return Categories_Repository
	 */
	public function get_repository(): Categories_Repository {
		if ( null === $this->repository ) {
			$this->repository = new Categories_Repository();
		}

		return $this->repository;
	}

	/**
	 * Vráti seeder.
	 *
	 * @return Categories_Seeder
	 */
	public function get_seeder(): Categories_Seeder {
		if ( null === $this->seeder ) {
			$this->seeder = new Categories_Seeder( $this->get_repository() );
		}

		return $this->seeder;
	}
}
