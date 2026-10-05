/**
 * REST klient.
 *
 * Používame `wp.apiFetch`, ktorý sám pripojí `X-WP-Nonce` z nastavenia
 * WordPressu a zvládne chyby ako `rest_forbidden`. Nepridávame vlastnú
 * vrstvu nad axios/fetch — každá ďalšia vrstva je miesto, kde sa dá
 * zabudnúť na nonce.
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Načíta bootstrap dáta z `data-hcc-bootstrap`.
 *
 * `Admin_Menu::render_app_shell()` vypisuje nonce, REST URL a počiatočnú
 * trasu priamo do atribútu — netreba ich posielať cez `wp_localize_script`.
 *
 * @return {Object|null} Bootstrap dáta alebo null, ak atribút chýba.
 */
export function readBootstrap() {
	const node = document.getElementById( 'hcc-app' );

	if ( ! node ) {
		return null;
	}

	const raw = node.getAttribute( 'data-hcc-bootstrap' );

	if ( ! raw ) {
		return null;
	}

	try {
		return JSON.parse( raw );
	} catch ( error ) {
		return null;
	}
}

/**
 * Vráti REST klienta s nastavenou nonce.
 *
 * @param {Object} bootstrap Bootstrap dáta.
 * @return {Object} apiFetch inštancia.
 */
export function createClient( bootstrap ) {
	apiFetch.use( apiFetch.createNonceMiddleware( bootstrap.nonce ) );
	apiFetch.use( apiFetch.createRootURLMiddleware( bootstrap.restUrl ) );

	return apiFetch;
}

/**
 * Vykoná GET požiadavku.
 *
 * @param {string} path       Cesta bez prefixu namespace.
 * @param {Object} queryParams Query parametre.
 * @return {Promise<any>}
 */
export function get( path, queryParams = {} ) {
	return apiFetch( { path: path.replace( /^\//, '' ), urlQueryParams: queryParams } );
}

/**
 * Vykoná POST požiadavku.
 *
 * @param {string} path Cesta bez prefixu namespace.
 * @param {Object} data Telo požiadavky.
 * @return {Promise<any>}
 */
export function post( path, data ) {
	return apiFetch( {
		path: path.replace( /^\//, '' ),
		method: 'POST',
		data,
	} );
}

/**
 * Vykoná PUT požiadavku.
 *
 * @param {string} path Cesta vrátane ID.
 * @param {Object} data Telo požiadavky.
 * @return {Promise<any>}
 */
export function put( path, data ) {
	return apiFetch( {
		path: path.replace( /^\//, '' ),
		method: 'PUT',
		data,
	} );
}

/**
 * Vykoná DELETE požiadavku.
 *
 * @param {string} path Cesta vrátane ID.
 * @return {Promise<any>}
 */
export function remove( path ) {
	return apiFetch( { path: path.replace( /^\//, '' ), method: 'DELETE' } );
}

/**
 * Preloží chybu z `apiFetch` na zrozumiteľný text.
 *
 * `apiFetch` vracia `WP_Error` s poľom `message`. Keď odpoveď nie je
 * JSON (napr. 502 od web servera), chyba je obyčajný Error.
 *
 * @param {unknown} error Chyba.
 * @return {string} Text na zobrazenie.
 */
export function errorMessage( error ) {
	if ( error && typeof error === 'object' && 'message' in error ) {
		return String( error.message );
	}

	if ( error instanceof Error ) {
		return error.message;
	}

	return 'Nastala chyba.';
}