<?php
/**
 * Stuby WordPress funkcií pre testy bez databázy.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'WEEK_IN_SECONDS', 604800 );
}

/**
 * Dočasná databáza transientov pre testy.
 *
 * @var array<string,mixed>
 */
$GLOBALS['hcc_test_transients'] = array();

/**
 * Dočasná databáza optionov pre testy.
 *
 * @var array<string,mixed>
 */
$GLOBALS['hcc_test_options'] = array();

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Stub pre `get_transient()`.
	 *
	 * @param string $key Kľúč transientu.
	 * @return mixed
	 */
	function get_transient( string $key ) {
		return $GLOBALS['hcc_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Stub pre `set_transient()`.
	 *
	 * @param string $key        Kľúč transientu.
	 * @param mixed  $value      Hodnota.
	 * @param int    $expiration Doba platnosti v sekundách.
	 * @return bool
	 */
	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		$GLOBALS['hcc_test_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Stub pre `delete_transient()`.
	 *
	 * @param string $key Kľúč transientu.
	 * @return bool
	 */
	function delete_transient( string $key ): bool {
		unset( $GLOBALS['hcc_test_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub pre `get_option()`.
	 *
	 * @param string $option        Názov optionu.
	 * @param mixed  $default_value Predvolená hodnota.
	 * @return mixed
	 */
	function get_option( string $option, $default_value = false ) {
		return $GLOBALS['hcc_test_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stub pre `update_option()`.
	 *
	 * @param string $option Názov optionu.
	 * @param mixed  $value  Hodnota.
	 * @return bool
	 */
	function update_option( string $option, $value ): bool {
		$GLOBALS['hcc_test_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Stub pre `add_option()`.
	 *
	 * @param string $option Názov optionu.
	 * @param mixed  $value  Hodnota.
	 * @return bool
	 */
	function add_option( string $option, $value ): bool {
		if ( array_key_exists( $option, $GLOBALS['hcc_test_options'] ) ) {
			return false;
		}

		$GLOBALS['hcc_test_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Stub pre `delete_option()`.
	 *
	 * @param string $option Názov optionu.
	 * @return bool
	 */
	function delete_option( string $option ): bool {
		unset( $GLOBALS['hcc_test_options'][ $option ] );

		return true;
	}
}

/**
 * Registrované filtre pre testy.
 *
 * @var array<string,array<int,array<int,array{callback:callable,args:int}>>>
 */
$GLOBALS['hcc_test_filters'] = array();

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub pre `apply_filters()`.
	 *
	 * @param string $hook_name Názov hooku.
	 * @param mixed  $value     Hodnota.
	 * @param mixed  ...$args   Ďalšie argumenty.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		foreach ( $GLOBALS['hcc_test_filters'][ $hook_name ] ?? array() as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$value = call_user_func_array(
					$entry['callback'],
					array_slice( array_merge( array( $value ), $args ), 0, $entry['args'] )
				);
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub pre `add_filter()`.
	 *
	 * @param string   $hook_name     Názov hooku.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priorita.
	 * @param int      $accepted_args Počet argumentov.
	 * @return true
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['hcc_test_filters'][ $hook_name ][ $priority ][] = array(
			'callback' => $callback,
			'args'     => $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	/**
	 * Stub pre `remove_all_filters()`.
	 *
	 * @param string $hook_name Názov hooku.
	 * @param mixed  $priority  Priorita.
	 * @return true
	 */
	function remove_all_filters( string $hook_name, $priority = false ): bool {
		if ( false === $priority ) {
			unset( $GLOBALS['hcc_test_filters'][ $hook_name ] );
		} else {
			unset( $GLOBALS['hcc_test_filters'][ $hook_name ][ $priority ] );
		}

		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Stub pre `do_action()`.
	 *
	 * @param string $hook_name Názov hooku.
	 * @param mixed  ...$args   Argumenty.
	 * @return void
	 */
	function do_action( string $hook_name, ...$args ): void {
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub pre `__()`.
	 *
	 * @param string $text   Text na preloženie.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Stub pre `esc_html__()`.
	 *
	 * @param string $text   Text na preloženie.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub pre `sanitize_key()`.
	 *
	 * @param string $key Kľúč.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub pre `sanitize_text_field()`.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( string $text ): string {
		return trim( strip_tags( $text ) );
	}
}
