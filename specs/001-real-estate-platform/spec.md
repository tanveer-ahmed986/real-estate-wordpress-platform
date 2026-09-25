# Feature Specification: PrimeEstate — Real Estate Management & Property Listing Platform

**Feature Branch**: `001-real-estate-platform`
**Created**: 2026-09-24
**Status**: Draft
**Input**: User description: "please read Real Estate WordPress Platform text file carefully, then write specs as mentioned in Real Estate WordPress Platform file, carefully read the project structure which also mentioned in this file"

**Source document**: `Real Estate WordPress Platform.txt` (full platform specification and architecture brief for the "PrimeEstate" product)

## Clarifications

### Session 2026-09-24

- Q: PrimeEstate is currently specified as a single platform where individual agents (optionally grouped by an "office" field) list properties under one shared brand/admin — not a marketplace of independently-branded agencies. Should this stay as-is? → A: Single platform, agents only — no Agency entity, no isolated per-agency admin/branding/billing.
- Q: When an Agent creates a property directly through their dashboard (not the public "Submit Property" form), should it publish immediately, or also require admin/editor review? → A: Agents publish directly (no additional review step) when their role grants a publish capability; only public/owner submissions via the "Submit Property" form require moderation.
- Q: The source doc's agent-profile flow mentions a "Reviews" step but never defines who can post a review or how it's moderated. Should agent reviews be in scope for this spec? → A: Exclude agent reviews/ratings from MVP entirely; no public submission or moderation workflow is built.
- Q: FR-056 requires rate limiting on public forms but no numeric threshold is defined. What's a reasonable per-visitor submission limit? → A: 3 submissions per visitor per rolling hour, applied across inquiry, viewing-request, and property-submission forms combined.
- Q: What concurrent-visitor traffic scale should the platform be built and tested to handle? → A: ~50 concurrent visitors, sized for a portfolio/production-grade demonstration rather than high-traffic SaaS load.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse & Search Properties (Priority: P1)

A visitor arrives on the platform wanting to find a property that matches their needs (buying or renting). They use a prominent search interface to specify what they're looking for (buy/rent, location, property type, price range, bedrooms, etc.), then refine results using advanced filters and sorting on the results listing, optionally switching to a map view to see properties geographically.

**Why this priority**: This is the core value proposition of the platform — without effective discovery, no other feature (inquiries, favorites, submissions) has anything to act on. It is the first experience nearly every visitor will have.

**Independent Test**: Can be fully tested by seeding a catalog of properties, performing searches/filters/sorts with known expected result sets, and verifying the correct properties appear — deliverable and demonstrable without any other feature existing.

**Acceptance Scenarios**:

1. **Given** the property catalog contains listings in multiple cities and types, **When** a visitor searches for "Buy" properties in a specific city, **Then** only available for-sale properties in that city are shown.
2. **Given** a visitor is viewing the property archive, **When** they apply filters (property type, price range, bedrooms, bathrooms, area, amenities, status), **Then** the results update to show only matching properties and a result count is displayed.
3. **Given** a visitor has applied filters and sorting, **When** they copy the current page URL and open it in a new session, **Then** the same filtered, sorted result set is reproduced.
4. **Given** no properties match the applied filters, **When** the results render, **Then** a clear empty state is shown with guidance to adjust the search.
5. **Given** a visitor prefers a spatial view, **When** they switch to map view, **Then** property markers appear at correct locations and selecting a marker shows a preview (image, price, title, location, CTA) linking to the full listing.
6. **Given** a visitor cannot or does not want to use the map, **When** they view the archive, **Then** a complete non-map (list/grid) way to browse the same properties is always available.

---

### User Story 2 - View Property Details & Contact an Agent (Priority: P1)

A visitor opens a specific property listing to review full details (gallery, price, specs, description, features, amenities, location, related properties) and, when interested, contacts the responsible agent with a question via an inquiry form.

**Why this priority**: Converting a browsing visitor into a lead is the primary business outcome of the platform. Detail pages and inquiries are equally foundational to search and must exist together for the platform to deliver value.

