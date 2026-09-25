import { test, expect } from '@playwright/test';

/**
 * T079 — the constitution's second required trust-critical-flow E2E test
 * (research.md §6): Property Owner Submission → Admin Approval → Public
 * Visibility. Requires a WordPress install seeded via `wp primeestate seed`
 * (quickstart.md §2) — uses the deterministic `demo_user_0` / `admin`
 * accounts documented there (both share `PrimeEstateDemo123!` for the
 * seeded account; the administrator is `admin`/`admin` from `wp core
 * install`, per quickstart.md §1).
 */

const DEMO_PASSWORD = 'PrimeEstateDemo123!';

async function login( page, username: string, password: string ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', username );
	await page.fill( '#user_pass', password );
	await page.click( '#wp-submit' );
}

test( 'a registered user\'s property submission stays hidden until an admin approves it', async ( { page } ) => {
	const uniqueTitle = 'Playwright Submission ' + Date.now();

	await login( page, 'demo_user_0', DEMO_PASSWORD );

	await page.goto( '/submit-property/' );

	const form = page.locator( '[data-component="property-form"]' );
	await expect( form ).toBeVisible();

	await form.locator( '#pe-property-title' ).fill( uniqueTitle );
	await form.locator( '#pe-property-description' ).fill( 'A property submitted end-to-end by the Playwright suite.' );
	await form.locator( '#pe-property-price' ).fill( '150000' );
	await form.locator( '#pe-property-listing-type' ).selectOption( { index: 1 } );
	await form.locator( '#pe-property-type' ).selectOption( { index: 1 } );
	await form.locator( '#pe-property-city' ).selectOption( { index: 1 } );

	await form.locator( 'button[type="submit"]' ).click();

	await expect( form.locator( '.pe-property-form__status' ) ).toContainText( 'Submitted for review' );

	// Not publicly visible yet — still `pending`.
	await page.goto( '/properties/?listing=&ids=' ); // Full unfiltered archive.
	await expect( page.locator( '.pe-property-card', { hasText: uniqueTitle } ) ).toHaveCount( 0 );

	// Approve as an administrator.
	await page.goto( '/wp-login.php?loggedout=true' );
	await login( page, 'admin', 'admin' );

	await page.goto( '/wp-admin/edit.php?post_type=property&page=primeestate-moderation' );
	const row = page.locator( 'tr', { hasText: uniqueTitle } );
	await expect( row ).toBeVisible();
	await row.getByRole( 'link', { name: 'Approve' } ).click();

	await expect( page.locator( '.notice-success' ) ).toContainText( 'approved' );

	// Now publicly visible.
	await page.goto( '/properties/' );
	await expect( page.locator( '.pe-property-card', { hasText: uniqueTitle } ) ).toBeVisible();
} );
