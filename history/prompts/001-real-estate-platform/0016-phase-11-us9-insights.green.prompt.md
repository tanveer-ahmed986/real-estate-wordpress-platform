---
id: 0016
title: Phase 11 US9 insights blog
stage: green
date: 2026-09-25
surface: agent
model: claude-sonnet-5
feature: 001-real-estate-platform
branch: 001-real-estate-platform
user: tanveer030402@gmail.com
command: /sp.implement (scoped to Phase 11 / US9 only, per explicit user instruction)
labels: [implementation, insights, blog, block-templates, seed-data, real-estate, wordpress, primeestate]
links:
  spec: specs/001-real-estate-platform/spec.md
  ticket: null
  adr: null
  pr: null
files:
 - wp-content/plugins/primeestate-core/includes/meta/insight-meta.php
 - wp-content/plugins/primeestate-core/includes/admin/dashboard.php
 - wp-content/themes/primeestate/inc/insight-render.php
 - wp-content/themes/primeestate/templates/taxonomy-insight_category.html
 - wp-content/themes/primeestate/templates/single-post.html
 - wp-content/plugins/primeestate-core/includes/cli/seed.php
 - wp-content/plugins/primeestate-core/includes/cli/reset.php
 - tests/unit/test-insight-related-properties.php
 - specs/001-real-estate-platform/tasks.md
tests:
 - tests/unit/test-insight-related-properties.php (4 cases — empty when nothing curated, renders curated properties, drops unpublished curated IDs, empty when every curated ID is gone)
---

## Prompt

continue into Phase 11 (US9 — Insights)

## Response snapshot

Implemented all 3 Phase 11 (User Story 9 — Read Real Estate Insights/Blog, P5) tasks, T093-T095. This is the last user-story phase — all 9 user stories are now independently functional.

Key decisions:
- **T093's named filename doesn't match WordPress's actual template hierarchy**: `archive-{slug}.html` is the pattern for a post-*type* archive (e.g., `archive-property.html` from Phase 3), not a taxonomy term archive — WordPress matches those via `taxonomy-{taxonomy}.html`. Building the task-named `archive-insight-category.html` would have produced a file WordPress simply never selects, silently falling back to whatever generic template exists instead. Built `taxonomy-insight_category.html` instead, the same category of naming substitution as T077's `single-agent.html`→`author.html` (a WP role isn't a template-hierarchy slot either).
- **Both new templates lean almost entirely on native WP core blocks** (`core/post-title`, `core/post-featured-image`, `core/post-author-name`, `core/post-date`, `core/post-terms`, `core/post-content`, `core/query` with `"inherit":true`), unlike every property-related template which needed a PHP shortcode for everything — this is the literal payoff of data-model.md §9's original design bet ("uses the native `post` type... so standard WordPress blog tooling works unmodified"). The *only* genuinely custom piece is `[primeestate_related_properties]`, since curated related-property data has no core-block equivalent.
- **The related-properties meta box is a plain scrollable checkbox list**, not a searchable/async widget — FR-060's demo-scale property count (50+) comfortably fits without needing search infrastructure this codebase doesn't otherwise have anywhere.
- **Seed-data gap caught before it shipped, this time proactively**: while writing T095's seeding function, checked `wp primeestate reset` (reset.php) before assuming it would clean up the new demo articles — it wouldn't have (`$post_types` never included `'post'`), which would have made every `seed`→`reset`→`seed` cycle accumulate duplicate demo articles indefinitely. Fixed by adding `'post'` to reset.php's cleanup list. The Phase 10 PHR's stated "next experiment" (check for this exact class of gap before Phase 11 touches admin/seed code) is what prompted checking this before writing the seeding function rather than after.

## Outcome

- ✅ Impact: User Story 9 is code-complete, and with it, **the full platform feature set** — all 9 user stories (browse/search through admin oversight through insights) are independently functional. A visitor can browse categorized articles with correctly-rendering featured image/author/date/category (entirely via native WP blocks), and see manually-curated related properties where an author set them.
- 🧪 Tests: 4 new PHPUnit cases covering the related-properties shortcode's defensive filtering (the one piece of genuinely custom logic this phase added). Not executed — same no-runtime constraint as every phase.
- 📁 Files: 1 new meta-registration file, 1 extended admin file (meta box + save handler), 1 new theme render file (the related-properties shortcode), 2 new block templates (both almost entirely native-block markup), 1 extended seed.php (insight-article seeding, editor-authored, one guaranteed related-properties example), 1 extended reset.php (added `post` to the demo-cleanup post types — a gap caught before shipping, not after), 1 new test file, `tasks.md` updated with 3 completed checkboxes (T093 annotated with the template-hierarchy substitution).
- 🔁 Next prompts: Phase 12 (Polish & Cross-Cutting Concerns, T096+): structured data/Open Graph metadata (FR-053), breadcrumbs/URL-structure QA (FR-052), and whatever remaining cross-cutting tasks the tasks.md Polish phase lists — the last phase before the full platform is considered done.
- 🧠 Reflection: This was the first phase where "smallest viable diff" pointed toward *using less custom code*, not more — leaning on native WP core blocks instead of building another shortcode-hosting wrapper, because the underlying data (native `post` + native taxonomy) genuinely didn't need one. Worth remembering as a check before reflexively reaching for the shortcode pattern that's been correct for every property-related template so far: the pattern was never "always use a shortcode," it was "use a shortcode when the data is custom" — and this phase's data mostly wasn't.

## Evaluation notes (flywheel)

- Failure modes observed: None new in the shipped code; the template-hierarchy mismatch (`archive-` vs `taxonomy-` prefix) was caught before writing the file rather than after, by checking WordPress's actual block-template matching rules against the task's literal filename instead of transcribing it verbatim — the same discipline that caught T077's mismatch two phases ago.
- Graders run and results (PASS/FAIL): Brace/paren balance check — PASS (all 5 modified/new PHP files). Block-template PHP-leak grep — PASS (both new .html files are pure block markup, none expected or found). Manual cross-check of `reset.php`'s `$post_types` array against every post type `seed.php` now creates — confirmed `post` was the one gap, now closed.
- Prompt variant (if applicable): n/a
- Next experiment (smallest change to try): Before Phase 12 (Polish) touches structured data/Open Graph work, do one pass checking whether the Insights templates built this phase need any schema.org `Article` markup considered alongside T096's `Property`/`Organization` schema work, so Phase 12 doesn't have to circle back to files this phase just finished.
