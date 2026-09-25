/**
 * InquiryForm progressive enhancement: intercepts submit and posts via
 * fetch() so the visitor gets an inline success/error message instead of a
 * full-page navigation to the REST endpoint's raw JSON response (the no-JS
 * baseline, still functional — see inquiry-form.php).
 */
( function () {
	'use strict';

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '[data-component="inquiry-form"]' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var status = form.querySelector( '.pe-inquiry-form__status' );
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
					return { ok: response.ok, status: response.status, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok ) {
					form.reset();
					status.textContent = form.getAttribute( 'data-success-message' ) || 'Thank you — your inquiry has been sent.';
					status.classList.remove( 'pe-inquiry-form__status--error' );
					status.classList.add( 'pe-inquiry-form__status--success' );
				} else {
					status.textContent = ( result.body && result.body.message ) || 'Something went wrong. Please try again.';
					status.classList.remove( 'pe-inquiry-form__status--success' );
					status.classList.add( 'pe-inquiry-form__status--error' );
				}
			} )
			.catch( function () {
				status.textContent = 'Network error. Please try again.';
				status.classList.add( 'pe-inquiry-form__status--error' );
			} )
			.finally( function () {
				submitButton.disabled = false;
			} );
	} );
} )();
