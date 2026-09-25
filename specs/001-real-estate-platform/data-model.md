# Phase 1 Data Model: PrimeEstate Platform

**Feature**: `001-real-estate-platform` | **Date**: 2026-09-24
**Input**: `spec.md` Key Entities & Functional Requirements, `research.md` decisions

All storage uses native WordPress constructs (Principle I / research.md §2): Custom Post Types, Post Meta, Taxonomies, and User/User Meta. No custom database tables.

---

## 1. Property (Custom Post Type: `property`)

Maps FR-001–FR-009, FR-018, FR-024.

| Field | Storage | Type | Validation / Notes |
|---|---|---|---|
| Title | `post_title` | string | Required, 1–200 chars |
| Description | `post_content` | rich text | Required, sanitized with `wp_kses_post` |
| Short description | postmeta `_pe_short_description` | string | Optional, ≤300 chars, escaped with `esc_html` on output |
| Reference number | postmeta `_pe_reference` | string | System-generated on publish (e.g., `PE-000123`), unique, immutable after creation |
| Featured image | `post_thumbnail` | attachment ID | Required before publish; falls back to a placeholder in templates if absent (edge case) |
| Gallery | postmeta `_pe_gallery` | array of attachment IDs | Registered via `register_post_meta` (type `array`), each ID validated as an existing image attachment owned by the site |
| Status (workflow) | `post_status` | enum | `pending` (awaiting moderation, FR-042) → `publish` (live) / `draft` (rejected, FR-043) |
| Price | postmeta `_pe_price` | float | Required, ≥ 0, registered numeric meta for `meta_query` range filtering (FR-011) |
| Price type | postmeta `_pe_price_type` | enum | `fixed` \| `starting_from` \| `on_request` |
| Currency | postmeta `_pe_currency` | string (ISO 4217) | Default from site setting; not multi-currency-converted (Assumptions) |
| Previous price | postmeta `_pe_previous_price` | float | Optional, for "price reduced" display |
| Negotiable | postmeta `_pe_negotiable` | boolean | Default `false` |
| Property type | taxonomy `property_type` | term | Required, single-select (apartment, house, villa, office, shop, warehouse, land, farmhouse, penthouse, commercial building) |
| Listing type | taxonomy `listing_type` | term | Required, single-select (for-sale, for-rent, for-lease) — drives Buy/Rent search mode (FR-010) |
| Property status (market) | taxonomy `property_status` | term | Required (available, pending, sold, rented, off-market) — distinct from `post_status` workflow above |
| Bedrooms / Bathrooms / Living rooms / Parking spaces | postmeta `_pe_bedrooms` etc. | integer | ≥ 0, registered numeric meta |
| Property area / Land area | postmeta `_pe_area`, `_pe_land_area` | float | ≥ 0, unit stored in a site-wide setting (sq ft / sq m) |
| Year built / Floor / Total floors | postmeta | integer | Optional |
| Location: country, state/province, city, area | taxonomy `location` (hierarchical) | term path | Required down to city at minimum; `area` optional leaf term — drives FR-007/FR-052 hierarchical URLs |
| Address, postal code | postmeta | string | Optional (privacy: exact address may be withheld until inquiry, a documented display option) |
| Latitude / Longitude | postmeta `_pe_lat`, `_pe_lng` | float | Required only if the property should appear on the map view; absence simply omits it from map markers (not an error) |
| Amenities/features | taxonomy `amenity` | terms (multi) | e.g., furnished, balcony, garden, garage, pool, security, elevator, AC, heating, solar, generator, parking |
| Assigned Agent | postmeta `_pe_agent_id` (or `post_author`) | user ID | Required; `post_author` reused as the assigned-agent link keeps WP-native ownership/capability checks working for free |
| Submitting user (owner submissions only) | postmeta `_pe_submitted_by` | user ID | Set only when created via the public "Submit Property" form (FR-041); absent for agent-authored listings |
| Demo flag | postmeta `_primeestate_demo` | boolean | Set by the seed command (research.md §8); used by `wp primeestate reset` |

