/**
 * Account Settings form (T086) — submits to WP core's own
 * `PATCH /wp/v2/users/me`, not a custom `primeestate/v1` route (see the code
 * comment on `primeestate_render_account_settings_form()`), so this uses
 * `config.coreRestUrl` rather than `config.restUrl`.
 *
 * Also owns the Saved Searches "Remove" button (DELETE /saved-searches/{id})
 * — the only interactive control on that dashboard section, since the rest
 * of it is plain server-rendered links.
 */
( function () {
	'use strict';

	var config = window.primeEstateFavorites || { restUrl: '', coreRestUrl: '', nonce: '' };

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-component="delete-saved-search"]' ) : null;

		if ( ! button ) {
			return;
		}

		var id = button.getAttribute( 'data-saved-search-id' );
		var row = button.closest( 'tr' );

		button.disabled = true;

		fetch( config.restUrl + 'saved-searches/' + id, {
			method: 'DELETE',
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( function ( response ) {
				if ( response.ok && row ) {
					row.remove();
				} else {
					button.disabled = false;
				}
			} )
			.catch( function () {
				button.disabled = false;
			} );
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '[data-component="account-settings-form"]' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var status = form.querySelector( '.pe-account-settings-form__status' );
		var submitButton = form.querySelector( 'button[type="submit"]' );

		var password = form.querySelector( '#pe-account-password' ).value;
		var passwordConfirm = form.querySelector( '#pe-account-password-confirm' ).value;

		if ( password && password !== passwordConfirm ) {
			status.textContent = 'Passwords do not match.';
			status.classList.add( 'pe-account-settings-form__status--error' );
			return;
		}

		var payload = {
			first_name: form.querySelector( '#pe-account-first-name' ).value,
			last_name: form.querySelector( '#pe-account-last-name' ).value,
			email: form.querySelector( '#pe-account-email' ).value,
		};

		if ( password ) {
			payload.password = password;
		}

		submitButton.disabled = true;
		status.classList.remove( 'pe-account-settings-form__status--error' );

		fetch( config.coreRestUrl + 'users/me', {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( payload ),
		} )
			.then( function ( response ) {
				status.textContent = response.ok ? 'Account settings saved.' : 'Something went wrong. Please try again.';
				status.classList.toggle( 'pe-account-settings-form__status--success', response.ok );
				status.classList.toggle( 'pe-account-settings-form__status--error', ! response.ok );

				if ( response.ok ) {
					form.querySelector( '#pe-account-password' ).value = '';
					form.querySelector( '#pe-account-password-confirm' ).value = '';
				}
			} )
			.catch( function () {
				status.textContent = 'Network error. Please try again.';
				status.classList.add( 'pe-account-settings-form__status--error' );
			} )
			.finally( function () {
				submitButton.disabled = false;
			} );
	} );
} )();
