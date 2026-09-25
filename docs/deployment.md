# PrimeEstate — Deployment

## Environment Requirements

- WordPress 6.5+, PHP 8.1+, MySQL/MariaDB
- HTTPS (required — inquiry/viewing/property-submission forms carry personal data and file uploads)
- A transactional email path capable of sending from the site's own domain (see below) — WordPress's default `wp_mail()` via the server's local MTA is rarely deliverable from a real domain; a dedicated SMTP/transactional provider (e.g. an SMTP plugin, or an API-based provider) is expected in production
- No custom database tables (constitution Principle I) — a standard WordPress backup/restore of the database covers this plugin's entire data model

## Release Process

1. Deploy the `primeestate-core` plugin and `primeestate` theme files to the target environment.
2. Activate the plugin (`wp plugin activate primeestate-core`) and the theme (`wp theme activate primeestate`) — activation is idempotent and safe to re-run; it only creates what doesn't already exist (roles, default Pages, default taxonomy terms).
3. Confirm rewrite rules flushed correctly: visit a property detail page and a `location` taxonomy archive URL directly; a 404 on either indicates a rewrite-flush issue, not a content issue — re-save Permalinks (Settings → Permalinks) to force a flush.
4. Configure Settings → PrimeEstate (analytics IDs, map provider) — see `admin-guide.md`. Nothing here has a default credential; every field ships empty (FR-057).
5. Do **not** run `wp primeestate seed` against production — it is a development/demo tool only. If a production install was seeded during setup/staging, run `wp primeestate reset` before going live.

## Rollback

Because there is no custom database schema, rollback is standard WordPress rollback: restore the previous plugin/theme file versions and, if a release included a data-affecting change (a new postmeta key, a taxonomy change), restore the database backup taken immediately before deployment. There is no plugin-specific migration state to reconcile.

## Transactional Email Deliverability

Every inquiry, viewing request, and property-submission status change sends a notification email (`includes/inquiries/`, `includes/viewing/`, `includes/post-types/property.php`). Per the constitution's Production Readiness & Operations principle, deliverability must be **verified**, not just assumed, before a production launch:

1. Configure the production SMTP/transactional-email-provider credentials (via an SMTP plugin, or the provider's own WordPress integration) — never hardcode credentials in this codebase; they belong in the hosting environment's own configuration.
2. Publish SPF, DKIM, and DMARC DNS records for the sending domain, matching whatever provider is sending on the domain's behalf. Most transactional providers generate the exact record values to publish as part of their own setup flow.
3. Send one real notification of each type (a test inquiry, a test viewing request, a test property submission requiring moderation) to a real inbox at a major provider (Gmail and Outlook/Microsoft 365 cover the large majority of real-world deliverability failure modes) and confirm inbox — not spam-folder — placement.
4. Re-check placement after any change to the sending domain, the transactional provider, or the "from" address.

**Status of this task in the current environment**: steps 1–4 require a real production SMTP/DNS configuration and a live target inbox, neither of which exists in this development environment (no PHP/mail runtime is available here — see the project-wide no-runtime caveat in every Phase PHR under `history/prompts/001-real-estate-platform/`). This section documents the required procedure; the verification itself is a pre-launch operational step to be performed against the actual production environment, not something this codebase can self-certify.
