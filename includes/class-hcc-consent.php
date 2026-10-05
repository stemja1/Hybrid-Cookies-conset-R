<?php
/**
 * Správa súhlasu – čítanie, zápis a overovanie súhlasu.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consent manažér.
 */
class HCC_Consent {

	/**
	 * Názov cookie s uloženým súhlasom.
	 */
	const COOKIE_NAME = 'hcc_consent';

	/**
	 * Registruje háky.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_hcc_save_consent', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_nopriv_hcc_save_consent', array( $this, 'ajax_save' ) );
	}

	/**
	 * Načíta súhlas z cookie.
	 *
	 * @return array{categories:array<int,string>,given:bool,timestamp:int}
	 */
	public function get_consent() {
		$default = array(
			'categories' => array(),
			'given'      => false,
			'timestamp'  => 0,
		);

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return $default;
		}

		$raw = wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		$raw = is_string( $raw ) ? sanitize_text_field( $raw ) : '';

		$data = json_decode( base64_decode( $raw ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! is_array( $data ) ) {
			return $default;
		}

		$categories = isset( $data['categories'] ) && is_array( $data['categories'] )
			? array_values( array_intersect( array_map( 'sanitize_key', $data['categories'] ), array_keys( HCC_Helpers::get_categories() ) ) )
			: array();

		return array(
			'categories' => $categories,
			'given'      => ! empty( $data['given'] ),
			'timestamp'  => isset( $data['t'] ) ? (int) $data['t'] : 0,
		);
	}

	/**
	 * Overí, či má návštevník súhlas pre danú kategóriu.
	 *
	 * @param string $category Kategória cookies.
	 * @return bool
	 */
	public function has_consent( $category ) {
		$consent = $this->get_consent();

		if ( ! $consent['given'] ) {
			return 'necessary' === $category;
		}

		return in_array( $category, $consent['categories'], true );
	}

	/**
	 * Uloží súhlas (AJAX handler).
	 *
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( 'hcc_consent', 'nonce' );

		$categories = isset( $_POST['categories'] ) ? (array) wp_unslash( $_POST['categories'] ) : array();
		$categories = array_values( array_intersect( array_map( 'sanitize_key', $categories ), array_keys( HCC_Helpers::get_categories() ) ) );

		// Nevyhnutné kategórie sú vždy súhlasné.
		$categories[] = 'necessary';
		$categories   = array_values( array_unique( $categories ) );

		$payload = array(
			'categories' => $categories,
			'given'      => true,
			't'          => time(),
		);

		$value = base64_encode( wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$days   = (int) HCC_Helpers::get_option( 'consent_expiry_days', 180 );

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$value,
				array(
					'expires'  => time() + ( $days * DAY_IN_SECONDS ),
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => false, // Súhlas musí byť čitateľný zo stránky.
					'samesite' => 'Lax',
				)
			);
		}

		$_COOKIE[ self::COOKIE_NAME ] = $value;

		$this->log_consent( $categories );

		wp_send_json_success(
			array(
				'categories' => $categories,
				'expires'    => time() + ( $days * DAY_IN_SECONDS ),
			)
		);
	}

	/**
	 * Zapíše súhlas do logu (anonymizované).
	 *
	 * @param array<int,string> $categories Kategórie.
	 * @return void
	 */
	private function log_consent( $categories ) {
		global $wpdb;

		// TODO: Rate-limit a agregácia po dňoch, aby tabuľka nerástla donekonečna.
		$wpdb->insert(
			HCC_Activator::consent_table(),
			array(
				'visitor_hash'  => HCC_Helpers::visitor_hash(),
				'categories'    => wp_json_encode( $categories ),
				'consent_given' => 1,
				'ip_anonymized' => HCC_Helpers::anonymize_ip( HCC_Helpers::get_client_ip() ),
				'user_agent'    => substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', 0, 255 ),
			)
		);
	}
}