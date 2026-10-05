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
 * Zodpovedná za cookie `hcc_consent`, zápis do `hcc_consent_logs`,
 * určenie regiónu a REST endpointy na uloženie a odvolanie súhlasu.
 */
class Consent_Module extends Abstract_Module {

	/**
	 * Priorita modulu.
	 *
	 * @var int
	 */
	protected int $priority = 20;

	/**
	 * Cookie handler.
	 *
	 * @var Consent_Cookie|null
	 */
	private ?Consent_Cookie $cookie = null;

	/**
	 * Recorder súhlasov.
	 *
	 * @var Consent_Recorder|null
	 */
	private ?Consent_Recorder $recorder = null;

	/**
	 * Resolver regiónu.
	 *
	 * @var Region_Resolver|null
	 */
	private ?Region_Resolver $region = null;

	/**
	 * REST controller.
	 *
	 * @var Consent_Controller|null
	 */
	private ?Consent_Controller $controller = null;

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
		$this->get_controller()->register();

		add_action( 'hcc_activated', array( $this, 'schedule_cleanup' ) );
		add_action( 'hcc_daily_cleanup', array( $this, 'cleanup_logs' ) );

		// Testy a CLI môžu cache regiónu ručne zrušiť.
		add_action( 'hcc_options_updated', array( Region_Resolver::class, 'flush' ) );
	}

	/**
	 * Naplánuje denné čistenie starých logov.
	 *
	 * @return void
	 */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'hcc_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hcc_daily_cleanup' );
		}
	}

	/**
	 * Zmaže logy staršie ako retenčná doba.
	 *
	 * @return void
	 */
	public function cleanup_logs(): void {
		$this->get_recorder()->cleanup();
	}

	/**
	 * Vráti cookie handler.
	 *
	 * @return Consent_Cookie
	 */
	public function get_cookie(): Consent_Cookie {
		if ( null === $this->cookie ) {
			$this->cookie = new Consent_Cookie();
		}

		return $this->cookie;
	}

	/**
	 * Vráti recorder.
	 *
	 * @return Consent_Recorder
	 */
	public function get_recorder(): Consent_Recorder {
		if ( null === $this->recorder ) {
			$this->recorder = new Consent_Recorder();
		}

		return $this->recorder;
	}

	/**
	 * Vráti resolver regiónu.
	 *
	 * @return Region_Resolver
	 */
	public function get_region(): Region_Resolver {
		if ( null === $this->region ) {
			$this->region = new Region_Resolver();
		}

		return $this->region;
	}

	/**
	 * Vráti REST controller.
	 *
	 * @return Consent_Controller
	 */
	public function get_controller(): Consent_Controller {
		if ( null === $this->controller ) {
			$this->controller = new Consent_Controller();
		}

		return $this->controller;
	}
}
