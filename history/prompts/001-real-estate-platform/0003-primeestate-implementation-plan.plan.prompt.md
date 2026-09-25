---
id: 0003
title: PrimeEstate implementation plan
stage: plan
date: 2026-09-24
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.plan
labels: [plan, architecture, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - specs/001-real-estate-platform/plan.md
 - specs/001-real-estate-platform/research.md
 - specs/001-real-estate-platform/data-model.md
 - specs/001-real-estate-platform/contracts/primeestate-api.openapi.yaml
 - specs/001-real-estate-platform/quickstart.md
 - CLAUDE.md
tests:
 - none (planning stage; test strategy defined in research.md §6 for implementation phase)
---

## Prompt

/sp.plan (no additional arguments)

## Response snapshot

Ran `setup-plan.ps1 -Json` to copy the plan template and resolve paths, then loaded `spec.md` and `.specify/memory/constitution.md` (the WordPress Premium Development Constitution, generalized from a prior WooCommerce project — Principle I, II, IV, VIII, XI etc. required mapping onto PrimeEstate's non-commerce, lead-generation nature).

**Phase 0 (research.md)** resolved 9 technical decisions: server-rendered `WP_Query`-based search with AJAX-fragment progressive enhancement + URL state sync; no custom DB table at this scale; Leaflet/OpenStreetMap as the zero-cost default map provider with configurable Google/Mapbox and graceful no-map fallback; `localStorage`-based guest favorites/comparison merged into account on login; honeypot + WP-transient rate limiting (3/visitor/hour, per the clarified FR-056 threshold) with optional CAPTCHA; PHPUnit for plugin logic + Playwright for 2 required E2E flows (mapping the constitution's "core purchase path" requirement onto PrimeEstate's actual conversion path: Search→Detail→Inquiry, plus Owner Submission→Approval); documented (not hardcoded) transactional-email/SMTP requirement; a custom `wp primeestate seed`/`reset` WP-CLI workflow using Faker; and native WordPress personal-data export/erasure integration for inquiries/viewings/favorites (Principle XI compliance) with indefinite retention.

**Phase 1** produced `data-model.md` (Property CPT with full postmeta field table and workflow/state-transition rules distinguishing agent-direct-publish from owner-submission moderation per FR-045a; Agent as a WP user role with custom capabilities, not a separate CPT; Inquiry and Viewing Request CPTs with status-transition rules; Favorites/Comparison as usermeta/localStorage, not standalone entities; a full capability matrix), `contracts/primeestate-api.openapi.yaml` (OpenAPI 3.0 spec for all custom `primeestate/v1` REST endpoints — properties search/submit/moderate/related, favorites + merge, inquiries, viewings — each with permission/validation notes tied back to FRs), and `quickstart.md` (environment setup, seed commands, a manual smoke-test table mapped 1:1 to the 9 user stories, and non-functional verification steps for performance/accessibility/security/caching).

Filled `plan.md`'s Technical Context and ran a full Constitution Check: all 11 principles PASS, with two principles requiring explicit interpretive mapping (documented, not treated as violations) — Principle I's WooCommerce clauses are N/A (no commerce/checkout in scope) and Principle VIII's "core purchase path" is mapped to the inquiry-submission flow. No Complexity Tracking entries were needed (zero violations). Documented the Project Structure as the source document's own `wp-content/themes/primeestate` + `wp-content/plugins/primeestate-core` layout, with two additions surfaced by research: `includes/privacy/` (Principle XI exporters/erasers) and `includes/cli/` (seed/reset commands).

Ran `update-agent-context.ps1 -AgentType claude`; discovered it silently no-op'd (it expects `## Active Technologies` marker sections from the standard agent-file-template that this project's custom-authored `CLAUDE.md` doesn't have), so manually added an equivalent "Active Technologies" section to `CLAUDE.md` to keep future turns informed of the stack without needing to re-read `plan.md`.

## Outcome

- ✅ Impact: Full Phase 0/Phase 1 planning artifact set produced and Constitution Check passed cleanly; ready for `/sp.tasks`.
- 🧪 Tests: None run (planning stage). Test strategy fully specified in research.md §6 for the implementation phase (PHPUnit + Playwright, 2 required E2E flows).
- 📁 Files: specs/001-real-estate-platform/{plan.md, research.md, data-model.md, quickstart.md, contracts/primeestate-api.openapi.yaml} (all new); CLAUDE.md (Active Technologies section added manually after the generated agent-context script no-op'd).
- 🔁 Next prompts: /sp.tasks to generate the dependency-ordered task breakdown from this plan.
- 🧠 Reflection: The project constitution was written for a prior WooCommerce e-commerce project and generalizes awkwardly to a non-commerce listings platform in places (purchase path, product/cart/checkout language) — rather than treat those clauses as violated or the constitution as inapplicable, mapped them onto PrimeEstate's real equivalents (inquiry = conversion event) and documented the mapping explicitly in the Constitution Check table so the reasoning is auditable, not just asserted.

## Evaluation notes (flywheel)

- Failure modes observed: `update-agent-context.ps1` silently no-ops on a CLAUDE.md that doesn't already contain the agent-file-template's marker headings — worth a future fix (either bootstrap the markers once, or have the script warn instead of silently succeeding) but worked around manually this session, not blocking.
- Graders run and results (PASS/FAIL): Constitution Check — PASS (11/11 principles, 1 N/A, 2 explicitly-mapped interpretations, 0 violations, empty Complexity Tracking table).
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): During /sp.tasks, verify the two required Playwright E2E flows and the PHPUnit-covered business logic areas from research.md §6 actually get corresponding task entries, not just implementation tasks — the constitution's testing discipline requirement needs to survive task decomposition, not just planning.
