/**
 * Testy pre `blocker.js`.
 *
 * Bežia cez Node.js + jsdom bez prehliadača. Použitie:
 *   npm install --no-save jsdom
 *   node tests/js/blocker.test.mjs
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const SCRIPT = readFileSync(
	new URL( '../../modules/Blocker/js/blocker.js', import.meta.url ),
	'utf8'
);

const PATTERNS = {
	'googletagmanager.com': 'marketing',
	'googletagmanager.com/ns.html': 'necessary',
	'google-analytics.com': 'statistics',
	'facebook.net': 'marketing',
	'recaptcha.net': 'necessary',
};

/**
 * Vytvorí DOM, načíta blocker.js a vráti obsah okna.
 *
 * @param {object} opts Konfigurácia blockeru.
 * @param {string} opts.granted Súhlasné kategórie.
 * @param {string} opts.body    HTML tela dokumentu.
 * @return {object} `{ window, document }`.
 */
function setup( opts ) {
	const dom = new JSDOM(
		`<!DOCTYPE html><html><head></head><body>${ opts.body || '' }</body></html>`,
		{
			runScripts: 'dangerously',
			url: 'https://example.test/',
			pretendToBeVisual: true,
		}
	);

	dom.window.hccBlockerConfig = {
		cookie: 'hcc_consent',
		granted: opts.granted || [ 'necessary' ],
		categories: [ 'functional', 'statistics', 'marketing' ],
		patterns: PATTERNS,
		consentUrl: 'https://example.test/wp-json/hcc/v1/consent',
		nonce: 'test-nonce',
		blockedList: [],
	};

	// jsdom neposkytuje `fetch`. Stub vráti to, čo by vrátil endpoint —
	// kategórie z posledného volania `hccConsent.save()`.
	dom.window.__lastConsent = opts.granted || [ 'necessary' ];

	dom.window.fetch = ( url, init ) => {
		dom.window.__fetchCalls = dom.window.__fetchCalls || [];
		dom.window.__fetchCalls.push( { url, init } );

		let categories = dom.window.__lastConsent;

		if ( init && init.body ) {
			try {
				categories = JSON.parse( init.body ).categories;
				dom.window.__lastConsent = categories;
			} catch ( e ) {
				// Neplatné telo — použijeme predchádzajúci stav.
			}
		}

		return Promise.resolve( {
			json: () => Promise.resolve( { categories } ),
		} );
	};

	dom.window.eval( SCRIPT );

	return dom;
}

/**
 * Počká na `DOMContentLoaded` a na spracovanie fronty.
 *
 * @param {object} dom JSDOM inštancia.
 * @return {Promise<void>}
 */
async function settle( dom ) {
	await new Promise( ( resolve ) => {
		if ( dom.window.document.readyState !== 'loading' ) {
			resolve();
			return;
		}

		dom.window.addEventListener( 'load', () => resolve() );
	} );

	// MutationObserver je asynchrónny.
	await new Promise( ( resolve ) => setTimeout( resolve, 20 ) );
}

const tests = [];

/**
 * Registruje test.
 *
 * @param {string}   name Názov testu.
 * @param {Function} fn   Testovací funkcia.
 * @return {void}
 */
function test( name, fn ) {
	tests.push( { name, fn } );
}

test( 'blokuje skript bez súhlasu', async () => {
	const dom = setup( { body: '<script src="https://www.googletagmanager.com/gtag/js"></script>' } );

	await settle( dom );

	const script = dom.window.document.querySelector( 'script[src*="googletagmanager"]' );

	assert.equal( script.getAttribute( 'type' ), 'text/plain' );
	assert.equal( script.getAttribute( 'data-hcc-blocked' ), '1' );
	assert.equal( script.getAttribute( 'data-hcc-category' ), 'marketing' );
} );

test( 'nesúhlasný iframe sa zablokuje odstránením src', async () => {
	const dom = setup( { body: '<iframe src="https://connect.facebook.net/sdk"></iframe>' } );

	await settle( dom );

	const iframe = dom.window.document.querySelector( 'iframe' );

	assert.equal( iframe.getAttribute( 'src' ), null );
	assert.equal( iframe.getAttribute( 'data-hcc-original-src' ), 'https://connect.facebook.net/sdk' );
	assert.equal( iframe.getAttribute( 'data-hcc-blocked' ), '1' );
} );

test( 'necessary sa neblokuje ani bez súhlasu', async () => {
	const dom = setup( {
		body: '<script src="https://www.recaptcha.net/recaptcha/api.js"></script>',
	} );

	await settle( dom );

	const script = dom.window.document.querySelector( 'script[src*="recaptcha"]' );

	assert.equal( script.getAttribute( 'type' ), null );
	assert.equal( script.getAttribute( 'data-hcc-blocked' ), null );
} );

test( 'najdlhší pattern vyhráva', async () => {
	const dom = setup( {
		body: '<iframe src="https://www.googletagmanager.com/ns.html?id=GTM-1"></iframe>',
	} );

	await settle( dom );

	const iframe = dom.window.document.querySelector( 'iframe' );

	// `ns.html` je `necessary`, takže musí zostať nedotknutý.
	assert.equal( iframe.getAttribute( 'src' ), 'https://www.googletagmanager.com/ns.html?id=GTM-1' );
	assert.equal( iframe.getAttribute( 'data-hcc-blocked' ), null );
} );

test( 'prijatá kategória sa nablokuje', async () => {
	const dom = setup( {
		granted: [ 'necessary', 'statistics' ],
		body: '<script src="https://www.google-analytics.com/analytics.js"></script>',
	} );

	await settle( dom );

	const script = dom.window.document.querySelector( 'script[src*="google-analytics"]' );

	assert.equal( script.getAttribute( 'data-hcc-blocked' ), null );
} );