**Independent Test**: Can be tested by opening a seeded property's detail page, verifying all required information renders, and submitting an inquiry that is confirmed to be stored and visible to the correct agent — independent of favorites, comparison, or submission features.

**Acceptance Scenarios**:

1. **Given** a published property exists, **When** a visitor opens its detail page, **Then** they see the gallery, title, location, price, listing status, bedrooms, bathrooms, area, parking, description, key features, amenities, full property details, location, and the assigned agent's contact card.
2. **Given** a visitor is on a property detail page, **When** they browse the photo gallery, **Then** they can view thumbnails, open a fullscreen/lightbox view, and swipe through images on a mobile device.
3. **Given** a visitor wants more information, **When** they submit the inquiry form (name, email, phone, message, preferred contact method, and the property is auto-associated), **Then** the inquiry is validated, stored with a "New" status, and the assigned agent (and administrator where configured) is notified.
4. **Given** a visitor submits an inquiry with missing or invalid required fields, **When** they submit the form, **Then** they see clear, field-level validation errors and no inquiry record is created.
5. **Given** a property has been removed or is no longer available, **When** a visitor navigates to its detail page, **Then** a friendly "property not found / no longer available" message is shown instead of an error.
6. **Given** a property detail page is open, **When** the page loads related properties, **Then** listings are shown that share signals such as city, property type, similar price, or similar bedroom count — without noticeably slowing the page.

---

### User Story 3 - Save Favorites & Compare Properties (Priority: P2)

A visitor marks properties of interest as favorites while browsing, and selects a subset of them to compare side-by-side on key attributes (price, bedrooms, bathrooms, area, type, location, parking, amenities, status).

**Why this priority**: Favorites and comparison materially increase engagement and conversion for return visits, but the platform still delivers core value (search + inquiry) without them, so they rank below discovery and contact.

**Independent Test**: Can be tested by saving/removing properties as favorites (both logged-in and guest) and building a comparison set, independent of the inquiry or agent-management features.

**Acceptance Scenarios**:

1. **Given** a visitor is browsing properties, **When** they select "Save"/favorite on a property card or detail page, **Then** the property is added to their favorites and the control reflects the saved state immediately.
2. **Given** a logged-in user has saved favorites, **When** they view "My Favorites," **Then** all previously saved properties are listed and can be individually removed.
3. **Given** a guest (not logged in) saves a favorite, **When** they return to the site in the same browser, **Then** their favorites persist without requiring an account.
4. **Given** a visitor selects multiple properties for comparison, **When** they reach the configured maximum (3–4 properties), **Then** further add-to-compare attempts are blocked with a clear message.
5. **Given** a visitor has 2 or more properties selected, **When** they open the comparison view, **Then** a side-by-side table of price, bedrooms, bathrooms, area, type, location, parking, amenities, and status is shown, and it remains usable on a mobile screen width.
6. **Given** a favorited or compared property is later unpublished, **When** the user views their favorites/comparison, **Then** the unavailable property is clearly indicated rather than causing an error.

---

### User Story 4 - Request a Property Viewing (Priority: P2)

A visitor interested in a specific property requests an in-person or virtual viewing by submitting their preferred date, time, and contact details.

**Why this priority**: Viewing requests are a natural extension of the inquiry flow and a key conversion step toward a transaction, but depend on properties and agents already existing, placing it just behind core discovery/contact.

**Independent Test**: Can be tested by submitting a viewing request against a seeded property and verifying it is stored with "Requested" status and visible to the assigned agent, independent of favorites/comparison.

**Acceptance Scenarios**:

1. **Given** a visitor is on a property detail page, **When** they open "Request Viewing" and submit name, email, phone, preferred date, preferred time, and an optional message, **Then** the request is stored with status "Requested" and the assigned agent is notified.
2. **Given** a viewing request is submitted, **When** validation runs, **Then** past dates and missing required fields are rejected with clear messaging.
3. **Given** a viewing request has been submitted, **When** an authorized agent/administrator updates it, **Then** its status can move to Confirmed, Rescheduled, Completed, or Cancelled, and the system never implies a guaranteed confirmation at submission time.
4. **Given** a user has submitted viewing requests, **When** they check their dashboard, **Then** they can see the current status of each request.

