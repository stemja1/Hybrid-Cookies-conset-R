<?php
/**
 * PHP admin obrazovky ako fallback pre React.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Admin;

use HCC\Capabilities;
use HCC\Modules\Banner\Banner_Module;
use HCC\Modules\Categories\Categories_Module;
use HCC\Modules\Consent\Consent_Module;
use HCC\Modules\Cookies\Cookies_Module;
use HCC\Options;
use HCC\Plugin;
use HCC\Tables;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback obrazovky pre prípad, že React build ešte neexistuje.
 *
 * Tieto obrazovky nie sú odsúdené — plnia rolu rezervy, aby bol plugin
 * spravovateľný aj predtým, než je `admin/ui/dist` zbuildovaný. V
 * Sesii 7 ich nahradí React SPA, tento kód ostane ako safety net.
 */
class Fallback_Pages {

	/**
	 * Registruje hooky.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_hcc_export_consent_logs', array( $this, 'export_csv' ) );
		add_action( 'admin_post_hcc_save_options', array( $this, 'save_options' ) );
	}

	/**
	 * Vykreslí prehľad.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->guard();

		$categories = $this->get_categories_module()?->get_repository();
		$cookies    = $this->get_cookies_module()?->get_repository();
		$recorder   = $this->get_consent_module()?->get_recorder();

		$total_cookies    = $cookies ? (int) $cookies->all( array( 'per_page' => 1 ) )['total'] : 0;
		$total_discovered = $cookies
			? (int) $cookies->all(
				array(
					'is_discovered' => 1,
					'per_page'      => 1,
				)
			)['total']
			: 0;
		$total_consents   = $recorder ? (int) $recorder->recent( array( 'per_page' => 1 ) )['total'] : 0;
		$summary          = $recorder ? $recorder->summary() : array();

		$this->header( __( 'Prehľad', 'hybrid-cookies-conset-r-plus' ) );

		echo '<div class="hcc-cards">';

		$this->card(
			__( 'Kategórie', 'hybrid-cookies-conset-r-plus' ),
			(string) ( $categories ? count( $categories->all() ) : 0 ),
			__( 'Definované kategórie súhlasu', 'hybrid-cookies-conset-r-plus' )
		);

		$this->card(
			__( 'Cookies', 'hybrid-cookies-conset-r-plus' ),
			(string) $total_cookies,
			__( 'Záznamov v katalógu', 'hybrid-cookies-conset-r-plus' )
		);

		$this->card(
			__( 'Nezdokumentované', 'hybrid-cookies-conset-r-plus' ),
			(string) $total_discovered,
			__( 'Cookies objavené automaticky, čakajú na zaradenie', 'hybrid-cookies-conset-r-plus' )
		);

		$this->card(
			__( 'Súhlasy', 'hybrid-cookies-conset-r-plus' ),
			(string) $total_consents,
			__( 'Zaznamenaných rozhodnutí', 'hybrid-cookies-conset-r-plus' )
		);

		echo '</div>';

		if ( $summary ) {
			echo '<h2>' . esc_html__( 'Súhlasy podľa kategórie', 'hybrid-cookies-conset-r-plus' ) . '</h2>';
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Kategória', 'hybrid-cookies-conset-r-plus' ) . '</th>';
			echo '<th>' . esc_html__( 'Poznámka', 'hybrid-cookies-conset-r-plus' ) . '</th>';
			echo '<th>' . esc_html__( 'Používateľov', 'hybrid-cookies-conset-r-plus' ) . '</th>';
			echo '<th>' . esc_html__( 'Podiel', 'hybrid-cookies-conset-r-plus' ) . '</th>';
			echo '</tr></thead><tbody>';

			$category_names = $categories ? $categories->names() : array();

			foreach ( $summary as $slug => $data ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $slug ) . '</code></td>';
				echo '<td>' . esc_html( $category_names[ $slug ] ?? '' ) . '</td>';
				echo '<td>' . esc_html( (string) $data['count'] ) . '</td>';
				echo '<td>' . esc_html( (string) $data['percentage'] ) . ' %</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		$this->footer();
	}

	/**
	 * Vykreslí editor bannera.
	 *
	 * Bez Reactu je to prehľad aktuálnej konfigurácie s odkazmi na
	 * úpravu cez REST. Vlastné formulárové polia pridá React v Sesií 7 —
	 * tu by duplicovali sanitizáciu farieb a textov.
	 *
	 * @return void
	 */
	public function render_banner(): void {
		$this->guard();

		$this->header( __( 'Banner', 'hybrid-cookies-conset-r-plus' ) );

		$banner_module = $this->get_banner_module();
		$repository    = $banner_module?->get_repository();
		$banners       = $repository ? $repository->all() : array();
		$regions       = hcc_get_region_definitions();

		if ( ! $banners ) {
			$this->notice_error( __( 'Nie je vytvorený žiadny banner. Aktivujte plugin znova alebo vytvorte ho cez REST.', 'hybrid-cookies-conset-r-plus' ) );
			$this->footer();

			return;
		}

		echo '<p class="description">';
		esc_html_e( 'Úpravu banneru spraví vizuálny editor v React aplikácii. Tu je aktuálny stav.', 'hybrid-cookies-conset-r-plus' );
		echo '</p>';

		foreach ( $banners as $banner ) {
			$config = (array) $banner['config'];
			$region = (string) $banner['region'];
			$label  = 'default' === $region
				? __( 'Všetky regióny', 'hybrid-cookies-conset-r-plus' )
				: ( $regions[ $region ]['label'] ?? $region );

			echo '<h2>' . esc_html( (string) $banner['title'] ) . '</h2>';

			echo '<table class="widefat striped"><tbody>';
			echo '<tr><th>' . esc_html__( 'Región', 'hybrid-cookies-conset-r-plus' ) . '</th><td>' . esc_html( $label ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Layout', 'hybrid-cookies-conset-r-plus' ) . '</th><td><code>' . esc_html( (string) $config['layout'] ) . '</code></td></tr>';
			echo '<tr><th>' . esc_html__( 'Pozícia', 'hybrid-cookies-conset-r-plus' ) . '</th><td>' . esc_html( (string) $config['position'] ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Modálny', 'hybrid-cookies-conset-r-plus' ) . '</th><td>' . esc_html( ! empty( $config['modal'] ) ? __( 'áno', 'hybrid-cookies-conset-r-plus' ) : __( 'nie', 'hybrid-cookies-conset-r-plus' ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Zobraziť „Odmietnuť"', 'hybrid-cookies-conset-r-plus' ) . '</th><td>' . esc_html( ! empty( $config['show_reject_all'] ) ? __( 'áno', 'hybrid-cookies-conset-r-plus' ) : __( 'nie', 'hybrid-cookies-conset-r-plus' ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Verzia', 'hybrid-cookies-conset-r-plus' ) . '</th><td>' . esc_html( (string) $banner['version'] ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Aktualizovaný', 'hybrid-cookies-conset-r-plus' ) . '</th><td>' . esc_html( (string) $banner['updated_at'] ) . '</td></tr>';
			echo '<tr><th>ID</th><td><code>' . esc_html( (string) $banner['id'] ) . '</code></td></tr>';
			echo '</tbody></table>';
		}

		$this->render_preview( $banners[0] );
		$this->footer();
	}

	/**
	 * Vykreslí náhľad bannera.
	 *
	 * Použije rovnaký renderer ako frontend — náhľad teda ukazuje to,
	 * čo návštevník naozaj uvidí, namiesto aproximácie v admin CSS.
	 *
	 * @param array<string,mixed> $banner Banner.
	 * @return void
	 */
	private function render_preview( array $banner ): void {
		$renderer = $this->get_banner_module()?->get_loader()->get_renderer();

		if ( ! $renderer ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Náhľad', 'hybrid-cookies-conset-r-plus' ) . '</h2>';
		echo '<div class="hcc-banner-preview">';

		// `wp_footer` sa v náhľade nevolá — renderer voláme priamo.
		$renderer->render( $banner, array( 'necessary' ) );

		echo '</div>';
	}

	/**
	 * Vykreslí katalóg cookies.
	 *
	 * @return void
	 */
	public function render_cookies(): void {
		$this->guard();

		$this->header( __( 'Katalóg cookies', 'hybrid-cookies-conset-r-plus' ) );

		$repository = $this->get_cookies_module()?->get_repository();
		$categories = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			$this->notice_error( __( 'Modul katalógu cookies nie je dostupný.', 'hybrid-cookies-conset-r-plus' ) );
			$this->footer();

			return;
		}

		$categories_by_id = array();

		foreach ( (array) $categories?->all() as $category ) {
			$categories_by_id[ (int) $category['id'] ] = $category;
		}

		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = $repository->all(
			array(
				'page'     => $page,
				'per_page' => 30,
				'search'   => $search,
			)
		);

		?>
		<form method="get">
			<input type="hidden" name="page" value="hybrid-cookies-cookies" />
			<p class="search-box">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" />
				<?php submit_button( __( 'Hľadať', 'hybrid-cookies-conset-r-plus' ), '', '', false ); ?>
			</p>
		</form>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Názov cookie', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Poskytovateľ', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Kategória', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Doba platnosti', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Doména', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Zdroj', 'hybrid-cookies-conset-r-plus' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $result['rows'] ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'Zatiaľ nie sú žiadne cookies v katalógu.', 'hybrid-cookies-conset-r-plus' ); ?></td>
					</tr>
				<?php endif; ?>

				<?php foreach ( $result['rows'] as $cookie ) : ?>
					<?php $category = $categories_by_id[ (int) $cookie['category_id'] ] ?? null; ?>
					<tr>
						<td><code><?php echo esc_html( $cookie['name'] ); ?></code></td>
						<td><?php echo esc_html( $cookie['provider'] ); ?></td>
						<td>
							<span class="hcc-badge hcc-badge--<?php echo esc_attr( $category['slug'] ?? 'unknown' ); ?>">
								<?php echo esc_html( $category['name'] ?? __( 'Nezaradené', 'hybrid-cookies-conset-r-plus' ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( '' !== $cookie['duration'] ? $cookie['duration'] : '—' ); ?></td>
						<td><code><?php echo esc_html( '' !== $cookie['domain_pattern'] ? $cookie['domain_pattern'] : '—' ); ?></code></td>
						<td>
							<?php
							echo esc_html(
								! empty( $cookie['is_discovered'] )
									? __( 'Autodetekcia', 'hybrid-cookies-conset-r-plus' )
									: __( 'Ručne', 'hybrid-cookies-conset-r-plus' )
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$pages = (int) ceil( $result['total'] / 30 );

		if ( $pages > 1 ) :
			?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $pages,
								'prev_text' => '‹',
								'next_text' => '›',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

		<?php
		$this->footer();
	}

	/**
	 * Vykreslí kategórie.
	 *
	 * @return void
	 */
	public function render_categories(): void {
		$this->guard();

		$this->header( __( 'Kategórie súhlasu', 'hybrid-cookies-conset-r-plus' ) );

		$repository = $this->get_categories_module()?->get_repository();

		if ( ! $repository ) {
			$this->notice_error( __( 'Modul kategórií nie je dostupný.', 'hybrid-cookies-conset-r-plus' ) );
			$this->footer();

			return;
		}

		$cookies_module = $this->get_cookies_module()?->get_repository();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Názov', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Popis', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Cookies', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Nevyhnutná', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Predáva osobné údaje', 'hybrid-cookies-conset-r-plus' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $repository->all() as $category ) : ?>
					<?php
					$count = $cookies_module
						? (int) $cookies_module->all(
							array(
								'category_id' => (int) $category['id'],
								'per_page'    => 1,
							)
						)['total']
						: 0;
					?>
					<tr>
						<td><strong><?php echo esc_html( $category['name'] ); ?></strong></td>
						<td><code><?php echo esc_html( $category['slug'] ); ?></code></td>
						<td><?php echo esc_html( $category['description'] ); ?></td>
						<td><?php echo esc_html( (string) $count ); ?></td>
						<td><?php echo ! empty( $category['is_necessary'] ) ? '✓' : '—'; ?></td>
						<td><?php echo ! empty( $category['sell_personal_data'] ) ? '✓' : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php esc_html_e( 'Nevyhnutná kategória sa nedá premeniť na dobrovoľnú ani zmazať. Kategóriu s priradenými cookies nie je možné odstrániť, kým ich nepriradíte inam.', 'hybrid-cookies-conset-r-plus' ); ?>
		</p>
		<?php
		$this->footer();
	}

	/**
	 * Vykreslí log súhlasov.
	 *
	 * @return void
	 */
	public function render_logs(): void {
		$this->guard();

		$this->header( __( 'Log súhlasov', 'hybrid-cookies-conset-r-plus' ) );

		$recorder = $this->get_consent_module()?->get_recorder();

		if ( ! $recorder ) {
			$this->notice_error( __( 'Modul súhlasu nie je dostupný.', 'hybrid-cookies-conset-r-plus' ) );
			$this->footer();

			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = $recorder->recent(
			array(
				'action'   => $action,
				'page'     => $page,
				'per_page' => 30,
			)
		);
		?>
		<div class="tablenav top">
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hybrid-cookies-logs' ) ); ?>" <?php echo '' === $action ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Všetko', 'hybrid-cookies-conset-r-plus' ); ?>
					</a> |
				</li>
				<?php foreach ( array( 'accept_all', 'reject_all', 'custom' ) as $filter ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=hybrid-cookies-logs&action=' . $filter ) ); ?>" <?php echo $action === $filter ? 'class="current"' : ''; ?>>
							<?php echo esc_html( $this->get_action_label( $filter ) ); ?>
						</a>
						<?php echo 'custom' !== $filter ? '|' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hcc_export_consent_logs' ), 'hcc_export_consent_logs' ) ); ?>">
				<?php esc_html_e( 'Export CSV', 'hybrid-cookies-conset-r-plus' ); ?>
			</a>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Dátum', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Akcia', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Región', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Kategórie', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Banner v.', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<th><?php esc_html_e( 'Návštevník', 'hybrid-cookies-conset-r-plus' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $result['rows'] ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'Zatiaľ nie sú žiadne záznamy.', 'hybrid-cookies-conset-r-plus' ); ?></td>
					</tr>
				<?php endif; ?>

				<?php foreach ( $result['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td><?php echo esc_html( $this->get_action_label( (string) $row['action'] ) ); ?></td>
						<td><?php echo esc_html( $row['region'] ); ?></td>
						<td>
							<?php
							$cats = json_decode( (string) $row['categories'], true );

							echo esc_html(
								is_array( $cats ) ? implode( ', ', $cats ) : (string) $row['categories']
							);
							?>
						</td>
						<td><?php echo esc_html( (string) $row['banner_version'] ); ?></td>
						<td><code><?php echo esc_html( substr( (string) $row['ip_hash'], 0, 12 ) ); ?>…</code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php esc_html_e( 'IP adresy sa nikdy neukladajú v čitateľnej forme — vidíte len SHA-256 hash so saltom. Log je dôkaz o súhlase a má zostať čo najdlhšie.', 'hybrid-cookies-conset-r-plus' ); ?>
		</p>
		<?php

		$pages = (int) ceil( $result['total'] / 30 );

		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $page,
						'total'     => $pages,
						'prev_text' => '‹',
						'next_text' => '›',
					)
				)
			);
			echo '</div></div>';
		}

		$this->footer();
	}

	/**
	 * Vykreslí nastavenia.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$this->guard();

		$this->header( __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hcc_save_options' ); ?>
			<input type="hidden" name="action" value="hcc_save_options" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Consent banner', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hcc_banner_enabled" value="1" <?php checked( (bool) Options::get( 'banner_enabled' ) ); ?> />
							<?php esc_html_e( 'Zobrazovať banner na frontende', 'hybrid-cookies-conset-r-plus' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Blokovanie skriptov', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hcc_blocker_enabled" value="1" <?php checked( (bool) Options::get( 'blocker_enabled' ) ); ?> />
							<?php esc_html_e( 'Blokovať skripty tretích strán pred súhlasom', 'hybrid-cookies-conset-r-plus' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Bloker zachytáva skripty aj vložené inými pluginmi, bez manuálneho označovania.', 'hybrid-cookies-conset-r-plus' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Prihlásený používateľ', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hcc_show_on_login" value="1" <?php checked( (bool) Options::get( 'show_on_login' ) ); ?> />
							<?php esc_html_e( 'Zobrazovať banner aj prihláseným', 'hybrid-cookies-conset-r-plus' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="hcc_consent_expiry_days"><?php esc_html_e( 'Platnosť súhlasu', 'hybrid-cookies-conset-r-plus' ); ?></label></th>
					<td>
						<input
							type="number"
							id="hcc_consent_expiry_days"
							name="hcc_consent_expiry_days"
							value="<?php echo esc_attr( (string) Options::get( 'consent_expiry_days' ) ); ?>"
							min="1"
							max="3650"
						/>
						<?php esc_html_e( 'dní', 'hybrid-cookies-conset-r-plus' ); ?>
						<p class="description"><?php esc_html_e( 'Po uplynutí platnosti je súhlas neplatný a banner sa zobrazí znova.', 'hybrid-cookies-conset-r-plus' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="hcc_log_retention_days"><?php esc_html_e( 'Retencia logov', 'hybrid-cookies-conset-r-plus' ); ?></label></th>
					<td>
						<input
							type="number"
							id="hcc_log_retention_days"
							name="hcc_log_retention_days"
							value="<?php echo esc_attr( (string) Options::get( 'log_retention_days' ) ); ?>"
							min="0"
							max="3650"
						/>
						<?php esc_html_e( 'dní (0 = bez mazania)', 'hybrid-cookies-conset-r-plus' ); ?>
						<p class="description"><?php esc_html_e( 'Log súhlasov je dôkaz pre GDPR — nechajte ho čo najdlhšie.', 'hybrid-cookies-conset-r-plus' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="hcc_rate_limit_per_min"><?php esc_html_e( 'Rate limit', 'hybrid-cookies-conset-r-plus' ); ?></label></th>
					<td>
						<input
							type="number"
							id="hcc_rate_limit_per_min"
							name="hcc_rate_limit_per_min"
							value="<?php echo esc_attr( (string) Options::get( 'rate_limit_per_min' ) ); ?>"
							min="0"
							max="120"
						/>
						<?php esc_html_e( 'požiadaviek za minútu na IP (0 = vypnuté)', 'hybrid-cookies-conset-r-plus' ); ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="hcc_region_mode"><?php esc_html_e( 'Regionálna detekcia', 'hybrid-cookies-conset-r-plus' ); ?></label></th>
					<td>
						<select id="hcc_region_mode" name="hcc_region_mode">
							<?php
							$choices = array(
								'auto'   => __( 'Automaticky (Accept-Language)', 'hybrid-cookies-conset-r-plus' ),
								'manual' => __( 'Ručne (len nastavené regióny)', 'hybrid-cookies-conset-r-plus' ),
								'off'    => __( 'Vypnutá', 'hybrid-cookies-conset-r-plus' ),
							);

							foreach ( $choices as $value => $label ) {
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $value ),
									selected( (string) Options::get( 'region_mode' ), $value, false ),
									esc_html( $label )
								);
							}
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Predvolene plugin nikdy nevolá externé API — určí región z jazyka prehliadača, čo je aproximácia, nie presná detekcia.', 'hybrid-cookies-conset-r-plus' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Mazanie údajov', 'hybrid-cookies-conset-r-plus' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hcc_delete_data_on_uninstall" value="1" <?php checked( (bool) Options::get( 'delete_data_on_uninstall' ) ); ?> />
							<?php esc_html_e( 'Zmazať tabuľky a nastavenia pri odinštalácii', 'hybrid-cookies-conset-r-plus' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Predvolene vypnuté — log súhlasov je doklad pre GDPR a mal by zostať.', 'hybrid-cookies-conset-r-plus' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php

		$this->render_tables_info();
		$this->footer();
	}

	/**
	 * Vypíše názvy tabuliek — hodí sa pri riešení problémov.
	 *
	 * @return void
	 */
	private function render_tables_info(): void {
		global $wpdb;

		echo '<h2>' . esc_html__( 'Tabuľky databázy', 'hybrid-cookies-conset-r-plus' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';

		foreach ( Tables::all() as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			// Názov tabuľky pochádza z `Tables::all()`, nie od používateľa.
			$count = $exists
				? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				: 0;

			echo '<tr>';
			echo '<td><code>' . esc_html( $table ) . '</code></td>';
			echo '<td>' . ( $exists ? esc_html__( 'existuje', 'hybrid-cookies-conset-r-plus' ) : esc_html__( 'chýba', 'hybrid-cookies-conset-r-plus' ) ) . '</td>';
			echo '<td>' . esc_html( sprintf( '%d %s', $count, __( 'záznamov', 'hybrid-cookies-conset-r-plus' ) ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Uloží nastavenia z formulára.
	 *
	 * @return void
	 */
	public function save_options(): void {
		check_admin_referer( 'hcc_save_options' );

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemáte oprávnenie.', 'hybrid-cookies-conset-r-plus' ) );
		}

		// Checkboxy sa neodosielajú, keď sú odfajkované — musíme ich
		// explicitne nastaviť na 0.
		$bools = array(
			'banner_enabled'           => 'hcc_banner_enabled',
			'blocker_enabled'          => 'hcc_blocker_enabled',
			'show_on_login'            => 'hcc_show_on_login',
			'delete_data_on_uninstall' => 'hcc_delete_data_on_uninstall',
		);

		$values = array();

		foreach ( $bools as $key => $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce overený vyššie.
			$values[ $key ] = isset( $_POST[ $field ] ) ? '1' : '0';
		}

		$ints = array( 'consent_expiry_days', 'log_retention_days', 'rate_limit_per_min' );

		foreach ( $ints as $key ) {
			$field = 'hcc_' . $key;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce overený vyššie.
			if ( isset( $_POST[ $field ] ) ) {
				$values[ $key ] = (int) $_POST[ $field ];
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce overený vyššie.
		if ( isset( $_POST['hcc_region_mode'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce overený vyššie.
			$values['region_mode'] = sanitize_key( wp_unslash( $_POST['hcc_region_mode'] ) );
		}

		Options::update_many( $values );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'hybrid-cookies-settings',
					'message' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Export logov do CSV.
	 *
	 * @return void
	 */
	public function export_csv(): void {
		check_admin_referer( 'hcc_export_consent_logs' );

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na export.', 'hybrid-cookies-conset-r-plus' ) );
		}

		$recorder = $this->get_consent_module()?->get_recorder();

		if ( ! $recorder ) {
			wp_die( esc_html__( 'Logy nie sú dostupné.', 'hybrid-cookies-conset-r-plus' ) );
		}

		nocache_headers();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=hcc-consent-logs-' . gmdate( 'Y-m-d' ) . '.csv' );

		$stream = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $stream ) {
			wp_die( esc_html__( 'Export sa nepodarilo otvoriť.', 'hybrid-cookies-conset-r-plus' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fputcsv( $stream, array( 'UUID', 'Dátum', 'Akcia', 'Región', 'Verzia bannera', 'Kategórie', 'IP hash' ) );

		$page   = 1;
		$result = array( 'rows' => array() );

		do {
			$result = $recorder->recent(
				array(
					'per_page' => 500,
					'page'     => $page,
				)
			);

			$rows = $result['rows'];

			$rows       = $result['rows'];
			$rows_count = count( $rows );

			foreach ( $rows as $row ) {
				$categories = json_decode( (string) $row['categories'], true );

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
				fputcsv(
					$stream,
					array(
						$row['consent_uuid'],
						$row['created_at'],
						$row['action'],
						$row['region'],
						$row['banner_version'],
						is_array( $categories ) ? implode( ', ', $categories ) : '',
						substr( (string) $row['ip_hash'], 0, 16 ) . '…',
					)
				);
			}

			++$page;
		} while ( 500 === $rows_count );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $stream );

		exit;
	}

	/*
	-----------------------------------------------------------------------
	 * Pomocné
	 */

	/**
	 * Skontroluje oprávnenie.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'hybrid-cookies-conset-r-plus' ) );
		}
	}

	/**
	 * Vypíše hlavičku obrazovky.
	 *
	 * @param string $title Titulok.
	 * @return void
	 */
	private function header( string $title ): void {
		echo '<div class="wrap hcc-admin">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Nastavenia sú uložené.', 'hybrid-cookies-conset-r-plus' );
			echo '</p></div>';
		}
	}

	/**
	 * Vypíše pätu obrazovky.
	 *
	 * @return void
	 */
	private function footer(): void {
		echo '</div>';
	}

	/**
	 * Vypíše chybové hlásenie.
	 *
	 * @param string $message Hlásenie.
	 * @return void
	 */
	private function notice_error( string $message ): void {
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Vypíše jednu štatistickú kartu.
	 *
	 * @param string $label  Názov.
	 * @param string $value  Hodnota.
	 * @param string $help   Pomocný text.
	 * @return void
	 */
	private function card( string $label, string $value, string $help ): void {
		echo '<div class="hcc-card">';
		echo '<div class="hcc-card__value">' . esc_html( $value ) . '</div>';
		echo '<div class="hcc-card__label">' . esc_html( $label ) . '</div>';
		echo '<div class="hcc-card__help">' . esc_html( $help ) . '</div>';
		echo '</div>';
	}

	/**
	 * Vráti preložený názov akcie.
	 *
	 * @param string $action Akcia.
	 * @return string
	 */
	private function get_action_label( string $action ): string {
		switch ( $action ) {
			case 'accept_all':
				return __( 'Prijal všetko', 'hybrid-cookies-conset-r-plus' );

			case 'reject_all':
				return __( 'Odmietol', 'hybrid-cookies-conset-r-plus' );

			default:
				return __( 'Vlastná voľba', 'hybrid-cookies-conset-r-plus' );
		}
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
