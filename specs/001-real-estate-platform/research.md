# Phase 0 Research: PrimeEstate Platform

**Feature**: `001-real-estate-platform` | **Date**: 2026-09-24
**Input**: `spec.md` (Clarifications, Assumptions), `Real Estate WordPress Platform.txt`, `.specify/memory/constitution.md`

This document resolves every open technical decision needed before Phase 1 design. Each decision follows the format: Decision / Rationale / Alternatives Considered.

---

## 1. Search & Advanced Filtering Implementation

**Decision**: Server-rendered property archive built on `WP_Query` (`tax_query` for taxonomies, `meta_query` for numeric ranges), progressively enhanced with vanilla JS: filter changes trigger a `fetch()` call to a dedicated internal endpoint that re-renders the same PHP result-list template server-side and returns an HTML fragment, which replaces the results container. The browser URL is updated via the History API (`pushState`) to match the shareable query-string pattern from FR-013 (`?listing=sale&city=karachi&type=apartment&min_price=...`). Loading the same URL directly (no JS, or first page load) renders identical results server-side.

**Rationale**: One template (PHP) drives both the AJAX and non-AJAX path, so there is no risk of the two diverging — a common source of bugs when JSON+client templating duplicates server rendering. It satisfies FR-014 (AJAX update, no full reload) and FR-013 (SEO-friendly, shareable, crawlable URLs) simultaneously, and gives every filtered view a working no-JS fallback (accessibility + edge-case requirement). Registering property meta via `register_post_meta()` with explicit types keeps numeric range filtering (price/bedrooms/area) correct.

**Alternatives considered**:
- *Fully client-side filtering via REST API + JS templating* — rejected: duplicates the result-card template in two languages, harder to keep crawlable without a server-rendered fallback, and edges toward the headless-frontend pattern the constitution disallows by default.
- *Raw `meta_query` with no registered meta types* — rejected: numeric comparisons against unregistered string meta are unreliable (e.g., price "9" > "10" string-sorts wrong); `register_post_meta()` fixes this natively, no custom table needed.

---

## 2. Custom Database Table for Search Performance

**Decision**: No custom database table at this scale. Standard `wp_postmeta`/`wp_term_relationships` queries via `WP_Query`, with registered/typed meta keys, are sufficient for the demo dataset (50+ properties) and the confirmed performance target (~50 concurrent visitors, SC-009).

**Rationale**: The constitution disallows custom tables by default (Principle I) and requires a documented, demonstrated need before introducing one. Nothing at this scale demonstrates that need. Staying entirely within core post/postmeta/taxonomy structures is also a stronger portfolio signal — it proves the platform is fully upgrade-safe and not reliant on bespoke schema.

**Alternatives considered**:
- *A custom `wp_primeestate_properties` denormalized index table* — rejected as premature optimization; documented here as the first thing to revisit if the catalog ever grows into the thousands, per source doc §49's "document schema/indexes/migration/cleanup/backup" requirement for any future custom table.

---

## 3. Map Provider

**Decision**: Leaflet.js + OpenStreetMap tiles as the zero-cost, no-API-key default. The map component reads a provider setting (`none` / `osm` / `google` / `mapbox`) from an admin-configurable option; when a paid provider is selected but no API key is present, or when the map script fails to load, the map section is omitted entirely and the existing grid/list view remains the complete, fully functional discovery interface (FR-016).

**Rationale**: Satisfies FR-058 (configurable, no hardcoded credentials) and the graceful-degradation requirement, while keeping the platform deployable as a working portfolio demo with zero paid accounts required out of the box.

**Alternatives considered**:
- *Google Maps as the only/default provider* — rejected: requires a billing-enabled API key even for light use, which breaks zero-config demo deployability.
- *Mapbox as default* — rejected for the same reason; kept as a documented configurable alternative alongside Google.

---

## 4. Guest Favorites & Comparison Persistence

**Decision**: Guest (non-logged-in) favorites and the comparison selection are stored client-side in `localStorage` under a namespaced key. On successful login, a one-time JS call to a `favorites/merge` endpoint merges any local guest favorites into the user's account-level favorites (stored in `usermeta`), then clears the local key.

**Rationale**: Satisfies FR-020 without forcing guest registration, while giving logged-in users cross-device persistence. Merge-on-login prevents silently discarding a guest's saved-property activity at the exact moment they convert to a registered account — the highest-value moment to preserve it.

