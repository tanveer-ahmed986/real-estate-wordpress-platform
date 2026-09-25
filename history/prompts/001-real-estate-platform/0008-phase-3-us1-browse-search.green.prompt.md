---
id: 0008
title: Phase 3 US1 browse and search
stage: green
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 3 / US1 only, per explicit user instruction)
labels: [implementation, search, filters, rest-api, phpunit, fse-template, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - composer.json
 - phpunit.xml.dist
 - tests/bootstrap.php
 - tests/unit/test-property-query.php
 - tests/unit/test-search-url-state.php
 - wp-content/plugins/primeestate-core/includes/search/property-query.php
 - wp-content/plugins/primeestate-core/includes/search/search-api.php
 - wp-content/themes/primeestate/components/search-form/search-form.php
 - wp-content/themes/primeestate/components/filter-panel/filter-panel.php
 - wp-content/themes/primeestate/components/property-card/property-card.php
 - wp-content/themes/primeestate/components/empty-state/empty-state.php
 - wp-content/themes/primeestate/components/map/map.php
 - wp-content/themes/primeestate/inc/archive-render.php
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/themes/primeestate/functions.php
 - wp-content/themes/primeestate/templates/archive-property.html
 - wp-content/themes/primeestate/parts/header.html
 - wp-content/themes/primeestate/parts/footer.html
 - wp-content/themes/primeestate/assets/js/property-search.js
 - wp-content/themes/primeestate/assets/js/map.js
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-property-query.php (11 cases — tax_query/meta_query construction, invalid-input clamping, sort mapping)
 - tests/unit/test-search-url-state.php (6 cases — round-trip equality, canonical URL ordering, default omission, malformed-input safety)
---

## Prompt

continue into Phase 3

## Response snapshot

Implemented all 11 Phase 3 (User Story 1 — Browse & Search, P1/MVP) tasks, T034-T044.

Two gaps fixed along the way, before they could cause silent runtime failures:
- **Missing `phpunit/phpunit` dependency**: Phase 1's `composer.json` listed `wp-phpunit/wp-phpunit` (WordPress's test scaffolding) but not the PHPUnit framework itself — added `phpunit/phpunit: ^9.6`. Also added `phpunit.xml.dist` and `tests/bootstrap.php` (the standard wp-phpunit bootstrap pattern) since T034/T035 can't run without them and no earlier task created them.
- **Components were never autoloaded**: `functions.php`'s autoloader only globbed `inc/*.php`; `theme/components/*/*.php` (PropertyCard, SearchForm, FilterPanel, EmptyState, Map) was never included anywhere, which would have made every `primeestate_render_*()` call fatal with "function not defined". Extended the autoloader to also glob `components/*/*.php`.
- **Caught before it shipped**: an early draft of `parts/footer.html` had inline `<?php ... ?>` tags for a dynamic copyright year — block-template `.html` files are parsed as block markup only, never executed as PHP, so this would have printed literal `<?php ... ?>` text on every page. Replaced with a static line before writing any other file that might have copied the same mistake.