---

### User Story 5 - Agent Manages Listings via Dashboard (Priority: P3)

An authenticated agent logs in to a dedicated dashboard to add and edit their own property listings, track listing status, and manage inquiries and viewing requests assigned to them.

**Why this priority**: Without agents actively maintaining listings, the catalog visitors search (P1) eventually goes stale — this is essential to platform sustainability, but the platform can launch and be demonstrated with seeded data before agent self-service is exercised.

**Independent Test**: Can be tested by logging in as a seeded agent account, creating/editing a property, and verifying it appears in search while confirming the agent cannot see or modify another agent's listings or inquiries.

**Acceptance Scenarios**:

1. **Given** an authenticated agent, **When** they open their dashboard, **Then** they see their own properties, an "Add Property" action, their assigned inquiries, and their assigned viewing requests.
2. **Given** an agent adds a new property with all required fields and images, **When** they submit it, **Then** it is created and becomes visible according to the platform's publishing rules for agent-created listings.
3. **Given** an agent attempts to edit or delete a property they do not own, **When** the action is attempted, **Then** it is denied and logged as unauthorized.
4. **Given** an agent views their assigned inquiries or viewing requests, **When** they filter or update status or add internal notes, **Then** changes are saved and visible only to authorized staff.
5. **Given** an agent updates their public profile (bio, photo, phone, email, license number, areas served, specializations, social links), **When** they save changes, **Then** the public agent profile page reflects the update.

---

### User Story 6 - Registered User Submits a Property for Review (Priority: P3)

A registered (non-agent) user who owns a property logs in, fills out the property submission form with details and images, and submits it. The listing enters a pending-review queue until an administrator or authorized editor approves or rejects it.

**Why this priority**: Owner-submitted listings expand the catalog and demonstrate a key differentiator (user-generated content with moderation), but depend on search, detail pages, and basic account/authentication already existing.

**Independent Test**: Can be tested end-to-end by submitting a property as a registered user and confirming it does NOT appear publicly until an administrator approves it, then confirming it appears after approval — independent of agent-dashboard functionality.

**Acceptance Scenarios**:

1. **Given** a registered user is logged in, **When** they open "Submit Property" and complete the required fields (title, description, price, listing type, property type, bedrooms, bathrooms, area, location, address, amenities, images, contact details), **Then** the submission is validated, sanitized, and saved with a pending-review status.
2. **Given** a submission is pending review, **When** any visitor searches or browses the archive, **Then** the pending property does NOT appear in public results.
3. **Given** an administrator or authorized editor reviews a pending submission, **When** they approve it, **Then** it becomes publicly visible and the submitter is notified.
4. **Given** an administrator rejects a submission, **When** the decision is saved, **Then** the property remains unpublished and the submitter is notified.
5. **Given** a user attempts to upload an invalid file (wrong type, executable, oversized), **When** the upload is processed, **Then** it is rejected with a clear error and no file is stored.

---

### User Story 7 - Registered User Manages Personal Dashboard (Priority: P4)

A logged-in visitor (buyer/tenant/investor) uses a personal dashboard to review all their saved favorites, comparison sets, submitted inquiries, and viewing requests, and manage basic account settings, in one place.

**Why this priority**: This consolidates value already delivered by P2–P4 stories into a convenience layer; it improves retention but is not required for the platform's core transactional value to exist.

**Independent Test**: Can be tested by logging in as a seeded registered user with existing favorites/inquiries/viewing requests and confirming the dashboard accurately reflects that user's own data only.

**Acceptance Scenarios**:

1. **Given** a logged-in registered user, **When** they open their dashboard, **Then** they see sections for Favorites, Comparisons, Inquiries, Viewing Requests, and Account Settings.
2. **Given** a user has prior activity, **When** they view each dashboard section, **Then** only data belonging to that user is shown, never another user's data.
3. **Given** a user updates account settings, **When** they save, **Then** the changes persist and are reflected across the site.
4. **Given** a user's dashboard contains personal data, **When** the page is served, **Then** it is never delivered from a shared/public cache.

