<?php
/**
 * Capability a kontrola prístupu.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Správa vlastnej capability pluginu.
 *
 * Plugin používa `manage_hybrid_cookies` namiesto `manage_options`, aby
 * sa dal prístup delegovať bez toho, aby bolo treba udeľovať plné
 * administrátorské práva.
 */
class Capabilities {

	/**
	 * Názov capability.
	 */
	const CAPABILITY = 'manage_hybrid_cookies';

	/**
	 * Pridá capability do rolí.
	 *
	 * @return void
	 */
	public static function add(): void {
		$administrator = get_role( 'administrator' );

		if ( $administrator && ! $administrator->has_cap( self::CAPABILITY ) ) {
			$administrator->add_cap( self::CAPABILITY );
		}
	}

	/**
	 * Odoberie capability z rolí.
	 *
	 * @return void
	 */
	public static function remove(): void {
		foreach ( array( 'administrator' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role && $role->has_cap( self::CAPABILITY ) ) {
				$role->remove_cap( self::CAPABILITY );
			}
		}
	}

	/**
	 * Overí, či aktuálny používateľ môže plugin spravovať.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::CAPABILITY ) || current_user_can( 'manage_options' );
	}

	/**
	 * `permission_callback` pre REST endpointy.
	 *
	 * @return bool
	 */
	public static function rest_permission_check(): bool {
		return self::current_user_can_manage();
	}
}
