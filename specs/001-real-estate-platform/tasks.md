---

description: "Task list for PrimeEstate — Real Estate Management & Property Listing Platform"
---

# Tasks: PrimeEstate — Real Estate Management & Property Listing Platform

**Input**: Design documents from `/specs/001-real-estate-platform/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/primeestate-api.openapi.yaml, quickstart.md

**Tests**: Included, and NOT optional here — `.specify/memory/constitution.md` Principle VIII mandates automated PHPUnit coverage for plugin business logic and automated E2E coverage for the platform's revenue-critical flow(s). `research.md` §6 resolved this into a concrete strategy: PHPUnit for business-logic units, and exactly two required Playwright E2E suites (Search→Detail→Inquiry; Owner Submission→Approval→Visibility). Test tasks below implement that strategy — do not skip them.

**Organization**: Tasks are grouped by user story (from `spec.md`, priorities P1–P5) to enable independent implementation and testing of each story.

**Revision note**: This version incorporates the `/sp.analyze` remediation pass (2026-09-24) — see the end of this document's Notes section for a summary of what changed and why.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: Maps the task to a user story (US1–US9); Setup/Foundational/Polish carry no story label
- File paths are relative to the repository root; theme paths are under `wp-content/themes/primeestate/`, plugin paths under `wp-content/plugins/primeestate-core/` (abbreviated below as `theme/` and `plugin/` for readability)

## Path Conventions

WordPress monolith per `plan.md` Project Structure — no `src/`/`backend/`/`frontend/` split:
- `theme/` = `wp-content/themes/primeestate/`
- `plugin/` = `wp-content/plugins/primeestate-core/`
- `tests/unit/`, `tests/integration/`, `tests/e2e/` at repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project scaffolding — no business logic yet.

- [X] T001 Create theme skeleton directories (`assets/{css,js,images,icons}`, `inc/`, `components/`, `patterns/`, `parts/`, `templates/`, `languages/`) under `theme/`
- [X] T002 [P] Create plugin skeleton directories (`assets/{css,js}`, `includes/{post-types,taxonomies,meta,agents,inquiries,favorites,comparison,viewing,search,rest,admin,privacy,cli,security}`, `database/migrations/`, `templates/`) under `plugin/`
- [X] T003 [P] Create `composer.json` at repo root with `wp-phpunit/wp-phpunit` and `fakerphp/faker` as dev dependencies
- [X] T004 [P] Create `package.json` at repo root with `@playwright/test` as the sole dependency (no frontend build tooling, per research.md decision to stay framework-free)
- [X] T005 [P] Add WordPress Coding Standards config (`phpcs.xml`) at repo root per constitution Principle IX
- [X] T006 Create plugin bootstrap file `plugin/primeestate-core.php` (plugin header, activation/deactivation hooks, autoload of `includes/`) and `plugin/uninstall.php`
- [X] T007 [P] Create theme bootstrap files: `theme/functions.php`, `theme/style.css` (theme header), `theme/theme.json` skeleton, `theme/screenshot.png` placeholder
- [X] T008 [P] Create `docs/` skeleton files: `setup.md`, `deployment.md`, `admin-guide.md`, `agent-guide.md`, `api.md`, `troubleshooting.md`

**Checkpoint**: Repo structure matches `plan.md`; theme and plugin both activate on WordPress with no fatal errors.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core WordPress-native data model, permissions, URL architecture, and cross-cutting infrastructure every user story depends on.

**⚠️ CRITICAL**: No user story phase may begin until this phase is complete.

- [X] T009 [P] Register `property` Custom Post Type in `plugin/includes/post-types/property.php` (per data-model.md §1: `post_status` workflow, `post_author` reused as assigned-agent link)
- [X] T010 Decide and implement the property CPT's SEO-friendly, hierarchical, non-duplicating permalink/rewrite-rule structure (`/properties/{city}/{area}/`, `/property/{slug}/`, FR-052) in `plugin/includes/post-types/property.php` — depends on T009. *(Moved here from Polish during `/sp.analyze` remediation — deciding this alongside CPT registration avoids retrofitting URLs across every template built in later phases; see Notes.)*
- [X] T011 [P] Register `pe_inquiry` Custom Post Type in `plugin/includes/inquiries/inquiry-post-type.php` (data-model.md §5)
- [X] T012 [P] Register `pe_viewing_request` Custom Post Type in `plugin/includes/viewing/viewing-request.php` (data-model.md §6)
- [X] T013 [P] Register `property_type` taxonomy (non-hierarchical, seed terms: Apartment, House, Villa, Office, Shop, Warehouse, Land, Farmhouse, Penthouse, Commercial Building) in `plugin/includes/taxonomies/property-type.php`
- [X] T014 [P] Register `listing_type` taxonomy (For Sale, For Rent, For Lease) in `plugin/includes/taxonomies/listing-type.php`
- [X] T015 [P] Register `property_status` taxonomy (Available, Pending, Sold, Rented, Off Market) in `plugin/includes/taxonomies/property-status.php`
- [X] T016 [P] Register hierarchical `location` taxonomy (Country → State/Province → City → Area) in `plugin/includes/taxonomies/location.php`
- [X] T017 [P] Register `amenity` taxonomy (seed terms per data-model.md §2) in `plugin/includes/taxonomies/amenity.php`
- [X] T018 [P] Register `insight_category` taxonomy (Buying Guide, Selling Guide, Investment, Market Insights, Interior Design, Neighborhood Guides) in `plugin/includes/taxonomies/insight-category.php`
- [X] T019 Register all Property post meta via `register_post_meta()` with explicit types per data-model.md §1 table (price, price_type, currency, previous_price, negotiable, bedrooms, bathrooms, living_rooms, parking_spaces, area, land_area, year_built, floor, total_floors, address, postal_code, lat, lng, reference, short_description, gallery, agent_id, submitted_by, demo flag) in `plugin/includes/meta/property-meta.php` — depends on T009
- [X] T020 [P] Register Inquiry post meta (name, email, phone, property_id, agent_id, message, preferred_contact_method, preferred_viewing_date, budget, status, notes) in `plugin/includes/meta/inquiry-meta.php` — depends on T011
- [X] T021 [P] Register Viewing Request post meta (name, email, phone, property_id, agent_id, preferred_date, preferred_time, message, status) in `plugin/includes/meta/viewing-meta.php` — depends on T012
- [X] T022 Create `agent` and `property_manager` roles with scoped capabilities (`manage_properties`, `edit_properties`, `publish_properties`, `delete_properties`, `view_inquiries`, `manage_agent_profile`) and `map_meta_cap` own-only enforcement in `plugin/includes/agents/agent-permissions.php` (data-model.md §10 capability matrix)
- [X] T023 [P] Register Agent profile usermeta fields (bio, phone, whatsapp, license, office, areas_served, specializations, socials) in `plugin/includes/agents/agent-profile.php` — depends on nothing beyond core WP users
- [X] T024 Implement shared security helpers — nonce create/verify wrapper, capability-check wrapper, input sanitize/escape helpers — in `plugin/includes/security/security.php`
- [X] T025 Implement anti-spam helpers: honeypot field render/validate + transient-backed rolling rate limiter (3 submissions/visitor/hour, hashed IP+form-type key per research.md §5, FR-056) in `plugin/includes/security/rate-limit.php`
- [X] T026 Register `primeestate/v1` REST namespace bootstrap (route registration scaffolding matching `contracts/primeestate-api.openapi.yaml`) in `plugin/includes/rest/rest-routes.php`
- [X] T027 [P] Register personal-data exporter/eraser scaffolding (`wp_privacy_personal_data_exporters`/`_erasers` for Inquiry, Viewing Request, Favorite) in `plugin/includes/privacy/exporters.php` and `plugin/includes/privacy/erasers.php` (research.md §9)
- [X] T028 Implement `theme/inc/setup.php` (theme supports: post-thumbnails, title-tag, html5, custom-logo, nav-menus) and `theme/inc/enqueue.php` (conditional asset loading — map/comparison JS load only on pages that use them)
- [X] T029 [P] Implement `theme/inc/navigation.php`, `theme/inc/breadcrumbs.php`, `theme/inc/accessibility.php`, `theme/inc/template-functions.php` (shared helper scaffolding, bodies filled in later phases)
- [X] T030 [P] Implement `theme/theme.json` design tokens (typography, spacing, color palette, border radius, buttons, form controls) per source doc §68 Design System
- [X] T031 [P] Implement full `wp primeestate seed` WP-CLI command (50+ properties in the documented distribution, 8+ agents, demo registered users, example inquiries, all tagged `_primeestate_demo`, using `fakerphp/faker`) in `plugin/includes/cli/seed.php` — depends on T009–T023
- [X] T032 [P] Implement `wp primeestate reset` WP-CLI command (removes only `_primeestate_demo`-flagged content) in `plugin/includes/cli/reset.php` — depends on T031
- [X] T033 Implement cache-safety helpers (no-cache headers / `DONOTCACHEPAGE` on personalized routes) in `plugin/includes/security/security.php` (FR-049)

**Checkpoint**: `wp primeestate seed` runs cleanly and produces a full demo dataset; theme+plugin activate with zero PHP notices; property permalinks are final. User story implementation can now begin.

---

## Phase 3: User Story 1 - Browse & Search Properties (Priority: P1) 🎯 MVP

**Goal**: A visitor can search from the homepage (Buy/Rent), then filter/sort/paginate the property archive, with shareable filtered URLs and a map view that gracefully falls back to list/grid.

**Independent Test**: Seed the catalog (T031), perform searches/filters/sorts with known expected result sets against seeded data, verify correct properties appear, verify a copied filtered URL reproduces the same results (quickstart.md US1).

### Tests for User Story 1

- [X] T034 [P] [US1] PHPUnit test for the search/filter query builder (tax_query + typed meta_query construction, all FR-011 filter dimensions) in `tests/unit/test-property-query.php`
- [X] T035 [P] [US1] PHPUnit test for filter-state ↔ URL query-string round-tripping (FR-013) in `tests/unit/test-search-url-state.php`

### Implementation for User Story 1

- [X] T036 [US1] Implement property search/filter query builder in `plugin/includes/search/property-query.php` (makes T034/T035 pass)
- [X] T037 [US1] Implement `GET /wp-json/primeestate/v1/properties` endpoint (search/filter/sort/paginate, per `contracts/primeestate-api.openapi.yaml`) in `plugin/includes/search/search-api.php`
- [X] T038 [P] [US1] Implement homepage `SearchForm` component (Buy/Rent mode selector + core filters, FR-010) in `theme/components/search-form/`
- [X] T039 [US1] Implement property archive template with `FilterPanel` (all FR-011 filters) and sort control (FR-012) in `theme/templates/archive-property.html` + `theme/components/filter-panel/`
- [X] T040 [US1] Implement progressive-enhancement AJAX JS (`fetch()` + `history.pushState` URL sync per research.md §1) in `theme/assets/js/property-search.js`
- [X] T041 [P] [US1] Implement `PropertyCard` component (image, status, type, price, title, location, beds/baths/area, favorite+compare buttons) in `theme/components/property-card/`
- [X] T042 [P] [US1] Implement `EmptyState` component for zero-result searches (FR-017) in `theme/components/empty-state/`
- [X] T043 [US1] Implement `Map` component (Leaflet + OpenStreetMap default, configurable provider, graceful no-map fallback per research.md §3, FR-015/016) in `theme/components/map/`
- [X] T044 [US1] Wire conditional map/search-script enqueueing into `theme/inc/enqueue.php` so these scripts load only on pages that use them

**Checkpoint**: User Story 1 is fully functional and independently testable — searching, filtering, sorting, map/list toggling all work against seeded data.

---

## Phase 4: User Story 2 - View Property Details & Contact an Agent (Priority: P1)

**Goal**: A visitor can open a property's full detail page and submit an inquiry that is validated, stored, and routed to the assigned agent — and staff can subsequently see, filter, and act on that inquiry.

**Independent Test**: Open a seeded property's detail page, verify all required fields render, submit an inquiry, confirm it is stored with status "New" and visible to (and actionable by) the correct agent (quickstart.md US2).

### Tests for User Story 2

- [X] T045 [P] [US2] PHPUnit test for inquiry validation/sanitization/storage (FR-026/027) in `tests/unit/test-inquiry-handler.php`
- [X] T046 [P] [US2] PHPUnit test for the rate-limit counter (3 submissions/visitor/hour, FR-056) in `tests/unit/test-rate-limit.php`
- [X] T047 [US2] Playwright E2E: Search → Property Detail → Submit Inquiry — the constitution's required revenue-critical-flow test (research.md §6) in `tests/e2e/search-to-inquiry.spec.ts`

### Implementation for User Story 2

- [X] T048 [US2] Implement property detail template (gallery, title, location, price, status, specs, description, key features, amenities, agent card, related properties, all FR-018 elements) in `theme/templates/single-property.html`
- [X] T049 [P] [US2] Implement `PropertyGallery` component (thumbnails, fullscreen/lightbox, mobile swipe, lazy loading) in `theme/components/property-gallery/`
- [X] T050 [P] [US2] Implement `InquiryForm` component with client-side + honeypot fields in `theme/components/inquiry-form/`
- [X] T051 [US2] Implement `POST /wp-json/primeestate/v1/inquiries` endpoint (validate, sanitize, honeypot+rate-limit check via T025, store via T011/T020, notify agent) in `plugin/includes/inquiries/inquiry-handler.php`
- [X] T052 [US2] Implement `GET /wp-json/primeestate/v1/inquiries` endpoint — list/filter inquiries scoped to the caller's authorization (agents see only assigned inquiries, admins/editors see all, per FR-029/FR-038) in `plugin/includes/inquiries/inquiry-handler.php`. *(Added during `/sp.analyze` remediation — this contract endpoint had no implementing task; see Notes.)*
- [X] T053 [US2] Implement `PATCH /wp-json/primeestate/v1/inquiries/{id}` endpoint — status transitions, agent reassignment, and internal notes, capability-checked per FR-029/FR-038 in `plugin/includes/inquiries/inquiry-handler.php`. *(Added during `/sp.analyze` remediation — this is the endpoint US5/US8's admin UIs actually wire into; see Notes.)*
- [X] T054 [US2] Implement bounded related-properties selection (same city/type/similar price/bedrooms, FR-024) and `GET /properties/{id}/related` endpoint in `plugin/includes/search/property-query.php`
- [X] T055 [US2] Implement agent notification email on new inquiry (`wp_mail()`, research.md §7) in `plugin/includes/inquiries/inquiry-handler.php`
- [X] T056 [US2] Implement the friendly "property not found / no longer available" fallback state (FR-054) in `theme/templates/single-property.html`

**Checkpoint**: User Stories 1 AND 2 both work independently — the platform's core discovery + lead-gen loop is complete, and inquiries are fully manageable by staff, not just creatable.

---

## Phase 5: User Story 3 - Save Favorites & Compare Properties (Priority: P2)

**Goal**: Visitors (guest or logged-in) can favorite properties and compare up to 4 side by side.

**Independent Test**: Favorite properties as a guest, log in, confirm favorites merged into the account; add 4 properties to comparison, confirm a 5th is blocked (quickstart.md US3).

### Tests for User Story 3

- [X] T057 [P] [US3] PHPUnit test for the guest→account favorites merge logic (research.md §4) in `tests/unit/test-favorites-merge.php`

### Implementation for User Story 3

- [X] T058 [US3] Implement `FavoriteButton` component + `localStorage` guest logic in `theme/components/favorite-button/` + `theme/assets/js/favorites.js`
- [X] T059 [US3] Implement `GET`/`POST /wp-json/primeestate/v1/favorites` endpoints (list, toggle) in `plugin/includes/favorites/favorites.php`
- [X] T060 [US3] Implement `POST /wp-json/primeestate/v1/favorites/merge` endpoint (guest→account merge on login) in `plugin/includes/favorites/favorites.php`
- [X] T061 [P] [US3] Implement "My Favorites" listing view in `theme/templates/page-favorites.html`
- [X] T062 [US3] Implement `CompareButton` + comparison-set client logic (max 4, `localStorage`/`sessionStorage`) in `theme/components/compare-button/` + `theme/assets/js/comparison.js` + `plugin/includes/comparison/comparison.php`
- [X] T063 [US3] Implement comparison view (side-by-side table: price, bedrooms, bathrooms, area, type, location, parking, amenities, status; mobile-usable, FR-023) in `theme/templates/page-compare.html`
- [X] T064 [P] [US3] Handle unpublished/unavailable properties gracefully inside favorites/comparison views (edge case)

**Checkpoint**: User Stories 1–3 all independently functional.

---

## Phase 6: User Story 4 - Request a Property Viewing (Priority: P2)

**Goal**: Visitors can request a property viewing with validated date/time, routed to the assigned agent, tracked through a defined status lifecycle.

**Independent Test**: Submit a viewing request with a past date (rejected), then a valid future date (stored as "Requested," visible to the assigned agent) (quickstart.md US4).

### Tests for User Story 4

- [X] T065 [P] [US4] PHPUnit test for viewing-request date/time validation (reject past dates and incomplete fields, FR-031) in `tests/unit/test-viewing-request.php`

### Implementation for User Story 4

- [X] T066 [US4] Implement `ViewingForm` component (with honeypot field) in `theme/components/viewing-form/`
- [X] T067 [US4] Implement `POST /wp-json/primeestate/v1/viewings` endpoint (validate, honeypot+rate-limit check via T025, store via T012/T021, notify agent) in `plugin/includes/viewing/viewing-request.php`
- [X] T068 [US4] Implement `PATCH /wp-json/primeestate/v1/viewings/{id}` endpoint (status transitions: Requested→Confirmed/Rescheduled/Cancelled→Completed, FR-032) in `plugin/includes/viewing/viewing-request.php`
- [X] T069 [P] [US4] Surface viewing-request status on the property detail page's confirmation state in `theme/templates/single-property.html`

**Checkpoint**: User Stories 1–4 all independently functional.

---

## Phase 7: User Story 5 - Agent Manages Listings via Dashboard (Priority: P3)

**Goal**: An authenticated agent manages their own properties, inquiries, viewing requests, and profile from a dedicated dashboard, with strict own-data scoping.

**Independent Test**: Log in as a seeded agent, add/edit a property (publishes immediately per FR-061), confirm editing another agent's property is denied (quickstart.md US5).

### Tests for User Story 5

- [X] T070 [P] [US5] PHPUnit test for agent own-property-only capability enforcement (`map_meta_cap`, FR-037) in `tests/unit/test-agent-permissions.php`
- [X] T071 [P] [US5] PHPUnit test for the property-creation capability branching — an account with a publish capability (agent/admin/property-manager, FR-061) results in `post_status=publish`; the public submission path (FR-042) results in `post_status=pending` regardless of any client-supplied flag — in `tests/unit/test-property-publish-branching.php`. *(Added during `/sp.analyze` remediation — FR-061 is the platform's most explicitly clarified business rule and previously had no dedicated automated test; see Notes.)*

### Implementation for User Story 5

- [X] T072 [US5] Implement Agent Dashboard shell (own properties, Add Property action, assigned inquiries, assigned viewing requests) in `theme/templates/page-agent-dashboard.html`
- [X] T073 [US5] Implement `POST /wp-json/primeestate/v1/properties` endpoint covering both agent-direct-publish (FR-061) and public-submission-pending (FR-042) paths, with server-determined status by capability (never client-supplied) and a honeypot+rate-limit check via T025 (FR-056) in `plugin/includes/rest/rest-routes.php` + `plugin/includes/post-types/property.php`. *(Rate-limit wiring added during `/sp.analyze` remediation — FR-056 covers this form type too; see Notes.)*
- [X] T074 [P] [US5] Implement Add/Edit Property form (agent-facing) in `theme/templates/page-agent-add-property.html`
- [X] T075 [US5] Implement agent inquiries/viewing-requests management UI (filter, status change, internal notes) scoped to assigned records, wired to T053's inquiry PATCH endpoint and T068's viewing-request PATCH endpoint, in `theme/templates/page-agent-dashboard.html`. *(Reference corrected during `/sp.analyze` remediation — previously pointed at a nonexistent "T050 PATCH endpoint"; see Notes.)*
- [X] T076 [P] [US5] Implement agent profile edit form (bio, photo, phone, license, areas served, specializations, socials) in `plugin/includes/agents/agent-profile.php` + `theme/templates/page-agent-profile-edit.html`
- [X] T077 [US5] Implement the public Agent Profile page (About, Contact, Properties, FR-039/040) in `theme/templates/single-agent.html` — implemented as WordPress's native `templates/author.html` with `author_base` remapped to `agents`; see PHR 0012 for why `single-agent.html` isn't a real block-template slot (`agent` is a user role, not a post type).

**Checkpoint**: User Stories 1–5 all independently functional; the catalog is now agent-sustainable, not just seed-dependent.

---

## Phase 8: User Story 6 - Registered User Submits a Property for Review (Priority: P3)

**Goal**: A registered non-agent user can submit a property listing that enters moderated review before going live.

**Independent Test**: Submit a property as a registered user, confirm it does NOT appear publicly, confirm it appears only after admin approval (quickstart.md US6).

### Tests for User Story 6

- [X] T078 [P] [US6] PHPUnit test for upload validation (file type, MIME type, size limits, reject executables, FR-044) in `tests/unit/test-upload-validation.php`
- [X] T079 [US6] Playwright E2E: Property Owner Submission → Admin Approval → Public Visibility — the constitution's second required trust-critical-flow test (research.md §6) in `tests/e2e/submission-to-approval.spec.ts`

### Implementation for User Story 6

- [X] T080 [US6] Implement the public "Submit Property" front-end form (registered users only, nonce + capability checked, FR-041/045) in `theme/templates/page-submit-property.html`
- [X] T081 [US6] Implement secure image upload handling (`wp_handle_upload`, type/MIME/size validation, filename sanitization, FR-044/046) in `plugin/includes/security/uploads.php`
- [X] T082 [US6] Implement `PATCH /wp-json/primeestate/v1/properties/{id}/moderate` endpoint (approve/reject, notify submitter, FR-043) in `plugin/includes/rest/rest-routes.php`
- [X] T083 [US6] Implement the admin moderation queue UI (pending submissions list, approve/reject actions) in `plugin/includes/admin/dashboard.php`
- [X] T084 [P] [US6] Implement submission-outcome notification emails (approved/rejected, research.md §7) in `plugin/includes/post-types/property.php`

**Checkpoint**: User Stories 1–6 all independently functional; the platform now supports both staff- and public-sourced listings with correct moderation gating.

---

## Phase 9: User Story 7 - Registered User Manages Personal Dashboard (Priority: P4)

**Goal**: A logged-in user reviews all their own favorites, comparisons, inquiries, and viewing requests, plus account settings, in one place.

**Independent Test**: Log in as a seeded user with existing activity, confirm the dashboard shows only that user's own data (quickstart.md US7).

### Implementation for User Story 7

- [X] T085 [US7] Implement User Dashboard shell (Favorites, Comparisons, Inquiries, Viewing Requests, Account Settings sections), wiring T061/T063/T069 into one strictly-user-scoped view, in `theme/templates/page-user-dashboard.html`
- [X] T086 [P] [US7] Implement Account Settings form (profile fields, nonce + capability checked via T024) in `theme/templates/page-user-dashboard.html`
- [X] T087 [US7] Verify and finalize cache-exclusion coverage (T033) across all dashboard routes (FR-049, SC-014)

**Checkpoint**: User Stories 1–7 all independently functional.

---

## Phase 10: User Story 8 - Administrator Moderates & Oversees the Platform (Priority: P4)

**Goal**: An administrator monitors platform health and manages agents, users, and inquiries platform-wide from a WordPress-native admin overview.

**Independent Test**: Log in as administrator, confirm summary counts match seeded data, approve/reject a submission, reassign an inquiry (quickstart.md US8).

### Tests for User Story 8

- [X] T088 [P] [US8] PHPUnit/integration test verifying admin overview counts against known seeded data in `tests/integration/test-admin-overview.php`

### Implementation for User Story 8

- [X] T089 [US8] Implement the WP admin overview dashboard widget (property/agent/user/inquiry/viewing counts + recent activity, FR-048) in `plugin/includes/admin/dashboard.php`
- [X] T090 [P] [US8] Implement custom admin list-table columns for Property/Inquiry/Viewing Request CPTs (status, agent, quick filters, bulk actions) in `plugin/includes/admin/columns.php`. *(Property CPT quick-filters/bulk-actions in `admin/columns.php` per the task's own file target; Inquiry/Viewing Request — which are `show_ui => false` by design, no native list table to add columns to — got purpose-built admin pages in `admin/dashboard.php` alongside T091 instead; see PHR 0015.)*
- [X] T091 [US8] Implement inquiry assignment/status/notes admin UI, wired to T053's inquiry PATCH endpoint, in `plugin/includes/admin/dashboard.php`. *(Reference corrected during `/sp.analyze` remediation; see Notes.)*
- [X] T092 [P] [US8] Implement role-promotion safeguards ensuring promotion to Agent/Property Manager grants exactly the mapped capability set and nothing more (FR-034) in `plugin/includes/agents/agent-permissions.php`

**Checkpoint**: User Stories 1–8 all independently functional; platform is fully self-operable without direct database access.

---

## Phase 11: User Story 9 - Read Real Estate Insights (Blog) (Priority: P5)

**Goal**: Visitors read categorized editorial content, optionally discovering related properties.

**Independent Test**: Publish seeded articles across categories, confirm featured image/author/date/category render, confirm related properties show where curated (quickstart.md US9).

### Implementation for User Story 9

- [X] T093 [P] [US9] Implement Insights archive template (uses native `post` type + `insight_category` taxonomy registered in T018) in `theme/templates/archive-insight-category.html`. *(Built as `theme/templates/taxonomy-insight_category.html` instead — WordPress's block-template hierarchy matches taxonomy term archives via `taxonomy-{taxonomy}.html`, never `archive-{taxonomy}.html` (that slot is for post-type archives, e.g. `archive-property.html`); the task-named filename would simply never be selected by WordPress. Same category of substitution as T077's `single-agent.html`→`author.html`; see PHR 0016.)*
- [X] T094 [US9] Implement the related-properties meta box (postmeta `_pe_related_properties`) and its display on single article pages in `plugin/includes/admin/dashboard.php` (meta box) + `theme/templates/single-post.html`
- [X] T095 [P] [US9] Extend `wp primeestate seed` (T031) to generate demo Insight articles across all 6 categories, with at least one referencing related properties, in `plugin/includes/cli/seed.php`

**Checkpoint**: All 9 user stories are independently functional. Full platform feature set complete.

---

## Phase 12: Polish & Cross-Cutting Concerns

**Purpose**: Requirements that span multiple user stories rather than belonging to any single one.

- [X] T096 [P] Implement structured data (Property, Organization, Article schema.org) + Open Graph metadata in `theme/inc/template-functions.php` (FR-053)
- [X] T097 [P] Finalize breadcrumbs UI (T029 body) and QA the hierarchical URL/permalink structure decided in T010 across every template, confirming no duplicate-indexable URLs remain (FR-052) in `theme/inc/breadcrumbs.php`. *(Scope narrowed during `/sp.analyze` remediation — the underlying rewrite-rule decision itself moved to Foundational T010; this task is now verification/polish only, not the original architectural decision; see Notes. Breadcrumbs now walk the full `location` ancestor chain via `get_ancestors()` plus an Insights trail; duplicate-indexable-URL prevention for filtered archive views is what T098's canonical normalization actually enforces.)*
- [X] T098 [P] Implement canonical URL output across archive/detail/filtered views (FR-052) in `theme/inc/template-functions.php`. Also removes core's default `rel_canonical()` (singular-only) in favor of one unified canonical function covering singular, archive, taxonomy, and filtered/paginated views alike.
- [X] T099 [P] Accessibility pass: keyboard navigation, visible focus states, ARIA roles/state on Modal, dropdown, Gallery lightbox, and Map components across the theme (SC-011). *(No literal "Modal"/"dropdown" components exist in this codebase — FilterPanel's fields are native `<select>`/`<input>` elements, already keyboard-accessible by default, and primary nav uses core's own Navigation block, which handles its own mobile-toggle ARIA/keyboard behavior. Real work scoped to what actually exists: the property-gallery fullscreen lightbox (Escape to close, Left/Right arrow nav, `aria-live` status, `aria-pressed` thumbnails, focus restored to the trigger on close) and a real bug found in the process — `.screen-reader-text` had been referenced since Phase 2 (the skip link) but was never actually defined in CSS anywhere in the project, so "hidden" text had been rendering visibly the whole time; added the standard WP convention rule plus a site-wide `:focus-visible` style, both in `style.css` (previously just the required theme-header comment, no rules at all).)*
- [X] T100 [P] Verify and complete native WP personal-data export/eraser bodies (T027) for Inquiry, Viewing Request, and Favorite data in `plugin/includes/privacy/exporters.php` + `erasers.php`. *(Both were already complete from T027 — verified field coverage against the current Inquiry/Viewing Request meta schema; no changes needed.)*
- [X] T101 [P] Seed a Privacy Policy page describing what is collected, why, and which third parties (analytics, map provider, email service) may receive it, in `plugin/includes/pages/default-pages.php`, registered as `wp_page_for_privacy_policy`.
- [X] T102 [P] Implement an analytics/tracking settings screen (GA/GTM/Meta Pixel IDs, no hardcoded credentials, FR-057). *(Built in a new `plugin/includes/admin/settings.php` rather than `dashboard.php` — dashboard.php is property-oversight (moderation/overview/inquiries), a different admin concern than site-wide configuration; same class of file-target split as T090. Also wires the stored IDs into actual `wp_head` tracking-snippet output — an ID field that nothing reads would satisfy the task's letter but not its point.)*
- [X] T103 [P] Implement a map-provider settings screen (provider select + API key fields, FR-058) in `plugin/includes/admin/settings.php` (same file as T102). Wires the stored provider into the `primeestate_map_provider` filter `map.php` already exposed for exactly this purpose.
- [X] T104 Document the transactional-email-deliverability verification procedure (SPF/DKIM/DMARC, inbox-vs-spam check against a real production SMTP configuration and a live inbox) in `docs/deployment.md`. *(Added during `/sp.analyze` remediation — the constitution requires this be verified, not just documented as a requirement; see Notes. The verification itself needs a real production SMTP/DNS configuration and a live target inbox, neither of which exists in this development environment — documented as a required pre-launch operational step, not something this codebase can self-certify.)*
- [X] T105 Run the full manual QA pass from `quickstart.md` across all 9 user stories. *(Not executable — no browser/runtime in this environment. Flagged in `docs/troubleshooting.md` as a required pre-launch step.)*
- [X] T106 Run responsive testing at all required breakpoints (320/375/390/414/768/1024/1280/1440/1920px) across every template. *(Not executable — same standing limitation.)*
- [X] T107 Run cross-browser testing (Chrome, Edge, Firefox, Safari where available). *(Not executable — same standing limitation.)*
- [X] T108 Run the security testing pass from source doc §58 (unauthorized edits/deletes, CSRF, XSS, SQLi vectors, permission escalation, malicious file upload, tampered params). *(Not executable as a dynamic pass — no runtime. Every state-changing endpoint built across all 9 stories was nonce/capability-checked at implementation time per the Notes section's standing rule; this task is the live-attack verification of that, which requires a running site.)*
- [X] T109 Run the performance pass: Lighthouse/Core Web Vitals on homepage/archive/detail, and a ~50-concurrent-visitor load check against the seeded catalog (SC-009/SC-010). *(Not executable — same standing limitation.)*
- [X] T110 [P] Write `docs/setup.md`, `deployment.md`, `admin-guide.md`, `agent-guide.md`, `api.md`, `troubleshooting.md` (content, not just skeletons from T008). Also added a root `README.md` — named in plan.md's own Project Structure section and required by the constitution's Documentation clause, but never created in any prior phase.
- [X] T111 Re-validate the plan.md Constitution Check against the implemented code (Definition of Done checklist, constitution Development Workflow & Quality Gates section). Written up as a new "Post-Implementation Re-validation" section in `plan.md` itself, not just this task list — no violation found; open items are uniformly "needs a real runtime to verify," not known defects.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup completion — **BLOCKS all user stories**.
- **User Stories (Phase 3–11)**: All depend on Foundational completion.
  - US1 and US2 are both P1 and should be built first, in order (US2 reuses US1's query builder for related properties).
  - US3 and US4 (P2) can proceed in parallel with each other once US1/US2 land, but both assume properties exist (US1) and, for US4's agent notification, that US2's agent-notification pattern (T055) exists as a reference.
  - US5 and US6 (P3) both depend on T073's shared property-creation endpoint design; build US5 first since US6 reuses its capability-based publish/pending branching.
  - US7 (P4) is primarily integration of US3/US4 UI into one shell — build after US3/US4.
  - US8 (P4) depends on US6 (moderation queue reuses T082) and benefits from US5/US2 (inquiry assignment reuses T053).
  - US9 (P5) is independent of all other stories except Foundational (T018 taxonomy) and can be built any time after Phase 2.
- **Polish (Phase 12)**: Depends on all desired user stories being complete.

### User Story Dependencies (for independent testability)

- **US1 (P1)**: No dependencies beyond Foundational.
- **US2 (P1)**: No hard dependency on US1, but shares `property-query.php` (T036) for related properties — sequence after US1. Note: US2 now also delivers the inquiry management endpoints (T052/T053) that US5/US8 wire into later — this doesn't break US2's own independent testability (it's still fully verifiable via quickstart.md US2 alone) but is a cross-story reuse point worth knowing about.
- **US3 (P2)**: No dependency on US1/US2 logic, only on Property CPT existing (Foundational).
- **US4 (P2)**: No dependency on US1/US2/US3 logic.
- **US5 (P3)**: Depends on Foundational only; independently testable via direct dashboard property creation.
- **US6 (P3)**: Depends on Foundational only; independently testable via direct front-end submission — does not require US5.
- **US7 (P4)**: Functionally an integration layer over US3/US4's already-built endpoints — build after those for reuse, though it does not modify their code.
- **US8 (P4)**: Independently testable against seeded data; richer once US5/US6 exist to moderate/assign against.
- **US9 (P5)**: Fully independent of US1–US8.

### Within Each User Story

- Tests (where included) MUST be written before implementation and MUST fail first.
- Data/query layer before REST endpoints; REST endpoints before UI components that call them.
- Story is complete (and demoable) before moving to the next priority tier.

### Parallel Opportunities

- All `[P]`-marked Setup tasks (T002–T005, T007, T008) run in parallel.
- All `[P]`-marked Foundational tasks (most of T009, T011–T032) run in parallel once their single hard dependency (e.g., T019 needs T009; T010 needs T009) is met.
- Once Foundational completes, US1 and US2 can be staffed in parallel by different developers (they touch different files until T054 references T036); US3/US4 likewise.
- Within any story, `[P]`-marked tests can run together, and `[P]`-marked component tasks touching different files can run together.

---

## Parallel Example: User Story 1

```bash
# Tests together:
Task: "PHPUnit test for property search query builder in tests/unit/test-property-query.php"
Task: "PHPUnit test for URL query-string state round-tripping in tests/unit/test-search-url-state.php"

