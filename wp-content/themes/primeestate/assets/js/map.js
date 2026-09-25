/**
 * Initializes the Leaflet/OpenStreetMap map for the #pe-map container
 * (theme/components/map/map.php). No-ops gracefully if the container is
 * absent, has no markers, or the Leaflet library failed to load (network
 * failure, ad blocker, etc.) — the property grid/list remains the primary,
 * fully functional UI either way (research.md §3, FR-015/016).
 */
( function () {
	'use strict';

	function init() {
		var container = document.getElementById( 'pe-map' );

		if ( ! container || typeof window.L === 'undefined' ) {
			return;
		}

		var markers;
		try {
			markers = JSON.parse( container.getAttribute( 'data-markers' ) || '[]' );
		} catch ( e ) {
			return;
		}

		if ( ! markers.length ) {
			return;
		}

		var map = window.L.map( container, { scrollWheelZoom: false } );

		window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		} ).addTo( map );

		var bounds = [];

		markers.forEach( function ( marker ) {
			var latLng = [ marker.lat, marker.lng ];
			bounds.push( latLng );

			var popupHtml =
				'<a href="' + marker.permalink + '">' + marker.title + '</a><br>' + marker.price;

			// A plain pin hides the price behind a click — every competitor
			// reviewed puts the price directly on the pin so a result set can
			// be scanned without clicking each one individually.
			// `iconAnchor: [0, 0]` plus the CSS `translate(-50%, -100%)` on the
			// label itself (see style.css) is the standard Leaflet technique
			// for a variable-width divIcon: Leaflet can only anchor at a fixed
			// pixel offset, but the pill's width changes with the price text,
			// so the actual centering has to happen in CSS instead.
			var icon = window.L.divIcon( {
				className: 'pe-map-pin',
				html: '<span class="pe-map-pin__label">' + marker.pinLabel + '</span>',
				iconSize: null,
				iconAnchor: [ 0, 0 ],
			} );

			window.L.marker( latLng, { icon: icon } ).addTo( map ).bindPopup( popupHtml );
		} );

		if ( bounds.length > 1 ) {
			map.fitBounds( bounds, { padding: [ 32, 32 ] } );
		} else {
			map.setView( bounds[ 0 ], 14 );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
