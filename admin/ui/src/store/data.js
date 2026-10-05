/**
 * Store — jediný zdroj pravdy pre React aplikáciu.
 *
 * Nepoužívame `@wordpress/data` — máme jeden obrazovkový strom a šesť
 * zdrojov dát. `useReducer` + provider je na to jednoduchší a ľahšie sa
 * testuje. `@wordpress/data` príde, až bude potrebné deliť stav medzi
 * viacero vstupov.
 */

import {
	createContext,
	useContext,
	useMemo,
	useReducer,
	useRef,
} from '@wordpress/element';
import { get } from '../api/rest';

/**
 * @type {Object} Kontext.
 */
const DataContext = createContext( null );

/**
 * Počiatočný stav.
 *
 * @return {Object} Stav.
 */
function initialState() {
	return {
		status: 'idle',
		error: '',
		dashboard: null,
		categories: [],
		cookies: { rows: [], total: 0, page: 1, pages: 1, providers: [] },
		banners: [],
		bannerDefaults: null,
		logs: { rows: [], total: 0, page: 1, pages: 1, summary: {} },
		settings: null,
		filters: {
			search: '',
			categoryId: 0,
			page: 1,
			logAction: '',
		},
	};
}

/**
 * Reducer — jediné miesto, kde sa stav mení.
 *
 * @param {Object} state  Aktuálny stav.
 * @param {Object} action Akcia.
 * @return {Object} Nový stav.
 */
function reducer( state, action ) {
	switch ( action.type ) {
		case 'loading':
			return { ...state, status: 'loading', error: '' };

		case 'error':
			return { ...state, status: 'error', error: action.message };

		case 'dashboard':
			return { ...state, dashboard: action.data };

		case 'categories':
			return { ...state, categories: action.data };

		case 'cookies':
			return { ...state, cookies: action.data };

		case 'banners':
			return { ...state, banners: action.data };

		case 'bannerDefaults':
			return { ...state, bannerDefaults: action.data };

		case 'logs':
			return { ...state, logs: action.data };

		case 'settings':
			return { ...state, settings: action.data };

		case 'filter':
			return {
				...state,
				filters: { ...state.filters, ...action.data },
			};

		case 'patch':
			return { ...state, ...action.data };

		default:
			return state;
	}
}

/**
 * Provider — načíta dáta a poskytne akcie.
 *
 * Načítavame všetko naraz namiesto po jednotlivých trasách: dashboard je
 * malý a návštevník adminu očakáva, že všetko vidí naraz. Ak sa niektorý
 * zdroj nepodarí načítať, ostatné fungujú ďalej.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.children Potomkovia.
 * @param {Object}   props.bootstrap Bootstrap dáta.
 * @return {JSX.Element} Provider.
 */
export function DataProvider( { children, bootstrap } ) {
	const [ state, dispatch ] = useReducer( reducer, undefined, initialState );

	// Filtre držíme v ref, nie v stave — `setFilters` musí čítať
	// najnovšie hodnoty bez toho, aby sa `useMemo` prepočítaval po
	// každej zmene stavu a vytvoril nové funkcie.
	const filtersRef = useRef( state.filters );

	const value = useMemo( () => {
		/**
		 * Načíta dáta zo všetkých zdrojov.
		 *
		 * `Promise.allSettled` nie `all` — jedna chyba nesmie zablokovať
		 * celú obrazovku.
		 *
		 * @return {Promise<void>}
		 */
		async function loadAll() {
			dispatch( { type: 'loading' } );

			const results = await Promise.allSettled( [
				get( '/dashboard' ),
				get( '/categories' ),
				get( '/banners' ),
				get( '/consent-logs' ),
				get( '/settings' ),
				get( '/banner-defaults' ),
			] );

			dispatch( { type: 'dashboard', data: unwrap( results[ 0 ] ) } );
			dispatch( { type: 'categories', data: unwrap( results[ 1 ] ) } );
			dispatch( { type: 'banners', data: unwrap( results[ 2 ] ) } );
			dispatch( { type: 'logs', data: unwrap( results[ 3 ] ) } );
			dispatch( { type: 'settings', data: unwrap( results[ 4 ] ) } );
			dispatch( { type: 'bannerDefaults', data: unwrap( results[ 5 ] ) } );

			// Cookies idú zvlášť, lebo majú vlastné filtre.
			const cookies = await reloadCookies();

			const failed = results
				.slice( 0, 5 )
				.filter( ( result ) => 'rejected' in result );

			if ( failed.length || 'rejected' in cookies ) {
				dispatch( {
					type: 'error',
					message: bootstrap.l10n?.error ?? 'Nastala chyba.',
				} );

				return;
			}

			dispatch( { type: 'patch', data: { error: '', status: 'idle' } } );
		}

		/**
		 * Znovu načíta cookies s aktuálnymi filtrami.
		 *
		 * @return {Promise<PromiseSettledResult>}
		 */
		async function reloadCookies() {
			const { search, categoryId, page } = filtersRef.current;

			try {
				const data = await get( '/cookies', {
					search: search || undefined,
					category_id: categoryId || undefined,
					page: page || 1,
					per_page: 30,
				} );

				dispatch( { type: 'cookies', data } );

				return { status: 'fulfilled' };
			} catch ( error ) {
				return { status: 'rejected', reason: error };
			}
		}

		/**
		 * Zmení filter a znovu načíta cookies.
		 *
		 * @param {Object} data Nové hodnoty filtrov.
		 * @return {Promise<void>}
		 */
		async function setFilters( data ) {
			filtersRef.current = { ...filtersRef.current, ...data, page: data.page ?? 1 };

			dispatch( { type: 'filter', data: filtersRef.current } );

			await reloadCookies();
		}

		return {
			state,
			loadAll,
			reloadCookies,
			setFilters,
			bootstrap,
		};
	}, [ state, bootstrap ] );

	return <DataContext.Provider value={ value }>{ children }</DataContext.Provider>;
}

/**
 * Vráti hodnotu z výsledku `Promise.allSettled`.
 *
 * @param {Object} result Výsledok.
 * @return {*} Hodnota alebo fallback.
 */
function unwrap( result ) {
	return 'value' in result ? result.value : null;
}

/**
 * Hook na prístup ku kontextu.
 *
 * @return {Object} Kontext.
 */
export function useData() {
	const context = useContext( DataContext );

	if ( ! context ) {
		throw new Error( 'useData musí byť volané vnútri DataProvider.' );
	}

	return context;
}