---
id: 0014
title: Phase 9 US7 personal dashboard
stage: green
date: 2026-09-25
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 9 / US7 only, per explicit user instruction)
labels: [implementation, dashboard, cache-safety, seed-data, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/themes/primeestate/inc/user-dashboard-render.php
 - wp-content/themes/primeestate/templates/page-user-dashboard.html
 - wp-content/themes/primeestate/assets/js/user-dashboard.js
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/plugins/primeestate-core/includes/inquiries/inquiry-handler.php
 - wp-content/plugins/primeestate-core/includes/viewing/viewing-request.php
 - wp-content/plugins/primeestate-core/includes/security/security.php
 - wp-content/plugins/primeestate-core/includes/pages/default-pages.php
 - wp-content/plugins/primeestate-core/includes/cli/seed.php
 - tests/unit/test-user-dashboard.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-user-dashboard.php (6 cases — own-inquiry/viewing email scoping, isolation, empty-email guard, dashboard-page-slug coverage regression guard)
---

## Prompt

continue into Phase 9 (US7 — Personal Dashboard)

## Response snapshot

Implemented all 3 Phase 9 (User Story 7 — Registered User Manages Personal Dashboard, P4) tasks, T085-T087.

Key decisions:
- **Favorites/Comparisons sections reuse existing rendering wholesale**: the dashboard's Favorites section calls the same `primeestate_get_favorited_properties()` + `primeestate_render_favorites_results()` pair favorites-render.php already uses for logged-in visitors; Comparisons embeds `primeestate_compare_page_shortcode()`'s output directly (its container is populated client-side from `localStorage` by the existing comparison.js — comparison has zero server persistence for anyone per data-model.md §8, so there was nothing page-specific to build).
- **"My Inquiries"/"My Viewing Requests" scoped by account email, not user ID**: neither CPT stores a submitter user-ID link (data-model.md §5/§6 — both are deliberately guest-submittable, keyed only by name/email/phone). Added `primeestate_get_own_inquiries()`/`primeestate_get_own_viewing_requests()` matching `_pe_email` against the logged-in user's account email — a best-effort match (a submission made with a different email won't surface here), documented inline as a deliberate, spec-consistent tradeoff rather than a schema change this late for a P4 story with no FR demanding a hard FK.
- **Account Settings reuses WP core's `/wp/v2/users/me`**, not a new custom endpoint — the same "don't build what core already provides" call made for property edits (`/wp/v2/property-posts/{id}`, Phase 7). Core's `edit_user` map-meta-cap already grants self-edit unconditionally, and the existing `wp_rest`-action nonce (already localized for every other REST-calling script) works against core routes too — satisfies T086's "nonce + capability checked" without a redundant custom layer.
- **T087's cache-exclusion audit found a real, six-phase-old gap**: `primeestate_mark_page_uncacheable()` (T033, Phase 2) had never actually been called from anywhere in the codebase — confirmed by a full-text search before writing any new code. Every "personalized" page built across Phases 5-7 (Favorites, Compare, Agent Dashboard, Add/Edit Property, Profile Edit) had been silently servable from a shared cache the whole time. Fixed by adding one centralized `primeestate_mark_dashboard_pages_uncacheable()` hooked to `template_redirect`, covering all six personalized routes including the new user-dashboard page — one audited list rather than scattering a call into each render file (each of which could be forgotten again the way the original helper was). The slug list itself was extracted into a small pure function so it's unit-testable without depending on `DONOTCACHEPAGE`, a process-wide `define()` that can't be reset to false mid-PHPUnit-run.
- **Seed-data gap found and fixed while making T085 actually demoable**: `primeestate_seed_demo_inquiries()` set `_pe_email` to a fully random Faker address, never a seeded demo user's real account email — meaning the new email-matching "My Inquiries" section would show nothing for *any* seeded account, and quickstart.md's own US7 line ("log in as a demo user with existing activity") would have been untestable as written. Fixed by attributing roughly half of seeded inquiries to a real demo user's actual name/email. Also discovered `pe_viewing_request` had **no seed data at all** — Phase 6 built the CPT and REST endpoints but seeding stopped at inquiries — added `primeestate_seed_demo_viewing_requests()` with the same demo-user-attribution logic (reset.php's cleanup list already included `pe_viewing_request`, anticipating this gap before it was filled).

## Outcome

- ✅ Impact: User Story 7 is code-complete — a logged-in user sees their own Favorites, Comparisons, Inquiries, Viewing Requests, and can edit their Account Settings, all on one page, with zero data leakage across users. The T087 cache-safety fix and the seed-data fixes both retroactively benefit every dashboard-style page built in Phases 5-7, not just this one.
- 🧪 Tests: 6 new PHPUnit cases covering own-data email-scoping (including cross-user isolation and the empty-email edge case) and a regression guard on the T087 page-slug coverage list. Not executed — same no-runtime constraint as every prior phase.
- 📁 Files: 1 new theme render file, 1 new block template, 1 new JS file, 1 extended enqueue.php (new `coreRestUrl` localized field + two new conditional-asset registrations), 2 extended CPT handler files (own-data query helpers), 1 extended security.php (T087's centralized cache-exclusion hook + testable slug list), 1 extended default-pages.php, 1 extended seed.php (inquiry/viewing seed-data attribution fix + new viewing-request seeding), 1 new test file, `tasks.md` updated with 3 completed checkboxes.
- 🔁 Next prompts: Phase 10 (User Story 8 — Administrator Moderates & Oversees the Platform, P4, T088-T09x): a PHPUnit test verifying admin overview counts against seeded data, and the WP-admin overview dashboard widget itself (FR-048).
- 🧠 Reflection: This phase's most valuable finding wasn't in the new code — it was a full-text search confirming a Phase-2 helper had sat unused for six phases, and a re-read of seed.php's inquiry function that revealed its random-email data would have made this exact phase's own feature undemoable. Both were caught only because T087 explicitly asked to "verify and finalize" rather than "add," which prompted checking existing state before writing new code — a useful distinction to keep applying to any future task phrased as verification rather than pure addition.

## Evaluation notes (flywheel)

- Failure modes observed: A helper function defined but never invoked (T033/`primeestate_mark_page_uncacheable()`) survived six phases of "brace/paren balance + block-template grep + manual call-tracing" verification because none of those checks test *whether* a function is called, only whether it's syntactically well-formed and its callees exist — the same category gap the Phase 8 PHR flagged for seed-data values, now confirmed to also apply to dead-but-valid code paths.
- Graders run and results (PASS/FAIL): Brace/paren balance check — PASS (all 10 modified/new PHP files). Block-template PHP-leak grep — PASS. Full-text search for `primeestate_mark_page_uncacheable` call sites prior to this phase — 0 found (confirmed gap, now fixed). Manual cross-reference of `primeestate_seed_demo_inquiries()`'s call site against its new signature — PASS.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Before Phase 10 (the admin overview widget, which will read counts across every CPT this project has), do one pass checking whether any other "define once, never call" or "seed with unrelated random data" pattern exists in the admin/moderation code path specifically, since both of this phase's real findings came from that same category of bug.