---

### User Story 8 - Administrator Moderates & Oversees the Platform (Priority: P4)

An administrator uses an overview dashboard to monitor platform health (counts of properties by status, agents, users, new inquiries, viewing requests), review recent activity, moderate pending property submissions, and manage agents, users, and inquiries platform-wide.

**Why this priority**: Administrative oversight is essential for trust and moderation (owner submissions, inquiry routing) but the platform's visitor-facing value can be demonstrated before every administrative convenience is built out.

**Independent Test**: Can be tested by logging in as an administrator, confirming accurate summary counts against known seed data, approving/rejecting a pending submission, and reassigning/updating an inquiry.

**Acceptance Scenarios**:

1. **Given** seeded platform data, **When** an administrator opens the admin overview, **Then** they see accurate counts of total properties, active/sold/rented/pending-approval properties, agents, users, new inquiries, and viewing requests, plus recent properties/inquiries/submissions.
2. **Given** pending property submissions exist, **When** the administrator reviews one, **Then** they can approve or reject it with the outcome immediately reflected platform-wide.
3. **Given** an inquiry needs routing, **When** the administrator assigns it to an agent and updates its status or adds notes, **Then** the change is saved and the correct agent gains visibility.
4. **Given** an administrator manages roles, **When** they promote a user to Agent or Property Manager, **Then** that user gains exactly the capabilities associated with the new role and nothing more.

---

### User Story 9 - Read Real Estate Insights (Blog) (Priority: P5)

A visitor reads editorial content (buying/selling guides, market insights, neighborhood guides) to build trust in the platform, optionally discovering related properties linked from an article.

**Why this priority**: Content marketing supports SEO and trust-building but is not required for the platform's transactional core to function or be demonstrated.

**Independent Test**: Can be tested by publishing seeded articles in defined categories and confirming they render with featured image, author, date, category, and any related properties.

**Acceptance Scenarios**:

1. **Given** published articles exist across categories (Buying Guide, Selling Guide, Investment, Market Insights, Interior Design, Neighborhood Guides), **When** a visitor browses the insights section, **Then** articles are listed with featured image, title, category, author, and date.
2. **Given** an article references specific properties or locations, **When** a visitor reads it, **Then** related properties are shown where relevant.

---

### Edge Cases

- What happens when a search or filter combination matches zero properties? The system shows an empty state with adjustment guidance, not an error.
- How does the system handle a property being unpublished, sold, or rented while it exists in someone's favorites, comparison set, or an open inquiry/viewing request? The record is retained for history but clearly marked unavailable, never silently removed or errored.
- What happens when a guest (not logged in) attempts to favorite a property? It is saved via a local/browser-based mechanism without requiring login, and the system never blocks the action solely due to guest status.
- How does the system handle rapid repeated inquiry or viewing-request submissions from the same visitor (potential spam/abuse)? Submissions are rate-limited and additional anti-spam checks (e.g., honeypot) apply without materially inconveniencing legitimate users.
- What happens when an agent or property owner attempts to edit, publish, or delete a property they do not own or manage? The action is denied and the attempt is not silently ignored — it is surfaced as an authorization failure.
- What happens when a viewing request specifies a date/time that has already passed, or is submitted with missing required fields? The submission is rejected with clear validation messaging before it is stored.
- What happens when the map cannot load (no provider configured, network/API failure, or a paid provider unavailable)? A complete non-map alternative for browsing the same properties remains fully available, and accessibility is not degraded.
- What happens when a user attempts to upload a disallowed file type, oversized image, or a file disguised with a mismatched extension during property submission? The upload is rejected with a clear error, and no file is written to storage.
- What happens when a user attempts to add a fifth property to a comparison limited to 3–4? The action is blocked with a message explaining the limit, and the user can remove an existing item to add a new one.
- What happens when a filtered search URL contains an unrecognized or invalid parameter value (e.g., unknown city, negative price, non-numeric bedroom count)? Invalid parameters are ignored or safely clamped, and the system degrades to the nearest valid result set rather than erroring.
- What happens when two authorized staff members (e.g., an agent and an administrator) update the same inquiry's status at nearly the same time? The system preserves a consistent final state and does not corrupt or duplicate the record.
- What happens when a property has no gallery images at all? A defined placeholder/empty state is shown instead of a broken image.

