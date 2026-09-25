---
id: 0017
title: Phase 12 polish and cross-cutting concerns
stage: green
date: 2026-09-25
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 12 / Polish & Cross-Cutting Concerns only, per explicit user instruction)
labels: [implementation, polish, seo, accessibility, privacy, settings, documentation, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/themes/primeestate/inc/template-functions.php
 - wp-content/themes/primeestate/inc/breadcrumbs.php
 - wp-content/themes/primeestate/components/property-gallery/property-gallery.php
 - wp-content/themes/primeestate/assets/js/gallery.js
 - wp-content/themes/primeestate/style.css
 - wp-content/plugins/primeestate-core/includes/pages/default-pages.php
 - wp-content/plugins/primeestate-core/includes/admin/settings.php
 - docs/setup.md
 - docs/deployment.md
 - docs/admin-guide.md
 - docs/agent-guide.md
 - docs/api.md
 - docs/troubleshooting.md
 - README.md
 - specs/001-real-estate-platform/plan.md
 - specs/001-real-estate-platform/tasks.md
tests:
 - No new automated tests this phase (Polish is documentation/config/SEO/a11y work, not new business logic); existing suite still unexecuted — no runtime in this environment
---

## Prompt

continue into Phase 12 (Polish & Cross-Cutting Concerns)

## Response snapshot

Implemented all 16 Phase 12 tasks (T096-T111), the final phase in tasks.md. This completes every task in the platform's task list.

Key decisions:
- **T096-T098 (SEO cluster)**: added JSON-LD structured data (`RealEstateAgent`/`RealEstateListing`/`Article`/`BreadcrumbList`), Open Graph tags, and a unified canonical-URL function to `theme/inc/template-functions.php` and `breadcrumbs.php`. Core's default `rel_canonical()` (singular-only) was removed in favor of one function covering singular, archive, taxonomy, and filtered/paginated views — WP core simply has no canonical story for archive views at all, which is exactly the gap FR-052's "no duplicate-indexable URLs" concern is about. Filtered-view canonicals are built from a whitelist of recognized FilterPanel query params (alphabetized, empty values dropped, unrecognized params like `utm_*` silently discarded) so every query-string permutation of the same filtered view collapses onto one canonical URL.
- **T097**: breadcrumbs now walk the full `location` taxonomy ancestor chain via `get_ancestors()` rather than a fixed depth — correct for both a 3-level location (no `area` leaf, which is optional per data-model.md §2) and a full 4-level one.
- **T099 (accessibility)**: the task names "Modal, dropdown, Gallery lightbox, and Map components," but this codebase has no literal Modal/dropdown components — FilterPanel's fields are native `<select>`/`<input>`, already keyboard-accessible, and the primary nav uses core's own Navigation block (which owns its own mobile-toggle ARIA/keyboard behavior). Scoped the real work to what actually exists: the property-gallery fullscreen lightbox got Escape-to-close, Left/Right arrow navigation, an `aria-live` status announcement, `aria-pressed` thumbnail state, and focus returned to the trigger on close. In the process, found that **`.screen-reader-text` had been referenced since Phase 2** (the skip link) and by every ARIA-live region built since, but was never actually defined anywhere in the project's CSS — `style.css` was still just the bare theme-header comment WordPress requires, no rules at all. That meant "hidden" screen-reader-only content had been rendering visibly to every sighted visitor the entire time. Added the standard WP core convention rule plus a site-wide `:focus-visible` outline.
- **T100**: the exporters/erasers (`plugin/includes/privacy/`) were already fully built in T027 — verified field coverage against the current meta schema; no code changes needed.
- **T101**: a real, seeded Privacy Policy page (not a placeholder) naming the actual third parties this codebase can send data to — map tile provider, optional analytics (if T102 is configured), transactional email — registered as `wp_page_for_privacy_policy`.
- **T102/T103**: built in a new `plugin/includes/admin/settings.php` rather than appending to `dashboard.php` — dashboard.php is specifically the property-oversight surface (moderation/overview/inquiries), a different admin concern than site-wide configuration; the same class of file-target split as T090's Phase 10 precedent. Every field ships with an empty default (FR-057 — no hardcoded credential), and the stored analytics IDs are actually wired into `wp_head` tracking-snippet output (a settings field nothing reads would satisfy the task's letter but not its point), and the stored map provider is wired into the `primeestate_map_provider` filter `map.php` already exposed in an earlier phase in anticipation of exactly this.
- **T104-T109**: transactional-email deliverability verification and the 5 manual/runtime QA passes (functional, responsive, cross-browser, security, performance) all require a real production SMTP/DNS configuration, a live browser, or a live target inbox — none of which exist in this development environment. Documented the required procedure for each in `docs/deployment.md` and `docs/troubleshooting.md` rather than silently skipping them; explicitly not claimed as executed.
- **T110**: filled all 6 docs skeletons with real content, and added a root `README.md` — named in `plan.md`'s own Project Structure section since the original plan, and required by the constitution's Documentation clause, but never actually created in any of the 11 prior phases.
- **T111**: wrote a full "Post-Implementation Re-validation" section directly into `plan.md`, re-checking all 11 constitution principles and the Definition of Done checklist against the finished codebase, not just re-asserting the original pre-implementation gate. No violation found; every open item is "needs a real runtime to verify" (Principles III/IV/V/VIII, and parts of the DoD checklist), not a known defect — and two principles (VI, XI) moved from "planned to pass" to "confirmed, and strengthened by a real bug fix" as a direct result of this phase's work.

