---
id: 0010
title: Phase 5 US3 favorites and compare
stage: green
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 5 / US3 only, per explicit user instruction)
labels: [implementation, favorites, comparison, rest-api, phpunit, localstorage, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/search/property-query.php
 - wp-content/plugins/primeestate-core/includes/search/search-api.php
 - wp-content/plugins/primeestate-core/includes/favorites/favorites.php
 - wp-content/plugins/primeestate-core/includes/comparison/comparison.php
 - wp-content/plugins/primeestate-core/includes/pages/default-pages.php
 - wp-content/themes/primeestate/components/favorite-button/favorite-button.php
 - wp-content/themes/primeestate/components/compare-button/compare-button.php
 - wp-content/themes/primeestate/components/property-card/property-card.php
 - wp-content/themes/primeestate/inc/favorites-render.php
 - wp-content/themes/primeestate/inc/compare-render.php
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/themes/primeestate/templates/page-favorites.html
 - wp-content/themes/primeestate/templates/page-compare.html
 - wp-content/themes/primeestate/assets/js/favorites.js
 - wp-content/themes/primeestate/assets/js/comparison.js
 - specs/001-real-estate-platform/contracts/primeestate-api.openapi.yaml
 - tests/unit/test-favorites-merge.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-favorites-merge.php (6 cases — union/dedup merge, idempotency, per-user isolation, toggle)
---

## Prompt

continue into Phase 5

## Response snapshot

Implemented all 8 Phase 5 (User Story 3 — Save Favorites & Compare Properties, P2) tasks, T057-T064.

Foundational addition made before the feature work: extended `property-query.php`'s filter system with an `ids` filter (explicit-ID lookup, bypasses all other filtering, preserves request order via `post__in`/`orderby: post__in`) — both Favorites and Comparison are client-side-only data (data-model.md §7/§8), so their listing views need a way to turn a locally-held ID array into fresh, still-published property data, and this is the one piece of infrastructure both features share. Documented and added to the OpenAPI contract as a deliberate, justified post-MVP extension (`ids` param on `GET /properties`, plus a new `GET /properties/compare` endpoint), rather than leaving the contract silently out of sync with the implementation.

Key design decisions:
- **Favorites (server-backed for logged-in, localStorage for guests)**: `favorites.php` implements list/toggle/merge against usermeta `_pe_favorites`, with a per-request static cache (`primeestate_get_current_user_favorites_cached()`) so rendering N PropertyCards doesn't read usermeta N times. `favorites.js` calls `POST /favorites/merge` on every page load whenever a logged-in user still has non-empty guest `localStorage` data — merge is a set union, so this is safe to repeat and needs no "just logged in" detection, avoiding a fragile client-side login-transition heuristic.
- **Comparison (client-only for everyone, including logged-in users)**: per data-model.md §8, comparison has no server persistence at all — `comparison.php`'s only job is a 4-capped, defense-in-depth data-fetch endpoint (a client bypassing the JS cap still can't get more than 4 back from the server).
- **Consistent server-rendered-fragment pattern for both listing pages**: rather than duplicating PropertyCard/table markup in JavaScript, `favorites-render.php` and `compare-render.php` both follow archive-render.php's established pattern (Phase 3) — a `template_redirect` handler keyed on the same `X-PrimeEstate-Fragment` header serves server-rendered HTML for a client-supplied `?ids=` list. JS's only job is reading `localStorage` and swapping HTML, never generating it — keeping exactly one rendering path (PHP) for every piece of UI in the project so far.
- **Self-healing localStorage (T064)**: both `favorites.js` and `comparison.js` compare the IDs they requested against `data-property-id` attributes actually present in the returned fragment, and silently drop any that didn't come back (property was unpublished/deleted since being saved) — caught and fixed a sloppy first draft of this logic in comparison.js (it could only detect "all vs. nothing," not which specific ID vanished) by adding `data-property-id` to each comparison-table cell before finishing the JS.
- **Missing default pages caught proactively**: `page-favorites.html`/`page-compare.html` only activate for an actual WordPress Page with that slug — nothing creates one automatically. Added `default-pages.php`, hooked to the same `primeestate_core_activated` action from Phase 2, to create both on activation if missing — the same class of "supporting infrastructure gap" caught in every phase so far (PHPUnit bootstrap in Phase 3, Playwright config in Phase 4).
- **FavoriteButton/CompareButton extracted as real shared components**: refactored `property-card.php`'s previously-inlined button markup to call the new `primeestate_render_favorite_button()`/`primeestate_render_compare_button()` — one definition, used everywhere a card appears (archive, related properties, favorites page).

## Outcome

- ✅ Impact: User Story 3 is code-complete — guests can favorite/compare entirely client-side, logged-in users get server-backed favorites that merge automatically on login, comparison works identically for both (client-only by design), and both listing pages gracefully drop properties that became unavailable since being saved.
- 🧪 Tests: 6 new PHPUnit cases (T057) covering merge/toggle/isolation. Not executed — same no-runtime constraint as every prior phase.
- 📁 Files: 3 new plugin files, 1 extended plugin file (property-query.php's `ids` filter, reused by search-api.php's arg whitelist), 2 new theme components, 1 refactored component (property-card.php), 2 new theme orchestration files, 1 extended enqueue.php, 2 new block templates, 2 new JS files, OpenAPI contract updated with 2 additions, 1 new PHPUnit file, `tasks.md` updated with 8 completed checkboxes.
- 🔁 Next prompts: Phase 6 (User Story 4 — Request a Property Viewing, P2, T065-T070 or similar range): viewing-request validation/storage, the `POST`/`PATCH /viewings` endpoints, a ViewingRequestForm component, and the date/time validation (FR-031: no past dates) this story's independent test explicitly calls out.
- 🧠 Reflection: The self-healing localStorage bug caught in comparison.js (detecting "some ID vanished" vs. "all or nothing") is a good example of why writing the *test* for behavior like T064 — even mentally, without executing it — surfaces mistakes that reading the code alone doesn't: the original one-liner "looked" like it did what T064 asked, and only concretely tracing through "what if 3 of 4 compared properties are still valid" exposed that it couldn't actually express that case. Worth continuing to explicitly trace edge-case task descriptions (not just the happy path) against new code before considering a task done, especially for anything JS-side where there's no PHPUnit safety net at all in this project's testing strategy.

## Evaluation notes (flywheel)

- Failure modes observed: The comparison.js self-heal logic (see Reflection) — caught and fixed before being reported as done, not after. No other new failure modes this phase.
- Graders run and results (PASS/FAIL): Brace/paren balance check across all new/modified PHP files — PASS. Block-template PHP-leak grep — PASS (0 matches). Full manual read-through of both final JS files (favorites.js, comparison.js) to verify structural correctness after multiple incremental edits — PASS, both well-formed.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Apply the same "trace the edge case, not just the happy path" discipline explicitly to Phase 6's FR-031 past-date validation before calling it done — it's structurally similar (a rule that's easy to state but has an off-by-one/timezone-boundary way to get subtly wrong, e.g. "is today itself a valid viewing date, and in which timezone").
