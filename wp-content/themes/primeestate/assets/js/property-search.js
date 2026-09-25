/**
 * Progressive enhancement for the property archive's FilterPanel
 * (research.md §1): intercepts the filter form's submit, fetches the same
 * URL's HTML results-fragment (theme/inc/archive-render.php's
 * `X-PrimeEstate-Fragment` path) instead of a full page reload, swaps the
 * results container, and syncs the address bar via `history.pushState` so
 * the URL stays shareable (SC-013). Without JavaScript, the same form still
 * works as a plain GET submission to a full page reload — this file only
 * enhances, never replaces, that baseline.
 */
( function () {
	'use strict';

	var resultsContainer = document.getElementById( 'pe-archive-results' );

	if ( ! resultsContainer ) {
		return;
	}

	var archiveUrl = resultsContainer.getAttribute( 'data-fragment-url' );

	function findFilterForm( target ) {
		return target.closest ? target.closest( '.pe-filter-panel' ) : null;
	}

	async function fetchAndSwap( url, pushState ) {
		resultsContainer.setAttribute( 'aria-busy', 'true' );

		try {
			var response = await fetch( url, {
				headers: { 'X-PrimeEstate-Fragment': '1' },
			} );

			if ( ! response.ok ) {
				return; // Fall through: user can still resubmit the form normally.
			}

			var html = await response.text();
			resultsContainer.innerHTML = html;

			if ( pushState ) {
				window.history.pushState( {}, '', url );
			}

			resultsContainer.focus();
		} catch ( error ) {
			// Network failure: leave the last-rendered results in place rather
			// than blanking the page — the form itself still works via a real
			// submit if the user tries again.
		} finally {
			resultsContainer.removeAttribute( 'aria-busy' );
		}
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = findFilterForm( event.target );

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var params = new URLSearchParams( new FormData( form ) );
		var url = archiveUrl + ( params.toString() ? '?' + params.toString() : '' );

		fetchAndSwap( url, true );
	} );

	window.addEventListener( 'popstate', function () {
		fetchAndSwap( window.location.href, false );
	} );

	resultsContainer.setAttribute( 'tabindex', '-1' );
} )();
