<?php
/**
 * Pomocné funkcie a nastavenia.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpery pluginu.
 */
class HCC_Helpers {

	/**
	 * Predvolené hodnoty nastavení.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_options() {
		return array(
			'enabled'            => true,
			'banner_position'    => 'bottom',
			'modal'              => false,
			'show_on_login'      => true,
			'show_on_mobile'    => true,
			'consent_expiry_days'=> 180,
			'geo_targeting'      => 'none',
			'reject_all'         => true,
			'categories_enabled' => array( 'necessary', 'functional', 'statistics', 'marketing' ),
			'delete_on_uninstall'=> false,
			'script_mode'        => 'manual', // manual | auto.
			'block_patterns'     => array(),
			'custom_css'         => '',
		);
	}

	/**
	 * Uloží predvolené nastavenia, ak ešte neexistujú.
	 *
	 * @return void
	 */
	public static function set_default_options() {
		$defaults = self::default_options();

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( "hcc_{$key}", false ) ) {
				add_option( "hcc_{$key}", $value );
			}
		}
	}

	/**
	 * Načíta všetky nastavenia pluginu.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_options() {
		$options = array();

		foreach ( self::default_options() as $key => $default ) {
			$options[ $key ] = get_option( "hcc_{$key}", $default );
		}

		return $options;
	}

	/**
	 * Načíta jednu konkrétnu hodnotu.
	 *
	 * @param string $key     Kľúč bez prefixu.
	 * @param mixed  $default Predvolená hodnota.
	 * @return mixed
	 */
	public static function get_option( $key, $default = null ) {
		$defaults = self::default_options();
		$fallback = array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;

		return get_option( "hcc_{$key}", $fallback );
	}

	/**
	 * Uloží jednu konkrétnu hodnotu.
	 *
	 * @param string $key   Kľúč bez prefixu.
	 * @param mixed  $value Hodnota.
	 * @return void
	 */
	public static function update_option( $key, $value ) {
		update_option( "hcc_{$key}", $value );
	}

	/**
	 * Kategórie cookies.
	 *
	 * @return array<string,string>
	 */
	public static function get_categories() {
		return array(
			'necessary'  => __( 'Nevyhnutné', 'hybrid-cookies-conset-r-plus' ),
			'functional' => __( 'Funkčné', 'hybrid-cookies-conset-r-plus' ),
			'statistics' => __( 'Štatistické', 'hybrid-cookies-conset-r-plus' ),
			'marketing'  => __( 'Marketingové', 'hybrid-cookies-conset-r-plus' ),
		);
	}

	/**
	 * Iracionalizovaný hash návštevníka pre GDPR log.
	 *
	 * @return string
	 */
	public static function visitor_hash() {
		$salt = defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : 'hcc';
		$ip   = self::anonymize_ip( self::get_client_ip() );
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return hash( 'sha256', $ip . '|' . $ua . '|' . $salt );
	}

	/**
	 * anonymizuje IP adresu (odstráni posledný oktet).
	 *
	 * @param string $ip IP adresa.
	 * @return string
	 */
	public static function anonymize_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );

			return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = array_slice( explode( ':', $ip ), 0, 4 );

			return implode( ':', $parts ) . '::';
		}

		return '';
	}

	/**
	 * Zistí IP adresu návštevníka.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$first = trim( explode( ',', $value )[0] );

			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}

		return '';
	}

	/**
	 * Preloží text a zároveň ho bezpečne vypíše.
	 *
	 * @param string $text Text na preloženie.
	 * @return void
	 */
	public static function esc_html__( $text ) {
		echo esc_html__( $text, 'hybrid-cookies-conset-r-plus' );
	}
}