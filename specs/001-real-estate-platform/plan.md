# Implementation Plan: PrimeEstate — Real Estate Management & Property Listing Platform

**Branch**: `001-real-estate-platform` | **Date**: 2026-09-24 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-real-estate-platform/spec.md`

**Note**: This template is filled in by the `/sp.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

PrimeEstate is a custom WordPress real estate listing and management platform (portfolio/production-grade demonstration, per `spec.md` and the user's stated goal of showcasing custom WordPress development to prospective clients). Primary requirement: let visitors search/browse/filter properties, view rich detail pages, contact agents, save favorites, compare listings, and request viewings, while agents manage their own listings/leads via a dashboard, registered users can submit owner listings for moderated approval, and administrators oversee the whole platform — all built on WordPress-native extension points (custom post types, taxonomies, post/user meta, the REST API) with zero WooCommerce dependency, zero custom database tables, and zero headless/JS-framework frontend, per `.specify/memory/constitution.md`. Technical approach (from `research.md`): a custom theme (`primeestate`) for presentation and a custom plugin (`primeestate-core`) for all business logic/data, server-rendered search progressively enhanced with vanilla JS + URL-state sync, Leaflet/OpenStreetMap as the default zero-cost map provider, `localStorage`-based guest favorites merged on login, transient-backed anti-spam rate limiting, and a WP-CLI-driven repeatable demo-seed workflow.

## Technical Context

**Language/Version**: PHP 8.1+ (WordPress plugin/theme code), JavaScript ES6+ (vanilla, no framework), HTML5, modern CSS (theme.json + Sass/PostCSS optional)
**Primary Dependencies**: WordPress (latest stable) core APIs only — Custom Post Types, Taxonomies, Post/User Meta, REST API, Options, Transients, Cron, WP-CLI. Composer (PHP dependency management: `wp-phpunit`, `fakerphp/faker` dev-only). No WooCommerce (not a commerce/checkout product — a lead-generation listings platform). No React/Next.js/headless frontend (constitution default).
**Storage**: WordPress-native only — MySQL/MariaDB via `wp_posts`/`wp_postmeta` (Property CPT, Inquiry CPT, Viewing Request CPT, Article/`post`), `wp_terms`/`wp_term_taxonomy` (property_type, listing_type, property_status, location [hierarchical], amenity, insight_category), `wp_users`/`wp_usermeta` (Agents, Registered Users, favorites, capabilities). No custom database tables (research.md §2). Guest favorites/comparison: browser `localStorage` only.
**Testing**: PHPUnit via `wp-phpunit` scaffolding (plugin business logic: search query building, capability checks, status transitions, favorites merge, rate-limit counter) + Playwright (2 required E2E flows: Search→Detail→Inquiry, and Owner Submission→Admin Approval→Public Visibility) — see research.md §6 for the constitution Principle VIII mapping. Manual QA checklist for remaining functional/responsive/cross-browser/SEO surface.
**Target Platform**: Self-hosted WordPress on standard Linux web hosting (Apache/Nginx + PHP-FPM + MySQL/MariaDB), compatible with page/object caching and a CDN; no serverless/edge runtime requirement.
**Project Type**: WordPress monolith — server-rendered theme + plugin, progressively enhanced with vanilla JS (not a frontend/backend split; not mobile/native).
**Performance Goals**: Filtered search results update in <2s (SC-002); "good" Core Web Vitals on representative mobile network for homepage/archive/detail (SC-010); no perceptible degradation up to ~50 concurrent visitors against a 50+/8+ seeded catalog (SC-009, confirmed via `/sp.clarify`).
**Constraints**: No custom DB tables (constitution Principle I / research.md §2); no WooCommerce; no headless/React frontend; no hardcoded map/analytics/email-provider credentials (FR-057, FR-058); WCAG 2.2 AA (SC-011); anti-spam rate limit fixed at 3 submissions/visitor/rolling hour (FR-056, confirmed via `/sp.clarify`); personalized pages (dashboards, favorites) never cache-served (FR-049, SC-014).
**Scale/Scope**: 9 prioritized user stories, 61 functional requirements, 9 key entities; demo dataset of 50+ properties / 8+ agents (FR-060); single active language (English) at launch, translation-ready; single-platform tenancy — no multi-agency marketplace (Clarifications).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|---|---|---|
| I. WordPress-Native, WooCommerce-Native Architecture | **PASS** | WooCommerce-specific clauses are **N/A by design** — PrimeEstate is a lead-generation listings platform, not a commerce/checkout product; no products, cart, or orders exist in this spec. The WordPress-native clause (no core/plugin-source mods, custom theme + custom plugin split, no custom DB tables by default) fully applies and is honored: presentation lives in `primeestate` theme, all business logic/CPTs/taxonomies/REST/capabilities live in `primeestate-core` plugin (source doc §65–66), no custom tables introduced (research.md §2). |
| II. Security-First Development | **PASS** | FR-045/FR-055/FR-056 plus research.md §5 directly implement sanitize/validate/escape, nonces, capability checks, rate limiting, no hardcoded secrets. No payment card data exists (N/A). AI-safety clauses N/A — no AI feature in this spec's scope. |
| III. World-Class UI/UX & Design Excellence | **PASS** | Matches source doc §7 Design Direction and Brand (§6); enforced in the theme's design-system phase, not this plan. |
| IV. Performance & Scalability | **PASS (scoped)** | SC-002/009/010 set concrete targets sized to the confirmed ~50-concurrent-visitor portfolio-demo scale (not "hundreds to thousands of products" enterprise scale) — architecture (registered/typed meta, transient caching for related-properties/rate-limiting, no N+1 patterns) does not preclude scaling later if the platform's real usage grows. |
| V. Mobile-First, Fully Responsive Design | **PASS** | Spec's required breakpoint set (320–1920px) is identical to the constitution's. |
| VI. Accessibility (WCAG 2.2 AA) | **PASS** | SC-011 sets this as a measurable outcome; map has a mandatory non-map fallback (FR-016) satisfying the constitution's map-accessibility carve-out. |
| VII. SEO-Ready by Architecture | **PASS** | FR-052/053 implement semantic HTML, canonical URLs, breadcrumbs, structured data, hierarchical location URLs; custom SEO handling must remain compatible with Yoast/Rank Math per constitution — documented as an integration constraint, not a conflict. |
| VIII. Specification-Driven Development & Testing Discipline | **PASS (mapped)** | The constitution's literal "core purchase path" E2E requirement is mapped to this platform's actual conversion path (Search→Detail→Inquiry) plus its other trust-critical flow (Owner Submission→Approval), per research.md §6 — not skipped just because there is no cart/checkout. |
| IX. Clean, Modular, Maintainable Code | **PASS** | WPCS, modular `inc/`/`includes/` structure per source doc §10, every dependency documented (research.md decisions 5/6/7/8). |
| X. AI Feature Safety | **N/A** | No AI-assisted feature is in scope for this spec. Revisit this gate if a future feature (e.g., AI-assisted property description generation) is added. |
| XI. Privacy, Data Protection & Compliance | **PASS** | research.md §9: native WP personal-data export/erasure integration for Inquiry/Viewing Request/Favorite data, seeded Privacy Policy page, analytics/tracking gated behind configuration + consent (FR-057), no "just in case" data collection (spec forms collect only what each flow needs). |

**Result**: No violations. Complexity Tracking table below is not applicable (left empty per template instructions).

## Project Structure

### Documentation (this feature)

```text
specs/001-real-estate-platform/
├── plan.md              # This file (/sp.plan command output)
├── research.md          # Phase 0 output (/sp.plan command)
├── data-model.md         # Phase 1 output (/sp.plan command)
├── quickstart.md        # Phase 1 output (/sp.plan command)
├── contracts/
│   └── primeestate-api.openapi.yaml   # Phase 1 output (/sp.plan command)
├── checklists/
│   └── requirements.md
└── tasks.md              # Phase 2 output (/sp.tasks command - NOT created by /sp.plan)
```

### Source Code (repository root)

This is a WordPress monolith (custom theme + custom plugin), not a frontend/backend-split web app — none of the template's generic Option 1/2/3 layouts fit, so the structure below (adapted from the source document's own project-structure mandate, §10) is used instead, as explicitly authorized by that document ("structure may be adjusted... any significant change must be documented" — this is that documentation).