**Workflow / state transitions** (`post_status`):

```
Agent-authored (FR-061):  draft → publish                (immediate, capability-gated)
Public submission (FR-041–043): draft/pending → publish   (admin/editor approval)
                                 draft/pending → draft/trash (admin/editor rejection, submitter notified)
publish → draft/trash                                      (unpublish; existing favorites/inquiries retained per edge cases)
```

`property_status` term (available/pending/sold/rented/off-market) is independent of `post_status` and is staff-editable at any time to reflect real-world sale/rental state without unpublishing the listing.

---

## 2. Taxonomies

| Taxonomy | Hierarchical? | Terms (seed) | Used by |
|---|---|---|---|
| `property_type` | No | Apartment, House, Villa, Office, Shop, Warehouse, Land, Farmhouse, Penthouse, Commercial Building | FR-006, FR-011 |
| `listing_type` | No | For Sale, For Rent, For Lease | FR-006, FR-010 |
| `property_status` | No | Available, Pending, Sold, Rented, Off Market | FR-006 |
| `location` | Yes | Country → State/Province → City → Area | FR-007, FR-052 |
| `amenity` | No | Swimming Pool, Gym, Parking, Security, Elevator, Garden, Balcony, Central Air, Backup Generator, Solar (extensible) | FR-008, FR-011 |
| `insight_category` | No | Buying Guide, Selling Guide, Investment, Market Insights, Interior Design, Neighborhood Guides | FR-050 |

---

## 3. Agent (WordPress User, role `agent`)

Maps FR-034–FR-040. No separate CPT (source doc §11 explicitly disallows one when WP users suffice).

| Field | Storage | Type | Validation / Notes |
|---|---|---|---|
| Name, email | `wp_users` core fields | string | Standard WP user validation |
| Profile image | usermeta (attachment ID) or Gravatar fallback | attachment ID | Optional |
| Biography | usermeta `_pe_agent_bio` | rich text | Sanitized `wp_kses_post` |
| Phone / WhatsApp | usermeta `_pe_agent_phone`, `_pe_agent_whatsapp` | string | Validated format, no country-code enforcement (assumption: free text, display-formatted) |
| License number | usermeta `_pe_agent_license` | string | Optional (jurisdiction-dependent) |
| Office | usermeta `_pe_agent_office` | string | Free-text grouping label only — **not** a separate Agency entity (Clarifications: single-platform tenancy) |
| Areas served | usermeta `_pe_agent_areas` | array of `location` term IDs | |
| Specializations | usermeta `_pe_agent_specializations` | array of strings | e.g., "Luxury", "Commercial", "First-time buyers" |
| Social links | usermeta `_pe_agent_socials` | array of `{platform, url}` | URLs validated with `esc_url_raw` |

**Capabilities** (custom capabilities added to the `agent` role, FR-035):

`manage_properties`, `edit_properties` (own only, enforced via `map_meta_cap`), `publish_properties` (grants FR-061 direct-publish), `delete_properties` (own only), `view_inquiries` (assigned only), `manage_agent_profile` (own only).

