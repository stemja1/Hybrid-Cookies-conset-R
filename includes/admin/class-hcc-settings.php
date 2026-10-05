<?php
/**
 * Stránka nastavení.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nastavenia pluginu.
 */
class HCC_Settings {

	/**
	 * Registruje nastavenia pre options.php.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( array_keys( HCC_Helpers::default_options() ) as $key ) {
			register_setting(
				'hcc_settings',
				"hcc_{$key}",
				array(
					'type'              => 'mixed',
					'sanitize_callback' => static function ( $value ) use ( $key ) {
						return self::sanitize_value( $key, $value );
					},
					'default'           => HCC_Helpers::get_option( $key ),
				)
			);
		}
	}

	/**
	 * Vyčistí hodnotu podľa typu poľa.
	 *
	 * @param string $key   Kľúč bez prefixu.
	 * @param mixed  $value Vstupná hodnota.
	 * @return mixed
	 */
	public static function sanitize_value( $key, $value ) {
		$defaults = HCC_Helpers::default_options();

		if ( is_bool( $defaults[ $key ] ) ) {
			return (bool) $value ? '1' : '0';
		}

		if ( is_int( $defaults[ $key ] ) ) {
			return (int) $value;
		}

		if ( is_array( $defaults[ $key ] ) ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			return 'categories_enabled' === $key
				? array_values( array_intersect( array_map( 'sanitize_key', $value ), array_keys( HCC_Helpers::get_categories() ) ) )
				: array_map( 'sanitize_text_field', $value );
		}

		if ( 'custom_css' === $key ) {
			return wp_strip_all_tags( (string) $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Skupiny nastavení.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function groups() {
		return array(
			'general'   => array(
				'label'  => __( 'Všeobecné', 'hybrid-cookies-conset-r-plus' ),
				'fields' => array( 'enabled', 'banner_position', 'modal', 'show_on_login', 'show_on_mobile', 'consent_expiry_days' ),
			),
			'scripting' => array(
				'label'  => __( 'Blokovanie skriptov', 'hybrid-cookies-conset-r-plus' ),
				'fields' => array( 'script_mode', 'block_patterns', 'categories_enabled' ),
			),
			'privacy'   => array(
				'label'  => __( 'Súkromie a GDPR', 'hybrid-cookies-conset-r-plus' ),
				'fields' => array( 'geo_targeting', 'reject_all', 'delete_on_uninstall' ),
			),
			'advanced'  => array(
				'label'  => __( 'Pokročilé', 'hybrid-cookies-conset-r-plus' ),
				'fields' => array( 'custom_css' ),
			),
		);
	}

	/**
	 * Pole typu checkbox pre daný kľúč.
	 *
	 * @param string $key    Kľúč bez prefixu.
	 * @param string $label  Označenie.
	 * @param string $help   Pomocný text.
	 * @return void
	 */
	public static function checkbox_field( $key, $label, $help = '' ) {
		$value = HCC_Helpers::get_option( $key );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( "hcc_{$key}" ); ?>" value="1" <?php checked( (bool) $value ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
				<?php if ( $help ) : ?>
					<p class="description"><?php echo esc_html( $help ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Pole typu select pre daný kľúč.
	 *
	 * @param string $key    Kľúč bez prefixu.
	 * @param string $label  Označenie.
	 * @param array  $choices Možnosti.
	 * @return void
	 */
	public static function select_field( $key, $label, $choices ) {
		$value = HCC_Helpers::get_option( $key );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<select name="<?php echo esc_attr( "hcc_{$key}" ); ?>">
					<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( (string) $value, (string) $choice_value ); ?>>
							<?php echo esc_html( $choice_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Vykreslí stránku nastavení.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$groups = self::groups();
		$active  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $groups[ $active ] ) ) {
			$active = 'general';
		}
		?>
		<div class="wrap hcc-settings">
			<h1><?php echo esc_html__( 'Hybrid Cookies conset R+', 'hybrid-cookies-conset-r-plus' ); ?></h1>

			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $groups as $tab => $group ) : ?>
					<a class="nav-tab <?php echo $active === $tab ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=hcc-cookies&tab=' . $tab ) ); ?>">
						<?php echo esc_html( $group['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'hcc_settings' ); ?>
				<table class="form-table" role="presentation">
					<?php
					// TODO: Doplniť konkrétne polia podľa aktívneho tabu.
					self::checkbox_field(
						'enabled',
						__( 'Plugin je aktívny', 'hybrid-cookies-conset-r-plus' ),
						__( 'Vypnutím sa banner prestane zobrazovať.', 'hybrid-cookies-conset-r-plus' )
					);
					self::select_field(
						'banner_position',
						__( 'Pozícia bannera', 'hybrid-cookies-conset-r-plus' ),
						array(
							'top'    => __( 'Hore', 'hybrid-cookies-conset-r-plus' ),
							'bottom' => __( 'Dole', 'hybrid-cookies-conset-r-plus' ),
						)
					);
					?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}