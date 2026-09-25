/**
 * Comparison-set client logic (FR-022/023, data-model.md §8): client-side
 * only, max 4, `localStorage` (same mechanism as guest favorites — no server
 * persistence for either guests or logged-in users). Event delegation on
 * `document` so buttons injected by the archive's AJAX fragment swap work
 * without re-binding.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'pe_compare';
	var MAX_COMPARE = 4;

	function getCompareList() {
		try {
			var raw = window.localStorage.getItem( STORAGE_KEY );
			var parsed = raw ? JSON.parse( raw ) : [];
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( e ) {
			return [];
		}
	}

	function setCompareList( ids ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( ids ) );
		} catch ( e ) {
			// localStorage unavailable — comparison simply won't persist for this visitor.
		}
	}

	function syncButtonState( button ) {
		var id = parseInt( button.getAttribute( 'data-property-id' ), 10 );
		var inCompare = getCompareList().indexOf( id ) !== -1;
		button.setAttribute( 'aria-pressed', inCompare ? 'true' : 'false' );
	}

	function syncAllButtons() {
		document
			.querySelectorAll( '[data-component="compare-button"]' )
			.forEach( syncButtonState );
	}

	function toggleCompare( button ) {
		var id = parseInt( button.getAttribute( 'data-property-id' ), 10 );
		var list = getCompareList();
		var index = list.indexOf( id );

		if ( -1 !== index ) {
			list.splice( index, 1 );
			setCompareList( list );
			button.setAttribute( 'aria-pressed', 'false' );
			return;
		}

		if ( list.length >= MAX_COMPARE ) {
			button.setAttribute( 'data-limit-reached', 'true' );
			window.setTimeout( function () {
				button.removeAttribute( 'data-limit-reached' );
			}, 2000 );
			return;
		}

		list.push( id );
		setCompareList( list );
		button.setAttribute( 'aria-pressed', 'true' );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-component="compare-button"]' ) : null;

		if ( button ) {
			toggleCompare( button );
		}
	} );

	document.addEventListener( 'DOMContentLoaded', syncAllButtons );
	if ( 'loading' !== document.readyState ) {
		syncAllButtons();
	}

	/**
	 * The compare page (theme/inc/compare-render.php) always starts empty —
	 * comparison has no server-side state for anyone, guest or logged-in
	 * (data-model.md §8) — so this populates it from `localStorage` via the
	 * same server-rendered fragment pattern used elsewhere, and self-heals
	 * `localStorage` by dropping any ID the server didn't return (T064).
	 */
	function populateComparePage() {
		var container = document.getElementById( 'pe-compare-results' );

		if ( ! container ) {
			return;
		}

		var ids = getCompareList();

		if ( ! ids.length ) {
			return; // Leave the shortcode's default "add properties" message as-is.
		}

		var fragmentUrl = container.getAttribute( 'data-fragment-url' ) + '?ids=' + ids.join( ',' );

		fetch( fragmentUrl, { headers: { 'X-PrimeEstate-Fragment': '1' } } )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( html ) {
				container.innerHTML = html;

				var returnedIds = Array.prototype.slice
					.call( container.querySelectorAll( '[data-property-id]' ) )
					.map( function ( el ) {
						return parseInt( el.getAttribute( 'data-property-id' ), 10 );
					} );

				var stillValid = ids.filter( function ( id ) {
					return returnedIds.indexOf( id ) !== -1;
				} );

				if ( stillValid.length !== ids.length ) {
					setCompareList( stillValid );
				}
			} )
			.catch( function () {
				container.innerHTML = '<p>Could not load comparison.</p>';
			} );
	}

	populateComparePage();
} )();
