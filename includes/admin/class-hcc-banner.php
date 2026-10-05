<?php
/**
 * Consent banner na frontende + blokovanie skriptov.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Banner súhlasu.
 */
class HCC_Banner {

	/**
	 * Registruje háky.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'print_blocking_snippet' ), 1 );
		add_action( 'wp_footer', array( $this, 'render_banner' ) );
	}

	/**
	 * Načíta frontendové assety.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! HCC_Helpers::get_option( 'enabled' ) ) {
			return;
		}

		wp_enqueue_script(
			'hcc-banner',
			HCC_PLUGIN_URL . 'assets/js/hcc-banner.js',
			array(),
			HCC_VERSION,
			false
		);

		wp_localize_script(
			'hcc-banner',
			'hccSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hcc_consent' ),
				'restUrl' => esc_url_raw( rest_url( 'hcc/v1/' ) ),
				'cookie'  => HCC_Consent::COOKIE_NAME,
				'expiry'  => (int) HCC_Helpers::get_option( 'consent_expiry_days', 180 ),
				'mode'    => HCC_Helpers::get_option( 'script_mode' ),
				'position'=> HCC_Helpers::get_option( 'banner_position' ),
				'modal'   => (bool) HCC_Helpers::get_option( 'modal' ),
				'i18n'    => array(
					'title'       => __( 'Používame cookies', 'hybrid-cookies-conset-r-plus' ),
					'description' => __( 'Používame cookies na zlepšenie webu. Môžete súhlasiť s celou sadou alebo len s vybranými kategóriami.', 'hybrid-cookies-conset-r-plus' ),
					'acceptAll'   => __( 'Prijať všetko', 'hybrid-cookies-conset-r-plus' ),
					'rejectAll'   => __( 'Odmietnuť', 'hybrid-cookies-conset-r-plus' ),
					'save'        => __( 'Uložiť voľby', 'hybrid-cookies-conset-r-plus' ),
					'settings'    => __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ),
					'necessary'   => __( 'Nevyhnutné', 'hybrid-cookies-conset-r-plus' ),
					'functional'  => __( 'Funkčné', 'hybrid-cookies-conset-r-plus' ),
					'statistics'  => __( 'Štatistické', 'hybrid-cookies-conset-r-plus' ),
					'marketing'   => __( 'Marketingové', 'hybrid-cookies-conset-r-plus' ),
				),
			)
		);

		wp_enqueue_style(
			'hcc-banner',
			HCC_PLUGIN_URL . 'assets/css/hcc-banner.css',
			array(),
			HCC_VERSION
		);

		$custom_css = (string) HCC_Helpers::get_option( 'custom_css', '' );
		if ( $custom_css ) {
			wp_add_inline_style( 'hcc-banner', wp_strip_all_tags( $custom_css ) );
		}
	}

	/**
	 * Vypíše blokovací snippet do <head>.
	 *
	 * TODO: Využiť WordPress Script API (wp_register_script / strategy) namiesto
	 * ručného snippetu, ak je to možné – WP 6.3+ podporuje wp_script_add_data( 'strategy' ).
	 *
	 * @return void
	 */
	public function print_blocking_snippet() {
		if ( ! HCC_Helpers::get_option( 'enabled' ) ) {
			return;
		}

		if ( 'manual' !== HCC_Helpers::get_option( 'script_mode' ) ) {
			// TODO: Implementovať automatickú detekciu a automatické blokovanie.
			return;
		}

		// Vzor má tvar "substring|kategória", napr. "google-analytics.com|statistics".
		$patterns = (array) HCC_Helpers::get_option( 'block_patterns', array() );
		$patterns = array_values( array_filter( array_map( 'sanitize_text_field', $patterns ) ) );

		if ( ! $patterns ) {
			return;
		}

		$config = array(
			'cookie' => HCC_Consent::COOKIE_NAME,
			'rules'  => $patterns,
		);
		?>
		<script id="hcc-blocking" type="text/javascript">
		window.hccBlocking = <?php echo wp_json_encode( $config ); ?>;
		(function () {
			var cfg = window.hccBlocking || {};

			function grantedCategories() {
				var raw = document.cookie.split('; ').find(function (c) { return c.indexOf(cfg.cookie + '=') === 0; });
				if (!raw) { return null; }
				try {
					var json = decodeURIComponent(raw.split('=').slice(1).join('='));
					var data = JSON.parse(window.atob(json));
					return data && data.given ? (data.categories || []) : null;
				} catch (e) {
					return null;
				}
			}

			function isAllowed(category, granted) {
				if (category === 'necessary') { return true; }
				return granted !== null && granted.indexOf(category) !== -1;
			}

			window.hccIsAllowed = function (category) {
				return isAllowed(category, grantedCategories());
			};

			window.hccBlockedTypes = [];

			function blockIfNeeded() {
				var granted = grantedCategories();
				document.querySelectorAll('script[src]').forEach(function (node) {
					if (node.getAttribute('data-hcc-blocked')) { return; }
					var rule = (cfg.rules || []).find(function (r) {
						var parts = r.split('|');
						return parts.length > 1 && node.src.indexOf(parts[0]) !== -1;
					});
					if (!rule) { return; }
					if (isAllowed(rule.split('|')[1], granted)) { return; }
					node.type = 'text/plain';
					node.setAttribute('data-hcc-blocked', '1');
					window.hccBlockedTypes.push(node.src);
				});
			}

			var observer = new MutationObserver(function () { blockIfNeeded(); });
			observer.observe(document.documentElement, { childList: true, subtree: true });
			blockIfNeeded();
		})();
		</script>
		<?php
	}

	/**
	 * Vykreslí banner (prázdny kontajner, obsah generuje JS).
	 *
	 * @return void
	 */
	public function render_banner() {
		if ( ! HCC_Helpers::get_option( 'enabled' ) ) {
			return;
		}

		if ( is_user_logged_in() && ! HCC_Helpers::get_option( 'show_on_login' ) ) {
			return;
		}
		?>
		<div id="hcc-banner" class="hcc-banner" hidden aria-live="polite" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Používame cookies', 'hybrid-cookies-conset-r-plus' ); ?>">
			<div class="hcc-banner__inner"></div>
		</div>
		<?php
	}
}