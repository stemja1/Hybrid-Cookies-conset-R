/**
 * Navigácia medzi obrazovkami.
 *
 * Používame `hash` routing namiesto `react-router`: WordPress načítava
 * každú admin stránku nanovo, takže serverové trasy by znamenali plné
 * načítanie pri každom kliku. Hash prežije reload a ide sa s ním
 * deliť.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Trasy aplikácie.
 *
 * @return {Array<{id:string,label:string,slug:string}>} Trasy.
 */
export function routes() {
	return [
		{
			id: 'dashboard',
			label: __( 'Prehľad', 'hybrid-cookies-conset-r-plus' ),
			slug: 'hybrid-cookies',
		},
		{
			id: 'banner',
			label: __( 'Banner', 'hybrid-cookies-conset-r-plus' ),
			slug: 'hybrid-cookies-banner',
		},
		{
			id: 'categories',
			label: __( 'Kategórie', 'hybrid-cookies-conset-r-plus' ),
			slug: 'hybrid-cookies-categories',
		},
		{
			id: 'cookies',
			label: __( 'Cookies', 'hybrid-cookies-conset-r-plus' ),
			slug: 'hybrid-cookies-cookies',
		},
		{
			id: 'logs',
			label: __( 'Log súhlasov', 'hybrid-cookies-conset-r-plus' ),
			slug: 'hybrid-cookies-logs',
		},
		{
			id: 'settings',
			label: __( 'Nastavenia', 'hybrid-cookies-conset-r-plus' ),
			slug: 'hybrid-cookies-settings',
		},
	];
}

/**
 * Hook na aktuálnu trasu.
 *
 * @param {string} initial Počiatočná trasa z bootstrapu.
 * @return {{route:string, navigate:Function}} Stav.
 */
export function useRoute( initial ) {
	const [ route, setRoute ] = useState( initial );

	useEffect( () => {
		const onHashChange = () => {
			const hash = window.location.hash.replace( /^#\/?/, '' );

			if ( hash ) {
				setRoute( hash );
			}
		};

		window.addEventListener( 'hashchange', onHashChange );

		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	/**
	 * Prejde na trasu.
	 *
	 * @param {string} next Nová trasa.
	 * @return {void}
	 */
	function navigate( next ) {
		window.location.hash = `/${ next }`;
		setRoute( next );
	}

	return { route, navigate };
}

/**
 * Navigačné menu.
 *
 * @param {Object}   props      Props.
 * @param {string}   props.route Aktuálna trasa.
 * @param {Function} props.navigate Prechod na trasu.
 * @return {JSX.Element} Menu.
 */
export function Navigation( { route, navigate } ) {
	return (
		<nav className="hcc-nav" aria-label={ __( 'Hybrid Cookies', 'hybrid-cookies-conset-r-plus' ) }>
			<ul>
				{ routes().map( ( item ) => (
					<li key={ item.id }>
						<button
							type="button"
							className={ `hcc-nav__item${
								route === item.id ? ' is-active' : ''
							}` }
							aria-current={ route === item.id ? 'page' : undefined }
							onClick={ () => navigate( item.id ) }
						>
							{ item.label }
						</button>
					</li>
				) ) }
			</ul>
		</nav>
	);
}