## Requirements *(mandatory)*

### Functional Requirements — Property Catalog & Publishing

- **FR-001**: System MUST allow authorized staff (agents, property managers, administrators) to create, edit, and publish property listings with: title, description, short description, a system-generated property ID/reference number, featured image, image gallery, and status.
- **FR-002**: System MUST support pricing data per property: price, price type, currency, previous price, and a negotiable flag.
- **FR-003**: System MUST support property detail attributes: property type, listing type, bedrooms, bathrooms, living rooms, parking spaces, property area, land area, year built, floor, and total floors.
- **FR-004**: System MUST support structured location data per property: country, state/province, city, area/neighborhood, address, postal code, latitude, and longitude.
- **FR-005**: System MUST support a defined set of property features/amenities per listing (e.g., furnished, balcony, garden, garage, swimming pool, security, elevator, air conditioning, heating, solar, generator, parking).
- **FR-006**: System MUST classify every property using: property type (e.g., apartment, house, villa, office, shop, warehouse, land, farmhouse, penthouse, commercial building), listing type (for sale, for rent, for lease), and property status (available, pending, sold, rented, off market).
- **FR-007**: System MUST support a hierarchical location classification (country → state/province → city → area) usable for filtering and SEO-friendly navigation.
- **FR-008**: System MUST support a defined, reusable set of amenity classifications shared across properties (e.g., swimming pool, gym, parking, security, elevator, garden, balcony, central air, backup generator, solar).
- **FR-009**: System MUST support multiple images per property (featured image plus gallery), with thumbnails, a fullscreen/lightbox viewing mode, mobile swipe support, and lazy loading so full-resolution images are not loaded until needed.

### Functional Requirements — Search, Filtering & Discovery

- **FR-010**: System MUST provide a prominent property search interface on the homepage supporting at minimum a Buy/Rent mode selection.
- **FR-011**: System MUST allow searching/filtering by: location, property type, minimum price, maximum price, bedrooms, bathrooms, minimum area, maximum area, amenities, and property status.
- **FR-012**: System MUST support sorting results by: newest, price low→high, price high→low, largest area, and most relevant.
- **FR-013**: System MUST reflect active filters and sort order in a shareable, copyable URL so a filtered result set can be reproduced by opening that URL directly.
- **FR-014**: System MUST update filtered results without requiring a full page reload, while preserving SEO-friendly, crawlable URLs for the same filtered views.
- **FR-015**: System MUST provide a map-based discovery view showing property locations, where selecting a marker reveals a preview (image, name, price, location, call-to-action) linking to the full listing.
- **FR-016**: System MUST provide a complete, fully-functional non-map alternative for browsing the same properties, for use when the map is unavailable or inaccessible.
- **FR-017**: System MUST show a clear, actionable empty state when a search/filter combination returns no results.

### Functional Requirements — Property Detail, Favorites & Comparison

- **FR-018**: System MUST render, on every published property's detail page: gallery, title, location, price, listing status, bedrooms, bathrooms, area, parking, description, key features, amenities, full property details, location, assigned agent, contact-agent action, request-viewing action, save/favorite action, compare action, and related properties.
- **FR-019**: System MUST allow any visitor (guest or logged in) to save and remove a property as a favorite.
- **FR-020**: System MUST persist favorites for logged-in users against their account, and persist favorites for guest users using a browser-local mechanism that does not require account creation.
- **FR-021**: System MUST allow logged-in users to view a consolidated list of their saved favorite properties.
- **FR-022**: System MUST allow users to select multiple properties for side-by-side comparison, enforcing a configured maximum (3–4 properties).
- **FR-023**: Comparison view MUST present, at minimum: price, bedrooms, bathrooms, area, property type, location, parking, amenities, and status — and MUST remain usable on mobile screen widths.
- **FR-024**: System MUST select and display related properties on a detail page using signals such as same city, same property type, similar price, and similar bedroom count, without incurring an expensive, unbounded query per page view.