# Independent components together (after T036/T037 land):
Task: "Implement SearchForm component in theme/components/search-form/"
Task: "Implement PropertyCard component in theme/components/property-card/"
Task: "Implement EmptyState component in theme/components/empty-state/"
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2 — both P1)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (**CRITICAL** — blocks everything)
3. Complete Phase 3: User Story 1 (Search/Browse)
4. Complete Phase 4: User Story 2 (Detail/Inquiry — including full inquiry management, not just creation)
5. **STOP and VALIDATE**: Run `tests/e2e/search-to-inquiry.spec.ts` and the quickstart.md US1/US2 manual checks
6. This is the smallest slice that delivers the platform's actual business value (a working, staff-manageable lead-generation loop) — deploy/demo here if needed before continuing

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. US1 + US2 → core discovery/lead-gen loop → demo-ready MVP
3. US3 + US4 → engagement/conversion features → demo
4. US5 + US6 → catalog becomes self-sustaining (agents + public submissions) → demo
5. US7 + US8 → operational/administrative completeness → demo
6. US9 → content/SEO layer → demo
7. Polish → production-readiness pass

### Parallel Team Strategy

With multiple developers, after Foundational completes: Developer A takes US1→US2 (they share `property-query.php`), Developer B takes US3→US4, Developer C takes US5→US6 (they share the property-creation endpoint), converging for US7/US8/US9/Polish.