```text
primeestate/                         # repo root (this repository)
├── README.md
├── LICENSE
├── .gitignore
├── composer.json                    # PHP deps: wp-phpunit, fakerphp/faker (dev)
├── package.json                     # Playwright only — no frontend build step required to run the site
│
├── specs/                           # SDD artifacts (this feature: 001-real-estate-platform/)
│
├── wp-content/
│   ├── themes/
│   │   └── primeestate/
│   │       ├── assets/{css,js,images,icons}/
│   │       ├── inc/                 # setup.php, enqueue.php, navigation.php, template-functions.php,
│   │       │                        # breadcrumbs.php, accessibility.php
│   │       ├── components/          # property-card, agent-card, search-form, filter-panel, map, testimonial
│   │       ├── patterns/  parts/  templates/  languages/
│   │       ├── functions.php  style.css  theme.json  screenshot.png
│   │
│   └── plugins/
│       └── primeestate-core/
│           ├── assets/{css,js}/
│           ├── includes/
│           │   ├── post-types/      # property.php, inquiry.php, viewing-request.php
│           │   ├── taxonomies/      # property-type.php, property-status.php, location.php, amenity.php, property-feature.php
│           │   ├── meta/            # property-meta.php (register_post_meta calls)
│           │   ├── agents/          # agent-profile.php, agent-permissions.php (capabilities)
│           │   ├── inquiries/       # inquiry-handler.php, inquiry-post-type.php
│           │   ├── favorites/       # favorites.php (account + merge endpoint)
│           │   ├── comparison/      # comparison.php (client-side support only)
│           │   ├── viewing/         # viewing-request.php
│           │   ├── search/          # property-query.php, search-api.php
│           │   ├── rest/            # rest-routes.php (implements contracts/primeestate-api.openapi.yaml)
│           │   ├── admin/           # dashboard.php, columns.php
│           │   ├── privacy/         # exporters/erasers (research.md §9)
│           │   ├── cli/             # seed.php, reset.php (WP-CLI commands, research.md §8)
│           │   └── security/        # security.php (nonces, rate limiting, honeypot — research.md §5)
│           ├── database/migrations/ # present but expected empty (no custom tables — research.md §2)
│           ├── templates/
│           ├── primeestate-core.php  uninstall.php
│
├── tests/
│   ├── unit/            # PHPUnit — pure logic (query builders, validators)
│   ├── integration/     # PHPUnit + wp-phpunit — CPT/taxonomy/capability/REST behavior
│   └── e2e/             # Playwright — Search→Detail→Inquiry; Submission→Approval→Visibility
│
└── docs/
    ├── setup.md  deployment.md  admin-guide.md  agent-guide.md  api.md  troubleshooting.md
```