### Functional Requirements — Inquiries & Viewing Requests

- **FR-025**: System MUST allow any visitor to submit a property inquiry containing name, email, phone, associated property, message, and preferred contact method, with optional preferred viewing date and budget.
- **FR-026**: System MUST validate and sanitize all inquiry input server-side before storing it, rejecting invalid/incomplete submissions with clear, field-level feedback.
- **FR-027**: System MUST persist every inquiry (never email-only/discard) with: name, email, phone, associated property, assigned agent, message, submission date, and a status from {New, Contacted, Qualified, Viewing Scheduled, Closed, Spam}.
- **FR-028**: System MUST notify the assigned agent (and administrator, where configured) when a new inquiry is submitted, without exposing unnecessary sensitive information in the notification.
- **FR-029**: System MUST allow authorized staff to view, filter, change status of, assign, and add internal notes to inquiries, restricted so agents see only inquiries they are authorized to access.
- **FR-030**: System MUST allow any visitor to request a property viewing, submitting name, email, phone, associated property, preferred date, preferred time, and an optional message.
- **FR-031**: System MUST validate viewing-request submissions (e.g., reject past dates and incomplete required fields) before storing them.
- **FR-032**: System MUST track each viewing request through a status of {Requested, Confirmed, Rescheduled, Completed, Cancelled}, and MUST NOT imply guaranteed availability at the moment of submission.
- **FR-033**: System MUST notify the assigned agent when a new viewing request is submitted.

### Functional Requirements — Accounts, Roles & Permissions

- **FR-034**: System MUST support distinct account roles at minimum: Administrator, Editor, Agent, Property Manager, and Registered User, each with a distinct, least-privilege set of capabilities.
- **FR-035**: System MUST NOT grant agents administrator-level privileges automatically; agent capabilities MUST be explicitly scoped (e.g., manage own properties, edit own properties, publish own properties, delete own properties, view own assigned inquiries, manage own agent profile).
- **FR-036**: System MUST restrict system-wide settings management to Administrators only.
- **FR-037**: System MUST prevent any user from editing, publishing, or deleting a property they do not own or are not otherwise authorized to manage.
- **FR-038**: System MUST prevent any staff member from viewing or modifying an inquiry or viewing request they are not authorized to access.
- **FR-039**: System MUST maintain a public agent profile for every agent, including: name, profile image, biography, phone, email, a WhatsApp/alternate contact option, license number, office, areas served, specializations, and social links.
- **FR-040**: Agent profile pages MUST present, at minimum, an About section, Contact options, and a list of the agent's active Properties.

### Functional Requirements — Property Owner Submission

- **FR-041**: System MUST allow a logged-in registered user to submit a new property listing via a front-end submission form (title, description, price, listing type, property type, bedrooms, bathrooms, area, location, address, amenities, images, contact details).
- **FR-042**: Every user-submitted property MUST enter a pending-review state and MUST NOT be automatically published or appear in public search/browse results.
- **FR-043**: System MUST allow an administrator or authorized editor to approve or reject a pending submission, and MUST notify the submitter of the outcome.
- **FR-044**: System MUST reject property-submission file uploads that fail authentication, file-type validation, MIME-type validation, or file-size limits, and MUST prevent executable file uploads.
- **FR-045**: System MUST require authentication and a valid anti-forgery token (nonce/equivalent) for every property submission request, and MUST enforce capability checks server-side (never trusting client-supplied role/permission claims).
- **FR-061**: Properties created by an Agent (or Administrator/Property Manager) directly through the Agent Dashboard (FR-047), as distinct from the public "Submit Property" form, MUST publish immediately without an additional moderation step, provided the creating account holds a publish capability for properties; the pending-review requirement in FR-042 applies only to submissions made through the public "Submit Property" form.

### Functional Requirements — Dashboards

