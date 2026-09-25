/**
 * Agent-facing Add/Edit Property form (T074). Two different submit targets
 * depending on mode, per agent-property-form-render.php's doc comment:
 * create → the custom `POST primeestate/v1/properties` (business-rule
 * status branching); edit → WordPress core's own
 * `PATCH wp/v2/property-posts/{id}`, which expects taxonomy fields as term
 * ID arrays rather than the slugs the custom endpoint accepts — this file
 * reads each field's `data-term-id` attribute for the edit path only.
 */
( function () {
	'use strict';

	var config = window.primeEstateFavorites || { restUrl: '', nonce: '' };

	function selectedTermId( select ) {
		var option = select.options[ select.selectedIndex ];
		return option ? parseInt( option.getAttribute( 'data-term-id' ), 10 ) : null;
	}

	function checkedTermIds( form, name ) {
		return Array.prototype.slice
			.call( form.querySelectorAll( 'input[name="' + name + '"]:checked' ) )
			.map( function ( input ) {
				return parseInt( input.getAttribute( 'data-term-id' ), 10 );
			} );
	}

	function uploadedImageIds( form ) {
		var raw = form.querySelector( '#pe-property-image-ids' ).value;
		return raw
			? raw.split( ',' ).map( function ( id ) {
				return parseInt( id, 10 );
			} )
			: [];
	}

	function buildCreatePayload( form ) {
		return {
			title: form.querySelector( '#pe-property-title' ).value,
			description: form.querySelector( '#pe-property-description' ).value,
			price: parseFloat( form.querySelector( '#pe-property-price' ).value ) || 0,
			listing_type: form.querySelector( '#pe-property-listing-type' ).value,
			property_type: form.querySelector( '#pe-property-type' ).value,
			location: {
				city: form.querySelector( '#pe-property-city' ).value,
				address: form.querySelector( '#pe-property-address' ).value,
			},
			bedrooms: parseInt( form.querySelector( '#pe-property-bedrooms' ).value, 10 ) || 0,
			bathrooms: parseInt( form.querySelector( '#pe-property-bathrooms' ).value, 10 ) || 0,
			area: parseFloat( form.querySelector( '#pe-property-area' ).value ) || 0,
			amenities: Array.prototype.slice
				.call( form.querySelectorAll( 'input[name="amenities[]"]:checked' ) )
				.map( function ( input ) {
					return input.value;
				} ),
			images: uploadedImageIds( form ),
		};
	}

	/**
	 * Uploads each selected file to `POST /uploads` (T081) individually,
	 * appending its returned attachment ID to the hidden `image_ids` field
	 * and a thumbnail to the visible list — used by both the agent Add/Edit
	 * form and the public Submit Property form (T080), since both render
	 * the same file input.
	 */
	function initImageUpload( form ) {
		var input = form.querySelector( '#pe-property-images' );
		var hidden = form.querySelector( '#pe-property-image-ids' );
		var list = form.querySelector( '.pe-property-form__image-list' );

		if ( ! input ) {
			return;
		}

		input.addEventListener( 'change', function () {
			Array.prototype.forEach.call( input.files, function ( file ) {
				var formData = new FormData();
				formData.append( 'file', file );

				var item = document.createElement( 'li' );
				item.textContent = 'Uploading ' + file.name + '…';
				list.appendChild( item );

				fetch( config.restUrl + 'uploads', {
					method: 'POST',
					headers: { 'X-WP-Nonce': config.nonce },
					body: formData,
				} )
					.then( function ( response ) {
						return response.json().then( function ( body ) {
							return { ok: response.ok, body: body };
						} );
					} )
					.then( function ( result ) {
						if ( result.ok ) {
							var ids = uploadedImageIds( form );
							ids.push( result.body.attachment_id );
							hidden.value = ids.join( ',' );
							item.textContent = file.name + ' uploaded.';
						} else {
							item.textContent = file.name + ': ' + ( ( result.body && result.body.message ) || 'upload failed' );
						}
					} )
					.catch( function () {
						item.textContent = file.name + ': network error.';
					} );
			} );

			input.value = '';
		} );
	}

	function buildEditPayload( form ) {
		return {
			title: form.querySelector( '#pe-property-title' ).value,
			content: form.querySelector( '#pe-property-description' ).value,
			listing_type: [ selectedTermId( form.querySelector( '#pe-property-listing-type' ) ) ].filter( Boolean ),
			property_type: [ selectedTermId( form.querySelector( '#pe-property-type' ) ) ].filter( Boolean ),
			location: [ selectedTermId( form.querySelector( '#pe-property-city' ) ) ].filter( Boolean ),
			amenity: checkedTermIds( form, 'amenities[]' ),
			meta: {
				_pe_price: parseFloat( form.querySelector( '#pe-property-price' ).value ) || 0,
				_pe_address: form.querySelector( '#pe-property-address' ).value,
				_pe_bedrooms: parseInt( form.querySelector( '#pe-property-bedrooms' ).value, 10 ) || 0,
				_pe_bathrooms: parseInt( form.querySelector( '#pe-property-bathrooms' ).value, 10 ) || 0,
				_pe_area: parseFloat( form.querySelector( '#pe-property-area' ).value ) || 0,
			},
		};
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest ? event.target.closest( '[data-component="property-form"]' ) : null;

		if ( ! form ) {
			return;
		}

		event.preventDefault();

		var status = form.querySelector( '.pe-property-form__status' );
		var submitButton = form.querySelector( 'button[type="submit"]' );
		var isEdit = 'edit' === form.getAttribute( 'data-mode' );

		var url = isEdit
			? config.restUrl.replace( 'primeestate/v1/', 'wp/v2/property-posts/' ) + form.getAttribute( 'data-property-id' )
			: config.restUrl + 'properties';

		submitButton.disabled = true;
		status.textContent = '';

		fetch( url, {
			method: isEdit ? 'PATCH' : 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( isEdit ? buildEditPayload( form ) : buildCreatePayload( form ) ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok ) {
					status.textContent = isEdit ? 'Changes saved.' : ( form.getAttribute( 'data-create-success-message' ) || 'Property added.' );
					status.classList.remove( 'pe-property-form__status--error' );
					status.classList.add( 'pe-property-form__status--success' );

					var redirectTo = form.getAttribute( 'data-redirect-on-create' );

					if ( ! isEdit && redirectTo ) {
						window.setTimeout( function () {
							window.location.href = redirectTo;
						}, 1200 );
					}
				} else {
					status.textContent = ( result.body && result.body.message ) || 'Something went wrong. Please try again.';
					status.classList.add( 'pe-property-form__status--error' );
				}
			} )
			.catch( function () {
				status.textContent = 'Network error. Please try again.';
				status.classList.add( 'pe-property-form__status--error' );
			} )
			.finally( function () {
				submitButton.disabled = false;
			} );
	} );

	Array.prototype.forEach.call( document.querySelectorAll( '[data-component="property-form"]' ), initImageUpload );
} )();