**Structure Decision**: Adopt the source document's `wp-content/themes/primeestate` + `wp-content/plugins/primeestate-core` split as-is (§10), with two additions surfaced by this plan: an `includes/privacy/` directory (Principle XI exporters/erasers) and an `includes/cli/` directory (WP-CLI seed/reset commands), neither of which the source document enumerated explicitly but both of which are required by resolved research decisions (§8, §9). `database/migrations/` is kept per the source structure but is expected to remain empty, since research.md §2 concluded no custom tables are justified at this scale.

## Complexity Tracking

*No Constitution Check violations were identified — this table is intentionally empty.*

## Post-Implementation Re-validation (T111, Phase 12)

Re-checked against the actually-implemented code at the end of Phase 12, not just the plan. No principle regressed from its pre-implementation PASS; three (VI, VII, XI) went from "planned to pass" to "implemented and confirmed," since the Polish phase is what actually delivered their remaining pieces.

| Principle | Status | What changed since the original gate |
|---|---|---|
| I. WordPress-Native Architecture | **CONFIRMED** | Held for all 12 phases — no custom DB table was introduced anywhere; theme/plugin boundary never blurred. |
| II. Security-First | **CONFIRMED** | Nonce + capability check present on every state-changing endpoint built across all 9 stories; T102/T103's new settings ship with empty defaults, no hardcoded credential. |
| III. UI/UX Excellence | **OPEN** | No regression, but not independently re-verifiable here — this environment has no browser/runtime to visually QA against source doc §7. Unchanged from plan-time assumption. |
| IV. Performance & Scalability | **OPEN** | Architecture (transients, registered/typed meta, no N+1 patterns) unchanged from plan; the Lighthouse/load-test pass itself (T109) was not executable in this environment. |
| V. Mobile-First Responsive | **OPEN** | Breakpoint set unchanged from plan; the responsive QA pass itself (T106) was not executable here. |
| VI. Accessibility (WCAG 2.2 AA) | **CONFIRMED — strengthened** | T099 found and fixed a real gap (`.screen-reader-text` was referenced since Phase 2 but never actually defined in CSS, so "hidden" skip-link/status text was rendering visibly the whole time), added keyboard/focus-management/ARIA-live to the gallery lightbox, and added a site-wide `:focus-visible` rule. Manual AT/keyboard verification in a real browser is still open. |
| VII. SEO-Ready by Architecture | **CONFIRMED** | FR-052/053 are now actually implemented, not just planned: JSON-LD structured data (RealEstateListing/Article/Organization/BreadcrumbList), Open Graph tags, a normalized canonical URL for filtered/paginated views, and a full `location`-ancestor breadcrumb trail. |
| VIII. SDD & Testing Discipline | **OPEN (authoring confirmed, execution not)** | Every phase wrote PHPUnit coverage as it went (unit + integration); none of it has been executed anywhere in this session — no PHP/MySQL/Node runtime was available to the implementing agent at any point. This is the same standing caveat repeated in every phase's PHR. |
| IX. Clean, Modular, Maintainable Code | **CONFIRMED** | Every intentional deviation from a task's literal file target (T090's admin-page split, T102/103's new `settings.php`, several template-hierarchy filename corrections) is documented inline in `tasks.md` and its phase's PHR — none were silent. |
| X. AI Feature Safety | **N/A** (unchanged) | No AI-assisted feature was added anywhere in this feature's scope. |
| XI. Privacy, Data Protection & Compliance | **CONFIRMED — strengthened** | The Privacy Policy page is now a real, seeded page (T101) naming the actual third parties this codebase can send data to (map tile provider, optional analytics, transactional email), not just a planned placeholder; the export/erasure integration (T100) was verified complete against the actual Inquiry/Viewing Request/Favorite field set. |

**Definition of Done checklist** (constitution § Development Workflow & Quality Gates), re-checked against the finished codebase:

- [x] Implemented via native WordPress extension points (Principle I)
- [x] Security checklist satisfied (Principle II)
- [ ] Responsive behavior verified at the Principle V breakpoints — **not executable in this environment**
- [~] Accessibility spot-checked — code-level keyboard/focus/ARIA work done (T099); real keyboard/AT verification **not executable in this environment**
- [ ] No console errors / no PHP notices in the debug log — **not verifiable without a runtime**
- [x] Loading/empty/error states implemented (fragment-swap `aria-busy`, EmptyState component, property-unavailable fallback, etc.)
- [x] Documentation updated (`docs/*.md` given real content, root `README.md` added — it did not previously exist despite being named in this plan's own Project Structure section)
- [x] Reviewed for scope creep — every substitution/addition beyond a task's literal wording is documented with its rationale, not silently introduced

**Net assessment**: no constitutional violation exists in the implemented code. The open items are uniformly "requires a real runtime/browser to verify," not "known to be broken" — they are pre-launch verification steps (tasks.md T105–T109), not design defects.
