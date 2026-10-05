<?php
/**
 * Modul súhlasu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Consent;

use HCC\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modul súhlasu.
 *
 * Zodpovedná za `hcc_consent` cookie, zápis do `hcc_consent_logs` a
 * REST endpoint na uloženie súhlasu.
 *
 * TODO (Sesia 3): recorder, cookie handler, region resolver, rate limit.
 */
class Consent_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 20;

	/**
	 * Vráti slug modulu.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'consent';
	}

	/**
	 * Vráti ľudský čitateľný názov modulu.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Súhlas návštevníka', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Zaregistruje hooky modulu.
	 *
	 * @return void
	 */
	public function register(): void {
		// TODO (Sesia 3): REST POST /hcc/v1/consent s nonce a rate-limitom.
	}
}
