/**
 * Standard amortization formula, computed entirely client-side — no
 * request ever leaves the browser, so there's nothing to debounce and
 * nothing to validate server-side (theme/components/mortgage-calculator's
 * own doc comment explains why this is safe to keep that simple).
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-component="mortgage-calculator"]' ).forEach( function ( calc ) {
		var priceInput = calc.querySelector( '#pe-mortgage-price' );
		var downInput = calc.querySelector( '#pe-mortgage-down' );
		var rateInput = calc.querySelector( '#pe-mortgage-rate' );
		var termSelect = calc.querySelector( '#pe-mortgage-term' );
		var result = calc.querySelector( '#pe-mortgage-result' );
		var currency = calc.getAttribute( 'data-currency' ) || '';

		if ( ! priceInput || ! downInput || ! rateInput || ! termSelect || ! result ) {
			return;
		}

		// PrimeEstate's price/currency are decoupled per-property (data-model.md
		// §1) — hardcoding "USD" here would silently show the wrong currency
		// symbol for anything listed in another currency, so this always
		// formats against whatever `_pe_currency` the listing actually has.
		function formatCurrency( amount ) {
			if ( currency ) {
				try {
					return new Intl.NumberFormat( undefined, {
						style: 'currency',
						currency: currency,
						maximumFractionDigits: 0,
					} ).format( amount );
				} catch ( e ) {
					// Fall through: an unrecognized ISO code shouldn't break the widget.
				}
			}

			return currency + ' ' + Math.round( amount ).toLocaleString();
		}

		function recalculate() {
			var price = parseFloat( priceInput.value ) || 0;
			var downPct = Math.min( 100, Math.max( 0, parseFloat( downInput.value ) || 0 ) );
			var annualRate = Math.max( 0, parseFloat( rateInput.value ) || 0 );
			var years = parseInt( termSelect.value, 10 ) || 30;

			var principal = price * ( 1 - downPct / 100 );
			var months = years * 12;
			var monthlyRate = annualRate / 100 / 12;

			var payment;
			if ( principal <= 0 ) {
				payment = 0;
			} else if ( monthlyRate === 0 ) {
				payment = principal / months;
			} else {
				var factor = Math.pow( 1 + monthlyRate, months );
				payment = principal * ( monthlyRate * factor ) / ( factor - 1 );
			}

			result.textContent = formatCurrency( payment ) + ' / mo';
		}

		[ priceInput, downInput, rateInput, termSelect ].forEach( function ( input ) {
			input.addEventListener( 'input', recalculate );
			input.addEventListener( 'change', recalculate );
		} );

		recalculate();
	} );
} )();
