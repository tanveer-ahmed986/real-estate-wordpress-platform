# PrimeEstate — Agent Guide

Everything in this guide happens from the front end — the **Agent Dashboard** (`/agent-dashboard/`) and its linked pages — not from wp-admin. Access requires the `agent` or `property_manager` role.

## Adding a Property

From **Agent Dashboard → Add Property**, fill in the listing details (price, type, listing type, bedrooms/bathrooms/area, address, amenities, gallery images) and submit.

- A property you submit **publishes immediately** — agents are trusted to list directly, unlike the public "Submit a Property" path (Story US6), which always goes to moderation first.
- Uploading an image goes through `POST /uploads`, which returns an attachment ID to attach to the listing — the same endpoint the public submission form uses, with the same file-type/size validation.

## Managing Your Listings

**Agent Dashboard** lists every property you own, with quick links to edit or unpublish. You can only edit or moderate your own listings — attempting to act on another agent's property is rejected by the same capability check the REST API enforces, whether the attempt comes from the dashboard UI or a direct API call.

## Handling Inquiries & Viewing Requests

Inquiries and viewing requests for your properties appear in your **Agent Dashboard**, most recent first.

- Update an inquiry's status (`New → Contacted → Qualified → Viewing Scheduled → Closed`, or `→ Spam` at any point) and add a note directly from the dashboard.
- Viewing requests carry a preferred date/time; confirm or update their status the same way.
- You only ever see inquiries/viewing requests assigned to you — an administrator or property manager can reassign one to you or another agent from wp-admin (see `admin-guide.md`).

## Your Public Profile

**Agent Dashboard → Edit Profile** controls what appears on your public agent page: bio, phone number, profile photo, and the `location` areas you serve. This is the only place that profile is editable — there is no wp-admin equivalent by design, so there's exactly one source of truth for it.