- **FR-046**: System MUST provide a registered-user dashboard containing: Favorites, Comparisons, Inquiries, Viewing Requests, and Account Settings, scoped strictly to that user's own data.
- **FR-047**: System MUST provide an agent dashboard containing: the agent's own properties, an Add Property action, per-property status management, the agent's assigned inquiries, assigned viewing requests, and profile management — scoped strictly to that agent's own authorized data.
- **FR-048**: System MUST provide an administrator overview showing: total properties, active/sold/rented/pending-approval property counts, agent count, user count, new-inquiry count, viewing-request count, and recent properties/inquiries/submissions.
- **FR-049**: System MUST NOT serve personalized dashboard content (favorites, inquiries, agent/user dashboards) from a shared or public cache.

### Functional Requirements — Content & SEO

- **FR-050**: System MUST provide an editorial content section ("Insights") with categories including Buying Guide, Selling Guide, Investment, Market Insights, Interior Design, and Neighborhood Guides.
- **FR-051**: Every article MUST support a featured image, author, publish date, category, and optionally related properties.
- **FR-052**: System MUST produce human-readable, SEO-friendly, location-aware URLs for property and location pages (e.g., a hierarchical `/properties/{city}/{area}/` style pattern) and MUST avoid creating duplicate, independently-indexable URLs for the same content.
- **FR-053**: System MUST provide correct semantic heading hierarchy, canonical URLs, breadcrumbs, Open Graph metadata, and structured data appropriate to properties, the organization, and articles.
- **FR-054**: System MUST surface friendly, non-technical error messaging for at least: property not found, no properties found, invalid search, unauthorized request, inquiry submission failure, viewing-request failure, image upload failure, and login failure — and MUST NOT expose internal technical details (stack traces, internal errors, credentials, file paths) to end users.

### Functional Requirements — Trust, Safety & Compliance

- **FR-055**: System MUST validate and sanitize all externally-supplied input (URL parameters, form submissions, uploaded files, and any programmatic request) before use, and MUST escape all output rendered to users.
- **FR-056**: System MUST apply anti-spam protection to all public-facing forms (inquiry, viewing request, property submission): at minimum a honeypot check on every submission, plus a combined rate limit of 3 submissions per visitor per rolling hour across those three form types, with CAPTCHA available as a configurable additional escalation where warranted.
- **FR-057**: System MUST make analytics/tracking integrations (e.g., web analytics, tag manager, ad pixels) configurable rather than hardcoded, and MUST respect applicable user-privacy expectations.
- **FR-058**: System MUST make any map provider integration configurable, MUST NOT hardcode provider credentials, and MUST degrade gracefully (see FR-016) when a provider/API key is unavailable.
- **FR-059**: Demo/seed content (properties, agents, users) MUST be clearly fictional and MUST NOT use real individuals' personal information.
- **FR-060**: System MUST support a repeatable seed/reset process capable of regenerating a full demo dataset (properties, agents, taxonomies, amenities, demo users, example inquiries) without manual, one-by-one data entry.

### Assumptions

Five previously-open decisions were confirmed via `/sp.clarify` on 2026-09-24 (see Clarifications above) and are no longer treated as assumptions: platform tenancy model, agent-created-property publishing rule, agent reviews exclusion, anti-spam rate-limit threshold, and concurrent-visitor traffic scale.

The following remaining defaults were assumed where the source specification allowed reasonable industry-standard interpretation. These should be revisited during `/sp.plan` or a future `/sp.clarify` pass if incorrect:

- **Authentication model**: Standard account-based authentication (email/password with session-based login) is sufficient for MVP; no social/SSO login is assumed required unless later specified.
- **Guest favorites**: Guest (non-logged-in) favorites are stored via browser-local storage/cookies tied to that browser only; they are not portable across devices unless the guest registers/logs in.
- **Monetization**: No payment processing, paid listings, or subscription billing is in scope for this specification — the source document does not describe any monetization mechanism. All listing and inquiry actions are free.
- **Localization**: A single active language (English) is required at launch; the platform must be technically translation-ready but multiple simultaneous live languages are not required for MVP.
- **Demo data volume**: At least 50 property listings (distributed roughly: 20 apartments, 10 houses, 8 villas, 5 commercial, 4 land, 3 luxury) and at least 8 agents are required in the seeded demo dataset, spanning multiple cities, prices, statuses, and listing types.
- **Comparison limit**: The maximum number of properties in a single comparison is 4.

