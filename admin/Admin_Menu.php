<?php
/**
 * Admin menu pluginu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Admin;

use HCC\Capabilities;
use HCC\Version;
use HCC\Module_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu.
 *
 * V Sesii 1 je to len shell â€” strĂˇnky vykreslia placeholder. Obsah
 * strĂˇnok doplnia Ssie 6 (PHP fallback) a 7â€“8 (React).
 */
class Admin_Menu {

	/**
	 * HlavnĂ˝ slug strĂˇnky pluginu.
	 */
	const PAGE_SLUG = 'hybrid-cookies';

	/**
	 * Registry modulov.
	 *
	 * @var Module_Registry
	 */
	private Module_Registry $registry;

	/**
	 * KonĹˇruktor.
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . HCC_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'render_module_error_notice' ) );
	}

	/**
	 * PridĂˇ hlavnĂ© menu a podmenu.
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
			array( $this, 'render_app_shell' ),
			'dashicons-privacy',
			58
		);

		$submenus = array(
			self::PAGE_SLUG             => __( 'PrehÄľad', 'hybrid-cookies-conset-r-plus' ),
			'hybrid-cookies-banner'     => __( 'Banner', 'hybrid-cookies-conset-r-plus' ),
			'hybrid-cookies-categories' => __( 'KategĂłrie', 'hybrid-cookies-conset-r-plus' ),
			'hybrid-cookies-cookies'    => __( 'Cookies', 'hybrid-cookies-conset-r-plus' ),
			'hybrid-cookies-logs'       => __( 'Log sĂşhlasov', 'hybrid-cookies-conset-r-plus' ),
			'hybrid-cookies-settings'   => __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ),
		);

		foreach ( $submenus as $slug => $title ) {
			add_submenu_page(
				self::PAGE_SLUG,
				$title,
				$title,
				$capability,
				$slug,
				array( $this, 'render_app_shell' )
			);
		}
	}

	/**
	 * NaÄŤĂ­ta admin assety.
	 *
	 * @param string $hook AktuĂˇlna admin strĂˇnka.
	 * @return void
	 */
	public function enqueue_assets( string $hook = '' ): void {
		if ( ! str_contains( $hook, 'hybrid-cookies' ) ) {
			return;
		}

		// TODO (Sesia 7): naÄŤĂ­taĹĄ `admin/ui/dist/index.asset.php` z build-u
		// @wordpress/scripts. KĂ˝m build neexistuje, admin CSS nahrĂˇvame ruÄŤne.
		wp_enqueue_style(
			'hcc-admin',
			HCC_PLUGIN_URL . 'assets/css/hcc-admin.css',
			array(),
			Version::NUMBER
		);
	}

	/**
	 * PridĂˇ odporĂşÄŤanie v zozname pluginov.
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
	 * VykreslĂ­ shell pre React aplikĂˇciu.
	 *
	 * @return void
	 */
	public function render_app_shell(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'NemĂˇte oprĂˇvnenie na prĂ­stup k tejto strĂˇnke.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$bootstrap = array(
			'version'  => Version::NUMBER,
			'restUrl'  => esc_url_raw( rest_url( 'hcc/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'pageSlug' => self::PAGE_SLUG,
			'modules'  => $this->registry->get_slugs(),
			'submenus' => array(
				'dashboard'  => __( 'PrehÄľad', 'hybrid-cookies-conset-r-plus' ),
				'banner'     => __( 'Banner', 'hybrid-cookies-conset-r-plus' ),
				'categories' => __( 'KategĂłrie', 'hybrid-cookies-conset-r-plus' ),
				'cookies'    => __( 'Cookies', 'hybrid-cookies-conset-r-plus' ),
				'logs'       => __( 'Log sĂşhlasov', 'hybrid-cookies-conset-r-plus' ),
				'settings'   => __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ),
			),
		);
		?>
		<div class="wrap hcc-admin">
			<h1><?php esc_html_e( 'Hybrid Cookies conset R+', 'hybrid-cookies-conset-r-plus' ); ?></h1>
			<div
				id="hcc-app"
				class="hcc-app"
				data-hcc-bootstrap="<?php echo esc_attr( wp_json_encode( $bootstrap ) ); ?>"
			>
				<p class="hcc-app__loading">
					<?php esc_html_e( 'NaÄŤĂ­tavamâ€¦', 'hybrid-cookies-conset-r-plus' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * UpozornĂ­ na moduly, ktorĂ© sa nepodarilo naÄŤĂ­taĹĄ.
	 *
	 * @return void
	 */
	public function render_module_error_notice(): void {
		$failed = $this->registry->get_failed();

		if ( ! $failed ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'NiektorĂ© moduly pluginu sa nepodarilo naÄŤĂ­taĹĄ:', 'hybrid-cookies-conset-r-plus' );
		echo '</strong></p><ul style="list-style:disc;margin-left:20px">';

		foreach ( $failed as $slug => $reason ) {
			echo '<li><code>' . esc_html( $slug ) . '</code>: ' . esc_html( $reason ) . '</li>';
		}

		echo '</ul></div>';
	}
}