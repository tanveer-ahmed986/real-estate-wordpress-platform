---
id: 0011
title: Phase 6 US4 viewing requests
stage: green
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 6 / US4 only, per explicit user instruction)
labels: [implementation, viewing-requests, rest-api, phpunit, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/viewing/viewing-request.php
 - wp-content/themes/primeestate/components/viewing-form/viewing-form.php
 - wp-content/themes/primeestate/inc/single-property-render.php
 - wp-content/themes/primeestate/inc/enqueue.php
 - wp-content/themes/primeestate/assets/js/viewing-form.js
 - tests/unit/test-viewing-request.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-viewing-request.php (9 cases — date validation incl. the today-is-not-past boundary, required-field checks, status storage, transition-graph validity)
---

## Prompt

continue into Phase 6

## Response snapshot

Implemented all 5 Phase 6 (User Story 4 — Request a Property Viewing, P2) tasks, T065-T069.

Applied the explicit lesson from the previous phase's PHR (the "trace the edge case, not just the happy path" reflection) directly to FR-031's past-date validation: compared `preferred_date` against `current_time( 'Y-m-d' )` as a pure calendar-date string comparison, not a combined datetime instant — so "today" is always valid regardless of what time it currently is, and there's no timezone-boundary trap from mixing a date-only field with a time-aware comparison. Wrote `test_todays_date_is_accepted_not_treated_as_past()` specifically to lock this in, per that same reflection's stated next-experiment intent.

Other decisions:
- **Status transition graph enforced server-side**: `PATCH /viewings/{id}` doesn't just accept any of the 5 enum values — `primeestate_is_valid_viewing_transition()` encodes data-model.md §6's actual graph (Requested→{Confirmed,Rescheduled}, Confirmed→{Completed,Rescheduled}, Rescheduled→{Confirmed}, Cancelled reachable from anywhere, Completed terminal) and rejects an invalid jump (e.g. Requested straight to Completed) with 400, going beyond what the OpenAPI contract's prose literally requires but consistent with the data model it's built from.
- **Authorization reuses Phase 4's inquiries pattern exactly**: `primeestate_user_sees_all_inquiries()` (defined in inquiry-handler.php) gates viewing-request PATCH too, since data-model.md §10's capability matrix explicitly lumps "view all inquiries/viewings" into one row — no new capability or duplicated ownership logic needed.
- **T069's "confirmation state"**: read this as more than InquiryForm's inline success text — a viewing request has its own visible status lifecycle (FR-032), so on success `viewing-form.js` now replaces the entire form with a confirmation panel showing the stored status and requested date/time, giving the visitor something concrete to look at rather than a form that still looks submittable. Wrapped the form in a new `[data-component="viewing-form-wrapper"]` container specifically to make this swap clean.
- **Wired ViewingForm into the detail page**: Phase 4 never added it (US2's scope was inquiries only) — added `primeestate_render_viewing_form( $post->ID )` to `single-property-render.php` right after the inquiry form, and registered `viewing-form.js` in `enqueue.php` alongside the other single-property-only scripts.

## Outcome

- ✅ Impact: User Story 4 is code-complete — visitors can request a viewing with server-enforced past-date rejection and a real status lifecycle, and staff can confirm/reschedule/cancel/complete through the same authorization model as inquiries.
- 🧪 Tests: 9 new PHPUnit cases, including the explicit today-boundary case flagged as this phase's planned focus in the prior PHR. Not executed — same no-runtime constraint as every prior phase.
- 📁 Files: 1 extended plugin file (viewing-request.php — CPT registration from Phase 2 plus all new validation/storage/REST logic), 1 new theme component, 1 extended single-property-render.php, 1 extended enqueue.php, 1 new JS file, 1 new PHPUnit file, `tasks.md` updated with 5 completed checkboxes.
- 🔁 Next prompts: Phase 7 (User Story 5 — Agent Dashboard, P3, T070-T077): the two PHPUnit tests flagged by the earlier `/sp.analyze` remediation (own-property capability enforcement, FR-061's publish-branching test — this is the platform's most explicitly clarified business rule), the Agent Dashboard shell, the agent-facing property submit/edit form, wiring the dashboard's inquiry/viewing management UI to the PATCH endpoints already built in Phases 4 and 6, the agent profile edit form, and the public Agent Profile page.
- 🧠 Reflection: Explicitly carrying forward a named lesson from one phase's PHR into the next phase's implementation (rather than just noting it and moving on) worked cleanly here — the date-comparison design was decided correctly on the first attempt instead of needing a fix-after-the-fact like the last two phases' caught bugs. Worth continuing this pattern deliberately: end each phase's PHR with one concrete, checkable thing the next phase should get right the first time, not just a general area of caution.

## Evaluation notes (flywheel)

- Failure modes observed: None new this phase — no bugs caught and fixed mid-implementation, unlike Phases 3, 4, and 5 each finding one. Possibly a sign the established patterns (server-rendered fragments, shared query builder, reused capability checks) are stabilizing enough that new phases are mostly recombination rather than new-surface-area risk — worth staying alert to whether that holds for Phase 7's dashboard UI, which is more form-heavy and admin-facing than anything built so far.
- Graders run and results (PASS/FAIL): Brace/paren balance check — PASS. Block-template PHP-leak grep — PASS (0 matches; no new block templates this phase regardless).
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Phase 7's T071 (the analyze-remediation-added publish-branching test) is the single highest-value test in the whole task list per the original `/sp.analyze` findings — worth writing it first, before the dashboard UI, so the capability branching it verifies is locked in before any UI code assumes a particular behavior from it.