### Key Entities *(data involved)*

- **Property**: A single real-estate listing. Holds identity (title, description, short description, reference number), pricing (price, price type, currency, previous price, negotiable flag), physical details (type, listing type, bedrooms, bathrooms, living rooms, parking, area, land area, year built, floor, total floors), location (country, state/province, city, area, address, postal code, latitude/longitude), features/amenities, media (featured image + gallery), status, and relationships to an assigned Agent and, where applicable, a submitting Registered User.
- **Agent**: A staff account representing a real-estate professional. Holds profile data (name, image, biography, phone, email, alternate contact, license number, office, areas served, specializations, social links), a role-based capability set, and relationships to owned Properties and assigned Inquiries/Viewing Requests.
- **Registered User**: A visitor account able to save Favorites, build Comparisons, submit Inquiries/Viewing Requests/Property Submissions, and manage personal account settings. May optionally submit properties as an owner (pending review).
- **Inquiry**: A message from a visitor about a specific Property, addressed to an Agent/agency. Holds contact details, message, preferred contact method, optional preferred viewing date/budget, submission date, status, assignment, and internal notes.
- **Viewing Request**: A request from a visitor to view a specific Property in person/virtually. Holds contact details, preferred date/time, optional message, submission date, and status.
- **Favorite**: An association between a visitor (registered user or guest browser) and a Property they have saved.
- **Comparison Set**: A transient, user-scoped selection of up to 4 Properties chosen for side-by-side comparison.
- **Taxonomy Term (Property Type / Listing Type / Property Status / Location / Amenity)**: Reusable classification values used to categorize and filter Properties; Location is hierarchical (country → state/province → city → area).
- **Article (Insight)**: An editorial content piece with a category, author, featured image, publish date, and optional related Properties.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A visitor can go from landing on the homepage to viewing a relevant, filtered list of matching properties in under 30 seconds.
- **SC-002**: Filtered/sorted search results update without a full page reload and complete within 2 seconds under normal conditions.
- **SC-003**: A visitor can submit a complete property inquiry in under 2 minutes from opening the property detail page.
- **SC-004**: 100% of submitted inquiries and viewing requests are durably stored and appear in the correct staff-facing queue — zero submissions are lost or delivered by notification alone.
- **SC-005**: A user can save or remove a favorite in a single interaction, with visible confirmation, in under 1 second of perceived response time.
- **SC-006**: A side-by-side comparison of up to 4 properties renders correctly with no horizontal overflow or broken layout at a 390px mobile viewport width.
- **SC-007**: An authorized agent can create and publish a new property listing, from login to live (where their role permits direct publishing), in under 5 minutes.
- **SC-008**: 100% of publicly-submitted (non-agent) property listings are held in a pending state and require an explicit administrator/editor approval action before becoming publicly visible.
- **SC-009**: The platform sustains a seeded catalog of at least 50 properties and 8 agents, under a load of up to ~50 concurrent visitors (the target scale for this portfolio/production-grade demonstration), with no perceptible degradation in browse, search, or filter response time.
- **SC-010**: Core visitor-facing pages (homepage, property archive, property detail) meet "good" Core Web Vitals thresholds when tested on a representative mobile network condition.
- **SC-011**: Every primary user flow (search, view detail, favorite, compare, inquire, request viewing, submit property, dashboards) is fully operable using keyboard-only navigation and meets WCAG 2.2 AA contrast and labeling expectations.
- **SC-012**: Security testing finds zero cases where a user can view, edit, or delete data (properties, inquiries, viewing requests, other users' favorites) outside their authorized scope.
- **SC-013**: A shared/copied filtered-search URL reproduces the identical result set 100% of the time when opened in a fresh session.
- **SC-014**: Zero pages presenting personalized data (dashboards, favorites, agent/admin views) are ever served from a shared/public cache during testing.
