/**
 * "Save this search" button (FilterPanel, logged-in users only — the panel
 * itself hides this control for guests, see filter-panel.php). Sends the
 * current page's own query string, since the archive page IS the applied
 * filter set (property-search.js keeps the URL in sync via
 * `history.pushState` on every filter change) — no separate form-state
 * tracking needed here.
 *
 * Expects `window.primeEstateFavorites = { restUrl, nonce }` (same
 * dependency-only localized handle every other REST-calling script uses).
 */
( function () {
	'use strict';

	var config = window.primeEstateFavorites || { restUrl: '', nonce: '' };

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-component="save-search-button"]' ) : null;

		if ( ! button ) {
			return;
		}

		var wrapper = button.closest( '[data-component="save-search"]' );
		var labelInput = wrapper.querySelector( '.pe-save-search__label' );
		var status = wrapper.querySelector( '.pe-save-search__status' );
		var queryString = window.location.search.replace( /^\?/, '' );

		button.disabled = true;
		status.textContent = '';
		status.classList.remove( 'pe-save-search__status--error', 'pe-save-search__status--success' );

		fetch( config.restUrl + 'saved-searches', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( {
				label: labelInput.value,
				query_string: queryString,
			} ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok ) {
					status.textContent = 'Saved. We will email you when new properties match "' + result.body.label + '".';
					status.classList.add( 'pe-save-search__status--success' );
					labelInput.value = '';
				} else {
					status.textContent = result.body && result.body.message ? result.body.message : 'Could not save this search.';
					status.classList.add( 'pe-save-search__status--error' );
				}
			} )
			.catch( function () {
				status.textContent = 'Network error. Please try again.';
				status.classList.add( 'pe-save-search__status--error' );
			} )
			.finally( function () {
				button.disabled = false;
			} );
	} );
} )();
