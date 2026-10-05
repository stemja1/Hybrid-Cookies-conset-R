/**
 * Testy pre `consent-banner.js`.
 *
 * Bežia cez Node.js + jsdom. Použitie:
 *   npm install --no-save jsdom
 *   node tests/js/banner.test.mjs
 *
 * Testy načítajú serverom vykreslený HTML banner — presne tak, ako by
 * sa naozaj objavil vo WordPresse.
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

// `readFileSync` acceptuje URL objekt — cesta s medzerami v názve priečinka
// by sa inak zlomila na `%20`.
const source = readFileSync(
	new URL( '../../public/js/consent-banner.js', import.meta.url ),
	'utf8'
);

/**
 * HTML vykreslené `Banner_Renderer` pre daný layout.
 *
 * @param {string} layout     `default`, `bar` alebo `box`.
 * @param {boolean} hasConsent Má už návštevník súhlas.
 * @return {string}
 */
function bannerHtml( layout, hasConsent ) {
	const hidden = hasConsent ? ' hidden' : '';

	return `
<div id="hcc-banner" class="hcc-banner hcc-banner--${ layout }" data-hcc-state='{"version":1,"granted":["necessary"],"layout":"${ layout }"}' role="dialog" aria-live="polite"${ hidden }>
	<div class="hcc-banner__inner">
		<div class="hcc-banner__content">
			<h2 class="hcc-banner__title">Používame cookies</h2>
			<p class="hcc-banner__description">Popis</p>
		</div>
		<div class="hcc-banner__categories" hidden>
			<div class="hcc-banner__category hcc-banner__category--locked">
				<input type="checkbox" value="necessary" data-hcc-category="necessary" checked disabled />
				<label><span class="hcc-banner__category-name">Nevyhnutné</span></label>
			</div>
			<div class="hcc-banner__category">
				<input type="checkbox" value="functional" data-hcc-category="functional" />
				<label><span class="hcc-banner__category-name">Funkčné</span></label>
			</div>
			<div class="hcc-banner__category">
				<input type="checkbox" value="statistics" data-hcc-category="statistics" />
				<label><span class="hcc-banner__category-name">Štatistické</span></label>
			</div>
			<div class="hcc-banner__category">
				<input type="checkbox" value="marketing" data-hcc-category="marketing" />
				<label><span class="hcc-banner__category-name">Marketingové</span></label>
			</div>
		</div>
		<div class="hcc-banner__actions">
			<button type="button" class="hcc-btn" data-hcc-action="settings">Nastavenia</button>
			<button type="button" class="hcc-btn hcc-btn--reject" data-hcc-action="reject">Odmietnuť</button>
			<button type="button" class="hcc-btn hcc-btn--save" data-hcc-action="save">Uložiť</button>
			<button type="button" class="hcc-btn hcc-btn--accept" data-hcc-action="accept">Prijať všetko</button>
		</div>
	</div>
</div>
`;
}

/**
 * Vytvorí DOM s bannerom a načíta skript.
 *
 * @param {object} opts Konfigurácia.
 * @return {Promise<object>} `{ window, document, submits }`.
 */
async function setup( opts = {} ) {
	const dom = new JSDOM(
		`<!DOCTYPE html><html><head></head><body>${ bannerHtml( opts.layout || 'default', opts.hasConsent ) }</body></html>`,
		{ runScripts: 'dangerously', url: 'https://example.test/' }
	);

	const submits = [];
	let nextResponse = opts.response || { categories: [ 'necessary' ] };

	dom.window.hccBanner = {
		consentUrl: 'https://example.test/wp-json/hcc/v1/consent',
		nonce: 'test-nonce',
		region: 'eu',
		law: 'GDPR',
		optout: false,
		texts: {},
	};

	dom.window.fetch = ( url, init ) => {
		let body = {};

		try {
			body = JSON.parse( init.body || '{}' );
		} catch ( e ) {
			body = {};
		}

		submits.push( { url, body, headers: init.headers } );

		return Promise.resolve( {
			json: () => Promise.resolve( nextResponse ),
		} );
	};

	dom.window.__setResponse = ( value ) => {
		nextResponse = value;
	};

	// Blokera v teste netreba — banner má volať len ak existuje.
	if ( opts.withBlocker ) {
		dom.window.__blockerCalls = [];
		dom.window.hccConsent = {
			applyConsent: ( data ) => dom.window.__blockerCalls.push( data ),
			revoke: () => Promise.resolve( {} ),
		};
	}

	dom.window.eval( source );

	// Skript sa hydratuje až na `DOMContentLoaded`. Bez čakania by testy
	// klikali na tlačidlá, na ktoré ešte nikto nepočúva.
	await waitForHydration( dom );

	return { window: dom.window, document: dom.window.document, submits };
}

/**
 * Vyčká na splnenie Promise v fetche.
 *
 * @return {Promise<void>}
 */
