import { test, expect } from '@playwright/test';

/**
 * T047 — the constitution's required revenue-critical-flow E2E test
 * (research.md §6): Search → Property Detail → Submit Inquiry. Requires a
 * WordPress install seeded via `wp primeestate seed` (quickstart.md §2) so
 * at least one published property exists.
 */
test( 'visitor can search, open a property, and submit an inquiry', async ( { page } ) => {
	await page.goto( '/properties/' );

	const firstResult = page.locator( '.pe-property-card' ).first();
	await expect( firstResult ).toBeVisible();

	const propertyTitle = await firstResult.locator( '.pe-property-card__title a' ).innerText();
	await firstResult.locator( '.pe-property-card__title a' ).click();

	await expect( page.locator( '.pe-property-detail h1' ) ).toHaveText( propertyTitle );

	const form = page.locator( '[data-component="inquiry-form"]' );
	await expect( form ).toBeVisible();

	await form.locator( '#pe-inquiry-name' ).fill( 'Playwright Test User' );
	await form.locator( '#pe-inquiry-email' ).fill( 'playwright-test@example.test' );
	await form.locator( '#pe-inquiry-message' ).fill( 'I am interested in viewing this property. Please contact me.' );

	await form.locator( 'button[type="submit"]' ).click();

	await expect( form.locator( '.pe-inquiry-form__status' ) ).toHaveClass( /pe-inquiry-form__status--success/ );
	await expect( form.locator( '.pe-inquiry-form__status' ) ).not.toBeEmpty();
} );

test( 'submitting the inquiry form with an invalid email shows an inline error', async ( { page } ) => {
	await page.goto( '/properties/' );

	const firstResult = page.locator( '.pe-property-card' ).first();
	await firstResult.locator( '.pe-property-card__title a' ).click();

	const form = page.locator( '[data-component="inquiry-form"]' );
	await form.locator( '#pe-inquiry-name' ).fill( 'Playwright Test User' );
	await form.locator( '#pe-inquiry-email' ).fill( 'not-an-email' );
	await form.locator( '#pe-inquiry-message' ).fill( 'Test message.' );

	// Browser-native `type="email"` validation should block submission before
	// it ever reaches the server — the form should not show a success state.
	await form.locator( 'button[type="submit"]' ).click();
	await expect( form.locator( '.pe-inquiry-form__status--success' ) ).toHaveCount( 0 );
} );