**Alternatives considered**:
- *Cookie-only guest storage* — rejected: more size-constrained than `localStorage` for a growing favorites/comparison list.
- *Server-side PHP session for guests* — rejected: sessions are cache-hostile, and the platform otherwise depends on full-page caching working for anonymous visitors (Principle IV / FR-049's caching compatibility requirement); a session cookie on every anonymous request would defeat that.

---

## 5. Anti-Spam & Rate Limiting

**Decision**: Every public form (inquiry, viewing request, property submission) gets a server-checked honeypot field, plus a WordPress transient-backed rolling counter keyed by a hash of (visitor IP + form type) enforcing the confirmed limit of 3 submissions/visitor/rolling hour combined across the three form types (FR-056). An optional CAPTCHA (reCAPTCHA v3 or hCaptcha) is togglable via an admin setting with a configurable site/secret key, enforced only when configured.

**Rationale**: Transients are a core WordPress API — no custom table required. Hashing the IP (rather than storing it raw) minimizes personal-data retention per Principle XI. Honeypot + rate limit alone is unobtrusive to legitimate visitors, matching FR-056's "without unreasonably burdening legitimate users"; CAPTCHA stays available but off by default.

**Alternatives considered**:
- *A dedicated anti-spam plugin (e.g., Akismet for forms)* — kept as an optional, documented dependency (source doc §64 lists a security plugin as optional) but not required, since the hand-rolled honeypot+transient approach is simpler and satisfies Principle IV's "don't install a plugin for what can reasonably be hand-rolled."

---

## 6. Testing Strategy (mapping Constitution Principle VIII to a non-commerce platform)

**Decision**: PHPUnit, via the standard WordPress PHPUnit test scaffolding (`wp-phpunit`, Composer-managed), covers plugin business logic: property search/filter query building, role/capability checks, inquiry and viewing-request status-transition rules, favorites merge logic, and the anti-spam rate-limit counter. Playwright provides two required end-to-end suites:

1. **Search → Property Detail → Submit Inquiry** — this platform's equivalent of the constitution's mandatory "core purchase path" test, since PrimeEstate is a lead-generation platform, not a store; the inquiry submission is the actual conversion event the business depends on.
2. **Property Owner Submission → Admin Approval → Public Visibility** — the platform's other trust-critical, multi-actor flow (FR-042/FR-043), where a moderation bug would mean either leaking unapproved content publicly or silently losing legitimate submissions.

Remaining functional/responsive/cross-browser/SEO surface is covered by the manual QA checklist already defined in the source document (§57–§60).

**Rationale**: Directly satisfies Principle VIII's mandatory automated-test requirement for the revenue-critical flow by mapping its literal "purchase path" language onto this platform's real conversion path, rather than treating the requirement as inapplicable just because there is no cart/checkout.

**Alternatives considered**:
- *Cypress instead of Playwright* — either satisfies the constitution; Playwright is chosen for built-in multi-browser (Chromium/Firefox/WebKit) support, covering the spec's cross-browser requirement (source doc §57) from one tool.

---

## 7. Transactional Email

**Decision**: Inquiry/viewing-request/submission-status notification emails are sent via `wp_mail()` calls in `primeestate-core`, but production deployment MUST route `wp_mail()` through authenticated SMTP or a transactional email provider (documented in `docs/deployment.md`, not hardcoded in plugin code) per the constitution's Production Readiness section. Local development may use a mail-catcher (e.g., Mailhog) or WordPress's default unauthenticated mail for content-only testing.

**Rationale**: Matches the constitution's explicit deliverability requirement (SPF/DKIM/DMARC, authenticated sending) without baking a specific paid vendor's API into the plugin, which would violate the "no hardcoded credentials" rule and lock the portfolio piece to one vendor.

**Alternatives considered**:
- *Hardcoding a specific ESP's API key/integration in `primeestate-core`* — rejected outright on both the "no hardcoded credentials" and "no vendor lock-in for a portfolio demo" grounds.

---

## 8. Demo/Seed Data Tooling

**Decision**: A custom WP-CLI command, `wp primeestate seed`, implemented in `primeestate-core`, uses the `fakerphp/faker` Composer package (dev-only dependency) to generate the FR-060 dataset (50+ properties in the documented distribution, 8+ agents, taxonomies, amenities, demo users, example inquiries). Every generated post/user is tagged with a `_primeestate_demo` meta flag. A companion `wp primeestate reset` command removes only flagged demo content, never touching non-demo data.

**Rationale**: Matches the source document's explicit "Preferred: WP-CLI" guidance (§56) and the constitution's hand-roll-before-plugin preference. Tagging demo content makes `reset` safe, auditable, and repeatable — directly satisfying "must be possible to reset/recreate demo data without manually entering everything."

**Alternatives considered**:
- *A GUI-based content-import plugin* — rejected: WP-CLI is explicitly preferred by the source spec and is more scriptable/repeatable for a portfolio demo that needs to be reset often.

---

## 9. Personal Data Retention & Erasure (Constitution Principle XI)

**Decision**: Inquiry, viewing-request, and property-submission records are retained indefinitely by default (real-estate lead data has ongoing value to the assigned agent — deleting it on a timer would break FR-047's "My Inquiries" use case), but are fully covered by WordPress's built-in Personal Data Export and Erasure tools: `primeestate-core` registers exporters/erasers (via `wp_privacy_personal_data_exporters` / `_erasers`) for Inquiry, Viewing Request, and Favorite data tied to an email/account. A seeded Privacy Policy page documents what is collected, why, and which third parties (analytics, map provider, email service) may receive it.

**Rationale**: Satisfies Principle XI's mandatory access/export/erasure path using native WordPress extension points (Principle I), without inventing a bespoke retention subsystem.

**Alternatives considered**:
- *A fixed auto-delete retention window (e.g., purge inquiries after 12 months)* — rejected: no support in the source document, and would actively undermine the agent's ability to track ongoing leads.

---

## Summary of Resolved Unknowns

| # | Topic | Resolved To |
|---|-------|-------------|
| 1 | Search/filter architecture | Server-rendered `WP_Query`, AJAX-fragment-enhanced, URL-state-synced |
| 2 | Custom DB table | Not used — core post/postmeta/taxonomy only |
| 3 | Map provider | Leaflet + OpenStreetMap default, configurable Google/Mapbox, graceful no-map fallback |
| 4 | Guest favorites/comparison | `localStorage`, merged into account on login |
| 5 | Anti-spam | Honeypot + transient rate limit (3/visitor/hour), optional CAPTCHA |
| 6 | Testing strategy | PHPUnit (business logic) + Playwright (2 required E2E flows) |
| 7 | Transactional email | `wp_mail()` + documented production SMTP/ESP requirement |
| 8 | Demo/seed data | Custom `wp primeestate seed`/`reset` WP-CLI commands, Faker-based, tagged content |
| 9 | Personal data retention | Indefinite retention + native WP export/erasure integration |

No `NEEDS CLARIFICATION` markers remain in the Technical Context.