function tick() {
	return new Promise( ( resolve ) => setTimeout( resolve, 10 ) );
}

/**
 * Vyčká, kým sa skript hydrátuje na `DOMContentLoaded`.
 *
 * @param {object} dom JSDOM inštancia.
 * @return {Promise<void>}
 */
function waitForHydration( dom ) {
	return new Promise( ( resolve ) => {
		if ( dom.window.document.readyState !== 'loading' ) {
			resolve();
			return;
		}

		dom.window.addEventListener( 'DOMContentLoaded', () => resolve() );
	} );
}

const tests = [];

/**
 * Registruje test.
 *
 * @param {string}   name Názov.
 * @param {Function} fn   Implementácia.
 * @return {void}
 */
function test( name, fn ) {
	tests.push( { name, fn } );
}

test( 'DOM je vykreslený serverom, skript ho len oživuje', async () => {
	const { document } = await setup();

	// Kritický test: banner musí existovať v HTML, inak je JS jediným
	// zdrojom a vráti sa FOUC.
	const banner = document.getElementById( 'hcc-banner' );

	assert.ok( banner, 'banner musí byť v DOM pred načítaním JS' );
	assert.equal( banner.querySelectorAll( '.hcc-btn' ).length, 4 );
	assert.equal( document.querySelectorAll( 'input[data-hcc-category]' ).length, 4 );
} );

test( 'necessary je zapnutý a disabled', async () => {
	const { document } = await setup();
	const input = document.querySelector( 'input[data-hcc-category="necessary"]' );

	assert.equal( input.checked, true );
	assert.equal( input.disabled, true );
} );

test( 'prijať všetko odfajkuje všetko a odošle accept_all', async () => {
	const { document, submits } = await setup();

	document.querySelector( '[data-hcc-action="accept"]' ).click();

	await tick();

	assert.equal( submits.length, 1 );
	assert.equal( submits[ 0 ].body.action, 'accept_all' );
	assert.deepEqual(
		Array.from( submits[ 0 ].body.categories ).sort(),
		[ 'functional', 'marketing', 'necessary', 'statistics' ]
	);
} );

test( 'odmietnuť odošle len necessary', async () => {
	const { document, submits } = await setup();

	document.querySelector( '[data-hcc-action="reject"]' ).click();

	await tick();

	assert.equal( submits[ 0 ].body.action, 'reject_all' );
	assert.deepEqual( Array.from( submits[ 0 ].body.categories ), [ 'necessary' ] );
} );

test( 'necessary sa nedá vyradiť z custom voľby', async () => {
	const { document, submits } = await setup();

	// Návštevník odfajkuje marketing, ostatné nechá prázdne.
	document.querySelector( 'input[data-hcc-category="marketing"]' ).checked = true;

	document.querySelector( '[data-hcc-action="save"]' ).click();

	await tick();

	assert.equal( submits[ 0 ].body.action, 'custom' );
	assert.deepEqual(
		Array.from( submits[ 0 ].body.categories ).sort(),
		[ 'marketing', 'necessary' ]
	);
} );

test( 'nastavenia prepínajú zobrazenie kategórií', async () => {
	const { document } = await setup();
	const settings = document.querySelector( '[data-hcc-action="settings"]' );
	const details = document.querySelector( '.hcc-banner__categories' );

	assert.equal( details.hidden, true );

	settings.click();
	assert.equal( details.hidden, false );

	settings.click();
	assert.equal( details.hidden, true );
} );

test( 'po uložení sa banner skryje', async () => {
	const { document } = await setup();
	const banner = document.getElementById( 'hcc-banner' );

	assert.equal( banner.hidden, false );

	document.querySelector( '[data-hcc-action="accept"]' ).click();

	await tick();

	assert.equal( banner.hidden, true );
} );

test( 'nonce sa posiela v hlavičke', async () => {
	const { document, submits } = await setup();

	document.querySelector( '[data-hcc-action="accept"]' ).click();

	await tick();

	assert.equal( submits[ 0 ].headers[ 'X-WP-Nonce' ], 'test-nonce' );
	assert.equal( submits[ 0 ].headers[ 'Content-Type' ], 'application/json' );
} );

test( 'banner pošle URL endpointu súhlasu', async () => {
	const { document, submits } = await setup();

	document.querySelector( '[data-hcc-action="save"]' ).click();

	await tick();

	assert.equal( submits[ 0 ].url, 'https://example.test/wp-json/hcc/v1/consent' );
} );

test( 'po súhlase sa vyvolá hcc:consent-saved', async () => {
	const { document, window } = await setup();

	let event = null;

	document.addEventListener( 'hcc:consent-saved', ( e ) => {
		event = e.detail;
	} );

	document.querySelector( '[data-hcc-action="accept"]' ).click();

	await tick();

	assert.ok( event, 'event musí byť vyvolaný' );
	window.__unused = undefined;
} );

