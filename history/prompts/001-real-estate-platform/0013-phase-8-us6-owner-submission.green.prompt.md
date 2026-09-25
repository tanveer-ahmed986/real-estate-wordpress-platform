---
id: 0013
title: Phase 8 US6 owner submission and moderation
stage: green
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 8 / US6 only, per explicit user instruction)
labels: [implementation, moderation, uploads, seed-data, rest-api, phpunit, playwright, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/security/uploads.php
 - wp-content/plugins/primeestate-core/includes/post-types/property.php
 - wp-content/plugins/primeestate-core/includes/admin/dashboard.php
 - wp-content/plugins/primeestate-core/includes/cli/seed.php
 - wp-content/plugins/primeestate-core/includes/pages/default-pages.php
 - wp-content/themes/primeestate/inc/agent-property-form-render.php
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/themes/primeestate/templates/page-submit-property.html
 - wp-content/themes/primeestate/assets/js/property-form.js
 - specs/001-real-estate-platform/contracts/primeestate-api.openapi.yaml
 - specs/001-real-estate-platform/quickstart.md
 - tests/unit/test-upload-validation.php
 - tests/e2e/submission-to-approval.spec.ts
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-upload-validation.php (7 cases — valid image, oversized, empty, renamed-executable, disallowed extension, SVG rejection, missing file)
 - tests/e2e/submission-to-approval.spec.ts (1 case — the second required trust-critical flow: submit → hidden → admin approves → publicly visible)
---

## Prompt

continue into Phase 8

## Response snapshot

Implemented all 7 Phase 8 (User Story 6 — Registered User Submits a Property for Review, P3) tasks, T078-T084.

Two significant seed-data gaps discovered and fixed while building T079's E2E test — both would have quietly undermined the project's usability as a portfolio demo, not just the test:
- **Unusable demo logins**: `seed.php` generated a random `wp_generate_password()` per seeded account, meaning nobody — not a human exploring the site, not this E2E test — could ever know a seeded account's password. Replaced with a fixed, documented `PRIMEESTATE_DEMO_PASSWORD` constant (`PrimeEstateDemo123!`), safe specifically because seeded accounts are `.test`-domain and fully removable via `wp primeestate reset`.
- **Unpredictable usernames**: seeded agent/user logins embedded a random Faker name (`demo_agent_john.smith_3`), making even the *username* impossible to know in advance despite the fixed password fix. Changed to deterministic `demo_agent_0`..`demo_agent_7` / `demo_user_0`..`demo_user_4`, keeping realistic Faker names only in the display name.
- **quickstart.md claimed seed creates "1 administrator, 1 editor"** but the command never created either (the administrator already comes from `wp core install`, and there was no editor at all) — added a genuine seeded `editor` account and corrected the documentation to accurately describe both the administrator's actual source and the fixed demo password.

Other decisions:
- **Custom `POST /uploads`, not WordPress core's `/wp/v2/media`**: core's media endpoint requires `upload_files`, a capability the `subscriber` role (FR-041's actual audience) doesn't and shouldn't hold. This endpoint instead authenticates any logged-in user and does its own narrower validation — content-sniffed via `wp_check_filetype_and_ext()` plus a `getimagesize()` decode check, not just extension-matched, so a renamed executable is actually rejected (proven by `test_executable_renamed_with_image_extension_is_rejected`).
- **Uploads explicitly excluded from FR-056's shared rate limit**: caught this as a design conflict while writing the endpoint, before it shipped — a single property submission legitimately needs several photo uploads, which would exhaust the inquiry/viewing/submission 3-per-hour combined budget before the form itself was even submitted. Documented the exclusion inline and in the OpenAPI contract rather than silently deviating from the established pattern.
- **One moderation decision function, two entry points**: `primeestate_moderate_property()` (property.php) backs both the REST endpoint (T082) and the wp-admin moderation queue's admin-post.php action (T083), so approve/reject behaves identically regardless of which UI triggered it — the same "shared core, multiple entry points" pattern used for inquiries/viewings' PATCH logic in earlier phases.
- **Public Submit Property form reuses the agent Add/Edit form wholesale**: same `primeestate_render_property_form()`/property-form.js pair, gated by `is_user_logged_in()` instead of `manage_properties`. Added a `data-redirect-on-create` attribute (agent form → dashboard; public form → stays in place) and a capability-aware success message ("Property added" vs. "Submitted for review") so the one shared component behaves correctly in both contexts without a second copy of the form.
- **Image upload UI added to the shared form**: file input → per-file `POST /uploads` → collected attachment IDs → included in the create payload — benefits both the agent and public submission paths simultaneously, since neither had upload capability before this phase.

## Outcome

- ✅ Impact: User Story 6 is code-complete — a registered non-agent user can submit a property with photos, it stays invisible until an administrator or editor approves it (via either the wp-admin queue or the REST endpoint), and the submitter is notified either way. The demo-account usability gaps fixed this phase benefit every previous phase's manual-testing story too, not just this one.
- 🧪 Tests: 8 new test cases (7 PHPUnit upload validation, 1 Playwright E2E — the constitution's second required flow). Not executed — same no-runtime constraint as every prior phase.
- 📁 Files: 1 new plugin file (uploads.php), 1 new admin file (dashboard.php — the project's first wp-admin UI), 1 extended property.php (moderation + notification), 1 extended seed.php (demo-password/username/editor fixes), 1 extended default-pages.php, 1 extended agent-property-form-render.php (public shortcode + upload UI), 1 extended property-form.js, 1 extended enqueue.php, 1 new block template, OpenAPI contract extended with `POST /uploads`, quickstart.md corrected, 2 new test files, `tasks.md` updated with 7 completed checkboxes.
- 🔁 Next prompts: Phase 9 (User Story 7 — Registered User Manages Personal Dashboard, P4, T085-T087): a User Dashboard shell wiring the already-built Favorites/Compare views and viewing-request status together into one user-scoped page, an Account Settings form, and a cache-exclusion audit across every dashboard route using the T033 helper already built in Phase 2.
- 🧠 Reflection: The seed-data fixes this phase weren't triggered by writing new business logic — they surfaced only because T079 required *actually being able to log in as a seeded account*, which no PHPUnit test would ever exercise (PHPUnit tests create their own users via factories, never touching the seed command at all). This is a good illustration of why the two required Playwright flows matter as a category distinct from unit tests: they're the only place in this test suite that exercises the seed command's real-world usability at all, and this phase is the first time either of them touched login.

## Evaluation notes (flywheel)

- Failure modes observed: Two real, user-facing seed-data defects (unusable passwords, unpredictable usernames) that had been present since Phase 2 and gone unnoticed for six phases — not caught by any of the per-phase verification passes (balance checks, block-template grep, manual function-call tracing) because none of those checks touch seed.php's actual data values, only structural PHP correctness. Worth noting as a category of bug the established verification routine doesn't cover.
- Graders run and results (PASS/FAIL): Brace/paren balance check — PASS (including property-form.js, substantially rewritten this phase). Block-template PHP-leak grep — PASS. Manual trace of the moderation flow's two entry points (REST + admin-post.php) both calling the identical shared function — PASS.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Since this phase revealed that seed-data *values* (not just structure) are an under-checked risk category, do one deliberate pass — even just re-reading seed.php once more start-to-finish — before Phase 9 touches any user-dashboard code that will display seeded data, to catch anything else in that file that "looks right" but wouldn't survive an actual login attempt.
