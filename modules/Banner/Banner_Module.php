<?php
/**
 * Modul bannera.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Banner;

use HCC\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul banner.
 *
 * Consent banner sa renderuje server-side (aby nevznikol FOUC) a JS ho
 * iba hydratuje. V MVP je modul zatiaľ bez funkcie.
 *
 * TODO (Sesia 5): repository, renderer, šablóny, frontend loader.
 */
class Banner_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 30;

	/**
	 * Repozitár bannerov.
	 *
	 * @var Banner_Repository|null
	 */
	private ?Banner_Repository $repository = null;

	/**
	 * Frontend loader.
	 *
	 * @var Frontend_Loader|null
	 */
	private ?Frontend_Loader $loader = null;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'banner';
	}

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Consent banner', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Zaregistruje hooky modulu.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->get_loader()->register();

		add_action( 'hcc_activated', array( $this, 'on_activated' ), 30 );
	}

	/**
	 * Vytvorí predvolený banner pri aktivácii.
	 *
	 * Hook beží s prioritou 30, teda po `hcc_activated` na priorite 10
	 * (kategórie) a 20 (cookies).
	 *
	 * @return void
	 */
	public function on_activated(): void {
		$this->get_repository()->seed_default();
	}

	/**
	 * Vráti repozitár bannerov.
	 *
	 * @return Banner_Repository
	 */
	public function get_repository(): Banner_Repository {
		if ( null === $this->repository ) {
			$this->repository = new Banner_Repository();
		}

		return $this->repository;
	}

	/**
	 * Vráti frontend loader.
	 *
	 * @return Frontend_Loader
	 */
	public function get_loader(): Frontend_Loader {
		if ( null === $this->loader ) {
			$this->loader = new Frontend_Loader();
		}

		return $this->loader;
	}
}
