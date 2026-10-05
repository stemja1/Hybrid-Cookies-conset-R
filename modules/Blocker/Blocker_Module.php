<?php
/**
 * Modul blokovania skriptov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Blocker;

use HCC\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul blocker.
 *
 * Vlastní katalóg poskytovateľov skriptov (`Script_Catalog`) a v Sesii 4
 * aj frontend logiku, ktorá blokuje skripty pred súhlasom.
 */
class Blocker_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 10;

	/**
	 * Katalóg poskytovateľov.
	 *
	 * @var Script_Catalog|null
	 */
	private ?Script_Catalog $catalog = null;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'blocker';
	}

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Blokovanie skriptov', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Zaregistruje hooky modulu.
	 *
	 * @return void
	 */
	public function register(): void {
		// TODO (Sesia 4): enqueue blocker.js a inline konfigurácia v <head>.
		add_action( 'hcc_options_updated', array( $this, 'flush_catalog' ) );
	}

	/**
	 * Vymaže cache katalógu po zmene nastavení.
	 *
	 * @return void
	 */
	public function flush_catalog(): void {
		$this->get_catalog()->flush();
	}

	/**
	 * Vráti katalóg poskytovateľov.
	 *
	 * @return Script_Catalog
	 */
	public function get_catalog(): Script_Catalog {
		if ( null === $this->catalog ) {
			$this->catalog = new Script_Catalog();
		}

		return $this->catalog;
	}
}
