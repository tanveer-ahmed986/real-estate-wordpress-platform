# PrimeEstate — REST API

The authoritative contract — full request/response schemas and error codes — is `specs/001-real-estate-platform/contracts/primeestate-api.openapi.yaml`. This page is a human-readable map of what exists and how auth/permissions work in practice.

## Namespace

`primeestate/v1` — every endpoint below is relative to `/wp-json/primeestate/v1`.

## Authentication

Standard WordPress REST authentication: a logged-in browser session's cookie + nonce (`X-WP-Nonce`, supplied to front-end JS via `wp_localize_script` — see `theme/inc/enqueue.php`), or an Application Password for programmatic access. Endpoints with no auth requirement below are intentionally public (guest browsing, guest inquiry/viewing submission — the platform never requires an account to contact an agent).

wp-admin surfaces that reuse these endpoints (the Inquiries/Viewing Requests admin pages, the Moderation Queue) call them in-process via `rest_do_request()` rather than a real HTTP round-trip — that path runs the same `permission_callback` as a normal API call, but is protected by wp-admin's own nonce (`check_admin_referer()`) instead of `X-WP-Nonce`, since there is no HTTP header to check in an in-process dispatch.

## Endpoints

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/properties` | Search/filter/sort properties (FR-011–013) | Public |
| POST | `/properties` | Submit a new listing (FR-041, FR-045) | Logged-in; agents publish immediately, everyone else goes to moderation (FR-061) |
| GET | `/properties/{id}/related` | Related properties for a detail page (FR-024) | Public |
| GET | `/properties/compare` | Fresh data for a client-held comparison set (FR-022/023) | Public |
| PATCH | `/properties/{id}/moderate` | Approve/reject a pending submission (FR-043) | `moderate_properties` capability |
| GET | `/agents/{id}` / PATCH | Public agent profile / update own profile (FR-034–040) | PATCH: the agent themself |
| GET | `/favorites` | List the current user's favorites (FR-021) | Logged-in |
| POST | `/favorites` | Toggle a favorite on/off (FR-019/020) | Logged-in |
| POST | `/favorites/merge` | Merge guest (localStorage) favorites into the account on login | Logged-in |
| POST | `/inquiries` | Submit a property inquiry (FR-025–027) | Public (rate-limited, FR-056) |
| GET | `/inquiries` | List inquiries visible to the caller (FR-029) | `view_inquiries`, scoped to own if not staff-wide |
| PATCH | `/inquiries/{id}` | Update status/assignment/notes (FR-029) | `view_inquiries` |
| POST | `/viewings` | Request a viewing (FR-030/031) | Public (rate-limited, FR-056) |
| PATCH | `/viewings/{id}` | Update viewing request status (FR-032) | `view_inquiries` |
| POST | `/uploads` | Upload a property image, returns an attachment ID (FR-044/046) | Logged-in |

Note: **Account Settings** (self-service name/email/password) deliberately does not have a PrimeEstate-specific endpoint — it calls WordPress core's own `PATCH /wp/v2/users/me`, which already grants unconditional self-edit. Building a duplicate endpoint here would only duplicate validation core already does correctly.

## Errors

Standard `WP_Error`-shaped REST error responses (`code`, `message`, `data.status`). Rate-limit rejections (FR-056) and validation failures both use `4xx` with a `message` safe to show the end user directly — no endpoint returns a raw exception or stack trace.