**Roles** (FR-034): `administrator` (WP core), `editor` (WP core, used for moderation per FR-043), `agent` (custom), `property_manager` (custom — same property capabilities as agent but platform-wide, not own-only, for FR-001's "authorized staff"), `subscriber`/"registered user" (WP core `subscriber`, used for Registered Users).

---

## 4. Registered User

Uses the WordPress core `subscriber` role plus PrimeEstate-specific usermeta:

| Field | Storage | Notes |
|---|---|---|
| Favorites | usermeta `_pe_favorites` | array of property post IDs (FR-020, FR-021) |
| Account settings | WP core user fields + usermeta as needed | FR-046 |

Guest (non-logged-in) favorites/comparison exist only client-side (`localStorage`) per research.md §4 — no server entity until merged on login.

---

## 5. Inquiry (Custom Post Type: `pe_inquiry`)

Maps FR-025–FR-029. A CPT (not a custom table) keeps this within Principle I while giving inquiries their own admin list, capability model, and meta box UI.

| Field | Storage | Type | Validation |
|---|---|---|---|
| Name, email, phone | postmeta | string | Required; email validated with `is_email` |
| Associated property | postmeta `_pe_property_id` | post ID | Required, must reference an existing `property` post |
| Assigned agent | postmeta `_pe_agent_id` | user ID | Defaults to the property's assigned agent; re-assignable by admin (FR-029) |
| Message | postmeta | text | Required, sanitized `sanitize_textarea_field` |
| Preferred contact method | postmeta | enum | phone \| email \| whatsapp |
| Preferred viewing date, budget | postmeta | date, float | Optional |
| Status | postmeta `_pe_status` (or `post_status` mapped) | enum | New → Contacted → Qualified → Viewing Scheduled → Closed; or → Spam at any point |
| Internal notes | postmeta `_pe_notes` | array of `{author, note, date}` | Staff-only, never exposed to the submitter |
| Submission date | `post_date` | datetime | Automatic |

**State transitions**: `New → {Contacted, Spam}`; `Contacted → {Qualified, Closed, Spam}`; `Qualified → {Viewing Scheduled, Closed}`; `Viewing Scheduled → Closed`. Any state → `Spam` is always permitted (staff override). Transitions are staff-only (FR-029); the submitter cannot change status.

---

## 6. Viewing Request (Custom Post Type: `pe_viewing_request`)

Maps FR-030–FR-033.

| Field | Storage | Type | Validation |
|---|---|---|---|
| Name, email, phone | postmeta | string | Required |
| Associated property | postmeta `_pe_property_id` | post ID | Required |
| Assigned agent | postmeta `_pe_agent_id` | user ID | Defaults to property's agent |
| Preferred date, preferred time | postmeta | date, time | Required; date MUST NOT be in the past (server-validated at submission, FR-031) |
| Message | postmeta | text | Optional |
| Status | postmeta `_pe_status` | enum | Requested → Confirmed → Completed; Requested/Confirmed → Rescheduled → Confirmed; any → Cancelled |
| Submission date | `post_date` | datetime | Automatic |

---

## 7. Favorite

Not a standalone entity — implemented as:
- **Logged-in**: an array of property post IDs in usermeta `_pe_favorites` (§4).
- **Guest**: an array of property post IDs in browser `localStorage`, merged into usermeta on login (research.md §4).

---

## 8. Comparison Set

Client-side only (no server persistence): an array (≤4) of property post IDs held in browser storage (`sessionStorage` or `localStorage`, same mechanism as guest favorites) for both guests and logged-in users. Not synced server-side — comparisons are a transient browsing aid, not account data requiring cross-device durability (Assumptions: comparison limit = 4).

---

## 9. Article / Insight (native WordPress `post`)

Uses the native `post` type plus the `insight_category` taxonomy (rather than a new CPT) so standard WordPress blog tooling (RSS, categories, authors, comments-off-by-default) works unmodified. Related properties: postmeta `_pe_related_properties` (array of property post IDs), manually curated by the author or auto-suggested by the same related-properties signals as FR-024.

---

## 10. Capability & Role Matrix Summary

| Capability | Administrator | Editor | Property Manager | Agent | Registered User |
|---|---|---|---|---|---|
| Manage system settings | ✅ | ❌ | ❌ | ❌ | ❌ |
| Approve/reject public submissions | ✅ | ✅ | ❌ | ❌ | ❌ |
| Publish own property directly | ✅ | ✅ | ✅ | ✅ | ❌ (always pending) |
| Edit/delete any property | ✅ | ✅ | ✅ | ❌ (own only) | ❌ |
| Edit/delete own property | ✅ | ✅ | ✅ | ✅ | ❌ |
| View all inquiries/viewings | ✅ | ✅ | ✅ | ❌ (assigned only) | ❌ |
| Submit property (pending review) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Save favorites / compare / inquire / request viewing | ✅ | ✅ | ✅ | ✅ | ✅ (+ guest, unauthenticated) |

This directly implements FR-034–FR-038 and the Clarifications' agent-direct-publish decision (FR-061).
