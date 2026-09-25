---
id: 0015
title: Phase 10 US8 admin overview
stage: green
date: 2026-09-25
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 10 / US8 only, per explicit user instruction)
labels: [implementation, admin, wp-admin, capabilities, test-infrastructure, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/admin/dashboard.php
 - wp-content/plugins/primeestate-core/includes/admin/columns.php
 - wp-content/plugins/primeestate-core/includes/agents/agent-permissions.php
 - tests/bootstrap.php
 - tests/integration/test-admin-overview.php
 - tests/unit/test-role-promotion-safeguards.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/integration/test-admin-overview.php (2 cases — known-seeded-data counts, fresh-install zero counts)
 - tests/unit/test-role-promotion-safeguards.php (4 cases — stray-cap stripping on Agent/Property Manager promotion, mapped caps still granted, safeguard scope limited to those two roles)
---

## Prompt

continue into Phase 10 (US8 — Administrator Overview)

## Response snapshot

Implemented all 5 Phase 10 (User Story 8 — Administrator Moderates & Oversees the Platform, P4) tasks, T088-T092.

Key decisions:
- **T089's overview widget is a real WP-admin Dashboard widget** (`wp_add_dashboard_widget`), the literal reading of "WordPress-native admin overview" — distinct from the Moderation Queue page, a glance-level summary not an action surface. Counts logic was factored into a standalone `primeestate_get_admin_overview_counts()` so T088's test could assert against it directly rather than scraping rendered HTML. "Active/sold/rented" read from `property_status`'s own taxonomy term counts (which WordPress's default counting callback already scopes to `publish`-status posts only, correctly excluding pending submissions); "pending-approval" and "total" come from `wp_count_posts()`'s workflow-state counts instead — two different axes per data-model.md §1, matched to FR-048's wording exactly (it lists them as separate figures).
- **T090/T091 file split**: Property CPT quick-filters/bulk-actions (a real native list table, `show_ui => true`) went in `admin/columns.php` per the task's own file target. Inquiry/Viewing Request — deliberately `show_ui => false` since Phase 2, with no native list table to add columns to — got purpose-built admin pages instead, placed in `admin/dashboard.php` next to the Moderation Queue rather than split across two files, since all three are the same kind of surface for a CPT with no native admin screen.
- **Every write in the new Inquiries/Viewing-Requests admin pages goes through `rest_do_request()`** against the exact same `/inquiries/{id}` and `/viewings/{id}` PATCH endpoints (T053/T068) the front-end Agent Dashboard already uses — `rest_do_request()` calls `WP_REST_Server::dispatch()` directly, which runs each route's `permission_callback` but (unlike a real HTTP call) skips the cookie-nonce check, since `wp-admin`'s own `check_admin_referer()` nonce (enforced before dispatch) is what actually protects the action here. This reuses all existing status-transition validation and "sees all vs. assigned only" logic with zero duplication, rather than reimplementing inquiry/viewing update logic a third time.
- **T092's safeguard targets a real WordPress footgun, not a hypothetical one**: `WP_User::set_role()` only clears the *previous role's* capabilities — any capability ever granted directly to a specific user via `add_cap()` (rather than through a role) survives a role change completely untouched. Added a `set_user_role` hook that strips any per-user capability not part of the new role's exact registered set, scoped to promotions into `agent`/`property_manager` only.
- **Found and fixed a test-infrastructure gap spanning every prior phase**: while reasoning through how `get_term_by('name', 'Available', 'property_status')` could possibly work in T088's test, traced that `primeestate_core_activated` — the action every role/capability registration and taxonomy-term seed hooks into — only ever fires via `register_activation_hook()`, which `tests/bootstrap.php` never triggers (it just `require`s the plugin file). This meant `agent`/`property_manager` roles, their capabilities, and every seeded taxonomy term had silently never existed in *any* PHPUnit run since Phase 7 introduced the first capability test — not a bug in any tested code, but in the fixture every test since then implicitly depended on. Fixed with the standard technique for this exact situation (also used by WP-CLI's own `wp scaffold plugin-tests` template): `tests_add_filter( 'init', 'primeestate_core_activate', 20 )`, firing once for the whole suite after CPT/taxonomy registration.

## Outcome

- ✅ Impact: User Story 8 is code-complete — an administrator gets a native wp-admin dashboard widget with platform-wide counts and recent activity, quick filters and bulk market-status changes on the Property list table, full Inquiries/Viewing-Requests management pages (status, notes, reassignment) reusing the exact REST logic the Agent Dashboard already relies on, and a closed gap in role-promotion capability hygiene. The bootstrap.php fix retroactively makes every prior phase's role/capability/taxonomy-dependent test actually able to pass for the first time, not just this phase's new ones.
- 🧪 Tests: 6 new PHPUnit cases (2 integration, 4 unit) plus the bootstrap fix needed for these and prior phases' capability tests to be meaningful at all. Still not executed — same no-runtime constraint as every prior phase, though this is the first phase where that caveat covers a fix to the test harness itself, not just the feature code.
- 📁 Files: 1 new admin file (columns.php), 1 extended admin file (dashboard.php — overview widget, two new admin pages, two new admin-post handlers), 1 extended agent-permissions.php (role-promotion safeguard), 1 fixed test bootstrap, 2 new test files, `tasks.md` updated with 5 completed checkboxes (T090 annotated with the file-split rationale).
- 🔁 Next prompts: Phase 11 (User Story 9 — Read Real Estate Insights, P5, T093-T095): the Insights archive template, a related-properties meta box on single blog posts, and extending `wp primeestate seed` to generate demo articles across all 6 insight categories.
- 🧠 Reflection: This phase's most consequential finding came from thinking through *why* a new test's own fixture assumption (`property_status` terms already existing) would actually hold, rather than just writing the assertion and trusting the pattern from prior test files. That question traced back through activation-hook timing and uncovered that the same unstated assumption had been silently false for every capability-dependent test since Phase 7. Worth carrying forward: when writing a test whose correctness depends on some global setup having already happened, explicitly trace *how* that setup would actually get triggered in the test environment, rather than assuming it mirrors production because a similar-looking earlier test assumed the same thing.

## Evaluation notes (flywheel)

- Failure modes observed: A test-environment fixture gap (activation hook never fired) that would have caused a large fraction of the existing test suite to fail for a reason unrelated to the code those tests were actually written to verify — the riskiest kind of test-infra bug, since a failure would misleadingly implicate the feature code rather than the harness. Caught only by explicitly tracing hook-firing mechanics for a new test rather than pattern-matching off existing test files.
- Graders run and results (PASS/FAIL): Brace/paren balance check — PASS (all 7 modified/new PHP files, including tests/bootstrap.php). No PHP-in-block-template leaks (Phase 10 touched no theme templates). Manual trace of `rest_do_request()`'s bypass of `serve_request()`'s cookie-nonce check, confirming wp-admin's own nonce is the actual protection for the new admin-post.php handlers — PASS. Manual trace of hook registration order between the new `tests_add_filter('init', 'primeestate_core_activate', 20)` and the plugin's own same-priority rewrite-flush hook, confirming no double-fire or ordering hazard — PASS.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Given this phase's fixture-gap finding, before Phase 11 do one targeted check of whether `wp primeestate seed`'s Insight-article extension (T095) will need anything from `primeestate_core_activated` that isn't already covered by the bootstrap.php fix just made — cheaper to check now than to discover it the same way T088 did.
