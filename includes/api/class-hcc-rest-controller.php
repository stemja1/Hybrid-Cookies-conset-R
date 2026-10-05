<?php
/**
 * REST API – endpointy pre katalóg cookies a súhlas.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST kontrolér pluginu.
 */
class HCC_REST_Controller {

	/**
	 * Namespace REST.
	 */
	const NAMESPACE_V1 = 'hcc/v1';

	/**
	 * Registruje hooky.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Zaregistruje trasy.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/cookies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cookies' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_public_settings' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Kto môže spravovať plugin.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Vráti zoznam cookies z katalógu.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response
	 */
	public function get_cookies( $request ) {
		$args = array();

		if ( $request->get_param( 'category' ) ) {
			$args['category'] = sanitize_key( $request->get_param( 'category' ) );
		}

		$cookies = HCC_Cookie_Catalog::all( $args );

		return rest_ensure_response( $cookies );
	}

	/**
	 * Vráti verejné nastavenia (pre banner).
	 *
	 * @return WP_REST_Response
	 */
	public function get_public_settings() {
		$options = HCC_Helpers::get_options();

		$public = array(
			'enabled'         => (bool) $options['enabled'],
			'banner_position' => $options['banner_position'],
			'modal'           => (bool) $options['modal'],
			'reject_all'      => (bool) $options['reject_all'],
		);

		return rest_ensure_response( $public );
	}

	/**
	 * Uloží nastavenia.
	 *
	 * @param WP_REST_Request $request Požiadavka.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$body  = $request->get_json_params();
		$known = HCC_Helpers::default_options();
		$changed = array();

		foreach ( (array) $body as $key => $value ) {
			if ( ! array_key_exists( $key, $known ) ) {
				continue;
			}

			$sanitized         = HCC_Settings::sanitize_value( $key, $value );
			HCC_Helpers::update_option( $key, $sanitized );
			$changed[ $key ]   = $sanitized;
		}

		return rest_ensure_response( $changed );
	}
}