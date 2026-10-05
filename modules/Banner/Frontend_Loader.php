<?php
/**
 * Frontend loader bannera.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Banner;

use HCC\Modules\Consent\Consent_Cookie;
use HCC\Modules\Consent\Region_Resolver;
use HCC\Options;
use HCC\Version;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue a hydratácia bannera.
 */
class Frontend_Loader {

	/**
	 * Handle štýlov bannera.
	 */
	const STYLE_HANDLE = 'hcc-banner';

	/**
	 * Handle skriptu bannera.
	 */
	const SCRIPT_HANDLE = 'hcc-banner';

	/**
	 * Handle štýlov revoke widgetu.
	 */
	const REVOKE_STYLE_HANDLE = 'hcc-revoke';

	/**
	 * Repozitár bannerov.
	 *
	 * @var Banner_Repository|null
	 */
	private ?Banner_Repository $repository = null;

	/**
	 * Renderer bannera.
	 *
	 * @var Banner_Renderer|null
	 */
	private ?Banner_Renderer $renderer = null;

	/**
	 * Consent cookie.
	 *
	 * @var Consent_Cookie|null
	 */
	private ?Consent_Cookie $cookie = null;

	/**
	 * Resolver regiónu.
	 *
	 * @var Region_Resolver|null
	 */
	private ?Region_Resolver $region = null;

	/**
	 * Registruje hooky.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 5 );
		add_action( 'wp_head', array( $this, 'print_critical_css' ), 1 );
		add_action( 'wp_footer', array( $this, 'render_banner' ), 20 );
		add_shortcode( 'hcc_revoke_consent', array( $this, 'revoke_shortcode' ) );
		add_shortcode( 'hcc_cookie_policy_link', array( $this, 'policy_link_shortcode' ) );
	}

	/**
	 * Načíta assety bannera.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		wp_register_style(
			self::STYLE_HANDLE,
			HCC_PLUGIN_URL . 'public/css/banner.css',
			array(),
			Version::NUMBER
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			HCC_PLUGIN_URL . 'public/js/consent-banner.js',
			array(),
			Version::NUMBER,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'hccBanner',
			array(
				'consentUrl' => esc_url_raw( rest_url( 'hcc/v1/consent' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'region'     => $this->get_region()->resolve(),
				'law'        => $this->get_region()->get_law(),
				'optout'     => $this->get_region()->is_optout(),
				'texts'      => $this->get_ui_texts(),
			)
		);

		// `wp_enqueue_style` aj skript — štýly sú už inline v `<head>`,
		// enqueue je fallback pre prípad, že inline CSS zlyhá.
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Vypíše kritické CSS do `<head>`.
	 *
	 * Bez toho by sa banner najprv vykreslil bez štýlu a potom
	 * „poskočil" — presne to je FOUC, ktorý špecifikácia požaduje
	 * odstrániť. Ide o pár desiatok riadkov CSS, čo je prijateľné.
	 *
	 * @return void
	 */
	public function print_critical_css(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		$css = $this->get_critical_css();

		if ( ! $css ) {
			return;
		}

		echo "<style id=\"hcc-critical-css\">\n" . wp_strip_all_tags( $css ) . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Vráti kritické CSS.
	 *
	 * @return string
	 */
	private function get_critical_css(): string {
		$path = HCC_PLUGIN_DIR . 'public/css/banner-critical.css';

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return false === $contents ? '' : $contents;
	}

	/**
	 * Vykreslí banner do päty.
	 *
	 * @return void
	 */
	public function render_banner(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		$banner = $this->get_repository()->for_region( $this->get_region()->resolve() );

		if ( ! $banner ) {
			return;
		}

		$this->get_renderer()->render( $banner, $this->get_cookie()->granted_categories() );
	}

	/**
	 * Shortcode pre revoke tlačidlo.
	 *
	 * `[hcc_revoke_consent label="Nastavenia cookies"]`
	 *
	 * @param array<string,string>|string $atts Atribúty shortcode.
	 * @return string
	 */
	public function revoke_shortcode( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		$atts = shortcode_atts(
			array(
				'label' => $this->get_ui_texts()['revoke'],
				'class' => 'hcc-revoke',
			),
			$atts,
			'hcc_revoke_consent'
		);

		if ( ! $this->should_render() ) {
			return '';
		}

		wp_enqueue_style( self::REVOKE_STYLE_HANDLE );

		return sprintf(
			'<button type="button" class="%s" data-hcc-revoke>%s</button>',
			esc_attr( $atts['class'] ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Shortcode na odkaz na cookie policy.
	 *
	 * Bez nastavenej stránky vráti prázdny string — radšej nič
	 * než odkaz na neexistujúcu stránku.
	 *
	 * @param array<string,string>|string $atts Atribúty shortcode.
	 * @return string
	 */
	public function policy_link_shortcode( $atts ): string {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		$atts = shortcode_atts(
			array(
				'label' => $this->get_ui_texts()['policy'],
				'url'   => '',
			),
			$atts,
			'hcc_cookie_policy_link'
		);

		$url = $this->resolve_policy_url( $atts['url'] );

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<a href="%s" class="hcc-policy-link">%s</a>',
			esc_url( $url ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Určí URL stránky s cookie policy.
	 *
	 * Priorita: explicitný atribút shortcodu → nastavenie v databáze.
	 * Bez nastavenej stránky vráti prázdny string — radšej žiadny odkaz
	 * než odkaz na neexistujúcu stránku 404.
	 *
	 * @param string $url Atribút `url` shortcodu.
	 * @return string
	 */
	private function resolve_policy_url( string $url ): string {
		if ( '' !== $url ) {
			return $url;
		}

		$page_id = (int) get_option( 'hcc_policy_page_id', 0 );

		if ( $page_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Overí, či sa má banner vykresliť.
	 *
	 * @return bool
	 */
	private function should_render(): bool {
		if ( is_admin() || ! Options::get( 'banner_enabled' ) ) {
			return false;
		}

		if ( is_user_logged_in() && ! Options::get( 'show_on_login' ) ) {
			return false;
		}

		return (bool) apply_filters( 'hcc_should_render_banner', true );
	}

	/**
	 * Vráti texty pre revoke widget a policy link.
	 *
	 * @return array{revoke:string,policy:string}
	 */
	private function get_ui_texts(): array {
		return array(
			'revoke' => __( 'Nastavenia cookies', 'hybrid-cookies-conset-r-plus' ),
			'policy' => __( 'Cookie policy', 'hybrid-cookies-conset-r-plus' ),
		);
	}

	/**
	 * Vráti repozitár bannerov.
	 *
	 * @return Banner_Repository
	 */
	public function get_repository(): Banner_Repository {
		if ( null === $this->repository ) {
			$this->repository = new Banner_Repository();
		}

		return $this->repository;
	}

	/**
	 * Vráti renderer.
	 *
	 * @return Banner_Renderer
	 */
	public function get_renderer(): Banner_Renderer {
		if ( null === $this->renderer ) {
			$this->renderer = new Banner_Renderer();
		}

		return $this->renderer;
	}

	/**
	 * Vráti consent cookie.
	 *
	 * @return Consent_Cookie
	 */
	private function get_cookie(): Consent_Cookie {
		if ( null === $this->cookie ) {
			$this->cookie = new Consent_Cookie();
		}

		return $this->cookie;
	}

	/**
	 * Vráti resolver regiónu.
	 *
	 * @return Region_Resolver
	 */
	private function get_region(): Region_Resolver {
		if ( null === $this->region ) {
			$this->region = new Region_Resolver();
		}

		return $this->region;
	}
}
