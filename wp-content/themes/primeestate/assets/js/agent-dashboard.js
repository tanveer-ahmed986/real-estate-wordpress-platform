/**
 * Agent Dashboard inline management (T075): status-change selects and note
 * forms PATCH the same `/inquiries/{id}` (T053) and `/viewings/{id}` (T068)
 * endpoints the dashboard table data was rendered from. Event delegation on
 * `document`, consistent with every other component in this theme.
 *
 * Expects `window.primeEstateFavorites` for `{ restUrl, nonce }` — reused
 * rather than introducing a second identically-shaped localized object.
 */
( function () {
	'use strict';

	var config = window.primeEstateFavorites || { restUrl: '', nonce: '' };

	function patchRecord( type, id, body ) {
		return fetch( config.restUrl + type + '/' + id, {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( body ),
		} );
	}

	document.addEventListener( 'change', function ( event ) {
		var filterSelect = event.target.closest ? event.target.closest( '.pe-status-filter__select' ) : null;

		if ( filterSelect ) {
			var table = document.querySelector( '[data-table="' + filterSelect.getAttribute( 'data-filter-target' ) + '"]' );
			var filterValue = filterSelect.value;

			if ( table ) {
				Array.prototype.forEach.call( table.querySelectorAll( 'tbody tr[data-status]' ), function ( row ) {
					row.hidden = !! filterValue && row.getAttribute( 'data-status' ) !== filterValue;
				} );
			}
			return;
		}

		var select = event.target.closest ? event.target.closest( '.pe-status-select' ) : null;

		if ( ! select ) {
			return;
		}

		var row = select.closest( '[data-record-id]' );
		if ( ! row ) {
			return;
		}

		patchRecord( select.getAttribute( 'data-endpoint' ), row.getAttribute( 'data-record-id' ), {
			status: select.value,
		} )
			.then( function ( response ) {
				if ( response.ok ) {
					row.setAttribute( 'data-status', select.value );
				}
			} )
			.catch( function () {
				// Leave the select as the user set it — a failed PATCH means the
				// server-side status didn't actually change, but there's no reliable
				// "previous value" to revert to without extra bookkeeping; the next
				// page load will show the true state.
			} );
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '.pe-note-form' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var row = form.closest( '[data-record-id]' );
		if ( ! row ) {
			return;
		}

		var textarea = form.querySelector( 'textarea[name="note"]' );
		var note = textarea.value.trim();

		if ( ! note ) {
			return;
		}

		patchRecord( form.getAttribute( 'data-endpoint' ), row.getAttribute( 'data-record-id' ), { note: note } )
			.then( function ( response ) {
				if ( response.ok ) {
					textarea.value = '';
				}
			} );
	} );
} )();
