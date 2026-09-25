---
id: 0009
title: Phase 4 US2 detail and inquiry
stage: green
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 4 / US2 only, per explicit user instruction)
labels: [implementation, inquiries, rest-api, phpunit, playwright, fse-template, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/inquiries/inquiry-handler.php
 - wp-content/plugins/primeestate-core/includes/search/property-query.php
 - wp-content/themes/primeestate/components/property-gallery/property-gallery.php
 - wp-content/themes/primeestate/components/inquiry-form/inquiry-form.php
 - wp-content/themes/primeestate/inc/single-property-render.php
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/themes/primeestate/templates/single-property.html
 - wp-content/themes/primeestate/assets/js/gallery.js
 - wp-content/themes/primeestate/assets/js/inquiry-form.js
 - playwright.config.ts
 - tests/e2e/search-to-inquiry.spec.ts
 - tests/unit/test-inquiry-handler.php
 - tests/unit/test-rate-limit.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-inquiry-handler.php (7 cases — validation, sanitization, storage)
 - tests/unit/test-rate-limit.php (5 cases — quota enforcement, shared-across-forms, honeypot detection, per-user identity)
 - tests/e2e/search-to-inquiry.spec.ts (2 cases — the required revenue-critical flow, plus an invalid-email negative case)
---

## Prompt

continue into Phase 4

## Response snapshot

Implemented all 12 Phase 4 (User Story 2 — View Property Details & Contact an Agent, P1) tasks, T045-T056, including the two REST endpoints (`GET`/`PATCH /inquiries`) added by the earlier `/sp.analyze` remediation.

Key implementation decisions:
- **"Assigned only" vs. "sees all" for inquiries (FR-029/FR-038)**: reused the `edit_others_properties` capability (which `agent` lacks and `property_manager`/`editor`/`administrator` all hold, from Phase 2's role setup) as the "sees all inquiries" gate, rather than inventing a new capability beyond the six named in T022. `view_inquiries` alone only gates "can touch the inquiries API"; `primeestate_user_sees_all_inquiries()` decides scope. Reassignment (`assigned_agent_id` in the PATCH body) is restricted to "sees all" callers only — an agent can update their own inquiry's status but not hand it to someone else, which the OpenAPI contract's wording ("admin re-assignable") supports.
- **Honeypot response**: a triggered honeypot returns a fake 201 success without persisting anything — standard anti-spam practice (never reveal detection to the bot) — and does not consume the rate-limit quota, matching T046's test coverage.
- **Related properties** (`property-query.php`): candidates share the source property's city OR property type (an OR tax_query), then a wider candidate pool is ranked in PHP by price/bedroom similarity and trimmed to 6 — avoids needing a numeric-distance `ORDER BY` that WP_Query's meta_query can't naturally express.
- **Detail page architecture**: same pattern as Phase 3's archive — `single-property.html` is a real block template hosting a `[primeestate_property_detail]` shortcode, since gallery/specs/agent-card/inquiry-form/related-properties are too data-driven for static block markup.
- **FR-054 fallback**: two distinct cases handled — (1) the template is somehow reached with a missing/unpublished post (defensive; WordPress's own query resolution normally prevents this) shows a "no longer available, browse all properties" notice; (2) a still-published property whose `property_status` term is sold/rented/off-market shows an inline "no longer available" banner but keeps the page itself viewable (SEO/history) and replaces the InquiryForm with a "contact us about similar properties" message instead of hiding the whole page.
- **InquiryForm progressive enhancement**: the form's native `action` posts directly to the public REST endpoint, which WordPress's REST API accepts as a standard form-encoded POST — so the inquiry is genuinely created even with JavaScript disabled (the user just sees the endpoint's raw JSON instead of an inline thank-you). `inquiry-form.js` intercepts submit for the enhanced inline-status UX. Documented this tradeoff explicitly in a code comment rather than building an unrequested redirect-wrapper endpoint.
- **Map reuse**: the single-property page reuses Phase 3's Map component for the one property's own location (`primeestate_render_map( [ $post ] )`), extending `enqueue.php`'s existing Leaflet condition from archive-only to archive-or-singular — exactly the "later phase" extension point the Phase 3 PHR flagged as a likely next step.

## Outcome

- ✅ Impact: User Story 2 is code-complete — the full Search → Detail → Inquiry loop (the platform's required revenue-critical path per constitution Principle VIII) now exists end to end, and inquiries are staff-manageable (list, filter, status/reassign/notes), not just creatable — closing the exact gap the earlier `/sp.analyze` pass (E1, CRITICAL) flagged before any code existed.
- 🧪 Tests: 14 new test cases across 3 files (7 PHPUnit inquiry-handler, 5 PHPUnit rate-limit, 2 Playwright E2E). None executed — still no PHP/MySQL/Node runtime available in this environment (same limitation as Phases 2-3); `playwright.config.ts` and the wp-phpunit bootstrap are both now in place, so once an environment exists, `composer test` and `npx playwright test` should be runnable without further scaffolding work.
- 📁 Files: 2 plugin files (1 new, 1 extended), 2 new theme components, 1 new theme orchestration file, 1 extended `inc/enqueue.php`, 1 new block template, 2 new JS files, Playwright config + spec (both new — no earlier task had created Playwright infrastructure), 2 new PHPUnit test files, `tasks.md` updated with 12 completed checkboxes.
- 🔁 Next prompts: Phase 5 (User Story 3 — Save Favorites & Compare Properties, P2, T057-T064): the favorites merge-on-login test, FavoriteButton + localStorage guest logic, `GET`/`POST /favorites` and `POST /favorites/merge` endpoints, the favorites listing page, CompareButton + comparison-set client logic, and the side-by-side comparison view.
- 🧠 Reflection: This phase had no equivalent of Phase 3's "PHP tag inside a block template" near-miss — the explicit grep check added to the verification pass at the end of Phase 3 paid off immediately by being routine rather than reactive. The one genuinely non-obvious design call (reusing `edit_others_properties` as the inquiries "sees all" gate rather than adding a new capability) is exactly the kind of decision that's easy to get subtly wrong in either direction (too permissive or too restrictive) without the capability matrix from data-model.md §10 open alongside the code — worth the same discipline in Phase 5's favorites/comparison authorization, which has its own, simpler "own data only" shape.

## Evaluation notes (flywheel)

- Failure modes observed: None new. Same no-runtime constraint as every prior phase; verification remained brace/paren balance (all new files pass) plus the now-routine PHP-in-block-template grep (0 matches).
- Graders run and results (PASS/FAIL): Balance check — PASS. Block-template PHP-leak check — PASS. Manual cross-reference of every `primeestate_*` function call in the new files against its defining file (confirming autoload coverage, the same class of bug caught in Phase 3) — PASS, no undefined-function calls found.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Phase 5's favorites/comparison logic is `localStorage`-heavy (research.md §4) in a way Phases 1-4 weren't — worth explicitly tracing the guest→account merge-on-login sequence end-to-end against research.md §4's decision text before writing `favorites.js`, since that's the one piece of Phase 5 most likely to have a subtle client/server state-sync bug that static review alone won't catch.
