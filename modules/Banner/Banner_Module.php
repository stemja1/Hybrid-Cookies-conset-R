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
		// TODO (Sesia 5): SSR banneru do wp_footer, inline critical CSS.
	}
}
