<?php
/**
 * Typovaný wrapper nad `wp_options` s validačnou schémou.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prístup k nastaveniam pluginu.
 *
 * Každé nastavenie má v schéme typ a sanitizačné pravidlo, takže hodnoty
 * z REST API alebo formulára sa dajú čítať bez opakovaného overovania
 * na každom mieste.
 */
class Options {

	/**
	 * Prefix všetkých optionov.
	 */
	const PREFIX = 'hcc_';

	/**
	 * Schéma nastavení.
	 *
	 * @var array<string,array<string,string>>|null
	 */
	private static ?array $schema = null;

	/**
	 * Vráti schému nastavení.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_schema(): array {
		if ( null !== self::$schema ) {
			return self::$schema;
		}

		$schema = array(
			'enabled'                  => array(
				'type'    => 'bool',
				'default' => true,
			),
			'banner_enabled'           => array(
				'type'    => 'bool',
				'default' => true,
			),
			'consent_expiry_days'      => array(
				'type'    => 'int',
				'default' => 365,
			),
			'region_mode'              => array(
				'type'    => 'enum',
				'default' => 'auto',
				'values'  => array( 'auto', 'manual', 'off' ),
			),
			'regions'                  => array(
				'type'    => 'array',
				'default' => array(),
			),
			'simple_mode'              => array(
				'type'    => 'bool',
				'default' => true,
			),
			'show_on_login'            => array(
				'type'    => 'bool',
				'default' => true,
			),
			'rate_limit_per_min'       => array(
				'type'    => 'int',
				'default' => 10,
			),
			'delete_data_on_uninstall' => array(
				'type'    => 'bool',
				'default' => false,
			),
			'log_retention_days'       => array(
				'type'    => 'int',
				'default' => 365,
			),
			'version'                  => array(
				'type'    => 'string',
				'default' => HCC_VERSION,
			),
		);

		/**
		 * Filter umožňuje pridať vlastné nastavenia.
		 *
		 * @param array<string,array<string,string>> $schema Schéma nastavení.
		 */
		self::$schema = (array) apply_filters( 'hcc_options_schema', $schema );

		return self::$schema;
	}

	/**
	 * Odoberie cache schémy (používajú ho testy).
	 *
	 * @return void
	 */
	public static function flush_schema_cache(): void {
		self::$schema = null;
	}

	/**
	 * Načíta jedno nastavenie.
	 *
	 * @param string $key Kľúč bez prefixu.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$schema = self::get_schema();

		if ( ! isset( $schema[ $key ] ) ) {
			return null;
		}

		$value = get_option( self::PREFIX . $key, $schema[ $key ]['default'] );

		return self::sanitize( $key, $value );
	}

	/**
	 * Uloží jedno nastavenie.
	 *
	 * @param string $key   Kľúč bez prefixu.
	 * @param mixed  $value Hodnota.
	 * @return bool
	 */
	public static function update( string $key, $value ): bool {
		$schema = self::get_schema();

		if ( ! isset( $schema[ $key ] ) ) {
			return false;
		}

		$value = self::sanitize( $key, $value );

		$type = $schema[ $key ]['type'];

		// `version` nechávame bez autoloadu — načítava sa len pri kontrole updatov.
		if ( 'version' === $key ) {
			return (bool) update_option( self::PREFIX . $key, $value, false );
		}

		return (bool) update_option( self::PREFIX . $key, $value, 'array' === $type );
	}

	/**
	 * Načíta všetky nastavenia naraz.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$result = array();

		foreach ( array_keys( self::get_schema() ) as $key ) {
			$result[ $key ] = self::get( $key );
		}

		return $result;
	}

	/**
	 * Uloží viacero nastavení naraz.
	 *
	 * @param array<string,mixed> $values Kľúč => hodnota.
	 * @return void
	 */
	public static function update_many( array $values ): void {
		foreach ( $values as $key => $value ) {
			self::update( $key, $value );
		}

		/**
		 * Action po uložení nastavení.
		 *
		 * @param array<string,mixed> $values Uložené hodnoty.
		 */
		do_action( 'hcc_options_updated', $values );
	}

	/**
	 * Vyčistí a overí hodnotu podľa schémy.
	 *
	 * @param string $key   Kľúč bez prefixu.
	 * @param mixed  $value Vstupná hodnota.
	 * @return mixed
	 */
	public static function sanitize( string $key, $value ) {
		$schema = self::get_schema();
		$type   = $schema[ $key ]['type'] ?? 'string';

		switch ( $type ) {
			case 'bool':
				return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );

			case 'int':
				return (int) $value;

			case 'array':
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					$value   = is_array( $decoded ) ? $decoded : array();
				}

				return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : array();

			case 'enum':
				$allowed = (array) ( $schema[ $key ]['values'] ?? array() );
				$value   = sanitize_key( (string) $value );

				return in_array( $value, $allowed, true ) ? $value : $schema[ $key ]['default'];

			case 'string':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Uloží predvolené hodnoty pre všetky chýbajúce nastavenia.
	 *
	 * @return void
	 */
	public static function seed_defaults(): void {
		foreach ( self::get_schema() as $key => $definition ) {
			$option_name = self::PREFIX . $key;

			if ( false === get_option( $option_name, false ) ) {
				// Tretí parameter `add_option()` bol deprecated — autoload riadime
				// cez `update_option()` nižšie.
				add_option( $option_name, $definition['default'] );
			}
		}
	}

	/**
	 * Vymaže všetky nastavenia pluginu.
	 *
	 * @return void
	 */
	public static function delete_all(): void {
		foreach ( array_keys( self::get_schema() ) as $key ) {
			delete_option( self::PREFIX . $key );
		}
	}
}
