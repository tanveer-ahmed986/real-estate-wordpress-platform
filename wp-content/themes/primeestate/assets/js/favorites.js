/**
 * Favorites (FR-019–FR-021, research.md §4). Guests: `localStorage` only.
 * Logged-in: server via REST, with the button's initial state already
 * server-rendered correctly (favorite-button.php) — this file only handles
 * the *click* for logged-in users, not the initial paint.
 *
 * Uses event delegation on `document` (not per-button listeners) so
 * favorite buttons injected later by the archive's AJAX fragment swap
 * (property-search.js) work without any re-binding step.
 *
 * Expects `window.primeEstateFavorites = { restUrl, nonce, isLoggedIn }`,
 * localized from theme/inc/enqueue.php.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'pe_favorites';
	var config = window.primeEstateFavorites || { isLoggedIn: false, restUrl: '', nonce: '' };

	function getGuestFavorites() {
		try {
			var raw = window.localStorage.getItem( STORAGE_KEY );
			var parsed = raw ? JSON.parse( raw ) : [];
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( e ) {
			return [];
		}
	}

	function setGuestFavorites( ids ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( ids ) );
		} catch ( e ) {
			// localStorage unavailable (private mode, quota) — favoriting simply
			// won't persist for this guest; the button still gives feedback.
		}
	}

	function setButtonState( button, favorited ) {
		button.setAttribute( 'aria-pressed', favorited ? 'true' : 'false' );
		var icon = button.querySelector( '[aria-hidden="true"]' );
		if ( icon ) {
			icon.textContent = favorited ? '♥' : '♡';
		}
	}

	function toggleGuestFavorite( button ) {
		var id = parseInt( button.getAttribute( 'data-property-id' ), 10 );
		var favorites = getGuestFavorites();
		var index = favorites.indexOf( id );
		var nowFavorited;

		if ( -1 === index ) {
			favorites.push( id );
			nowFavorited = true;
		} else {
			favorites.splice( index, 1 );
			nowFavorited = false;
		}

		setGuestFavorites( favorites );
		setButtonState( button, nowFavorited );
	}

	function toggleAccountFavorite( button ) {
		var id = button.getAttribute( 'data-property-id' );

		fetch( config.restUrl + 'favorites', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( { property_id: parseInt( id, 10 ) } ),
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( body ) {
				if ( 'boolean' === typeof body.favorited ) {
					setButtonState( button, body.favorited );
				}
			} )
			.catch( function () {
				// Leave button state unchanged on network failure — no misleading feedback.
			} );
	}

	function mergeGuestFavoritesIntoAccount() {
		var guestFavorites = getGuestFavorites();

		if ( ! guestFavorites.length ) {
			return;
		}

		fetch( config.restUrl + 'favorites/merge', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( { property_ids: guestFavorites } ),
		} )
			.then( function ( response ) {
				if ( response.ok ) {
					setGuestFavorites( [] );
				}
			} )
			.catch( function () {
				// Leave localStorage intact — the merge will simply retry on the next page load.
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-component="favorite-button"]' ) : null;

		if ( ! button ) {
			return;
		}

		if ( config.isLoggedIn ) {
			toggleAccountFavorite( button );
		} else {
			toggleGuestFavorite( button );
		}
	} );

	if ( config.isLoggedIn ) {
		mergeGuestFavoritesIntoAccount();
	}

	/**
	 * Guest-only: the favorites page (theme/inc/favorites-render.php) renders
	 * an empty container for guests since PHP can't read `localStorage` — this
	 * populates it via the same server-rendered fragment pattern the archive
	 * uses, and self-heals `localStorage` by dropping any ID the server
	 * didn't return (T064: the property was unpublished/deleted since it was
	 * favorited).
	 */
	function populateGuestFavoritesPage() {
		var container = document.getElementById( 'pe-favorites-results' );

		if ( ! container || config.isLoggedIn ) {
			return;
		}

		var ids = getGuestFavorites();

		if ( ! ids.length ) {
			container.innerHTML = '<p>' + ( container.getAttribute( 'data-empty-message' ) || 'You have not saved any favorites yet.' ) + '</p>';
			return;
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
					setGuestFavorites( stillValid );
				}
			} )
			.catch( function () {
				container.innerHTML = '<p>Could not load favorites.</p>';
			} );
	}

	populateGuestFavoritesPage();
} )();
