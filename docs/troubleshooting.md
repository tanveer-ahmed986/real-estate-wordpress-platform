# PrimeEstate — Troubleshooting

## A property/taxonomy-archive URL 404s after deployment or a fresh activation

Rewrite rules haven't flushed. Activation schedules a one-time flush on the next `init` (after CPT/taxonomy registration has run — flushing during the activation hook itself is too early, since `init` hasn't fired yet in that request). If it still 404s, re-save Permalinks (Settings → Permalinks) to force a flush.

## `taxonomy-{taxonomy}.html` / `archive-{post-type}.html` templates aren't being picked up

These are two different slots in WordPress's block-template hierarchy: `archive-{post-type}.html` matches a **post-type archive** (e.g. `archive-property.html`), `taxonomy-{taxonomy}.html` matches a **taxonomy term archive** (e.g. `taxonomy-insight_category.html`). A file named with the wrong prefix is simply never selected — WordPress falls back to a more generic template instead of erroring, so this fails silently. Double-check which of the two you actually need.

## A PHPUnit test fails on a missing role, capability, or seeded taxonomy term that "should" exist

Activation-only setup (`primeestate_core_activated` — roles/capabilities, default taxonomy terms) only fires through WordPress's real plugin-activation code path, never through a plain `require` of the plugin file. `tests/bootstrap.php` handles this by hooking `primeestate_core_activate` onto `init` via `tests_add_filter()` before the test suite's own bootstrap runs — if a new test file bypasses the shared bootstrap, or a fixture assumes activation ran without going through it, this is the first thing to check.

## Dashboard/Favorites/Compare pages appear stale under a page-cache plugin

These pages are marked uncacheable via `primeestate_mark_dashboard_pages_uncacheable()` (`includes/security/security.php`, hooked to `template_redirect`) for every account-specific page slug (`favorites`, `compare`, `agent-dashboard`, `agent-add-property`, `agent-profile-edit`, `user-dashboard`). If a new account-specific page is added later, its slug needs adding to `primeestate_dashboard_page_slugs()` or it will silently start being served from cache.

## A role promotion (e.g. subscriber → agent) leaves a stray capability behind

WordPress core's `WP_User::set_role()` only clears the *previous* role's capabilities — any capability granted directly to the user (via `add_cap()`, not through a role) survives untouched. `primeestate_enforce_role_promotion_capability_safeguard()` (`includes/agents/agent-permissions.php`, hooked to `set_user_role`) strips anything not in the new role's own capability set, scoped to promotions into `agent`/`property_manager`. If a different promotion path needs the same guarantee, extend that function's role check rather than adding a second mechanism.

## Demo content keeps reappearing after `wp primeestate reset`

`reset.php`'s cleanup only touches post types listed in its `$post_types` array. If a new seed function starts creating a new post type (as the Insight-article seeding did for `post` in Phase 11), that type has to be added to `reset.php` explicitly — it is not automatic.

## No PHP/Node runtime in this development environment

This project was built without a running PHP/MySQL/Node stack available to the implementing agent. Every phase's verification was therefore limited to static checks: brace/parenthesis balance on edited PHP files, and a grep for raw `<?php` inside block templates (which must be pure block markup, never PHP). New PHPUnit tests were written but not executed. Before relying on this code in production, run the full `composer test` / `npx playwright test` suite and the manual QA pass in `quickstart.md` against a real environment — see `specs/001-real-estate-platform/tasks.md` Phase 12, T105–T109.
