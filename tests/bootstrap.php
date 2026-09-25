<?php
/**
 * PHPUnit bootstrap — loads the wp-phpunit test scaffolding, then the
 * primeestate-core plugin (and switches to the primeestate theme) before
 * WordPress's own test bootstrap runs, per the standard wp-phpunit pattern
 * documented in quickstart.md.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once $_tests_dir . '/includes/functions.php';

function primeestate_manually_load_plugin_and_theme(): void {
	require dirname( __DIR__ ) . '/wp-content/plugins/primeestate-core/primeestate-core.php';
	switch_theme( 'primeestate' );
}
tests_add_filter( 'muplugins_loaded', 'primeestate_manually_load_plugin_and_theme' );

/**
 * `primeestate_core_activate()` (primeestate-core.php) only ever runs via
 * `register_activation_hook()`, which fires exclusively through WordPress's
 * real "activate a plugin" code path (wp-admin or `wp plugin activate`) —
 * never merely from `require`ing the plugin file, which is all
 * `primeestate_manually_load_plugin_and_theme()` above does. Left
 * unaddressed, this means role/capability registration
 * (agent-permissions.php), taxonomy term seeding (property_status,
 * listing_type, …), and default-page creation (default-pages.php) would
 * silently never run in the PHPUnit environment at all — every test that
 * assumes the `agent`/`property_manager` roles exist with their mapped
 * capabilities (test-agent-permissions.php et al.) or that
 * `property_status` terms already exist (test-admin-overview.php) would
 * fail not because of a bug in the code under test, but because the
 * fixture it depends on was never built. Hooking activation to `init` at a
 * late priority — after CPT/taxonomy registration, which use the default
 * priority — is the standard technique for this exact situation (also used
 * by WP-CLI's own `wp scaffold plugin-tests` bootstrap template), and runs
 * once for the whole suite, matching how a real one-time activation
 * behaves.
 */
tests_add_filter( 'init', 'primeestate_core_activate', 20 );

require $_tests_dir . '/includes/bootstrap.php';
