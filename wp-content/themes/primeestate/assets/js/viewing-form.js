/**
 * ViewingForm progressive enhancement — same submit-via-fetch pattern as
 * inquiry-form.js, but on success (T069) replaces the entire form with a
 * confirmation panel showing the stored status and requested date/time,
 * rather than just an inline message — the viewing request has its own
 * status lifecycle (FR-032), so "submitted" is worth showing as a distinct
 * state, not just a toast-style note next to an now-irrelevant empty form.
 */
( function () {
	'use strict';

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value;
		return div.innerHTML;
	}

	function renderConfirmation( wrapper, viewing ) {
		wrapper.innerHTML =
			'<div class="pe-viewing-form__confirmation" role="status">' +
			'<p class="pe-viewing-form__confirmation-status">Status: <strong>' + escapeHtml( viewing.status || 'Requested' ) + '</strong></p>' +
			'<p>Requested for ' + escapeHtml( viewing.preferred_date || '' ) + ' at ' + escapeHtml( viewing.preferred_time || '' ) + '. The agent will confirm shortly.</p>' +
			'</div>';
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '[data-component="viewing-form"]' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var wrapper = form.closest( '[data-component="viewing-form-wrapper"]' ) || form;
		var status = form.querySelector( '.pe-viewing-form__status' );
		var submitButton = form.querySelector( 'button[type="submit"]' );

		submitButton.disabled = true;
		status.textContent = '';

		fetch( form.action, {
			method: 'POST',
			headers: { Accept: 'application/json' },
			body: new FormData( form ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok ) {
					renderConfirmation( wrapper, result.body || {} );
				} else {
					status.textContent = ( result.body && result.body.message ) || 'Something went wrong. Please try again.';
					status.classList.add( 'pe-viewing-form__status--error' );
					submitButton.disabled = false;
				}
			} )
			.catch( function () {
				status.textContent = 'Network error. Please try again.';
				status.classList.add( 'pe-viewing-form__status--error' );
				submitButton.disabled = false;
			} );
	} );
} )();
