/**
 * Agent profile edit form — submits to `PATCH /agents/{id}` (T076).
 */
( function () {
	'use strict';

	var config = window.primeEstateFavorites || { restUrl: '', nonce: '' };

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '[data-component="agent-profile-form"]' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var status = form.querySelector( '.pe-profile-form__status' );
		var submitButton = form.querySelector( 'button[type="submit"]' );

		var areas = Array.prototype.slice
			.call( form.querySelectorAll( 'input[name="areas[]"]:checked' ) )
			.map( function ( input ) {
				return parseInt( input.value, 10 );
			} );

		var specializations = form.querySelector( '#pe-profile-specializations' ).value
			.split( ',' )
			.map( function ( value ) {
				return value.trim();
			} )
			.filter( Boolean );

		var payload = {
			bio: form.querySelector( '#pe-profile-bio' ).value,
			phone: form.querySelector( '#pe-profile-phone' ).value,
			whatsapp: form.querySelector( '#pe-profile-whatsapp' ).value,
			license: form.querySelector( '#pe-profile-license' ).value,
			office: form.querySelector( '#pe-profile-office' ).value,
			areas: areas,
			specializations: specializations,
		};

		submitButton.disabled = true;

		fetch( config.restUrl + 'agents/' + form.getAttribute( 'data-agent-id' ), {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( payload ),
		} )
			.then( function ( response ) {
				status.textContent = response.ok ? 'Profile saved.' : 'Something went wrong. Please try again.';
				status.classList.toggle( 'pe-profile-form__status--success', response.ok );
				status.classList.toggle( 'pe-profile-form__status--error', ! response.ok );
			} )
			.catch( function () {
				status.textContent = 'Network error. Please try again.';
				status.classList.add( 'pe-profile-form__status--error' );
			} )
			.finally( function () {
				submitButton.disabled = false;
			} );
	} );
} )();
