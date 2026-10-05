<?php
/**
 * Modul katalógu cookies.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Cookies;

use HCC\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul katalógu cookies.
 *
 * TODO (Sesia 2): repository s CRUD, `is_discovered`, `last_seen`.
 */
class Cookies_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 6;

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
		// TODO (Sesia 2): repository s CRUD a admin list table.
	}
}
