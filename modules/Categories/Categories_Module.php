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
 * GDPR súhlasu. V MVP sú tieto moduly zatiaľ registre — implementácia
 * CRUD príde v Sesii 2.
 */
class Categories_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 5;

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
		// TODO (Sesia 2): načítať kategórie do databázy pri aktivácii
		// a poskytnúť REST endpointy na ich CRUD.
	}
}