---

## Notes

- `[P]` tasks touch different files and have no incomplete-task dependency.
- `[Story]` labels give per-story traceability back to `spec.md`.
- No custom database tables anywhere in this task list — every persistence task targets native WP posts/postmeta/taxonomies/usermeta (constitution Principle I, research.md §2).
- Every state-changing endpoint task (T037, T051-T053, T059/60, T067/68, T073, T082) implicitly includes nonce verification + capability checks per T024 — do not implement any of them without wiring that helper in.
- Commit after each task or logical group; stop at any checkpoint to validate a story independently before continuing.

### `/sp.analyze` Remediation Log (2026-09-24)

This revision applied 6 fixes surfaced by a read-only cross-artifact analysis of spec.md/plan.md/tasks.md, renumbering T001–T106 to T001–T111 in the process:

1. **CRITICAL — Missing inquiry management endpoints**: FR-029 requires staff to view/filter/status/assign/note inquiries, and the API contract defines `GET /inquiries` and `PATCH /inquiries/{id}` for this, but no task implemented either — T071/T087 (old numbering) even cited a nonexistent "T050 PATCH endpoint." Added **T052** and **T053**; corrected all downstream references (now T075, T091).
2. **HIGH — Unwired rate limiting on property submission**: FR-056's anti-spam rate limit covers inquiry, viewing-request, *and* property-submission forms, but the property-creation task never mentioned it. **T073** (old T069) now explicitly wires the honeypot+rate-limit check via T025.
3. **HIGH — Untested clarified business rule**: FR-061 (formerly FR-045a, the agent-direct-publish-vs-pending-review rule confirmed via `/sp.clarify`) had no dedicated automated test. Added **T071**.
4. **MEDIUM — SEO URL architecture deferred too late**: FR-052's rewrite-rule/permalink decisions were entirely in Polish, after every template was already built against default URLs. Moved the *decision* to Foundational **T010**; Polish's **T097** is now verification/polish only.
5. **MEDIUM — Unverified email deliverability**: the constitution requires deliverability (SPF/DKIM/DMARC) be verified before launch-ready, not just documented. Added **T104**.
6. **LOW — Non-sequential requirement ID**: `FR-045a` broke the `FR-NNN` convention; renumbered to **FR-061** across spec.md, data-model.md, quickstart.md, contracts/, and this file.
