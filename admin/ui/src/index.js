/**
 * Vstupný bod React aplikácie.
 *
 * Bootstrap dáta čítame z `data-hcc-bootstrap`, ktoré vypíše
 * `Admin_Menu::render_app_shell()`. Nonce a REST URL teda nemusia
 * cestovať cez `wp_localize_script` — je ich rovno v HTML.
 */

import { createRoot, render } from '@wordpress/element';
import App from './App';
import { createClient, readBootstrap } from './api/rest';
import './styles.scss';

const container = document.getElementById( 'hcc-app' );
const bootstrap = readBootstrap();

if ( ! container || ! bootstrap ) {
	// Bez bootstrapu sa aplikácia nenačíta — radšej než tichá chyba
	// v konzole, ktorú si nikto ne všimne.
	container?.insertAdjacentHTML(
		'afterbegin',
		'<p class="hcc-app__error">React aplikácia sa nenačítala — chýbajú bootstrap dáta.</p>'
	);
} else {
	createClient( bootstrap );

	const root = createRoot( container );

	render( <App bootstrap={ bootstrap } />, root );

	// Odstránime "Načítavam…" — React už prevzal kontrolu nad DOM.
	container.classList.add( 'is-ready' );
}