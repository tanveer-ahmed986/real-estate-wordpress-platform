# PrimeEstate — Local Setup

## Prerequisites

- PHP 8.1+
- MySQL or MariaDB
- [WP-CLI](https://wp-cli.org/)
- [Composer](https://getcomposer.org/) (PHPUnit, Faker, `wp-phpunit` scaffolding — dev-only)
- Node.js (Playwright E2E only — there is no JS build step to run the site itself; theme JS is plain ES6, enqueued as-is)
- Any local WordPress stack works: `wp-env`, Local, DevKinsta, or a plain LAMP/LEMP install

## Installation

```bash
# 1. Stand up WordPress
wp core download
wp config create --dbname=primeestate --dbuser=root --dbpass=root
wp core install --url=primeestate.local --title="PrimeEstate" --admin_user=admin --admin_password=admin --admin_email=admin@primeestate.local

# 2. Place the theme and plugin
#    - wp-content/themes/primeestate      → the presentation layer
#    - wp-content/plugins/primeestate-core → CPTs, taxonomies, capabilities, REST API, business logic

# 3. Activate both
wp theme activate primeestate
wp plugin activate primeestate-core
```

Activating `primeestate-core` registers the `property`, `pe_inquiry`, and `pe_viewing_request` post types, the `location`/`property_type`/`property_status`/`amenity`/`listing_type`/`insight_category` taxonomies, the `agent`/`property_manager` roles and their capabilities, seeds default taxonomy terms, creates the theme's required Pages (Favorites, Compare, Agent Dashboard, Submit a Property, My Dashboard, Privacy Policy — see `wp-content/plugins/primeestate-core/includes/pages/default-pages.php`), and flushes rewrite rules on the next request. If any of that appears missing, deactivate and reactivate the plugin rather than debugging around it — activation-only setup only runs through WordPress's real activation hook, never a plain page load.

```bash
# 4. PHP dev dependencies (test tooling only — not required to run the site)
composer install

# 5. Playwright (E2E only)
npm install
npx playwright install
```

## Seeding Demo Data

```bash
wp primeestate seed        # 50+ properties, 8+ agents, taxonomies, amenities, demo users, inquiries, viewing requests, Insight articles
wp primeestate reset       # Removes only content/users tagged _primeestate_demo — safe to re-run, never touches real data
```

Every seeded account shares the password `PrimeEstateDemo123!` (documented here rather than randomized, so demo logins are actually usable — see FR-059):

| Role | Username pattern | Notes |
|---|---|---|
| Administrator | `admin` | Created in step 1 above, not by the seeder |
| Editor | `demo_editor` | Authors the seeded Insight articles |
| Agent | `demo_agent_1`…`demo_agent_8` | Complete profiles, own listings |
| Subscriber | `demo_user_1`…`demo_user_5` | Pre-existing favorites/inquiries, exercises the personal dashboard |

Run `seed` again after a `reset` any time you need a clean, fully-populated demo state.

## Running Tests

```bash
composer test              # PHPUnit — includes/tests/unit and tests/integration
npx playwright test        # E2E — search-to-inquiry and submission-to-approval flows
```

PHPUnit uses `wp-phpunit`; `tests/bootstrap.php` fires `primeestate_core_activate()` on WordPress's `init` action before the suite runs, so activation-only fixtures (roles, capabilities, seeded taxonomy terms) exist even though the bootstrap never runs a real plugin-activation request — see `tests/bootstrap.php` if a test appears to be missing a capability or term that should exist.
