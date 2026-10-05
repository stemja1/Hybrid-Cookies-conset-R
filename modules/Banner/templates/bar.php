<?php
/**
 * Tenký banner — jediný riadok, ovládacie prvky vpravo.
 *
 * Premenné dostupné zo súboru:
 * $classes, $style, $state, $texts, $categories, $granted, $show_reject, $has_consent
 *
 * @package HybridCookies
 *
 * @var array<int,string>    $classes     CSS triedy bannera.
 * @var string               $style       Inline CSS premenné.
 * @var string               $state       JSON s verziou a kategóriami.
 * @var array<string,string> $texts       Texty tlačidiel a nadpisu.
 * @var array<int,array>     $categories  Kategórie.
 * @var array<int,string>    $granted     Súhlasné kategórie.
 * @var bool                 $show_reject Zobraziť tlačidlo „Odmietnuť".
 * @var bool                 $has_consent Má návštevník už súhlas.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="hcc-banner"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	style="<?php echo esc_attr( $style ); ?>"
	data-hcc-state="<?php echo esc_attr( $state ); ?>"
	role="dialog"
	aria-live="polite"
	aria-labelledby="hcc-banner-title"
	<?php echo $has_consent ? 'hidden' : ''; ?>
>
	<div class="hcc-banner__inner">

		<div class="hcc-banner__content">
			<h2 id="hcc-banner-title" class="hcc-banner__title">
				<?php echo esc_html( $texts['title'] ); ?>
			</h2>
			<p class="hcc-banner__description">
				<?php echo esc_html( $texts['description'] ); ?>
			</p>
		</div>

		<?php if ( ! empty( $categories ) ) : ?>
			<div class="hcc-banner__categories" hidden>
				<?php foreach ( $categories as $category ) : ?>
					<?php
					$is_necessary = ! empty( $category['is_necessary'] );
					$input_id     = 'hcc-cat-' . sanitize_html_class( (string) $category['slug'] );
					$is_checked   = $is_necessary || in_array( $category['slug'], $granted, true );
					?>
					<div class="hcc-banner__category<?php echo $is_necessary ? ' hcc-banner__category--locked' : ''; ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $input_id ); ?>"
							value="<?php echo esc_attr( $category['slug'] ); ?>"
							data-hcc-category="<?php echo esc_attr( $category['slug'] ); ?>"
							<?php checked( (bool) $is_checked ); ?>
							<?php disabled( $is_necessary ); ?>
						/>
						<label for="<?php echo esc_attr( $input_id ); ?>">
							<span class="hcc-banner__category-name"><?php echo esc_html( $category['name'] ); ?></span>
							<?php if ( ! empty( $category['description'] ) ) : ?>
								<span class="hcc-banner__category-desc"><?php echo esc_html( $category['description'] ); ?></span>
							<?php endif; ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="hcc-banner__actions">
			<button
				type="button"
				class="hcc-btn hcc-btn--ghost hcc-btn--small"
				data-hcc-action="settings"
			>
				<?php echo esc_html( $texts['settings'] ); ?>
			</button>

			<?php if ( $show_reject ) : ?>
				<button
					type="button"
					class="hcc-btn hcc-btn--reject hcc-btn--small"
					data-hcc-action="reject"
				>
					<?php echo esc_html( $texts['reject_all'] ); ?>
				</button>
			<?php endif; ?>

			<button
				type="button"
				class="hcc-btn hcc-btn--accept hcc-btn--small"
				data-hcc-action="accept"
			>
				<?php echo esc_html( $texts['accept_all'] ); ?>
			</button>
		</div>

	</div>
</div>