<?php
/**
 * REST API pre kategórie, cookies, bannery a logy súhlasov.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Admin;

use HCC\Capabilities;
use HCC\Modules\Banner\Banner_Module;
use HCC\Modules\Banner\Banner_Repository;
use HCC\Modules\Categories\Categories_Module;
use HCC\Modules\Consent\Consent_Module;
use HCC\Modules\Cookies\Cookies_Module;
use HCC\Options;
use HCC\Plugin;
use HCC\Version;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST kontrolér admin časti.
 *
 * Všetky endpointy sú chránené `permission_callback` — čítanie katalógu
 * cookies nie je na škodu, ale zápis áno. `__return_true` tu nie je
 * nikde.
 */
class Rest_Controller {

	/**
	 * Namespace REST.
	 */
	const NAMESPACE_V1 = 'hcc/v1';

	/**
	 * Registruje hooky.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Zaregistruje všetky trasy.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$this->register_collection(
			'categories',
			array(
				'index'      => array( $this, 'categories' ),
				'index_args' => array( $this, 'categories_args' ),
				'show'       => array( $this, 'show_category' ),
				'create'     => array( $this, 'create_category' ),
				'update'     => array( $this, 'update_category' ),
				'destroy'    => array( $this, 'destroy_category' ),
			)
		);

		$this->register_collection(
			'cookies',
			array(
				'index'      => array( $this, 'cookies' ),
				'index_args' => array( $this, 'cookies_args' ),
				'show'       => array( $this, 'show_cookie' ),
				'create'     => array( $this, 'create_cookie' ),
				'update'     => array( $this, 'update_cookie' ),
				'destroy'    => array( $this, 'destroy_cookie' ),
			)
		);

		$this->register_collection(
			'banners',
			array(
				'index'   => array( $this, 'banners' ),
				'show'    => array( $this, 'show_banner' ),
				'create'  => array( $this, 'create_banner' ),
				'update'  => array( $this, 'update_banner' ),
				'destroy' => array( $this, 'destroy_banner' ),
			)
		);

		$this->register_collection(
			'consent-logs',
			array(
				'index'      => array( $this, 'consent_logs' ),
				'index_args' => array( $this, 'consent_logs_args' ),
			)
		);

		$this->register_collection(
			'settings',
			array(
				'index'   => array( $this, 'settings' ),
				'show'    => array( $this, 'show_setting' ),
				'create'  => array( $this, 'create_setting' ),
				'update'  => array( $this, 'update_setting' ),
				'destroy' => array( $this, 'destroy_setting' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/banner-defaults',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'banner_defaults' ),
				'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/cookies/bulk-category',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_assign_category' ),
				'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * Zaregistruje CRUD trasy pre daný zdroj.
	 *
	 * @param string              $slug      Slug zdroja.
	 * @param array<string,mixed> $callbacks Mapovanie operácií (`index`, `show`,
	 *                                      `create`, `update`, `destroy`) na callbacky.
	 * @return void
	 */
	private function register_collection( string $slug, array $callbacks ): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/' . $slug,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => $callbacks['index'],
					'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
					'args'                => $callbacks['index_args'] ?? array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => $callbacks['create'],
					'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/' . $slug . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => $callbacks['show'],
					'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => $callbacks['update'],
					'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => $callbacks['destroy'],
					'permission_callback' => array( Capabilities::class, 'rest_permission_check' ),
				),
			)
		);
	}

	/*
	-----------------------------------------------------------------------
	 * Kategórie
	 */

	/**
	 * Vráti zoznam argumentov pre index kategórií.
	 *
	 * @return array<string,mixed>
	 */
	public function categories_args(): array {
		return array();
	}

	/**
	 * Zoznam kategórií.
	 *
	 * @return WP_REST_Response
	 */
	public function categories(): WP_REST_Response {
		$repository = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_REST_Response( array(), 200 );
		}

		return new WP_REST_Response( $repository->all(), 200 );
	}

	/**
	 * Jedna kategória.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show_category( WP_REST_Request $request ) {
		$repository = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$category = $repository->get( (int) $request['id'] );

		if ( ! $category ) {
			return new WP_Error(
				'hcc_not_found',
				__( 'Kategória neexistuje.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $category, 200 );
	}

	/**
	 * Vytvorí kategóriu.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_category( WP_REST_Request $request ) {
		$repository = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$id = $repository->create( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $this->to_rest_error( $id );
		}

		return new WP_REST_Response( $repository->get( $id ), 201 );
	}

	/**
	 * Aktualizuje kategóriu.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_category( WP_REST_Request $request ) {
		$repository = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$result = $repository->update( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		return new WP_REST_Response( $repository->get( (int) $request['id'] ), 200 );
	}

	/**
	 * Zmaže kategóriu.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy_category( WP_REST_Request $request ) {
		$repository = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$result = $repository->delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		return new WP_REST_Response( null, 204 );
	}

	/*
	-----------------------------------------------------------------------
	 * Cookies
	 */

	/**
	 * Vráti argumenty pre index cookies.
	 *
	 * @return array<string,mixed>
	 */
	public function cookies_args(): array {
		return array(
			'category_id'   => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'search'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'provider'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'is_discovered' => array(
				'type'    => 'boolean',
				'default' => null,
			),
			'page'          => array(
				'type'    => 'integer',
				'default' => 1,
			),
			'per_page'      => array(
				'type'    => 'integer',
				'default' => 20,
			),
			'orderby'       => array(
				'type'    => 'string',
				'default' => 'name',
			),
			'order'         => array(
				'type'    => 'string',
				'default' => 'ASC',
			),
		);
	}

	/**
	 * Zoznam cookies.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response
	 */
	public function cookies( WP_REST_Request $request ) {
		$repository = $this->get_cookies_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_REST_Response(
				array(
					'rows'  => array(),
					'total' => 0,
				),
				200
			);
		}

		$result = $repository->all(
			array(
				'category_id'   => (int) $request->get_param( 'category_id' ),
				'search'        => (string) $request->get_param( 'search' ),
				'provider'      => (string) $request->get_param( 'provider' ),
				'is_discovered' => null === $request->get_param( 'is_discovered' )
					? null
					: (bool) $request->get_param( 'is_discovered' ),
				'page'          => (int) $request->get_param( 'page' ),
				'per_page'      => (int) $request->get_param( 'per_page' ),
				'orderby'       => (string) $request->get_param( 'orderby' ),
				'order'         => (string) $request->get_param( 'order' ),
			)
		);

		$result['providers'] = $repository->providers();
		$result['page']      = (int) $request->get_param( 'page' );
		$result['per_page']  = (int) $request->get_param( 'per_page' );
		$result['pages']     = (int) ceil( $result['total'] / max( 1, $result['per_page'] ) );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Jedna cookie.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show_cookie( WP_REST_Request $request ) {
		$repository = $this->get_cookies_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$cookie = $repository->get( (int) $request['id'] );

		if ( ! $cookie ) {
			return new WP_Error(
				'hcc_not_found',
				__( 'Cookie neexistuje.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $cookie, 200 );
	}

	/**
	 * Vytvorí cookie.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_cookie( WP_REST_Request $request ) {
		$repository = $this->get_cookies_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$id = $repository->create( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $this->to_rest_error( $id );
		}

		return new WP_REST_Response( $repository->get( $id ), 201 );
	}

	/**
	 * Aktualizuje cookie.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_cookie( WP_REST_Request $request ) {
		$repository = $this->get_cookies_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$result = $repository->update( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		return new WP_REST_Response( $repository->get( (int) $request['id'] ), 200 );
	}

	/**
	 * Zmaže cookie.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy_cookie( WP_REST_Request $request ) {
		$repository = $this->get_cookies_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$result = $repository->delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Hromadne priradí cookies ku kategórii.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_assign_category( WP_REST_Request $request ) {
		$repository = $this->get_cookies_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$ids         = array_map( 'intval', (array) $request->get_param( 'ids' ) );
		$category_id = (int) $request->get_param( 'category_id' );

		if ( ! $ids ) {
			return new WP_Error(
				'hcc_missing_ids',
				__( 'Nevybrali ste žiadne cookies.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 400 )
			);
		}

		if ( $category_id <= 0 ) {
			return new WP_Error(
				'hcc_missing_category',
				__( 'Je potrebné vybrať kategóriu.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array( 'updated' => $repository->assign_category( $ids, $category_id ) ),
			200
		);
	}

	/*
	-----------------------------------------------------------------------
	 * Bannery
	 */

	/**
	 * Zoznam bannerov.
	 *
	 * @return WP_REST_Response
	 */
	public function banners(): WP_REST_Response {
		$repository = $this->get_banner_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_REST_Response( array(), 200 );
		}

		return new WP_REST_Response( $repository->all(), 200 );
	}

	/**
	 * Jeden banner.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show_banner( WP_REST_Request $request ) {
		$repository = $this->get_banner_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$banner = $repository->get( (int) $request['id'] );

		if ( ! $banner ) {
			return new WP_Error(
				'hcc_not_found',
				__( 'Banner neexistuje.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $banner, 200 );
	}

	/**
	 * Vytvorí banner.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_banner( WP_REST_Request $request ) {
		$repository = $this->get_banner_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$id = $repository->create( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $this->to_rest_error( $id );
		}

		return new WP_REST_Response( $repository->get( $id ), 201 );
	}

	/**
	 * Aktualizuje banner.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_banner( WP_REST_Request $request ) {
		$repository = $this->get_banner_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$result = $repository->update( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		return new WP_REST_Response( $repository->get( (int) $request['id'] ), 200 );
	}

	/**
	 * Zmaže banner.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy_banner( WP_REST_Request $request ) {
		$repository = $this->get_banner_module()?->get_repository();

		if ( ! $repository ) {
			return new WP_Error( 'hcc_no_module', $this->module_error(), array( 'status' => 503 ) );
		}

		$result = $repository->delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Vráti predvolenú konfiguráciu bannera.
	 *
	 * Frontend používa túto hodnotu pri vytváraní nového bannera,
	 * takže nemusí duplikovať predvolené hodnoty v JavaScript-e.
	 *
	 * @return WP_REST_Response
	 */
	public function banner_defaults(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'config'    => Banner_Repository::default_config(),
				'layouts'   => array( 'default', 'bar', 'box' ),
				'positions' => array( 'bottom', 'top' ),
			),
			200
		);
	}

	/*
	-----------------------------------------------------------------------
	 * Log súhlasov
	 */

	/**
	 * Vráti argumenty pre logy.
	 *
	 * @return array<string,mixed>
	 */
	public function consent_logs_args(): array {
		return array(
			'action'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'region'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'before'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'after'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 20,
			),
		);
	}

	/**
	 * Zoznam záznamov.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response
	 */
	public function consent_logs( WP_REST_Request $request ) {
		$recorder = $this->get_consent_module()?->get_recorder();

		if ( ! $recorder ) {
			return new WP_REST_Response(
				array(
					'rows'  => array(),
					'total' => 0,
				),
				200
			);
		}

		$result = $recorder->recent(
			array(
				'action'   => (string) $request->get_param( 'action' ),
				'region'   => (string) $request->get_param( 'region' ),
				'before'   => (string) $request->get_param( 'before' ),
				'after'    => (string) $request->get_param( 'after' ),
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
			)
		);

		$result['summary'] = $recorder->summary();
		$result['page']    = (int) $request->get_param( 'page' );
		$result['pages']   = (int) ceil( $result['total'] / max( 1, $request->get_param( 'per_page' ) ) );

		return new WP_REST_Response( $result, 200 );
	}

	/*
	-----------------------------------------------------------------------
	 * Nastavenia
	 */

	/**
	 * Vráti všetky nastavenia.
	 *
	 * @return WP_REST_Response
	 */
	public function settings(): WP_REST_Response {
		return new WP_REST_Response( Options::all(), 200 );
	}

	/**
	 * Jeden záznam nastavenia.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show_setting( WP_REST_Request $request ) {
		$key = (string) $request['id'];
		$all = Options::all();

		if ( ! array_key_exists( $key, $all ) ) {
			return new WP_Error(
				'hcc_not_found',
				__( 'Nastavenie neexistuje.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( array( $key => $all[ $key ] ), 200 );
	}

	/**
	 * Uloží viacero nastavení naraz.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response
	 */
	public function create_setting( WP_REST_Request $request ): WP_REST_Response {
		$params = (array) $request->get_json_params();
		$schema = Options::get_schema();

		$accepted = array();

		foreach ( $params as $key => $value ) {
			if ( ! array_key_exists( $key, $schema ) ) {
				continue;
			}

			$accepted[ $key ] = $value;
		}

		Options::update_many( $accepted );

		return new WP_REST_Response( Options::all(), 200 );
	}

	/**
	 * Aktualizuje jedno nastavenie.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_setting( WP_REST_Request $request ) {
		$key  = (string) $request['id'];
		$body = (array) $request->get_json_params();

		// React posiela `{ value: … }`, curl môže poslať priamo hodnotu.
		$value = array_key_exists( 'value', $body ) ? $body['value'] : $body;

		$result = Options::update( $key, $value );

		if ( ! $result ) {
			return new WP_Error(
				'hcc_unknown_setting',
				__( 'Nastavenie neexistuje alebo sa nepodarilo uložiť.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( array( $key => Options::get( $key ) ), 200 );
	}

	/**
	 * Zmaže nastavenie (vráti predvolenú hodnotu).
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy_setting( WP_REST_Request $request ) {
		$key = (string) $request['id'];

		if ( ! array_key_exists( $key, Options::get_schema() ) ) {
			return new WP_Error(
				'hcc_not_found',
				__( 'Nastavenie neexistuje.', 'hybrid-cookies-conset-r-plus' ),
				array( 'status' => 404 )
			);
		}

		delete_option( Options::PREFIX . $key );

		return new WP_REST_Response( array( $key => Options::get( $key ) ), 200 );
	}

	/*
	-----------------------------------------------------------------------
	 * Dashboard
	 */

	/**
	 * Súhrn pre dashboard.
	 *
	 * @return WP_REST_Response
	 */
	public function dashboard(): WP_REST_Response {
		$categories = $this->get_categories_module()?->get_repository();
		$cookies    = $this->get_cookies_module()?->get_repository();
		$recorder   = $this->get_consent_module()?->get_recorder();

		$cookies_result = $cookies ? $cookies->all( array( 'per_page' => 1 ) ) : array( 'total' => 0 );

		return new WP_REST_Response(
			array(
				'version'         => Version::NUMBER,
				'categories'      => $categories ? count( $categories->all() ) : 0,
				'cookies'         => (int) $cookies_result['total'],
				'discovered'      => $cookies
					? (int) $cookies->all(
						array(
							'is_discovered' => 1,
							'per_page'      => 1,
						)
					)['total']
					: 0,
				'consents'        => $recorder ? (int) $recorder->recent( array( 'per_page' => 1 ) )['total'] : 0,
				'summary'         => $recorder ? $recorder->summary() : array(),
				'banner_enabled'  => (bool) Options::get( 'banner_enabled' ),
				'blocker_enabled' => (bool) Options::get( 'blocker_enabled' ),
			),
			200
		);
	}

	/*
	-----------------------------------------------------------------------
	 * Pomocné
	 */

	/**
	 * Prevedie `WP_Error` na REST odpoveď s HTTP kódom.
	 *
	 * @param WP_Error $error Chyba.
	 * @return WP_Error
	 */
	private function to_rest_error( WP_Error $error ): WP_Error {
		$data = $error->get_error_data();
		$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		return new WP_Error( $error->get_error_code(), $error->get_error_message(), array( 'status' => $code ) );
	}

	/**
	 * Chybová hláška pre nenačítaný modul.
	 *
	 * @return string
	 */
	private function module_error(): string {
		return __( 'Modul pluginu nie je dostupný.', 'hybrid-cookies-conset-r-plus' );
	}

	/**
	 * Vráti modul kategórií.
	 *
	 * @return Categories_Module|null
	 */
	private function get_categories_module(): ?Categories_Module {
		$module = Plugin::instance()->get_module( 'categories' );

		return $module instanceof Categories_Module ? $module : null;
	}

	/**
	 * Vráti modul cookies.
	 *
	 * @return Cookies_Module|null
	 */
	private function get_cookies_module(): ?Cookies_Module {
		$module = Plugin::instance()->get_module( 'cookies' );

		return $module instanceof Cookies_Module ? $module : null;
	}

	/**
	 * Vráti modul bannera.
	 *
	 * @return Banner_Module|null
	 */
	private function get_banner_module(): ?Banner_Module {
		$module = Plugin::instance()->get_module( 'banner' );

		return $module instanceof Banner_Module ? $module : null;
	}

	/**
	 * Vráti modul súhlasu.
	 *
	 * @return Consent_Module|null
	 */
	private function get_consent_module(): ?Consent_Module {
		$module = Plugin::instance()->get_module( 'consent' );

		return $module instanceof Consent_Module ? $module : null;
	}
}
