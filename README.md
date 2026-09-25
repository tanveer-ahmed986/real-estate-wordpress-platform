# PrimeEstate

A custom WordPress real estate listing and management platform: property search/browse, agent-managed listings with a moderated public-submission path, inquiries and viewing requests, favorites/comparison, a personal dashboard for registered users, an administrator oversight surface, and a categorized Insights/blog section — built as a custom theme + custom plugin, entirely on WordPress-native data structures (no custom database tables, no headless/React front end).

Built end-to-end following a Spec-Driven Development workflow — see `specs/001-real-estate-platform/` for the full spec, architecture plan, data model, API contract, and task breakdown, and `history/prompts/001-real-estate-platform/` for a Prompt History Record of every implementation phase.

## Structure

- `wp-content/themes/primeestate/` — presentation layer (block templates, components, theme JS/CSS)
- `wp-content/plugins/primeestate-core/` — business logic: custom post types, taxonomies, capabilities, REST API, WP-CLI commands
- `tests/` — PHPUnit (unit + integration) and Playwright E2E specs
- `docs/` — setup, deployment, admin/agent guides, API reference, troubleshooting
- `specs/001-real-estate-platform/` — the SDD artifacts this project was built from

## Getting started

See [`docs/setup.md`](docs/setup.md) for local environment setup, and [`specs/001-real-estate-platform/quickstart.md`](specs/001-real-estate-platform/quickstart.md) for a manual smoke-test pass through every user story once the environment is running.

## Status

All 9 user stories (spec.md) are implemented; see `specs/001-real-estate-platform/tasks.md` for the current state of Phase 12 (Polish & Cross-Cutting Concerns). This codebase was implemented without a PHP/MySQL/Node runtime available to the implementing agent — verification was limited to static checks (see `docs/troubleshooting.md` § *No PHP/Node runtime in this development environment*). Running the full automated test suite and the manual QA pass in `quickstart.md` against a real environment is a prerequisite before production use.
