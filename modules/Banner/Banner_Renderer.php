<?php
/**
 * Vykreslenie bannera na serveri.
 *
 * @package HybridCookies
 */

declare( strict_types=1 );

namespace HCC\Modules\Banner;

use HCC\Modules\Categories\Categories_Module;
use HCC\Modules\Categories\Categories_Repository;
use HCC\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer bannera.
 *
 * Banner sa kreslí server-side a JS ho iba hydrátuje. Ak by sa vykreslil
 * až cez JS, návštevník by najprv videl stránku bez bannera a banner by
 * potom „skočil" do obrazovky — typický FOUC, na ktorý sa sťažovalo na
 * CookieYes.
 */
class Banner_Renderer {

	/**
	 * Repozitár bannerov.
	 *
	 * @var Banner_Repository|null
	 */
	private ?Banner_Repository $repository = null;

	/**
	 * Repozitár kategórií.
	 *
	 * @var Categories_Repository|null
	 */
	private ?Categories_Repository $categories = null;

	/**
	 * Vykreslí banner.
	 *
	 * @param array<string,mixed> $banner Banner z repozitára.
	 * @param array<int,string>   $granted Aktuálne súhlasné kategórie.
	 * @return void
	 */
	public function render( array $banner, array $granted ): void {
		$config = $banner['config'];
		$layout = (string) $config['layout'];

		// `necessary` je vždy zapnutý a nedá sa odpojiť.
		$has_consent = ! empty( $granted ) && count( $granted ) > 1;

		$classes = array(
			'hcc-banner',
			'hcc-banner--' . $layout,
			'hcc-banner--' . $config['position'],
		);

		if ( ! empty( $config['modal'] ) ) {
			$classes[] = 'hcc-banner--modal';
		}

		if ( $has_consent ) {
			$classes[] = 'hcc-banner--has-consent';
		}

		$state = wp_json_encode(
			array(
				'version' => (int) $banner['version'],
				'granted' => array_values( $granted ),
				'layout'  => $layout,
			)
		);

		$template = __DIR__ . '/templates/' . $layout . '.php';

		if ( ! is_readable( $template ) ) {
			$template = __DIR__ . '/templates/default.php';
		}

		$colors      = (array) $config['colors'];
		$style       = $this->inline_style( $colors );
		$categories  = $this->get_categories();
		$texts       = (array) $config['texts'];
		$show_reject = ! empty( $config['show_reject_all'] );

		include $template;
	}

	/**
	 * Vráti inline CSS z farebného nastavenia.
	 *
	 * Farby sú sanitizované v repozitári (len hex), takže tu nehrozí
	 * injekcia cez `style` atribút.
	 *
	 * @param array<string,string> $colors Farby.
	 * @return string
	 */
	private function inline_style( array $colors ): string {
		$rules = array(
			'--hcc-bg'     => $colors['background'] ?? '#ffffff',
			'--hcc-text'   => $colors['text'] ?? '#1e1e1e',
			'--hcc-accent' => $colors['accent'] ?? '#2271b1',
			'--hcc-border' => $colors['border'] ?? '#e0e0e0',
		);

		$declarations = array();

		foreach ( $rules as $property => $value ) {
			$declarations[] = $property . ':' . $value;
		}

		return implode( ';', $declarations );
	}

	/**
	 * Vráti kategórie s preloženými názvami a popismi.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_categories(): array {
		$categories_module = Plugin::instance()->get_module( 'categories' );

		if ( ! $categories_module instanceof Categories_Module ) {
			return array();
		}

		$repository = $this->get_categories_repository();
		$result     = array();

		foreach ( $repository->all() as $category ) {
			$result[] = array(
				'slug'         => $category['slug'],
				'name'         => $category['name'],
				'description'  => $category['description'],
				'is_necessary' => ! empty( $category['is_necessary'] ),
			);
		}

		return $result;
	}

	/**
	 * Vráti repozitár bannerov.
	 *
	 * @return Banner_Repository
	 */
	public function get_repository(): Banner_Repository {
		if ( null === $this->repository ) {
			$this->repository = new Banner_Repository();
		}

		return $this->repository;
	}

	/**
	 * Vráti repozitár kategórií.
	 *
	 * @return Categories_Repository
	 */
	private function get_categories_repository(): Categories_Repository {
		if ( null === $this->categories ) {
			$this->categories = new Categories_Repository();
		}

		return $this->categories;
	}
}