Architecture decisions:
- **Query builder** (`property-query.php`): a single `primeestate_normalize_filters()` is the one place that clamps/drops invalid input (spec.md: "invalid params are ignored, not hard errors") — used by the REST endpoint, the main-query `pre_get_posts` filter, the AJAX fragment handler, and the URL codec, so all four consumers agree on what a "valid" filter looks like. `primeestate_filters_to_query_string()` produces canonical, stable-ordered URLs (defaults like `page=1`/`sort=relevance` omitted) so equivalent searches are byte-identical, satisfying SC-013 exactly, not just approximately.
- **Server-rendered archive, not a JSON+client-render SPA**: research.md §1 calls for "AJAX fragment", not a JSON API consumed by client-side templating. `theme/inc/archive-render.php` renders the results grid via the *same* PHP function (`primeestate_render_property_results()`) for both the full page load and the AJAX path — the AJAX path is identified by a custom `X-PrimeEstate-Fragment` request header (never a query param, so it can't leak into the canonical/shareable URL) intercepted on `template_redirect`. `property-search.js` fetches that fragment, swaps the results container, and syncs the URL via `pushState`. Pagination links are deliberately left as plain full-page `<a href>` navigations (not AJAX-intercepted) — a reasonable scope boundary the task wording ("URL sync" on filter changes) supports, and they still respect active filters because the main query's own `pre_get_posts` filter reads the same `$_GET` state.
- **FSE template as a shortcode host, not hand-authored block markup for the dynamic parts**: `archive-property.html` is a real block template (required by the `.html` extension in the task), but the search/filter/grid UI — too dynamic and server-logic-driven for static block markup — is a single `[primeestate_property_archive]` shortcode. `parts/header.html`/`footer.html` (not themselves a named task, but required for the template to resolve at all) use core blocks only (Site Logo, Site Title, Navigation) — no custom PHP inside block templates, per the bug caught above.
- **Data flow discipline**: `theme/components/property-card/property-card.php` calls the plugin's `primeestate_property_to_summary()` (defined in `search-api.php`) rather than reading postmeta/taxonomy terms directly — the same mapping function now backs both the JSON REST response and the server-rendered card, so there is exactly one place that defines what a "property summary" is.
- **Map**: Leaflet 1.9.4 + OpenStreetMap tiles from a versioned CDN URL (no SRI hash — fabricating one without being able to verify it against the actual served bytes would be worse than omitting it, since a wrong hash silently breaks loading; noted in code for whoever pins a deployment to add one). Renders nothing when zero properties in the result set have coordinates; `map.js` no-ops if the container, its markers, or `window.L` itself are missing — the property grid is never dependent on the map succeeding.

## Outcome

- ✅ Impact: User Story 1 (Browse & Search, the MVP) is code-complete — search, all FR-011 filter dimensions, FR-012 sorting, FR-013 shareable filtered URLs, FR-015/016 map with graceful fallback, and FR-017's empty state are all implemented and wired together.
- 🧪 Tests: 17 PHPUnit cases written (T034: 11, T035: 6) covering the query builder and URL codec. Not executed — no PHP/MySQL runtime available in this environment (same limitation noted in Phase 2's PHR); this is unverified until run against a real wp-phpunit test database.
- 📁 Files: 2 new plugin files (search/), 5 new theme components, 3 new/modified theme `inc/` files, 3 new block-template files, 2 new JS files, PHPUnit infra (bootstrap + xml config), `composer.json` gap fix, `tasks.md` updated with 11 completed checkboxes.
- 🔁 Next prompts: Phase 4 (User Story 2 — View Property Details & Contact an Agent, P1, T045-T053): inquiry validation/storage tests, the inquiry handler, `GET`/`PATCH /inquiries` endpoints (the ones the earlier `/sp.analyze` remediation added — T052/T053), the single-property detail template, and the InquiryForm component.
- 🧠 Reflection: All three gaps caught this phase (missing PHPUnit dependency, missing component autoloading, PHP-in-block-template) were the kind that only surface when code actually *runs* — static review caught them here only through careful manual tracing of "what calls what, and is it ever loaded", not through any automated check. This continues to argue for getting a real local WordPress/PHP environment in the loop before Phase 4 goes much further, per Phase 2's PHR recommendation, which has not yet happened.

## Evaluation notes (flywheel)

- Failure modes observed: Same no-PHP-runtime constraint as Phase 2 — `php -l` still unavailable, so verification remained: brace/paren balance check (27 new files, all balanced) plus an explicit grep confirming no `<?php` leaked into the two `.html` block-template files (the exact bug class just fixed by hand in footer.html). This is a narrower, more targeted check than Phase 2's, added specifically because this phase introduced the FSE-template risk surface for the first time.
- Graders run and results (PASS/FAIL): Balance check — PASS (all new files). Block-template PHP-leak check — PASS (0 matches after the footer.html fix). Autoload completeness — manually traced (not automated): confirmed every `primeestate_render_*`/`primeestate_get_*` function called from `archive-render.php` has a defining file under a globbed path (`inc/*.php` or `components/*/*.php`).
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Add a cheap, no-runtime-needed static check for the "PHP tag inside a `templates/*.html` or `parts/*.html` file" class of bug to the standard per-phase verification pass (it's a two-line grep, and this phase proved it's a real, easy-to-make mistake) — worth carrying into Phase 4 and beyond even though no more block templates are currently planned.
