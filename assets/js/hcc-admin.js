/**
 * Hybrid Cookies conset R+ – admin skripty
 */
( function () {
	'use strict';

	var cfg = window.hccAdmin || {};
	var strings = cfg.strings || {};

	function confirmDelete( id ) {
		if ( ! window.confirm( strings.confirmDelete || 'Naozaj chcete zmazať tento záznam?' ) ) {
			return;
		}

		// TODO: Napájať na endpoint `DELETE /hcc/v1/cookies/<id>`.
	}

	function toggleCategory( input ) {
		var row = input.closest( '.hcc-banner__category' );
		if ( ! row ) {
			return;
		}
		row.classList.toggle( 'hcc-banner__category--disabled', ! input.checked );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.hcc-delete' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				confirmDelete( link.getAttribute( 'data-id' ) );
			} );
		} );

		document.querySelectorAll( '.hcc-banner__category input' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				toggleCategory( input );
			} );
		} );
	} );
} )();