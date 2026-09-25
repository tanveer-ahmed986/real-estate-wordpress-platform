# Quickstart: PrimeEstate Platform

**Feature**: `001-real-estate-platform` | **Date**: 2026-09-24

This is a developer-facing smoke-test guide: how to stand up the environment, seed demo data, and manually verify each user story from `spec.md` once implementation begins. It complements (not replaces) the automated PHPUnit/Playwright suites from `research.md` §6.

## 1. Environment Setup

Prerequisites: PHP 8.1+, MySQL/MariaDB, WP-CLI, Composer, Node.js (for Playwright only — no JS build step is required to run the site itself, per the framework-free front end decision).

```bash
# 1. Local WordPress environment (any local stack works: wp-env, Local, DevKinsta, LAMP)
wp core download
wp config create --dbname=primeestate --dbuser=root --dbpass=root
wp core install --url=primeestate.local --title="PrimeEstate" --admin_user=admin --admin_password=admin --admin_email=admin@primeestate.local

# 2. Activate the theme and plugin
wp theme activate primeestate
wp plugin activate primeestate-core

# 3. Install PHP dependencies (PHPUnit, Faker, wp-phpunit scaffolding)
composer install

# 4. Install Playwright (E2E only)
npm install
npx playwright install
```

## 2. Seed Demo Data

```bash
wp primeestate seed        # 50+ properties, 8+ agents, taxonomies, amenities, demo users, example inquiries (FR-060)
wp primeestate reset       # Removes only _primeestate_demo-flagged content — safe to re-run
```

Seeded accounts (all clearly fictional, per FR-059) — every seeded account shares the password `PrimeEstateDemo123!`, documented here rather than randomly generated per account, so demo logins are actually usable (the administrator itself isn't seeded — it's the account created by `wp core install --admin_user=admin --admin_password=admin` in step 1):
- 1 `editor` (`demo_editor` / `PrimeEstateDemo123!`)
- 8 agents (`agent` role) with complete profiles (`demo_agent_*` / `PrimeEstateDemo123!`)
- 5 `subscriber`-role demo registered users with pre-existing favorites/inquiries (`demo_user_*` / `PrimeEstateDemo123!`)

## 3. Manual Smoke Test — One Pass Per User Story

| Story | Steps | Expected Result |
|---|---|---|
| US1 Browse & Search | Visit homepage → search "Buy" in a seeded city → apply filters (type, price, bedrooms) → copy URL → open in new tab | Same filtered result set both times; empty state shown for an impossible filter combo |
| US2 Detail & Inquiry | Open any published property → review gallery/specs → submit inquiry form | Inquiry appears in `wp-admin` under Inquiries with status "New"; assigned agent notified |
| US3 Favorites & Compare | As a guest, favorite 2 properties → log in as a seeded demo user → check favorites merged → add 4 properties to comparison → attempt a 5th | Guest favorites appear in account after login; 5th compare attempt blocked with message |
| US4 Viewing Request | Submit "Request Viewing" with a past date | Rejected with validation error; a future date succeeds and appears under the agent's Viewing Requests |
| US5 Agent Dashboard | Log in as a seeded agent → add a property → attempt to edit another agent's property | Own property publishes immediately (FR-061); edit attempt on another agent's property is denied |
| US6 Owner Submission | Log in as a demo registered user (non-agent) → submit a property | Property does NOT appear in public search; appears after admin approves it in `wp-admin` |
| US7 User Dashboard | Log in as a demo user with existing activity | Dashboard shows only that user's favorites/inquiries/viewings, nothing else |
| US8 Admin Oversight | Log in as administrator → open the PrimeEstate admin overview | Counts match seeded data; approve a pending submission and confirm it goes live |
| US9 Insights/Blog | Visit the Insights section | Seeded articles appear with category/author/date; at least one shows related properties |

## 4. Automated Tests

```bash
composer test              # PHPUnit — plugin business logic (search query building, capability checks, status transitions, favorites merge, rate limiting)
npx playwright test        # E2E — Search→Detail→Inquiry, and Submission→Approval→Public-visibility
```

## 5. Verifying Non-Functional Targets

- **Performance (SC-002, SC-009, SC-010)**: run Lighthouse against homepage/archive/detail on a throttled mobile profile; load-test the archive endpoint at ~50 concurrent requests.
- **Accessibility (SC-011)**: keyboard-only pass through search → detail → inquiry → favorites → comparison; automated pass with axe-core as a supplement, not a replacement, for manual keyboard testing.
- **Security (SC-012, FR-055/056)**: attempt cross-account inquiry access, submit 4 inquiries within an hour from one browser (expect the 4th blocked per FR-056), attempt a disallowed file upload on property submission.
- **URL sharing (SC-013)**: copy a filtered archive URL, open in a private/incognito window, confirm identical results.
- **Cache safety (SC-014, FR-049)**: with a page-cache plugin active, confirm dashboard/favorites pages are never served from cache (check cache-control/debug headers).

## 6. Map Fallback Check (FR-016, FR-058)

Unset the map provider option entirely → confirm the archive/detail pages render with zero map-related console errors and the grid/list view remains fully usable.
