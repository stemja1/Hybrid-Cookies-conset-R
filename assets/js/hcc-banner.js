/**
 * Hybrid Cookies conset R+ – banner logika
 */
( function () {
	'use strict';

	var cfg = window.hccSettings || {};
	var i18n = cfg.i18n || {};
	var CATEGORIES = [ 'necessary', 'functional', 'statistics', 'marketing' ];

	function readConsent() {
		var parts = document.cookie ? document.cookie.split('; ') : [];
		for ( var i = 0; i < parts.length; i++ ) {
			if ( parts[ i ].indexOf( cfg.cookie + '=' ) === 0 ) {
				try {
					var json = decodeURIComponent( parts[ i ].split( '=' ).slice( 1 ).join( '=' ) );
					return JSON.parse( window.atob( json ) );
				} catch ( e ) {
					return null;
				}
			}
		}
		return null;
	}

	function esc( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value == null ? '' : String( value );
		return div.innerHTML;
	}

	function saveConsent( categories ) {
		var payload = {
			categories: categories,
			given: true,
			t: Math.floor( Date.now() / 1000 )
		};
		var value = window.btoa( unescape( encodeURIComponent( JSON.stringify( payload ) ) ) );
		var days = parseInt( cfg.expiry, 10 ) || 180;
		var expires = new Date( Date.now() + days * 864e5 ).toUTCString();

		document.cookie = cfg.cookie + '=' + encodeURIComponent( value ) +
			'; expires=' + expires + '; path=/; SameSite=Lax' +
			( location.protocol === 'https:' ? '; Secure' : '' );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: 'action=hcc_save_consent&nonce=' + encodeURIComponent( cfg.nonce ) +
				'&categories[]=' + categories.join( '&categories[]=' )
		} );
	}

	function reloadBlockedScripts() {
		var blocked = document.querySelectorAll( 'script[data-hcc-blocked]' );
		Array.prototype.forEach.call( blocked, function ( node ) {
			var fresh = document.createElement( 'script' );
			Array.prototype.forEach.call( node.attributes, function ( attr ) {
				if ( attr.name.indexOf( 'data-hcc' ) !== 0 ) {
					fresh.setAttribute( attr.name, attr.value );
				}
			} );
			node.parentNode.replaceChild( fresh, node );
		} );
	}

	function buildBanner() {
		var banner = document.getElementById( 'hcc-banner' );
		if ( ! banner ) {
			return;
		}

		banner.classList.add( 'hcc-banner--' + ( cfg.position || 'bottom' ) );
		if ( cfg.modal ) {
			banner.classList.add( 'hcc-banner--modal' );
		}

		var inner = banner.querySelector( '.hcc-banner__inner' );
		inner.innerHTML =
			'<div class="hcc-banner__content">' +
				'<p class="hcc-banner__title">' + esc( i18n.title ) + '</p>' +
				'<p class="hcc-banner__description">' + esc( i18n.description ) + '</p>' +
			'</div>' +
			'<div class="hcc-banner__actions">' +
				'<button type="button" class="hcc-btn hcc-btn--ghost" data-hcc-action="settings">' + esc( i18n.settings ) + '</button>' +
				( i18n.rejectAll ? '<button type="button" class="hcc-btn hcc-btn--secondary" data-hcc-action="reject">' + esc( i18n.rejectAll ) + '</button>' : '' ) +
				'<button type="button" class="hcc-btn hcc-btn--secondary" data-hcc-action="save">' + esc( i18n.save ) + '</button>' +
				'<button type="button" class="hcc-btn" data-hcc-action="accept">' + esc( i18n.acceptAll ) + '</button>' +
			'</div>';

		var details = document.createElement( 'div' );
		details.className = 'hcc-banner__details';
		details.hidden = true;

		CATEGORIES.forEach( function ( category ) {
			var disabled = category === 'necessary';
			var row = document.createElement( 'label' );
			row.className = 'hcc-banner__category' + ( disabled ? ' hcc-banner__category--disabled' : '' );
			row.innerHTML =
				'<input type="checkbox" value="' + category + '"' + ( disabled ? ' checked disabled' : '' ) + ' />' +
				'<span>' + esc( i18n[ category ] || category ) + '</span>';
			details.appendChild( row );
		} );
		inner.appendChild( details );

		inner.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-hcc-action]' );
			if ( ! button ) {
				return;
			}

			var action = button.getAttribute( 'data-hcc-action' );
			var selected = CATEGORIES.filter( function ( category ) {
				var input = details.querySelector( 'input[value="' + category + '"]' );
				return category === 'necessary' || ( input && input.checked );
			} );

			if ( action === 'settings' ) {
				details.hidden = ! details.hidden;
				return;
			}

			if ( action === 'accept' ) {
				selected = CATEGORIES.slice();
			}

			saveConsent( selected ).then( function () {
				banner.hidden = true;
				reloadBlockedScripts();
			} );
		} );
	}

	function init() {
		buildBanner();

		var consent = readConsent();
		var banner = document.getElementById( 'hcc-banner' );
		if ( ! banner ) {
			return;
		}

		if ( ! consent || ! consent.given ) {
			banner.hidden = false;
			return;
		}

		// Consent už existuje – odblokujeme povolené skripty.
		if ( Array.isArray( consent.categories ) && consent.categories.length ) {
			reloadBlockedScripts();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();