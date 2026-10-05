<?php
/**
 * Admin menu pluginu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Admin;

use HCC\Capabilities;
use HCC\Module_Registry;
use HCC\Version;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu.
 *
 * Prednost má React SPA v `admin/ui/dist`. Kým build neexistuje, stránky
 * vykreslia `Fallback_Pages`, aby bol plugin spravovateľný aj bez
 * `npm run build`. V Sesii 7 React prevezme všetko.
 */
class Admin_Menu {

	/**
	 * Hlavný slug stránky pluginu.
	 */
	const PAGE_SLUG = 'hybrid-cookies';

	/**
	 * Registry modulov.
	 *
	 * @var Module_Registry
	 */
	private Module_Registry $registry;

	/**
	 * REST kontrolér.
	 *
	 * @var Rest_Controller|null
	 */
	private ?Rest_Controller $rest = null;

	/**
	 * Fallback obrazovky.
	 *
	 * @var Fallback_Pages|null
	 */
	private ?Fallback_Pages $fallback = null;

	/**
	 * Konštruktór.
	 *
	 * @param Module_Registry $registry Registry modulov.
	 */
	public function __construct( Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registruje admin menu.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->get_rest()->register();
		$this->get_fallback()->register();

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . HCC_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'render_module_error_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_settings_notice' ) );
	}

	/**
	 * Pridá hlavné menu a podmenu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$capability = Capabilities::CAPABILITY;

		add_menu_page(
			__( 'Hybrid Cookies conset R+', 'hybrid-cookies-conset-r-plus' ),
			__( 'Cookies', 'hybrid-cookies-conset-r-plus' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-privacy',
			58
		);

		$submenus = array(
			self::PAGE_SLUG             => array(
				'title'  => __( 'Prehľad', 'hybrid-cookies-conset-r-plus' ),
				'render' => array( $this, 'render_dashboard' ),
			),
			'hybrid-cookies-banner'     => array(
				'title'  => __( 'Banner', 'hybrid-cookies-conset-r-plus' ),
				'render' => array( $this, 'render_banner' ),
			),
			'hybrid-cookies-categories' => array(
				'title'  => __( 'Kategórie', 'hybrid-cookies-conset-r-plus' ),
				'render' => array( $this, 'render_categories' ),
			),
			'hybrid-cookies-cookies'    => array(
				'title'  => __( 'Cookies', 'hybrid-cookies-conset-r-plus' ),
				'render' => array( $this, 'render_cookies' ),
			),
			'hybrid-cookies-logs'       => array(
				'title'  => __( 'Log súhlasov', 'hybrid-cookies-conset-r-plus' ),
				'render' => array( $this, 'render_logs' ),
			),
			'hybrid-cookies-settings'   => array(
				'title'  => __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ),
				'render' => array( $this, 'render_settings' ),
			),
		);

		foreach ( $submenus as $slug => $item ) {
			add_submenu_page(
				self::PAGE_SLUG,
				$item['title'],
				$item['title'],
				$capability,
				$slug,
				$item['render']
			);
		}
	}

	/**
	 * Načíta admin assety.
	 *
	 * @param string $hook Aktuálna admin stránka.
	 * @return void
	 */
	public function enqueue_assets( string $hook = '' ): void {
		if ( ! str_contains( $hook, 'hybrid-cookies' ) ) {
			return;
		}

		$asset = $this->get_react_asset();

		if ( $asset ) {
			wp_enqueue_script(
				'hcc-app',
				HCC_PLUGIN_URL . $asset['script'],
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_enqueue_style( 'hcc-app', HCC_PLUGIN_URL . $asset['style'], array(), $asset['version'] );

			return;
		}

		// Build ešte neexistuje — aspoň základné štýly fallbacku.
		wp_enqueue_style(
			'hcc-admin',
			HCC_PLUGIN_URL . 'assets/css/hcc-admin.css',
			array(),
			Version::NUMBER
		);
	}

	/**
	 * Pridá odporúčanie v zozname pluginov.
	 *
	 * @param array<int,string> $links Odkazy.
	 * @return array<int,string>
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ) . '</a>' );

		return $links;
	}

	/**
	 * Prehľad.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( $this->react_is_built() ) {
			$this->render_app_shell( 'dashboard' );

			return;
		}

		$this->get_fallback()->render_dashboard();
	}

	/**
	 * Editor bannera.
	 *
	 * @return void
	 */
	public function render_banner(): void {
		if ( $this->react_is_built() ) {
			$this->render_app_shell( 'banner' );

			return;
		}

		$this->get_fallback()->render_banner();
	}

	/**
	 * Kategórie.
	 *
	 * @return void
	 */
	public function render_categories(): void {
		if ( $this->react_is_built() ) {
			$this->render_app_shell( 'categories' );

			return;
		}

		$this->get_fallback()->render_categories();
	}

	/**
	 * Katalóg cookies.
	 *
	 * @return void
	 */
	public function render_cookies(): void {
		if ( $this->react_is_built() ) {
			$this->render_app_shell( 'cookies' );

			return;
		}

		$this->get_fallback()->render_cookies();
	}

	/**
	 * Log súhlasov.
	 *
	 * @return void
	 */
	public function render_logs(): void {
		if ( $this->react_is_built() ) {
			$this->render_app_shell( 'logs' );

			return;
		}

		$this->get_fallback()->render_logs();
	}

	/**
	 * Nastavenia.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( $this->react_is_built() ) {
			$this->render_app_shell( 'settings' );

			return;
		}

		$this->get_fallback()->render_settings();
	}

	/**
	 * Vykreslí shell pre React aplikáciu.
	 *
	 * @param string $route Počiatočná trasa.
	 * @return void
	 */
	public function render_app_shell( string $route = 'dashboard' ): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$bootstrap = array(
			'version' => Version::NUMBER,
			'restUrl' => esc_url_raw( rest_url( Rest_Controller::NAMESPACE_V1 . '/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'route'   => $route,
			'modules' => $this->registry->get_slugs(),
			'l10n'    => array(
				'title'      => __( 'Hybrid Cookies conset R+', 'hybrid-cookies-conset-r-plus' ),
				'saving'     => __( 'Ukladám…', 'hybrid-cookies-conset-r-plus' ),
				'saved'      => __( 'Uložené.', 'hybrid-cookies-conset-r-plus' ),
				'error'      => __( 'Nastala chyba.', 'hybrid-cookies-conset-r-plus' ),
				'confirmDel' => __( 'Naozaj chcete zmazať tento záznam?', 'hybrid-cookies-conset-r-plus' ),
				'noResults'  => __( 'Žiadne záznamy.', 'hybrid-cookies-conset-r-plus' ),
			),
		);
		?>
		<div class="wrap hcc-admin">
			<h1><?php esc_html_e( 'Hybrid Cookies conset R+', 'hybrid-cookies-conset-r-plus' ); ?></h1>
			<div
				id="hcc-app"
				class="hcc-app"
				data-hcc-bootstrap="<?php echo esc_attr( (string) wp_json_encode( $bootstrap ) ); ?>"
			>
				<p class="hcc-app__loading"><?php esc_html_e( 'Načítavam…', 'hybrid-cookies-conset-r-plus' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Upozorní na moduly, ktoré sa nepodarilo načítať.
	 *
	 * @return void
	 */
	public function render_module_error_notice(): void {
		$failed = $this->registry->get_failed();

		if ( ! $failed ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'Niektoré moduly pluginu sa nepodarilo načítať:', 'hybrid-cookies-conset-r-plus' );
		echo '</strong></p><ul style="list-style:disc;margin-left:20px">';

		foreach ( $failed as $slug => $reason ) {
			echo '<li><code>' . esc_html( $slug ) . '</code>: ' . esc_html( $reason ) . '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * Informuje, že React build ešte neexistuje.
	 *
	 * @return void
	 */
	public function render_settings_notice(): void {
		if ( $this->react_is_built() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! str_contains( (string) $screen->id, 'hybrid-cookies' ) ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		esc_html_e( 'Zobrazujú sa základné PHP obrazovky — React aplikácia zatial nie je zbuildovaná.', 'hybrid-cookies-conset-r-plus' );
		echo ' ';
		esc_html_e( 'Na jej nasadenie spustite:', 'hybrid-cookies-conset-r-plus' );
		echo ' <code>npm install &amp;&amp; npm run build</code>';
		echo '</p></div>';
	}

	/**
	 * Vráti údaje o zbuildovanom React assete.
	 *
	 * `@wordpress/scripts` generuje `index.asset.php` s verziami
	 * závislostí — používame ho, aby sme nemuseli duplikovať zoznam.
	 *
	 * @return array{script:string,style:string,version:string,dependencies:array<int,string>}|null
	 */
	private function get_react_asset(): ?array {
		$asset_file = HCC_PLUGIN_DIR . 'admin/ui/dist/index.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return null;
		}

		$asset = require $asset_file;

		if ( ! is_array( $asset ) ) {
			return null;
		}

		return array(
			'script'       => 'admin/ui/dist/index.js',
			'style'        => 'admin/ui/dist/index.css',
			'version'      => (string) ( $asset['version'] ?? Version::NUMBER ),
			'dependencies' => (array) ( $asset['dependencies'] ?? array() ),
		);
	}

	/**
	 * Vráti, či je React aplikácia zbuildovaná.
	 *
	 * @return bool
	 */
	private function react_is_built(): bool {
		return null !== $this->get_react_asset();
	}

	/**
	 * Vráti REST kontrolér.
	 *
	 * @return Rest_Controller
	 */
	public function get_rest(): Rest_Controller {
		if ( null === $this->rest ) {
			$this->rest = new Rest_Controller();
		}

		return $this->rest;
	}

	/**
	 * Vráti fallback obrazovky.
	 *
	 * @return Fallback_Pages
	 */
	public function get_fallback(): Fallback_Pages {
		if ( null === $this->fallback ) {
			$this->fallback = new Fallback_Pages();
		}

		return $this->fallback;
	}
}