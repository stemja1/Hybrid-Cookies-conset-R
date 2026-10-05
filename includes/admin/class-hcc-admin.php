<?php
/**
 * Admin menu a načítanie assetov.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin časť pluginu.
 */
class HCC_Admin {

	/**
	 * Hook slug hlavnej stránky.
	 */
	const PAGE_SLUG = 'hcc-cookies';

	/**
	 * Registruje háky.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . HCC_PLUGIN_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Pridá menu do wp-admin.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			self::esc__( 'Cookies conset' ),
			self::esc__( 'Cookies' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-privacy',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			self::esc__( 'Prehľad' ),
			self::esc__( 'Prehľad' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			self::esc__( 'Katalóg cookies' ),
			self::esc__( 'Katalóg cookies' ),
			'manage_options',
			'hcc-catalog',
			array( $this, 'render_catalog' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			self::esc__( 'Nastavenia' ),
			self::esc__( 'Nastavenia' ),
			'manage_options',
			'hcc-settings',
			array( HCC_Settings::class, 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			self::esc__( 'Log súhlasov' ),
			self::esc__( 'Log súhlasov' ),
			'manage_options',
			'hcc-log',
			array( $this, 'render_log' )
		);
	}

	/**
	 * Načíta CSS/JS v admin časti.
	 *
	 * @param string $hook Aktuálna admin stránka.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'hcc-' ) && false === strpos( $hook, 'hcc_' ) ) {
			return;
		}

		wp_enqueue_style(
			'hcc-admin',
			HCC_PLUGIN_URL . 'assets/css/hcc-admin.css',
			array(),
			HCC_VERSION
		);

		wp_enqueue_script(
			'hcc-admin',
			HCC_PLUGIN_URL . 'assets/js/hcc-admin.js',
			array( 'wp-i18n' ),
			HCC_VERSION,
			true
		);

		wp_localize_script(
			'hcc-admin',
			'hccAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'hcc/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'strings'   => array(
					'confirmDelete' => self::esc__( 'Naozaj chcete zmazať tento záznam?' ),
					'saved'         => self::esc__( 'Uložené.' ),
					'error'         => self::esc__( 'Nastala chyba.' ),
				),
			)
		);
	}

	/**
	 * Pridá odporúčanie v zozname pluginov.
	 *
	 * @param array<int,string> $links Odkazy.
	 * @return array<int,string>
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . self::esc__( 'Nastavenia' ) . '</a>' );

		return $links;
	}

	/**
	 * Vykreslí prehľadovú stránku.
	 *
	 * @return void
	 */
	public function render_page() {
		$settings = new HCC_Settings();
		$settings->render();
	}

	/**
	 * Vykreslí katalóg cookies.
	 *
	 * @return void
	 */
	public function render_catalog() {
		$table = new HCC_Cookie_List_Table();
		$table->display();
	}

	/**
	 * Vykreslí log súhlasov.
	 *
	 * @return void
	 */
	public function render_log() {
		// TODO: Implementovať WP_List_Table s logom súhlasov a filtrom podľa dátumu.
		echo '<div class="wrap"><h1>' . self::esc__( 'Log súhlasov' ) . '</h1>';
		echo '<p>' . self::esc__( 'Zatiaľ nie je dostupný detailný prehľad.' ) . '</p></div>';
	}

	/**
	 * Skrátka pre esc_html__ s plugin text domain.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	public static function esc__( $text ) {
		echo esc_html__( $text, 'hybrid-cookies-conset-r-plus' );
	}
}