test( 'skript vložený cez innerHTML sa zablokuje', async () => {
	const dom = setup( { body: '<div id="target"></div>' } );

	await settle( dom );

	const target = dom.window.document.getElementById( 'target' );

	target.innerHTML = '<script src="https://connect.facebook.net/fbevents.js"></script>';

	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );

	const script = dom.window.document.querySelector( 'script[src*="facebook"]' );

	assert.ok( script, 'skript má byť v DOM' );
	assert.equal( script.getAttribute( 'type' ), 'text/plain' );
} );

test( 'skript vytvorený cez createElement sa zablokuje', async () => {
	const dom = setup( {} );

	await settle( dom );

	const doc = dom.window.document;

	const script = doc.createElement( 'script' );

	script.src = 'https://www.googletagmanager.com/gtm.js';

	doc.body.appendChild( script );

	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );

	assert.equal( script.getAttribute( 'type' ), 'text/plain' );
} );

test( 'skript vytvorený a vložený naraz sa zablokuje', async () => {
	const dom = setup( {} );

	await settle( dom );

	const doc = dom.window.document;
	const script = doc.createElement( 'script' );

	script.setAttribute( 'src', 'https://www.google-analytics.com/ga.js' );

	doc.head.appendChild( script );

	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );

	assert.equal( script.getAttribute( 'type' ), 'text/plain' );
} );

test( 'inline skript bez src sa netýka', async () => {
	const dom = setup( { body: '<script>window.inline = 1;</script>' } );

	await settle( dom );

	const script = dom.window.document.querySelector( 'script:not([src])' );

	assert.equal( script.getAttribute( 'data-hcc-blocked' ), null );
	assert.equal( dom.window.inline, 1 );
} );

test( 'data: URI sa netýka', async () => {
	const dom = setup( {
		body: '<iframe src="data:text/html,<p>test</p>"></iframe>',
	} );

	await settle( dom );

	const iframe = dom.window.document.querySelector( 'iframe' );

	assert.equal( iframe.getAttribute( 'data-hcc-blocked' ), null );
} );

test( 'neznámy provider sa netýka', async () => {
	const dom = setup( {
		body: '<script src="https://cdn.mojskript.sk/app.js"></script>',
	} );

	await settle( dom );

	const script = dom.window.document.querySelector( 'script[src*="mojskript"]' );

	assert.equal( script.getAttribute( 'data-hcc-blocked' ), null );
} );

test( 'po súhlase sa skript odblokuje a načíta', async () => {
	const dom = setup( {
		body: '<script src="https://www.googletagmanager.com/gtag/js"></script>',
	} );

	await settle( dom );

	assert.equal(
		dom.window.document.querySelector( 'script[src*="googletagmanager"]' ).getAttribute( 'type' ),
		'text/plain'
	);

	await dom.window.hccConsent.save( [ 'necessary', 'marketing' ] );

	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );

	const script = dom.window.document.querySelector( 'script[src*="googletagmanager"]' );

	assert.equal( script.getAttribute( 'type' ), 'text/javascript' );
	assert.equal( script.getAttribute( 'data-hcc-blocked' ), null );
	assert.equal( script.getAttribute( 'src' ), 'https://www.googletagmanager.com/gtag/js' );
} );

test( 'odblokovanie vyvolá custom event', async () => {
	const dom = setup( {
		body: '<script src="https://connect.facebook.net/fbevents.js"></script>',
	} );

	await settle( dom );

	let event = null;

	dom.window.document.addEventListener( 'hcc:category-enabled', ( e ) => {
		event = e.detail;
	} );

	await dom.window.hccConsent.save( [ 'necessary', 'marketing' ] );

	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );

	assert.ok( event, 'event musí byť vyvolaný' );
	assert.equal( event.category, 'marketing' );
	assert.equal( event.restored, 1 );
} );

test( 'inline skript si zachová vlastný type', async () => {
	const dom = setup( {
		body: '<script type="module" src="https://www.googletagmanager.com/mod.js"></script>',
	} );

	await settle( dom );

	await dom.window.hccConsent.save( [ 'necessary', 'marketing' ] );

	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );

	const script = dom.window.document.querySelector( 'script[src*="googletagmanager"]' );

	assert.equal( script.getAttribute( 'type' ), 'module' );
} );

test( 'verejné API vrací stav', async () => {
	const dom = setup( { granted: [ 'necessary', 'marketing' ] } );

	await settle( dom );

	assert.deepEqual( dom.window.hccConsent.getGranted(), [ 'necessary', 'marketing' ] );
	assert.equal( dom.window.hccConsent.hasConsent( 'marketing' ), true );
	assert.equal( dom.window.hccConsent.hasConsent( 'statistics' ), false );
	assert.equal( dom.window.hccConsent.hasConsent( 'necessary' ), true );
} );

test( 'getBlocked vypíše zablokované skripty', async () => {
	const dom = setup( {
		body: '<script src="https://www.google-analytics.com/analytics.js"></script>',
	} );

	await settle( dom );

	const blocked = dom.window.hccConsent.getBlocked();

	assert.equal( blocked.length, 1 );
	assert.equal( blocked[ 0 ].category, 'statistics' );
} );

test( 'pred blokovaním vyvolá event', async () => {
	const dom = setup( {
		body: '<script src="https://connect.facebook.net/fbevents.js"></script>',
	} );

	let beforeBlock = null;

	dom.window.document.addEventListener( 'hcc:before-block', ( e ) => {
		beforeBlock = e.detail;
	} );

	await settle( dom );

	assert.ok( beforeBlock );
	assert.equal( beforeBlock.category, 'marketing' );
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