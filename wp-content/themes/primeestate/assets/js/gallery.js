/**
 * PropertyGallery progressive enhancement: thumbnail switching, a fullscreen
 * lightbox, and touch-swipe. Baseline (no JS) is already usable — each
 * thumbnail/slide is a real link to the full-size image.
 *
 * T099 (accessibility pass, SC-011): fullscreen is a lightweight lightbox,
 * not a true modal dialog removed from document flow (no CSS build exists
 * in this project to support `inert`/focus-trap styling), so instead of
 * faking `role="dialog"` semantics this focuses on what actually matters
 * for keyboard/AT users regardless of visual state: Escape and the visible
 * Close button both exit fullscreen and return focus to the control that
 * opened it, Left/Right arrows move between images without needing to tab
 * to a specific thumbnail, an `aria-live` status announces the current
 * image on every change, and each thumbnail's `aria-pressed` reflects which
 * slide is showing.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-component="property-gallery"]' ).forEach( function ( gallery ) {
		var slides = Array.prototype.slice.call( gallery.querySelectorAll( '.pe-property-gallery__slide' ) );
		var thumbs = Array.prototype.slice.call( gallery.querySelectorAll( '.pe-property-gallery__thumb' ) );
		var closeButton = gallery.querySelector( '.pe-property-gallery__close' );
		var status = gallery.querySelector( '.pe-property-gallery__status' );

		if ( slides.length < 2 ) {
			return;
		}

		var current = 0;
		var lastFocusedBeforeFullscreen = null;

		function announce( index ) {
			if ( status ) {
				status.textContent = ( index + 1 ) + ' of ' + slides.length;
			}
		}

		function show( index ) {
			index = ( index + slides.length ) % slides.length;
			slides[ current ].hidden = true;
			slides[ index ].hidden = false;

			thumbs.forEach( function ( thumb, thumbIndex ) {
				thumb.setAttribute( 'aria-pressed', thumbIndex === index ? 'true' : 'false' );
			} );

			current = index;
			announce( index );
		}

		function openFullscreen( trigger ) {
			lastFocusedBeforeFullscreen = trigger || document.activeElement;
			gallery.classList.add( 'pe-property-gallery--fullscreen' );
			if ( closeButton ) {
				closeButton.focus();
			}
		}

		function closeFullscreen() {
			gallery.classList.remove( 'pe-property-gallery--fullscreen' );
			if ( lastFocusedBeforeFullscreen && lastFocusedBeforeFullscreen.focus ) {
				lastFocusedBeforeFullscreen.focus();
			}
			lastFocusedBeforeFullscreen = null;
		}

		function isFullscreen() {
			return gallery.classList.contains( 'pe-property-gallery--fullscreen' );
		}

		thumbs.forEach( function ( thumb, index ) {
			thumb.addEventListener( 'click', function () {
				show( index );
			} );
		} );

		// Prevent the slide's native link navigation once JS can offer a
		// same-page fullscreen view instead. Activating a focused link with
		// Enter fires this same click handler, so opening is already
		// keyboard-operable without extra wiring.
		slides.forEach( function ( slide ) {
			slide.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( isFullscreen() ) {
					closeFullscreen();
				} else {
					openFullscreen( slide );
				}
			} );
		} );

		if ( closeButton ) {
			closeButton.addEventListener( 'click', closeFullscreen );
		}

		gallery.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && isFullscreen() ) {
				event.preventDefault();
				closeFullscreen();
				return;
			}

			if ( 'ArrowRight' === event.key ) {
				event.preventDefault();
				show( current + 1 );
			} else if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				show( current - 1 );
			}
		} );

		// Touch swipe on the main slide area.
		var touchStartX = null;
		var main = gallery.querySelector( '.pe-property-gallery__main' );

		main.addEventListener( 'touchstart', function ( event ) {
			touchStartX = event.touches[ 0 ].clientX;
		}, { passive: true } );

		main.addEventListener( 'touchend', function ( event ) {
			if ( null === touchStartX ) {
				return;
			}

			var delta = event.changedTouches[ 0 ].clientX - touchStartX;

			if ( Math.abs( delta ) > 40 ) {
				show( current + ( delta < 0 ? 1 : -1 ) );
			}

			touchStartX = null;
		} );
	} );
} )();
