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
 * Automaticky zablokuje skripty tretích strán pred súhlasom. V MVP je
 * modul zatiaľ bez funkcie.
 *
 * TODO (Sesia 4): MutationObserver + override `document.createElement`,
 * katalóg poskytovateľov v `data/known-cookies.json`.
 */
class Blocker_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 10;

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
		// TODO (Sesia 4): enqueue blocker.js + inline konfigurácia v <head>.
	}
}
