---
id: 0012
title: Phase 7 US5 agent dashboard
stage: green
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 7 / US5 only, per explicit user instruction)
labels: [implementation, agent-dashboard, capabilities, rest-api, phpunit, author-template, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/post-types/property.php
 - wp-content/plugins/primeestate-core/includes/agents/agent-profile.php
 - wp-content/plugins/primeestate-core/includes/inquiries/inquiry-handler.php
 - wp-content/plugins/primeestate-core/includes/viewing/viewing-request.php
 - wp-content/plugins/primeestate-core/includes/pages/default-pages.php
 - wp-content/plugins/primeestate-core/includes/security/security.php
 - wp-content/themes/primeestate/inc/agent-dashboard-render.php
 - wp-content/themes/primeestate/inc/agent-property-form-render.php
 - wp-content/themes/primeestate/inc/agent-profile-edit-render.php
 - wp-content/themes/primeestate/inc/agent-profile-render.php
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/themes/primeestate/templates/page-agent-dashboard.html
 - wp-content/themes/primeestate/templates/page-agent-add-property.html
 - wp-content/themes/primeestate/templates/page-agent-profile-edit.html
 - wp-content/themes/primeestate/templates/author.html
 - wp-content/themes/primeestate/assets/js/agent-dashboard.js
 - wp-content/themes/primeestate/assets/js/property-form.js
 - wp-content/themes/primeestate/assets/js/agent-profile-form.js
 - specs/001-real-estate-platform/contracts/primeestate-api.openapi.yaml
 - tests/unit/test-agent-permissions.php
 - tests/unit/test-property-publish-branching.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-property-publish-branching.php (9 cases — capability branching, client-supplied-status-is-ignored proof, submitted_by/post_author correctness)
 - tests/unit/test-agent-permissions.php (9 cases — own-only enforcement via map_meta_cap, property_manager's platform-wide access, moderate_properties exclusivity)
---

## Prompt

continue into Phase 7

## Response snapshot

Implemented all 8 Phase 7 (User Story 5 — Agent Manages Listings via Dashboard, P3) tasks, T070-T077 — the largest phase so far (8 tasks spanning 2 plugin capability-branching functions, 3 REST endpoints, 4 new theme pages, 3 new JS files, and an architectural correction on T077).

Followed through on the prior phase's stated plan: wrote T071 (the `/sp.analyze`-flagged FR-061 publish-branching test) immediately after implementing the capability-branching logic itself (`primeestate_determine_property_publish_status()`), before any dashboard UI code could assume behavior from it, including an explicit test that a malicious/confused client stuffing `status`/`post_status` into the request body cannot influence the outcome — proven by construction, since `primeestate_create_property_from_submission()`'s signature has no parameter that reads one.

Key decisions:
- **T077 architectural correction**: `theme/templates/single-agent.html` isn't a real WordPress block-template slot — the hierarchy only recognizes `single-{post_type}.html` for a *registered post type*, and `agent` is deliberately a WP user role, not a CPT (data-model.md §3). Implemented as `templates/author.html` (WordPress's actual native mechanism for a user's public page) with `author_base` remapped from `/author/` to `/agents/` via `$wp_rewrite->author_base`, so URLs still read naturally. Documented this substitution inline, in the PHR, and as an annotation on T077's tasks.md checkbox — the same transparency pattern used for T010's URL-structure decision in Phase 2.
- **One capability-branching endpoint, one decision point**: `POST /properties` (T073) determines `post_status` from `user_can( $user_id, 'publish_properties' )` — one function, reused by nothing else, tested in isolation (T071) before the REST handler wraps it. The honeypot/rate-limit check is skipped entirely for `publish_properties` holders (an authenticated agent adding their own listing isn't the anonymous-abuse surface FR-056 targets) using the exact same capability check that decides publish status — no second "trusted submitter" concept invented.
- **Editing reuses WordPress core's REST API instead of a custom endpoint**: the Add/Edit Property form's *create* path hits the custom endpoint (owns the FR-061 business rule); its *edit* path PATCHes `/wp-json/wp/v2/property-posts/{id}` — WordPress's own auto-generated CPT controller, already capability-gated by the same `map_meta_cap` setup T070 verifies, with every needed field/meta/taxonomy already `show_in_rest`. No custom "update property" endpoint was written because none was needed — a deliberate small-footprint choice consistent with constitution Principle I.
- **Agent profile writes deliberately bypass `/wp/v2/users`**: `_pe_agent_*` usermeta is registered `show_in_rest: false` specifically so WordPress core's generic users endpoint can't touch it (that endpoint has no owner-aware per-field gating — any caller with `edit_users` could otherwise edit any agent's profile through it). Added a dedicated `PATCH /agents/{id}` instead, enforcing `manage_agent_profile` + explicit own-ID-or-`edit_others_properties` check, and updated the OpenAPI contract to document it.
- **Bug caught before being reported done**: an early draft of `PATCH /agents/{id}`'s field-mapping used `ltrim( $meta_key, '_pe_agent_' )` to derive the request param name from the meta key — looked plausible, but `ltrim()` strips a *character set*, not a prefix string, and every letter in "phone" also appears in "_pe_agent_", so it silently mangled `phone` to `hone`. Caught by tracing the string transformation by hand (not by running anything, since no PHP runtime exists here) and replaced with an explicit `param_name => [meta_key, sanitizer]` map — documented inline as a cautionary comment for future readers.
- **T075's "filter" requirement**: initially built only status-change + notes (the two most obviously "management" actions) and only noticed the explicit "filter" word in the task description on review — added a client-side status filter (hide/show rows already in the DOM; no server round-trip needed since both tables render the full assigned-record set already) to both the inquiries and viewing-requests tables, plus kept each row's `data-status` attribute in sync when its own status select changes, so the filter stays correct after an inline edit without a page reload.
- **Shared REST config script**: extracted the `{ restUrl, nonce, isLoggedIn }` localization (previously bundled into favorites.js) into its own empty-src dependency-only script handle, now shared by favorites.js, comparison.js, agent-dashboard.js, property-form.js, and agent-profile-form.js — avoids five copies of the same `wp_localize_script()` call.

## Outcome

- ✅ Impact: User Story 5 is code-complete — an authenticated agent can add/edit their own properties (publishing immediately), manage assigned inquiries/viewing requests with filtering and notes, and edit their public profile; the catalog is no longer solely seed-dependent. The capability model this entire dashboard depends on (own-only enforcement, the publish/pending branch) now has direct, isolated test coverage rather than only being exercised incidentally through other features.
- 🧪 Tests: 18 new PHPUnit cases across 2 files. Not executed — same no-runtime constraint as every prior phase; the `ltrim()` bug above is a concrete illustration of exactly the class of error that constraint leaves this project exposed to until a real environment check happens.
- 📁 Files: 4 extended plugin files, 1 extended security.php (access-guard helper), 1 extended default-pages.php (3 new gated pages), 4 new theme render files, 1 extended enqueue.php, 4 new/1 repurposed block templates, 3 new JS files, OpenAPI contract extended with `PATCH /agents/{id}`, 2 new PHPUnit files, `tasks.md` updated with 8 completed checkboxes (T077 annotated with the author.html substitution).
- 🔁 Next prompts: Phase 8 (User Story 6 — Registered User Submits a Property for Review, P3): this phase's `POST /properties` endpoint already handles the pending-submission branch correctly (T073 covers both paths in one endpoint), so Phase 8's remaining work is primarily the public submission FORM (a non-agent-facing version of T074's form, most likely reusable via `primeestate_render_property_form()`), the moderation approve/reject endpoint (`PATCH /properties/{id}/moderate`, already documented in the OpenAPI contract but not yet implemented), and FR-044's upload validation.
- 🧠 Reflection: The `ltrim()` bug is the fourth distinct "looked right, wasn't" mistake caught across seven phases (activation-hook timing, comparison.js's all-or-nothing self-heal, `phone`→`hone`, plus Phase 3's PHP-in-block-template) — each was caught by deliberately tracing the exact transformation by hand rather than trusting that the code "reads correctly." That tracing discipline is doing real work in an environment with no PHP runtime to catch these automatically, and is worth treating as load-bearing, not optional, for as long as that constraint holds.

## Evaluation notes (flywheel)

- Failure modes observed: The `ltrim()` prefix-vs-charset bug (see Response snapshot) — caught and fixed pre-completion. The missing "filter" control in the first draft of T075 — caught on a re-read of the task description against what was actually built, not from any external check; worth noting that re-reading the ORIGINAL task wording after implementing, not just working from memory of it, is what caught this.
- Graders run and results (PASS/FAIL): Brace/paren balance check across all new/modified PHP files — PASS. Block-template PHP-leak grep — PASS (0 matches across 4 new/repurposed template files this phase). Manual cross-reference of every new REST route registration against its permission_callback and capability model — PASS, each traced back to an existing, tested capability (`manage_properties`, `manage_agent_profile`, `edit_others_properties`, `publish_properties`) rather than an ungated or newly-invented one.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Before starting Phase 8's moderation endpoint, re-read T075's exact wording pattern applied here — cross-check each new phase's task descriptions against the actual diff word-by-word before marking done, specifically hunting for verbs/nouns in the task text that didn't make it into the implementation, since that's exactly how the missing filter slipped through on the first pass this phase.
