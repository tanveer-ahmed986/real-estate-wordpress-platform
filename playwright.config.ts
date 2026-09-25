import { defineConfig } from '@playwright/test';

/**
 * Minimal Playwright config for the two required E2E flows (research.md §6):
 * Search → Detail → Inquiry (tests/e2e/search-to-inquiry.spec.ts) and
 * Owner Submission → Approval → Visibility (a later phase's spec file).
 * Point PLAYWRIGHT_BASE_URL at a local WordPress install seeded via
 * `wp primeestate seed` (quickstart.md §1-2) before running `npx playwright test`.
 */
export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 30 * 1000,
	fullyParallel: true,
	reporter: 'list',
	use: {
		baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://primeestate.local',
		trace: 'on-first-retry',
	},
} );