test( 'blocker dostane nové kategórie', async () => {
	const { document, window } = await setup( {
		withBlocker: true,
		response: { categories: [ 'necessary', 'marketing' ] },
	} );

	document.querySelector( '[data-hcc-action="accept"]' ).click();

	await tick();

	assert.equal( window.__blockerCalls.length, 1 );
	assert.deepEqual( window.__blockerCalls[ 0 ].categories, [ 'necessary', 'marketing' ] );
} );

test( 'revoke widget zobrazí banner', async () => {
	const dom = new JSDOM(
		'<!DOCTYPE html><html><body>' +
			bannerHtml( 'default', true ) +
			'<button data-hcc-revoke>Nastavenia cookies</button>' +
			'</body></html>',
		{ runScripts: 'dangerously', url: 'https://example.test/' }
	);

	dom.window.hccBanner = { consentUrl: '', nonce: 'test' };
	dom.window.hccConsent = { revoke: () => Promise.resolve( {} ) };
	dom.window.eval( source );

	// Bez čakania na `DOMContentLoaded` by `show()` nemal referenciu na
	// banner a neurobil nič.
	await waitForHydration( dom );

	const banner = dom.window.document.getElementById( 'hcc-banner' );

	// Súhlas bol — banner je skrytý.
	assert.equal( banner.hidden, true );

	dom.window.document.querySelector( '[data-hcc-revoke]' ).click();

	assert.equal( banner.hidden, false );
} );

test( 'revoke widget sa nezobrazí bez súhlasu', async () => {
	const dom = new JSDOM(
		'<!DOCTYPE html><html><body>' +
			bannerHtml( 'default', false ) +
			'<button data-hcc-revoke>Nastavenia cookies</button>' +
			'</body></html>',
		{ runScripts: 'dangerously', url: 'https://example.test/' }
	);

	dom.window.hccBanner = { consentUrl: '', nonce: 'test' };
	dom.window.eval( source );

	const banner = dom.window.document.getElementById( 'hcc-banner' );

	assert.equal( banner.hidden, false );
} );

test( 'verejné API banneru', async () => {
	const { window } = await setup();
	const banner = window.document.getElementById( 'hcc-banner' );

	assert.equal( typeof window.hccBanner.show, 'function' );
	assert.equal( typeof window.hccBanner.hide, 'function' );
	assert.equal( typeof window.hccBanner.getSelection, 'function' );

	banner.hidden = false;
	window.hccBanner.hide();
	assert.equal( banner.hidden, true );

	window.hccBanner.show();
	assert.equal( banner.hidden, false );
} );

test( 'getSelection vždy vráti necessary', async () => {
	const { window } = await setup();

	assert.deepEqual(
		Array.from( window.hccBanner.getSelection() ).sort(),
		[ 'necessary' ]
	);

	window.document.querySelector( 'input[data-hcc-category="statistics"]' ).checked = true;

	assert.deepEqual(
		Array.from( window.hccBanner.getSelection() ).sort(),
		[ 'necessary', 'statistics' ]
	);
} );

test( 'bar layout funguje rovnako', async () => {
	const { document, submits } = await setup( { layout: 'bar' } );

	assert.equal(
		document.getElementById( 'hcc-banner' ).className.includes( 'hcc-banner--bar' ),
		true
	);

	document.querySelector( '[data-hcc-action="accept"]' ).click();

	await tick();

	assert.equal( submits.length, 1 );
} );

test( 'box layout funguje rovnako', async () => {
	const { document, submits } = await setup( { layout: 'box' } );

	assert.equal(
		document.getElementById( 'hcc-banner' ).className.includes( 'hcc-banner--box' ),
		true
	);

	document.querySelector( '[data-hcc-action="reject"]' ).click();

	await tick();

	assert.equal( submits[ 0 ].body.action, 'reject_all' );
} );

test( 'banner bez prvkov tlačidiel nespôsobí chybu', async () => {
	const dom = new JSDOM(
		'<!DOCTYPE html><html><body><div id="hcc-banner" data-hcc-state="not-json"></div></body></html>',
		{ runScripts: 'dangerously', url: 'https://example.test/' }
	);

	dom.window.hccBanner = { consentUrl: '', nonce: 'test' };
	dom.window.eval( source );

	assert.deepEqual( Array.from( dom.window.hccBanner.getSelection() ), [ 'necessary' ] );
} );

let passed = 0;
let failed = 0;

for ( const { name, fn } of tests ) {
	try {
		await fn();

		passed++;
		console.log( `  ok  ${ name }` );
	} catch ( error ) {
		failed++;
		console.log( `FAIL  ${ name }` );
		console.log( `      ${ error.message }` );
	}
}

console.log( `\n${ passed } ok, ${ failed } zlyhalo` );
process.exit( failed > 0 ? 1 : 0 );