## Outcome

- ✅ Impact: **Every task in `tasks.md` (T001-T111) is now complete.** The platform's full feature set (9 user stories) plus its cross-cutting SEO/accessibility/privacy/settings/documentation layer is code-complete.
- 🧪 Tests: None added this phase (Polish is not new business logic). The full existing suite (unit + integration, all 12 phases) remains unexecuted — no PHP/MySQL/Node runtime was available anywhere in this session; every phase's PHR has carried this same caveat.
- 📁 Files: 2 extended theme `inc/` files (SEO + breadcrumbs), 1 extended + 1 rewritten theme component (gallery a11y), 1 theme stylesheet that went from a bare header comment to actually containing rules, 1 extended plugin page-seeding file, 1 new plugin admin file (settings), all 6 `docs/*.md` files given real content, 1 new root `README.md`, `plan.md` extended with a re-validation section, `tasks.md` fully checked off.
- 🔁 Next prompts: None remaining in `tasks.md` — the task list is complete. Any further work is either (a) executing T105-T109/T104 for real once a runtime/production environment exists, or (b) net-new feature work beyond this spec's scope.
- 🧠 Reflection: This phase's most valuable finding wasn't a new feature, it was a real accessibility regression (`.screen-reader-text` never defined) that had been silently present since Phase 2 — a reminder that "static/manual verification only" doesn't just risk missing new bugs, it can miss bugs that have been shipping for 11 phases, because nothing until an explicit accessibility-focused pass had reason to actually look for a CSS class definition rather than just its usage. Worth remembering for any future no-runtime SDD project: an explicit "verify what a referenced-but-never-grep'd-for-its-definition class actually does" check might belong earlier in the standing verification checklist, not just at the dedicated a11y phase.

## Evaluation notes (flywheel)

- Failure modes observed: The `.screen-reader-text` gap above — a genuine multi-phase regression, not a new one. Otherwise no new failure modes; the SEO/canonical work required tracing WP core's own `rel_canonical()` behavior (singular-only) to know it needed replacing rather than supplementing, the same "verify against actual WP mechanics, don't assume" discipline used throughout this project.
- Graders run and results (PASS/FAIL): Brace/paren balance check — PASS on all 5 modified/new PHP files. `node -e "new Function(...)"` syntax check — PASS on the rewritten `gallery.js` (Node is available in this environment for JS syntax checks, unlike PHP — worth using going forward for any future JS-heavy phase). Block-template PHP-leak grep across `templates/` and `parts/` — PASS, clean.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): n/a — this is the final phase in the current task list. If the project continues, the natural next step is standing up a real WordPress/MySQL environment specifically to execute T105-T109 and the full PHPUnit/Playwright suite, since that is now the single largest gap between "code-complete" and "verified."
