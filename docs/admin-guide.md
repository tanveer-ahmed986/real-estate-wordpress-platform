# PrimeEstate — Administrator Guide

Administrators and Property Managers (the `moderate_properties` / `view_inquiries` capabilities) see extra menu items under **Properties** in wp-admin, plus a **PrimeEstate Overview** widget on the main Dashboard.

## Moderating Property Submissions

Properties submitted by non-agent registered users (Story US6) publish as `pending`, not live, until reviewed.

1. **Properties → Moderation Queue** lists every pending submission with who submitted it and when.
2. **Approve** publishes the listing immediately (it becomes publicly searchable). **Reject** asks for confirmation, then leaves the post unpublished.
3. Both actions go through the same `primeestate_moderate_property()` function the REST API's `PATCH /properties/{id}/moderate` endpoint uses — wp-admin and the API can never disagree about what "approve" means.

Properties submitted by **agents** publish immediately and never appear in this queue (FR-061) — the queue is specifically for the public-submission path.

## Managing Inquiries & Viewing Requests

**Properties → Inquiries** and **Properties → Viewing Requests** list every submission, with a quick status filter.

- Administrators, Property Managers, and Editors see every inquiry/viewing request and can change status, reassign the agent, and add an internal note (inquiries only — viewing requests have no notes field).
- Agents see only inquiries/viewing requests assigned to them, and (for inquiries) can update status and add notes on their own, but cannot reassign to another agent.
- Every save here dispatches through the same `/inquiries/{id}` / `/viewings/{id}` REST endpoints the Agent Dashboard's own UI uses (via `rest_do_request()`), so status-transition rules (e.g. inquiry status can't skip from `New` directly to `Closed`) are enforced identically no matter which UI made the change.

The **PrimeEstate Overview** dashboard widget (main wp-admin Dashboard) summarizes property counts by market status, pending-approval count, agent/registered-user counts, new-inquiry count, and viewing-request count, each linking to the relevant filtered list.

## Managing Agents

Agent accounts are standard WordPress users with the `agent` role — create or promote one from **Users** like any other role change. Promoting a user to `agent` or `property_manager` also strips any capability that user held individually outside their previous role, so a promoted user ends up with exactly the new role's capability set, never a leftover mix (see `includes/agents/agent-permissions.php`).

Each agent manages their own public profile (bio, phone, areas served, photo) from the front-end **Agent Dashboard → Edit Profile** — there is no admin-side profile editor; editing an agent's public-facing profile fields from wp-admin is intentionally not supported, to keep exactly one place that logic lives.

## Site Settings

**Settings → PrimeEstate** configures:

- **Analytics & Tracking** — optional GA4 Measurement ID, Google Tag Manager Container ID, and/or Meta Pixel ID. Leaving a field blank means that provider's snippet is never output — nothing is enabled by default.
- **Map Provider** — defaults to OpenStreetMap via Leaflet, which needs no API key. Selecting Google Maps or Mapbox stores the provider + API key for a future integration; only the Leaflet renderer is implemented today (research.md's documented decision), so the map itself does not switch providers yet.

A **Privacy Policy** page is seeded automatically and registered as the site's official privacy policy page — review and adjust its wording (Pages → Privacy Policy) if the production deployment adds analytics, a different email provider, or another third party beyond what's described in `docs/deployment.md` § Transactional Email Deliverability.
