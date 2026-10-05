<?php
/**
 * REST endpoint na uloženie súhlasu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Consent;

use HCC\Modules\Categories\Categories_Module;
use HCC\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller súhlasu.
 */
class Consent_Controller {

	/**
	 * Namespace REST.
	 */
	const NAMESPACE_V1 = 'hcc/v1';

	/**
	 * Cookie súhlasu.
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
	 * Zoznam platných akcií.
	 *
	 * @var array<int,string>
	 */
	private const ACTIONS = array( 'accept_all', 'reject_all', 'custom' );

	/**
	 * Registruje REST trasy.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Zaregistruje endpointy.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/consent',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_consent' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_consent' ),
					'permission_callback' => array( $this, 'verify_request' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/consent/revoke',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'revoke_consent' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);
	}

	/**
	 * Skontroluje nonce a rate limit.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return true|WP_Error
	 */
	public function verify_request( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'hcc_invalid_nonce',
				__( 'Neplatný bezpečnostný token. Obnovte stránku a skúste znova.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->check_rate_limit() ) {
			return new WP_Error(
				'hcc_rate_limited',
				__( 'Príliš veľa požiadaviek. Skúste to o chvíľu.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Skontroluje rate limit.
	 *
	 * Bez limitu by bot mohol zapĺňať tabuľku logov. Identifikátorom je
	 * hash IP, nie IP samotná.
	 *
	 * @return bool
	 */
	private function check_rate_limit(): bool {
		$limit = (int) Options::get( 'rate_limit_per_min' );

		if ( $limit <= 0 ) {
			return true;
		}

		$key   = 'hcc_rate_' . substr( hash( 'sha256', $this->get_rate_key() ), 0, 32 );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Vráti kľúč pre rate limit.
	 *
	 * @return string
	 */
	private function get_rate_key(): string {
		$ip = '';

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $server_key ) {
			if ( empty( $_SERVER[ $server_key ] ) ) {
				continue;
			}

			$raw   = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			$first = trim( (string) explode( ',', $raw )[0] );

			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				$ip = $first;
				break;
			}
		}

		return $ip . wp_salt( 'nonce' );
	}

	/**
	 * Vráti aktuálny stav súhlasu.
	 *
	 * @return WP_REST_Response
	 */
	public function get_consent(): WP_REST_Response {
		$consent = $this->get_cookie()->read();

		return new WP_REST_Response(
			array(
				'given'      => (bool) ( $consent['given'] ?? false ),
				'categories' => $this->get_cookie()->granted_categories(),
				'region'     => $this->get_region()->resolve(),
				'law'        => $this->get_region()->get_law(),
				'optout'     => $this->get_region()->is_optout(),
			),
			200
		);
	}

	/**
	 * Uloží súhlas.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_consent( WP_REST_Request $request ) {
		$categories = $this->sanitize_categories( $request->get_param( 'categories' ) );
		$action     = $this->sanitize_action( (string) $request->get_param( 'action' ) );

		// Akcia musí sedieť s obsahom — `accept_all` bez všetkých kategórií
		// by v logu klamal a pri kontrole súhlasu by to bolo nejednoznačné.
		$all = $this->get_all_slugs();

		if ( 'accept_all' === $action ) {
			$categories = $all;
		} elseif ( 'reject_all' === $action ) {
			$categories = array( 'necessary' );
		}

		$region     = $this->get_region()->resolve();
		$banner_ver = $this->get_banner_version();

		$payload = array(
			'uuid'           => wp_generate_uuid4(),
			'categories'     => $categories,
			'given'          => true,
			't'              => time(),
			'banner_version' => $banner_ver,
		);

		$validated = $this->get_cookie()->validate( $payload );

		if ( ! $validated ) {
			return new WP_Error(
				'hcc_invalid_payload',
				__( 'Neplatné údaje súhlasu.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 400 )
			);
		}

		$this->get_cookie()->write( $validated );

		$recorded = $this->get_recorder()->record(
			array_merge(
				$validated,
				array(
					'action' => $action,
					'region' => $region,
				)
			)
		);

		if ( is_wp_error( $recorded ) ) {
			// Cookie je zapísaná, log nie. Je to horšie ako nič?
			// Súhlas plati, chýba len dôkaz — návštevníka to neblokuje.
			return $recorded;
		}

		/**
		 * Action po udelení súhlasu na frontende.
		 *
		 * @param array $payload Payload súhlasu.
		 * @param string $uuid   UUID záznamu.
		 */
		do_action( 'hcc_frontend_consent_saved', $validated, (string) $recorded );

		return new WP_REST_Response(
			array(
				'uuid'           => (string) $recorded,
				'categories'     => $validated['categories'],
				'banner_version' => $validated['banner_version'],
				'expires'        => time() + ( (int) Options::get( 'consent_expiry_days' ) * DAY_IN_SECONDS ),
			),
			201
		);
	}

	/**
	 * Odvolá súhlas.
	 *
	 * Podľa GDPR má návštevník právo kedykoľvek súhlas odvolať — musí
	 * na to byť dostupná rovnako jednoduchá cesta ako jeho udelenie.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response
	 */
	public function revoke_consent( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$this->get_cookie()->delete();

		$recorded = $this->get_recorder()->record(
			array(
				'uuid'           => wp_generate_uuid4(),
				'categories'     => array( 'necessary' ),
				'given'          => false,
				't'              => time(),
				'banner_version' => $this->get_banner_version(),
				'action'         => 'reject_all',
				'region'         => $this->get_region()->resolve(),
			)
		);

		return new WP_REST_Response(
			array(
				'given'      => false,
				'categories' => array( 'necessary' ),
				'uuid'       => is_wp_error( $recorded ) ? '' : (string) $recorded,
			),
			200
		);
	}

	/**
	 * Vyčistí kategórie a nechá iba tie platné.
	 *
	 * @param mixed $categories Kategórie z requestu.
	 * @return array<int,string>
	 */
	private function sanitize_categories( $categories ): array {
		$categories = is_array( $categories ) ? $categories : array();
		$clean      = array();

		foreach ( $categories as $category ) {
			if ( ! is_string( $category ) ) {
				continue;
			}

			$category = sanitize_key( $category );

			if ( in_array( $category, $this->get_all_slugs(), true ) ) {
				$clean[] = $category;
			}
		}

		$clean[] = 'necessary';

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Overí akciu.
	 *
	 * @param string $action Akcia z requestu.
	 * @return string
	 */
	private function sanitize_action( string $action ): string {
		return in_array( $action, self::ACTIONS, true ) ? $action : 'custom';
	}

	/**
	 * Vráti všetky platné slugs kategórií.
	 *
	 * @return array<int,string>
	 */
	private function get_all_slugs(): array {
		$categories_module = $this->get_module( 'categories' );

		if ( ! $categories_module instanceof Categories_Module ) {
			return array( 'necessary' );
		}

		$slugs = array_keys( $categories_module->get_repository()->names() );

		return $slugs ? $slugs : array( 'necessary' );
	}

	/**
	 * Vráti aktuálnu verziu bannera.
	 *
	 * @return int
	 */
	private function get_banner_version(): int {
		return (int) apply_filters( 'hcc_current_banner_version', 1 );
	}

	/**
	 * Vráti modul pluginu.
	 *
	 * @param string $slug Slug modulu.
	 * @return \HCC\Abstract_Module|null
	 */
	private function get_module( string $slug ) {
		return \HCC\Plugin::instance()->get_module( $slug );
	}

	/**
	 * Vráti cookie handler.
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
	 * Vráti recorder.
	 *
	 * @return Consent_Recorder
	 */
	private function get_recorder(): Consent_Recorder {
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
	private function get_region(): Region_Resolver {
		if ( null === $this->region ) {
			$this->region = new Region_Resolver();
		}

		return $this->region;
	}
}
