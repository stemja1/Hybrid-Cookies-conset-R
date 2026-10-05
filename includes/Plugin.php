<?php
/**
 * Hlavná trieda pluginu — bootstrap a životný cyklus.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton pluginu.
 *
 * `boot()` je jediná vstupná brána: načíta jadro, načíta moduly a
 * zaregistrovanie ich hookov. Všetko ostatné je už na moduloch.
 */
final class Plugin {

	/**
	 * Singleton inštancia.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Registry modulov.
	 *
	 * @var Module_Registry
	 */
	private Module_Registry $registry;

	/**
	 * I18n modul.
	 *
	 * @var I18n
	 */
	private I18n $i18n;

	/**
	 * Admin menu.
	 *
	 * @var Admin\Admin_Menu
	 */
	private Admin\Admin_Menu $admin;

	/**
	 * Či bol plugin už spustený.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Privátny konšruktor — používaj `instance()`.
	 */
	private function __construct() {
		$this->registry = new Module_Registry();
		$this->i18n     = new I18n();
		$this->admin    = new Admin\Admin_Menu( $this->registry );
	}

	/**
	 * Vráti singleton inštanciu.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Spustí plugin.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->registry->load();
		$this->i18n->register();
		$this->admin->register();

		$this->registry->register_hooks();

		add_action( 'admin_init', array( $this, 'check_version' ) );

		/**
		 * Action po načítaní všetkých modulov.
		 *
		 * @param Plugin $plugin Inštancia pluginu.
		 */
		do_action( 'hcc_loaded', $this );
	}

	/**
	 * Vráti registry modulov.
	 *
	 * @return Module_Registry
	 */
	public function get_registry(): Module_Registry {
		return $this->registry;
	}

	/**
	 * Vráti modul podľa slugu.
	 *
	 * @param string $slug Slug modulu.
	 * @return Abstract_Module|null
	 */
	public function get_module( string $slug ): ?Abstract_Module {
		return $this->registry->get( $slug );
	}

	/**
	 * Vráti admin menu.
	 *
	 * @return Admin\Admin_Menu
	 */
	public function get_admin(): Admin\Admin_Menu {
		return $this->admin;
	}

	/**
	 * Skontroluje, či je databáza aktuálna.
	 *
	 * @return void
	 */
	public function check_version(): void {
		$stored = Options::get( 'version' );

		if ( HCC_VERSION === $stored ) {
			return;
		}

		// Schéma sa mohla zmeniť — pre istotu ju prebehneme znova.
		Activator::create_tables();

		Options::update( 'version', HCC_VERSION );
	}
}
