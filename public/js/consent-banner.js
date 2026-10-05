/**
 * Hybrid Cookies conset R+ — hydrátcia bannera
 *
 * Banner je vykreslený na serveri. Tento skript ho iba „oživí" —
 * pripája obsluhy udalostí. Nevytvára ho od nuly, takže nevzniká FOUC.
 */
( function () {
	'use strict';

	var cfg = window.hccBanner || {};
	var banner = null;

	/**
	 * Prečíta stav z `data-hcc-state`.
	 *
	 * @return {Object}
	 */
	function readState() {
		if ( ! banner ) {
			return { version: 1, granted: [ 'necessary' ], layout: 'default' };
		}

		try {
			return JSON.parse( banner.getAttribute( 'data-hcc-state' ) || '{}' );
		} catch ( e ) {
			return { version: 1, granted: [ 'necessary' ], layout: 'default' };
		}
	}

	/**
	 * Vráti odfajkované kategórie.
	 *
	 * `necessary` je vždy prítomný, aj keď je checkbox disabled — inak by
	 * návštevník mohol odmietnutúť kategóriu, bez ktorej stránka nefunguje.
	 *
	 * @return {Array<string>}
	 */
	function readSelection() {
		var selected = [ 'necessary' ];

		if ( ! banner ) {
			return selected;
		}

		var inputs = banner.querySelectorAll( 'input[data-hcc-category]' );

		Array.prototype.forEach.call( inputs, function ( input ) {
			if ( input.checked && selected.indexOf( input.value ) === -1 ) {
				selected.push( input.value );
			}
		} );

		return selected;
	}

	/**
	 * Skryje alebo zobrazí detail kategórií.
	 *
	 * @param {boolean} show Zobraziť.
	 * @return {void}
	 */
	function toggleDetails( show ) {
		var details = banner && banner.querySelector( '.hcc-banner__categories' );

		if ( details ) {
			details.hidden = ! show;
		}
	}

	/**
	 * Odošle súhlas a schová banner.
	 *
	 * @param {Array<string>} categories Kategórie.
	 * @param {string}        action      Akcia pre server.
	 * @return {Promise}
	 */
	function submit( categories, action ) {
		var body = {
			categories: categories,
			action: action
		};

		return fetch( cfg.consentUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify( body )
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( data ) {
			hide();

			// Blocker (ak je aktívny) musí vedieť, čo sa zmenilo —
			// odblokuje uzly novej kategórie a vyvolá event.
			if ( window.hccConsent && window.hccConsent.applyConsent ) {
				window.hccConsent.applyConsent( data );
			}

			document.dispatchEvent( new CustomEvent( 'hcc:consent-saved', {
				detail: data
			} ) );

			return data;
		} );
	}

	/**
	 * Skryje banner.
	 *
	 * @return {void}
	 */
	function hide() {
		if ( banner ) {
			banner.hidden = true;
		}

		document.dispatchEvent( new CustomEvent( 'hcc:banner-hidden' ) );
	}

	/**
	 * Zobrazí banner (revoke widget).
	 *
	 * @return {void}
	 */
	function show() {
		if ( banner ) {
			banner.hidden = false;
		}

		document.dispatchEvent( new CustomEvent( 'hcc:banner-shown' ) );
	}

	/**
	 * Pripojí obsluhy banneru.
	 *
	 * @return {void}
	 */
	function bind() {
		if ( ! banner ) {
			return;
		}

		banner.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-hcc-action]' );

			if ( ! button ) {
				return;
			}

			var action = button.getAttribute( 'data-hcc-action' );

			if ( action === 'settings' ) {
				var details = banner.querySelector( '.hcc-banner__categories' );

				toggleDetails( details && details.hidden );

				return;
			}

			// `necessary` je vždy v `readSelection()` — aj keby bol
			// pri `accept` odfajkovaný, dostane sa do súhlasu.
			if ( action === 'accept' ) {
				var inputs = banner.querySelectorAll( 'input[data-hcc-category]' );

				Array.prototype.forEach.call( inputs, function ( input ) {
					input.checked = true;
				} );

				submit( readSelection(), 'accept_all' );
				return;
			}

			if ( action === 'reject' ) {
				submit( [ 'necessary' ], 'reject_all' );
				return;
			}

			submit( readSelection(), 'custom' );
		} );
	}

	/**
	 * Pripojí revoke widget.
	 *
	 * @return {void}
	 */
	function bindRevoke() {
		var widgets = document.querySelectorAll( '[data-hcc-revoke]' );

		Array.prototype.forEach.call( widgets, function ( widget ) {
			widget.addEventListener( 'click', function () {
				if ( window.hccConsent && window.hccConsent.revoke ) {
					window.hccConsent.revoke();
				}

				show();
			} );
		} );
	}

	/**
	 * Verejné API pre témy a pluginy.
	 */
	window.hccBanner = window.hccBanner || cfg;

	window.hccBanner.show = show;
	window.hccBanner.hide = hide;
	window.hccBanner.getSelection = readSelection;
	window.hccBanner.openDetails = function () {
		toggleDetails( true );
	};

	/**
	 * Spustí hydrátáciu.
	 *
	 * @return {void}
	 */
	function init() {
		banner = document.getElementById( 'hcc-banner' );

		bind();
		bindRevoke();

		document.dispatchEvent( new CustomEvent( 'hcc:ready', {
			detail: readState()
		} ) );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();