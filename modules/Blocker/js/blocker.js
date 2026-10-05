/**
 * Hybrid Cookies conset R+ — automatické blokovanie skriptov
 *
 * Súbor sa načíta v `<head>` pred akýmkoľvek skriptom tretej strany.
 * Funguje v troch režimoch naraz:
 *
 * 1. `document.createElement` je prekrytý — zachytí skripty, ktoré si
 *    vytvárajú plugin počas behu (analytics, chat widgety, A/B testy).
 * 2. `MutationObserver` sleduje celý DOM — zachytí skripty vložené
 *    cez innerHTML alebo serverovým výstupom.
 * 3. Kategórie `necessary` neprekazujeme nikdy.
 *
 * Bez manuálneho tagovania — na rozdiel od Complianz tu netreba
 * upravovať kód tretích strán ani písať integračné adaptery.
 */
( function () {
	'use strict';

	var cfg = window.hccBlockerConfig || {};
	var PATTERNS = cfg.patterns || {};
	var GRANTED = cfg.granted || [ 'necessary' ];
	var COOKIE = cfg.cookie || 'hcc_consent';

	// Uzly, ktoré boli zablokované a čakajú na súhlas.
	var blocked = [];

	// Uzly zablokované pred `DOMContentLoaded` — ešte ich nepozoruje observer.
	var pending = [];

	var PHASE_ACTIVATE = 1;
	var PHASE_RUN = 2;

	var phase = PHASE_ACTIVATE;

	/**
	 * Prečíta súhlas priamo z cookie.
	 *
	 * Nemôžeme použiť `document.cookie` na čítanie v čase `<head>`,
	 * pretože DOM ešte nie je vytvorený — `document.cookie` vracia
	 * prázdny string. Čítame preto priamo cez `cookieStore` ak je
	 * dostupný, inak čakáme na `DOMContentLoaded` a čítame z cookie.
	 *
	 * @return {Promise<Object>}
	 */
	function readConsentAsync() {
		if ( window.cookieStore && window.cookieStore.get ) {
			return window.cookieStore.get( COOKIE )
				.then( function ( entry ) {
					return entry ? decodeConsent( entry.value ) : null;
				} )
				.catch( function () {
					return null;
				} );
		}

		return Promise.resolve( null );
	}

	/**
	 * Dekóduje hodnotu cookie súhlasu.
	 *
	 * @param {string} raw Hodnota cookie.
	 * @return {Object|null}
	 */
	function decodeConsent( raw ) {
		try {
			var json = decodeURIComponent( raw );
			var data = JSON.parse( window.atob( json ) );

			return data && data.given ? data : null;
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Prečíta súhlas synchronously cez `document.cookie`.
	 *
	 * Použiteľné až po `DOMContentLoaded`.
	 *
	 * @return {Object|null}
	 */
	function readConsentSync() {
		var parts = document.cookie ? document.cookie.split( '; ' ) : [];

		for ( var i = 0; i < parts.length; i++ ) {
			if ( parts[ i ].indexOf( COOKIE + '=' ) === 0 ) {
				return decodeConsent( parts[ i ].slice( COOKIE.length + 1 ) );
			}
		}

		return null;
	}

	/**
	 * Zistí, či je kategória súhlasná.
	 *
	 * @param {string} category Kategória.
	 * @return {boolean}
	 */
	function isGranted( category ) {
		if ( category === 'necessary' ) {
			return true;
		}

		return GRANTED.indexOf( category ) !== -1;
	}

	/**
	 * Nájde kategóriu pre URL.
	 *
	 * Berie najdlhší zhodný pattern, aby `googletagmanager.com/ns.html`
	 * vyhral pred `googletagmanager.com`.
	 *
	 * @param {string} src URL skriptu.
	 * @return {string|null}
	 */
	function matchCategory( src ) {
		var best = null;
		var bestLength = 0;

		for ( var pattern in PATTERNS ) {
			if ( src.indexOf( pattern ) !== -1 && pattern.length > bestLength ) {
				best = pattern;
				bestLength = pattern.length;
			}
		}

		return best === null ? null : PATTERNS[ best ];
	}

	/**
	 * Zablokuje uzol.
	 *
	 * `type="text/plain"` zabráni vykonaniu, ale uzol zostáva v DOM,
	 * takže ho neskôr vieme nahradiť bez toho, aby sme zistili jeho
	 * pozíciu nanovo.
	 *
	 * @param {Element} node    Uzol.
	 * @param {string}  src     URL skriptu.
	 * @param {string}  category Kategória.
	 * @return {boolean} Či bol uzol zablokovaný.
	 */
	function blockNode( node, src, category ) {
		if ( ! node || node.getAttribute( 'data-hcc-blocked' ) ) {
			return false;
		}

		if ( isGranted( category ) ) {
			return false;
		}

		if ( node.tagName === 'SCRIPT' ) {
			var originalType = node.getAttribute( 'type' ) || '';
			node.setAttribute( 'type', 'text/plain' );
			node.setAttribute( 'data-hcc-original-type', originalType );
		} else if ( node.tagName === 'IFRAME' ) {
			// Iframe sa dá zastaviť jednoducho — odstránením src.
			var originalSrc = node.getAttribute( 'src' ) || '';
			node.setAttribute( 'data-hcc-original-src', originalSrc );
			node.removeAttribute( 'src' );
		} else {
			return false;
		}

		node.setAttribute( 'data-hcc-blocked', '1' );
		node.setAttribute( 'data-hcc-category', category );

		blocked.push( {
			node: node,
			src: src,
			category: category
		} );

		cfg.blockedList = cfg.blockedList || [];
		cfg.blockedList.push( src );

		document.dispatchEvent( new CustomEvent( 'hcc:before-block', {
			detail: { src: src, category: category }
		} ) );

		if ( cfg.debug && window.console ) {
			window.console.log( '[hcc] blocked', category, src );
		}

		return true;
	}

	/**
	 * Skúsi zistiť kategóriu a prípadne zablokuje uzol.
	 *
	 * @param {Element} node Uzol.
	 * @return {boolean}
	 */
	function inspect( node ) {
		if ( ! node || node.nodeType !== 1 ) {
			return false;
		}

		var tag = node.tagName;
		var src = '';

		if ( tag === 'SCRIPT' ) {
			src = node.src || node.getAttribute( 'src' ) || '';
		} else if ( tag === 'IFRAME' ) {
			src = node.src || node.getAttribute( 'src' ) || '';
		} else {
			return false;
		}

		if ( ! src || src.indexOf( 'data:' ) === 0 ) {
			return false;
		}

		var category = matchCategory( src );

		return category ? blockNode( node, src, category ) : false;
	}

	/**
	 * Preverí uzol a jeho potomkov.
	 *
	 * @param {Element} node Uzol.
	 * @return {void}
	 */
	function inspectTree( node ) {
		if ( ! node || node.nodeType !== 1 ) {
			return;
		}

		inspect( node );

		if ( node.querySelectorAll ) {
			var children = node.querySelectorAll( 'script[src], iframe[src]' );
			for ( var i = 0; i < children.length; i++ ) {
				inspect( children[ i ] );
			}
		}
	}

	/**
	 * Skontroluje uzly, ktoré prišli do fronty pred `DOMContentLoaded`.
	 *
	 * @return {void}
	 */
	function flushPending() {
		while ( pending.length ) {
			inspect( pending.shift() );
		}
	}

	/**
	 * Prekryje `document.createElement`.
	 *
	 * Pluginy typicky volajú `document.createElement( 'script' )` a potom
	 * nastavia `src`. Zachytíme uzol ešte pred vložením do DOM, takže
	 * skript sa načíta oneskorenie alebo vôbec.
	 *
	 * @param {string} tagName Názov elementu.
	 * @return {Element}
	 */
	var nativeCreateElement = document.createElement;

	document.createElement = function ( tagName ) {
		var node = nativeCreateElement.call( document, tagName );
		var tag = String( tagName ).toUpperCase();

		if ( tag !== 'SCRIPT' && tag !== 'IFRAME' ) {
			return node;
		}

		// Prekryjeme `setAttribute`, aby sa zachytilo aj `node.setAttribute('src', …)`.
		var nativeSetAttribute = node.setAttribute;

		node.setAttribute = function ( name, value ) {
			if ( name === 'src' || ( tag === 'SCRIPT' && name === 'type' ) ) {
				if ( name === 'src' && typeof value === 'string' ) {
					checkCreated( node, tag, value );
				}
			}

			return nativeSetAttribute.call( node, name, value );
		};

		// A priamo `node.src = …`, čo je najčastejší spôsob.
		try {
			var descriptor = Object.getOwnPropertyDescriptor(
				window[ tag === 'SCRIPT' ? 'HTMLScriptElement' : 'HTMLIFrameElement' ].prototype,
				'src'
			);

			if ( descriptor && descriptor.set ) {
				Object.defineProperty( node, 'src', {
					configurable: true,
					enumerable: true,
					get: descriptor.get
						? function () { return descriptor.get.call( node ); }
						: function () { return node.getAttribute( 'src' ); },
					set: function ( value ) {
						descriptor.set.call( node, value );
						if ( typeof value === 'string' ) {
							checkCreated( node, tag, value );
						}
					}
				} );
			}
		} catch ( e ) {
			// Niektoré prehliadače nedovolia definovať property na inštancii.
			// `setAttribute` zachytí prípad.
		}

		return node;
	};

	/**
	 * Skontroluje uzol vytvorený cez `createElement`.
	 *
	 * @param {Element} node Uzol.
	 * @param {string}  tag  Názov elementu.
	 * @param {string}  src  URL.
	 * @return {void}
	 */
	function checkCreated( node, tag, src ) {
		if ( phase === PHASE_RUN ) {
			// Uzol je už v DOM — MutationObserver ho zachytí.
			if ( node.parentNode ) {
				inspect( node );
			} else {
				pending.push( node );
			}

			return;
		}

		// Uzol ešte nie je v DOM. Ak ho nikto nepripojí, nikdy ho
		// neuvidíme — zablokujeme teraz a aj keď ostane osamotený,
		// taký skript sa ničoho nezúčastní.
		inspect( node );
	}

	/**
	 * Odblokuje uzly novej kategórie.
	 *
	 * @param {string} category Kategória.
	 * @return {number} Počet odblokovaných uzlov.
	 */
	function unblockCategory( category ) {
		var restored = 0;

		for ( var i = 0; i < blocked.length; i++ ) {
			var entry = blocked[ i ];

			if ( entry.category !== category ) {
				continue;
			}

			restore( entry );
			blocked.splice( i, 1 );
			i--;
			restored++;
		}

		return restored;
	}

	/**
	 * Obnoví pôvodný uzol.
	 *
	 * Nahradzujeme uzol novým, aby sa skript načítal nanovo — obnovenie
	 * `type` späť na pôvodnú hodnotu by nespôsobilo nové načítanie
	 * v prehliadačoch, ktoré už majú prvky v cache.
	 *
	 * @param {Object} entry Záznam z `blocked`.
	 * @return {void}
	 */
	function restore( entry ) {
		var node = entry.node;

		if ( ! node || ! node.parentNode ) {
			return;
		}

		var fresh = document.createElement( node.tagName );

		for ( var i = 0; i < node.attributes.length; i++ ) {
			var attr = node.attributes[ i ];

			// Atribúty, ktoré sme pridali my, neprenášame.
			if ( attr.name.indexOf( 'data-hcc' ) === 0 ) {
				continue;
			}

			// `type="text/plain"` je náš — pôvodný je uložený osobitne.
			if ( node.tagName === 'SCRIPT' && attr.name === 'type' ) {
				continue;
			}

			// Prázdny `src` je náš — pôvodný je uložený osobitne.
			if ( node.tagName === 'IFRAME' && attr.name === 'src' ) {
				continue;
			}

			fresh.setAttribute( attr.name, attr.value );
		}

		if ( node.tagName === 'SCRIPT' ) {
			var originalType = node.getAttribute( 'data-hcc-original-type' );

			// Inline skripty nemajú `type`, ten bol pôvodne prázdny.
			fresh.setAttribute( 'type', originalType || 'text/javascript' );
			fresh.setAttribute( 'src', entry.src );
		} else if ( node.tagName === 'IFRAME' ) {
			fresh.setAttribute( 'src', node.getAttribute( 'data-hcc-original-src' ) || entry.src );
		}

		node.parentNode.replaceChild( fresh, node );

		document.dispatchEvent( new CustomEvent( 'hcc:after-unblock', {
			detail: { src: entry.src, category: entry.category }
		} ) );

		if ( cfg.debug && window.console ) {
			window.console.log( '[hcc] unblocked', entry.category, entry.src );
		}
	}

	/**
	 * Reaguje na súhlas udelený cez REST.
	 *
	 * @param {Object} data Payload z odpovede endpointu.
	 * @return {void}
	 */
	function applyConsent( data ) {
		if ( ! data || ! data.categories ) {
			return;
		}

		GRANTED = data.categories;

		for ( var i = 0; i < data.categories.length; i++ ) {
			var category = data.categories[ i ];
			var count = unblockCategory( category );

			document.dispatchEvent( new CustomEvent( 'hcc:category-enabled', {
				detail: { category: category, restored: count }
			} ) );
		}
	}

	/**
	 * Verejné API pre vývojárov a pluginy.
	 */
	window.hccConsent = window.hccConsent || {};

	window.hccConsent.getGranted = function () {
		return GRANTED.slice();
	};

	window.hccConsent.hasConsent = function ( category ) {
		return isGranted( category );
	};

	window.hccConsent.getBlocked = function () {
		return blocked.map( function ( entry ) {
			return { src: entry.src, category: entry.category };
		} );
	};

	window.hccConsent.save = function ( categories ) {
		return fetch( cfg.consentUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify( { categories: categories } )
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( data ) {
			if ( data && data.categories ) {
				applyConsent( data );
			}

			return data;
		} );
	};

	window.hccConsent.revoke = function () {
		return fetch( cfg.consentUrl + '/revoke', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce
			}
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( data ) {
			// Všetko odblokované treba znova zablokovať — návštevník
			// súhlas odvolal, takže skripty sa nesmú načítať.
			GRANTED = [ 'necessary' ];

			for ( var i = 0; i < blocked.length; i++ ) {
				blockNode( blocked[ i ].node, blocked[ i ].src, blocked[ i ].category );
			}

			document.dispatchEvent( new CustomEvent( 'hcc:consent-revoked', {
				detail: data
			} ) );

			return data;
		} );
	};

	// Banner volá túto metódu po uložení súhlasu. Bez nej by uzly
	// zostali zablokované, kým by sa nenačítala nová stránka.
	window.hccConsent.applyConsent = applyConsent;

	/**
	 * Spustí sledovanie DOM.
	 *
	 * @return {void}
	 */
	function startObserver() {
		if ( ! window.MutationObserver ) {
			return;
		}

		var observer = new MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				var added = mutations[ i ].addedNodes;

				for ( var j = 0; j < added.length; j++ ) {
					inspectTree( added[ j ] );
				}
			}
		} );

		observer.observe( document.documentElement, {
			childList: true,
			subtree: true
		} );
	}

	/**
	 * Spustí všetko.
	 *
	 * @return {void}
	 */
	function init() {
		// Kategórie, ktoré sú súhlasné, načítame z cookie.
		var consent = readConsentSync();

		if ( consent && consent.categories ) {
			GRANTED = consent.categories;
		}

		// `cookieStore` dá výsledok aj v `<head>`, bez čakania na DOM.
		readConsentAsync().then( function ( asyncConsent ) {
			if ( asyncConsent && asyncConsent.categories ) {
				GRANTED = asyncConsent.categories;
			}
		} );

		phase = PHASE_RUN;

		flushPending();
		inspectTree( document.documentElement );
		startObserver();

		document.dispatchEvent( new CustomEvent( 'hcc:ready', {
			detail: { granted: GRANTED.slice() }
		} ) );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();